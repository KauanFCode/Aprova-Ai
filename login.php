<?php
/**
 * AprovaAI - Login e Cadastro de Agência (Multi-Tenant)
 * Compatível estritamente com PHP 7.4+
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/components.php';

if (is_logged_in()) {
    redirect('dashboard.php');
}

$activeTab = isset($_GET['tab']) && $_GET['tab'] === 'register' ? 'register' : 'login';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    if (!verify_csrf_token($csrfToken)) {
        $errorMessage = 'Sessão expirada. Recarregue a página e tente novamente.';
    } else {
        $pdo = get_pdo();
        $formAction = isset($_POST['form_action']) ? $_POST['form_action'] : 'login';

        if ($formAction === 'register') {
            $activeTab  = 'register';
            $agencyName = isset($_POST['agency_name']) ? $_POST['agency_name'] : '';
            $userName   = isset($_POST['user_name']) ? $_POST['user_name'] : '';
            $email      = isset($_POST['email']) ? $_POST['email'] : '';
            $password   = isset($_POST['password']) ? $_POST['password'] : '';

            $result = register_agency($pdo, $agencyName, $userName, $email, $password);
            if ($result['success']) {
                set_flash('success', 'Conta da agência criada com sucesso! Bem-vindo ao AprovaAI.');
                redirect('dashboard.php');
            } else {
                $errorMessage = $result['error'];
            }
        } else {
            $activeTab = 'login';
            $email     = isset($_POST['email']) ? $_POST['email'] : '';
            $password  = isset($_POST['password']) ? $_POST['password'] : '';

            $result = attempt_login($pdo, $email, $password);
            if ($result['success']) {
                redirect('dashboard.php');
            } else {
                $errorMessage = $result['error'];
            }
        }
    }
}

render_head('Entrar — AprovaAI');
?>

<main class="flex-1 flex items-center justify-center px-4 py-12 bg-gradient-to-br from-slate-50 via-indigo-50/40 to-slate-100">
    <div class="w-full max-w-md">
        <!-- Logo e Título -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-indigo-600 text-white shadow-lg shadow-indigo-600/20 mb-4">
                <i data-lucide="sparkles" class="w-7 h-7"></i>
            </div>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">
                Aprova<span class="text-indigo-600">AI</span>
            </h1>
            <p class="text-sm text-slate-500 mt-1">
                Aprovação de criativos sem atrito para agências e freelancers
            </p>
        </div>

        <?php render_flash_alert(); ?>

        <?php if ($errorMessage !== ''): ?>
            <div class="mb-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-800 flex items-start gap-2.5 shadow-sm">
                <i data-lucide="x-circle" class="w-5 h-5 shrink-0 mt-0.5"></i>
                <span><?php echo e($errorMessage); ?></span>
            </div>
        <?php endif; ?>

        <!-- Card Principal com Abas -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xl shadow-slate-200/50 overflow-hidden">
            <!-- Abas -->
            <div class="grid grid-cols-2 border-b border-slate-100 bg-slate-50/60">
                <button type="button" id="tab-btn-login" onclick="switchAuthTab('login')"
                    class="py-3.5 text-sm font-semibold transition border-b-2 <?php echo $activeTab === 'login' ? 'border-indigo-600 text-indigo-600 bg-white' : 'border-transparent text-slate-500 hover:text-slate-700'; ?>">
                    Entrar
                </button>
                <button type="button" id="tab-btn-register" onclick="switchAuthTab('register')"
                    class="py-3.5 text-sm font-semibold transition border-b-2 <?php echo $activeTab === 'register' ? 'border-indigo-600 text-indigo-600 bg-white' : 'border-transparent text-slate-500 hover:text-slate-700'; ?>">
                    Criar Conta de Agência
                </button>
            </div>

            <!-- Formulário de Login -->
            <form id="form-login" method="POST" action="login.php" class="p-6 sm:p-7 space-y-4 <?php echo $activeTab === 'login' ? '' : 'hidden'; ?>">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="form_action" value="login">

                <div>
                    <label for="login-email" class="block text-xs font-semibold text-slate-700 uppercase tracking-wide mb-1.5">E-mail</label>
                    <div class="relative">
                        <i data-lucide="mail" class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2"></i>
                        <input type="email" id="login-email" name="email" required
                            value="<?php echo isset($_POST['email']) && $activeTab === 'login' ? e($_POST['email']) : ''; ?>"
                            placeholder="voce@suaagencia.com"
                            class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition">
                    </div>
                </div>

                <div>
                    <label for="login-password" class="block text-xs font-semibold text-slate-700 uppercase tracking-wide mb-1.5">Senha</label>
                    <div class="relative">
                        <i data-lucide="lock" class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2"></i>
                        <input type="password" id="login-password" name="password" required
                            placeholder="••••••••"
                            class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition">
                    </div>
                </div>

                <button type="submit"
                    class="w-full py-2.5 px-4 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-semibold text-sm shadow-sm shadow-indigo-600/20 transition flex items-center justify-center gap-2">
                    <i data-lucide="log-in" class="w-4 h-4"></i>
                    <span>Acessar Painel</span>
                </button>

                <div class="pt-3 border-t border-slate-100 text-center">
                    <p class="text-xs text-slate-400">
                        Demo: <span class="font-mono text-slate-600">admin@aprovaai.com</span> / <span class="font-mono text-slate-600">123456</span>
                    </p>
                </div>
            </form>

            <!-- Formulário de Cadastro de Agência -->
            <form id="form-register" method="POST" action="login.php?tab=register" class="p-6 sm:p-7 space-y-4 <?php echo $activeTab === 'register' ? '' : 'hidden'; ?>">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="form_action" value="register">

                <div>
                    <label for="reg-agency" class="block text-xs font-semibold text-slate-700 uppercase tracking-wide mb-1.5">Nome da Agência ou Freelancer</label>
                    <div class="relative">
                        <i data-lucide="building-2" class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2"></i>
                        <input type="text" id="reg-agency" name="agency_name" required maxlength="120"
                            value="<?php echo isset($_POST['agency_name']) ? e($_POST['agency_name']) : ''; ?>"
                            placeholder="Ex: Agência Impulso Digital"
                            class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition">
                    </div>
                </div>

                <div>
                    <label for="reg-name" class="block text-xs font-semibold text-slate-700 uppercase tracking-wide mb-1.5">Seu Nome</label>
                    <div class="relative">
                        <i data-lucide="user" class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2"></i>
                        <input type="text" id="reg-name" name="user_name" required maxlength="120"
                            value="<?php echo isset($_POST['user_name']) ? e($_POST['user_name']) : ''; ?>"
                            placeholder="Ex: Maria Silva"
                            class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition">
                    </div>
                </div>

                <div>
                    <label for="reg-email" class="block text-xs font-semibold text-slate-700 uppercase tracking-wide mb-1.5">E-mail Profissional</label>
                    <div class="relative">
                        <i data-lucide="mail" class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2"></i>
                        <input type="email" id="reg-email" name="email" required maxlength="150"
                            value="<?php echo isset($_POST['email']) && $activeTab === 'register' ? e($_POST['email']) : ''; ?>"
                            placeholder="maria@impulsodigital.com"
                            class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition">
                    </div>
                </div>

                <div>
                    <label for="reg-password" class="block text-xs font-semibold text-slate-700 uppercase tracking-wide mb-1.5">Senha (mín. 6 caracteres)</label>
                    <div class="relative">
                        <i data-lucide="lock" class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2"></i>
                        <input type="password" id="reg-password" name="password" required minlength="6"
                            placeholder="Crie uma senha segura"
                            class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition">
                    </div>
                </div>

                <button type="submit"
                    class="w-full py-2.5 px-4 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-semibold text-sm shadow-sm shadow-indigo-600/20 transition flex items-center justify-center gap-2">
                    <i data-lucide="rocket" class="w-4 h-4"></i>
                    <span>Criar Conta Grátis</span>
                </button>
            </form>
        </div>
    </div>
</main>

<?php
$extraScripts = <<<'HTML'
<script>
function switchAuthTab(tab) {
    var loginForm = document.getElementById('form-login');
    var regForm = document.getElementById('form-register');
    var btnLogin = document.getElementById('tab-btn-login');
    var btnReg = document.getElementById('tab-btn-register');

    if (tab === 'register') {
        loginForm.classList.add('hidden');
        regForm.classList.remove('hidden');
        btnReg.className = 'py-3.5 text-sm font-semibold transition border-b-2 border-indigo-600 text-indigo-600 bg-white';
        btnLogin.className = 'py-3.5 text-sm font-semibold transition border-b-2 border-transparent text-slate-500 hover:text-slate-700';
    } else {
        regForm.classList.add('hidden');
        loginForm.classList.remove('hidden');
        btnLogin.className = 'py-3.5 text-sm font-semibold transition border-b-2 border-indigo-600 text-indigo-600 bg-white';
        btnReg.className = 'py-3.5 text-sm font-semibold transition border-b-2 border-transparent text-slate-500 hover:text-slate-700';
    }
}
</script>
HTML;
render_footer($extraScripts);
?>
