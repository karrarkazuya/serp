CREATE TABLE IF NOT EXISTS "migrations"(
  "id" integer primary key autoincrement not null,
  "migration" varchar not null,
  "batch" integer not null
);
CREATE TABLE IF NOT EXISTS "password_reset_tokens"(
  "email" varchar not null,
  "token" varchar not null,
  "created_at" datetime,
  primary key("email")
);
CREATE TABLE IF NOT EXISTS "sessions"(
  "id" varchar not null,
  "user_id" integer,
  "ip_address" varchar,
  "user_agent" text,
  "payload" text not null,
  "last_activity" integer not null,
  primary key("id")
);
CREATE INDEX "sessions_user_id_index" on "sessions"("user_id");
CREATE INDEX "sessions_last_activity_index" on "sessions"("last_activity");
CREATE TABLE IF NOT EXISTS "cache"(
  "key" varchar not null,
  "value" text not null,
  "expiration" integer not null,
  primary key("key")
);
CREATE INDEX "cache_expiration_index" on "cache"("expiration");
CREATE TABLE IF NOT EXISTS "cache_locks"(
  "key" varchar not null,
  "owner" varchar not null,
  "expiration" integer not null,
  primary key("key")
);
CREATE INDEX "cache_locks_expiration_index" on "cache_locks"("expiration");
CREATE TABLE IF NOT EXISTS "jobs"(
  "id" integer primary key autoincrement not null,
  "queue" varchar not null,
  "payload" text not null,
  "attempts" integer not null,
  "reserved_at" integer,
  "available_at" integer not null,
  "created_at" integer not null
);
CREATE INDEX "jobs_queue_index" on "jobs"("queue");
CREATE TABLE IF NOT EXISTS "job_batches"(
  "id" varchar not null,
  "name" varchar not null,
  "total_jobs" integer not null,
  "pending_jobs" integer not null,
  "failed_jobs" integer not null,
  "failed_job_ids" text not null,
  "options" text,
  "cancelled_at" integer,
  "created_at" integer not null,
  "finished_at" integer,
  primary key("id")
);
CREATE TABLE IF NOT EXISTS "failed_jobs"(
  "id" integer primary key autoincrement not null,
  "uuid" varchar not null,
  "connection" varchar not null,
  "queue" varchar not null,
  "payload" text not null,
  "exception" text not null,
  "failed_at" datetime not null default CURRENT_TIMESTAMP
);
CREATE INDEX "failed_jobs_connection_queue_failed_at_index" on "failed_jobs"(
  "connection",
  "queue",
  "failed_at"
);
CREATE UNIQUE INDEX "failed_jobs_uuid_unique" on "failed_jobs"("uuid");
CREATE TABLE IF NOT EXISTS "companies"(
  "id" integer primary key autoincrement not null,
  "uuid" varchar,
  "created_by" integer,
  "updated_by" integer,
  "name" varchar not null,
  "email" varchar,
  "phone" varchar,
  "mobile" varchar,
  "website" varchar,
  "street" varchar,
  "city" varchar,
  "state" varchar,
  "country" varchar,
  "zip" varchar,
  "tax_id" varchar,
  "currency" varchar not null default 'USD',
  "logo" varchar,
  "notes" text,
  "active" tinyint(1) not null default '1',
  "created_at" datetime,
  "updated_at" datetime,
  "accounting_period_lock_date" date,
  "accounting_fiscal_year_lock_date" date,
  "deleted_at" datetime,
  foreign key("created_by") references "users"("id") on delete set null,
  foreign key("updated_by") references "users"("id") on delete set null
);
CREATE INDEX "companies_active_index" on "companies"("active");
CREATE INDEX "companies_name_index" on "companies"("name");
CREATE UNIQUE INDEX "companies_uuid_unique" on "companies"("uuid");
CREATE TABLE IF NOT EXISTS "users"(
  "id" integer primary key autoincrement not null,
  "uuid" varchar,
  "created_by" integer,
  "updated_by" integer,
  "name" varchar not null,
  "email" varchar not null,
  "email_verified_at" datetime,
  "password" varchar not null,
  "active" tinyint(1) not null default('1'),
  "job_position" varchar,
  "phone" varchar,
  "avatar" varchar,
  "language" varchar not null default('en'),
  "remember_token" varchar,
  "created_at" datetime,
  "updated_at" datetime,
  "company_id" integer,
  "deleted_at" datetime,
  foreign key("updated_by") references users("id") on delete set null on update no action,
  foreign key("created_by") references users("id") on delete set null on update no action,
  foreign key("company_id") references "companies"("id") on delete set null
);
CREATE UNIQUE INDEX "users_email_unique" on "users"("email");
CREATE UNIQUE INDEX "users_uuid_unique" on "users"("uuid");
CREATE TABLE IF NOT EXISTS "roles"(
  "id" integer primary key autoincrement not null,
  "uuid" varchar,
  "created_by" integer,
  "updated_by" integer,
  "name" varchar not null,
  "key" varchar not null,
  "description" varchar,
  "active" tinyint(1) not null default '1',
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("created_by") references "users"("id") on delete set null,
  foreign key("updated_by") references "users"("id") on delete set null
);
CREATE UNIQUE INDEX "roles_uuid_unique" on "roles"("uuid");
CREATE UNIQUE INDEX "roles_name_unique" on "roles"("name");
CREATE UNIQUE INDEX "roles_key_unique" on "roles"("key");
CREATE TABLE IF NOT EXISTS "permissions"(
  "id" integer primary key autoincrement not null,
  "uuid" varchar,
  "created_by" integer,
  "updated_by" integer,
  "name" varchar not null,
  "key" varchar not null,
  "module" varchar not null,
  "description" varchar,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("created_by") references "users"("id") on delete set null,
  foreign key("updated_by") references "users"("id") on delete set null
);
CREATE UNIQUE INDEX "permissions_uuid_unique" on "permissions"("uuid");
CREATE UNIQUE INDEX "permissions_key_unique" on "permissions"("key");
CREATE TABLE IF NOT EXISTS "role_permission"(
  "id" integer primary key autoincrement not null,
  "role_id" integer not null,
  "permission_id" integer not null,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("role_id") references "roles"("id") on delete cascade,
  foreign key("permission_id") references "permissions"("id") on delete cascade
);
CREATE UNIQUE INDEX "role_permission_role_id_permission_id_unique" on "role_permission"(
  "role_id",
  "permission_id"
);
CREATE TABLE IF NOT EXISTS "user_role"(
  "id" integer primary key autoincrement not null,
  "user_id" integer not null,
  "role_id" integer not null,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("user_id") references "users"("id") on delete cascade,
  foreign key("role_id") references "roles"("id") on delete cascade
);
CREATE UNIQUE INDEX "user_role_user_id_role_id_unique" on "user_role"(
  "user_id",
  "role_id"
);
CREATE TABLE IF NOT EXISTS "user_company"(
  "id" integer primary key autoincrement not null,
  "user_id" integer not null,
  "company_id" integer not null,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("user_id") references "users"("id") on delete cascade,
  foreign key("company_id") references "companies"("id") on delete cascade
);
CREATE UNIQUE INDEX "user_company_user_id_company_id_unique" on "user_company"(
  "user_id",
  "company_id"
);
CREATE TABLE IF NOT EXISTS "tags"(
  "id" integer primary key autoincrement not null,
  "uuid" varchar,
  "created_by" integer,
  "updated_by" integer,
  "name" varchar not null,
  "color" varchar not null default '#8B5CF6',
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("created_by") references "users"("id") on delete set null,
  foreign key("updated_by") references "users"("id") on delete set null
);
CREATE UNIQUE INDEX "tags_uuid_unique" on "tags"("uuid");
CREATE TABLE IF NOT EXISTS "contacts"(
  "id" integer primary key autoincrement not null,
  "uuid" varchar,
  "company_id" integer,
  "parent_id" integer,
  "created_by" integer,
  "updated_by" integer,
  "name" varchar not null,
  "company_name" varchar,
  "contact_type" varchar check("contact_type" in('individual', 'company')) not null default 'individual',
  "email" varchar,
  "website" varchar,
  "street" varchar,
  "city" varchar,
  "state" varchar,
  "country" varchar,
  "zip" varchar,
  "tax_id" varchar,
  "job_position" varchar,
  "notes" text,
  "avatar" varchar,
  "active" tinyint(1) not null default '1',
  "deleted_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("company_id") references "companies"("id") on delete set null,
  foreign key("parent_id") references "contacts"("id") on delete set null,
  foreign key("created_by") references "users"("id") on delete set null,
  foreign key("updated_by") references "users"("id") on delete set null
);
CREATE INDEX "contacts_active_contact_type_index" on "contacts"(
  "active",
  "contact_type"
);
CREATE INDEX "contacts_email_index" on "contacts"("email");
CREATE INDEX "contacts_name_index" on "contacts"("name");
CREATE INDEX "contacts_company_id_index" on "contacts"("company_id");
CREATE UNIQUE INDEX "contacts_uuid_unique" on "contacts"("uuid");
CREATE TABLE IF NOT EXISTS "contact_phones"(
  "id" integer primary key autoincrement not null,
  "contact_id" integer not null,
  "phone" varchar not null,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("contact_id") references "contacts"("id") on delete cascade
);
CREATE INDEX "contact_phones_contact_id_index" on "contact_phones"(
  "contact_id"
);
CREATE UNIQUE INDEX "contact_phones_phone_unique" on "contact_phones"("phone");
CREATE TABLE IF NOT EXISTS "contact_tag"(
  "contact_id" integer not null,
  "tag_id" integer not null,
  foreign key("contact_id") references "contacts"("id") on delete cascade,
  foreign key("tag_id") references "tags"("id") on delete cascade,
  primary key("contact_id", "tag_id")
);
CREATE TABLE IF NOT EXISTS "chatter_messages"(
  "id" integer primary key autoincrement not null,
  "uuid" varchar,
  "created_by" integer,
  "updated_by" integer,
  "model_type" varchar not null,
  "model_id" integer not null,
  "user_id" integer,
  "message_type" varchar check("message_type" in('log', 'comment', 'system')) not null default 'log',
  "body" text not null,
  "metadata" text,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("created_by") references "users"("id") on delete set null,
  foreign key("updated_by") references "users"("id") on delete set null,
  foreign key("user_id") references "users"("id") on delete set null
);
CREATE INDEX "chatter_messages_model_type_model_id_index" on "chatter_messages"(
  "model_type",
  "model_id"
);
CREATE INDEX "chatter_messages_message_type_index" on "chatter_messages"(
  "message_type"
);
CREATE UNIQUE INDEX "chatter_messages_uuid_unique" on "chatter_messages"(
  "uuid"
);
CREATE TABLE IF NOT EXISTS "settings"(
  "id" integer primary key autoincrement not null,
  "uuid" varchar,
  "created_by" integer,
  "updated_by" integer,
  "key" varchar not null,
  "value" text,
  "group" varchar not null default 'general',
  "type" varchar not null default 'string',
  "label" varchar,
  "description" varchar,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("created_by") references "users"("id") on delete set null,
  foreign key("updated_by") references "users"("id") on delete set null
);
CREATE UNIQUE INDEX "settings_uuid_unique" on "settings"("uuid");
CREATE UNIQUE INDEX "settings_key_unique" on "settings"("key");
CREATE TABLE IF NOT EXISTS "personal_access_tokens"(
  "id" integer primary key autoincrement not null,
  "tokenable_type" varchar not null,
  "tokenable_id" integer not null,
  "name" text not null,
  "token" varchar not null,
  "abilities" text,
  "last_used_at" datetime,
  "expires_at" datetime,
  "created_at" datetime,
  "updated_at" datetime
);
CREATE INDEX "personal_access_tokens_tokenable_type_tokenable_id_index" on "personal_access_tokens"(
  "tokenable_type",
  "tokenable_id"
);
CREATE UNIQUE INDEX "personal_access_tokens_token_unique" on "personal_access_tokens"(
  "token"
);
CREATE INDEX "personal_access_tokens_expires_at_index" on "personal_access_tokens"(
  "expires_at"
);
CREATE TABLE IF NOT EXISTS "notifications"(
  "id" integer primary key autoincrement not null,
  "uuid" varchar,
  "user_id" integer not null,
  "title" varchar not null,
  "body" text,
  "url" varchar,
  "seen_at" datetime,
  "created_by" integer,
  "updated_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("user_id") references "users"("id") on delete cascade
);
CREATE UNIQUE INDEX "notifications_uuid_unique" on "notifications"("uuid");
CREATE TABLE IF NOT EXISTS "files"(
  "id" integer primary key autoincrement not null,
  "uuid" varchar not null,
  "disk" varchar not null default 'local',
  "path" varchar not null,
  "thumbnail_path" varchar,
  "original_name" varchar not null,
  "mime_type" varchar not null,
  "extension" varchar not null default '',
  "size" integer not null,
  "permission_key" varchar,
  "context_type" varchar,
  "context_id" integer,
  "source_type" varchar,
  "source_id" integer,
  "created_by" integer,
  "updated_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("created_by") references "users"("id") on delete set null,
  foreign key("updated_by") references "users"("id") on delete set null
);
CREATE INDEX "files_context_type_context_id_index" on "files"(
  "context_type",
  "context_id"
);
CREATE INDEX "files_source_index" on "files"("source_type", "source_id");
CREATE UNIQUE INDEX "files_uuid_unique" on "files"("uuid");
CREATE TABLE IF NOT EXISTS "chat_rooms"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "type" varchar not null default 'channel',
  "description" varchar,
  "created_by_user_id" integer,
  "active" tinyint(1) not null default '1',
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("created_by_user_id") references "users"("id") on delete set null
);
CREATE TABLE IF NOT EXISTS "chat_messages"(
  "id" integer primary key autoincrement not null,
  "room_id" integer not null,
  "user_id" integer,
  "body" text,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("room_id") references "chat_rooms"("id") on delete cascade,
  foreign key("user_id") references "users"("id") on delete set null
);
CREATE TABLE IF NOT EXISTS "chat_message_files"(
  "id" integer primary key autoincrement not null,
  "uuid" varchar not null,
  "message_id" integer not null,
  "disk" varchar not null default 'local',
  "path" varchar not null,
  "original_name" varchar not null,
  "mime_type" varchar not null,
  "size" integer not null,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("message_id") references "chat_messages"("id") on delete cascade
);
CREATE UNIQUE INDEX "chat_message_files_uuid_unique" on "chat_message_files"(
  "uuid"
);
CREATE TABLE IF NOT EXISTS "chat_room_members"(
  "id" integer primary key autoincrement not null,
  "room_id" integer not null,
  "user_id" integer not null,
  "last_read_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("room_id") references "chat_rooms"("id") on delete cascade,
  foreign key("user_id") references "users"("id") on delete cascade
);
CREATE UNIQUE INDEX "chat_room_members_room_id_user_id_unique" on "chat_room_members"(
  "room_id",
  "user_id"
);
CREATE TABLE IF NOT EXISTS "workflow_groups"(
  "id" integer primary key autoincrement not null,
  "uuid" varchar,
  "name" varchar not null,
  "active" tinyint(1) not null default '1',
  "created_by" integer,
  "updated_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("created_by") references "users"("id") on delete set null,
  foreign key("updated_by") references "users"("id") on delete set null
);
CREATE UNIQUE INDEX "workflow_groups_uuid_unique" on "workflow_groups"("uuid");
CREATE TABLE IF NOT EXISTS "workflow_users"(
  "id" integer primary key autoincrement not null,
  "uuid" varchar,
  "user_id" integer not null,
  "default_department_id" integer,
  "active" tinyint(1) not null default '1',
  "created_by" integer,
  "updated_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("user_id") references "users"("id") on delete cascade,
  foreign key("default_department_id") references "hr_departments"("id") on delete set null,
  foreign key("created_by") references "users"("id") on delete set null,
  foreign key("updated_by") references "users"("id") on delete set null
);
CREATE UNIQUE INDEX "workflow_users_uuid_unique" on "workflow_users"("uuid");
CREATE UNIQUE INDEX "workflow_users_user_id_unique" on "workflow_users"(
  "user_id"
);
CREATE TABLE IF NOT EXISTS "workflow_user_group"(
  "workflow_user_id" integer not null,
  "workflow_group_id" integer not null,
  foreign key("workflow_user_id") references "workflow_users"("id") on delete cascade,
  foreign key("workflow_group_id") references "workflow_groups"("id") on delete cascade,
  primary key("workflow_user_id", "workflow_group_id")
);
CREATE TABLE IF NOT EXISTS "workflow_user_dept_assign"(
  "workflow_user_id" integer not null,
  "workflow_department_id" integer not null,
  foreign key("workflow_user_id") references "workflow_users"("id") on delete cascade,
  foreign key("workflow_department_id") references "hr_departments"("id") on delete cascade,
  primary key("workflow_user_id", "workflow_department_id")
);
CREATE TABLE IF NOT EXISTS "workflow_managers"(
  "id" integer primary key autoincrement not null,
  "uuid" varchar,
  "workflow_user_id" integer not null,
  "active" tinyint(1) not null default '1',
  "created_by" integer,
  "updated_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("workflow_user_id") references "workflow_users"("id") on delete cascade,
  foreign key("created_by") references "users"("id") on delete set null,
  foreign key("updated_by") references "users"("id") on delete set null
);
CREATE UNIQUE INDEX "workflow_managers_uuid_unique" on "workflow_managers"(
  "uuid"
);
CREATE TABLE IF NOT EXISTS "workflow_manager_department"(
  "workflow_manager_id" integer not null,
  "workflow_department_id" integer not null,
  foreign key("workflow_manager_id") references "workflow_managers"("id") on delete cascade,
  foreign key("workflow_department_id") references "hr_departments"("id") on delete cascade,
  primary key("workflow_manager_id", "workflow_department_id")
);
CREATE TABLE IF NOT EXISTS "workflow_ticket_templates"(
  "id" integer primary key autoincrement not null,
  "uuid" varchar,
  "name" varchar not null,
  "description" text,
  "default_group_id" integer,
  "default_department_id" integer,
  "resolve_max_duration" integer not null default '168',
  "enabled" tinyint(1) not null default '0',
  "active" tinyint(1) not null default '1',
  "created_by" integer,
  "updated_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("default_group_id") references "workflow_groups"("id") on delete set null,
  foreign key("default_department_id") references "hr_departments"("id") on delete set null,
  foreign key("created_by") references "users"("id") on delete set null,
  foreign key("updated_by") references "users"("id") on delete set null
);
CREATE UNIQUE INDEX "workflow_ticket_templates_uuid_unique" on "workflow_ticket_templates"(
  "uuid"
);
CREATE TABLE IF NOT EXISTS "workflow_ticket_template_department"(
  "ticket_template_id" integer not null,
  "workflow_department_id" integer not null,
  foreign key("ticket_template_id") references "workflow_ticket_templates"("id") on delete cascade,
  foreign key("workflow_department_id") references "hr_departments"("id") on delete cascade,
  primary key("ticket_template_id", "workflow_department_id")
);
CREATE TABLE IF NOT EXISTS "workflow_procedure_templates"(
  "id" integer primary key autoincrement not null,
  "uuid" varchar,
  "name" varchar not null,
  "description" text,
  "default_group_id" integer,
  "creator_see_tasks" tinyint(1) not null default '0',
  "enabled" tinyint(1) not null default '0',
  "active" tinyint(1) not null default '1',
  "created_by" integer,
  "updated_by" integer,
  "flowchart_sub_positions" text,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("default_group_id") references "workflow_groups"("id") on delete set null,
  foreign key("created_by") references "users"("id") on delete set null,
  foreign key("updated_by") references "users"("id") on delete set null
);
CREATE UNIQUE INDEX "workflow_procedure_templates_uuid_unique" on "workflow_procedure_templates"(
  "uuid"
);
CREATE TABLE IF NOT EXISTS "workflow_procedure_template_department"(
  "procedure_template_id" integer not null,
  "workflow_department_id" integer not null,
  foreign key("procedure_template_id") references "workflow_procedure_templates"("id") on delete cascade,
  foreign key("workflow_department_id") references "hr_departments"("id") on delete cascade,
  primary key("procedure_template_id", "workflow_department_id")
);
CREATE TABLE IF NOT EXISTS "workflow_procedure_steps"(
  "id" integer primary key autoincrement not null,
  "uuid" varchar,
  "procedure_template_id" integer not null,
  "name" varchar not null,
  "description" text,
  "default_department_id" integer,
  "is_approve_only" tinyint(1) not null default '0',
  "has_procedures" tinyint(1) not null default '0',
  "procedures_required" tinyint(1) not null default '0',
  "ignore_state" tinyint(1) not null default '0',
  "has_path_choice" tinyint(1) not null default '0',
  "path_choice_question" varchar,
  "path_choice_required" tinyint(1) not null default '0',
  "flowchart_x" integer not null default '0',
  "flowchart_y" integer not null default '0',
  "flowchart_position_saved" tinyint(1) not null default '0',
  "enabled" tinyint(1) not null default '1',
  "active" tinyint(1) not null default '1',
  "created_by" integer,
  "updated_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("procedure_template_id") references "workflow_procedure_templates"("id") on delete cascade,
  foreign key("default_department_id") references "hr_departments"("id") on delete set null,
  foreign key("created_by") references "users"("id") on delete set null,
  foreign key("updated_by") references "users"("id") on delete set null
);
CREATE UNIQUE INDEX "workflow_procedure_steps_uuid_unique" on "workflow_procedure_steps"(
  "uuid"
);
CREATE TABLE IF NOT EXISTS "workflow_procedure_step_next"(
  "step_id" integer not null,
  "next_step_id" integer not null,
  foreign key("step_id") references "workflow_procedure_steps"("id") on delete cascade,
  foreign key("next_step_id") references "workflow_procedure_steps"("id") on delete cascade,
  primary key("step_id", "next_step_id")
);
CREATE TABLE IF NOT EXISTS "workflow_procedure_step_paths"(
  "id" integer primary key autoincrement not null,
  "step_id" integer not null,
  "target_step_id" integer not null,
  "name" varchar,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("step_id") references "workflow_procedure_steps"("id") on delete cascade,
  foreign key("target_step_id") references "workflow_procedure_steps"("id") on delete cascade
);
CREATE TABLE IF NOT EXISTS "workflow_procedure_step_sub_proc"(
  "step_id" integer not null,
  "procedure_template_id" integer not null,
  foreign key("step_id") references "workflow_procedure_steps"("id") on delete cascade,
  foreign key("procedure_template_id") references "workflow_procedure_templates"("id") on delete cascade,
  primary key("step_id", "procedure_template_id")
);
CREATE TABLE IF NOT EXISTS "workflow_procedures"(
  "id" integer primary key autoincrement not null,
  "uuid" varchar,
  "procedure_template_id" integer not null,
  "company_id" integer,
  "name" varchar not null,
  "description" text,
  "state" varchar not null default 'pending',
  "created_by_user_id" integer,
  "optional_contact_id" integer,
  "optional_ticket_id" integer,
  "optional_procedure_id" integer,
  "active" tinyint(1) not null default '1',
  "created_by" integer,
  "updated_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("procedure_template_id") references "workflow_procedure_templates"("id") on delete restrict,
  foreign key("company_id") references "companies"("id") on delete set null,
  foreign key("created_by_user_id") references "users"("id") on delete set null,
  foreign key("optional_contact_id") references "contacts"("id") on delete set null,
  foreign key("created_by") references "users"("id") on delete set null,
  foreign key("updated_by") references "users"("id") on delete set null
);
CREATE UNIQUE INDEX "workflow_procedures_uuid_unique" on "workflow_procedures"(
  "uuid"
);
CREATE TABLE IF NOT EXISTS "workflow_procedure_viewers"(
  "procedure_id" integer not null,
  "user_id" integer not null,
  foreign key("procedure_id") references "workflow_procedures"("id") on delete cascade,
  foreign key("user_id") references "users"("id") on delete cascade,
  primary key("procedure_id", "user_id")
);
CREATE TABLE IF NOT EXISTS "workflow_ticket_durations"(
  "id" integer primary key autoincrement not null,
  "ticket_id" integer not null,
  "department_id" integer,
  "user_id" integer,
  "duration" numeric not null default '0',
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("ticket_id") references "workflow_tickets"("id") on delete cascade,
  foreign key("department_id") references "hr_departments"("id") on delete set null,
  foreign key("user_id") references "users"("id") on delete set null
);
CREATE TABLE IF NOT EXISTS "workflow_ticket_next"(
  "ticket_id" integer not null,
  "next_ticket_id" integer not null,
  foreign key("ticket_id") references "workflow_tickets"("id") on delete cascade,
  foreign key("next_ticket_id") references "workflow_tickets"("id") on delete cascade,
  primary key("ticket_id", "next_ticket_id")
);
CREATE TABLE IF NOT EXISTS "workflow_ticket_paths"(
  "id" integer primary key autoincrement not null,
  "ticket_id" integer not null,
  "target_ticket_id" integer not null,
  "name" varchar,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("ticket_id") references "workflow_tickets"("id") on delete cascade,
  foreign key("target_ticket_id") references "workflow_tickets"("id") on delete cascade
);
CREATE TABLE IF NOT EXISTS "workflow_tickets"(
  "id" integer primary key autoincrement not null,
  "chat_room_id" integer,
  "uuid" varchar,
  "template_id" integer,
  "procedure_id" integer,
  "procedure_step_id" integer,
  "previous_ticket_id" integer,
  "company_id" integer,
  "name" varchar not null,
  "description" text,
  "state" varchar not null default('pending'),
  "priority" varchar not null default('1'),
  "assigned_to_department_id" integer,
  "assigned_to_user_id" integer,
  "created_by_user_id" integer,
  "resolve_max_duration" integer not null default('168'),
  "resolve_deadline" datetime,
  "resolve_duration" integer not null default('0'),
  "resolve_deadline_passed" integer not null default('0'),
  "share_enabled" tinyint(1) not null default('0'),
  "share_token" varchar,
  "is_approve_only" tinyint(1) not null default('0'),
  "has_path_choice" tinyint(1) not null default('0'),
  "path_choice_question" varchar,
  "path_choice_required" tinyint(1) not null default('0'),
  "has_procedures" tinyint(1) not null default('0'),
  "procedures_required" tinyint(1) not null default('0'),
  "ignore_state" tinyint(1) not null default('0'),
  "return_reason" text,
  "unlock_at" datetime,
  "finished_creation" tinyint(1) not null default('0'),
  "optional_contact_id" integer,
  "optional_ticket_id" integer,
  "active" tinyint(1) not null default('1'),
  "created_by" integer,
  "updated_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  "path_chosen_id" integer,
  "deleted_at" datetime,
  foreign key("previous_ticket_id") references workflow_tickets("id") on delete set null on update no action,
  foreign key("chat_room_id") references chat_rooms("id") on delete set null on update no action,
  foreign key("template_id") references workflow_ticket_templates("id") on delete restrict on update no action,
  foreign key("procedure_id") references workflow_procedures("id") on delete set null on update no action,
  foreign key("procedure_step_id") references workflow_procedure_steps("id") on delete set null on update no action,
  foreign key("company_id") references companies("id") on delete set null on update no action,
  foreign key("assigned_to_department_id") references hr_departments("id") on delete set null on update no action,
  foreign key("assigned_to_user_id") references users("id") on delete set null on update no action,
  foreign key("created_by_user_id") references users("id") on delete set null on update no action,
  foreign key("optional_contact_id") references contacts("id") on delete set null on update no action,
  foreign key("optional_ticket_id") references workflow_tickets("id") on delete set null on update no action,
  foreign key("created_by") references users("id") on delete set null on update no action,
  foreign key("updated_by") references users("id") on delete set null on update no action,
  foreign key("path_chosen_id") references "workflow_ticket_paths"("id") on delete set null
);
CREATE UNIQUE INDEX "workflow_tickets_share_token_unique" on "workflow_tickets"(
  "share_token"
);
CREATE UNIQUE INDEX "workflow_tickets_uuid_unique" on "workflow_tickets"(
  "uuid"
);
CREATE TABLE IF NOT EXISTS "workflow_ticket_procedure_lines"(
  "id" integer primary key autoincrement not null,
  "uuid" varchar,
  "ticket_id" integer not null,
  "procedure_template_id" integer not null,
  "procedure_id" integer,
  "name" varchar not null,
  "state" varchar not null default 'pending',
  "active" tinyint(1) not null default '1',
  "created_by" integer,
  "updated_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("ticket_id") references "workflow_tickets"("id") on delete cascade,
  foreign key("procedure_template_id") references "workflow_procedure_templates"("id") on delete restrict,
  foreign key("created_by") references "users"("id") on delete set null,
  foreign key("updated_by") references "users"("id") on delete set null
);
CREATE UNIQUE INDEX "workflow_ticket_procedure_lines_uuid_unique" on "workflow_ticket_procedure_lines"(
  "uuid"
);
CREATE TABLE IF NOT EXISTS "workflow_shared_links"(
  "id" integer primary key autoincrement not null,
  "uuid" varchar,
  "shareable_type" varchar not null,
  "shareable_id" integer not null,
  "token" varchar not null,
  "message" text,
  "enabled" tinyint(1) not null default '0',
  "created_by" integer,
  "updated_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime
);
CREATE INDEX "workflow_shared_links_shareable_type_shareable_id_index" on "workflow_shared_links"(
  "shareable_type",
  "shareable_id"
);
CREATE UNIQUE INDEX "workflow_shared_links_uuid_unique" on "workflow_shared_links"(
  "uuid"
);
CREATE UNIQUE INDEX "workflow_shared_links_token_unique" on "workflow_shared_links"(
  "token"
);
CREATE TABLE IF NOT EXISTS "workflow_allowed_users"(
  "id" integer primary key autoincrement not null,
  "user_id" integer not null,
  "record_id" integer not null,
  "record_type" varchar not null,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("user_id") references "users"("id") on delete cascade
);
CREATE UNIQUE INDEX "workflow_allowed_users_user_id_record_id_record_type_unique" on "workflow_allowed_users"(
  "user_id",
  "record_id",
  "record_type"
);
CREATE INDEX "wau_record_user_idx" on "workflow_allowed_users"(
  "record_id",
  "record_type",
  "user_id"
);
CREATE TABLE IF NOT EXISTS "workflow_template_inputs"(
  "id" integer primary key autoincrement not null,
  "uuid" varchar,
  "owner_id" integer not null,
  "owner_type" varchar not null,
  "name" varchar not null,
  "type" varchar not null default 'char',
  "is_required" tinyint(1) not null default '1',
  "sort_order" integer not null default '0',
  "active" tinyint(1) not null default '1',
  "created_by" integer,
  "updated_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("created_by") references "users"("id") on delete set null,
  foreign key("updated_by") references "users"("id") on delete set null
);
CREATE INDEX "wti_owner_idx" on "workflow_template_inputs"(
  "owner_id",
  "owner_type"
);
CREATE UNIQUE INDEX "workflow_template_inputs_uuid_unique" on "workflow_template_inputs"(
  "uuid"
);
CREATE TABLE IF NOT EXISTS "workflow_template_input_options"(
  "id" integer primary key autoincrement not null,
  "template_input_id" integer not null,
  "name" varchar not null,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("template_input_id") references "workflow_template_inputs"("id") on delete cascade
);
CREATE TABLE IF NOT EXISTS "workflow_record_inputs"(
  "id" integer primary key autoincrement not null,
  "uuid" varchar,
  "record_id" integer not null,
  "record_type" varchar not null,
  "template_input_id" integer,
  "name" varchar not null,
  "type" varchar not null default 'char',
  "value_char" varchar,
  "value_int" integer,
  "value_date" date,
  "value_datetime" datetime,
  "value_boolean" tinyint(1) not null default '0',
  "value_float" numeric,
  "value_text" text,
  "value_file_path" varchar,
  "value_file_name" varchar,
  "value_file_mime" varchar,
  "value_file_size" integer,
  "value_select_id" integer,
  "is_required" tinyint(1) not null default '1',
  "active" tinyint(1) not null default '1',
  "created_by" integer,
  "updated_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("template_input_id") references "workflow_template_inputs"("id") on delete set null,
  foreign key("value_select_id") references "workflow_template_input_options"("id") on delete set null,
  foreign key("created_by") references "users"("id") on delete set null,
  foreign key("updated_by") references "users"("id") on delete set null
);
CREATE INDEX "wri_record_idx" on "workflow_record_inputs"(
  "record_id",
  "record_type"
);
CREATE UNIQUE INDEX "workflow_record_inputs_uuid_unique" on "workflow_record_inputs"(
  "uuid"
);
CREATE TABLE IF NOT EXISTS "workflow_record_input_multiselect"(
  "record_input_id" integer not null,
  "option_id" integer not null,
  foreign key("record_input_id") references "workflow_record_inputs"("id") on delete cascade,
  foreign key("option_id") references "workflow_template_input_options"("id") on delete cascade,
  primary key("record_input_id", "option_id")
);
CREATE TABLE IF NOT EXISTS "hr_departure_reasons"(
  "id" integer primary key autoincrement not null,
  "uuid" varchar not null,
  "name" varchar not null,
  "active" tinyint(1) not null default '1',
  "created_by" integer,
  "updated_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("created_by") references "users"("id") on delete set null,
  foreign key("updated_by") references "users"("id") on delete set null
);
CREATE UNIQUE INDEX "hr_departure_reasons_uuid_unique" on "hr_departure_reasons"(
  "uuid"
);
CREATE TABLE IF NOT EXISTS "hr_employee_categories"(
  "id" integer primary key autoincrement not null,
  "uuid" varchar not null,
  "name" varchar not null,
  "color" varchar not null default '#6366f1',
  "active" tinyint(1) not null default '1',
  "created_by" integer,
  "updated_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("created_by") references "users"("id") on delete set null,
  foreign key("updated_by") references "users"("id") on delete set null
);
CREATE UNIQUE INDEX "hr_employee_categories_uuid_unique" on "hr_employee_categories"(
  "uuid"
);
CREATE TABLE IF NOT EXISTS "hr_jobs"(
  "id" integer primary key autoincrement not null,
  "uuid" varchar not null,
  "name" varchar not null,
  "description" text,
  "requirements" text,
  "expected_employees" integer not null default '1',
  "no_of_recruitment" integer not null default '0',
  "state" varchar check("state" in('open', 'recruitment', 'closed')) not null default 'open',
  "active" tinyint(1) not null default '1',
  "company_id" integer,
  "department_id" integer,
  "created_by" integer,
  "updated_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("company_id") references "companies"("id") on delete set null,
  foreign key("department_id") references "hr_departments"("id") on delete set null,
  foreign key("created_by") references "users"("id") on delete set null,
  foreign key("updated_by") references "users"("id") on delete set null
);
CREATE INDEX "hr_jobs_active_company_id_index" on "hr_jobs"(
  "active",
  "company_id"
);
CREATE UNIQUE INDEX "hr_jobs_uuid_unique" on "hr_jobs"("uuid");
CREATE TABLE IF NOT EXISTS "hr_work_locations"(
  "id" integer primary key autoincrement not null,
  "uuid" varchar not null,
  "name" varchar not null,
  "address" text,
  "latitude" numeric,
  "longitude" numeric,
  "active" tinyint(1) not null default '1',
  "company_id" integer,
  "created_by" integer,
  "updated_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("company_id") references "companies"("id") on delete set null,
  foreign key("created_by") references "users"("id") on delete set null,
  foreign key("updated_by") references "users"("id") on delete set null
);
CREATE INDEX "hr_work_locations_active_company_id_index" on "hr_work_locations"(
  "active",
  "company_id"
);
CREATE UNIQUE INDEX "hr_work_locations_uuid_unique" on "hr_work_locations"(
  "uuid"
);
CREATE TABLE IF NOT EXISTS "hr_resource_calendars"(
  "id" integer primary key autoincrement not null,
  "uuid" varchar not null,
  "name" varchar not null,
  "timezone" varchar not null default 'UTC',
  "hours_per_day" numeric not null default '8',
  "company_hours_per_week" numeric,
  "flexible_hours" tinyint(1) not null default '0',
  "active" tinyint(1) not null default '1',
  "company_id" integer,
  "created_by" integer,
  "updated_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("company_id") references "companies"("id") on delete set null,
  foreign key("created_by") references "users"("id") on delete set null,
  foreign key("updated_by") references "users"("id") on delete set null
);
CREATE INDEX "hr_resource_calendars_active_company_id_index" on "hr_resource_calendars"(
  "active",
  "company_id"
);
CREATE UNIQUE INDEX "hr_resource_calendars_uuid_unique" on "hr_resource_calendars"(
  "uuid"
);
CREATE TABLE IF NOT EXISTS "hr_resource_calendar_attendances"(
  "id" integer primary key autoincrement not null,
  "calendar_id" integer not null,
  "day_of_week" integer not null,
  "hour_from" numeric not null default '9',
  "hour_to" numeric not null default '17',
  "day_period" varchar check("day_period" in('morning', 'afternoon', 'evening')) not null default 'morning',
  "sequence" integer not null default '0',
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("calendar_id") references "hr_resource_calendars"("id") on delete cascade
);
CREATE INDEX "hr_resource_calendar_attendances_calendar_id_index" on "hr_resource_calendar_attendances"(
  "calendar_id"
);
CREATE TABLE IF NOT EXISTS "hr_skill_types"(
  "id" integer primary key autoincrement not null,
  "uuid" varchar not null,
  "name" varchar not null,
  "active" tinyint(1) not null default '1',
  "created_by" integer,
  "updated_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("created_by") references "users"("id") on delete set null,
  foreign key("updated_by") references "users"("id") on delete set null
);
CREATE UNIQUE INDEX "hr_skill_types_uuid_unique" on "hr_skill_types"("uuid");
CREATE TABLE IF NOT EXISTS "hr_skills"(
  "id" integer primary key autoincrement not null,
  "uuid" varchar not null,
  "name" varchar not null,
  "active" tinyint(1) not null default '1',
  "skill_type_id" integer not null,
  "created_by" integer,
  "updated_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("skill_type_id") references "hr_skill_types"("id") on delete cascade,
  foreign key("created_by") references "users"("id") on delete set null,
  foreign key("updated_by") references "users"("id") on delete set null
);
CREATE INDEX "hr_skills_skill_type_id_index" on "hr_skills"("skill_type_id");
CREATE UNIQUE INDEX "hr_skills_uuid_unique" on "hr_skills"("uuid");
CREATE TABLE IF NOT EXISTS "hr_skill_levels"(
  "id" integer primary key autoincrement not null,
  "uuid" varchar not null,
  "name" varchar not null,
  "level_progress" integer not null default '0',
  "sequence" integer not null default '0',
  "skill_type_id" integer not null,
  "created_by" integer,
  "updated_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("skill_type_id") references "hr_skill_types"("id") on delete cascade,
  foreign key("created_by") references "users"("id") on delete set null,
  foreign key("updated_by") references "users"("id") on delete set null
);
CREATE INDEX "hr_skill_levels_skill_type_id_index" on "hr_skill_levels"(
  "skill_type_id"
);
CREATE UNIQUE INDEX "hr_skill_levels_uuid_unique" on "hr_skill_levels"("uuid");
CREATE TABLE IF NOT EXISTS "hr_employee_category_rel"(
  "employee_id" integer not null,
  "category_id" integer not null,
  foreign key("employee_id") references "hr_employees"("id") on delete cascade,
  foreign key("category_id") references "hr_employee_categories"("id") on delete cascade,
  primary key("employee_id", "category_id")
);
CREATE TABLE IF NOT EXISTS "hr_employee_skills"(
  "id" integer primary key autoincrement not null,
  "employee_id" integer not null,
  "skill_id" integer not null,
  "skill_type_id" integer not null,
  "skill_level_id" integer,
  "years_of_experience" numeric,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("employee_id") references "hr_employees"("id") on delete cascade,
  foreign key("skill_id") references "hr_skills"("id") on delete cascade,
  foreign key("skill_type_id") references "hr_skill_types"("id") on delete cascade,
  foreign key("skill_level_id") references "hr_skill_levels"("id") on delete set null
);
CREATE INDEX "hr_employee_skills_employee_id_index" on "hr_employee_skills"(
  "employee_id"
);
CREATE UNIQUE INDEX "hr_employee_skills_employee_id_skill_id_unique" on "hr_employee_skills"(
  "employee_id",
  "skill_id"
);
CREATE TABLE IF NOT EXISTS "hr_contracts"(
  "id" integer primary key autoincrement not null,
  "uuid" varchar not null,
  "name" varchar not null,
  "employee_id" integer not null,
  "department_id" integer,
  "job_id" integer,
  "company_id" integer,
  "resource_calendar_id" integer,
  "date_start" date,
  "date_end" date,
  "trial_date_start" date,
  "trial_date_end" date,
  "state" varchar check("state" in('draft', 'open', 'close', 'cancelled')) not null default 'draft',
  "contract_type" varchar check("contract_type" in('full_time', 'part_time', 'temporary', 'internship', 'contractor')) not null default 'full_time',
  "wage" numeric,
  "currency" varchar not null default 'IQD',
  "notes" text,
  "image" varchar,
  "created_by" integer,
  "updated_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("employee_id") references "hr_employees"("id") on delete cascade,
  foreign key("department_id") references "hr_departments"("id") on delete set null,
  foreign key("job_id") references "hr_jobs"("id") on delete set null,
  foreign key("company_id") references "companies"("id") on delete set null,
  foreign key("resource_calendar_id") references "hr_resource_calendars"("id") on delete set null,
  foreign key("created_by") references "users"("id") on delete set null,
  foreign key("updated_by") references "users"("id") on delete set null
);
CREATE INDEX "hr_contracts_employee_id_state_index" on "hr_contracts"(
  "employee_id",
  "state"
);
CREATE INDEX "hr_contracts_company_id_state_index" on "hr_contracts"(
  "company_id",
  "state"
);
CREATE UNIQUE INDEX "hr_contracts_uuid_unique" on "hr_contracts"("uuid");
CREATE TABLE IF NOT EXISTS "hr_employee_documents"(
  "id" integer primary key autoincrement not null,
  "uuid" varchar not null,
  "name" varchar not null,
  "document_type" varchar check("document_type" in('contract', 'id_card', 'passport', 'certificate', 'resume', 'medical', 'other')) not null default 'other',
  "file_path" varchar,
  "issue_date" date,
  "expiry_date" date,
  "notify_before_days" integer not null default '30',
  "notes" text,
  "employee_id" integer not null,
  "created_by" integer,
  "updated_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("employee_id") references "hr_employees"("id") on delete cascade,
  foreign key("created_by") references "users"("id") on delete set null,
  foreign key("updated_by") references "users"("id") on delete set null
);
CREATE INDEX "hr_employee_documents_employee_id_index" on "hr_employee_documents"(
  "employee_id"
);
CREATE INDEX "hr_employee_documents_expiry_date_index" on "hr_employee_documents"(
  "expiry_date"
);
CREATE UNIQUE INDEX "hr_employee_documents_uuid_unique" on "hr_employee_documents"(
  "uuid"
);
CREATE TABLE IF NOT EXISTS "hr_employee_bank_accounts"(
  "id" integer primary key autoincrement not null,
  "employee_id" integer not null,
  "bank_name" varchar,
  "account_holder_name" varchar,
  "account_number" varchar,
  "iban" varchar,
  "swift_code" varchar,
  "branch_name" varchar,
  "currency" varchar not null default 'IQD',
  "is_primary" tinyint(1) not null default '0',
  "active" tinyint(1) not null default '1',
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("employee_id") references "hr_employees"("id") on delete cascade
);
CREATE INDEX "hr_employee_bank_accounts_employee_id_index" on "hr_employee_bank_accounts"(
  "employee_id"
);
CREATE TABLE IF NOT EXISTS "hr_employee_emergency_contacts"(
  "id" integer primary key autoincrement not null,
  "employee_id" integer not null,
  "name" varchar not null,
  "relationship" varchar,
  "phone" varchar,
  "email" varchar,
  "address" text,
  "is_primary" tinyint(1) not null default '0',
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("employee_id") references "hr_employees"("id") on delete cascade
);
CREATE INDEX "hr_employee_emergency_contacts_employee_id_index" on "hr_employee_emergency_contacts"(
  "employee_id"
);
CREATE TABLE IF NOT EXISTS "hr_employee_dependents"(
  "id" integer primary key autoincrement not null,
  "employee_id" integer not null,
  "name" varchar not null,
  "relationship" varchar check("relationship" in('spouse', 'child', 'parent', 'sibling', 'other')),
  "birthdate" date,
  "gender" varchar check("gender" in('male', 'female', 'other')),
  "identification_number" varchar,
  "notes" text,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("employee_id") references "hr_employees"("id") on delete cascade
);
CREATE INDEX "hr_employee_dependents_employee_id_index" on "hr_employee_dependents"(
  "employee_id"
);
CREATE TABLE IF NOT EXISTS "hr_departments"(
  "id" integer primary key autoincrement not null,
  "uuid" varchar not null,
  "name" varchar not null,
  "note" text,
  "color_index" integer,
  "active" tinyint(1) not null default('1'),
  "company_id" integer,
  "parent_id" integer,
  "manager_id" integer,
  "created_by" integer,
  "updated_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("updated_by") references users("id") on delete set null on update no action,
  foreign key("created_by") references users("id") on delete set null on update no action,
  foreign key("parent_id") references hr_departments("id") on delete set null on update no action,
  foreign key("company_id") references companies("id") on delete set null on update no action,
  foreign key("manager_id") references "hr_employees"("id") on delete set null
);
CREATE INDEX "hr_departments_active_company_id_index" on "hr_departments"(
  "active",
  "company_id"
);
CREATE INDEX "hr_departments_manager_id_index" on "hr_departments"(
  "manager_id"
);
CREATE UNIQUE INDEX "hr_departments_uuid_unique" on "hr_departments"("uuid");
CREATE TABLE IF NOT EXISTS "hr_employees"(
  "id" integer primary key autoincrement not null,
  "uuid" varchar not null,
  "name" varchar not null,
  "name_ar" varchar,
  "name_en" varchar,
  "family_name" varchar,
  "mother_name" varchar,
  "employee_code" varchar,
  "first_name" varchar,
  "last_name" varchar,
  "avatar" varchar,
  "barcode" varchar,
  "pin_code" varchar,
  "notes" text,
  "work_email" varchar,
  "work_phone" varchar,
  "work_mobile" varchar,
  "job_title" varchar,
  "company_id" integer,
  "department_id" integer,
  "job_id" integer,
  "work_location_id" integer,
  "resource_calendar_id" integer,
  "timezone" varchar,
  "parent_id" integer,
  "coach_id" integer,
  "expense_manager_id" integer,
  "attendance_manager_id" integer,
  "user_id" integer,
  "contact_id" integer,
  "private_email" varchar,
  "private_phone" varchar,
  "private_mobile" varchar,
  "private_address" text,
  "km_home_work" integer,
  "private_car_plate" varchar,
  "country" varchar,
  "state" varchar,
  "city" varchar,
  "zip" varchar,
  "nationality" varchar,
  "identification_id" varchar,
  "passport_id" varchar,
  "ssnid" varchar,
  "gender" varchar,
  "birthday" date,
  "place_of_birth" varchar,
  "country_of_birth" varchar,
  "marital_status" varchar,
  "spouse_name" varchar,
  "spouse_birthdate" date,
  "children" integer not null default('0'),
  "certificate_level" varchar,
  "study_field" varchar,
  "study_school" varchar,
  "visa_no" varchar,
  "work_permit_no" varchar,
  "visa_expire" date,
  "work_permit_expiration_date" date,
  "work_permit_file" varchar,
  "employment_status" varchar not null default('active'),
  "hire_date" date,
  "first_contract_date" date,
  "end_date" date,
  "departure_date" date,
  "departure_reason_id" integer,
  "departure_description" text,
  "probation_start_date" date,
  "probation_end_date" date,
  "contract_id" integer,
  "wage" numeric,
  "payment_method" varchar,
  "emergency_contact" varchar,
  "emergency_phone" varchar,
  "emergency_relation" varchar,
  "active" tinyint(1) not null default('1'),
  "created_by" integer,
  "updated_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("updated_by") references users("id") on delete set null on update no action,
  foreign key("created_by") references users("id") on delete set null on update no action,
  foreign key("departure_reason_id") references hr_departure_reasons("id") on delete set null on update no action,
  foreign key("contact_id") references contacts("id") on delete set null on update no action,
  foreign key("user_id") references users("id") on delete set null on update no action,
  foreign key("attendance_manager_id") references hr_employees("id") on delete set null on update no action,
  foreign key("expense_manager_id") references hr_employees("id") on delete set null on update no action,
  foreign key("coach_id") references hr_employees("id") on delete set null on update no action,
  foreign key("parent_id") references hr_employees("id") on delete set null on update no action,
  foreign key("resource_calendar_id") references hr_resource_calendars("id") on delete set null on update no action,
  foreign key("work_location_id") references hr_work_locations("id") on delete set null on update no action,
  foreign key("job_id") references hr_jobs("id") on delete set null on update no action,
  foreign key("department_id") references hr_departments("id") on delete set null on update no action,
  foreign key("company_id") references companies("id") on delete set null on update no action,
  foreign key("contract_id") references "hr_contracts"("id") on delete set null
);
CREATE INDEX "hr_employees_active_company_id_index" on "hr_employees"(
  "active",
  "company_id"
);
CREATE UNIQUE INDEX "hr_employees_barcode_unique" on "hr_employees"("barcode");
CREATE INDEX "hr_employees_contract_id_index" on "hr_employees"("contract_id");
CREATE INDEX "hr_employees_department_id_index" on "hr_employees"(
  "department_id"
);
CREATE UNIQUE INDEX "hr_employees_employee_code_unique" on "hr_employees"(
  "employee_code"
);
CREATE INDEX "hr_employees_employment_status_index" on "hr_employees"(
  "employment_status"
);
CREATE INDEX "hr_employees_parent_id_index" on "hr_employees"("parent_id");
CREATE UNIQUE INDEX "hr_employees_uuid_unique" on "hr_employees"("uuid");
CREATE INDEX "hr_employees_work_email_index" on "hr_employees"("work_email");
CREATE TABLE IF NOT EXISTS "hr_resume_line_types"(
  "id" integer primary key autoincrement not null,
  "uuid" varchar not null,
  "name" varchar not null,
  "active" tinyint(1) not null default '1',
  "created_by" integer,
  "updated_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("created_by") references "users"("id") on delete set null,
  foreign key("updated_by") references "users"("id") on delete set null
);
CREATE UNIQUE INDEX "hr_resume_line_types_uuid_unique" on "hr_resume_line_types"(
  "uuid"
);
CREATE TABLE IF NOT EXISTS "hr_employment_types"(
  "id" integer primary key autoincrement not null,
  "uuid" varchar not null,
  "name" varchar not null,
  "active" tinyint(1) not null default '1',
  "created_by" integer,
  "updated_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("created_by") references "users"("id") on delete set null,
  foreign key("updated_by") references "users"("id") on delete set null
);
CREATE UNIQUE INDEX "hr_employment_types_uuid_unique" on "hr_employment_types"(
  "uuid"
);
CREATE TABLE IF NOT EXISTS "hr_badges"(
  "id" integer primary key autoincrement not null,
  "uuid" varchar not null,
  "name" varchar not null,
  "description" text,
  "active" tinyint(1) not null default '1',
  "created_by" integer,
  "updated_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("created_by") references "users"("id") on delete set null,
  foreign key("updated_by") references "users"("id") on delete set null
);
CREATE UNIQUE INDEX "hr_badges_uuid_unique" on "hr_badges"("uuid");
CREATE TABLE IF NOT EXISTS "hr_challenges"(
  "id" integer primary key autoincrement not null,
  "uuid" varchar not null,
  "name" varchar not null,
  "description" text,
  "active" tinyint(1) not null default '1',
  "created_by" integer,
  "updated_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("created_by") references "users"("id") on delete set null,
  foreign key("updated_by") references "users"("id") on delete set null
);
CREATE UNIQUE INDEX "hr_challenges_uuid_unique" on "hr_challenges"("uuid");
CREATE TABLE IF NOT EXISTS "hr_goals"(
  "id" integer primary key autoincrement not null,
  "uuid" varchar not null,
  "name" varchar not null,
  "description" text,
  "active" tinyint(1) not null default '1',
  "created_by" integer,
  "updated_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("created_by") references "users"("id") on delete set null,
  foreign key("updated_by") references "users"("id") on delete set null
);
CREATE UNIQUE INDEX "hr_goals_uuid_unique" on "hr_goals"("uuid");
CREATE TABLE IF NOT EXISTS "accounts"(
  "id" integer primary key autoincrement not null,
  "uuid" varchar,
  "company_id" integer not null,
  "parent_id" integer,
  "created_by" integer,
  "updated_by" integer,
  "code" varchar not null,
  "name" varchar not null,
  "name_en" varchar,
  "account_type" varchar not null,
  "internal_type" varchar not null default 'other',
  "currency" varchar,
  "reconcile" tinyint(1) not null default '0',
  "notes" text,
  "active" tinyint(1) not null default '1',
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("company_id") references "companies"("id") on delete cascade,
  foreign key("parent_id") references "accounts"("id") on delete set null,
  foreign key("created_by") references "users"("id") on delete set null,
  foreign key("updated_by") references "users"("id") on delete set null
);
CREATE UNIQUE INDEX "accounts_company_id_code_unique" on "accounts"(
  "company_id",
  "code"
);
CREATE INDEX "accounts_company_id_account_type_index" on "accounts"(
  "company_id",
  "account_type"
);
CREATE INDEX "accounts_company_id_active_index" on "accounts"(
  "company_id",
  "active"
);
CREATE UNIQUE INDEX "accounts_uuid_unique" on "accounts"("uuid");
CREATE TABLE IF NOT EXISTS "account_journals"(
  "id" integer primary key autoincrement not null,
  "uuid" varchar,
  "company_id" integer not null,
  "default_account_id" integer,
  "suspense_account_id" integer,
  "created_by" integer,
  "updated_by" integer,
  "code" varchar not null,
  "name" varchar not null,
  "type" varchar not null,
  "currency" varchar,
  "sequence_prefix" varchar not null default '',
  "sequence_next_number" integer not null default '1',
  "sequence_padding" integer not null default '4',
  "active" tinyint(1) not null default '1',
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("company_id") references "companies"("id") on delete cascade,
  foreign key("default_account_id") references "accounts"("id") on delete set null,
  foreign key("suspense_account_id") references "accounts"("id") on delete set null,
  foreign key("created_by") references "users"("id") on delete set null,
  foreign key("updated_by") references "users"("id") on delete set null
);
CREATE UNIQUE INDEX "account_journals_company_id_code_unique" on "account_journals"(
  "company_id",
  "code"
);
CREATE INDEX "account_journals_company_id_type_index" on "account_journals"(
  "company_id",
  "type"
);
CREATE INDEX "account_journals_company_id_active_index" on "account_journals"(
  "company_id",
  "active"
);
CREATE UNIQUE INDEX "account_journals_uuid_unique" on "account_journals"(
  "uuid"
);
CREATE TABLE IF NOT EXISTS "account_partial_reconciles"(
  "id" integer primary key autoincrement not null,
  "company_id" integer not null,
  "debit_move_line_id" integer not null,
  "credit_move_line_id" integer not null,
  "created_by" integer,
  "amount" numeric not null,
  "date" date not null,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("company_id") references "companies"("id") on delete cascade,
  foreign key("debit_move_line_id") references "account_move_lines"("id") on delete cascade,
  foreign key("credit_move_line_id") references "account_move_lines"("id") on delete cascade,
  foreign key("created_by") references "users"("id") on delete set null
);
CREATE INDEX "account_partial_reconciles_debit_move_line_id_index" on "account_partial_reconciles"(
  "debit_move_line_id"
);
CREATE INDEX "account_partial_reconciles_credit_move_line_id_index" on "account_partial_reconciles"(
  "credit_move_line_id"
);
CREATE TABLE IF NOT EXISTS "account_taxes"(
  "id" integer primary key autoincrement not null,
  "uuid" varchar,
  "company_id" integer not null,
  "name" varchar not null,
  "amount_type" varchar not null default 'percent',
  "amount" numeric not null default '0',
  "type_tax_use" varchar not null default 'sale',
  "account_id" integer,
  "description" varchar,
  "include_base_amount" tinyint(1) not null default '0',
  "active" tinyint(1) not null default '1',
  "created_by" integer,
  "updated_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("company_id") references "companies"("id") on delete cascade,
  foreign key("account_id") references "accounts"("id") on delete set null,
  foreign key("created_by") references "users"("id") on delete set null,
  foreign key("updated_by") references "users"("id") on delete set null
);
CREATE UNIQUE INDEX "account_taxes_uuid_unique" on "account_taxes"("uuid");
CREATE TABLE IF NOT EXISTS "account_move_line_taxes"(
  "account_move_line_id" integer not null,
  "account_tax_id" integer not null,
  foreign key("account_move_line_id") references "account_move_lines"("id") on delete cascade,
  foreign key("account_tax_id") references "account_taxes"("id") on delete cascade,
  primary key("account_move_line_id", "account_tax_id")
);
CREATE TABLE IF NOT EXISTS "account_move_lines"(
  "id" integer primary key autoincrement not null,
  "uuid" varchar,
  "company_id" integer not null,
  "move_id" integer not null,
  "account_id" integer not null,
  "journal_id" integer not null,
  "partner_id" integer,
  "created_by" integer,
  "updated_by" integer,
  "name" varchar not null,
  "date" date not null,
  "state" varchar not null default('draft'),
  "debit" numeric not null default('0'),
  "credit" numeric not null default('0'),
  "currency" varchar,
  "amount_currency" numeric not null default('0'),
  "sequence" integer not null default('10'),
  "created_at" datetime,
  "updated_at" datetime,
  "product_id" integer,
  "uom_id" integer,
  "tax_line_id" integer,
  "tax_base_amount" numeric,
  "deleted_at" datetime,
  foreign key("uom_id") references inventory_uoms("id") on delete set null on update no action,
  foreign key("product_id") references inventory_products("id") on delete set null on update no action,
  foreign key("company_id") references companies("id") on delete cascade on update no action,
  foreign key("move_id") references account_moves("id") on delete cascade on update no action,
  foreign key("account_id") references accounts("id") on delete cascade on update no action,
  foreign key("journal_id") references account_journals("id") on delete cascade on update no action,
  foreign key("partner_id") references contacts("id") on delete set null on update no action,
  foreign key("created_by") references users("id") on delete set null on update no action,
  foreign key("updated_by") references users("id") on delete set null on update no action,
  foreign key("tax_line_id") references "account_taxes"("id") on delete set null
);
CREATE INDEX "account_move_lines_account_id_state_index" on "account_move_lines"(
  "account_id",
  "state"
);
CREATE INDEX "account_move_lines_company_id_date_index" on "account_move_lines"(
  "company_id",
  "date"
);
CREATE INDEX "account_move_lines_journal_id_state_index" on "account_move_lines"(
  "journal_id",
  "state"
);
CREATE INDEX "account_move_lines_move_id_index" on "account_move_lines"(
  "move_id"
);
CREATE UNIQUE INDEX "account_move_lines_uuid_unique" on "account_move_lines"(
  "uuid"
);
CREATE TABLE IF NOT EXISTS "accounting_payment_terms"(
  "id" integer primary key autoincrement not null,
  "uuid" varchar not null,
  "company_id" integer not null,
  "name" varchar not null,
  "note" text,
  "active" tinyint(1) not null default '1',
  "created_by" integer,
  "updated_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("company_id") references "companies"("id") on delete cascade,
  foreign key("created_by") references "users"("id") on delete set null,
  foreign key("updated_by") references "users"("id") on delete set null
);
CREATE UNIQUE INDEX "accounting_payment_terms_uuid_unique" on "accounting_payment_terms"(
  "uuid"
);
CREATE TABLE IF NOT EXISTS "accounting_payment_term_lines"(
  "id" integer primary key autoincrement not null,
  "payment_term_id" integer not null,
  "value_type" varchar check("value_type" in('percent', 'fixed', 'balance')) not null default 'balance',
  "value" numeric not null default '0',
  "days" integer not null default '0',
  "sequence" integer not null default '0',
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("payment_term_id") references "accounting_payment_terms"("id") on delete cascade
);
CREATE TABLE IF NOT EXISTS "accounting_incoterms"(
  "id" integer primary key autoincrement not null,
  "uuid" varchar not null,
  "code" varchar not null,
  "name" varchar not null,
  "created_by" integer,
  "updated_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("created_by") references "users"("id") on delete set null,
  foreign key("updated_by") references "users"("id") on delete set null
);
CREATE UNIQUE INDEX "accounting_incoterms_uuid_unique" on "accounting_incoterms"(
  "uuid"
);
CREATE UNIQUE INDEX "accounting_incoterms_code_unique" on "accounting_incoterms"(
  "code"
);
CREATE TABLE IF NOT EXISTS "accounting_tax_groups"(
  "id" integer primary key autoincrement not null,
  "uuid" varchar not null,
  "company_id" integer not null,
  "name" varchar not null,
  "sequence" integer not null default '0',
  "created_by" integer,
  "updated_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("company_id") references "companies"("id") on delete cascade,
  foreign key("created_by") references "users"("id") on delete set null,
  foreign key("updated_by") references "users"("id") on delete set null
);
CREATE UNIQUE INDEX "accounting_tax_groups_uuid_unique" on "accounting_tax_groups"(
  "uuid"
);
CREATE TABLE IF NOT EXISTS "accounting_account_groups"(
  "id" integer primary key autoincrement not null,
  "uuid" varchar not null,
  "company_id" integer not null,
  "parent_id" integer,
  "name" varchar not null,
  "code_prefix_start" varchar,
  "code_prefix_end" varchar,
  "created_by" integer,
  "updated_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("company_id") references "companies"("id") on delete cascade,
  foreign key("parent_id") references "accounting_account_groups"("id") on delete set null,
  foreign key("created_by") references "users"("id") on delete set null,
  foreign key("updated_by") references "users"("id") on delete set null
);
CREATE UNIQUE INDEX "accounting_account_groups_uuid_unique" on "accounting_account_groups"(
  "uuid"
);
CREATE TABLE IF NOT EXISTS "currency_rates"(
  "id" integer primary key autoincrement not null,
  "uuid" varchar,
  "company_id" integer not null,
  "currency" varchar not null,
  "rate" numeric not null,
  "date" date not null,
  "active" tinyint(1) not null default '1',
  "created_by" integer,
  "updated_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("company_id") references "companies"("id") on delete cascade,
  foreign key("created_by") references "users"("id") on delete set null,
  foreign key("updated_by") references "users"("id") on delete set null
);
CREATE INDEX "currency_rates_company_id_currency_date_index" on "currency_rates"(
  "company_id",
  "currency",
  "date"
);
CREATE UNIQUE INDEX "currency_rates_uuid_unique" on "currency_rates"("uuid");
CREATE TABLE IF NOT EXISTS "inventory_uom_categories"(
  "id" integer primary key autoincrement not null,
  "uuid" varchar,
  "name" varchar not null,
  "active" tinyint(1) not null default '1',
  "created_by" integer,
  "updated_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("created_by") references "users"("id") on delete set null,
  foreign key("updated_by") references "users"("id") on delete set null
);
CREATE UNIQUE INDEX "inventory_uom_categories_uuid_unique" on "inventory_uom_categories"(
  "uuid"
);
CREATE TABLE IF NOT EXISTS "inventory_uoms"(
  "id" integer primary key autoincrement not null,
  "uuid" varchar,
  "uom_category_id" integer not null,
  "name" varchar not null,
  "symbol" varchar,
  "ratio" numeric not null default '1',
  "rounding" numeric not null default '0.01',
  "uom_type" varchar not null default 'reference',
  "active" tinyint(1) not null default '1',
  "created_by" integer,
  "updated_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("uom_category_id") references "inventory_uom_categories"("id") on delete cascade,
  foreign key("created_by") references "users"("id") on delete set null,
  foreign key("updated_by") references "users"("id") on delete set null
);
CREATE INDEX "inventory_uoms_uom_category_id_active_index" on "inventory_uoms"(
  "uom_category_id",
  "active"
);
CREATE UNIQUE INDEX "inventory_uoms_uuid_unique" on "inventory_uoms"("uuid");
CREATE TABLE IF NOT EXISTS "inventory_product_categories"(
  "id" integer primary key autoincrement not null,
  "uuid" varchar,
  "parent_id" integer,
  "name" varchar not null,
  "complete_name" varchar,
  "removal_strategy" varchar not null default 'fifo',
  "costing_method" varchar not null default 'standard_price',
  "active" tinyint(1) not null default '1',
  "created_by" integer,
  "updated_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("parent_id") references "inventory_product_categories"("id") on delete set null,
  foreign key("created_by") references "users"("id") on delete set null,
  foreign key("updated_by") references "users"("id") on delete set null
);
CREATE UNIQUE INDEX "inventory_product_categories_uuid_unique" on "inventory_product_categories"(
  "uuid"
);
CREATE TABLE IF NOT EXISTS "inventory_products"(
  "id" integer primary key autoincrement not null,
  "uuid" varchar,
  "company_id" integer,
  "category_id" integer,
  "uom_id" integer not null,
  "uom_po_id" integer not null,
  "name" varchar not null,
  "internal_reference" varchar,
  "barcode" varchar,
  "description" text,
  "description_picking" text,
  "product_type" varchar not null default 'storable',
  "tracking" varchar not null default 'none',
  "cost" numeric not null default '0',
  "sale_price" numeric not null default '0',
  "weight" numeric,
  "volume" numeric,
  "image_uuid" varchar,
  "active" tinyint(1) not null default '1',
  "created_by" integer,
  "updated_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("company_id") references "companies"("id") on delete set null,
  foreign key("category_id") references "inventory_product_categories"("id") on delete set null,
  foreign key("uom_id") references "inventory_uoms"("id") on delete restrict,
  foreign key("uom_po_id") references "inventory_uoms"("id") on delete restrict,
  foreign key("created_by") references "users"("id") on delete set null,
  foreign key("updated_by") references "users"("id") on delete set null
);
CREATE INDEX "inventory_products_company_id_active_index" on "inventory_products"(
  "company_id",
  "active"
);
CREATE INDEX "inventory_products_product_type_active_index" on "inventory_products"(
  "product_type",
  "active"
);
CREATE INDEX "inventory_products_barcode_index" on "inventory_products"(
  "barcode"
);
CREATE UNIQUE INDEX "inventory_products_uuid_unique" on "inventory_products"(
  "uuid"
);
CREATE TABLE IF NOT EXISTS "inventory_product_suppliers"(
  "id" integer primary key autoincrement not null,
  "product_id" integer not null,
  "partner_id" integer,
  "partner_name" varchar,
  "partner_product_name" varchar,
  "partner_product_code" varchar,
  "min_qty" numeric not null default '0',
  "price" numeric not null default '0',
  "delay" integer not null default '1',
  "currency_id" integer,
  "active" tinyint(1) not null default '1',
  "created_by" integer,
  "updated_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("product_id") references "inventory_products"("id") on delete cascade,
  foreign key("partner_id") references "contacts"("id") on delete set null,
  foreign key("created_by") references "users"("id") on delete set null,
  foreign key("updated_by") references "users"("id") on delete set null
);
CREATE INDEX "inventory_product_suppliers_product_id_index" on "inventory_product_suppliers"(
  "product_id"
);
CREATE TABLE IF NOT EXISTS "inventory_locations"(
  "id" integer primary key autoincrement not null,
  "uuid" varchar,
  "company_id" integer,
  "parent_id" integer,
  "name" varchar not null,
  "complete_name" varchar,
  "usage" varchar not null default 'internal',
  "removal_strategy" varchar,
  "scrap_location" tinyint(1) not null default '0',
  "return_location" tinyint(1) not null default '0',
  "barcode" varchar,
  "notes" varchar,
  "posx" integer not null default '0',
  "posy" integer not null default '0',
  "posz" integer not null default '0',
  "active" tinyint(1) not null default '1',
  "created_by" integer,
  "updated_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("company_id") references "companies"("id") on delete set null,
  foreign key("parent_id") references "inventory_locations"("id") on delete set null,
  foreign key("created_by") references "users"("id") on delete set null,
  foreign key("updated_by") references "users"("id") on delete set null
);
CREATE INDEX "inventory_locations_company_id_usage_active_index" on "inventory_locations"(
  "company_id",
  "usage",
  "active"
);
CREATE INDEX "inventory_locations_parent_id_active_index" on "inventory_locations"(
  "parent_id",
  "active"
);
CREATE UNIQUE INDEX "inventory_locations_uuid_unique" on "inventory_locations"(
  "uuid"
);
CREATE TABLE IF NOT EXISTS "inventory_warehouses"(
  "id" integer primary key autoincrement not null,
  "uuid" varchar,
  "company_id" integer not null,
  "partner_id" integer,
  "lot_stock_id" integer,
  "wh_input_stock_loc_id" integer,
  "wh_output_stock_loc_id" integer,
  "wh_pack_stock_loc_id" integer,
  "view_location_id" integer,
  "name" varchar not null,
  "short_name" varchar not null,
  "reception_steps" varchar not null default 'one_step',
  "delivery_steps" varchar not null default 'one_step',
  "active" tinyint(1) not null default '1',
  "created_by" integer,
  "updated_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("company_id") references "companies"("id") on delete cascade,
  foreign key("partner_id") references "contacts"("id") on delete set null,
  foreign key("lot_stock_id") references "inventory_locations"("id") on delete set null,
  foreign key("wh_input_stock_loc_id") references "inventory_locations"("id") on delete set null,
  foreign key("wh_output_stock_loc_id") references "inventory_locations"("id") on delete set null,
  foreign key("wh_pack_stock_loc_id") references "inventory_locations"("id") on delete set null,
  foreign key("view_location_id") references "inventory_locations"("id") on delete set null,
  foreign key("created_by") references "users"("id") on delete set null,
  foreign key("updated_by") references "users"("id") on delete set null
);
CREATE UNIQUE INDEX "inventory_warehouses_company_id_short_name_unique" on "inventory_warehouses"(
  "company_id",
  "short_name"
);
CREATE INDEX "inventory_warehouses_company_id_active_index" on "inventory_warehouses"(
  "company_id",
  "active"
);
CREATE UNIQUE INDEX "inventory_warehouses_uuid_unique" on "inventory_warehouses"(
  "uuid"
);
CREATE TABLE IF NOT EXISTS "inventory_operation_types"(
  "id" integer primary key autoincrement not null,
  "uuid" varchar,
  "company_id" integer not null,
  "warehouse_id" integer,
  "default_location_src_id" integer,
  "default_location_dest_id" integer,
  "return_picking_type_id" integer,
  "name" varchar not null,
  "code" varchar not null default 'internal',
  "use_existing_lots" tinyint(1) not null default '1',
  "use_create_lots" tinyint(1) not null default '1',
  "show_entire_packs" tinyint(1) not null default '0',
  "sequence_prefix" varchar not null default '',
  "sequence_next_number" integer not null default '1',
  "sequence_padding" integer not null default '5',
  "active" tinyint(1) not null default '1',
  "created_by" integer,
  "updated_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("company_id") references "companies"("id") on delete cascade,
  foreign key("warehouse_id") references "inventory_warehouses"("id") on delete set null,
  foreign key("default_location_src_id") references "inventory_locations"("id") on delete set null,
  foreign key("default_location_dest_id") references "inventory_locations"("id") on delete set null,
  foreign key("return_picking_type_id") references "inventory_operation_types"("id") on delete set null,
  foreign key("created_by") references "users"("id") on delete set null,
  foreign key("updated_by") references "users"("id") on delete set null
);
CREATE INDEX "inventory_operation_types_company_id_code_active_index" on "inventory_operation_types"(
  "company_id",
  "code",
  "active"
);
CREATE INDEX "inventory_operation_types_warehouse_id_active_index" on "inventory_operation_types"(
  "warehouse_id",
  "active"
);
CREATE UNIQUE INDEX "inventory_operation_types_uuid_unique" on "inventory_operation_types"(
  "uuid"
);
CREATE TABLE IF NOT EXISTS "inventory_routes"(
  "id" integer primary key autoincrement not null,
  "uuid" varchar,
  "company_id" integer,
  "supplied_wh_id" integer,
  "supplier_wh_id" integer,
  "name" varchar not null,
  "sequence" integer not null default '10',
  "product_category_selectable" tinyint(1) not null default '0',
  "product_selectable" tinyint(1) not null default '1',
  "warehouse_selectable" tinyint(1) not null default '0',
  "active" tinyint(1) not null default '1',
  "created_by" integer,
  "updated_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("company_id") references "companies"("id") on delete set null,
  foreign key("supplied_wh_id") references "inventory_warehouses"("id") on delete set null,
  foreign key("supplier_wh_id") references "inventory_warehouses"("id") on delete set null,
  foreign key("created_by") references "users"("id") on delete set null,
  foreign key("updated_by") references "users"("id") on delete set null
);
CREATE UNIQUE INDEX "inventory_routes_uuid_unique" on "inventory_routes"(
  "uuid"
);
CREATE TABLE IF NOT EXISTS "inventory_route_rules"(
  "id" integer primary key autoincrement not null,
  "uuid" varchar,
  "company_id" integer,
  "route_id" integer not null,
  "operation_type_id" integer,
  "location_src_id" integer,
  "location_dest_id" integer,
  "name" varchar not null,
  "action" varchar not null default 'pull',
  "sequence" integer not null default '10',
  "delay" integer not null default '0',
  "group_propagation_option" varchar not null default 'propagate',
  "active" tinyint(1) not null default '1',
  "created_by" integer,
  "updated_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("company_id") references "companies"("id") on delete set null,
  foreign key("route_id") references "inventory_routes"("id") on delete cascade,
  foreign key("operation_type_id") references "inventory_operation_types"("id") on delete set null,
  foreign key("location_src_id") references "inventory_locations"("id") on delete set null,
  foreign key("location_dest_id") references "inventory_locations"("id") on delete set null,
  foreign key("created_by") references "users"("id") on delete set null,
  foreign key("updated_by") references "users"("id") on delete set null
);
CREATE INDEX "inventory_route_rules_route_id_active_index" on "inventory_route_rules"(
  "route_id",
  "active"
);
CREATE UNIQUE INDEX "inventory_route_rules_uuid_unique" on "inventory_route_rules"(
  "uuid"
);
CREATE TABLE IF NOT EXISTS "inventory_putaway_rules"(
  "id" integer primary key autoincrement not null,
  "uuid" varchar,
  "company_id" integer,
  "location_id" integer not null,
  "fixed_location_id" integer not null,
  "product_id" integer,
  "product_category_id" integer,
  "sequence" integer not null default '10',
  "active" tinyint(1) not null default '1',
  "created_by" integer,
  "updated_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("company_id") references "companies"("id") on delete set null,
  foreign key("location_id") references "inventory_locations"("id") on delete cascade,
  foreign key("fixed_location_id") references "inventory_locations"("id") on delete cascade,
  foreign key("product_id") references "inventory_products"("id") on delete set null,
  foreign key("product_category_id") references "inventory_product_categories"("id") on delete set null,
  foreign key("created_by") references "users"("id") on delete set null,
  foreign key("updated_by") references "users"("id") on delete set null
);
CREATE INDEX "inventory_putaway_rules_location_id_active_index" on "inventory_putaway_rules"(
  "location_id",
  "active"
);
CREATE UNIQUE INDEX "inventory_putaway_rules_uuid_unique" on "inventory_putaway_rules"(
  "uuid"
);
CREATE TABLE IF NOT EXISTS "inventory_product_routes"(
  "product_id" integer not null,
  "route_id" integer not null,
  foreign key("product_id") references "inventory_products"("id") on delete cascade,
  foreign key("route_id") references "inventory_routes"("id") on delete cascade,
  primary key("product_id", "route_id")
);
CREATE TABLE IF NOT EXISTS "inventory_category_routes"(
  "category_id" integer not null,
  "route_id" integer not null,
  foreign key("category_id") references "inventory_product_categories"("id") on delete cascade,
  foreign key("route_id") references "inventory_routes"("id") on delete cascade,
  primary key("category_id", "route_id")
);
CREATE TABLE IF NOT EXISTS "inventory_lots"(
  "id" integer primary key autoincrement not null,
  "uuid" varchar,
  "company_id" integer not null,
  "product_id" integer not null,
  "name" varchar not null,
  "ref" varchar,
  "expiration_date" date,
  "use_date" date,
  "removal_date" date,
  "note" text,
  "active" tinyint(1) not null default '1',
  "created_by" integer,
  "updated_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("company_id") references "companies"("id") on delete cascade,
  foreign key("product_id") references "inventory_products"("id") on delete cascade,
  foreign key("created_by") references "users"("id") on delete set null,
  foreign key("updated_by") references "users"("id") on delete set null
);
CREATE UNIQUE INDEX "inventory_lots_company_id_product_id_name_unique" on "inventory_lots"(
  "company_id",
  "product_id",
  "name"
);
CREATE INDEX "inventory_lots_product_id_active_index" on "inventory_lots"(
  "product_id",
  "active"
);
CREATE UNIQUE INDEX "inventory_lots_uuid_unique" on "inventory_lots"("uuid");
CREATE TABLE IF NOT EXISTS "inventory_pickings"(
  "id" integer primary key autoincrement not null,
  "uuid" varchar,
  "company_id" integer not null,
  "operation_type_id" integer not null,
  "partner_id" integer,
  "location_src_id" integer not null,
  "location_dest_id" integer not null,
  "origin_picking_id" integer,
  "name" varchar,
  "origin" varchar,
  "note" varchar,
  "state" varchar not null default 'draft',
  "scheduled_date" datetime,
  "date_done" datetime,
  "active" tinyint(1) not null default '1',
  "created_by" integer,
  "updated_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("company_id") references "companies"("id") on delete cascade,
  foreign key("operation_type_id") references "inventory_operation_types"("id") on delete restrict,
  foreign key("partner_id") references "contacts"("id") on delete set null,
  foreign key("location_src_id") references "inventory_locations"("id") on delete restrict,
  foreign key("location_dest_id") references "inventory_locations"("id") on delete restrict,
  foreign key("origin_picking_id") references "inventory_pickings"("id") on delete set null,
  foreign key("created_by") references "users"("id") on delete set null,
  foreign key("updated_by") references "users"("id") on delete set null
);
CREATE UNIQUE INDEX "inventory_pickings_company_id_name_unique" on "inventory_pickings"(
  "company_id",
  "name"
);
CREATE INDEX "inventory_pickings_company_id_state_index" on "inventory_pickings"(
  "company_id",
  "state"
);
CREATE INDEX "inventory_pickings_operation_type_id_state_index" on "inventory_pickings"(
  "operation_type_id",
  "state"
);
CREATE UNIQUE INDEX "inventory_pickings_uuid_unique" on "inventory_pickings"(
  "uuid"
);
CREATE TABLE IF NOT EXISTS "inventory_moves"(
  "id" integer primary key autoincrement not null,
  "uuid" varchar,
  "company_id" integer not null,
  "picking_id" integer,
  "product_id" integer not null,
  "uom_id" integer not null,
  "location_src_id" integer not null,
  "location_dest_id" integer not null,
  "origin_returned_move_id" integer,
  "name" varchar not null,
  "origin" varchar,
  "product_qty" numeric not null default '0',
  "qty_done" numeric not null default '0',
  "reserved_qty" numeric not null default '0',
  "state" varchar not null default 'draft',
  "sequence" integer not null default '10',
  "date" date,
  "created_by" integer,
  "updated_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("company_id") references "companies"("id") on delete cascade,
  foreign key("picking_id") references "inventory_pickings"("id") on delete cascade,
  foreign key("product_id") references "inventory_products"("id") on delete restrict,
  foreign key("uom_id") references "inventory_uoms"("id") on delete restrict,
  foreign key("location_src_id") references "inventory_locations"("id") on delete restrict,
  foreign key("location_dest_id") references "inventory_locations"("id") on delete restrict,
  foreign key("origin_returned_move_id") references "inventory_moves"("id") on delete set null,
  foreign key("created_by") references "users"("id") on delete set null,
  foreign key("updated_by") references "users"("id") on delete set null
);
CREATE INDEX "inventory_moves_picking_id_state_index" on "inventory_moves"(
  "picking_id",
  "state"
);
CREATE INDEX "inventory_moves_product_id_state_index" on "inventory_moves"(
  "product_id",
  "state"
);
CREATE INDEX "inventory_moves_company_id_state_index" on "inventory_moves"(
  "company_id",
  "state"
);
CREATE UNIQUE INDEX "inventory_moves_uuid_unique" on "inventory_moves"("uuid");
CREATE TABLE IF NOT EXISTS "inventory_move_lines"(
  "id" integer primary key autoincrement not null,
  "uuid" varchar,
  "company_id" integer not null,
  "move_id" integer not null,
  "picking_id" integer,
  "product_id" integer not null,
  "uom_id" integer not null,
  "location_id" integer not null,
  "location_dest_id" integer not null,
  "lot_id" integer,
  "lot_name" varchar,
  "reserved_qty" numeric not null default '0',
  "qty_done" numeric not null default '0',
  "date" date,
  "created_by" integer,
  "updated_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("company_id") references "companies"("id") on delete cascade,
  foreign key("move_id") references "inventory_moves"("id") on delete cascade,
  foreign key("picking_id") references "inventory_pickings"("id") on delete set null,
  foreign key("product_id") references "inventory_products"("id") on delete restrict,
  foreign key("uom_id") references "inventory_uoms"("id") on delete restrict,
  foreign key("location_id") references "inventory_locations"("id") on delete restrict,
  foreign key("location_dest_id") references "inventory_locations"("id") on delete restrict,
  foreign key("lot_id") references "inventory_lots"("id") on delete set null,
  foreign key("created_by") references "users"("id") on delete set null,
  foreign key("updated_by") references "users"("id") on delete set null
);
CREATE INDEX "inventory_move_lines_move_id_index" on "inventory_move_lines"(
  "move_id"
);
CREATE INDEX "inventory_move_lines_picking_id_index" on "inventory_move_lines"(
  "picking_id"
);
CREATE INDEX "inventory_move_lines_product_id_lot_id_index" on "inventory_move_lines"(
  "product_id",
  "lot_id"
);
CREATE UNIQUE INDEX "inventory_move_lines_uuid_unique" on "inventory_move_lines"(
  "uuid"
);
CREATE TABLE IF NOT EXISTS "inventory_quants"(
  "id" integer primary key autoincrement not null,
  "uuid" varchar,
  "company_id" integer not null,
  "product_id" integer not null,
  "location_id" integer not null,
  "lot_id" integer,
  "quantity" numeric not null default '0',
  "reserved_quantity" numeric not null default '0',
  "in_date" datetime,
  "created_by" integer,
  "updated_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("company_id") references "companies"("id") on delete cascade,
  foreign key("product_id") references "inventory_products"("id") on delete cascade,
  foreign key("location_id") references "inventory_locations"("id") on delete cascade,
  foreign key("lot_id") references "inventory_lots"("id") on delete set null,
  foreign key("created_by") references "users"("id") on delete set null,
  foreign key("updated_by") references "users"("id") on delete set null
);
CREATE INDEX "inventory_quants_product_id_location_id_index" on "inventory_quants"(
  "product_id",
  "location_id"
);
CREATE INDEX "inventory_quants_location_id_company_id_index" on "inventory_quants"(
  "location_id",
  "company_id"
);
CREATE UNIQUE INDEX "inventory_quants_uuid_unique" on "inventory_quants"(
  "uuid"
);
CREATE TABLE IF NOT EXISTS "inventory_scrap_orders"(
  "id" integer primary key autoincrement not null,
  "uuid" varchar,
  "company_id" integer not null,
  "product_id" integer not null,
  "uom_id" integer not null,
  "location_id" integer not null,
  "scrap_location_id" integer not null,
  "lot_id" integer,
  "picking_id" integer,
  "move_id" integer,
  "name" varchar,
  "scrap_qty" numeric not null default '1',
  "state" varchar not null default 'draft',
  "origin" varchar,
  "date_done" date,
  "created_by" integer,
  "updated_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("company_id") references "companies"("id") on delete cascade,
  foreign key("product_id") references "inventory_products"("id") on delete restrict,
  foreign key("uom_id") references "inventory_uoms"("id") on delete restrict,
  foreign key("location_id") references "inventory_locations"("id") on delete restrict,
  foreign key("scrap_location_id") references "inventory_locations"("id") on delete restrict,
  foreign key("lot_id") references "inventory_lots"("id") on delete set null,
  foreign key("picking_id") references "inventory_pickings"("id") on delete set null,
  foreign key("move_id") references "inventory_moves"("id") on delete set null,
  foreign key("created_by") references "users"("id") on delete set null,
  foreign key("updated_by") references "users"("id") on delete set null
);
CREATE INDEX "inventory_scrap_orders_company_id_state_index" on "inventory_scrap_orders"(
  "company_id",
  "state"
);
CREATE INDEX "inventory_scrap_orders_product_id_index" on "inventory_scrap_orders"(
  "product_id"
);
CREATE UNIQUE INDEX "inventory_scrap_orders_uuid_unique" on "inventory_scrap_orders"(
  "uuid"
);
CREATE TABLE IF NOT EXISTS "inventory_reorder_rules"(
  "id" integer primary key autoincrement not null,
  "uuid" varchar,
  "company_id" integer not null,
  "product_id" integer not null,
  "location_id" integer not null,
  "warehouse_id" integer,
  "route_id" integer,
  "qty_min" numeric not null default '0',
  "qty_max" numeric not null default '0',
  "qty_multiple" numeric not null default '1',
  "qty_on_hand" numeric not null default '0',
  "qty_forecast" numeric not null default '0',
  "lead_days" integer not null default '0',
  "active" tinyint(1) not null default '1',
  "created_by" integer,
  "updated_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("company_id") references "companies"("id") on delete cascade,
  foreign key("product_id") references "inventory_products"("id") on delete cascade,
  foreign key("location_id") references "inventory_locations"("id") on delete cascade,
  foreign key("warehouse_id") references "inventory_warehouses"("id") on delete set null,
  foreign key("route_id") references "inventory_routes"("id") on delete set null,
  foreign key("created_by") references "users"("id") on delete set null,
  foreign key("updated_by") references "users"("id") on delete set null
);
CREATE INDEX "inventory_reorder_rules_company_id_active_index" on "inventory_reorder_rules"(
  "company_id",
  "active"
);
CREATE INDEX "inventory_reorder_rules_product_id_location_id_index" on "inventory_reorder_rules"(
  "product_id",
  "location_id"
);
CREATE UNIQUE INDEX "inventory_reorder_rules_uuid_unique" on "inventory_reorder_rules"(
  "uuid"
);
CREATE TABLE IF NOT EXISTS "inventory_adjustments"(
  "id" integer primary key autoincrement not null,
  "uuid" varchar,
  "company_id" integer not null,
  "name" varchar,
  "state" varchar not null default 'draft',
  "exhausted" tinyint(1) not null default '0',
  "date" date,
  "note" text,
  "active" tinyint(1) not null default '1',
  "created_by" integer,
  "updated_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("company_id") references "companies"("id") on delete cascade,
  foreign key("created_by") references "users"("id") on delete set null,
  foreign key("updated_by") references "users"("id") on delete set null
);
CREATE INDEX "inventory_adjustments_company_id_state_index" on "inventory_adjustments"(
  "company_id",
  "state"
);
CREATE UNIQUE INDEX "inventory_adjustments_uuid_unique" on "inventory_adjustments"(
  "uuid"
);
CREATE TABLE IF NOT EXISTS "inventory_adjustment_lines"(
  "id" integer primary key autoincrement not null,
  "uuid" varchar,
  "company_id" integer not null,
  "adjustment_id" integer not null,
  "product_id" integer not null,
  "location_id" integer not null,
  "lot_id" integer,
  "inventory_qty" numeric not null default '0',
  "theoretical_qty" numeric not null default '0',
  "difference_qty" numeric not null default '0',
  "created_by" integer,
  "updated_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("company_id") references "companies"("id") on delete cascade,
  foreign key("adjustment_id") references "inventory_adjustments"("id") on delete cascade,
  foreign key("product_id") references "inventory_products"("id") on delete cascade,
  foreign key("location_id") references "inventory_locations"("id") on delete cascade,
  foreign key("lot_id") references "inventory_lots"("id") on delete set null,
  foreign key("created_by") references "users"("id") on delete set null,
  foreign key("updated_by") references "users"("id") on delete set null
);
CREATE INDEX "inventory_adjustment_lines_adjustment_id_index" on "inventory_adjustment_lines"(
  "adjustment_id"
);
CREATE INDEX "inventory_adjustment_lines_product_id_location_id_index" on "inventory_adjustment_lines"(
  "product_id",
  "location_id"
);
CREATE UNIQUE INDEX "inventory_adjustment_lines_uuid_unique" on "inventory_adjustment_lines"(
  "uuid"
);
CREATE TABLE IF NOT EXISTS "account_moves"(
  "id" integer primary key autoincrement not null,
  "uuid" varchar,
  "company_id" integer not null,
  "journal_id" integer not null,
  "partner_id" integer,
  "reversed_move_id" integer,
  "created_by" integer,
  "updated_by" integer,
  "posted_by" integer,
  "name" varchar,
  "ref" varchar,
  "date" date not null,
  "state" varchar not null default('draft'),
  "payment_state" varchar not null default('not_paid'),
  "move_type" varchar not null default('entry'),
  "currency" varchar,
  "amount_total" numeric not null default('0'),
  "narration" text,
  "posted_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  "invoice_date_due" date,
  "payment_term_id" integer,
  "incoterm_id" integer,
  "invoice_origin" varchar,
  "deleted_at" datetime,
  foreign key("posted_by") references users("id") on delete set null on update no action,
  foreign key("updated_by") references users("id") on delete set null on update no action,
  foreign key("created_by") references users("id") on delete set null on update no action,
  foreign key("reversed_move_id") references account_moves("id") on delete set null on update no action,
  foreign key("partner_id") references contacts("id") on delete set null on update no action,
  foreign key("journal_id") references account_journals("id") on delete cascade on update no action,
  foreign key("company_id") references companies("id") on delete cascade on update no action,
  foreign key("payment_term_id") references "accounting_payment_terms"("id") on delete set null,
  foreign key("incoterm_id") references "accounting_incoterms"("id") on delete set null
);
CREATE INDEX "account_moves_company_id_date_index" on "account_moves"(
  "company_id",
  "date"
);
CREATE UNIQUE INDEX "account_moves_company_id_name_unique" on "account_moves"(
  "company_id",
  "name"
);
CREATE INDEX "account_moves_company_id_state_index" on "account_moves"(
  "company_id",
  "state"
);
CREATE INDEX "account_moves_journal_id_state_index" on "account_moves"(
  "journal_id",
  "state"
);
CREATE UNIQUE INDEX "account_moves_uuid_unique" on "account_moves"("uuid");
CREATE TABLE IF NOT EXISTS "account_payments"(
  "id" integer primary key autoincrement not null,
  "uuid" varchar,
  "company_id" integer not null,
  "journal_id" integer not null,
  "move_id" integer not null,
  "partner_id" integer,
  "paired_document_id" integer,
  "created_by" integer,
  "updated_by" integer,
  "payment_type" varchar not null,
  "date" date not null,
  "amount" numeric not null,
  "currency" varchar,
  "memo" varchar,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  "state" varchar not null default 'draft',
  "payment_method" varchar not null default 'manual',
  "bank_reference" varchar,
  "cheque_number" varchar,
  "destination_account_id" integer,
  foreign key("updated_by") references users("id") on delete set null on update no action,
  foreign key("created_by") references users("id") on delete set null on update no action,
  foreign key("paired_document_id") references account_moves("id") on delete set null on update no action,
  foreign key("partner_id") references contacts("id") on delete set null on update no action,
  foreign key("move_id") references account_moves("id") on delete cascade on update no action,
  foreign key("journal_id") references account_journals("id") on delete cascade on update no action,
  foreign key("company_id") references companies("id") on delete cascade on update no action,
  foreign key("destination_account_id") references "accounts"("id") on delete set null
);
CREATE INDEX "account_payments_company_id_date_index" on "account_payments"(
  "company_id",
  "date"
);
CREATE INDEX "account_payments_paired_document_id_index" on "account_payments"(
  "paired_document_id"
);
CREATE UNIQUE INDEX "account_payments_uuid_unique" on "account_payments"(
  "uuid"
);

