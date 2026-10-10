<?php

require_once __DIR__ . '/../config/config.php';
require_staff();

$page_title = 'Document Requests';


 
 
 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    require_csrf();

     
    if ($_SESSION['role'] === 'admin') {
        $barangay_id = (int)($_POST['barangay_id'] ?? 0);
    } else {
         
        $barangay_id = (int)($_SESSION['barangay_id'] ?? 0);
    }

    $requestor_name = trim($_POST['requestor_name'] ?? '');
    $document_type  = trim($_POST['document_type'] ?? '');
    $purpose        = trim($_POST['purpose'] ?? '');
    $notes          = trim($_POST['notes'] ?? '');


     
    if (
        $barangay_id > 0 &&
        $requestor_name !== '' &&
        $document_type !== '' &&
        $purpose !== ''
    ) {

        $stmt = $conn->prepare("
            INSERT INTO document_requests
            (
                barangay_id,
                requestor_name,
                document_type,
                purpose,
                notes,
                status
            )
            VALUES (?, ?, ?, ?, ?, 'Pending')
        ");

        if ($stmt === false) {
            die("Prepare Error: " . $conn->error);
        }

        $stmt->bind_param(
            "issss",
            $barangay_id,
            $requestor_name,
            $document_type,
            $purpose,
            $notes
        );

        if ($stmt->execute()) {

            $stmt->close();

            header("Location: " . url('pages/documents.php?saved=1'));
            exit;

        } else {

            $error = "Failed to save request: " . $stmt->error;

            $stmt->close();
        }

    } else {

        $error = "Please fill in all required fields.";
    }
}


 
 
 

