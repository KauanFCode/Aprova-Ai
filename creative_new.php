<?php
/**
 * AprovaAI - Upload de Novo Criativo (Vídeo MP4/WebM ou Imagem JPG/PNG/WebP + Copy)
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

$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    if (!verify_csrf_token($csrfToken)) {
        $errorMessage = 'Sessão expirada. Recarregue a página e tente novamente.';
    } else {
        $clientId = isset($_POST['client_id']) ? (int)$_POST['client_id'] : 0;
        $title    = trim((string)(isset($_POST['title']) ? $_POST['title'] : ''));
        $copyText = trim((string)(isset($_POST['copy_text']) ? $_POST['copy_text'] : ''));

        // Garante que o cliente pertence ao tenant atual
        $clientCheck = $pdo->prepare('SELECT id FROM clients WHERE id = ? AND tenant_id = ? LIMIT 1');
        $clientCheck->execute(array($clientId, $tenantId));
        if (!$clientCheck->fetch()) {
            $errorMessage = 'Selecione um cliente válido da sua agência.';
        } elseif ($title === '') {
            $errorMessage = 'Informe o título da campanha ou criativo.';
        } elseif (!isset($_FILES['media_file'])) {
            $errorMessage = 'Selecione um arquivo de imagem ou vídeo para enviar.';
        } else {
            $uploadResult = handle_creative_upload($_FILES['media_file'], $tenantId);
            if (!$uploadResult['success']) {
                $errorMessage = $uploadResult['error'];
            } else {
                $publicToken = generate_public_token(24);
                $stmt = $pdo->prepare(
                    'INSERT INTO creatives (tenant_id, client_id, title, media_type, file_path, copy_text, status, public_token)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute(array(
                    $tenantId,
                    $clientId,
                    $title,
                    $uploadResult['media_type'],
                    $uploadResult['file_path'],
                    $copyText !== '' ? $copyText : null,
                    'pending',
                    $publicToken,
                ));

                $newId = (int)$pdo->lastInsertId();
                set_flash('success', 'Criativo enviado com sucesso! Copie o Link Mágico abaixo para enviar ao cliente.');
                redirect('creative_view.php?id=' . $newId);
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
            <a href="dashboard.php" class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 hover:text-indigo-600 mb-2 transition">
                <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i> Voltar ao Dashboard
            </a>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Enviar Novo Criativo</h1>
            <p class="text-sm text-slate-500 mt-1">Faça upload da arte ou vídeo e adicione a legenda para aprovação.</p>
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
                            <option value="<?php echo (int)$client['id']; ?>" <?php echo (isset($_POST['client_id']) && (int)$_POST['client_id'] === (int)$client['id']) ? 'selected' : ''; ?>>
                                <?php echo e($client['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="title" class="block text-xs font-semibold text-slate-700 uppercase tracking-wide mb-1.5">Título da Campanha / Criativo *</label>
                    <input type="text" id="title" name="title" required maxlength="150"
                        value="<?php echo isset($_POST['title']) ? e($_POST['title']) : ''; ?>"
                        placeholder="Ex: Reels Lançamento Café Especial"
                        class="w-full rounded-xl border border-slate-200 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                </div>
            </div>

            <!-- Área de Upload -->
            <div>
                <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wide mb-1.5">Arquivo de Mídia (Vídeo ou Imagem) *</label>
                <label for="media_file" id="drop-zone" class="mt-1 flex flex-col items-center justify-center px-6 py-8 border-2 border-dashed border-slate-300 rounded-2xl bg-slate-50/60 hover:bg-indigo-50/30 hover:border-indigo-400 cursor-pointer transition">
                    <div class="w-12 h-12 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center mb-3">
                        <i data-lucide="upload-cloud" class="w-6 h-6"></i>
                    </div>
                    <p class="text-sm font-semibold text-slate-700">Clique para selecionar um arquivo</p>
                    <p class="text-xs text-slate-500 mt-1">Vídeos (MP4, WebM) ou Imagens (JPG, PNG, WebP) até 64MB</p>
                    <input id="media_file" name="media_file" type="file" required accept="image/jpeg,image/png,image/webp,video/mp4,video/webm" class="sr-only" onchange="previewSelectedFile(this)">
                </label>

                <div id="file-preview-box" class="hidden mt-3 p-3 rounded-xl border border-slate-200 bg-slate-50 flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3 min-w-0">
                        <i data-lucide="file-check" class="w-5 h-5 text-emerald-600 shrink-0"></i>
                        <div class="min-w-0">
                            <div id="file-preview-name" class="text-sm font-semibold text-slate-800 truncate"></div>
                            <div id="file-preview-size" class="text-xs text-slate-500"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Copy / Legenda -->
            <div>
                <label for="copy_text" class="block text-xs font-semibold text-slate-700 uppercase tracking-wide mb-1.5">Copy / Legenda da Publicação (Opcional)</label>
                <textarea id="copy_text" name="copy_text" rows="5"
                    placeholder="Digite aqui a legenda que acompanhará o post ou anúncio..."
                    class="w-full rounded-xl border border-slate-200 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500"><?php echo isset($_POST['copy_text']) ? e($_POST['copy_text']) : ''; ?></textarea>
            </div>

            <div class="pt-2 flex items-center justify-end gap-3">
                <a href="dashboard.php" class="px-4 py-2.5 rounded-xl border border-slate-200 text-sm font-semibold text-slate-600 hover:bg-slate-50 transition">
                    Cancelar
                </a>
                <button type="submit" class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold shadow-sm shadow-indigo-600/20 transition">
                    <i data-lucide="send" class="w-4 h-4"></i>
                    <span>Salvar e Gerar Link Mágico</span>
                </button>
            </div>
        </form>
    <?php endif; ?>
</main>

<?php
$extraScripts = <<<'HTML'
<script>
function previewSelectedFile(input) {
    var box = document.getElementById('file-preview-box');
    var nameEl = document.getElementById('file-preview-name');
    var sizeEl = document.getElementById('file-preview-size');

    if (input.files && input.files[0]) {
        var file = input.files[0];
        var sizeMB = (file.size / (1024 * 1024)).toFixed(2);
        nameEl.textContent = file.name;
        sizeEl.textContent = sizeMB + ' MB';
        box.classList.remove('hidden');
    } else {
        box.classList.add('hidden');
    }
}
</script>
HTML;
render_footer($extraScripts);
?>
