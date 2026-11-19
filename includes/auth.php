<?php
/**
 * AprovaAI - Autenticação Multi-Tenant e Controle de Sessão
 * Compatível estritamente com PHP 7.4+
 */

/**
 * Verifica se existe um usuário autenticado na sessão atual.
 *
 * @return bool
 */
function is_logged_in()
{
    return !empty($_SESSION['user_id']) && !empty($_SESSION['tenant_id']);
}

/**
 * Exige autenticação para acessar a página atual.
 * Redireciona para login.php caso o usuário não esteja logado.
 *
 * @return void
 */
function require_auth()
{
    if (!is_logged_in()) {
        set_flash('warning', 'Faça login para acessar o painel da sua agência.');
        redirect('login.php');
    }
}

/**
 * Retorna os dados do usuário e tenant autenticados na sessão.
 *
 * @return array|null
 */
function current_user()
{
    if (!is_logged_in()) {
        return null;
    }

    return array(
        'id'          => (int)$_SESSION['user_id'],
        'tenant_id'   => (int)$_SESSION['tenant_id'],
        'name'        => isset($_SESSION['user_name']) ? $_SESSION['user_name'] : '',
        'email'       => isset($_SESSION['user_email']) ? $_SESSION['user_email'] : '',
        'tenant_name' => isset($_SESSION['tenant_name']) ? $_SESSION['tenant_name'] : '',
    );
}

/**
 * Realiza a autenticação de um usuário pelo e-mail e senha.
 *
 * @param PDO    $pdo
 * @param string $email
 * @param string $password
 * @return array ['success' => bool, 'error' => string]
 */
function attempt_login(PDO $pdo, $email, $password)
{
    $email = trim(strtolower((string)$email));
    $password = (string)$password;

    if ($email === '' || $password === '') {
        return array('success' => false, 'error' => 'Preencha o e-mail e a senha.');
    }

    $stmt = $pdo->prepare(
        'SELECT u.id, u.tenant_id, u.name, u.email, u.password_hash, t.name AS tenant_name
         FROM users u
         INNER JOIN tenants t ON t.id = u.tenant_id
         WHERE u.email = ?
         LIMIT 1'
    );
    $stmt->execute(array($email));
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        return array('success' => false, 'error' => 'E-mail ou senha incorretos.');
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }

    $_SESSION['user_id']     = (int)$user['id'];
    $_SESSION['tenant_id']   = (int)$user['tenant_id'];
    $_SESSION['user_name']   = $user['name'];
    $_SESSION['user_email']  = $user['email'];
    $_SESSION['tenant_name'] = $user['tenant_name'];

    return array('success' => true);
}

/**
 * Cria uma nova agência (tenant) e seu usuário administrador inicial em transação.
 *
 * @param PDO    $pdo
 * @param string $agencyName
 * @param string $userName
 * @param string $email
 * @param string $password
 * @return array ['success' => bool, 'error' => string]
 */
function register_agency(PDO $pdo, $agencyName, $userName, $email, $password)
{
    $agencyName = trim((string)$agencyName);
    $userName   = trim((string)$userName);
    $email      = trim(strtolower((string)$email));
    $password   = (string)$password;

    if ($agencyName === '' || $userName === '' || $email === '' || $password === '') {
        return array('success' => false, 'error' => 'Todos os campos são obrigatórios.');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return array('success' => false, 'error' => 'Informe um endereço de e-mail válido.');
    }

    if (strlen($password) < 6) {
        return array('success' => false, 'error' => 'A senha deve ter pelo menos 6 caracteres.');
    }

    // Verifica se o e-mail já está em uso
    $checkStmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $checkStmt->execute(array($email));
    if ($checkStmt->fetch()) {
        return array('success' => false, 'error' => 'Este e-mail já está cadastrado. Tente fazer login.');
    }

    try {
        $pdo->beginTransaction();

        $slug = generate_unique_slug($pdo, $agencyName);
        $tenantStmt = $pdo->prepare('INSERT INTO tenants (name, slug) VALUES (?, ?)');
        $tenantStmt->execute(array($agencyName, $slug));
        $tenantId = (int)$pdo->lastInsertId();

        $passwordHash = password_hash($password, PASSWORD_BCRYPT);
        $userStmt = $pdo->prepare(
            'INSERT INTO users (tenant_id, name, email, password_hash) VALUES (?, ?, ?, ?)'
        );
        $userStmt->execute(array($tenantId, $userName, $email, $passwordHash));
        $userId = (int)$pdo->lastInsertId();

        $pdo->commit();

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }

        $_SESSION['user_id']     = $userId;
        $_SESSION['tenant_id']   = $tenantId;
        $_SESSION['user_name']   = $userName;
        $_SESSION['user_email']  = $email;
        $_SESSION['tenant_name'] = $agencyName;

        return array('success' => true);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('AprovaAI Register Error: ' . $e->getMessage());
        return array('success' => false, 'error' => 'Erro ao criar conta da agência. Tente novamente.');
    }
}

/**
 * Encerra a sessão do usuário de forma segura.
 *
 * @return void
 */
function logout_user()
{
    $_SESSION = array();

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}
