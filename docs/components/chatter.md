# Chatter Component

## Purpose

The chatter component renders an Odoo-style record activity panel. Use it on record show pages for comments, system messages, and tracked field-change logs.

Use it instead of hand-built chatter markup in module views.

## Files

- Blade component view: `resources/views/components/chatter.blade.php`
- Blade component class: `app/View/Components/Chatter.php`
- Chatter model: `app/Models/Chatter/ChatterMessage.php`
- Model trait: `app/Traits/HasChatter.php`

## Model Setup

Models that need chatter should use `HasChatter`.

```php
use App\Traits\HasChatter;

class Invoice extends Model
{
    use HasChatter;
}
```

Tracked fields are handled by module services when they call the chatter service or model logging helpers.

## Controller Usage

Load messages with users before returning the show view:

```php
public function show(Invoice $invoice)
{
    $this->authorize('view', $invoice);

    $messages = $invoice->chatterMessages()->with('user')->latest()->get();

    return view('invoices.show', compact('invoice', 'messages'));
}
```

Add a comment endpoint:

```php
public function addComment(Request $request, Invoice $invoice)
{
    $this->authorize('comment', $invoice);

    $request->validate(['body' => 'required|string|max:5000']);
    $invoice->logComment($request->body);

    return back()->with('success', 'Comment added.');
}
```

Add the route near the record routes:

```php
Route::post('/{invoice}/comment', [InvoiceController::class, 'addComment'])
    ->middleware('permission:invoices.write')
    ->name('comment');
```

Policies should expose `comment()` and usually map it to the module write permission:

```php
public function comment(User $user, Invoice $_invoice): bool
{
    return $user->hasPermission('invoices.write');
}
```

## Blade Usage

Use the component on a record page:

```blade
<x-chatter
    :model="$invoice"
    :messages="$messages"
    :comment-url="route('invoices.comment', $invoice)"
    :can-comment="auth()->user()->can('comment', $invoice)"
/>
```

The component can fetch messages from the model if `messages` is omitted:

```blade
<x-chatter
    :model="$invoice"
    :comment-url="route('invoices.comment', $invoice)"
    :can-comment="auth()->user()->can('comment', $invoice)"
/>
```

Use a read-only chatter by omitting `comment-url` or setting `can-comment` to false:

```blade
<x-chatter :model="$invoice" :messages="$messages" :can-comment="false" />
```

Optional display props:

```blade
<x-chatter
    :model="$invoice"
    :messages="$messages"
    title="Activity"
    empty-text="No messages yet."
/>
```

## Props

| Prop | Type | Required | Description |
|------|------|----------|-------------|
| `model` | Eloquent model | No | Record that owns chatter messages. Required when `messages` is omitted. |
| `messages` | collection/array | No | Preloaded `ChatterMessage` rows with `user`; if omitted, the component loads them from `model`. |
| `comment-url` | string | No | POST endpoint for new comments. Required for the composer to render. |
| `can-comment` | bool | No | Whether the current user may post comments. Defaults to false. |
| `title` | string | No | Header text. Defaults to `Log & Chatter`. |
| `empty-text` | string | No | Empty-state text. Defaults to `No activity yet.` |

## Behavior

- Shows tabs for all activity, comments, and logs.
- Posts comments with a `body` field and CSRF token.
- Displays tracked field changes from `metadata.changes`.
- Displays comment and system badges based on `message_type`.
- Does not render the comment composer unless both `comment-url` and `can-comment` are present.

## Current Uses

- Contacts show page.
- Companies show page.
- Workflow tickets show page.
- Workflow procedures show page.
