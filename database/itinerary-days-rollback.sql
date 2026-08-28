-- ROLLBACK for itinerary-days.sql
-- Drops the itinerary_days table. The original tour_packages.itinerary column
-- and all tour content are preserved (they were never touched by the migration).

DROP TABLE IF EXISTS itinerary_days;
