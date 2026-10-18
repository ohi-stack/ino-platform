<?php
/**
 * INO Platform Shortcode Manager.
 * Administrative inventory only: inspecting a callback never executes it.
 * Originator: One Gregory Onegodian™.
 */
if (!defined('ABSPATH')) { exit; }

final class INO_Platform_Shortcode_Manager {
    const SLUG = 'ino-platform-shortcodes';

    /**
     * Explicit metadata manifest. This is a source-code / intended-access
     * assessment, NOT a production or end-to-end readiness certification.
     *
     * Each row: purpose, maturity, intended access, expected callback owner.
     */
    private static function catalogue() {
        return array(
            'Platform & Membership' => array(
                'ino_portal' => array('Main INO services landing and navigation','interface','Public','INO_Platform_Shortcodes'),
                'ino_about' => array('About the INO Platform introduction','informational','Public','INO_Platform_Shortcodes'),
                'ino_membership' => array('Membership information and portal entry','interface','Public','INO_Platform_Shortcodes'),
                'ino_membership_portal' => array('Membership portal introduction and pathway','interface','Public','INO_Platform_Membership_UI'),
                'ino_citizenship' => array('Voluntary INO enrollment information; not civil citizenship','informational','Public','INO_Platform_Shortcodes'),
                'ino_member_dashboard' => array('Member dashboard and linked account records','interface','Signed-in member','INO_Platform_Shortcodes'),
                'ino_member_directory' => array('Directory subject to public profile consent','interface','Public; consent-filtered','INO_Platform_Shortcodes'),
                'ino_member_profile' => array('Member profile with permission controls','interface','Member / approved public fields','INO_Platform_Shortcodes'),
                'ino_people_network' => array('People Network overview; social workflow separate','informational','Public','INO_Platform_Shortcodes'),
                'ino_community' => array('Programs and community services overview','informational','Public','INO_Platform_Shortcodes'),
                'ino_contact_form' => array('Contact information only; not a verified intake form','informational','Public','INO_Platform_Shortcodes')
            ),
            'Identity & Heritage' => array(
                'ino_identity_intro' => array('Identity and heritage introduction','informational','Public','INO_Platform_Shortcodes'),
                'ino_identity_declaration' => array('Member identity declaration form','workflow','Signed-in member','INO_Platform_Shortcodes'),
                'ino_identity_dashboard' => array('Private identity and heritage record view','interface','Signed-in member','INO_Platform_Shortcodes'),
                'ino_identity_standards' => array('Identity classifications, standards and disclosures','informational','Public','INO_Platform_Shortcodes'),
                'ino_book_of_names' => array('People\'s Book of Names overview; search not established','informational','Public','INO_Platform_Shortcodes'),
                'ino_family_archives' => array('Family and cultural archives overview; upload not established','informational','Public','INO_Platform_Shortcodes'),
                'ino_certificates' => array('Certificate program overview; issuance not established','informational','Public','INO_Platform_Shortcodes'),
                'ino_verify_identity' => array('Identity verification overview; external validation not established','informational','Public','INO_Platform_Shortcodes')
            ),
            'Genealogy & Family' => array(
                'ino_family_tree' => array('Private ancestor workspace and member-managed relationships','workflow','Signed-in owner / authorized administrator','INO_Platform_Genealogy'),
                'ino_genealogy_public' => array('Revocable token-limited public ancestor view','interface','Public with valid share token','INO_Platform_Genealogy')
            ),
            'Documents & Declarations' => array(
                'ino_documents' => array('Published public document metadata register','interface','Public; approved metadata only','INO_Platform_Documents'),
                'ino_documents_registry' => array('Alias for the published public document register','interface','Public; approved metadata only','INO_Platform_Documents'),
                'ino_declarations_affidavits' => array('Declarations and affidavits information plus approved index','interface','Public; approved metadata only','INO_Platform_Declarations'),
                'ino_declarations_directory' => array('Filterable approved declaration index','interface','Public; approved metadata only','INO_Platform_Declarations')
            ),
            'Governance' => array(
                'ino_governance' => array('Institutional governance information','interface','Public','INO_Platform_Shortcodes'),
                'ino_governance_dashboard' => array('Governance dashboard and approved record views','interface','Governance access-controlled','INO_Platform_Governance'),
                'ino_constitution' => array('Constitution source and amendments register','interface','Public; authorized records','INO_Platform_Governance'),
                'ino_governance_structure' => array('Organizational structure and offices','interface','Public; authorized records','INO_Platform_Governance'),
                'ino_governance_records' => array('Published governance decisions and records','interface','Public; approved records only','INO_Platform_Governance'),
                'ino_governance_operations' => array('Controlled internal governance operations','workflow','Governance-authorized staff','INO_Governance_Operations_UI')
            ),
            'ODIN & Transparency' => array(
                'ino_odin_registry' => array('ODIN institutional public reference index','interface','Public; approved records only','INO_Governance_ODIN_UI'),
                'ino_odin_verify' => array('ODIN internal record-reference lookup','interface','Public; permitted metadata only','INO_Governance_ODIN_UI'),
                'ino_odin_timeline' => array('ODIN historical record timeline','interface','Public; approved records only','INO_Governance_ODIN_UI')
            ),
            'Voting & Consultation' => array(
                'ino_voting' => array('Consultations and eligibility-controlled participation','workflow','Eligible signed-in participant','INO_Platform_Voting'),
                'ino_vote_results' => array('Permitted aggregate consultation results','interface','Public; result policy applies','INO_Platform_Voting'),
                'ino_my_votes' => array('Member\'s individual participation history','interface','Signed-in owner only','INO_Platform_Voting')
            ),
            'Treasury & Housing' => array(
                'ino_treasury_grants' => array('Treasury and grant-program introduction; transactions absent','informational','Public','INO_Platform_Shortcodes'),
                'ino_housing' => array('Housing planning and development introduction','informational','Public','INO_Platform_Shortcodes')
            )
        );
    }

