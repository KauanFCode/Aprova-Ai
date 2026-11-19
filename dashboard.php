<?php
/**
 * AprovaAI - Dashboard da Agência
 * KPIs, filtros por cliente/status, grid de criativos e botão Copiar Link.
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
// KPIs do Tenant
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
// Lista de Clientes para Filtro
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

$sql = "SELECT c.*, cl.name AS client_name,
               (SELECT COUNT(*) FROM feedbacks f WHERE f.creative_id = c.id) AS feedback_count
        FROM creatives c
        INNER JOIN clients cl ON cl.id = c.client_id
        WHERE c.tenant_id = ?";
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

render_head('Dashboard — AprovaAI');
render_navbar('dashboard');
?>

<main class="max-w-7xl mx-auto w-full px-4 sm:px-6 lg:px-8 py-8 flex-1">
    <?php render_flash_alert(); ?>

    <!-- Cabeçalho da Página -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Criativos &amp; Aprovações</h1>
            <p class="text-sm text-slate-500 mt-1">Gerencie campanhas, envie links mágicos e acompanhe aprovações.</p>
        </div>
        <a href="creative_new.php" class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold shadow-sm shadow-indigo-600/20 transition">
            <i data-lucide="plus" class="w-4 h-4"></i>
            <span>Novo Criativo</span>
        </a>
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
    <section class="bg-white rounded-2xl border border-slate-200/80 p-4 mb-6 shadow-sm">
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
                <label for="filter-status" class="block text-xs font-semibold text-slate-600 mb-1">Filtrar por Status</label>
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

    <!-- Grid de Criativos -->
    <?php if (empty($creatives)): ?>
        <div class="bg-white rounded-2xl border border-dashed border-slate-300 p-12 text-center">
            <div class="w-14 h-14 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center mx-auto mb-4">
                <i data-lucide="image-plus" class="w-7 h-7"></i>
            </div>
            <h2 class="text-lg font-bold text-slate-900">Nenhum criativo encontrado</h2>
            <p class="text-sm text-slate-500 mt-1 max-w-md mx-auto">
                <?php if ($filterClient > 0 || $filterStatus !== ''): ?>
                    Nenhum criativo corresponde aos filtros selecionados. Tente limpar os filtros.
                <?php else: ?>
                    Envie seu primeiro vídeo ou imagem estática para gerar um link mágico de aprovação para o cliente.
                <?php endif; ?>
            </p>
            <div class="mt-6">
                <a href="creative_new.php" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold shadow-sm transition">
                    <i data-lucide="plus" class="w-4 h-4"></i>
                    <span>Enviar Primeiro Criativo</span>
                </a>
            </div>
        </div>
    <?php else: ?>
        <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($creatives as $creative): ?>
                <?php render_creative_card($creative); ?>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>
</main>

<?php render_footer(); ?>
