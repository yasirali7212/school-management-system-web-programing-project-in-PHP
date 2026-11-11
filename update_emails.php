<?php
require_once __DIR__ . '/config/db.php';
if ($pdo) {
    $pdo->exec("UPDATE users SET email = 'admin.super@roots.edu' WHERE id = 1");
    $pdo->exec("UPDATE users SET email = 'faculty.first@roots.edu' WHERE id = 2");
    $pdo->exec("UPDATE users SET email = 'student.id@roots.edu' WHERE id = 3");
    echo "USERS_UPDATED_SUCCESSFULLY\n";
} else {
    echo "PDO_OFFLINE\n";
}