    private static function labels() {
        return array(
            'workflow' => 'Workflow coded — unverified',
            'interface' => 'Interface coded — unverified',
            'informational' => 'Informational / placeholder',
            'unknown' => 'Not classified'
        );
    }

    public static function init() {
        add_action('admin_enqueue_scripts',array(__CLASS__,'assets'));
        add_action('admin_post_ino_shortcode_report',array(__CLASS__,'export'));
    }

    public static function assets($hook) {
        if(!isset($_GET['page']) || !is_string($_GET['page']) ||
           sanitize_key(wp_unslash($_GET['page'])) !== self::SLUG){return;}
        $css='assets/css/ino-shortcode-manager.css';
        $js='assets/js/ino-shortcode-manager.js';
        wp_enqueue_style('ino-shortcode-manager',INO_PLATFORM_URL.$css,array(),
            file_exists(INO_PLATFORM_PATH.$css)?(string)filemtime(INO_PLATFORM_PATH.$css):INO_PLATFORM_VERSION);
        wp_enqueue_script('ino-shortcode-manager',INO_PLATFORM_URL.$js,array(),
            file_exists(INO_PLATFORM_PATH.$js)?(string)filemtime(INO_PLATFORM_PATH.$js):INO_PLATFORM_VERSION,true);
    }

    private static function flatten() {
        $rows=array();
        foreach(self::catalogue() as $group=>$items){
            foreach($items as $tag=>$spec){
                $rows[$tag]=array('tag'=>$tag,'group'=>$group,'purpose'=>$spec[0],
                    'maturity'=>$spec[1],'access'=>$spec[2],'expected_owner'=>$spec[3]);
            }
        }
        // Extras registered by an installed add-on appear as unknown, rather
        // than wrongly being represented as a certified part of the core.
        global $shortcode_tags;
        foreach((array)$shortcode_tags as $tag=>$callback){
            if(strpos((string)$tag,'ino_')!==0 || isset($rows[$tag])){continue;}
            $rows[$tag]=array('tag'=>$tag,'group'=>'Other active INO-prefix handlers',
                'purpose'=>'Registered at runtime outside the core catalogue; review its owning plugin',
                'maturity'=>'unknown','access'=>'Not assessed','expected_owner'=>'');
        }
        return $rows;
    }

    private static function callback_owner($callback) {
        if(is_array($callback) && count($callback)===2){
            $target=$callback[0];
            return is_object($target)?get_class($target):(is_string($target)?$target:'Unknown');
        }
        if(is_string($callback)){return $callback;}
        if($callback instanceof Closure){return 'Closure';}
        if(is_object($callback)){return get_class($callback);}
        return 'Unknown';
    }

    /**
     * A removed module can leave literal [ino_...] tags in page content.
     * WordPress has_shortcode() only recognizes active registered tags, so
     * retain a narrow literal fallback to report those orphaned assignments.
     */
    private static function contains_shortcode($content,$tag) {
        if(strpos($content,'['.$tag)===false){return false;}
        if(shortcode_exists($tag) && has_shortcode($content,$tag)){return true;}
        return preg_match('/(?<!\\[)\\['.preg_quote($tag,'/').'(?=[\\s\\/\\]])/', $content)===1;
    }

