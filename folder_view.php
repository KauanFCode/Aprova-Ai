<?php
/**
 * AprovaAI - Visão Interna da Pasta / Campanha (Agência)
 * Exibe progresso, link mágico da pasta e grid de criativos vinculados.
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

$folderId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$folder   = get_folder_by_id($pdo, $folderId, $tenantId);

if (!$folder) {
    set_flash('error', 'Pasta não encontrada ou sem permissão de acesso.');
    redirect('dashboard.php');
}

$creativesStmt = $pdo->prepare(
    'SELECT c.*, cl.name AS client_name,
            (SELECT COUNT(*) FROM feedbacks f WHERE f.creative_id = c.id) AS feedback_count
     FROM creatives c
     INNER JOIN clients cl ON cl.id = c.client_id
     WHERE c.folder_id = ? AND c.tenant_id = ?
     ORDER BY c.id ASC'
);
$creativesStmt->execute(array($folderId, $tenantId));
$creatives = $creativesStmt->fetchAll();

$progress        = get_folder_progress($folder['approved_creatives'], $folder['total_creatives']);
$folderMagicLink = base_url('review.php?folder=' . $folder['public_token']);
$csrfToken       = generate_csrf_token();

$barColor = ($progress['total'] > 0 && $progress['approved'] === $progress['total'])
    ? 'bg-emerald-500'
    : 'bg-indigo-600';

render_head(e($folder['name']) . ' — Pasta | AprovaAI');
render_navbar('dashboard');
?>

<main class="max-w-7xl mx-auto w-full px-4 sm:px-6 lg:px-8 py-8 flex-1">
    <?php render_flash_alert(); ?>

    <!-- Cabeçalho da Pasta -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6 mb-8">
        <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-5">
            <div class="space-y-2 flex-1">
                <a href="dashboard.php" class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 hover:text-indigo-600 transition">
                    <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i> Voltar ao Dashboard
                </a>
                <div class="flex flex-wrap items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center shrink-0">
                        <i data-lucide="folder-kanban" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <div class="text-xs font-semibold text-indigo-600 uppercase tracking-wide">
                            Cliente: <?php echo e($folder['client_name']); ?>
                        </div>
                        <h1 class="text-2xl font-bold text-slate-900 tracking-tight"><?php echo e($folder['name']); ?></h1>
                    </div>
                </div>
                <?php if (!empty($folder['description'])): ?>
                    <p class="text-sm text-slate-600 whitespace-pre-line pt-1"><?php echo e($folder['description']); ?></p>
                <?php endif; ?>
            </div>

            <div class="flex flex-wrap items-center gap-2.5 shrink-0">
                <button type="button" onclick="copyMagicLink('<?php echo e($folderMagicLink); ?>', this)"
                    class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold shadow-sm shadow-indigo-600/20 transition">
                    <i data-lucide="link" class="w-4 h-4"></i>
                    <span>Copiar Link Mágico da Pasta</span>
                </button>

                <a href="<?php echo e($folderMagicLink); ?>" target="_blank" rel="noopener"
                    class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 text-sm font-semibold transition">
                    <i data-lucide="external-link" class="w-4 h-4"></i>
                    <span>Visão do Cliente</span>
                </a>

                <a href="creative_new.php?folder_id=<?php echo (int)$folder['id']; ?>"
                    class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-white text-sm font-semibold transition">
                    <i data-lucide="plus" class="w-4 h-4"></i>
                    <span>+ Adicionar Criativos</span>
                </a>

                <button type="button" onclick="deleteFolder()"
                    class="inline-flex items-center justify-center p-2.5 rounded-xl border border-rose-200 bg-white hover:bg-rose-50 text-rose-600 transition" title="Excluir pasta (criativos viram avulsos)">
                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                </button>
            </div>
        </div>

        <!-- Barra de Progresso -->
        <div class="mt-6 pt-5 border-t border-slate-100">
            <div class="flex items-center justify-between text-xs font-semibold text-slate-600 mb-2">
                <span><?php echo e($progress['label']); ?></span>
                <span><?php echo $progress['percent']; ?>%</span>
            </div>
            <div class="w-full h-2.5 rounded-full bg-slate-100 overflow-hidden">
                <div class="h-full rounded-full transition-all duration-300 <?php echo $barColor; ?>" style="width: <?php echo $progress['percent']; ?>%"></div>
            </div>
        </div>
    </div>

    <!-- Grid de Criativos da Pasta -->
    <?php if (empty($creatives)): ?>
        <div class="bg-white rounded-2xl border border-dashed border-slate-300 p-12 text-center">
            <div class="w-14 h-14 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center mx-auto mb-4">
                <i data-lucide="upload-cloud" class="w-7 h-7"></i>
            </div>
            <h2 class="text-lg font-bold text-slate-900">Esta pasta ainda está vazia</h2>
            <p class="text-sm text-slate-500 mt-1 max-w-md mx-auto">
                Envie um ou vários vídeos e imagens estáticas de uma só vez para esta campanha.
            </p>
            <div class="mt-6">
                <a href="creative_new.php?folder_id=<?php echo (int)$folder['id']; ?>" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold shadow-sm transition">
                    <i data-lucide="plus" class="w-4 h-4"></i>
                    <span>Adicionar Criativos nesta Pasta</span>
                </a>
            </div>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($creatives as $creative): ?>
                <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm hover:shadow-md transition overflow-hidden flex flex-col">
                    <a href="creative_view.php?id=<?php echo (int)$creative['id']; ?>" class="relative block bg-slate-900 aspect-video overflow-hidden group">
                        <?php if ($creative['media_type'] === 'video'): ?>
                            <video src="<?php echo e($creative['file_path']); ?>" class="w-full h-full object-cover opacity-90 group-hover:scale-105 transition duration-300" muted preload="metadata"></video>
                            <div class="absolute inset-0 flex items-center justify-center bg-black/20 group-hover:bg-black/30 transition">
                                <span class="w-11 h-11 rounded-full bg-white/90 text-slate-900 flex items-center justify-center shadow-lg">
                                    <i data-lucide="play" class="w-5 h-5 ml-0.5"></i>
                                </span>
                            </div>
                        <?php else: ?>
                            <img src="<?php echo e($creative['file_path']); ?>" alt="<?php echo e($creative['title']); ?>" class="w-full h-full object-cover group-hover:scale-105 transition duration-300">
                        <?php endif; ?>
                        <div class="absolute top-2.5 right-2.5"><?php echo render_status_badge($creative['status']); ?></div>
                    </a>

                    <div class="p-4 flex-1 flex flex-col justify-between gap-3">
                        <div>
                            <a href="creative_view.php?id=<?php echo (int)$creative['id']; ?>" class="block text-base font-bold text-slate-900 hover:text-indigo-600 transition line-clamp-1">
                                <?php echo e($creative['title']); ?>
                            </a>
                            <?php if (!empty($creative['copy_text'])): ?>
                                <p class="mt-1.5 text-xs text-slate-500 line-clamp-2"><?php echo e($creative['copy_text']); ?></p>
                            <?php else: ?>
                                <p class="mt-1.5 text-xs text-slate-400 italic">Sem legenda cadastrada</p>
                            <?php endif; ?>
                            <div class="mt-2.5 flex items-center gap-3 text-xs text-slate-500">
                                <span class="inline-flex items-center gap-1"><i data-lucide="message-square" class="w-3.5 h-3.5"></i> <?php echo (int)$creative['feedback_count']; ?></span>
                                <span class="inline-flex items-center gap-1"><i data-lucide="calendar" class="w-3.5 h-3.5"></i> <?php echo e(format_datetime_br($creative['updated_at'])); ?></span>
                            </div>
                        </div>

                        <div class="pt-3 border-t border-slate-100 flex items-center gap-2">
                            <a href="creative_view.php?id=<?php echo (int)$creative['id']; ?>" class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl text-xs font-semibold bg-slate-100 hover:bg-slate-200 text-slate-700 transition">
                                <i data-lucide="eye" class="w-3.5 h-3.5"></i> Revisão Detalhada
                            </a>
                            <button type="button" onclick="copyMagicLink('<?php echo e(base_url('review.php?token=' . $creative['public_token'])); ?>', this)" class="inline-flex items-center justify-center gap-1 px-3 py-2 rounded-xl text-xs font-semibold bg-indigo-50 hover:bg-indigo-100 text-indigo-700 transition" title="Copiar link individual">
                                <i data-lucide="link" class="w-3.5 h-3.5"></i>
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

<?php
$folderIdJs = (int)$folder['id'];
$csrfJs     = e($csrfToken);
$extraScripts = <<<HTML
<script>
function deleteFolder() {
    if (!confirm('Deseja excluir esta pasta? Os criativos dentro dela NÃO serão apagados, apenas voltarão a ficar como avulsos.')) return;

    var formData = new FormData();
    formData.append('action', 'delete_folder');
    formData.append('folder_id', {$folderIdJs});
    formData.append('csrf_token', '{$csrfJs}');

    fetch('api/action.php', { method: 'POST', body: formData })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (data.success) {
                window.location.href = 'dashboard.php';
            } else {
                showToast(data.error || 'Erro ao excluir pasta.', 'error');
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
