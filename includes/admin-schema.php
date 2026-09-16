<?php
// Cached, idempotent schema migrations.
//
// Admin pages used to run ALTER TABLE / SHOW COLUMNS / SHOW TABLES on EVERY
// request (e.g. ~11 statements in tours.php, 3 ALTERs in each of bookings.php,
// inquiries.php and quotes.php). That made sidebar links load slowly because
// every page re-ran the same DDL/metadata queries.
//
// Each migration now runs exactly ONCE per schema version. Afterwards a marker
// file in cache/schema/ short-circuits the migration so later requests skip it.
// Bump ADMIN_SCHEMA_VERSION below whenever new migrations are added so they run
// again on the next load (old markers become inert).

if (!defined('BASE_PATH')) {
    return;
}

if (!defined('ADMIN_SCHEMA_VERSION')) {
    define('ADMIN_SCHEMA_VERSION', '1');
}

function admin_schema_mark_dir() {
    return BASE_PATH . 'cache/schema/';
}

function adminSchemaOnce($key, callable $migrate) {
    if (!defined('BASE_PATH')) {
        return false;
    }
    $dir = admin_schema_mark_dir();
    if (!is_dir($dir)) {
        if (@mkdir($dir, 0775, true) === false && !is_dir($dir)) {
            error_log("adminSchemaOnce: cannot create {$dir}");
            return false;
        }
    }
    $file = $dir . 'v' . ADMIN_SCHEMA_VERSION . '_' . preg_replace('/[^a-z0-9_-]/i', '', $key) . '.done';
    if (file_exists($file)) {
        return true;
    }
    try {
        $result = $migrate();
    } catch (\Throwable $e) {
        error_log("Schema migration failed ({$key}): " . $e->getMessage());
        return false;
    }
    if ($result === false) {
        return false;
    }
    @file_put_contents($file, date('c'));
    return true;
}