    /**
     * Page assignments are a read-only index of literal shortcode usage in
     * native wp_posts.post_content. It intentionally does not execute tags.
     * Scan bounded to 2,000 matching pages; warn if some are unscanned.
     */
    private static function placements($tags) {
        global $wpdb;
        $sql=$wpdb->prepare(
            "SELECT ID,post_title,post_name,post_status,post_content FROM {$wpdb->posts}
             WHERE post_type=%s AND post_status IN ('publish','private','future','draft','pending')
               AND post_content LIKE %s
             ORDER BY ID ASC LIMIT 2001",
            'page','%'.$wpdb->esc_like('[ino_').'%'
        );
        $pages=$wpdb->get_results($sql);
        if(!is_array($pages)){$pages=array();}
        $partial=count($pages)>2000;
        $found=array_fill_keys($tags,array());
        foreach(array_slice($pages,0,2000) as $page){
            $content=(string)$page->post_content;
            foreach($tags as $tag){
                if(!self::contains_shortcode($content,$tag)){continue;}
                $found[$tag][]=array('id'=>(int)$page->ID,'title'=>(string)$page->post_title,
                    'status'=>(string)$page->post_status,'slug'=>(string)$page->post_name);
            }
        }
        return array('map'=>$found,'partial'=>$partial,'examined'=>min(2000,count($pages)));
    }

    private static function inventory() {
        global $shortcode_tags;
        $manifest=self::flatten();
        $indexed=self::placements(array_keys($manifest));
        $rows=array();
        foreach($manifest as $tag=>$item){
            $registered=shortcode_exists($tag);
            $callback=$registered && isset($shortcode_tags[$tag])?$shortcode_tags[$tag]:null;
            $callable=$registered && is_callable($callback);
            $owner=$registered?self::callback_owner($callback):'—';
            $overridden=$registered && $item['expected_owner']!=='' &&
                $owner!==$item['expected_owner'];
            $item['registered']=$registered;
            $item['callable']=$callable;
            $item['owner']=$owner;
            $item['overridden']=$overridden;
            $item['pages']=$indexed['map'][$tag]??array();
            $rows[$tag]=$item;
        }
        return array('rows'=>$rows,'partial'=>$indexed['partial'],'examined'=>$indexed['examined']);
    }

    private static function filtered($rows) {
        $q=isset($_GET['ino_sc_q'])?sanitize_text_field(wp_unslash($_GET['ino_sc_q'])):'';
        $group=isset($_GET['ino_sc_group'])?sanitize_text_field(wp_unslash($_GET['ino_sc_group'])):'';
        $maturity=isset($_GET['ino_sc_maturity'])?sanitize_key(wp_unslash($_GET['ino_sc_maturity'])):'';
        $placement=isset($_GET['ino_sc_placement'])?sanitize_key(wp_unslash($_GET['ino_sc_placement'])):'';
        $availability=isset($_GET['ino_sc_availability'])?sanitize_key(wp_unslash($_GET['ino_sc_availability'])):'';
        $out=array();
        foreach($rows as $tag=>$row){
            if($group!=='' && $row['group']!==$group){continue;}
            if($maturity!=='' && $row['maturity']!==$maturity){continue;}
            if($placement==='assigned' && !$row['pages']){continue;}
            if($placement==='unassigned' && $row['pages']){continue;}
            if($availability==='available' && !$row['callable']){continue;}
            if($availability==='missing' && $row['callable']){continue;}
            if($availability==='override' && !$row['overridden']){continue;}
            if($q!=='' && stripos(implode(' ',array($tag,$row['purpose'],$row['group'],$row['access'],
                   $row['owner'])),$q)===false){continue;}
            $out[$tag]=$row;
        }
        return $out;
    }

    private static function cell($value) {
        $value=(string)$value;
        // Spreadsheet formula mitigation for user-controlled WP titles/metadata.
        if(preg_match('/^[\s\x00-\x1f]*[=+@\-]/u',$value)){$value="'".$value;}
        return $value;
    }

