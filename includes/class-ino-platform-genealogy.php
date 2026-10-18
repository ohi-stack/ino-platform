<?php
/**
 * INO Genealogy Workspace — independent family records, not WordPress accounts.
 * Public sharing is an explicit, revocable, deceased-only projection.
 */
if (!defined('ABSPATH')) { exit; }

final class INO_Platform_Genealogy {
    const SCHEMA = '2026-10-18.1';
    private static $types = array('parent_of'=>'Parent of','spouse_of'=>'Spouse / partner of','sibling_of'=>'Sibling of','related_to'=>'Other family connection');

    public static function init() {
        add_shortcode('ino_family_tree', array(__CLASS__, 'render'));
        add_shortcode('ino_genealogy_public', array(__CLASS__, 'render'));
        add_action('init', array(__CLASS__, 'handle'), 20);
        add_action('wp_enqueue_scripts', array(__CLASS__, 'assets'));
        add_action('admin_menu', array(__CLASS__, 'admin_menu'), 30);
    }

    public static function install() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $cc = $wpdb->get_charset_collate();
        $p = $wpdb->prefix;
        dbDelta("CREATE TABLE {$p}ino_genealogy_people (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            owner_id BIGINT UNSIGNED NOT NULL,
            full_name VARCHAR(190) NOT NULL,
            born VARCHAR(80) NOT NULL DEFAULT '',
            died VARCHAR(80) NOT NULL DEFAULT '',
            place VARCHAR(190) NOT NULL DEFAULT '',
            life_status VARCHAR(20) NOT NULL DEFAULT 'unknown',
            visibility VARCHAR(20) NOT NULL DEFAULT 'private',
            evidence_notes TEXT NULL,
            verification VARCHAR(20) NOT NULL DEFAULT 'unverified',
            reviewed_by BIGINT UNSIGNED NULL,
            reviewed_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY owner_id (owner_id),
            KEY visibility (visibility)
        ) $cc;");
        dbDelta("CREATE TABLE {$p}ino_genealogy_links (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            owner_id BIGINT UNSIGNED NOT NULL,
            person_a BIGINT UNSIGNED NOT NULL,
            person_b BIGINT UNSIGNED NOT NULL,
            relation VARCHAR(30) NOT NULL,
            visibility VARCHAR(20) NOT NULL DEFAULT 'private',
            evidence_notes TEXT NULL,
            verification VARCHAR(20) NOT NULL DEFAULT 'unverified',
            reviewed_by BIGINT UNSIGNED NULL,
            reviewed_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY owner_id (owner_id),
            KEY person_a (person_a),
            KEY person_b (person_b)
        ) $cc;");
        update_option('ino_genealogy_schema', self::SCHEMA, false);
    }

    private static function people($owner) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}ino_genealogy_people WHERE owner_id=%d ORDER BY full_name ASC, id ASC LIMIT 500", $owner));
    }
    private static function links($owner) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}ino_genealogy_links WHERE owner_id=%d ORDER BY created_at DESC, id DESC LIMIT 1000", $owner));
    }
    private static function owned_person($id,$owner) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}ino_genealogy_people WHERE id=%d AND owner_id=%d",$id,$owner));
    }
    private static function owned_link($id,$owner) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}ino_genealogy_links WHERE id=%d AND owner_id=%d",$id,$owner));
    }
    private static function public_person($person) {
        return $person && $person->life_status === 'deceased' && $person->visibility === 'public';
    }
    private static function input($key,$max=190) {
        return isset($_POST[$key]) && is_string($_POST[$key]) ? substr(sanitize_text_field(wp_unslash($_POST[$key])),0,$max) : '';
    }
    private static function notes() {
        return isset($_POST['evidence_notes']) && is_string($_POST['evidence_notes'])
            ? substr(sanitize_textarea_field(wp_unslash($_POST['evidence_notes'])),0,4000) : '';
    }
    private static function go($message) {
        $back=wp_get_referer() ?: home_url('/family-tree/');
        wp_safe_redirect(add_query_arg('ino_g_message',$message,remove_query_arg(array('ino_g_message','edit_person'),$back)));
        exit;
    }

    public static function handle() {
        if (empty($_POST['ino_genealogy_action'])) { return; }
        if (!is_user_logged_in()) { wp_die(esc_html__('Log in to edit family records.','ino-platform'),403); }
        if (!isset($_POST['ino_genealogy_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['ino_genealogy_nonce'])),'ino_genealogy_action')) {
            wp_die(esc_html__('Genealogy request verification failed.','ino-platform'),403);
        }
        global $wpdb;
        $owner=get_current_user_id();
        $action=sanitize_key(wp_unslash($_POST['ino_genealogy_action']));
        $p=$wpdb->prefix;
        $now=current_time('mysql');
        if ($action==='save_person') {
            $name=self::input('full_name');
            if (!$name) { self::go('missing_name'); }
            $id=absint($_POST['person_id'] ?? 0);
            $existing=$id ? self::owned_person($id,$owner) : null;
            if ($id && !$existing) { wp_die('Person not available.',403); }
            $life=self::input('life_status',20);
            if (!in_array($life,array('living','deceased','unknown'),true)) { $life='unknown'; }
            $visibility=self::input('visibility',20)==='public' && $life==='deceased' ? 'public' : 'private';
            $data=array('full_name'=>$name,'born'=>self::input('born',80),'died'=>self::input('died',80),
                'place'=>self::input('place'),'life_status'=>$life,'visibility'=>$visibility,
                'evidence_notes'=>self::notes(),'verification'=>'unverified','reviewed_by'=>null,
                'reviewed_at'=>null,'updated_at'=>$now);
            if ($existing) {
                // Changing a person's facts invalidates any previous institutional review.
                $wpdb->update("{$p}ino_genealogy_people",$data,array('id'=>$id,'owner_id'=>$owner));
                $wpdb->query($wpdb->prepare("UPDATE {$p}ino_genealogy_links SET verification='unverified',reviewed_by=NULL,reviewed_at=NULL,visibility='private' WHERE owner_id=%d AND (person_a=%d OR person_b=%d)",$owner,$id,$id));
            } else {
                $data['owner_id']=$owner; $data['created_at']=$now;
                $wpdb->insert("{$p}ino_genealogy_people",$data);
            }
            self::go('person_saved');
        }
        if ($action==='delete_person') {
            $id=absint($_POST['person_id'] ?? 0);
            if (!self::owned_person($id,$owner)) { wp_die('Person not available.',403); }
            $wpdb->query($wpdb->prepare("DELETE FROM {$p}ino_genealogy_links WHERE owner_id=%d AND (person_a=%d OR person_b=%d)",$owner,$id,$id));
            $wpdb->delete("{$p}ino_genealogy_people",array('id'=>$id,'owner_id'=>$owner));
            self::go('person_deleted');
        }
        if ($action==='save_link') {
            $a=absint($_POST['person_a'] ?? 0); $b=absint($_POST['person_b'] ?? 0);
            $rel=self::input('relation',30);
            if (!$a || !$b || $a===$b || !isset(self::$types[$rel])) { self::go('invalid_link'); }
            $person_a=self::owned_person($a,$owner); $person_b=self::owned_person($b,$owner);
            if (!$person_a || !$person_b) { wp_die('Person not available.',403); }
            $existing=$wpdb->get_var($wpdb->prepare("SELECT id FROM {$p}ino_genealogy_links WHERE owner_id=%d AND relation=%s AND ((person_a=%d AND person_b=%d) OR (person_a=%d AND person_b=%d AND relation IN ('spouse_of','sibling_of','related_to'))) LIMIT 1",$owner,$rel,$a,$b,$b,$a));
            if ($existing) { self::go('duplicate'); }
            $public=self::input('visibility',20)==='public' && self::public_person($person_a) && self::public_person($person_b);
            $wpdb->insert("{$p}ino_genealogy_links",array('owner_id'=>$owner,'person_a'=>$a,'person_b'=>$b,'relation'=>$rel,
                'visibility'=>$public?'public':'private','evidence_notes'=>self::notes(),'verification'=>'unverified',
                'created_at'=>$now,'updated_at'=>$now));
            self::go('link_saved');
        }
        if ($action==='toggle_link') {
            $id=absint($_POST['link_id'] ?? 0); $link=self::owned_link($id,$owner);
            if (!$link) { wp_die('Relationship not available.',403); }
            $a=self::owned_person((int)$link->person_a,$owner);$b=self::owned_person((int)$link->person_b,$owner);
            $public=self::input('visibility',20)==='public' && self::public_person($a) && self::public_person($b);
            $wpdb->update("{$p}ino_genealogy_links",array('visibility'=>$public?'public':'private','updated_at'=>$now),array('id'=>$id,'owner_id'=>$owner));
            self::go('visibility_saved');
        }
        if ($action==='delete_link') {
            $id=absint($_POST['link_id'] ?? 0);
            if (!self::owned_link($id,$owner)) { wp_die('Relationship not available.',403); }
            $wpdb->delete("{$p}ino_genealogy_links",array('id'=>$id,'owner_id'=>$owner));
            self::go('link_deleted');
        }
        if ($action==='share') {
            $enable=self::input('share',20)==='enable';
            if ($enable && !get_user_meta($owner,'ino_genealogy_share_token',true)) {
                update_user_meta($owner,'ino_genealogy_share_token',wp_generate_password(40,false,false));
            }
            if (!$enable) { delete_user_meta($owner,'ino_genealogy_share_token'); }
            self::go('share_saved');
        }
        if ($action==='review') {
            if (!current_user_can('manage_options')) { wp_die('Permission denied.',403); }
            $table=self::input('record_type',20)==='link' ? "{$p}ino_genealogy_links" : "{$p}ino_genealogy_people";
            $id=absint($_POST['record_id'] ?? 0);
            $record=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id=%d",$id));
            if (!$record) { wp_die('Review record missing.',404); }
            $decision=self::input('decision',20);
            if (!in_array($decision,array('verified','unverified'),true)) { wp_die('Invalid decision.',400); }
            // Require a traceable source note; an internal review is not external proof of ancestry.
            if ($decision==='verified' && !$record->evidence_notes) { self::go('source_required'); }
            $wpdb->update($table,array('verification'=>$decision,'reviewed_by'=>$owner,'reviewed_at'=>$now),array('id'=>$id));
            self::go('review_saved');
        }
        wp_die('Unknown genealogy action.',400);
    }

    public static function assets() {
        wp_enqueue_style('ino-genealogy-workspace',INO_PLATFORM_URL.'assets/css/ino-genealogy.css',array(),INO_PLATFORM_VERSION);
    }
    private static function form_open($action,$class='') {
        return '<form method="post" class="ino-g-form '.esc_attr($class).'">'
            .wp_nonce_field('ino_genealogy_action','ino_genealogy_nonce',true,false)
            .'<input type="hidden" name="ino_genealogy_action" value="'.esc_attr($action).'">';
    }
    private static function msg() {
        $code=isset($_GET['ino_g_message']) ? sanitize_key(wp_unslash($_GET['ino_g_message'])) : '';
        $messages=array('person_saved'=>'Person saved privately or with your selected deceased-only visibility.',
            'person_deleted'=>'Person and associated connections deleted.','missing_name'=>'Enter a name to save a person.',
            'link_saved'=>'Connection saved as unverified.','link_deleted'=>'Connection deleted.',
            'invalid_link'=>'Choose two different people and a valid connection.','duplicate'=>'That connection already exists.',
            'visibility_saved'=>'Connection visibility updated.','share_saved'=>'Public link preference updated.',
            'source_required'=>'A source citation is required for review.','review_saved'=>'Reviewer decision recorded.');
        return isset($messages[$code]) ? '<p class="ino-g-alert" role="status">'.esc_html($messages[$code]).'</p>' : '';
    }
    public static function render($atts=array()) {
        if (isset($_GET['ino_share']) && is_string($_GET['ino_share'])) {
            return self::public_view(sanitize_text_field(wp_unslash($_GET['ino_share'])));
        }
        if (!is_user_logged_in()) {
            return '<section class="ino-g"><div class="ino-g-hero"><p class="ino-g-eyebrow">Genealogy • Private workspace</p><h1>Start your family tree</h1><p>Sign in to create people, document family connections, and control what you share.</p><a href="'.esc_url(wp_login_url(get_permalink())).'" class="ino-g-button">Log in</a></div></section>';
        }
        $atts=shortcode_atts(array('user_id'=>0),$atts,'ino_family_tree');
        $owner=absint($atts['user_id']) ?: get_current_user_id();
        if ($owner !== get_current_user_id() && !current_user_can('manage_options')) {
            return '<p class="ino-notice">Other members’ family trees are private.</p>';
        }
        $people=self::people($owner);$links=self::links($owner);
        $by_id=array();$verified=0;$public=0;
        foreach ($people as $person) { $by_id[(int)$person->id]=$person; if ($person->verification==='verified') {$verified++;} if(self::public_person($person)) {$public++;} }
        $token=get_user_meta($owner,'ino_genealogy_share_token',true);
        $share=$token ? add_query_arg('ino_share',$token,home_url('/family-tree/')) : '';
        $html='<section class="ino-g"><div class="ino-g-hero"><p class="ino-g-eyebrow">INO • Identity & Heritage • Genealogy</p><h1>My Family Tree</h1><p>Build connections across generations. Save private research, document sources, and choose which deceased ancestors may appear in a shared view.</p><div class="ino-g-actions"><a href="#ino-add-person" class="ino-g-button">+ Add a Person</a><a href="#ino-add-connection" class="ino-g-button ino-g-outline">Connect Relatives</a></div></div>'.self::msg();
        $html.='<div class="ino-g-stats"><div><strong>'.count($people).'</strong><span>People</span></div><div><strong>'.count($links).'</strong><span>Connections</span></div><div><strong>'.$verified.'</strong><span>Reviewed people</span></div><div><strong>'.$public.'</strong><span>Public deceased records</span></div></div>';
        $html.='<nav class="ino-g-nav" aria-label="Genealogy sections"><a href="#ino-people">People</a><a href="#ino-connections">Connections</a><a href="#ino-add-person">Add person</a><a href="#ino-add-connection">Add connection</a><a href="#ino-publish">Privacy & sharing</a></nav>';
        $html.='<div class="ino-g-panel" id="ino-people"><div class="ino-g-section-head"><div><p class="ino-g-eyebrow">Your research</p><h2>People in your tree</h2></div><span>'.count($people).' recorded</span></div>';
        if (!$people) {
            $html.='<div class="ino-g-empty"><span aria-hidden="true">✧</span><h3>Every family story starts with a name.</h3><p>Add yourself, a parent, a grandparent, or an ancestor. You do not need proof to save a private research lead.</p><a href="#ino-add-person" class="ino-g-button">Add your first person</a></div>';
        } else {
            $html.='<div class="ino-g-people">';
            foreach ($people as $person) {
                $html.='<article class="ino-g-person"><span class="ino-g-avatar" aria-hidden="true">'.esc_html(substr($person->full_name,0,1)).'</span><div><h3>'.esc_html($person->full_name).'</h3><p>'.esc_html(trim($person->born.' — '.$person->died,' —') ?: 'Dates not added').'</p><p>'.esc_html($person->place).'</p><div class="ino-g-tags"><span>'.esc_html(ucfirst($person->verification)).'</span><span>'.esc_html(ucfirst($person->visibility)).'</span><span>'.esc_html(ucfirst($person->life_status)).'</span></div><a class="ino-g-small-link" href="'.esc_url(add_query_arg('edit_person',(int)$person->id,remove_query_arg('ino_g_message'))).'#ino-add-person">Edit person</a></div></article>';
            }
            $html.='</div>';
        }
        $html.='</div><div class="ino-g-panel" id="ino-connections"><div class="ino-g-section-head"><div><p class="ino-g-eyebrow">Relationships & evidence</p><h2>Family connections</h2></div><span>'.count($links).' recorded</span></div>';
        if (!$links) { $html.='<div class="ino-g-empty"><h3>No connections recorded yet</h3><p>People you add can be connected as parent, sibling, spouse/partner, or another family relationship. Unverified links remain visible in your private research.</p></div>'; }
        foreach ($links as $link) {
            if (!isset($by_id[(int)$link->person_a],$by_id[(int)$link->person_b])) { continue; }
            $a=$by_id[(int)$link->person_a];$b=$by_id[(int)$link->person_b];
            $html.='<div class="ino-g-link"><div><strong>'.esc_html($a->full_name).'</strong><span class="ino-g-relation">'.esc_html(self::$types[$link->relation] ?? $link->relation).'</span><strong>'.esc_html($b->full_name).'</strong></div><p class="ino-g-tags"><span>'.esc_html(ucfirst($link->verification)).'</span><span>'.esc_html(ucfirst($link->visibility)).'</span></p>';
            if ($link->evidence_notes) { $html.='<p class="ino-g-source"><strong>Research note:</strong> '.esc_html($link->evidence_notes).'</p>'; }
            $eligible=self::public_person($a) && self::public_person($b);
            $html.=self::form_open('toggle_link','ino-g-inline').'<input type="hidden" name="link_id" value="'.(int)$link->id.'"><input type="hidden" name="visibility" value="'.($link->visibility==='public'?'private':'public').'"><button type="submit" class="ino-g-text-button">'.($link->visibility==='public'?'Make private':($eligible?'Share publicly':'Public sharing requires two public deceased records')).'</button></form>';
            $html.=self::form_open('delete_link','ino-g-inline').'<input type="hidden" name="link_id" value="'.(int)$link->id.'"><button type="submit" class="ino-g-text-button">Delete connection</button></form></div>';
        }
        $html.='</div>';
        // Preserve existing approved member relationships without exposing pending
        // requests or auto-migrating member profiles into ancestor records.
        global $wpdb;
        $legacy=$wpdb->get_results($wpdb->prepare(
            "SELECT person_a,person_b,relationship_type FROM {$wpdb->prefix}ino_family_relationships WHERE (person_a=%d OR person_b=%d) AND status='approved' ORDER BY created_at DESC LIMIT 100",
            $owner,$owner
        ));
        if ($legacy) {
            $html.='<div class="ino-g-panel"><h2>Existing approved INO member relationships</h2><p class="ino-g-muted">These are legacy member connections. They remain private and are not part of your publicly shared ancestor tree.</p>';
            foreach ($legacy as $item) {
                $other=(int)$item->person_a===$owner ? (int)$item->person_b : (int)$item->person_a;
                $relative=get_userdata($other);
                if ($relative) {
                    $html.='<p><strong>'.esc_html($relative->display_name).'</strong> — '.esc_html(ucwords(str_replace('_',' ',$item->relationship_type))).' (approved INO relationship)</p>';
                }
            }
            $html.='</div>';
        }
        $edit=isset($_GET['edit_person']) ? self::owned_person(absint($_GET['edit_person']),$owner) : null;
        $html.='<div class="ino-g-columns"><div class="ino-g-panel" id="ino-add-person"><h2>'.($edit?'Edit person':'Add a person').'</h2><p class="ino-g-muted">Living and unknown-status people are always kept private. Public visibility is only offered for deceased records.</p>';
        $html.=self::form_open('save_person').'<input type="hidden" name="person_id" value="'.(int)($edit->id??0).'"><label>Full name <input name="full_name" maxlength="190" required value="'.esc_attr($edit->full_name??'').'" placeholder="Name as recorded"></label><div class="ino-g-two"><label>Born (exact or approximate)<input name="born" maxlength="80" value="'.esc_attr($edit->born??'').'" placeholder="c. 1890"></label><label>Died (if known)<input name="died" maxlength="80" value="'.esc_attr($edit->died??'').'" placeholder="1955"></label></div><label>Place or locality<input name="place" maxlength="190" value="'.esc_attr($edit->place??'').'" placeholder="Stamford, Connecticut"></label><div class="ino-g-two"><label>Life status<select name="life_status"><option value="unknown" '.selected($edit->life_status??'unknown','unknown',false).'>Unknown</option>';
        foreach (array('living'=>'Living','deceased'=>'Deceased') as $v=>$label) { $html.='<option value="'.esc_attr($v).'" '.selected($edit->life_status??'', $v,false).'>'.esc_html($label).'</option>'; }
        $html.='</select></label><label>Visibility<select name="visibility"><option value="private">Private</option><option value="public" '.selected($edit->visibility??'','public',false).'>Public, deceased only</option></select></label></div><label>Evidence / citation / family account<textarea name="evidence_notes" rows="3" placeholder="Record title, archive, date, reference, or family testimony">'.esc_textarea($edit->evidence_notes??'').'</textarea></label><button class="ino-g-button" type="submit">'.($edit?'Save changes':'Save person').'</button></form>';
        if($edit){$html.=self::form_open('delete_person').'<input type="hidden" name="person_id" value="'.(int)$edit->id.'"><button class="ino-g-text-button" type="submit">Delete this person and their connections</button></form>';}
        $html.='</div><div class="ino-g-panel" id="ino-add-connection"><h2>Connect two people</h2><p class="ino-g-muted">Record the connection as a research statement first; only authorized review can change its evidence status.</p>';
        if(count($people)<2){$html.='<div class="ino-g-empty">Add at least two people before connecting them.</div>';}
        else {$html.=self::form_open('save_link');foreach(array('person_a'=>'First person','person_b'=>'Second person') as $name=>$label){$html.='<label>'.esc_html($label).'<select name="'.esc_attr($name).'" required><option value="">Choose person</option>';foreach($people as $person){$html.='<option value="'.(int)$person->id.'">'.esc_html($person->full_name).'</option>';}$html.='</select></label>';}
            $html.='<label>Relationship (first person → second)<select name="relation">';foreach(self::$types as $value=>$label){$html.='<option value="'.esc_attr($value).'">'.esc_html($label).'</option>';}$html.='</select></label><label>Visibility<select name="visibility"><option value="private">Private research</option><option value="public">Public if both are shared deceased records</option></select></label><label>Supporting source or oral-history note<textarea name="evidence_notes" rows="3"></textarea></label><button class="ino-g-button" type="submit">Save unverified connection</button></form>';}
        $html.='</div></div>';
        $html.='<div class="ino-g-panel" id="ino-publish"><div class="ino-g-section-head"><div><p class="ino-g-eyebrow">You control what leaves your tree</p><h2>Public sharing</h2></div><span>Opt-in only</span></div><p>Private by default. A public link shows only records you marked public and deceased, and only their public connections. Unverified claims are labeled as such. It never reveals living, unknown-status, or private records, source notes, or membership data.</p>';
        if($share){$html.='<p><strong>Shareable link:</strong> <a href="'.esc_url($share).'">'.esc_html($share).'</a></p>';}
        $html.=self::form_open('share').'<input type="hidden" name="share" value="'.($share?'disable':'enable').'"><button class="ino-g-button" type="submit">'.($share?'Revoke public link':'Enable shareable public view').'</button></form></div><p class="ino-g-disclosure">Evidence labels describe review of submitted sources, not external recognition, enrollment, nationality, or proof of Indigenous affiliation. Research records may be incomplete or disputed.</p></section>';
        return $html;
    }
    private static function public_view($token) {
        if(!preg_match('/^[A-Za-z0-9]{40}$/D',$token)) {return '<p class="ino-notice">This family-tree link is not available.</p>';}
        $users=get_users(array('number'=>1,'fields'=>'ID','meta_key'=>'ino_genealogy_share_token','meta_value'=>$token));
        if(!$users){return '<p class="ino-notice">This family-tree link is no longer available.</p>';}
        $owner=(int)$users[0];$people=self::people($owner);$links=self::links($owner);$published=array();
        foreach($people as $person){if(self::public_person($person)){$published[(int)$person->id]=$person;}}
        $html='<section class="ino-g"><div class="ino-g-hero"><p class="ino-g-eyebrow">Shared genealogy • Read-only</p><h1>Family history</h1><p>Only explicitly published deceased persons and connections are shown. All unverified information is a research claim, not a verified genealogical conclusion.</p></div><div class="ino-g-panel"><h2>Published ancestors</h2>';
        if(!$published){$html.='<p>No records have been selected for sharing.</p>';}
        foreach($published as $person){$html.='<div class="ino-g-public-person"><strong>'.esc_html($person->full_name).'</strong> <span>'.esc_html(trim($person->born.' — '.$person->died,' —')).'</span><p>'.esc_html($person->place).' · '.esc_html($person->verification==='verified'?'Source reviewed by INO':'Unverified family research').'</p></div>';}
        $html.='</div><div class="ino-g-panel"><h2>Published connections</h2>';
        foreach($links as $link){
            if($link->visibility!=='public' || !isset($published[(int)$link->person_a],$published[(int)$link->person_b])) {continue;}
            $html.='<p><strong>'.esc_html($published[(int)$link->person_a]->full_name).'</strong> — '.esc_html(self::$types[$link->relation]??$link->relation).' — <strong>'.esc_html($published[(int)$link->person_b]->full_name).'</strong> ('.esc_html($link->verification==='verified'?'Source reviewed by INO':'Unverified').')</p>';
        }
        return $html.'</div><p class="ino-g-disclosure">Shared genealogy does not establish membership or recognized tribal affiliation. Please contact the researcher with any corrections.</p></section>';
    }

    public static function admin_menu() {
        add_submenu_page('ino-platform','Genealogy Evidence Review','Genealogy Review','manage_options','ino-genealogy-review',array(__CLASS__,'review_page'));
    }
    public static function review_page() {
        if(!current_user_can('manage_options')) {wp_die('Access denied.',403);}
        global $wpdb;$p=$wpdb->prefix;
        $people=$wpdb->get_results("SELECT id,owner_id,full_name,evidence_notes,verification FROM {$p}ino_genealogy_people ORDER BY id DESC LIMIT 100");
        $links=$wpdb->get_results("SELECT id,owner_id,person_a,person_b,relation,evidence_notes,verification FROM {$p}ino_genealogy_links ORDER BY id DESC LIMIT 100");
        echo '<div class="wrap"><h1>Genealogy Evidence Review</h1><p>Internal review only. Reviewing source notes does not certify external lineage, tribal enrollment, or legal status. No status is automatically verified.</p>'.self::msg();
        foreach(array('person'=>$people,'link'=>$links) as $kind=>$records){
            echo '<h2>'.esc_html(ucfirst($kind)).' records</h2><table class="widefat striped"><thead><tr><th>Record</th><th>Owner</th><th>Source note</th><th>Status</th><th>Decision</th></tr></thead><tbody>';
            foreach($records as $item){
                $label=$kind==='person'?$item->full_name:("#{$item->person_a} → #{$item->person_b}: {$item->relation}");
                echo '<tr><td>'.esc_html($label).'</td><td>'.(int)$item->owner_id.'</td><td>'.esc_html($item->evidence_notes).'</td><td>'.esc_html($item->verification).'</td><td>';
                echo self::form_open('review','ino-g-inline').'<input type="hidden" name="record_type" value="'.esc_attr($kind).'"><input type="hidden" name="record_id" value="'.(int)$item->id.'"><select name="decision"><option value="unverified">Unverified</option><option value="verified">Source reviewed</option></select><button class="button" type="submit">Record decision</button></form></td></tr>';
            }echo '</tbody></table>';
        }
        echo '</div>';
    }
}
