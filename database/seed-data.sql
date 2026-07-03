-- =============================================
-- KIZZA TOURS & SAFARIS - COMPREHENSIVE SEED DATA
-- Premium East Africa Tourism Platform
-- Complete sample data matching the brand prompt
-- =============================================

USE kizza_tours;

-- =============================================
-- DESTINATIONS (9 from the prompt)
-- =============================================
INSERT INTO destinations (name, country, slug, description, short_description, status, sort_order) VALUES
('Serengeti', 'Tanzania', 'serengeti', 'Endless plains stretching as far as the eye can see, the Serengeti is home to the Great Migration, the Big Five, and some of the most spectacular wildlife viewing on Earth. Experience the raw beauty of Africa''s most iconic wilderness.', 'Endless plains, abundant wildlife, and the Great Migration.', 'active', 1),
('Maasai Mara', 'Kenya', 'maasai-mara', 'Kenya''s most famous reserve, the Maasai Mara offers unparalleled wildlife viewing with the Big Five, the spectacular Great Migration river crossings, and authentic Maasai cultural experiences against a backdrop of golden savannah.', 'Kenya''s premier safari destination with the Big Five.', 'active', 2),
('Ngorongoro', 'Tanzania', 'ngorongoro', 'The world''s largest inactive volcanic caldera, the Ngorongoro Crater is a natural wonder teeming with wildlife. This UNESCO World Heritage site offers a unique safari experience with dense animal populations in a stunning setting.', 'The world''s largest inactive volcanic caldera.', 'active', 3),
('Kilimanjaro', 'Tanzania', 'kilimanjaro', 'Africa''s highest peak at 5,895 meters, Mount Kilimanjaro offers the adventure of a lifetime. Climb through five distinct climate zones from rainforest to arctic summit, standing on the roof of Africa.', 'Africa''s highest peak at 5,895 meters.', 'active', 4),
('Zanzibar', 'Tanzania', 'zanzibar', 'Pristine white-sand beaches, turquoise Indian Ocean waters, and a rich cultural heritage await in Zanzibar. The Spice Island offers perfect beach relaxation, water sports, and exploration of historic Stone Town.', 'Pristine beaches, turquoise waters, and rich history.', 'active', 5),
('Bwindi', 'Uganda', 'bwindi', 'The Bwindi Impenetrable Forest is a UNESCO World Heritage site and home to half of the world''s remaining mountain gorillas. Trek through ancient rainforest for an unforgettable gorilla encounter.', 'Home to half the world''s mountain gorillas.', 'active', 6),
('Volcanoes National Park', 'Rwanda', 'volcanoes-national-park', 'Misty mountains, bamboo forests, and rare mountain gorillas make Volcanoes National Park one of Africa''s most magical destinations. Track gorillas in the footsteps of Dian Fossey.', 'Misty mountains and rare mountain gorillas.', 'active', 7),
('Amboseli', 'Kenya', 'amboseli', 'Famous for its large elephant herds and stunning views of Mount Kilimanjaro, Amboseli National Park offers classic African safari scenery with the backdrop of Africa''s highest peak.', 'Iconic views of Kilimanjaro and large elephant herds.', 'active', 8),
('Tarangire', 'Tanzania', 'tarangire', 'Known for its massive baobab trees and large elephant herds, Tarangire National Park is a hidden gem with diverse wildlife, over 550 bird species, and beautiful landscapes.', 'Famous for baobab trees and elephant herds.', 'active', 9);

