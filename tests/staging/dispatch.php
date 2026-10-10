<?php
/**
 * One authenticated/unauthenticated WordPress admin-post call per CLI process.
 * Env: INO_CASE_USER, INO_CASE_OP, INO_CASE_FIELDS (base64 JSON object),
 *      INO_CASE_NONCE (valid, missing, invalid).
 * Success is plugin's wp_safe_redirect + exit(0); expected failures use wp_die.
 */
if (!defined('ABSPATH') || !defined('WP_CLI')) { exit(1); }
$who=getenv('INO_CASE_USER') ?: 'outsider';
$email='ino-stage-'.$who.'@example.invalid';
$user=get_user_by('email',$email);
if (!$user) { WP_CLI::error('Synthetic test user not provisioned.'); }
wp_set_current_user((int)$user->ID);
$op=getenv('INO_CASE_OP') ?: '';
$fields_json=base64_decode((string)getenv('INO_CASE_FIELDS'),true);
$fields=$fields_json?json_decode($fields_json,true):array();
if (!is_array($fields)) { WP_CLI::error('Malformed fixture JSON.'); }
$_POST=array_merge($fields,array('action'=>'ino_gov_ops','operation'=>$op));
$nonce_mode=getenv('INO_CASE_NONCE') ?: 'valid';
if ($nonce_mode==='valid') {
    $_POST['ino_ops_nonce']=wp_create_nonce('ino_gov_ops_'.$op);
} elseif ($nonce_mode==='invalid') {
    $_POST['ino_ops_nonce']='intentionally-invalid';
}
$_REQUEST=$_POST;
$_SERVER['REQUEST_METHOD']='POST';
INO_Governance_Operations::handle();
WP_CLI::error('Expected a WordPress redirect or rejection from operation.');
