<?php
// KIZZA TOURS & SAFARIS - Configuration
// Premium East Africa Tourism Platform

// Error Reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ===========================================
// ENVIRONMENT (.env) LOADER
// ===========================================
$envFile = dirname(__DIR__) . '/.env';
if (file_exists($envFile)) {
    $envLines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($envLines as $envLine) {
        $envLine = trim($envLine);
        if ($envLine === '' || strpos($envLine, '#') === 0) continue;
        if (strpos($envLine, '=') !== false) {
            list($envKey, $envValue) = explode('=', $envLine, 2);
            $_ENV[trim($envKey)] = trim(trim($envValue), '"\'');
            putenv(trim($envKey) . '=' . trim(trim($envValue), '"\''));
        }
    }
}

function env($key, $default = null) {
    $value = $_ENV[$key] ?? getenv($key);
    return ($value === false || $value === null) ? $default : $value;
}

// Database Configuration
define('DB_HOST', env('DB_HOST', 'localhost'));
define('DB_NAME', env('DB_NAME', 'kizza_tours'));
define('DB_USER', env('DB_USER', 'root'));
define('DB_PASS', env('DB_PASS', ''));
define('DB_CHARSET', env('DB_CHARSET', 'utf8mb4'));

// Site Configuration
define('SITE_NAME', env('SITE_NAME', 'Kizza Tours & Safaris'));
define('SITE_TAGLINE', env('SITE_TAGLINE', 'Discover East Africa Beyond Expectations'));
define('SITE_EMAIL', env('SITE_EMAIL', 'info@kizzatoursandsafaris.com'));
define('SITE_PHONE', env('SITE_PHONE', '+255 734 335 668'));
define('SITE_WHATSAPP', env('SITE_WHATSAPP', '+255734335668'));
define('SITE_ADDRESS', env('SITE_ADDRESS', 'Arusha, Tanzania'));

// Auto-detect SITE_URL: production domains use root path, dev uses subdirectory
$siteProtocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$siteHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
$siteDocRoot = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? getenv('DOCUMENT_ROOT') ?: ''), '/');
$siteBasePath = rtrim(str_replace('\\', '/', dirname(__DIR__)), '/');
$siteScriptDir = !empty($siteDocRoot) ? str_replace($siteDocRoot, '', $siteBasePath) : rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
$productionDomainsEnv = env('PRODUCTION_DOMAINS', 'kizzatoursandsafaris.com,www.kizzatoursandsafaris.com');
$productionDomains = array_map('trim', explode(',', $productionDomainsEnv));
if (in_array($siteHost, $productionDomains)) {
    define('SITE_URL', $siteProtocol . '://' . $siteHost);
} else {
    define('SITE_URL', $siteProtocol . '://' . $siteHost . $siteScriptDir);
}
define('SITE_CURRENCY', env('SITE_CURRENCY', 'USD'));

// Paths
define('BASE_PATH', dirname(__DIR__) . '/');
define('ASSETS_PATH', env('ASSETS_PATH', 'assets/'));
define('UPLOADS_PATH', env('UPLOADS_PATH', 'uploads/'));
define('ADMIN_PATH', env('ADMIN_PATH', 'admin/'));

// Timezone
date_default_timezone_set(env('TIMEZONE', 'Africa/Dar_es_Salaam'));

// Pagination
define('ITEMS_PER_PAGE', (int)env('ITEMS_PER_PAGE', 12));

// Booking Reference Prefix
define('BOOKING_PREFIX', env('BOOKING_PREFIX', 'KIZ'));

// Upload Limits
define('MAX_FILE_SIZE', (int)env('MAX_FILE_SIZE', 20971520));
$allowedExts = env('ALLOWED_EXTENSIONS', 'jpg,jpeg,png,webp,gif,svg,avif,bmp,tiff,tif,heic,heif,mp4,webm,ogg');
define('ALLOWED_EXTENSIONS', array_map('trim', explode(',', $allowedExts)));

// ===========================================
// DYNAMIC SETTINGS HELPER
// ===========================================
$settings_cache = null;

