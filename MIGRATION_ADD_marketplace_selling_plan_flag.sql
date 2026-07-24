-- Adds the "does this plan unlock marketplace selling" toggle to `plans`.
-- Not required to run manually — includes/billing.php's
-- inkwell_ensure_billing_columns() adds this column automatically the
-- first time any page loads after deploying. This file is here only for
-- parity with the repo's other MIGRATION_*.sql files, e.g. if you prefer
-- to run migrations by hand / want it in version control explicitly.

ALTER TABLE plans
  ADD COLUMN unlocks_marketplace_selling TINYINT(1) NOT NULL DEFAULT 0;
