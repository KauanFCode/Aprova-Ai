<?php
/**
 * AprovaAI - Biblioteca Central de Funções Auxiliares
 * Compatível estritamente com PHP 7.4+
 */

/**
 * Escapa strings para saída segura em HTML (prevenção de XSS).
 *
 * @param string|int|float|null $value
 * @return string
 */
function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/**
 * Monta uma URL completa a partir da constante BASE_URL.
 *
 * @param string $path Caminho relativo (ex: 'review.php?token=abc')
 * @return string
 */
function base_url($path = '')
{
    $trimmed = ltrim((string)$path, '/');
    if ($trimmed === '') {
        return BASE_URL;
    }
    return BASE_URL . '/' . $trimmed;
}

/**
 * Redireciona o navegador para outra página e encerra a execução.
 *
 * @param string $location URL ou caminho relativo
 * @return void
 */
function redirect($location)
{
    header('Location: ' . $location);
    exit;
}

/**
 * Define uma mensagem flash na sessão para ser exibida na próxima página.
 *
 * @param string $type    'success' | 'error' | 'warning' | 'info'
 * @param string $message Texto da mensagem
 * @return void
 */
function set_flash($type, $message)
{
    $_SESSION['flash_message'] = array(
        'type'    => (string)$type,
        'message' => (string)$message,
    );
}

/**
 * Obtém e remove a mensagem flash da sessão, se existir.
 *
 * @return array|null
 */
function get_flash()
{
    if (!empty($_SESSION['flash_message']) && is_array($_SESSION['flash_message'])) {
        $flash = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $flash;
    }
    return null;
}

/**
 * Converte um texto em slug amigável para URL/identificador.
 *
 * @param string $text
 * @return string
 */
function slugify($text)
{
    $text = trim((string)$text);
    if (function_exists('iconv')) {
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        if ($converted !== false) {
            $text = $converted;
        }
    }
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    $text = trim((string)$text, '-');

    return $text !== '' ? $text : 'agencia';
}

/**
 * Gera um slug único para a tabela tenants.
 *
 * @param PDO    $pdo
 * @param string $name
 * @return string
 */
function generate_unique_slug(PDO $pdo, $name)
{
    $baseSlug = slugify($name);
    $slug = $baseSlug;
    $counter = 1;

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM tenants WHERE slug = ?');
    while (true) {
        $stmt->execute(array($slug));
        if ((int)$stmt->fetchColumn() === 0) {
            return $slug;
        }
        $counter++;
        $slug = $baseSlug . '-' . $counter;
    }
}

/**
 * Gera um token público criptograficamente seguro para o Link Mágico.
 *
 * @param int $bytes Quantidade de bytes aleatórios (24 bytes = 48 caracteres hex)
 * @return string
 */
function generate_public_token($bytes = 24)
{
    return bin2hex(random_bytes((int)$bytes));
}

/**
 * Realiza o upload seguro de imagem ou vídeo para uploads/{tenant_id}/.
 * Cria o diretório automaticamente caso não exista.
 *
 * @param array $file     Item de $_FILES (ex: $_FILES['media_file'])
 * @param int   $tenantId ID do tenant proprietário
 * @return array ['success' => bool, 'error' => string, 'media_type' => string, 'file_path' => string]
 */
