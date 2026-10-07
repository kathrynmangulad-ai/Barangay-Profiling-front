<?php
// Syntax check helper
$file = __DIR__ . '/../admin/users.php';
$content = file_get_contents($file);
// Basic syntax validation by checking for common errors
if (strpos($content, '<?php') === false) {
    echo "ERROR: No opening PHP tag\n";
    exit(1);
}
// Check for balanced braces
$open = substr_count($content, '{');
$close = substr_count($content, '}');
if ($open !== $close) {
    echo "ERROR: Unbalanced braces: $open open, $close close\n";
    exit(1);
}
// Check for balanced parentheses
$open_p = substr_count($content, '(');
$close_p = substr_count($content, ')');
if ($open_p !== $close_p) {
    echo "ERROR: Unbalanced parentheses: $open_p open, $close_p close\n";
    exit(1);
}
echo "OK: File syntax appears valid\n";
echo "Lines: " . count(file($file)) . "\n";