-- =============================================
-- PACKAGE CATEGORIES (6 from the prompt)
-- =============================================
INSERT INTO package_categories (name, slug, description, sort_order) VALUES
('Tanzania Safaris', 'tanzania-safaris', 'Explore the best of Tanzania with our premium safari packages across Serengeti, Ngorongoro, Tarangire and more.', 1),
('Kenya Safaris', 'kenya-safaris', 'Discover Kenya\'s magnificent wildlife and landscapes from the Maasai Mara to Amboseli.', 2),
('Uganda Gorilla Tours', 'uganda-gorilla-tours', 'Trek through lush forests to encounter mountain gorillas in their natural habitat.', 3),
('Rwanda Tours', 'rwanda-tours', 'Experience luxury gorilla trekking in Rwanda\'s Volcanoes National Park.', 4),
('Zanzibar Holidays', 'zanzibar-holidays', 'Relax on pristine beaches and explore the cultural wonders of Zanzibar.', 5),
('Kilimanjaro Packages', 'kilimanjaro-packages', 'Conquer Africa\'s highest peak with our expert-guided climbing packages.', 6);

-- =============================================
-- TOUR PACKAGES (10 premium packages matching prompt)
-- =============================================
INSERT INTO tour_packages (destination_id, title, slug, duration, price, currency, country, rating, max_guests, description, highlights, includes, status, featured, sort_order) VALUES
(1, 'Serengeti Luxury Safari Experience', 'serengeti-luxury-safari', '7 Days / 6 Nights', 4200.00, 'USD', 'Tanzania', 5.0, 12,
'A premium luxury safari through the Serengeti ecosystem. Witness the Great Migration, track the Big Five, and stay in award-winning luxury lodges with panoramic views of the African savannah.',
'Game Drives,Great Migration Viewing,Big Five Tracking,Luxury Lodge Accommodation,Professional Guide,Sunset Sundowners,Bush Breakfast',
'All Accommodation,Full Board Meals,Professional Guide,Game Drives,Park Fees,Airport Transfers,Drinking Water',
'active', 1, 1),

(2, 'Maasai Mara Great Migration Safari', 'maasai-mara-migration', '5 Days / 4 Nights', 3800.00, 'USD', 'Kenya', 5.0, 10,
'Witness one of nature''s greatest spectacles - the Great Migration river crossings in the Maasai Mara. Stay in premium tented camps and enjoy hot air balloon safaris over the savannah.',
'River Crossings,Hot Air Balloon Safari,Big Five Game Drives,Bush Dinner,Maasai Village Visit,Sundowner Cocktails',
'Luxury Tented Camp,All Meals,Game Drives,Park Fees,Balloon Safari,Professional Guide',
'active', 1, 2),

(3, 'Ngorongoro Crater Expedition', 'ngorongoro-crater', '4 Days / 3 Nights', 3200.00, 'USD', 'Tanzania', 5.0, 10,
'Descend into the world''s largest intact volcanic caldera for a unique safari experience. The Ngorongoro Crater boasts one of the highest densities of wildlife in Africa.',
'Crater Floor Game Drive,Big Five Viewing,Empakaai Crater Hike,Olmoti Village Visit,Lake Magadi Flamingos',
'Luxury Lodge,Full Board,Park Fees,Crater Tour,Professional Guide,Transfers',
'active', 1, 3),

(4, 'Kilimanjaro Machame Route Expedition', 'kilimanjaro-machame', '7 Days / 6 Nights', 3200.00, 'USD', 'Tanzania', 5.0, 12,
'Conquer Africa\'s highest peak via the scenic Machame Route. Experience five climate zones, from lush rainforest to arctic summit, with expert guides and premium camping equipment.',
'Summit at Uhuru Peak,Machame Route,Professional Guides,Quality Camping Gear,Summit Certificate,Porters & Cooks',
'Professional Guide Team,All Meals,Camping Equipment,Park Fees,Summit Certificate,Transfers',
'active', 1, 4),

(4, 'Kilimanjaro Lemosho Route Premium', 'kilimanjaro-lemosho', '8 Days / 7 Nights', 4200.00, 'USD', 'Tanzania', 5.0, 10,
'Our most exclusive Kilimanjaro climb via the remote and scenic Lemosho Route. Higher success rates, smaller groups, and extra acclimatization days make this the premium choice.',
'Western Breach Approach,Panoramic Views,High Success Rate,Small Groups,Luxury Camping,Private Toilets',
'Expert Guides,All Equipment,Nutritious Meals,Park Fees,Oxygen Monitoring,Summit Celebration',
'active', 1, 5),

