<?php
/* WP-CLI isolated process dispatcher for actual INO admin_post voting handlers. */
if (!defined('ABSPATH') || !defined('WP_CLI')) { exit(1); }
$actor=getenv('INO_VOTE_USER') ?: 'admin_test';
$user=get_user_by('login',$actor);
if (!$user) { WP_CLI::error('Voting fixture actor missing: '.$actor); }
wp_set_current_user($user->ID);
$action=getenv('INO_VOTE_ACTION') ?: 'create';
$kind=getenv('INO_VOTE_KIND') ?: 'manage';
$json=base64_decode((string)getenv('INO_VOTE_FIELDS'),true);
$data=$json?json_decode($json,true):array();
if (!is_array($data)) { WP_CLI::error('Invalid voting fixture JSON.'); }
$_POST=$data;
if ($kind==='manage') {
    $_POST['action']='ino_vote_manage';
    $_POST['operation']=$action;
    $nonce_action='ino_vote_'.$action;
} else {
    $_POST['action']='ino_vote_cast';
    $nonce_action='ino_vote_cast';
}
$mode=getenv('INO_VOTE_NONCE') ?: 'valid';
if ($mode==='valid') { $_POST['ino_vote_nonce']=wp_create_nonce($nonce_action); }
elseif ($mode==='invalid') { $_POST['ino_vote_nonce']='invalid-token'; }
$_REQUEST=$_POST;
$_SERVER['REQUEST_METHOD']='POST';
if ($kind==='manage') { INO_Platform_Voting::manage(); }
else { INO_Platform_Voting::cast(); }
WP_CLI::error('Voting handler unexpectedly returned.');
