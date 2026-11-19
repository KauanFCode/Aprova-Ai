<?php
/**
 * AprovaAI - Biblioteca de Componentes Visuais (UI)
 * Utiliza Tailwind CSS + Lucide Icons via CDN.
 * Compatível estritamente com PHP 7.4+
 */

/**
 * Renderiza o cabeçalho HTML5 padrão com Tailwind CSS e Lucide Icons.
 *
 * @param string $title     Título da página
 * @param string $extraHead HTML extra opcional dentro de <head>
 * @return void
 */
function render_head($title = 'AprovaAI', $extraHead = '')
{
    $safeTitle = e($title);
    echo <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$safeTitle}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
    </style>
    {$extraHead}
</head>
<body class="bg-slate-50 text-slate-800 antialiased min-h-screen flex flex-col">
HTML;
}

/**
 * Renderiza a barra de navegação superior da área autenticada da agência.
 *
 * @param string $activePage 'dashboard' | 'clients' | 'creative_new'
 * @return void
 */
function render_navbar($activePage = 'dashboard')
{
    $user = current_user();
    $tenantName = $user ? e($user['tenant_name']) : 'Agência';
    $userName   = $user ? e($user['name']) : '';

    $navItems = array(
        'dashboard'    => array('label' => 'Criativos', 'href' => 'dashboard.php', 'icon' => 'layout-grid'),
        'clients'      => array('label' => 'Clientes', 'href' => 'clients.php', 'icon' => 'users'),
        'creative_new' => array('label' => 'Novo Criativo', 'href' => 'creative_new.php', 'icon' => 'plus-circle'),
    );

    echo '<header class="bg-white border-b border-slate-200 sticky top-0 z-30">';
    echo '<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">';
    echo '<div class="flex items-center justify-between h-16">';

    // Logo + Nome da Agência
    echo '<div class="flex items-center gap-4">';
    echo '<a href="dashboard.php" class="flex items-center gap-2.5 group">';
    echo '<div class="w-9 h-9 rounded-xl bg-indigo-600 text-white flex items-center justify-center shadow-sm group-hover:bg-indigo-700 transition">';
    echo '<i data-lucide="sparkles" class="w-5 h-5"></i>';
    echo '</div>';
    echo '<div>';
    echo '<span class="text-lg font-bold tracking-tight text-slate-900">Aprova<span class="text-indigo-600">AI</span></span>';
    echo '</div>';
    echo '</a>';
    echo '<span class="hidden md:inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-600 border border-slate-200">' . $tenantName . '</span>';
    echo '</div>';

    // Navegação Desktop
    echo '<nav class="hidden md:flex items-center gap-1">';
    foreach ($navItems as $key => $item) {
        $isActive = ($activePage === $key);
        $classes = $isActive
            ? 'bg-indigo-50 text-indigo-700 font-semibold'
            : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100 font-medium';
        echo '<a href="' . e($item['href']) . '" class="inline-flex items-center gap-2 px-3.5 py-2 rounded-lg text-sm transition ' . $classes . '">';
        echo '<i data-lucide="' . e($item['icon']) . '" class="w-4 h-4"></i>';
        echo '<span>' . e($item['label']) . '</span>';
        echo '</a>';
    }
    echo '</nav>';

    // Usuário e Sair
    echo '<div class="flex items-center gap-3">';
    echo '<div class="hidden sm:flex flex-col items-end">';
    echo '<span class="text-xs font-semibold text-slate-700">' . $userName . '</span>';
    echo '<span class="text-[11px] text-slate-400">' . $tenantName . '</span>';
    echo '</div>';
    echo '<a href="logout.php" title="Sair do sistema" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium text-slate-600 hover:text-rose-600 hover:bg-rose-50 border border-slate-200 transition">';
    echo '<i data-lucide="log-out" class="w-4 h-4"></i>';
    echo '<span class="hidden sm:inline">Sair</span>';
    echo '</a>';

    // Botão Menu Mobile
    echo '<button type="button" onclick="toggleMobileNav()" class="md:hidden p-2 rounded-lg text-slate-600 hover:bg-slate-100" aria-label="Abrir menu">';
    echo '<i data-lucide="menu" class="w-5 h-5"></i>';
    echo '</button>';
    echo '</div>';

    echo '</div>'; // flex

    // Navegação Mobile
    echo '<div id="mobile-nav" class="hidden md:hidden pb-3 pt-1 border-t border-slate-100 space-y-1">';
    foreach ($navItems as $key => $item) {
        $isActive = ($activePage === $key);
        $classes = $isActive
            ? 'bg-indigo-50 text-indigo-700 font-semibold'
            : 'text-slate-600 hover:bg-slate-100 font-medium';
        echo '<a href="' . e($item['href']) . '" class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-sm ' . $classes . '">';
        echo '<i data-lucide="' . e($item['icon']) . '" class="w-4 h-4"></i>';
        echo '<span>' . e($item['label']) . '</span>';
        echo '</a>';
    }
    echo '</div>';

    echo '</div>';
    echo '</header>';
}

