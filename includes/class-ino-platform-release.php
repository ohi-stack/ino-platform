<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Deployment boundaries for the canonical, single-plugin INO runtime.
 * Never silently migrate or replace the separate INO Suite package.
 */
final class INO_Platform_Release {
    const SCHEMA_VERSION = '2026-10-10.2';

    public static function conflicting_plugins() {
        $candidates=array(
            'ino-platform-suite/ino-platform-suite.php',
            'ino-platform-core/ino-platform-core.php',
            'ino-platform-plugin/ino-platform.php'
        );
        $active=(array)get_option('active_plugins',array());
        $network=is_multisite() ? (array)get_site_option('active_sitewide_plugins',array()) : array();
        $conflicts=array();
        foreach ($candidates as $path) {
            if (in_array($path,$active,true) || isset($network[$path])) { $conflicts[]=$path; }
        }
        return $conflicts;
    }

    public static function approved() {
        return defined('INO_PLATFORM_PRODUCTION_APPROVED')
            && INO_PLATFORM_PRODUCTION_APPROVED === true;
    }

    public static function preflight() {
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            wp_die('INO Platform requires PHP 7.4 or newer.');
        }
        global $wp_version;
        if (version_compare($wp_version, '6.5', '<')) {
            wp_die('INO Platform requires WordPress 6.5 or newer.');
        }
        if (self::conflicting_plugins() || class_exists('INO_Suite_Core_Module',false)) {
            wp_die('INO Platform activation stopped: an incompatible INO Suite or legacy Core plugin is active. Back up and stage a controlled single-plugin migration; do not run both plugin families together.');
        }
        // Explicit site-operator opt-in is required to deploy an unpromoted
        // release candidate in a live production WordPress environment.
        if (function_exists('wp_get_environment_type') &&
            wp_get_environment_type()==='production' &&
            !self::approved()) {
            wp_die('INO Platform production activation is blocked until real hosted staging acceptance is documented and INO_PLATFORM_PRODUCTION_APPROVED is explicitly true in wp-config.php. Install and test in staging first.');
        }
    }

    public static function activate() {
        self::preflight();
        INO_Platform_Activator::activate();
        INO_Platform_Governance::maybe_install();
        INO_Governance_Operations::install();
        INO_Governance_ODIN::install();
        INO_Platform_Voting::install();
        update_option('ino_platform_schema_version',self::SCHEMA_VERSION,false);
    }

    public static function maybe_upgrade() {
        if (!is_admin() || !current_user_can('manage_options')) { return; }
        if (self::conflicting_plugins()) { return; }
        if (get_option('ino_platform_schema_version')===self::SCHEMA_VERSION) { return; }
        // Upgrade only inside an authenticated administrative request, not
        // during page rendering or cacheable public traffic.
        INO_Platform_Activator::activate();
        INO_Platform_Governance::maybe_install();
        INO_Governance_Operations::install();
        INO_Governance_ODIN::install();
        INO_Platform_Voting::install();
        update_option('ino_platform_schema_version',self::SCHEMA_VERSION,false);
    }

    public static function notices() {
        if (!current_user_can('manage_options')) { return; }
        if (self::conflicting_plugins()) {
            echo '<div class="notice notice-error"><p><strong>INO Platform compatibility conflict.</strong> An older INO Suite/Core plugin is active. Do not use duplicate platforms simultaneously. Take a complete backup and reconcile WordPress tables and shortcodes on staging.</p></div>';
        }
        if (function_exists('wp_get_environment_type') &&
            wp_get_environment_type()==='production' && !self::approved()) {
            echo '<div class="notice notice-warning"><p><strong>INO production acceptance is not recorded.</strong> Current code remains a release candidate; do not treat governance decisions or unpublished records as institutionally certified.</p></div>';
        }
    }

    public static function private_page_headers() {
        if (!is_singular('page')) { return; }
        $page=get_queried_object();
        if (!$page || !($page instanceof WP_Post)) { return; }
        $sensitive_slugs=array(
            'ino-member-dashboard','identity-heritage-dashboard',
            'member-profile','identity-declaration','family-tree',
            'ino-governance-operations','ino-my-votes','ino-voting'
        );
        $sensitive_tags=array(
            'ino_member_dashboard','ino_identity_dashboard',
            'ino_member_profile','ino_identity_declaration',
            'ino_family_tree','ino_governance_operations','ino_my_votes','ino_voting'
        );
        $sensitive=in_array($page->post_name,$sensitive_slugs,true);
        foreach ($sensitive_tags as $tag) {
            if (!$sensitive && has_shortcode((string)$page->post_content,$tag)) {
                $sensitive=true;
            }
        }
        if ($sensitive) {
            if (!defined('DONOTCACHEPAGE')) { define('DONOTCACHEPAGE',true); }
            nocache_headers();
        }
    }

    public static function init() {
        add_action('template_redirect',array(__CLASS__,'private_page_headers'),0);
        add_action('admin_init',array(__CLASS__,'maybe_upgrade'),15);
        add_action('admin_notices',array(__CLASS__,'notices'));
    }
}
