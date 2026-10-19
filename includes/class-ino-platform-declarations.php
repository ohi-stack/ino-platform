<?php
/**
 * INO Declarations & Affidavits — public informational page and read-only
 * projection of explicitly published institutional document metadata.
 * Author/originator: One Gregory Onegodian.
 */
if (!defined('ABSPATH')) { exit; }

final class INO_Platform_Declarations {
    public static function kinds() {
        return array(
            'declaration'=>'Declaration',
            'affidavit'=>'Affidavit (sworn or affirmed)',
            'attestation'=>'Attestation / Witness Statement',
            'notice'=>'Public Institutional Notice'
        );
    }
    public static function areas() {
        return array(
            'identity'=>array('Identity & Personal Declarations','Document personal statements, names, voluntary religious affiliation and identity-related testimony. A self-declaration is not independent civil or genealogical verification.'),
            'ancestry'=>array('Ancestry & Heritage Affidavits','Preserve family testimony, generational memories, surnames, historical sources and evidence with respect for privacy. An affidavit does not establish third-party tribal enrollment.'),
            'ecclesiastical'=>array('Religious & Ecclesiastical Declarations','Record religious principles, commitments and internal ecclesiastical administration within the organization.'),
            'land'=>array('Land & Conservation Declarations','Preserve historical land research, cultural associations, conservation proposals and landowner communications. A declaration does not establish title or negate land-use requirements.'),
            'governance'=>array('Governance & Institutional Declarations','Publish decisions and statements only where a duly authorized body or officer adopted them under applicable internal procedures.'),
            'testimony'=>array('Witness & Historical Testimony','Archive firsthand observations, oral histories and interpretations, distinguishing direct observation from information received from others.')
        );
    }
    public static function init() {
        add_shortcode('ino_declarations_affidavits',array(__CLASS__,'render'));
        add_shortcode('ino_declarations_directory',array(__CLASS__,'directory'));
        add_action('wp_enqueue_scripts',array(__CLASS__,'assets'));
    }
    public static function install() {
        // Do not overwrite an existing page, title, slug, or previously authored content.
        $parent=get_page_by_path('documents',OBJECT,'page');
        if(!$parent){
            $id=wp_insert_post(array('post_type'=>'page','post_status'=>'publish','post_title'=>'Documents',
                'post_name'=>'documents','post_content'=>'[ino_documents]','comment_status'=>'closed'),true);
            if(!is_wp_error($id)){$parent=get_post($id);}
        }
        if(!$parent){return;}
        if(!get_page_by_path('documents/affidavits-and-declarations',OBJECT,'page')){
            wp_insert_post(array('post_type'=>'page','post_parent'=>(int)$parent->ID,'post_status'=>'publish',
                'post_title'=>'Declarations & Affidavits','post_name'=>'affidavits-and-declarations',
                'post_content'=>'[ino_declarations_affidavits]','comment_status'=>'closed'),true);
        }
    }
    public static function assets() {
        if(!is_singular('page')){return;}
        global $post;
        if(!$post||(!has_shortcode($post->post_content,'ino_declarations_affidavits') && !has_shortcode($post->post_content,'ino_declarations_directory'))){return;}
        $css='assets/css/ino-declarations.css';
        wp_enqueue_style('ino-declarations',INO_PLATFORM_URL.$css,array(),file_exists(INO_PLATFORM_PATH.$css)?(string)filemtime(INO_PLATFORM_PATH.$css):INO_PLATFORM_VERSION);
    }
    private static function published($limit=50) {
        global $wpdb;
        // The public projection never reads private notes, testimony, attachments,
        // oath references, real names, membership fields, or source document URLs.
        return $wpdb->get_results($wpdb->prepare(
            "SELECT record_code,document_title,version,declaration_kind,declaration_area,
                    statement_basis,execution_date,reviewed_at,published_at,execution_method
             FROM {$wpdb->prefix}ino_documents
             WHERE LOWER(status)=%s AND confidentiality=%s
               AND public_listing=1 AND declaration_kind IN ('declaration','affidavit','attestation','notice')
               AND (declaration_kind<>'affidavit' OR (execution_method='sworn_affirmed'
                    AND oath_reference<>'' AND reviewed_by IS NOT NULL))
             ORDER BY published_at DESC,id DESC LIMIT %d",
            'published','public',$limit
        ));
    }
    public static function directory() {
        $kind=isset($_GET['ino_decl_kind'])?sanitize_key(wp_unslash($_GET['ino_decl_kind'])):'';
        if($kind&&!isset(self::kinds()[$kind])){$kind='';}
        $rows=self::published();
        $html='<div class="ino-da-directory" id="ino-da-directory"><div class="ino-da-section-head"><div><p class="ino-da-kicker">Public institutional register</p><h2>Published declarations and affidavits</h2></div><span>Approval-controlled records</span></div>';
        $html.='<form method="get" class="ino-da-search" aria-label="Filter public declarations"><label>Document type <select name="ino_decl_kind"><option value="">All types</option>';
        foreach(self::kinds() as $v=>$label){$html.='<option value="'.esc_attr($v).'" '.selected($kind,$v,false).'>'.esc_html($label).'</option>';}
        $html.='</select></label><button type="submit" class="ino-da-btn ino-da-btn-outline">Filter published records</button></form>';
        $count=0;
        foreach($rows as $row){
            if($kind && $row->declaration_kind!==$kind){continue;}
            $count++;
            $area=self::areas()[$row->declaration_area][0]??'Institutional record';
            $kind_label=self::kinds()[$row->declaration_kind]??'Statement';
            $basis_labels=array('facts'=>'Personal knowledge / facts','belief'=>'Belief or conviction',
                'interpretation'=>'Historical interpretation','institutional'=>'Institutional position',
                'testimony'=>'Reported testimony','mixed'=>'Mixed statement');
            $basis=$basis_labels[$row->statement_basis]??'Basis not specified';
            $html.='<article class="ino-da-record"><div><span class="ino-da-tag">'.esc_html($kind_label).'</span> <span class="ino-da-tag">'.esc_html($area).'</span></div><h3>'.esc_html($row->document_title).'</h3><p>'.esc_html($basis).'</p><p class="ino-da-meta">Record: '.esc_html($row->record_code).' · Version '.esc_html($row->version).($row->execution_date?' · Dated '.esc_html($row->execution_date):'').'</p><p class="ino-da-verified">Published after INO internal administrative review'.($row->declaration_kind==='affidavit'?' · Oath/affirmation evidence recorded and internally reviewed':'').'</p></article>';
        }
        if(!$count){$html.='<div class="ino-da-empty"><h3>No matching public records</h3><p>No declarations or affidavits have been approved for display in this category. Drafts, private testimony and unsigned statements are never auto-published.</p></div>';}
        return $html.'</div>';
    }
    public static function render() {
        $html='<section class="ino-da"><header class="ino-da-hero"><span class="ino-da-pill">Indigenous Nation of Onegodia • Documents • Public Records</span><h1>Declarations &amp; Affidavits</h1><p class="ino-da-lead">Documenting facts, preserving testimony, and maintaining accountable institutional records.</p><p>Explore how statements are classified, reviewed, and archived, and discover declarations expressly approved for public access.</p><div class="ino-da-hero-actions"><a class="ino-da-btn" href="#ino-da-directory">View Public Records</a><a class="ino-da-btn ino-da-btn-ghost" href="#ino-da-guidance">Learn How Records Work</a></div></header>';
        $html.='<div class="ino-da-intro"><p class="ino-da-kicker">The standard for every statement</p><h2>Know what a document actually establishes.</h2><p>An affidavit is a written statement sworn or affirmed before someone legally authorized to administer an oath. A declaration is a written statement which may or may not carry legal effect under applicable law. An attestation records a witness’s confirmation. INO archival registration preserves an internal record; it is not itself notarization, governmental certification, real-property title, or external tribal recognition.</p></div>';
        $html.='<div class="ino-da-feature-grid">';
        foreach(array(
            array('file-check','Affidavits','Statements sworn or affirmed before a legally authorized official, with the required execution record retained outside the public index.'),
            array('scroll-text','Declarations','Personal or institutional statements identified as fact, belief, interpretation, or an adopted organizational position.'),
            array('book-open','Historical Testimony','Family histories, witnessed events, oral accounts and citations preserved with context and provenance.')
        ) as $item){
            $html.='<article class="ino-da-feature"><span class="ino-da-feature-mark" aria-hidden="true">✦</span><h3>'.esc_html($item[1]).'</h3><p>'.esc_html($item[2]).'</p></article>';
        }
        $html.='</div><div class="ino-da-section-head"><div><p class="ino-da-kicker">Organized by subject</p><h2>Six declaration categories</h2></div></div><div class="ino-da-categories">';
        $n=0;
        foreach(self::areas() as $key=>$area) {
            $html.='<article class="ino-da-category"><span class="ino-da-category-number">'.esc_html(sprintf('%02d',++$n)).'</span><h3>'.esc_html($area[0]).'</h3><p>'.esc_html($area[1]).'</p></article>';
        }
        $html.='</div>'.self::directory();
        $html.='<section id="ino-da-guidance" class="ino-da-guidance"><p class="ino-da-kicker">Submission to public register</p><h2>How a document moves through review</h2><ol><li><strong>Prepare:</strong> Identify title, author, purpose, factual basis, supporting records and applicable execution formalities.</li><li><strong>Submit privately:</strong> The INO records office receives metadata and authorized source references through the restricted administrative workflow.</li><li><strong>Review:</strong> Separate staff evaluate provenance, wording, authority and, for an affidavit, independently documented oath/affirmation formalities.</li><li><strong>Approve publication:</strong> Only a public-classified record with separate publisher authorization appears in the public index.</li><li><strong>Preserve:</strong> Retain revisions and record history. Sensitive evidence and confidential family information remain protected.</li></ol><p>At present, this module manages document metadata and controlled publication. Secure original-document submission, protected attachments, external attestation validation, and certified downloads remain separate development requirements.</p></section>';
        $html.='<footer class="ino-da-footer"><h2>Preserve the record. Protect the evidence.</h2><p>Statements published by INO do not automatically establish ownership, recognition, immunity, membership in another nation, or official action by an external authority. Records intended for courts or governmental proceedings must meet the applicable jurisdiction’s requirements.</p><a class="ino-da-btn" href="'.esc_url(home_url('/ino-document-registry/')).'">Explore the Document Registry</a></footer></section>';
        return $html;
    }
}
