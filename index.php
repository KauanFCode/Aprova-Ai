<?php
/**
 * AprovaAI - Landing Page Pública
 * Apresentação do produto com detecção inteligente de sessão (sem redirecionamento forçado).
 * Compatível estritamente com PHP 7.4+
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

$isLoggedIn = is_logged_in();
$navCtaHref  = $isLoggedIn ? 'dashboard.php' : 'login.php';
$navCtaLabel = $isLoggedIn ? 'Acessar Meu Painel' : 'Entrar / Começar Agora';
$heroCtaHref = $isLoggedIn ? 'dashboard.php' : 'login.php';
$heroCtaLabel = $isLoggedIn ? 'Acessar Meu Painel' : 'Acessar Plataforma / Criar Conta';
?>
<!DOCTYPE html>
<html lang="pt-BR" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AprovaAI — Aprove vídeos, artes e legendas em um único Link Mágico</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
    </style>
</head>
<body class="bg-white text-slate-800 antialiased selection:bg-indigo-100 selection:text-indigo-800">

    <!-- 1. Navbar -->
    <header class="sticky top-0 z-40 bg-white/90 backdrop-blur-md border-b border-slate-200/80">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between gap-4">
            <a href="index.php" class="flex items-center gap-2.5 group">
                <div class="w-9 h-9 rounded-xl bg-indigo-600 text-white flex items-center justify-center shadow-sm shadow-indigo-600/20 group-hover:bg-indigo-700 transition">
                    <i data-lucide="sparkles" class="w-5 h-5"></i>
                </div>
                <span class="text-lg font-bold tracking-tight text-slate-900">
                    Aprova<span class="text-indigo-600">AI</span>
                </span>
            </a>

            <nav class="hidden md:flex items-center gap-7 text-sm font-medium text-slate-600">
                <a href="#como-funciona" class="hover:text-indigo-600 transition">Como Funciona</a>
                <a href="#recursos" class="hover:text-indigo-600 transition">Recursos</a>
                <a href="#planos" class="hover:text-indigo-600 transition">Planos</a>
            </nav>

            <div class="flex items-center gap-3">
                <a href="<?php echo e($navCtaHref); ?>"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold shadow-sm shadow-indigo-600/20 transition">
                    <span><?php echo e($navCtaLabel); ?></span>
                    <i data-lucide="arrow-right" class="w-4 h-4"></i>
                </a>
                <button type="button" onclick="toggleLandingMenu()" class="md:hidden p-2 rounded-lg text-slate-600 hover:bg-slate-100" aria-label="Abrir menu">
                    <i data-lucide="menu" class="w-5 h-5"></i>
                </button>
            </div>
        </div>

        <!-- Menu Mobile -->
        <div id="landing-mobile-menu" class="hidden md:hidden border-t border-slate-100 bg-white px-4 py-3 space-y-2 text-sm font-medium">
            <a href="#como-funciona" onclick="toggleLandingMenu()" class="block py-1.5 text-slate-600 hover:text-indigo-600">Como Funciona</a>
            <a href="#recursos" onclick="toggleLandingMenu()" class="block py-1.5 text-slate-600 hover:text-indigo-600">Recursos</a>
            <a href="#planos" onclick="toggleLandingMenu()" class="block py-1.5 text-slate-600 hover:text-indigo-600">Planos</a>
        </div>
    </header>

    <!-- 2. Hero Section -->
    <section class="relative overflow-hidden bg-gradient-to-b from-slate-50 via-indigo-50/30 to-white pt-14 pb-20 lg:pt-20 lg:pb-28">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 items-center">
                <!-- Texto Principal -->
                <div class="lg:col-span-6 text-center lg:text-left">
                    <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-indigo-50 border border-indigo-200/80 text-indigo-700 text-xs font-semibold mb-6">
                        <i data-lucide="zap" class="w-3.5 h-3.5"></i>
                        <span>Chega de aprovações perdidas no WhatsApp e Drive</span>
                    </div>

                    <h1 class="text-3xl sm:text-4xl lg:text-5xl font-extrabold text-slate-900 tracking-tight leading-[1.15]">
                        Aprove vídeos, artes estáticas e legendas em um único <span class="text-indigo-600">Link Mágico</span>.
                    </h1>

                    <p class="mt-5 text-base sm:text-lg text-slate-600 leading-relaxed max-w-2xl mx-auto lg:mx-0">
                        Agências e freelancers economizam até <strong class="text-slate-900">41% do tempo operacional</strong> eliminando retrabalho, áudios intermináveis e perda de qualidade de mídia. Seu cliente aprova ou pede ajustes com 1 clique, sem precisar criar conta.
                    </p>

                    <div class="mt-8 flex flex-col sm:flex-row items-center justify-center lg:justify-start gap-3.5">
                        <a href="<?php echo e($heroCtaHref); ?>"
                            class="w-full sm:w-auto inline-flex items-center justify-center gap-2.5 px-7 py-3.5 rounded-2xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-base shadow-lg shadow-indigo-600/25 transition">
                            <span><?php echo e($heroCtaLabel); ?></span>
                            <i data-lucide="arrow-right" class="w-5 h-5"></i>
                        </a>
                        <a href="#como-funciona"
                            class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-6 py-3.5 rounded-2xl bg-white hover:bg-slate-50 text-slate-700 font-semibold text-base border border-slate-200 transition">
                            <i data-lucide="play-circle" class="w-5 h-5 text-indigo-600"></i>
                            <span>Ver como funciona</span>
                        </a>
                    </div>

                    <div class="mt-7 flex flex-wrap items-center justify-center lg:justify-start gap-x-6 gap-y-2 text-xs font-medium text-slate-500">
                        <span class="inline-flex items-center gap-1.5"><i data-lucide="check" class="w-4 h-4 text-emerald-500"></i> Cliente aprova sem login</span>
                        <span class="inline-flex items-center gap-1.5"><i data-lucide="check" class="w-4 h-4 text-emerald-500"></i> Pastas e upload em lote</span>
                        <span class="inline-flex items-center gap-1.5"><i data-lucide="check" class="w-4 h-4 text-emerald-500"></i> Qualidade original MP4/WebP</span>
                    </div>
                </div>

                <!-- Mockup Visual do Player com Timestamp 00:14 -->
                <div class="lg:col-span-6">
                    <div class="relative mx-auto max-w-lg lg:max-w-none">
                        <div class="absolute -inset-3 bg-gradient-to-tr from-indigo-500/20 via-violet-500/10 to-emerald-500/20 rounded-3xl blur-2xl"></div>
                        <div class="relative bg-white rounded-2xl border border-slate-200/90 shadow-2xl overflow-hidden">
                            <!-- Barra Superior do Mockup -->
                            <div class="px-4 py-3 bg-slate-50 border-b border-slate-100 flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="w-2.5 h-2.5 rounded-full bg-rose-400"></span>
                                    <span class="w-2.5 h-2.5 rounded-full bg-amber-400"></span>
                                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-400"></span>
                                    <span class="ml-2 text-xs font-semibold text-slate-600">Reels Lançamento &bull; Cafeteria Grão Nobre</span>
                                </div>
                                <span class="px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-amber-50 text-amber-700 border border-amber-200">
                                    Aguardando
                                </span>
                            </div>

                            <!-- Player Simulado -->
                            <div class="p-4 sm:p-5 space-y-4">
                                <div class="relative rounded-xl bg-slate-900 aspect-video overflow-hidden flex flex-col justify-between p-4 text-white">
                                    <div class="flex items-center justify-between">
                                        <span class="px-2 py-0.5 rounded-md bg-white/15 backdrop-blur text-[11px] font-medium">1080p Original</span>
                                        <span class="px-2 py-0.5 rounded-md bg-indigo-600 text-[11px] font-mono font-bold">00:14 / 00:30</span>
                                    </div>

                                    <div class="flex items-center justify-center">
                                        <div class="w-14 h-14 rounded-full bg-white/95 text-indigo-600 flex items-center justify-center shadow-lg">
                                            <i data-lucide="play" class="w-6 h-6 ml-0.5"></i>
                                        </div>
                                    </div>

                                    <!-- Barra de progresso com marcador em 00:14 -->
                                    <div class="space-y-1.5">
                                        <div class="relative h-2 bg-white/20 rounded-full overflow-visible">
                                            <div class="h-full w-[46%] bg-indigo-500 rounded-full"></div>
                                            <div class="absolute top-1/2 left-[46%] -translate-y-1/2 -translate-x-1/2 w-3.5 h-3.5 rounded-full bg-rose-500 ring-4 ring-rose-500/30"></div>
                                        </div>
                                        <div class="flex justify-between text-[10px] font-mono text-slate-300">
                                            <span>00:00</span>
                                            <span class="text-rose-300 font-semibold">Ajuste marcado em 00:14</span>
                                            <span>00:30</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Card de Comentário Timestamped -->
                                <div class="rounded-xl border border-rose-200 bg-rose-50/40 p-3.5">
                                    <div class="flex items-center justify-between mb-1.5">
                                        <div class="flex items-center gap-2">
                                            <span class="text-xs font-bold text-slate-900">Camila (Cliente)</span>
                                            <span class="px-2 py-0.5 rounded-md bg-indigo-600 text-white text-[11px] font-mono font-semibold inline-flex items-center gap-1">
                                                <i data-lucide="play-circle" class="w-3 h-3"></i> 00:14
                                            </span>
                                        </div>
                                        <span class="text-[11px] font-semibold text-rose-700">Pediu Ajuste</span>
                                    </div>
                                    <p class="text-xs text-slate-700">Aumentar um pouco o contraste da legenda nesse segundo para destacar a oferta.</p>
                                </div>

                                <!-- Botões de Decisão -->
                                <div class="grid grid-cols-2 gap-2.5">
                                    <div class="py-2.5 px-3 rounded-xl bg-emerald-600 text-white text-xs font-bold flex items-center justify-center gap-1.5 shadow-sm">
                                        <i data-lucide="check-circle-2" class="w-4 h-4"></i>
                                        <span>Aprovar Criativo</span>
                                    </div>
                                    <div class="py-2.5 px-3 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-xs font-bold flex items-center justify-center gap-1.5">
                                        <i data-lucide="alert-circle" class="w-4 h-4"></i>
                                        <span>Solicitar Alteração</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- 3. Como Funciona (3 Passos) -->
    <section id="como-funciona" class="py-20 bg-white border-t border-slate-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-2xl mx-auto mb-14">
                <span class="text-xs font-bold uppercase tracking-wider text-indigo-600">Fluxo Simplificado</span>
                <h2 class="mt-2 text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
                    Da criação à aprovação final em 3 passos
                </h2>
                <p class="mt-3 text-sm sm:text-base text-slate-500">
                    Sem downloads pesados no celular do cliente e sem prints riscados no WhatsApp.
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="rounded-2xl border border-slate-200/80 bg-slate-50/50 p-7 relative">
                    <div class="w-11 h-11 rounded-xl bg-indigo-600 text-white font-bold text-lg flex items-center justify-center shadow-sm mb-5">1</div>
                    <h3 class="text-lg font-bold text-slate-900 mb-2">Upload em Lote + Copy</h3>
                    <p class="text-sm text-slate-600 leading-relaxed">
                        Envie vídeos (MP4/WebM) ou imagens estáticas (JPG/PNG/WebP) avulsos ou agrupados em uma Pasta de Campanha, já com a legenda da publicação.
                    </p>
                </div>

                <div class="rounded-2xl border border-slate-200/80 bg-slate-50/50 p-7 relative">
                    <div class="w-11 h-11 rounded-xl bg-indigo-600 text-white font-bold text-lg flex items-center justify-center shadow-sm mb-5">2</div>
                    <h3 class="text-lg font-bold text-slate-900 mb-2">Envie o Link Mágico</h3>
                    <p class="text-sm text-slate-600 leading-relaxed">
                        Copie o link único do criativo ou da pasta inteira e mande no WhatsApp. O cliente abre no navegador sem precisar de login ou senha.
                    </p>
                </div>

                <div class="rounded-2xl border border-slate-200/80 bg-slate-50/50 p-7 relative">
                    <div class="w-11 h-11 rounded-xl bg-indigo-600 text-white font-bold text-lg flex items-center justify-center shadow-sm mb-5">3</div>
                    <h3 class="text-lg font-bold text-slate-900 mb-2">Feedback na Minutagem Exata</h3>
                    <p class="text-sm text-slate-600 leading-relaxed">
                        O cliente aprova em 1 clique (ou o lote inteiro de uma vez) ou marca o segundo exato do vídeo (`00:14`) onde deseja um ajuste.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- 4. Diferenciais do Sistema (4 Cards) -->
    <section id="recursos" class="py-20 bg-slate-50 border-t border-slate-200/70">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-2xl mx-auto mb-14">
                <span class="text-xs font-bold uppercase tracking-wider text-indigo-600">Recursos Pensados para Agências</span>
                <h2 class="mt-2 text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
                    Tudo o que você precisa para escalar suas entregas
                </h2>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                <div class="bg-white rounded-2xl border border-slate-200/80 p-6 shadow-sm">
                    <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center mb-4">
                        <i data-lucide="smartphone" class="w-5 h-5"></i>
                    </div>
                    <h3 class="text-base font-bold text-slate-900 mb-2">Feed Mobile-First</h3>
                    <p class="text-sm text-slate-600 leading-relaxed">
                        Experiência otimizada para o cliente revisar artes e vídeos diretamente pelo celular com preview realista e cópia rápida da legenda.
                    </p>
                </div>

                <div class="bg-white rounded-2xl border border-slate-200/80 p-6 shadow-sm">
                    <div class="w-10 h-10 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center mb-4">
                        <i data-lucide="clock" class="w-5 h-5"></i>
                    </div>
                    <h3 class="text-base font-bold text-slate-900 mb-2">Timestamp (MM:SS)</h3>
                    <p class="text-sm text-slate-600 leading-relaxed">
                        Captura automática do segundo atual do player HTML5. No painel, basta clicar no timestamp para o vídeo pular direto para o ponto marcado.
                    </p>
                </div>

                <div class="bg-white rounded-2xl border border-slate-200/80 p-6 shadow-sm">
                    <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center mb-4">
                        <i data-lucide="folder-kanban" class="w-5 h-5"></i>
                    </div>
                    <h3 class="text-base font-bold text-slate-900 mb-2">Pastas &amp; Campanhas</h3>
                    <p class="text-sm text-slate-600 leading-relaxed">
                        Agrupe todos os criativos da semana ou de uma campanha em uma única pasta com barra de progresso e aprovação individual ou em lote.
                    </p>
                </div>

                <div class="bg-white rounded-2xl border border-slate-200/80 p-6 shadow-sm">
                    <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center mb-4">
                        <i data-lucide="shield-check" class="w-5 h-5"></i>
                    </div>
                    <h3 class="text-base font-bold text-slate-900 mb-2">Multi-Tenant Seguro</h3>
                    <p class="text-sm text-slate-600 leading-relaxed">
                        Cada agência possui seu ambiente isolado, proteção CSRF, validação real de arquivos e histórico auditável de todas as aprovações.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- 5. Planos e Chamada Final -->
    <section id="planos" class="py-20 bg-white border-t border-slate-200/70">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-2xl mx-auto mb-14">
                <span class="text-xs font-bold uppercase tracking-wider text-indigo-600">Planos Transparentes</span>
                <h2 class="mt-2 text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
                    Comece hoje mesmo na sua agência
                </h2>
                <p class="mt-3 text-sm sm:text-base text-slate-500">
                    Escolha o plano ideal para o seu volume de clientes e campanhas.
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 items-stretch">
                <!-- Plano Freelancer -->
                <div class="rounded-2xl border border-slate-200 bg-white p-8 flex flex-col justify-between shadow-sm">
                    <div>
                        <div class="text-sm font-bold text-slate-500 uppercase tracking-wide">Plano Freelancer</div>
                        <div class="mt-3 flex items-baseline gap-1">
                            <span class="text-4xl font-extrabold text-slate-900">Grátis</span>
                            <span class="text-sm text-slate-500">/ para começar</span>
                        </div>
                        <p class="mt-3 text-sm text-slate-600">Ideal para social media e designers autônomos validando entregas com clientes.</p>

                        <ul class="mt-6 space-y-3 text-sm text-slate-700">
                            <li class="flex items-center gap-2.5"><i data-lucide="check-circle-2" class="w-4 h-4 text-emerald-500 shrink-0"></i> Até 3 clientes ativos</li>
                            <li class="flex items-center gap-2.5"><i data-lucide="check-circle-2" class="w-4 h-4 text-emerald-500 shrink-0"></i> Links mágicos sem login</li>
                            <li class="flex items-center gap-2.5"><i data-lucide="check-circle-2" class="w-4 h-4 text-emerald-500 shrink-0"></i> Upload de imagens e vídeos (MP4/WebM)</li>
                            <li class="flex items-center gap-2.5"><i data-lucide="check-circle-2" class="w-4 h-4 text-emerald-500 shrink-0"></i> Marcação de timestamp em vídeos</li>
                        </ul>
                    </div>

                    <a href="login.php?tab=register" class="mt-8 w-full py-3 px-4 rounded-xl border border-slate-300 hover:bg-slate-50 text-slate-800 font-bold text-sm text-center transition">
                        Criar Conta Grátis
                    </a>
                </div>

                <!-- Plano Agência -->
                <div class="rounded-2xl border-2 border-indigo-600 bg-slate-900 text-white p-8 flex flex-col justify-between shadow-xl relative">
                    <span class="absolute -top-3.5 right-6 px-3 py-1 rounded-full bg-indigo-600 text-white text-xs font-bold shadow">
                        Mais Popular
                    </span>
                    <div>
                        <div class="text-sm font-bold text-indigo-300 uppercase tracking-wide">Plano Agência</div>
                        <div class="mt-3 flex items-baseline gap-1">
                            <span class="text-4xl font-extrabold text-white">R$ 49,90</span>
                            <span class="text-sm text-slate-400">/ mês</span>
                        </div>
                        <p class="mt-3 text-sm text-slate-300">Para agências de marketing e produtoras com múltiplas campanhas simultâneas.</p>

                        <ul class="mt-6 space-y-3 text-sm text-slate-200">
                            <li class="flex items-center gap-2.5"><i data-lucide="check-circle-2" class="w-4 h-4 text-indigo-400 shrink-0"></i> Clientes e criativos ilimitados</li>
                            <li class="flex items-center gap-2.5"><i data-lucide="check-circle-2" class="w-4 h-4 text-indigo-400 shrink-0"></i> Pastas de Campanha e Upload em Lote</li>
                            <li class="flex items-center gap-2.5"><i data-lucide="check-circle-2" class="w-4 h-4 text-indigo-400 shrink-0"></i> Aprovação em 1 clique de todo o lote</li>
                            <li class="flex items-center gap-2.5"><i data-lucide="check-circle-2" class="w-4 h-4 text-indigo-400 shrink-0"></i> Histórico completo e suporte prioritário</li>
                        </ul>
                    </div>

                    <a href="login.php?tab=register" class="mt-8 w-full py-3 px-4 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-sm text-center shadow-lg shadow-indigo-600/30 transition">
                        Começar Agora
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Rodapé -->
    <footer class="py-8 bg-slate-50 border-t border-slate-200/80">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row items-center justify-between gap-4 text-xs text-slate-500">
            <div class="flex items-center gap-2 font-semibold text-slate-700">
                <i data-lucide="sparkles" class="w-4 h-4 text-indigo-600"></i>
                <span>AprovaAI &mdash; Aprovação de Criativos para Agências</span>
            </div>
            <div class="flex items-center gap-5">
                <a href="#como-funciona" class="hover:text-indigo-600 transition">Como Funciona</a>
                <a href="#recursos" class="hover:text-indigo-600 transition">Recursos</a>
                <a href="login.php" class="hover:text-indigo-600 transition font-semibold text-indigo-600">Acessar Plataforma</a>
            </div>
        </div>
    </footer>

    <script>
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
        function toggleLandingMenu() {
            var el = document.getElementById('landing-mobile-menu');
            if (el) {
                el.classList.toggle('hidden');
            }
        }
    </script>
</body>
</html>
