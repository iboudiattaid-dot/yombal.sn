<?php

declare(strict_types=1);

namespace Yombal\Core\Partners;

if (! defined('ABSPATH')) {
    exit;
}

final class Partner_Stats {
    public static function count_orders(int $partner_user_id): int {
        global $wpdb;

        if ($partner_user_id <= 0) {
            return 0;
        }

        $lookup_table = $wpdb->prefix . 'wc_order_product_lookup';
        $order_stats_table = $wpdb->prefix . 'wc_order_stats';

        $lookup_exists = (bool) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $lookup_table));
        $stats_exists = (bool) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $order_stats_table));

        if (! $lookup_exists || ! $stats_exists) {
            return 0;
        }

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(DISTINCT lookup_table.order_id)
                FROM {$lookup_table} AS lookup_table
                INNER JOIN {$wpdb->posts} AS products ON products.ID = lookup_table.product_id
                INNER JOIN {$order_stats_table} AS stats ON stats.order_id = lookup_table.order_id
                WHERE products.post_author = %d
                AND products.post_type = 'product'
                AND stats.status IN ('wc-completed', 'wc-processing', 'wc-on-hold', 'completed', 'processing', 'on-hold')",
                $partner_user_id
            )
        );
    }

    public static function count_orders_since(int $partner_user_id, int $days = 7): int {
        global $wpdb;

        if ($partner_user_id <= 0 || $days <= 0) {
            return 0;
        }

        $lookup_table = $wpdb->prefix . 'wc_order_product_lookup';
        $order_stats_table = $wpdb->prefix . 'wc_order_stats';

        $lookup_exists = (bool) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $lookup_table));
        $stats_exists = (bool) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $order_stats_table));

        if (! $lookup_exists || ! $stats_exists) {
            return 0;
        }

        $threshold = gmdate('Y-m-d H:i:s', current_time('timestamp', true) - ($days * DAY_IN_SECONDS));

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(DISTINCT lookup_table.order_id)
                FROM {$lookup_table} AS lookup_table
                INNER JOIN {$wpdb->posts} AS products ON products.ID = lookup_table.product_id
                INNER JOIN {$order_stats_table} AS stats ON stats.order_id = lookup_table.order_id
                WHERE products.post_author = %d
                AND products.post_type = 'product'
                AND stats.status IN ('wc-completed', 'wc-processing', 'wc-on-hold', 'completed', 'processing', 'on-hold')
                AND COALESCE(stats.date_updated_gmt, stats.date_created_gmt) >= %s",
                $partner_user_id,
                $threshold
            )
        );
    }

    public static function recent_orders(int $partner_user_id, int $limit = 5): array {
        global $wpdb;

        if ($partner_user_id <= 0 || $limit <= 0) {
            return [];
        }

        $lookup_table = $wpdb->prefix . 'wc_order_product_lookup';
        $order_stats_table = $wpdb->prefix . 'wc_order_stats';

        $lookup_exists = (bool) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $lookup_table));
        $stats_exists = (bool) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $order_stats_table));

        if (! $lookup_exists || ! $stats_exists) {
            return [];
        }

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT DISTINCT lookup_table.order_id,
                    stats.status AS order_status,
                    COALESCE(stats.date_updated_gmt, stats.date_created_gmt) AS modified
                FROM {$lookup_table} AS lookup_table
                INNER JOIN {$wpdb->posts} AS products ON products.ID = lookup_table.product_id
                INNER JOIN {$order_stats_table} AS stats ON stats.order_id = lookup_table.order_id
                WHERE products.post_author = %d
                AND products.post_type = 'product'
                ORDER BY COALESCE(stats.date_updated_gmt, stats.date_created_gmt) DESC
                LIMIT %d",
                $partner_user_id,
                $limit
            ),
            ARRAY_A
        );

        return is_array($rows) ? $rows : [];
    }

    public static function average_rating(int $partner_user_id): string {
        global $wpdb;

        if ($partner_user_id <= 0) {
            return '';
        }

        $rating = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT AVG(CAST(meta.meta_value AS DECIMAL(10,2)))
                FROM {$wpdb->comments} AS comments
                INNER JOIN {$wpdb->commentmeta} AS meta
                    ON meta.comment_id = comments.comment_ID
                    AND meta.meta_key = 'rating'
                INNER JOIN {$wpdb->posts} AS products
                    ON products.ID = comments.comment_post_ID
                WHERE comments.comment_approved = '1'
                AND products.post_type = 'product'
                AND products.post_author = %d",
                $partner_user_id
            )
        );

        if ($rating === null) {
            return '';
        }

        $value = (float) $rating;

        return $value > 0 ? number_format($value, 1) : '';
    }
}
