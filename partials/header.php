<?php








require_login();

$current    = basename($_SERVER['PHP_SELF'] ?? '');
$is_admin   = (($_SESSION['role'] ?? '') === 'admin');
$is_resident = (($_SESSION['role'] ?? '') === 'resident');
$full_name  = $_SESSION['full_name'] ?? 'User';
$role_raw   = $_SESSION['role'] ?? 'user';


$role_label = function_exists('role_label') ? role_label($role_raw) : ($is_admin ? 'System Administrator' : ucfirst($role_raw));



$home_href  = $is_resident ? url('resident/resident_dashboard.php') : url('pages/dashboard.php');

$initials = '';
foreach (preg_split('/\s+/', trim($full_name)) as $part) {
    if ($part !== '' && preg_match('/\p{L}/u', $part, $m)) { $initials .= strtoupper($m[0]); }
}
$initials = substr($initials, 0, 2);
if ($initials === '') { $initials = 'U'; }
















$avatar_photo = '';
if ($is_resident && isset($conn) && $conn instanceof mysqli) {
    $pq = $conn->prepare('SELECT photo FROM residents WHERE user_id = ? LIMIT 1');
    if ($pq) {
        $avUid = (int)($_SESSION['user_id'] ?? 0);
        $pq->bind_param('i', $avUid);
        $pq->execute();
        $prow = $pq->get_result()->fetch_assoc();
        $pq->close();
        $cand = trim((string)($prow['photo'] ?? ''));
        if ($cand !== '' && @file_exists(dirname(__DIR__) . '/' . $cand)) {
            $avatar_photo = $cand;
        }
    }
}

if (!function_exists('icon')) {
    function icon($name, $class = 'ico') {
        static $paths = [
            'home'           => '<path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>',
            'users'          => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
            'user-plus'      => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" x2="19" y1="8" y2="14"/><line x1="22" x2="16" y1="11" y2="11"/>',
            'file-text'      => '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7z"/><polyline points="14 2 14 8 20 8"/><line x1="16" x2="8" y1="13" y2="13"/><line x1="16" x2="8" y1="17" y2="17"/>',
            'clipboard'      => '<rect width="8" height="4" x="8" y="2" rx="1" ry="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/>',
            'receipt'        => '<path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1Z"/><line x1="8" x2="16" y1="8" y2="8"/><line x1="8" x2="16" y1="12" y2="12"/>',
            'map'            => '<path d="M15 5.764v15"/><path d="M9 3.236v15"/><path d="M14.106 5.553a2 2 0 0 0 1.788 0l3.659-1.83A1 1 0 0 1 21 4.619v12.764a1 1 0 0 1-.553.894l-4.553 2.277a2 2 0 0 1-1.788 0l-4.212-2.106a2 2 0 0 0-1.788 0l-3.659 1.83A1 1 0 0 1 3 19.381V6.618a1 1 0 0 1 .553-.894l4.553-2.277a2 2 0 0 1 1.788 0z"/>',
            'shield'         => '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/>',
            'log-out'        => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" x2="9" y1="12" y2="12"/>',
            'menu'           => '<line x1="4" x2="20" y1="6" y2="6"/><line x1="4" x2="20" y1="12" y2="12"/><line x1="4" x2="20" y1="18" y2="18"/>',
            'bell'           => '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/>',
            'search'         => '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
            'chevron-down'   => '<path d="m6 9 6 6 6-6"/>',
            'chevrons-left'  => '<path d="m11 17-5-5 5-5"/><path d="m18 17-5-5 5-5"/>',
            'arrow-right'    => '<line x1="5" x2="19" y1="12" y2="12"/><polyline points="12 5 19 12 12 19"/>',
            'plus'           => '<line x1="12" x2="12" y1="5" y2="19"/><line x1="5" x2="19" y1="12" y2="12"/>',
            'alert-triangle' => '<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/>',
            'alert-circle'   => '<circle cx="12" cy="12" r="10"/><line x1="12" x2="12" y1="8" y2="12"/><line x1="12" x2="12.01" y1="16" y2="16"/>',
            'check-circle'   => '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>',
            'x'              => '<line x1="18" x2="6" y1="6" y2="18"/><line x1="6" x2="18" y1="6" y2="18"/>',
            'house'          => '<path d="M15 21v-8a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v8"/><path d="M3 10a2 2 0 0 1 .709-1.528l7-5.999a2 2 0 0 1 2.582 0l7 5.999A2 2 0 0 1 21 10v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>',
            'accessibility'  => '<circle cx="16" cy="4" r="1"/><path d="m18 19 1-7-6 1"/><path d="m5 8 3-3 5.5 3-2.36 3.5"/><path d="M4.24 14.5a5 5 0 0 0 6.88 6"/><path d="M13.76 17.5a5 5 0 0 0-6.88-6"/>',
            'graduation-cap' => '<path d="M21.42 10.922a1 1 0 0 0-.019-1.838L12.83 5.18a2 2 0 0 0-1.66 0L2.6 9.08a1 1 0 0 0 0 1.832l8.57 3.908a2 2 0 0 0 1.66 0z"/><path d="M22 10v6"/><path d="M6 12.5V16a6 3 0 0 0 12 0v-3.5"/>',
            'file-plus'      => '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7z"/><polyline points="14 2 14 8 20 8"/><line x1="12" x2="12" y1="12" y2="18"/><line x1="9" x2="15" y1="15" y2="15"/>',
            'clock'          => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
            'activity'       => '<path d="M22 12h-4l-3 9L9 3l-3 9H2"/>',
            'trending-up'    => '<polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/><polyline points="16 7 22 7 22 13"/>',
            'minus'          => '<line x1="5" x2="19" y1="12" y2="12"/>',
            'inbox'          => '<polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/>',
            'user'           => '<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
            'male'           => '<circle cx="10" cy="14" r="5.5"/><line x1="14.2" y1="9.8" x2="20.5" y2="3.5"/><polyline points="15 3.5 20.5 3.5 20.5 9"/>',
            'female'         => '<circle cx="12" cy="8.5" r="5.5"/><line x1="12" y1="14" x2="12" y2="21"/><line x1="8.5" y1="18" x2="15.5" y2="18"/>',
        ];
        $path = $paths[$name] ?? '';
        return '<svg class="'.$class.'" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'.$path.'</svg>';
    }
}













