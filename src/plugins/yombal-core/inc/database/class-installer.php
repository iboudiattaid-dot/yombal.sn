<?php

declare(strict_types=1);

namespace Yombal\Core\Database;

use wpdb;

if (! defined('ABSPATH')) {
    exit;
}

final class Installer {
    public const DB_VERSION = '0.2.0';

    public static function boot(): void {
        add_action('init', [self::class, 'maybe_upgrade'], 2);
    }

    public static function maybe_upgrade(): void {
        if ((string) get_option('yombal_core_db_version', '') === self::DB_VERSION) {
            return;
        }

        self::activate();
    }

    public static function activate(): void {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();

        $partner_profiles = self::table_name('yombal_partner_profiles');
        $measurements = self::table_name('yombal_mesures');
        $requests = self::table_name('yombal_couture_requests');
        $request_events = self::table_name('yombal_couture_request_events');
        $notifications = self::table_name('yombal_notifications');
        $message_threads = self::table_name('yombal_message_threads');
        $message_entries = self::table_name('yombal_message_entries');
        $support_tickets = self::table_name('yombal_support_tickets');
        $support_replies = self::table_name('yombal_support_replies');

        $sql = [];

        $sql[] = "CREATE TABLE {$partner_profiles} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            partner_type VARCHAR(40) NOT NULL DEFAULT 'tailor',
            profile_status VARCHAR(30) NOT NULL DEFAULT 'pending',
            display_name VARCHAR(190) NOT NULL,
            store_name VARCHAR(190) DEFAULT NULL,
            city VARCHAR(120) DEFAULT NULL,
            phone VARCHAR(60) DEFAULT NULL,
            specialties LONGTEXT DEFAULT NULL,
            materials LONGTEXT DEFAULT NULL,
            biography LONGTEXT DEFAULT NULL,
            legacy_vendor_type VARCHAR(60) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_id (user_id),
            KEY partner_type (partner_type),
            KEY profile_status (profile_status)
        ) {$charset_collate};";

