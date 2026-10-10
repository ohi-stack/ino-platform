<?php
if (!defined('ABSPATH')) { exit; }

/**
 * BuddyPress administrative bridge for the canonical INO Platform.
 *
 * The two systems share WP user IDs. INO membership, ancestral records,
 * approval and publication consent remain authoritative in INO tables.
 * No automatic copy to BuddyPress xProfile is performed.
 */
final class INO_Platform_BuddyPress {
    const OPTION = 'ino_bp_bridge_settings';
    const AUDIT = 'ino_bp_bridge_audit';
    const REPORT = 'ino_bp_bridge_report';

    public static function defaults() {
        return array(
            'profile_tab' => 0,       // Private heritage tab is explicit opt-in.
            'friend_requests' => 1,   // Existing connection workflow; opt-in targets only.
            'prefer_bp_media' => 1
        );
    }

    public static function settings() {
        $raw = get_option(self::OPTION, array());
        return array_merge(self::defaults(), is_array($raw) ? $raw : array());
    }

    public static function enabled($key) {
        $settings = self::settings();
        return !empty($settings[$key]);
    }

    public static function active() {
        return function_exists('buddypress') && function_exists('bp_is_active');
    }

    public static function component($component) {
        return self::active() && (bool) bp_is_active($component);
    }

    public static function init() {
        add_action('admin_post_ino_bp_bridge_save', array(__CLASS__, 'save'));
        add_action('admin_post_ino_bp_bridge_check', array(__CLASS__, 'run_checks'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'assets'));
    }

    private static function require_admin($action) {
        if (!is_user_logged_in() || !current_user_can('manage_options')) {
            wp_die(esc_html__('INO BuddyPress administration requires a site administrator.', 'ino-platform'), '', array('response'=>403));
        }
        check_admin_referer($action, '_ino_bp_nonce');
    }

    private static function go($notice) {
        wp_safe_redirect(add_query_arg('ino_bp_status', $notice, admin_url('admin.php?page=ino-platform-buddypress')));
        exit;
    }

    private static function audit($event) {
        $entries = get_option(self::AUDIT, array());
        if (!is_array($entries)) { $entries = array(); }
        array_unshift($entries, array(
            'action' => $event,
            'actor' => get_current_user_id(),
            'date' => current_time('mysql')
        ));
        update_option(self::AUDIT, array_slice($entries, 0, 30), false);
    }

    public static function save() {
        self::require_admin('ino_bp_bridge_save');
        $values = array();
        foreach (array_keys(self::defaults()) as $key) {
            $values[$key] = isset($_POST[$key]) && (string)wp_unslash($_POST[$key]) === '1' ? 1 : 0;
        }
        update_option(self::OPTION, $values, false);
        self::audit('bridge_settings_saved');
        self::go('Settings saved. Profile visibility and site permissions still require testing.');
    }

    public static function checks() {
        $active = self::active();
        $items = array(
            'BuddyPress runtime' => array($active, 'BuddyPress core functions loaded'),
            'Extended Profiles' => array(self::component('xprofile'), 'Optional: BuddyPress xProfile component'),
            'Friends' => array(self::component('friends') && function_exists('friends_add_friend'), 'Needed for native friend requests'),
            'Avatar rendering' => array($active && function_exists('bp_core_fetch_avatar'), 'Fallback uses WordPress avatars'),
            'Profile URL' => array($active && function_exists('bp_core_get_user_domain'), 'Used by member account links'),
            'INO private tab' => array(self::enabled('profile_tab') && $active && function_exists('bp_core_new_nav_item'), 'Only own account or authorized staff; off by default')
        );
        return $items;
    }

