-- Remove old individual stat image keys (replaced by about_stat_images)
DELETE FROM settings WHERE setting_key IN ('about_stat_image_1','about_stat_image_2','about_stat_image_3','about_stat_image_4');
-- Add new single stat images key (comma-separated paths)
INSERT INTO settings (setting_key, setting_value, setting_group) VALUES ('about_stat_images', '', 'media') ON DUPLICATE KEY UPDATE setting_value = setting_value;
