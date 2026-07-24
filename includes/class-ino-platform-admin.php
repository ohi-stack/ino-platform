<?php
if (!defined('ABSPATH')) { exit; }

class INO_Platform_Admin {
    public static function init() {
        add_action('admin_menu', array(__CLASS__, 'register_menu'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'assets'));
    }

    public static function register_menu() {
        add_menu_page('INO Platform', 'INO Platform', 'manage_options', 'ino-platform', array(__CLASS__, 'dashboard'), 'dashicons-networking', 3);
        $items = array(
            'ino-platform-members'=>'Members',
            'ino-platform-citizenship'=>'Citizenship',
            'ino-platform-identity'=>'Identity & Heritage',
            'ino-platform-family'=>'Family Relationships',
            'ino-platform-connections'=>'People Connections',
            'ino-platform-grants'=>'Treasury & Grants',
            'ino-platform-housing'=>'Housing Projects',
            'ino-platform-documents'=>'Documents',
            'ino-platform-governance'=>'Governance',
            'ino-platform-forms'=>'Forms',
            'ino-platform-reports'=>'Reports',
            'ino-platform-buddypress'=>'BuddyPress Integration',
            'ino-platform-settings'=>'Settings'
        );
        foreach ($items as $slug=>$label) {
            add_submenu_page('ino-platform', $label, $label, 'manage_options', $slug, function() use ($label,$slug){ self::module($label,$slug); });
        }
    }

    public static function assets($hook) {
        if (strpos($hook, 'ino-platform') === false) { return; }
        wp_register_style('ino-platform-admin', false, array(), INO_PLATFORM_VERSION);
        wp_enqueue_style('ino-platform-admin');
        wp_add_inline_style('ino-platform-admin', self::css());
    }

    private static function css() {
        return ':root{--ino-navy:#0D2B45;--ino-deep:#071B2D;--ino-mid:#0C1020;--ino-red:#A5070A;--ino-gold:#C99A2E;--ino-light-gold:#E2C56F;--ino-cream:#FAF4DF}.ino-admin{max-width:1240px;margin:24px 20px 40px 0}.ino-hero{background:linear-gradient(135deg,var(--ino-deep),var(--ino-navy),var(--ino-mid));color:#fff;border-radius:18px;padding:34px;box-shadow:0 18px 50px rgba(7,27,45,.2)}.ino-hero h1{color:#fff;font-size:34px;margin:0 0 8px}.ino-kicker{color:var(--ino-light-gold);font-weight:800;letter-spacing:.1em;text-transform:uppercase;font-size:12px}.ino-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px;margin-top:20px}.ino-card{background:#fff;border:1px solid #dfe5ea;border-radius:14px;padding:22px}.ino-card strong{font-size:28px;color:var(--ino-navy)}.ino-panel{background:#fff;border:1px solid #dfe5ea;border-radius:14px;padding:24px;margin-top:18px}.ino-badge{display:inline-block;border-radius:999px;padding:6px 10px;font-weight:700;background:#edf7ef;color:#236b32}.ino-btn{display:inline-block;padding:11px 16px;border-radius:8px;text-decoration:none;font-weight:700;margin-right:8px}.ino-btn-primary{background:var(--ino-navy);color:#fff}.ino-btn-gold{background:var(--ino-gold);color:var(--ino-deep)}.ino-table{width:100%;border-collapse:collapse}.ino-table th,.ino-table td{padding:12px;border-bottom:1px solid #e7ebef;text-align:left}.ino-note{background:var(--ino-cream);border-left:4px solid var(--ino-gold);padding:16px;margin-top:18px}@media(max-width:900px){.ino-grid{grid-template-columns:repeat(2,1fr)}}@media(max-width:560px){.ino-grid{grid-template-columns:1fr}}';
    }

    public static function dashboard() {
        global $wpdb;
        $p = $wpdb->prefix;
        $counts = array(
            'Members'=>(int)$wpdb->get_var("SELECT COUNT(*) FROM {$p}ino_members"),
            'Identity Declarations'=>(int)$wpdb->get_var("SELECT COUNT(*) FROM {$p}ino_identity_declarations"),
            'Family Relationships'=>(int)$wpdb->get_var("SELECT COUNT(*) FROM {$p}ino_family_relationships"),
            'Connections'=>(int)$wpdb->get_var("SELECT COUNT(*) FROM {$p}ino_connections"),
            'Grant Opportunities'=>(int)$wpdb->get_var("SELECT COUNT(*) FROM {$p}ino_grants"),
            'Housing Projects'=>(int)$wpdb->get_var("SELECT COUNT(*) FROM {$p}ino_housing_projects"),
            'Documents'=>(int)$wpdb->get_var("SELECT COUNT(*) FROM {$p}ino_documents")
        );
        echo '<div class="ino-admin"><div class="ino-hero"><div class="ino-kicker">Indigenous Nation of Onegodia</div><h1>INO Platform Command Dashboard</h1><p>Membership, identity, genealogy, community connections, grants, housing, documents, and governance in one control panel.</p></div><div class="ino-grid">';
        foreach ($counts as $label=>$count) { echo '<div class="ino-card"><strong>'.esc_html($count).'</strong><p>'.esc_html($label).'</p></div>'; }
        echo '</div><div class="ino-panel"><h2>Operational Status</h2><p><span class="ino-badge">Plugin active</span> Version '.esc_html(INO_PLATFORM_VERSION).'</p><p>BuddyPress: <strong>'.(INO_Platform_Social::buddyPress_active()?'Active — native social features enabled':'Inactive — INO fallback social features enabled').'</strong></p><p>Automatic pages: <strong>'.count((array)get_option('ino_platform_page_ids',array())).'</strong></p></div></div>';
    }

    public static function module($label,$slug) {
        echo '<div class="ino-admin"><div class="ino-hero"><div class="ino-kicker">INO Platform Module</div><h1>'.esc_html($label).'</h1><p>Manage '.esc_html(strtolower($label)).' records, workflows, permissions, and reports.</p></div><div class="ino-panel"><a class="ino-btn ino-btn-primary" href="#">Add New Record</a><a class="ino-btn ino-btn-gold" href="#">Export Report</a><h2>Module Registry</h2><table class="ino-table"><thead><tr><th>Record</th><th>Status</th><th>Updated</th></tr></thead><tbody><tr><td>'.esc_html($label).' module initialized</td><td><span class="ino-badge">Ready</span></td><td>'.esc_html(current_time('mysql')).'</td></tr></tbody></table>';
        if ($slug === 'ino-platform-identity') { echo '<div class="ino-note">Ancestral declarations are classified as self-declared, family-attested, document-supported, reviewed, pending, or unverified. They do not independently establish recognition by an external government or tribal nation.</div>'; }
        if ($slug === 'ino-platform-buddypress') { echo '<div class="ino-note">When BuddyPress is active, INO uses its friendship, avatar, cover-image, and extended profile systems. Without BuddyPress, INO uses its own connection and family relationship tables.</div>'; }
        echo '</div></div>';
    }
}
