<?php
if (!defined('ABSPATH')) { exit; }

/**
 * INO Platform Command Center.
 *
 * Presentation only: all numbers below originate in INO tables. This class
 * intentionally does not authorize transactions or invent module readiness.
 */
class INO_Platform_Admin {
    private static function modules() {
        return array(
            'ino-platform-members'     => array('Members', 'Membership registry and application review'),
            'ino-platform-citizenship' => array('INO Enrollment', 'Internal membership classifications, not civil citizenship'),
            'ino-platform-identity'    => array('Identity & Heritage', 'Private, reviewed and self-declared records'),
            'ino-platform-family'      => array('Family Relationships', 'Relationship records and privacy controls'),
            'ino-platform-connections' => array('People Connections', 'Member social connection records'),
            'ino-platform-grants'      => array('Treasury & Grants', 'Funding opportunities and award administration'),
            'ino-platform-housing'     => array('Housing Projects', 'Housing and site planning records'),
            'ino-platform-documents'   => array('Documents', 'Institutional record inventory'),
            'ino-platform-governance'  => array('Governance', 'Policies, resolutions and organizational records'),
            'ino-platform-forms'       => array('Forms', 'Intake forms and submission processes'),
            'ino-platform-reports'     => array('Reports', 'Database-backed operational indicators'),
            'ino-platform-buddypress'  => array('BuddyPress Integration', 'External social integration status'),
            'ino-platform-settings'    => array('Settings', 'Platform configuration')
        );
    }

    public static function init() {
        add_action('admin_menu', array(__CLASS__, 'register_menu'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'assets'));
    }

    public static function register_menu() {
        add_menu_page('INO Governance', 'INO Governance', 'ino_governance_view', 'ino-governance', array('INO_Platform_Governance', 'admin_page'), 'dashicons-bank', 4);
        add_submenu_page('ino-governance', 'Governance Operations', 'Operational Governance', 'ino_governance_view', 'ino-governance-operations', array('INO_Governance_Operations_UI','page'));
        add_submenu_page('ino-governance', 'ODIN & Transparency', 'ODIN & Transparency', 'ino_governance_view', 'ino-governance-odin', array('INO_Governance_ODIN_UI','admin_page'));
        add_menu_page('INO Platform', 'INO Platform', 'manage_options', 'ino-platform', array(__CLASS__, 'dashboard'), 'dashicons-networking', 3);
        foreach (self::modules() as $slug => $details) {
            $label = $details[0];
            add_submenu_page('ino-platform', $label, $label, 'manage_options', $slug, function () use ($slug, $label) {
                if ($slug === 'ino-platform-governance') {
                    INO_Platform_Governance::admin_page();
                } else {
                    self::module($label, $slug);
                }
            });
        }
    }

    public static function assets($hook) {
        if (strpos((string) $hook, 'ino-platform') === false && strpos((string) $hook, 'ino-governance') === false) { return; }
        $style = 'assets/css/ino-admin-command.css';
        $script = 'assets/js/ino-admin-command.js';
        wp_enqueue_style(
            'ino-platform-admin-command',
            INO_PLATFORM_URL . $style,
            array(),
            file_exists(INO_PLATFORM_PATH . $style) ? (string) filemtime(INO_PLATFORM_PATH . $style) : INO_PLATFORM_VERSION
        );
        wp_enqueue_script(
            'ino-platform-admin-command',
            INO_PLATFORM_URL . $script,
            array(),
            file_exists(INO_PLATFORM_PATH . $script) ? (string) filemtime(INO_PLATFORM_PATH . $script) : INO_PLATFORM_VERSION,
            true
        );
    }

    private static function metric_tables() {
        return array(
            'Members'               => 'ino_members',
            'Identity Declarations' => 'ino_identity_declarations',
            'Family Relationships'  => 'ino_family_relationships',
            'Connections'           => 'ino_connections',
            'Grant Opportunities'   => 'ino_grants',
            'Housing Projects'      => 'ino_housing_projects',
            'Documents'             => 'ino_documents'
        );
    }

    private static function count_records() {
        global $wpdb;
        $counts = array();
        foreach (self::metric_tables() as $label => $suffix) {
            // Table names are drawn exclusively from internal constants above.
            $table = $wpdb->prefix . $suffix;
            $counts[$label] = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
        }
        return $counts;
    }

