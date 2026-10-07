
<?php

require_once __DIR__ . '/../config/config.php';
require_admin();
csrf_verify_get();

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    die("Invalid user ID.");
}

// Never let an admin soft-delete their own account.
if ($id === (int)($_SESSION['user_id'] ?? 0)) {
    die("You cannot delete the account you are signed in with.");
}

// Soft delete: mark the row with a deletion timestamp instead of removing it.
// The account is kept in the database for recovery/audit but is hidden from
// the user lists and blocked from signing in. Already-deleted rows are left
// untouched (deleted_at IS NULL guard).
$stmt = $conn->prepare("UPDATE users SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL");
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    $stmt->close();
    log_access('user_deleted', 'user', $id);
    header("Location:" . url('admin/users.php?deleted=1'));
    exit;
} else {
    $err = $conn->error;
    $stmt->close();
    die("Error deleting user: " . $err);
}
