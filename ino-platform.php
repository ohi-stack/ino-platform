<?php
/**
 * Plugin Name: INO Platform Plugin
 * Description: Integrated administration, membership, identity, heritage, genealogy, social connections, grants, housing, documents, governance, and public portal tools for the Indigenous Nation of Onegodia.
 * Version: 1.5.2-rc.1
 * Requires at least: 6.5
 * Requires PHP: 7.4
 * Author: OneGodian
 * Text Domain: ino-platform
 */

if (!defined('ABSPATH')) { exit; }

define('INO_PLATFORM_VERSION', '1.5.2-rc.1');
define('INO_PLATFORM_SCHEMA_VERSION', '2026-10-10.1');
define('INO_PLATFORM_PATH', plugin_dir_path(__FILE__));
define('INO_PLATFORM_URL', plugin_dir_url(__FILE__));

require_once INO_PLATFORM_PATH . 'includes/class-ino-platform-activator.php';
require_once INO_PLATFORM_PATH . 'includes/class-ino-platform-release.php';
require_once INO_PLATFORM_PATH . 'includes/class-ino-platform-admin.php';
require_once INO_PLATFORM_PATH . 'includes/class-ino-platform-shortcodes.php';
require_once INO_PLATFORM_PATH . 'includes/class-ino-platform-buddypress.php';
require_once INO_PLATFORM_PATH . 'includes/class-ino-platform-social.php';
require_once INO_PLATFORM_PATH . 'includes/class-ino-platform-governance.php';
require_once INO_PLATFORM_PATH . 'includes/class-ino-governance-operations.php';
require_once INO_PLATFORM_PATH . 'includes/class-ino-governance-operations-ui.php';
require_once INO_PLATFORM_PATH . 'includes/class-ino-governance-odin.php';
require_once INO_PLATFORM_PATH . 'includes/class-ino-governance-odin-ui.php';

register_activation_hook(__FILE__, array('INO_Platform_Release', 'activate'));

add_action('plugins_loaded', function () {
    INO_Platform_Release::init();
    // An existing installation must not register duplicate shortcodes or
    // mutate tables if an incompatible Suite/Core plugin becomes active.
    if (INO_Platform_Release::conflicting_plugins() ||
        class_exists('INO_Suite_Core_Module',false)) { return; }
    INO_Platform_Governance::init();
    INO_Governance_Operations::init();
    INO_Governance_ODIN::init();
    INO_Platform_Admin::init();
    INO_Platform_Shortcodes::init();
    INO_Platform_BuddyPress::init();
    INO_Platform_Social::init();
});
