<?php
$c = file_get_contents('C:\\xampp\\apache\\conf\\httpd.conf');

preg_match('/DocumentRoot\s+"([^"]+)"/', $c, $m);
echo "DocumentRoot: " . ($m[1] ?? 'not found') . "\n";

$hasRewrite = (bool) preg_match('/^\s*LoadModule\s+rewrite_module/m', $c);
echo "mod_rewrite: " . ($hasRewrite ? 'LOADED' : 'NOT LOADED') . "\n";

preg_match_all('/AllowOverride\s+(\S+)/', $c, $ao);
echo "AllowOverride: " . implode(', ', array_unique($ao[1])) . "\n";

// Check the htdocs path
$docRoot = $m[1] ?? '';
$pixelforge = $docRoot . '/pixelforge';
echo "\nPixelforge path: $pixelforge\n";
echo "Exists: " . (is_dir($pixelforge) ? 'YES' : 'NO') . "\n";

// Check if api files are actually there
$apiFile = $pixelforge . '/api/debug.php';
echo "api/debug.php exists: " . (file_exists($apiFile) ? 'YES' : 'NO') . "\n";
