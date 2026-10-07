<?php
require __DIR__ . '/../config/config.php';
 
require_admin();

$default_password = "secretary123";

$result = $conn->query("SELECT id, barangay_name FROM barangays ORDER BY id");

while ($b = $result->fetch_assoc()) {
    $barangay_id = (int)$b["id"];
    $username = "secretary" . $barangay_id;
    $full_name = "Secretary - " . $b["barangay_name"];
    $role = "secretary";
    $hashed_password = password_hash($default_password, PASSWORD_DEFAULT);

    $check = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $check->bind_param("s", $username);
    $check->execute();

    if ($check->get_result()->num_rows === 0) {
        $stmt = $conn->prepare(
            "INSERT INTO users (username,password,full_name,role,barangay_id)
             VALUES (?,?,?,?,?)"
        );
        $stmt->bind_param(
            "ssssi",
            $username, $hashed_password, $full_name, $role, $barangay_id
        );
        $stmt->execute();
    }

    echo htmlspecialchars($b["barangay_name"]) . " -> "
       . htmlspecialchars($username) . " / "
       . htmlspecialchars($default_password) . "<br>";
}

echo "<br>Delete create_secretaries.php after testing.";
?>
