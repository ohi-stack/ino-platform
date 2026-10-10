<?php
/**
 * All assertions run inside a freshly installed real WordPress/MySQL site.
 * INO_ASSERT selects a durable database contract; fails with WP-CLI exit != 0.
 */
if (!defined('ABSPATH') || !defined('WP_CLI')) { exit(1); }
global $wpdb;
$case=getenv('INO_ASSERT') ?: '';
function ino_stage_assert($condition,$message) {
    if (!$condition) { WP_CLI::error('FAIL '.$message); }
}
function ino_stage_count($table,$where='1=1') {
    global $wpdb;
    $t=INO_Governance_Operations::table($table);
    return (int)$wpdb->get_var("SELECT COUNT(*) FROM {$t} WHERE {$where}");
}
function ino_stage_latest($table) {
    global $wpdb;
    $t=INO_Governance_Operations::table($table);
    return $wpdb->get_row("SELECT * FROM {$t} ORDER BY id DESC LIMIT 1");
}
function ino_stage_state($table,$status) {
    $item=ino_stage_latest($table);
    ino_stage_assert($item!==null && $item->status===$status,
        "{$table}: expected {$status}, got ".($item?$item->status:'no record'));
    return $item;
}
$meeting=ino_stage_latest('meetings');
switch ($case) {
case 'baseline':
    ino_stage_assert(ino_stage_count('meetings')===0,'fresh site meetings not empty');
    ino_stage_assert(ino_stage_count('events')===0,'fresh site events not empty');
    $admin=get_user_by('login','admin_test');
    ino_stage_assert($admin && !user_can($admin,'ino_governance_review') && !user_can($admin,'ino_governance_publish'),
        'WordPress admin unexpectedly inherits governance adoption rights');
    break;
case 'draft':
    ino_stage_assert(ino_stage_count('meetings')===1,'one draft meeting');
    ino_stage_state('meetings','draft');
    break;
case 'agenda':
    ino_stage_assert(ino_stage_count('agenda')===1,'one initial agenda');
    ino_stage_state('meetings','draft');
    break;
case 'scheduled':
    ino_stage_state('meetings','scheduled');
    ino_stage_assert(ino_stage_count('agenda')===1,'schedule must not mutate agenda');
    break;
case 'locked':
    ino_stage_state('meetings','scheduled');
    ino_stage_assert(ino_stage_count('agenda')===1,'agenda must remain locked');
    break;
case 'held':
    $m=ino_stage_state('meetings','held');
    ino_stage_assert((int)$m->attendance_count===3,'3 recorded participants');
    break;
case 'minutes_v1':
    ino_stage_assert(ino_stage_count('minutes')===1,'one minutes edition');
    $v=ino_stage_state('minutes','submitted');
    ino_stage_assert((int)$v->revision===1,'first revision index');
    break;
case 'minutes_reviewed':
    $v=ino_stage_state('minutes','reviewed');
    ino_stage_assert((int)$v->reviewed_by!== (int)$v->created_by,'minutes review independence');
    break;
case 'resolution_reviewed':
    $v=ino_stage_state('resolutions','reviewed');
    ino_stage_assert((int)$v->reviewed_by!==(int)$v->created_by,'resolution review independence');
    ino_stage_assert((int)$v->meeting_id===(int)$meeting->id,'resolution meeting linkage');
    break;
case 'minutes_v2':
    ino_stage_assert(ino_stage_count('minutes')===2,'second immutable minutes edition');
    $v=ino_stage_state('minutes','submitted');
    ino_stage_assert((int)$v->revision===2,'second revision number');
    $t=INO_Governance_Operations::table('minutes');
    $old=$wpdb->get_row("SELECT * FROM {$t} WHERE revision=1 LIMIT 1");
    ino_stage_assert($old && $old->status==='reviewed','older reviewed minutes retained');
    break;
case 'minutes_v2_reviewed':
    $v=ino_stage_state('minutes','reviewed');
    ino_stage_assert((int)$v->revision===2,'newest minutes reviewed');
    break;
case 'outcome':
    $r=ino_stage_state('resolutions','outcome_recorded');
    ino_stage_assert($r->outcome==='carried' && (int)$r->votes_for===2 && (int)$r->votes_against===1,
        'evidenced vote and outcome data');
    ino_stage_assert((int)$r->reviewed_by!==(int)$r->recorded_by,'decision recorder independent');
    $t=$wpdb->prefix.'ino_governance_items';
    $n=(int)$wpdb->get_var("SELECT COUNT(*) FROM {$t} WHERE record_type='record' AND status='published'");
    ino_stage_assert($n===0,'recorded outcome must not automatically become a published governance act');
    break;
case 'assignment':
    ino_stage_assert(ino_stage_count('tasks')===1,'single assigned action');
    ino_stage_state('tasks','open');
    ino_stage_assert(ino_stage_count('notices')===1,'one private in-app notice');
    $note=ino_stage_latest('notices');
    $viewer=get_user_by('email','ino-stage-viewer@example.invalid');
    ino_stage_assert((int)$note->recipient_id===(int)$viewer->ID,'correct notification recipient');
    ino_stage_assert((int)$note->is_read===0,'notification unread');
    ino_stage_assert(strpos($note->message,'secret')===false,'notice has no confidential text');
    break;
case 'completed':
    $task=ino_stage_state('tasks','complete');
    ino_stage_assert(strlen($task->completion_note)>=12,'completion proof stored');
    break;
case 'notice_read':
    $note=ino_stage_latest('notices');
    ino_stage_assert((int)$note->is_read===1 && !!$note->read_at,'owner marked notice read');
    break;
case 'privacy':
    $outsider=get_user_by('email','ino-stage-outsider@example.invalid');
    wp_set_current_user((int)$outsider->ID);
    $html=do_shortcode('[ino_governance_operations]');
    ino_stage_assert(strpos($html,'Meeting Minutes')===false,'ordinary user cannot see minutes');
    ino_stage_assert(strpos($html,'Governance operations are restricted')!==false,'public fallback');
    wp_set_current_user(0);
    $html=do_shortcode('[ino_governance_operations]');
    ino_stage_assert(strpos($html,'Meeting Minutes')===false,'anonymous user cannot see meeting details');
    break;
case 'audit':
    $events=INO_Governance_Operations::table('events');
    $keys=$wpdb->get_col("SELECT event_key FROM {$events} ORDER BY id ASC");
    $expected=array(
        'meeting_drafted','agenda_added','meeting_scheduled','meeting_held',
        'minutes_submitted','minutes_reviewed','resolution_drafted','resolution_reviewed',
        'minutes_submitted','minutes_reviewed','motion_outcome_recorded',
        'task_assigned','task_completed'
    );
    ino_stage_assert($keys===$expected,'full append-only event chain and no extra failed-action events; actual='.implode(',',$keys));
    $tasks=INO_Governance_Operations::table('tasks');
    $notice=INO_Governance_Operations::table('notices');
    $missing=(int)$wpdb->get_var("SELECT COUNT(*) FROM {$tasks} t LEFT JOIN {$notice} n ON n.entity_type='task' AND n.entity_id=t.id AND n.event_key='assignment' WHERE n.id IS NULL");
    ino_stage_assert($missing===0,'every assigned task paired with durable in-platform notice');
    $rows=$wpdb->get_results("SELECT actor_id,event_key FROM {$events}");
    foreach ($rows as $row) { ino_stage_assert((int)$row->actor_id>0,'audited event has actual account actor'); }
    break;
default:
    WP_CLI::error('Unknown INO_ASSERT contract: '.$case);
}
WP_CLI::success('PASS '. $case);
