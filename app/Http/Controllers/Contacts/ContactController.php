<?php

namespace App\Http\Controllers\Contacts;

use App\Http\Controllers\Controller;
use App\Http\Requests\Contacts\StoreContactRequest;
use App\Http\Requests\Contacts\UpdateContactRequest;
use App\Models\Contacts\Contact;
use App\Services\Company\CompanyContextService;
use App\Services\Contacts\ContactService;
use App\Services\FileService;
use App\Helpers\GroupsQuery;
use App\Helpers\SearchFilters;
use App\Helpers\SortsTable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ContactController extends Controller
{
    public function __construct(
        private readonly ContactService $contactService,
        private readonly CompanyContextService $companyContext,
        private readonly FileService $fileService,
    ) {}

    public function read(Request $request)
    {
        $this->authorize('viewAny', Contact::class);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        $query = Contact::query()->with(['creator', 'company', 'tags', 'phones']);

        if (!empty($activeCompanyIds)) {
            $query->forCompanies($activeCompanyIds);
        }

        SearchFilters::apply($query, $request);

        if ($request->query('filter') === 'archived') {
            $query->inactive();
        } elseif ($request->query('filter') === 'all') {
            // no filter
        } else {
            $query->active();
        }

        if ($type = $request->query('type')) {
            $query->where('contact_type', $type);
        }

        $view = $request->query('view', 'kanban');

        if ($view === 'list') {
            $groupBy = $request->query('group_by');
            if ($groupBy) {
                $fields = SearchFilters::fieldsFor(Contact::class);
                if (isset($fields[$groupBy])) {
                    $records = (clone $query)->with(['creator', 'company', 'tags', 'phones'])->orderBy('id')->get();
                    $groups  = GroupsQuery::apply($records, $fields[$groupBy]);
                    return view('contacts.index', compact('groups', 'view'));
                }
            }
        }

        SortsTable::apply($query, $request);

        $contacts = $query->paginate(24)->withQueryString();

        return view('contacts.index', compact('contacts', 'view'));
    }

    public function show(Contact $contact)
    {
        $this->authorize('view', $contact);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($contact->company_id, $activeCompanyIds), 403);

        $contact->load(['company', 'tags', 'phones', 'children.tags', 'parent', 'creator', 'updater']);

        $allIds = Contact::active()
            ->when(!empty($activeCompanyIds), fn($q) => $q->forCompanies($activeCompanyIds))
            ->orderBy('name')
            ->pluck('id');

        $currentIndex = $allIds->search($contact->id);
        $prevId = $currentIndex > 0 ? $allIds[$currentIndex - 1] : null;
        $nextId = $currentIndex !== false && $currentIndex < $allIds->count() - 1 ? $allIds[$currentIndex + 1] : null;
        $recordPosition = $currentIndex !== false ? $currentIndex + 1 : null;
        $recordTotal = $allIds->count();

        return view('contacts.show', compact(
            'contact', 'prevId', 'nextId', 'recordPosition', 'recordTotal'
        ));
    }

    public function create(Request $_request)
    {
        $this->authorize('create', Contact::class);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        $defaultCompanyId = count($activeCompanyIds) === 1 ? $activeCompanyIds[0] : null;

        return view('contacts.create', compact('defaultCompanyId'));
    }

    public function store(StoreContactRequest $request)
    {
        $data = $request->validated();
        $tagIds = $data['tags'] ?? [];
        $relatedContactIds = $data['related_contacts'] ?? [];
        $phones = $this->cleanPhones($data['phones'] ?? []);
        unset($data['tags'], $data['related_contacts'], $data['phones']);

        if ($conflict = $this->phoneInUse($phones, ignoreContactId: null)) {
            return back()->withInput()->with('error', "Phone number {$conflict} is already in use by another contact in this company.");
        }

        $fileRecord = null;
        if ($request->hasFile('avatar')) {
            $fileRecord      = $this->fileService->store($request->file('avatar'), 'avatars/contacts', 'contacts.read');
            $data['avatar']  = $fileRecord->uuid;
        }

        try {
            $contact = DB::transaction(function () use ($data, $tagIds, $relatedContactIds, $phones, $fileRecord) {
                $contact = $this->contactService->create($data);
                $contact->tags()->sync($tagIds);
                $this->syncRelatedContacts($contact, $relatedContactIds);
                $this->syncPhones($contact, $phones);
                $fileRecord?->update(['source_type' => $contact->getTable(), 'source_id' => $contact->id]);

                return $contact;
            });
        } catch (\Throwable $e) {
            if (isset($data['avatar'])) {
                $this->fileService->deleteByUuid($data['avatar']);
            }
            throw $e;
        }

        return redirect()
            ->route('contacts.show', $contact)
            ->with('success', 'Contact created successfully.');
    }

    public function edit(Contact $contact)
    {
        $this->authorize('update', $contact);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($contact->company_id, $activeCompanyIds), 403);

        $contact->load(['company', 'tags', 'phones', 'children', 'parent']);
        $relatedContactIds = $contact->children->pluck('id')->toArray();

        return view('contacts.edit', compact('contact', 'relatedContactIds'));
    }

    public function write(UpdateContactRequest $request, Contact $contact)
    {
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($contact->company_id, $activeCompanyIds), 403);

        $data = $request->validated();
        $tagIds = $data['tags'] ?? [];
        $relatedContactIds = $data['related_contacts'] ?? [];
        $phones = $this->cleanPhones($data['phones'] ?? []);
        unset($data['tags'], $data['related_contacts'], $data['phones']);

        // Cycle guard on the parent_id field — the form request only validates that
        // the parent exists and is in an active company, not that pointing at it
        // would form a loop with this contact's own descendants.
        if (array_key_exists('parent_id', $data) && $data['parent_id']) {
            $parentId = (int) $data['parent_id'];
            if ($parentId === $contact->id || $this->isDescendantOf($parentId, $contact->id)) {
                return back()->withInput()->with('error', 'Selected parent would create a circular contact hierarchy.');
            }
        }

        if ($conflict = $this->phoneInUse($phones, ignoreContactId: $contact->id)) {
            return back()->withInput()->with('error', "Phone number {$conflict} is already in use by another contact in this company.");
        }

        $oldAvatarUuid = $contact->avatar;

        if ($request->hasFile('avatar')) {
            $fileRecord     = $this->fileService->store($request->file('avatar'), 'avatars/contacts', 'contacts.read', null, $contact);
            $data['avatar'] = $fileRecord->uuid;
        }

        try {
            DB::transaction(function () use ($contact, $data, $tagIds, $relatedContactIds, $phones) {
                $this->contactService->update($contact, $data);
                $contact->tags()->sync($tagIds);
                $this->syncRelatedContacts($contact, $relatedContactIds);
                $this->syncPhones($contact, $phones);
            });
        } catch (\Throwable $e) {
            if (isset($data['avatar'])) {
                $this->fileService->deleteByUuid($data['avatar']);
            }
            throw $e;
        }

        if ($request->hasFile('avatar') && $oldAvatarUuid) {
            $this->fileService->deleteByUuid($oldAvatarUuid);
        }

        return redirect()
            ->route('contacts.show', $contact)
            ->with('success', 'Contact updated successfully.');
    }

    public function archive(Request $_request, Contact $contact)
    {
        $this->authorize('update', $contact);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($contact->company_id, $activeCompanyIds), 403);

        DB::transaction(fn () => $this->contactService->archive($contact));

        return redirect()->route('contacts.index')->with('success', 'Contact archived.');
    }

    public function unarchive(Request $_request, Contact $contact)
    {
        $this->authorize('update', $contact);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($contact->company_id, $activeCompanyIds), 403);

        DB::transaction(fn () => $this->contactService->unarchive($contact));

        return redirect()->route('contacts.show', $contact)->with('success', 'Contact restored.');
    }

    public function unlink(Request $_request, Contact $contact)
    {
        $this->authorize('delete', $contact);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($contact->company_id, $activeCompanyIds), 403);

        DB::transaction(fn () => $this->contactService->delete($contact));

        return redirect()->route('contacts.index')->with('success', 'Contact deleted.');
    }

    public function addComment(Request $request, Contact $contact)
    {
        $this->authorize('comment', $contact);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($contact->company_id, $activeCompanyIds), 403);

        $request->validate(['body' => 'required|string|max:5000']);
        DB::transaction(fn () => $contact->logComment($request->body));

        return back()->with('success', 'Comment added.');
    }

    private function cleanPhones(array $raw): array
    {
        return collect($raw)
            ->map('trim')
            ->filter(fn($p) => $p !== '')
            ->unique()
            ->values()
            ->all();
    }

    private function syncPhones(Contact $contact, array $phones): void
    {
        // Hard delete: phones are value-data attached to a contact, not auditable
        // records on their own. Soft-deleting them used to accumulate orphan rows
        // that then collided with phone re-use checks at the app layer.
        $contact->phones()->forceDelete();
        foreach ($phones as $phone) {
            $contact->phones()->create(['phone' => $phone]);
        }
    }

    /**
     * Returns the first phone that is already used by another contact within the
     * actor's active companies, or null if all phones are available. Only counts
     * live (non-soft-deleted) phones attached to live contacts.
     */
    private function phoneInUse(array $phones, ?int $ignoreContactId): ?string
    {
        if (empty($phones)) {
            return null;
        }

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        if (empty($activeCompanyIds)) {
            return null;
        }

        $query = \App\Models\Contacts\ContactPhone::query()
            ->whereIn('phone', $phones)
            ->whereHas('contact', function ($q) use ($activeCompanyIds) {
                $q->whereIn('company_id', $activeCompanyIds);
            });

        if ($ignoreContactId !== null) {
            $query->where('contact_id', '!=', $ignoreContactId);
        }

        return $query->value('phone');
    }

    private function syncRelatedContacts(Contact $contact, array $relatedContactIds): void
    {
        $relatedContactIds = collect($relatedContactIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0 && $id !== $contact->id)
            ->unique()
            ->values()
            ->all();

        // Cycle guard: any candidate that is already an ancestor of $contact would
        // form a loop (A→B→A) once we set its parent_id to $contact->id. Walking the
        // parent chain upward from $contact captures every ancestor we must exclude.
        $ancestorIds = $this->ancestorIds($contact);

        $relatedContactIds = array_values(array_diff($relatedContactIds, $ancestorIds));

        Contact::where('parent_id', $contact->id)
            ->whereNotIn('id', $relatedContactIds)
            ->get()
            ->each(fn (Contact $child) => $child->update(['parent_id' => null]));

        if (!empty($relatedContactIds)) {
            Contact::whereIn('id', $relatedContactIds)
                ->get()
                ->each(fn (Contact $child) => $child->update(['parent_id' => $contact->id]));
        }
    }

    /**
     * Walk the parent_id chain upward and return every ancestor's id. Bounded loop
     * so an already-corrupted cycle in the data can't hang the request.
     *
     * @return array<int, int>
     */
    private function ancestorIds(Contact $contact): array
    {
        $ids = [$contact->id];
        $current = $contact;

        for ($i = 0; $i < 64; $i++) {
            $parentId = $current->parent_id;
            if (!$parentId || in_array($parentId, $ids, true)) {
                break;
            }
            $ids[] = $parentId;
            $current = Contact::find($parentId);
            if (!$current) {
                break;
            }
        }

        return $ids;
    }

    /**
     * Is $candidateId a descendant of $rootId? Walks parent_id upward from
     * $candidateId; if we hit $rootId, candidate is in $rootId's subtree.
     * Bounded so a corrupted cycle in the data can't hang the check.
     */
    private function isDescendantOf(int $candidateId, int $rootId): bool
    {
        $seen = [];
        $currentId = $candidateId;

        for ($i = 0; $i < 64; $i++) {
            if (in_array($currentId, $seen, true)) {
                return false;
            }
            $seen[] = $currentId;

            $parentId = Contact::where('id', $currentId)->value('parent_id');
            if (!$parentId) {
                return false;
            }
            if ((int) $parentId === $rootId) {
                return true;
            }
            $currentId = (int) $parentId;
        }

        return false;
    }
}
