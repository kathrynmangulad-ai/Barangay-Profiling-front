<?php
require __DIR__ . '/../config/config.php';
 
require_admin();

$username = "admin";
$password = "admin123";
$full_name = "System Administrator";
$role = "admin";
$barangay_id = null;

$hashed_password = password_hash($password, PASSWORD_DEFAULT);

$stmt = $conn->prepare(
    "INSERT INTO users (username,password,full_name,role,barangay_id)
     VALUES (?,?,?,?,?)"
);
$stmt->bind_param(
    "ssssi",
    $username, $hashed_password, $full_name, $role, $barangay_id
);

if ($stmt->execute()) {
    echo "Admin created successfully. Username: admin | Password: admin123";
} else {
    echo "Error: " . $stmt->error;
}
?>
