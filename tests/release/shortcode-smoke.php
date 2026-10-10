<?php
/**
 * Runs after a real, installed WordPress ZIP has been deactivated/reinstalled.
 * No private/real accounts, files, or external HTTP requests.
 */
if (!defined('ABSPATH') || !defined('WP_CLI')) { exit(1); }
function ino_rc_check($condition,$description) {
    if (!$condition) { WP_CLI::error('FAIL '.$description); }
    WP_CLI::log('PASS '.$description);
}
ino_rc_check(defined('INO_PLATFORM_VERSION') && INO_PLATFORM_VERSION==='1.5.1-rc.1','packaged release version consistent');
ino_rc_check(defined('INO_PLATFORM_SCHEMA_VERSION') && 
    get_option('ino_platform_schema_version')===INO_PLATFORM_SCHEMA_VERSION,'schema marker established without resetting governance');
ino_rc_check(shortcode_exists('ino_member_dashboard'),'member dashboard compatibility shortcode registered');
ino_rc_check(has_action('template_redirect',array('INO_Platform_Release','private_page_headers'))!==false,'account-sensitive page response uses no-cache hook');
ino_rc_check(shortcode_exists('ino_governance'),'governance portal shortcode registered');
ino_rc_check(shortcode_exists('ino_governance_operations'),'operations shortcode registered');
ino_rc_check(shortcode_exists('ino_odin_registry') && shortcode_exists('ino_odin_verify'),'ODIN publication and verification shortcode registered');
ino_rc_check((bool)get_page_by_path('ino-member-dashboard'),'private member page provisioned once');
ino_rc_check((bool)get_page_by_path('ino-governance-operations'),'governance operations page preserved');
ino_rc_check(!INO_Platform_Release::approved(),'unapproved production activation remains blocked by default');
$previous=(array)get_option('active_plugins',array());
update_option('active_plugins',array_merge($previous,array('ino-platform-suite/ino-platform-suite.php')));
ino_rc_check(in_array('ino-platform-suite/ino-platform-suite.php',
    INO_Platform_Release::conflicting_plugins(),true),'Suite collision detection works');
update_option('active_plugins',$previous);
$member=get_user_by('email','ino-stage-viewer@example.invalid');
$outsider=get_user_by('email','ino-stage-outsider@example.invalid');
ino_rc_check($member && $outsider,'synthetic account fixtures retained after plugin reinstall');
wp_set_current_user(0);
$guest=do_shortcode('[ino_member_dashboard]');
ino_rc_check(strpos($guest,'Please log in')!==false,'anonymous dashboard renders sign-in boundary');
wp_set_current_user((int)$member->ID);
$member_view=do_shortcode('[ino_member_dashboard]');
ino_rc_check(strpos($member_view,'Private member workspace')!==false,'authenticated member dashboard renders');
ino_rc_check(strpos($member_view,'Identity declarations')!==false &&
    strpos($member_view,'Approved relationships')!==false,'member dashboard uses existing self-scoped count widgets');
wp_set_current_user((int)$outsider->ID);
$private=do_shortcode('[ino_governance_operations]');
ino_rc_check(strpos($private,'Governance operations are restricted')!==false,'ordinary member cannot access governance operations');
wp_set_current_user(0);
$public=do_shortcode('[ino_odin_verify]');
ino_rc_check(strpos($public,'Verify an ODIN Registry ID')!==false,'public ODIN verification screen renders without private data');
foreach (array(
    'assets/css/ino-admin-command.css',
    'assets/css/ino-public-platform.css',
    'assets/css/ino-governance-operations.css',
    'assets/css/ino-governance-odin.css',
    'assets/js/ino-admin-command.js'
) as $relative) {
    ino_rc_check(is_file(INO_PLATFORM_PATH.$relative),'packaged asset '.$relative);
}
WP_CLI::success('Release ZIP smoke checks completed against the installed plugin.');
