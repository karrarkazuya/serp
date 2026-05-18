# Test Credentials

These credentials are created by the database seeders for local development and testing.

## Users

Defined in `database/seeders/UserSeeder.php`.

| Email | Password | Name | Role | Access |
| --- | --- | --- | --- | --- |
| `admin@example.com` | `password` | System Admin | Administrator | Full access to all seeded permissions |
| `user@example.com` | `password` | Basic User | Basic User | Read-only access to contacts |
| `system@example.com` | Not for login | System | None | No roles or permissions |

## Roles

Defined in `database/seeders/RoleSeeder.php`.

| Role Key | Name | Permissions |
| --- | --- | --- |
| `admin` | Administrator | All permissions are synced in `UserSeeder` |
| `basic_user` | Basic User | `contacts.read` |

The System user has ID `0`. It is used by the audit observer when no authenticated user is available, such as seeders, artisan commands, or background work without an auth context.

## Company Access

Defined in `database/seeders/CompanySeeder.php`.

| User | Default Company | Allowed Companies |
| --- | --- | --- |
| `admin@example.com` | Acme Holdings | Acme Holdings, TechStart Europe, Gulf Operations LLC |
| `user@example.com` | Acme Holdings | Acme Holdings, TechStart Europe |

## Seeded Companies

| Company | Email | Currency |
| --- | --- | --- |
| Acme Holdings | `info@acme-holdings.com` | USD |
| TechStart Europe | `hello@techstart.eu` | GBP |
| Gulf Operations LLC | `ops@gulf-ops.ae` | AED |

## Notes

- These credentials are for local seeded environments only.
- The password is generated with Laravel hashing in the seeder; use the plain text value shown above when logging in.
- Re-running `php artisan migrate:fresh --seed` recreates this seeded state.
