<?php






















$files = glob(__DIR__ . '/*.php');
if (!$files) { fwrite(STDERR, "No files found\n"); exit(2); }

$problems = [];

foreach ($files as $file) {
    $name = basename($file);
    $src  = file_get_contents($file);
    if ($src === false) { continue; }

    $tokens = token_get_all($src);
    $n      = count($tokens);

    for ($i = 0; $i < $n; $i++) {
        $t = $tokens[$i];
        if (!is_array($t) || $t[0] !== T_STRING || strtolower($t[1]) !== 'bind_param') {
            continue;
        }

        $line = $t[2];

         
        $j = $i + 1;
        while ($j < $n && is_array($tokens[$j]) && in_array($tokens[$j][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) { $j++; }
        if ($j >= $n || $tokens[$j] !== '(') { continue; }

         
        $args   = [];
        $depth  = 0;
        $cur    = [];
        $k      = $j;
        for (; $k < $n; $k++) {
            $tok = $tokens[$k];
            $txt = is_array($tok) ? $tok[0] : $tok;

            if ($txt === '(' || $txt === '[') { $depth++; if ($depth === 1) { continue; } }
            if ($txt === ')' || $txt === ']') {
                $depth--;
                if ($depth === 0) { $args[] = $cur; break; }
            }
            if ($txt === ',' && $depth === 1) { $args[] = $cur; $cur = []; continue; }
            $cur[] = $tok;
        }
        if (!$args) { continue; }

         
        $typeToks = array_values(array_filter($args[0], function ($x) {
            return !(is_array($x) && in_array($x[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true));
        }));

        $typeLen  = null;
        $isDynamic = false;
        foreach ($typeToks as $tt) {
            if (is_array($tt) && $tt[0] === T_CONSTANT_ENCAPSED_STRING) {
                $lit = trim($tt[1], "'\"");
                if (preg_match('/^[is]*$/', $lit)) { $typeLen = strlen($lit); }
            } elseif (is_array($tt) && $tt[0] === T_STRING && strtolower($tt[1]) === 'str_repeat') {
                $isDynamic = true;
            }
        }

         
        $values = array_slice($args, 1);
        foreach ($values as $vi => $vt) {
            $sig = array_values(array_filter($vt, function ($x) {
                return !(is_array($x) && in_array($x[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true));
            }));
            if (!$sig) { continue; }

            $first = $sig[0];
            $desc  = '';

            if (is_array($first) && $first[0] === T_LNUMBER) {
                $desc = 'numeric literal';
            } elseif (is_array($first) && $first[0] === T_DNUMBER) {
                $desc = 'float literal';
            } elseif (is_array($first) && $first[0] === T_CONSTANT_ENCAPSED_STRING) {
                $desc = 'string literal';
            } elseif (is_array($first) && $first[0] === T_STRING) {
                 
                $desc = 'function result (' . $first[1] . ')';
            } elseif (is_array($first) && $first[0] === T_DOUBLE_CAST) {
                $desc = '(double) cast';
            } elseif (is_array($first) && $first[0] === T_ARRAY_CAST) {
                $desc = '(array) cast';
            } elseif (is_array($first) && $first[0] === T_OBJECT_CAST) {
                $desc = '(object) cast';
            } elseif (is_array($first) && $first[0] === T_INT_CAST) {
                $desc = '(int) cast';
            } elseif (is_array($first) && $first[0] === T_STRING_CAST) {
                $desc = '(string) cast';
            } elseif (is_array($first) && $first[0] === T_BOOL_CAST) {
                $desc = '(bool) cast';
            } elseif (is_array($first) && $first[0] === T_UNSET_CAST) {
                $desc = '(unset) cast';
            } elseif (is_array($first) && $first[0] === T_VARIABLE) {
                $desc = '';    
            }

            if ($desc !== '') {
                $problems[] = "$name:$line argument " . ($vi + 2) . ": $desc cannot be bound by reference";
            }
        }

        if (!$isDynamic && $typeLen !== null && $typeLen !== count($values)) {
            $problems[] = "$name:$line arity mismatch: '$typeLen' declares $typeLen values but " . count($values) . " given";
        }

        $i = $k;
    }
}

if ($problems) {
    echo 'PROBLEMS FOUND (' . count($problems) . "):\n";
    foreach ($problems as $p) { echo "  - $p\n"; }
    exit(1);
}
echo "OK - scanned " . count($files) . " files; no bind_param() problems.\n";
exit(0);
