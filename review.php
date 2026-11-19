<?php
/**
 * AprovaAI - Visão Pública do Cliente
 * Suporta Link Mágico individual (?token=HASH) e Link Mágico de Pasta/Campanha (?folder=HASH).
 * Mobile-first, sem necessidade de login.
 * Compatível estritamente com PHP 7.4+
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/components.php';

$pdo         = get_pdo();
$token       = isset($_GET['token']) ? trim((string)$_GET['token']) : '';
$folderToken = isset($_GET['folder']) ? trim((string)$_GET['folder']) : '';
$csrfToken   = generate_csrf_token();

// ============================================================================
// MODO 1: VISUALIZAÇÃO DE PASTA / CAMPANHA INTEIRA (?folder=HASH)
// ============================================================================
if ($folderToken !== '') {
    $folder = get_folder_by_token($pdo, $folderToken);

    if (!$folder) {
        render_head('Link de Pasta Inválido — AprovaAI');
        echo '<main class="flex-1 flex items-center justify-center px-4 py-16">';
        echo '<div class="max-w-md w-full bg-white rounded-2xl border border-slate-200 p-8 text-center shadow-sm">';
        echo '<div class="w-14 h-14 rounded-2xl bg-rose-50 text-rose-600 flex items-center justify-center mx-auto mb-4">';
        echo '<i data-lucide="folder-x" class="w-7 h-7"></i>';
        echo '</div>';
        echo '<h1 class="text-xl font-bold text-slate-900">Pasta não encontrada</h1>';
        echo '<p class="text-sm text-slate-500 mt-2">Este link de campanha pode ter sido removido pela agência. Solicite um novo link.</p>';
        echo '</div>';
        echo '</main>';
        render_footer();
        exit;
    }

    $creativesStmt = $pdo->prepare(
        'SELECT * FROM creatives WHERE folder_id = ? ORDER BY id ASC'
    );
    $creativesStmt->execute(array((int)$folder['id']));
    $creatives = $creativesStmt->fetchAll();

    // Carrega feedbacks de cada criativo da pasta
    $feedbacksByCreative = array();
    $remainingToApprove  = 0;
    $fbStmt = $pdo->prepare('SELECT * FROM feedbacks WHERE creative_id = ? ORDER BY created_at DESC, id DESC');

    foreach ($creatives as $cItem) {
        $cid = (int)$cItem['id'];
        if ($cItem['status'] !== 'approved') {
            $remainingToApprove++;
        }
        $fbStmt->execute(array($cid));
        $feedbacksByCreative[$cid] = $fbStmt->fetchAll();
    }

    $progress = get_folder_progress($folder['approved_creatives'], $folder['total_creatives']);
    $barColor = ($progress['total'] > 0 && $progress['approved'] === $progress['total'])
        ? 'bg-emerald-500'
        : 'bg-indigo-600';

    render_head(e($folder['name']) . ' — Aprovação | AprovaAI');
?>
    <header class="bg-white border-b border-slate-200 sticky top-0 z-30">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 h-16 flex items-center justify-between gap-3">
            <div class="flex items-center gap-2.5 min-w-0">
                <div class="w-9 h-9 rounded-xl bg-indigo-600 text-white flex items-center justify-center shrink-0">
                    <i data-lucide="sparkles" class="w-5 h-5"></i>
                </div>
                <div class="min-w-0">
                    <div class="text-sm font-bold text-slate-900 truncate"><?php echo e($folder['tenant_name']); ?></div>
                    <div class="text-[11px] text-slate-400 truncate">Portal de Aprovação &bull; AprovaAI</div>
                </div>
            </div>
            <span class="shrink-0 inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-indigo-50 text-indigo-700 border border-indigo-200">
                <i data-lucide="check-circle-2" class="w-3.5 h-3.5"></i>
                <span><?php echo $progress['approved']; ?> de <?php echo $progress['total']; ?> aprovadas</span>
            </span>
        </div>
    </header>

    <main class="max-w-5xl mx-auto w-full px-4 sm:px-6 py-6 sm:py-8 flex-1 space-y-6">
        <!-- Resumo da Pasta + Aprovação em Lote -->
        <section class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-5 sm:p-6 space-y-4">
            <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4">
                <div>
                    <span class="inline-block text-xs font-semibold text-indigo-600 uppercase tracking-wide mb-1">
                        Para: <?php echo e($folder['client_name']); ?>
                    </span>
                    <h1 class="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight"><?php echo e($folder['name']); ?></h1>
                    <?php if (!empty($folder['description'])): ?>
                        <p class="text-sm text-slate-600 mt-1.5 whitespace-pre-line"><?php echo e($folder['description']); ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Progresso -->
            <div>
                <div class="flex items-center justify-between text-xs font-semibold text-slate-600 mb-1.5">
                    <span><?php echo e($progress['label']); ?></span>
                    <span><?php echo $progress['percent']; ?>%</span>
                </div>
                <div class="w-full h-2.5 rounded-full bg-slate-100 overflow-hidden">
                    <div class="h-full rounded-full transition-all duration-300 <?php echo $barColor; ?>" style="width: <?php echo $progress['percent']; ?>%"></div>
                </div>
            </div>

            <!-- Identificação do Cliente + Botão Global -->
            <div class="pt-3 border-t border-slate-100 flex flex-col sm:flex-row items-stretch sm:items-end gap-3">
                <div class="flex-1">
                    <label for="global-author-name" class="block text-xs font-semibold text-slate-700 uppercase tracking-wide mb-1">Seu Nome *</label>
                    <input type="text" id="global-author-name" maxlength="100" required
                        placeholder="Ex: João (Diretor)"
                        oninput="syncAuthorName(this.value)"
                        class="w-full rounded-xl border border-slate-200 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                </div>

                <?php if ($remainingToApprove > 0): ?>
                    <button type="button" onclick="approveFolderAll()" id="btn-approve-all"
                        class="sm:w-auto py-2.5 px-5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold shadow-sm shadow-emerald-600/20 transition flex items-center justify-center gap-2">
                        <i data-lucide="check-check" class="w-4 h-4"></i>
                        <span>Aprovar Todos os Criativos Restantes (<?php echo $remainingToApprove; ?>)</span>
                    </button>
                <?php else: ?>
                    <div class="py-2.5 px-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm font-semibold flex items-center justify-center gap-2">
                        <i data-lucide="check-circle-2" class="w-4 h-4"></i>
                        <span>Todos os criativos aprovados!</span>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- Lista em Feed de Criativos da Pasta -->
        <?php if (empty($creatives)): ?>
            <div class="bg-white rounded-2xl border border-dashed border-slate-300 p-10 text-center">
                <p class="text-sm text-slate-500">A agência ainda não adicionou criativos nesta pasta.</p>
            </div>
        <?php else: ?>
            <div class="space-y-8">
                <?php foreach ($creatives as $idx => $item): ?>
                    <?php
                        $cid = (int)$item['id'];
                        $cToken = e($item['public_token']);
                        $videoDomId = 'creative-video-player-' . $cid;
                        $itemFeedbacks = isset($feedbacksByCreative[$cid]) ? $feedbacksByCreative[$cid] : array();
                    ?>
                    <article class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
                        <!-- Cabeçalho do Card -->
                        <div class="px-5 py-4 border-b border-slate-100 flex flex-wrap items-center justify-between gap-2">
                            <div class="flex items-center gap-2.5">
                                <span class="w-7 h-7 rounded-lg bg-slate-100 text-slate-700 text-xs font-bold flex items-center justify-center">
                                    #<?php echo ($idx + 1); ?>
                                </span>
                                <h2 class="text-base sm:text-lg font-bold text-slate-900"><?php echo e($item['title']); ?></h2>
                            </div>
                            <?php echo render_status_badge($item['status']); ?>
                        </div>

                        <div class="grid grid-cols-1 lg:grid-cols-12">
                            <!-- Mídia + Copy -->
                            <div class="lg:col-span-7 p-5 space-y-4 border-b lg:border-b-0 lg:border-r border-slate-100">
                                <div class="bg-slate-900 rounded-xl overflow-hidden">
                                    <?php if ($item['media_type'] === 'video'): ?>
                                        <video id="<?php echo $videoDomId; ?>" src="<?php echo e($item['file_path']); ?>" controls playsinline class="w-full max-h-[480px] mx-auto bg-black"></video>
                                    <?php else: ?>
                                        <img src="<?php echo e($item['file_path']); ?>" alt="<?php echo e($item['title']); ?>" class="w-full max-h-[520px] object-contain mx-auto bg-slate-950">
                                    <?php endif; ?>
                                </div>

                                <?php if (!empty($item['copy_text'])): ?>
                                    <div class="rounded-xl bg-slate-50 border border-slate-200/80 p-4">
                                        <div class="flex items-center justify-between gap-2 mb-2">
                                            <span class="text-xs font-bold text-slate-700 uppercase tracking-wide flex items-center gap-1.5">
                                                <i data-lucide="align-left" class="w-3.5 h-3.5 text-indigo-600"></i> Legenda / Copy
                                            </span>
                                            <button type="button" onclick="copyTextById('caption-<?php echo $cid; ?>')" class="inline-flex items-center gap-1 px-2 py-1 rounded-md bg-white border border-slate-200 text-xs font-semibold text-slate-700 hover:bg-slate-100 transition">
                                                <i data-lucide="copy" class="w-3 h-3"></i> Copiar
                                            </button>
                                        </div>
                                        <p id="caption-<?php echo $cid; ?>" class="text-sm text-slate-700 whitespace-pre-line leading-relaxed"><?php echo e($item['copy_text']); ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Decisão Individual + Histórico -->
                            <div class="lg:col-span-5 p-5 space-y-5 flex flex-col justify-between">
                                <div class="space-y-3">
                                    <h3 class="text-sm font-bold text-slate-900">Avaliar esta peça</h3>

                                    <?php if ($item['media_type'] === 'video'): ?>
                                        <div class="flex items-center justify-between gap-2 bg-slate-50 p-2 rounded-xl border border-slate-200/70">
                                            <button type="button" onclick="captureFolderVideoTimestamp(<?php echo $cid; ?>)"
                                                class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold transition">
                                                <i data-lucide="clock" class="w-3.5 h-3.5"></i>
                                                <span>Marcar momento</span>
                                            </button>
                                            <input type="text" id="ts-<?php echo $cid; ?>" placeholder="00:00" maxlength="15"
                                                class="w-20 rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs font-mono text-center">
                                        </div>
                                    <?php endif; ?>

                                    <textarea id="comment-<?php echo $cid; ?>" rows="3"
                                        placeholder="Opcional para aprovar. Obrigatório para solicitar alteração..."
                                        class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500"></textarea>

                                    <div class="grid grid-cols-2 gap-2">
                                        <button type="button" onclick="submitFolderItemDecision(<?php echo $cid; ?>, '<?php echo $cToken; ?>', 'approve')" data-item-btn
                                            class="py-2.5 px-3 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold transition flex items-center justify-center gap-1.5">
                                            <i data-lucide="check-circle-2" class="w-4 h-4"></i>
                                            <span>Aprovar</span>
                                        </button>

                                        <button type="button" onclick="submitFolderItemDecision(<?php echo $cid; ?>, '<?php echo $cToken; ?>', 'request_changes')" data-item-btn
                                            class="py-2.5 px-3 rounded-xl bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 text-xs font-bold transition flex items-center justify-center gap-1.5">
                                            <i data-lucide="alert-circle" class="w-4 h-4"></i>
                                            <span>Solicitar Alteração</span>
                                        </button>
                                    </div>
                                </div>

                                <!-- Histórico compacto da peça -->
                                <?php if (!empty($itemFeedbacks)): ?>
                                    <div class="pt-4 border-t border-slate-100">
                                        <div class="text-xs font-bold text-slate-500 uppercase tracking-wide mb-2.5">
                                            Histórico (<?php echo count($itemFeedbacks); ?>)
                                        </div>
                                        <div class="space-y-2 max-h-60 overflow-y-auto pr-1">
                                            <?php foreach ($itemFeedbacks as $fb): ?>
                                                <div class="rounded-lg border border-slate-200/80 bg-slate-50/70 p-2.5 text-xs">
                                                    <div class="flex items-center justify-between gap-2 mb-1">
                                                        <span class="font-bold text-slate-800"><?php echo e($fb['author_name']); ?></span>
                                                        <span class="text-[10px] text-slate-400"><?php echo e(format_datetime_br($fb['created_at'])); ?></span>
                                                    </div>
                                                    <?php if (!empty($fb['video_timestamp']) && $item['media_type'] === 'video'): ?>
                                                        <button type="button" onclick="seekVideoTo('<?php echo e($fb['video_timestamp']); ?>', '<?php echo $videoDomId; ?>')"
                                                            class="mb-1 inline-flex items-center gap-1 px-1.5 py-0.5 rounded bg-indigo-600 text-white font-mono text-[10px] font-semibold">
                                                            <i data-lucide="play-circle" class="w-3 h-3"></i> <?php echo e($fb['video_timestamp']); ?>
                                                        </button>
                                                    <?php endif; ?>
                                                    <p class="text-slate-700 whitespace-pre-line"><?php echo e($fb['comment']); ?></p>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

<?php
    $folderTokenJs = e($folderToken);
    $csrfJs        = e($csrfToken);
    $extraScripts  = <<<HTML
<script>
var FOLDER_TOKEN = '{$folderTokenJs}';
var CSRF_TOKEN   = '{$csrfJs}';

document.addEventListener('DOMContentLoaded', function() {
    var savedName = localStorage.getItem('aprovaai_client_name');
    var nameInput = document.getElementById('global-author-name');
    if (savedName && nameInput && !nameInput.value) {
        nameInput.value = savedName;
    }
});

function syncAuthorName(val) {
    localStorage.setItem('aprovaai_client_name', val.trim());
}

function captureFolderVideoTimestamp(cid) {
    var video = document.getElementById('creative-video-player-' + cid);
    var input = document.getElementById('ts-' + cid);
    if (video && input) {
        input.value = formatSecondsToTimestamp(video.currentTime);
    }
}

function copyTextById(elId) {
    var el = document.getElementById(elId);
    if (!el) return;
    var text = el.textContent;
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(function() {
            showToast('Legenda copiada!', 'success');
        });
    } else {
        fallbackCopyText(text);
    }
}

function approveFolderAll() {
    var nameInput = document.getElementById('global-author-name');
    var authorName = nameInput ? nameInput.value.trim() : '';

    if (!authorName) {
        showToast('Informe seu nome no topo antes de aprovar os criativos.', 'warning');
        if (nameInput) nameInput.focus();
        return;
    }

    if (!confirm('Confirma a aprovação de todos os criativos restantes desta pasta?')) return;

    var btn = document.getElementById('btn-approve-all');
    if (btn) btn.disabled = true;

    var formData = new FormData();
    formData.append('action', 'approve_folder_all');
    formData.append('folder_token', FOLDER_TOKEN);
    formData.append('author_name', authorName);
    formData.append('csrf_token', CSRF_TOKEN);

    fetch('api/action.php', { method: 'POST', body: formData })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (data.success) {
                showToast(data.message || 'Criativos aprovados!', 'success');
                setTimeout(function() { window.location.reload(); }, 600);
            } else {
                showToast(data.error || 'Não foi possível aprovar em lote.', 'error');
                if (btn) btn.disabled = false;
            }
        })
        .catch(function() {
            showToast('Erro de comunicação. Tente novamente.', 'error');
            if (btn) btn.disabled = false;
        });
}

function submitFolderItemDecision(cid, itemToken, action) {
    var nameInput = document.getElementById('global-author-name');
    var authorName = nameInput ? nameInput.value.trim() : '';
    var commentEl = document.getElementById('comment-' + cid);
    var tsEl = document.getElementById('ts-' + cid);

    var comment = commentEl ? commentEl.value.trim() : '';

    if (!authorName) {
        showToast('Informe seu nome no topo da página antes de enviar.', 'warning');
        if (nameInput) {
            nameInput.focus();
            nameInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        return;
    }

    if (action === 'request_changes' && !comment) {
        showToast('Descreva o ajuste necessário no campo de comentário da peça.', 'warning');
        if (commentEl) commentEl.focus();
        return;
    }

    localStorage.setItem('aprovaai_client_name', authorName);

    var formData = new FormData();
    formData.append('action', action);
    formData.append('public_token', itemToken);
    formData.append('author_name', authorName);
    formData.append('comment', comment);
    if (tsEl && tsEl.value.trim() !== '') {
        formData.append('video_timestamp', tsEl.value.trim());
    }
    formData.append('csrf_token', CSRF_TOKEN);

    fetch('api/action.php', { method: 'POST', body: formData })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (data.success) {
                showToast(data.message || 'Registrado com sucesso!', 'success');
                setTimeout(function() { window.location.reload(); }, 600);
            } else {
                showToast(data.error || 'Erro ao enviar avaliação.', 'error');
            }
        })
        .catch(function() {
            showToast('Erro de comunicação. Tente novamente.', 'error');
        });
}
</script>
HTML;
    render_footer($extraScripts);
    exit;
}

// ============================================================================
// MODO 2: VISUALIZAÇÃO DE CRIATIVO INDIVIDUAL (?token=HASH)
// ============================================================================
$creative = false;
if ($token !== '') {
    $stmt = $pdo->prepare(
        'SELECT c.*, cl.name AS client_name, t.name AS tenant_name
         FROM creatives c
         INNER JOIN clients cl ON cl.id = c.client_id
         INNER JOIN tenants t ON t.id = c.tenant_id
         WHERE c.public_token = ?
         LIMIT 1'
    );
    $stmt->execute(array($token));
    $creative = $stmt->fetch();
}

if (!$creative) {
    render_head('Link Inválido — AprovaAI');
    echo '<main class="flex-1 flex items-center justify-center px-4 py-16">';
    echo '<div class="max-w-md w-full bg-white rounded-2xl border border-slate-200 p-8 text-center shadow-sm">';
    echo '<div class="w-14 h-14 rounded-2xl bg-rose-50 text-rose-600 flex items-center justify-center mx-auto mb-4">';
    echo '<i data-lucide="link-2-off" class="w-7 h-7"></i>';
    echo '</div>';
    echo '<h1 class="text-xl font-bold text-slate-900">Link de aprovação inválido</h1>';
    echo '<p class="text-sm text-slate-500 mt-2">Este link pode ter expirado ou o criativo foi removido pela agência. Solicite um novo link.</p>';
    echo '</div>';
    echo '</main>';
    render_footer();
    exit;
}

$fbStmt = $pdo->prepare(
    'SELECT * FROM feedbacks WHERE creative_id = ? ORDER BY created_at DESC, id DESC'
);
$fbStmt->execute(array((int)$creative['id']));
$feedbacks = $fbStmt->fetchAll();

render_head(e($creative['title']) . ' — Aprovação | AprovaAI');
?>

<!-- Cabeçalho Público Mobile-First -->
<header class="bg-white border-b border-slate-200 sticky top-0 z-30">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 h-16 flex items-center justify-between gap-3">
        <div class="flex items-center gap-2.5 min-w-0">
            <div class="w-9 h-9 rounded-xl bg-indigo-600 text-white flex items-center justify-center shrink-0">
                <i data-lucide="sparkles" class="w-5 h-5"></i>
            </div>
            <div class="min-w-0">
                <div class="text-sm font-bold text-slate-900 truncate"><?php echo e($creative['tenant_name']); ?></div>
                <div class="text-[11px] text-slate-400 truncate">Portal de Aprovação &bull; AprovaAI</div>
            </div>
        </div>
        <div id="public-status-badge" class="shrink-0">
            <?php echo render_status_badge($creative['status']); ?>
        </div>
    </div>
</header>

<main class="max-w-5xl mx-auto w-full px-4 sm:px-6 py-6 sm:py-8 flex-1 space-y-6">
    <!-- Título e Cliente -->
    <div>
        <span class="inline-block text-xs font-semibold text-indigo-600 uppercase tracking-wide mb-1">
            Para: <?php echo e($creative['client_name']); ?>
        </span>
        <h1 class="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight"><?php echo e($creative['title']); ?></h1>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
        <!-- Mídia + Copy -->
        <div class="lg:col-span-7 space-y-5">
            <div class="bg-slate-900 rounded-2xl overflow-hidden shadow-md border border-slate-200/80">
                <?php if ($creative['media_type'] === 'video'): ?>
                    <video id="creative-video-player" src="<?php echo e($creative['file_path']); ?>" controls playsinline class="w-full max-h-[540px] mx-auto bg-black"></video>
                <?php else: ?>
                    <img src="<?php echo e($creative['file_path']); ?>" alt="<?php echo e($creative['title']); ?>" class="w-full max-h-[580px] object-contain mx-auto bg-slate-950">
                <?php endif; ?>
            </div>

            <?php if (!empty($creative['copy_text'])): ?>
                <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-5">
                    <div class="flex items-center justify-between gap-2 mb-2.5">
                        <h2 class="text-sm font-bold text-slate-900 flex items-center gap-1.5">
                            <i data-lucide="align-left" class="w-4 h-4 text-indigo-600"></i>
                            <span>Legenda / Copy</span>
                        </h2>
                        <button type="button" onclick="copyCaptionText()" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold transition">
                            <i data-lucide="copy" class="w-3.5 h-3.5"></i>
                            <span>Copiar</span>
                        </button>
                    </div>
                    <p id="caption-content" class="text-sm text-slate-700 whitespace-pre-line leading-relaxed"><?php echo e($creative['copy_text']); ?></p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Painel de Decisão do Cliente + Histórico -->
        <div class="lg:col-span-5 space-y-6">
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-5 sm:p-6">
                <h2 class="text-base font-bold text-slate-900 mb-1">Sua Avaliação</h2>
                <p class="text-xs text-slate-500 mb-4">Aprove este material ou informe os ajustes necessários.</p>

                <div class="space-y-3.5">
                    <div>
                        <label for="client-author-name" class="block text-xs font-semibold text-slate-700 uppercase tracking-wide mb-1">Seu Nome *</label>
                        <input type="text" id="client-author-name" maxlength="100" required
                            placeholder="Ex: João (Diretor)"
                            class="w-full rounded-xl border border-slate-200 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                    </div>

                    <?php if ($creative['media_type'] === 'video'): ?>
                        <div class="flex items-center justify-between gap-2 bg-slate-50 p-2.5 rounded-xl border border-slate-200/70">
                            <button type="button" onclick="captureClientTimestamp()"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold transition">
                                <i data-lucide="clock" class="w-3.5 h-3.5"></i>
                                <span>Marcar momento do vídeo</span>
                            </button>
                            <input type="text" id="client-video-timestamp" placeholder="00:00" maxlength="15"
                                class="w-20 rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs font-mono text-center focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                        </div>
                    <?php endif; ?>

                    <div>
                        <label for="client-comment" class="block text-xs font-semibold text-slate-700 uppercase tracking-wide mb-1">
                            Comentário / Detalhes do Ajuste
                        </label>
                        <textarea id="client-comment" rows="3"
                            placeholder="Opcional para aprovar. Obrigatório ao solicitar ajustes ou comentar..."
                            class="w-full rounded-xl border border-slate-200 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500"></textarea>
                    </div>

                    <!-- Botões de Ação -->
                    <div class="pt-1 space-y-2.5">
                        <button type="button" onclick="submitClientDecision('approve')" data-action-btn
                            class="w-full py-3 px-4 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold shadow-sm shadow-emerald-600/20 transition flex items-center justify-center gap-2">
                            <i data-lucide="check-circle-2" class="w-4 h-4"></i>
                            <span>Aprovar Criativo</span>
                        </button>

                        <div class="grid grid-cols-2 gap-2.5">
                            <button type="button" onclick="submitClientDecision('request_changes')" data-action-btn
                                class="py-2.5 px-3 rounded-xl bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 text-xs sm:text-sm font-bold transition flex items-center justify-center gap-1.5">
                                <i data-lucide="alert-circle" class="w-4 h-4"></i>
                                <span>Solicitar Ajuste</span>
                            </button>

                            <button type="button" onclick="submitClientDecision('comment')" data-action-btn
                                class="py-2.5 px-3 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs sm:text-sm font-semibold transition flex items-center justify-center gap-1.5">
                                <i data-lucide="message-square" class="w-4 h-4"></i>
                                <span>Só Comentar</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Histórico -->
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-5 sm:p-6">
                <h2 class="text-base font-bold text-slate-900 mb-4 flex items-center gap-2">
                    <i data-lucide="history" class="w-4 h-4 text-indigo-600"></i>
                    <span>Histórico de Comentários</span>
                </h2>
                <?php render_feedback_timeline($feedbacks, $creative['media_type']); ?>
            </div>
        </div>
    </div>
</main>

<?php
$tokenJs = e($token);
$csrfJs  = e($csrfToken);
$extraScripts = <<<HTML
<script>
var PUBLIC_TOKEN = '{$tokenJs}';
var CSRF_TOKEN   = '{$csrfJs}';

document.addEventListener('DOMContentLoaded', function() {
    var savedName = localStorage.getItem('aprovaai_client_name');
    var nameInput = document.getElementById('client-author-name');
    if (savedName && nameInput && !nameInput.value) {
        nameInput.value = savedName;
    }
});

function captureClientTimestamp() {
    var video = document.getElementById('creative-video-player');
    var input = document.getElementById('client-video-timestamp');
    if (video && input) {
        input.value = formatSecondsToTimestamp(video.currentTime);
    }
}

function copyCaptionText() {
    var el = document.getElementById('caption-content');
    if (!el) return;
    var text = el.textContent;
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(function() {
            showToast('Legenda copiada!', 'success');
        });
    } else {
        fallbackCopyText(text);
    }
}

function submitClientDecision(action) {
    var nameInput = document.getElementById('client-author-name');
    var commentInput = document.getElementById('client-comment');
    var tsInput = document.getElementById('client-video-timestamp');

    var authorName = nameInput.value.trim();
    var comment = commentInput.value.trim();

    if (!authorName) {
        showToast('Por favor, informe seu nome antes de enviar.', 'warning');
        nameInput.focus();
        return;
    }

    if ((action === 'request_changes' || action === 'comment') && !comment) {
        showToast('Descreva o ajuste ou comentário no campo de texto.', 'warning');
        commentInput.focus();
        return;
    }

    localStorage.setItem('aprovaai_client_name', authorName);

    var buttons = document.querySelectorAll('[data-action-btn]');
    buttons.forEach(function(b) { b.disabled = true; });

    var formData = new FormData();
    formData.append('action', action);
    formData.append('public_token', PUBLIC_TOKEN);
    formData.append('author_name', authorName);
    formData.append('comment', comment);
    if (tsInput && tsInput.value.trim() !== '') {
        formData.append('video_timestamp', tsInput.value.trim());
    }
    formData.append('csrf_token', CSRF_TOKEN);

    fetch('api/action.php', { method: 'POST', body: formData })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (data.success) {
                showToast(data.message || 'Registrado com sucesso!', 'success');
                setTimeout(function() { window.location.reload(); }, 600);
            } else {
                showToast(data.error || 'Não foi possível enviar sua resposta.', 'error');
                buttons.forEach(function(b) { b.disabled = false; });
            }
        })
        .catch(function() {
            showToast('Erro de comunicação. Tente novamente.', 'error');
            buttons.forEach(function(b) { b.disabled = false; });
        });
}
</script>
HTML;
render_footer($extraScripts);
?>
