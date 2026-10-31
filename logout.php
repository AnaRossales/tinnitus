<?php
require_once __DIR__ . '/includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $_SESSION = [];
    session_destroy();
    session_start();
    flash('info', 'Cerraste sesión. ¡Nos vemos en el próximo concierto!');
}
redirect('index.php');