/**
 * Renderiza o badge de status do criativo.
 *
 * @param string $status 'pending' | 'changes_requested' | 'approved'
 * @return string HTML do badge
 */
function render_status_badge($status)
{
    $meta = get_status_meta($status);
    return '<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold border ' . $meta['badge_class'] . '">'
        . '<i data-lucide="' . $meta['icon'] . '" class="w-3.5 h-3.5"></i>'
        . '<span>' . e($meta['label']) . '</span>'
        . '</span>';
}

/**
 * Renderiza mensagens flash de sessão (sucesso, erro, aviso, info).
 *
 * @return void
 */
function render_flash_alert()
{
    $flash = get_flash();
    if (!$flash) {
        return;
    }

    switch ($flash['type']) {
        case 'success':
            $classes = 'bg-emerald-50 border-emerald-200 text-emerald-800';
            $icon = 'check-circle-2';
            break;
        case 'error':
            $classes = 'bg-rose-50 border-rose-200 text-rose-800';
            $icon = 'x-circle';
            break;
        case 'warning':
            $classes = 'bg-amber-50 border-amber-200 text-amber-800';
            $icon = 'alert-triangle';
            break;
        default:
            $classes = 'bg-indigo-50 border-indigo-200 text-indigo-800';
            $icon = 'info';
            break;
    }

    echo '<div class="mb-6 rounded-xl border px-4 py-3 flex items-start gap-3 shadow-sm ' . $classes . '" role="alert">';
    echo '<i data-lucide="' . $icon . '" class="w-5 h-5 shrink-0 mt-0.5"></i>';
    echo '<div class="text-sm font-medium flex-1">' . e($flash['message']) . '</div>';
    echo '<button type="button" onclick="this.parentElement.remove()" class="opacity-60 hover:opacity-100 transition" aria-label="Fechar">';
    echo '<i data-lucide="x" class="w-4 h-4"></i>';
    echo '</button>';
    echo '</div>';
}

/**
 * Renderiza o card de uma Pasta / Campanha no Dashboard.
 *
 * @param array $folder Linha associativa da pasta com client_name, total_creatives e approved_creatives
 * @return void
 */
