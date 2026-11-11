<?php
try {
    $pdo = new PDO("mysql:host=localhost;port=3307", "root", "", [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    
    $sql = file_get_contents(__DIR__ . '/database/schema.sql');
    $pdo->exec($sql);
    echo "SUCCESS: Database 'school_management_db' created and schema imported on port 3307!\n";
    
    // Verify tables
    $pdo->exec("USE school_management_db");
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "Tables created: " . implode(', ', $tables) . "\n";
    
    $users = $pdo->query("SELECT id, username, email, role FROM users")->fetchAll(PDO::FETCH_ASSOC);
    echo "Seed users count: " . count($users) . "\n";
    foreach ($users as $u) {
        echo " - [{$u['role']}] {$u['username']} | {$u['email']}\n";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