    public static function export() {
        if(!current_user_can('manage_options')){wp_die('Permission denied',403);}
        check_admin_referer('ino_shortcode_report');
        $data=self::inventory();
        nocache_headers();
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="ino-shortcode-inventory-'.gmdate('Ymd').'.csv"');
        $fp=fopen('php://output','w');
        $cols=array('shortcode','module','purpose','declared_readiness','intended_access',
            'registered','callable','handler_owner','expected_handler','handler_changed',
            'assigned_page_count','assigned_page_ids','assigned_page_statuses','page_index_partial');
        fputcsv($fp,$cols);
        foreach($data['rows'] as $row){
            $fields=array('['.$row['tag'].']',$row['group'],$row['purpose'],
                self::labels()[$row['maturity']]??$row['maturity'],$row['access'],
                $row['registered']?'Yes':'No',$row['callable']?'Yes':'No',$row['owner'],
                $row['expected_owner'],$row['overridden']?'Yes':'No',count($row['pages']),
                implode(';',array_map(function($p){return $p['id'];},$row['pages'])),
                implode(';',array_map(function($p){return $p['status'];},$row['pages'])),
                $data['partial']?'Yes':'No');
            fputcsv($fp,array_map(array(__CLASS__,'cell'),$fields));
        }
        fclose($fp);exit;
    }

