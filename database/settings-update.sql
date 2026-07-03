-- KIZZA TOURS & SAFARIS - Additional Settings for Dynamic Media
-- Run this to add media settings to existing database

INSERT INTO settings (setting_key, setting_value, setting_group) VALUES
('hero_video', '', 'media'),
('hero_poster', '', 'media'),
('about_image', '', 'media'),
('cta_background', '', 'media'),
('og_image', '', 'media'),
('site_favicon', '', 'media'),
('about_content_1', 'Kizza Tours & Safaris is dedicated to providing unforgettable travel experiences across East Africa while delivering exceptional service from the moment guests inquire until they return home with unforgettable memories.', 'content'),
('about_content_2', 'Kizza Tours & Safaris specializes in safaris, gorilla trekking, Kilimanjaro expeditions, cultural journeys, luxury escapes, and tailor-made adventures that showcase the very best of East Africa.', 'content'),
('about_content_3', 'Kizza Tours & Safaris creates meaningful adventures, lifelong memories, and authentic connections with Africa. Every journey is crafted with passion, expertise, and an unwavering commitment to excellence.', 'content'),
('vision_text', 'Kizza Tours & Safaris aspires to become the leading and most trusted tour operator in East Africa, recognized globally for delivering exceptional travel experiences, promoting sustainable tourism, and creating meaningful connections between travelers, wildlife, nature, and local communities.', 'content'),
('about_stat_years', '10+', 'content'),
('about_stat_years_label', 'Years Experience', 'content'),
('about_stat_travelers', '5000+', 'content'),
('about_stat_travelers_label', 'Happy Travelers', 'content'),
('about_stat_images', '', 'media')
ON DUPLICATE KEY UPDATE setting_value = setting_value;

-- Add image field to tour_packages if not exists
ALTER TABLE tour_packages ADD COLUMN IF NOT EXISTS image VARCHAR(255) AFTER highlights;

-- Add photo field to testimonials
ALTER TABLE testimonials ADD COLUMN IF NOT EXISTS customer_photo VARCHAR(255) AFTER customer_title;