function handle_creative_upload($file, $tenantId)
{
    $tenantId = (int)$tenantId;

    if (!isset($file['error']) || is_array($file['error'])) {
        return array('success' => false, 'error' => 'Parâmetros de upload inválidos.');
    }

    switch ($file['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_NO_FILE:
            return array('success' => false, 'error' => 'Nenhum arquivo foi enviado.');
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return array('success' => false, 'error' => 'O arquivo excede o tamanho máximo permitido (64MB).');
        default:
            return array('success' => false, 'error' => 'Erro desconhecido ao enviar o arquivo.');
    }

    $maxBytes = 64 * 1024 * 1024; // 64 MB
    if ((int)$file['size'] > $maxBytes) {
        return array('success' => false, 'error' => 'O arquivo ultrapassa o limite de 64MB.');
    }

    if ((int)$file['size'] <= 0) {
        return array('success' => false, 'error' => 'O arquivo enviado está vazio.');
    }

    // Validação real do MIME type via Fileinfo
    if (!function_exists('finfo_open')) {
        return array('success' => false, 'error' => 'Extensão fileinfo não está habilitada no servidor.');
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    $allowedMimes = array(
        'image/jpeg' => array('ext' => 'jpg',  'media_type' => 'image'),
        'image/png'  => array('ext' => 'png',  'media_type' => 'image'),
        'image/webp' => array('ext' => 'webp', 'media_type' => 'image'),
        'video/mp4'  => array('ext' => 'mp4',  'media_type' => 'video'),
        'video/webm' => array('ext' => 'webm', 'media_type' => 'video'),
    );

    if (!isset($allowedMimes[$mimeType])) {
        return array(
            'success' => false,
            'error'   => 'Formato não permitido. Envie apenas imagens (JPG, PNG, WebP) ou vídeos (MP4, WebM).',
        );
    }

    $ext = $allowedMimes[$mimeType]['ext'];
    $mediaType = $allowedMimes[$mimeType]['media_type'];

    // Criação automática do diretório uploads/{tenant_id}/
    $uploadDir = APP_ROOT . '/uploads/' . $tenantId;
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
            return array('success' => false, 'error' => 'Não foi possível criar a pasta de uploads do tenant.');
        }
    }

    $safeFileName = bin2hex(random_bytes(16)) . '.' . $ext;
    $destPath = $uploadDir . '/' . $safeFileName;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        return array('success' => false, 'error' => 'Falha ao mover o arquivo enviado para o diretório final.');
    }

    return array(
        'success'    => true,
        'media_type' => $mediaType,
        'file_path'  => 'uploads/' . $tenantId . '/' . $safeFileName,
    );
}

/**
 * Remove fisicamente um arquivo de criativo dentro de /uploads/ de forma segura.
 *
 * @param string $relativePath Caminho relativo salvo no banco (ex: 'uploads/1/abc.jpg')
 * @return bool
 */
function delete_creative_file($relativePath)
{
    $relativePath = ltrim((string)$relativePath, '/');
    if (strpos($relativePath, 'uploads/') !== 0) {
        return false;
    }

    $fullPath = APP_ROOT . '/' . $relativePath;
    $uploadsBase = realpath(APP_ROOT . '/uploads');
    $realTarget = realpath($fullPath);

    if ($uploadsBase !== false && $realTarget !== false && strpos($realTarget, $uploadsBase) === 0 && is_file($realTarget)) {
        return @unlink($realTarget);
    }

    return false;
}

/**
 * Formata uma data/hora do banco (Y-m-d H:i:s) para o padrão brasileiro (d/m/Y H:i).
 *
 * @param string|null $datetime
 * @return string
 */
function format_datetime_br($datetime)
{
    if (empty($datetime)) {
        return '-';
    }
    $timestamp = strtotime($datetime);
    if ($timestamp === false) {
        return (string)$datetime;
    }
    return date('d/m/Y \à\s H:i', $timestamp);
}

/**
 * Retorna metadados visuais (rótulo, classes Tailwind, ícone Lucide) para o status do criativo.
 *
 * @param string $status 'pending' | 'changes_requested' | 'approved'
 * @return array
 */
function get_status_meta($status)
{
    switch ($status) {
        case 'approved':
            return array(
                'label'       => 'Aprovado',
                'badge_class' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                'dot_class'   => 'bg-emerald-500',
                'icon'        => 'check-circle-2',
            );
        case 'changes_requested':
            return array(
                'label'       => 'Ajustes Solicitados',
                'badge_class' => 'bg-rose-50 text-rose-700 border-rose-200',
                'dot_class'   => 'bg-rose-500',
                'icon'        => 'alert-circle',
            );
        case 'pending':
        default:
            return array(
                'label'       => 'Aguardando Aprovação',
                'badge_class' => 'bg-amber-50 text-amber-700 border-amber-200',
                'dot_class'   => 'bg-amber-500',
                'icon'        => 'clock',
            );
    }
}

/**
 * Valida formato de timestamp de vídeo (ex: "00:14" ou "01:05:22").
 *
 * @param string $timestamp
 * @return bool
 */
function is_valid_video_timestamp($timestamp)
{
    return preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', trim((string)$timestamp)) === 1;
}

/**
 * Envia resposta JSON padronizada e encerra a execução.
 *
 * @param array $data
 * @param int   $statusCode
 * @return void
 */
function json_response($data, $statusCode = 200)
{
    http_response_code((int)$statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
