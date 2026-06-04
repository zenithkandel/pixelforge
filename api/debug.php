<?php
$c = file_get_contents('C:\\xampp\\apache\\conf\\httpd.conf');
preg_match_all('/DocumentRoot\s+"([^"]+)"/', $c, $m);
echo "DocumentRoot: " . ($m[1][0] ?? 'not found') . "\n";

$loaded = preg_match('/^\s*LoadModule\s+rewrite_module/', $c, $matches, PREG_OFFSET_LINE);
echo "mod_rewrite: " . ($loaded ? 'loaded' : 'NOT LOADED') . "\n";

preg_match_all('/<Directory\s+"([^"]+)"[^>]*>/', $c, $d);
echo "Directories:\n";
foreach ($d[1] as $dir) {
    echo "  - $dir\n";
}

// Check AllowOverride
preg_match_all('/AllowOverride\s+(\S+)/', $c, $ao);
echo "AllowOverride values: " . implode(', ', array_unique($ao[1])) . "\n";
