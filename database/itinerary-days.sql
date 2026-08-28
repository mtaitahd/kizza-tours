-- Itinerary Days table for structured, per-day tour itineraries.
-- Replaces the single free-text tour_packages.itinerary TEXT column with a
-- proper structured model: each row = one itinerary day for one tour.
--
-- The existing tour_packages.itinerary column is intentionally left untouched
-- for backward compatibility. Tours without structured days will fall back to
-- rendering that legacy newline-separated text via tour-details.php.

CREATE TABLE IF NOT EXISTS itinerary_days (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tour_id INT NOT NULL,
    day_number INT NOT NULL DEFAULT 1,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    drive_time VARCHAR(255) DEFAULT NULL,
    meals VARCHAR(255) DEFAULT NULL,
    accommodation VARCHAR(255) DEFAULT NULL,
    image_path VARCHAR(255) DEFAULT NULL,
    image_alt VARCHAR(255) DEFAULT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_itinerary_days_tour (tour_id, sort_order),
    CONSTRAINT fk_itinerary_days_tour FOREIGN KEY (tour_id) REFERENCES tour_packages(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
