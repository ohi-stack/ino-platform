<?php
/**
 * Plugin Name: INO Platform Plugin
 * Description: Integrated administration, membership, identity, heritage, genealogy, social connections, grants, housing, documents, governance, and public portal tools for the Indigenous Nation of Onegodia.
 * Version: 1.2.0
 * Author: OneGodian
 * Text Domain: ino-platform
 */

if (!defined('ABSPATH')) { exit; }

define('INO_PLATFORM_VERSION', '1.2.0');
define('INO_PLATFORM_PATH', plugin_dir_path(__FILE__));
define('INO_PLATFORM_URL', plugin_dir_url(__FILE__));

require_once INO_PLATFORM_PATH . 'includes/class-ino-platform-activator.php';
require_once INO_PLATFORM_PATH . 'includes/class-ino-platform-admin.php';
require_once INO_PLATFORM_PATH . 'includes/class-ino-platform-shortcodes.php';
require_once INO_PLATFORM_PATH . 'includes/class-ino-platform-social.php';

register_activation_hook(__FILE__, array('INO_Platform_Activator', 'activate'));

add_action('plugins_loaded', function () {
    if (get_option('ino_platform_version') !== INO_PLATFORM_VERSION) {
        INO_Platform_Activator::activate();
    }
    INO_Platform_Admin::init();
    INO_Platform_Shortcodes::init();
    INO_Platform_Social::init();
});
