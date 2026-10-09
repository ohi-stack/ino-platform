<?php
if (!defined('ABSPATH')) { exit; }

class INO_Platform_Shortcodes {
    public static function init() {
        $map = array(
            'ino_portal'=>'portal','ino_about'=>'about','ino_membership'=>'membership','ino_citizenship'=>'citizenship',
            'ino_member_directory'=>'member_directory','ino_member_profile'=>'member_profile','ino_people_network'=>'people_network','ino_family_tree'=>'family_tree',
            'ino_identity_intro'=>'identity_intro','ino_identity_declaration'=>'identity_declaration','ino_identity_dashboard'=>'identity_dashboard',
            'ino_book_of_names'=>'book_of_names','ino_family_archives'=>'family_archives','ino_certificates'=>'certificates','ino_verify_identity'=>'verify_identity',
            'ino_identity_standards'=>'identity_standards','ino_treasury_grants'=>'treasury_grants','ino_housing'=>'housing','ino_documents'=>'documents',
            'ino_governance'=>'governance','ino_community'=>'community','ino_contact_form'=>'contact'
        );
        foreach ($map as $tag=>$method) { add_shortcode($tag, array(__CLASS__, $method)); }
        add_action('wp_enqueue_scripts', array(__CLASS__, 'assets'));
    }

    public static function assets() {
        wp_register_style('ino-platform-public', false, array(), INO_PLATFORM_VERSION);
        wp_enqueue_style('ino-platform-public');
        wp_add_inline_style('ino-platform-public', self::css());
        $style = 'assets/css/ino-public-platform.css';
        wp_enqueue_style(
            'ino-platform-public-design',
            INO_PLATFORM_URL . $style,
            array('ino-platform-public'),
            file_exists(INO_PLATFORM_PATH . $style) ? (string) filemtime(INO_PLATFORM_PATH . $style) : INO_PLATFORM_VERSION
        );
    }

    private static function css() {
        return '.ino-shell{max-width:1180px;margin:0 auto;padding:40px 20px}.ino-heading span{color:#C99A2E;font-size:12px;font-weight:800;letter-spacing:.12em;text-transform:uppercase}.ino-heading h1{color:#071B2D;font-size:42px;line-height:1.15;margin:10px 0 18px}.ino-hero-public{background:linear-gradient(135deg,#071B2D,#0D2B45,#0C1020);color:#fff;border-radius:20px;padding:54px}.ino-hero-public h1{color:#fff;margin-top:0}.ino-public-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px}.ino-public-grid>div,.ino-card-public{background:#fff;border:1px solid #dfe5ea;border-radius:15px;padding:24px}.ino-card-public img,.ino-avatar{border-radius:999px}.ino-cover{height:220px;background:#0D2B45 center/cover;border-radius:18px 18px 0 0}.ino-profile{border:1px solid #dfe5ea;border-radius:18px;overflow:hidden;background:#fff}.ino-profile-body{padding:0 28px 28px}.ino-profile-avatar{margin-top:-70px}.ino-profile-avatar img{border:5px solid #fff;border-radius:999px}.ino-btn{display:inline-block;padding:12px 18px;border-radius:8px;text-decoration:none;font-weight:800;border:0;cursor:pointer}.ino-btn-gold{background:#C99A2E;color:#071B2D}.ino-btn-navy{background:#0D2B45;color:#fff}.ino-form{display:grid;gap:14px}.ino-form label{font-weight:700;color:#102B45}.ino-form input,.ino-form textarea,.ino-form select{width:100%;padding:12px;border:1px solid #cbd5df;border-radius:8px;margin-top:6px}.ino-notice{background:#FAF4DF;border-left:4px solid #C99A2E;padding:16px;margin:18px 0}.ino-tree-list{display:grid;gap:12px}.ino-tree-item{background:#fff;border:1px solid #dfe5ea;border-radius:12px;padding:16px}.ino-muted{color:#64748b}@media(max-width:800px){.ino-public-grid{grid-template-columns:1fr}.ino-hero-public{padding:34px}.ino-heading h1{font-size:34px}}';
    }

