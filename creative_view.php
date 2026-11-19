<?php
/**
 * AprovaAI - Visão da Agência do Criativo
 * Player/Imagem, Edição de Copy, Link Mágico, Resposta da Agência e Histórico clicável.
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

$creativeId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $pdo->prepare(
    'SELECT c.*, cl.name AS client_name
     FROM creatives c
     INNER JOIN clients cl ON cl.id = c.client_id
     WHERE c.id = ? AND c.tenant_id = ?
     LIMIT 1'
);
$stmt->execute(array($creativeId, $tenantId));
$creative = $stmt->fetch();

if (!$creative) {
    set_flash('error', 'Criativo não encontrado ou sem permissão de acesso.');
    redirect('dashboard.php');
}

$fbStmt = $pdo->prepare(
    'SELECT * FROM feedbacks WHERE creative_id = ? ORDER BY created_at DESC, id DESC'
);
$fbStmt->execute(array($creativeId));
$feedbacks = $fbStmt->fetchAll();

$magicLink = base_url('review.php?token=' . $creative['public_token']);
$csrfToken = generate_csrf_token();

render_head(e($creative['title']) . ' — AprovaAI');
render_navbar('dashboard');
?>

<main class="max-w-7xl mx-auto w-full px-4 sm:px-6 lg:px-8 py-8 flex-1">
    <?php render_flash_alert(); ?>

    <!-- Breadcrumb e Ações Principais -->
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 mb-6">
        <div>
            <a href="dashboard.php" class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 hover:text-indigo-600 mb-2 transition">
                <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i> Voltar aos Criativos
            </a>
            <div class="flex flex-wrap items-center gap-3">
                <h1 class="text-2xl font-bold text-slate-900 tracking-tight"><?php echo e($creative['title']); ?></h1>
                <div id="status-badge-container">
                    <?php echo render_status_badge($creative['status']); ?>
                </div>
            </div>
            <p class="text-sm text-slate-500 mt-1">
                Cliente: <span class="font-semibold text-slate-700"><?php echo e($creative['client_name']); ?></span>
                &middot; Criado em <?php echo e(format_datetime_br($creative['created_at'])); ?>
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2.5">
            <button type="button" onclick="copyMagicLink('<?php echo e($magicLink); ?>', this)"
                class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold shadow-sm shadow-indigo-600/20 transition">
                <i data-lucide="link" class="w-4 h-4"></i>
                <span>Copiar Link Mágico</span>
            </button>

            <a href="<?php echo e($magicLink); ?>" target="_blank" rel="noopener"
                class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 text-sm font-semibold transition">
                <i data-lucide="external-link" class="w-4 h-4"></i>
                <span>Abrir Visão do Cliente</span>
            </a>

            <?php if ($creative['status'] !== 'pending'): ?>
                <button type="button" onclick="resetToPending()"
                    class="inline-flex items-center gap-1.5 px-3.5 py-2.5 rounded-xl border border-amber-200 bg-amber-50 hover:bg-amber-100 text-amber-800 text-sm font-semibold transition">
                    <i data-lucide="rotate-ccw" class="w-4 h-4"></i>
                    <span>Marcar como Pendente</span>
                </button>
            <?php endif; ?>

            <button type="button" onclick="deleteCreative()"
                class="inline-flex items-center gap-1.5 px-3.5 py-2.5 rounded-xl border border-rose-200 bg-white hover:bg-rose-50 text-rose-600 text-sm font-semibold transition" title="Excluir criativo">
                <i data-lucide="trash-2" class="w-4 h-4"></i>
            </button>
        </div>
    </div>

    <!-- Layout 2 Colunas -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
        <!-- Coluna Esquerda: Mídia + Edição de Copy -->
        <div class="lg:col-span-7 space-y-6">
            <!-- Player / Imagem -->
            <div class="bg-slate-900 rounded-2xl overflow-hidden shadow-md border border-slate-200/80">
                <?php if ($creative['media_type'] === 'video'): ?>
                    <video id="creative-video-player" src="<?php echo e($creative['file_path']); ?>" controls playsinline class="w-full max-h-[540px] mx-auto bg-black"></video>
                <?php else: ?>
                    <img src="<?php echo e($creative['file_path']); ?>" alt="<?php echo e($creative['title']); ?>" class="w-full max-h-[600px] object-contain mx-auto bg-slate-950">
                <?php endif; ?>
            </div>

            <!-- Editor de Copy / Legenda -->
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="text-base font-bold text-slate-900 flex items-center gap-2">
                        <i data-lucide="align-left" class="w-4 h-4 text-indigo-600"></i>
                        <span>Copy / Legenda da Publicação</span>
                    </h2>
                    <button type="button" onclick="saveCopyText()" id="btn-save-copy"
                        class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-lg bg-slate-900 hover:bg-slate-800 text-white text-xs font-semibold transition">
                        <i data-lucide="save" class="w-3.5 h-3.5"></i>
                        <span>Salvar Legenda</span>
                    </button>
                </div>
                <textarea id="copy-text-input" rows="5" placeholder="Adicione ou edite a legenda deste criativo..."
                    class="w-full rounded-xl border border-slate-200 px-3.5 py-2.5 text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500"><?php echo e($creative['copy_text']); ?></textarea>
                <p class="text-xs text-slate-400 mt-2">As alterações salvas aqui ficam visíveis imediatamente no Link Mágico do cliente.</p>
            </div>
        </div>

        <!-- Coluna Direita: Resposta da Agência + Histórico -->
        <div class="lg:col-span-5 space-y-6">
            <!-- Formulário de Comentário da Agência -->
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6">
                <h2 class="text-base font-bold text-slate-900 mb-3 flex items-center gap-2">
                    <i data-lucide="message-square-plus" class="w-4 h-4 text-indigo-600"></i>
                    <span>Adicionar Nota / Resposta da Agência</span>
                </h2>

                <form id="agency-comment-form" onsubmit="submitAgencyComment(event)" class="space-y-3">
                    <?php if ($creative['media_type'] === 'video'): ?>
                        <div class="flex items-center gap-2">
                            <button type="button" onclick="captureAgencyTimestamp()"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-indigo-50 hover:bg-indigo-100 text-indigo-700 text-xs font-semibold transition">
                                <i data-lucide="clock" class="w-3.5 h-3.5"></i>
                                <span>Marcar tempo atual</span>
                            </button>
                            <input type="text" id="agency-timestamp" placeholder="00:00" maxlength="15"
                                class="w-24 rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs font-mono text-center focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                        </div>
                    <?php endif; ?>

                    <textarea id="agency-comment-input" rows="3" required placeholder="Responda a um pedido de ajuste ou deixe uma observação para o cliente..."
                        class="w-full rounded-xl border border-slate-200 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500"></textarea>

                    <button type="submit" id="btn-agency-comment"
                        class="w-full py-2.5 px-4 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold transition flex items-center justify-center gap-2">
                        <i data-lucide="send" class="w-4 h-4"></i>
                        <span>Publicar Comentário</span>
                    </button>
                </form>
            </div>

            <!-- Histórico de Feedbacks -->
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-base font-bold text-slate-900 flex items-center gap-2">
                        <i data-lucide="history" class="w-4 h-4 text-indigo-600"></i>
                        <span>Histórico de Aprovação</span>
                    </h2>
                    <span class="text-xs font-semibold text-slate-400"><?php echo count($feedbacks); ?> registro(s)</span>
                </div>

                <?php render_feedback_timeline($feedbacks, $creative['media_type']); ?>
            </div>
        </div>
    </div>
</main>

<?php
$creativeIdJs = (int)$creative['id'];
$csrfJs       = e($csrfToken);
$extraScripts = <<<HTML
<script>
var CREATIVE_ID = {$creativeIdJs};
var CSRF_TOKEN  = '{$csrfJs}';

function captureAgencyTimestamp() {
    var video = document.getElementById('creative-video-player');
    var input = document.getElementById('agency-timestamp');
    if (video && input) {
        input.value = formatSecondsToTimestamp(video.currentTime);
    }
}

function saveCopyText() {
    var btn = document.getElementById('btn-save-copy');
    var copyVal = document.getElementById('copy-text-input').value;
    btn.disabled = true;

    var formData = new FormData();
    formData.append('action', 'update_copy');
    formData.append('creative_id', CREATIVE_ID);
    formData.append('copy_text', copyVal);
    formData.append('csrf_token', CSRF_TOKEN);

    fetch('api/action.php', { method: 'POST', body: formData })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (data.success) {
                showToast(data.message || 'Legenda atualizada!', 'success');
            } else {
                showToast(data.error || 'Erro ao salvar legenda.', 'error');
            }
        })
        .catch(function() {
            showToast('Erro de comunicação com o servidor.', 'error');
        })
        .finally(function() {
            btn.disabled = false;
        });
}

function submitAgencyComment(e) {
    e.preventDefault();
    var btn = document.getElementById('btn-agency-comment');
    var commentEl = document.getElementById('agency-comment-input');
    var tsEl = document.getElementById('agency-timestamp');

    var commentVal = commentEl.value.trim();
    if (!commentVal) return;

    btn.disabled = true;
    var formData = new FormData();
    formData.append('action', 'agency_comment');
    formData.append('creative_id', CREATIVE_ID);
    formData.append('comment', commentVal);
    if (tsEl && tsEl.value.trim() !== '') {
        formData.append('video_timestamp', tsEl.value.trim());
    }
    formData.append('csrf_token', CSRF_TOKEN);

    fetch('api/action.php', { method: 'POST', body: formData })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (data.success) {
                window.location.reload();
            } else {
                showToast(data.error || 'Erro ao publicar comentário.', 'error');
                btn.disabled = false;
            }
        })
        .catch(function() {
            showToast('Erro de comunicação com o servidor.', 'error');
            btn.disabled = false;
        });
}

function resetToPending() {
    if (!confirm('Deseja marcar este criativo como "Aguardando Aprovação" novamente?')) return;

    var formData = new FormData();
    formData.append('action', 'reset_pending');
    formData.append('creative_id', CREATIVE_ID);
    formData.append('csrf_token', CSRF_TOKEN);

    fetch('api/action.php', { method: 'POST', body: formData })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (data.success) {
                window.location.reload();
            } else {
                showToast(data.error || 'Erro ao atualizar status.', 'error');
            }
        })
        .catch(function() {
            showToast('Erro de comunicação com o servidor.', 'error');
        });
}

function deleteCreative() {
    if (!confirm('Tem certeza que deseja excluir este criativo permanentemente? Esta ação não pode ser desfeita.')) return;

    var formData = new FormData();
    formData.append('action', 'delete_creative');
    formData.append('creative_id', CREATIVE_ID);
    formData.append('csrf_token', CSRF_TOKEN);

    fetch('api/action.php', { method: 'POST', body: formData })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (data.success) {
                window.location.href = 'dashboard.php';
            } else {
                showToast(data.error || 'Erro ao excluir criativo.', 'error');
            }
        })
        .catch(function() {
            showToast('Erro de comunicação com o servidor.', 'error');
        });
}
</script>
HTML;
render_footer($extraScripts);
?>
