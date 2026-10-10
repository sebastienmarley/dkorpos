CREATE TABLE "migrations"(
  "id" integer primary key autoincrement not null,
  "migration" varchar not null,
  "batch" integer not null
);
CREATE TABLE "password_reset_tokens"(
  "email" varchar not null,
  "token" varchar not null,
  "created_at" datetime,
  primary key("email")
);
CREATE TABLE "sessions"(
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
CREATE TABLE "cache"(
  "key" varchar not null,
  "value" text not null,
  "expiration" integer not null,
  primary key("key")
);
CREATE INDEX "cache_expiration_index" on "cache"("expiration");
CREATE TABLE "cache_locks"(
  "key" varchar not null,
  "owner" varchar not null,
  "expiration" integer not null,
  primary key("key")
);
CREATE INDEX "cache_locks_expiration_index" on "cache_locks"("expiration");
CREATE TABLE "jobs"(
  "id" integer primary key autoincrement not null,
  "queue" varchar not null,
  "payload" text not null,
  "attempts" integer not null,
  "reserved_at" integer,
  "available_at" integer not null,
  "created_at" integer not null
);
CREATE INDEX "jobs_queue_index" on "jobs"("queue");
CREATE TABLE "job_batches"(
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
CREATE TABLE "failed_jobs"(
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
CREATE TABLE "customers"(
  "id" integer primary key autoincrement not null,
  "firstname" varchar not null,
  "lastname" varchar not null,
  "phone" varchar,
  "cellphone" varchar,
  "email" varchar,
  "created_at" datetime,
  "updated_at" datetime,
  "address_civic" varchar,
  "address_apartment" varchar,
  "address_street" varchar,
  "address_city" varchar,
  "address_province" varchar,
  "address_country" varchar,
  "address_postal_code" varchar,
  "search_name" varchar,
  "credit_balance" numeric not null default '0'
);
CREATE UNIQUE INDEX "customers_email_unique" on "customers"("email");
CREATE TABLE "customer_payment_methods"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "is_active" tinyint(1) not null default '1',
  "created_at" datetime,
  "updated_at" datetime,
  "code" varchar
);
CREATE TABLE "merchant_payment_methods"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "account_type_id" integer,
  "is_active" tinyint(1) not null default '1',
  "created_at" datetime,
  "updated_at" datetime
);
CREATE TABLE "inventory_stocks"(
  "id" integer primary key autoincrement not null,
  "product_id" integer not null,
  "quantity_in_stock" integer not null default '0',
  "quantity_on_order" integer not null default '0',
  "quantity_in_demo" integer not null default '0',
  "quantity_reserved" integer not null default '0',
  "quantity_customer_order" integer not null default '0',
  "created_at" datetime,
  "updated_at" datetime,
  "quantity_in_delivery" integer not null default '0',
  "quantity_defective_stock" integer not null default '0',
  "quantity_defective_shipped" integer not null default '0',
  "quantity_lost" integer not null default '0',
  foreign key("product_id") references "products"("id") on delete cascade
);
CREATE UNIQUE INDEX "inventory_stocks_product_id_unique" on "inventory_stocks"(
  "product_id"
);
CREATE TABLE "departments"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "created_at" datetime,
  "updated_at" datetime
);
CREATE UNIQUE INDEX "departments_name_unique" on "departments"("name");
CREATE TABLE "categories"(
  "id" integer primary key autoincrement not null,
  "department_id" integer,
  "name" varchar not null,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("department_id") references "departments"("id") on delete set null
);
CREATE TABLE "colors"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "hex_code" varchar,
  "created_at" datetime,
  "updated_at" datetime
);
CREATE TABLE "products"(
  "id" integer primary key autoincrement not null,
  "supplier_id" integer not null,
  "model" varchar not null,
  "cost" numeric not null,
  "created_at" datetime,
  "updated_at" datetime,
  "department_id" integer,
  "category_id" integer,
  "color_id" integer,
  "description" text,
  "is_discontinued" tinyint(1) not null default '0',
  "is_non_orderable" tinyint(1) not null default '0',
  "supplier_model" varchar,
  "clean_model" varchar,
  "collection" varchar,
  "length" numeric,
  "width" numeric,
  "height" numeric,
  "weight" numeric,
  "imap" numeric,
  "supplier_clean_model" varchar,
  "is_taxable" tinyint(1) not null default '1',
  foreign key("supplier_id") references suppliers("id") on delete cascade on update no action,
  foreign key("department_id") references "departments"("id") on delete set null,
  foreign key("category_id") references "categories"("id") on delete set null,
  foreign key("color_id") references "colors"("id") on delete set null
);
CREATE UNIQUE INDEX "products_supplier_id_clean_model_unique" on "products"(
  "supplier_id",
  "clean_model"
);
CREATE TABLE "appointments"(
  "id" integer primary key autoincrement not null,
  "user_id" integer not null,
  "date" date not null,
  "title" varchar not null,
  "notes" text,
  "created_at" datetime,
  "updated_at" datetime,
  "customer_id" integer,
  "start_minute" integer not null default('0'),
  "duration_minutes" integer not null default('60'),
  "created_by" integer,
  "last_updated_by" integer,
  foreign key("customer_id") references customers("id") on delete set null on update no action,
  foreign key("user_id") references users("id") on delete cascade on update no action,
  foreign key("created_by") references "users"("id") on delete set null,
  foreign key("last_updated_by") references "users"("id") on delete set null
);
CREATE INDEX "appointments_user_id_date_index" on "appointments"(
  "user_id",
  "date"
);
CREATE TABLE "shift_templates"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "start_time" time not null,
  "end_time" time not null,
  "break_minutes" integer not null default '0',
  "created_at" datetime,
  "updated_at" datetime
);
CREATE UNIQUE INDEX "shift_templates_name_unique" on "shift_templates"("name");
CREATE TABLE "week_templates"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "created_at" datetime,
  "updated_at" datetime
);
CREATE UNIQUE INDEX "week_templates_name_unique" on "week_templates"("name");
CREATE TABLE "week_template_entries"(
  "id" integer primary key autoincrement not null,
  "week_template_id" integer not null,
  "user_id" integer not null,
  "shift_template_id" integer not null,
  "weekday" integer not null,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("week_template_id") references "week_templates"("id") on delete cascade,
  foreign key("user_id") references "users"("id") on delete cascade,
  foreign key("shift_template_id") references "shift_templates"("id") on delete cascade
);
CREATE UNIQUE INDEX "week_template_entries_week_template_id_user_id_weekday_unique" on "week_template_entries"(
  "week_template_id",
  "user_id",
  "weekday"
);
CREATE TABLE "holidays"(
  "id" integer primary key autoincrement not null,
  "date" date not null,
  "name" varchar not null,
  "is_closed" tinyint(1) not null default '1',
  "created_at" datetime,
  "updated_at" datetime
);
CREATE UNIQUE INDEX "holidays_date_unique" on "holidays"("date");
CREATE TABLE "schedules"(
  "id" integer primary key autoincrement not null,
  "user_id" integer not null,
  "date" date not null,
  "start_time" time,
  "end_time" time,
  "notes" text,
  "created_at" datetime,
  "updated_at" datetime,
  "break_minutes" integer not null default('0'),
  "status" varchar not null default('draft'),
  "type" varchar not null default('work'),
  "created_by" integer,
  "last_updated_by" integer,
  foreign key("user_id") references users("id") on delete cascade on update no action,
  foreign key("created_by") references "users"("id") on delete set null,
  foreign key("last_updated_by") references "users"("id") on delete set null
);
CREATE UNIQUE INDEX "schedules_user_id_date_unique" on "schedules"(
  "user_id",
  "date"
);
CREATE TABLE "permissions"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "guard_name" varchar not null,
  "created_at" datetime,
  "updated_at" datetime,
  "label" varchar,
  "description" varchar
);
CREATE UNIQUE INDEX "permissions_name_guard_name_unique" on "permissions"(
  "name",
  "guard_name"
);
CREATE TABLE "roles"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "guard_name" varchar not null,
  "created_at" datetime,
  "updated_at" datetime,
  "label" varchar,
  "level" integer not null default '0'
);
CREATE UNIQUE INDEX "roles_name_guard_name_unique" on "roles"(
  "name",
  "guard_name"
);
CREATE TABLE "model_has_permissions"(
  "permission_id" integer not null,
  "model_type" varchar not null,
  "model_id" integer not null,
  foreign key("permission_id") references "permissions"("id") on delete cascade,
  primary key("permission_id", "model_id", "model_type")
);
CREATE INDEX "model_has_permissions_model_id_model_type_index" on "model_has_permissions"(
  "model_id",
  "model_type"
);
CREATE TABLE "model_has_roles"(
  "role_id" integer not null,
  "model_type" varchar not null,
  "model_id" integer not null,
  foreign key("role_id") references "roles"("id") on delete cascade,
  primary key("role_id", "model_id", "model_type")
);
CREATE INDEX "model_has_roles_model_id_model_type_index" on "model_has_roles"(
  "model_id",
  "model_type"
);
CREATE TABLE "role_has_permissions"(
  "permission_id" integer not null,
  "role_id" integer not null,
  foreign key("permission_id") references "permissions"("id") on delete cascade,
  foreign key("role_id") references "roles"("id") on delete cascade,
  primary key("permission_id", "role_id")
);
CREATE TABLE "positions"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "created_at" datetime,
  "updated_at" datetime
);
CREATE UNIQUE INDEX "positions_name_unique" on "positions"("name");
CREATE TABLE "currencies"(
  "id" integer primary key autoincrement not null,
  "code" varchar not null,
  "name" varchar not null,
  "created_at" datetime,
  "updated_at" datetime,
  "rate" numeric not null default '1',
  "is_archived" tinyint(1) not null default '0'
);
CREATE UNIQUE INDEX "currencies_code_unique" on "currencies"("code");
CREATE TABLE "suppliers"(
  "id" integer primary key autoincrement not null,
  "type" varchar not null default('product'),
  "name" varchar not null,
  "phone" varchar,
  "created_at" datetime,
  "updated_at" datetime,
  "email" varchar,
  "account_number" varchar,
  "bank_account" varchar,
  "order_email" varchar,
  "orderable" tinyint(1) not null default('1'),
  "is_active" tinyint(1) not null default('1'),
  "price_multiplier" numeric not null default('1'),
  "address_civic" varchar,
  "address_apartment" varchar,
  "address_street" varchar,
  "address_city" varchar,
  "address_province" varchar,
  "address_country" varchar,
  "address_postal_code" varchar,
  "payment_address_civic" varchar,
  "payment_address_apartment" varchar,
  "payment_address_street" varchar,
  "payment_address_city" varchar,
  "payment_address_province" varchar,
  "payment_address_country" varchar,
  "payment_address_postal_code" varchar,
  "base_multiplier" numeric not null default('2'),
  "customs_fee" numeric not null default('0'),
  "shipping_fee" numeric not null default('0'),
  "currency_id" integer,
  "prepaid_amount" numeric default('0'),
  "collect" tinyint(1) not null default('0'),
  "default_shipping_supplier_id" integer,
  "early_payment_discount_percent" numeric not null default '0',
  "early_payment_discount_days" integer,
  "early_payment_next_month" tinyint(1) not null default '0',
  foreign key("currency_id") references currencies("id") on delete set null on update no action,
  foreign key("default_shipping_supplier_id") references "suppliers"("id") on delete set null
);
CREATE TABLE "supplier_order_lines"(
  "id" integer primary key autoincrement not null,
  "supplier_order_id" integer not null,
  "product_id" integer,
  "description" varchar,
  "quantity" integer not null,
  "unit_cost" numeric not null,
  "quantity_received" integer not null default('0'),
  "created_at" datetime,
  "updated_at" datetime,
  "status" varchar not null default('active'),
  "cancellation_reason" varchar,
  "cancellation_requested_at" datetime,
  "cancelled_at" datetime,
  "substituted_from_line_id" integer,
  foreign key("product_id") references products("id") on delete set null on update no action,
  foreign key("supplier_order_id") references supplier_orders("id") on delete cascade on update no action,
  foreign key("substituted_from_line_id") references "supplier_order_lines"("id") on delete set null
);
CREATE TABLE "supplier_orders"(
  "id" integer primary key autoincrement not null,
  "number" varchar,
  "type" varchar not null,
  "supplier_id" integer not null,
  "status" varchar not null default('draft'),
  "notes" text,
  "sent_at" datetime,
  "received_at" datetime,
  "created_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  "is_collect" tinyint(1) not null default '0',
  "shipping_supplier_id" integer,
  "quote_number" varchar,
  "last_emailed_at" datetime,
  "is_drop_ship" tinyint(1) not null default '0',
  "drop_ship_name" varchar,
  "drop_ship_address_civic" varchar,
  "drop_ship_address_apartment" varchar,
  "drop_ship_address_street" varchar,
  "drop_ship_address_city" varchar,
  "drop_ship_address_province" varchar,
  "drop_ship_address_country" varchar,
  "drop_ship_address_postal_code" varchar,
  foreign key("created_by") references users("id") on delete set null on update no action,
  foreign key("supplier_id") references suppliers("id") on delete restrict on update no action,
  foreign key("shipping_supplier_id") references "suppliers"("id") on delete set null
);
CREATE UNIQUE INDEX "supplier_orders_number_unique" on "supplier_orders"(
  "number"
);
CREATE INDEX "supplier_orders_type_status_index" on "supplier_orders"(
  "type",
  "status"
);
CREATE TABLE "receptions"(
  "id" integer primary key autoincrement not null,
  "number" varchar,
  "supplier_id" integer not null,
  "received_by" integer,
  "received_at" datetime not null,
  "reference" varchar,
  "notes" text,
  "created_at" datetime,
  "updated_at" datetime,
  "status" varchar not null default 'completed',
  "completed_at" datetime,
  foreign key("supplier_id") references "suppliers"("id") on delete restrict,
  foreign key("received_by") references "users"("id") on delete set null
);
CREATE UNIQUE INDEX "receptions_number_unique" on "receptions"("number");
CREATE TABLE "reception_lines"(
  "id" integer primary key autoincrement not null,
  "reception_id" integer not null,
  "supplier_order_line_id" integer not null,
  "product_id" integer,
  "quantity" integer not null,
  "unit_cost" numeric not null,
  "created_at" datetime,
  "updated_at" datetime,
  "quantity_reversed" integer not null default '0',
  "reversed_at" datetime,
  "reversed_by" integer,
  "reversal_reason" varchar,
  "quantity_damaged" integer not null default '0',
  foreign key("product_id") references products("id") on delete set null on update no action,
  foreign key("supplier_order_line_id") references supplier_order_lines("id") on delete restrict on update no action,
  foreign key("reception_id") references receptions("id") on delete cascade on update no action,
  foreign key("reversed_by") references "users"("id") on delete set null
);
CREATE TABLE "inventory_units"(
  "id" integer primary key autoincrement not null,
  "product_id" integer not null,
  "cost" numeric not null,
  "inserted_at" date not null,
  "delivered_at" date,
  "created_at" datetime,
  "updated_at" datetime,
  "reception_line_id" integer,
  foreign key("product_id") references products("id") on delete cascade on update no action,
  foreign key("reception_line_id") references "reception_lines"("id") on delete set null
);
CREATE INDEX "inventory_units_product_id_inserted_at_index" on "inventory_units"(
  "product_id",
  "inserted_at"
);
CREATE TABLE "supplier_invoices"(
  "id" integer primary key autoincrement not null,
  "supplier_id" integer not null,
  "reception_id" integer,
  "invoice_number" varchar not null,
  "invoice_date" date not null,
  "merchandise_total" numeric not null,
  "freight_fee" numeric not null default('0'),
  "customs_fee" numeric not null default('0'),
  "taxes" numeric not null default('0'),
  "computed_total" numeric not null,
  "invoice_total" numeric not null,
  "variance" numeric not null default('0'),
  "discount_percent" numeric not null default('0'),
  "discount_days" integer,
  "discount_due_date" date,
  "discount_amount" numeric not null default('0'),
  "created_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  "discount_next_month" tinyint(1) not null default('0'),
  "supplier_order_id" integer,
  "description" varchar,
  foreign key("created_by") references users("id") on delete set null on update no action,
  foreign key("reception_id") references receptions("id") on delete restrict on update no action,
  foreign key("supplier_id") references suppliers("id") on delete restrict on update no action,
  foreign key("supplier_order_id") references "supplier_orders"("id") on delete restrict
);
CREATE UNIQUE INDEX "supplier_invoices_reception_id_unique" on "supplier_invoices"(
  "reception_id"
);
CREATE UNIQUE INDEX "supplier_invoices_supplier_id_invoice_number_unique" on "supplier_invoices"(
  "supplier_id",
  "invoice_number"
);
CREATE UNIQUE INDEX "supplier_invoices_supplier_order_id_unique" on "supplier_invoices"(
  "supplier_order_id"
);
CREATE TABLE "supplier_invoice_lines"(
  "id" integer primary key autoincrement not null,
  "supplier_invoice_id" integer not null,
  "reception_line_id" integer,
  "quantity" integer not null,
  "unit_cost" numeric not null,
  "created_at" datetime,
  "updated_at" datetime,
  "supplier_order_line_id" integer,
  foreign key("reception_line_id") references reception_lines("id") on delete restrict on update no action,
  foreign key("supplier_invoice_id") references supplier_invoices("id") on delete cascade on update no action,
  foreign key("supplier_order_line_id") references "supplier_order_lines"("id") on delete restrict
);
CREATE TABLE "stores"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "type" varchar not null default('physical'),
  "phone" varchar,
  "email" varchar,
  "address_civic" varchar,
  "address_apartment" varchar,
  "address_street" varchar,
  "address_city" varchar,
  "address_province" varchar,
  "address_country" varchar,
  "address_postal_code" varchar,
  "bank_account" varchar,
  "opening_hours" text,
  "warehouse_store_id" integer,
  "is_active" tinyint(1) not null default('1'),
  "created_at" datetime,
  "updated_at" datetime,
  "shipping_warehouse_id" integer,
  "vacation_accrual_start" varchar,
  "sick_accrual_start" varchar,
  "sick_days_full_time" integer,
  "sick_days_part_time" integer,
  "cancellation_fee_percent" numeric not null default '0',
  "province" varchar not null default 'QC',
  foreign key("warehouse_store_id") references stores("id") on delete set null on update no action,
  foreign key("shipping_warehouse_id") references "stores"("id") on delete set null
);
CREATE TABLE "user_denied_permissions"(
  "user_id" integer not null,
  "permission_id" integer not null,
  foreign key("user_id") references "users"("id") on delete cascade,
  foreign key("permission_id") references "permissions"("id") on delete cascade,
  primary key("user_id", "permission_id")
);
CREATE TABLE "users"(
  "id" integer primary key autoincrement not null,
  "email" varchar not null,
  "email_verified_at" datetime,
  "password" varchar not null,
  "remember_token" varchar,
  "created_at" datetime,
  "updated_at" datetime,
  "is_active" tinyint(1) not null default('1'),
  "firstname" varchar,
  "lastname" varchar,
  "username" varchar,
  "first_day" date,
  "last_day" date,
  "personal_email" varchar,
  "phone" varchar,
  "cellphone" varchar,
  "last_modified" datetime,
  "last_modified_by" integer,
  "position_id" integer,
  "address_civic" varchar,
  "address_apartment" varchar,
  "address_street" varchar,
  "address_city" varchar,
  "address_province" varchar,
  "address_country" varchar,
  "address_postal_code" varchar,
  "is_full_time" tinyint(1) not null default('0'),
  "has_group_insurance" tinyint(1) not null default('0'),
  "insurance_plan" varchar,
  "is_salaried" tinyint(1) not null default('0'),
  "hourly_rate" numeric,
  "weekly_salary" numeric,
  "commission_rate" numeric,
  "has_commission" tinyint(1) not null default('0'),
  "has_bonus" tinyint(1) not null default('0'),
  "weekly_sales_target" integer,
  "bonus_amount" integer,
  "bonus_step" integer,
  "hours_per_day" numeric,
  "vacation_days_accrued" numeric,
  "vacation_hours_available" numeric,
  "vacation_balance_computed_at" datetime,
  "store_id" integer,
  "search_name" varchar,
  foreign key("position_id") references positions("id") on delete set null on update no action,
  foreign key("last_modified_by") references users("id") on delete set null on update no action,
  foreign key("store_id") references "stores"("id") on delete set null
);
CREATE UNIQUE INDEX "users_email_unique" on "users"("email");
CREATE UNIQUE INDEX "users_username_unique" on "users"("username");
CREATE TABLE "payroll_periods"(
  "id" integer primary key autoincrement not null,
  "start_date" date not null,
  "end_date" date not null,
  "locked_at" datetime not null,
  "locked_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("locked_by") references "users"("id") on delete set null
);
CREATE UNIQUE INDEX "payroll_periods_start_date_unique" on "payroll_periods"(
  "start_date"
);
CREATE TABLE "price_lists"(
  "id" integer primary key autoincrement not null,
  "supplier_id" integer not null,
  "starts_on" date not null,
  "ends_on" date not null,
  "archived_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("supplier_id") references "suppliers"("id") on delete cascade
);
CREATE INDEX "price_lists_supplier_id_archived_at_index" on "price_lists"(
  "supplier_id",
  "archived_at"
);
CREATE TABLE "price_list_lists"(
  "id" integer primary key autoincrement not null,
  "price_list_id" integer not null,
  "name" varchar not null,
  "discount_percent" numeric not null default '0',
  "created_at" datetime,
  "updated_at" datetime,
  "applied_at" datetime,
  foreign key("price_list_id") references "price_lists"("id") on delete cascade
);
CREATE TABLE "price_list_items"(
  "id" integer primary key autoincrement not null,
  "price_list_list_id" integer not null,
  "product_id" integer,
  "model" varchar not null,
  "clean_model" varchar not null,
  "cost" numeric,
  "imap" numeric,
  "upc" varchar,
  "collection" varchar,
  "description" text,
  "length" numeric,
  "width" numeric,
  "height" numeric,
  "weight" numeric,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("price_list_list_id") references "price_list_lists"("id") on delete cascade,
  foreign key("product_id") references "products"("id") on delete set null
);
CREATE INDEX "price_list_items_clean_model_index" on "price_list_items"(
  "clean_model"
);
CREATE TABLE "product_upcs"(
  "id" integer primary key autoincrement not null,
  "product_id" integer not null,
  "upc" varchar not null,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("product_id") references "products"("id") on delete cascade
);
CREATE UNIQUE INDEX "product_upcs_product_id_upc_unique" on "product_upcs"(
  "product_id",
  "upc"
);
CREATE INDEX "product_upcs_upc_index" on "product_upcs"("upc");
CREATE INDEX "products_supplier_clean_model_index" on "products"(
  "supplier_clean_model"
);
CREATE TABLE "customer_order_salesperson"(
  "id" integer primary key autoincrement not null,
  "customer_order_id" integer not null,
  "user_id" integer not null,
  "percent" integer not null,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("customer_order_id") references "customer_orders"("id") on delete cascade,
  foreign key("user_id") references "users"("id") on delete restrict
);
CREATE UNIQUE INDEX "customer_order_salesperson_customer_order_id_user_id_unique" on "customer_order_salesperson"(
  "customer_order_id",
  "user_id"
);
CREATE TABLE "customer_order_pickups"(
  "id" integer primary key autoincrement not null,
  "customer_order_id" integer not null,
  "handled_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("customer_order_id") references "customer_orders"("id") on delete cascade,
  foreign key("handled_by") references "users"("id") on delete set null
);
CREATE INDEX "customers_search_name_index" on "customers"("search_name");
CREATE INDEX "users_search_name_index" on "users"("search_name");
CREATE TABLE "customer_order_payments"(
  "id" integer primary key autoincrement not null,
  "customer_order_id" integer not null,
  "customer_order_pickup_id" integer,
  "customer_payment_method_id" integer,
  "amount" numeric not null,
  "received_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  "type" varchar not null default 'payment',
  "rounding_adjustment" numeric,
  "cash_tendered" numeric,
  "change_given" numeric,
  foreign key("received_by") references users("id") on delete set null on update no action,
  foreign key("customer_payment_method_id") references customer_payment_methods("id") on delete restrict on update no action,
  foreign key("customer_order_pickup_id") references customer_order_pickups("id") on delete set null on update no action,
  foreign key("customer_order_id") references customer_orders("id") on delete restrict on update no action
);
CREATE UNIQUE INDEX "customer_payment_methods_code_unique" on "customer_payment_methods"(
  "code"
);
CREATE TABLE "customer_orders"(
  "id" integer primary key autoincrement not null,
  "customer_id" integer not null,
  "status" varchar not null default('new'),
  "balance_due" numeric not null default('0'),
  "created_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  "subtotal" numeric not null default('0'),
  "total" numeric not null default('0'),
  "amount_paid" numeric not null default('0'),
  "store_id" integer,
  foreign key("created_by") references users("id") on delete set null on update no action,
  foreign key("customer_id") references customers("id") on delete restrict on update no action,
  foreign key("store_id") references "stores"("id") on delete set null
);
CREATE INDEX "customer_orders_status_index" on "customer_orders"("status");
CREATE TABLE "defective_products"(
  "id" integer primary key autoincrement not null,
  "product_id" integer not null,
  "customer_order_id" integer,
  "customer_order_line_id" integer,
  "quantity" integer not null,
  "resolution" varchar not null,
  "status" varchar not null default('open'),
  "reason" text,
  "replacement_part" varchar,
  "supplier_order_line_id" integer,
  "photo_path" varchar,
  "created_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  "reception_line_id" integer,
  foreign key("created_by") references users("id") on delete set null on update no action,
  foreign key("supplier_order_line_id") references supplier_order_lines("id") on delete set null on update no action,
  foreign key("customer_order_line_id") references customer_order_lines("id") on delete set null on update no action,
  foreign key("customer_order_id") references customer_orders("id") on delete set null on update no action,
  foreign key("product_id") references products("id") on delete restrict on update no action,
  foreign key("reception_line_id") references "reception_lines"("id") on delete set null
);
CREATE INDEX "defective_products_status_resolution_index" on "defective_products"(
  "status",
  "resolution"
);
CREATE TABLE "inventory_movements"(
  "id" integer primary key autoincrement not null,
  "product_id" integer not null,
  "from_status" varchar,
  "to_status" varchar,
  "quantity" integer not null,
  "type" varchar not null,
  "reference_type" varchar,
  "reference_id" integer,
  "user_id" integer,
  "note" varchar,
  "created_at" datetime not null default(CURRENT_TIMESTAMP),
  foreign key("user_id") references users("id") on delete set null on update no action,
  foreign key("product_id") references "products"("id") on delete restrict
);
CREATE INDEX "inventory_movements_product_id_created_at_index" on "inventory_movements"(
  "product_id",
  "created_at"
);
CREATE INDEX "inventory_movements_reference_type_reference_id_index" on "inventory_movements"(
  "reference_type",
  "reference_id"
);
CREATE TABLE "taxes"(
  "id" integer primary key autoincrement not null,
  "province" varchar not null,
  "name" varchar not null,
  "rate" numeric not null,
  "is_compound" tinyint(1) not null default '0',
  "start_date" date not null,
  "end_date" date not null default '2100-12-31',
  "created_at" datetime,
  "updated_at" datetime
);
CREATE INDEX "taxes_province_start_date_end_date_index" on "taxes"(
  "province",
  "start_date",
  "end_date"
);
CREATE TABLE "store_tax_registrations"(
  "id" integer primary key autoincrement not null,
  "store_id" integer not null,
  "tax_name" varchar not null,
  "number" varchar not null,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("store_id") references "stores"("id") on delete cascade
);
CREATE UNIQUE INDEX "store_tax_registrations_store_id_tax_name_unique" on "store_tax_registrations"(
  "store_id",
  "tax_name"
);
CREATE TABLE "customer_order_taxes"(
  "id" integer primary key autoincrement not null,
  "customer_order_id" integer not null,
  "tax_id" integer,
  "name" varchar not null,
  "rate" numeric not null,
  "is_compound" tinyint(1) not null default '0',
  "registration_number" varchar,
  "amount" numeric not null default '0',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("customer_order_id") references "customer_orders"("id") on delete cascade,
  foreign key("tax_id") references "taxes"("id") on delete set null
);
CREATE TABLE "services"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "description_template" text,
  "is_internal" tinyint(1) not null default '0',
  "selling_price" numeric,
  "is_taxable" tinyint(1) not null default '1',
  "is_active" tinyint(1) not null default '1',
  "created_at" datetime,
  "updated_at" datetime
);
CREATE UNIQUE INDEX "services_name_unique" on "services"("name");
CREATE TABLE "service_supplier"(
  "id" integer primary key autoincrement not null,
  "service_id" integer not null,
  "supplier_id" integer not null,
  "cost" numeric not null default '0',
  "selling_price" numeric not null default '0',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("service_id") references "services"("id") on delete cascade,
  foreign key("supplier_id") references "suppliers"("id") on delete cascade
);
CREATE UNIQUE INDEX "service_supplier_service_id_supplier_id_unique" on "service_supplier"(
  "service_id",
  "supplier_id"
);
CREATE TABLE "customer_order_lines"(
  "id" integer primary key autoincrement not null,
  "customer_order_id" integer not null,
  "product_id" integer,
  "quantity" integer not null,
  "unit_price" numeric not null,
  "note" text,
  "status" varchar not null,
  "delivered_at" datetime,
  "returned_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  "quantity_reserved" integer not null default('0'),
  "quantity_on_order" integer not null default('0'),
  "supplier_order_line_id" integer,
  "customer_order_pickup_id" integer,
  "cancellation_fee" numeric,
  "service_id" integer,
  "supplier_id" integer,
  "description" varchar,
  "is_taxable" tinyint(1) not null default '1',
  foreign key("customer_order_pickup_id") references customer_order_pickups("id") on delete set null on update no action,
  foreign key("product_id") references products("id") on delete restrict on update no action,
  foreign key("customer_order_id") references customer_orders("id") on delete cascade on update no action,
  foreign key("supplier_order_line_id") references supplier_order_lines("id") on delete set null on update no action,
  foreign key("service_id") references "services"("id") on delete restrict,
  foreign key("supplier_id") references "suppliers"("id") on delete restrict
);
CREATE INDEX "customer_order_lines_status_index" on "customer_order_lines"(
  "status"
);