    private static function monthly_series() {
        global $wpdb;
        $months = array();
        for ($i = 5; $i >= 0; $i--) {
            $months[] = gmdate('Y-m', strtotime('-' . $i . ' months', time()));
        }
        $monthly = array('months' => $months);
        $mapping = array(
            'members'      => 'ino_members',
            'declarations' => 'ino_identity_declarations',
            'grants'       => 'ino_grants',
            'documents'    => 'ino_documents'
        );
        $since = $months[0] . '-01 00:00:00';
        foreach ($mapping as $key => $suffix) {
            $table = $wpdb->prefix . $suffix;
            $lookup = array_fill_keys($months, 0);
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT DATE_FORMAT(created_at, '%%Y-%%m') AS month_key, COUNT(*) AS total
                 FROM {$table} WHERE created_at >= %s
                 GROUP BY DATE_FORMAT(created_at, '%%Y-%%m')
                 ORDER BY month_key ASC",
                $since
            ));
            if (is_array($rows)) {
                foreach ($rows as $row) {
                    if (isset($lookup[$row->month_key])) {
                        $lookup[$row->month_key] = (int) $row->total;
                    }
                }
            }
            $monthly[$key] = array_values($lookup);
        }
        return $monthly;
    }

    private static function module_link($slug) {
        return admin_url('admin.php?page=' . $slug);
    }

    private static function head($kicker, $title, $message) {
        echo '<div class="ino-shell-head"><div class="ino-wordmark"><span class="ino-mark" aria-hidden="true">INO</span><div>Indigenous Nation of Onegodia<div class="ino-caption">Institutional Platform · Administration</div></div></div><span class="ino-caption">Design candidate · Core ' . esc_html(INO_PLATFORM_VERSION) . '</span></div>';
        echo '<header class="ino-hero"><span class="ino-kicker">' . esc_html($kicker) . '</span><h1>' . esc_html($title) . '</h1><p>' . esc_html($message) . '</p><div class="ino-actions">';
        echo '<a class="ino-btn ino-btn-gold" href="' . esc_url(self::module_link('ino-platform-members')) . '">Membership workspace ↗</a>';
        echo '<a class="ino-btn ino-btn-light" href="' . esc_url(self::module_link('ino-platform-reports')) . '">Review reporting ↗</a></div></header>';
        echo '<nav class="ino-subnav" aria-label="INO module navigation">';
        foreach (array('ino-platform' => 'Overview', 'ino-platform-members' => 'Members', 'ino-platform-identity' => 'Identity', 'ino-platform-grants' => 'Grants', 'ino-platform-housing' => 'Housing', 'ino-platform-documents' => 'Records') as $slug => $label) {
            echo '<a href="' . esc_url(self::module_link($slug)) . '">' . esc_html($label) . '</a>';
        }
        echo '</nav>';
    }

    public static function dashboard() {
        if (!current_user_can('manage_options')) { wp_die(esc_html__('You do not have permission to view this page.', 'ino-platform')); }
        $counts = self::count_records();
        $monthly = self::monthly_series();
        $pages = (array) get_option('ino_platform_page_ids', array());
        $bp = INO_Platform_Social::buddyPress_active();

        echo '<main class="ino-admin" data-ino-command>';
        self::head('INO Platform / Command Center', 'Institutional Operations', 'A unified view of stored records, reporting trends, and module navigation. Metrics reflect existing database records; readiness is not inferred from a chart or a visible screen.');
        echo '<div class="ino-section-heading"><div><h2>Records at a glance</h2><p>Counts are read directly from the INO WordPress database.</p></div><span class="ino-badge info">Database-backed</span></div>';
        echo '<section class="ino-grid" aria-label="Record totals">';
        foreach ($counts as $label => $count) {
            echo '<article class="ino-card"><span class="ino-value">' . esc_html(number_format_i18n($count)) . '</span><span class="ino-label">' . esc_html($label) . '</span><div class="ino-card-foot">Stored records · not approvals or active cases</div></article>';
        }
        echo '</section>';

        echo '<div class="ino-panels"><section class="ino-panel" aria-labelledby="ino-chart-title"><h2 id="ino-chart-title">Record creation trends</h2><p class="ino-panel-help">New entries by month from existing records. Zero means no entries were recorded for that month in the selected table.</p>';
        echo '<div class="ino-tabs" role="group" aria-label="Choose chart dataset">';
        foreach (array('members' => 'Members', 'declarations' => 'Declarations', 'grants' => 'Grants', 'documents' => 'Documents') as $key => $label) {
            echo '<button type="button" data-ino-metric="' . esc_attr($key) . '" aria-pressed="' . ($key === 'members' ? 'true' : 'false') . '">' . esc_html($label) . '</button>';
        }
        echo '</div><div class="ino-chart" data-ino-chart data-ino-series="' . esc_attr(wp_json_encode($monthly)) . '"><p class="ino-empty">The chart requires JavaScript. Source values remain available below.</p></div>';
        echo '<p class="ino-metric-legend" data-ino-chart-summary aria-live="polite">Monthly database counts</p>';
        echo '<details><summary>View source figures without the chart</summary><div style="overflow-x:auto"><table class="ino-table"><thead><tr><th>Month</th><th>Members</th><th>Declarations</th><th>Grants</th><th>Documents</th></tr></thead><tbody>';
        foreach ($monthly['months'] as $i => $month) {
            echo '<tr><td>' . esc_html($month) . '</td><td>' . esc_html($monthly['members'][$i]) . '</td><td>' . esc_html($monthly['declarations'][$i]) . '</td><td>' . esc_html($monthly['grants'][$i]) . '</td><td>' . esc_html($monthly['documents'][$i]) . '</td></tr>';
        }
        echo '</tbody></table></div></details></section>';
        echo '<section class="ino-panel" aria-labelledby="ino-status-title"><h2 id="ino-status-title">System overview</h2><p class="ino-panel-help">Component presence and remaining verification gates.</p><ul class="ino-status-list">';
        echo '<li><span>WordPress plugin</span><span class="ino-badge info">Loaded</span></li>';
        echo '<li><span>Core version</span><strong>' . esc_html(INO_PLATFORM_VERSION) . '</strong></li>';
        echo '<li><span>Generated page entries</span><strong>' . esc_html(number_format_i18n(count($pages))) . '</strong></li>';
        echo '<li><span>BuddyPress detected</span><strong>' . ($bp ? 'Yes' : 'No') . '</strong></li>';
        echo '<li><span>End-to-end membership workflow</span><span class="ino-badge draft">Not verified</span></li>';
        echo '<li><span>External app integration</span><span class="ino-badge draft">Not verified</span></li>';
        echo '</ul><p class="ino-note">A loaded plugin, database table, or rendered shortcode is not proof of an operational workflow. Application approvals, financial actions and credential issuance still require authorized implementations and testing.</p></section></div>';

        echo '<div class="ino-section-heading"><div><h2>Department workspaces</h2><p>Navigate to each module. Available actions depend on implemented permissions and workflows.</p></div></div>';
        echo '<section class="ino-module-grid" aria-label="INO platform workspaces">';
        foreach (self::modules() as $slug => $item) {
            echo '<a class="ino-module-card" href="' . esc_url(self::module_link($slug)) . '"><strong>' . esc_html($item[0]) . ' ↗</strong><span>' . esc_html($item[1]) . '</span></a>';
        }
        echo '</section></main>';
    }

    public static function module($label, $slug) {
        if (!current_user_can('manage_options')) { wp_die(esc_html__('You do not have permission to view this page.', 'ino-platform')); }
        $counts = self::count_records();
        $key = array(
            'ino-platform-members' => 'Members',
            'ino-platform-identity' => 'Identity Declarations',
            'ino-platform-family' => 'Family Relationships',
            'ino-platform-connections' => 'Connections',
            'ino-platform-grants' => 'Grant Opportunities',
            'ino-platform-housing' => 'Housing Projects',
            'ino-platform-documents' => 'Documents'
        );
        echo '<main class="ino-admin" data-ino-command>';
        self::head('INO Platform / Workspace', $label, 'Module overview and administrative routing. This screen does not yet implement record creation, editing, approval, exporting, or financial transactions.');
        echo '<section class="ino-panel" style="margin-top:20px"><h2>Module status</h2><p class="ino-panel-help">Interface available; transactional workflow not independently verified.</p>';
        if (isset($key[$slug], $counts[$key[$slug]])) {
            echo '<div class="ino-card" style="max-width:260px"><span class="ino-value">' . esc_html(number_format_i18n($counts[$key[$slug]])) . '</span><span class="ino-label">' . esc_html($key[$slug]) . ' stored</span></div>';
        } else {
            echo '<div class="ino-empty-action"><strong>Administrative interface in development.</strong><p>No record-management handler has been verified for this workspace. Unavailable controls are not displayed.</p></div>';
        }
        if ($slug === 'ino-platform-identity') {
            echo '<p class="ino-note">Ancestral statements are not proof of external tribal enrollment or government recognition. Evidence classification and administrative review must remain distinct.</p>';
        }
        if ($slug === 'ino-platform-buddypress') {
            echo '<p class="ino-note">BuddyPress plugin detected: ' . (INO_Platform_Social::buddyPress_active() ? 'Yes' : 'No') . '. This does not verify functional integration.</p>';
        }
        echo '<p style="margin-top:24px"><a class="ino-btn ino-btn-primary" href="' . esc_url(self::module_link('ino-platform')) . '">← Return to Command Center</a></p></section></main>';
    }
}