function render_folder_card($folder)
{
    $id          = (int)$folder['id'];
    $name        = e($folder['name']);
    $clientName  = e($folder['client_name']);
    $description = isset($folder['description']) ? trim((string)$folder['description']) : '';
    $progress    = get_folder_progress($folder['approved_creatives'], $folder['total_creatives']);
    $folderLink  = e(base_url('review.php?folder=' . $folder['public_token']));

    $barColor = ($progress['total'] > 0 && $progress['approved'] === $progress['total'])
        ? 'bg-emerald-500'
        : 'bg-indigo-600';

    echo '<div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm hover:shadow-md transition p-5 flex flex-col justify-between gap-4">';
    echo '<div>';
    echo '<div class="flex items-start justify-between gap-3">';
    echo '<div class="flex items-center gap-3 min-w-0">';
    echo '<div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center shrink-0">';
    echo '<i data-lucide="folder-kanban" class="w-5 h-5"></i>';
    echo '</div>';
    echo '<div class="min-w-0">';
    echo '<div class="text-[11px] font-semibold text-indigo-600 uppercase tracking-wide truncate">' . $clientName . '</div>';
    echo '<a href="folder_view.php?id=' . $id . '" class="block text-base font-bold text-slate-900 hover:text-indigo-600 transition truncate">' . $name . '</a>';
    echo '</div>';
    echo '</div>';
    echo '<span class="shrink-0 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-700">' . $progress['approved'] . '/' . $progress['total'] . '</span>';
    echo '</div>';

    if ($description !== '') {
        echo '<p class="mt-2.5 text-xs text-slate-500 line-clamp-2">' . e($description) . '</p>';
    }

    // Barra de Progresso
    echo '<div class="mt-4">';
    echo '<div class="flex items-center justify-between text-xs text-slate-500 mb-1.5">';
    echo '<span>' . e($progress['label']) . '</span>';
    echo '<span class="font-semibold text-slate-700">' . $progress['percent'] . '%</span>';
    echo '</div>';
    echo '<div class="w-full h-2 rounded-full bg-slate-100 overflow-hidden">';
    echo '<div class="h-full rounded-full transition-all duration-300 ' . $barColor . '" style="width: ' . $progress['percent'] . '%"></div>';
    echo '</div>';
    echo '</div>';
    echo '</div>';

    // Ações da Pasta
    echo '<div class="pt-3 border-t border-slate-100 flex items-center gap-2">';
    echo '<a href="folder_view.php?id=' . $id . '" class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl text-xs font-semibold bg-slate-100 hover:bg-slate-200 text-slate-700 transition">';
    echo '<i data-lucide="folder-open" class="w-3.5 h-3.5"></i> Abrir Pasta';
    echo '</a>';
    echo '<button type="button" onclick="copyMagicLink(\'' . $folderLink . '\', this)" class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl text-xs font-semibold bg-indigo-50 hover:bg-indigo-100 text-indigo-700 transition">';
    echo '<i data-lucide="link" class="w-3.5 h-3.5"></i> <span>Copiar Link da Pasta</span>';
    echo '</button>';
    echo '</div>';
    echo '</div>';
}

/**
 * Renderiza o card de um criativo no grid do Dashboard ou Pasta.
 *
 * @param array $creative Linha associativa do criativo com client_name e feedback_count
 * @return void
 */
function render_creative_card($creative)
{
    $id          = (int)$creative['id'];
    $title       = e($creative['title']);
    $clientName  = e($creative['client_name']);
    $mediaType   = $creative['media_type'];
    $filePath    = e($creative['file_path']);
    $statusBadge = render_status_badge($creative['status']);
    $magicLink   = e(base_url('review.php?token=' . $creative['public_token']));
    $updatedAt   = e(format_datetime_br($creative['updated_at']));
    $fbCount     = isset($creative['feedback_count']) ? (int)$creative['feedback_count'] : 0;

    echo '<div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm hover:shadow-md transition overflow-hidden flex flex-col">';

    // Preview da mídia
    echo '<a href="creative_view.php?id=' . $id . '" class="relative block bg-slate-900 aspect-video overflow-hidden group">';
    if ($mediaType === 'video') {
        echo '<video src="' . $filePath . '" class="w-full h-full object-cover opacity-90 group-hover:scale-105 transition duration-300" muted preload="metadata"></video>';
        echo '<div class="absolute inset-0 flex items-center justify-center bg-black/20 group-hover:bg-black/30 transition">';
        echo '<span class="w-11 h-11 rounded-full bg-white/90 text-slate-900 flex items-center justify-center shadow-lg">';
        echo '<i data-lucide="play" class="w-5 h-5 ml-0.5"></i>';
        echo '</span>';
        echo '</div>';
        echo '<span class="absolute bottom-2.5 left-2.5 px-2 py-0.5 rounded-md bg-black/65 text-white text-[11px] font-medium inline-flex items-center gap-1">';
        echo '<i data-lucide="video" class="w-3 h-3"></i> Vídeo';
        echo '</span>';
    } else {
        echo '<img src="' . $filePath . '" alt="' . $title . '" class="w-full h-full object-cover group-hover:scale-105 transition duration-300">';
        echo '<span class="absolute bottom-2.5 left-2.5 px-2 py-0.5 rounded-md bg-black/65 text-white text-[11px] font-medium inline-flex items-center gap-1">';
        echo '<i data-lucide="image" class="w-3 h-3"></i> Imagem';
        echo '</span>';
    }
    echo '<div class="absolute top-2.5 right-2.5">' . $statusBadge . '</div>';
    echo '</a>';

    // Corpo do Card
    echo '<div class="p-4 flex-1 flex flex-col justify-between gap-3">';
    echo '<div>';
    echo '<div class="text-xs font-semibold text-indigo-600 uppercase tracking-wide mb-1">' . $clientName . '</div>';
    echo '<a href="creative_view.php?id=' . $id . '" class="block text-base font-bold text-slate-900 hover:text-indigo-600 transition line-clamp-1">' . $title . '</a>';
    echo '<div class="mt-2 flex items-center gap-3 text-xs text-slate-500">';
    echo '<span class="inline-flex items-center gap-1"><i data-lucide="message-square" class="w-3.5 h-3.5"></i> ' . $fbCount . '</span>';
    echo '<span class="inline-flex items-center gap-1"><i data-lucide="calendar" class="w-3.5 h-3.5"></i> ' . $updatedAt . '</span>';
    echo '</div>';
    echo '</div>';

    // Ações do Card
    echo '<div class="pt-3 border-t border-slate-100 flex items-center gap-2">';
    echo '<a href="creative_view.php?id=' . $id . '" class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl text-xs font-semibold bg-slate-100 hover:bg-slate-200 text-slate-700 transition">';
    echo '<i data-lucide="eye" class="w-3.5 h-3.5"></i> Visualizar';
    echo '</a>';
    echo '<button type="button" onclick="copyMagicLink(\'' . $magicLink . '\', this)" class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl text-xs font-semibold bg-indigo-50 hover:bg-indigo-100 text-indigo-700 transition">';
    echo '<i data-lucide="link" class="w-3.5 h-3.5"></i> <span>Copiar Link</span>';
    echo '</button>';
    echo '</div>';
    echo '</div>';

    echo '</div>';
}

