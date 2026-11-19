<?php
/**
 * AprovaAI - Endpoint de Ações AJAX (Agência e Cliente Público)
 * Processa: approve, request_changes, comment, agency_comment, update_copy, reset_pending, delete_creative.
 * Compatível estritamente com PHP 7.4+
 */

require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(array('success' => false, 'error' => 'Método não permitido.'), 405);
}

// Suporta tanto FormData/x-www-form-urlencoded ($_POST) quanto JSON raw
$payload = $_POST;
if (empty($payload)) {
    $rawInput = file_get_contents('php://input');
    if ($rawInput !== false && $rawInput !== '') {
        $decoded = json_decode($rawInput, true);
        if (is_array($decoded)) {
            $payload = $decoded;
        }
    }
}

$csrfToken = isset($payload['csrf_token']) ? $payload['csrf_token'] : '';
if (!verify_csrf_token($csrfToken)) {
    json_response(array('success' => false, 'error' => 'Token de segurança inválido. Recarregue a página.'), 403);
}

$action = isset($payload['action']) ? trim((string)$payload['action']) : '';
$pdo    = get_pdo();

$agencyActions = array('update_copy', 'agency_comment', 'reset_pending', 'delete_creative');
$clientActions = array('approve', 'request_changes', 'comment');

// ============================================================================
// AÇÕES AUTENTICADAS DA AGÊNCIA (Escopo estrito por tenant_id)
// ============================================================================
if (in_array($action, $agencyActions, true)) {
    if (!is_logged_in()) {
        json_response(array('success' => false, 'error' => 'Não autenticado.'), 401);
    }

    $user       = current_user();
    $tenantId   = $user['tenant_id'];
    $creativeId = isset($payload['creative_id']) ? (int)$payload['creative_id'] : 0;

    $stmt = $pdo->prepare('SELECT * FROM creatives WHERE id = ? AND tenant_id = ? LIMIT 1');
    $stmt->execute(array($creativeId, $tenantId));
    $creative = $stmt->fetch();

    if (!$creative) {
        json_response(array('success' => false, 'error' => 'Criativo não encontrado ou sem permissão.'), 404);
    }

    switch ($action) {
        case 'update_copy':
            $copyText = trim((string)(isset($payload['copy_text']) ? $payload['copy_text'] : ''));
            $upd = $pdo->prepare('UPDATE creatives SET copy_text = ? WHERE id = ? AND tenant_id = ?');
            $upd->execute(array($copyText !== '' ? $copyText : null, $creativeId, $tenantId));
            json_response(array('success' => true, 'message' => 'Legenda atualizada com sucesso!'));
            break;

        case 'agency_comment':
            $comment = trim((string)(isset($payload['comment']) ? $payload['comment'] : ''));
            $videoTs = trim((string)(isset($payload['video_timestamp']) ? $payload['video_timestamp'] : ''));

            if ($comment === '') {
                json_response(array('success' => false, 'error' => 'Digite um comentário antes de enviar.'), 422);
            }

            $validTs = null;
            if ($creative['media_type'] === 'video' && $videoTs !== '' && is_valid_video_timestamp($videoTs)) {
                $validTs = $videoTs;
            }

            $ins = $pdo->prepare(
                'INSERT INTO feedbacks (creative_id, author_name, author_role, comment, video_timestamp, action_taken)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $ins->execute(array($creativeId, $user['name'], 'agency', $comment, $validTs, 'comment'));
            json_response(array('success' => true, 'message' => 'Comentário publicado!'));
            break;

        case 'reset_pending':
            $upd = $pdo->prepare("UPDATE creatives SET status = 'pending' WHERE id = ? AND tenant_id = ?");
            $upd->execute(array($creativeId, $tenantId));
            json_response(array('success' => true, 'message' => 'Status alterado para Aguardando Aprovação.'));
            break;

        case 'delete_creative':
            delete_creative_file($creative['file_path']);
            $del = $pdo->prepare('DELETE FROM creatives WHERE id = ? AND tenant_id = ?');
            $del->execute(array($creativeId, $tenantId));
            set_flash('success', 'Criativo "' . $creative['title'] . '" excluído com sucesso.');
            json_response(array('success' => true, 'message' => 'Criativo excluído.'));
            break;
    }
}

// ============================================================================
// AÇÕES PÚBLICAS DO CLIENTE (Autenticadas via public_token)
// ============================================================================
if (in_array($action, $clientActions, true)) {
    $publicToken = isset($payload['public_token']) ? trim((string)$payload['public_token']) : '';
    $authorName  = trim((string)(isset($payload['author_name']) ? $payload['author_name'] : ''));
    $comment     = trim((string)(isset($payload['comment']) ? $payload['comment'] : ''));
    $videoTs     = trim((string)(isset($payload['video_timestamp']) ? $payload['video_timestamp'] : ''));

    if ($publicToken === '') {
        json_response(array('success' => false, 'error' => 'Token de aprovação ausente.'), 400);
    }

    $stmt = $pdo->prepare('SELECT * FROM creatives WHERE public_token = ? LIMIT 1');
    $stmt->execute(array($publicToken));
    $creative = $stmt->fetch();

    if (!$creative) {
        json_response(array('success' => false, 'error' => 'Criativo não encontrado.'), 404);
    }

    if ($authorName === '') {
        json_response(array('success' => false, 'error' => 'Informe seu nome para registrar a avaliação.'), 422);
    }

    if (($action === 'request_changes' || $action === 'comment') && $comment === '') {
        json_response(array('success' => false, 'error' => 'Escreva um comentário descrevendo sua observação.'), 422);
    }

    $validTs = null;
    if ($creative['media_type'] === 'video' && $videoTs !== '' && is_valid_video_timestamp($videoTs)) {
        $validTs = $videoTs;
    }

    $creativeId = (int)$creative['id'];

    try {
        $pdo->beginTransaction();

        if ($action === 'approve') {
            $newStatus   = 'approved';
            $actionTaken = 'approved';
            $finalComment = $comment !== '' ? $comment : 'Criativo aprovado pelo cliente.';
            $msg = 'Criativo aprovado com sucesso!';
        } elseif ($action === 'request_changes') {
            $newStatus   = 'changes_requested';
            $actionTaken = 'changes_requested';
            $finalComment = $comment;
            $msg = 'Solicitação de ajuste enviada para a agência!';
        } else {
            $newStatus   = null;
            $actionTaken = 'comment';
            $finalComment = $comment;
            $msg = 'Comentário enviado!';
        }

        if ($newStatus !== null) {
            $upd = $pdo->prepare('UPDATE creatives SET status = ? WHERE id = ?');
            $upd->execute(array($newStatus, $creativeId));
        }

        $ins = $pdo->prepare(
            'INSERT INTO feedbacks (creative_id, author_name, author_role, comment, video_timestamp, action_taken)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $ins->execute(array($creativeId, $authorName, 'client', $finalComment, $validTs, $actionTaken));

        $pdo->commit();
        json_response(array('success' => true, 'message' => $msg));
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('AprovaAI Action Error: ' . $e->getMessage());
        json_response(array('success' => false, 'error' => 'Erro interno ao registrar sua ação.'), 500);
    }
}

json_response(array('success' => false, 'error' => 'Ação desconhecida.'), 400);
