<?php
/**
 * INO Identity, Ancestry & Peoplehood Registry.
 *
 * Private, voluntary claims and source references. It neither determines
 * civil/tribal status nor changes membership or existing identity declarations.
 */
if (!defined('ABSPATH')) { exit; }

final class INO_Platform_Peoplehood {
    const SCHEMA = '2026-10-20.1';
    private static $types = array(
        'civil_citizenship'=>'Civil citizenship (self-reported)',
        'ancestry'=>'Ancestry or lineage',
        'cultural_peoplehood'=>'Cultural peoplehood',
        'religious_identity'=>'Religious identity (voluntary)',
        'external_affiliation'=>'External nation or community affiliation (claimed)'
    );
    private static $decisions = array('record_examined','needs_information','unable_to_conclude');

    public static function init() {
        add_shortcode('ino_peoplehood_registry',array(__CLASS__,'shortcode'));
        add_shortcode('ino_peoplehood_profile',array(__CLASS__,'shortcode'));
        add_action('init',array(__CLASS__,'handle'),20);
        add_action('admin_menu',array(__CLASS__,'menu'),40);
    }

    public static function install() {
        global $wpdb;
        require_once ABSPATH.'wp-admin/includes/upgrade.php';
        $p=$wpdb->prefix;
        $cc=$wpdb->get_charset_collate();
        // Additive schema: do not modify legacy members, declarations or family links.
        dbDelta("CREATE TABLE {$p}ino_peoplehood_claims (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            claim_type VARCHAR(50) NOT NULL,
            statement TEXT NOT NULL,
            basis VARCHAR(30) NOT NULL DEFAULT 'self_declared',
            supersedes_id BIGINT UNSIGNED NULL,
            review_state VARCHAR(40) NOT NULL DEFAULT 'submitted',
            reviewed_by BIGINT UNSIGNED NULL,
            reviewed_at DATETIME NULL,
            review_note TEXT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY review_state (review_state),
            KEY supersedes_id (supersedes_id)
        ) $cc;");
        dbDelta("CREATE TABLE {$p}ino_peoplehood_evidence (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            claim_id BIGINT UNSIGNED NOT NULL,
            owner_id BIGINT UNSIGNED NOT NULL,
            source_type VARCHAR(40) NOT NULL,
            source_title VARCHAR(190) NOT NULL,
            source_reference VARCHAR(500) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY claim_id (claim_id),
            KEY owner_id (owner_id)
        ) $cc;");
        dbDelta("CREATE TABLE {$p}ino_peoplehood_consents (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            claim_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            scope VARCHAR(50) NOT NULL,
            granted TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY claim_id (claim_id),
            KEY user_id (user_id)
        ) $cc;");
        dbDelta("CREATE TABLE {$p}ino_peoplehood_events (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            claim_id BIGINT UNSIGNED NOT NULL,
            actor_id BIGINT UNSIGNED NOT NULL,
            event_type VARCHAR(50) NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY claim_id (claim_id),
            KEY actor_id (actor_id)
        ) $cc;");
        update_option('ino_peoplehood_schema_version',self::SCHEMA,false);
    }

