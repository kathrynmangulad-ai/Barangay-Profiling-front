<?php
/**
 * Document Logos: the images printed on a barangay's certificates.
 *
 * A secretary manages the logos of their own barangay only. An administrator
 * may open any barangay with ?barangay_id= (for support); there is no menu
 * entry for that. Storage, validation and the built-in fallbacks live in
 * includes/logos.php; includes/certificate.php reads them when it prints.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/logos.php';
require_role(['admin', 'secretary']);

$page_title    = 'Document Logos';
$page_crumb    = 'Document Logos';
$page_subtitle = 'The seals and logos printed on the certificates your barangay issues.';

$myBrgy   = current_barangay_id();
$viewBrgy = $myBrgy;
if (is_admin()) {
    $pick = (int)($_GET['barangay_id'] ?? ($_POST['barangay_id'] ?? 0));
    if ($pick > 0) { $viewBrgy = $pick; }
    if ($viewBrgy === null) {
        // An administrator has no barangay of their own: start on the first one.
        $first = $conn->query('SELECT id FROM barangays ORDER BY barangay_name LIMIT 1');
        $viewBrgy = $first ? (int)($first->fetch_assoc()['id'] ?? 0) : 0;
    }
}
if ($viewBrgy === null || $viewBrgy <= 0) {
    deny_access('Your account is not assigned to a barangay yet.');
}
$viewBrgy = (int)$viewBrgy;

$brgyName = '';
if ($b = $conn->prepare('SELECT barangay_name FROM barangays WHERE id = ? LIMIT 1')) {
    $b->bind_param('i', $viewBrgy);
    $b->execute();
    $brgyName = (string)($b->get_result()->fetch_assoc()['barangay_name'] ?? '');
    $b->close();
}
if ($brgyName === '') {
    deny_access('That barangay does not exist.');
}
$slug  = logo_slug($brgyName);
$slots = logo_slots();
$self  = 'secretary/document_logos.php' . (is_admin() ? '?barangay_id=' . $viewBrgy : '');

/* ---- Upload / remove ----------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // A request bigger than post_max_size arrives with no fields at all, so the
    // security token is missing too: explain the real problem instead.
    if (empty($_POST) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        flash('err', 'That file is too large for this server to accept.');
        redirect(url($self));
    }
    require_csrf();
    $slot   = (string)($_POST['slot'] ?? '');
    $action = (string)($_POST['action'] ?? '');

    if (!isset($slots[$slot])) {
        flash('err', 'Unknown logo.');
    } elseif ($action === 'upload') {
        $problem = logo_save_upload($_FILES['logo'] ?? [], $viewBrgy, $slot);
        if ($problem === null) {
            log_access('logo_upload_' . $slot, 'barangay', $viewBrgy);
            flash('ok', $slots[$slot]['label'] . ' saved. New certificates use it from now on.');
        } else {
            flash('err', $problem);
        }
    } elseif ($action === 'remove') {
        if (logo_remove_upload($viewBrgy, $slot)) {
            log_access('logo_remove_' . $slot, 'barangay', $viewBrgy);
            flash('ok', $slots[$slot]['label'] . ' removed.');
        }
    }
    redirect(url($self));
}

/* ---- Current state of every slot ---------------------------------------- */
$logos = [];
foreach ($slots as $key => $_) {
    $logos[$key] = brgy_logo($viewBrgy, $slug, $key);
}
$brgyTitle = ucwords(mb_strtolower($brgyName, 'UTF-8'), ' -.');
$max_mb    = rtrim(rtrim(number_format(logo_max_bytes() / 1048576, 1), '0'), '.');
// What the certificate actually shows at the top right and as the watermark.
$headSeal  = $logos['seal'] ?: $logos['watermark'];
$watermark = $logos['watermark'] ?: ($logos['seal'] ?: $logos['municipal']);

$allBarangays = is_admin() ? $conn->query('SELECT id, barangay_name FROM barangays ORDER BY barangay_name') : null;

include BASE_PATH . '/partials/header.php';
?>

<?php if ($allBarangays): ?>
<div class="toolbar">
    <form method="get">
        <label class="sr-only" for="barangay_id">Barangay</label>
        <select id="barangay_id" name="barangay_id">
            <?php while ($bb = $allBarangays->fetch_assoc()): ?>
                <option value="<?= (int)$bb['id'] ?>"<?= $viewBrgy === (int)$bb['id'] ? ' selected' : '' ?>><?= e($bb['barangay_name']) ?></option>
            <?php endwhile; ?>
        </select>
        <button class="btn" type="submit">View</button>
    </form>
</div>
<?php endif; ?>

