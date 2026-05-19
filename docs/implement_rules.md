# Implementation Rules

These rules are **non-negotiable**. Every implementer must follow them without exception. Violating them introduces inconsistency, bugs, or security gaps that are hard to trace and expensive to fix later.

---

## 1. List Views Must Use `<x-list>` and `<x-search>`

Every index/list view must use the `<x-list>` component for the table and `<x-search>` for the filter bar.

**Do not** hand-build a `<div class="overflow-auto"><table>` block. **Do not** write a custom search input or filter form. The components exist precisely to standardize this.

```blade
{{-- Header bar --}}
<div class="flex flex-wrap items-center gap-3 px-4 py-2 border-b border-gray-200 shrink-0">
    @can('create', \App\Models\Foo\Bar::class)
    <a href="{{ route('foo.bars.create') }}" class="px-4 py-2 bg-[#714B67] hover:bg-[#5c3d55] text-white text-sm font-semibold rounded shadow-sm">New</a>
    @endcan
    <span class="text-xl font-semibold text-gray-700">Bars</span>
    <x-search :model="\App\Models\Foo\Bar::class" :action="route('foo.bars.index')" />
</div>

{{-- Table --}}
<x-list :paginator="$bars" empty-text="No bars found.">
    <x-slot:columns>
        <x-sortable-th column="name" label="Name" class="px-4 py-2.5" :default="true" />
    </x-slot:columns>

    @foreach($bars as $bar)
    <tr class="hover:bg-purple-50/30 cursor-pointer" onclick="window.location='{{ route('foo.bars.show', $bar) }}'">
        <td class="px-4 py-2 font-medium text-gray-900">{{ $bar->name }}</td>
    </tr>
    @endforeach
</x-list>
```

Use `@foreach`, not `@forelse` — the empty state is handled by `<x-list>`.

See `docs/components/list.md` and `docs/components/search.md`.

---

## 2. Show Pages for Chatter-Enabled Models Must Include `<x-chatter>`

Any model that uses the `HasChatter` trait must have `<x-chatter>` on its show page.

The controller must load messages and pass them to the view:

```php
$messages = $bar->chatterMessages()->with('user')->latest()->get();
return view('foo.bars.show', compact('bar', 'messages'));
```

The Blade show view must include the chatter panel:

```blade
<x-chatter
    :model="$bar"
    :messages="$messages"
    :comment-url="route('foo.bars.comment', $bar)"
    :can-comment="auth()->user()->can('update', $bar)"
/>
```

See `docs/components/chatter.md`.

---

## 3. Forms Must Follow Odoo Design

Forms must visually match the Odoo form style. Two approved layouts exist — use the one that fits the complexity of the model:

### Inline / border-bottom style (simple models)
Used in: `resources/views/contacts/_form.blade.php`

Fields are rendered as rows with a label on the left and an input on the right, separated by a bottom border. The overall form has no card or box shadow.

```blade
<div class="border-b border-gray-100 py-3 flex items-center gap-4">
    <label class="w-40 shrink-0 text-sm font-medium text-gray-600">Name</label>
    <input type="text" name="name" value="{{ old('name', $bar->name) }}"
        class="flex-1 border border-gray-200 rounded px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-purple-400">
</div>
```

### Card / section style (complex models with grouped fields)
Used in: `resources/views/settings/companies/_form.blade.php`

Fields are grouped into labeled card sections with a white background and border. Each card has a title row and a grid of fields inside.

```blade
<div class="bg-white border border-gray-200 rounded-lg p-6">
    <h3 class="text-sm font-semibold text-gray-700 mb-4">General Information</h3>
    <div class="grid grid-cols-2 gap-4">
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Name</label>
            <input type="text" name="name" ...>
        </div>
    </div>
</div>
```

**Do not** invent new form layouts. Pick one of the two above.

---

## 4. Relation Fields Must Use `<x-relation-dropdown>`

Never use a raw `<select>` that loads an entire table to populate options. All relational fields (many2one, one2one, many2many, one2many) must use `<x-relation-dropdown>`.

```blade
<x-relation-dropdown
    table="contacts"
    field="name"
    name="contact_id"
    relation="many2one"
    :selected="old('contact_id', $bar->contact_id)"
/>
```

