<?php
require_once 'backend/core/Database.php';
$conn = Database::getInstance()->getConnection();
$sql = "SELECT id, username, full_name, role, status FROM users";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    echo "<table border='1' cellpadding='5'><tr><th>ID</th><th>Username</th><th>Full Name</th><th>Role</th><th>Status</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr><td>{$row['id']}</td><td>{$row['username']}</td><td>{$row['full_name']}</td><td>{$row['role']}</td><td>{$row['status']}</td></tr>";
    }
    echo "</table>";
} else {
    echo "No users found.";
}
?>