$barangays = $conn->query("
    SELECT id, barangay_name
    FROM barangays
    ORDER BY barangay_name ASC
");

if ($barangays === false) {
    die("Barangay Query Error: " . $conn->error);
}


 
 
 






list($scopeSql, $scopeParams) = scope_barangay('dr');

 
$f_q        = trim($_GET['q'] ?? '');
$f_document = trim($_GET['document'] ?? '');
$f_barangay = (int)($_GET['barangay'] ?? 0);
$f_purpose  = trim($_GET['purpose'] ?? '');
// Tab: '' = everything, 'unreleased' = Pending + Processing, 'released' = Released.
$f_tab = trim($_GET['tab'] ?? '');
if (!in_array($f_tab, ['unreleased', 'released'], true)) { $f_tab = ''; }

$requestsSql = "
    SELECT
        dr.id,
        dr.requestor_name,
        dr.document_type,
        dr.purpose,
        dr.notes,
        dr.status,
        dr.released_at,
        b.barangay_name
    FROM document_requests dr

    LEFT JOIN barangays b
        ON dr.barangay_id = b.id

    WHERE 1=1 " . $scopeSql . "
";

$fTypes = '';
$fParams = [];
$filterSql = '';
if ($f_q !== '') {
    $filterSql .= " AND (dr.requestor_name LIKE ? OR dr.document_type LIKE ? OR dr.purpose LIKE ?)";
    $like = "%$f_q%";
    $fTypes .= 'sss';
    $fParams[] = $like; $fParams[] = $like; $fParams[] = $like;
}
if ($f_document !== '') {
    $filterSql .= " AND dr.document_type = ?";
    $fTypes .= 's';
    $fParams[] = $f_document;
}
if ($f_barangay > 0 && $_SESSION['role'] === 'admin') {
    $filterSql .= " AND dr.barangay_id = ?";
    $fTypes .= 'i';
    $fParams[] = $f_barangay;
}
if ($f_purpose !== '') {
    $filterSql .= " AND dr.purpose LIKE ?";
    $fTypes .= 's';
    $fParams[] = "%$f_purpose%";
}
$requestsSql .= $filterSql;

if ($f_tab === 'unreleased') {
    $requestsSql .= " AND dr.status IN ('Pending','Processing')";
} elseif ($f_tab === 'released') {
    $requestsSql .= " AND dr.status = 'Released'";
}

$requestsSql .= " ORDER BY dr.id DESC ";

$allParams = array_merge($scopeParams, $fParams);
$allTypes  = str_repeat('i', count($scopeParams)) . $fTypes;

if ($allParams) {
    $requests = $conn->prepare($requestsSql);
    $requests->bind_param($allTypes, ...$allParams);
    $requests->execute();
    $requests = $requests->get_result();
} else {
    $requests = $conn->query($requestsSql);
}

 
$docTypes = [];
if ($dt = $conn->query("SELECT DISTINCT document_type FROM document_requests WHERE document_type <> '' ORDER BY document_type ASC")) {
    while ($x = $dt->fetch_assoc()) { $docTypes[] = $x['document_type']; }
}

if ($requests === false) {
    die("Document Request Query Error: " . $conn->error);
}

// Per-tab counts, honouring every active filter (q, document, barangay, purpose)
// so the badges always match what each tab will show.
$tab_counts = ['all' => 0, 'unreleased' => 0, 'released' => 0];
$cntBase = "SELECT COUNT(*) c FROM document_requests dr LEFT JOIN barangays b ON dr.barangay_id = b.id WHERE 1=1 " . $scopeSql . $filterSql;
foreach (['all' => '', 'unreleased' => " AND dr.status IN ('Pending','Processing')", 'released' => " AND dr.status = 'Released'"] as $tk => $tcond) {
    if ($allParams) {
        $cs = $conn->prepare($cntBase . $tcond);
        $cs->bind_param($allTypes, ...$allParams);
        $cs->execute();
        $tab_counts[$tk] = (int)$cs->get_result()->fetch_assoc()['c'];
        $cs->close();
    } else {
        $cq = $conn->query($cntBase . $tcond);
        $tab_counts[$tk] = $cq ? (int)$cq->fetch_assoc()['c'] : 0;
    }
}
$tabs = [
    'all'        => ['label' => 'All'],
    'unreleased' => ['label' => 'Unreleased'],
    'released'   => ['label' => 'Released'],
];
$tab_key = $f_tab === '' ? 'all' : $f_tab;
$tab_qs = function (string $key) use ($f_q, $f_document, $f_barangay, $f_purpose): string {
    $qs = ['q' => $f_q, 'document' => $f_document, 'barangay' => $f_barangay, 'purpose' => $f_purpose];
    if ($key !== 'all') { $qs['tab'] = $key; }
    return url('pages/documents.php?' . http_build_query($qs));
};


include BASE_PATH . '/partials/header.php';


 
 
 

if (isset($_GET['saved']) && $_GET['saved'] == '1') {
?>

    <div class="alert alert-success">
        Document request saved successfully.
    </div>

<?php
}


 
 
 

if (isset($error)) {
?>

    <div class="alert alert-danger">
        <?= e($error) ?>
    </div>

<?php
}
?>









<div class="toolbar" style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">
    <nav class="tabs" style="flex:1;flex-wrap:wrap;margin-bottom:0" aria-label="Document request status">
        <?php foreach ($tabs as $key => $t): ?>
        <a class="<?= $key === $tab_key ? 'active' : '' ?>"
           href="<?= e($tab_qs($key)) ?>"
           <?= $key === $tab_key ? 'aria-current="page"' : '' ?>>
            <?= e($t['label']) ?> (<?= number_format($tab_counts[$key]) ?>)
        </a>
        <?php endforeach; ?>
    </nav>
    <button type="button" class="btn push-right" data-toggle="#new-request-form"
            aria-controls="new-request-form" aria-expanded="false">+ New Request</button>
</div>

<div class="toolbar">
    <form method="get">
        <input type="hidden" name="q" value="<?= e($f_q) ?>">
        <?php if ($tab_key !== 'all'): ?><input type="hidden" name="tab" value="<?= e($tab_key) ?>"><?php endif; ?>

        <label class="sr-only" for="f_document">Document</label>
        <select id="f_document" name="document">
            <option value="">All documents</option>
            <?php foreach ($docTypes as $t): ?>
                <option value="<?= e($t) ?>"<?= $f_document === $t ? ' selected' : '' ?>><?= e($t) ?></option>
            <?php endforeach; ?>
        </select>
        <?php if ($_SESSION['role'] === 'admin'): ?>
        <label class="sr-only" for="f_barangay">Barangay</label>
        <select id="f_barangay" name="barangay">
            <option value="0">All barangays</option>
            <?php $barangays->data_seek(0); while ($b = $barangays->fetch_assoc()): ?>
                <option value="<?= (int)$b['id'] ?>"<?= $f_barangay === (int)$b['id'] ? ' selected' : '' ?>><?= e($b['barangay_name']) ?></option>
            <?php endwhile; ?>
        </select>
        <?php endif; ?>
        <label class="sr-only" for="f_purpose">Purpose</label>
        <input id="f_purpose" type="text" name="purpose" placeholder="Purpose" value="<?= e($f_purpose) ?>">
        <button class="btn" type="submit">Filter</button>
        <a class="btn btn--ghost" href="<?= e(url('pages/documents.php')) ?>">Reset</a>
    </form>
</div>

<p class="muted-meta" role="status">Total: <?= number_format($requests->num_rows) ?> request<?= $requests->num_rows === 1 ? '' : 's' ?></p>





<div class="card" id="new-request-form"<?= isset($error) ? '' : ' hidden' ?>>

    <h3>New Request</h3>

    <form method="post">
        <?= csrf_field() ?>

        <div class="form-grid">


             

            <?php if ($_SESSION['role'] === 'admin'): ?>

                <div>

                    <label>Barangay</label>

                    <select name="barangay_id" required>

                        <option value="">
                            Select Barangay
                        </option>

                        <?php $barangays->data_seek(0); while ($b = $barangays->fetch_assoc()): ?>

                            <option value="<?= $b['id'] ?>">
                                <?= e($b['barangay_name']) ?>
                            </option>

                        <?php endwhile; ?>

                    </select>

                </div>

            <?php endif; ?>


             

            <div>

                <label>Requestor Name</label>

                <input
                    type="text"
                    name="requestor_name"
                    required
                >

            </div>


             

            <div>

                <label>Document Type</label>

                <select name="document_type" required>

                    <option value="">
                        Select Document
                    </option>

                    <option value="Barangay Clearance">
                        Barangay Clearance
                    </option>

                    <option value="Certificate of Residency">
                        Certificate of Residency
                    </option>

                    <option value="Certificate of Indigency">
                        Certificate of Indigency
                    </option>

                    <option value="Business Clearance">
                        Business Clearance
                    </option>

                    <option value="Animal Travel Pass">
                        Animal Travel Pass
                    </option>

                </select>

            </div>


             

            <div>

                <label>Purpose</label>

                <input
                    type="text"
                    name="purpose"
                    required
                >

            </div>


             

            <div class="full">

                <label>Notes</label>

                <textarea name="notes"></textarea>

            </div>


        </div>


        <br>

        <button
            type="submit"
            class="btn"
        >
            Save Request
        </button>

        <button type="button" class="btn btn--ghost" data-toggle="#new-request-form">
            Cancel
        </button>

    </form>

</div>






<div class="card table-wrap">

    <table>

        <thead>

            <tr>

                <th>Requestor</th>

                <th>Document</th>

                <th>Barangay</th>

                <th>Purpose</th>

                <th>Status</th>

                <th>Date</th>

                <th>Action</th>

            </tr>

        </thead>


        <tbody>

        <?php if ($requests->num_rows > 0): ?>


            <?php while ($r = $requests->fetch_assoc()): ?>

                <tr>

                     

                    <td>
                        <?= e($r['requestor_name']) ?>
                    </td>


                     

                    <td>
                        <?= e($r['document_type']) ?>
                    </td>


                     

                    <td>
                        <?= e($r['barangay_name']) ?>
                    </td>


                     

                    <td>
                        <?= e($r['purpose']) ?>
                    </td>


                     

                    <td>
                        <?= e($r['status']) ?>
                    </td>


                     

                    <td>
                        <?= e($r['released_at']) ?>
                    </td>


                     

                    <td>


                        <?php if ($r['status'] === 'Pending'): ?>

                            <a
                                href="<?= e(url('actions/process_document.php?id=' . $r['id'] . '&token=' . csrf_token())) ?>"
                                class="btn btn-primary"
                            >
                                Processing
                            </a>

                            <a
                                href="<?= e(url('actions/release_document.php?id=' . $r['id'] . '&token=' . csrf_token())) ?>"
                                class="btn btn-success"
                                target="_blank"
                                data-confirm="Release this document now?"
                                data-confirm-title="Release document"
                            >
                                Released
                            </a>

                        <?php elseif ($r['status'] === 'Processing'): ?>

                            <a
                                href="<?= e(url('actions/release_document.php?id=' . $r['id'] . '&token=' . csrf_token())) ?>"
                                class="btn btn-success"
                                target="_blank"
                                data-confirm="Release this document now?"
                                data-confirm-title="Release document"
                            >
                                Released
                            </a>

                        <?php elseif ($r['status'] === 'Released'): ?>

                            <a
                                href="<?= e(url('pages/document_print.php?id=' . $r['id'])) ?>"
                                class="btn btn-success"
                                target="_blank"
                            >
                                View / Print
                            </a>

                        <?php endif; ?>

                        <a
                            href="<?= e(url('actions/document_delete.php?id=' . $r['id'] . '&token=' . csrf_token())) ?>"
                            class="btn-delete"
                            data-confirm="Delete this request? This action cannot be undone."
                            data-confirm-title="Delete request"
                        >
                            Delete
                        </a>


                    </td>

                </tr>

            <?php endwhile; ?>


        <?php else: ?>


            <tr>

                <td
                    colspan="7"
                    style="text-align:center;"
                >
                    No document requests found.
                </td>

            </tr>


        <?php endif; ?>

        </tbody>

    </table>

</div>


<?php include BASE_PATH . '/partials/footer.php'; ?>