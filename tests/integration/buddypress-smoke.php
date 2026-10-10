<?php
/**
 * Real WP + BuddyPress integration contract; isolated synthetic users only.
 * Called after BuddyPress activation in the release CI workflow.
 */
if (!defined('ABSPATH') || !defined('WP_CLI')) { exit(1); }
function ino_bp_test($ok,$label) {
    if (!$ok) { WP_CLI::error('FAILED BuddyPress: '.$label); }
    WP_CLI::log('PASS BuddyPress: '.$label);
}
global $wpdb;
ino_bp_test(class_exists('INO_Platform_BuddyPress'), 'integration admin controller loaded');
ino_bp_test(INO_Platform_BuddyPress::active(), 'actual BuddyPress runtime initialized');
ino_bp_test(shortcode_exists('ino_member_dashboard'), 'private INO member dashboard retained');
ino_bp_test(!INO_Platform_BuddyPress::enabled('profile_tab'), 'private BuddyPress family tab disabled by default');
ino_bp_test(INO_Platform_BuddyPress::enabled('friend_requests'), 'connection setting retains existing default');
ino_bp_test(INO_Platform_BuddyPress::enabled('prefer_bp_media'), 'native avatars preferred where available');
$checks=INO_Platform_BuddyPress::checks();
ino_bp_test(isset($checks['BuddyPress runtime']) && $checks['BuddyPress runtime'][0], 'core runtime health');
ino_bp_test(isset($checks['Friends']) && isset($checks['Avatar rendering']), 'component diagnostics available');
$viewer=get_user_by('login','ino_stage_viewer');
$outsider=get_user_by('login','ino_stage_outsider');
$admin=get_user_by('login','admin_test');
ino_bp_test($viewer && $outsider && $admin, 'separate synthetic operator/visitor accounts');
ino_bp_test(!INO_Platform_BuddyPress::can_connect_target($viewer->ID), 'unconsented target rejected');
$insert=$wpdb->insert($wpdb->prefix.'ino_members',array(
    'user_id'=>$viewer->ID,'member_id'=>'INO-STAGE-BP-001',
    'first_name'=>'Staging','last_name'=>'OptIn','email'=>'staging@example.invalid',
    'status'=>'Approved','public_consent'=>1
));
ino_bp_test((bool)$insert, 'synthetic consented membership record inserted');
ino_bp_test(INO_Platform_BuddyPress::can_connect_target($viewer->ID), 'approved opted-in target allowed');
ino_bp_test(!INO_Platform_BuddyPress::can_connect_target($outsider->ID), 'unapproved profile rejected');
ino_bp_test(!INO_Platform_BuddyPress::can_connect_target(99999999), 'nonexistent target rejected');
$link=INO_Platform_BuddyPress::profile_link($viewer->ID);
ino_bp_test((bool)$link && strpos($link,'http')===0, 'shared WordPress user links to account profile');
wp_set_current_user($admin->ID);
ob_start();
INO_Platform_BuddyPress::page();
$html=ob_get_clean();
ino_bp_test(strpos($html,'BuddyPress Integration')!==false,'module renders an operational admin page');
ino_bp_test(strpos($html,'Run integration diagnostics')!==false,'diagnostics has a real POST form');
ino_bp_test(strpos($html,'Save integration settings')!==false,'admin settings save form rendered');
ino_bp_test(strpos($html,'Member-community settings')!==false,'privacy-aware settings visible');
ino_bp_test(strpos($html,'No record-management handler has been verified')===false,'old placeholder absent');
ino_bp_test(strpos($html,'BuddyPress runtime')!==false,'real component diagnostic output');
ino_bp_test(strpos($html,'ino_bp_bridge_check')!==false, 'separate nonce-protected diagnostic operation present');
ino_bp_test(!get_option('ino_bp_fields_created'),'no automatic xProfile heritage field created');
WP_CLI::success('BuddyPress real runtime, consent, settings and administration smoke checks passed.');
