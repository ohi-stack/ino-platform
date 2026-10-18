<?php
/**
 * INO Records Register — actual records and a separate requirement catalogue.
 * No source documents are silently imported, approved, or published.
 * Author / originator: One Gregory Onegodian.
 */
if (!defined('ABSPATH')) { exit; }

final class INO_Platform_Documents {
    const SCHEMA = '2026-10-18.3';
    private static function categories() {
        return array(
            'Formation & Institutional Status' => array(
                'Connecticut Certificate of Formation / Incorporation',
                'Certified Current Standing / Status Verification',
                'Founding Chronology and Signatory Identification',
                'Institutional Purpose and Public Status Statement',
                'Religious Society / Voluntary Association Organizational Record',
                'External Tax Status / Determination Evidence (if applicable)'
            ),
            'Constitution & Governance' => array(
                'Constitution — Adopted Text and Amendments',
                'Governance Charter and Organizational Bylaws',
                'Chief / Officer Appointment and Authority Record',
                'Council Structure, Membership, and Delegation Matrix',
                'Meeting Notices, Agendas, and Attendance',
                'Approved Meeting Minutes and Voting Records',
                'Resolutions, Actions, and Official Decisions',
                'Signature Authority / Records Authentication Policy',
                'Conflict of Interest Policy and Disclosures',
                'Internal Governance Complaints and Appeals Procedure'
            ),
            'Membership, Identity & Family' => array(
                'Membership Rules, Eligibility, and Enrollment Policy',
                'Membership Application and Written Consent',
                'Application Review, Decision, and Appeal Record',
                'Membership Directory Public-Consent Record',
                'Credential Issuance, Expiry, and Revocation Policy',
                'Family Tree / Ancestry Source-Citation Record',
                'Oral History Contributor Permission and Access Record',
                'Identity and Heritage Affidavits / Personal Declarations'
            ),
            'Treasury, Banking & Grants' => array(
                'Treasury Charter and Office Authority',
                'Financial Policies and Procedures Manual',
                'Internal Controls and Segregation of Duties Policy',
                'Annual Budget and Budget Resolution',
                'Banking Resolution and Authorized Signers',
                'Treasury Operations Manual and Officer Appointment',
                'Grant Management Policy and Grant Opportunity Register',
                'Grant Application Authority Resolution',
                'Grant Award, Agreement, Budget and Reporting File',
                'Procurement Policy, Vendor Selection and Contracts',
                'Financial Reports, Reconciliations, and Supporting Receipts'
            ),
            'Land, Housing & Conservation' => array(
                'Land Research and Provenance Dossier',
                'Property Ownership / Title and Deed Due Diligence',
                'Landowner Consent and Access Permission',
                'Conservation Stewardship and Partnership Agreements',
                'Preliminary Environmental and Site Review',
                'Housing Needs Assessment and Project Concept',
                'Housing Readiness Binder and Project Authorizations',
                'Site Control, Financing, and Development Partner Records',
                'Municipal Applications, Permits, and Decisions'
            ),
            'Programs, Community & Culture' => array(
                'Program Charter, Eligibility and Service Procedures',
                'Volunteer Application, Waivers, and Service Logs',
                'Education and Language Source Attribution Register',
                'Historical Testimony and Research Source Register',
                'Community Partnership Memoranda and Permissions',
                'Publication Releases, Image Rights, and Media Consents'
            ),
            'Records, Privacy & Compliance' => array(
                'Document and Records Retention Policy',
                'Records Access, Classification and Redaction Policy',
                'Privacy Notice and Data Handling Standard',
                'Information Security, Incident and Breach Procedures',
                'Record Request, Correction and Disclosure Log',
                'ODIN Record Index, Provenance and Version History',
                'Public Notices and Publication Approval Record'
            ),
            'Declarations & Affidavits' => array(
                'Identity and Personal Declaration',
                'Ancestry and Heritage Affidavit',
                'Religious and Ecclesiastical Declaration',
                'Land and Conservation Declaration',
                'Governance and Institutional Declaration',
                'Witness and Historical Testimony / Attestation'
            )
        );
    }
    private static function checklist() {
        $rows=array();$i=0;
        foreach(self::categories() as $category=>$titles) {
            foreach($titles as $title) {
                $rows[sprintf('INO-REQ-%03d',++$i)]=array('category'=>$category,'title'=>$title);
            }
        }
        return $rows;
    }
    private static function can($ability) {
        return current_user_can('manage_options') || current_user_can('ino_documents_'.$ability);
    }
    private static function statuses() {
        return array('draft'=>'Draft','in_review'=>'Under Review','returned'=>'Returned for Revision','reviewed'=>'Internally Reviewed','published'=>'Published','archived'=>'Archived','filed'=>'Legacy Filed / Unassessed');
    }
    private static function classification() {
        return array('restricted'=>'Restricted / Internal','confidential'=>'Confidential / Sensitive','public'=>'Public (only after publication approval)');
    }
    public static function init() {
        add_action('admin_post_ino_doc_save',array(__CLASS__,'save'));
        add_action('admin_post_ino_doc_transition',array(__CLASS__,'transition'));
        add_action('admin_post_ino_doc_csv',array(__CLASS__,'export_csv'));
        add_shortcode('ino_documents',array(__CLASS__,'public_records'));
        add_shortcode('ino_documents_registry',array(__CLASS__,'public_records'));
        add_action('admin_menu',array(__CLASS__,'menu'),40);
        add_action('admin_enqueue_scripts',array(__CLASS__,'admin_assets'));
    }
    public static function install() {
        global $wpdb;require_once ABSPATH.'wp-admin/includes/upgrade.php';
        $p=$wpdb->prefix;$cc=$wpdb->get_charset_collate();
        // Additive evolution of the existing table; older 'Filed' records retain that value.
        dbDelta("CREATE TABLE {$p}ino_documents (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            document_title VARCHAR(190) NOT NULL,
            document_category VARCHAR(100) DEFAULT 'General',
            document_url TEXT NULL,
            version VARCHAR(40) DEFAULT '1.0',
            status VARCHAR(50) DEFAULT 'Filed',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            record_code VARCHAR(60) NOT NULL DEFAULT '',
            requirement_code VARCHAR(20) NOT NULL DEFAULT '',
            issuing_office VARCHAR(150) NOT NULL DEFAULT '',
            description TEXT NULL,
            source_reference TEXT NULL,
            execution_date DATE NULL,
            confidentiality VARCHAR(30) NOT NULL DEFAULT 'restricted',
            evidence_level VARCHAR(40) NOT NULL DEFAULT 'unreviewed',
            declaration_kind VARCHAR(30) NOT NULL DEFAULT '',
            declaration_area VARCHAR(30) NOT NULL DEFAULT '',
            statement_basis VARCHAR(30) NOT NULL DEFAULT '',
            execution_method VARCHAR(30) NOT NULL DEFAULT 'unrecorded',
            oath_reference TEXT NULL,
            public_listing TINYINT(1) NOT NULL DEFAULT 0,
            created_by BIGINT UNSIGNED NULL,
            updated_by BIGINT UNSIGNED NULL,
            reviewed_by BIGINT UNSIGNED NULL,
            reviewed_at DATETIME NULL,
            published_by BIGINT UNSIGNED NULL,
            published_at DATETIME NULL,
            updated_at DATETIME NULL,
            PRIMARY KEY  (id),
            KEY category (document_category),
            KEY status (status),
            KEY requirement (requirement_code),
            KEY confidentiality (confidentiality)
        ) $cc;");
        dbDelta("CREATE TABLE {$p}ino_document_events (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            document_id BIGINT UNSIGNED NOT NULL,
            actor_id BIGINT UNSIGNED NOT NULL,
            action VARCHAR(40) NOT NULL,
            before_status VARCHAR(50) NOT NULL DEFAULT '',
            after_status VARCHAR(50) NOT NULL DEFAULT '',
            notes VARCHAR(500) NOT NULL DEFAULT '',
            happened_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY doc_time (document_id,happened_at),
            KEY actor_id (actor_id)
        ) $cc;");
        $roles=array(
            'ino_records_officer'=>array('read'=>true,'ino_documents_access'=>true,'ino_documents_manage'=>true),
            'ino_records_reviewer'=>array('read'=>true,'ino_documents_access'=>true,'ino_documents_review'=>true),
            'ino_records_publisher'=>array('read'=>true,'ino_documents_access'=>true,'ino_documents_publish'=>true)
        );
        foreach($roles as $slug=>$caps){
            if(!get_role($slug)){add_role($slug,ucwords(str_replace('_',' ',$slug)),$caps);}
            $role=get_role($slug);if($role){foreach($caps as $cap=>$allowed){if($allowed){$role->add_cap($cap);}}}
        }
        $admin=get_role('administrator');
        if($admin){foreach(array('ino_documents_access','ino_documents_manage','ino_documents_review','ino_documents_publish') as $cap){$admin->add_cap($cap);}}
        update_option('ino_documents_schema',self::SCHEMA,false);
        // Deliberately DO NOT seed checklist items as existing or adopted documents.
    }
    private static function record($id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}ino_documents WHERE id=%d",$id));
    }
    private static function log($id,$action,$before,$after,$note='') {
        global $wpdb;
        $wpdb->insert($wpdb->prefix.'ino_document_events',array('document_id'=>$id,'actor_id'=>get_current_user_id(),
            'action'=>$action,'before_status'=>$before,'after_status'=>$after,'notes'=>substr($note,0,500),'happened_at'=>current_time('mysql')));
    }
    private static function redirect($message,$id=0) {
        $slug=current_user_can('manage_options')?'ino-platform-documents':'ino-documents-workspace';
        $url=admin_url('admin.php?page='.$slug);
        wp_safe_redirect(add_query_arg(array('doc_notice'=>$message,'doc_id'=>$id?:null),$url));exit;
    }
    private static function input($name,$length=190) {
        if(!isset($_POST[$name]) || !is_string($_POST[$name])) {return '';}
        return substr(sanitize_text_field(wp_unslash($_POST[$name])),0,$length);
    }
    public static function save() {
        if(!self::can('manage')) {wp_die('Access denied',403);}
        check_admin_referer('ino_doc_save');
        global $wpdb;$id=absint($_POST['doc_id']??0);
        $old=$id?self::record($id):null;
        if($id && !$old) {wp_die('Document not found',404);}
        if($old && !in_array(strtolower($old->status),array('draft','returned','filed'),true)) {
            self::redirect('locked',$id);
        }
        $title=self::input('document_title');
        $requirement=self::input('requirement_code',20);
        $list=self::checklist();
        if($requirement && !isset($list[$requirement])) {wp_die('Invalid requirement identifier',400);}
        if(!$title){self::redirect('missing_title',$id);}
        $category=self::input('document_category',100);
        if(!array_key_exists($category,self::categories())) {wp_die('Invalid document category',400);}
        $conf=self::input('confidentiality',30);
        if(!isset(self::classification()[$conf])) {wp_die('Invalid access classification',400);}
        $date=self::input('execution_date',10);
        if($date && (!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$date)||date('Y-m-d',strtotime($date))!==$date)) {
            self::redirect('invalid_date',$id);
        }
        // Classification and execution metadata are not independent proof.
        $kind=self::input('declaration_kind',30);
        $areas=INO_Platform_Declarations::areas();
        $allowed_kinds=INO_Platform_Declarations::kinds();
        if($kind!=='' && !isset($allowed_kinds[$kind])){wp_die('Invalid declaration type',400);}
        $area=self::input('declaration_area',30);
        if($area!==''&&!isset($areas[$area])){wp_die('Invalid declaration subject',400);}
        $basis=self::input('statement_basis',30);
        if($basis!==''&&!in_array($basis,array('facts','belief','interpretation','institutional','testimony','mixed'),true)){wp_die('Invalid statement basis',400);}
        $execution=self::input('execution_method',30);
        if(!in_array($execution,array('unrecorded','unsigned','signed_reported','sworn_affirmed'),true)){$execution='unrecorded';}
        if($kind!=='affidavit' && $execution==='sworn_affirmed'){$execution='unrecorded';}
        $oath=$kind==='affidavit' && isset($_POST['oath_reference']) && is_string($_POST['oath_reference'])
            ? substr(sanitize_textarea_field(wp_unslash($_POST['oath_reference'])),0,2000):'';
        $public_listing=($kind!=='' && $conf==='public' && isset($_POST['public_listing']))?1:0;
        if($kind===''){$area='';$basis='';$execution='unrecorded';$oath='';}
        $data=array('document_title'=>$title,'document_category'=>$category,'requirement_code'=>$requirement,
            'issuing_office'=>self::input('issuing_office',150),'version'=>self::input('version',40)?:'1.0',
            'description'=>isset($_POST['description'])?sanitize_textarea_field(wp_unslash($_POST['description'])):'',
            'source_reference'=>isset($_POST['source_reference'])?sanitize_textarea_field(wp_unslash($_POST['source_reference'])):'',
            'execution_date'=>$date?:null,'confidentiality'=>$conf,
            'declaration_kind'=>$kind,'declaration_area'=>$area,'statement_basis'=>$basis,
            'execution_method'=>$execution,'oath_reference'=>$oath,'public_listing'=>$public_listing,
            'evidence_level'=>'unreviewed','reviewed_by'=>null,'reviewed_at'=>null,
            'updated_at'=>current_time('mysql'),'updated_by'=>get_current_user_id());
        if($old) {
            $data['status']='draft';
            if (!(int)$old->created_by) { $data['created_by']=get_current_user_id(); }
            $ok=$wpdb->update($wpdb->prefix.'ino_documents',$data,array('id'=>$id));
            if($ok===false){self::redirect('db_error',$id);}
            self::log($id,'revised',$old->status,'draft','Existing review invalidated by revision');
        } else {
            $data['created_by']=get_current_user_id();
            $data['created_at']=current_time('mysql');$data['status']='draft';
            $ok=$wpdb->insert($wpdb->prefix.'ino_documents',$data);
            if(!$ok){self::redirect('db_error');}
            $id=(int)$wpdb->insert_id;
            $wpdb->update($wpdb->prefix.'ino_documents',array('record_code'=>sprintf('INO-DOC-%06d',$id)),array('id'=>$id));
            self::log($id,'created','','draft','Administrative metadata entry; original document not attached');
        }
        self::redirect('saved',$id);
    }
    public static function transition() {
        check_admin_referer('ino_doc_transition');
        $id=absint($_POST['doc_id']??0);$old=self::record($id);
        if(!$old){wp_die('Document not found',404);}
        $action=self::input('decision',20);
        $current=strtolower($old->status);
        $dest='';
        if($action==='submit' && self::can('manage') && in_array($current,array('draft','returned'),true)){$dest='in_review';}
        if($action==='return' && self::can('review') && $current==='in_review' && (int)$old->created_by!==get_current_user_id()){$dest='returned';}
        $affidavit_ready=($old->declaration_kind!=='affidavit' ||
            ($old->execution_method==='sworn_affirmed' && trim((string)$old->oath_reference)!==''));
        if($action==='review' && self::can('review') && $current==='in_review' &&
            (int)$old->created_by!==get_current_user_id() && trim((string)$old->source_reference)!=='' &&
            $affidavit_ready){$dest='reviewed';}
        if($action==='publish' && self::can('publish') && $current==='reviewed' && $old->confidentiality==='public' &&
            ($old->declaration_kind==='' || (int)$old->public_listing===1) && $affidavit_ready &&
           (int)$old->created_by!==get_current_user_id() && (int)$old->reviewed_by!==get_current_user_id()){$dest='published';}
        if($action==='archive' && self::can('publish') && $current==='published'){$dest='archived';}
        if(!$dest){self::redirect('forbidden',$id);}
        global $wpdb;
        $data=array('status'=>$dest,'updated_at'=>current_time('mysql'),'updated_by'=>get_current_user_id());
        if($dest==='reviewed'){$data['reviewed_at']=current_time('mysql');$data['reviewed_by']=get_current_user_id();$data['evidence_level']='internal_review';}
        if($dest==='published'){$data['published_at']=current_time('mysql');$data['published_by']=get_current_user_id();}
        // Atomic compare-and-set resists stale concurrent review/approval submissions.
        $where=array('id'=>$id,'status'=>$old->status);
        $updated=$wpdb->update($wpdb->prefix.'ino_documents',$data,$where);
        if($updated!==1){self::redirect('stale',$id);}
        self::log($id,$action,$old->status,$dest);
        self::redirect('transitioned',$id);
    }
    private static function csv_cell($value) {
        $str=(string)($value??'');
        // Prevent spreadsheet apps from executing cell formulas.
        if(preg_match('/^[\s\x00-\x1F]*[=+@\-]/u',$str)){$str="'".$str;}
        return $str;
    }
    public static function export_csv() {
        if(!self::can('manage')){wp_die('Access denied',403);}
        check_admin_referer('ino_doc_csv');
        global $wpdb;
        $rows=$wpdb->get_results("SELECT id,record_code,requirement_code,document_title,document_category,version,status,confidentiality,issuing_office,execution_date,evidence_level,created_at,updated_at FROM {$wpdb->prefix}ino_documents ORDER BY id DESC",ARRAY_A);
        nocache_headers();header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="ino-documents-register-'.gmdate('Ymd').'.csv"');
        $out=fopen('php://output','w');
        $columns=array('id','record_code','requirement_code','document_title','document_category','version','status','confidentiality','issuing_office','execution_date','evidence_level','created_at','updated_at');
        fputcsv($out,$columns);
        foreach($rows as $r){fputcsv($out,array_map(array(__CLASS__,'csv_cell'),array_values($r)));}
        fclose($out);exit;
    }
    private static function post_form($action,$doc_id,$label,$class='button button-secondary',$note='') {
        $url=admin_url('admin-post.php');
        return '<form method="post" action="'.esc_url($url).'" style="display:inline-block;margin:2px 4px 2px 0">'
           .'<input type="hidden" name="action" value="ino_doc_transition">'
           .'<input type="hidden" name="doc_id" value="'.(int)$doc_id.'">'
           .'<input type="hidden" name="decision" value="'.esc_attr($action).'">'
           .wp_nonce_field('ino_doc_transition','_wpnonce',true,false)
           .'<button class="'.esc_attr($class).'" type="submit" title="'.esc_attr($note).'">'.esc_html($label).'</button></form>';
    }
    public static function menu() {
        add_menu_page('INO Documents','INO Documents','ino_documents_access','ino-documents-workspace',array(__CLASS__,'page'),'dashicons-media-document',24);
    }
    public static function admin_assets($hook) {
        if(strpos((string)$hook,'ino-documents-workspace')===false &&
           strpos((string)$hook,'ino-platform-documents')===false){return;}
        $css='assets/css/ino-documents-admin.css';
        wp_enqueue_style('ino-documents-admin',INO_PLATFORM_URL.$css,array(),
            file_exists(INO_PLATFORM_PATH.$css)?(string)filemtime(INO_PLATFORM_PATH.$css):INO_PLATFORM_VERSION);
    }
    public static function page() {
        if(!(self::can('manage')||self::can('review')||self::can('publish'))){wp_die('Access denied',403);}
        global $wpdb;
        $all=self::checklist();
        $category_filter=isset($_GET['category'])?sanitize_text_field(wp_unslash($_GET['category'])):'';
        $status_filter=isset($_GET['doc_status'])?sanitize_key(wp_unslash($_GET['doc_status'])):'';
        $search=isset($_GET['doc_search'])?sanitize_text_field(wp_unslash($_GET['doc_search'])):'';
        $where=array('1=1');$params=array();
        if($category_filter && isset(self::categories()[$category_filter])){$where[]='document_category=%s';$params[]=$category_filter;}
        if($status_filter && isset(self::statuses()[$status_filter])){$where[]='LOWER(status)=%s';$params[]=$status_filter;}
        if($search!==''){$where[]='document_title LIKE %s';$params[]='%'.$wpdb->esc_like($search).'%';}
        $sql="SELECT * FROM {$wpdb->prefix}ino_documents WHERE ".implode(' AND ',$where)." ORDER BY created_at DESC,id DESC LIMIT 200";
        $records=$wpdb->get_results($params?$wpdb->prepare($sql,$params):$sql);
        $counts=$wpdb->get_results("SELECT LOWER(status) AS status,COUNT(*) AS total FROM {$wpdb->prefix}ino_documents GROUP BY LOWER(status)");
        $totals=array();foreach($counts as $entry){$totals[$entry->status]=(int)$entry->total;}
        $record_requirements=$wpdb->get_col("SELECT DISTINCT requirement_code FROM {$wpdb->prefix}ino_documents WHERE requirement_code<>''");
        $registered=array_fill_keys($record_requirements,true);
        $edit_id=absint($_GET['edit']??0);$edit=$edit_id?self::record($edit_id):null;
        $template=isset($_GET['requirement'])?sanitize_text_field(wp_unslash($_GET['requirement'])):'';
        if(!isset($all[$template])){$template='';}
        $notice=isset($_GET['doc_notice'])?sanitize_key(wp_unslash($_GET['doc_notice'])):'';
        $messages=array('saved'=>'Record saved as a draft.','transitioned'=>'Workflow action recorded.',
            'locked'=>'This record is under review or has been published; it cannot be silently edited.','missing_title'=>'Enter a document title.',
            'invalid_date'=>'Enter a valid ISO date (YYYY-MM-DD).','forbidden'=>'Transition rejected: check permissions, state, confidentiality and separation of duties.',
            'stale'=>'Another update changed this record; refresh and retry.','db_error'=>'The record was not saved.');
        echo '<div class="wrap ino-doc-admin"><h1>INO Documents & Records</h1><p>Required-document checklist and controlled administrative record register. Requirements are not evidence of adopted, signed, filed, or uploaded records.</p>';
        if(isset($messages[$notice])){echo '<div class="notice notice-info"><p>'.esc_html($messages[$notice]).'</p></div>';}
        echo '<div class="ino-doc-stats"><div><strong>'.(int)array_sum($totals).'</strong><span>Recorded items</span></div><div><strong>'.count($all).'</strong><span>Required document types</span></div><div><strong>'.(int)count($registered).'</strong><span>Requirement types registered</span></div><div><strong>'.(int)($totals['published']??0).'</strong><span>Published records</span></div></div>';
        echo '<h2>1. Required document checklist</h2><p>These are requirements and recommended records, not automatically created documents. "Not registered" means no linked metadata record is saved in this module.</p>';
        foreach(self::categories() as $category=>$titles){
            echo '<details class="ino-doc-group"><summary>'.esc_html($category).' — '.count($titles).' types</summary><table class="widefat striped"><thead><tr><th>Requirement</th><th>Registry status</th><th>Action</th></tr></thead><tbody>';
            foreach($all as $code=>$entry){
                if($entry['category']!==$category){continue;}
                echo '<tr><td>'.esc_html($entry['title']).'<br><small>'.esc_html($code).'</small></td><td>'.(isset($registered[$code])?'Metadata registered':'Not registered').'</td><td>';
                if(self::can('manage')){echo '<a href="'.esc_url(add_query_arg(array('page'=>'ino-platform-documents','requirement'=>$code),admin_url('admin.php'))).'#ino-document-editor">Prepare record</a>';}
                echo '</td></tr>';
            }
            echo '</tbody></table></details>';
        }
        echo '<h2>2. Document register</h2><form method="get" class="ino-doc-filter"><input type="hidden" name="page" value="'.esc_attr(current_user_can('manage_options')?'ino-platform-documents':'ino-documents-workspace').'"><label>Search <input name="doc_search" value="'.esc_attr($search).'" placeholder="Document title"></label><label>Category <select name="category"><option value="">All categories</option>';
        foreach(self::categories() as $cat=>$v){echo '<option '.selected($category_filter,$cat,false).' value="'.esc_attr($cat).'">'.esc_html($cat).'</option>';}
        echo '</select></label><label>Status <select name="doc_status"><option value="">All statuses</option>';
        foreach(self::statuses() as $st=>$label){echo '<option '.selected($status_filter,$st,false).' value="'.esc_attr($st).'">'.esc_html($label).'</option>';}
        echo '</select></label><button class="button button-primary">Filter</button></form>';
        if(self::can('manage')){echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="ino_doc_csv">'.wp_nonce_field('ino_doc_csv','_wpnonce',true,false).'<button class="button">Export register CSV (metadata only)</button></form>';}
        echo '<div style="overflow-x:auto"><table class="widefat striped"><thead><tr><th>Registry ID</th><th>Document</th><th>Category</th><th>Status</th><th>Access</th><th>Actions</th></tr></thead><tbody>';
        if(!$records){echo '<tr><td colspan="6">No documents match. Prepare a requirement record below; nothing has been adopted automatically.</td></tr>';}
        foreach($records as $row){
            $status=strtolower($row->status);$conf=$row->confidentiality?:'restricted';
            echo '<tr><td>'.esc_html($row->record_code ?: 'LEGACY-'.$row->id).'</td><td><strong>'.esc_html($row->document_title).'</strong><br><small>v'.esc_html($row->version).' • '.esc_html($row->requirement_code).'</small></td><td>'.esc_html($row->document_category).'</td><td>'.esc_html(self::statuses()[$status]??$row->status).'</td><td>'.esc_html(self::classification()[$conf]??$conf).'</td><td>';
            if(self::can('manage')&&in_array($status,array('draft','returned','filed'),true)){echo '<a class="button" href="'.esc_url(add_query_arg(array('page'=>'ino-platform-documents','edit'=>$row->id),admin_url('admin.php'))).'#ino-document-editor">Edit</a> ';}
            if(self::can('manage') && in_array($status,array('draft','returned'),true)){echo self::post_form('submit',$row->id,'Submit review');}
            if(self::can('review') && $status==='in_review' && (int)$row->created_by!==get_current_user_id()){
                echo self::post_form('review',$row->id,'Mark reviewed');echo self::post_form('return',$row->id,'Return');
            }
            if(self::can('publish') && $status==='reviewed' && $conf==='public' &&
                ($row->declaration_kind==='' || (int)$row->public_listing===1) &&
                ($row->declaration_kind!=='affidavit' || ($row->execution_method==='sworn_affirmed' && trim((string)$row->oath_reference)!=='')) &&
                (int)$row->created_by!==get_current_user_id() && (int)$row->reviewed_by!==get_current_user_id()) {
                echo self::post_form('publish',$row->id,'Publish','button button-primary');
            }
            if(self::can('publish') && $status==='published'){echo self::post_form('archive',$row->id,'Archive');}
            echo '</td></tr>';
        }
        echo '</tbody></table></div>';
        if(self::can('manage')) {
            $chosen=$edit?($edit->requirement_code??''):$template;
            $cat=$edit?($edit->document_category??''):($template?$all[$template]['category']:'Formation & Institutional Status');
            $title=$edit?($edit->document_title??''):($template?$all[$template]['title']:'');
            echo '<section id="ino-document-editor" class="ino-doc-editor"><h2>3. '.($edit?'Edit draft / legacy record':'Prepare a new document record').'</h2><p>Save metadata without declaring the document executed, approved, or publicly filed. Attachments and original files are not yet stored by this module.</p>';
            echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="ino_doc_save"><input type="hidden" name="doc_id" value="'.(int)($edit->id??0).'">'.wp_nonce_field('ino_doc_save','_wpnonce',true,false);
            echo '<div class="ino-doc-fields"><label>Document title *<input name="document_title" required maxlength="190" value="'.esc_attr($title).'"></label><label>Requirement mapping <select name="requirement_code"><option value="">Unmapped / other</option>';
            foreach($all as $code=>$entry){echo '<option value="'.esc_attr($code).'" '.selected($chosen,$code,false).'>'.esc_html($code.' — '.$entry['title']).'</option>';}
            echo '</select></label><label>Category <select name="document_category">';
            foreach(self::categories() as $k=>$v){echo '<option value="'.esc_attr($k).'" '.selected($cat,$k,false).'>'.esc_html($k).'</option>';}
            echo '</select></label><label>Version<input maxlength="40" name="version" value="'.esc_attr($edit->version??'1.0').'"></label><label>Issuing office<input maxlength="150" name="issuing_office" value="'.esc_attr($edit->issuing_office??'').'" placeholder="Office of the Chief / Treasury / Records"></label><label>Execution date (if actually signed)<input name="execution_date" type="date" value="'.esc_attr($edit->execution_date??'').'"></label><label>Confidentiality <select name="confidentiality">';
            foreach(self::classification() as $k=>$label){echo '<option value="'.esc_attr($k).'" '.selected($edit->confidentiality??'restricted',$k,false).'>'.esc_html($label).'</option>';}
            echo '</select></label><label>Instrument type <select name="declaration_kind"><option value="">General document / not a statement</option>';
            foreach(INO_Platform_Declarations::kinds() as $code=>$name){
                echo '<option value="'.esc_attr($code).'" '.selected($edit->declaration_kind??'',$code,false).'>'.esc_html($name).'</option>';
            }
            echo '</select></label><label>Statement subject <select name="declaration_area"><option value="">Not classified</option>';
            foreach(INO_Platform_Declarations::areas() as $code=>$subject){
                echo '<option value="'.esc_attr($code).'" '.selected($edit->declaration_area??'',$code,false).'>'.esc_html($subject[0]).'</option>';
            }
            echo '</select></label><label>Statement basis <select name="statement_basis"><option value="">Not recorded</option>';
            foreach(array('facts'=>'Personal knowledge / facts','belief'=>'Personal belief','interpretation'=>'Historical interpretation','institutional'=>'Institutional position','testimony'=>'Reported testimony','mixed'=>'Mixed') as $code=>$label){
                echo '<option value="'.esc_attr($code).'" '.selected($edit->statement_basis??'',$code,false).'>'.esc_html($label).'</option>';
            }
            echo '</select></label><label>Execution evidence state <select name="execution_method">';
            foreach(array('unrecorded'=>'Not recorded','unsigned'=>'Unsigned / draft','signed_reported'=>'Signature reported; not independently validated','sworn_affirmed'=>'Oath / affirmation reported; requires evidence review') as $code=>$label){
                echo '<option value="'.esc_attr($code).'" '.selected($edit->execution_method??'unrecorded',$code,false).'>'.esc_html($label).'</option>';
            }
            echo '</select></label><label class="ino-doc-wide">Oath/affirmation source reference (protected; for affidavits)<textarea name="oath_reference" rows="2">'.esc_textarea($edit->oath_reference??'').'</textarea></label>';
            echo '<label class="ino-doc-wide"><input type="checkbox" name="public_listing" value="1" '.checked((int)($edit->public_listing??0),1,false).'> Request listing in public Declarations &amp; Affidavits directory after review and publication</label>';
            echo '<label class="ino-doc-wide">Description and record purpose<textarea name="description" rows="3">'.esc_textarea($edit->description??'').'</textarea></label><label class="ino-doc-wide">Source/provenance reference (admin only; no sensitive URL required)<textarea name="source_reference" rows="3">'.esc_textarea($edit->source_reference??'').'</textarea></label></div><button type="submit" class="button button-primary">Save as draft</button></form></section>';
        }
        echo '<p class="ino-doc-note">Review status records an internal administrative action only. It is not notarization, government certification, proof of Indigenous affiliation, real-property title, or external legal recognition. Export is restricted to authorized staff and must not be posted publicly.</p></div>';
    }
    public static function public_records() {
        global $wpdb;
        $rows=$wpdb->get_results($wpdb->prepare("SELECT record_code,document_title,document_category,version,execution_date FROM {$wpdb->prefix}ino_documents WHERE LOWER(status)=%s AND confidentiality=%s ORDER BY published_at DESC LIMIT 50",'published','public'));
        $html='<section class="ino-shell"><div class="ino-heading"><span>Public INO records</span><h1>Documents & Archives</h1></div><div class="ino-card-public"><p>Only specifically authorized published metadata is shown. Protected, confidential, unreviewed and historical legacy items are not displayed.</p>';
        if(!$rows){$html.='<p class="ino-notice">No records are currently approved for public listing in this module.</p>';}
        else{$html.='<ul>';foreach($rows as $r){$html.='<li><strong>'.esc_html($r->document_title).'</strong> — '.esc_html($r->document_category).' ('.esc_html($r->record_code).', version '.esc_html($r->version).')</li>';}$html.='</ul>';}
        return $html.'</div></section>';
    }
}
