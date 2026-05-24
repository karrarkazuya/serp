<?php

namespace Database\Seeders;

use App\Models\Accounting\Account;
use App\Models\Accounting\AccountJournal;
use App\Models\Inventory\Location;
use App\Models\Inventory\Uom;
use App\Models\Inventory\UomCategory;
use App\Models\Security\Permission;
use App\Models\Security\Role;
use App\Models\Settings\Company;
use App\Models\Settings\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CoreSeeder extends Seeder
{
    /**
     * Path to the Iraqi UAS chart of accounts CSV.
     * Columns: code, parent, name (Arabic).
     */
    private const CHART_CSV = 'data/uas.csv';

    /**
     * Path to the English translations map (returns array code => english name).
     */
    private const TRANSLATIONS_PHP = 'database/seeders/data/uas_translations_en.php';

    /**
     * Codes whose nature is "reconcilable" — trade debtors/suppliers, notes, bank cash.
     * Any account whose code starts with one of these prefixes gets reconcile=true.
     */
    private const RECONCILE_PREFIXES = ['161', '162', '183', '185', '261', '262'];

    /**
     * Standard journals installed into every company.
     * default_account_code references a code from the UAS chart loaded from CSV.
     */
    public const STANDARD_JOURNALS = [
        ['code' => 'INV',  'name' => 'يومية المبيعات (Sales Journal)',          'type' => 'sales',    'sequence_prefix' => 'INV/',  'default_account_code' => '421'],
        ['code' => 'BILL', 'name' => 'يومية المشتريات (Purchase Journal)',      'type' => 'purchase', 'sequence_prefix' => 'BILL/', 'default_account_code' => '261'],
        ['code' => 'BANK', 'name' => 'يومية المصارف (Bank Journal)',            'type' => 'bank',     'sequence_prefix' => 'BNK/',  'default_account_code' => '183'],
        ['code' => 'CASH', 'name' => 'يومية الصندوق (Cash Journal)',            'type' => 'cash',     'sequence_prefix' => 'CSH/',  'default_account_code' => '181'],
        ['code' => 'MISC', 'name' => 'يومية القيود المتنوعة (Miscellaneous)',  'type' => 'general',  'sequence_prefix' => 'MISC/', 'default_account_code' => null],
        ['code' => 'EXCH', 'name' => 'يومية فروق العملة (Exchange Difference)', 'type' => 'general',  'sequence_prefix' => 'EXCH/', 'default_account_code' => null],
    ];

    public function run(): void
    {
        $this->seedSystemUser();
        $this->seedPermissions();
        $this->seedRoles();
        $this->seedUsers();
        $this->seedSettings();
        $this->seedInventory();
        $this->seedCurrencies();
        $this->seedAccounting();
    }

    /**
     * MC1 (Odoo parity): seed common ISO 4217 currencies. Idempotent —
     * updateOrCreate keyed on `code`. Decimal_places follow ISO 4217 (JPY=0,
     * BHD=3, most=2); IQD per Iraqi practice uses 0 decimals (the smallest
     * common-circulation note is 250 IQD).
     */
    private function seedCurrencies(): void
    {
        $currencies = [
            ['code' => 'IQD', 'name' => 'Iraqi Dinar',       'symbol' => 'د.ع', 'position' => 'after',  'decimal_places' => 0, 'rounding' => 1.0],
            ['code' => 'USD', 'name' => 'US Dollar',         'symbol' => '$',    'position' => 'before', 'decimal_places' => 2, 'rounding' => 0.01],
            ['code' => 'EUR', 'name' => 'Euro',              'symbol' => '€',    'position' => 'after',  'decimal_places' => 2, 'rounding' => 0.01],
            ['code' => 'GBP', 'name' => 'Pound Sterling',    'symbol' => '£',    'position' => 'before', 'decimal_places' => 2, 'rounding' => 0.01],
            ['code' => 'JPY', 'name' => 'Japanese Yen',      'symbol' => '¥',    'position' => 'before', 'decimal_places' => 0, 'rounding' => 1.0],
            ['code' => 'SAR', 'name' => 'Saudi Riyal',       'symbol' => 'ر.س', 'position' => 'after',  'decimal_places' => 2, 'rounding' => 0.01],
            ['code' => 'AED', 'name' => 'UAE Dirham',        'symbol' => 'د.إ', 'position' => 'after',  'decimal_places' => 2, 'rounding' => 0.01],
            ['code' => 'KWD', 'name' => 'Kuwaiti Dinar',     'symbol' => 'د.ك', 'position' => 'after',  'decimal_places' => 3, 'rounding' => 0.001],
            ['code' => 'BHD', 'name' => 'Bahraini Dinar',    'symbol' => '.د.ب','position' => 'after',  'decimal_places' => 3, 'rounding' => 0.001],
            ['code' => 'TRY', 'name' => 'Turkish Lira',      'symbol' => '₺',    'position' => 'before', 'decimal_places' => 2, 'rounding' => 0.01],
            ['code' => 'CHF', 'name' => 'Swiss Franc',       'symbol' => 'CHF',  'position' => 'before', 'decimal_places' => 2, 'rounding' => 0.01],
            ['code' => 'CAD', 'name' => 'Canadian Dollar',   'symbol' => 'C$',   'position' => 'before', 'decimal_places' => 2, 'rounding' => 0.01],
            ['code' => 'CNY', 'name' => 'Chinese Yuan',      'symbol' => '¥',    'position' => 'before', 'decimal_places' => 2, 'rounding' => 0.01],
            ['code' => 'INR', 'name' => 'Indian Rupee',      'symbol' => '₹',    'position' => 'before', 'decimal_places' => 2, 'rounding' => 0.01],
        ];

        foreach ($currencies as $row) {
            \App\Models\Accounting\Currency::updateOrCreate(
                ['code' => $row['code']],
                $row + ['active' => true],
            );
        }
    }

    // ── Inventory ─────────────────────────────────────────────────────────────

    /**
     * Seed default UoM categories + UoMs and global virtual locations.
     * Idempotent: uses updateOrCreate throughout.
     */
    private function seedInventory(): void
    {
        $this->seedUoms();
        $this->seedVirtualLocations();
    }

    private function seedUoms(): void
    {
        $categories = [
            'Units'  => [
                ['name' => 'Units',   'ratio' => 1.0,    'rounding' => 1.0,    'reference' => true],
                ['name' => 'Dozen',   'ratio' => 12.0,   'rounding' => 0.01,   'reference' => false],
                ['name' => 'Hundred', 'ratio' => 100.0,  'rounding' => 0.01,   'reference' => false],
            ],
            'Weight' => [
                ['name' => 'g',   'ratio' => 0.001,  'rounding' => 0.001, 'reference' => false],
                ['name' => 'kg',  'ratio' => 1.0,    'rounding' => 0.01,  'reference' => true],
                ['name' => 't',   'ratio' => 1000.0, 'rounding' => 0.001, 'reference' => false],
                ['name' => 'oz',  'ratio' => 0.02835,'rounding' => 0.001, 'reference' => false],
                ['name' => 'lb',  'ratio' => 0.4536, 'rounding' => 0.001, 'reference' => false],
            ],
            'Volume' => [
                ['name' => 'ml',  'ratio' => 0.001,  'rounding' => 0.001, 'reference' => false],
                ['name' => 'L',   'ratio' => 1.0,    'rounding' => 0.01,  'reference' => true],
                ['name' => 'fl oz','ratio' => 0.02957,'rounding' => 0.001,'reference' => false],
                ['name' => 'gal', 'ratio' => 3.785,  'rounding' => 0.001, 'reference' => false],
            ],
            'Time'   => [
                ['name' => 'Hours',  'ratio' => 1.0,   'rounding' => 0.01, 'reference' => true],
                ['name' => 'Days',   'ratio' => 24.0,  'rounding' => 0.01, 'reference' => false],
                ['name' => 'Minutes','ratio' => 1/60,  'rounding' => 0.01, 'reference' => false],
            ],
            'Length' => [
                ['name' => 'mm',  'ratio' => 0.001,  'rounding' => 0.001, 'reference' => false],
                ['name' => 'cm',  'ratio' => 0.01,   'rounding' => 0.001, 'reference' => false],
                ['name' => 'm',   'ratio' => 1.0,    'rounding' => 0.01,  'reference' => true],
                ['name' => 'km',  'ratio' => 1000.0, 'rounding' => 0.001, 'reference' => false],
                ['name' => 'in',  'ratio' => 0.0254, 'rounding' => 0.001, 'reference' => false],
                ['name' => 'ft',  'ratio' => 0.3048, 'rounding' => 0.001, 'reference' => false],
            ],
        ];

        foreach ($categories as $catName => $uoms) {
            $cat = UomCategory::updateOrCreate(['name' => $catName], ['name' => $catName]);
            foreach ($uoms as $u) {
                Uom::updateOrCreate(
                    ['uom_category_id' => $cat->id, 'name' => $u['name']],
                    [
                        'uom_category_id' => $cat->id,
                        'name'            => $u['name'],
                        'ratio'           => $u['ratio'],
                        'rounding'        => $u['rounding'],
                        'active'          => true,
                        'uom_type'        => $u['reference'] ? 'reference' : ($u['ratio'] > 1 ? 'bigger' : 'smaller'),
                    ]
                );
            }
        }
    }

    private function seedVirtualLocations(): void
    {
        // These are global locations with no company (company_id = null).
        // Root virtual view
        $virtualRoot = Location::updateOrCreate(
            ['complete_name' => 'Virtual Locations', 'company_id' => null],
            [
                'name'          => 'Virtual Locations',
                'complete_name' => 'Virtual Locations',
                'usage'         => 'view',
                'parent_id'     => null,
                'company_id'    => null,
                'active'        => true,
            ]
        );

        $virtuals = [
            ['name' => 'Suppliers',             'usage' => 'supplier'],
            ['name' => 'Customers',             'usage' => 'customer'],
            ['name' => 'Inventory Adjustments', 'usage' => 'inventory'],
            ['name' => 'Scrap',                 'usage' => 'scrap',    'scrap_location' => true],
            ['name' => 'Production',            'usage' => 'production'],
        ];

        foreach ($virtuals as $v) {
            Location::updateOrCreate(
                ['name' => $v['name'], 'parent_id' => $virtualRoot->id],
                [
                    'name'            => $v['name'],
                    'complete_name'   => 'Virtual Locations / ' . $v['name'],
                    'usage'           => $v['usage'],
                    'parent_id'       => $virtualRoot->id,
                    'company_id'      => null,
                    'active'          => true,
                    'scrap_location'  => $v['scrap_location'] ?? false,
                ]
            );
        }
    }

    /**
     * Install the standard chart of accounts + journals into every existing company.
     * Idempotent: re-running keeps existing rows in place (matched by company_id+code).
     */
    private function seedAccounting(): void
    {
        foreach (Company::all() as $company) {
            $this->installAccountingForCompany($company);
        }
    }

    /**
     * Install the Iraqi UAS chart of accounts + standard journals for a single company.
     *
     * - Reads the chart from the CSV file (code, parent, name)
     * - Applies English translations from the PHP map
     * - Derives account_type from the UAS class prefix
     * - Two-pass insert: first creates every account with parent_id = null,
     *   then links parents using the CSV's parent column
     *
     * Idempotent: re-running keeps existing rows (matched by company_id+code) and
     * refreshes their name / name_en / parent_id from the source files.
     *
     * Returns ['accounts' => array keyed by code, 'journals' => array keyed by code].
     *
     * Safe to call from any other seeder or from a model observer right after
     * a Company is created.
     */
    public function installAccountingForCompany(Company $company): array
    {
        $currency      = $company->currency ?: 'USD';
        $rows          = $this->loadUasChartCsv();
        $translations  = $this->loadUasTranslations();

        // Pass 1 — create/update every account without parent links.
        $accounts = [];
        foreach ($rows as $row) {
            $code = $row['code'];
            $type = $this->deriveAccountType($code);
            $accounts[$code] = Account::updateOrCreate(
                ['company_id' => $company->id, 'code' => $code],
                [
                    'company_id'    => $company->id,
                    'code'          => $code,
                    'name'          => $row['name'],
                    'name_en'       => $translations[$code] ?? null,
                    'parent_id'     => null,
                    'account_type'  => $type,
                    'internal_type' => Account::INTERNAL_TYPE_MAP[$type] ?? 'other',
                    'currency'      => $currency,
                    'reconcile'     => $this->isReconcilable($code),
                    'active'        => true,
                ]
            );
        }

        // Pass 2 — link parent_id by parent code.
        foreach ($rows as $row) {
            $parentCode = $row['parent'];
            if ($parentCode === '' || !isset($accounts[$parentCode])) continue;
            if (!isset($accounts[$row['code']])) continue;

            $accounts[$row['code']]->update([
                'parent_id' => $accounts[$parentCode]->id,
            ]);
        }

        // Journals — codes reference accounts that were just installed.
        $journals = [];
        foreach (self::STANDARD_JOURNALS as $row) {
            $defaultAccountId = null;
            if ($row['default_account_code'] && isset($accounts[$row['default_account_code']])) {
                $defaultAccountId = $accounts[$row['default_account_code']]->id;
            }
            $journals[$row['code']] = AccountJournal::updateOrCreate(
                ['company_id' => $company->id, 'code' => $row['code']],
                [
                    'company_id'         => $company->id,
                    'code'               => $row['code'],
                    'name'               => $row['name'],
                    'type'               => $row['type'],
                    'sequence_prefix'    => $row['sequence_prefix'],
                    'sequence_padding'   => 4,
                    'default_account_id' => $defaultAccountId,
                    'currency'           => $currency,
                    'active'             => true,
                ]
            );
        }

        return ['accounts' => $accounts, 'journals' => $journals];
    }

    /**
     * Load the UAS chart CSV as an array of ['code' => string, 'parent' => string, 'name' => string].
     */
    private function loadUasChartCsv(): array
    {
        $path = base_path(self::CHART_CSV);
        if (!is_file($path)) {
            throw new \RuntimeException("UAS chart CSV not found at {$path}");
        }

        $rows = [];
        if (($fh = fopen($path, 'r')) === false) {
            throw new \RuntimeException("Cannot open UAS chart CSV at {$path}");
        }
        try {
            fgetcsv($fh); // discard header row
            while (($cols = fgetcsv($fh)) !== false) {
                if (count($cols) < 3) continue;
                $code = trim((string) $cols[0]);
                if ($code === '') continue;
                $rows[] = [
                    'code'   => $code,
                    'parent' => trim((string) $cols[1]),
                    'name'   => trim((string) $cols[2]),
                ];
            }
        } finally {
            fclose($fh);
        }
        return $rows;
    }

    /**
     * Load the UAS English translations map (code => English name).
     */
    private function loadUasTranslations(): array
    {
        $path = base_path(self::TRANSLATIONS_PHP);
        if (!is_file($path)) {
            return [];
        }
        $data = require $path;
        return is_array($data) ? $data : [];
    }

    /**
     * Map a UAS code to one of the project's account_type enum values.
     */
    private function deriveAccountType(string $code): string
    {
        if (strlen($code) === 1) {
            return match ($code) {
                '1' => 'asset_current',
                '2' => 'liability_current',
                '3' => 'expense',
                '4' => 'income',
                default => 'off_balance', // 5-9 are analytical cost-center roots
            };
        }

        $prefix2 = substr($code, 0, 2);
        return match ($prefix2) {
            '11', '12'      => 'asset_fixed',
            '13'            => 'asset_current',
            '14', '15'      => 'asset_non_current',
            '16'            => (str_starts_with($code, '161') || str_starts_with($code, '162'))
                                 ? 'asset_receivable'
                                 : (str_starts_with($code, '1663') ? 'asset_prepayments' : 'asset_current'),
            '18'            => 'asset_cash',
            '19'            => 'asset_fixed', // counter-debit (contra)
            '21', '22'      => 'equity',
            '23'            => 'liability_current', // provisions (display as liability)
            '24'            => 'liability_non_current',
            '25'            => 'liability_current',
            '26'            => (str_starts_with($code, '261') || str_starts_with($code, '262'))
                                 ? 'liability_payable'
                                 : 'liability_current',
            '28'            => 'liability_current',
            '29'            => 'equity_unaffected',
            '31', '33', '34', '36', '38', '39' => 'expense',
            '32', '35'      => 'expense_direct_cost',
            '37'            => 'expense_depreciation',
            '41', '42', '43', '44', '45' => 'income',
            '46', '47', '48', '49' => 'income_other',
            default         => 'off_balance',
        };
    }

    /**
     * Trade debtors/suppliers, notes, and bank cash get reconcile=true.
     */
    private function isReconcilable(string $code): bool
    {
        foreach (self::RECONCILE_PREFIXES as $prefix) {
            if (str_starts_with($code, $prefix)) return true;
        }
        return false;
    }

    // ── Permissions ───────────────────────────────────────────────────────────

    private function seedPermissions(): void
    {
        $permissions = [
            // Contacts
            ['name' => 'Read Contacts',   'key' => 'contacts.read',   'module' => 'contacts',  'description' => 'View the contacts list and individual contact records.'],
            ['name' => 'Create Contacts', 'key' => 'contacts.create', 'module' => 'contacts',  'description' => 'Create new contact records.'],
            ['name' => 'Edit Contacts',   'key' => 'contacts.write',  'module' => 'contacts',  'description' => 'Edit and archive contact records.'],
            ['name' => 'Delete Contacts', 'key' => 'contacts.unlink', 'module' => 'contacts',  'description' => 'Permanently delete contact records.'],

            // Users
            ['name' => 'Read Users',      'key' => 'users.read',      'module' => 'users',     'description' => 'View user list and profiles.'],
            ['name' => 'Create Users',    'key' => 'users.create',    'module' => 'users',     'description' => 'Create new user accounts.'],
            ['name' => 'Edit Users',         'key' => 'users.write',         'module' => 'users',     'description' => 'Edit user accounts (name, email, password, contact details, active state).'],
            ['name' => 'Delete Users',       'key' => 'users.unlink',        'module' => 'users',     'description' => 'Delete user accounts.'],
            ['name' => 'Assign User Roles',  'key' => 'users.assign_roles',  'module' => 'users',     'description' => 'Attach or detach roles on user accounts. Grants effective privilege escalation; assign with care.'],

            // Roles
            ['name' => 'Read Roles',      'key' => 'roles.read',      'module' => 'roles',     'description' => 'View roles and their permissions.'],
            ['name' => 'Create Roles',    'key' => 'roles.create',    'module' => 'roles',     'description' => 'Create new roles.'],
            ['name' => 'Edit Roles',      'key' => 'roles.write',     'module' => 'roles',     'description' => 'Edit roles and assign permissions.'],
            ['name' => 'Delete Roles',    'key' => 'roles.unlink',    'module' => 'roles',     'description' => 'Delete roles.'],

            // Companies
            ['name' => 'Read Companies',   'key' => 'companies.read',   'module' => 'companies', 'description' => 'View company list and company records.'],
            ['name' => 'Create Companies', 'key' => 'companies.create', 'module' => 'companies', 'description' => 'Create new company records.'],
            ['name' => 'Edit Companies',   'key' => 'companies.write',  'module' => 'companies', 'description' => 'Edit and archive company records.'],
            ['name' => 'Delete Companies', 'key' => 'companies.unlink', 'module' => 'companies', 'description' => 'Permanently delete company records.'],

            // Settings
            ['name' => 'Read Settings',   'key' => 'settings.read',   'module' => 'settings',  'description' => 'View application settings.'],
            ['name' => 'Edit Settings',   'key' => 'settings.write',  'module' => 'settings',  'description' => 'Modify application settings.'],

            // Employees
            ['name' => 'Read Employees',   'key' => 'employees.read',   'module' => 'employees', 'description' => 'View employees, departments, jobs, and related records.'],
            ['name' => 'Create Employees', 'key' => 'employees.create', 'module' => 'employees', 'description' => 'Create new employee records.'],
            ['name' => 'Edit Employees',   'key' => 'employees.write',  'module' => 'employees', 'description' => 'Edit and archive employee records.'],
            ['name' => 'Delete Employees', 'key' => 'employees.unlink', 'module' => 'employees', 'description' => 'Permanently delete employee records.'],

            // Workflow — Tickets
            ['name' => 'Read Tickets',   'key' => 'workflow.tickets.read',   'module' => 'workflow', 'description' => 'View tickets assigned or visible to the user.'],
            ['name' => 'Create Tickets', 'key' => 'workflow.tickets.create', 'module' => 'workflow', 'description' => 'Create new tickets from templates.'],
            ['name' => 'Edit Tickets',   'key' => 'workflow.tickets.write',  'module' => 'workflow', 'description' => 'Update ticket state, assignment, and inputs.'],
            ['name' => 'Delete Tickets', 'key' => 'workflow.tickets.unlink', 'module' => 'workflow', 'description' => 'Delete ticket records.'],

            // Workflow — Procedures
            ['name' => 'Read Procedures',   'key' => 'workflow.procedures.read',   'module' => 'workflow', 'description' => 'View procedures and their tasks.'],
            ['name' => 'Create Procedures', 'key' => 'workflow.procedures.create', 'module' => 'workflow', 'description' => 'Start procedures from templates.'],
            ['name' => 'Edit Procedures',   'key' => 'workflow.procedures.write',  'module' => 'workflow', 'description' => 'Complete, close, and manage procedure tasks.'],
            ['name' => 'Delete Procedures', 'key' => 'workflow.procedures.unlink', 'module' => 'workflow', 'description' => 'Delete procedure records.'],

            // Workflow — Configuration
            ['name' => 'Read Workflow Config',   'key' => 'workflow.config.read',   'module' => 'workflow', 'description' => 'View workflow configuration (groups, departments, templates).'],
            ['name' => 'Edit Workflow Config',   'key' => 'workflow.config.write',  'module' => 'workflow', 'description' => 'Create and edit workflow configuration.'],
            ['name' => 'Delete Workflow Config', 'key' => 'workflow.config.unlink', 'module' => 'workflow', 'description' => 'Delete workflow configuration records.'],

            // Inventory
            ['name' => 'Read Inventory',          'key' => 'inventory.read',   'module' => 'inventory', 'description' => 'View inventory: products, transfers, lots, stock, adjustments.'],
            ['name' => 'Create Inventory Records', 'key' => 'inventory.create', 'module' => 'inventory', 'description' => 'Create products, transfers, scrap orders, and adjustments.'],
            ['name' => 'Edit Inventory Records',   'key' => 'inventory.write',  'module' => 'inventory', 'description' => 'Edit, validate, and archive inventory records.'],
            ['name' => 'Delete Inventory Records', 'key' => 'inventory.unlink', 'module' => 'inventory', 'description' => 'Permanently delete inventory records.'],
            ['name' => 'Inventory Configuration',  'key' => 'inventory.config', 'module' => 'inventory', 'description' => 'Manage warehouses, locations, routes, operation types, UoMs, and categories.'],

            // Export permissions — one per module, separate from read so exports can be restricted
            ['name' => 'Export Contacts',          'key' => 'contacts.export',              'module' => 'contacts',   'description' => 'Export contact records to XLSX or CSV.'],
            ['name' => 'Export Users',             'key' => 'users.export',                 'module' => 'users',      'description' => 'Export user records to XLSX or CSV.'],
            ['name' => 'Export Employees',         'key' => 'employees.export',             'module' => 'employees',  'description' => 'Export employee records to XLSX or CSV.'],
            ['name' => 'Export Tickets',           'key' => 'workflow.tickets.export',      'module' => 'workflow',   'description' => 'Export workflow ticket records to XLSX or CSV.'],
            ['name' => 'Export Procedures',        'key' => 'workflow.procedures.export',   'module' => 'workflow',   'description' => 'Export workflow procedure records to XLSX or CSV.'],
            ['name' => 'Export Inventory',         'key' => 'inventory.export',             'module' => 'inventory',  'description' => 'Export inventory records to XLSX or CSV.'],
            ['name' => 'Export Accounting',        'key' => 'accounting.export',            'module' => 'accounting', 'description' => 'Export accounting records to XLSX or CSV.'],

            // Accounting — Unified Accounting System
            ['name' => 'Read Accounting',      'key' => 'accounting.read',   'module' => 'accounting', 'description' => 'View chart of accounts, journals, and journal entries.'],
            ['name' => 'Create Accounting',    'key' => 'accounting.create', 'module' => 'accounting', 'description' => 'Create accounts, journals, and draft journal entries.'],
            ['name' => 'Edit Accounting',      'key' => 'accounting.write',  'module' => 'accounting', 'description' => 'Edit accounts, journals, and draft journal entries.'],
            ['name' => 'Post Accounting',      'key' => 'accounting.post',   'module' => 'accounting', 'description' => 'Post, reset to draft, cancel, and reverse journal entries.'],
            ['name' => 'Delete Accounting',    'key' => 'accounting.unlink', 'module' => 'accounting', 'description' => 'Delete accounts, journals, and draft journal entries.'],
            ['name' => 'Lock Accounting',      'key' => 'accounting.lock',   'module' => 'accounting', 'description' => 'Set period and fiscal year lock dates; bypass period locks when posting.'],
        ];

        foreach ($permissions as $perm) {
            Permission::updateOrCreate(['key' => $perm['key']], $perm);
        }
    }

    // ── Roles ─────────────────────────────────────────────────────────────────

    private function seedRoles(): void
    {
        Role::updateOrCreate(
            ['key' => 'admin'],
            [
                'name'        => 'Administrator',
                'key'         => 'admin',
                'description' => 'Full access to all modules and settings.',
                'active'      => true,
            ]
        );

        $basicUser = Role::updateOrCreate(
            ['key' => 'basic_user'],
            [
                'name'        => 'Basic User',
                'key'         => 'basic_user',
                'description' => 'Read-only access to contacts.',
                'active'      => true,
            ]
        );

        $readPermissions = Permission::whereIn('key', [
            'contacts.read',
            'employees.read',
            'workflow.tickets.read',
            'workflow.procedures.read',
        ])->pluck('id');

        $basicUser->permissions()->sync($readPermissions);
    }

    // ── Users ─────────────────────────────────────────────────────────────────

    private function seedSystemUser(): void
    {
        // Use raw DB insert so id=0 is preserved — Eloquent strips primary keys
        // not in $fillable, causing the self-referential created_by=0 FK to fail.
        \Illuminate\Support\Facades\DB::table('users')->updateOrInsert(
            ['id' => 0],
            [
                'uuid'         => '00000000-0000-0000-0000-000000000000',
                'name'         => 'System',
                'email'        => 'system@example.com',
                'password'     => Hash::make(Str::random(64)),
                'active'       => false,
                'job_position' => 'System User',
                'created_by'   => 0,
                'updated_by'   => 0,
                'created_at'   => now(),
                'updated_at'   => now(),
            ]
        );
    }

    private function seedUsers(): void
    {
        $admin = User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name'         => 'System Admin',
                'email'        => 'admin@example.com',
                'password'     => Hash::make('password'),
                'active'       => true,
                'job_position' => 'System Administrator',
            ]
        );

        $adminRole = Role::where('key', 'admin')->first();
        if ($adminRole) {
            $adminRole->permissions()->sync(Permission::pluck('id'));
            $admin->roles()->syncWithoutDetaching([$adminRole->id]);
        }

        $basicUser = User::updateOrCreate(
            ['email' => 'user@example.com'],
            [
                'name'         => 'Basic User',
                'email'        => 'user@example.com',
                'password'     => Hash::make('password'),
                'active'       => true,
                'job_position' => 'Staff',
            ]
        );

        $basicRole = Role::where('key', 'basic_user')->first();
        if ($basicRole) {
            $basicUser->roles()->syncWithoutDetaching([$basicRole->id]);
        }
    }

    // ── Settings ──────────────────────────────────────────────────────────────

    private function seedSettings(): void
    {
        $defaults = [
            ['key' => 'company_name',    'value' => 'SERP ERP',          'group' => 'general', 'type' => 'string', 'label' => 'Company Name'],
            ['key' => 'company_email',   'value' => 'admin@example.com', 'group' => 'general', 'type' => 'string', 'label' => 'Company Email'],
            ['key' => 'company_phone',   'value' => '',                  'group' => 'general', 'type' => 'string', 'label' => 'Company Phone'],
            ['key' => 'company_website', 'value' => '',                  'group' => 'general', 'type' => 'string', 'label' => 'Website'],
            ['key' => 'company_address', 'value' => '',                  'group' => 'general', 'type' => 'string', 'label' => 'Address'],
        ];

        foreach ($defaults as $setting) {
            Setting::updateOrCreate(['key' => $setting['key']], $setting);
        }
    }
}