    private static function simple($title,$body) {
        return '<section class="ino-shell"><div class="ino-heading"><span>Indigenous Nation of Onegodia</span><h1>'.esc_html($title).'</h1></div><div class="ino-card-public">'.$body.'</div></section>';
    }

    public static function portal() {
        $services = array(
            array('01', 'Membership', 'Learn about INO membership categories, participation, and administrative standards.', '/ino-membership/', 'View membership'),
            array('02', 'Identity & Heritage', 'Preserve personal, religious and ancestral information using appropriate evidence classifications.', '/choose-your-identity/', 'Explore identity'),
            array('03', 'Documents & Archives', 'Review the purpose of the INO document registry and protected institutional records.', '/ino-document-registry/', 'Explore records'),
            array('04', 'Treasury & Grants', 'Understand funding-pipeline tracking, grant administration and reporting objectives.', '/ino-treasury-grants/', 'Explore treasury'),
            array('05', 'Land & Housing', 'View housing-development planning and the organization’s stewardship initiatives.', '/ino-housing-development/', 'Explore housing'),
            array('06', 'Community Programs', 'Discover education, volunteer service and community development initiatives.', '/ino-community-programs/', 'View programs')
        );
        $html = '<section class="ino-shell ino-portal"><header class="ino-hero-public"><span class="ino-portal-eyebrow">Indigenous Nation of Onegodia · Digital Services</span><h1>A Connected Institutional Platform</h1><p class="ino-portal-lead">Explore membership, identity, cultural heritage, governance, records, community programs and development pathways within one evolving digital environment.</p><div class="ino-portal-actions"><a class="ino-btn ino-btn-gold" href="' . esc_url(home_url('/ino-membership/')) . '">Membership overview ↗</a><a class="ino-portal-outline" href="' . esc_url(home_url('/about-ino-platform/')) . '">About the platform</a></div><div class="ino-portal-strap"><span>Records &amp; identity</span><span>Community programs</span><span>Institutional accountability</span></div></header>';
        $html .= '<section class="ino-portal-section" aria-labelledby="ino-services-heading"><div class="ino-portal-section-head"><div><span class="ino-portal-label">Explore the ecosystem</span><h2 id="ino-services-heading">Platform workspaces</h2><p>Each service area has a defined purpose. Features that are not yet operational remain informational until independently verified.</p></div></div><div class="ino-service-grid">';
        foreach ($services as $service) {
            $html .= '<article class="ino-service-card"><span class="ino-service-symbol" aria-hidden="true">' . esc_html($service[0]) . '</span><h3>' . esc_html($service[1]) . '</h3><p>' . esc_html($service[2]) . '</p><a href="' . esc_url(home_url($service[3])) . '">' . esc_html($service[4]) . ' ↗</a></article>';
        }
        $html .= '</div></section><aside class="ino-portal-banner"><div><strong>Building reliable institutional services</strong><p>The INO Platform is under phased development. Membership or identity records do not independently confer civil citizenship, governmental recognition, or external tribal enrollment.</p></div><a class="ino-btn ino-btn-navy" href="' . esc_url(home_url('/identity-standards/')) . '">Review standards</a></aside></section>';
        return $html;
    }
    public static function about() { return self::simple('About the INO Platform','<p>The INO Platform is the central WordPress-based administration and service-delivery system for the Indigenous Nation of Onegodia. It connects public pages, secure member dashboards, and administrative control panels.</p>'); }
    public static function membership() { return self::simple('INO Membership','<p>Membership tools support applications, member numbers, classifications, status histories, profile records, and access to INO programs.</p>'); }
    public static function citizenship() { return self::simple('INO Citizenship','<p>This portal records voluntary INO enrollment and institutional membership classifications. It does not independently alter government-recognized civil citizenship or immigration status.</p>'); }
    public static function treasury_grants() { return self::simple('Treasury & Grants','<p>Track grant opportunities, funding pipelines, deadlines, proposals, awards, compliance, and closeout records.</p>'); }
    public static function housing() { return self::simple('Housing Development','<p>Track properties, readiness tasks, partners, funding sources, project milestones, construction phases, and occupancy status.</p>'); }
    public static function documents() { return self::simple('Document Registry','<p>Organize institutional documents, versions, approval records, classifications, and public or restricted access.</p>'); }
    public static function governance() { return self::simple('Governance','<p>Maintain constitutions, charters, bylaws, policies, resolutions, meeting records, and institutional history.</p>'); }
    public static function community() { return self::simple('Community Programs','<p>Coordinate education, cultural preservation, volunteer service, family support, housing, workforce, and economic-development initiatives.</p>'); }
    public static function contact() { return self::simple('Contact INO','<p>Use the official contact channels published by the Indigenous Nation of Onegodia.</p>'); }
    public static function identity_intro() { return self::simple('Honor Your Origin. Preserve Your Name. Join Without Erasure.','<p>Members may document their ancestry, family history, cultural identity, language, and community affiliations while maintaining their voluntary INO membership identity.</p><div class="ino-notice">An INO record does not by itself establish external governmental or tribal recognition.</div>'); }
    public static function identity_standards() { return self::simple('Identity Standards and Disclosures','<p>Identity records are classified as self-declared, family-attested, document-supported, institutionally reviewed, pending documentation, or not independently verified.</p>'); }
    public static function book_of_names() { return self::simple("People's Book of Names",'<p>A searchable registry for family names, clan names, cultural affiliations, geographic origins, oral histories, and related member records.</p>'); }
    public static function family_archives() { return self::simple('Tribal and Family Archives','<p>Preserve family narratives, photographs, documents, oral histories, language records, and member-contributed research with privacy controls.</p>'); }
    public static function certificates() { return self::simple('Dual Identity Certificates','<p>Generate ancestral identity and INO membership certificates with evidence classification, issue date, certificate number, and verification notice.</p>'); }
    public static function verify_identity() { return self::simple('Verify Identity Record','<p>Public verification tools can confirm that an INO record exists and show the permitted public status without exposing restricted evidence.</p>'); }

