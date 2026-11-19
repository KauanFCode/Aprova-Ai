# AprovaAI — Documentação Técnica

Documentação completa da arquitetura, biblioteca de funções (`/includes/functions.php`), autenticação (`/includes/auth.php`), componentes visuais (`/includes/components.php`), endpoints e fluxos do sistema **AprovaAI** (incluindo Landing Page, Pastas/Campanhas e Upload em Lote).

---

## 1. Arquitetura e Compatibilidade

- **PHP:** Estritamente compatível com **PHP 7.4+** (sem recursos exclusivos do PHP 8.0+).
- **Banco de Dados:** MySQL 5.7+ / 8.0 / MariaDB com `InnoDB` e `utf8mb4_unicode_ci`, acessado exclusivamente via **PHP PDO** com *Prepared Statements*.
- **Migrações:**
  - `database.sql`: Schema inicial (`tenants`, `users`, `clients`, `creatives`, `feedbacks`) + seed de demonstração.
  - `migration_folders.sql`: Migração incremental que adiciona a tabela `folders` e a coluna opcional `creatives.folder_id` (`ON DELETE SET NULL`).
- **Hospedagem cPanel:**
  - `.htaccess`: Protege `config/`, `includes/`, `.git/`, `database.sql` e `DOCS.md`; encapsula `php_value` em `<IfModule mod_php7.c>` e `<IfModule lsapi_module>` para evitar Erro 500 em servidores PHP-FPM/FastCGI.
  - `.user.ini`: Define `upload_max_filesize = 64M` e `post_max_size = 64M` para PHP-FPM/FastCGI.
  - `uploads/.htaccess`: Bloqueia execução de scripts PHP e listagem de diretórios dentro da pasta de uploads.
  - `BASE_URL`: Detectada dinamicamente em `config/db.php`, permitindo instalação na raiz do domínio ou em subpastas (ex: `meudominio.com/15987/aprovaai/`).

---

## 2. Configuração e Segurança (`config/db.php`)

- **Inicialização de Sessão:** Verifica `session_status() === PHP_SESSION_NONE` antes de `session_start()`.
- `detect_base_url(): string` — Detecta protocolo (`http`/`https`), host e subpasta da aplicação para compor `BASE_URL` sem barra final.
- `get_pdo(): PDO` — Retorna instância singleton de `PDO` configurada com `ERRMODE_EXCEPTION`, `FETCH_ASSOC` e `EMULATE_PREPARES = false`.
- `generate_csrf_token(): string` — Gera e armazena o token CSRF na sessão (`$_SESSION['csrf_token']`).
- `verify_csrf_token(?string $token): bool` — Valida o token recebido com `hash_equals()`.
- `csrf_field(): string` — Retorna `<input type="hidden" name="csrf_token" ...>` pronto para uso em formulários HTML.

---

## 3. Autenticação Multi-Tenant (`includes/auth.php`)

- `is_logged_in(): bool` — Verifica se `user_id` e `tenant_id` estão ativos na sessão.
- `require_auth(): void` — Protege páginas privadas da agência, redirecionando para `login.php` caso não autenticado.
- `current_user(): ?array` — Retorna `['id', 'tenant_id', 'name', 'email', 'tenant_name']` do usuário logado.
- `attempt_login(PDO $pdo, string $email, string $password): array` — Autentica usuário via `password_verify()`, regenera o ID da sessão e popula `$_SESSION`.
- `register_agency(PDO $pdo, string $agencyName, string $userName, string $email, string $password): array` — Cria `tenant` (com slug único) e `user` administrador em transação atômica, autenticando automaticamente após o cadastro.
- `logout_user(): void` — Limpa `$_SESSION`, remove cookie de sessão e destrói a sessão.

---

## 4. Biblioteca Central de Funções (`includes/functions.php`)

