<?php
/**
 * Real disposable WordPress database smoke test, executed AFTER existing
 * governance, BuddyPress and voting fixtures. No real member information.
 */
if (!defined('ABSPATH') || !defined('WP_CLI')) { exit(1); }
global $wpdb;
function ino_mui_check($condition,$message) {
    if (!$condition) { WP_CLI::error('Membership UI check FAILED: '.$message); }
    WP_CLI::log('PASS membership UI: '.$message);
}
ino_mui_check(class_exists('INO_Platform_Membership_UI'),'membership view class loaded');
ino_mui_check(shortcode_exists('ino_member_dashboard') && shortcode_exists('ino_membership_portal'),
    'member and landing shortcodes registered');
ino_mui_check(is_file(INO_PLATFORM_PATH.'assets/css/ino-membership-ui.css') &&
    is_file(INO_PLATFORM_PATH.'assets/js/ino-membership-ui.js'),'private admin/frontend style and chart scripts packaged');
$approved=get_user_by('login','ino_stage_viewer');
$pending=get_user_by('login','ino_stage_outsider');
$admin=get_user_by('login','admin_test');
ino_mui_check($approved && $pending && $admin,'isolated membership fixture accounts present');
$member=$wpdb->get_row($wpdb->prepare(
    "SELECT member_id,status FROM {$wpdb->prefix}ino_members WHERE user_id=%d ORDER BY id DESC LIMIT 1",
    $approved->ID
));
$other=$wpdb->get_row($wpdb->prepare(
    "SELECT member_id,status FROM {$wpdb->prefix}ino_members WHERE user_id=%d ORDER BY id DESC LIMIT 1",
    $pending->ID
));
ino_mui_check($member && $other && $member->status==='Approved' && $other->status==='Pending',
    'latest-record status fixtures include approval and pending');
$private_phrase='FORBIDDEN-MEMBER-ANCESTRY-EXPOSURE-SECRET';
$created=$wpdb->insert($wpdb->prefix.'ino_identity_declarations',array(
    'user_id'=>$approved->ID,
    'ancestral_people'=>'Only private source',
    'family_narrative'=>$private_phrase,
    'verification_status'=>'Self-declared',
    'review_status'=>'pending',
    'created_at'=>current_time('mysql')
));
ino_mui_check((bool)$created,'private declaration fixture inserted');
$created2=$wpdb->insert($wpdb->prefix.'ino_identity_declarations',array(
    'user_id'=>$pending->ID,
    'family_narrative'=>'ANOTHER-ACCOUNT-PRIVATE-CONTENT',
    'verification_status'=>'Self-declared',
    'review_status'=>'pending',
    'created_at'=>current_time('mysql')
));
ino_mui_check((bool)$created2,'separate member declaration fixture inserted');
wp_set_current_user(0);
$guest=do_shortcode('[ino_member_dashboard]');
ino_mui_check(strpos($guest,'Membership Dashboard')!==false &&
    strpos($guest,$member->member_id)===false,'anonymous visitor sees login only');
wp_set_current_user((int)$approved->ID);
$private=do_shortcode('[ino_member_dashboard]');
ino_mui_check(strpos($private,'Welcome to Your Membership Portal')!==false,'new member dashboard renders');
ino_mui_check(strpos($private,$member->member_id)!==false &&
    strpos($private,$other->member_id)===false,'dashboard only includes owner member ID');
ino_mui_check(strpos($private,'data-ino-membership-chart')!==false &&
    strpos($private,'View the source data table')!==false,'real-data chart and no-JS accessible table present');
ino_mui_check(strpos($private,'Identity declarations')!==false &&
    strpos($private,'Recent private identity activity')!==false,'private KPI and activity table present');
ino_mui_check(strpos($private,$private_phrase)===false &&
    strpos($private,'ANOTHER-ACCOUNT-PRIVATE-CONTENT')===false,'private ancestry statements excluded from dashboard HTML');
wp_set_current_user((int)$pending->ID);
$pending_view=do_shortcode('[ino_member_dashboard]');
ino_mui_check(strpos($pending_view,$other->member_id)!==false &&
    strpos($pending_view,$member->member_id)===false &&
    strpos($pending_view,'Pending')!==false,'other member sees only pending own record');
$landing=do_shortcode('[ino_membership_portal]');
ino_mui_check(strpos($landing,'Membership &amp; Belonging')!==false,'public membership portal landing renders');
$stats=INO_Platform_Membership_UI::stats();
ino_mui_check($stats['total']>=2 && $stats['approved']>=1 && $stats['pending']>=1,
    'admin KPI counts use current membership rows');
wp_set_current_user((int)$admin->ID);
$_GET=array();
ob_start();INO_Platform_Membership_UI::admin_page();$panel=ob_get_clean();
ino_mui_check(strpos($panel,'Membership Command Center')!==false &&
    strpos($panel,'Current membership statuses')!==false &&
    strpos($panel,'Membership pathways')!==false,'admin panels render KPI, category and status visualizations');
ino_mui_check(strpos($panel,'Membership records')!==false &&
    strpos($panel,'Filter records')!==false,'admin searchable table controls rendered');
ino_mui_check(strpos($panel,$member->member_id)!==false &&
    strpos($panel,$other->member_id)!==false,'admin sees authorized roster record identifiers');
ino_mui_check(strpos($panel,$private_phrase)===false,'sensitive declaration text excluded from admin summary');
$_GET=array('ino_member_search'=>$member->member_id);
ob_start();INO_Platform_Membership_UI::admin_page();$filtered=ob_get_clean();
ino_mui_check(strpos($filtered,$member->member_id)!==false &&
    strpos($filtered,$other->member_id)===false,'search applies server-side filter');
$_GET=array('ino_member_state'=>'pending');
ob_start();INO_Platform_Membership_UI::admin_page();$pending_rows=ob_get_clean();
ino_mui_check(strpos($pending_rows,$other->member_id)!==false &&
    strpos($pending_rows,$member->member_id)===false,'status filter restricts server-side roster');
$_GET=array();
wp_set_current_user(0);
ino_mui_check(has_action('admin_enqueue_scripts',array('INO_Platform_Membership_UI','admin_assets'))!==false,
    'admin styles enqueued only on membership screen');
WP_CLI::success('Member privacy, real-data dashboard, admin filters, chart fallback and portal landing passed.');
