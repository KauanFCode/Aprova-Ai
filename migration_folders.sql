-- ============================================================================
-- AprovaAI - Migração Incremental: Pastas / Campanhas e Upload em Lote
-- Compatível com MySQL 5.7+ / 8.0 / MariaDB (InnoDB, utf8mb4_unicode_ci)
-- Seguro para execução direta no phpMyAdmin sem apagar dados existentes.
-- ============================================================================

SET NAMES utf8mb4;

-- ----------------------------------------------------------------------------
-- 1. Criar tabela: folders (Pastas / Campanhas para agrupamento de criativos)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `folders` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `tenant_id` INT NOT NULL,
    `client_id` INT NOT NULL,
    `name` VARCHAR(150) NOT NULL,
    `description` TEXT NULL,
    `public_token` VARCHAR(64) NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_folders_public_token` (`public_token`),
    KEY `idx_folders_tenant_id` (`tenant_id`),
    KEY `idx_folders_client_id` (`client_id`),
    CONSTRAINT `fk_folders_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_folders_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 2. Alterar tabela: creatives (Adicionar vínculo opcional com folders)
-- Se folder_id for NULL, o criativo é tratado como Avulso (Individual).
-- ----------------------------------------------------------------------------
ALTER TABLE `creatives`
    ADD COLUMN `folder_id` INT NULL DEFAULT NULL AFTER `client_id`,
    ADD KEY `idx_creatives_folder_id` (`folder_id`),
    ADD CONSTRAINT `fk_creatives_folder` FOREIGN KEY (`folder_id`) REFERENCES `folders` (`id`) ON DELETE SET NULL;
