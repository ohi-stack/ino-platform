<?php
/**
 * Treasury & Grants integration for the existing INO Platform.
 * Research/reference records never establish applicant eligibility or land title.
 */
if (!defined('ABSPATH')) { exit; }

class INO_Platform_Grants {
    const SCHEMA = '2026-10-20.1';
    const VIEW = 'ino_view_grants';
    const MANAGE = 'ino_manage_grants';
    const EXPORT = 'ino_export_grants';
    const COMPLIANCE = 'ino_manage_grant_compliance';

    public static function init() {
        add_action('init', array(__CLASS__, 'maybe_install'), 5);
        add_action('admin_menu', array(__CLASS__, 'menus'), 99);
        add_action('admin_post_ino_funding_save', array(__CLASS__, 'save'));
        add_action('admin_post_ino_funding_export', array(__CLASS__, 'export'));
        add_action('rest_api_init', array(__CLASS__, 'routes'));
        add_shortcode('ino_treasury_grants', array(__CLASS__, 'shortcode'));
        add_shortcode('ino_grant_opportunities', array(__CLASS__, 'shortcode'));
    }

    public static function maybe_install() {
        if (get_option('ino_funding_schema_version') === self::SCHEMA) { return; }
        self::install();
    }

    public static function install() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $p = $wpdb->prefix;
        $cc = $wpdb->get_charset_collate();
        // Additive migration of the pre-existing ino_grants registry; do not delete old rows.
        dbDelta("CREATE TABLE {$p}ino_grants (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            tracking_id varchar(60) NOT NULL,
            funding_source varchar(190) DEFAULT '',
            agency varchar(190) DEFAULT '',
            program_name varchar(190) DEFAULT '',
            category varchar(80) DEFAULT 'Land & Conservation',
            funding_type varchar(100) DEFAULT '',
            amount_available varchar(120) DEFAULT '',
            match_requirement varchar(190) DEFAULT '',
            applicant_entity varchar(120) DEFAULT 'Eligibility Review Required',
            eligibility_basis text NULL,
            recognition_eligibility_basis text NULL,
            partner_required tinyint(1) NOT NULL DEFAULT 0,
            partner_notes text NULL,
            geographic_eligibility text NULL,
            eligible_costs longtext NULL,
            priority varchar(10) DEFAULT 'P1',
            project_id bigint(20) unsigned DEFAULT NULL,
            program_url text NULL,
            application_url text NULL,
            deadline date NULL,
            status varchar(60) DEFAULT 'Opportunity Identified',
            verification_status varchar(30) NOT NULL DEFAULT 'Needs Verification',
            readiness_score tinyint(3) unsigned NOT NULL DEFAULT 0,
            assigned_to varchar(190) DEFAULT '',
            is_public tinyint(1) NOT NULL DEFAULT 0,
            notes longtext NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY tracking_id (tracking_id),
            KEY priority (priority),
            KEY deadline (deadline)
        ) $cc;");
        dbDelta("CREATE TABLE {$p}ino_grant_projects (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            project_code varchar(60) NOT NULL,
            title varchar(190) NOT NULL,
            category varchar(80) DEFAULT 'Land',
            priority varchar(10) DEFAULT 'P1',
            location varchar(190) DEFAULT '',
            site_status varchar(80) DEFAULT 'Research',
            applicant_entity varchar(120) DEFAULT 'Eligibility Review Required',
            funding_need decimal(15,2) DEFAULT NULL,
            status varchar(60) DEFAULT 'Research',
            notes longtext NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY project_code (project_code)
        ) $cc;");
        dbDelta("CREATE TABLE {$p}ino_grant_matches (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            project_id bigint(20) unsigned NOT NULL,
            grant_id bigint(20) unsigned NOT NULL,
            eligibility_status varchar(60) DEFAULT 'Not Reviewed',
            go_no_go varchar(20) DEFAULT 'Pending',
            rationale text NULL,
            reviewed_by bigint(20) unsigned DEFAULT NULL,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY project_grant (project_id,grant_id)
        ) $cc;");
        dbDelta("CREATE TABLE {$p}ino_grant_applications (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            application_code varchar(60) NOT NULL,
            grant_id bigint(20) unsigned NOT NULL,
            project_id bigint(20) unsigned DEFAULT NULL,
            applicant_entity varchar(120) DEFAULT '',
            partner_entity varchar(190) DEFAULT '',
            status varchar(60) DEFAULT 'Pre-Application',
            go_no_go varchar(20) DEFAULT 'Pending',
            requested_amount decimal(15,2) DEFAULT NULL,
            submitted_at date DEFAULT NULL,
            award_amount decimal(15,2) DEFAULT NULL,
            notes longtext NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY application_code (application_code),
            KEY grant_id (grant_id)
        ) $cc;");
        dbDelta("CREATE TABLE {$p}ino_grant_compliance (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            application_id bigint(20) unsigned NOT NULL,
            requirement varchar(190) NOT NULL,
            due_date date DEFAULT NULL,
            status varchar(60) DEFAULT 'Open',
            document_url text NULL,
            notes longtext NULL,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY application_id (application_id),
            KEY due_date (due_date)
        ) $cc;");
        dbDelta("CREATE TABLE {$p}ino_grant_audit (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            record_type varchar(30) NOT NULL,
            record_id bigint(20) unsigned NOT NULL,
            action varchar(30) NOT NULL,
            actor_id bigint(20) unsigned NOT NULL,
            recorded_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY record_lookup (record_type,record_id)
        ) $cc;");
        $caps = array(self::VIEW, self::MANAGE, self::EXPORT, self::COMPLIANCE);
        $admin = get_role('administrator');
        if ($admin) { foreach ($caps as $cap) { $admin->add_cap($cap); } }
        add_role('ino_grants_administrator', 'INO Grants Administrator', array('read'=>true));
        $role = get_role('ino_grants_administrator');
        if ($role) { foreach ($caps as $cap) { $role->add_cap($cap); } }
        update_option('ino_funding_schema_version', self::SCHEMA, false);
    }

    private static function fields() {
        return array(
            'opportunities'=>array(
                'program_name'=>array('label'=>'Program / opportunity','required'=>true),
                'agency'=>array('label'=>'Agency / funder'),
                'category'=>array('choices'=>array('Land & Conservation','Property Acquisition','Housing','Community Facilities','Infrastructure','Energy','Family & Community','Technology R&D','Other')),
                'priority'=>array('choices'=>array('P1','P2','P3')),
                'applicant_entity'=>array('label'=>'Applicant entity / partner'),
                'eligibility_basis'=>array('label'=>'Verified eligibility basis','textarea'=>true),
                'recognition_eligibility_basis'=>array('label'=>'Recognition / eligibility qualifications','textarea'=>true),
                'geographic_eligibility'=>array('textarea'=>true),
                'eligible_costs'=>array('textarea'=>true),
                'partner_required'=>array('choices'=>array('0','1'),'label'=>'Partner required? 1 = yes'),
                'partner_notes'=>array('textarea'=>true),
                'funding_type'=>array(),
                'amount_available'=>array('label'=>'Award range (unverified until checked)'),
                'match_requirement'=>array(),
                'deadline'=>array('type'=>'date'),
                'verification_status'=>array('choices'=>array('Needs Verification','Verified','Ineligible')),
                'status'=>array('choices'=>array('Opportunity Identified','Eligibility Review','Project Match','Go/No-Go','Pre-Application','Application Development','Internal Approval','Submitted','Under Review','Awarded','Declined','Compliance','Reporting','Closeout','Closed')),
                'readiness_score'=>array('type'=>'number'),
                'assigned_to'=>array(),
                'program_url'=>array('type'=>'url'),
                'application_url'=>array('type'=>'url'),
                'is_public'=>array('choices'=>array('0','1'),'label'=>'Public listing? 1 = yes; only verified records display'),
                'notes'=>array('textarea'=>true)
            ),
            'projects'=>array(
                'title'=>array('required'=>true),
                'category'=>array('choices'=>array('Land','Conservation','Housing','Facilities','Programs','Technology','Infrastructure','Energy','Other')),
                'priority'=>array('choices'=>array('P1','P2','P3')),
                'location'=>array(),
                'site_status'=>array('choices'=>array('Research','Opportunity Reported','Owner Contact Pending','Site Control Under Review','Preliminary Planning','Partner Development','Active Stewardship','Conserved/Completed')),
                'applicant_entity'=>array(),
                'funding_need'=>array('type'=>'number'),
                'status'=>array(),
                'notes'=>array('textarea'=>true)
            ),
            'matches'=>array(
                'project_id'=>array('type'=>'number','required'=>true),
                'grant_id'=>array('type'=>'number','required'=>true),
                'eligibility_status'=>array('choices'=>array('Not Reviewed','Eligible','Ineligible','Partner Required','Pending Verification')),
                'go_no_go'=>array('choices'=>array('Pending','Go','No-Go','Hold')),
                'rationale'=>array('textarea'=>true)
            ),
            'applications'=>array(
                'grant_id'=>array('type'=>'number','required'=>true),
                'project_id'=>array('type'=>'number'),
                'applicant_entity'=>array('required'=>true),
                'partner_entity'=>array(),
                'status'=>array('choices'=>array('Pre-Application','Application Development','Internal Approval','Submitted','Under Review','Awarded','Declined','Compliance','Reporting','Closeout','Closed')),
                'go_no_go'=>array('choices'=>array('Pending','Go','No-Go','Hold')),
                'requested_amount'=>array('type'=>'number'),
                'submitted_at'=>array('type'=>'date'),
                'award_amount'=>array('type'=>'number'),
                'notes'=>array('textarea'=>true)
            ),
            'compliance'=>array(
                'application_id'=>array('type'=>'number','required'=>true),
                'requirement'=>array('required'=>true),
                'due_date'=>array('type'=>'date'),
                'status'=>array('choices'=>array('Open','In Progress','Submitted','Complete','Overdue','Closed')),
                'document_url'=>array('type'=>'url'),
                'notes'=>array('textarea'=>true)
            )
        );
    }
    private static function table($kind) {
        $names = array('opportunities'=>'ino_grants','projects'=>'ino_grant_projects','matches'=>'ino_grant_matches','applications'=>'ino_grant_applications','compliance'=>'ino_grant_compliance');
        global $wpdb;
        return isset($names[$kind]) ? $wpdb->prefix.$names[$kind] : '';
    }
    private static function require_cap($cap) {
        if (!current_user_can($cap)) { wp_die(esc_html__('Insufficient privileges for INO funding records.', 'ino-platform'), '', array('response'=>403)); }
    }
    public static function menus() {
        add_menu_page('INO Funding Pipeline','INO Funding',self::VIEW,'ino-funding',array(__CLASS__,'admin_page'),'dashicons-chart-area',4);
        foreach (array('opportunities'=>'Opportunities','projects'=>'Projects','matches'=>'Matches','applications'=>'Applications','compliance'=>'Compliance','library'=>'Program Library') as $kind=>$label) {
            add_submenu_page('ino-funding',$label,$label,self::VIEW,'ino-funding-'.$kind,array(__CLASS__,'admin_page'));
        }
        // Replace only the former grants placeholder; leave other INO modules untouched.
        remove_submenu_page('ino-platform','ino-platform-grants');
        add_submenu_page('ino-platform','Treasury & Grants','Treasury & Grants','manage_options','ino-platform-grants',array(__CLASS__,'admin_page'));
    }
    public static function save() {
        self::require_cap(self::MANAGE);
        $kind = isset($_POST['kind']) ? sanitize_key(wp_unslash($_POST['kind'])) : '';
        if ($kind==='compliance') { self::require_cap(self::COMPLIANCE); }
        $schema = self::fields();
        if (!isset($schema[$kind])) { wp_die('Invalid record type.', '', array('response'=>400)); }
        check_admin_referer('ino_funding_save_'.$kind);
        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
        $data = array();
        foreach ($schema[$kind] as $name=>$cfg) {
            $raw = isset($_POST[$name]) && is_scalar($_POST[$name]) ? wp_unslash($_POST[$name]) : '';
            $val = sanitize_text_field($raw);
            if (!empty($cfg['required']) && $val==='') { wp_die('A required field is missing.', '', array('response'=>400)); }
            if (isset($cfg['choices']) && !in_array($val,$cfg['choices'],true)) {
                $val = $cfg['choices'][0];
            }
            if (($cfg['type']??'')==='number') { $val = ($val==='') ? null : (is_numeric($val) ? max(0, (float)$val) : null); }
            if (($cfg['type']??'')==='url') { $val = esc_url_raw($raw); }
            if (($cfg['type']??'')==='date') {
                $dt = DateTimeImmutable::createFromFormat('!Y-m-d',$val);
                $val = ($dt && $dt->format('Y-m-d') === $val) ? $val : null;
            }
            if (!empty($cfg['textarea'])) { $val = sanitize_textarea_field($raw); }
            if (in_array($name,array('grant_id','project_id','application_id','readiness_score'),true)) { $val = (int)$val; }
            $data[$name] = $val;
        }
        global $wpdb;
        // Validate cross-record references; never accept a fabricated related record id.
        foreach (array('grant_id'=>'opportunities','project_id'=>'projects','application_id'=>'applications') as $fk=>$target) {
            if (!array_key_exists($fk,$data) || !$data[$fk]) { continue; }
            $parent = self::table($target);
            if (!$wpdb->get_var($wpdb->prepare("SELECT id FROM {$parent} WHERE id=%d",$data[$fk]))) {
                wp_die('Referenced record does not exist.', '', array('response'=>400));
            }
        }
        if ($kind==='opportunities') {
            // Public disclosure must be individually enabled and eligibility verified.
            $data['is_public'] = ($data['is_public']==='1' && $data['verification_status']==='Verified') ? 1 : 0;
            $data['readiness_score'] = min(100,max(0,(int)$data['readiness_score']));
        }
        $table = self::table($kind);
        $data['updated_at'] = current_time('mysql');
        if ($kind==='matches') {
            $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE project_id=%d AND grant_id=%d",$data['project_id'],$data['grant_id']));
            if ($existing) { $id = (int)$existing; }
            $data['reviewed_by'] = get_current_user_id();
        }
        if (!$id && $kind==='opportunities') { $data['tracking_id']='INO-GR-'.strtoupper(wp_generate_password(10,false,false)); }
        if (!$id && $kind==='projects') { $data['project_code']='INO-PRJ-'.strtoupper(wp_generate_password(10,false,false)); }
        if (!$id && $kind==='applications') { $data['application_code']='INO-APP-'.strtoupper(wp_generate_password(10,false,false)); }
        if ($id) {
            if (!$wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE id=%d",$id))) { wp_die('Record not found.','',array('response'=>404)); }
            $ok = $wpdb->update($table,$data,array('id'=>$id));
            if ($ok === false) { wp_die('Unable to update record.','',array('response'=>500)); }
        } else {
            $ok = $wpdb->insert($table,$data);
            if (!$ok) { wp_die('Unable to save record.','',array('response'=>500)); }
            $id = (int)$wpdb->insert_id;
        }
        $wpdb->insert($wpdb->prefix.'ino_grant_audit',array('record_type'=>$kind,'record_id'=>$id,'action'=>$ok===0?'reviewed':'saved','actor_id'=>get_current_user_id()));
        wp_safe_redirect(add_query_arg(array('page'=>'ino-funding-'.$kind,'updated'=>'1'),admin_url('admin.php')));
        exit;
    }
    public static function admin_page() {
        self::require_cap(self::VIEW);
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
        $kind = str_replace('ino-funding-','',$page);
        if ($page==='ino-funding' || $page==='ino-platform-grants') { $kind='dashboard'; }
        echo '<div class="wrap"><h1>INO Treasury & Grants</h1><p>Connecticut Land Reclamation & Conservation is Priority 1. Research leads do not establish eligibility, site control, title, or an active grant opportunity.</p>';
        echo '<nav style="display:flex;gap:12px;flex-wrap:wrap;margin:18px 0">';
        foreach (array('dashboard'=>'Dashboard','opportunities'=>'Opportunities','projects'=>'Projects','matches'=>'Matches','applications'=>'Applications','compliance'=>'Compliance','library'=>'Library') as $k=>$label) {
            $slug=$k==='dashboard'?'ino-funding':'ino-funding-'.$k;
            echo '<a class="button" href="'.esc_url(admin_url('admin.php?page='.$slug)).'">'.esc_html($label).'</a>';
        }
        echo '</nav>';
        if ($kind==='dashboard') { self::dashboard(); }
        elseif ($kind==='library') { self::library(); }
        elseif (self::table($kind)) { self::registry($kind); }
        echo '</div>';
    }
    private static function dashboard() {
        global $wpdb;
        foreach (array('opportunities','projects','matches','applications','compliance') as $kind) {
            $table=self::table($kind);
            $count=(int)$wpdb->get_var("SELECT COUNT(*) FROM {$table}");
            echo '<div style="display:inline-block;background:#fff;border:1px solid #C99A2E;border-radius:12px;margin:5px;padding:20px;min-width:115px"><strong>'.esc_html(number_format_i18n($count)).'</strong><br>'.esc_html(ucfirst($kind)).'</div>';
        }
        echo '<p><strong>Funding pipeline:</strong> Project → Eligibility → Applicant/Partner → Go/No-Go → Application → Award → Compliance → Reporting.</p>';
        echo '<p>Reference programs are not counted as current opportunities or available awards. For actual submissions verify the current notice, applicant eligibility, geography, match requirements, and deadline.</p>';
        if (current_user_can(self::EXPORT)) { echo '<p><a class="button" href="'.esc_url(wp_nonce_url(admin_url('admin-post.php?action=ino_funding_export'),'ino_funding_export')).'">Export opportunities CSV</a></p>'; }
    }
    private static function registry($kind) {
        global $wpdb;
        $table=self::table($kind);
        $schema=self::fields();
        $fields=$schema[$kind];
        $can_edit=current_user_can(self::MANAGE) && ($kind!=='compliance'||current_user_can(self::COMPLIANCE));
        $id=isset($_GET['edit'])?absint($_GET['edit']):0;
        $record=$id && $can_edit ? $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id=%d",$id)) : null;
        if ($can_edit) {
            echo '<details '.($record?'open':'').'><summary><strong>'.($record?'Edit':'Add').' '.esc_html(ucfirst($kind)).'</strong></summary><form method="post" action="'.esc_url(admin_url('admin-post.php')).'" style="background:#fff;padding:20px;max-width:780px">';
            echo '<input type="hidden" name="action" value="ino_funding_save"><input type="hidden" name="kind" value="'.esc_attr($kind).'"><input type="hidden" name="id" value="'.esc_attr($id).'">';
            wp_nonce_field('ino_funding_save_'.$kind);
            foreach ($fields as $name=>$cfg) {
                $value=$record && isset($record->$name)?(string)$record->$name:'';
                echo '<p><label for="ino_'.esc_attr($name).'"><strong>'.esc_html($cfg['label']??ucwords(str_replace('_',' ',$name))).'</strong></label><br>';
                if (isset($cfg['choices'])) {
                    echo '<select id="ino_'.esc_attr($name).'" name="'.esc_attr($name).'">';
                    foreach ($cfg['choices'] as $choice) { echo '<option value="'.esc_attr($choice).'" '.selected($choice,$value,false).'>'.esc_html($choice).'</option>'; }
                    echo '</select>';
                } elseif (!empty($cfg['textarea'])) { echo '<textarea id="ino_'.esc_attr($name).'" name="'.esc_attr($name).'" rows="3" style="width:100%">'.esc_textarea($value).'</textarea>'; }
                else { echo '<input id="ino_'.esc_attr($name).'" type="'.esc_attr($cfg['type']??'text').'" name="'.esc_attr($name).'" value="'.esc_attr($value).'" '.(!empty($cfg['required'])?'required':'').' style="width:100%">'; }
                echo '</p>';
            }
            echo '<button class="button button-primary" type="submit">Save record</button></form></details>';
        }
        $rows=$wpdb->get_results("SELECT * FROM {$table} ORDER BY id DESC LIMIT 200");
        echo '<h2>'.esc_html(ucfirst($kind)).' register</h2><div style="overflow:auto"><table class="widefat striped"><thead><tr><th>ID</th>';
        foreach (array_keys($fields) as $name) {
            if (in_array($name,array('notes','eligibility_basis','recognition_eligibility_basis','geographic_eligibility','eligible_costs','rationale','partner_notes'),true)) { continue; }
            echo '<th>'.esc_html(ucwords(str_replace('_',' ',$name))).'</th>';
        }
        echo '<th>Action</th></tr></thead><tbody>';
        if (!$rows) { echo '<tr><td colspan="12">No records yet.</td></tr>'; }
        foreach ($rows as $row) {
            echo '<tr><td>'.esc_html($row->id).'</td>';
            foreach (array_keys($fields) as $name) {
                if (in_array($name,array('notes','eligibility_basis','recognition_eligibility_basis','geographic_eligibility','eligible_costs','rationale','partner_notes'),true)) { continue; }
                $value=$row->$name??'';
                echo '<td>'.esc_html(is_scalar($value)?(string)$value:'').'</td>';
            }
            echo '<td>'.($can_edit?'<a href="'.esc_url(add_query_arg(array('page'=>'ino-funding-'.$kind,'edit'=>(int)$row->id),admin_url('admin.php'))).'">Edit</a>':'—').'</td></tr>';
        }
        echo '</tbody></table></div><p>Showing up to 200 recent records. Use the CSV export for the opportunity register.</p>';
    }
    private static function library() {
        $programs=array(
            'CT DEEP conservation and stewardship programs','CT DOH FLEX / Housing Trust Fund','CHFA/DOH affordable housing pathways',
            'EPA Brownfields','HUD Community Development Block Grant','HUD HOME','USDA Community Facilities',
            'USDA Rural Business Development Grant','USDA REAP','CT STEAP','Federal Home Loan Bank Affordable Housing Program',
            'Low-Income Housing Tax Credits','USDA self-help housing','Head Start / Early Head Start',
            'CT OEC facility grants','Community Services Block Grant','EDA Build to Scale','NSF SBIR/STTR',
            'DOE energy programs','State and municipal conservation partnership programs'
        );
        echo '<p><strong>Reference-only program library.</strong> Program availability, application windows, eligibility and funding amounts must be verified from current official notices.</p><ul>';
        foreach ($programs as $name) { echo '<li>'.esc_html($name).' — eligibility not established</li>'; }
        echo '</ul>';
    }
    public static function export() {
        self::require_cap(self::EXPORT);
        check_admin_referer('ino_funding_export');
        global $wpdb;
        $rows=$wpdb->get_results("SELECT tracking_id,program_name,agency,category,priority,applicant_entity,verification_status,status,deadline,readiness_score FROM {$wpdb->prefix}ino_grants ORDER BY id DESC LIMIT 5000",ARRAY_A);
        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=ino-funding-'.gmdate('Ymd').'.csv');
        $out=fopen('php://output','w');
        if ($out) {
            fputcsv($out,array('Tracking','Program','Agency','Category','Priority','Applicant','Verification','Status','Deadline','Readiness'));
            foreach ($rows as $row) {
                foreach ($row as &$cell) {
                    $cell=(string)$cell;
                    if (preg_match('/^[\s]*[=+\-@]/u',$cell)) { $cell="'".$cell; }
                }
                unset($cell);
                fputcsv($out,array_values($row));
            }
            fclose($out);
        }
        exit;
    }
    public static function shortcode($atts=array(),$content='',$tag='') {
        global $wpdb;
        $authorized=current_user_can(self::VIEW);
        $html='<section class="ino-shell"><h2>INO Treasury & Grants</h2><p>Connecticut land conservation, lawful acquisition and responsible community development are our initial funding priorities.</p>';
        if ($authorized) { $html.='<p><a href="'.esc_url(admin_url('admin.php?page=ino-funding')).'">Open authorized funding dashboard</a></p>'; }
        if ($tag==='ino_grant_opportunities') {
            // Never leak private diligence, application, budgets, or internal review notes.
            $rows=$wpdb->get_results("SELECT program_name,agency,category,deadline,program_url FROM {$wpdb->prefix}ino_grants WHERE is_public=1 AND verification_status='Verified' ORDER BY deadline IS NULL, deadline LIMIT 50");
            if (!$rows) { $html.='<p>No externally verified public funding opportunities are currently listed.</p>'; }
            foreach ($rows as $r) {
                $html.='<article><h3>'.esc_html($r->program_name).'</h3><p>'.esc_html($r->agency.' · '.$r->category.' · '.($r->deadline?:'Deadline not specified')).'</p>';
                if ($r->program_url) { $html.='<a href="'.esc_url($r->program_url).'" rel="noopener noreferrer">Official program information</a>'; }
                $html.='</article>';
            }
        } else { $html.='<p>Only authorized staff may review grant applications, site records, or compliance documents.</p>'; }
        return $html.'</section>';
    }
    public static function routes() {
        register_rest_route('ino-platform/v1','/funding/public',array('methods'=>'GET','callback'=>array(__CLASS__,'public_api'),'permission_callback'=>'__return_true'));
        register_rest_route('ino-platform/v1','/funding/summary',array('methods'=>'GET','callback'=>array(__CLASS__,'summary_api'),'permission_callback'=>function(){return current_user_can(INO_Platform_Grants::VIEW);}));
    }
    public static function public_api() {
        global $wpdb;
        $rows=$wpdb->get_results("SELECT program_name,agency,category,deadline,program_url FROM {$wpdb->prefix}ino_grants WHERE is_public=1 AND verification_status='Verified' ORDER BY deadline IS NULL, deadline LIMIT 50",ARRAY_A);
        return rest_ensure_response($rows);
    }
    public static function summary_api() {
        global $wpdb;
        $out=array();
        foreach (array('opportunities','projects','matches','applications','compliance') as $kind) {
            $table=self::table($kind);
            $out[$kind]=(int)$wpdb->get_var("SELECT COUNT(*) FROM {$table}");
        }
        return rest_ensure_response($out);
    }
}
