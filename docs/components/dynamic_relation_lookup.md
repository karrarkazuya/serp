# Dynamic Relation Lookup

## Purpose

The dynamic relation lookup is a reusable Odoo-style selector for relational fields. It is used when a form needs to select records from another table without loading the entire table into the page.

Current examples:

- Contact tags in `resources/views/contacts/_form.blade.php`
- Related contacts in `resources/views/contacts/_form.blade.php`

The component supports:

- `many2one`
- `one2one`
- `many2many`
- `one2many`
- Search
- Paginated "Search More" dialog
- Existing selected values
- Excluding records, such as excluding the current contact from related contacts
- Permission checks per configured table
- Company-context filtering when the target table has a `company_id` column

## Files

- Blade component view: `resources/views/components/relation-dropdown.blade.php`
- Blade component class: `app/View/Components/RelationDropdown.php`
- Lookup controller: `app/Http/Controllers/Components/RelationLookupController.php`
- Lookup route: `routes/web.php`
- Table allowlist config: `config/relation_dropdowns.php`

## Route

The component fetches options from:

```http
GET /relation-dropdown/{table}
```

Route name:

```php
relation-dropdown.lookup
```

Query parameters:

```text
field       Display field to search and return. Must be allowlisted.
search      Optional search text.
page        Paginator page number.
per_page    Page size. Minimum 1, maximum 50.
exclude[]   Optional IDs to exclude from results.
```

Example:

```http
GET /relation-dropdown/contacts?field=name&search=abe&page=1&per_page=8&exclude[]=12
```

## Response

The endpoint returns Laravel's paginator JSON. The `data` rows are normalized for the component:

```json
{
  "current_page": 1,
  "data": [
    {
      "id": 1,
      "label": "Abe Lynchx.",
      "color": null
    }
  ],
  "from": 1,
  "last_page": 4,
  "per_page": 8,
  "to": 8,
  "total": 28
}
```

Field meanings:

- `id`: selected record ID.
- `label`: display text from the configured field.
- `color`: optional color value if the table config defines a color field.
- Pagination keys are used by the "Search More" dialog controls.

## Configuration

Every table that can be queried by this component must be registered in:

```php
config/relation_dropdowns.php
```

Example:

```php
'contacts' => [
    'read' => 'contacts.read',
    'write' => 'contacts.write',
    'create_permission' => 'contacts.create',
    'route' => 'contacts.index',
    'create' => 'contacts.create',
    'color' => null,
    'fields' => ['name', 'email'],
],
```

Config keys:

- `read`: permission required to search and view records.
- `write`: permission used for write-level access where needed.
- `create_permission`: permission required to show the `New` button. If omitted, `write` is used.
- `route`: list page route for the model. Kept for module navigation compatibility.
- `create`: create page route used by the `New` button.
- `color`: optional field used as the color swatch value.
- `fields`: allowed display/search fields. This prevents arbitrary table column access.

Do not call arbitrary tables or fields directly from Blade. Add the table and fields to this config first.

## Blade Usage

Many-to-many tags:

```blade
<x-relation-dropdown
    table="tags"
    field="name"
    name="tags"
    label="Tags"
    :selected="$selectedTags"
    relation="many2many"
/>
```

One-to-many related contacts:

```blade
<x-relation-dropdown
    table="contacts"
    field="name"
    name="related_contacts"
    label="Related Contacts"
    :selected="$selectedRelatedContacts"
    relation="one2many"
    :exclude="$contact?->id"
/>
```

Many-to-one company:

```blade
<x-relation-dropdown
    table="companies"
    field="name"
    name="company_id"
    label="Company"
    :selected="$contact?->company_id"
    relation="many2one"
/>
```

Optional page size:

```blade
<x-relation-dropdown
    table="contacts"
    field="name"
    name="related_contacts"
    relation="one2many"
    :selected="$selectedRelatedContacts"
    :limit="12"
/>
```

For `many2many` and `one2many`, the component posts repeated hidden inputs:

```text
tags[]=1
tags[]=2
```

For `many2one` and `one2one`, the component posts one hidden input:

```text
company_id=1
```

## Permissions

The lookup controller checks the authenticated user against the configured `read` permission. If the user does not have access, the endpoint returns `403`.

The component also checks permissions before rendering:

- Without read permission, it shows `No access`.
- The `New` button is only shown when the user has `create_permission` and the configured create route exists.

## Company Context

If the queried table has a `company_id` column, the lookup is filtered by `CompanyContextService::getActiveCompanyIds()`.

That service validates the active company session against the authenticated user's allowed companies. This prevents a user from changing session state to query companies they are not assigned to.

If a company-scoped table has no active allowed company IDs, the lookup returns no records.

Important: the lookup only protects searching/selecting. The form request must also validate posted IDs against the user's active company context. For contacts this is handled in:

- `app/Http/Requests/Contacts/StoreContactRequest.php`
- `app/Http/Requests/Contacts/UpdateContactRequest.php`

When using the component in another module, add equivalent request validation for posted relation IDs.

## Implementation Steps For A New Module

1. Add the target table to `config/relation_dropdowns.php`.
2. Allow only fields that are safe to search and display.
3. Add the component to the form Blade file.
4. Pass the selected IDs from the controller to the view.
5. Validate posted IDs in the module's form request.
6. Save the relationship in the controller or service.

## Notes

The component is intentionally config-driven. Avoid adding table-specific logic to the Blade view or the lookup controller unless it applies to all dynamic relation lookups.
