<?php
/**
 * AprovaAI - Upload Individual ou em Lote de Criativos (Vídeos MP4/WebM ou Imagens JPG/PNG/WebP + Copy)
 * Suporta vínculo opcional a uma Pasta/Campanha e seleção múltipla de arquivos.
 * Compatível estritamente com PHP 7.4+
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/components.php';

require_auth();
$user     = current_user();
$tenantId = $user['tenant_id'];
$pdo      = get_pdo();

$clientsStmt = $pdo->prepare('SELECT id, name FROM clients WHERE tenant_id = ? ORDER BY name ASC');
$clientsStmt->execute(array($tenantId));
$clients = $clientsStmt->fetchAll();

$folders = get_folders_by_tenant($pdo, $tenantId);

// Pré-seleção via URL (?folder_id=X)
$preselectedFolderId = isset($_GET['folder_id']) ? (int)$_GET['folder_id'] : 0;
$preselectedClientId = 0;

if ($preselectedFolderId > 0) {
    foreach ($folders as $f) {
        if ((int)$f['id'] === $preselectedFolderId) {
            $preselectedClientId = (int)$f['client_id'];
            break;
        }
    }
}

$selectedClientId = isset($_POST['client_id']) ? (int)$_POST['client_id'] : $preselectedClientId;
$selectedFolderId = isset($_POST['folder_id']) ? (int)$_POST['folder_id'] : $preselectedFolderId;

$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    if (!verify_csrf_token($csrfToken)) {
        $errorMessage = 'Sessão expirada. Recarregue a página e tente novamente.';
    } else {
        $clientId = isset($_POST['client_id']) ? (int)$_POST['client_id'] : 0;
        $folderId = isset($_POST['folder_id']) ? (int)$_POST['folder_id'] : 0;
        $title    = trim((string)(isset($_POST['title']) ? $_POST['title'] : ''));
        $copyText = trim((string)(isset($_POST['copy_text']) ? $_POST['copy_text'] : ''));

        // Garante que o cliente pertence ao tenant atual
        $clientCheck = $pdo->prepare('SELECT id FROM clients WHERE id = ? AND tenant_id = ? LIMIT 1');
        $clientCheck->execute(array($clientId, $tenantId));

        // Se selecionou pasta, valida propriedade pelo tenant
        $validFolderId = null;
        if ($folderId > 0) {
            $folderCheck = $pdo->prepare('SELECT id FROM folders WHERE id = ? AND tenant_id = ? LIMIT 1');
            $folderCheck->execute(array($folderId, $tenantId));
            if (!$folderCheck->fetch()) {
                $errorMessage = 'Pasta selecionada é inválida.';
            } else {
                $validFolderId = $folderId;
            }
        }

        if ($errorMessage === '' && !$clientCheck->fetch()) {
            $errorMessage = 'Selecione um cliente válido da sua agência.';
        } elseif ($errorMessage === '' && $title === '') {
            $errorMessage = 'Informe o título da campanha ou criativo.';
        } elseif ($errorMessage === '') {
            $filesList = isset($_FILES['media_files']) ? normalize_files_array($_FILES['media_files']) : array();

            if (empty($filesList)) {
                $errorMessage = 'Selecione pelo menos um arquivo de imagem ou vídeo para enviar.';
            } else {
                $uploadedIds = array();
                $uploadErrors = array();
                $totalFiles = count($filesList);

                $insStmt = $pdo->prepare(
                    'INSERT INTO creatives (tenant_id, client_id, folder_id, title, media_type, file_path, copy_text, status, public_token)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );

                foreach ($filesList as $index => $singleFile) {
                    $uploadResult = handle_creative_upload($singleFile, $tenantId);
                    if (!$uploadResult['success']) {
                        $uploadErrors[] = $singleFile['name'] . ': ' . $uploadResult['error'];
                        continue;
                    }

                    $itemTitle = ($totalFiles > 1)
                        ? $title . ' (' . ($index + 1) . '/' . $totalFiles . ')'
                        : $title;

                    $publicToken = generate_public_token(24);
                    $insStmt->execute(array(
                        $tenantId,
                        $clientId,
                        $validFolderId,
                        $itemTitle,
                        $uploadResult['media_type'],
                        $uploadResult['file_path'],
                        $copyText !== '' ? $copyText : null,
                        'pending',
                        $publicToken,
                    ));
                    $uploadedIds[] = (int)$pdo->lastInsertId();
                }

                if (empty($uploadedIds)) {
                    $errorMessage = 'Nenhum arquivo pôde ser enviado: ' . implode(' | ', $uploadErrors);
                } else {
                    if (!empty($uploadErrors)) {
                        set_flash('warning', count($uploadedIds) . ' criativo(s) enviado(s), mas houve erro em: ' . implode('; ', $uploadErrors));
                    } else {
                        set_flash('success', count($uploadedIds) . ' criativo(s) enviado(s) com sucesso!');
                    }

                    if ($validFolderId !== null) {
                        redirect('folder_view.php?id=' . $validFolderId);
                    } elseif (count($uploadedIds) === 1) {
                        redirect('creative_view.php?id=' . $uploadedIds[0]);
                    } else {
                        redirect('dashboard.php');
                    }
                }
            }
        }
    }
}

render_head('Novo Criativo — AprovaAI');
render_navbar('creative_new');
?>

<main class="max-w-3xl mx-auto w-full px-4 sm:px-6 lg:px-8 py-8 flex-1">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <a href="<?php echo $selectedFolderId > 0 ? 'folder_view.php?id=' . (int)$selectedFolderId : 'dashboard.php'; ?>"
               class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 hover:text-indigo-600 mb-2 transition">
                <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i> Voltar
            </a>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Enviar Criativos (Individual ou Lote)</h1>
            <p class="text-sm text-slate-500 mt-1">Selecione um ou múltiplos arquivos (vídeos e imagens) e vincule opcionalmente a uma pasta.</p>
        </div>
    </div>

    <?php if ($errorMessage !== ''): ?>
        <div class="mb-6 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-800 flex items-start gap-2.5">
            <i data-lucide="x-circle" class="w-5 h-5 shrink-0 mt-0.5"></i>
            <span><?php echo e($errorMessage); ?></span>
        </div>
    <?php endif; ?>

    <?php if (empty($clients)): ?>
        <div class="bg-white rounded-2xl border border-amber-200 bg-amber-50/40 p-8 text-center">
            <i data-lucide="users" class="w-10 h-10 text-amber-500 mx-auto mb-3"></i>
            <h2 class="text-lg font-bold text-slate-900">Cadastre um cliente primeiro</h2>
            <p class="text-sm text-slate-600 mt-1 mb-5">Para enviar um criativo, você precisa vinculá-lo a um cliente da sua agência.</p>
            <a href="clients.php" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold transition">
                <i data-lucide="user-plus" class="w-4 h-4"></i> Cadastrar Cliente
            </a>
        </div>
    <?php else: ?>
        <form method="POST" action="creative_new.php" enctype="multipart/form-data" class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6 sm:p-8 space-y-6">
            <?php echo csrf_field(); ?>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <label for="client_id" class="block text-xs font-semibold text-slate-700 uppercase tracking-wide mb-1.5">Cliente *</label>
                    <select id="client_id" name="client_id" required class="w-full rounded-xl border border-slate-200 px-3.5 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                        <option value="">Selecione um cliente...</option>
                        <?php foreach ($clients as $client): ?>
                            <option value="<?php echo (int)$client['id']; ?>" <?php echo $selectedClientId === (int)$client['id'] ? 'selected' : ''; ?>>
                                <?php echo e($client['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="folder_id" class="block text-xs font-semibold text-slate-700 uppercase tracking-wide mb-1.5">Pasta / Campanha (Opcional)</label>
                    <select id="folder_id" name="folder_id" onchange="syncClientFromFolder(this)" class="w-full rounded-xl border border-slate-200 px-3.5 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                        <option value="0" data-client-id="0">Nenhum (Criativo Avulso)</option>
                        <?php foreach ($folders as $folder): ?>
                            <option value="<?php echo (int)$folder['id']; ?>"
                                data-client-id="<?php echo (int)$folder['client_id']; ?>"
                                <?php echo $selectedFolderId === (int)$folder['id'] ? 'selected' : ''; ?>>
                                <?php echo e($folder['name'] . ' (' . $folder['client_name'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div>
                <label for="title" class="block text-xs font-semibold text-slate-700 uppercase tracking-wide mb-1.5">Título Base da Campanha / Criativo *</label>
                <input type="text" id="title" name="title" required maxlength="150"
                    value="<?php echo isset($_POST['title']) ? e($_POST['title']) : ''; ?>"
                    placeholder="Ex: Carrossel Oferta Semana 03 (ao enviar vários, será numerado automaticamente)"
                    class="w-full rounded-xl border border-slate-200 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
            </div>

            <!-- Área de Upload Múltiplo -->
            <div>
                <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wide mb-1.5">Arquivos de Mídia (1 ou múltiplos vídeos/imagens) *</label>
                <label for="media_files" id="drop-zone" class="mt-1 flex flex-col items-center justify-center px-6 py-8 border-2 border-dashed border-slate-300 rounded-2xl bg-slate-50/60 hover:bg-indigo-50/30 hover:border-indigo-400 cursor-pointer transition">
                    <div class="w-12 h-12 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center mb-3">
                        <i data-lucide="upload-cloud" class="w-6 h-6"></i>
                    </div>
                    <p class="text-sm font-semibold text-slate-700">Clique para selecionar um ou vários arquivos</p>
                    <p class="text-xs text-slate-500 mt-1">Misture Vídeos (MP4, WebM) e Imagens (JPG, PNG, WebP) &bull; até 64MB cada</p>
                    <input id="media_files" name="media_files[]" type="file" required multiple accept="image/jpeg,image/png,image/webp,video/mp4,video/webm" class="sr-only" onchange="previewSelectedFiles(this)">
                </label>

                <div id="files-preview-list" class="hidden mt-3 space-y-2"></div>
            </div>

            <!-- Copy / Legenda -->
            <div>
                <label for="copy_text" class="block text-xs font-semibold text-slate-700 uppercase tracking-wide mb-1.5">Copy / Legenda Padrão (Opcional)</label>
                <textarea id="copy_text" name="copy_text" rows="4"
                    placeholder="Digite aqui a legenda que acompanhará o(s) criativo(s)..."
                    class="w-full rounded-xl border border-slate-200 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500"><?php echo isset($_POST['copy_text']) ? e($_POST['copy_text']) : ''; ?></textarea>
            </div>

            <div class="pt-2 flex items-center justify-end gap-3">
                <a href="<?php echo $selectedFolderId > 0 ? 'folder_view.php?id=' . (int)$selectedFolderId : 'dashboard.php'; ?>" class="px-4 py-2.5 rounded-xl border border-slate-200 text-sm font-semibold text-slate-600 hover:bg-slate-50 transition">
                    Cancelar
                </a>
                <button type="submit" class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold shadow-sm shadow-indigo-600/20 transition">
                    <i data-lucide="send" class="w-4 h-4"></i>
                    <span>Enviar e Gerar Link Mágico</span>
                </button>
            </div>
        </form>
    <?php endif; ?>
</main>

<?php
$extraScripts = <<<'HTML'
<script>
function syncClientFromFolder(selectEl) {
    var option = selectEl.options[selectEl.selectedIndex];
    var clientId = option ? option.getAttribute('data-client-id') : '0';
    var clientSelect = document.getElementById('client_id');
    if (clientId && clientId !== '0' && clientSelect) {
        clientSelect.value = clientId;
    }
}

function previewSelectedFiles(input) {
    var listEl = document.getElementById('files-preview-list');
    listEl.innerHTML = '';

    if (input.files && input.files.length > 0) {
        for (var i = 0; i < input.files.length; i++) {
            var file = input.files[i];
            var sizeMB = (file.size / (1024 * 1024)).toFixed(2);
            var row = document.createElement('div');
            row.className = 'p-3 rounded-xl border border-slate-200 bg-slate-50 flex items-center justify-between gap-3';
            row.innerHTML = '<div class="text-sm font-semibold text-slate-800 truncate"></div><div class="text-xs text-slate-500 shrink-0"></div>';
            row.firstChild.textContent = (i + 1) + '. ' + file.name;
            row.lastChild.textContent = sizeMB + ' MB';
            listEl.appendChild(row);
        }
        listEl.classList.remove('hidden');
    } else {
        listEl.classList.add('hidden');
    }
}
</script>
HTML;
render_footer($extraScripts);
?>
