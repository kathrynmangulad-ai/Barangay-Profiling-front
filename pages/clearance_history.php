<?php
/**
 * Barangay Clearance History
 *
 * Lists all Barangay Clearance requests with their current status.
 * Reuses the existing document_requests table — no new tables needed.
 *
 * Access: Staff (admin / secretary) can view all clearances;
 * residents can only view their own.
 */

require_once __DIR__ . '/../config/config.php';
require_staff();

$page_title = 'Barangay Clearance History';
$page_crumb = 'Clearance History';

// Scope: admin sees all barangays; secretary sees only their own barangay.
list($scopeSql, $scopeParams) = scope_barangay('dr');

// Filters
$f_q        = trim($_GET['q'] ?? '');
$f_status   = trim($_GET['status'] ?? '');
$f_barangay = (int)($_GET['barangay'] ?? 0);

// Build the query
$sql = "
    SELECT
        dr.id,
        dr.barangay_id,
        dr.resident_id,
        dr.requestor_name,
        dr.document_type,
        dr.purpose,
        dr.status,
        dr.control_no,
        dr.requested_at,
        dr.released_at,
        b.barangay_name
    FROM document_requests dr
    LEFT JOIN barangays b ON b.id = dr.barangay_id
    WHERE dr.document_type = 'Barangay Clearance' $scopeSql
";

$types = '';
$params = [];

if ($f_q !== '') {
    $sql .= " AND (dr.requestor_name LIKE ? OR dr.purpose LIKE ? OR dr.control_no LIKE ?)";
    $like = "%$f_q%";
    $types .= 'sss';
    $params[] = $like; $params[] = $like; $params[] = $like;
}

if ($f_status !== '') {
    $sql .= " AND dr.status = ?";
    $types .= 's';
    $params[] = $f_status;
}

if ($f_barangay > 0 && is_admin()) {
    $sql .= " AND dr.barangay_id = ?";
    $types .= 'i';
    $params[] = $f_barangay;
}

$sql .= " ORDER BY dr.requested_at DESC, dr.id DESC";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die('Prepare Error: ' . $conn->error);
}

if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$clearances = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Barangay list for the filter dropdown (admin only)
$brgys = null;
if (is_admin()) {
    $brgys = $conn->query('SELECT id, barangay_name FROM barangays ORDER BY barangay_name ASC');
}

$statusOptions = ['Pending', 'Processing', 'Released'];

// Count by status
$statusCounts = [];
foreach ($statusOptions as $s) {
    $statusCounts[$s] = 0;
}
foreach ($clearances as $c) {
    $statusCounts[$c['status']] = ($statusCounts[$c['status']] ?? 0) + 1;
}

include BASE_PATH . '/partials/header.php';
?>
<div class="page">
    <div class="toolbar">
        <h2 style="margin:0">Barangay Clearance History</h2>
        <a class="btn btn--ghost" href="<?= e(url('pages/documents.php')) ?>">← Back to Document Requests</a>
    </div>

    <!-- Status summary -->
    <div class="card table-wrap" style="overflow-x:auto">
        <table style="width:100%; text-align:center">
            <thead>
                <tr>
                    <?php foreach ($statusOptions as $s): ?>
                    <th style="width:33%">
                        <span style="display:block; font-size:9pt; font-weight:600; text-transform:uppercase; letter-spacing:1px; color:#666"><?= e($s) ?></span>
                        <span style="display:block; font-size:16pt; font-weight:800; color:#1a1a1a"><?= (int)($statusCounts[$s] ?? 0) ?></span>
                    </th>
                    <?php endforeach; ?>
                </tr>
            </thead>
        </table>
    </div>

    <!-- Search / filter form -->
    <div class="card table-wrap" style="overflow-x:auto">
        <form method="get">
            <input type="search" name="q" placeholder="Search requestor, purpose, or control no." value="<?= e($f_q) ?>">
            <select name="status">
                <option value="">All Statuses</option>
                <?php foreach ($statusOptions as $s): ?>
                <option value="<?= e($s) ?>"<?= $f_status === $s ? ' selected' : '' ?>><?= e(ucfirst($s)) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if (is_admin() && $brgys): ?>
            <select name="barangay">
                <option value="0">All Barangays</option>
                <?php while ($b = $brgys->fetch_assoc()): ?>
                <option value="<?= (int)$b['id'] ?>"<?= $f_barangay === (int)$b['id'] ? ' selected' : '' ?>><?= e($b['barangay_name']) ?></option>
                <?php endwhile; ?>
            </select>
            <?php endif; ?>
            <button class="btn" type="submit">Filter</button>
            <a class="btn btn--ghost" href="<?= e(url('pages/clearance_history.php')) ?>">Reset</a>
        </form>
    </div>

    <!-- Clearance list -->
    <div class="card table-wrap" style="overflow-x:auto">
        <table style="width:100%">
            <thead>
                <tr>
                    <th style="text-align:left">Control No.</th>
                    <th style="text-align:left">Requestor</th>
                    <th style="text-align:left">Barangay</th>
                    <th style="text-align:center">Status</th>
                    <th style="text-align:center">Requested</th>
                    <th style="text-align:center">Released</th>
                    <th style="text-align:center">Action</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($clearances)): ?>
                <tr>
                    <td colspan="7" style="text-align:center">No Barangay Clearance requests found.</td>
                </tr>
            <?php else: foreach ($clearances as $c): ?>
                <tr>
                    <td style="white-space:nowrap"><strong><?= e($c['control_no'] ?? '-') ?></strong></td>
                    <td><?= e($c['requestor_name']) ?></td>
                    <td><?= e($c['barangay_name'] ?? '-') ?></td>
                    <td style="text-align:center">
                        <span class="badge badge--<?= e($c['status']) ?>"><?= e(ucfirst($c['status'])) ?></span>
                    </td>
                    <td style="white-space:nowrap"><?= e($c['requested_at'] ? date('M j, Y', strtotime($c['requested_at'])) : '-') ?></td>
                    <td style="white-space:nowrap"><?= e($c['released_at'] ? date('M j, Y', strtotime($c['released_at'])) : '-') ?></td>
                    <td style="text-align:center">
                        <?php if ($c['status'] === 'Released'): ?>
                            <a
                                class="btn btn-success btn--ghost"
                                href="<?= e(url('documents/clearance_generate.php?request_id=' . (int)$c['id'])) ?>"
                                target="_blank"
                            >
                                View / Print
                            </a>
                        <?php else: ?>
                            <span class="muted-meta">Pending release</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include BASE_PATH . '/partials/footer.php'; ?>