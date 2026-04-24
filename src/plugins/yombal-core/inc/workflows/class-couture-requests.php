<?php

declare(strict_types=1);

namespace Yombal\Core\Workflows;

use Yombal\Core\Database\Installer;
use Yombal\Core\Notifications\Notification_Center;
use Yombal\Core\Support\Logger;

if (! defined('ABSPATH')) {
    exit;
}

final class Couture_Requests {
    public const STATUS_PENDING_TAILOR_REVIEW = 'pending_tailor_review';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_PAYMENT_COMPLETED = 'payment_completed';
    public const STATUS_NEEDS_MORE_FABRIC = 'needs_more_fabric';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CANCELLED = 'cancelled';

    public static function boot(): void {
        add_action('yombal_couture_request_expiry', [self::class, 'expire_request'], 10, 1);
        add_filter('cron_schedules', [self::class, 'register_cron_schedule']);
        add_action('init', [self::class, 'ensure_expiry_sweep_scheduled']);
        add_action('yombal_couture_request_expiry_sweep', [self::class, 'sweep_expired_requests']);
    }

    public static function create(array $data): int {
        global $wpdb;

        $defaults = [
            'customer_id' => get_current_user_id(),
            'tailor_user_id' => 0,
            'measurement_profile_id' => null,
            'wc_order_id' => null,
            'model_source_type' => 'upload',
            'model_reference' => null,
            'model_attachment_id' => null,
            'cart_snapshot' => null,
            'fabric_requirements' => null,
            'customer_notes' => null,
            'tailor_response' => null,
            'required_fabric_qty' => null,
            'couture_price' => null,
            'status' => self::STATUS_PENDING_TAILOR_REVIEW,
            'payment_unlocked' => 0,
            'expires_at' => gmdate('Y-m-d H:i:s', time() + DAY_IN_SECONDS),
        ];

        $payload = wp_parse_args($data, $defaults);
        $table = Installer::table_name('yombal_couture_requests');

        $wpdb->insert(
            $table,
            [
                'customer_id' => (int) $payload['customer_id'],
                'tailor_user_id' => (int) $payload['tailor_user_id'],
                'measurement_profile_id' => $payload['measurement_profile_id'] ? (int) $payload['measurement_profile_id'] : null,
                'wc_order_id' => $payload['wc_order_id'] ? (int) $payload['wc_order_id'] : null,
                'model_source_type' => sanitize_key((string) $payload['model_source_type']),
                'model_reference' => $payload['model_reference'] ? sanitize_text_field((string) $payload['model_reference']) : null,
                'model_attachment_id' => $payload['model_attachment_id'] ? (int) $payload['model_attachment_id'] : null,
                'cart_snapshot' => is_string($payload['cart_snapshot']) ? $payload['cart_snapshot'] : wp_json_encode($payload['cart_snapshot']),
                'fabric_requirements' => is_string($payload['fabric_requirements']) ? $payload['fabric_requirements'] : wp_json_encode($payload['fabric_requirements']),
                'customer_notes' => $payload['customer_notes'] ? wp_kses_post((string) $payload['customer_notes']) : null,
                'tailor_response' => $payload['tailor_response'] ? wp_kses_post((string) $payload['tailor_response']) : null,
                'required_fabric_qty' => $payload['required_fabric_qty'] !== null ? (float) $payload['required_fabric_qty'] : null,
                'couture_price' => $payload['couture_price'] !== null ? (float) $payload['couture_price'] : null,
                'status' => sanitize_key((string) $payload['status']),
                'payment_unlocked' => ! empty($payload['payment_unlocked']) ? 1 : 0,
                'expires_at' => (string) $payload['expires_at'],
            ]
        );

        $request_id = (int) $wpdb->insert_id;
        if ($request_id <= 0) {
            return 0;
        }

        self::add_event($request_id, (int) $payload['customer_id'], 'request_created', [
            'tailor_user_id' => (int) $payload['tailor_user_id'],
        ]);

        Notification_Center::create(
            (int) $payload['customer_id'],
            'couture_request_created',
            'Demande couture envoyee',
            'Votre demande a bien ete envoyee au couturier. Vous pourrez passer au paiement des qu il aura confirme la confection.',
            'couture_request',
            $request_id
        );

        Notification_Center::create(
            (int) $payload['tailor_user_id'],
            'couture_request_received',
            'Nouvelle demande couture',
            'Une nouvelle demande client vous attend. Merci de repondre dans les 24 heures.',
            'couture_request',
            $request_id
        );

        self::schedule_expiry($request_id, (string) $payload['expires_at']);

        return $request_id;
    }

