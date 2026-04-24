<?php

declare(strict_types=1);

namespace Yombal\Core;

if (! defined('ABSPATH')) {
    exit;
}

require_once YOMBAL_CORE_DIR . 'inc/support/class-logger.php';
require_once YOMBAL_CORE_DIR . 'inc/database/class-installer.php';
require_once YOMBAL_CORE_DIR . 'inc/journeys/class-fixtures.php';
require_once YOMBAL_CORE_DIR . 'inc/frontend/class-assets.php';
require_once YOMBAL_CORE_DIR . 'inc/frontend/class-public-shell.php';
require_once YOMBAL_CORE_DIR . 'inc/frontend/class-legacy-cleanup.php';
require_once YOMBAL_CORE_DIR . 'inc/ui/class-dashboard-shell.php';
require_once YOMBAL_CORE_DIR . 'inc/security/class-hardening.php';
require_once YOMBAL_CORE_DIR . 'inc/messages/class-message-center.php';
require_once YOMBAL_CORE_DIR . 'inc/notifications/class-notification-center.php';
require_once YOMBAL_CORE_DIR . 'inc/partners/class-roles.php';
require_once YOMBAL_CORE_DIR . 'inc/partners/class-registration.php';
require_once YOMBAL_CORE_DIR . 'inc/partners/class-partner-stats.php';
require_once YOMBAL_CORE_DIR . 'inc/partners/class-profile-service.php';
require_once YOMBAL_CORE_DIR . 'inc/partners/class-public-pages.php';
require_once YOMBAL_CORE_DIR . 'inc/partners/class-workspace.php';
require_once YOMBAL_CORE_DIR . 'inc/routing/class-front-router.php';
require_once YOMBAL_CORE_DIR . 'inc/catalog/class-product-editor.php';
require_once YOMBAL_CORE_DIR . 'inc/catalog/class-storefront.php';
require_once YOMBAL_CORE_DIR . 'inc/customers/class-events.php';
require_once YOMBAL_CORE_DIR . 'inc/customers/class-measurements.php';
require_once YOMBAL_CORE_DIR . 'inc/customers/class-workspace.php';
require_once YOMBAL_CORE_DIR . 'inc/workflows/class-couture-requests.php';
require_once YOMBAL_CORE_DIR . 'inc/workflows/class-couture-portal.php';
require_once YOMBAL_CORE_DIR . 'inc/orders/class-checkout-guard.php';
require_once YOMBAL_CORE_DIR . 'inc/orders/class-grouped-payment.php';
require_once YOMBAL_CORE_DIR . 'inc/admin/class-page-provisioner.php';
require_once YOMBAL_CORE_DIR . 'inc/admin/class-rollout-controls.php';
require_once YOMBAL_CORE_DIR . 'inc/support/class-ticket-center.php';

if (is_admin()) {
    require_once YOMBAL_CORE_DIR . 'inc/migrations/class-wcfm-adapter.php';
    require_once YOMBAL_CORE_DIR . 'inc/migrations/class-partner-importer.php';
    require_once YOMBAL_CORE_DIR . 'inc/admin/class-journey-lab.php';
    require_once YOMBAL_CORE_DIR . 'inc/admin/class-readiness-center.php';
    require_once YOMBAL_CORE_DIR . 'inc/admin/class-menu.php';
}

use Yombal\Core\Admin\Journey_Lab;
use Yombal\Core\Admin\Menu;
use Yombal\Core\Admin\Readiness_Center;
use Yombal\Core\Admin\Rollout_Controls;
use Yombal\Core\Catalog\Product_Editor;
use Yombal\Core\Catalog\Storefront;
use Yombal\Core\Customers\Events;
use Yombal\Core\Customers\Measurements;
use Yombal\Core\Customers\Workspace as Customer_Workspace;
use Yombal\Core\Database\Installer;
use Yombal\Core\Frontend\Assets;
use Yombal\Core\Frontend\Legacy_Cleanup;
use Yombal\Core\Frontend\Public_Shell;
use Yombal\Core\Messages\Message_Center;
use Yombal\Core\Notifications\Notification_Center;
use Yombal\Core\Orders\Checkout_Guard;
use Yombal\Core\Orders\Grouped_Payment;
use Yombal\Core\Partners\Public_Pages;
use Yombal\Core\Partners\Profile_Service;
use Yombal\Core\Partners\Registration;
use Yombal\Core\Partners\Roles;
use Yombal\Core\Partners\Workspace;
use Yombal\Core\Routing\Front_Router;
use Yombal\Core\Security\Hardening;
use Yombal\Core\Support\Ticket_Center;
use Yombal\Core\Workflows\Couture_Portal;
use Yombal\Core\Workflows\Couture_Requests;

final class Bootstrap {
    public static function boot(): void {
        Installer::boot();
        Hardening::boot();
        Roles::boot();
        Registration::boot();
        Profile_Service::boot();
        Public_Pages::boot();
        Workspace::boot();
        Front_Router::boot();
        Rollout_Controls::boot();
        Assets::boot();
        Public_Shell::boot();
        Legacy_Cleanup::boot();
        Product_Editor::boot();
        Storefront::boot();
        Events::boot();
        Measurements::boot();
        Customer_Workspace::boot();
        Message_Center::boot();
        Notification_Center::boot();
        Ticket_Center::boot();
        Couture_Requests::boot();
        Couture_Portal::boot();
        Checkout_Guard::boot();
        Grouped_Payment::boot();

        if (is_admin()) {
            Journey_Lab::boot();
            Readiness_Center::boot();
            Menu::boot();
        }
    }

    public static function activate(): void {
        require_once YOMBAL_CORE_DIR . 'inc/migrations/class-wcfm-adapter.php';
        require_once YOMBAL_CORE_DIR . 'inc/migrations/class-partner-importer.php';

        Installer::activate();
        Roles::activate();
        \Yombal\Core\Admin\Page_Provisioner::ensure_core_pages();
        \Yombal\Core\Migrations\Partner_Importer::import_legacy_profiles();
        update_option('yombal_core_rollout', \Yombal\Core\Admin\Rollout_Controls::defaults(), false);
        flush_rewrite_rules();
    }

    public static function deactivate(): void {
        flush_rewrite_rules();
    }
}
