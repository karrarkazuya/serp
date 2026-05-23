<?php

namespace App\Http\Controllers\Contacts;

use App\Http\Controllers\Controller;
use App\Http\Requests\Contacts\StoreContactRequest;
use App\Http\Requests\Contacts\UpdateContactRequest;
use App\Models\Contacts\Contact;
use App\Services\Company\CompanyContextService;
use App\Services\Contacts\ContactService;
use App\Services\FileService;
use App\Helpers\SearchFilters;
use App\Helpers\SortsTable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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

        SortsTable::apply($query, $request);

        $contacts = $query->paginate(24)->withQueryString();

        return view('contacts.index', compact('contacts'));
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

    /**
     * Redirect to the unified file route. Kept for backward compat with existing Blade views.
     * Falls back to an inline SVG placeholder when the contact has no avatar.
     */
    public function avatar(string $uuid): Response|\Illuminate\Http\RedirectResponse
    {
        $contact = Contact::where('uuid', $uuid)->first();

        if (!$contact || !$contact->avatar) {
            return $this->defaultAvatarResponse();
        }

        return redirect()->route('files.serve', $contact->avatar);
    }

    private function defaultAvatarResponse(): Response
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">'
             . '<rect width="100" height="100" fill="#e5e7eb"/>'
             . '<circle cx="50" cy="37" r="19" fill="#9ca3af"/>'
             . '<ellipse cx="50" cy="82" rx="30" ry="22" fill="#9ca3af"/>'
             . '</svg>';

        return response($svg, 200, [
            'Content-Type'  => 'image/svg+xml',
            'Cache-Control' => 'private, max-age=60',
        ]);
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
        $contact->phones()->delete();
        foreach ($phones as $phone) {
            $contact->phones()->create(['phone' => $phone]);
        }
    }

    private function syncRelatedContacts(Contact $contact, array $relatedContactIds): void
    {
        $relatedContactIds = collect($relatedContactIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0 && $id !== $contact->id)
            ->unique()
            ->values()
            ->all();

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
}