function getSetting($key, $default = '') {
    global $settings_cache;
    if ($settings_cache === null) {
        try {
            $db = Database::getInstance();
            $results = $db->fetchAll("SELECT setting_key, setting_value FROM settings");
            $settings_cache = [];
            foreach ($results as $row) {
                $settings_cache[$row['setting_key']] = $row['setting_value'];
            }
        } catch (Exception $e) {
            $settings_cache = [];
        }
    }
    $value = $settings_cache[$key] ?? null;
    return ($value !== null && $value !== '') ? $value : $default;
}

function getSettings($group = 'general') {
    try {
        $db = Database::getInstance();
        return $db->fetchAll("SELECT * FROM settings WHERE setting_group = ?", [$group]);
    } catch (Exception $e) {
        return [];
    }
}

function updateSetting($key, $value) {
    try {
        $db = Database::getInstance();
        $exists = $db->fetchOne("SELECT id FROM settings WHERE setting_key = ?", [$key]);
        if ($exists) {
            $db->query("UPDATE settings SET setting_value = ?, updated_at = NOW() WHERE setting_key = ?", [$value, $key]);
        } else {
            $db->query("INSERT INTO settings (setting_key, setting_value, setting_group) VALUES (?, ?, 'general')", [$key, $value]);
        }
        global $settings_cache;
        $settings_cache = null;
        return true;
    } catch (Exception $e) {
        error_log("Settings Update Error: " . $e->getMessage());
        return false;
    }
}

function getMediaUrl($key, $fallback = '') {
    $value = getSetting($key, '');
    if (!empty($value) && file_exists(BASE_PATH . $value)) {
        return SITE_URL . '/' . $value;
    }
    if (!empty($fallback)) {
        if (strpos($fallback, 'http://') === 0 || strpos($fallback, 'https://') === 0) {
            return $fallback;
        }
        $fbPath = strpos($fallback, ASSETS_PATH) === 0 ? $fallback : ASSETS_PATH . $fallback;
        if (file_exists(BASE_PATH . $fbPath)) {
            return SITE_URL . '/' . $fbPath;
        }
    }
    return '';
}

function getMediaPath($key) {
    $value = getSetting($key, '');
    if (!empty($value) && file_exists(BASE_PATH . $value)) {
        return $value;
    }
    return '';
}

// ===========================================
// DATA HELPERS
// ===========================================

function getDestinations($limit = null) {
    try {
        $db = Database::getInstance();
        $sql = "SELECT * FROM destinations WHERE status = 'active' ORDER BY sort_order ASC, name ASC";
        if ($limit) {
            $sql .= " LIMIT " . intval($limit);
        }
        return $db->fetchAll($sql);
    } catch (Exception $e) {
        return [];
    }
}

function getTourPackages($filter = [], $limit = null) {
    try {
        $db = Database::getInstance();
        $sql = "SELECT p.*, d.name as destination_name, d.country as destination_country 
                FROM tour_packages p 
                LEFT JOIN destinations d ON p.destination_id = d.id 
                WHERE p.status = 'active'";
        $params = [];
        
        if (!empty($filter['destination'])) {
            $sql .= " AND (p.destination_id = ? OR p.country = ?)";
            $params[] = $filter['destination'];
            $params[] = $filter['destination'];
        }
        if (!empty($filter['featured'])) {
            $sql .= " AND p.featured = 1";
        }
        
        $sql .= " ORDER BY p.sort_order ASC, p.created_at DESC";
        if ($limit) {
            $sql .= " LIMIT " . intval($limit);
        }
        return $db->fetchAll($sql, $params);
    } catch (Exception $e) {
        return [];
    }
}

function getGalleryItems($category = null, $limit = null) {
    try {
        $db = Database::getInstance();
        $sql = "SELECT * FROM gallery WHERE status = 'active'";
        $params = [];
        if ($category && $category !== 'all') {
            $sql .= " AND category = ?";
            $params[] = $category;
        }
        $sql .= " ORDER BY sort_order ASC, created_at DESC";
        if ($limit) {
            $sql .= " LIMIT " . intval($limit);
        }
        return $db->fetchAll($sql, $params);
    } catch (Exception $e) {
        return [];
    }
}