    public static function run_checks() {
        self::require_admin('ino_bp_bridge_check');
        $rows = self::checks();
        $summary = array();
        foreach ($rows as $name => $entry) { $summary[$name] = (bool)$entry[0]; }
        update_option(self::REPORT, array(
            'checked_at' => current_time('mysql'),
            'actor' => get_current_user_id(),
            'results' => $summary
        ), false);
        self::audit('bridge_diagnostics_checked');
        self::go('Diagnostic snapshot updated using current BuddyPress runtime.');
    }

    public static function can_connect_target($user_id) {
        $user_id = absint($user_id);
        if (!$user_id || !get_userdata($user_id)) { return false; }
        global $wpdb;
        $table = $wpdb->prefix . 'ino_members';
        // An older consented/approved edition cannot override a newer
        // withdrawal, pending status, denial or opt-out record.
        $latest = $wpdb->get_row($wpdb->prepare(
            "SELECT status,public_consent FROM {$table} WHERE user_id=%d ORDER BY id DESC LIMIT 1",
            $user_id
        ));
        return $latest && strtolower((string)$latest->status)==='approved' &&
            (int)$latest->public_consent===1;
    }

    public static function profile_link($user_id) {
        $user_id = absint($user_id);
        if (!$user_id || !get_userdata($user_id)) { return ''; }
        if (self::active() && function_exists('bp_core_get_user_domain')) {
            $url = bp_core_get_user_domain($user_id);
            if ($url) { return esc_url($url); }
        }
        return esc_url(add_query_arg('member', $user_id, home_url('/member-profile/')));
    }

    private static function count_members($public=false) {
        global $wpdb;
        $table=$wpdb->prefix.'ino_members';
        $sql="SELECT COUNT(*) FROM {$table} m INNER JOIN
            (SELECT user_id,MAX(id) AS latest_id FROM {$table}
             WHERE user_id IS NOT NULL GROUP BY user_id) newest
            ON newest.latest_id=m.id WHERE LOWER(m.status)='approved'";
        if ($public) { $sql.=" AND m.public_consent=1"; }
        return (int)$wpdb->get_var($sql);
    }

    public static function assets($hook) {
        if (strpos((string)$hook, 'ino-platform-buddypress') === false) { return; }
        wp_enqueue_style('ino-bp-admin', INO_PLATFORM_URL.'assets/css/ino-buddypress-admin.css',
            array('ino-platform-admin-command'), INO_PLATFORM_VERSION);
    }

    private static function state_label($ok, $required=false) {
        if ($ok) { return '<span class="ino-bp-pill is-ready">Available</span>'; }
        return '<span class="ino-bp-pill is-off">' . ($required ? 'Unavailable' : 'Optional / inactive') . '</span>';
    }