INSERT INTO migrations VALUES(1,'2026_01_01_000001_create_framework_tables',1);
INSERT INTO migrations VALUES(2,'2026_01_01_000002_create_companies_table',1);
INSERT INTO migrations VALUES(3,'2026_01_01_000003_add_company_id_to_users',1);
INSERT INTO migrations VALUES(4,'2026_01_01_000004_create_roles_permissions_tables',1);
INSERT INTO migrations VALUES(5,'2026_01_01_000005_create_contacts_tables',1);
INSERT INTO migrations VALUES(6,'2026_01_01_000006_create_core_tables',1);
INSERT INTO migrations VALUES(7,'2026_01_01_000007_create_files_table',1);
INSERT INTO migrations VALUES(8,'2026_01_01_000008_create_chat_tables',1);
INSERT INTO migrations VALUES(9,'2026_01_01_000009_create_workflow_base_tables',1);
INSERT INTO migrations VALUES(10,'2026_01_01_000010_create_workflow_template_tables',1);
INSERT INTO migrations VALUES(11,'2026_01_01_000011_create_workflow_instance_tables',1);
INSERT INTO migrations VALUES(12,'2026_01_01_000012_add_workflow_ticket_path_chosen_fk',1);
INSERT INTO migrations VALUES(13,'2026_01_01_000013_create_workflow_misc_tables',1);
INSERT INTO migrations VALUES(14,'2026_01_01_000014_create_workflow_input_tables',1);
INSERT INTO migrations VALUES(15,'2026_01_01_000015_add_soft_deletes_to_workflow_procedure_tables',1);
INSERT INTO migrations VALUES(16,'2026_01_01_000015_create_hr_base_tables',1);
INSERT INTO migrations VALUES(17,'2026_01_01_000016_create_hr_skill_tables',1);
INSERT INTO migrations VALUES(18,'2026_01_01_000017_create_hr_employees_table',1);
INSERT INTO migrations VALUES(19,'2026_01_01_000018_create_hr_employee_relations',1);
INSERT INTO migrations VALUES(20,'2026_01_01_000019_create_hr_contracts_table',1);
INSERT INTO migrations VALUES(21,'2026_01_01_000020_create_hr_employee_details',1);
INSERT INTO migrations VALUES(22,'2026_01_01_000021_add_hr_circular_foreign_keys',1);
INSERT INTO migrations VALUES(23,'2026_01_01_000022_create_hr_misc_tables',1);
INSERT INTO migrations VALUES(24,'2026_01_01_000023_create_accounting_base_tables',1);
INSERT INTO migrations VALUES(25,'2026_01_01_000024_add_payment_state_to_account_moves',1);
INSERT INTO migrations VALUES(26,'2026_01_01_000025_create_accounting_payment_reconciliation_tables',1);
INSERT INTO migrations VALUES(27,'2026_01_01_000026_add_product_uom_to_account_move_lines',1);
INSERT INTO migrations VALUES(28,'2026_01_01_000026_create_account_taxes_table',1);
INSERT INTO migrations VALUES(29,'2026_01_01_000027_add_tax_fields_and_lock_dates',1);
INSERT INTO migrations VALUES(30,'2026_01_01_000027_create_accounting_config_tables',1);
INSERT INTO migrations VALUES(31,'2026_01_01_000028_create_currency_rates_table',1);
INSERT INTO migrations VALUES(32,'2026_01_01_000030_create_inventory_base_tables',1);
INSERT INTO migrations VALUES(33,'2026_01_01_000031_create_inventory_warehouse_tables',1);
INSERT INTO migrations VALUES(34,'2026_01_01_000032_create_inventory_stock_tables',1);
INSERT INTO migrations VALUES(35,'2026_01_01_000033_add_invoice_fields_to_account_moves',1);
INSERT INTO migrations VALUES(36,'2026_01_01_000034_add_soft_deletes_to_all_tables',1);
INSERT INTO migrations VALUES(37,'2026_01_01_000035_add_payment_fields_to_account_payments',1);
