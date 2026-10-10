<?php
if (!defined('ABSPATH')) { exit; }

/**
 * INO ODIN / Transparency foundation, Phase 3.
 * Internal record indexing only. This is not a blockchain, digital-signing
 * authority, government registry, or independent authentication service.
 */
class INO_Governance_ODIN {
    const SCHEMA_VERSION = '1';

    public static function table($key) {
        global $wpdb;
        $map = array(
            'records'=>'ino_odin_records',
            'versions'=>'ino_odin_versions',
            'evidence'=>'ino_odin_signature_evidence',
            'events'=>'ino_odin_events'
        );
        if (!isset($map[$key])) { wp_die('Invalid ODIN table.', '', array('response'=>500)); }
        return $wpdb->prefix . $map[$key];
    }

    public static function init() {
        add_action('admin_post_ino_odin_action', array(__CLASS__, 'handle'));
        add_shortcode('ino_odin_registry', array('INO_Governance_ODIN_UI', 'registry_shortcode'));
        add_shortcode('ino_odin_verify', array('INO_Governance_ODIN_UI', 'verify_shortcode'));
        add_shortcode('ino_odin_timeline', array('INO_Governance_ODIN_UI', 'timeline_shortcode'));
        add_action('admin_enqueue_scripts', array('INO_Governance_ODIN_UI','admin_assets'));
        add_action('wp_enqueue_scripts', array('INO_Governance_ODIN_UI','public_assets'));
    }