(5, 'Zanzibar Luxury Beach Holiday', 'zanzibar-luxury-beach', '6 Days / 5 Nights', 3100.00, 'USD', 'Tanzania', 5.0, 8,
'Unwind on the pristine beaches of Zanzibar at a premium beach resort. Enjoy sunset dhow cruises, spice tours, snorkeling in turquoise waters, and explore historic Stone Town.',
'Beach Resort,Spa Treatments,Snorkeling,Stone Town Tour,Spice Farm Visit,Sunset Dhow Cruise,Deep Sea Fishing',
'Luxury Resort,Breakfast,Park Fees,Stone Town Tour,Airport Transfers',
'active', 1, 6),

(6, 'Uganda Gorilla Trekking Adventure', 'uganda-gorilla-trekking', '4 Days / 3 Nights', 5500.00, 'USD', 'Uganda', 5.0, 8,
'Trek through the misty Bwindi Impenetrable Forest to spend an unforgettable hour with a mountain gorilla family. A life-changing wildlife encounter in one of Africa\'s most beautiful settings.',
'Gorilla Encounter,Nature Walks,Bird Watching,Luxury Eco-Lodge,Community Visit,Waterfall Hike',
'Gorilla Permit,Luxury Lodge,Full Board,Professional Guide,Park Fees,Transfers',
'active', 1, 7),

(7, 'Rwanda Luxury Gorilla Safari', 'rwanda-luxury-gorilla', '4 Days / 3 Nights', 7200.00, 'USD', 'Rwanda', 5.0, 6,
'The ultimate luxury gorilla trekking experience in Rwanda\'s Volcanoes National Park. Stay at a world-class lodge, trek to see mountain gorillas, and explore the land of a thousand hills.',
'Gorilla Trekking,Luxury Lodge,Golden Monkey Trek,Kigali City Tour,Dian Fossey Hike,Private Butler Service',
'Gorilla Permit,Ultra-Luxury Lodge,All Meals,Private Guide,Transfers,Personalized Service',
'active', 1, 8),

(8, 'Amboseli & Kilimanjaro Views Safari', 'amboseli-kilimanjaro', '3 Days / 2 Nights', 2200.00, 'USD', 'Kenya', 5.0, 10,
'Experience the iconic view of Kilimanjaro from Amboseli National Park. Large elephant herds, stunning mountain backdrops, and excellent bird watching make this a must-do safari.',
'Elephant Herds,Kilimanjaro Views,Game Drives,Bird Watching,Maasai Cultural Visit,Photography',
'Luxury Camp,Full Board,Park Fees,Game Drives,Professional Guide,Transfers',
'active', 0, 9),

(9, 'Tarangire & Manyara Safari', 'tarangire-manyara', '3 Days / 2 Nights', 2100.00, 'USD', 'Tanzania', 5.0, 10,
'Discover two of Tanzania\'s most scenic parks. Tarangire\'s baobab-studded landscapes and Lake Manyara\'s tree-climbing lions offer a unique and diverse safari experience.',
'Baobab Trees,Tree-Climbing Lions,Lake Manyara,Flight of Flamingos,Game Drives,Bird Watching',
'Lodge Accommodation,Full Board,Park Fees,Game Drives,Professional Guide,Transfers',
'active', 0, 10);