function getTestimonials($limit = null) {
    try {
        $db = Database::getInstance();
        $sql = "SELECT * FROM testimonials WHERE status = 'approved' ORDER BY created_at DESC";
        if ($limit) {
            $sql .= " LIMIT " . intval($limit);
        }
        return $db->fetchAll($sql);
    } catch (Exception $e) {
        return [];
    }
}

function getFAQs($limit = null) {
    try {
        $db = Database::getInstance();
        $sql = "SELECT * FROM faq WHERE status = 'active' ORDER BY sort_order ASC";
        if ($limit) {
            $sql .= " LIMIT " . intval($limit);
        }
        return $db->fetchAll($sql);
    } catch (Exception $e) {
        return [];
    }
}

// ===========================================
// UPLOAD HELPERS
// ===========================================

function convertToWebp($sourcePath, $quality = 80) {
    $ext = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));
    $image_formats = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'tiff', 'tif', 'avif'];
    if (!in_array($ext, $image_formats) || !function_exists('imagewebp')) {
        return $sourcePath;
    }
    $webpPath = preg_replace('/\.' . preg_quote($ext, '/') . '$/i', '.webp', $sourcePath);
    if (file_exists($webpPath)) {
        return $sourcePath;
    }
    $image = null;
    switch ($ext) {
        case 'jpg':
        case 'jpeg':
            $image = @imagecreatefromjpeg($sourcePath);
            break;
        case 'png':
            $image = @imagecreatefrompng($sourcePath);
            if ($image) {
                imagepalettetotruecolor($image);
                imagealphablending($image, true);
                imagesavealpha($image, true);
            }
            break;
        case 'gif':
            $image = @imagecreatefromgif($sourcePath);
            break;
        case 'bmp':
            $image = @imagecreatefrombmp($sourcePath);
            break;
        case 'tiff':
        case 'tif':
        case 'avif':
            if (function_exists('imagecreatefromtiff')) {
                $image = @imagecreatefromtiff($sourcePath);
            }
            break;
    }
    if (!$image) {
        return $sourcePath;
    }
    $result = @imagewebp($image, $webpPath, $quality);
    imagedestroy($image);
    if ($result) {
        @unlink($sourcePath);
        return $webpPath;
    }
    return $sourcePath;
}

function uploadFile($file, $targetDir, $prefix = 'file') {
    if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
        return false;
    }
    
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_EXTENSIONS)) {
        return false;
    }
    
    if ($file['size'] > MAX_FILE_SIZE) {
        return false;
    }
    
    $filename = $prefix . '_' . uniqid() . '.' . $ext;
    $targetPath = rtrim($targetDir, '/') . '/' . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        $converted = convertToWebp($targetPath);
        return str_replace(BASE_PATH, '', $converted);
    }
    
    return false;
}

function deleteFile($filePath) {
    $fullPath = BASE_PATH . ltrim($filePath, '/');
    if (file_exists($fullPath) && is_file($fullPath)) {
        return unlink($fullPath);
    }
    return false;
}

// ===========================================
// FORMATTING HELPERS
// ===========================================

function formatPrice($amount, $currency = 'USD') {
    $symbols = ['USD' => '$', 'EUR' => '€', 'GBP' => '£', 'TZS' => 'TSh'];
    $symbol = $symbols[$currency] ?? '$';
    return $symbol . number_format($amount, 0);
}

function formatDate($date, $format = 'M d, Y') {
    if (empty($date)) return 'N/A';
    return date($format, strtotime($date));
}

function limitText($text, $limit = 100) {
    if (strlen($text) <= $limit) return $text;
    return substr($text, 0, $limit) . '...';
}

function slugify($text) {
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    $text = preg_replace('~[^-\w]+~', '', $text);
    $text = trim($text, '-');
    $text = preg_replace('~-+~', '-', $text);
    $text = strtolower($text);
    return empty($text) ? 'n-a' : $text;
}

// Load SEO module (available globally after config)
require_once __DIR__ . '/seo.php';

// Load language module
require_once __DIR__ . '/lang.php';
