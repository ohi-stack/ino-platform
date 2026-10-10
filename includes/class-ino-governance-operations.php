<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Phase 2 operational governance — controlled internal records, not legal adoption.
 * The source Constitution, approval thresholds and signatory roster require verification.
 */
class INO_Governance_Operations {
    const SCHEMA_VERSION = '1';

    public static function init() {
        add_action('admin_post_ino_gov_ops', array(__CLASS__, 'handle'));
        add_shortcode('ino_governance_operations', array('INO_Governance_Operations_UI', 'shortcode'));
        add_action('admin_enqueue_scripts', array('INO_Governance_Operations_UI', 'assets'));
    }

    public static function table($key) {
        global $wpdb;
        $names = array(
            'meetings'=>'ino_gov_meetings','agenda'=>'ino_gov_agenda',
            'minutes'=>'ino_gov_minute_versions','resolutions'=>'ino_gov_resolutions',
            'tasks'=>'ino_gov_actions','notices'=>'ino_gov_notifications','events'=>'ino_gov_ops_events'
        );
        if (!isset($names[$key])) { wp_die('Invalid internal governance table key.', '', array('response'=>500)); }
        return $wpdb->prefix . $names[$key];
    }

    public static function install() {
        if (get_option('ino_gov_ops_schema_version') === self::SCHEMA_VERSION) { return; }
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $cc = $wpdb->get_charset_collate();
        $m=self::table('meetings'); $a=self::table('agenda'); $mi=self::table('minutes');
        $r=self::table('resolutions'); $t=self::table('tasks');
        $n=self::table('notices'); $e=self::table('events');
        dbDelta("CREATE TABLE {$m} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            ref_code varchar(90) NOT NULL,
            title varchar(190) NOT NULL,
            meeting_at datetime NOT NULL,
            venue varchar(190) NOT NULL DEFAULT '',
            office_id bigint(20) unsigned NULL,
            source_ref varchar(190) NOT NULL DEFAULT '',
            status varchar(25) NOT NULL DEFAULT 'draft',
            attendance_count int(10) unsigned NOT NULL DEFAULT 0,
            attendance_evidence text NULL,
            created_by bigint(20) unsigned NOT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY ref_code (ref_code),
            KEY status_meeting_at (status,meeting_at)
        ) {$cc};");
        dbDelta("CREATE TABLE {$a} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            meeting_id bigint(20) unsigned NOT NULL,
            sequence_no int(10) unsigned NOT NULL DEFAULT 1,
            topic varchar(190) NOT NULL,
            briefing text NULL,
            created_by bigint(20) unsigned NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY meeting_sequence (meeting_id,sequence_no)
        ) {$cc};");
        dbDelta("CREATE TABLE {$mi} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            meeting_id bigint(20) unsigned NOT NULL,
            revision int(10) unsigned NOT NULL,
            minutes_text longtext NOT NULL,
            status varchar(25) NOT NULL DEFAULT 'submitted',
            created_by bigint(20) unsigned NOT NULL,
            reviewed_by bigint(20) unsigned NULL,
            source_ref varchar(190) NOT NULL DEFAULT '',
            created_at datetime NOT NULL,
            reviewed_at datetime NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY meeting_revision (meeting_id,revision),
            KEY meeting_status (meeting_id,status)
        ) {$cc};");
        dbDelta("CREATE TABLE {$r} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            ref_code varchar(90) NOT NULL,
            title varchar(190) NOT NULL,
            motion_text longtext NOT NULL,
            authority_ref varchar(190) NOT NULL DEFAULT '',
            meeting_id bigint(20) unsigned NULL,
            status varchar(30) NOT NULL DEFAULT 'draft',
            created_by bigint(20) unsigned NOT NULL,
            reviewed_by bigint(20) unsigned NULL,
            recorded_by bigint(20) unsigned NULL,
            outcome varchar(30) NOT NULL DEFAULT '',
            votes_for int(10) unsigned NOT NULL DEFAULT 0,
            votes_against int(10) unsigned NOT NULL DEFAULT 0,
            abstentions int(10) unsigned NOT NULL DEFAULT 0,
            quorum_evidence text NULL,
            decision_evidence varchar(190) NOT NULL DEFAULT '',
            created_at datetime NOT NULL,
            reviewed_at datetime NULL,
            recorded_at datetime NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY ref_code (ref_code),
            KEY status_created (status,created_at),
            KEY meeting_id (meeting_id)
        ) {$cc};");
        dbDelta("CREATE TABLE {$t} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            source_type varchar(20) NOT NULL,
            source_id bigint(20) unsigned NOT NULL,
            title varchar(190) NOT NULL,
            details text NULL,
            assignee_id bigint(20) unsigned NOT NULL,
            due_on date NULL,
            status varchar(20) NOT NULL DEFAULT 'open',
            completion_note text NULL,
            created_by bigint(20) unsigned NOT NULL,
            created_at datetime NOT NULL,
            completed_at datetime NULL,
            PRIMARY KEY  (id),
            KEY assignee_status (assignee_id,status),
            KEY source_record (source_type,source_id)
        ) {$cc};");
        dbDelta("CREATE TABLE {$n} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            recipient_id bigint(20) unsigned NOT NULL,
            event_key varchar(50) NOT NULL,
            entity_type varchar(20) NOT NULL,
            entity_id bigint(20) unsigned NOT NULL,
            subject varchar(190) NOT NULL,
            message text NULL,
            is_read tinyint(1) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            read_at datetime NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY delivery (recipient_id,event_key,entity_type,entity_id),
            KEY recipient_read (recipient_id,is_read)
        ) {$cc};");
        dbDelta("CREATE TABLE {$e} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            entity_type varchar(20) NOT NULL,
            entity_id bigint(20) unsigned NOT NULL,
            event_key varchar(50) NOT NULL,
            actor_id bigint(20) unsigned NOT NULL,
            detail text NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY entity_event (entity_type,entity_id),
            KEY created_at (created_at)
        ) {$cc};");
        foreach (array($m,$a,$mi,$r,$t,$n,$e) as $table) {
            if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) { return; }
        }
        update_option('ino_gov_ops_schema_version', self::SCHEMA_VERSION, false);
    }

    public static function url() { return admin_url('admin.php?page=ino-governance-operations'); }
    private static function fail($message, $code=400) { wp_die(esc_html($message), '', array('response'=>$code)); }
    private static function text($key, $max=190) {
        $value = isset($_POST[$key]) ? sanitize_text_field(wp_unslash($_POST[$key])) : '';
        return function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max);
    }
    private static function paragraph($key, $max=30000) {
        $value = isset($_POST[$key]) ? sanitize_textarea_field(wp_unslash($_POST[$key])) : '';
        return function_exists('mb_substr') ? mb_substr($value,0,$max) : substr($value,0,$max);
    }
    private static function number($key) {
        if (!isset($_POST[$key]) || $_POST[$key] === '') { return 0; }
        $value = (string)wp_unslash($_POST[$key]);
        if (!preg_match('/^(0|[1-9][0-9]*)$/D', $value)) {
            self::fail('A nonnegative whole number is required for '.$key.'.');
        }
        if (strlen($value) > 15) { self::fail('Numeric value exceeds safe range.'); }
        return (int)$value;
    }
    private static function date_value($key, $time=false) {
        $s=self::text($key,30);
        if ($s==='') { return null; }
        if ($time) {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}T[0-2]\d:[0-5]\d$/D',$s)) { self::fail('Invalid local meeting date/time.'); }
            $date=substr($s,0,10);$clock=substr($s,11);
            if ((int)substr($clock,0,2)>23) { self::fail('Invalid meeting hour.'); }
        } else {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$s)) { self::fail('Invalid date.'); }
            $date=$s;
        }
        $p=explode('-',$date);
        if (!checkdate((int)$p[1],(int)$p[2],(int)$p[0])) { self::fail('Invalid calendar date.'); }
        return $time ? ($date.' '.$clock.':00') : $date;
    }
    private static function code($prefix) {
        return $prefix.'-'.gmdate('Ymd').'-'.strtoupper(substr(str_replace('-','',wp_generate_uuid4()),0,12));
    }
    private static function get($key,$id) {
        global $wpdb;
        if (!$id) { self::fail('Record ID required.'); }
        $table=self::table($key);
        $item=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id=%d",$id));
        if (!$item) { self::fail('Record not found.',404); }
        return $item;
    }
    private static function audit($key,$id,$event,$detail='') {
        global $wpdb;
        return (bool)$wpdb->insert(self::table('events'),array(
            'entity_type'=>$key,'entity_id'=>$id,'event_key'=>$event,
            'actor_id'=>get_current_user_id(),'detail'=>$detail,
            'created_at'=>current_time('mysql')
        ));
    }
    private static function done($notice) {
        wp_safe_redirect(add_query_arg('ino_ops_notice',rawurlencode($notice),self::url()));
        exit;
    }
    private static function insert_audited($key,$data,$event,$detail) {
        global $wpdb;
        $wpdb->query('START TRANSACTION');
        $result=$wpdb->insert(self::table($key),$data);
        if (!$result) { $wpdb->query('ROLLBACK'); self::fail('Record creation failed; verify source and unique identifier.',409); }
        $id=(int)$wpdb->insert_id;
        if (!self::audit($key,$id,$event,$detail)) { $wpdb->query('ROLLBACK'); self::fail('Audit failed; record rolled back.',500); }
        $wpdb->query('COMMIT');
        return $id;
    }
    private static function transition_audited($key,$id,$status,$updates,$new_status,$event,$detail) {
        global $wpdb;
        $updates['status']=$new_status;
        $wpdb->query('START TRANSACTION');
        $result=$wpdb->update(self::table($key),$updates,array('id'=>$id,'status'=>$status));
        if ($result!==1) { $wpdb->query('ROLLBACK'); self::fail('Status changed or transition not permitted.',409); }
        if (!self::audit($key,$id,$event,$detail)) { $wpdb->query('ROLLBACK'); self::fail('Audit failed; change rolled back.',500); }
        $wpdb->query('COMMIT');
    }
    private static function require_cap($op) {
        $review=array('review_minutes','review_resolution');
        $publish=array('record_decision');
        $cap=in_array($op,$review,true) ? 'ino_governance_review' : (in_array($op,$publish,true) ? 'ino_governance_publish' : 'ino_governance_edit');
        if (in_array($op,array('complete_task','read_notice'),true)) {
            if (!is_user_logged_in()) { self::fail('Authentication required.',403); }
        } elseif (!current_user_can($cap)) {
            self::fail('Governance authorization required for this operation.',403);
        }
    }

    public static function handle() {
        $op=isset($_POST['operation']) ? sanitize_key(wp_unslash($_POST['operation'])) : '';
        $allowed=array('create_meeting','schedule_meeting','hold_meeting','add_agenda','submit_minutes',
            'review_minutes','create_resolution','review_resolution','record_decision','create_task','complete_task','read_notice');
        if (!in_array($op,$allowed,true)) { self::fail('Unknown operation.'); }
        self::require_cap($op);
        check_admin_referer('ino_gov_ops_'.$op,'ino_ops_nonce');
        switch($op) {
            case 'create_meeting': self::create_meeting(); break;
            case 'schedule_meeting': self::schedule_meeting(); break;
            case 'hold_meeting': self::hold_meeting(); break;
            case 'add_agenda': self::add_agenda(); break;
            case 'submit_minutes': self::submit_minutes(); break;
            case 'review_minutes': self::review_minutes(); break;
            case 'create_resolution': self::create_resolution(); break;
            case 'review_resolution': self::review_resolution(); break;
            case 'record_decision': self::record_decision(); break;
            case 'create_task': self::create_task(); break;
            case 'complete_task': self::complete_task(); break;
            case 'read_notice': self::read_notice(); break;
        }
    }
    private static function create_meeting() {
        $title=self::text('title'); $time=self::date_value('meeting_at',true);
        $source=self::text('source_ref');$office=self::number('office_id');
        if (!$title || !$time || !$source) { self::fail('Meeting title, time, and source/authority reference required.'); }
        if ($office) {
            global $wpdb;
            $o=$wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}ino_governance_items WHERE id=%d AND record_type='office' AND status='published'",$office));
            if (!$o) { self::fail('Office must be published in Phase 1 registry.'); }
        }
        $id=self::insert_audited('meetings',array(
            'ref_code'=>self::code('INO-MTG'),'title'=>$title,'meeting_at'=>$time,
            'venue'=>self::text('venue'),'office_id'=>$office?:null,'source_ref'=>$source,
            'status'=>'draft','created_by'=>get_current_user_id(),
            'created_at'=>current_time('mysql'),'updated_at'=>current_time('mysql')
        ),'meeting_drafted','Meeting created for internal consideration.');
        self::done('Meeting draft #'.$id.' stored.');
    }
    private static function schedule_meeting() {
        $m=self::get('meetings',self::number('meeting_id'));
        if ($m->status!=='draft') { self::fail('Only draft meetings can be scheduled.',409); }
        global $wpdb;
        $n=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM ".self::table('agenda')." WHERE meeting_id=%d",$m->id));
        if (!$n) { self::fail('At least one agenda item must be recorded before scheduling.'); }
        self::transition_audited('meetings',$m->id,'draft',array('updated_at'=>current_time('mysql')),
            'scheduled','meeting_scheduled','Schedule published only within restricted workspace.');
        self::done('Meeting scheduled within restricted workspace.');
    }
    private static function hold_meeting() {
        $m=self::get('meetings',self::number('meeting_id'));
        if ($m->status!=='scheduled') { self::fail('Only scheduled meetings may be recorded as held.',409); }
        if ($m->meeting_at > current_time('mysql')) { self::fail('A future meeting cannot be recorded as held.',409); }
        $n=self::number('attendance_count'); $e=self::paragraph('attendance_evidence',3000);
        if (!$n || strlen($e)<12) { self::fail('Attendance count and attendance evidence reference required.'); }
        self::transition_audited('meetings',$m->id,'scheduled',array(
            'attendance_count'=>$n,'attendance_evidence'=>$e,'updated_at'=>current_time('mysql')
        ),'held','meeting_held','Attendance was recorded; quorum and voting validity are NOT independently verified.');
        self::done('Meeting recorded as held (no quorum certification inferred).');
    }
    private static function add_agenda() {
        $m=self::get('meetings',self::number('meeting_id'));
        if ($m->status!=='draft') { self::fail('Agendas are locked after scheduling; create a new meeting/version if changes are required.',409); }
        $topic=self::text('topic');
        if (!$topic) { self::fail('Agenda topic required.'); }
        $id=self::insert_audited('agenda',array(
            'meeting_id'=>$m->id,'sequence_no'=>max(1,self::number('sequence_no')),
            'topic'=>$topic,'briefing'=>self::paragraph('briefing',6000),
            'created_by'=>get_current_user_id(),'created_at'=>current_time('mysql')
        ),'agenda_added','Agenda item added to draft meeting '.$m->id);
        self::done('Agenda item #'.$id.' created.');
    }
    private static function submit_minutes() {
        global $wpdb;
        $m=self::get('meetings',self::number('meeting_id'));
        if ($m->status!=='held') { self::fail('Only a meeting recorded as held may receive minutes.',409); }
        $body=self::paragraph('minutes_text',80000);
        $source=self::text('source_ref');
        if (strlen(trim($body))<50 || !$source) { self::fail('Minutes require at least 50 characters and source reference.'); }
        // Serial increments in a transaction with a row lock to avoid duplicate revisions.
        $wpdb->query('START TRANSACTION');
        $locked=$wpdb->get_var($wpdb->prepare("SELECT id FROM ".self::table('meetings')." WHERE id=%d FOR UPDATE",$m->id));
        if (!$locked) { $wpdb->query('ROLLBACK'); self::fail('Meeting no longer exists.',409); }
        $version=1+(int)$wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(MAX(revision),0) FROM ".self::table('minutes')." WHERE meeting_id=%d",$m->id
        ));
        $ok=$wpdb->insert(self::table('minutes'),array(
            'meeting_id'=>$m->id,'revision'=>$version,'minutes_text'=>$body,'status'=>'submitted',
            'source_ref'=>$source,'created_by'=>get_current_user_id(),'created_at'=>current_time('mysql')
        ));
        if (!$ok) { $wpdb->query('ROLLBACK'); self::fail('Minutes submission failed.',409); }
        $id=(int)$wpdb->insert_id;
        if (!self::audit('minutes',$id,'minutes_submitted','Version '.$version.' for meeting '.$m->id)) {
            $wpdb->query('ROLLBACK'); self::fail('Audit failed.',500);
        }
        $wpdb->query('COMMIT');
        self::done('Minutes revision '.$version.' submitted for independent review.');
    }
    private static function review_minutes() {
        $mi=self::get('minutes',self::number('minutes_id'));
        if ($mi->status!=='submitted') { self::fail('Only submitted minutes may be reviewed.',409); }
        if ((int)$mi->created_by===get_current_user_id()) { self::fail('Independent reviewer required.',403); }
        $note=self::paragraph('review_note',3000);
        if (strlen($note)<12) { self::fail('Substantive review note required.'); }
        self::transition_audited('minutes',$mi->id,'submitted',array(
            'reviewed_by'=>get_current_user_id(),'reviewed_at'=>current_time('mysql')
        ),'reviewed','minutes_reviewed',$note);
        self::done('Meeting minutes were reviewed (not a legal quorum or vote certification).');
    }
    private static function create_resolution() {
        $title=self::text('title');$body=self::paragraph('motion_text',80000);$authority=self::text('authority_ref');
        if (!$title || strlen(trim($body))<25 || !$authority) { self::fail('Resolution title, motion text and authority reference required.'); }
        $meeting=self::number('meeting_id');
        if ($meeting) { self::get('meetings',$meeting); }
        $id=self::insert_audited('resolutions',array(
            'ref_code'=>self::code('INO-RES'),'title'=>$title,'motion_text'=>$body,
            'authority_ref'=>$authority,'meeting_id'=>$meeting?:null,'status'=>'draft',
            'created_by'=>get_current_user_id(),'created_at'=>current_time('mysql')
        ),'resolution_drafted','Draft motion, not adopted.');
        self::done('Resolution draft #'.$id.' registered.');
    }
    private static function review_resolution() {
        $r=self::get('resolutions',self::number('resolution_id'));
        if ($r->status!=='draft') { self::fail('Only draft resolutions can be reviewed.',409); }
        if ((int)$r->created_by===get_current_user_id()) { self::fail('Review by another authorized person is required.',403); }
        $note=self::paragraph('review_note',3000);
        if (strlen($note)<12) { self::fail('Review evidence note required.'); }
        self::transition_audited('resolutions',$r->id,'draft',array(
            'reviewed_by'=>get_current_user_id(),'reviewed_at'=>current_time('mysql')
        ),'reviewed','resolution_reviewed',$note);
        self::done('Resolution reviewed, not adopted.');
    }
    private static function record_decision() {
        global $wpdb;
        $r=self::get('resolutions',self::number('resolution_id'));
        if ($r->status!=='reviewed' || !$r->meeting_id) { self::fail('Reviewed resolution linked to a held meeting required.',409); }
        if ((int)$r->reviewed_by===get_current_user_id()) { self::fail('Decision recorder must differ from resolution reviewer.',403); }
        $meeting=self::get('meetings',(int)$r->meeting_id);
        if ($meeting->status!=='held') { self::fail('Meeting must be recorded as held.',409); }
        $latest=$wpdb->get_row($wpdb->prepare(
            "SELECT status FROM ".self::table('minutes')." WHERE meeting_id=%d ORDER BY revision DESC LIMIT 1",(int)$r->meeting_id
        ));
        if (!$latest || $latest->status!=='reviewed') { self::fail('Latest meeting minutes must have an independent review.',409); }
        $outcome=self::text('outcome',30);
        if (!in_array($outcome,array('carried','not_carried','tabled'),true)) { self::fail('Invalid recorded motion outcome.'); }
        $for=self::number('votes_for');$against=self::number('votes_against');$abstain=self::number('abstentions');
        if ($for+$against+$abstain>(int)$meeting->attendance_count) { self::fail('Votes exceed recorded attendance.'); }
        if ($outcome!=='tabled' && ($for+$against)===0) { self::fail('A recorded yes/no vote tally is required for this outcome.'); }
        $quorum=self::paragraph('quorum_evidence',3000);$source=self::text('decision_evidence');
        if (strlen($quorum)<20 || !$source) { self::fail('Documented quorum-rule reference, attendance assessment, and decision evidence reference required.'); }
        if (empty($_POST['decision_attestation']) || (string)$_POST['decision_attestation']!=='1') { self::fail('Explicit decision recording attestation required.'); }
        self::transition_audited('resolutions',$r->id,'reviewed',array(
            'recorded_by'=>get_current_user_id(),'outcome'=>$outcome,
            'votes_for'=>$for,'votes_against'=>$against,'abstentions'=>$abstain,
            'quorum_evidence'=>$quorum,'decision_evidence'=>$source,
            'recorded_at'=>current_time('mysql')
        ),'outcome_recorded','motion_outcome_recorded',
            'Human attested outcome '.$outcome.'; internal record only; no automatic legal adoption or public Phase 1 publication.');
        self::done('Decision evidence recorded; separate formal adoption/authority verification still required.');
    }
    private static function create_task() {
        global $wpdb;
        $title=self::text('title');$assignee=self::number('assignee_id');
        $type=self::text('source_type',20);$source=self::number('source_id');
        if (!$title || !$assignee || !in_array($type,array('meeting','resolution'),true)) { self::fail('Title, authorized assignee and related record are required.'); }
        $user=get_userdata($assignee);
        if (!$user || !user_can($assignee,'ino_governance_view')) { self::fail('Assignee must have current governance-view permission.'); }
        self::get($type==='meeting'?'meetings':'resolutions',$source);
        $due=self::date_value('due_on');
        $data=array('source_type'=>$type,'source_id'=>$source,'title'=>$title,
            'details'=>self::paragraph('details',5000),'assignee_id'=>$assignee,
            'due_on'=>$due,'status'=>'open','created_by'=>get_current_user_id(),
            'created_at'=>current_time('mysql'));
        $wpdb->query('START TRANSACTION');
        if (!$wpdb->insert(self::table('tasks'),$data)) { $wpdb->query('ROLLBACK'); self::fail('Task creation failed.',409); }
        $id=(int)$wpdb->insert_id;
        if (!self::audit('tasks',$id,'task_assigned','Task assigned to authorized user #'.$assignee)) {
            $wpdb->query('ROLLBACK');self::fail('Task audit failed.',500);
        }
        $notice=array('recipient_id'=>$assignee,'event_key'=>'assignment','entity_type'=>'task',
            'entity_id'=>$id,'subject'=>'Governance task assigned',
            'message'=>'A restricted INO governance task has been assigned to you. Open the Governance Operations portal to review it.',
            'is_read'=>0,'created_at'=>current_time('mysql'));
        if (!$wpdb->insert(self::table('notices'),$notice)) {
            $wpdb->query('ROLLBACK');self::fail('Notification creation failed; task rolled back.',500);
        }
        $wpdb->query('COMMIT');
        self::done('Task #'.$id.' assigned with a private in-platform notification.');
    }
    private static function complete_task() {
        $t=self::get('tasks',self::number('task_id'));
        if ((int)$t->assignee_id!==get_current_user_id()) { self::fail('Only the assigned officer may complete this task.',403); }
        if ($t->status!=='open') { self::fail('Task is already complete.',409); }
        $note=self::paragraph('completion_note',3000);
        if (strlen($note)<12) { self::fail('Completion evidence required.'); }
        self::transition_audited('tasks',$t->id,'open',array(
            'completion_note'=>$note,'completed_at'=>current_time('mysql')
        ),'complete','task_completed',$note);
        self::done('Task completion recorded with evidence.');
    }
    private static function read_notice() {
        global $wpdb;
        $id=self::number('notice_id');
        $notice=self::get('notices',$id);
        if ((int)$notice->recipient_id!==get_current_user_id()) { self::fail('Notice is not addressed to you.',403); }
        $wpdb->query('START TRANSACTION');
        $updated=$wpdb->update(self::table('notices'),
            array('is_read'=>1,'read_at'=>current_time('mysql')),
            array('id'=>$id,'recipient_id'=>get_current_user_id(),'is_read'=>0));
        if ($updated===false) {
            $wpdb->query('ROLLBACK');
            self::fail('Notification could not be updated.',500);
        }
        // An actual state transition must have a corresponding actor-attributed
        // audit event. Re-reading an already-read notice is idempotent.
        if ($updated===1 && !self::audit('notices',$id,'notification_read',
            'Recipient acknowledged a private governance notification.')) {
            $wpdb->query('ROLLBACK');
            self::fail('Notification audit failed; change rolled back.',500);
        }
        $wpdb->query('COMMIT');
        self::done('Notification marked read.');
    }
}
