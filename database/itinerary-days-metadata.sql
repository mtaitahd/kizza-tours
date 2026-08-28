-- Incremental migration: add optional per-day metadata fields.
-- Run this AFTER database/itinerary-days.sql on an existing database,
-- or apply both on a fresh install (itinerary-days.sql now includes these).
--
-- These fields power the compact "Drive | Meals | Accommodation" metadata row
-- shown beneath every itinerary day. They are optional: an empty value simply
-- hides that item on the frontend. Existing rows are preserved untouched.

ALTER TABLE itinerary_days
    ADD COLUMN drive_time VARCHAR(255) DEFAULT NULL AFTER description,
    ADD COLUMN meals VARCHAR(255) DEFAULT NULL AFTER drive_time,
    ADD COLUMN accommodation VARCHAR(255) DEFAULT NULL AFTER meals;