The target table must be registered in `config/relation_dropdowns.php` with its allowed lookup fields before this will work. This registration is a required step when creating any new model that will be used as a relation target.

See `docs/components/dynamic_relation_lookup.md`.

---

## 5. Tables With `company_id` Must Apply Company Filtering in Every Controller Method

If a model's table has a `company_id` column, the controller must filter by the user's active companies in **every** method — not just `read`. This includes: `read`, `show`, `create`, `store`, `edit`, `write`, `archive`, `unarchive`, `unlink`, and any custom methods that query or mutate records of that model.

Use `CompanyContextService::getActiveCompanyIds()`:

```php
public function __construct(private CompanyContextService $companyContext) {}

public function read(Request $request): View
{
    $query = Bar::whereIn('company_id', $this->companyContext->getActiveCompanyIds());
    // ...
}

public function show(Bar $bar): View
{
    abort_unless(
        in_array($bar->company_id, $this->companyContext->getActiveCompanyIds()),
        403
    );
    // ...
}

public function write(WriteBarRequest $request, Bar $bar): RedirectResponse
{
    abort_unless(
        in_array($bar->company_id, $this->companyContext->getActiveCompanyIds()),
        403
    );
    // ...
}
```

Missing this in even one method (e.g., `archive`, `unlink`) is a data isolation bug — a user could act on a record belonging to a company they don't have access to.

---

## 6. Permissions Must Be Enforced as Route Middleware

Every route must have a `permission:` middleware attached. Do not rely solely on `@can` in Blade or policy checks inside the controller — middleware is the hard gate that prevents unauthorized HTTP access entirely.

Apply the middleware directly on each route, not on a group, so the permission is explicit and traceable:

```php
// web.php or api.php
Route::get('/',          [BarController::class, 'read'])    ->middleware('permission:foo.read')   ->name('index');
Route::get('/create',    [BarController::class, 'create'])  ->middleware('permission:foo.create') ->name('create');
Route::post('/',         [BarController::class, 'store'])   ->middleware('permission:foo.create') ->name('store');
Route::get('/{bar}',     [BarController::class, 'show'])    ->middleware('permission:foo.read')   ->name('show');
Route::get('/{bar}/edit',[BarController::class, 'edit'])    ->middleware('permission:foo.write')  ->name('edit');
Route::put('/{bar}',     [BarController::class, 'write'])   ->middleware('permission:foo.write')  ->name('update');
Route::patch('/{bar}/archive',   [BarController::class, 'archive'])   ->middleware('permission:foo.write') ->name('archive');
Route::patch('/{bar}/unarchive', [BarController::class, 'unarchive']) ->middleware('permission:foo.write') ->name('unarchive');
Route::delete('/{bar}',  [BarController::class, 'unlink'])  ->middleware('permission:foo.unlink') ->name('delete');
Route::post('/{bar}/comment', [BarController::class, 'addComment']) ->middleware('permission:foo.write') ->name('comment');
```

Permission key format: `module.read`, `module.create`, `module.write`, `module.unlink`.

For nested sub-modules (e.g., workflow tickets), prefix the module path: `workflow.tickets.read`, `workflow.tickets.write`, etc.

This rule applies to both `routes/web.php` and `routes/api.php`. API routes must carry the same permission middleware — never assume that token authentication alone is sufficient authorization.

---

## 7. All State-Changing Operations Must Use `DB::transaction`

Every controller method that writes to the database must wrap its logic in `DB::transaction`. This includes: `store`, `write`, `archive`, `unarchive`, `unlink`, `addComment`, and any other method that calls a service or executes a mutation.

```php
public function store(StoreBarRequest $request): RedirectResponse
{
    $bar = DB::transaction(fn () => $this->barService->create($request->validated()));
    return redirect()->route('foo.bars.show', $bar)->with('success', 'Bar created.');
}

public function archive(Bar $bar): RedirectResponse
{
    DB::transaction(fn () => $this->barService->archive($bar));
    return redirect()->route('foo.bars.index')->with('success', 'Bar archived.');
}
```

A transaction that wraps a single service call still provides atomicity and rollback if the service or an observer throws. Always use it.
