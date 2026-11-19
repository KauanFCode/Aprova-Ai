<?php
/**
 * AprovaAI - Ponto de Entrada
 * Redireciona para o Dashboard (se autenticado) ou para o Login.
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    redirect('dashboard.php');
}

redirect('login.php');
