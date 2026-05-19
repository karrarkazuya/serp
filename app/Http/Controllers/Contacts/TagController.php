<?php

namespace App\Http\Controllers\Contacts;

use App\Http\Controllers\Controller;
use App\Models\Contacts\Tag;
use App\Helpers\SearchFilters;
use App\Helpers\SortsTable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TagController extends Controller
{
    public function read(Request $request)
    {
        abort_unless($request->user()->hasPermission('contacts.read'), 403);

        $query = Tag::query()->withCount('contacts');

        SearchFilters::apply($query, $request);

        SortsTable::apply($query, $request);

        $tags = $query->paginate(24)->withQueryString();

        return view('contacts.tags.index', compact('tags'));
    }

    public function create(Request $request)
    {
        abort_unless($request->user()->hasPermission('contacts.write'), 403);

        return view('contacts.tags.create', ['tag' => null]);
    }

    public function show(Request $request, Tag $tag)
    {
        abort_unless($request->user()->hasPermission('contacts.read'), 403);

        $tag->loadCount('contacts');
        $contacts = $tag->contacts()
            ->with('company')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        $allIds = Tag::orderBy('name')->pluck('id');
        $currentIndex = $allIds->search($tag->id);
        $prevId = $currentIndex > 0 ? $allIds[$currentIndex - 1] : null;
        $nextId = $currentIndex !== false && $currentIndex < $allIds->count() - 1 ? $allIds[$currentIndex + 1] : null;
        $recordPosition = $currentIndex !== false ? $currentIndex + 1 : null;
        $recordTotal = $allIds->count();

        return view('contacts.tags.show', compact(
            'tag',
            'contacts',
            'prevId',
            'nextId',
            'recordPosition',
            'recordTotal'
        ));
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->hasPermission('contacts.write'), 403);

        $tag = DB::transaction(fn () => Tag::create($this->validatedData($request)));

        return redirect()
            ->route('contacts.tags.index')
            ->with('success', "Contact tag {$tag->name} created.");
    }

    public function edit(Request $request, Tag $tag)
    {
        abort_unless($request->user()->hasPermission('contacts.write'), 403);

        return view('contacts.tags.edit', compact('tag'));
    }

    public function write(Request $request, Tag $tag)
    {
        abort_unless($request->user()->hasPermission('contacts.write'), 403);

        DB::transaction(fn () => $tag->update($this->validatedData($request, $tag)));

        return redirect()
            ->route('contacts.tags.index')
            ->with('success', "Contact tag {$tag->name} updated.");
    }

    public function unlink(Request $request, Tag $tag)
    {
        abort_unless($request->user()->hasPermission('contacts.unlink'), 403);

        if ($tag->contacts()->exists()) {
            return back()->with('error', 'Tags assigned to contacts cannot be deleted.');
        }

        DB::transaction(fn () => $tag->delete());

        return redirect()
            ->route('contacts.tags.index')
            ->with('success', 'Contact tag deleted.');
    }

    private function validatedData(Request $request, ?Tag $tag = null): array
    {
        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                'unique:tags,name' . ($tag ? ',' . $tag->id : ''),
            ],
            'color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);
    }
}