    private static function problem($message) {
        return new WP_Error('ino_peoplehood_rejected',$message);
    }
    private static function actor_ok($user_id) {
        return is_user_logged_in() && $user_id>0 && get_current_user_id()===$user_id;
    }
    private static function owned($id,$user_id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ino_peoplehood_claims WHERE id=%d AND user_id=%d",
            $id,$user_id
        ));
    }
    private static function event($id,$actor,$action,$now) {
        global $wpdb;
        return $wpdb->insert($wpdb->prefix.'ino_peoplehood_events',array(
            'claim_id'=>$id,'actor_id'=>$actor,'event_type'=>$action,'created_at'=>$now
        ));
    }

    /**
     * Used by private POST intake and staging assertions.
     * Evidence fields are references only, NEVER a public media upload.
     */
    public static function submit($actor,$type,$statement,$basis='self_declared',$source_type='',$source_title='',$source_reference='',$supersedes=0) {
        if (!self::actor_ok((int)$actor)) { return self::problem('Account access denied.'); }
        $type=sanitize_key((string)$type);
        $statement=trim(sanitize_textarea_field((string)$statement));
        $basis=sanitize_key((string)$basis);
        $source_type=sanitize_key((string)$source_type);
        $source_title=trim(sanitize_text_field((string)$source_title));
        $source_reference=trim(sanitize_text_field((string)$source_reference));
        $supersedes=absint($supersedes);
        if (!isset(self::$types[$type]) || $statement==='' || strlen($statement)>4000 ||
            !in_array($basis,array('self_declared','family_attested'),true)) {
            return self::problem('Invalid voluntary declaration.');
        }
        if ($supersedes) {
            $prior=self::owned($supersedes,(int)$actor);
            if (!$prior || $prior->claim_type!==$type) {
                return self::problem('Amendment must reference your own record of the same type.');
            }
        }
        $has_source=($source_title!=='' || $source_reference!=='' || $source_type!=='');
        if ($has_source && ($source_title==='' ||
            !in_array($source_type,array('family_record','oral_history','historical_archive','civil_record','other'),true) ||
            strlen($source_title)>190 || strlen($source_reference)>500)) {
            return self::problem('Source type and title are required for evidence references.');
        }
        global $wpdb;
        $now=current_time('mysql',true);
        if ($wpdb->query('START TRANSACTION')===false) { return self::problem('Unable to begin record transaction.'); }
        $ok=$wpdb->insert($wpdb->prefix.'ino_peoplehood_claims',array(
            'user_id'=>(int)$actor,'claim_type'=>$type,'statement'=>$statement,
            'basis'=>$basis,'supersedes_id'=>$supersedes?:null,
            'review_state'=>'submitted','created_at'=>$now
        ));
        $id=$ok?(int)$wpdb->insert_id:0;
        if ($id && $has_source) {
            $ok=$wpdb->insert($wpdb->prefix.'ino_peoplehood_evidence',array(
                'claim_id'=>$id,'owner_id'=>(int)$actor,'source_type'=>$source_type,
                'source_title'=>$source_title,'source_reference'=>$source_reference,
                'created_at'=>$now
            ));
        }
        if ($id && $ok) { $ok=self::event($id,(int)$actor,$supersedes?'amended':'submitted',$now); }
        if (!$id || !$ok || $wpdb->query('COMMIT')===false) {
            $wpdb->query('ROLLBACK');
            return self::problem('Record could not be saved.');
        }
        return $id;
    }

    // Specific consent is recorded, but this module intentionally has NO public
    // claim renderer. Granting consent never publishes a record.
    public static function consent($actor,$claim_id,$grant) {
        $actor=(int)$actor;
        if (!self::actor_ok($actor) || !self::owned(absint($claim_id),$actor)) {
            return self::problem('Consent can only be changed by the record owner.');
        }
        global $wpdb;
        $now=current_time('mysql',true);
        if ($wpdb->query('START TRANSACTION')===false) { return self::problem('Unable to begin consent transaction.'); }
        $ok=$wpdb->insert($wpdb->prefix.'ino_peoplehood_consents',array(
            'claim_id'=>absint($claim_id),'user_id'=>$actor,
            'scope'=>'future_public_summary_only','granted'=>$grant?1:0,'created_at'=>$now
        ));
        if ($ok) { $ok=self::event(absint($claim_id),$actor,$grant?'consent_granted':'consent_withdrawn',$now); }
        if (!$ok || $wpdb->query('COMMIT')===false) {
            $wpdb->query('ROLLBACK');
            return self::problem('Consent could not be recorded.');
        }
        return true;
    }

    // Internal review of the RECORD, not certification of the underlying
    // ancestry, identity, external enrollment, or civil status.
    public static function review($claim_id,$decision,$note) {
        if (!is_user_logged_in() || !current_user_can('manage_options')) {
            return self::problem('Administrator review permission required.');
        }
        $decision=sanitize_key((string)$decision);
        $note=trim(sanitize_textarea_field((string)$note));
        if (!in_array($decision,self::$decisions,true) || $note==='' || strlen($note)>3000) {
            return self::problem('Review decision and rationale required.');
        }
        global $wpdb;
        $id=absint($claim_id);
        $claim=$wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ino_peoplehood_claims WHERE id=%d",$id
        ));
        if (!$claim || (int)$claim->user_id===get_current_user_id() ||
            $claim->review_state!=='submitted') {
            return self::problem('Claim unavailable, already reviewed, or self-review prohibited.');
        }
        if ($decision==='record_examined') {
            $count=(int)$wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}ino_peoplehood_evidence WHERE claim_id=%d",$id
            ));
            if ($count<1) { return self::problem('Document examination requires a recorded source reference.'); }
        }
        $now=current_time('mysql',true);
        if ($wpdb->query('START TRANSACTION')===false) { return self::problem('Unable to begin review transaction.'); }
        $ok=$wpdb->update($wpdb->prefix.'ino_peoplehood_claims',array(
            'review_state'=>$decision,'review_note'=>$note,
            'reviewed_by'=>get_current_user_id(),'reviewed_at'=>$now
        ),array('id'=>$id,'review_state'=>'submitted'));
        if ($ok===1) { $ok=self::event($id,get_current_user_id(),'review_'.$decision,$now); }
        if (!$ok || $wpdb->query('COMMIT')===false) {
            $wpdb->query('ROLLBACK');
            return self::problem('Review was not recorded.');
        }
        return true;
    }

    public static function handle() {
        if (empty($_POST['ino_peoplehood_action'])) { return; }
        $action=sanitize_key(wp_unslash($_POST['ino_peoplehood_action']));
        $nonce=isset($_POST['ino_peoplehood_nonce']) && is_string($_POST['ino_peoplehood_nonce'])
            ? sanitize_text_field(wp_unslash($_POST['ino_peoplehood_nonce'])) : '';
        if (!is_user_logged_in() || !wp_verify_nonce($nonce,'ino_peoplehood_action')) {
            wp_die('Identity registry authorization failed.',403);
        }
        $actor=get_current_user_id();
        if ($action==='submit') {
            $result=self::submit($actor,
                wp_unslash($_POST['claim_type']??''),
                wp_unslash($_POST['statement']??''),
                wp_unslash($_POST['basis']??'self_declared'),
                wp_unslash($_POST['source_type']??''),
                wp_unslash($_POST['source_title']??''),
                wp_unslash($_POST['source_reference']??''),
                absint($_POST['supersedes_id']??0)
            );
        } elseif ($action==='consent') {
            $result=self::consent($actor,absint($_POST['claim_id']??0),
                isset($_POST['grant']) && $_POST['grant']==='1');
        } elseif ($action==='review') {
            $result=self::review(absint($_POST['claim_id']??0),
                wp_unslash($_POST['decision']??''),wp_unslash($_POST['review_note']??''));
        } else {
            wp_die('Unknown registry action.',400);
        }
        $target=wp_get_referer();
        if (!$target) { $target=home_url('/peoplehood-registry/'); }
        wp_safe_redirect(add_query_arg('ino_iap_result',is_wp_error($result)?'rejected':'saved',
            remove_query_arg('ino_iap_result',$target)));
        exit;
    }

    private static function form_start($action) {
        return '<form method="post" class="ino-form">'
            .wp_nonce_field('ino_peoplehood_action','ino_peoplehood_nonce',true,false)
            .'<input type="hidden" name="ino_peoplehood_action" value="'.esc_attr($action).'">';
    }

    public static function shortcode() {
        if (!is_user_logged_in()) {
            return '<section class="ino-shell"><h2>Private Identity, Ancestry &amp; Peoplehood Registry</h2><p>Log in to review or submit your own voluntary identity records.</p></section>';
        }
        global $wpdb;
        $uid=get_current_user_id();
        $rows=$wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ino_peoplehood_claims WHERE user_id=%d ORDER BY id DESC LIMIT 100",$uid
        ));
        $html='<section class="ino-shell"><div class="ino-heading"><span>Private member records</span><h1>Identity, Ancestry &amp; Peoplehood Registry</h1></div>'
            .'<p>Record voluntary identity descriptions separately from INO membership. These statements do not determine government citizenship, ancestry, external tribal enrollment, or recognition. New records remain private, including after consent is recorded.</p>';
        if (isset($_GET['ino_iap_result'])) {
            $html.='<div class="ino-notice" role="status">'.(
                $_GET['ino_iap_result']==='saved'?'Registry action recorded.':'Registry action not accepted.'
            ).'</div>';
        }
        $html.='<h2>Your private statements</h2>';
        foreach ((array)$rows as $row) {
            $consent=$wpdb->get_var($wpdb->prepare(
                "SELECT granted FROM {$wpdb->prefix}ino_peoplehood_consents WHERE claim_id=%d AND user_id=%d ORDER BY id DESC LIMIT 1",
                $row->id,$uid
            ));
            $html.='<article class="ino-card-public"><h3>#'.esc_html($row->id).' · '
                .esc_html(self::$types[$row->claim_type]??'Identity statement').'</h3><p>'
                .esc_html($row->statement).'</p><p>Submitted basis: '.esc_html($row->basis)
                .' · Administrative review: '.esc_html($row->review_state)
                .' · Public-summary consent on file: '.((string)$consent==='1'?'Yes':'No')
                .' (no publication enabled)</p>';
            $html.=self::form_start('consent')
                .'<input type="hidden" name="claim_id" value="'.esc_attr($row->id).'">'
                .'<input type="hidden" name="grant" value="'.((string)$consent==='1'?'0':'1').'">'
                .'<button type="submit" class="ino-btn ino-btn-navy">'
                .((string)$consent==='1'?'Withdraw future public-summary consent':'Consent to possible future summary only')
                .'</button></form></article>';
        }
        if (!$rows) { $html.='<p>No statements have been submitted.</p>'; }
        $html.='<h2>Submit a statement or amendment</h2>'.self::form_start('submit')
            .'<label>Identity dimension<select name="claim_type" required>';
        foreach (self::$types as $key=>$label) {
            $html.='<option value="'.esc_attr($key).'">'.esc_html($label).'</option>';
        }
        $html.='</select></label><label>Voluntary statement<textarea name="statement" rows="5" maxlength="4000" required></textarea></label>'
            .'<label>Basis<select name="basis"><option value="self_declared">Self-declared</option><option value="family_attested">Family-attested</option></select></label>'
            .'<label>Amend an existing statement (optional record number)<input type="number" min="1" name="supersedes_id"></label>'
            .'<p>Optional supporting source reference. Providing a reference does not establish that the source or claim has been verified.</p>'
            .'<label>Source type<select name="source_type"><option value="">None</option><option value="family_record">Family record</option><option value="oral_history">Oral history</option><option value="historical_archive">Historical archive</option><option value="civil_record">Civil record</option><option value="other">Other source</option></select></label>'
            .'<label>Source title<input name="source_title" maxlength="190"></label>'
            .'<label>Private source citation/reference<input name="source_reference" maxlength="500"></label>'
            .'<button type="submit" class="ino-btn ino-btn-gold">Save private statement</button></form>'
            .'<div class="ino-notice">Review means that INO examined the submitted record within a defined scope. It is not an external determination of genealogy, tribal enrollment, or civil citizenship. Existing membership status remains in the INO Member Registry.</div></section>';
        return $html;
    }

    public static function menu() {
        add_submenu_page('ino-platform','Peoplehood Record Review','Peoplehood Review',
            'manage_options','ino-peoplehood-review',array(__CLASS__,'admin_page'));
    }
    public static function admin_page() {
        if (!current_user_can('manage_options')) {
            wp_die('Administrator authorization required.',403);
        }
        global $wpdb;
        $rows=$wpdb->get_results("SELECT * FROM {$wpdb->prefix}ino_peoplehood_claims
            WHERE review_state='submitted' ORDER BY id ASC LIMIT 50");
        echo '<div class="wrap"><h1>Private Peoplehood Record Review</h1>'
            .'<p>Source and claim review only. Never confers external status or INO membership. Only administrators may access this private review queue; the submitter cannot review their own record.</p>';
        foreach ((array)$rows as $row) {
            $evidence=$wpdb->get_results($wpdb->prepare(
                "SELECT source_type,source_title,source_reference FROM {$wpdb->prefix}ino_peoplehood_evidence WHERE claim_id=%d",$row->id
            ));
            echo '<div class="postbox" style="padding:16px"><h2>Record #'.esc_html($row->id).'</h2>'
                .'<p>Account #'.esc_html($row->user_id).' · Type: '
                .esc_html($row->claim_type).' · Basis: '.esc_html($row->basis).'</p><p>'
                .nl2br(esc_html($row->statement)).'</p>';
            foreach ((array)$evidence as $source) {
                echo '<p>Submitted source (not authenticated): '.esc_html($source->source_type)
                    .' · '.esc_html($source->source_title).' · '.esc_html($source->source_reference).'</p>';
            }
            if ((int)$row->user_id!==get_current_user_id()) {
                echo self::form_start('review')
                    .'<input type="hidden" name="claim_id" value="'.esc_attr($row->id).'">'
                    .'<label>Decision<select name="decision"><option value="needs_information">Needs information</option>'
                    .'<option value="record_examined">Record examined (source reference required)</option>'
                    .'<option value="unable_to_conclude">Unable to conclude</option></select></label>'
                    .'<label>Scope and rationale<textarea name="review_note" rows="3" maxlength="3000" required></textarea></label>'
                    .'<button class="button button-primary" type="submit">Record review</button></form>';
            } else {
                echo '<p>Independent reviewer required.</p>';
            }
            echo '</div>';
        }
        if (!$rows) { echo '<p>No submitted records awaiting review.</p>'; }
        echo '</div>';
    }
}
