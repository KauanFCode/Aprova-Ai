<?php
/**
 * AprovaAI - Gestão de Clientes da Agência
 * Listagem, cadastro e exclusão de clientes com isolamento multi-tenant.
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    if (!verify_csrf_token($csrfToken)) {
        set_flash('error', 'Token de segurança inválido. Tente novamente.');
        redirect('clients.php');
    }

    $action = isset($_POST['action']) ? $_POST['action'] : '';

    if ($action === 'create') {
        $name  = trim((string)(isset($_POST['name']) ? $_POST['name'] : ''));
        $phone = trim((string)(isset($_POST['contact_phone']) ? $_POST['contact_phone'] : ''));

        if ($name === '') {
            set_flash('error', 'Informe o nome da empresa cliente.');
            redirect('clients.php');
        }

        $stmt = $pdo->prepare('INSERT INTO clients (tenant_id, name, contact_phone) VALUES (?, ?, ?)');
        $stmt->execute(array($tenantId, $name, $phone !== '' ? $phone : null));

        set_flash('success', 'Cliente "' . $name . '" cadastrado com sucesso!');
        redirect('clients.php');
    }

    if ($action === 'delete') {
        $clientId = isset($_POST['client_id']) ? (int)$_POST['client_id'] : 0;

        // Verifica propriedade do cliente pelo tenant
        $checkStmt = $pdo->prepare('SELECT id, name FROM clients WHERE id = ? AND tenant_id = ? LIMIT 1');
        $checkStmt->execute(array($clientId, $tenantId));
        $clientRow = $checkStmt->fetch();

        if (!$clientRow) {
            set_flash('error', 'Cliente não encontrado ou sem permissão.');
            redirect('clients.php');
        }

        // Remove arquivos físicos dos criativos vinculados antes do CASCADE no banco
        $filesStmt = $pdo->prepare('SELECT file_path FROM creatives WHERE client_id = ? AND tenant_id = ?');
        $filesStmt->execute(array($clientId, $tenantId));
        $files = $filesStmt->fetchAll();
        foreach ($files as $fileRow) {
            delete_creative_file($fileRow['file_path']);
        }

        $delStmt = $pdo->prepare('DELETE FROM clients WHERE id = ? AND tenant_id = ?');
        $delStmt->execute(array($clientId, $tenantId));

        set_flash('success', 'Cliente "' . $clientRow['name'] . '" e seus criativos foram removidos.');
        redirect('clients.php');
    }
}

$clientsStmt = $pdo->prepare(
    'SELECT cl.*,
            (SELECT COUNT(*) FROM creatives c WHERE c.client_id = cl.id) AS creatives_count
     FROM clients cl
     WHERE cl.tenant_id = ?
     ORDER BY cl.created_at DESC, cl.id DESC'
);
$clientsStmt->execute(array($tenantId));
$clients = $clientsStmt->fetchAll();

render_head('Clientes — AprovaAI');
render_navbar('clients');
?>

<main class="max-w-7xl mx-auto w-full px-4 sm:px-6 lg:px-8 py-8 flex-1">
    <?php render_flash_alert(); ?>

    <div class="mb-8">
        <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Clientes</h1>
        <p class="text-sm text-slate-500 mt-1">Cadastre as empresas ou contas para organizar seus criativos.</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
        <!-- Formulário de Novo Cliente -->
        <section class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6">
            <h2 class="text-base font-bold text-slate-900 mb-4 flex items-center gap-2">
                <i data-lucide="user-plus" class="w-4 h-4 text-indigo-600"></i>
                <span>Novo Cliente</span>
            </h2>
            <form method="POST" action="clients.php" class="space-y-4">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="create">

                <div>
                    <label for="client-name" class="block text-xs font-semibold text-slate-700 uppercase tracking-wide mb-1.5">Nome da Empresa *</label>
                    <input type="text" id="client-name" name="name" required maxlength="120"
                        placeholder="Ex: Clínica Sorriso Mais"
                        class="w-full rounded-xl border border-slate-200 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                </div>

                <div>
                    <label for="client-phone" class="block text-xs font-semibold text-slate-700 uppercase tracking-wide mb-1.5">WhatsApp / Contato</label>
                    <input type="text" id="client-phone" name="contact_phone" maxlength="30"
                        placeholder="Ex: (11) 99999-0000"
                        class="w-full rounded-xl border border-slate-200 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                </div>

                <button type="submit" class="w-full py-2.5 px-4 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold shadow-sm transition flex items-center justify-center gap-2">
                    <i data-lucide="plus" class="w-4 h-4"></i>
                    <span>Cadastrar Cliente</span>
                </button>
            </form>
        </section>

        <!-- Lista de Clientes -->
        <section class="lg:col-span-2 bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                <h2 class="text-base font-bold text-slate-900">Clientes Cadastrados (<?php echo count($clients); ?>)</h2>
            </div>

            <?php if (empty($clients)): ?>
                <div class="p-10 text-center">
                    <i data-lucide="users" class="w-10 h-10 text-slate-300 mx-auto mb-3"></i>
                    <p class="text-sm text-slate-500">Nenhum cliente cadastrado ainda. Adicione o primeiro ao lado.</p>
                </div>
            <?php else: ?>
                <div class="divide-y divide-slate-100">
                    <?php foreach ($clients as $client): ?>
                        <div class="px-6 py-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 hover:bg-slate-50/70 transition">
                            <div>
                                <div class="text-sm font-bold text-slate-900"><?php echo e($client['name']); ?></div>
                                <div class="mt-1 flex flex-wrap items-center gap-3 text-xs text-slate-500">
                                    <?php if (!empty($client['contact_phone'])): ?>
                                        <span class="inline-flex items-center gap-1">
                                            <i data-lucide="phone" class="w-3.5 h-3.5"></i>
                                            <?php echo e($client['contact_phone']); ?>
                                        </span>
                                    <?php endif; ?>
                                    <span class="inline-flex items-center gap-1">
                                        <i data-lucide="layers" class="w-3.5 h-3.5"></i>
                                        <?php echo (int)$client['creatives_count']; ?> criativo(s)
                                    </span>
                                    <span class="inline-flex items-center gap-1">
                                        <i data-lucide="calendar" class="w-3.5 h-3.5"></i>
                                        <?php echo e(format_datetime_br($client['created_at'])); ?>
                                    </span>
                                </div>
                            </div>

                            <div class="flex items-center gap-2">
                                <a href="dashboard.php?client_id=<?php echo (int)$client['id']; ?>" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-slate-100 hover:bg-slate-200 text-slate-700 transition">
                                    <i data-lucide="folder-open" class="w-3.5 h-3.5"></i>
                                    Ver Criativos
                                </a>
                                <form method="POST" action="clients.php" onsubmit="return confirm('Tem certeza que deseja excluir este cliente e todos os seus criativos?');">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="client_id" value="<?php echo (int)$client['id']; ?>">
                                    <button type="submit" class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs font-semibold text-rose-600 hover:bg-rose-50 border border-rose-200/80 transition" title="Excluir cliente">
                                        <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>
</main>

<?php render_footer(); ?>
