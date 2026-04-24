<?php

declare(strict_types=1);

namespace Yombal\Core\UI;

use Yombal\Core\Frontend\Public_Shell;

if (! defined('ABSPATH')) {
    exit;
}

final class Dashboard_Shell {
    public static function render_layout(array $args): string {
        $sidebar_title = (string) ($args['sidebar_title'] ?? 'Yombal');
        $sidebar_meta = (string) ($args['sidebar_meta'] ?? '');
        $sidebar_items = (array) ($args['sidebar_items'] ?? []);
        $content = (string) ($args['content'] ?? '');
        $logo_url = plugins_url('assets/images/yombal-logo-primary.jpg', YOMBAL_CORE_FILE);

        ob_start();
        ?>
        <div class="yombal-ui yombal-dashboard-shell yhr-page-shell yhr-page-shell--dashboard">
            <?php echo Public_Shell::render_identity_strip(); ?>
            <div class="yombal-dashboard">
                <aside class="yombal-dashboard__sidebar">
                    <div class="yombal-dashboard__sidebar-card">
                        <div class="yombal-dashboard__sidebar-brand">
                            <img class="yombal-dashboard__sidebar-logo" src="<?php echo esc_url($logo_url); ?>" alt="Yombal" loading="lazy" decoding="async">
                            <span class="yombal-dashboard__sidebar-eyebrow">Tableau de bord</span>
                            <h2><?php echo esc_html($sidebar_title); ?></h2>
                            <?php if ($sidebar_meta !== '') : ?>
                                <p><?php echo esc_html($sidebar_meta); ?></p>
                            <?php endif; ?>
                        </div>
                        <nav class="yombal-sidebar-nav">
                            <?php foreach ($sidebar_items as $item) : ?>
                                <?php
                                $label = (string) ($item['label'] ?? '');
                                $url = (string) ($item['url'] ?? '#');
                                $is_active = ! empty($item['active']);
                                $modifier = ! empty($item['modifier']) ? ' yombal-sidebar-nav__link--' . sanitize_html_class((string) $item['modifier']) : '';
                                if ($label === '') {
                                    continue;
                                }
                                ?>
                                <a href="<?php echo esc_url($url); ?>" class="yombal-sidebar-nav__link<?php echo $is_active ? ' is-active' : ''; ?><?php echo esc_attr($modifier); ?>">
                                    <?php echo esc_html($label); ?>
                                </a>
                            <?php endforeach; ?>
                        </nav>
                    </div>
                </aside>
                <main class="yombal-dashboard__main">
                    <?php echo $content; ?>
                </main>
            </div>
        </div>
        <?php

        return (string) ob_get_clean();
    }

    public static function render_metrics(array $metrics): string {
        ob_start();
        ?>
        <section class="yombal-grid yombal-grid--stats">
            <?php foreach ($metrics as $metric) : ?>
                <?php
                $value = (string) ($metric['value'] ?? '0');
                $label = (string) ($metric['label'] ?? '');
                $meta = (string) ($metric['meta'] ?? '');
                ?>
                <article class="yombal-card yombal-stat">
                    <div class="yombal-stat__value"><?php echo esc_html($value); ?></div>
                    <div class="yombal-stat__label"><?php echo esc_html($label); ?></div>
                    <?php if ($meta !== '') : ?>
                        <div class="yombal-card__meta"><?php echo esc_html($meta); ?></div>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </section>
        <?php

        return (string) ob_get_clean();
    }

    public static function render_action_cards(array $actions): string {
        ob_start();
        ?>
        <div class="yombal-action-grid">
            <?php foreach ($actions as $action) : ?>
                <?php
                $label = (string) ($action['label'] ?? '');
                $description = (string) ($action['description'] ?? '');
                $url = (string) ($action['url'] ?? '#');
                $tone = (string) ($action['tone'] ?? 'secondary');
                if ($label === '') {
                    continue;
                }
                ?>
                <a href="<?php echo esc_url($url); ?>" class="yombal-action-card yombal-action-card--<?php echo esc_attr(sanitize_html_class($tone)); ?>">
                    <strong><?php echo esc_html($label); ?></strong>
                    <?php if ($description !== '') : ?>
                        <span><?php echo esc_html($description); ?></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
        <?php

        return (string) ob_get_clean();
    }

    public static function render_section(string $title, string $meta, string $content, string $modifier = ''): string {
        $modifier_class = $modifier !== '' ? ' yombal-card--' . sanitize_html_class($modifier) : '';

        ob_start();
        ?>
        <section class="yombal-card<?php echo esc_attr($modifier_class); ?>">
            <div class="yombal-card__header">
                <div class="yombal-stack">
                    <h2 class="yombal-section-title"><?php echo esc_html($title); ?></h2>
                    <?php if ($meta !== '') : ?>
                        <div class="yombal-card__meta"><?php echo esc_html($meta); ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <?php echo $content; ?>
        </section>
        <?php

        return (string) ob_get_clean();
    }
}
