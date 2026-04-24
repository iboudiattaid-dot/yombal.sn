<?php

declare(strict_types=1);

namespace Yombal\Core\Notifications;

use Yombal\Core\Database\Installer;
use Yombal\Core\Frontend\Public_Shell;

if (! defined('ABSPATH')) {
    exit;
}

final class Notification_Center {
    public static function boot(): void {
        add_shortcode('yombal_notifications', [self::class, 'render_page']);
    }

    public static function create(
        int $user_id,
        string $type,
        string $title,
        string $message,
        string $related_object_type = '',
        int $related_object_id = 0
    ): void {
        global $wpdb;

        if ($user_id <= 0 || $title === '') {
            return;
        }

        $wpdb->insert(
            Installer::table_name('yombal_notifications'),
            [
                'user_id' => $user_id,
                'channel' => 'in_app',
                'type' => sanitize_key($type),
                'title' => sanitize_text_field($title),
                'message' => wp_kses_post($message),
                'status' => 'pending',
                'related_object_type' => $related_object_type !== '' ? sanitize_key($related_object_type) : null,
                'related_object_id' => $related_object_id > 0 ? $related_object_id : null,
            ]
        );
    }

    public static function count_user_notifications(int $user_id, string $status = ''): int {
        global $wpdb;

        if ($user_id <= 0) {
            return 0;
        }

        $table = Installer::table_name('yombal_notifications');

        if ($status !== '') {
            return (int) $wpdb->get_var(
                $wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE user_id = %d AND status = %s", $user_id, $status)
            );
        }

        return (int) $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE user_id = %d", $user_id)
        );
    }

    public static function get_user_notifications(int $user_id, int $limit = 20): array {
        global $wpdb;

        if ($user_id <= 0) {
            return [];
        }

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT * FROM ' . Installer::table_name('yombal_notifications') . ' WHERE user_id = %d ORDER BY created_at DESC LIMIT %d',
                $user_id,
                $limit
            ),
            ARRAY_A
        );

        return is_array($rows) ? $rows : [];
    }

    public static function render_page(): string {
        if (! is_user_logged_in()) {
            return '<div class="yombal-ui"><div class="yombal-empty-state">Vous devez etre connecte pour consulter vos notifications.</div></div>';
        }

        $notifications = self::get_user_notifications(get_current_user_id(), 30);

        ob_start();
        ?>
        <div class="yombal-ui yombal-notifications yombal-shell">
            <?php echo Public_Shell::render_identity_strip(); ?>
            <section class="yombal-hero">
                <span class="yombal-eyebrow">Restez informe</span>
                <h1>Mes notifications</h1>
                <p>Retrouvez ici les informations utiles sur votre compte, vos demandes et les prochaines etapes a suivre.</p>
            </section>
            <?php if (! $notifications) : ?>
                <div class="yombal-empty-state">Aucune notification pour le moment.</div>
            <?php else : ?>
                <?php foreach ($notifications as $notification) : ?>
                    <article class="yombal-card yombal-notification-card">
                        <div class="yombal-card__header">
                            <div class="yombal-stack">
                                <h2 class="yombal-section-title"><?php echo esc_html((string) $notification['title']); ?></h2>
                                <div class="yombal-inline-meta">
                                    <span><?php echo esc_html((string) $notification['created_at']); ?></span>
                                    <span><?php echo esc_html(self::type_label((string) $notification['type'])); ?></span>
                                </div>
                            </div>
                            <span class="yombal-badge <?php echo esc_attr(self::status_badge_class((string) $notification['status'])); ?>">
                                <?php echo esc_html(self::status_label((string) $notification['status'])); ?>
                            </span>
                        </div>
                        <?php if (! empty($notification['message'])) : ?>
                            <div class="yombal-prose"><?php echo wp_kses_post(wpautop((string) $notification['message'])); ?></div>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php

        return (string) ob_get_clean();
    }

    private static function status_badge_class(string $status): string {
        return match ($status) {
            'sent', 'delivered', 'read' => 'yombal-badge--success',
            'failed', 'cancelled' => 'yombal-badge--danger',
            default => 'yombal-badge--muted',
        };
    }

    private static function status_label(string $status): string {
        return match ($status) {
            'sent', 'delivered' => 'Envoye',
            'read' => 'Lu',
            'failed' => 'Non distribue',
            'cancelled' => 'Annule',
            default => 'A lire',
        };
    }

    private static function type_label(string $type): string {
        return match ($type) {
            'partner_application_received', 'partner_application_approved', 'partner_application_rejected', 'partner_application_pending_review' => 'Compte partenaire',
            'couture_request_created', 'couture_request_received', 'couture_request_approved', 'couture_request_needs_more_fabric', 'couture_request_expired', 'couture_request_payment_completed', 'couture_request_customer_paid' => 'Couture',
            'message_received' => 'Messages',
            'support_ticket_created', 'support_ticket_reply' => 'Aide et litiges',
            default => 'Information',
        };
    }
}
