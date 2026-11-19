-- ============================================================================
-- AprovaAI - Schema e Dados Iniciais (Seed)
-- Compatível com MySQL 5.7+ / 8.0 / MariaDB (InnoDB, utf8mb4_unicode_ci)
-- Pronto para importação direta via phpMyAdmin
-- ============================================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------------------------------------------------------
-- 1. Tabela: tenants (Agências / Freelancers)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tenants` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(120) NOT NULL,
    `slug` VARCHAR(120) NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_tenants_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 2. Tabela: users (Usuários da Agência)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `tenant_id` INT NOT NULL,
    `name` VARCHAR(120) NOT NULL,
    `email` VARCHAR(150) NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_users_email` (`email`),
    KEY `idx_users_tenant_id` (`tenant_id`),
    CONSTRAINT `fk_users_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 3. Tabela: clients (Clientes atendidos pela Agência)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `clients` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `tenant_id` INT NOT NULL,
    `name` VARCHAR(120) NOT NULL,
    `contact_phone` VARCHAR(30) NULL DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_clients_tenant_id` (`tenant_id`),
    CONSTRAINT `fk_clients_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 4. Tabela: creatives (Criativos: Vídeos e Imagens + Copy)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `creatives` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `tenant_id` INT NOT NULL,
    `client_id` INT NOT NULL,
    `title` VARCHAR(150) NOT NULL,
    `media_type` ENUM('video', 'image') NOT NULL,
    `file_path` VARCHAR(255) NOT NULL,
    `copy_text` TEXT NULL,
    `status` ENUM('pending', 'changes_requested', 'approved') NOT NULL DEFAULT 'pending',
    `public_token` VARCHAR(64) NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_creatives_public_token` (`public_token`),
    KEY `idx_creatives_tenant_id` (`tenant_id`),
    KEY `idx_creatives_client_id` (`client_id`),
    KEY `idx_creatives_status` (`status`),
    CONSTRAINT `fk_creatives_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_creatives_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 5. Tabela: feedbacks (Histórico de comentários e aprovações)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `feedbacks` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `creative_id` INT NOT NULL,
    `author_name` VARCHAR(100) NOT NULL,
    `author_role` ENUM('agency', 'client') NOT NULL,
    `comment` TEXT NOT NULL,
    `video_timestamp` VARCHAR(15) NULL DEFAULT NULL,
    `action_taken` ENUM('comment', 'changes_requested', 'approved') NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_feedbacks_creative_id` (`creative_id`),
    CONSTRAINT `fk_feedbacks_creative` FOREIGN KEY (`creative_id`) REFERENCES `creatives` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================================
-- DADOS INICIAIS (SEED DE DEMONSTRAÇÃO)
-- Login de teste: admin@aprovaai.com / Senha: 123456
-- ============================================================================

INSERT INTO `tenants` (`id`, `name`, `slug`) VALUES
(1, 'Agência Demo AprovaAI', 'agencia-demo-aprovaai')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

INSERT INTO `users` (`id`, `tenant_id`, `name`, `email`, `password_hash`) VALUES
(1, 1, 'Administrador Demo', 'admin@aprovaai.com', '$2y$10$otHGZ7CKlEz12udvRfVCE.aheRMAZ2.9gtfeB2mR/ftFL8Wd0mvwu')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

INSERT INTO `clients` (`id`, `tenant_id`, `name`, `contact_phone`) VALUES
(1, 1, 'Cafeteria Grão Nobre', '(11) 99999-8888')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);