INSERT INTO migrations VALUES(1,'0001_01_01_000000_create_users_table',1);
INSERT INTO migrations VALUES(2,'0001_01_01_000001_create_cache_table',1);
INSERT INTO migrations VALUES(3,'0001_01_01_000002_create_jobs_table',1);
INSERT INTO migrations VALUES(4,'2026_09_24_000001_add_is_active_to_users_table',1);
INSERT INTO migrations VALUES(5,'2026_09_24_000002_add_user_profile_fields_to_users_table',1);
INSERT INTO migrations VALUES(6,'2026_09_24_000003_drop_name_from_users_table',1);
INSERT INTO migrations VALUES(7,'2026_09_25_000001_add_personal_email_to_users_table',1);
INSERT INTO migrations VALUES(8,'2026_09_26_175503_create_customers_table',2);
INSERT INTO migrations VALUES(9,'2026_09_26_184721_add_phone_cellphone_to_users_table',2);
INSERT INTO migrations VALUES(10,'2026_09_26_191906_create_schedules_table',2);
INSERT INTO migrations VALUES(11,'2026_09_26_194737_add_break_minutes_to_schedules_table',2);
INSERT INTO migrations VALUES(12,'2026_09_26_220826_add_status_to_schedules_table',2);
INSERT INTO migrations VALUES(13,'2026_09_26_224542_create_appointments_table',2);
INSERT INTO migrations VALUES(14,'2026_09_26_225507_add_customer_id_to_appointments_table',2);
INSERT INTO migrations VALUES(15,'2026_09_26_225921_add_duration_hours_to_appointments_table',2);
INSERT INTO migrations VALUES(16,'2026_09_27_154123_create_suppliers_table',2);
INSERT INTO migrations VALUES(17,'2026_09_27_161316_add_fields_to_suppliers_table',2);
INSERT INTO migrations VALUES(18,'2026_09_27_163508_migrate_user_role_to_salesman',2);
INSERT INTO migrations VALUES(19,'2026_09_27_202003_create_customer_payment_methods_table',2);
INSERT INTO migrations VALUES(20,'2026_09_27_202004_create_merchant_payment_methods_table',2);
INSERT INTO migrations VALUES(21,'2026_09_27_232331_add_price_multiplier_to_suppliers_table',2);
INSERT INTO migrations VALUES(22,'2026_09_27_232332_create_products_table',2);
INSERT INTO migrations VALUES(23,'2026_09_27_233711_create_inventory_units_table',2);
INSERT INTO migrations VALUES(24,'2026_09_27_233712_create_inventory_stocks_table',2);
INSERT INTO migrations VALUES(25,'2026_09_27_235027_create_departments_table',2);
INSERT INTO migrations VALUES(26,'2026_09_27_235028_create_categories_table',2);
INSERT INTO migrations VALUES(27,'2026_09_27_235029_create_colors_table',2);
INSERT INTO migrations VALUES(28,'2026_09_27_235030_add_catalog_fields_to_products_table',2);
INSERT INTO migrations VALUES(29,'2026_09_28_001941_rename_name_add_supplier_model_to_products_table',2);
INSERT INTO migrations VALUES(30,'2026_09_28_003407_add_clean_model_to_products_table',2);
INSERT INTO migrations VALUES(31,'2026_09_28_005236_restructure_address_fields_on_suppliers_table',2);
INSERT INTO migrations VALUES(32,'2026_09_28_005828_restructure_address_field_on_customers_table',2);
INSERT INTO migrations VALUES(33,'2026_09_28_231743_convert_appointments_to_minutes',2);
INSERT INTO migrations VALUES(34,'2026_09_28_235524_add_audit_columns_to_appointments_table',2);
INSERT INTO migrations VALUES(35,'2026_09_29_000001_create_shift_templates_table',2);
INSERT INTO migrations VALUES(36,'2026_09_29_000002_create_week_templates_table',2);
INSERT INTO migrations VALUES(37,'2026_09_29_000003_create_week_template_entries_table',2);
INSERT INTO migrations VALUES(38,'2026_09_29_000010_add_type_to_schedules_table',2);
INSERT INTO migrations VALUES(39,'2026_09_29_000011_create_holidays_table',2);
INSERT INTO migrations VALUES(40,'2026_09_29_000733_add_audit_columns_to_schedules_table',2);
INSERT INTO migrations VALUES(41,'2026_09_30_014856_add_last_modified_to_users_table',2);
INSERT INTO migrations VALUES(42,'2026_10_01_234042_create_permission_tables',2);
INSERT INTO migrations VALUES(43,'2026_10_01_234106_add_access_columns_to_roles_and_permissions_tables',2);
INSERT INTO migrations VALUES(44,'2026_10_01_234107_create_positions_table',2);
INSERT INTO migrations VALUES(45,'2026_10_01_234108_migrate_user_roles_to_permission_tables',2);
INSERT INTO migrations VALUES(46,'2026_10_02_000147_grant_page_view_permissions_to_existing_roles',2);
INSERT INTO migrations VALUES(47,'2026_10_02_000408_add_action_permissions_and_rename_users_update',2);
INSERT INTO migrations VALUES(48,'2026_10_02_003530_add_address_columns_to_users_table',2);
INSERT INTO migrations VALUES(49,'2026_10_02_003752_remove_kitchen_role',2);
INSERT INTO migrations VALUES(50,'2026_10_02_011917_add_price_components_to_suppliers_table',2);
INSERT INTO migrations VALUES(51,'2026_10_02_012150_create_currencies_table',2);
INSERT INTO migrations VALUES(52,'2026_10_02_012151_add_currency_id_to_suppliers_table',2);
INSERT INTO migrations VALUES(53,'2026_10_02_013308_add_rate_to_currencies_table',2);
INSERT INTO migrations VALUES(54,'2026_10_02_013812_drop_exchange_rate_from_suppliers_table',2);
INSERT INTO migrations VALUES(55,'2026_10_02_015227_archive_currencies_and_drop_symbol',2);
INSERT INTO migrations VALUES(56,'2026_10_02_015829_change_supplier_type_to_string',2);
INSERT INTO migrations VALUES(57,'2026_10_02_020317_add_freight_to_suppliers_table',2);
INSERT INTO migrations VALUES(58,'2026_10_02_020407_add_default_shipping_supplier_to_suppliers_table',2);
INSERT INTO migrations VALUES(59,'2026_10_02_222926_create_supplier_orders_table',2);
INSERT INTO migrations VALUES(60,'2026_10_02_222927_create_supplier_order_lines_table',2);
INSERT INTO migrations VALUES(61,'2026_10_02_223500_add_supplier_order_permissions',2);
INSERT INTO migrations VALUES(62,'2026_10_02_223742_add_status_to_supplier_order_lines_table',2);
INSERT INTO migrations VALUES(63,'2026_10_02_230315_add_substitution_to_supplier_order_lines_table',2);
INSERT INTO migrations VALUES(64,'2026_10_02_231500_add_supplier_order_delete_permission',2);
INSERT INTO migrations VALUES(65,'2026_10_03_005611_add_shipping_to_supplier_orders_table',2);
INSERT INTO migrations VALUES(66,'2026_10_03_010118_add_quote_number_to_supplier_orders_table',2);
INSERT INTO migrations VALUES(67,'2026_10_03_011154_add_last_emailed_at_to_supplier_orders_table',2);
INSERT INTO migrations VALUES(68,'2026_10_03_012619_add_drop_ship_to_supplier_orders_table',2);
INSERT INTO migrations VALUES(69,'2026_10_03_015323_create_receptions_table',2);
INSERT INTO migrations VALUES(70,'2026_10_03_015324_create_reception_lines_table',2);
INSERT INTO migrations VALUES(71,'2026_10_03_020000_add_reception_permissions',2);
INSERT INTO migrations VALUES(72,'2026_10_03_020322_add_reversal_to_reception_lines_and_unit_link_to_inventory_units',2);
INSERT INTO migrations VALUES(73,'2026_10_03_020810_create_inventory_movements_table',2);
INSERT INTO migrations VALUES(74,'2026_10_03_020811_add_states_to_inventory_stocks_table',2);
INSERT INTO migrations VALUES(75,'2026_10_03_030000_add_reception_reverse_permission',2);
INSERT INTO migrations VALUES(76,'2026_10_03_040000_add_inventory_permissions',2);
INSERT INTO migrations VALUES(77,'2026_10_03_184017_add_collection_and_dimensions_to_products_table',2);
INSERT INTO migrations VALUES(78,'2026_10_03_215817_add_status_to_receptions_table',2);
INSERT INTO migrations VALUES(79,'2026_10_03_221531_create_supplier_invoices_table',2);
INSERT INTO migrations VALUES(80,'2026_10_03_221532_create_supplier_invoice_lines_table',2);
INSERT INTO migrations VALUES(81,'2026_10_03_221533_add_early_payment_discount_to_suppliers_table',2);
INSERT INTO migrations VALUES(82,'2026_10_03_222000_add_invoice_permissions',2);
INSERT INTO migrations VALUES(83,'2026_10_03_222207_add_early_payment_next_month',2);
INSERT INTO migrations VALUES(84,'2026_10_03_223312_let_supplier_invoices_cover_service_orders',2);
INSERT INTO migrations VALUES(85,'2026_10_03_224209_add_description_to_supplier_invoices_table',2);
INSERT INTO migrations VALUES(86,'2026_10_03_234026_create_stores_table',2);
INSERT INTO migrations VALUES(87,'2026_10_03_234027_add_store_permissions',2);
INSERT INTO migrations VALUES(88,'2026_10_04_000415_add_shipping_warehouse_id_to_stores_table',2);
INSERT INTO migrations VALUES(89,'2026_10_04_000759_add_accrual_settings_to_stores_table',2);
INSERT INTO migrations VALUES(90,'2026_10_04_151917_create_user_denied_permissions_table',2);
INSERT INTO migrations VALUES(91,'2026_10_04_153741_add_hr_columns_to_users_table',2);
INSERT INTO migrations VALUES(92,'2026_10_04_154103_add_hr_permissions',2);
INSERT INTO migrations VALUES(93,'2026_10_04_160448_add_commission_and_bonus_columns_to_users_table',2);
INSERT INTO migrations VALUES(94,'2026_10_04_195409_add_vacation_columns_to_users_table',2);
INSERT INTO migrations VALUES(95,'2026_10_04_202410_add_store_and_hours_per_day_to_users_table',2);
INSERT INTO migrations VALUES(96,'2026_10_04_202411_create_payroll_periods_table',2);
INSERT INTO migrations VALUES(97,'2026_10_04_202412_add_payroll_permissions',2);
INSERT INTO migrations VALUES(98,'2026_10_04_213637_create_price_lists_table',2);
INSERT INTO migrations VALUES(99,'2026_10_04_213638_add_price_list_permissions',2);
INSERT INTO migrations VALUES(100,'2026_10_04_215434_create_price_list_lists_table',2);
INSERT INTO migrations VALUES(101,'2026_10_04_215435_create_price_list_items_table',2);
INSERT INTO migrations VALUES(102,'2026_10_04_215907_create_product_upcs_table',2);
INSERT INTO migrations VALUES(103,'2026_10_04_230526_add_supplier_clean_model_to_products_table',2);
INSERT INTO migrations VALUES(104,'2026_10_04_231112_backfill_supplier_model_on_products',2);
INSERT INTO migrations VALUES(105,'2026_10_04_234626_add_applied_at_to_price_list_lists_table',2);
INSERT INTO migrations VALUES(106,'2026_10_06_233727_add_customer_order_permissions',2);
INSERT INTO migrations VALUES(107,'2026_10_07_013452_add_customer_order_assign_salespeople_permission',2);
INSERT INTO migrations VALUES(108,'2026_10_07_231330_create_customer_orders_table',2);
INSERT INTO migrations VALUES(109,'2026_10_07_231331_create_customer_order_salesperson_table',2);
INSERT INTO migrations VALUES(110,'2026_10_07_233302_create_customer_order_lines_table',2);
INSERT INTO migrations VALUES(111,'2026_10_07_235215_add_customer_order_create_edit_permissions',2);
INSERT INTO migrations VALUES(112,'2026_10_08_000347_add_stock_quantities_to_customer_order_lines_table',2);
INSERT INTO migrations VALUES(113,'2026_10_08_003512_add_supplier_order_line_id_to_customer_order_lines_table',2);
INSERT INTO migrations VALUES(114,'2026_10_08_232403_add_payment_method_permissions',2);
INSERT INTO migrations VALUES(115,'2026_10_08_232404_add_tax_totals_to_customer_orders_table',2);
INSERT INTO migrations VALUES(116,'2026_10_08_232405_create_customer_order_pickups_table',2);
INSERT INTO migrations VALUES(117,'2026_10_08_232406_create_customer_order_payments_table',2);
INSERT INTO migrations VALUES(118,'2026_10_09_002514_add_search_name_to_customers_table',2);
INSERT INTO migrations VALUES(119,'2026_10_09_003054_add_search_name_to_users_table',2);
INSERT INTO migrations VALUES(120,'2026_10_09_005918_add_credit_balance_to_customers_table',2);
INSERT INTO migrations VALUES(121,'2026_10_09_005919_add_type_to_customer_order_payments_table',2);
INSERT INTO migrations VALUES(122,'2026_10_09_011131_add_code_to_customer_payment_methods_table',2);
INSERT INTO migrations VALUES(123,'2026_10_09_011132_add_cash_details_to_customer_order_payments_table',2);
INSERT INTO migrations VALUES(124,'2026_10_09_150121_add_cancellation_fee_percent_to_stores_table',2);
INSERT INTO migrations VALUES(125,'2026_10_09_150122_add_store_id_to_customer_orders_table',2);
INSERT INTO migrations VALUES(126,'2026_10_09_150123_add_cancellation_fee_to_customer_order_lines_table',2);
INSERT INTO migrations VALUES(127,'2026_10_10_003112_create_defective_products_table',2);
INSERT INTO migrations VALUES(128,'2026_10_10_004316_add_damaged_receipts',2);
INSERT INTO migrations VALUES(129,'2026_10_10_154719_restrict_product_deletion_on_inventory_movements_table',2);
INSERT INTO migrations VALUES(130,'2026_10_10_173537_create_taxes_table',3);
INSERT INTO migrations VALUES(131,'2026_10_10_173544_add_tax_permissions',3);
INSERT INTO migrations VALUES(132,'2026_10_10_180545_add_province_to_stores_table',3);
INSERT INTO migrations VALUES(133,'2026_10_10_180546_create_store_tax_registrations_table',3);
INSERT INTO migrations VALUES(134,'2026_10_10_181236_create_customer_order_taxes_table',3);
INSERT INTO migrations VALUES(135,'2026_10_10_202303_create_services_table',4);
INSERT INTO migrations VALUES(136,'2026_10_10_202304_create_service_supplier_table',4);
INSERT INTO migrations VALUES(137,'2026_10_10_202305_add_is_taxable_to_products_table',4);
INSERT INTO migrations VALUES(138,'2026_10_10_202306_add_service_columns_to_customer_order_lines_table',4);
INSERT INTO migrations VALUES(139,'2026_10_10_202307_add_service_permissions',4);