-- =============================================
-- GALLERY ITEMS (matching prompt categories)
-- =============================================
INSERT INTO gallery (title, description, category, location, featured, status, sort_order) VALUES
('King of the Savannah', 'A majestic lion surveys his kingdom from atop a rocky outcrop in the Serengeti.', 'wildlife', 'Serengeti, Tanzania', 1, 'active', 1),
('Elephants at Sunset', 'A family of elephants silhouetted against the setting sun in Amboseli.', 'wildlife', 'Amboseli, Kenya', 1, 'active', 2),
('Paradise Found', 'Crystal clear turquoise waters and pristine white sands of Zanzibar.', 'beaches', 'Zanzibar, Tanzania', 1, 'active', 3),
('Roof of Africa', 'The majestic summit of Kilimanjaro at sunrise, seen from the Shira Plateau.', 'mountains', 'Kilimanjaro, Tanzania', 1, 'active', 4),
('Warrior Spirit', 'A Maasai warrior in traditional red shuka stands proudly on the Mara plains.', 'culture', 'Maasai Mara, Kenya', 1, 'active', 5),
('Luxury in the Wild', 'An infinity pool overlooking the Serengeti plains at a premium safari lodge.', 'lodges', 'Serengeti, Tanzania', 1, 'active', 6),
('Cheetah Sprint', 'A cheetah in full sprint across the Serengeti grasslands.', 'wildlife', 'Serengeti, Tanzania', 0, 'active', 7),
('Mountain Gorilla', 'A silverback mountain gorilla in the misty forests of Volcanoes National Park.', 'wildlife', 'Volcanoes National Park, Rwanda', 0, 'active', 8),
('Great Migration Crossing', 'Thousands of wildebeest brave crocodile-infested waters during the Mara River crossing.', 'wildlife', 'Maasai Mara, Kenya', 0, 'active', 9),
('Ngorongoro Sunrise', 'The sun rising over the misty Ngorongoro Crater floor.', 'mountains', 'Ngorongoro, Tanzania', 0, 'active', 10),
('Stone Town Streets', 'The narrow, vibrant streets of Zanzibar''s historic Stone Town.', 'culture', 'Zanzibar, Tanzania', 0, 'active', 11),
('Baobab Sunset', 'Ancient baobab trees silhouetted against a fiery African sunset in Tarangire.', 'mountains', 'Tarangire, Tanzania', 0, 'active', 12),
('Luxury Tented Camp', 'An elegant tented suite with views of the Maasai Mara at dusk.', 'lodges', 'Maasai Mara, Kenya', 0, 'active', 13),
('Dhow at Sunset', 'A traditional dhow sailing boat gliding across the Zanzibar channel at sunset.', 'beaches', 'Zanzibar, Tanzania', 0, 'active', 14),
('Maasai Jumping Dance', 'Maasai warriors performing the traditional adumu jumping dance.', 'culture', 'Maasai Mara, Kenya', 0, 'active', 15);

-- =============================================
-- TESTIMONIALS (luxury reviews matching prompt)
-- =============================================
INSERT INTO testimonials (customer_name, customer_title, review, rating, country, tour_package, featured, status) VALUES
('Sarah & Michael Johnson', 'United States - Serengeti Luxury Safari', 'The most incredible experience of our lives! Kizza Tours & Safaris organized an unforgettable safari in the Serengeti. Every detail was perfect - from the luxury lodge with views of the savannah to our expert guide who found us the Big Five within two days. Our guide was knowledgeable, kind, and made sure we saw everything we dreamed of.', 5.0, 'United States', 'Serengeti Luxury Safari Experience', 1, 'approved'),

('David & Lisa Chen', 'Canada - Gorilla Trekking Adventure', 'Gorilla trekking with Kizza Tours & Safaris was a life-changing experience. Coming face to face with a mountain gorilla family in Bwindi was something we will never forget. The team took care of every detail and made us feel completely safe throughout the trek. The lodges were exceptional and the service was world-class.', 5.0, 'Canada', 'Uganda Gorilla Trekking Adventure', 1, 'approved'),

