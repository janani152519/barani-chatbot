<?php
require_once __DIR__ . '/../core/auth.php';

try {
    echo "--- Testing Successful Login (janani) ---\n";
    $janani = Auth::login('janani', 'password123');
    echo "SUCCESS: Logged in as {$janani['username']} | Role: {$janani['role']} | Dept: {$janani['department_name']}\n\n";

    echo "--- Testing Successful Login (rajesh - HR) ---\n";
    $rajesh = Auth::login('rajesh', 'password123');
    echo "SUCCESS: Logged in as {$rajesh['username']} | Role: {$rajesh['role']}\n\n";

    echo "--- Testing Successful Login (amit - Finance) ---\n";
    $amit = Auth::login('amit', 'password123');
    echo "SUCCESS: Logged in as {$amit['username']} | Role: {$amit['role']}\n\n";

    echo "--- Testing Failed Login (Wrong Password) ---\n";
    try {
        Auth::login('janani', 'wrongpassword');
    } catch (Throwable $e) {
        echo "CAUGHT EXPECTED ERROR: " . $e->getMessage() . "\n";
    }

} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
