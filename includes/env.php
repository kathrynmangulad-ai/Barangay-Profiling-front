<?php
/**
 * Minimal .env loader (no Composer dependency).
 *
 * Reads KEY=value pairs from a .env file in the project root and exposes them
 * through the env() helper (and $_ENV / getenv()). Values already present in
 * the real environment are NOT overwritten, so server-level config wins.
 *
 * Supported syntax:
 *   KEY=value
 *   KEY="quoted value"      (double or single quotes are stripped)
 *   # full line comments and blank lines are ignored
 *   export KEY=value        (leading "export " is tolerated)
 */

if (!function_exists('env_load')) {
    function env_load($path) {
        if (!is_file($path) || !is_readable($path)) {
            return false;
        }
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return false;
        }
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            if (strncmp($line, 'export ', 7) === 0) {
                $line = ltrim(substr($line, 7));
            }
            $eq = strpos($line, '=');
            if ($eq === false) {
                continue;
            }
            $key = trim(substr($line, 0, $eq));
            $val = trim(substr($line, $eq + 1));

            if ($key === '') {
                continue;
            }

            // Strip a single pair of surrounding quotes, if present.
            $len = strlen($val);
            if ($len >= 2) {
                $first = $val[0];
                $last  = $val[$len - 1];
                if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                    $val = substr($val, 1, -1);
                }
            }

            // Do not override values already set in the real environment.
            if (getenv($key) !== false || array_key_exists($key, $_ENV)) {
                continue;
            }

            putenv($key . '=' . $val);
            $_ENV[$key]    = $val;
            $_SERVER[$key] = $val;
        }
        return true;
    }
}

if (!function_exists('env')) {
    /**
     * Read a configuration value from the environment, with a default.
     * Recognises common boolean/null spellings for convenience.
     */
    function env($key, $default = null) {
        $val = getenv($key);
        if ($val === false) {
            $val = $_ENV[$key] ?? null;
        }
        if ($val === null || $val === false) {
            return $default;
        }
        switch (strtolower((string)$val)) {
            case 'true':  return true;
            case 'false': return false;
            case 'null':  return null;
            case '':      return $default;
        }
        return $val;
    }
}
