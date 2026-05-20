Perform a thorough security and code quality review of the Laravel codebase. Check every file listed below. Report every finding with file path, line number, severity (Critical / High / Medium / Low), and a one-sentence explanation.

Do not stop at the first issue per category — find all of them.

---

## Security Checks

### Authorization & Access Control
- Find controller methods that skip `$this->authorize()` or `abort_unless()` for routes that modify data
- Find company-scoped models (tables with `company_id`) where any controller method does not call `getActiveCompanyIds()` and gate/filter by it — this is a data isolation bug
- Find routes inside authenticated groups missing `->middleware('permission:...')`
- Find Blade templates using `@can` / `@cannot` as the **only** authorization gate (middleware must also be present)
- Find any hardcoded user ID checks (e.g. `if ($user->id === 1)`) used as authorization

### Injection & Output
- Find raw SQL via `DB::statement`, `DB::select`, `whereRaw`, `selectRaw`, `orderByRaw` that interpolates user input without bindings — SQL injection risk
- Find Blade output using `{!! !!}` (unescaped) — XSS risk unless the value is explicitly trusted/sanitized
- Find `shell_exec`, `exec`, `system`, `passthru`, `proc_open` — command injection risk
- Find any use of `eval()` — code injection risk

### File Handling
- Find file uploads not validated by MIME type via `finfo` (reading actual bytes, not user-supplied header)
- Find uploaded files stored on the `public` disk — files should be on the `local` (private) disk, served through a controller
- Find file download endpoints that do not verify the file record belongs to the authenticated user's accessible records before serving
- Find missing extension validation as a secondary gate on uploads

### CSRF & Forms
- Find `<form method="POST">` in Blade templates missing `@csrf`
- Find `<form method="POST" action="...">` for DELETE/PUT/PATCH without `@method(...)`

### Mass Assignment
- Find Eloquent models missing a `$fillable` array (or using `$guarded = []` without reason)
- Find `Model::create($request->all())` or `$model->fill($request->all())` without validated data — use `$request->validated()` only

### Secrets & Credentials
- Find hardcoded passwords, API keys, tokens, or secrets in PHP files or config files (not `.env`)
- Find `.env` values being echoed directly into Blade views

### Session & Authentication
- Find `Auth::loginUsingId()` or `Auth::login()` calls outside of dedicated auth controllers

---

## Code Quality Checks

### Transaction Safety (Rule 7)
- Find controller methods named store, write, archive, unarchive, unlink, addComment, or any method that calls a service or writes to the DB, that do NOT wrap in `DB::transaction`
- Find service methods that call `DB::transaction` — transactions belong in controllers

### N+1 Query Problems
- Find `@foreach` loops in Blade that call a relationship method (e.g. `$item->relation`) where that relation was not eager-loaded in the controller
- Find controller `show` methods that load a model without `->load([...])` but the view accesses relations

### Dead & Unsafe Code
- Find `dd()`, `dump()`, `ray()`, `var_dump()`, `print_r()` left in production code paths
- Find `TODO`, `FIXME`, `HACK` comments that indicate unfinished work
- Find commented-out code blocks (more than 3 lines)

### Validation Gaps
- Find form request `rules()` methods that accept user-controlled IDs with only `exists:table,id` without also scoping to the user's allowed companies (for company-scoped tables)
- Find controller methods that read `$request->input(...)` directly without validation for fields that affect database queries

---

## Output Format

Group findings by category. For each:
```
[SEVERITY] file/path/here.php:LINE — description of the issue
```

Severity levels:
- **Critical** — exploitable security vulnerability (auth bypass, injection, data isolation break)
- **High** — likely bug or significant security gap (missing CSRF, unvalidated upload, missing transaction)
- **Medium** — correctness issue or weak defense (N+1, missing eager load, unguarded model)
- **Low** — code quality / maintenance (debug code, TODO, dead code)

End with a count: X Critical, Y High, Z Medium, W Low findings.
