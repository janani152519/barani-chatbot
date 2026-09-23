<?php
require_once __DIR__ . '/../core/auth.php';

$res = Auth::login('admin@barani.com', 'admin123');
echo "LOGIN SUCCESS: User: {$res['username']} | Email: {$res['email']} | Role: {$res['role']}\n";
