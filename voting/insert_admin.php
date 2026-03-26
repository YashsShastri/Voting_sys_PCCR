<?php
require_once __DIR__ . '/config/db.php';

$db = getDB();
$email = 'admin1@pccoer.in';
$pass = password_hash('1234', PASSWORD_BCRYPT);

try {
    $db->prepare("INSERT INTO users (email, password, role) VALUES (?, ?, 'admin')")->execute([$email, $pass]);
    echo "SUCCESS: Inserted Admin Account -> $email with password '1234'\n";
    echo "\nSQL FOR PHPMYADMIN (if you ever need it):\n";
    echo "INSERT INTO users (email, password, role) VALUES ('$email', '$pass', 'admin');\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
