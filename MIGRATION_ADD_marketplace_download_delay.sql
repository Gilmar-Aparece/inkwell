-- Adds a per-listing "cooling off" period: the seller can require buyers to
-- wait N days after redeeming their unlock code before the ZIP download
-- actually opens. 0 = no delay (download unlocks immediately, same as
-- before this feature existed). Also self-heals automatically the first
-- time includes/marketplace.php runs (inkwell_ensure_marketplace_tables()),
-- so running this manually is optional.

ALTER TABLE marketplace_listings
  ADD COLUMN IF NOT EXISTS download_delay_days INT NOT NULL DEFAULT 0;
