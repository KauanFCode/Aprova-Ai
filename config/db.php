<?php
/**
 * AprovaAI - Configuração de Banco de Dados, Sessão, BASE_URL e CSRF
 * Compatível estritamente com PHP 7.4+ e hospedagem compartilhada cPanel.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ----------------------------------------------------------------------------
// Credenciais do Banco de Dados MySQL (cPanel)
// ----------------------------------------------------------------------------
define('DB_HOST', 'localhost');
define('DB_NAME', 'oficinahotrods_aprovai');
define('DB_USER', 'oficinahotrods_aprovai');
define('DB_PASS', '40028922Ka');
define('DB_CHARSET', 'utf8mb4');

// Caminho físico raiz da aplicação no servidor
define('APP_ROOT', dirname(__DIR__));

// ----------------------------------------------------------------------------
// Detecção Dinâmica da BASE_URL (Suporta raiz do domínio e subpastas)
// ----------------------------------------------------------------------------
if (!function_exists('detect_base_url')) {
    /**
     * Detecta dinamicamente a URL base da aplicação sem barra final.
     *
     * @return string
     */
    function detect_base_url()
    {
        $isHttps = false;
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            $isHttps = true;
        } elseif (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443) {
            $isHttps = true;
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
            $isHttps = true;
        }

        $scheme = $isHttps ? 'https' : 'http';
        $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';

        $subPath = '';
        $docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : false;
        $appRoot = realpath(APP_ROOT);

        if ($docRoot !== false && $appRoot !== false) {
            $docRootNorm = rtrim(str_replace('\\', '/', $docRoot), '/');
            $appRootNorm = rtrim(str_replace('\\', '/', $appRoot), '/');

            if ($docRootNorm !== '' && strpos($appRootNorm, $docRootNorm) === 0) {
                $subPath = substr($appRootNorm, strlen($docRootNorm));
            }
        }

        if ($subPath === '' && isset($_SERVER['SCRIPT_NAME'])) {
            $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
            $scriptDir = rtrim($scriptDir, '/');
            $knownSubdirs = array('/api', '/includes', '/config');
            foreach ($knownSubdirs as $subdir) {
                $len = strlen($subdir);
                if (strlen($scriptDir) >= $len && substr($scriptDir, -$len) === $subdir) {
                    $scriptDir = substr($scriptDir, 0, -$len);
                    break;
                }
            }
            $subPath = $scriptDir;
        }

        $subPath = '/' . ltrim((string)$subPath, '/');
        $subPath = rtrim($subPath, '/');

        return $scheme . '://' . $host . $subPath;
    }
}

if (!defined('BASE_URL')) {
    define('BASE_URL', detect_base_url());
}

// ----------------------------------------------------------------------------
// Conexão Singleton com PDO
// ----------------------------------------------------------------------------
if (!function_exists('get_pdo')) {
    /**
     * Retorna uma instância única (Singleton) da conexão PDO.
     *
     * @return PDO
     */
    function get_pdo()
    {
        static $pdo = null;

        if ($pdo === null) {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
            $options = array(
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            );

            try {
                $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $e) {
                error_log('AprovaAI DB Connection Error: ' . $e->getMessage());
                http_response_code(500);
                die('Erro ao conectar ao banco de dados. Verifique as configurações em config/db.php.');
            }
        }

        return $pdo;
    }
}

// ----------------------------------------------------------------------------
// Proteção CSRF (Cross-Site Request Forgery)
// ----------------------------------------------------------------------------
if (!function_exists('generate_csrf_token')) {
    /**
     * Gera ou retorna o token CSRF armazenado na sessão atual.
     *
     * @return string
     */
    function generate_csrf_token()
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('verify_csrf_token')) {
    /**
     * Valida o token CSRF enviado na requisição contra o token da sessão.
     *
     * @param string|null $token
     * @return bool
     */
    function verify_csrf_token($token)
    {
        if (empty($_SESSION['csrf_token']) || empty($token) || !is_string($token)) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }
}

if (!function_exists('csrf_field')) {
    /**
     * Retorna o campo input hidden com o token CSRF para formulários HTML.
     *
     * @return string
     */
    function csrf_field()
    {
        $token = htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8');
        return '<input type="hidden" name="csrf_token" value="' . $token . '">';
    }
}
