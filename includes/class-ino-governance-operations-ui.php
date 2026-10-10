<?php
if (!defined('ABSPATH')) { exit; }

/** Phase 2 workspace. No private material exposed through public shortcodes. */
class INO_Governance_Operations_UI {
    public static function assets($hook) {
        if (strpos((string)$hook,'ino-governance-operations')===false) { return; }
        wp_enqueue_style('ino-governance-operations',INO_PLATFORM_URL.'assets/css/ino-governance-operations.css',
            array('ino-platform-admin-command'),INO_PLATFORM_VERSION);
    }
    private static function form_start($operation) {
        echo '<form class="ino-ops-form" method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="ino_gov_ops"><input type="hidden" name="operation" value="' . esc_attr($operation) . '">';
        wp_nonce_field('ino_gov_ops_'.$operation,'ino_ops_nonce');
    }
    private static function form_end($label) {
        echo '<button class="ino-btn ino-btn-primary" type="submit">' . esc_html($label) . '</button></form>';
    }
    private static function options($rows,$text='title',$empty='Choose a record') {
        echo '<option value="0">' . esc_html($empty) . '</option>';
        foreach ((array)$rows as $row) {
            $label=isset($row->$text) ? $row->$text : '';
            echo '<option value="' . esc_attr((int)$row->id) . '">' . esc_html($label.' · #'.$row->id) . '</option>';
        }
    }
    private static function count($table,$status='') {
        global $wpdb;
        $t=INO_Governance_Operations::table($table);
        if ($status) { return (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$t} WHERE status=%s",$status)); }
        return (int)$wpdb->get_var("SELECT COUNT(*) FROM {$t}");
    }
    private static function headings($title,$subtitle) {
        echo '<div class="ino-section-heading"><div><h2>' . esc_html($title) . '</h2><p>' . esc_html($subtitle) . '</p></div></div>';
    }
    public static function shortcode() {
        if (!is_user_logged_in() || !current_user_can('ino_governance_view')) {
            return '<section class="ino-shell"><div class="ino-member-notice">Governance operations are restricted to authorized INO personnel. Public institutional records may be viewed through the Governance Portal.</div></section>';
        }
        return '<section class="ino-shell"><div class="ino-heading"><span>INO Governance Operations</span><h1>Restricted Workspace</h1></div><div class="ino-member-notice">This is a protected organizational workflow. Open the WordPress administrative workspace to manage meetings, agenda records, minutes, resolution drafts, evidence, assignments, and private notifications.</div><p><a class="ino-btn ino-btn-navy" href="' . esc_url(INO_Governance_Operations::url()) . '">Open Governance Operations ↗</a></p></section>';
    }
    public static function page() {
        if (!current_user_can('ino_governance_view')) { wp_die('Not authorized.', '', array('response'=>403)); }
        global $wpdb;
        $meet_table=INO_Governance_Operations::table('meetings');
        $agenda_table=INO_Governance_Operations::table('agenda');
        $minutes_table=INO_Governance_Operations::table('minutes');
        $res_table=INO_Governance_Operations::table('resolutions');
        $task_table=INO_Governance_Operations::table('tasks');
        $notice_table=INO_Governance_Operations::table('notices');
        $events_table=INO_Governance_Operations::table('events');
        $meetings=$wpdb->get_results("SELECT * FROM {$meet_table} ORDER BY meeting_at DESC,id DESC LIMIT 60");
        $agenda=$wpdb->get_results("SELECT id,meeting_id,sequence_no,topic,briefing FROM {$agenda_table} ORDER BY id DESC LIMIT 60");
        $minutes=$wpdb->get_results("SELECT id,meeting_id,revision,status,created_by,source_ref,minutes_text FROM {$minutes_table} ORDER BY id DESC LIMIT 35");
        $resolutions=$wpdb->get_results("SELECT id,ref_code,title,meeting_id,status,created_by,reviewed_by,outcome,motion_text,authority_ref,decision_evidence,quorum_evidence,votes_for,votes_against,abstentions FROM {$res_table} ORDER BY id DESC LIMIT 60");
        $uid=get_current_user_id();
        $tasks=$wpdb->get_results($wpdb->prepare("SELECT id,source_type,source_id,title,assignee_id,due_on,status FROM {$task_table} WHERE assignee_id=%d OR created_by=%d ORDER BY id DESC LIMIT 60",$uid,$uid));
        $notices=$wpdb->get_results($wpdb->prepare("SELECT id,subject,message,is_read,created_at FROM {$notice_table} WHERE recipient_id=%d ORDER BY id DESC LIMIT 30",$uid));
        $events=$wpdb->get_results("SELECT entity_type,entity_id,event_key,actor_id,detail,created_at FROM {$events_table} ORDER BY id DESC LIMIT 30");
        $offices=$wpdb->get_results("SELECT id,title FROM {$wpdb->prefix}ino_governance_items WHERE record_type='office' AND status='published' ORDER BY title ASC LIMIT 100");
        $users=get_users(array('number'=>200,'orderby'=>'display_name','order'=>'ASC','fields'=>array('ID','display_name')));
        $authorized_users=array();
        foreach ($users as $user) {
            if (user_can($user->ID,'ino_governance_view')) { $authorized_users[]=$user; }
        }
        $holdable=array_values(array_filter((array)$meetings,function($m){return $m->status==='scheduled';}));
        $draft_meetings=array_values(array_filter((array)$meetings,function($m){return $m->status==='draft';}));
        $held_meetings=array_values(array_filter((array)$meetings,function($m){return $m->status==='held';}));
        $reviewable_minutes=array_values(array_filter((array)$minutes,function($m)use($uid){return $m->status==='submitted' && (int)$m->created_by!==$uid;}));
        $reviewable_resolutions=array_values(array_filter((array)$resolutions,function($r)use($uid){return $r->status==='draft' && (int)$r->created_by!==$uid;}));
        $recordable=array_values(array_filter((array)$resolutions,function($r)use($uid){return $r->status==='reviewed' && (int)$r->reviewed_by!==$uid;}));

        echo '<main class="ino-admin ino-ops"><header class="ino-hero"><span class="ino-kicker">INO Governance · Phase 2</span><h1>Operational Governance</h1><p>Meetings, agenda records, minutes, resolution reviews, decision evidence, assigned actions and private in-app notices. Internal decision records do not automatically establish legal adoption or verified quorum.</p></header>';
        echo '<nav class="ino-ops-jump" aria-label="Operations sections"><a href="#ino-ops-meetings">Meetings</a><a href="#ino-ops-agenda">Agendas</a><a href="#ino-ops-minutes">Minutes</a><a href="#ino-ops-resolutions">Resolutions</a><a href="#ino-ops-tasks">Actions</a><a href="#ino-ops-notices">Notifications</a></nav>';
        if (isset($_GET['ino_ops_notice'])) {
            echo '<p class="ino-note" role="status">' . esc_html(sanitize_text_field(wp_unslash($_GET['ino_ops_notice']))) . '</p>';
        }
        self::headings('Operational snapshot','Counts reflect stored records and not independently validated approvals.');
        echo '<div class="ino-grid">';
        foreach (array(
            array('Meetings',self::count('meetings')),array('Agenda items',self::count('agenda')),
            array('Minute versions',self::count('minutes')),array('Resolution drafts',self::count('resolutions','draft')),
            array('Reviewed resolutions',self::count('resolutions','reviewed')),
            array('Recorded outcomes',self::count('resolutions','outcome_recorded')),
            array('Open actions',self::count('tasks','open')),
            array('My unread notifications',(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$notice_table} WHERE recipient_id=%d AND is_read=0",$uid)))
        ) as $metric) {
            echo '<div class="ino-card"><span class="ino-value">' . esc_html(number_format_i18n($metric[1])) . '</span><span class="ino-label">' . esc_html($metric[0]) . '</span></div>';
        }
        echo '</div>';

        echo '<section id="ino-ops-meetings" class="ino-panel"><h2>Meetings &amp; Schedule</h2><p class="ino-panel-help">Only documented draft meetings can be scheduled. Attendance and meeting-held entries do not automatically verify quorum.</p>';
        if (current_user_can('ino_governance_edit')) {
            self::form_start('create_meeting');
            echo '<label>Meeting title<input name="title" required maxlength="190"></label><label>Meeting time (WordPress site-local)<input type="datetime-local" name="meeting_at" required></label><label>Venue or remote meeting context<input name="venue" maxlength="190"></label><label>Authority/source reference<input name="source_ref" required maxlength="190"></label><label>Published governance office<select name="office_id">';
            self::options($offices,'title','No specified office');echo '</select></label>';
            self::form_end('Save meeting draft');
        }
        self::grid_table(array('Ref / meeting','When','Status','Source'),array_map(function($m){return array($m->ref_code.' · '.$m->title,$m->meeting_at,$m->status,$m->source_ref);},(array)$meetings));
        if (current_user_can('ino_governance_edit')) {
            echo '<div class="ino-ops-actions">';
            self::form_start('schedule_meeting');
            echo '<label>Schedule draft meeting<select name="meeting_id" required>';
            self::options($draft_meetings,'title'); echo '</select></label>';
            self::form_end('Schedule (agenda required)');
            self::form_start('hold_meeting');
            echo '<label>Record meeting as held<select name="meeting_id" required>';
            self::options($holdable,'title');echo '</select></label>';
            echo '<label>Recorded attendance count<input type="number" name="attendance_count" min="1" step="1" required></label>';
            echo '<label>Attendance evidence reference / notes<textarea name="attendance_evidence" required minlength="12"></textarea></label>';
            self::form_end('Record meeting held');
            echo '</div>';
        }
        echo '</section>';

        echo '<section id="ino-ops-agenda" class="ino-panel"><h2>Agenda Registry</h2><p class="ino-panel-help">Agenda items can only be added before a meeting is scheduled, preventing silent revisions to scheduled packets.</p>';
        if (current_user_can('ino_governance_edit')) {
            self::form_start('add_agenda');
            echo '<label>Draft meeting<select name="meeting_id" required>';
            self::options($draft_meetings,'title');echo '</select></label>';
            echo '<label>Order<input type="number" name="sequence_no" value="1" min="1" required></label><label>Agenda topic<input name="topic" required maxlength="190"></label><label>Briefing<textarea name="briefing"></textarea></label>';
            self::form_end('Add agenda item');
        }
        self::grid_table(array('Meeting ID','Order','Topic','Briefing'),array_map(function($a){return array($a->meeting_id,$a->sequence_no,$a->topic,$a->briefing);},(array)$agenda));
        echo '</section>';

        echo '<section id="ino-ops-minutes" class="ino-panel"><h2>Meeting Minutes</h2><p class="ino-panel-help">Immutable submitted versions, independent review, recorded evidence. Submission and review do not certify legal validity or adopt a motion.</p>';
        if (current_user_can('ino_governance_edit')) {
            self::form_start('submit_minutes');
            echo '<label>Meeting recorded as held<select name="meeting_id" required>';
            self::options($held_meetings,'title');echo '</select></label>';
            echo '<label>Minutes source reference<input name="source_ref" required maxlength="190"></label><label class="ino-ops-wide">Minutes text<textarea name="minutes_text" required minlength="50" rows="5"></textarea></label>';
            self::form_end('Submit new minutes version');
        }
        self::grid_table(array('Minutes ID','Meeting','Revision','Status'),array_map(function($m){return array($m->id,$m->meeting_id,$m->revision,$m->status);},(array)$minutes));
        foreach ((array)$minutes as $item) {
            echo '<details class="ino-ops-details"><summary>Review minutes #'.esc_html($item->id).' · meeting #'.esc_html($item->meeting_id).' · revision '.esc_html($item->revision).'</summary><p><strong>Source:</strong> '.esc_html($item->source_ref).'</p><div class="ino-ops-record-text">'.nl2br(esc_html($item->minutes_text)).'</div></details>';
        }
        if (current_user_can('ino_governance_review')) {
            self::form_start('review_minutes');
            echo '<label>Submitted minutes by another author<select name="minutes_id" required>';
            self::options($reviewable_minutes,'id');echo '</select></label><label>Independent review evidence note<textarea name="review_note" minlength="12" required></textarea></label>';
            self::form_end('Record minutes review');
        }
        echo '</section>';

        echo '<section id="ino-ops-resolutions" class="ino-panel"><h2>Resolution &amp; Decision Registry</h2><p class="ino-panel-help">Resolution motion text is reviewed independently. Outcomes require a held meeting, reviewed minutes, vote counts, quorum evidence, and a separate authorized recorder. The system does not determine constitutional adoption.</p>';
        if (current_user_can('ino_governance_edit')) {
            self::form_start('create_resolution');
            echo '<label>Resolution title<input name="title" required maxlength="190"></label><label>Meeting (may be assigned at draft creation)<select name="meeting_id">';
            self::options($meetings,'title','No meeting linked (draft only)');echo '</select></label><label>Rule / authority citation<input name="authority_ref" required maxlength="190"></label>';
            echo '<label class="ino-ops-wide">Proposed motion text<textarea name="motion_text" minlength="25" rows="5" required></textarea></label>';
            self::form_end('Save resolution draft');
        }
        self::grid_table(array('Resolution','Meeting','Status','Recorded outcome'),array_map(function($r){return array($r->ref_code.' · '.$r->title,$r->meeting_id?:'Not linked',$r->status,$r->outcome?:'—');},(array)$resolutions));
        foreach ((array)$resolutions as $item) {
            echo '<details class="ino-ops-details"><summary>Review motion '.esc_html($item->ref_code).' · '.esc_html($item->status).'</summary><p><strong>Authority:</strong> '.esc_html($item->authority_ref).'</p><div class="ino-ops-record-text">'.nl2br(esc_html($item->motion_text)).'</div>';
            if ($item->status==='outcome_recorded') {
                echo '<p><strong>Reported outcome:</strong> '.esc_html($item->outcome).' · for '.esc_html($item->votes_for).' / against '.esc_html($item->votes_against).' / abstain '.esc_html($item->abstentions).'</p>';
                echo '<p><strong>Quorum assessment:</strong> '.esc_html($item->quorum_evidence).'</p><p><strong>Evidence reference:</strong> '.esc_html($item->decision_evidence).'</p>';
            }
            echo '</details>';
        }
        if (current_user_can('ino_governance_review')) {
            self::form_start('review_resolution');
            echo '<label>Draft motion by another author<select name="resolution_id" required>';
            self::options($reviewable_resolutions,'title');echo '</select></label><label>Review evidence note<textarea name="review_note" required minlength="12"></textarea></label>';
            self::form_end('Record resolution review');
        }
        if (current_user_can('ino_governance_publish')) {
            self::form_start('record_decision');
            echo '<label>Reviewed resolution<select name="resolution_id" required>';
            self::options($recordable,'title');echo '</select></label><label>Recorded outcome<select name="outcome"><option value="carried">Carried (reported)</option><option value="not_carried">Not carried (reported)</option><option value="tabled">Tabled</option></select></label>';
            echo '<label>Votes for<input type="number" name="votes_for" value="0" min="0"></label><label>Votes against<input type="number" name="votes_against" value="0" min="0"></label><label>Abstentions<input type="number" name="abstentions" value="0" min="0"></label>';
            echo '<label>Decision evidence reference<input name="decision_evidence" required maxlength="190"></label>';
            echo '<label class="ino-ops-wide">Quorum rule citation, attendance assessment and evidence<textarea name="quorum_evidence" required minlength="20"></textarea></label>';
            echo '<label class="ino-ops-wide ino-ops-check"><input type="checkbox" name="decision_attestation" value="1" required> I attest I am authorized to record this reported decision and have inspected the cited evidence. I understand this does not automatically establish legal adoption.</label>';
            self::form_end('Record evidence-backed outcome');
        }
        echo '</section>';

        echo '<section id="ino-ops-tasks" class="ino-panel"><h2>Action Assignments</h2><p class="ino-panel-help">Assign work only to users with existing governance access. Assignees receive a private in-platform notification. There is no automatic email delivery in this candidate.</p>';
        if (current_user_can('ino_governance_edit')) {
            self::form_start('create_task');
            echo '<label>Action title<input name="title" required maxlength="190"></label><label>Related record type<select name="source_type"><option value="meeting">Meeting</option><option value="resolution">Resolution</option></select></label>';
            echo '<label>Related record ID<input name="source_id" type="number" min="1" required></label>';
            echo '<label>Authorized assignee<select name="assignee_id" required><option value="">Choose authorized user</option>';
            foreach ($authorized_users as $user) {
                echo '<option value="' . esc_attr((int)$user->ID) . '">' . esc_html($user->display_name) . ' (#' . esc_html((int)$user->ID) . ')</option>';
            }
            echo '</select></label><label>Due date<input type="date" name="due_on"></label><label class="ino-ops-wide">Assignment details<textarea name="details" rows="3"></textarea></label>';
            self::form_end('Assign action and notify');
        }
        self::grid_table(array('Action','Related record','Assignee','Due','Status'),array_map(function($t){return array($t->title,$t->source_type.' #'.$t->source_id,$t->assignee_id,$t->due_on?:'—',$t->status);},(array)$tasks));
        $mine=array_values(array_filter((array)$tasks,function($t)use($uid){return $t->status==='open' && (int)$t->assignee_id===$uid;}));
        if ($mine) {
            self::form_start('complete_task');
            echo '<label>My open action<select name="task_id" required>';
            self::options($mine,'title');echo '</select></label><label>Completion evidence<textarea name="completion_note" required minlength="12"></textarea></label>';
            self::form_end('Mark assigned action completed');
        }
        echo '</section>';

        echo '<section id="ino-ops-notices" class="ino-panel"><h2>My Private Notifications</h2><p class="ino-panel-help">In-platform task notices appear only for the addressed account; no bulk broadcast or email is sent.</p>';
        if (!$notices) { echo '<div class="ino-empty">No notifications for your account.</div>'; }
        foreach ($notices as $notice) {
            echo '<article class="ino-ops-notice"><strong>' . esc_html($notice->subject) . '</strong><p>' . esc_html($notice->message) . '</p><span class="ino-caption">' . esc_html($notice->created_at) . ' · ' . ($notice->is_read?'Read':'Unread') . '</span>';
            if (!$notice->is_read) {
                self::form_start('read_notice');
                echo '<input type="hidden" name="notice_id" value="' . esc_attr((int)$notice->id) . '">';
                self::form_end('Mark read');
            }
            echo '</article>';
        }
        echo '</section><section class="ino-panel"><h2>Recent Operational Audit</h2><p class="ino-panel-help">Restricted event history with the acting account and evidence notes.</p>';
        self::grid_table(array('When','Type','ID','Event','Actor','Evidence'),array_map(function($event) {
            return array($event->created_at,$event->entity_type,$event->entity_id,str_replace('_',' ',$event->event_key),$event->actor_id,$event->detail);
        },(array)$events));
        echo '</section><p class="ino-note">Governance authority, quorum thresholds, records-retention standards and current appointments must be verified from executed INO instruments. Technical access alone does not grant constitutional decision-making authority.</p></main>';
    }
    private static function grid_table($headers,$data) {
        echo '<div class="ino-ops-table"><table class="ino-table"><thead><tr>';
        foreach ($headers as $header) { echo '<th scope="col">' . esc_html($header) . '</th>'; }
        echo '</tr></thead><tbody>';
        if (!$data) { echo '<tr><td colspan="' . esc_attr(count($headers)) . '">No stored records.</td></tr>'; }
        foreach ($data as $row) {
            echo '<tr>';
            foreach ($row as $cell) { echo '<td>' . esc_html($cell) . '</td>'; }
            echo '</tr>';
        }
        echo '</tbody></table></div>';
    }
}
