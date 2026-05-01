<?php
require_once __DIR__ . "/config/db.php";

if ($conn) {
    echo "✅ Database connection successful!";
} else {
    echo "❌ Connection failed.";
}
?>
