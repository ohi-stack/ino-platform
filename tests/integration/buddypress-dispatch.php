<?php
/* BuddyPress bridge admin-post dispatcher for disposable WordPress CI. */
if (!defined('ABSPATH') || !defined('WP_CLI')) { exit(1); }
$who=getenv('INO_BP_ACTOR') ?: 'admin_test';
$user=get_user_by('login',$who);
if (!$user) { WP_CLI::error('Missing synthetic BuddyPress operator.'); }
wp_set_current_user($user->ID);
$operation=getenv('INO_BP_ACTION') ?: 'check';
$_POST=array(
    'action'=>$operation==='save'?'ino_bp_bridge_save':'ino_bp_bridge_check',
    'profile_tab'=>getenv('INO_BP_PROFILE_TAB')==='1'?'1':'0',
    'friend_requests'=>'1',
    'prefer_bp_media'=>'1'
);
$nonce_mode=getenv('INO_BP_NONCE') ?: 'valid';
if ($nonce_mode==='valid') {
    $_POST['_ino_bp_nonce']=wp_create_nonce('ino_bp_bridge_'.$operation);
} elseif ($nonce_mode==='invalid') {
    $_POST['_ino_bp_nonce']='invalid-nonce';
}
$_REQUEST=$_POST;
$_SERVER['REQUEST_METHOD']='POST';
if ($operation==='save') { INO_Platform_BuddyPress::save(); }
else { INO_Platform_BuddyPress::run_checks(); }
WP_CLI::error('Bridge handler returned instead of redirecting/denying.');
