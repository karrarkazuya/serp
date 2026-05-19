<?php

namespace App\Http\Controllers\Contacts;

use App\Http\Controllers\Controller;
use App\Http\Requests\Contacts\StoreContactRequest;
use App\Http\Requests\Contacts\UpdateContactRequest;
use App\Models\Contacts\Contact;
use App\Models\Contacts\Tag;
use App\Services\Company\CompanyContextService;
use App\Services\Contacts\ContactService;
use App\Helpers\SearchFilters;
use App\Helpers\SortsTable;
use Illuminate\Http\Request;
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

        $contact->load(['company', 'tags', 'children.tags', 'parent', 'creator', 'updater']);
        $messages = $contact->chatterMessages()->with('user')->latest()->get();

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
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
            'contact', 'messages', 'prevId', 'nextId', 'recordPosition', 'recordTotal'
        ));
    }

    public function create(Request $_request)
    {
        $this->authorize('create', Contact::class);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        $defaultCompanyId = count($activeCompanyIds) === 1 ? $activeCompanyIds[0] : null;
        $tags = Tag::orderBy('name')->get();
        $contacts = Contact::active()->orderBy('name')->get(['id', 'name']);

        return view('contacts.create', compact('defaultCompanyId', 'tags', 'contacts'));
    }

    public function store(StoreContactRequest $request)
    {
        $data = $request->validated();
        $tagIds = $data['tags'] ?? [];
        $relatedContactIds = $data['related_contacts'] ?? [];
        unset($data['tags']);
        unset($data['related_contacts']);

        if ($request->hasFile('avatar')) {
            $data['avatar'] = $request->file('avatar')->store('avatars/contacts', 'public');
        }

        $contact = DB::transaction(function () use ($data, $tagIds, $relatedContactIds) {
            $contact = $this->contactService->create($data);
            $contact->tags()->sync($tagIds);
            $this->syncRelatedContacts($contact, $relatedContactIds);

            return $contact;
        });

        return redirect()
            ->route('contacts.show', $contact)
            ->with('success', 'Contact created successfully.');
    }

    public function edit(Contact $contact)
    {
        $this->authorize('update', $contact);

        $contact->load(['company', 'tags', 'children', 'parent']);
        $tags = Tag::orderBy('name')->get();
        $contacts = Contact::active()->where('id', '!=', $contact->id)->orderBy('name')->get(['id', 'name']);
        $relatedContactIds = $contact->children->pluck('id')->toArray();

        return view('contacts.edit', compact('contact', 'tags', 'contacts', 'relatedContactIds'));
    }

    public function write(UpdateContactRequest $request, Contact $contact)
    {
        $data = $request->validated();
        $tagIds = $data['tags'] ?? [];
        $relatedContactIds = $data['related_contacts'] ?? [];
        unset($data['tags']);
        unset($data['related_contacts']);

        if ($request->hasFile('avatar')) {
            if ($contact->avatar) {
                Storage::disk('public')->delete($contact->avatar);
            }
            $data['avatar'] = $request->file('avatar')->store('avatars/contacts', 'public');
        }

        DB::transaction(function () use ($contact, $data, $tagIds, $relatedContactIds) {
            $this->contactService->update($contact, $data);
            $contact->tags()->sync($tagIds);
            $this->syncRelatedContacts($contact, $relatedContactIds);
        });

        return redirect()
            ->route('contacts.show', $contact)
            ->with('success', 'Contact updated successfully.');
    }

    public function archive(Request $_request, Contact $contact)
    {
        $this->authorize('update', $contact);
        DB::transaction(fn () => $this->contactService->archive($contact));

        return redirect()->route('contacts.index')->with('success', 'Contact archived.');
    }

    public function unarchive(Request $_request, Contact $contact)
    {
        $this->authorize('update', $contact);
        DB::transaction(fn () => $this->contactService->unarchive($contact));

        return redirect()->route('contacts.show', $contact)->with('success', 'Contact restored.');
    }

    public function unlink(Request $_request, Contact $contact)
    {
        $this->authorize('delete', $contact);

        if ($contact->avatar) {
            Storage::disk('public')->delete($contact->avatar);
        }

        DB::transaction(fn () => $this->contactService->delete($contact));

        return redirect()->route('contacts.index')->with('success', 'Contact deleted.');
    }

    public function addComment(Request $request, Contact $contact)
    {
        $this->authorize('comment', $contact);
        $request->validate(['body' => 'required|string|max:5000']);
        $contact->logComment($request->body);

        return back()->with('success', 'Comment added.');
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