| Função | Descrição |
| :--- | :--- |
| `e($value)` | Escapa strings com `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')` contra XSS. |
| `base_url($path = '')` | Monta URL absoluta combinando `BASE_URL` e caminho relativo. |
| `redirect($location)` | Envia cabeçalho `Location` e encerra execução (`exit`). |
| `set_flash($type, $message)` | Armazena alerta temporário na sessão (`success`, `error`, `warning`, `info`). |
| `get_flash()` | Recupera e limpa o alerta flash da sessão. |
| `slugify($text)` | Converte texto em slug ASCII minúsculo separado por hífens. |
| `generate_unique_slug(PDO $pdo, $name)` | Garante unicidade do `slug` na tabela `tenants`. |
| `generate_public_token($bytes = 24)` | Gera hash hexadecimal seguro (`bin2hex(random_bytes())`) para o Link Mágico. |
| `normalize_files_array($filesEntry)` | Normaliza `$_FILES` quando `<input multiple>` é utilizado para upload em lote. |
| `handle_creative_upload($file, $tenantId)` | Valida tamanho (até 64MB) e MIME real via `finfo_file` (`JPG`, `PNG`, `WebP`, `MP4`, `WebM`), cria `uploads/{tenant_id}/` automaticamente (`0755`) e salva com nome aleatório. |
| `delete_creative_file($relativePath)` | Remove fisicamente arquivo dentro de `uploads/` validando caminho via `realpath()`. |
| `format_datetime_br($datetime)` | Converte `Y-m-d H:i:s` para `d/m/Y às H:i`. |
| `get_status_meta($status)` | Retorna rótulo, classes Tailwind e ícone Lucide para `pending`, `changes_requested` e `approved`. |
| `is_valid_video_timestamp($timestamp)` | Valida timestamps no formato `MM:SS` ou `HH:MM:SS`. |
| `json_response($data, $statusCode = 200)` | Emite resposta JSON UTF-8 com código HTTP e encerra execução. |
| `create_folder($pdo, $tenantId, $clientId, $name, $description)` | Cria uma pasta/campanha validando propriedade do cliente pelo `tenant_id`. |
| `get_folders_by_tenant($pdo, $tenantId, $clientId = 0)` | Lista pastas do tenant com contagem total e de aprovados. |
| `get_folder_by_id($pdo, $folderId, $tenantId)` | Busca uma pasta específica isolada pelo `tenant_id`. |
| `get_folder_by_token($pdo, $publicToken)` | Busca uma pasta pública via `public_token` para o cliente. |
| `get_folder_progress($approvedCount, $totalCount)` | Retorna `approved`, `total`, `percent` e `label` de progresso. |

---

## 5. Biblioteca de Componentes UI (`includes/components.php`)

- `render_head($title, $extraHead = '')` — Estrutura HTML5, Google Fonts (`Inter`), Tailwind CSS CDN e Lucide Icons CDN.
- `render_navbar($activePage = 'dashboard')` — Barra superior responsiva com menu desktop/mobile, nome da agência e botão de logout.
- `render_status_badge($status)` — Pill badge estilizado com ícone Lucide conforme status do criativo.
- `render_flash_alert()` — Renderiza alertas flash de sessão com botão de fechar.
- `render_folder_card($folder)` — Card de Pasta/Campanha com cliente, barra de progresso, botão **Abrir Pasta** (`folder_view.php?id=X`) e **Copiar Link da Pasta** (`review.php?folder=HASH`).
- `render_creative_card($creative)` — Card do Dashboard com preview de imagem/vídeo, status, cliente, contagem de feedbacks e botões **Visualizar** e **Copiar Link**.
- `render_feedback_timeline($feedbacks, $mediaType = 'image')` — Timeline cronológica reversa de comentários e decisões, com botões clicáveis de timestamp (`seekVideoTo('MM:SS')`) para vídeos.
- `render_footer($extraScripts = '')` — Rodapé padrão, container de toasts e scripts globais (`showToast`, `copyMagicLink`, `seekVideoTo`, `formatSecondsToTimestamp`).

---

## 6. Fluxos Principais e Endpoints (`api/action.php`)

1. **Landing Page (`index.php`):**
   - Apresenta o produto com Hero, mockup de timestamp `00:14`, Como Funciona, 4 Diferenciais e Planos.
   - Se o usuário já estiver logado, altera o CTA para **"Acessar Meu Painel"** (`dashboard.php`) sem redirecionamento forçado.
2. **Gestão de Pastas e Upload em Lote (`dashboard.php`, `folder_view.php`, `creative_new.php`):**
   - A agência cria pastas via modal no Dashboard (`action=create_folder`) e envia múltiplos vídeos/imagens de uma só vez em `creative_new.php` vinculando à pasta (`folder_id`) ou mantendo como avulso (`folder_id IS NULL`).
   - Em `folder_view.php?id=X`, a agência acompanha o progresso da campanha e pode excluir a pasta (`action=delete_folder`), mantendo os criativos como avulsos (`ON DELETE SET NULL`).
3. **Aprovação Pública Individual ou por Pasta (`review.php`):**
   - `?token=HASH`: Exibe o criativo individual para aprovação, pedido de alteração com minutagem (`MM:SS`) ou comentário.
   - `?folder=HASH`: Exibe a pasta inteira em feed mobile-first com contador de progresso, botão global **"Aprovar Todos os Criativos Restantes"** (`action=approve_folder_all`) e controles individuais por peça.
