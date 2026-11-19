<?php
/**
 * AprovaAI - Dashboard da Agência
 * KPIs, Pastas/Campanhas Ativas, Criativos Avulsos, Modal de Nova Pasta e Filtros.
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

// ----------------------------------------------------------------------------
// KPIs Globais do Tenant (Pastas + Avulsos)
// ----------------------------------------------------------------------------
$kpiStmt = $pdo->prepare(
    "SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_count,
        SUM(CASE WHEN status = 'changes_requested' THEN 1 ELSE 0 END) AS changes_count,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) AS approved_count
     FROM creatives
     WHERE tenant_id = ?"
);
$kpiStmt->execute(array($tenantId));
$kpis = $kpiStmt->fetch();

$totalCount    = (int)$kpis['total'];
$pendingCount  = (int)$kpis['pending_count'];
$changesCount  = (int)$kpis['changes_count'];
$approvedCount = (int)$kpis['approved_count'];

// ----------------------------------------------------------------------------
// Lista de Clientes para Filtro e Modal de Nova Pasta
// ----------------------------------------------------------------------------
$clientsStmt = $pdo->prepare('SELECT id, name FROM clients WHERE tenant_id = ? ORDER BY name ASC');
$clientsStmt->execute(array($tenantId));
$clients = $clientsStmt->fetchAll();

// ----------------------------------------------------------------------------
// Filtros (Cliente e Status)
// ----------------------------------------------------------------------------
$filterClient = isset($_GET['client_id']) ? (int)$_GET['client_id'] : 0;
$filterStatus = isset($_GET['status']) ? trim((string)$_GET['status']) : '';
$allowedStatuses = array('pending', 'changes_requested', 'approved');
if (!in_array($filterStatus, $allowedStatuses, true)) {
    $filterStatus = '';
}

// Pastas / Campanhas Ativas
$folders = get_folders_by_tenant($pdo, $tenantId, $filterClient);

// Criativos Avulsos (folder_id IS NULL)
$sql = "SELECT c.*, cl.name AS client_name,
               (SELECT COUNT(*) FROM feedbacks f WHERE f.creative_id = c.id) AS feedback_count
        FROM creatives c
        INNER JOIN clients cl ON cl.id = c.client_id
        WHERE c.tenant_id = ? AND c.folder_id IS NULL";
$params = array($tenantId);

if ($filterClient > 0) {
    $sql .= ' AND c.client_id = ?';
    $params[] = $filterClient;
}

if ($filterStatus !== '') {
    $sql .= ' AND c.status = ?';
    $params[] = $filterStatus;
}

$sql .= ' ORDER BY c.updated_at DESC, c.id DESC';

$creativesStmt = $pdo->prepare($sql);
$creativesStmt->execute($params);
$creatives = $creativesStmt->fetchAll();

$csrfToken = generate_csrf_token();

render_head('Dashboard — AprovaAI');
render_navbar('dashboard');
?>

<main class="max-w-7xl mx-auto w-full px-4 sm:px-6 lg:px-8 py-8 flex-1">
    <?php render_flash_alert(); ?>

    <!-- Cabeçalho da Página -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Criativos &amp; Campanhas</h1>
            <p class="text-sm text-slate-500 mt-1">Organize campanhas em pastas com link único ou envie criativos avulsos.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2.5">
            <button type="button" onclick="openFolderModal()"
                class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl border border-indigo-200 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 text-sm font-semibold transition">
                <i data-lucide="folder-plus" class="w-4 h-4"></i>
                <span>+ Nova Pasta / Campanha</span>
            </button>
            <a href="creative_new.php"
                class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold shadow-sm shadow-indigo-600/20 transition">
                <i data-lucide="plus" class="w-4 h-4"></i>
                <span>+ Novo Criativo</span>
            </a>
        </div>
    </div>

    <!-- KPIs -->
    <section class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <div class="bg-white rounded-2xl border border-slate-200/80 p-4 sm:p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Total</span>
                <span class="w-8 h-8 rounded-lg bg-slate-100 text-slate-600 flex items-center justify-center">
                    <i data-lucide="layers" class="w-4 h-4"></i>
                </span>
            </div>
            <div class="mt-2 text-2xl sm:text-3xl font-bold text-slate-900"><?php echo $totalCount; ?></div>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200/80 p-4 sm:p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-amber-600 uppercase tracking-wide">Aguardando</span>
                <span class="w-8 h-8 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center">
                    <i data-lucide="clock" class="w-4 h-4"></i>
                </span>
            </div>
            <div class="mt-2 text-2xl sm:text-3xl font-bold text-slate-900"><?php echo $pendingCount; ?></div>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200/80 p-4 sm:p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-rose-600 uppercase tracking-wide">Ajustes</span>
                <span class="w-8 h-8 rounded-lg bg-rose-50 text-rose-600 flex items-center justify-center">
                    <i data-lucide="alert-circle" class="w-4 h-4"></i>
                </span>
            </div>
            <div class="mt-2 text-2xl sm:text-3xl font-bold text-slate-900"><?php echo $changesCount; ?></div>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200/80 p-4 sm:p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-emerald-600 uppercase tracking-wide">Aprovados</span>
                <span class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center">
                    <i data-lucide="check-circle-2" class="w-4 h-4"></i>
                </span>
            </div>
            <div class="mt-2 text-2xl sm:text-3xl font-bold text-slate-900"><?php echo $approvedCount; ?></div>
        </div>
    </section>

    <!-- Barra de Filtros -->
    <section class="bg-white rounded-2xl border border-slate-200/80 p-4 mb-8 shadow-sm">
        <form method="GET" action="dashboard.php" class="flex flex-col sm:flex-row items-stretch sm:items-end gap-3">
            <div class="flex-1">
                <label for="filter-client" class="block text-xs font-semibold text-slate-600 mb-1">Filtrar por Cliente</label>
                <select id="filter-client" name="client_id" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                    <option value="0">Todos os clientes</option>
                    <?php foreach ($clients as $client): ?>
                        <option value="<?php echo (int)$client['id']; ?>" <?php echo $filterClient === (int)$client['id'] ? 'selected' : ''; ?>>
                            <?php echo e($client['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="flex-1">
                <label for="filter-status" class="block text-xs font-semibold text-slate-600 mb-1">Filtrar Criativos Avulsos por Status</label>
                <select id="filter-status" name="status" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                    <option value="">Todos os status</option>
                    <option value="pending" <?php echo $filterStatus === 'pending' ? 'selected' : ''; ?>>Aguardando Aprovação</option>
                    <option value="changes_requested" <?php echo $filterStatus === 'changes_requested' ? 'selected' : ''; ?>>Ajustes Solicitados</option>
                    <option value="approved" <?php echo $filterStatus === 'approved' ? 'selected' : ''; ?>>Aprovado</option>
                </select>
            </div>

            <div class="flex items-center gap-2">
                <button type="submit" class="inline-flex items-center justify-center gap-1.5 px-4 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 text-white text-sm font-semibold transition">
                    <i data-lucide="filter" class="w-4 h-4"></i>
                    <span>Filtrar</span>
                </button>
                <?php if ($filterClient > 0 || $filterStatus !== ''): ?>
                    <a href="dashboard.php" class="inline-flex items-center justify-center px-3 py-2 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-100 text-sm font-medium transition">
                        Limpar
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </section>

    <!-- Seção Superior: Pastas / Campanhas Ativas -->
    <section class="mb-10">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-bold text-slate-900 flex items-center gap-2">
                <i data-lucide="folder-kanban" class="w-5 h-5 text-indigo-600"></i>
                <span>Pastas / Campanhas Ativas (<?php echo count($folders); ?>)</span>
            </h2>
        </div>

        <?php if (empty($folders)): ?>
            <div class="bg-white rounded-2xl border border-dashed border-slate-300 p-8 text-center">
                <p class="text-sm text-slate-500">
                    Nenhuma pasta de campanha criada ainda. Crie uma pasta para agrupar vários criativos em um único Link Mágico.
                </p>
                <button type="button" onclick="openFolderModal()" class="mt-3 inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-indigo-50 hover:bg-indigo-100 text-indigo-700 text-xs font-semibold transition">
                    <i data-lucide="folder-plus" class="w-4 h-4"></i>
                    <span>Criar Primeira Pasta</span>
                </button>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                <?php foreach ($folders as $folder): ?>
                    <?php render_folder_card($folder); ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <!-- Seção Inferior: Criativos Avulsos -->
    <section>
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-bold text-slate-900 flex items-center gap-2">
                <i data-lucide="images" class="w-5 h-5 text-indigo-600"></i>
                <span>Criativos Avulsos (<?php echo count($creatives); ?>)</span>
            </h2>
        </div>

        <?php if (empty($creatives)): ?>
            <div class="bg-white rounded-2xl border border-dashed border-slate-300 p-10 text-center">
                <div class="w-12 h-12 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center mx-auto mb-3">
                    <i data-lucide="image-plus" class="w-6 h-6"></i>
                </div>
                <h3 class="text-base font-bold text-slate-900">Nenhum criativo avulso encontrado</h3>
                <p class="text-sm text-slate-500 mt-1 max-w-md mx-auto">
                    <?php if ($filterClient > 0 || $filterStatus !== ''): ?>
                        Nenhum criativo avulso corresponde aos filtros selecionados.
                    <?php else: ?>
                        Envie um criativo individual ou vincule novos uploads a uma pasta de campanha.
                    <?php endif; ?>
                </p>
                <div class="mt-5">
                    <a href="creative_new.php" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold shadow-sm transition">
                        <i data-lucide="plus" class="w-4 h-4"></i>
                        <span>Enviar Criativo</span>
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($creatives as $creative): ?>
                    <?php render_creative_card($creative); ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</main>

<!-- Modal: Nova Pasta / Campanha -->
<div id="modal-new-folder" class="hidden fixed inset-0 z-50 flex items-center justify-center px-4 bg-slate-900/50 backdrop-blur-sm">
    <div class="bg-white rounded-2xl border border-slate-200 shadow-2xl w-full max-w-md overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h3 class="text-base font-bold text-slate-900 flex items-center gap-2">
                <i data-lucide="folder-plus" class="w-5 h-5 text-indigo-600"></i>
                <span>Nova Pasta / Campanha</span>
            </h3>
            <button type="button" onclick="closeFolderModal()" class="text-slate-400 hover:text-slate-600 transition" aria-label="Fechar">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <form id="form-new-folder" onsubmit="submitNewFolder(event)" class="p-6 space-y-4">
            <?php if (empty($clients)): ?>
                <div class="p-4 rounded-xl bg-amber-50 border border-amber-200 text-xs text-amber-800">
                    Você precisa cadastrar pelo menos um cliente antes de criar uma pasta.
                    <a href="clients.php" class="underline font-bold ml-1">Ir para Clientes</a>
                </div>
            <?php else: ?>
                <div>
                    <label for="folder-client-id" class="block text-xs font-semibold text-slate-700 uppercase tracking-wide mb-1.5">Cliente *</label>
                    <select id="folder-client-id" name="client_id" required class="w-full rounded-xl border border-slate-200 px-3.5 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                        <option value="">Selecione um cliente...</option>
                        <?php foreach ($clients as $client): ?>
                            <option value="<?php echo (int)$client['id']; ?>"><?php echo e($client['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="folder-name" class="block text-xs font-semibold text-slate-700 uppercase tracking-wide mb-1.5">Nome da Pasta / Campanha *</label>
                    <input type="text" id="folder-name" name="name" required maxlength="150"
                        placeholder="Ex: Campanha Dia das Mães"
                        class="w-full rounded-xl border border-slate-200 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                </div>

                <div>
                    <label for="folder-description" class="block text-xs font-semibold text-slate-700 uppercase tracking-wide mb-1.5">Briefing / Observação (Opcional)</label>
                    <textarea id="folder-description" name="description" rows="3"
                        placeholder="Orientações gerais para o cliente revisar os criativos desta pasta..."
                        class="w-full rounded-xl border border-slate-200 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500"></textarea>
                </div>

                <div class="pt-2 flex items-center justify-end gap-2.5">
                    <button type="button" onclick="closeFolderModal()" class="px-4 py-2 rounded-xl border border-slate-200 text-sm font-semibold text-slate-600 hover:bg-slate-50 transition">
                        Cancelar
                    </button>
                    <button type="submit" id="btn-create-folder" class="inline-flex items-center gap-2 px-5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold shadow-sm transition">
                        <i data-lucide="check" class="w-4 h-4"></i>
                        <span>Criar Pasta</span>
                    </button>
                </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<?php
$csrfJs = e($csrfToken);
$extraScripts = <<<HTML
<script>
var DASHBOARD_CSRF = '{$csrfJs}';

function openFolderModal() {
    var modal = document.getElementById('modal-new-folder');
    if (modal) modal.classList.remove('hidden');
}

function closeFolderModal() {
    var modal = document.getElementById('modal-new-folder');
    if (modal) modal.classList.add('hidden');
}

function submitNewFolder(e) {
    e.preventDefault();
    var btn = document.getElementById('btn-create-folder');
    var clientId = document.getElementById('folder-client-id').value;
    var name = document.getElementById('folder-name').value.trim();
    var description = document.getElementById('folder-description').value.trim();

    if (!clientId || !name) {
        showToast('Selecione o cliente e informe o nome da pasta.', 'warning');
        return;
    }

    btn.disabled = true;
    var formData = new FormData();
    formData.append('action', 'create_folder');
    formData.append('client_id', clientId);
    formData.append('name', name);
    formData.append('description', description);
    formData.append('csrf_token', DASHBOARD_CSRF);

    fetch('api/action.php', { method: 'POST', body: formData })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (data.success && data.folder_id) {
                window.location.href = 'folder_view.php?id=' + data.folder_id;
            } else {
                showToast(data.error || 'Erro ao criar pasta.', 'error');
                btn.disabled = false;
            }
        })
        .catch(function() {
            showToast('Erro de comunicação com o servidor.', 'error');
            btn.disabled = false;
        });
}
</script>
HTML;
render_footer($extraScripts);
?>
