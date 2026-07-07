<?php
// Load dompdf's own vendor dependencies (php-font-lib, php-svg-lib, etc.)
$dompdfVendorAutoload = __DIR__ . '/dompdf/vendor/autoload.php';
if (file_exists($dompdfVendorAutoload)) {
    require_once $dompdfVendorAutoload;
}

// PSR-4 autoloader for PHPMailer and Dompdf
spl_autoload_register(function ($class) {
    $prefixes = [
        'PHPMailer\\PHPMailer\\' => __DIR__ . '/phpmailer/phpmailer/src/',
        'Dompdf\\' => __DIR__ . '/dompdf/dompdf/src/',
    ];
    foreach ($prefixes as $prefix => $baseDir) {
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) continue;
        $file = $baseDir . str_replace('\\', '/', substr($class, $len)) . '.php';
        if (file_exists($file)) { require $file; return; }
    }
    // Classmap for legacy classes
    $classmap = [
        'Cpdf' => __DIR__ . '/dompdf/dompdf/lib/Cpdf.php',
    ];
    if (isset($classmap[$class])) {
        require $classmap[$class];
        return;
    }
});