('Emma & James Wilson', 'United Kingdom - Kilimanjaro Expedition', 'We climbed Kilimanjaro with Kizza Tours & Safaris and it was the most challenging and rewarding experience of our lives. The guides were professional, encouraging, and incredibly experienced. Thanks to their support and expertise, all six members of our group made it to the summit. We will never forget watching sunrise from the roof of Africa!', 5.0, 'United Kingdom', 'Kilimanjaro Machame Route Expedition', 1, 'approved'),

('Maria & Carlos Garcia', 'Spain - Zanzibar Beach Holiday', 'Our honeymoon in Zanzibar arranged by Kizza Tours & Safaris was pure magic. The beachfront resort was absolutely stunning, the sunset dhow cruise was romantic beyond words, and the spice tour was fascinating. It was the perfect blend of relaxation and adventure. Already planning our return!', 5.0, 'Spain', 'Zanzibar Luxury Beach Holiday', 1, 'approved'),

('Robert & Anne Mitchell', 'Australia - Rwanda Gorilla Safari', 'The Rwanda luxury gorilla safari exceeded every expectation. The lodge was one of the most beautiful places we have ever stayed, and the gorilla trek was absolutely breathtaking. Kizza Tours & Safaris arranged every detail flawlessly. This is truly a world-class tour operator.', 5.0, 'Australia', 'Rwanda Luxury Gorilla Safari', 0, 'approved'),

('Yuki Tanaka', 'Japan - Maasai Mara Migration Safari', 'Watching the Great Migration river crossing from a hot air balloon was the most spectacular experience of my life. Kizza Tours & Safaris organized everything perfectly. Our guide was incredibly knowledgeable about wildlife and the Maasai culture visit was a highlight. Highly recommended!', 5.0, 'Japan', 'Maasai Mara Great Migration Safari', 0, 'approved'),

('Pierre & Sophie Laurent', 'France - Ngorongoro Expedition', 'The Ngorongoro Crater is truly one of the most remarkable places on Earth. Kizza Tours & Safaris made our visit unforgettable with a knowledgeable guide who knew exactly where to find the wildlife. The lodge overlooking the crater rim was spectacular. Merci beaucoup!', 4.8, 'France', 'Ngorongoro Crater Expedition', 0, 'approved'),

('Hans & Ingrid Mueller', 'Germany - Luxury Tanzania Safari', 'Our 10-day luxury Tanzania safari with Kizza Tours & Safaris was flawless. From the Serengeti to Ngorongoro to Zanzibar, every detail was perfectly orchestrated. The guides were professional, the lodges were exceptional, and the wildlife viewing was beyond anything we imagined.', 5.0, 'Germany', 'Serengeti Luxury Safari Experience', 0, 'approved');

-- =============================================
-- FAQ (comprehensive questions matching prompt)
-- =============================================
INSERT INTO faq (question, answer, category, sort_order, status) VALUES
('What is the best time to visit East Africa?', 'The best time depends on what you want to see and do:\n\n• Great Migration (Serengeti & Maasai Mara): June to October for river crossings, December to March for calving season.\n• Gorilla Trekking (Uganda & Rwanda): Dry seasons June to August and December to February.\n• Kilimanjaro Climbing: Year-round possible, best conditions June to October and December to March.\n• Zanzibar Beach Holidays: June to October and December to March for driest weather.\n• General Wildlife Viewing: Dry season (June to October) offers best viewing as animals gather around water sources.', 'General', 1, 'active'),

('Do I need a visa to visit East African countries?', 'Visa requirements vary by country:\n\n• Tanzania: Most visitors need a visa (available on arrival or as e-visa).\n• Kenya: E-visa required before travel for most nationalities.\n• Uganda: Visa available on arrival for most nationalities.\n• Rwanda: Visa on arrival for all nationalities (or e-visa).\n• Zanzibar: Same visa as Tanzania.\n\nKizza Tours & Safaris provides comprehensive visa guidance and support documentation for all our guests. We recommend applying for e-visas before travel to avoid queues.', 'Travel', 2, 'active'),

