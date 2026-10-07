<?php











$auth_heading = $auth_heading ?? 'Sign in';
$auth_sub     = $auth_sub ?? '';

 
if (!function_exists('auth_eye_button')) {
    function auth_eye_button($target) {
        $t = e($target);
        return '<button class="affix-btn" type="button" data-password-toggle="#' . $t . '"'
             . ' aria-label="Show password" aria-pressed="false" title="Show password">'
             . '<svg class="affix-btn__ico affix-btn__ico--show" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"'
             . ' fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
             . '<path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"></path><circle cx="12" cy="12" r="3"></circle></svg>'
             . '<svg class="affix-btn__ico affix-btn__ico--hide" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"'
             . ' fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
             . '<path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"></path>'
             . '<path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"></path>'
             . '<path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"></path>'
             . '<line x1="2" x2="22" y1="2" y2="22"></line></svg>'
             . '</button>';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#075B6B">
<title><?= e($page_title ?? 'Sign in') ?> · Sto. Niño Barangay Management System</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
</head>
<body class="auth-body">
<main class="auth">
    <section class="auth__brand">
        <img class="auth__logo" src="<?= e(asset('img/logo.png')) ?>" alt="Sto. Niño barangay logo">
        <p class="auth__title">Sto. Niño</p>
        <p class="auth__tagline">Barangay Management System</p>
        <p class="auth__desc">A secure workspace for resident records, document requests and blotter/incident reports.</p>
    </section>
    <section class="auth__panel">
        <h1 class="auth__heading"><?= e($auth_heading) ?></h1>
        <?php if ($auth_sub !== ''): ?>
            <p class="auth__sub"><?= e($auth_sub) ?></p>
        <?php endif; ?>
