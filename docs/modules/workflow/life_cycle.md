# Workflow Module — Lifecycle & Rules

## Overview

The workflow module has two record types: **Procedures** and **Tickets**.

- A **Procedure** is a multi-step process created from a `ProcedureTemplate`. It owns a sequence of tickets.
- A **Ticket** is a single task. It can exist standalone (created directly) or as a step inside a procedure.

---

## Procedure Lifecycle

### States
| State | Label | Meaning |
|-------|-------|---------|
| `pending` | In Progress | Active — tickets are running |
| `completed` | Completed | The last active ticket was completed and there are no active tickets left |
| `closed` | Cancelled | Terminated — no active tickets remain and none completed |

### Creation
1. A procedure is created from a `ProcedureTemplate`.
2. All steps are instantiated as **Ticket** records with state `draft`. and their assigned department filled from the ticket template
3. Start tickets (tickets with no `previous_ticket_id`) are immediately unlocked to `pending`.
4. The users that has config set, and are active enabled in the users table and in the config, are added to the allowed_users of the ticket if their default department matches the assigned department.
5. The creator is added to the procedure's viewers list.

### Auto-Completion (triggered by `completeTicket`)
After a ticket is marked completed:
- If the completed ticket has **no next tickets** AND no other `pending` tickets remain → procedure → `completed`.
- Otherwise → next tickets are unlocked and the procedure continues.

### Auto-Cancellation (triggered by `rejectTicket` on a start ticket)
When a start ticket (no `previous_ticket_id`) is rejected:
- If no other `pending` tickets remain → remaining `draft` tickets are skipped → procedure → `closed`.
- If other `pending` tickets remain → procedure stays active.

### Skip terminal check (triggered by `skipTicket`)
When a skipped ticket has no next tickets and no other pending tickets remain:
- If any ticket in the procedure was ever `completed` → procedure → `completed`.
- If no ticket was ever completed → procedure → `closed`.

### Archive / Restore
- Only allowed when the procedure is **not** `pending` (must be completed or closed first).
- Procedures cannot be deleted.

---

## Ticket Lifecycle

### States — Procedure Tickets
| State | Label | Meaning |
|-------|-------|---------|
| `draft` | Waiting | Not yet unlocked — previous step not done |
| `pending` | Open | Active — ready for action |
| `completed` | Completed | Done — triggers next ticket unlock and procedure completion check |
| `rejected` | Returned | Rejected — triggers reactivation of the previous ticket or procedure cancellation |
| `skipped` | Skipped | Bypassed — unlocks next tickets |

### States — Standalone Tickets
| State | Label | Meaning |
|-------|-------|---------|
| `pending` | Open | Active |
| `completed` | Completed | Resolved |
| `closed` | Closed | Closed without completion |

---

## Ticket State Transitions (Procedure Tickets)

### Unlocking
**Unlocking a ticket = setting it from `draft`, `rejected`, or `skipped` → `pending`.**

A ticket is eligible to be unlocked when:
- Its previous ticket is `completed` (or it has no previous ticket).
- Its current state is `draft`, `rejected`, or `skipped`.

On unlock:
- All active WorkflowUsers in the ticket's assigned department are added as viewers (seeded into `workflow_allowed_users` and `workflow_procedure_viewers`).
- Department members are notified.

> Rejected and skipped tickets are re-unlocked to `pending` when their previous ticket completes/skips again after a reactivation cycle — they do not stay in their rejected/skipped state.

### Complete
1. Ticket → `completed` (duration and SLA fields recorded).
2. Check: no next tickets AND no other `pending` tickets → procedure completes.
3. Otherwise: unlock all next tickets (from `draft`/`rejected`/`skipped` → `pending`).

### Reject

**Case 1 — ticket has a previous ticket (mid-chain):**
1. A **return reason is required** from the user before the rejection is accepted.
2. The user may optionally select **which ancestor to return to** (defaults to the immediate previous ticket).
3. Ticket → `rejected`.
4. Every intermediate ticket between the rejected ticket and the target ancestor is also set to `rejected`, along with any of their sibling next-tickets that are still active.
5. The return reason is written to the **target ticket's** `return_reason` field — so its assignees see exactly why it was returned when the ticket is reactivated.
6. The target ticket is reactivated → `pending`.
7. When the target ticket is eventually completed again, all rejected downstream tickets are re-unlocked to `pending`.

> **Single-step rejection** (no target selected, or target = immediate previous) behaves exactly as before.

> **Chain rejection example:** Chain A → B → C → D → E. User rejects E and selects B as the return target. E → `rejected`, D → `rejected`, C → `rejected`, B → `pending` (with the return reason). A remains untouched.

> **Cycle guard:** The backwards walk is capped at 100 iterations and tracks visited IDs. If a cycle is detected the chain is treated as empty and no "Return to" selector is shown.

