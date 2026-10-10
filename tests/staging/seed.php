<?php
/**
 * Load with: wp eval-file tests/staging/seed.php --path="$WP_ROOT"
 * Local/ephemeral test WordPress ONLY. Uses synthetic users and no real INO records.
 */
if (!defined('ABSPATH') || !defined('WP_CLI')) { exit(1); }

$required = array(
    'ino_gov_meetings','ino_gov_agenda','ino_gov_minute_versions',
    'ino_gov_resolutions','ino_gov_actions','ino_gov_notifications','ino_gov_ops_events'
);
global $wpdb;
foreach ($required as $suffix) {
    $table=$wpdb->prefix.$suffix;
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$table)) !== $table) {
        WP_CLI::error("Missing migrated staging table: {$suffix}");
    }
    $engine=$wpdb->get_var($wpdb->prepare(
        'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=%s AND TABLE_NAME=%s',
        DB_NAME,$table
    ));
    if (strtoupper((string)$engine)!=='INNODB') {
        WP_CLI::error("Staging audit transactions require InnoDB: {$suffix} ({$engine})");
    }
}
foreach (array(
    'records'=>array('ino_gov_records_officer','ino_gov_records_officer'),
    'reviewer'=>array('ino_gov_reviewer','ino_gov_reviewer'),
    'publisher'=>array('ino_gov_publisher','ino_gov_publisher'),
    'viewer'=>array('subscriber','subscriber'),
    'outsider'=>array('subscriber','subscriber')
) as $who=>$description) {
    $email='ino-stage-'.$who.'@example.invalid';
    $uid=email_exists($email);
    if (!$uid) { $uid=wp_create_user('ino_stage_'.$who,wp_generate_password(22),$email); }
    if (is_wp_error($uid)) { WP_CLI::error($uid->get_error_message()); }
    $user=new WP_User($uid);
    $user->set_role($description[0]);
    if ($who==='viewer') { $user->add_cap('ino_governance_view'); }
    if ($who==='outsider') {
        foreach (array('ino_governance_view','ino_governance_edit','ino_governance_review','ino_governance_publish') as $cap) {
            $user->remove_cap($cap);
        }
    }
}
$checks=array('ino_governance_view','ino_governance_edit','ino_governance_review','ino_governance_publish');
foreach ($checks as $cap) {
    if (user_can(get_user_by('email','ino-stage-outsider@example.invalid')->ID,$cap)) {
        WP_CLI::error("Outsider has unexpected {$cap}.");
    }
}
WP_CLI::success('Fresh WordPress staging: 7 transactional tables, isolated roles and synthetic users are ready.');
