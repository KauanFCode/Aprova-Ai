<?php
/**
 * AprovaAI - Endpoint de Ações AJAX (Agência e Cliente Público)
 * Processa:
 *  - Agência: update_copy, agency_comment, reset_pending, delete_creative, create_folder, delete_folder
 *  - Cliente: approve, request_changes, comment, approve_folder_all
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

$agencyCreativeActions = array('update_copy', 'agency_comment', 'reset_pending', 'delete_creative');
$agencyFolderActions   = array('create_folder', 'delete_folder');
$clientCreativeActions = array('approve', 'request_changes', 'comment');

// ============================================================================
// 1. AÇÕES DE PASTA/CAMPANHA DA AGÊNCIA (Escopo estrito por tenant_id)
// ============================================================================
if (in_array($action, $agencyFolderActions, true)) {
    if (!is_logged_in()) {
        json_response(array('success' => false, 'error' => 'Não autenticado.'), 401);
    }

    $user     = current_user();
    $tenantId = $user['tenant_id'];

    if ($action === 'create_folder') {
        $clientId    = isset($payload['client_id']) ? (int)$payload['client_id'] : 0;
        $name        = trim((string)(isset($payload['name']) ? $payload['name'] : ''));
        $description = trim((string)(isset($payload['description']) ? $payload['description'] : ''));

        if ($name === '') {
            json_response(array('success' => false, 'error' => 'Informe o nome da pasta ou campanha.'), 422);
        }

        // Garante que o cliente pertence ao tenant logado
        $clientStmt = $pdo->prepare('SELECT id FROM clients WHERE id = ? AND tenant_id = ? LIMIT 1');
        $clientStmt->execute(array($clientId, $tenantId));
        if (!$clientStmt->fetch()) {
            json_response(array('success' => false, 'error' => 'Selecione um cliente válido da sua agência.'), 422);
        }

        $publicToken = generate_public_token(24);
        $ins = $pdo->prepare(
            'INSERT INTO folders (tenant_id, client_id, name, description, public_token)
             VALUES (?, ?, ?, ?, ?)'
        );
        $ins->execute(array(
            $tenantId,
            $clientId,
            $name,
            $description !== '' ? $description : null,
            $publicToken,
        ));

        $newFolderId = (int)$pdo->lastInsertId();
        set_flash('success', 'Pasta "' . $name . '" criada com sucesso!');
        json_response(array(
            'success'      => true,
            'folder_id'    => $newFolderId,
            'public_token' => $publicToken,
            'message'      => 'Pasta criada com sucesso!',
        ));
    }

    if ($action === 'delete_folder') {
        $folderId = isset($payload['folder_id']) ? (int)$payload['folder_id'] : 0;

        $checkStmt = $pdo->prepare('SELECT id, name FROM folders WHERE id = ? AND tenant_id = ? LIMIT 1');
        $checkStmt->execute(array($folderId, $tenantId));
        $folderRow = $checkStmt->fetch();

        if (!$folderRow) {
            json_response(array('success' => false, 'error' => 'Pasta não encontrada ou sem permissão.'), 404);
        }

        // ON DELETE SET NULL mantém os criativos como avulsos automaticamente
        $del = $pdo->prepare('DELETE FROM folders WHERE id = ? AND tenant_id = ?');
        $del->execute(array($folderId, $tenantId));

        set_flash('success', 'Pasta "' . $folderRow['name'] . '" removida. Os criativos foram mantidos como avulsos.');
        json_response(array('success' => true, 'message' => 'Pasta removida com sucesso.'));
    }
}

// ============================================================================
// 2. AÇÕES DE CRIATIVO DA AGÊNCIA (Escopo estrito por tenant_id)
// ============================================================================
if (in_array($action, $agencyCreativeActions, true)) {
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
// 3. AÇÃO PÚBLICA DO CLIENTE: APROVAR TODOS OS CRIATIVOS RESTANTES DA PASTA
// ============================================================================
if ($action === 'approve_folder_all') {
    $folderToken = isset($payload['folder_token']) ? trim((string)$payload['folder_token']) : '';
    $authorName  = trim((string)(isset($payload['author_name']) ? $payload['author_name'] : ''));
    $comment     = trim((string)(isset($payload['comment']) ? $payload['comment'] : ''));

    if ($folderToken === '') {
        json_response(array('success' => false, 'error' => 'Token da pasta ausente.'), 400);
    }

    if ($authorName === '') {
        json_response(array('success' => false, 'error' => 'Informe seu nome antes de aprovar os criativos.'), 422);
    }

    $folderStmt = $pdo->prepare('SELECT id, name FROM folders WHERE public_token = ? LIMIT 1');
    $folderStmt->execute(array($folderToken));
    $folder = $folderStmt->fetch();

    if (!$folder) {
        json_response(array('success' => false, 'error' => 'Pasta não encontrada.'), 404);
    }

    $folderId = (int)$folder['id'];

    try {
        $pdo->beginTransaction();

        $pendingStmt = $pdo->prepare(
            "SELECT id FROM creatives
             WHERE folder_id = ? AND status IN ('pending', 'changes_requested')"
        );
        $pendingStmt->execute(array($folderId));
        $toApprove = $pendingStmt->fetchAll();

        if (empty($toApprove)) {
            $pdo->rollBack();
            json_response(array('success' => false, 'error' => 'Todos os criativos desta pasta já estão aprovados.'), 422);
        }

        $upd = $pdo->prepare(
            "UPDATE creatives SET status = 'approved'
             WHERE folder_id = ? AND status IN ('pending', 'changes_requested')"
        );
        $upd->execute(array($folderId));

        $finalComment = $comment !== '' ? $comment : 'Aprovado em lote pelo cliente na pasta.';
        $ins = $pdo->prepare(
            'INSERT INTO feedbacks (creative_id, author_name, author_role, comment, video_timestamp, action_taken)
             VALUES (?, ?, ?, ?, ?, ?)'
        );

        foreach ($toApprove as $item) {
            $ins->execute(array((int)$item['id'], $authorName, 'client', $finalComment, null, 'approved'));
        }

        $pdo->commit();
        $count = count($toApprove);
        json_response(array(
            'success'        => true,
            'approved_count' => $count,
            'message'        => $count . ' criativo(s) aprovado(s) com sucesso!',
        ));
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('AprovaAI Approve Folder All Error: ' . $e->getMessage());
        json_response(array('success' => false, 'error' => 'Erro interno ao aprovar os criativos da pasta.'), 500);
    }
}

// ============================================================================
// 4. AÇÕES PÚBLICAS DO CLIENTE EM CRIATIVO INDIVIDUAL (Via public_token)
// ============================================================================
if (in_array($action, $clientCreativeActions, true)) {
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
            $newStatus    = 'approved';
            $actionTaken  = 'approved';
            $finalComment = $comment !== '' ? $comment : 'Criativo aprovado pelo cliente.';
            $msg          = 'Criativo aprovado com sucesso!';
        } elseif ($action === 'request_changes') {
            $newStatus    = 'changes_requested';
            $actionTaken  = 'changes_requested';
            $finalComment = $comment;
            $msg          = 'Solicitação de ajuste enviada para a agência!';
        } else {
            $newStatus    = null;
            $actionTaken  = 'comment';
            $finalComment = $comment;
            $msg          = 'Comentário enviado!';
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
