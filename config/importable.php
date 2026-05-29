<?php

/*
|--------------------------------------------------------------------------
| Importable Models Registry
|--------------------------------------------------------------------------
|
| Defines the whitelist of models that can be imported via the generic
| POST /import endpoint and the GET /import/{modelKey}/template sample
| download endpoint.
|
| Per-model entry:
|   class           — fully-qualified Eloquent model class.
|   permission      — permission key required before importing.
|   company_scoped  — whether company gating applies to created rows.
|   filename        — base filename for the sample template (no extension).
|   request         — FormRequest class whose rules() validates each row,
|                     matching the controller's store() validation exactly.
|   service         — service class to call for actual creation.
|   service_method  — service method name (defaults to 'create').
|   fields          — allowed import columns:
|                     - key:        column header in the CSV / XLSX file
|                     - label:      human label (used in modal + sample header)
|                     - type:       string | integer | decimal | boolean | date |
|                                   datetime | email | url | enum | array |
|                                   relation
|                     - required:   bool (default false)
|                     - options:    enum values (when type=enum)
|                     - separator:  delimiter for type=array (default ';')
|                     - relation:   ['table'=>..., 'lookup'=>['id','name']]
|                                   for type=relation — accepts id (numeric) or
|                                   resolves by lookup columns within the
|                                   actor's active companies.
|                     - relation_many: true for many-to-many relations stored as
|                                   a list in a single CSV column (e.g. tags).
|                     - example:    sample value rendered in the template.
|                     - default:    fallback when the column is missing/blank.
|
| Security:
| - Every import goes through the controller-equivalent flow: FormRequest
|   rules() → service create method. Same validation, same chatter logging,
|   same post-create side effects as a manual store() submission.
| - Every row + the entire import is wrapped in a single DB::transaction —
|   if any row fails, the whole batch rolls back atomically.
| - Permission `module.import` is checked at the route, the controller, and
|   the Gate policy. The button does not render unless Gate allows it.
| - Cross-company values are rejected by the FormRequest rules (Rule 11).
| - Phpspreadsheet reads XLSX inside an isolated reader (no calculated formula
|   evaluation) and the ImportService normalises string cells before passing
|   them to validation.
|
*/

return [

    'contacts' => [
        'class'          => \App\Models\Contacts\Contact::class,
        'permission'     => 'contacts.import',
        'company_scoped' => true,
        'filename'       => 'contacts-import-template',
        'request'        => \App\Http\Requests\Contacts\StoreContactRequest::class,
        'service'        => \App\Services\Contacts\ContactService::class,
        'service_method' => 'create',
        // Only fields the service's create method persists directly via the
        // model's $fillable are listed here. Phones, tags, and related-contact
        // pivots are intentionally OMITTED — they are attached by the
        // controller's store() flow, not by ContactService::create. A module
        // that needs to import pivot / has-many data must expose a wider
        // service method (e.g. createFromImport) and reference it via
        // service_method below. See docs/components/import.md → "Adding a new
        // module".
        'fields' => [
            ['key' => 'name',         'label' => 'Name',         'type' => 'string',  'required' => true, 'example' => 'John Doe'],
            ['key' => 'contact_type', 'label' => 'Type',         'type' => 'enum',    'required' => true, 'options' => ['individual', 'company'], 'example' => 'individual', 'default' => 'individual'],
            ['key' => 'company_id',   'label' => 'Company',      'type' => 'relation', 'relation' => ['table' => 'companies', 'lookup' => ['id', 'name']], 'example' => '1'],
            ['key' => 'company_name', 'label' => 'Company Name', 'type' => 'string',  'example' => 'Acme Co'],
            ['key' => 'email',        'label' => 'Email',        'type' => 'email',   'example' => 'john@example.com'],
            ['key' => 'job_position', 'label' => 'Job Position', 'type' => 'string',  'example' => 'Manager'],
            ['key' => 'website',      'label' => 'Website',      'type' => 'url',     'example' => 'https://example.com'],
            ['key' => 'street',       'label' => 'Street',       'type' => 'string',  'example' => '123 Main St'],
            ['key' => 'city',         'label' => 'City',         'type' => 'string',  'example' => 'Baghdad'],
            ['key' => 'state',        'label' => 'State',        'type' => 'string',  'example' => ''],
            ['key' => 'country',      'label' => 'Country',      'type' => 'string',  'example' => 'Iraq'],
            ['key' => 'zip',          'label' => 'ZIP',          'type' => 'string',  'example' => '10001'],
            ['key' => 'tax_id',       'label' => 'Tax ID',       'type' => 'string',  'example' => ''],
            ['key' => 'notes',        'label' => 'Notes',        'type' => 'string',  'example' => ''],
        ],
    ],

];
