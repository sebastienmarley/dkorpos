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
CREATE INDEX "sessions_last_activity_index" on "sessions"("last_activity");
CREATE INDEX "sessions_user_id_index" on "sessions"("user_id");
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
  "credit_balance" integer not null default '0'
);
CREATE UNIQUE INDEX "customers_email_unique" on "customers"("email");
CREATE INDEX "customers_search_name_index" on "customers"("search_name");
CREATE TABLE "customer_payment_methods"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "is_active" tinyint(1) not null default '1',
  "created_at" datetime,
  "updated_at" datetime,
  "code" varchar,
  "is_usable_on_delivery" tinyint(1) not null default '0'
);
CREATE UNIQUE INDEX "customer_payment_methods_code_unique" on "customer_payment_methods"(
  "code"
);
CREATE TABLE "merchant_payment_methods"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "account_type_id" integer,
  "is_active" tinyint(1) not null default '1',
  "created_at" datetime,
  "updated_at" datetime
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
  "department_id" integer not null,
  "name" varchar not null,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("department_id") references "departments"("id") on delete restrict
);
CREATE TABLE "colors"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "hex_code" varchar,
  "created_at" datetime,
  "updated_at" datetime
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
  "shift_template_id" integer not null,
  "weekday" integer not null,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("week_template_id") references "week_templates"("id") on delete cascade,
  foreign key("shift_template_id") references "shift_templates"("id") on delete cascade
);
CREATE UNIQUE INDEX "week_template_entries_week_template_id_weekday_unique" on "week_template_entries"(
  "week_template_id",
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
  "type" varchar not null default 'product',
  "name" varchar not null,
  "phone" varchar,
  "created_at" datetime,
  "updated_at" datetime,
  "email" varchar,
  "account_number" varchar,
  "bank_account" varchar,
  "order_email" varchar,
  "orderable" tinyint(1) not null default '1',
  "is_active" tinyint(1) not null default '1',
  "price_multiplier" numeric not null default '1',
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
  "base_multiplier" numeric not null default '2',
  "customs_fee" numeric not null default '0',
  "shipping_fee" numeric not null default '0',
  "currency_id" integer,
  "prepaid_amount" integer default '0',
  "collect" tinyint(1) not null default '0',
  "default_shipping_supplier_id" integer,
  "early_payment_discount_percent" numeric not null default '0',
  "early_payment_discount_days" integer,
  "early_payment_next_month" tinyint(1) not null default '0',
  "custom_made" tinyint(1) not null default '0',
  foreign key("currency_id") references "currencies"("id") on delete set null,
  foreign key("default_shipping_supplier_id") references "suppliers"("id") on delete set null
);
CREATE TABLE "stores"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "type" varchar not null default 'physical',
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
  "is_active" tinyint(1) not null default '1',
  "created_at" datetime,
  "updated_at" datetime,
  "shipping_warehouse_id" integer,
  "vacation_accrual_start" varchar,
  "sick_accrual_start" varchar,
  "sick_days_full_time" integer,
  "sick_days_part_time" integer,
  "cancellation_fee_percent" numeric not null default '0',
  "province" varchar not null default 'QC',
  "custom_deposit_percent" numeric not null default '50',
  "shipping_max_length" numeric,
  "shipping_max_weight" numeric,
  foreign key("shipping_warehouse_id") references "stores"("id") on delete set null,
  foreign key("warehouse_store_id") references "stores"("id") on delete set null
);
CREATE TABLE "users"(
  "id" integer primary key autoincrement not null,
  "email" varchar not null,
  "email_verified_at" datetime,
  "password" varchar not null,
  "remember_token" varchar,
  "created_at" datetime,
  "updated_at" datetime,
  "is_active" tinyint(1) not null default '1',
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
  "is_full_time" tinyint(1) not null default '0',
  "has_group_insurance" tinyint(1) not null default '0',
  "insurance_plan" varchar,
  "is_salaried" tinyint(1) not null default '0',
  "hourly_rate" integer,
  "weekly_salary" integer,
  "commission_rate" numeric,
  "has_commission" tinyint(1) not null default '0',
  "has_bonus" tinyint(1) not null default '0',
  "weekly_sales_target" integer,
  "bonus_amount" integer,
  "bonus_step" integer,
  "hours_per_day" numeric,
  "vacation_days_accrued" numeric,
  "vacation_hours_available" numeric,
  "vacation_balance_computed_at" datetime,
  "store_id" integer,
  "search_name" varchar,
  "two_factor_secret" text,
  "two_factor_recovery_codes" text,
  "two_factor_confirmed_at" datetime,
  "pin" varchar,
  foreign key("position_id") references "positions"("id") on delete set null,
  foreign key("store_id") references "stores"("id") on delete set null,
  foreign key("last_modified_by") references "users"("id") on delete set null
);
CREATE UNIQUE INDEX "users_email_unique" on "users"("email");
CREATE INDEX "users_search_name_index" on "users"("search_name");
CREATE UNIQUE INDEX "users_username_unique" on "users"("username");
CREATE TABLE "schedules"(
  "id" integer primary key autoincrement not null,
  "user_id" integer not null,
  "date" date not null,
  "start_time" time,
  "end_time" time,
  "notes" text,
  "created_at" datetime,
  "updated_at" datetime,
  "break_minutes" integer not null default '0',
  "status" varchar not null default 'draft',
  "type" varchar not null default 'work',
  "created_by" integer,
  "last_updated_by" integer,
  foreign key("user_id") references "users"("id") on delete cascade,
  foreign key("created_by") references "users"("id") on delete set null,
  foreign key("last_updated_by") references "users"("id") on delete set null
);
CREATE UNIQUE INDEX "schedules_user_id_date_unique" on "schedules"(
  "user_id",
  "date"
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
  "start_minute" integer not null default '0',
  "duration_minutes" integer not null default '60',
  "created_by" integer,
  "last_updated_by" integer,
  foreign key("user_id") references "users"("id") on delete cascade,
  foreign key("customer_id") references "customers"("id") on delete set null,
  foreign key("created_by") references "users"("id") on delete set null,
  foreign key("last_updated_by") references "users"("id") on delete set null
);
CREATE INDEX "appointments_user_id_date_index" on "appointments"(
  "user_id",
  "date"
);
CREATE TABLE "supplier_orders"(
  "id" integer primary key autoincrement not null,
  "number" varchar,
  "type" varchar not null,
  "supplier_id" integer not null,
  "status" varchar not null default 'draft',
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
  "store_id" integer,
  foreign key("supplier_id") references "suppliers"("id") on delete restrict,
  foreign key("created_by") references "users"("id") on delete set null,
  foreign key("shipping_supplier_id") references "suppliers"("id") on delete set null,
  foreign key("store_id") references "stores"("id") on delete restrict
);
CREATE UNIQUE INDEX "supplier_orders_number_unique" on "supplier_orders"(
  "number"
);
CREATE INDEX "supplier_orders_store_id_index" on "supplier_orders"("store_id");
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
  "store_id" integer,
  "exchange_rate" numeric not null default '1',
  foreign key("supplier_id") references "suppliers"("id") on delete restrict,
  foreign key("received_by") references "users"("id") on delete set null,
  foreign key("store_id") references "stores"("id") on delete restrict
);
CREATE UNIQUE INDEX "receptions_number_unique" on "receptions"("number");
CREATE INDEX "receptions_store_id_index" on "receptions"("store_id");
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
CREATE TABLE "customer_orders"(
  "id" integer primary key autoincrement not null,
  "customer_id" integer not null,
  "status" varchar not null default 'new',
  "balance_due" integer not null default '0',
  "created_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  "subtotal" integer not null default '0',
  "total" integer not null default '0',
  "amount_paid" integer not null default '0',
  "store_id" integer,
  foreign key("customer_id") references "customers"("id") on delete restrict,
  foreign key("created_by") references "users"("id") on delete set null,
  foreign key("store_id") references "stores"("id") on delete set null
);
CREATE INDEX "customer_orders_status_index" on "customer_orders"("status");
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
CREATE TABLE "customer_order_payments"(
  "id" integer primary key autoincrement not null,
  "customer_order_id" integer not null,
  "customer_order_pickup_id" integer,
  "customer_payment_method_id" integer,
  "amount" integer not null,
  "received_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  "type" varchar not null default 'payment',
  "rounding_adjustment" integer,
  "cash_tendered" integer,
  "change_given" integer,
  foreign key("customer_order_id") references "customer_orders"("id") on delete restrict,
  foreign key("customer_order_pickup_id") references "customer_order_pickups"("id") on delete set null,
  foreign key("customer_payment_method_id") references "customer_payment_methods"("id") on delete restrict,
  foreign key("received_by") references "users"("id") on delete set null
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
  "amount" integer not null default '0',
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
  "selling_price" integer,
  "is_taxable" tinyint(1) not null default '1',
  "is_active" tinyint(1) not null default '1',
  "created_at" datetime,
  "updated_at" datetime,
  "code" varchar
);
CREATE UNIQUE INDEX "services_code_unique" on "services"("code");
CREATE UNIQUE INDEX "services_name_unique" on "services"("name");
CREATE TABLE "service_supplier"(
  "id" integer primary key autoincrement not null,
  "service_id" integer not null,
  "supplier_id" integer not null,
  "cost" integer not null default '0',
  "selling_price" integer not null default '0',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("service_id") references "services"("id") on delete cascade,
  foreign key("supplier_id") references "suppliers"("id") on delete cascade
);
CREATE UNIQUE INDEX "service_supplier_service_id_supplier_id_unique" on "service_supplier"(
  "service_id",
  "supplier_id"
);
CREATE TABLE "parts"(
  "id" integer primary key autoincrement not null,
  "supplier_id" integer not null,
  "model" varchar not null,
  "clean_model" varchar not null,
  "description" varchar not null,
  "last_cost" integer not null default '0',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("supplier_id") references "suppliers"("id") on delete cascade
);
CREATE UNIQUE INDEX "parts_supplier_id_clean_model_unique" on "parts"(
  "supplier_id",
  "clean_model"
);
CREATE TABLE "styles"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "created_at" datetime,
  "updated_at" datetime
);
CREATE UNIQUE INDEX "styles_name_unique" on "styles"("name");
CREATE TABLE "products"(
  "id" integer primary key autoincrement not null,
  "supplier_id" integer not null,
  "model" varchar not null,
  "cost" integer not null,
  "created_at" datetime,
  "updated_at" datetime,
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
  "imap" integer,
  "supplier_clean_model" varchar,
  "is_taxable" tinyint(1) not null default '1',
  "is_custom" tinyint(1) not null default '0',
  "style_id" integer,
  "displayed_price" integer,
  foreign key("supplier_id") references "suppliers"("id") on delete cascade,
  foreign key("category_id") references "categories"("id") on delete set null,
  foreign key("color_id") references "colors"("id") on delete set null,
  foreign key("style_id") references "styles"("id") on delete set null
);
CREATE INDEX "products_supplier_clean_model_index" on "products"(
  "supplier_clean_model"
);
CREATE UNIQUE INDEX "products_supplier_id_clean_model_unique" on "products"(
  "supplier_id",
  "clean_model"
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
  "store_id" integer,
  "quantity_in_transfer" integer not null default '0',
  "quantity_in_transit" integer not null default '0',
  "quantity_customer_in_transfer" integer not null default '0',
  "quantity_customer_in_transit" integer not null default '0',
  foreign key("product_id") references "products"("id") on delete cascade,
  foreign key("store_id") references "stores"("id") on delete restrict
);
CREATE UNIQUE INDEX "inventory_stocks_product_id_store_id_unique" on "inventory_stocks"(
  "product_id",
  "store_id"
);
CREATE INDEX "inventory_stocks_store_id_index" on "inventory_stocks"(
  "store_id"
);
CREATE TABLE "supplier_order_lines"(
  "id" integer primary key autoincrement not null,
  "supplier_order_id" integer not null,
  "product_id" integer,
  "description" varchar,
  "quantity" integer not null,
  "unit_cost" integer not null,
  "quantity_received" integer not null default '0',
  "created_at" datetime,
  "updated_at" datetime,
  "status" varchar not null default 'active',
  "cancellation_reason" varchar,
  "cancellation_requested_at" datetime,
  "cancelled_at" datetime,
  "substituted_from_line_id" integer,
  "part_id" integer,
  "quantity_short" integer not null default '0',
  "is_overship" tinyint(1) not null default '0',
  foreign key("supplier_order_id") references "supplier_orders"("id") on delete cascade,
  foreign key("product_id") references "products"("id") on delete set null,
  foreign key("part_id") references "parts"("id") on delete restrict,
  foreign key("substituted_from_line_id") references "supplier_order_lines"("id") on delete set null
);
CREATE TABLE "reception_lines"(
  "id" integer primary key autoincrement not null,
  "reception_id" integer not null,
  "supplier_order_line_id" integer not null,
  "product_id" integer,
  "quantity" integer not null,
  "unit_cost" integer not null,
  "created_at" datetime,
  "updated_at" datetime,
  "quantity_reversed" integer not null default '0',
  "reversed_at" datetime,
  "reversed_by" integer,
  "reversal_reason" varchar,
  "quantity_damaged" integer not null default '0',
  "quantity_short" integer not null default '0',
  "quantity_expected" integer not null default '0',
  "checked_at" datetime,
  foreign key("reception_id") references "receptions"("id") on delete cascade,
  foreign key("supplier_order_line_id") references "supplier_order_lines"("id") on delete restrict,
  foreign key("product_id") references "products"("id") on delete set null,
  foreign key("reversed_by") references "users"("id") on delete set null
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
  "created_at" datetime not null default CURRENT_TIMESTAMP,
  "value" integer,
  "store_id" integer,
  foreign key("product_id") references "products"("id") on delete restrict,
  foreign key("user_id") references "users"("id") on delete set null,
  foreign key("store_id") references "stores"("id") on delete restrict
);
CREATE INDEX "inventory_movements_product_id_created_at_index" on "inventory_movements"(
  "product_id",
  "created_at"
);
CREATE INDEX "inventory_movements_reference_type_reference_id_index" on "inventory_movements"(
  "reference_type",
  "reference_id"
);
CREATE TABLE "inventory_units"(
  "id" integer primary key autoincrement not null,
  "product_id" integer not null,
  "cost" integer not null,
  "inserted_at" date not null,
  "delivered_at" date,
  "created_at" datetime,
  "updated_at" datetime,
  "reception_line_id" integer,
  "exit_movement_id" integer,
  "store_id" integer,
  foreign key("product_id") references "products"("id") on delete cascade,
  foreign key("reception_line_id") references "reception_lines"("id") on delete set null,
  foreign key("exit_movement_id") references "inventory_movements"("id") on delete set null,
  foreign key("store_id") references "stores"("id") on delete restrict
);
CREATE INDEX "inventory_units_exit_movement_id_index" on "inventory_units"(
  "exit_movement_id"
);
CREATE INDEX "inventory_units_product_id_inserted_at_index" on "inventory_units"(
  "product_id",
  "inserted_at"
);
CREATE INDEX "inventory_units_store_id_index" on "inventory_units"("store_id");
CREATE TABLE "supplier_invoice_lines"(
  "id" integer primary key autoincrement not null,
  "supplier_invoice_id" integer not null,
  "reception_line_id" integer,
  "quantity" integer not null,
  "unit_cost" integer not null,
  "created_at" datetime,
  "updated_at" datetime,
  "supplier_order_line_id" integer,
  "quantity_short" integer not null default '0',
  foreign key("supplier_invoice_id") references "supplier_invoices"("id") on delete cascade,
  foreign key("reception_line_id") references "reception_lines"("id") on delete restrict,
  foreign key("supplier_order_line_id") references "supplier_order_lines"("id") on delete restrict
);
CREATE TABLE "price_list_items"(
  "id" integer primary key autoincrement not null,
  "price_list_list_id" integer not null,
  "product_id" integer,
  "model" varchar not null,
  "clean_model" varchar not null,
  "cost" integer,
  "imap" integer,
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
CREATE TABLE "part_product"(
  "part_id" integer not null,
  "product_id" integer not null,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("part_id") references "parts"("id") on delete cascade,
  foreign key("product_id") references "products"("id") on delete cascade,
  primary key("part_id", "product_id")
);
CREATE TABLE "label_requests"(
  "id" integer primary key autoincrement not null,
  "product_id" integer not null,
  "quantity" integer not null,
  "reason" varchar not null,
  "requested_by" integer,
  "printed_at" datetime,
  "printed_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("product_id") references "products"("id") on delete cascade,
  foreign key("requested_by") references "users"("id") on delete set null,
  foreign key("printed_by") references "users"("id") on delete set null
);
CREATE INDEX "label_requests_printed_at_index" on "label_requests"(
  "printed_at"
);
CREATE TABLE "notifications"(
  "id" varchar not null,
  "type" varchar not null,
  "notifiable_type" varchar not null,
  "notifiable_id" integer not null,
  "data" text not null,
  "read_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  primary key("id")
);
CREATE INDEX "notifications_notifiable_type_notifiable_id_index" on "notifications"(
  "notifiable_type",
  "notifiable_id"
);
CREATE TABLE "delivery_trucks"(
  "id" integer primary key autoincrement not null,
  "store_id" integer not null,
  "name" varchar not null,
  "capacity_per_period" integer not null,
  "is_active" tinyint(1) not null default '1',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("store_id") references "stores"("id") on delete cascade
);
CREATE UNIQUE INDEX "delivery_trucks_store_id_name_unique" on "delivery_trucks"(
  "store_id",
  "name"
);
CREATE TABLE "customer_order_deliveries"(
  "id" integer primary key autoincrement not null,
  "customer_order_id" integer not null,
  "store_id" integer,
  "rescheduled_from_id" integer,
  "method" varchar not null,
  "status" varchar not null,
  "scheduled_date" date,
  "period" varchar,
  "address_civic" varchar not null,
  "address_apartment" varchar,
  "address_street" varchar not null,
  "address_city" varchar not null,
  "address_province" varchar not null,
  "address_country" varchar not null,
  "address_postal_code" varchar not null,
  "address_verified_by" integer,
  "address_verified_at" datetime not null,
  "pay_on_delivery" tinyint(1) not null default '0',
  "pay_on_delivery_authorized_by" integer,
  "notes" text,
  "requested_by" integer,
  "cancelled_at" datetime,
  "cancelled_by" integer,
  "cancellation_reason" varchar,
  "created_at" datetime,
  "updated_at" datetime,
  "ready_at" datetime,
  "route_position" integer,
  "departed_at" datetime,
  "departed_by" integer,
  "delivered_at" datetime,
  "delivered_by" integer,
  "signature_path" varchar,
  "signed_by_name" varchar,
  "signed_at" datetime,
  "undelivered_reason" varchar,
  "undelivered_at" datetime,
  "shipping_supplier_id" integer,
  "shipping_cost" integer,
  "tracking_number" varchar,
  "shipped_at" datetime,
  "shipped_by" integer,
  "supplier_invoice_id" integer,
  foreign key("customer_order_id") references "customer_orders"("id") on delete cascade,
  foreign key("store_id") references "stores"("id") on delete set null,
  foreign key("address_verified_by") references "users"("id") on delete set null,
  foreign key("pay_on_delivery_authorized_by") references "users"("id") on delete set null,
  foreign key("requested_by") references "users"("id") on delete set null,
  foreign key("cancelled_by") references "users"("id") on delete set null,
  foreign key("departed_by") references "users"("id") on delete set null,
  foreign key("delivered_by") references "users"("id") on delete set null,
  foreign key("shipping_supplier_id") references "suppliers"("id") on delete set null,
  foreign key("shipped_by") references "users"("id") on delete set null,
  foreign key("supplier_invoice_id") references "supplier_invoices"("id") on delete restrict,
  foreign key("rescheduled_from_id") references "customer_order_deliveries"("id") on delete set null
);
CREATE INDEX "customer_order_deliveries_store_id_scheduled_date_period_index" on "customer_order_deliveries"(
  "store_id",
  "scheduled_date",
  "period"
);
CREATE TABLE "ledger_accounts"(
  "id" integer primary key autoincrement not null,
  "code" varchar not null,
  "name" varchar not null,
  "type" varchar not null,
  "system_key" varchar,
  "created_at" datetime,
  "updated_at" datetime
);
CREATE UNIQUE INDEX "ledger_accounts_code_unique" on "ledger_accounts"("code");
CREATE UNIQUE INDEX "ledger_accounts_system_key_unique" on "ledger_accounts"(
  "system_key"
);
CREATE TABLE "ledger_entries"(
  "id" integer primary key autoincrement not null,
  "posted_on" date not null,
  "description" varchar not null,
  "reference_type" varchar,
  "reference_id" integer,
  "user_id" integer,
  "created_at" datetime not null default CURRENT_TIMESTAMP,
  "store_id" integer,
  foreign key("user_id") references "users"("id") on delete set null,
  foreign key("store_id") references "stores"("id") on delete restrict
);
CREATE INDEX "ledger_entries_posted_on_index" on "ledger_entries"("posted_on");
CREATE INDEX "ledger_entries_reference_type_reference_id_index" on "ledger_entries"(
  "reference_type",
  "reference_id"
);
CREATE TABLE "ledger_entry_lines"(
  "id" integer primary key autoincrement not null,
  "ledger_entry_id" integer not null,
  "ledger_account_id" integer not null,
  "debit" integer not null default '0',
  "credit" integer not null default '0',
  foreign key("ledger_entry_id") references "ledger_entries"("id") on delete cascade,
  foreign key("ledger_account_id") references "ledger_accounts"("id") on delete restrict
);
CREATE TABLE "inventory_adjustments"(
  "id" integer primary key autoincrement not null,
  "product_id" integer not null,
  "reason" varchar not null,
  "status" varchar not null,
  "is_entry" tinyint(1) not null,
  "quantity" integer not null,
  "note" varchar not null,
  "user_id" integer,
  "created_at" datetime not null default CURRENT_TIMESTAMP,
  "store_id" integer,
  foreign key("product_id") references "products"("id") on delete restrict,
  foreign key("user_id") references "users"("id") on delete set null,
  foreign key("store_id") references "stores"("id") on delete restrict
);
CREATE INDEX "inventory_adjustments_product_id_created_at_index" on "inventory_adjustments"(
  "product_id",
  "created_at"
);
CREATE INDEX "inventory_adjustments_store_id_index" on "inventory_adjustments"(
  "store_id"
);
CREATE TABLE "inventory_counts"(
  "id" integer primary key autoincrement not null,
  "counted_on" date not null,
  "status" varchar not null default 'in_progress',
  "stock_frozen_at" datetime,
  "started_by" integer,
  "finalized_at" datetime,
  "finalized_by" integer,
  "abandoned_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  "store_id" integer,
  foreign key("started_by") references "users"("id") on delete set null,
  foreign key("finalized_by") references "users"("id") on delete set null,
  foreign key("store_id") references "stores"("id") on delete restrict
);
CREATE INDEX "inventory_counts_status_index" on "inventory_counts"("status");
CREATE INDEX "inventory_counts_store_id_index" on "inventory_counts"(
  "store_id"
);
CREATE TABLE "inventory_count_lines"(
  "id" integer primary key autoincrement not null,
  "inventory_count_id" integer not null,
  "product_id" integer not null,
  "status" varchar not null,
  "counted_quantity" integer,
  "is_kept" tinyint(1) not null default '0',
  "expected_quantity" integer,
  "note" varchar,
  "counted_by" integer,
  "counted_at" datetime,
  "inventory_adjustment_id" integer,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("inventory_count_id") references "inventory_counts"("id") on delete cascade,
  foreign key("product_id") references "products"("id") on delete restrict,
  foreign key("counted_by") references "users"("id") on delete set null,
  foreign key("inventory_adjustment_id") references "inventory_adjustments"("id") on delete set null
);
CREATE UNIQUE INDEX "inventory_count_lines_inventory_count_id_product_id_status_unique" on "inventory_count_lines"(
  "inventory_count_id",
  "product_id",
  "status"
);
CREATE TABLE "inventory_zones"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "sort_order" integer not null default '0',
  "is_active" tinyint(1) not null default '1',
  "created_at" datetime,
  "updated_at" datetime,
  "store_id" integer,
  foreign key("store_id") references "stores"("id") on delete restrict
);
CREATE UNIQUE INDEX "inventory_zones_store_id_name_unique" on "inventory_zones"(
  "store_id",
  "name"
);
CREATE TABLE "inventory_count_sheets"(
  "id" integer primary key autoincrement not null,
  "inventory_count_id" integer not null,
  "status" varchar not null,
  "inventory_zone_id" integer,
  "pass" varchar not null,
  "assigned_to" integer,
  "state" varchar not null default 'todo',
  "started_at" datetime,
  "finished_at" datetime,
  "reconciled_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("inventory_count_id") references "inventory_counts"("id") on delete cascade,
  foreign key("inventory_zone_id") references "inventory_zones"("id") on delete restrict,
  foreign key("assigned_to") references "users"("id") on delete set null
);
CREATE INDEX "inventory_count_sheets_assigned_to_index" on "inventory_count_sheets"(
  "assigned_to"
);
CREATE INDEX "inventory_count_sheets_inventory_count_id_status_inventory_zone_id_index" on "inventory_count_sheets"(
  "inventory_count_id",
  "status",
  "inventory_zone_id"
);
CREATE TABLE "inventory_count_sheet_lines"(
  "id" integer primary key autoincrement not null,
  "inventory_count_sheet_id" integer not null,
  "product_id" integer not null,
  "quantity" integer not null default '0',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("inventory_count_sheet_id") references "inventory_count_sheets"("id") on delete cascade,
  foreign key("product_id") references "products"("id") on delete restrict
);
CREATE UNIQUE INDEX "inventory_count_sheet_lines_inventory_count_sheet_id_product_id_unique" on "inventory_count_sheet_lines"(
  "inventory_count_sheet_id",
  "product_id"
);
CREATE TABLE "inventory_count_scans"(
  "id" integer primary key autoincrement not null,
  "inventory_count_sheet_id" integer not null,
  "code" varchar,
  "product_id" integer,
  "quantity" integer not null,
  "user_id" integer,
  "undone_at" datetime,
  "created_at" datetime not null default CURRENT_TIMESTAMP,
  foreign key("inventory_count_sheet_id") references "inventory_count_sheets"("id") on delete cascade,
  foreign key("product_id") references "products"("id") on delete set null,
  foreign key("user_id") references "users"("id") on delete set null
);
CREATE TABLE "inventory_count_results"(
  "id" integer primary key autoincrement not null,
  "inventory_count_id" integer not null,
  "status" varchar not null,
  "inventory_zone_id" integer,
  "product_id" integer not null,
  "quantity" integer not null,
  "source" varchar not null,
  "reconciled_by" integer,
  "created_at" datetime not null default CURRENT_TIMESTAMP,
  foreign key("inventory_count_id") references "inventory_counts"("id") on delete cascade,
  foreign key("inventory_zone_id") references "inventory_zones"("id") on delete restrict,
  foreign key("product_id") references "products"("id") on delete restrict,
  foreign key("reconciled_by") references "users"("id") on delete set null
);
CREATE INDEX "inventory_count_results_inventory_count_id_status_inventory_zone_id_index" on "inventory_count_results"(
  "inventory_count_id",
  "status",
  "inventory_zone_id"
);
CREATE TABLE "supplier_invoice_taxes"(
  "id" integer primary key autoincrement not null,
  "supplier_invoice_id" integer not null,
  "tax_name" varchar not null,
  "amount" integer not null,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("supplier_invoice_id") references "supplier_invoices"("id") on delete cascade
);
CREATE UNIQUE INDEX "supplier_invoice_taxes_supplier_invoice_id_tax_name_unique" on "supplier_invoice_taxes"(
  "supplier_invoice_id",
  "tax_name"
);
CREATE TABLE "inventory_locations"(
  "id" integer primary key autoincrement not null,
  "inventory_zone_id" integer not null,
  "code" varchar not null,
  "description" varchar,
  "is_active" tinyint(1) not null default '1',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("inventory_zone_id") references "inventory_zones"("id") on delete restrict
);
CREATE TABLE "customer_order_lines"(
  "id" integer primary key autoincrement not null,
  "customer_order_id" integer not null,
  "product_id" integer,
  "quantity" integer not null,
  "unit_price" integer not null,
  "note" text,
  "status" varchar not null,
  "delivered_at" datetime,
  "returned_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  "quantity_reserved" integer not null default '0',
  "quantity_on_order" integer not null default '0',
  "supplier_order_line_id" integer,
  "customer_order_pickup_id" integer,
  "cancellation_fee" integer,
  "service_id" integer,
  "supplier_id" integer,
  "description" varchar,
  "is_taxable" tinyint(1) not null default '1',
  "is_custom" tinyint(1) not null default '0',
  "unit_cost" integer,
  "quote_number" varchar,
  "part_id" integer,
  "is_no_charge" tinyint(1) not null default '0',
  "inventory_location_id" integer,
  foreign key("customer_order_id") references "customer_orders"("id") on delete cascade,
  foreign key("product_id") references "products"("id") on delete restrict,
  foreign key("supplier_order_line_id") references "supplier_order_lines"("id") on delete set null,
  foreign key("customer_order_pickup_id") references "customer_order_pickups"("id") on delete set null,
  foreign key("service_id") references "services"("id") on delete restrict,
  foreign key("supplier_id") references "suppliers"("id") on delete restrict,
  foreign key("part_id") references "parts"("id") on delete restrict,
  foreign key("inventory_location_id") references "inventory_locations"("id") on delete set null
);
CREATE INDEX "customer_order_lines_status_index" on "customer_order_lines"(
  "status"
);
CREATE TABLE "defective_products"(
  "id" integer primary key autoincrement not null,
  "product_id" integer not null,
  "customer_order_id" integer,
  "customer_order_line_id" integer,
  "quantity" integer not null,
  "resolution" varchar not null,
  "status" varchar not null default 'open',
  "reason" text,
  "replacement_part" varchar,
  "supplier_order_line_id" integer,
  "photo_path" varchar,
  "created_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  "reception_line_id" integer,
  foreign key("product_id") references "products"("id") on delete restrict,
  foreign key("customer_order_id") references "customer_orders"("id") on delete set null,
  foreign key("customer_order_line_id") references "customer_order_lines"("id") on delete set null,
  foreign key("supplier_order_line_id") references "supplier_order_lines"("id") on delete set null,
  foreign key("created_by") references "users"("id") on delete set null,
  foreign key("reception_line_id") references "reception_lines"("id") on delete set null
);
CREATE INDEX "defective_products_status_resolution_index" on "defective_products"(
  "status",
  "resolution"
);
CREATE TABLE "customer_order_delivery_lines"(
  "id" integer primary key autoincrement not null,
  "customer_order_delivery_id" integer not null,
  "customer_order_line_id" integer not null,
  "quantity" integer not null,
  "created_at" datetime,
  "updated_at" datetime,
  "loaded_from_status" varchar,
  "accepted_quantity" integer,
  "refusal_reason" varchar,
  foreign key("customer_order_line_id") references "customer_order_lines"("id") on delete cascade,
  foreign key("customer_order_delivery_id") references "customer_order_deliveries"("id") on delete cascade
);
CREATE UNIQUE INDEX "delivery_line_unique" on "customer_order_delivery_lines"(
  "customer_order_delivery_id",
  "customer_order_line_id"
);
CREATE TABLE "product_store_locations"(
  "id" integer primary key autoincrement not null,
  "product_id" integer not null,
  "store_id" integer not null,
  "inventory_location_id" integer not null,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("product_id") references "products"("id") on delete cascade,
  foreign key("store_id") references "stores"("id") on delete cascade,
  foreign key("inventory_location_id") references "inventory_locations"("id") on delete cascade
);
CREATE UNIQUE INDEX "product_store_locations_product_id_store_id_unique" on "product_store_locations"(
  "product_id",
  "store_id"
);
CREATE TABLE "store_transfers"(
  "id" integer primary key autoincrement not null,
  "from_store_id" integer not null,
  "to_store_id" integer not null,
  "product_id" integer not null,
  "customer_order_line_id" integer,
  "quantity" integer not null,
  "quantity_received" integer,
  "source_status" varchar not null,
  "status" varchar not null,
  "refusal_reason" varchar,
  "requested_by" integer,
  "requested_at" datetime not null,
  "accepted_by" integer,
  "accepted_at" datetime,
  "received_by" integer,
  "received_at" datetime,
  "closed_by" integer,
  "closed_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("from_store_id") references "stores"("id") on delete restrict,
  foreign key("to_store_id") references "stores"("id") on delete restrict,
  foreign key("product_id") references "products"("id") on delete restrict,
  foreign key("customer_order_line_id") references "customer_order_lines"("id") on delete set null,
  foreign key("requested_by") references "users"("id") on delete set null,
  foreign key("accepted_by") references "users"("id") on delete set null,
  foreign key("received_by") references "users"("id") on delete set null,
  foreign key("closed_by") references "users"("id") on delete set null
);
CREATE INDEX "store_transfers_from_store_id_status_index" on "store_transfers"(
  "from_store_id",
  "status"
);
CREATE INDEX "store_transfers_to_store_id_status_index" on "store_transfers"(
  "to_store_id",
  "status"
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
CREATE TABLE "permissions"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "guard_name" varchar not null,
  "created_at" datetime,
  "updated_at" datetime,
  "label" varchar,
  "description" text
);
CREATE UNIQUE INDEX "permissions_name_guard_name_unique" on "permissions"(
  "name",
  "guard_name"
);
CREATE TABLE "user_denied_permissions"(
  "user_id" integer not null,
  "permission_id" integer not null,
  foreign key("user_id") references "users"("id") on delete cascade,
  foreign key("permission_id") references "permissions"("id") on delete cascade,
  primary key("user_id", "permission_id")
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
CREATE TABLE "supplier_invoices"(
  "id" integer primary key autoincrement not null,
  "supplier_id" integer not null,
  "reception_id" integer,
  "invoice_number" varchar not null,
  "invoice_date" date not null,
  "merchandise_total" integer not null,
  "freight_fee" integer not null default('0'),
  "customs_fee" integer not null default('0'),
  "taxes" integer not null default('0'),
  "computed_total" integer not null,
  "invoice_total" integer not null,
  "variance" integer not null default('0'),
  "discount_percent" numeric not null default('0'),
  "discount_days" integer,
  "discount_due_date" date,
  "discount_amount" integer not null default('0'),
  "created_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  "discount_next_month" tinyint(1) not null default('0'),
  "supplier_order_id" integer,
  "description" varchar,
  "credit_requested" integer not null default('0'),
  "credit_note_number" varchar,
  "credit_received_at" date,
  "exchange_rate" numeric not null default '1',
  "ledger_account_id" integer,
  foreign key("supplier_order_id") references supplier_orders("id") on delete restrict on update no action,
  foreign key("created_by") references users("id") on delete set null on update no action,
  foreign key("reception_id") references receptions("id") on delete restrict on update no action,
  foreign key("supplier_id") references suppliers("id") on delete restrict on update no action,
  foreign key("ledger_account_id") references "ledger_accounts"("id") on delete restrict
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

INSERT INTO migrations VALUES(1,'2026_10_25_000001_create_password_reset_tokens_table',1);
INSERT INTO migrations VALUES(2,'2026_10_25_000002_create_sessions_table',1);
INSERT INTO migrations VALUES(3,'2026_10_25_000003_create_cache_table',1);
INSERT INTO migrations VALUES(4,'2026_10_25_000004_create_cache_locks_table',1);
INSERT INTO migrations VALUES(5,'2026_10_25_000005_create_jobs_table',1);
INSERT INTO migrations VALUES(6,'2026_10_25_000006_create_job_batches_table',1);
INSERT INTO migrations VALUES(7,'2026_10_25_000007_create_failed_jobs_table',1);
INSERT INTO migrations VALUES(8,'2026_10_25_000008_create_customers_table',1);
INSERT INTO migrations VALUES(9,'2026_10_25_000009_create_customer_payment_methods_table',1);
INSERT INTO migrations VALUES(10,'2026_10_25_000010_create_merchant_payment_methods_table',1);
INSERT INTO migrations VALUES(11,'2026_10_25_000011_create_departments_table',1);
INSERT INTO migrations VALUES(12,'2026_10_25_000012_create_categories_table',1);
INSERT INTO migrations VALUES(13,'2026_10_25_000013_create_colors_table',1);
INSERT INTO migrations VALUES(14,'2026_10_25_000014_create_shift_templates_table',1);
INSERT INTO migrations VALUES(15,'2026_10_25_000015_create_week_templates_table',1);
INSERT INTO migrations VALUES(16,'2026_10_25_000016_create_week_template_entries_table',1);
INSERT INTO migrations VALUES(17,'2026_10_25_000017_create_holidays_table',1);
INSERT INTO migrations VALUES(18,'2026_10_25_000018_create_positions_table',1);
INSERT INTO migrations VALUES(19,'2026_10_25_000019_create_currencies_table',1);
INSERT INTO migrations VALUES(20,'2026_10_25_000020_create_suppliers_table',1);
INSERT INTO migrations VALUES(21,'2026_10_25_000021_create_stores_table',1);
INSERT INTO migrations VALUES(22,'2026_10_25_000022_create_users_table',1);
INSERT INTO migrations VALUES(23,'2026_10_25_000023_create_schedules_table',1);
INSERT INTO migrations VALUES(24,'2026_10_25_000024_create_appointments_table',1);
INSERT INTO migrations VALUES(25,'2026_10_25_000025_create_supplier_orders_table',1);
INSERT INTO migrations VALUES(26,'2026_10_25_000026_create_receptions_table',1);
INSERT INTO migrations VALUES(27,'2026_10_25_000027_create_supplier_invoices_table',1);
INSERT INTO migrations VALUES(28,'2026_10_25_000028_create_payroll_periods_table',1);
INSERT INTO migrations VALUES(29,'2026_10_25_000029_create_price_lists_table',1);
INSERT INTO migrations VALUES(30,'2026_10_25_000030_create_price_list_lists_table',1);
INSERT INTO migrations VALUES(31,'2026_10_25_000031_create_customer_orders_table',1);
INSERT INTO migrations VALUES(32,'2026_10_25_000032_create_customer_order_salesperson_table',1);
INSERT INTO migrations VALUES(33,'2026_10_25_000033_create_customer_order_pickups_table',1);
INSERT INTO migrations VALUES(34,'2026_10_25_000034_create_customer_order_payments_table',1);
INSERT INTO migrations VALUES(35,'2026_10_25_000035_create_taxes_table',1);
INSERT INTO migrations VALUES(36,'2026_10_25_000036_create_store_tax_registrations_table',1);
INSERT INTO migrations VALUES(37,'2026_10_25_000037_create_customer_order_taxes_table',1);
INSERT INTO migrations VALUES(38,'2026_10_25_000038_create_services_table',1);
INSERT INTO migrations VALUES(39,'2026_10_25_000039_create_service_supplier_table',1);
INSERT INTO migrations VALUES(40,'2026_10_25_000040_create_parts_table',1);
INSERT INTO migrations VALUES(41,'2026_10_25_000041_create_styles_table',1);
INSERT INTO migrations VALUES(42,'2026_10_25_000042_create_products_table',1);
INSERT INTO migrations VALUES(43,'2026_10_25_000043_create_inventory_stocks_table',1);
INSERT INTO migrations VALUES(44,'2026_10_25_000044_create_supplier_order_lines_table',1);
INSERT INTO migrations VALUES(45,'2026_10_25_000045_create_reception_lines_table',1);
INSERT INTO migrations VALUES(46,'2026_10_25_000046_create_inventory_movements_table',1);
INSERT INTO migrations VALUES(47,'2026_10_25_000047_create_inventory_units_table',1);
INSERT INTO migrations VALUES(48,'2026_10_25_000048_create_supplier_invoice_lines_table',1);
INSERT INTO migrations VALUES(49,'2026_10_25_000049_create_price_list_items_table',1);
INSERT INTO migrations VALUES(50,'2026_10_25_000050_create_product_upcs_table',1);
INSERT INTO migrations VALUES(51,'2026_10_25_000051_create_part_product_table',1);
INSERT INTO migrations VALUES(52,'2026_10_25_000052_create_label_requests_table',1);
INSERT INTO migrations VALUES(53,'2026_10_25_000053_create_notifications_table',1);
INSERT INTO migrations VALUES(54,'2026_10_25_000054_create_delivery_trucks_table',1);
INSERT INTO migrations VALUES(55,'2026_10_25_000055_create_customer_order_deliveries_table',1);
INSERT INTO migrations VALUES(56,'2026_10_25_000056_create_ledger_accounts_table',1);
INSERT INTO migrations VALUES(57,'2026_10_25_000057_create_ledger_entries_table',1);
INSERT INTO migrations VALUES(58,'2026_10_25_000058_create_ledger_entry_lines_table',1);
INSERT INTO migrations VALUES(59,'2026_10_25_000059_create_inventory_adjustments_table',1);
INSERT INTO migrations VALUES(60,'2026_10_25_000060_create_inventory_counts_table',1);
INSERT INTO migrations VALUES(61,'2026_10_25_000061_create_inventory_count_lines_table',1);
INSERT INTO migrations VALUES(62,'2026_10_25_000062_create_inventory_zones_table',1);
INSERT INTO migrations VALUES(63,'2026_10_25_000063_create_inventory_count_sheets_table',1);
INSERT INTO migrations VALUES(64,'2026_10_25_000064_create_inventory_count_sheet_lines_table',1);
INSERT INTO migrations VALUES(65,'2026_10_25_000065_create_inventory_count_scans_table',1);
INSERT INTO migrations VALUES(66,'2026_10_25_000066_create_inventory_count_results_table',1);
INSERT INTO migrations VALUES(67,'2026_10_25_000067_create_supplier_invoice_taxes_table',1);
INSERT INTO migrations VALUES(68,'2026_10_25_000068_create_inventory_locations_table',1);
INSERT INTO migrations VALUES(69,'2026_10_25_000069_create_customer_order_lines_table',1);
INSERT INTO migrations VALUES(70,'2026_10_25_000070_create_defective_products_table',1);
INSERT INTO migrations VALUES(71,'2026_10_25_000071_create_customer_order_delivery_lines_table',1);
INSERT INTO migrations VALUES(72,'2026_10_25_000072_create_product_store_locations_table',1);
INSERT INTO migrations VALUES(73,'2026_10_25_000073_create_store_transfers_table',1);
INSERT INTO migrations VALUES(74,'2026_10_25_000074_create_roles_table',1);
INSERT INTO migrations VALUES(75,'2026_10_25_000075_create_permissions_table',1);
INSERT INTO migrations VALUES(76,'2026_10_25_000076_create_user_denied_permissions_table',1);
INSERT INTO migrations VALUES(77,'2026_10_25_000077_create_model_has_permissions_table',1);
INSERT INTO migrations VALUES(78,'2026_10_25_000078_create_model_has_roles_table',1);
INSERT INTO migrations VALUES(79,'2026_10_25_000079_create_role_has_permissions_table',1);
INSERT INTO migrations VALUES(80,'2026_10_26_201403_add_pin_to_users_table',2);
INSERT INTO migrations VALUES(81,'2026_10_31_152256_add_expected_quantity_to_reception_lines_table',2);
INSERT INTO migrations VALUES(82,'2026_11_01_111652_add_exchange_rate_and_ledger_account_to_supplier_invoices_table',3);
INSERT INTO migrations VALUES(83,'2026_11_01_111653_add_exchange_rate_to_receptions_table',3);