if ($is_resident) {
    $nav_groups = [
        'MY ACCOUNT' => [
            ['file' => 'resident/resident_dashboard.php', 'label' => 'My Dashboard', 'icon' => 'home'],
            ['file' => 'resident/resident_profile.php',   'label' => 'My Profile',  'icon' => 'user'],
        ],
        'REQUESTS' => [
            ['file' => 'resident/document_request.php', 'label' => 'Request Document', 'icon' => 'file-plus'],
            ['file' => 'resident/blotter_request.php',  'label' => 'File a Blotter',  'icon' => 'alert-triangle'],
        ],
    ];
} else {
    $nav_groups = [
        'MAIN' => [
            ['file' => 'pages/dashboard.php',    'label' => 'Dashboard',    'icon' => 'home'],
            ['file' => 'pages/residents.php',    'label' => 'Residents',    'icon' => 'users'],
            ['file' => 'pages/resident_add.php', 'label' => 'New Resident', 'icon' => 'user-plus'],
        ],
        'MANAGEMENT' => [
            ['file' => 'pages/documents.php', 'label' => 'Document Requests',          'icon' => 'file-text'],
            ['file' => 'pages/blotters.php',  'label' => 'Blotter / Incident Records', 'icon' => 'clipboard'],
        ],
    ];
    if ($is_admin) {
        $nav_groups['ADMINISTRATION'] = [
            ['file' => 'admin/barangays.php', 'label' => 'Barangays', 'icon' => 'map'],
            ['file' => 'admin/users.php',     'label' => 'Users',     'icon' => 'shield'],
        ];
    } else {
        $nav_groups['MY RESIDENTS'] = [
            ['file' => 'secretary/resident_accounts.php', 'label' => 'Resident Accounts', 'icon' => 'shield'],
        ];
    }
}

 
$pending_notice = 0;
if ($is_resident) {
    

    $rid = current_resident_id();
    if ($rid !== null) {
        if ($nq = $conn->prepare("SELECT COUNT(*) c FROM document_requests WHERE resident_id=? AND status IN ('Pending','Processing')")) {
            $nq->bind_param('i', $rid);
            $nq->execute();
            $pending_notice = (int)$nq->get_result()->fetch_assoc()['c'];
        }
    }
} elseif ($is_admin) {
    if ($nq = $conn->query("SELECT COUNT(*) c FROM document_requests WHERE status='Pending'")) {
        $pending_notice = (int)$nq->fetch_assoc()['c'];
    }
} else {
    if ($nq = $conn->prepare("SELECT COUNT(*) c FROM document_requests WHERE status='Pending' AND barangay_id=?")) {
        $nq->bind_param('i', $_SESSION['barangay_id']);
        $nq->execute();
        $pending_notice = (int)$nq->get_result()->fetch_assoc()['c'];
    }
}