    public static function member_directory() {
        global $wpdb;
        $ids = $wpdb->get_col("SELECT DISTINCT user_id FROM {$wpdb->prefix}ino_members WHERE user_id IS NOT NULL AND public_consent = 1 AND LOWER(status) = 'approved' LIMIT 60");
        if (!$ids) {
            return self::simple('Member Directory', '<p>No member profiles have been approved for public listing.</p>');
        }
        $users = get_users(array('include'=>array_map('intval', $ids), 'orderby'=>'display_name', 'order'=>'ASC'));
        $html = '<section class="ino-shell"><div class="ino-heading"><span>People of Onegodia</span><h1>Member Directory</h1></div><div class="ino-public-grid">';
        foreach ($users as $user) {
            $name = get_user_meta($user->ID, 'ino_preferred_name', true);
            if (!$name) { $name = $user->display_name; }
            $html .= '<div class="ino-card-public">'.INO_Platform_Social::avatar($user->ID,120).'<h3>'.esc_html($name).'</h3><a class="ino-btn ino-btn-navy" href="'.esc_url(add_query_arg('member',$user->ID,home_url('/member-profile/'))).'">View Profile</a></div>';
        }
        return $html.'</div></section>';
    }

    public static function member_profile($atts=array()) {
        global $wpdb;
        $user_id = isset($_GET['member']) ? absint($_GET['member']) : (isset($atts['user_id']) ? absint($atts['user_id']) : get_current_user_id());
        $user = get_userdata($user_id);
        if (!$user) { return '<div class="ino-notice">Member not found.</div>'; }
        $is_self = is_user_logged_in() && get_current_user_id() === $user_id;
        $is_staff = current_user_can('manage_options');
        $public_consent = (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT 1 FROM {$wpdb->prefix}ino_members WHERE user_id = %d AND public_consent = 1 AND LOWER(status) = 'approved' LIMIT 1",
            $user_id
        ));
        if (!$is_self && !$is_staff && !$public_consent) {
            return '<div class="ino-notice">This member profile is private or unavailable.</div>';
        }
        $name = get_user_meta($user_id,'ino_preferred_name',true);
        if (!$name) { $name = $user->display_name; }
        $cover = INO_Platform_Social::cover($user_id);
        $style = $cover ? ' style="background-image:url('.esc_url($cover).')"' : '';
        $html = '<section class="ino-shell"><div class="ino-profile"><div class="ino-cover"'.$style.'></div><div class="ino-profile-body"><div class="ino-profile-avatar">'.INO_Platform_Social::avatar($user_id,150).'</div><h1>'.esc_html($name).'</h1>';
        if ($is_self || $is_staff) {
            $fields = array('Declared ancestral identity'=>'ino_ancestral_identity','Tribe, nation, clan, or community'=>'ino_tribe_clan','Family or lineage'=>'ino_family_lineage','Homeland'=>'ino_homeland','Language or tradition'=>'ino_language','INO classification'=>'ino_membership_class','Membership number'=>'ino_membership_number');
            foreach ($fields as $label=>$key) {
                $value = get_user_meta($user_id, $key, true);
                if ($value) { $html .= '<p><strong>'.esc_html($label).':</strong> '.esc_html($value).'</p>'; }
            }
        }
        if (is_user_logged_in() && get_current_user_id() !== $user_id) {
            $html .= '<form method="post"><input type="hidden" name="ino_social_action" value="connect"><input type="hidden" name="target_user" value="'.esc_attr($user_id).'">'.wp_nonce_field('ino_social_action','_ino_nonce',true,false).'<button class="ino-btn ino-btn-gold">Connect</button></form>';
        }
        return $html.'</div></div></section>';
    }

    public static function people_network() { return self::simple('People Network','<p>Discover member connections, family relationships, clan links, and community affiliations. BuddyPress friendships are used when available.</p>'); }

    public static function family_tree($atts=array()) {
        if (!is_user_logged_in()) { return '<div class="ino-notice">Please log in to view family records.</div>'; }
        global $wpdb;
        $user_id = isset($atts['user_id']) ? absint($atts['user_id']) : get_current_user_id();
        if ($user_id !== get_current_user_id() && !current_user_can('manage_options')) {
            return '<div class="ino-notice">Family records are private to the member and authorized staff.</div>';
        }
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ino_family_relationships WHERE (person_a=%d OR person_b=%d) AND status='approved' ORDER BY created_at DESC",
            $user_id, $user_id
        ));
        $html = '<section class="ino-shell"><div class="ino-heading"><span>Genealogy</span><h1>Family Tree & Relationships</h1></div><div class="ino-tree-list">';
        if (!$rows) { $html .= '<div class="ino-notice">No approved relationships are available.</div>'; }
        foreach ($rows as $row) {
            $other = ((int)$row->person_a === $user_id) ? (int)$row->person_b : (int)$row->person_a;
            $user = get_userdata($other);
            if (!$user) { continue; }
            $html .= '<div class="ino-tree-item"><strong>'.esc_html($user->display_name).'</strong><br>'.esc_html(ucwords(str_replace('_',' ',$row->relationship_type))).' · '.esc_html($row->status).'</div>';
        }
        return $html.'</div></section>';
    }

    public static function identity_declaration() {
        if (!is_user_logged_in()) { return '<div class="ino-notice">Please log in to submit an identity declaration.</div>'; }
        global $wpdb;
        if (!empty($_POST['ino_identity_submit']) && isset($_POST['_ino_identity_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_ino_identity_nonce'])),'ino_identity_submit')) {
            $wpdb->insert($wpdb->prefix.'ino_identity_declarations',array('user_id'=>get_current_user_id(),'ancestral_people'=>sanitize_text_field(wp_unslash($_POST['ancestral_people']??'')),'tribe_nation_clan'=>sanitize_text_field(wp_unslash($_POST['tribe_nation_clan']??'')),'family_lineage'=>sanitize_text_field(wp_unslash($_POST['family_lineage']??'')),'homeland'=>sanitize_text_field(wp_unslash($_POST['homeland']??'')),'language_tradition'=>sanitize_text_field(wp_unslash($_POST['language_tradition']??'')),'family_narrative'=>sanitize_textarea_field(wp_unslash($_POST['family_narrative']??'')),'preferred_wording'=>sanitize_textarea_field(wp_unslash($_POST['preferred_wording']??'')),'verification_status'=>'Self-declared','privacy_level'=>sanitize_text_field(wp_unslash($_POST['privacy_level']??'private'))));
        }
        return '<section class="ino-shell"><div class="ino-heading"><span>Identity & Heritage</span><h1>Identity Declaration</h1></div><form method="post" class="ino-form">'.wp_nonce_field('ino_identity_submit','_ino_identity_nonce',true,false).'<input type="hidden" name="ino_identity_submit" value="1"><label>Original or ancestral people<input name="ancestral_people"></label><label>Tribe, nation, clan, or community<input name="tribe_nation_clan"></label><label>Family or lineage name<input name="family_lineage"></label><label>Homeland or country of origin<input name="homeland"></label><label>Language or cultural tradition<input name="language_tradition"></label><label>Family narrative<textarea name="family_narrative" rows="5"></textarea></label><label>Preferred identity wording<textarea name="preferred_wording" rows="3"></textarea></label><p>Initial evidence classification: Self-declared. Independent review is a separate process.</p><label>Privacy<select name="privacy_level"><option value="private">Private</option><option value="members">Members only</option><option value="public_summary">Public summary</option></select></label><button class="ino-btn ino-btn-gold">Submit Declaration</button></form><div class="ino-notice">This record does not independently establish enrollment or recognition by an external government, agency, genealogical authority, or tribal nation.</div></section>';
    }

    public static function identity_dashboard() {
        if (!is_user_logged_in()) {
            return '<section class="ino-shell"><div class="ino-member-notice">Please log in to view your private Identity &amp; Heritage Dashboard.</div></section>';
        }
        global $wpdb;
        $uid = get_current_user_id();
        $declarations = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ino_identity_declarations WHERE user_id = %d", $uid
        ));
        $relations = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ino_family_relationships WHERE (person_a = %d OR person_b = %d) AND status = 'approved'",
            $uid, $uid
        ));
        $membership_status = $wpdb->get_var($wpdb->prepare(
            "SELECT status FROM {$wpdb->prefix}ino_members WHERE user_id = %d ORDER BY id DESC LIMIT 1", $uid
        ));
        $membership_status = $membership_status ? (string) $membership_status : 'No record';
        $html = '<section class="ino-shell ino-member-dashboard"><div class="ino-hero-public"><span class="ino-portal-eyebrow">Private member workspace</span><h1>Identity &amp; Heritage</h1><p class="ino-portal-lead">Review your recorded information and explore available private record tools. Record classifications are not independent external identity verification.</p><div class="ino-portal-actions"><a class="ino-btn ino-btn-gold" href="' . esc_url(home_url('/identity-declaration/')) . '">Open declaration form ↗</a></div></div>';
        $html .= '<section class="ino-portal-section" aria-labelledby="ino-member-records-heading"><div class="ino-portal-section-head"><div><span class="ino-portal-label">Your records</span><h2 id="ino-member-records-heading">Private overview</h2></div></div><div class="ino-member-widget-grid">';
        $widgets = array(
            array(number_format_i18n($declarations), 'Identity declarations', '/identity-declaration/', 'Open declarations'),
            array(number_format_i18n($relations), 'Approved relationships', '/family-tree/', 'View family records'),
            array($membership_status, 'Stored INO membership status', '/ino-membership/', 'Membership information')
        );
        foreach ($widgets as $widget) {
            $html .= '<article class="ino-member-widget"><strong>' . esc_html($widget[0]) . '</strong><span>' . esc_html($widget[1]) . '</span><a href="' . esc_url(home_url($widget[2])) . '">' . esc_html($widget[3]) . ' ↗</a></article>';
        }
        $html .= '</div></section><div class="ino-member-notice">These figures are drawn from your own INO records. Family relationships shown here count approved records only. Do not use an INO credential as a substitute for government-issued identification.</div></section>';
        return $html;
    }

}
