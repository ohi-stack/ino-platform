<?php
if (!defined('ABSPATH')) { exit; }

/**
 * INO Voting v1.6 — nonbinding community and internal consultation ballots.
 * Recorded votes are identifiable internally, never represented as secret or
 * anonymous, and cannot adopt ordinances or confer constitutional authority.
 */
final class INO_Platform_Voting {
    const SCHEMA = '1';
    const ADMIN = 'ino-platform-voting';

    public static function tables($key) {
        global $wpdb;
        $names=array('polls'=>'ino_votes_polls','options'=>'ino_votes_options','ballots'=>'ino_votes_ballots',
            'choices'=>'ino_votes_choices','events'=>'ino_votes_events');
        if (!isset($names[$key])) { wp_die('Unknown voting table.', '', array('response'=>500)); }
        return $wpdb->prefix.$names[$key];
    }
    public static function install() {
        if (get_option('ino_voting_schema_version')===self::SCHEMA) { return; }
        global $wpdb;
        require_once ABSPATH.'wp-admin/includes/upgrade.php';
        $cc=$wpdb->get_charset_collate();
        $p=self::tables('polls');$o=self::tables('options');$b=self::tables('ballots');
        $c=self::tables('choices');$e=self::tables('events');
        dbDelta("CREATE TABLE {$p} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            poll_code varchar(80) NOT NULL,
            title varchar(190) NOT NULL,
            description longtext NULL,
            category varchar(80) NOT NULL DEFAULT 'Community',
            electorate varchar(30) NOT NULL DEFAULT 'approved_members',
            ballot_type varchar(20) NOT NULL DEFAULT 'single',
            max_choices int(10) unsigned NOT NULL DEFAULT 1,
            results_policy varchar(20) NOT NULL DEFAULT 'after_close',
            status varchar(20) NOT NULL DEFAULT 'draft',
            opens_at datetime NULL,
            closes_at datetime NULL,
            created_by bigint(20) unsigned NOT NULL,
            opened_by bigint(20) unsigned NULL,
            closed_by bigint(20) unsigned NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY poll_code (poll_code),
            KEY status_window (status,opens_at,closes_at)
        ) {$cc};");
        dbDelta("CREATE TABLE {$o} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            poll_id bigint(20) unsigned NOT NULL,
            label varchar(190) NOT NULL,
            display_order int(10) unsigned NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY poll_order (poll_id,display_order),
            KEY poll_id (poll_id)
        ) {$cc};");
        dbDelta("CREATE TABLE {$b} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            poll_id bigint(20) unsigned NOT NULL,
            voter_id bigint(20) unsigned NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY one_ballot (poll_id,voter_id),
            KEY voter_id (voter_id)
        ) {$cc};");
        dbDelta("CREATE TABLE {$c} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            ballot_id bigint(20) unsigned NOT NULL,
            option_id bigint(20) unsigned NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY choice_once (ballot_id,option_id),
            KEY option_id (option_id)
        ) {$cc};");
        dbDelta("CREATE TABLE {$e} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            poll_id bigint(20) unsigned NOT NULL,
            actor_id bigint(20) unsigned NOT NULL,
            event_key varchar(45) NOT NULL,
            detail text NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY poll_event (poll_id,id)
        ) {$cc};");
        foreach (array($p,$o,$b,$c,$e) as $table) {
            if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$table))!==$table) { return; }
        }
        update_option('ino_voting_schema_version',self::SCHEMA,false);
    }
    public static function init() {
        add_action('admin_post_ino_vote_manage',array(__CLASS__,'manage'));
        add_action('admin_post_ino_vote_cast',array(__CLASS__,'cast'));
        add_shortcode('ino_voting',array(__CLASS__,'frontend'));
        add_shortcode('ino_vote_results',array(__CLASS__,'results_shortcode'));
        add_shortcode('ino_my_votes',array(__CLASS__,'my_votes'));
        add_action('admin_enqueue_scripts',array(__CLASS__,'admin_assets'));
        add_action('wp_enqueue_scripts',array(__CLASS__,'public_assets'));
    }
    public static function admin_assets($hook) {
        if (strpos((string)$hook,self::ADMIN)===false) { return; }
        wp_enqueue_style('ino-voting',INO_PLATFORM_URL.'assets/css/ino-voting.css',
            array('ino-platform-admin-command'),INO_PLATFORM_VERSION);
    }
    public static function public_assets() {
        wp_enqueue_style('ino-voting',INO_PLATFORM_URL.'assets/css/ino-voting.css',
            array('ino-platform-public-design'),INO_PLATFORM_VERSION);
    }
    private static function input($name,$max=190,$large=false) {
        $value=isset($_POST[$name]) ? wp_unslash($_POST[$name]) : '';
        if (!is_string($value)) { self::fail('Invalid field '.$name); }
        $value=$large?sanitize_textarea_field($value):sanitize_text_field($value);
        return function_exists('mb_substr') ? mb_substr($value,0,$max) : substr($value,0,$max);
    }
    private static function number($name) {
        $value=isset($_POST[$name]) && is_scalar($_POST[$name]) ? (string)wp_unslash($_POST[$name]) : '';
        if (!preg_match('/^(0|[1-9][0-9]*)$/D',$value) || strlen($value)>15) { self::fail('Invalid ID or numeric input.'); }
        return (int)$value;
    }
    private static function fail($error,$status=400) {
        wp_die(esc_html($error),'',array('response'=>$status));
    }
    private static function now() { return current_time('mysql'); }
    private static function audit($poll,$key,$detail='') {
        global $wpdb;
        return (bool)$wpdb->insert(self::tables('events'),array(
            'poll_id'=>$poll,'actor_id'=>get_current_user_id(),'event_key'=>$key,
            'detail'=>$detail,'created_at'=>self::now()
        ));
    }
    private static function commit($ok,$error) {
        global $wpdb;
        if (!$ok) { $wpdb->query('ROLLBACK');self::fail($error,409); }
        $wpdb->query('COMMIT');
    }
    private static function redirect($message,$admin=false) {
        $base=$admin ? admin_url('admin.php?page='.self::ADMIN) : home_url('/ino-voting/');
        wp_safe_redirect(add_query_arg('ino_vote_notice',$message,$base));
        exit;
    }
    private static function poll($id,$lock=false) {
        global $wpdb;$t=self::tables('polls');
        if (!$id) { self::fail('Poll ID required.'); }
        $result=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$t} WHERE id=%d".($lock?' FOR UPDATE':''),$id));
        if (!$result) { self::fail('Ballot not found.',404); }
        return $result;
    }
    public static function eligible($poll,$user_id=0) {
        $user_id=$user_id?:get_current_user_id();
        if (!$user_id || !get_userdata($user_id)) { return false; }
        if ($poll->electorate==='registered') { return true; }
        if ($poll->electorate==='governance') { return user_can($user_id,'ino_governance_view'); }
        if ($poll->electorate!=='approved_members') { return false; }
        global $wpdb;$t=$wpdb->prefix.'ino_members';
        $last=$wpdb->get_row($wpdb->prepare(
            "SELECT status FROM {$t} WHERE user_id=%d ORDER BY id DESC LIMIT 1",$user_id));
        // Private voting NEVER depends on public-directory consent.
        return $last && strtolower((string)$last->status)==='approved';
    }
    public static function available($p) {
        $now=self::now();
        return $p->status==='open' && (!$p->opens_at || $now >= $p->opens_at) &&
            (!$p->closes_at || $now < $p->closes_at);
    }
    public static function finished($p) {
        return $p->status==='closed' || ($p->status==='open' && $p->closes_at && self::now()>=$p->closes_at);
    }
    private static function options($id) {
        global $wpdb;$t=self::tables('options');
        return $wpdb->get_results($wpdb->prepare("SELECT id,label,display_order FROM {$t} WHERE poll_id=%d ORDER BY display_order,id",$id));
    }
    private static function has_voted($poll,$user) {
        global $wpdb;$t=self::tables('ballots');
        return (bool)$wpdb->get_var($wpdb->prepare("SELECT id FROM {$t} WHERE poll_id=%d AND voter_id=%d",$poll,$user));
    }
    private static function clean_options() {
        $raw=self::input('option_lines',16000,true);
        $lines=preg_split('/\r\n|\r|\n/',trim($raw));
        $out=array();$seen=array();
        foreach ((array)$lines as $item) {
            $item=trim(sanitize_text_field($item));
            if (!$item) { continue; }
            $item=function_exists('mb_substr')?mb_substr($item,0,190):substr($item,0,190);
            $key=strtolower($item);
            if (isset($seen[$key])) { self::fail('Duplicate ballot option.'); }
            $seen[$key]=true;$out[]=$item;
        }
        if (count($out)<2 || count($out)>20) { self::fail('Provide 2–20 distinct options, one per line.'); }
        return $out;
    }
    private static function datetime($name) {
        $raw=self::input($name,25);
        if ($raw==='') { return null; }
        $date=DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',$raw,wp_timezone());
        if (!$date || $date->format('Y-m-d\TH:i')!==$raw) { self::fail('Invalid date/time.'); }
        return $date->format('Y-m-d H:i:s');
    }
    public static function manage() {
        if (!is_user_logged_in() || !current_user_can('manage_options')) { self::fail('Administrator required.',403); }
        $op=self::input('operation',30);
        if (!in_array($op,array('create','edit','open','close','cancel','export'),true)) { self::fail('Unsupported action.'); }
        check_admin_referer('ino_vote_'.$op,'ino_vote_nonce');
        if ($op==='export') { self::export(); return; }
        if ($op==='create' || $op==='edit') { self::save_poll($op); return; }
        global $wpdb;$id=self::number('poll_id');$t=self::tables('polls');
        $wpdb->query('START TRANSACTION');
        $poll=self::poll($id,true);
        $next='';
        if ($op==='open' && $poll->status==='draft') {
            if (!$poll->opens_at || !$poll->closes_at || $poll->closes_at<=$poll->opens_at ||
                $poll->closes_at<=self::now()) { $wpdb->query('ROLLBACK');self::fail('Set a valid future close time and start time before opening.'); }
            $next='open';
        } elseif ($op==='close' && $poll->status==='open') {
            $next='closed';
        } elseif ($op==='cancel' && $poll->status==='draft') {
            $next='cancelled';
        } else {
            $wpdb->query('ROLLBACK');self::fail('Action not allowed in current poll state.',409);
        }
        $fields=array('status'=>$next,'updated_at'=>self::now());
        if ($next==='open') { $fields['opened_by']=get_current_user_id(); }
        if ($next==='closed') { $fields['closed_by']=get_current_user_id(); }
        $changed=$wpdb->update($t,$fields,array('id'=>$id,'status'=>$poll->status));
        self::commit($changed===1 && self::audit($id,'poll_'.$next,'State transition recorded.'),'Poll transition audit failed.');
        self::redirect('Poll state updated: '.$next,true);
    }
    private static function save_poll($op) {
        global $wpdb;
        $title=self::input('title');
        $description=self::input('description',14000,true);
        $category=self::input('category',80);
        $electorate=self::input('electorate',30);
        $ballot_type=self::input('ballot_type',20);
        $results_policy=self::input('results_policy',20);
        $max=self::number('max_choices');
        $from=self::datetime('opens_at');$to=self::datetime('closes_at');
        $options=self::clean_options();
        if (!$title || !in_array($electorate,array('registered','approved_members','governance'),true) ||
            !in_array($ballot_type,array('single','multiple'),true) ||
            !in_array($results_policy,array('after_close','admins_only'),true) ||
            !$from || !$to || $from>=$to || $to<=self::now() ||
            $max<1 || $max>count($options) ||
            ($ballot_type==='single' && $max!==1)) {
            self::fail('Review title, audience, choices, and future voting window.');
        }
        $data=array('title'=>$title,'description'=>$description,'category'=>$category?:'Community',
            'electorate'=>$electorate,'ballot_type'=>$ballot_type,'max_choices'=>$max,
            'results_policy'=>$results_policy,'opens_at'=>$from,'closes_at'=>$to,'updated_at'=>self::now());
        $wpdb->query('START TRANSACTION');
        if ($op==='edit') {
            $id=self::number('poll_id');
            $p=self::poll($id,true);
            if ($p->status!=='draft') { $wpdb->query('ROLLBACK');self::fail('Only drafts can be edited.',409); }
            if ($wpdb->update(self::tables('polls'),$data,array('id'=>$id,'status'=>'draft'))===false) {
                $wpdb->query('ROLLBACK');self::fail('Draft update failed.',409);
            }
            if ($wpdb->delete(self::tables('options'),array('poll_id'=>$id))===false) {
                $wpdb->query('ROLLBACK');self::fail('Draft option replacement failed.',409);
            }
        } else {
            $data['poll_code']='INO-VOTE-'.strtoupper(str_replace('-','',wp_generate_uuid4()));
            $data['created_by']=get_current_user_id();
            $data['created_at']=self::now();
            $data['status']='draft';
            if (!$wpdb->insert(self::tables('polls'),$data)) {
                $wpdb->query('ROLLBACK');self::fail('Poll creation failed.',409);
            }
            $id=(int)$wpdb->insert_id;
        }
        foreach ($options as $i=>$label) {
            if (!$wpdb->insert(self::tables('options'),array('poll_id'=>$id,'label'=>$label,'display_order'=>$i+1))) {
                $wpdb->query('ROLLBACK');self::fail('Ballot option creation failed.',409);
            }
        }
        self::commit(self::audit($id,$op==='create'?'poll_drafted':'draft_edited',
            'Internal draft; options and schedule recorded, no ballots.'),'Creation audit failed.');
        self::redirect('Voting draft saved. Open it only after review.',true);
    }
    public static function cast() {
        if (!is_user_logged_in()) { self::fail('Log in to participate.',403); }
        check_admin_referer('ino_vote_cast','ino_vote_nonce');
        $id=self::number('poll_id');
        $raw=isset($_POST['selected']) ? wp_unslash($_POST['selected']) : array();
        if (!is_array($raw) || count($raw)>20) { self::fail('Invalid submitted ballot.'); }
        $ids=array();
        foreach ($raw as $v) {
            if (!is_scalar($v) || !preg_match('/^[1-9][0-9]*$/D',(string)$v)) {
                self::fail('Invalid option.');
            }
            $ids[]=(int)$v;
        }
        $ids=array_values(array_unique($ids));
        if (!$ids) { self::fail('Select at least one option.'); }
        global $wpdb;
        $wpdb->query('START TRANSACTION');
        $poll=self::poll($id,true);
        if (!self::available($poll) || !self::eligible($poll)) {
            $wpdb->query('ROLLBACK');self::fail('Ballot closed or you are not eligible.',403);
        }
        if (count($ids)>(int)$poll->max_choices ||
            ($poll->ballot_type==='single' && count($ids)!==1)) {
            $wpdb->query('ROLLBACK');self::fail('Too many choices selected.');
        }
        $allowed=array_map('intval',array_column(self::options($id),'id'));
        foreach ($ids as $choice) {
            if (!in_array($choice,$allowed,true)) { $wpdb->query('ROLLBACK');self::fail('Option not part of this ballot.',409); }
        }
        if (self::has_voted($id,get_current_user_id())) {
            $wpdb->query('ROLLBACK');self::fail('One recorded ballot per account. Voting again is not permitted.',409);
        }
        if (!$wpdb->insert(self::tables('ballots'),array(
            'poll_id'=>$id,'voter_id'=>get_current_user_id(),'created_at'=>self::now()
        ))) { $wpdb->query('ROLLBACK');self::fail('Duplicate or invalid ballot.',409); }
        $ballot=(int)$wpdb->insert_id;
        foreach ($ids as $choice) {
            if (!$wpdb->insert(self::tables('choices'),array('ballot_id'=>$ballot,'option_id'=>$choice))) {
                $wpdb->query('ROLLBACK');self::fail('Vote selection failed.',409);
            }
        }
        self::commit(self::audit($id,'ballot_cast','Private voter identity recorded; selections excluded from audit summary.'),
            'Ballot audit failed. No vote was recorded.');
        self::redirect('Your vote was recorded. Your selections cannot be changed.');
    }
    public static function tallies($poll_id) {
        global $wpdb;
        $options=self::options($poll_id);$b=self::tables('ballots');$c=self::tables('choices');
        $rows=$wpdb->get_results($wpdb->prepare(
            "SELECT c.option_id,COUNT(*) AS total FROM {$c} c INNER JOIN {$b} b ON b.id=c.ballot_id
             WHERE b.poll_id=%d GROUP BY c.option_id",$poll_id
        ));
        $counts=array();
        foreach ((array)$rows as $r) { $counts[(int)$r->option_id]=(int)$r->total; }
        foreach ($options as $o) { $o->votes=isset($counts[(int)$o->id])?$counts[(int)$o->id]:0; }
        $total=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$b} WHERE poll_id=%d",$poll_id));
        return array($options,$total);
    }
    private static function results_allowed($poll) {
        return current_user_can('manage_options') ||
            ($poll->results_policy==='after_close' && self::finished($poll));
    }
    private static function ballot_card($p,$admin=false) {
        $html='<article class="ino-vote-card"><div class="ino-vote-card-top"><span class="ino-vote-kicker">'.esc_html($p->category).'</span><span class="ino-vote-state">'.esc_html(self::finished($p)?'Closed':ucfirst($p->status)).'</span></div>';
        $html.='<h3>'.esc_html($p->title).'</h3><p>'.nl2br(esc_html($p->description)).'</p>';
        $html.='<div class="ino-vote-meta"><span>Audience: '.esc_html(str_replace('_',' ',$p->electorate)).'</span><span>Closes: '.esc_html($p->closes_at?:'Not set').'</span></div>';
        $user=get_current_user_id();
        $voted=$user && self::has_voted($p->id,$user);
        if (!$admin && self::available($p) && self::eligible($p) && !$voted) {
            $options=self::options($p->id);
            $html.='<form action="'.esc_url(admin_url('admin-post.php')).'" method="post" class="ino-vote-ballot">';
            $html.='<input type="hidden" name="action" value="ino_vote_cast"><input type="hidden" name="poll_id" value="'.esc_attr($p->id).'">';
            $html.=wp_nonce_field('ino_vote_cast','ino_vote_nonce',true,false);
            $html.='<fieldset><legend>'.($p->ballot_type==='single'?'Choose one answer':'Choose up to '.esc_html($p->max_choices).' answers').'</legend>';
            foreach ($options as $o) {
                $type=$p->ballot_type==='single'?'radio':'checkbox';
                $html.='<label class="ino-vote-option"><input type="'.$type.'" name="selected[]" value="'.esc_attr($o->id).'"><span>'.esc_html($o->label).'</span></label>';
            }
            $html.='</fieldset><button class="ino-vote-button" type="submit">Submit final vote</button><p class="ino-vote-small">One ballot per eligible account. Your response cannot be changed. Ballots are not anonymous.</p></form>';
        } elseif (!$admin) {
            $html.='<p class="ino-vote-message">'.($voted?'Your ballot is on record.':(!is_user_logged_in()?'Log in to check voting eligibility.':(self::finished($p)?'Voting has ended.':'Not currently eligible or not yet open.'))).'</p>';
        }
        if (self::results_allowed($p)) { $html.=self::results_html($p); }
        elseif (!$admin) { $html.='<p class="ino-vote-small">Results are withheld until the ballot closes or are restricted to administrators.</p>'; }
        return $html.'</article>';
    }
    private static function results_html($p) {
        list($options,$total)=self::tallies($p->id);
        $html='<div class="ino-vote-results"><h4>Recorded results</h4><p>'.esc_html(number_format_i18n($total)).' participating account(s). This is advisory only.</p>';
        foreach ($options as $o) {
            $percent=$total ? round(100*$o->votes/$total,1) : 0;
            $html.='<div class="ino-vote-result"><div><span>'.esc_html($o->label).'</span><strong>'.esc_html(number_format_i18n($o->votes)).'</strong></div><progress max="100" value="'.esc_attr($percent).'">'.esc_html($percent).'%</progress></div>';
        }
        return $html.'</div>';
    }
    public static function frontend() {
        global $wpdb;$t=self::tables('polls');$now=self::now();
        $polls=$wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$t} WHERE status='open' AND (closes_at IS NULL OR closes_at>%s)
             ORDER BY created_at DESC LIMIT 25",$now
        ));
        $html='<section class="ino-vote-public"><header class="ino-vote-hero"><span>INO · Community Participation</span><h1>Voting &amp; Consultation</h1><p>Participate in community polls and institutional consultations. These polls are nonbinding and do not automatically adopt governance resolutions.</p></header>';
        if (isset($_GET['ino_vote_notice']) && is_string($_GET['ino_vote_notice'])) {
            $html.='<p role="status" class="ino-vote-notice">'.esc_html(sanitize_text_field(wp_unslash($_GET['ino_vote_notice']))).'</p>';
        }
        if (!is_user_logged_in()) { $html.='<p class="ino-vote-notice">Sign in to submit an eligible ballot. Public directory consent is not required for private member voting.</p>'; }
        if (!$polls) { $html.='<p class="ino-vote-notice">No open ballots are available.</p>'; }
        foreach ((array)$polls as $p) { $html.=self::ballot_card($p); }
        return $html.'</section>';
    }
    public static function results_shortcode() {
        global $wpdb;$t=self::tables('polls');$now=self::now();
        $polls=$wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$t} WHERE results_policy='after_close' AND
             (status='closed' OR (status='open' AND closes_at IS NOT NULL AND closes_at<=%s))
             ORDER BY id DESC LIMIT 30",$now
        ));
        $html='<section class="ino-vote-public"><header class="ino-vote-hero"><span>INO · Transparency</span><h1>Published Poll Results</h1><p>Aggregated, nonbinding results; individual voters are not publicly displayed.</p></header>';
        if (!$polls) { $html.='<p class="ino-vote-notice">No poll results have been released.</p>'; }
        foreach ((array)$polls as $p) { $html.=self::ballot_card($p,true); }
        return $html.'</section>';
    }
    public static function my_votes() {
        if (!is_user_logged_in()) {
            return '<section class="ino-vote-public"><p class="ino-vote-notice">Sign in to see your own participation history.</p></section>';
        }
        global $wpdb;$p=self::tables('polls');$b=self::tables('ballots');
        $rows=$wpdb->get_results($wpdb->prepare(
            "SELECT p.title,b.created_at FROM {$b} b INNER JOIN {$p} p ON p.id=b.poll_id
             WHERE b.voter_id=%d ORDER BY b.id DESC LIMIT 100",get_current_user_id()
        ));
        $html='<section class="ino-vote-public"><header class="ino-vote-hero"><span>Private Account</span><h1>My Voting History</h1><p>Recorded participation for your account only. Individual choices are never listed here.</p></header>';
        if (!$rows) { $html.='<p class="ino-vote-notice">You have no recorded ballots.</p>'; }
        foreach ((array)$rows as $r) { $html.='<article class="ino-vote-card"><h3>'.esc_html($r->title).'</h3><p>Recorded '.esc_html($r->created_at).'</p></article>'; }
        return $html.'</section>';
    }
    private static function admin_form($op,$label,$id=0) {
        $s='<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" class="ino-vote-admin-form">';
        $s.='<input type="hidden" name="action" value="ino_vote_manage"><input type="hidden" name="operation" value="'.esc_attr($op).'">';
        if ($id) { $s.='<input type="hidden" name="poll_id" value="'.esc_attr($id).'">'; }
        $s.=wp_nonce_field('ino_vote_'.$op,'ino_vote_nonce',true,false);
        return $s.'<button type="submit" class="ino-vote-small-button">'.esc_html($label).'</button></form>';
    }
    public static function admin_page() {
        if (!current_user_can('manage_options')) { self::fail('Administrator required.',403); }
        global $wpdb;$t=self::tables('polls');$e=self::tables('events');
        $polls=$wpdb->get_results("SELECT * FROM {$t} ORDER BY id DESC LIMIT 100");
        $events=$wpdb->get_results("SELECT poll_id,actor_id,event_key,detail,created_at FROM {$e} ORDER BY id DESC LIMIT 40");
        echo '<main class="ino-admin ino-vote-admin"><header class="ino-hero"><span class="ino-kicker">INO Platform · Participation</span><h1>Voting Management</h1><p>Create community consultations, surveys and internal advisory ballots. Every vote is recorded once per eligible WordPress account. Results do not create legally effective resolutions or certify an election.</p><div class="ino-actions"><a class="ino-btn ino-btn-gold" href="'.esc_url(home_url('/ino-voting/')).'">Open Frontend Voting ↗</a><a class="ino-btn ino-btn-light" href="'.esc_url(home_url('/ino-vote-results/')).'">Published Results ↗</a></div></header>';
        if (isset($_GET['ino_vote_notice']) && is_string($_GET['ino_vote_notice'])) {
            echo '<p class="ino-vote-notice" role="status">'.esc_html(sanitize_text_field(wp_unslash($_GET['ino_vote_notice']))).'</p>';
        }
        echo '<section class="ino-panel"><h2>New Ballot</h2><p>Create a private draft. Set an explicit future start and end time; opened ballots cannot have their options changed.</p>';
        self::render_editor();
        echo '</section><section class="ino-panel"><h2>Ballots &amp; Lifecycle</h2><div class="ino-vote-table"><table class="ino-table"><thead><tr><th>Ballot</th><th>Audience</th><th>Status</th><th>Participation</th><th>Manage</th></tr></thead><tbody>';
        if (!$polls) { echo '<tr><td colspan="5">No ballots created.</td></tr>'; }
        foreach ((array)$polls as $p) {
            list($options,$total)=self::tallies($p->id);
            echo '<tr><td><strong>'.esc_html($p->title).'</strong><br><small>'.esc_html($p->poll_code).'</small><details><summary>Ballot options / details</summary><p>'.nl2br(esc_html($p->description)).'</p><p>'.esc_html($p->opens_at).' → '.esc_html($p->closes_at).'</p>';
            foreach ($options as $o) { echo '<p>'.esc_html($o->label).' — '.esc_html($o->votes).' selections</p>'; }
            echo '</details></td><td>'.esc_html($p->electorate).'</td><td>'.esc_html($p->status).'</td><td>'.esc_html($total).' account(s)</td><td><div class="ino-vote-actions">';
            if ($p->status==='draft') {
                echo self::admin_form('open','Open',$p->id);
                echo self::admin_form('cancel','Cancel draft',$p->id);
                echo '<details><summary>Edit draft</summary>';self::render_editor($p,$options);echo '</details>';
            }
            if ($p->status==='open') { echo self::admin_form('close','Close now',$p->id); }
            echo self::admin_form('export','Export aggregate CSV',$p->id);
            echo '</div></td></tr>';
        }
        echo '</tbody></table></div></section><section class="ino-panel"><h2>Recent Audit Events</h2><p>Actor-attributed transitions and ballot receipts; ballot choices are excluded from audit descriptions.</p><div class="ino-vote-table"><table class="ino-table"><thead><tr><th>When</th><th>Poll ID</th><th>Event</th><th>Actor</th><th>Note</th></tr></thead><tbody>';
        foreach ((array)$events as $v) {
            echo '<tr><td>'.esc_html($v->created_at).'</td><td>'.esc_html($v->poll_id).'</td><td>'.esc_html($v->event_key).'</td><td>'.esc_html($v->actor_id).'</td><td>'.esc_html($v->detail).'</td></tr>';
        }
        if (!$events) { echo '<tr><td colspan="5">No vote events recorded.</td></tr>'; }
        echo '</tbody></table></div></section></main>';
    }
    private static function render_editor($poll=null,$options=array()) {
        $edit=$poll!==null;
        $data=$edit?$poll:(object)array('title'=>'','description'=>'','category'=>'Community',
            'electorate'=>'approved_members','ballot_type'=>'single','max_choices'=>1,'results_policy'=>'after_close',
            'opens_at'=>'','closes_at'=>'');
        echo '<form class="ino-vote-editor" method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="ino_vote_manage"><input type="hidden" name="operation" value="'.($edit?'edit':'create').'">';
        if ($edit) { echo '<input type="hidden" name="poll_id" value="'.esc_attr($poll->id).'">'; }
        wp_nonce_field('ino_vote_'.($edit?'edit':'create'),'ino_vote_nonce');
        echo '<label>Ballot question / title<input required maxlength="190" name="title" value="'.esc_attr($data->title).'"></label>';
        echo '<label>Category<input name="category" maxlength="80" value="'.esc_attr($data->category).'"></label>';
        echo '<label class="ino-vote-wide">Context and background<textarea name="description" rows="4">'.esc_textarea($data->description).'</textarea></label>';
        echo '<label>Who may vote?<select name="electorate">';
        foreach (array('approved_members'=>'Approved INO members','governance'=>'Authorized governance staff','registered'=>'All logged-in WordPress users') as $key=>$value) {
            echo '<option value="'.esc_attr($key).'" '.selected($data->electorate,$key,false).'>'.esc_html($value).'</option>';
        }
        echo '</select></label><label>Ballot type<select name="ballot_type">';
        foreach (array('single'=>'Select one','multiple'=>'Select multiple') as $key=>$value) {
            echo '<option value="'.esc_attr($key).'" '.selected($data->ballot_type,$key,false).'>'.esc_html($value).'</option>';
        }
        echo '</select></label><label>Maximum selections<input type="number" name="max_choices" min="1" max="20" required value="'.esc_attr($data->max_choices).'"></label><label>Public results policy<select name="results_policy">';
        foreach (array('after_close'=>'Aggregate after close','admins_only'=>'Administrators only') as $key=>$value) {
            echo '<option value="'.esc_attr($key).'" '.selected($data->results_policy,$key,false).'>'.esc_html($value).'</option>';
        }
        echo '</select></label><label>Start (site local)<input type="datetime-local" name="opens_at" required value="'.esc_attr($data->opens_at?str_replace(' ','T',substr($data->opens_at,0,16)):'').'"></label>';
        echo '<label>Close (site local)<input type="datetime-local" name="closes_at" required value="'.esc_attr($data->closes_at?str_replace(' ','T',substr($data->closes_at,0,16)):'').'"></label>';
        $lines=$edit?implode("\n",array_map(function($o){return $o->label;},$options)):'Yes'."\n".'No';
        echo '<label class="ino-vote-wide">Ballot options — one per line (2–20)<textarea name="option_lines" rows="5" required>'.esc_textarea($lines).'</textarea></label>';
        echo '<p class="ino-vote-wide">All ballots are advisory and internally attributable to a WordPress account. No vote is legally binding automatically.</p>';
        echo '<button class="ino-btn ino-btn-primary" type="submit">'.($edit?'Save draft changes':'Create draft ballot').'</button></form>';
    }
    private static function export() {
        $id=self::number('poll_id');$p=self::poll($id);
        list($options,$total)=self::tallies($id);
        if (headers_sent()) { self::fail('Headers already sent.'); }
        nocache_headers();
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="ino-poll-'.absint($id).'-aggregate.csv"');
        $stream=fopen('php://output','w');
        fputcsv($stream,array('Poll code','Title','Status','Option','Recorded selections','Participating accounts'));
        foreach ($options as $o) {
            $safe=array($p->poll_code,$p->title,$p->status,$o->label,$o->votes,$total);
            foreach ($safe as &$field) {
                $field=(string)$field;
                if (preg_match('/^[\s]*[=+\-@]/',$field)) { $field="'".$field; }
            } unset($field);
            fputcsv($stream,$safe);
        }
        fclose($stream);
        exit;
    }
}