('Is it safe to travel to East Africa?', 'Yes, East Africa is generally very safe for tourists. Kizza Tours & Safaris prioritizes your safety with:\n\n• Experienced, professionally trained guides.\n• Well-maintained, safari-ready 4x4 vehicles with safety equipment.\n• Carefully planned itineraries with reputable accommodations.\n• 24/7 support throughout your journey.\n• Comprehensive travel insurance recommendations.\n\nMillions of tourists visit East Africa safely every year. We monitor local conditions closely and maintain the highest safety standards.', 'Safety', 3, 'active'),

('What should I pack for a safari?', 'We recommend packing:\n\n• Neutral-colored clothing (khaki, beige, olive, brown).\n• Comfortable walking shoes or hiking boots.\n• Warm jacket or fleece for morning game drives (it can be cold!).\n• Sunscreen (SPF 50+), wide-brimmed hat, and sunglasses.\n• Insect repellent (containing DEET).\n• Binoculars (essential for wildlife viewing).\n• Camera with zoom lens and extra memory cards.\n• Reusable water bottle.\n• Small backpack for day trips.\n• Swimsuit (for lodge pools or beach time).\n\nKizza Tours & Safaris provides a detailed packing list after booking.', 'Packing', 4, 'active'),

('Can I customize my tour package?', 'Absolutely! Every Kizza Tours & Safaris experience is fully customizable. Our expert team will work with you to create a personalized itinerary that matches your interests, budget, and schedule. You can:\n\n• Mix and match destinations.\n• Choose your preferred accommodation style.\n• Add or remove activities.\n• Extend or shorten your stay.\n• Combine safari with beach relaxation.\n• Add cultural experiences.\n\nContact us and we will create your perfect East African adventure.', 'Booking', 5, 'active'),

('What types of accommodation do you offer?', 'We offer a wide range of accommodation options:\n\n• Luxury Lodges: World-class properties like Four Seasons Safari Lodge, Singita, and &Beyond.\n• Tented Camps: Premium canvas camps with en-suite bathrooms and panoramic views.\n• Boutique Hotels: Charming properties in cities and towns.\n• Beach Resorts: Five-star resorts on Zanzibar\'s pristine coastline.\n• Eco-Lodges: Sustainable properties with minimal environmental impact.\n• Mountain Huts: Comfortable accommodations on Kilimanjaro routes.\n\nAll accommodations are carefully selected for quality, service, and location.', 'Accommodation', 6, 'active'),

('What is included in the tour price?', 'Our tour packages typically include:\n\n• All accommodation as specified in the itinerary.\n• Full board meals (breakfast, lunch, dinner) on safari.\n• Professional English-speaking guide.\n• All game drives and activities.\n• Park entry and conservation fees.\n• Airport transfers on arrival and departure.\n• Drinking water during game drives.\n\nExcluded: International flights, visas, travel insurance, tips, personal expenses, and drinks at bars.', 'Booking', 7, 'active'),

('How do I book a tour with Kizza Tours & Safaris?', 'Booking is easy:\n\n1. Browse our packages and choose your preferred experience.\n2. Click "Book Now" or "Build Your Custom Safari".\n3. Fill in your details and travel preferences.\n4. Our team will respond within 24 hours with a personalized itinerary.\n5. Review and confirm your booking with a deposit.\n6. Prepare for your adventure!\n\nYou can also contact us via WhatsApp for immediate assistance.', 'Booking', 8, 'active'),

('What health preparations do I need?', 'Recommended health preparations:\n\n• Yellow Fever vaccination (required for entry to most East African countries).\n• Hepatitis A & B vaccinations.\n• Typhoid vaccination.\n• Anti-malarial medication (consult your doctor).\n• Tetanus booster.\n• Travel insurance with medical coverage.\n\nWe recommend consulting your travel health clinic or doctor at least 4-6 weeks before travel. Kizza Tours & Safaris provides detailed health and safety information after booking.', 'Health', 9, 'active'),