    public static function page() {
        if(!current_user_can('manage_options')){wp_die('Permission denied',403);}
        $data=self::inventory();$all=$data['rows'];$filtered=self::filtered($all);
        $groups=array_keys(self::catalogue());
        foreach($all as $item){if(!in_array($item['group'],$groups,true)){$groups[]=$item['group'];}}
        $total=count($all);$available=0;$assigned=0;$missing=0;$overrides=0;$info=0;
        foreach($all as $item){
            if($item['callable']){$available++;}else{$missing++;}
            if($item['pages']){$assigned++;}
            if($item['overridden']){$overrides++;}
            if($item['maturity']==='informational'){$info++;}
        }
        $q=isset($_GET['ino_sc_q'])?sanitize_text_field(wp_unslash($_GET['ino_sc_q'])):'';
        $group=isset($_GET['ino_sc_group'])?sanitize_text_field(wp_unslash($_GET['ino_sc_group'])):'';
        $mat=isset($_GET['ino_sc_maturity'])?sanitize_key(wp_unslash($_GET['ino_sc_maturity'])):'';
        $place=isset($_GET['ino_sc_placement'])?sanitize_key(wp_unslash($_GET['ino_sc_placement'])):'';
        $avail=isset($_GET['ino_sc_availability'])?sanitize_key(wp_unslash($_GET['ino_sc_availability'])):'';
        echo '<div class="wrap ino-sc"><header class="ino-sc-hero"><span class="ino-sc-kicker">INDIGENOUS NATION OF ONEGODIA · PLATFORM ADMINISTRATION</span><h1>Shortcode Manager</h1><p>Runtime handler inventory, content-page placements, intended access and source-level readiness. Registration is not proof of production operation.</p><div class="ino-sc-hero-actions"><span>INO Platform '.esc_html(INO_PLATFORM_VERSION).'</span><span>Read-only diagnostics</span></div></header>';
        echo '<div class="ino-sc-stats" role="group" aria-label="Shortcode health overview">';
        foreach(array(array($total,'Tracked shortcodes'),array($available,'Registered & callable'),
                      array($assigned,'Assigned to pages'),array($missing,'Missing / broken callbacks'),
                      array($overrides,'Unexpected owners')) as $s){
            echo '<div class="ino-sc-stat"><strong>'.(int)$s[0].'</strong><span>'.esc_html($s[1]).'</span></div>';
        }
        echo '</div><div class="ino-sc-notice"><strong>What these indicators mean:</strong> “Registered & callable” only confirms a handler exists in this PHP request. “Assigned” only means a WordPress page contains the shortcode in post content. Neither proves render success, authorization, database readiness, feature completeness, or live production deployment. <strong>'.(int)$info.'</strong> catalogue entries are explicitly informational.</div>';
        if($data['partial']){echo '<div class="notice notice-warning"><p>The page assignment index was capped at 2,000 matching pages; unlisted assignments may exist. Page counts are incomplete.</p></div>';}
        echo '<section class="ino-sc-panel"><div class="ino-sc-panel-head"><div><h2>Inventory &amp; Placement Audit</h2><p>'.count($filtered).' visible of '.(int)$total.' tracked shortcodes. Data refreshes with each page load.</p></div>';
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="ino_shortcode_report">'.wp_nonce_field('ino_shortcode_report','_wpnonce',true,false).'<button class="button ino-sc-export" type="submit">Export CSV report</button></form></div>';
        echo '<form method="get" class="ino-sc-filters" aria-label="Filter shortcodes"><input type="hidden" name="page" value="'.esc_attr(self::SLUG).'"><label>Search <input type="search" name="ino_sc_q" value="'.esc_attr($q).'" placeholder="Shortcode, purpose, access…"></label><label>Module <select name="ino_sc_group"><option value="">All modules</option>';
        foreach($groups as $name){echo '<option value="'.esc_attr($name).'" '.selected($group,$name,false).'>'.esc_html($name).'</option>';}
        echo '</select></label><label>Readiness <select name="ino_sc_maturity"><option value="">All source levels</option>';
        foreach(self::labels() as $value=>$label){echo '<option value="'.esc_attr($value).'" '.selected($mat,$value,false).'>'.esc_html($label).'</option>';}
        echo '</select></label><label>Page assignment <select name="ino_sc_placement">';
        foreach(array(''=>'All assignments','assigned'=>'Assigned','unassigned'=>'Unassigned') as $value=>$label){
            echo '<option value="'.esc_attr($value).'" '.selected($place,$value,false).'>'.esc_html($label).'</option>';
        }
        echo '</select></label><label>Handler health <select name="ino_sc_availability">';
        foreach(array(''=>'All handlers','available'=>'Callable','missing'=>'Missing / broken','override'=>'Unexpected owner') as $value=>$label){
            echo '<option value="'.esc_attr($value).'" '.selected($avail,$value,false).'>'.esc_html($label).'</option>';
        }
        echo '</select></label><button type="submit" class="button button-primary">Apply filters</button><a class="button" href="'.esc_url(admin_url('admin.php?page='.self::SLUG)).'">Reset</a></form>';
        echo '<div class="ino-sc-scroll" role="region" aria-label="Shortcode inventory table" tabindex="0"><table class="widefat striped ino-sc-table"><thead><tr><th>Shortcode &amp; purpose</th><th>Module</th><th>Source readiness</th><th>Intended access</th><th>WordPress page assignments</th><th>Runtime handler</th></tr></thead><tbody>';
        if(!$filtered){echo '<tr><td colspan="6" class="ino-sc-empty">No shortcodes matched your filters.</td></tr>';}
        foreach($filtered as $item){
            $tag=$item['tag'];$short='['.$tag.']';
            echo '<tr><td class="ino-sc-main"><code>'.esc_html($short).'</code> <button type="button" class="button button-small ino-sc-copy" data-shortcode="'.esc_attr($short).'" aria-label="Copy shortcode '.esc_attr($tag).'">Copy</button><p>'.esc_html($item['purpose']).'</p></td>';
            echo '<td>'.esc_html($item['group']).'</td>';
            $type=$item['maturity']==='informational'?'info':($item['maturity']==='unknown'?'unknown':'pending');
            echo '<td><span class="ino-sc-chip ino-sc-chip-'.esc_attr($type).'">'.esc_html(self::labels()[$item['maturity']]??'Not classified').'</span></td>';
            echo '<td>'.esc_html($item['access']).'</td><td>';
            if(!$item['pages']){echo '<span class="ino-sc-unassigned">No shortcode in scanned pages</span>';}
            else{
                foreach($item['pages'] as $p){
                    echo '<div class="ino-sc-page"><span>'.esc_html($p['title']?:'(Untitled page)').'</span> <small>'.esc_html($p['status']).' · #'.(int)$p['id'].'</small>';
                    if(current_user_can('edit_post',$p['id'])){
                        $url=get_edit_post_link($p['id'],'');
                        if($url){echo ' <a href="'.esc_url($url).'">Edit page ↗</a>';}
                    }
                    echo '</div>';
                }
            }
            echo '</td><td>';
            if(!$item['registered']){echo '<span class="ino-sc-chip ino-sc-chip-bad">Not registered</span>';}
            elseif(!$item['callable']){echo '<span class="ino-sc-chip ino-sc-chip-bad">Handler not callable</span>';}
            else{echo '<span class="ino-sc-chip ino-sc-chip-ok">Registered &amp; callable</span>';}
            echo '<div class="ino-sc-owner">Owner: <code>'.esc_html($item['owner']).'</code></div>';
            if($item['overridden']){echo '<div class="ino-sc-warning">Owner differs from expected: '.esc_html($item['expected_owner']).'. Review for collisions.</div>';}
            echo '</td></tr>';
        }
        echo '</tbody></table></div><div class="ino-sc-foot"><span>Page mapping reads WordPress page <code>post_content</code> only, including standard and WPBakery shortcode text. It does not inspect widgets, theme templates, reusable blocks, dynamic builders or plugin-owned routing.</span><span>Deployment and end-to-end readiness require separate staging acceptance.</span></div></section><p id="ino-sc-copy-feedback" class="screen-reader-text" role="status" aria-live="polite"></p></div>';
    }
}
