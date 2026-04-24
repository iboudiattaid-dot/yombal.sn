<?php
/**
 * Plugin Name: Yombal Core
 * Plugin URI: https://yombal.sn/
 * Description: Marketplace foundation and partner experience for Yombal on top of WordPress and WooCommerce.
 * Version: 0.4.15
 * Author: Yombal
 * Requires at least: 6.5
 * Requires PHP: 8.0
 * Text Domain: yombal-core
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

define('YOMBAL_CORE_VERSION', '0.4.15');
define('YOMBAL_CORE_FILE', __FILE__);
define('YOMBAL_CORE_DIR', plugin_dir_path(__FILE__));
define('YOMBAL_CORE_URL', plugin_dir_url(__FILE__));

require_once YOMBAL_CORE_DIR . 'inc/bootstrap.php';

register_activation_hook(__FILE__, ['Yombal\\Core\\Bootstrap', 'activate']);
register_deactivation_hook(__FILE__, ['Yombal\\Core\\Bootstrap', 'deactivate']);

Yombal\Core\Bootstrap::boot();
