<?php
include __DIR__ . '/config/app.php';
app_session_start();

$_SESSION = [];

if (session_status() === PHP_SESSION_ACTIVE) {
    session_destroy();
}

header('Location: index.php');
exit;