<div class="logos-layout">
    <div class="logo-grid">
        <?php foreach ($slots as $key => $slot): $cur = $logos[$key]; ?>
        <section class="card logo-card" aria-labelledby="logo-<?= e($key) ?>">
            <div class="logo-card__preview<?= $cur ? '' : ' logo-card__preview--empty' ?>">
                <?php if ($cur): ?>
                    <img src="<?= e($cur['url']) ?>" alt="Current <?= e(strtolower($slot['label'])) ?>">
                <?php else: ?>
                    <span><?= icon('image') ?> No image yet</span>
                <?php endif; ?>
            </div>
            <div class="logo-card__body">
                <div class="logo-card__title">
                    <h2 id="logo-<?= e($key) ?>"><?= e($slot['label']) ?></h2>
                    <?php if (!$cur): ?>
                        <span class="badge badge--pending">Missing</span>
                    <?php elseif ($cur['source'] === 'uploaded'): ?>
                        <span class="badge badge--released">Uploaded</span>
                    <?php else: ?>
                        <span class="badge badge--neutral">Built-in</span>
                    <?php endif; ?>
                </div>
                <p class="logo-card__where"><?= e($slot['where']) ?></p>

                <form method="post" enctype="multipart/form-data" class="logo-card__form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="upload">
                    <input type="hidden" name="slot" value="<?= e($key) ?>">
                    <?php if (is_admin()): ?><input type="hidden" name="barangay_id" value="<?= $viewBrgy ?>"><?php endif; ?>
                    <label class="sr-only" for="file-<?= e($key) ?>">Image for <?= e(strtolower($slot['label'])) ?></label>
                    <input id="file-<?= e($key) ?>" type="file" name="logo" accept="image/png,image/jpeg,image/webp,image/gif" required>
                    <button class="btn" type="submit"><?= icon('upload') ?> <?= $cur && $cur['source'] === 'uploaded' ? 'Replace' : 'Upload' ?></button>
                </form>
                <p class="field-hint"><?= e($slot['tip']) ?></p>

                <?php if ($cur && $cur['source'] === 'uploaded'): ?>
                <form method="post" class="logo-card__remove"
                      data-confirm="Remove this image? <?= logo_builtin_rel($slug, $key) !== null && is_file(BASE_PATH . '/assets/img/' . logo_builtin_rel($slug, $key)) ? 'Certificates will go back to the built-in one.' : 'Certificates will print without it until you upload another.' ?>"
                      data-confirm-title="Remove <?= e(strtolower($slot['label'])) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="remove">
                    <input type="hidden" name="slot" value="<?= e($key) ?>">
                    <?php if (is_admin()): ?><input type="hidden" name="barangay_id" value="<?= $viewBrgy ?>"><?php endif; ?>
                    <button class="btn-delete" type="submit">Remove my upload</button>
                </form>
                <?php endif; ?>
            </div>
        </section>
        <?php endforeach; ?>
    </div>

    <aside class="card logo-sheet" aria-label="How the logos appear on a certificate">
        <h2 class="logo-sheet__title">On the certificate</h2>
        <p class="logo-sheet__sub">A rough picture of where each image goes.</p>
        <div class="logo-sheet__page">
            <?php if ($watermark): ?><img class="logo-sheet__mark" src="<?= e($watermark['url']) ?>" alt=""><?php endif; ?>
            <div class="logo-sheet__band"></div>
            <div class="logo-sheet__head">
                <span class="logo-sheet__left">
                    <?php if ($logos['municipal']): ?><img src="<?= e($logos['municipal']['url']) ?>" alt=""><?php endif; ?>
                    <?php if ($logos['bagong']): ?><img src="<?= e($logos['bagong']['url']) ?>" alt=""><?php endif; ?>
                </span>
                <span class="logo-sheet__text">
                    <span>Republic of the Philippines</span>
                    <span>Province of Cagayan</span>
                    <span>Municipality of Sto. Niño</span>
                    <strong>Barangay <?= e($brgyTitle) ?></strong>
                </span>
                <span class="logo-sheet__right">
                    <?php if ($headSeal): ?><img src="<?= e($headSeal['url']) ?>" alt=""><?php endif; ?>
                </span>
            </div>
            <div class="logo-sheet__bar">OFFICE OF THE PUNONG BARANGAY</div>
            <div class="logo-sheet__lines" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i></div>
        </div>
        <p class="field-hint">Accepted: PNG, JPG, WebP or GIF, up to <?= e($max_mb) ?> MB. A PNG with a transparent background prints best. Open a released certificate to see the real result.</p>
    </aside>
</div>

<?php include BASE_PATH . '/partials/footer.php'; ?>
