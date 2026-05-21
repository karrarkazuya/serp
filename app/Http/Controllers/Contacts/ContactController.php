<?php

namespace App\Http\Controllers\Contacts;

use App\Http\Controllers\Controller;
use App\Http\Requests\Contacts\StoreContactRequest;
use App\Http\Requests\Contacts\UpdateContactRequest;
use App\Models\Contacts\Contact;
use App\Services\Company\CompanyContextService;
use App\Services\Contacts\ContactService;
use App\Helpers\SearchFilters;
use App\Helpers\SortsTable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ContactController extends Controller
{
    public function __construct(
        private readonly ContactService $contactService,
        private readonly CompanyContextService $companyContext,
    ) {}

    public function read(Request $request)
    {
        $this->authorize('viewAny', Contact::class);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        $query = Contact::query()->with(['creator', 'company', 'tags']);

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

        $contact->load(['company', 'tags', 'children.tags', 'parent', 'creator', 'updater']);

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
        unset($data['tags'], $data['related_contacts']);

        if ($request->hasFile('avatar')) {
            $data['avatar'] = $request->file('avatar')->store('avatars/contacts', 'local');
        }

        try {
            $contact = DB::transaction(function () use ($data, $tagIds, $relatedContactIds) {
                $contact = $this->contactService->create($data);
                $contact->tags()->sync($tagIds);
                $this->syncRelatedContacts($contact, $relatedContactIds);

                return $contact;
            });
        } catch (\Throwable $e) {
            if (isset($data['avatar'])) {
                Storage::disk('local')->delete($data['avatar']);
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

        $contact->load(['company', 'tags', 'children', 'parent']);
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
        unset($data['tags'], $data['related_contacts']);

        $oldAvatar = $contact->avatar;

        if ($request->hasFile('avatar')) {
            $data['avatar'] = $request->file('avatar')->store('avatars/contacts', 'local');
        }

        try {
            DB::transaction(function () use ($contact, $data, $tagIds, $relatedContactIds) {
                $this->contactService->update($contact, $data);
                $contact->tags()->sync($tagIds);
                $this->syncRelatedContacts($contact, $relatedContactIds);
            });
        } catch (\Throwable $e) {
            if (isset($data['avatar'])) {
                Storage::disk('local')->delete($data['avatar']);
            }
            throw $e;
        }

        if ($request->hasFile('avatar') && $oldAvatar) {
            Storage::disk('local')->delete($oldAvatar);
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

        $avatar = $contact->avatar;
        DB::transaction(fn () => $this->contactService->delete($contact));

        if ($avatar) {
            Storage::disk('local')->delete($avatar);
        }

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

    public function avatar(string $uuid): Response
    {
        $contact = Contact::where('uuid', $uuid)->first();

        if (
            !$contact ||
            !$contact->avatar ||
            !auth()->check() ||
            !auth()->user()->hasPermission('contacts.read') ||
            !Storage::disk('local')->exists($contact->avatar)
        ) {
            return $this->defaultAvatarResponse();
        }

        $path     = Storage::disk('local')->path($contact->avatar);
        $mime     = mime_content_type($path) ?: 'image/jpeg';

        return response(Storage::disk('local')->get($contact->avatar), 200, [
            'Content-Type'  => $mime,
            'Cache-Control' => 'private, max-age=3600',
        ]);
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
