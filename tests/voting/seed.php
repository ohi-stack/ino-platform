<?php
if (!defined('ABSPATH') || !defined('WP_CLI')) { exit(1); }
global $wpdb;
$keys=array('polls','options','ballots','choices','events');
foreach ($keys as $key) {
    $table=INO_Platform_Voting::tables($key);
    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s",$table))!==$table) {
        WP_CLI::error('Voting schema missing '.$key);
    }
    $engine=$wpdb->get_var($wpdb->prepare(
        "SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=%s AND TABLE_NAME=%s",
        DB_NAME,$table
    ));
    if (strtoupper((string)$engine)!=='INNODB') { WP_CLI::error('Voting table not transactional: '.$key); }
}
$viewer=get_user_by('login','ino_stage_viewer');
$outsider=get_user_by('login','ino_stage_outsider');
if (!$viewer || !$outsider) { WP_CLI::error('Run governance synthetic user provisioning first.'); }
if (!$wpdb->insert($wpdb->prefix.'ino_members',array(
    'user_id'=>$viewer->ID,'member_id'=>'INO-VOTING-CI-APPROVED',
    'status'=>'Approved','public_consent'=>0
))) { WP_CLI::error('Failed to create approved private member voting fixture.'); }
if (!$wpdb->insert($wpdb->prefix.'ino_members',array(
    'user_id'=>$outsider->ID,'member_id'=>'INO-VOTING-CI-PENDING',
    'status'=>'Pending','public_consent'=>0
))) { WP_CLI::error('Failed to create unapproved member voting fixture.'); }
WP_CLI::success('Five InnoDB tables; private approved voter and ineligible account created.');
