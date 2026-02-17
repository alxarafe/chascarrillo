<?php
// mimic basic path relative to public/debug_menu.php
// public is in alxarafe/skeleton/public (usually) or alxarafe/public?
// Let's assume document root.

$baseDir = __DIR__ . '/../Modules'; // skeleton/Modules?
// Check if we are in skeleton/public or alxarafe/public.
// User said /var/www/html which maps to...
// Let's try locating skeleton relative to this file.

echo "Current Dir: " . __DIR__ . "\n";
$skeletonModules = realpath(__DIR__ . '/../Modules');
// If public/ is inside skeleton/, then ../Modules is correct.
// If public/ is alxarafe/public, then ../skeleton/Modules?

echo "Trying path: " . $skeletonModules . "\n";

if (!$skeletonModules || !is_dir($skeletonModules)) {
    // Try deeper assumption
    $skeletonModules = realpath(__DIR__ . '/../../skeleton/Modules');
    echo "Retrying: " . $skeletonModules . "\n";
}

if ($skeletonModules && is_dir($skeletonModules)) {
    echo "Found Skeleton Modules at: $skeletonModules\n";

    $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($skeletonModules));
    foreach ($iterator as $file) {
        if ($file->isFile() && str_contains($file->getFilename(), 'Controller.php')) {
            echo "Found File: " . $file->getPathname() . "\n";
            require_once $file->getPathname();
            $content = file_get_contents($file->getPathname());
            if (preg_match('/namespace\s+([\w\\\\]+);/', $content, $nsMatches) && preg_match('/class\s+(\w+)/', $content, $clsMatches)) {
                $fullClassName = $nsMatches[1] . '\\' . $clsMatches[1];
                echo "  Class Detected: $fullClassName\n";
                if (class_exists($fullClassName)) {
                    echo "  [OK] Class Exists!\n";
                    // Check Menu Attribute?
                    try {
                        $reflection = new ReflectionClass($fullClassName);
                        // Just verify we can reflect
                        echo "  [OK] Reflection success.\n";
                    } catch (\Throwable $e) {
                        echo "  [FAIL] Reflection: " . $e->getMessage() . "\n";
                    }
                } else {
                    echo "  [FAIL] class_exists returned FALSE.\n";
                }
            } else {
                echo "  [FAIL] Regex failed to parse namespace/class.\n";
            }
        }
    }
} else {
    echo "FAILED to locate skeleton/Modules.\n";
}
