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
<meta name="theme-color" content="#192C48">
<?php require_once __DIR__ . '/theme.php'; ?>
<?= bms_theme_boot_script(0) ?>
<title><?= e($page_title ?? 'Sign in') ?> · Sto. Niño Barangay Management System</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
</head>
<body class="auth-body">
<main class="auth">
    <section class="auth__brand">
        <div class="auth__shapes" aria-hidden="true"><span></span><span></span><span></span><span></span><span></span><span></span></div>

        <div class="auth__brand-top">
            <img class="auth__logo" src="<?= e(asset('img/logo.png')) ?>" alt="Sto. Niño barangay logo">
            <div>
                <p class="auth__title">Sto. Niño</p>
                <p class="auth__tagline">Barangay Management System</p>
            </div>
        </div>

        <div class="auth__brand-mid">
            <h2 class="auth__headline"><span>One workspace</span><br>for every barangay record</h2>
            <p class="auth__desc">A secure workspace for resident records, document requests and blotter/incident reports.</p>
            <ul class="auth__features">
                <li class="auth__feature">
                    <span class="auth__feature-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></span>
                    <span><span class="auth__feature-label">Resident and household records</span><span class="auth__feature-desc">Profiles, households and classifications for every barangay.</span></span>
                </li>
                <li class="auth__feature">
                    <span class="auth__feature-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7z"/><polyline points="14 2 14 8 20 8"/><line x1="16" x2="8" y1="13" y2="13"/><line x1="16" x2="8" y1="17" y2="17"/></svg></span>
                    <span><span class="auth__feature-label">Document requests</span><span class="auth__feature-desc">Clearances and certificates, from request to release.</span></span>
                </li>
                <li class="auth__feature">
                    <span class="auth__feature-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect width="8" height="4" x="8" y="2" rx="1" ry="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/></svg></span>
                    <span><span class="auth__feature-label">Blotter and incident reports</span><span class="auth__feature-desc">Reports filed, recorded and receipted in one place.</span></span>
                </li>
                <li class="auth__feature">
                    <span class="auth__feature-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/><polyline points="16 7 22 7 22 13"/></svg></span>
                    <span><span class="auth__feature-label">Yearly reports</span><span class="auth__feature-desc">Population and service figures per barangay, per year.</span></span>
                </li>
            </ul>
        </div>

        <p class="auth__brand-foot">&copy; <?= date('Y') ?> Sto. Niño Barangay Management System</p>
    </section>
    <section class="auth__panel">
        <?php /* The form sits in one card: logo and place name on top, then the heading. */ ?>
        <div class="auth__card">
        <img class="auth__card-logo" src="<?= e(asset('img/logo.png')) ?>" alt="">
        <span class="auth__pill">Sto. Niño</span>
        <h1 class="auth__heading"><?= e($auth_heading) ?></h1>
        <?php if ($auth_sub !== ''): ?>
            <p class="auth__sub"><?= e($auth_sub) ?></p>
        <?php endif; ?>