        $sql[] = "CREATE TABLE {$measurements} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            profil_nom VARCHAR(100) NOT NULL DEFAULT 'Mon profil',
            occasion VARCHAR(100) DEFAULT NULL,
            poitrine DECIMAL(5,1) DEFAULT NULL,
            taille DECIMAL(5,1) DEFAULT NULL,
            hanches DECIMAL(5,1) DEFAULT NULL,
            epaules DECIMAL(5,1) DEFAULT NULL,
            longueur_buste DECIMAL(5,1) DEFAULT NULL,
            longueur_robe DECIMAL(5,1) DEFAULT NULL,
            longueur_manche DECIMAL(5,1) DEFAULT NULL,
            tour_bras DECIMAL(5,1) DEFAULT NULL,
            encolure DECIMAL(5,1) DEFAULT NULL,
            notes TEXT DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id)
        ) {$charset_collate};";

        $sql[] = "CREATE TABLE {$requests} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            customer_id BIGINT(20) UNSIGNED NOT NULL,
            tailor_user_id BIGINT(20) UNSIGNED NOT NULL,
            measurement_profile_id BIGINT(20) UNSIGNED DEFAULT NULL,
            wc_order_id BIGINT(20) UNSIGNED DEFAULT NULL,
            model_source_type VARCHAR(30) NOT NULL DEFAULT 'upload',
            model_reference VARCHAR(190) DEFAULT NULL,
            model_attachment_id BIGINT(20) UNSIGNED DEFAULT NULL,
            cart_snapshot LONGTEXT DEFAULT NULL,
            fabric_requirements LONGTEXT DEFAULT NULL,
            customer_notes LONGTEXT DEFAULT NULL,
            tailor_response LONGTEXT DEFAULT NULL,
            required_fabric_qty DECIMAL(10,2) DEFAULT NULL,
            couture_price DECIMAL(18,2) DEFAULT NULL,
            status VARCHAR(40) NOT NULL DEFAULT 'pending_tailor_review',
            payment_unlocked TINYINT(1) NOT NULL DEFAULT 0,
            expires_at DATETIME DEFAULT NULL,
            validated_at DATETIME DEFAULT NULL,
            cancelled_at DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY customer_id (customer_id),
            KEY tailor_user_id (tailor_user_id),
            KEY status (status),
            KEY expires_at (expires_at),
            KEY wc_order_id (wc_order_id)
        ) {$charset_collate};";

        $sql[] = "CREATE TABLE {$request_events} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            request_id BIGINT(20) UNSIGNED NOT NULL,
            actor_user_id BIGINT(20) UNSIGNED DEFAULT NULL,
            event_type VARCHAR(60) NOT NULL,
            payload LONGTEXT DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY request_id (request_id),
            KEY event_type (event_type)
        ) {$charset_collate};";

        $sql[] = "CREATE TABLE {$notifications} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            channel VARCHAR(30) NOT NULL DEFAULT 'in_app',
            type VARCHAR(60) NOT NULL,
            title VARCHAR(190) NOT NULL,
            message LONGTEXT DEFAULT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            related_object_type VARCHAR(30) DEFAULT NULL,
            related_object_id BIGINT(20) UNSIGNED DEFAULT NULL,
            sent_at DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY status (status),
            KEY type (type)
        ) {$charset_collate};";

        $sql[] = "CREATE TABLE {$message_threads} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            subject VARCHAR(190) NOT NULL,
            customer_id BIGINT(20) UNSIGNED DEFAULT NULL,
            partner_id BIGINT(20) UNSIGNED DEFAULT NULL,
            order_id BIGINT(20) UNSIGNED DEFAULT NULL,
            product_id BIGINT(20) UNSIGNED DEFAULT NULL,
            couture_request_id BIGINT(20) UNSIGNED DEFAULT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'open',
            last_message_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY customer_id (customer_id),
            KEY partner_id (partner_id),
            KEY status (status),
            KEY last_message_at (last_message_at)
        ) {$charset_collate};";

        $sql[] = "CREATE TABLE {$message_entries} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            thread_id BIGINT(20) UNSIGNED NOT NULL,
            sender_user_id BIGINT(20) UNSIGNED NOT NULL,
            recipient_user_id BIGINT(20) UNSIGNED NOT NULL,
            message LONGTEXT NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'sent',
            read_at DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY thread_id (thread_id),
            KEY sender_user_id (sender_user_id),
            KEY recipient_user_id (recipient_user_id),
            KEY status (status)
        ) {$charset_collate};";

        $sql[] = "CREATE TABLE {$support_tickets} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            order_id BIGINT(20) UNSIGNED DEFAULT NULL,
            product_id BIGINT(20) UNSIGNED DEFAULT NULL,
            customer_id BIGINT(20) UNSIGNED DEFAULT NULL,
            partner_user_id BIGINT(20) UNSIGNED DEFAULT NULL,
            category VARCHAR(60) NOT NULL DEFAULT 'general',
            priority VARCHAR(30) NOT NULL DEFAULT 'normal',
            status VARCHAR(30) NOT NULL DEFAULT 'open',
            subject VARCHAR(190) NOT NULL,
            message LONGTEXT NOT NULL,
            attachment_ids LONGTEXT DEFAULT NULL,
            last_reply_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            closed_at DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY order_id (order_id),
            KEY customer_id (customer_id),
            KEY partner_user_id (partner_user_id),
            KEY status (status),
            KEY category (category)
        ) {$charset_collate};";

        $sql[] = "CREATE TABLE {$support_replies} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            ticket_id BIGINT(20) UNSIGNED NOT NULL,
            author_user_id BIGINT(20) UNSIGNED NOT NULL,
            message LONGTEXT NOT NULL,
            attachment_ids LONGTEXT DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY ticket_id (ticket_id),
            KEY author_user_id (author_user_id)
        ) {$charset_collate};";

        foreach ($sql as $statement) {
            dbDelta($statement);
        }

        update_option('yombal_core_db_version', self::DB_VERSION);
    }

    public static function table_name(string $suffix): string {
        global $wpdb;

        return $wpdb->prefix . $suffix;
    }
}