**Case 2 — ticket has no previous ticket (start of chain):**
> No return reason required for start tickets.
1. Ticket → `rejected`.
2. If no other `pending` tickets remain → remaining `draft` tickets are skipped → procedure → `closed`.
3. If other `pending` tickets remain, the procedure stays active.

### Skip
1. Ticket → `skipped`.
2. If no next tickets AND no other `pending` non-`ignore_state` tickets remain → run terminal check (complete or cancel procedure).
3. Otherwise: unlock all next tickets (from `draft`/`rejected`/`skipped` → `pending`).

> **Skipped tickets are terminal.** Once a ticket is `skipped` its state cannot be changed by any user (`act` requires `pending`). This is especially important for path-choice-skipped tickets — they are permanently bypassed.

### Path Choice
A ticket with `has_path_choice = true` presents the user with a branching decision. The ticket's `path_choice_question` is shown alongside a set of named options, each pointing to a different next ticket.

**Rules:**
- Path choice options must be among the ticket's next tickets.
- The user selects one option by POSTing to the path endpoint. This records `path_chosen_id` on the ticket and immediately sets all other path targets to `skipped`.
- The chosen target ticket is **not** unlocked immediately — it is unlocked when the current ticket is completed (via the normal next-ticket unlock step, which skips non-chosen targets).
- After choosing a path the user can change their selection any time while the ticket is still `pending` (the previous skipped targets stay `skipped`; the new chosen target will be unlocked on completion).

**`path_choice_required`:**
- If `true`, the user must select a path before they are allowed to complete the ticket.
- The Complete button is visually disabled and the server rejects the complete request if no path has been chosen.
- If `false`, the user may complete the ticket without choosing a path; all next tickets are then unlocked normally.

**Option labels (`path_choice_names`):**
- Each next-step option can be given a custom display label in the procedure template step editor (e.g. "Approve", "Return for revision").
- These labels are stored in `workflow_ticket_path_choices` keyed by `target_step_id` / `target_ticket_id`.
- The label is what the agent sees on the choice button. If no label is set, the target ticket's name is used as the fallback.
- Labels are copied from the template step's path choices when the procedure is instantiated.

### Sub-Procedures (`has_procedures`)
A ticket with `has_procedures = true` carries a list of **sub-procedure lines** (`TicketProcedureLine`) — each references a `ProcedureTemplate` that must be run as a child procedure beneath this ticket.

**Data model:**
- `TicketProcedureLine` has `ticket_id`, `procedure_template_id`, and `procedure_id` (nullable FK to the running `Procedure`).
- Lines are copied from the procedure template step's `sub_procedures` pivot when the procedure is instantiated.
- `procedure_id` is `null` until the user clicks Start; it is set to the newly created procedure's ID and never changes again (even if the procedure is cancelled — restart creates a new procedure for the same line, overwriting `procedure_id`).

**Starting a sub-procedure:**
- Each line shows a **Start** button if no procedure has been launched yet, or if the previously launched procedure was `closed` (cancelled).
- Clicking Start creates a real `Procedure` from the line's template with:
  - `optional_ticket_id` = the parent ticket's ID
  - `optional_procedure_id` = the procedure the parent ticket belongs to (if any)
- The new procedure's `procedure_id` is written back to the line.
- The user is redirected to the new procedure.

**Parent ticket link on the sub-procedure:**
- Because `optional_ticket_id` is set, the sub-procedure's show page displays a **"Parent Ticket"** row in its metadata, linking back to the originating ticket.
- This lets anyone viewing the sub-procedure understand its context without navigating manually.

**Restarting after cancellation:**
- If a sub-procedure's first ticket is rejected with no other pending tickets (start-ticket rejection → procedure cancelled), the procedure becomes `closed`.
- The Start button re-appears (**Restart**), allowing the user to launch a fresh procedure for that line without being stuck.
- The new procedure replaces the old `procedure_id` on the line.

**`procedures_required` flag:**
- If `true`, the ticket cannot be completed until **every** sub-procedure line has a linked procedure in `completed` state.
- The Complete button is visually disabled and the server rejects the request with an error message if the requirement is not met.
- If `false`, the ticket may be completed regardless of sub-procedure states.

**State shown in the UI:**
| Sub-procedure state | Display |
|---------------------|---------|
| No procedure started | Start button only |
| `pending` (running) | "In Progress" badge + link + no Start button |
| `completed` | "Completed" badge + link |
| `closed` (cancelled) | "Cancelled" badge + link + **Restart** button |

### `ignore_state` Tickets
A ticket with `ignore_state = true` is an optional/informational step. Its state changes (complete, reject, skip) are recorded and logged, but they **never** trigger procedure advancement or cancellation:
- Completing an `ignore_state` ticket does not check whether the procedure should complete.
- Rejecting an `ignore_state` ticket does not reactivate the previous ticket and does not cancel the procedure.
- Skipping an `ignore_state` ticket does not unlock next tickets.
- `ignore_state` tickets are also excluded from the "are all tickets terminal?" check — they are invisible to the procedure state machine.