    public static function get(int $request_id): ?array {
        global $wpdb;

        if ($request_id <= 0) {
            return null;
        }

        $row = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT * FROM ' . Installer::table_name('yombal_couture_requests') . ' WHERE id = %d LIMIT 1',
                $request_id
            ),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    public static function get_status_label(string $status): string {
        return match ($status) {
            self::STATUS_PENDING_TAILOR_REVIEW => 'En attente de reponse du couturier',
            self::STATUS_APPROVED => 'Confirmee',
            self::STATUS_PAYMENT_COMPLETED => 'Payee',
            self::STATUS_NEEDS_MORE_FABRIC => 'Tissu supplementaire necessaire',
            self::STATUS_EXPIRED => 'Expiree',
            self::STATUS_CANCELLED => 'Annulee',
            default => $status,
        };
    }

    public static function approve(int $request_id, int $actor_user_id, ?float $couture_price = null): bool {
        $updated = self::update_status($request_id, $actor_user_id, self::STATUS_APPROVED, [
            'payment_unlocked' => 1,
            'validated_at' => current_time('mysql', true),
            'couture_price' => $couture_price,
        ], 'request_approved');

        if (! $updated) {
            return false;
        }

        $request = self::get($request_id);
        if ($request) {
            Notification_Center::create(
                (int) $request['customer_id'],
                'couture_request_approved',
                'Demande couture confirmee',
                'Votre couturier a confirme la demande. Vous pouvez maintenant regler le tissu et la confection.',
                'couture_request',
                $request_id
            );
        }

        return true;
    }

    public static function mark_requires_more_fabric(int $request_id, int $actor_user_id, float $required_qty, string $message = ''): bool {
        $updated = self::update_status($request_id, $actor_user_id, self::STATUS_NEEDS_MORE_FABRIC, [
            'required_fabric_qty' => $required_qty,
            'tailor_response' => $message,
        ], 'request_requires_more_fabric');

        if (! $updated) {
            return false;
        }

        $request = self::get($request_id);
        if ($request) {
            Notification_Center::create(
                (int) $request['customer_id'],
                'couture_request_needs_more_fabric',
                'Tissu supplementaire necessaire',
                'Votre couturier a indique qu il faut un peu plus de tissu. Consultez la demande pour voir la quantite recommandee.',
                'couture_request',
                $request_id
            );
        }

        return true;
    }

    public static function expire_request(int $request_id): void {
        $request = self::get($request_id);
        if (! $request || ! in_array($request['status'], [self::STATUS_PENDING_TAILOR_REVIEW, self::STATUS_NEEDS_MORE_FABRIC], true)) {
            return;
        }

        self::update_status($request_id, 0, self::STATUS_EXPIRED, [
            'cancelled_at' => current_time('mysql', true),
            'payment_unlocked' => 0,
        ], 'request_expired');

        Notification_Center::create(
            (int) $request['customer_id'],
            'couture_request_expired',
            'Demande couture expiree',
            'Le couturier n a pas repondu a temps. La demande a ete annulee automatiquement et vous pouvez en choisir un autre.',
            'couture_request',
            $request_id
        );
    }

    public static function link_order(int $request_id, int $order_id): bool {
        global $wpdb;

        if ($request_id <= 0 || $order_id <= 0) {
            return false;
        }

        $updated = $wpdb->update(
            Installer::table_name('yombal_couture_requests'),
            ['wc_order_id' => $order_id],
            ['id' => $request_id]
        );

        if ($updated === false) {
            Logger::error('Failed to link order to couture request.', [
                'request_id' => $request_id,
                'order_id' => $order_id,
            ]);
            return false;
        }

        self::add_event($request_id, 0, 'order_linked', [
            'order_id' => $order_id,
        ]);

        return true;
    }

