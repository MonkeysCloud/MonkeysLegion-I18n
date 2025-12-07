<?php

declare(strict_types=1);

/**
 * Migration for creating translations table
 * 
 * This migration creates a table for storing dynamic translations
 * that can be managed through an admin interface
 */

return [
    'up' => function($db) {
        $sql = "
            CREATE TABLE IF NOT EXISTS `translations` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `locale` VARCHAR(10) NOT NULL,
                `group` VARCHAR(100) NOT NULL,
                `namespace` VARCHAR(100) NULL,
                `key` VARCHAR(255) NOT NULL,
                `value` TEXT NOT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY `unique_translation` (`locale`, `group`, `namespace`, `key`),
                INDEX `idx_locale` (`locale`),
                INDEX `idx_group` (`group`),
                INDEX `idx_namespace` (`namespace`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        
        $db->exec($sql);
        
        // Insert some example translations
        $examples = "
            INSERT IGNORE INTO `translations` (`locale`, `group`, `namespace`, `key`, `value`) VALUES
            ('en', 'messages', NULL, 'welcome', 'Welcome to MonkeysLegion'),
            ('en', 'messages', NULL, 'goodbye', 'Goodbye!'),
            ('es', 'messages', NULL, 'welcome', 'Bienvenido a MonkeysLegion'),
            ('es', 'messages', NULL, 'goodbye', '¡Adiós!'),
            ('en', 'validation', NULL, 'required', 'The :field field is required.'),
            ('es', 'validation', NULL, 'required', 'El campo :field es obligatorio.'),
            ('en', 'auth', NULL, 'failed', 'These credentials do not match our records.'),
            ('es', 'auth', NULL, 'failed', 'Estas credenciales no coinciden con nuestros registros.');
        ";
        
        $db->exec($examples);
    },
    
    'down' => function($db) {
        $sql = "DROP TABLE IF EXISTS `translations`";
        $db->exec($sql);
    }
];