('What is your cancellation policy?', 'Our cancellation policy:\n\n• More than 60 days before departure: Full refund minus deposit.\n• 30-60 days before departure: 50% refund.\n• Less than 30 days before departure: No refund.\n\nWe strongly recommend comprehensive travel insurance to cover unforeseen circumstances. Kizza Tours & Safaris will work with you flexibly in case of emergencies.', 'Booking', 10, 'active'),

('Do you offer family-friendly safaris?', 'Yes! Kizza Tours & Safaris welcomes families and offers specially designed family safari packages. Our family-friendly features include:\n\n• Accommodations with family rooms or interconnecting suites.\n• Children\'s activities and educational programs.\n• Shorter, age-appropriate game drives.\n• Experienced guides who are excellent with children.\n• Flexible itineraries to suit family needs.\n• Special rates for children.\n\nContact us to plan the perfect family adventure!', 'Family', 11, 'active'),

('What makes Kizza Tours & Safaris different?', 'Kizza Tours & Safaris stands out because:\n\n• We are local experts with deep knowledge of East Africa.\n• We offer truly personalized service and custom itineraries.\n• We use only the best guides and accommodations.\n• We are committed to sustainable and responsible tourism.\n• We provide 24/7 support throughout your journey.\n• We offer competitive, transparent pricing with no hidden fees.\n• We give back to local communities through our foundation.\n\nExperience the Kizza difference - where every journey is crafted with passion and excellence.', 'General', 12, 'active');

-- =============================================
-- SITE SETTINGS (matching brand identity)
-- =============================================
INSERT INTO settings (setting_key, setting_value, setting_group) VALUES
('site_name', 'Kizza Tours & Safaris', 'general'),
('site_tagline', 'Discover East Africa Beyond Expectations', 'general'),
('site_email', 'info@kizzatours.com', 'contact'),
('site_phone', '+255 123 456 789', 'contact'),
('site_whatsapp', '255123456789', 'contact'),
('site_address', 'Arusha, Tanzania', 'contact'),
('facebook_url', 'https://facebook.com/kizzatours', 'social'),
('instagram_url', 'https://instagram.com/kizzatours', 'social'),
('twitter_url', 'https://twitter.com/kizzatours', 'social'),
('youtube_url', 'https://youtube.com/@kizzatours', 'social'),
('tripadvisor_url', 'https://tripadvisor.com/kizzatours', 'social'),
('about_content_1', 'is dedicated to providing unforgettable travel experiences across East Africa while delivering exceptional service from the moment guests inquire until they return home with unforgettable memories.', 'content'),
('about_content_2', 'specializes in safaris, gorilla trekking, Kilimanjaro expeditions, cultural journeys, luxury escapes, and tailor-made adventures that showcase the very best of East Africa.', 'content'),
('about_content_3', 'creates meaningful adventures, lifelong memories, and authentic connections with Africa. Every journey is crafted with passion, expertise, and an unwavering commitment to excellence.', 'content'),
('vision_text', 'aspires to become the leading and most trusted tour operator in East Africa, recognized globally for delivering exceptional travel experiences, promoting sustainable tourism, and creating meaningful connections between travelers, wildlife, nature, and local communities.', 'content'),
('about_stat_years', '10+', 'content'),
('about_stat_years_label', 'Years Experience', 'content'),
('about_stat_travelers', '5000+', 'content'),
('about_stat_travelers_label', 'Happy Travelers', 'content')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

-- Update specific settings that might conflict
UPDATE settings SET setting_value = 'Kizza Tours & Safaris' WHERE setting_key = 'site_name';
UPDATE settings SET setting_value = 'Discover East Africa Beyond Expectations' WHERE setting_key = 'site_tagline';