    public static function mark_payment_completed(int $request_id, int $order_id): bool {
        $updated = self::update_status($request_id, 0, self::STATUS_PAYMENT_COMPLETED, [
            'wc_order_id' => $order_id,
            'payment_unlocked' => 1,
        ], 'payment_completed');

        if (! $updated) {
            return false;
        }

        $request = self::get($request_id);
        if ($request) {
            Notification_Center::create(
                (int) $request['customer_id'],
                'couture_request_payment_completed',
                'Paiement confirme',
                'Votre paiement pour le tissu et la confection a bien ete confirme.',
                'couture_request',
                $request_id
            );

            Notification_Center::create(
                (int) $request['tailor_user_id'],
                'couture_request_customer_paid',
                'Commande confirmee',
                'Le client a finalise son paiement. Vous pouvez maintenant preparer la suite de la confection.',
                'couture_request',
                $request_id
            );
        }

        return true;
    }

    public static function get_status_counts(): array {
        global $wpdb;

        $table = Installer::table_name('yombal_couture_requests');
        $rows = $wpdb->get_results("SELECT status, COUNT(*) AS total FROM {$table} GROUP BY status", ARRAY_A);

        $counts = ['total' => 0];
        foreach ((array) $rows as $row) {
            $status = (string) ($row['status'] ?? '');
            $total = (int) ($row['total'] ?? 0);
            $counts[$status] = $total;
            $counts['total'] += $total;
        }

        return $counts;
    }

    public static function sweep_expired_requests(): int {
        global $wpdb;

        $table = Installer::table_name('yombal_couture_requests');
        $rows = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT id FROM {$table} WHERE status IN (%s, %s) AND expires_at IS NOT NULL AND expires_at <= %s",
                self::STATUS_PENDING_TAILOR_REVIEW,
                self::STATUS_NEEDS_MORE_FABRIC,
                current_time('mysql', true)
            )
        );

        $swept = 0;
        foreach ((array) $rows as $request_id) {
            self::expire_request((int) $request_id);
            $swept++;
        }

        return $swept;
    }

    public static function add_event(int $request_id, int $actor_user_id, string $event_type, array $payload = []): void {
        global $wpdb;

        $wpdb->insert(
            Installer::table_name('yombal_couture_request_events'),
            [
                'request_id' => $request_id,
                'actor_user_id' => $actor_user_id > 0 ? $actor_user_id : null,
                'event_type' => sanitize_key($event_type),
                'payload' => $payload ? wp_json_encode($payload) : null,
            ]
        );
    }

    private static function update_status(int $request_id, int $actor_user_id, string $status, array $updates, string $event_type): bool {
        global $wpdb;

        $data = array_merge(['status' => $status], $updates);
        $updated = $wpdb->update(
            Installer::table_name('yombal_couture_requests'),
            $data,
            ['id' => $request_id]
        );

        if ($updated === false) {
            Logger::error('Failed to update couture request status.', [
                'request_id' => $request_id,
                'status' => $status,
            ]);
            return false;
        }

        self::add_event($request_id, $actor_user_id, $event_type, $updates);

        return true;
    }

    private static function schedule_expiry(int $request_id, string $expires_at): void {
        $timestamp = strtotime($expires_at . ' UTC');
        if (! $timestamp) {
            return;
        }

        wp_clear_scheduled_hook('yombal_couture_request_expiry', [$request_id]);
        wp_schedule_single_event($timestamp, 'yombal_couture_request_expiry', [$request_id]);
    }

    public static function ensure_expiry_sweep_scheduled(): void {
        if (! wp_next_scheduled('yombal_couture_request_expiry_sweep')) {
            wp_schedule_event(time() + 300, 'yombal_fifteen_minutes', 'yombal_couture_request_expiry_sweep');
        }
    }

    public static function register_cron_schedule(array $schedules): array {
        if (! isset($schedules['yombal_fifteen_minutes'])) {
            $schedules['yombal_fifteen_minutes'] = [
                'interval' => 15 * MINUTE_IN_SECONDS,
                'display' => 'Every 15 Minutes (Yombal)',
            ];
        }

        return $schedules;
    }
}
