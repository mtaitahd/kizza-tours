-- KIZZA TOURS & SAFARIS Database Schema
-- Premium East Africa Tourism Platform

CREATE DATABASE IF NOT EXISTS kizza_tours;
USE kizza_tours;

-- Admin Users
CREATE TABLE admin_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('super_admin', 'admin', 'editor') DEFAULT 'admin',
    last_login DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Destinations
CREATE TABLE destinations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    country VARCHAR(50) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    short_description VARCHAR(255),
    image VARCHAR(255),
    thumbnail VARCHAR(255),
    video_url VARCHAR(255),
    map_coordinates VARCHAR(50),
    status ENUM('active', 'inactive') DEFAULT 'active',
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tour Packages
CREATE TABLE tour_packages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    destination_id INT,
    title VARCHAR(200) NOT NULL,
    slug VARCHAR(200) NOT NULL UNIQUE,
    duration VARCHAR(50),
    price DECIMAL(12,2),
    currency VARCHAR(10) DEFAULT 'USD',
    country VARCHAR(50),
    rating DECIMAL(2,1) DEFAULT 5.0,
    max_guests INT DEFAULT 10,
    description TEXT,
    itinerary TEXT,
    highlights TEXT,
    includes TEXT,
    excludes TEXT,
    image VARCHAR(255),
    gallery TEXT,
    video_url VARCHAR(255),
    featured TINYINT(1) DEFAULT 0,
    status ENUM('active', 'inactive') DEFAULT 'active',
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (destination_id) REFERENCES destinations(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Package Categories
CREATE TABLE package_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    image VARCHAR(255),
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Package-Category Relationship
CREATE TABLE package_category_rel (
    package_id INT NOT NULL,
    category_id INT NOT NULL,
    PRIMARY KEY (package_id, category_id),
    FOREIGN KEY (package_id) REFERENCES tour_packages(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES package_categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Bookings
CREATE TABLE bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_reference VARCHAR(20) NOT NULL UNIQUE,
    package_id INT,
    destination_id INT,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    phone VARCHAR(30) NOT NULL,
    country VARCHAR(50),
    travel_date DATE,
    guests INT DEFAULT 1,
    budget DECIMAL(12,2),
    accommodation VARCHAR(50),
    message TEXT,
    status ENUM('pending', 'confirmed', 'cancelled', 'completed') DEFAULT 'pending',
    payment_status ENUM('unpaid', 'partial', 'paid') DEFAULT 'unpaid',
    payment_method VARCHAR(50),
    source VARCHAR(50) DEFAULT 'website',
    admin_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (package_id) REFERENCES tour_packages(id) ON DELETE SET NULL,
    FOREIGN KEY (destination_id) REFERENCES destinations(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Inquiries / Contact Messages
CREATE TABLE inquiries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    phone VARCHAR(30),
    subject VARCHAR(200),
    message TEXT NOT NULL,
    status ENUM('new', 'read', 'replied') DEFAULT 'new',
    source VARCHAR(50) DEFAULT 'contact_form',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Gallery
CREATE TABLE gallery (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200),
    description TEXT,
    image VARCHAR(255) NOT NULL,
    thumbnail VARCHAR(255),
    category VARCHAR(50),
    location VARCHAR(100),
    featured TINYINT(1) DEFAULT 0,
    status ENUM('active', 'inactive') DEFAULT 'active',
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Gallery Categories
CREATE TABLE gallery_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Testimonials
CREATE TABLE testimonials (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_name VARCHAR(100) NOT NULL,
    customer_title VARCHAR(100),
    customer_photo VARCHAR(255),
    review TEXT NOT NULL,
    rating DECIMAL(2,1) DEFAULT 5.0,
    country VARCHAR(50),
    tour_package VARCHAR(200),
    featured TINYINT(1) DEFAULT 0,
    status ENUM('pending', 'approved') DEFAULT 'approved',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- FAQ
CREATE TABLE faq (
    id INT AUTO_INCREMENT PRIMARY KEY,
    question VARCHAR(500) NOT NULL,
    answer TEXT NOT NULL,
    category VARCHAR(50),
    sort_order INT DEFAULT 0,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Website Settings
CREATE TABLE settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    setting_group VARCHAR(50) DEFAULT 'general',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Newsletter Subscribers
CREATE TABLE booking_replies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    admin_id INT NOT NULL,
    subject VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (admin_id) REFERENCES admin_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE subscribers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(100) NOT NULL UNIQUE,
    name VARCHAR(100),
    status ENUM('active', 'unsubscribed') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert Default Admin
INSERT INTO admin_users (username, email, password, full_name, role)
VALUES ('admin', 'admin@kizzatours.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', 'super_admin');

-- Insert Default Settings
INSERT INTO settings (setting_key, setting_value, setting_group) VALUES
('site_name', 'Kizza Tours & Safaris', 'general'),
('site_tagline', 'Discover East Africa Beyond Expectations', 'general'),
('site_email', 'info@kizzatours.com', 'contact'),
('site_phone', '+255 123 456 789', 'contact'),
('site_address', 'Arusha, Tanzania', 'contact'),
('whatsapp_number', '+255123456789', 'contact'),
('facebook_url', '#', 'social'),
('instagram_url', '#', 'social'),
('twitter_url', '#', 'social'),
('youtube_url', '#', 'social'),
('tripadvisor_url', '#', 'social'),
('about_stat_years', '10+', 'content'),
('about_stat_years_label', 'Years Experience', 'content'),
('about_stat_travelers', '5000+', 'content'),
('about_stat_travelers_label', 'Happy Travelers', 'content'),
('about_stat_images', '', 'media');

-- Insert Gallery Categories
INSERT INTO gallery_categories (name, slug, sort_order) VALUES
('Wildlife', 'wildlife', 1),
('Beaches', 'beaches', 2),
('Mountains', 'mountains', 3),
('Culture', 'culture', 4),
('Lodges', 'lodges', 5);

-- Insert FAQ
INSERT INTO faq (question, answer, category, sort_order) VALUES
('What is the best time to visit East Africa?', 'The best time depends on what you want to see. For the Great Migration in Serengeti and Maasai Mara, June to October is ideal. For gorilla trekking in Uganda and Rwanda, the dry seasons (June-August and December-February) are best. Kilimanjaro climbing is possible year-round, but the best conditions are from June to October and December to March.', 'General', 1),
('Do I need a visa to visit East African countries?', 'Visa requirements vary by country. Most visitors need a visa for Tanzania, Kenya, Uganda, and Rwanda. Many can get visas on arrival, but we recommend obtaining e-visas before travel. Our team will guide you through all visa requirements.', 'Travel', 2),
('Is it safe to travel to East Africa?', 'Yes, East Africa is generally safe for tourists. Kizza Tours & Safaris prioritizes your safety with experienced guides, well-maintained vehicles, and carefully planned itineraries. We monitor local conditions and provide 24/7 support throughout your journey.', 'Safety', 3),
('What should I pack for a safari?', 'We recommend neutral-colored clothing (khaki, beige, olive), comfortable walking shoes, a warm jacket for morning game drives, sunscreen, hat, sunglasses, insect repellent, binoculars, camera with zoom lens, and a reusable water bottle.', 'Packing', 4),
('Can I customize my tour package?', 'Absolutely! Every Kizza Tours & Safaris experience is fully customizable. Our expert team will work with you to create a personalized itinerary that matches your interests, budget, and schedule.', 'Booking', 5);
