<?php
/**
 * AprovaAI - Encerramento de Sessão (Logout)
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

logout_user();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
set_flash('info', 'Você saiu da sua conta com segurança.');
redirect('login.php');
