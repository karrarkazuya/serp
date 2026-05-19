# Workflow Module — Missing Features

Features present in the Odoo `ss_workflow` module that could not be fully ported to the Laravel implementation, along with the reason each is a dead-end or requires significant additional work.

---

## 1. Notification System ✅ Implemented

**Odoo:** On ticket/procedure assignment or completion, Odoo sends in-app chat messages via `discuss.channel` and `message_post`.

**Laravel:** Implemented as a custom `notifications` table with `App\Models\Notification`, `User::notify()`, and `NotificationController`. The navbar bell shows an unread count badge, a dropdown of recent notifications, and plays a Web Audio API ping when new notifications arrive. Polling runs every 30 seconds.

**Workflow hooks:**
- Ticket created → assigned user notified
- Ticket assignment changed → new assignee notified
- Ticket resolved/closed → creator notified
- Task unlocked (→ pending) → all active WorkflowUsers in the assigned department notified
- Procedure completed → creator notified
- Procedure canceled → creator notified

---

## 2. Ticket / Procedure / Task Sharing (Public URL) ✅ Implemented

**Odoo:** Tickets have `share_enabled`, `share_token`, and `share_url` fields. A public route serves a read-only view of a ticket to anyone with the token.

**Laravel:** Implemented via a polymorphic `workflow_shared_links` table (`WorkflowSharedLink` model) that applies to tickets, procedures, and tasks. Each record stores a unique token, an optional message, and an `enabled` toggle.

**What was built:**
- `GET /share/{token}` — public route (no auth) served by `SharedLinkController`, dispatches to `shared.ticket`, `shared.procedure`, or `shared.task` views
- `ShareController` — authenticated toggle and message-save endpoints for all three model types
- Standalone Blade views styled like the Odoo share page (dark gradient background, state-coloured banner, metadata strip, message section)
- Ticket show page: "Share" / "Sharing On" toggle button in the header; share URL (with copy button) + message editor shown when enabled
- Procedure show page: same toggle for the procedure itself, plus per-task share icon buttons within the task list; each enabled task shows an inline share URL + message editor
- The token persists between enable/disable cycles so the URL never changes after first activation

---

## 3. Template Visibility by Contact Type (`is_contact_ticket_only`)

**Odoo:** Ticket and procedure templates can be restricted to specific contact types (`contact_types_can_create`). Templates marked `is_contact_ticket_only` are only visible to external contacts, not internal users.

**Laravel:** The `TicketTemplate` and `ProcedureTemplate` models have no `is_contact_ticket_only` or `contact_types_can_create` columns. The template selection in the create forms shows all enabled templates without this filtering.

**Path forward:** Add the columns to the template migrations, expose them in the template create/edit forms, and apply the domain filter in the ticket/procedure create controller actions.

---

## 4. Input Visibility Across Tasks (`show_in_other_tasks`)

**Odoo:** A `TemplateTaskInput` can be configured to also appear (read-only) inside other tasks via `show_in_other_tasks`. On procedure creation, `handle_inputs_viewed_in_other_tasks` wires runtime `TaskInput` records to those other tasks so they display there.

**Laravel:** No `show_in_other_tasks` column exists on `TemplateTaskInput` or `TaskInput`. The `instantiateTasks` logic does not implement this cross-task input visibility.

**Path forward:** Add a `Many2many` pivot between `workflow_template_task_inputs` and `workflow_template_tasks` for `show_in_other_tasks`. During `instantiateTasks`, copy the matching runtime `TaskInput` references to the target tasks.

---

## 5. Procedure Flowchart Visualization

**Odoo:** The procedure template edit view includes a drag-and-drop flowchart showing task nodes connected by arrows, powered by OWL components and a dedicated `procedure_flowchart.py` controller.

**Laravel:** The `TemplateTask` model has `flowchart_x`, `flowchart_y`, and `flowchart_position_saved` columns (data is preserved), but no flowchart UI exists.

**Path forward:** Implement a canvas-based or SVG task graph using Alpine.js or a JS library (e.g., Cytoscape.js). A dedicated API endpoint to save position data would be needed.

---

## 6. Reports (Activity, Performance Charts)

**Odoo:** Four OWL-based reports exist — Activity, Ticket Performance, Procedure Performance, and Task Performance — backed by PostgreSQL read-model views and Chart.js-rendered client actions.

**Laravel:** The reports index and show pages exist as stubs. The show page contains a TODO note. No data aggregation queries, read models, or chart rendering are implemented.

**Path forward:** This is a significant effort. Define SQL views or Eloquent aggregation queries for each report type, then render results using a JS chart library (Chart.js or ApexCharts) embedded in Blade.

---

## 7. Manager Create / Edit

**Odoo:** Managers are created by an admin by associating a `WorkflowUser` record with one or more departments. The `ssw_managers` model is editable.

**Laravel:** `ManagerController` only has `read`, `show`, and `addComment` — no `create`, `store`, `edit`, or `write`. The index and show views contain TODO notes.

**Path forward:** Add `StoreProcedureRequest` equivalent, service methods in `WorkflowConfigService`, controller actions, routes, and create/edit Blade views following the same pattern as `GroupController`.

---

## 8. Viewer-Scoped Ticket / Procedure Read

**Odoo:** Ticket and procedure records have a `users_can_view` Many2many field. Odoo record rules restrict read access to records where the current user is in `users_can_view`.

**Laravel:** `Ticket` and `Procedure` have a `viewers` pivot table and a `scopeForUser()` scope that filters by viewers for non-admin users. However:
- The `viewers` pivot is **never populated** — ticket and procedure creation does not add the creator or department members to `workflow_ticket_viewers` / `workflow_procedure_viewers`.
- `scopeForUser()` is **never called** in `TicketController::read()` or `ProcedureController::read()`.

As a result, all authenticated users with the `workflow.tickets.read` permission can see all tickets regardless of department or assignment.

**Path forward:** On ticket/procedure creation, populate the `viewers` pivot with the creator, the assigned-department's workflow users, and the relevant managers. Then apply `scopeForUser(auth()->user())` in the `read` controller methods.