> `ignore_state` tickets can still be viewed, acted on, and commented on subject to the normal locking rules (procedure must be `pending`, no locked next ticket, etc.).

### Why a ticket cannot be pending while its previous is also pending
The state machine guarantees this cannot happen: a ticket only becomes `pending` when its previous ticket reaches `completed` or `skipped`. At that point the previous ticket is no longer `pending`, so both can never be `pending` simultaneously.

---

## Locking Rules

### When a next ticket is `pending` or `completed`
The previous ticket is effectively locked:
- `update` (metadata, viewer management) → **blocked**
- `comment` (chat) → **blocked**
- `act` is already naturally blocked because the previous ticket's state is `completed` (not `pending`)

This lock is lifted as soon as the next ticket is rejected (which reactivates the previous ticket back to `pending`).

### When the procedure is no longer `pending`
All tickets in that procedure are fully frozen — no `act`, no `update`, no `comment` (chat), regardless of the ticket's own state. Applies to **all users including admins**.

### Archive / Restore (tickets)
- Only allowed when the ticket is **not** `pending`.
- Tickets cannot be deleted.

---

## Access Control

### Roles & Permissions
| Permission | What it allows |
|------------|----------------|
| `workflow.tickets.read` | View tickets |
| `workflow.tickets.write` | Act on, edit, and comment on tickets |
| `workflow.tickets.create` | Create standalone tickets |
| `workflow.procedures.read` | View procedures |
| `workflow.procedures.write` | Manage procedures (cancel, archive, etc.) |
| `workflow.procedures.create` | Start procedures from templates |
| `workflow.admin` | Bypasses viewer checks — sees everything |

### Viewer Tables
- **Tickets** → `workflow_allowed_users` (`record_id`, `record_type='ticket'`, `user_id`)
- **Procedures** → `workflow_procedure_viewers` (`procedure_id`, `user_id`)

All viewer checks also require the user to have an **active** `WorkflowUser` record (`workflow_users.active = true`). A deactivated WorkflowUser is immediately locked out of all tickets and procedures.

### Viewer Seeding
- **Procedure tickets**: viewers are seeded when the ticket is unlocked (`draft`/`rejected`/`skipped` → `pending`). All active WorkflowUsers whose `default_department_id` matches the ticket's `assigned_to_department_id` are added to the ticket's viewers and to the parent procedure's viewers.
- **Draft procedure tickets**: no viewers seeded yet — non-admins cannot view them.
- **Standalone tickets**: creator + department members seeded on creation.

---

## Ticket Policy (TicketPolicy)

#### `view`
- Requires `workflow.tickets.read`.
- Admin bypasses viewer check.
- Draft procedure tickets: always denied to non-admins.
- Non-admin: must be in `workflow_allowed_users` with active WorkflowUser.

#### `act` (state changes, field saves, input saves)
- Ticket must be `pending`.
- If procedure ticket: procedure must be `pending`.
- Requires `workflow.tickets.write`.
- Admin bypasses viewer check; non-admin must be in `workflow_allowed_users`.

#### `update` (metadata, viewer management)
- If procedure ticket: procedure must be `pending`.
- If procedure ticket: no next ticket may be `pending` or `completed`.
- Requires `workflow.tickets.write`.
- Admin bypasses viewer check; non-admin must be in `workflow_allowed_users`.

#### `comment` (chat messages)
- If procedure ticket: procedure must be `pending`.
- If procedure ticket: no next ticket may be `pending` or `completed`.
- Requires `workflow.tickets.write`.
- Admin bypasses viewer check; non-admin must be in `workflow_allowed_users`.

### Locking Summary
| Situation | `act` | `update` | `comment` |
|-----------|-------|----------|-----------|
| Procedure is completed/closed | Blocked | Blocked | Blocked |
| Next ticket is `pending`/`completed` | N/A — current ticket is already `completed` | Blocked | Blocked |
| Ticket is not `pending` | Blocked | Allowed | Allowed |

---

## Procedure Policy (ProcedurePolicy)

- `view`: requires `workflow.procedures.read` + (admin OR active viewer in `workflow_procedure_viewers`)
- `update`: requires `workflow.procedures.write` + (admin OR active viewer)
- `comment`: requires `workflow.procedures.write` + (admin OR active viewer)
- `delete`: always `false` — procedures cannot be deleted

---

## WorkflowUser

- Every system user who participates in workflows must have a `WorkflowUser` record.
- `active = false` → completely locked out: cannot view, act on, or be seeded into any ticket or procedure.
- `default_department_id` → determines which department's tickets they are auto-seeded into as viewers.
- `assignableDepartments` → which departments they can be manually assigned to on a ticket.
- `groups` → organizational grouping (for configuration/reporting).
