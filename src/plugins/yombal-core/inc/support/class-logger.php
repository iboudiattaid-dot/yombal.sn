<?php

declare(strict_types=1);

namespace Yombal\Core\Support;

if (! defined('ABSPATH')) {
    exit;
}

final class Logger {
    public static function info(string $message, array $context = []): void {
        self::write('INFO', $message, $context);
    }

    public static function warning(string $message, array $context = []): void {
        self::write('WARNING', $message, $context);
    }

    public static function error(string $message, array $context = []): void {
        self::write('ERROR', $message, $context);
    }

    private static function write(string $level, string $message, array $context): void {
        if (! defined('WP_DEBUG_LOG') || ! WP_DEBUG_LOG) {
            return;
        }

        $suffix = $context ? ' ' . wp_json_encode($context) : '';
        error_log(sprintf('[yombal-core][%s] %s%s', $level, $message, $suffix));
    }
}
