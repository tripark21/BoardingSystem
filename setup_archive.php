<?php
require_once 'config/database.php';

echo "<h2>Adding Message Archive Feature</h2>";
echo "<style>body{font-family:Arial;margin:20px;background:#f5f5f5}code{background:#eee;padding:2px 6px}.success{color:green;font-weight:bold}.error{color:red;font-weight:bold}</style>";

try {
    // Check if is_archived column exists using a simpler method
    try {
        $conn->query("SELECT is_archived FROM messages LIMIT 1");
        echo "<span class='success'>✓ is_archived column already exists</span><br>";
    } catch (Exception $check_error) {
        // Column doesn't exist, add it
        echo "Adding 'is_archived' column to messages table...<br>";
        $conn->exec("ALTER TABLE messages ADD COLUMN is_archived BOOLEAN DEFAULT FALSE");
        echo "<span class='success'>✓ Column added successfully</span><br>";
    }
    
    // Verify the column was added
    $verify = $conn->query("SELECT is_archived FROM boarding.messages LIMIT 1");
    echo "<span class='success'>✓ Archive feature ready to use!</span><br>";
    
} catch (Exception $e) {
    echo "<span class='error'>Error: " . $e->getMessage() . "</span>";
}

?>
