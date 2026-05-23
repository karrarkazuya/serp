# Print Action Component

## Purpose

The print action component provides a standard Odoo-style print button for record pages. It opens a dynamic print route in a new browser tab, where the print view can render the current record and call the browser print dialog.

Use it instead of hand-built print links on show pages.

## Files

- Blade component view: `resources/views/components/print-action.blade.php`
- Blade component class: `app/View/Components/PrintAction.php`

## Props

| Prop | Type | Required | Description |
| --- | --- | --- | --- |
| `href` | string | Yes | URL for the printable record view. |
| `label` | string | No | Button label. Defaults to `Print`. |
| `preview` | bool | No | Uses the secondary gray button style for preview-like actions. |

## Blade Usage

```blade
<x-print-action
    :href="route('accounting.invoices.print', $invoice)"
/>
```

Secondary preview-style action:

```blade
<x-print-action
    :href="route('accounting.invoices.print', $invoice)"
    label="Preview"
    :preview="true"
/>
```

## Route Pattern

Print routes should be explicit and permission-gated, like all module routes:

```php
Route::get('/{invoice}/print', [AccountDocumentController::class, 'printInvoice'])
    ->middleware('permission:accounting.read')
    ->name('print');
```

## Controller Pattern

The print action should return a dedicated print view with all data loaded server-side:

```php
$invoice->load(['journal', 'partner', 'company', 'lines.account']);

return view('accounting.documents.print', compact('invoice'));
```

## Print View Guidance

Print views should be dynamic and record-driven:

- Render values from the model, not hard-coded labels or totals.
- Keep print-only CSS inside the print view.
- Include a small script that triggers `window.print()` after the page loads.
- Keep the action button on the show page; the print page itself should be focused on the printable document.
