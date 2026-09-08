-- Incremental migration: add optional per-day map fields.
-- Run this on an EXISTING live database to power the OpenStreetMap route map
-- on the day-by-day itinerary section. It is safe to run multiple times
-- (it will fail harmlessly if a column already exists; see note below).
--
-- These new columns are OPTIONAL. The frontend map only renders for a tour
-- when at least one of its itinerary days has both lat and lng filled in
-- (set in Admin > Tours > that tour's itinerary day(s) > Map Location /
-- Latitude / Longitude). Existing rows are preserved untouched.
--
-- NOTE: database/itinerary-days.sql and database/schema.sql already include
-- these columns for fresh installs, and admin/tours.php auto-adds them via
-- ensureItineraryDayColumns() on page load. Running this file manually on the
-- live server is the explicit, version-controlled equivalent.

ALTER TABLE itinerary_days
    ADD COLUMN location_name VARCHAR(255) DEFAULT NULL AFTER accommodation,
    ADD COLUMN lat DECIMAL(10,7) DEFAULT NULL AFTER location_name,
    ADD COLUMN lng DECIMAL(10,7) DEFAULT NULL AFTER lat;