    public static function install() {
        if (get_option('ino_odin_schema_version') === self::SCHEMA_VERSION) { return; }
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $cc = $wpdb->get_charset_collate();
        $r=self::table('records'); $v=self::table('versions');
        $s=self::table('evidence'); $e=self::table('events');
        dbDelta("CREATE TABLE {$r} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            odin_id varchar(72) NOT NULL,
            created_by bigint(20) unsigned NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY odin_id (odin_id)
        ) {$cc};");
        dbDelta("CREATE TABLE {$v} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            record_id bigint(20) unsigned NOT NULL,
            version_no int(10) unsigned NOT NULL,
            title varchar(190) NOT NULL,
            summary text NULL,
            source_ref varchar(190) NOT NULL,
            source_item_id bigint(20) unsigned NULL,
            source_sha256 char(64) NOT NULL DEFAULT '',
            status varchar(24) NOT NULL DEFAULT 'draft',
            created_by bigint(20) unsigned NOT NULL,
            reviewed_by bigint(20) unsigned NULL,
            published_by bigint(20) unsigned NULL,
            review_note text NULL,
            created_at datetime NOT NULL,
            reviewed_at datetime NULL,
            published_at datetime NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY record_version (record_id,version_no),
            KEY record_status (record_id,status),
            KEY source_item_id (source_item_id),
            KEY published_at (published_at)
        ) {$cc};");
        dbDelta("CREATE TABLE {$s} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            version_id bigint(20) unsigned NOT NULL,
            witness_user_id bigint(20) unsigned NOT NULL,
            evidence_ref varchar(190) NOT NULL,
            attestation text NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY version_id (version_id)
        ) {$cc};");
        dbDelta("CREATE TABLE {$e} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            record_id bigint(20) unsigned NOT NULL,
            version_id bigint(20) unsigned NOT NULL,
            event_key varchar(40) NOT NULL,
            actor_id bigint(20) unsigned NOT NULL,
            note text NULL,
            occurred_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY record_event (record_id,occurred_at),
            KEY version_id (version_id)
        ) {$cc};");
        foreach (array($r,$v,$s,$e) as $table) {
            if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$table))!==$table) { return; }
        }
        update_option('ino_odin_schema_version',self::SCHEMA_VERSION,false);
    }

    public static function admin_url() { return admin_url('admin.php?page=ino-governance-odin'); }

    public static function fail($message,$http=400) {
        wp_die(esc_html($message),'',array('response'=>$http));
    }
    private static function input($key,$limit=190,$paragraph=false) {
        $value=isset($_POST[$key]) ? wp_unslash($_POST[$key]) : '';
        if (!is_string($value)) { self::fail('Unexpected field value.'); }
        $value=$paragraph ? sanitize_textarea_field($value) : sanitize_text_field($value);
        return function_exists('mb_substr') ? mb_substr($value,0,$limit) : substr($value,0,$limit);
    }
    private static function number($key) {
        $s=isset($_POST[$key]) && is_scalar($_POST[$key]) ? (string)wp_unslash($_POST[$key]) : '';
        if (!preg_match('/^(0|[1-9][0-9]*)$/D',$s) || strlen($s)>15) {
            self::fail('Invalid integer for '.$key.'.');
        }
        return (int)$s;
    }
    private static function record($id,$lock=false) {
        global $wpdb;
        if (!$id) { self::fail('Record ID is required.'); }
        $table=self::table('records');
        $q=$wpdb->prepare("SELECT * FROM {$table} WHERE id=%d".($lock?' FOR UPDATE':''),$id);
        $record=$wpdb->get_row($q);
        if (!$record) { self::fail('ODIN record not found.',404); }
        return $record;
    }
    private static function version($id,$lock=false) {
        global $wpdb;
        if (!$id) { self::fail('Version ID is required.'); }
        $table=self::table('versions');
        $q=$wpdb->prepare("SELECT * FROM {$table} WHERE id=%d".($lock?' FOR UPDATE':''),$id);
        $v=$wpdb->get_row($q);
        if (!$v) { self::fail('ODIN version not found.',404); }
        return $v;
    }
    private static function audit($record_id,$version_id,$event,$note) {
        global $wpdb;
        return (bool)$wpdb->insert(self::table('events'),array(
            'record_id'=>$record_id,'version_id'=>$version_id,'event_key'=>$event,
            'actor_id'=>get_current_user_id(),'note'=>$note,'occurred_at'=>current_time('mysql')
        ));
    }
    private static function commit_or_fail($ok,$message) {
        global $wpdb;
        if (!$ok) {
            $wpdb->query('ROLLBACK');
            self::fail($message,409);
        }
        $wpdb->query('COMMIT');
    }
    private static function redirect($message) {
        wp_safe_redirect(add_query_arg('ino_odin_notice',$message,self::admin_url()));
        exit;
    }
    private static function verify_cap($operation) {
        $cap=in_array($operation,array('review_version'),true) ? 'ino_governance_review' :
             (in_array($operation,array('publish_version'),true) ? 'ino_governance_publish' : 'ino_governance_edit');
        if (!is_user_logged_in() || !current_user_can($cap)) {
            self::fail('You are not authorized for this ODIN action.',403);
        }
    }

    public static function handle() {
        $op=isset($_POST['odin_operation']) ? sanitize_key(wp_unslash($_POST['odin_operation'])) : '';
        if (!in_array($op,array('register','revise','record_evidence','review_version','publish_version','withdraw_draft'),true)) {
            self::fail('Unknown ODIN action.');
        }
        self::verify_cap($op);
        check_admin_referer('ino_odin_'.$op,'ino_odin_nonce');
        switch ($op) {
            case 'register': self::register(); break;
            case 'revise': self::revise(); break;
            case 'record_evidence': self::record_evidence(); break;
            case 'review_version': self::review(); break;
            case 'publish_version': self::publish(); break;
            case 'withdraw_draft': self::withdraw(); break;
        }
    }

    private static function draft_data($record_id,$version_no) {
        $title=self::input('title'); $summary=self::input('summary',5000,true);
        $reference=self::input('source_ref');
        $item_id=self::number('source_item_id');
        if (!$title || !$reference) { self::fail('Title and documentary source reference are required.'); }
        if ($item_id) {
            global $wpdb;
            $table=$wpdb->prefix.'ino_governance_items';
            $present=$wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE id=%d",$item_id));
            if (!$present) { self::fail('Linked Phase 1 governance item was not found.',404); }
        }
        return array(
            'record_id'=>$record_id,'version_no'=>$version_no,'title'=>$title,
            'summary'=>$summary,'source_ref'=>$reference,
            'source_item_id'=>$item_id?:null,'status'=>'draft',
            'created_by'=>get_current_user_id(),'created_at'=>current_time('mysql')
        );
    }

    private static function register() {
        global $wpdb;
        $id='INO-ODIN-'.strtoupper(str_replace('-','',wp_generate_uuid4()));
        $wpdb->query('START TRANSACTION');
        if (!$wpdb->insert(self::table('records'),array(
            'odin_id'=>$id,'created_by'=>get_current_user_id(),'created_at'=>current_time('mysql')
        ))) {
            $wpdb->query('ROLLBACK'); self::fail('Registry ID allocation failed.',409);
        }
        $record_id=(int)$wpdb->insert_id;
        $data=self::draft_data($record_id,1);
        if (!$wpdb->insert(self::table('versions'),$data)) {
            $wpdb->query('ROLLBACK');self::fail('Initial version not stored.',409);
        }
        $version_id=(int)$wpdb->insert_id;
        self::commit_or_fail(self::audit($record_id,$version_id,'registered_draft','First registry version; private pending review.'),'Registry audit failed.');
        self::redirect('ODIN ID '.$id.' created as private draft.');
    }

    private static function revise() {
        global $wpdb;
        $id=self::number('record_id');
        $wpdb->query('START TRANSACTION');
        self::record($id,true);
        $t=self::table('versions');
        $previous=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$t} WHERE record_id=%d ORDER BY version_no DESC LIMIT 1",$id));
        if (!$previous || !in_array($previous->status,array('published','superseded','withdrawn'),true)) {
            $wpdb->query('ROLLBACK');
            self::fail('Finish or withdraw the existing draft/reviewed version before creating another.',409);
        }
        $data=self::draft_data($id,(int)$previous->version_no+1);
        if (!$wpdb->insert($t,$data)) {
            $wpdb->query('ROLLBACK');self::fail('Revision could not be stored.',409);
        }
        $version_id=(int)$wpdb->insert_id;
        self::commit_or_fail(self::audit($id,$version_id,'revision_drafted','Historical versions remain immutable.'),'Revision audit failed.');
        self::redirect('ODIN version '.((int)$previous->version_no+1).' created as private draft.');
    }

    private static function record_evidence() {
        global $wpdb;
        $v=self::version(self::number('version_id'));
        if ($v->status!=='draft') { self::fail('Evidence references may be added only to draft versions.',409); }
        $ref=self::input('evidence_ref');$note=self::input('attestation',3000,true);
        if (!$ref || strlen(trim($note))<20 ||
            empty($_POST['evidence_attestation']) || (string)$_POST['evidence_attestation']!=='1') {
            self::fail('Evidence reference, descriptive statement and first-person attestation required.');
        }
        $wpdb->query('START TRANSACTION');
        $locked=self::version((int)$v->id,true);
        if ($locked->status!=='draft') { $wpdb->query('ROLLBACK');self::fail('Version already submitted for review.',409); }
        $stored=$wpdb->insert(self::table('evidence'),array(
            'version_id'=>$v->id,'witness_user_id'=>get_current_user_id(),
            'evidence_ref'=>$ref,'attestation'=>$note,'created_at'=>current_time('mysql')
        ));
        if (!$stored || !self::audit((int)$v->record_id,(int)$v->id,'evidence_reference_added',
             'Evidence reference noted by staff; no signature authenticity or cryptographic validation inferred.')) {
            $wpdb->query('ROLLBACK');self::fail('Evidence registration failed.',409);
        }
        $wpdb->query('COMMIT');
        self::redirect('Evidence reference recorded, not certified.');
    }

    private static function review() {
        global $wpdb;
        $v=self::version(self::number('version_id'));
        if ($v->status!=='draft' || (int)$v->created_by===get_current_user_id()) {
            self::fail('A different reviewer must review a draft version.',403);
        }
        $note=self::input('review_note',3000,true);
        if (strlen(trim($note))<20) { self::fail('Provide a substantive evidence-review note.'); }
        if (!$v->source_item_id) { self::fail('Review requires linkage to an actual Phase 1 governance item.'); }
        $wpdb->query('START TRANSACTION');
        $changed=$wpdb->update(self::table('versions'),array(
            'status'=>'reviewed','reviewed_by'=>get_current_user_id(),
            'review_note'=>$note,'reviewed_at'=>current_time('mysql')
        ),array('id'=>$v->id,'status'=>'draft'));
        self::commit_or_fail($changed===1 && self::audit((int)$v->record_id,(int)$v->id,'reviewed',$note),
            'Review did not commit or the version changed.');
        self::redirect('Version reviewed; remains restricted and unpublished.');
    }

    private static function public_source($item_id) {
        global $wpdb;
        $table=$wpdb->prefix.'ino_governance_items';
        $source=$wpdb->get_row($wpdb->prepare(
            "SELECT id,title,status,visibility,attachment_id,document_hash
             FROM {$table} WHERE id=%d AND status='published' AND visibility='public' LIMIT 1",
            $item_id
        ));
        if (!$source) { return false; }
        if ($source->attachment_id) {
            $file=get_attached_file((int)$source->attachment_id);
            $expected=(string)$source->document_hash;
            if (!$expected || !$file || !is_file($file) || !is_readable($file) ||
                get_post_mime_type((int)$source->attachment_id)!=='application/pdf') { return false; }
            $actual=hash_file('sha256',$file);
            if (!$actual || !hash_equals($expected,$actual)) { return false; }
        }
        return $source;
    }

    private static function publish() {
        global $wpdb;
        $v=self::version(self::number('version_id'));
        if ($v->status!=='reviewed' || !$v->reviewed_by ||
            (int)$v->reviewed_by===get_current_user_id() ||
            (int)$v->created_by===get_current_user_id()) {
            self::fail('Only an independent, authorized publisher may release a reviewed version.',403);
        }
        if (empty($_POST['public_attestation']) || (string)$_POST['public_attestation']!=='1') {
            self::fail('Publication requires an explicit source and authorization attestation.');
        }
        $source=self::public_source((int)$v->source_item_id);
        if (!$source) { self::fail('Linked source must be published, public and document-integrity checked in Phase 1.',409); }

        $wpdb->query('START TRANSACTION');
        self::record((int)$v->record_id,true);
        $t=self::table('versions');
        $fresh=self::version((int)$v->id,true);
        $newest=(int)$wpdb->get_var($wpdb->prepare("SELECT MAX(version_no) FROM {$t} WHERE record_id=%d",$v->record_id));
        if ($fresh->status!=='reviewed' || $newest!==(int)$fresh->version_no) {
            $wpdb->query('ROLLBACK');self::fail('Version is stale or no longer reviewed.',409);
        }
        $source=self::public_source((int)$fresh->source_item_id);
        if (!$source) { $wpdb->query('ROLLBACK');self::fail('Phase 1 source no longer qualifies for publication.',409); }
        $previous=$wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$t} WHERE record_id=%d AND status='published' ORDER BY version_no DESC LIMIT 1",$fresh->record_id
        ));
        if ($previous) {
            $old=$wpdb->update($t,array('status'=>'superseded'),array('id'=>(int)$previous,'status'=>'published'));
            if ($old!==1 || !self::audit((int)$fresh->record_id,(int)$previous,'superseded','Replaced by version '.$fresh->version_no)) {
                $wpdb->query('ROLLBACK');self::fail('Cannot supersede prior public version.',409);
            }
        }
        $changed=$wpdb->update($t,array(
            'status'=>'published','published_by'=>get_current_user_id(),
            'source_sha256'=>(string)$source->document_hash,'published_at'=>current_time('mysql')
        ),array('id'=>$fresh->id,'status'=>'reviewed'));
        self::commit_or_fail($changed===1 && self::audit((int)$fresh->record_id,(int)$fresh->id,
            'published','Authorized publication of sourced version '.$fresh->version_no),
            'Publication failed; previous version restored.');
        self::redirect('Public ODIN version '.$fresh->version_no.' published with verified Phase 1 source.');
    }

    private static function withdraw() {
        global $wpdb;
        $v=self::version(self::number('version_id'));
        if ($v->status!=='draft') { self::fail('Only an unpublished draft can be withdrawn.',409); }
        $note=self::input('withdrawal_note',3000,true);
        if (strlen(trim($note))<12) { self::fail('Withdrawal explanation required.'); }
        $wpdb->query('START TRANSACTION');
        $changed=$wpdb->update(self::table('versions'),array('status'=>'withdrawn'),
            array('id'=>$v->id,'status'=>'draft'));
        self::commit_or_fail($changed===1 && self::audit((int)$v->record_id,(int)$v->id,'draft_withdrawn',$note),
            'Withdrawal failed.');
        self::redirect('Unpublished draft withdrawn. Public history unchanged.');
    }

    /**
     * Query only public, published versions of explicitly public Phase 1 items.
     * The signed media URL is not a vault: access is limited to existing public PDFs.
     * File integrity is rechecked again at display time.
     */
    public static function public_versions($limit=30,$include_history=false,$odin_id='') {
        global $wpdb;
        $r=self::table('records'); $v=self::table('versions');
        $g=$wpdb->prefix.'ino_governance_items';
        $statuses=$include_history ? "'published','superseded'" : "'published'";
        $where=$odin_id ? $wpdb->prepare(' AND r.odin_id=%s',$odin_id) : '';
        $sql="SELECT r.odin_id,v.version_no,v.id AS version_id,v.title,v.summary,v.source_ref,
                  v.source_item_id,v.source_sha256,v.status,v.published_at,
                  g.title AS source_title,g.attachment_id AS public_pdf_id,g.document_hash AS current_sha256
              FROM {$r} r JOIN {$v} v ON v.record_id=r.id
              JOIN {$g} g ON g.id=v.source_item_id
              WHERE v.status IN ({$statuses}) AND g.status='published'
                    AND g.visibility='public' {$where}
              ORDER BY v.published_at DESC,v.id DESC LIMIT ".absint($limit);
        $rows=$wpdb->get_results($sql);
        $result=array();
        foreach ((array)$rows as $row) {
            $expected=(string)$row->source_sha256;
            $current=(string)$row->current_sha256;
            if (!hash_equals($expected,$current)) { continue; }
            if ($row->public_pdf_id && !self::public_source((int)$row->source_item_id)) { continue; }
            $result[]=$row;
        }
        return $result;
    }

    public static function public_id_from_request() {
        $raw=isset($_GET['odin_id']) && is_string($_GET['odin_id']) ? wp_unslash($_GET['odin_id']) : '';
        $value=strtoupper(sanitize_text_field($raw));
        return preg_match('/^INO-ODIN-[0-9A-F]{32}$/D',$value) ? $value : '';
    }

    public static function private_counts() {
        global $wpdb;
        $records=(int)$wpdb->get_var("SELECT COUNT(*) FROM ".self::table('records'));
        $t=self::table('versions');
        $rows=$wpdb->get_results("SELECT status,COUNT(*) AS total FROM {$t} GROUP BY status");
        $counts=array('registered'=>$records,'draft'=>0,'reviewed'=>0,'published'=>0,'superseded'=>0,'withdrawn'=>0);
        foreach ((array)$rows as $row) {
            if (array_key_exists($row->status,$counts)) { $counts[$row->status]=(int)$row->total; }
        }
        return $counts;
    }

    public static function admin_records($limit=75) {
        global $wpdb;
        $r=self::table('records');$v=self::table('versions');
        return $wpdb->get_results($wpdb->prepare(
            "SELECT r.id AS record_id,r.odin_id,v.* FROM {$r} r
             JOIN {$v} v ON v.record_id=r.id
             ORDER BY v.id DESC LIMIT %d",(int)$limit
        ));
    }

    public static function admin_audit($limit=35) {
        global $wpdb;
        $t=self::table('events');
        return $wpdb->get_results($wpdb->prepare(
            "SELECT record_id,version_id,event_key,actor_id,note,occurred_at
             FROM {$t} ORDER BY id DESC LIMIT %d",(int)$limit
        ));
    }

    public static function evidence_counts($version_ids) {
        global $wpdb;
        if (!$version_ids) { return array(); }
        $ids=array_values(array_unique(array_map('absint',$version_ids)));
        $placeholders=implode(',',array_fill(0,count($ids),'%d'));
        $t=self::table('evidence');
        $rows=$wpdb->get_results($wpdb->prepare(
            "SELECT version_id,COUNT(*) AS total FROM {$t}
             WHERE version_id IN ({$placeholders}) GROUP BY version_id",$ids
        ));
        $result=array();
        foreach ((array)$rows as $row) { $result[(int)$row->version_id]=(int)$row->total; }
        return $result;
    }
}
