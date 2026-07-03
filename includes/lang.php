<?php
$available_languages = [
    'en' => ['name' => 'English', 'flag' => 'us', 'file' => 'en.php'],
    'sw' => ['name' => 'Kiswahili', 'flag' => 'tz', 'file' => 'sw.php'],
    'fr' => ['name' => 'Français', 'flag' => 'fr', 'file' => 'fr.php'],
    'pt' => ['name' => 'Português', 'flag' => 'pt', 'file' => 'pt.php'],
    'zh' => ['name' => '中文', 'flag' => 'cn', 'file' => 'zh.php'],
    'de' => ['name' => 'Deutsch', 'flag' => 'de', 'file' => 'de.php'],
    'es' => ['name' => 'Español', 'flag' => 'es', 'file' => 'es.php'],
    'it' => ['name' => 'Italiano', 'flag' => 'it', 'file' => 'it.php'],
];

$current_lang = 'en';

if (isset($_GET['lang']) && array_key_exists($_GET['lang'], $available_languages)) {
    $current_lang = $_GET['lang'];
    $_SESSION['lang'] = $current_lang;
    setcookie('lang', $current_lang, time() + 86400 * 365, '/');
} elseif (isset($_SESSION['lang']) && array_key_exists($_SESSION['lang'], $available_languages)) {
    $current_lang = $_SESSION['lang'];
} elseif (isset($_COOKIE['lang']) && array_key_exists($_COOKIE['lang'], $available_languages)) {
    $current_lang = $_COOKIE['lang'];
    $_SESSION['lang'] = $current_lang;
}

$lang_file = __DIR__ . '/languages/' . $available_languages[$current_lang]['file'];
$lang = file_exists($lang_file) ? require $lang_file : require __DIR__ . '/languages/en.php';

function __($key) {
    global $lang;
    return $lang[$key] ?? $key;
}

function t($key) {
    return __($key);
}

function get_current_lang() {
    global $current_lang;
    return $current_lang;
}

function get_lang_dir() {
    global $lang;
    return $lang['lang_dir'] ?? 'ltr';
}

function get_lang_flag() {
    global $lang;
    return $lang['lang_flag'] ?? 'us';
}

function get_lang_name() {
    global $lang;
    return $lang['lang_name'] ?? 'English';
}

function get_available_languages() {
    global $available_languages;
    return $available_languages;
}

function language_switcher_link($code) {
    $current_url = $_SERVER['REQUEST_URI'];
    $parsed_url = parse_url($current_url);
    $query_params = [];
    if (isset($parsed_url['query'])) {
        parse_str($parsed_url['query'], $query_params);
    }
    $query_params['lang'] = $code;
    $query_string = http_build_query($query_params);
    $path = $parsed_url['path'] ?? '/';
    return $path . '?' . $query_string;
}
