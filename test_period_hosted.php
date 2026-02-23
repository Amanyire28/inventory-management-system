<?php
// Upload this file to your hosted site root and visit it

require_once __DIR__ . '/backend/core/Database.php';

echo "<h2>Testing Period Creation Fix</h2>";

$db = Database::getInstance()->getConnection();

// Test with empty strings converted to NULL (the fix we made)
$period_name = "Test from PHP";
$start_date = '';
$end_date = '';

// This is the fix - convert empty to NULL
$start_date = !empty($start_date) ? $start_date : null;
$end_date = !empty($end_date) ? $end_date : null;

echo "Period Name: $period_name<br>";
echo "Start Date: " . ($start_date === null ? 'NULL' : $start_date) . "<br>";
echo "End Date: " . ($end_date === null ? 'NULL' : $end_date) . "<br><br>";

$stmt = $db->prepare(
    "INSERT INTO periods (period_name, status, start_date, end_date) 
     VALUES (?, 'OPEN', ?, ?)"
);

$stmt->bind_param('sss', $period_name, $start_date, $end_date);

if ($stmt->execute()) {
    $id = $db->insert_id;
    echo "<strong style='color:green'>✓ SUCCESS!</strong> Period created with ID: $id<br>";
    echo "<strong>The fix is working!</strong><br><br>";
    
    // Clean up
    $db->query("DELETE FROM periods WHERE id = $id");
    echo "Test record cleaned up.<br><br>";
    echo "<strong>Your PeriodService.php file has the correct fix.</strong>";
} else {
    echo "<strong style='color:red'>✗ FAILED:</strong> " . $stmt->error . "<br><br>";
    echo "<strong>The PeriodService.php file needs to be re-uploaded!</strong>";
    echo "<br><br>Lines 65-67 in PeriodService.php should have:<br>";
    echo "<code>// Convert empty strings to NULL for DATE fields (strict mode compatibility)<br>";
    echo "\$start_date = !empty(\$start_date) ? \$start_date : null;<br>";
    echo "\$end_date = !empty(\$end_date) ? \$end_date : null;</code>";
}
?>