/**
 * Renderiza a timeline de feedbacks/comentários com timestamps clicáveis para vídeo.
 *
 * @param array  $feedbacks Lista de feedbacks ordenados
 * @param string $mediaType 'video' | 'image'
 * @return void
 */
function render_feedback_timeline($feedbacks, $mediaType = 'image')
{
    if (empty($feedbacks)) {
        echo '<div class="text-center py-8 px-4 rounded-xl border border-dashed border-slate-200 bg-slate-50/50">';
        echo '<i data-lucide="message-circle" class="w-8 h-8 text-slate-300 mx-auto mb-2"></i>';
        echo '<p class="text-sm text-slate-500">Nenhum comentário ou avaliação registrado ainda.</p>';
        echo '</div>';
        return;
    }

    echo '<div class="space-y-3">';
    foreach ($feedbacks as $fb) {
        $isAgency = ($fb['author_role'] === 'agency');
        $roleBadge = $isAgency
            ? '<span class="px-2 py-0.5 rounded-md text-[11px] font-semibold bg-indigo-50 text-indigo-700">Agência</span>'
            : '<span class="px-2 py-0.5 rounded-md text-[11px] font-semibold bg-slate-100 text-slate-700">Cliente</span>';

        switch ($fb['action_taken']) {
            case 'approved':
                $actionBadge = '<span class="inline-flex items-center gap-1 text-[11px] font-semibold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-md"><i data-lucide="check" class="w-3 h-3"></i> Aprovou</span>';
                $borderClass = 'border-emerald-200 bg-emerald-50/20';
                break;
            case 'changes_requested':
                $actionBadge = '<span class="inline-flex items-center gap-1 text-[11px] font-semibold text-rose-700 bg-rose-50 px-2 py-0.5 rounded-md"><i data-lucide="alert-circle" class="w-3 h-3"></i> Pediu Ajuste</span>';
                $borderClass = 'border-rose-200 bg-rose-50/20';
                break;
            default:
                $actionBadge = '<span class="inline-flex items-center gap-1 text-[11px] font-medium text-slate-500 bg-slate-100 px-2 py-0.5 rounded-md"><i data-lucide="message-square" class="w-3 h-3"></i> Comentou</span>';
                $borderClass = 'border-slate-200 bg-white';
                break;
        }

        echo '<div class="rounded-xl border p-3.5 ' . $borderClass . '">';
        echo '<div class="flex flex-wrap items-center justify-between gap-2 mb-1.5">';
        echo '<div class="flex items-center gap-2 flex-wrap">';
        echo '<span class="text-sm font-bold text-slate-900">' . e($fb['author_name']) . '</span>';
        echo $roleBadge;
        echo $actionBadge;
        echo '</div>';
        echo '<span class="text-[11px] text-slate-400">' . e(format_datetime_br($fb['created_at'])) . '</span>';
        echo '</div>';

        if (!empty($fb['video_timestamp']) && $mediaType === 'video') {
            $ts = e($fb['video_timestamp']);
            echo '<div class="mb-2">';
            echo '<button type="button" onclick="seekVideoTo(\'' . $ts . '\')" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-mono font-semibold shadow-sm transition" title="Ir para este momento do vídeo">';
            echo '<i data-lucide="play-circle" class="w-3.5 h-3.5"></i> ' . $ts;
            echo '</button>';
            echo '</div>';
        }

        echo '<p class="text-sm text-slate-700 whitespace-pre-line leading-relaxed">' . e($fb['comment']) . '</p>';
        echo '</div>';
    }
    echo '</div>';
}

