<?php

require_once __DIR__ . '/../config/config.php';
require_staff();

$page_title = 'Incident Report Transaction Receipt';

$id = (int)($_GET['id'] ?? 0);
$r = null;

if ($id) {

    
    $s = $conn->prepare("
        SELECT *
        FROM blotter_records
        WHERE id = ?
    ");

    $s->bind_param('i', $id);
    $s->execute();

    $r = $s->get_result()->fetch_assoc();

     
    if (
        $r &&
        $_SESSION['role'] !== 'admin' &&
        $r['barangay_id'] != $_SESSION['barangay_id']
    ) {
        die('Access denied.');
    }
}



if (!$r) {

    $r = [
        'blotter_no'          => '1',
        'incident_type'       => 'Sample Data Only',
        'reporting_person'    => '',
        'reporting_address'   => '',
        'report_datetime'     => date('Y-m-d H:i:s'),
        'incident_datetime'   => '',
        'place_of_incident'   => '',
        'recorded_by'         => $_SESSION['full_name'] ?? '',
        'narrative'           => ''
    ];
}


include BASE_PATH . '/partials/header.php';

?>

<div class="receipt">

    <div class="tabs no-print">
    </div>

    <div class="no-print" style="text-align:right;">
        <button class="btn" type="button" onclick="window.print()">
            🖨 View / Print Receipt
        </button>
    </div>

    <h1 class="receipt-title">
        BLOTTER/INCIDENT REPORT TRANSACTION RECEIPT
    </h1>

    <form method="post" action="<?= e(url('actions/save_incident.php')) ?>">
        <?= csrf_field() ?>

        <input type="hidden" name="id" value="<?= $id ?>">

        <div class="receipt-grid">

            
            <label>BLOTTER ENTRY NUMBER:</label>

            <input
                type="text"
                name="blotter_no"
                value="<?= e($r['blotter_no'] ?? '') ?>"
            >


            
            <label>NAME OF REPORTING PERSON:</label>

            <input
                type="text"
                name="reporting_person"
                value="<?= e($r['reporting_person'] ?? '') ?>"
                required
            >


            
            <label>ADDRESS OF REPORTING PERSON:</label>

            <input
                type="text"
                name="reporting_address"
                value="<?= e($r['reporting_address'] ?? '') ?>"
            >


            
            <label>TYPE OF INCIDENT:</label>

            <input
                type="text"
                name="incident_type"
                value="<?= e($r['incident_type'] ?? '') ?>"
                required
            >


            
            <label>DATE/TIME OF REPORT:</label>

            <input
                type="datetime-local"
                name="report_datetime"
                value="<?=
                    !empty($r['report_datetime'])
                    ? date(
                        'Y-m-d\TH:i',
                        strtotime($r['report_datetime'])
                    )
                    : ''
                ?>"
            >


             
            <label>DATE/TIME OF INCIDENT:</label>

            <input
                type="datetime-local"
                name="incident_datetime"
                value="<?=
                    !empty($r['incident_datetime'])
                    ? date(
                        'Y-m-d\TH:i',
                        strtotime($r['incident_datetime'])
                    )
                    : ''
                ?>"
            >


             
            <label>PLACE OF INCIDENT:</label>

            <input
                type="text"
                name="place_of_incident"
                value="<?= e($r['place_of_incident'] ?? '') ?>"
            >


             
            <label>NARRATIVE:</label>

            <textarea
                name="narrative"
                rows="5"
            ><?= e($r['narrative'] ?? '') ?></textarea>

        </div>


         

        <div class="recorded">

            <div class="recorded-box">
                AND<br>
                RECORDED<br>
                BY:
            </div>

            <div class="signature">

                <div class="signature-name">
                    <?= e($r['recorded_by'] ?? ($_SESSION['full_name'] ?? '')) ?>
                </div>

                <div>
                    POSITION / NAME / SIGNATURE OF IN-CHARGE
                </div>

            </div>

        </div>


         

        <div
            class="no-print"
            style="text-align:center;margin-top:25px;"
        >

            <button
                type="submit"
                class="btn"
            >
                💾 Save Changes
            </button>

        </div>

    </form>

</div>

<?php include BASE_PATH . '/partials/footer.php'; ?>