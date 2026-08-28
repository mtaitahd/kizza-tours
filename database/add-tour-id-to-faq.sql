-- Add tour_id column to faq table for per-tour FAQ assignment
-- Existing records will have tour_id = NULL (global/shared FAQs)

ALTER TABLE faq ADD COLUMN tour_id INT DEFAULT NULL AFTER id;
ALTER TABLE faq ADD CONSTRAINT fk_faq_tour FOREIGN KEY (tour_id) REFERENCES tour_packages(id) ON DELETE SET NULL;
ALTER TABLE faq ADD INDEX idx_faq_tour_id (tour_id);