$crumb = $page_crumb ?? ($page_title ?? 'Overview');
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#075B6B">
<title><?= e(($page_title ?? 'Barangay System')) ?> · Sto. Niño Barangay Management System</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
</head>
<body>
<div class="app">
    <aside class="sidebar" id="sidebar" aria-label="Primary navigation">
        <div class="sidebar__brand">
            <img class="sidebar__logo" src="<?= e(asset('img/logo.png')) ?>" alt="Sto. Niño Barangay logo">
            <span class="sidebar__brand-text">
                <span class="sidebar__system">Barangay Management System</span>
                <span class="sidebar__barangay">Sto. Niño</span>
            </span>
            <button class="sidebar__collapse" type="button" data-sidebar-collapse aria-label="Collapse sidebar" title="Collapse sidebar"><?= icon('chevrons-left', 'ico ico--sm') ?></button>
        </div>

        <nav class="sidebar__nav" aria-label="Sections">
            <?php foreach ($nav_groups as $group => $items): ?>
                <p class="sidebar__label"><?= e($group) ?></p>
                <?php foreach ($items as $item): $active = ($current === basename($item['file'])); ?>
                    <a class="nav-link<?= $active ? ' is-active' : '' ?>" href="<?= e(url($item['file'])) ?>"<?= $active ? ' aria-current="page"' : '' ?> title="<?= e($item['label']) ?>">
                        <span class="nav-link__ico"><?= icon($item['icon']) ?></span>
                        <span class="nav-link__txt"><?= e($item['label']) ?></span>
                    </a>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </nav>

        <div class="sidebar__foot">
            <div class="sidebar__user">
                <span class="avatar" aria-hidden="true"><?= e($initials) ?></span>
                <span class="sidebar__user-meta">
                    <span class="sidebar__user-name"><?= e($full_name) ?></span>
                    <span class="sidebar__user-role"><?= e($role_label) ?></span>
                </span>
            </div>
            <a class="nav-link nav-link--logout" href="<?= e(url('auth/logout.php')) ?>" title="Logout">
                <span class="nav-link__ico"><?= icon('log-out') ?></span>
                <span class="nav-link__txt">Logout</span>
            </a>
        </div>
    </aside>

    <div class="sidebar-backdrop" data-sidebar-backdrop hidden></div>

    <main class="main">
        <header class="topbar">
            <button class="icon-btn topbar__toggle" type="button" data-sidebar-toggle aria-label="Toggle navigation" aria-controls="sidebar" aria-expanded="false"><?= icon('menu') ?></button>

            <nav class="crumbs" aria-label="Breadcrumb">
                <a class="crumbs__root" href="<?= e($home_href) ?>">Dashboard</a>
                <span class="crumbs__sep" aria-hidden="true">/</span>
                <span class="crumbs__current" aria-current="page"><?= e($crumb) ?></span>
            </nav>

            <?php
            



            $search_paths = [
                'residents.php' => 'pages/residents.php',
                'documents.php' => 'pages/documents.php',
                'blotters.php'  => 'pages/blotters.php',
                'barangays.php' => 'admin/barangays.php',
                'users.php'     => 'admin/users.php',
            ];
            $search_labels = ['residents.php' => 'Search residents', 'documents.php' => 'Search documents', 'blotters.php' => 'Search blotters', 'barangays.php' => 'Search barangay', 'users.php' => 'Search users']; 
            $search_label = $search_labels[$current] ?? 'Search residents';
            if (isset($search_paths[$current])): ?>
            <form class="topbar__search" role="search" method="get" action="<?= e(url($search_paths[$current])) ?>">
                <?php if ($current === 'users.php' && isset($_GET['tab'])): ?><input type="hidden" name="tab" value="<?= e($_GET['tab']) ?>"><?php endif; ?>
                <span class="topbar__search-ico" aria-hidden="true"><?= icon('search', 'ico ico--sm') ?></span>
                <label class="sr-only" for="global-search"><?= e($search_label) ?></label>
                <input id="global-search" type="search" name="q" placeholder="<?= e($search_label) ?>…" value="<?= e($_GET['q'] ?? '') ?>" autocomplete="off">
            </form>
            <?php endif; ?>

            <div class="topbar__actions">
                <a class="icon-btn" href="<?= e($is_resident ? url('resident/resident_profile.php') : url('pages/documents.php')) ?>" title="Pending document requests" aria-label="Notifications: <?= (int)$pending_notice ?> pending document requests">
                    <?= icon('bell') ?>
                    <?php if ($pending_notice > 0): ?><span class="icon-btn__badge"><?= (int)$pending_notice ?></span><?php endif; ?>
                </a>

                <div class="dropdown" data-dropdown>
                    <button class="profile-btn" type="button" data-dropdown-toggle aria-haspopup="true" aria-expanded="false">
                        <span class="avatar avatar--sm" aria-hidden="true"><?php if ($avatar_photo !== ''): ?><img src="<?= e(url($avatar_photo)) ?>" alt=""><?php else: ?><?= e($initials) ?><?php endif; ?></span>
                        <span class="profile-btn__meta">
                            <span class="profile-btn__name"><?= e($full_name) ?></span>
                            <span class="profile-btn__role"><?= e($role_label) ?></span>
                        </span>
                        <span class="profile-btn__caret" aria-hidden="true"><?= icon('chevron-down', 'ico ico--sm') ?></span>
                    </button>
                    <div class="dropdown__menu" data-dropdown-menu role="menu" hidden>
                        <div class="dropdown__head">
                            <span class="dropdown__name"><?= e($full_name) ?></span>
                            <span class="dropdown__role"><?= e($role_label) ?></span>
                        </div>
                        <a class="dropdown__item" href="<?= e($home_href) ?>" role="menuitem"><?= icon('home', 'ico ico--sm') ?> Dashboard</a>
                        <?php if ($is_resident): ?>
                            <a class="dropdown__item" href="<?= e(url('resident/resident_profile.php')) ?>" role="menuitem"><?= icon('user', 'ico ico--sm') ?> My Profile</a>
                        <?php else: ?>
                            <a class="dropdown__item" href="<?= e(url('pages/residents.php')) ?>" role="menuitem"><?= icon('users', 'ico ico--sm') ?> Residents</a>
                            <?php if ($is_admin): ?>
                                <a class="dropdown__item" href="<?= e(url('admin/users.php')) ?>" role="menuitem"><?= icon('shield', 'ico ico--sm') ?> Manage Users</a>
                            <?php endif; ?>
                        <?php endif; ?>
                        <div class="dropdown__sep"></div>
                        <a class="dropdown__item dropdown__item--danger" href="<?= e(url('auth/logout.php')) ?>" role="menuitem"><?= icon('log-out', 'ico ico--sm') ?> Logout</a>
                    </div>
                </div>
            </div>
        </header>

        <div class="page">
