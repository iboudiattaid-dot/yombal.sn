<?php

declare(strict_types=1);

namespace Yombal\Core\Journeys;

if (! defined('ABSPATH')) {
    exit;
}

final class Fixtures {
    public const FLAG_META_KEY = 'yombal_fixture';
    public const KEY_META_KEY = 'yombal_fixture_key';

    public static function mark_user(int $user_id, string $key): void {
        if ($user_id <= 0) {
            return;
        }

        update_user_meta($user_id, self::FLAG_META_KEY, '1');
        update_user_meta($user_id, self::KEY_META_KEY, sanitize_key($key));
    }

    public static function mark_post(int $post_id, string $key): void {
        if ($post_id <= 0) {
            return;
        }

        update_post_meta($post_id, self::FLAG_META_KEY, '1');
        update_post_meta($post_id, self::KEY_META_KEY, sanitize_key($key));
    }

    public static function is_fixture_user(int $user_id): bool {
        return $user_id > 0 && (string) get_user_meta($user_id, self::FLAG_META_KEY, true) === '1';
    }

    public static function is_fixture_post(int $post_id): bool {
        return $post_id > 0 && (string) get_post_meta($post_id, self::FLAG_META_KEY, true) === '1';
    }

    public static function fixture_key_for_user(int $user_id): string {
        if ($user_id <= 0) {
            return '';
        }

        return sanitize_key((string) get_user_meta($user_id, self::KEY_META_KEY, true));
    }

    public static function fixture_key_for_post(int $post_id): string {
        if ($post_id <= 0) {
            return '';
        }

        return sanitize_key((string) get_post_meta($post_id, self::KEY_META_KEY, true));
    }

    public static function filter_public_user_ids(array $user_ids): array {
        $visible = [];
        foreach (array_map('intval', $user_ids) as $user_id) {
            if ($user_id > 0 && ! self::is_fixture_user($user_id)) {
                $visible[] = $user_id;
            }
        }

        return array_values(array_unique($visible));
    }
}