/**
 * Renderiza o rodapé padrão e inicializa scripts globais (Lucide, Toast, Clipboard).
 *
 * @param string $extraScripts Scripts JS adicionais antes de fechar </body>
 * @return void
 */
function render_footer($extraScripts = '')
{
    echo <<<HTML
<div id="toast-container" class="fixed bottom-5 right-5 z-50 flex flex-col gap-2 pointer-events-none"></div>

<footer class="mt-auto py-6 border-t border-slate-200/80 bg-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row items-center justify-between gap-2 text-xs text-slate-400">
        <span>AprovaAI &mdash; Aprovação de Criativos para Agências</span>
        <span>Fluxo ágil sem login para o cliente</span>
    </div>
</footer>

<script>
if (typeof lucide !== 'undefined') {
    lucide.createIcons();
}

function toggleMobileNav() {
    var nav = document.getElementById('mobile-nav');
    if (nav) {
        nav.classList.toggle('hidden');
    }
}

function showToast(message, type) {
    type = type || 'success';
    var container = document.getElementById('toast-container');
    if (!container) return;

    var colors = {
        success: 'bg-slate-900 text-white',
        error: 'bg-rose-600 text-white',
        warning: 'bg-amber-600 text-white'
    };
    var toast = document.createElement('div');
    toast.className = 'pointer-events-auto px-4 py-2.5 rounded-xl shadow-lg text-sm font-medium flex items-center gap-2 transition-all duration-300 ' + (colors[type] || colors.success);
    toast.textContent = message;
    container.appendChild(toast);

    setTimeout(function() {
        toast.style.opacity = '0';
        toast.style.transform = 'translateY(8px)';
        setTimeout(function() { toast.remove(); }, 300);
    }, 3000);
}

function copyMagicLink(url, btn) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(url).then(function() {
            showToast('Link mágico copiado! Envie para o cliente.', 'success');
            if (btn) {
                var span = btn.querySelector('span');
                if (span) {
                    var orig = span.textContent;
                    span.textContent = 'Copiado!';
                    setTimeout(function() { span.textContent = orig; }, 2000);
                }
            }
        }).catch(function() {
            fallbackCopyText(url);
        });
    } else {
        fallbackCopyText(url);
    }
}

function fallbackCopyText(text) {
    var input = document.createElement('textarea');
    input.value = text;
    input.style.position = 'fixed';
    input.style.opacity = '0';
    document.body.appendChild(input);
    input.focus();
    input.select();
    try {
        document.execCommand('copy');
        showToast('Link mágico copiado!', 'success');
    } catch (err) {
        showToast('Não foi possível copiar automaticamente.', 'error');
    }
    document.body.removeChild(input);
}

function seekVideoTo(timestampStr, targetVideoId) {
    var video = document.getElementById(targetVideoId || 'creative-video-player');
    if (!video || !timestampStr) return;

    var parts = timestampStr.split(':').map(Number);
    var seconds = 0;
    if (parts.length === 2) {
        seconds = (parts[0] * 60) + parts[1];
    } else if (parts.length === 3) {
        seconds = (parts[0] * 3600) + (parts[1] * 60) + parts[2];
    }

    if (!isNaN(seconds)) {
        video.currentTime = seconds;
        video.play().catch(function() {});
        video.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
}

function formatSecondsToTimestamp(totalSeconds) {
    var sec = Math.floor(totalSeconds || 0);
    var mins = Math.floor(sec / 60);
    var remSec = sec % 60;
    var mm = mins < 10 ? '0' + mins : '' + mins;
    var ss = remSec < 10 ? '0' + remSec : '' + remSec;
    return mm + ':' + ss;
}
</script>
{$extraScripts}
</body>
</html>
HTML;
}