    public static function page() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Insufficient administrative permissions.', 'ino-platform'), '', array('response'=>403));
        }
        $active = self::active();
        $options=self::settings();
        $checks=self::checks();
        $last=get_option(self::REPORT, array());
        $audit=get_option(self::AUDIT, array());
        $requested=isset($_GET['user_id']) ? absint($_GET['user_id']) : 0;
        $person=$requested ? get_userdata($requested) : null;
        global $wpdb;
        echo '<main class="ino-admin ino-bp-admin" data-ino-command>';
        echo '<div class="ino-shell-head"><div class="ino-wordmark"><span class="ino-mark" aria-hidden="true">INO</span><div>Indigenous Nation of Onegodia<div class="ino-caption">Institutional Platform · Administration</div></div></div><span class="ino-caption">BuddyPress Bridge · ' . esc_html(INO_PLATFORM_VERSION) . '</span></div>';
        echo '<header class="ino-hero ino-bp-hero"><span class="ino-kicker">INO Platform / Member Connections</span><h1>BuddyPress Integration</h1><p>Configure the member-community bridge, verify active components, and review account alignment. INO retains its own membership approvals, identity evidence and publication-consent authority.</p><div class="ino-actions"><a class="ino-btn ino-btn-gold" href="' . esc_url(admin_url('admin.php?page=ino-platform-members')) . '">Membership workspace ↗</a><a class="ino-btn ino-btn-light" href="' . esc_url(admin_url('admin.php?page=bp-components')) . '">BuddyPress components ↗</a></div></header>';
        echo '<nav class="ino-subnav" aria-label="Integration navigation"><a href="#ino-bp-health">Health</a><a href="#ino-bp-settings">Settings</a><a href="#ino-bp-alignment">Account alignment</a><a href="#ino-bp-audit">Audit history</a></nav>';
        if (isset($_GET['ino_bp_status']) && is_string($_GET['ino_bp_status'])) {
            echo '<p class="ino-note" role="status">' . esc_html(sanitize_text_field(wp_unslash($_GET['ino_bp_status']))) . '</p>';
        }
        echo '<div class="ino-grid ino-bp-metrics" aria-label="Verified integration metrics">';
        foreach (array(
            array($active?'Connected':'Unavailable', 'BuddyPress core', $active?'Runtime detected':'Enable BuddyPress to use native features'),
            array(number_format_i18n(self::count_members()), 'Approved INO members', 'Internal INO membership records'),
            array(number_format_i18n(self::count_members(true)), 'Public-profile opt-ins', 'Approved with documented directory consent'),
            array(self::component('friends')?'On':'Off', 'Friends component', 'Native requests only when enabled')
        ) as $metric) {
            echo '<article class="ino-card"><span class="ino-value">' . esc_html($metric[0]) . '</span><span class="ino-label">' . esc_html($metric[1]) . '</span><span class="ino-card-foot">' . esc_html($metric[2]) . '</span></article>';
        }
        echo '</div><section id="ino-bp-health" class="ino-panel"><div class="ino-bp-section-head"><div><h2>Runtime health &amp; components</h2><p class="ino-panel-help">Current function/component checks; not a claim that the whole external plugin has passed production integration.</p></div>';
        echo '<form action="'.esc_url(admin_url('admin-post.php')).'" method="post"><input type="hidden" name="action" value="ino_bp_bridge_check">';
        wp_nonce_field('ino_bp_bridge_check','_ino_bp_nonce');
        echo '<button class="ino-btn ino-btn-primary" type="submit">Run integration diagnostics</button></form></div>';
        echo '<div class="ino-bp-checks">';
        foreach ($checks as $title=>$entry) {
            echo '<div class="ino-bp-check"><div><strong>'.esc_html($title).'</strong><p>'.esc_html($entry[1]).'</p></div>'.self::state_label($entry[0],$title==='BuddyPress runtime').'</div>';
        }
        echo '</div>';
        if (is_array($last) && !empty($last['checked_at'])) {
            echo '<p class="ino-caption">Last saved diagnostic snapshot: '.esc_html($last['checked_at']).' by WordPress user #'.esc_html((int)$last['actor']).'.</p>';
        }
        if (!$active) {
            echo '<p class="ino-note">BuddyPress is not currently available. The INO directory and self-scoped profile views remain independent of BuddyPress. Install/configure BuddyPress separately, then rerun diagnostics.</p>';
        }
        echo '</section>';

        echo '<section id="ino-bp-settings" class="ino-panel"><h2>Member-community settings</h2><p class="ino-panel-help">Changes take effect within the INO bridge. This does not change BuddyPress global visibility, component activation or BuddyPress directory privacy settings.</p>';
        echo '<form class="ino-bp-form" method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="ino_bp_bridge_save">';
        wp_nonce_field('ino_bp_bridge_save','_ino_bp_nonce');
        foreach (array(
            'friend_requests'=>array('Allow consent-gated connection requests', 'Native BuddyPress friends when enabled; otherwise INO connection records remain pending for review.'),
            'prefer_bp_media'=>array('Prefer BuddyPress profile avatars and cover images','Fallback to WordPress avatar and INO cover where BuddyPress media is unavailable.'),
            'profile_tab'=>array('Add private Family & Connections tab to BuddyPress profiles','Display this sensitive tab only to its owner or a site administrator. Disabled by default.')
        ) as $key=>$item) {
            echo '<label class="ino-bp-toggle"><input type="checkbox" name="'.esc_attr($key).'" value="1" '.checked(!empty($options[$key]),true,false).'><span><strong>'.esc_html($item[0]).'</strong><small>'.esc_html($item[1]).'</small></span></label>';
        }
        echo '<button class="ino-btn ino-btn-primary" type="submit">Save integration settings</button></form>';
        echo '<p class="ino-note"><strong>Privacy boundary:</strong> BuddyPress may expose its own general member directory independently of INO approval or consent. Configure BuddyPress privacy/directory settings separately. This bridge does not publish ancestry, household relationships, member IDs or enrollment decisions to xProfile.</p></section>';

        echo '<section id="ino-bp-alignment" class="ino-panel"><h2>WordPress / INO member alignment</h2><p class="ino-panel-help">BuddyPress uses WordPress user IDs; no duplicate membership import or overwrite is needed. Inspect a specific ID without exporting personal records.</p>';
        echo '<form class="ino-bp-search" method="get" action="'.esc_url(admin_url('admin.php')).'"><input type="hidden" name="page" value="ino-platform-buddypress"><label for="ino_bp_user_id">WordPress user ID</label><div><input id="ino_bp_user_id" type="number" min="1" step="1" name="user_id" value="'.esc_attr($requested?:'').'" required><button class="ino-btn ino-btn-primary" type="submit">Inspect account</button></div></form>';
        if ($requested) {
            if (!$person) {
                echo '<p class="ino-note">No WordPress account matches this ID.</p>';
            } else {
                $table=$wpdb->prefix.'ino_members';
                $membership=$wpdb->get_row($wpdb->prepare(
                    "SELECT status,public_consent,member_id FROM {$table} WHERE user_id=%d ORDER BY id DESC LIMIT 1", $requested
                ));
                echo '<div class="ino-bp-alignment-card"><h3>'.esc_html($person->display_name).' <small>WordPress #'.esc_html($requested).'</small></h3>';
                echo '<dl><div><dt>INO membership</dt><dd>'.esc_html($membership?$membership->status:'No INO membership row').'</dd></div>';
                echo '<div><dt>INO directory consent</dt><dd>'.($membership && (int)$membership->public_consent===1?'Recorded':'Not recorded').'</dd></div>';
                echo '<div><dt>Community link</dt><dd>'.($active?'Shared WordPress account':'BuddyPress not active').'</dd></div></dl>';
                echo '<p><a href="'.esc_url(get_edit_user_link($requested)).'">Open WordPress account ↗</a>';
                if ($active && $membership && (int)$membership->public_consent===1) {
                    echo ' · <a href="'.esc_url(self::profile_link($requested)).'" target="_blank" rel="noopener noreferrer">View community profile ↗</a>';
                }
                echo '</p></div>';
            }
        }
        echo '</section>';

        echo '<section id="ino-bp-audit" class="ino-panel"><h2>Integration administration history</h2><p class="ino-panel-help">Last 30 changes and diagnostic runs. No personal identity declarations are stored in this summary.</p><div class="ino-bp-table"><table class="ino-table"><thead><tr><th>When</th><th>Action</th><th>WordPress actor ID</th></tr></thead><tbody>';
        if (!$audit) { echo '<tr><td colspan="3">No bridge administration actions recorded.</td></tr>'; }
        foreach ((array)$audit as $entry) {
            echo '<tr><td>'.esc_html(isset($entry['date'])?$entry['date']:'').'</td><td>'.esc_html(isset($entry['action'])?$entry['action']:'').'</td><td>'.esc_html(isset($entry['actor'])?$entry['actor']:'').'</td></tr>';
        }
        echo '</tbody></table></div></section></main>';
    }
}
