<?php
if (!defined('ABSPATH')) { exit; }

/**
 * INO Governance Foundation — Phase 1 candidate.
 *
 * Separate institutional decisions from WordPress technical administration.
 * Records start as drafts. Review and publication are explicit privileged acts.
 * No constitutional text, office, appointment or external recognition is seeded.
 */
class INO_Platform_Governance {
    const SCHEMA_VERSION = '1';
    const TABLE_SUFFIX = 'ino_governance_items';
    const AUDIT_SUFFIX = 'ino_governance_audit';

    public static function init() {
        add_action('admin_post_ino_gov_save', array(__CLASS__, 'save'));
        add_action('admin_post_ino_gov_transition', array(__CLASS__, 'transition'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'assets'));
        add_action('wp_enqueue_scripts', array(__CLASS__, 'public_assets'));
        add_shortcode('ino_governance_dashboard', array(__CLASS__, 'dashboard_shortcode'));
        add_shortcode('ino_constitution', array(__CLASS__, 'constitution_shortcode'));
        add_shortcode('ino_governance_structure', array(__CLASS__, 'structure_shortcode'));
        add_shortcode('ino_governance_records', array(__CLASS__, 'records_shortcode'));
    }

    public static function maybe_install() {
        if (get_option('ino_governance_schema_version') === self::SCHEMA_VERSION) { return; }
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $collate = $wpdb->get_charset_collate();
        $items = $wpdb->prefix . self::TABLE_SUFFIX;
        $audit = $wpdb->prefix . self::AUDIT_SUFFIX;
        dbDelta("CREATE TABLE {$items} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            record_code varchar(90) NOT NULL,
            record_type varchar(20) NOT NULL DEFAULT 'record',
            title varchar(190) NOT NULL,
            version_label varchar(60) NOT NULL DEFAULT '',
            summary text NULL,
            source_ref varchar(190) NOT NULL DEFAULT '',
            parent_id bigint(20) unsigned NULL,
            attachment_id bigint(20) unsigned NULL,
            document_hash char(64) NOT NULL DEFAULT '',
            adopted_on date NULL,
            effective_on date NULL,
            visibility varchar(20) NOT NULL DEFAULT 'restricted',
            status varchar(20) NOT NULL DEFAULT 'draft',
            created_by bigint(20) unsigned NOT NULL,
            reviewed_by bigint(20) unsigned NULL,
            published_by bigint(20) unsigned NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            reviewed_at datetime NULL,
            published_at datetime NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY record_code (record_code),
            KEY type_status (record_type,status),
            KEY parent_id (parent_id)
        ) {$collate};");
        dbDelta("CREATE TABLE {$audit} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            item_id bigint(20) unsigned NOT NULL,
            event varchar(40) NOT NULL,
            actor_id bigint(20) unsigned NOT NULL,
            note text NULL,
            occurred_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY item_id (item_id),
            KEY actor_id (actor_id)
        ) {$collate};");

        // Only expressly appointed governance roles have review/publish authority.
        $roles = array(
            'ino_gov_records_officer' => array('INO Governance Records Officer', array('ino_governance_view'=>true,'ino_governance_edit'=>true)),
            'ino_gov_reviewer' => array('INO Governance Reviewer', array('ino_governance_view'=>true,'ino_governance_review'=>true)),
            'ino_gov_publisher' => array('INO Governance Publisher', array('ino_governance_view'=>true,'ino_governance_publish'=>true))
        );
        foreach ($roles as $slug => $role_data) {
            add_role($slug, $role_data[0], array_merge(array('read'=>true), $role_data[1]));
            $role = get_role($slug);
            if ($role) {
                foreach ($role_data[1] as $cap => $value) { $role->add_cap($cap, $value); }
                foreach (array('ino_governance_view','ino_governance_edit','ino_governance_review','ino_governance_publish') as $candidate) {
                    if (!isset($role_data[1][$candidate])) { $role->remove_cap($candidate); }
                }
                // Avoid carrying accidental site-wide powers from older definitions.
                foreach (array('manage_options', 'edit_users', 'upload_files', 'edit_posts') as $cap) {
                    $role->remove_cap($cap);
                }
            }
        }
        // WP administrators may prepare drafts and maintain software, but may
        // not attest, review, publish or adopt governance acts by default.
        $administrator = get_role('administrator');
        if ($administrator) {
            $administrator->add_cap('ino_governance_view');
            $administrator->add_cap('ino_governance_edit');
        }
        update_option('ino_governance_schema_version', self::SCHEMA_VERSION, false);
    }

    private static function table() {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_SUFFIX;
    }

    private static function log_event($id, $event, $note) {
        global $wpdb;
        return $wpdb->insert($wpdb->prefix . self::AUDIT_SUFFIX, array(
            'item_id'=>(int)$id, 'event'=>$event, 'actor_id'=>get_current_user_id(),
            'note'=>$note, 'occurred_at'=>current_time('mysql')
        ), array('%d','%s','%d','%s','%s'));
    }

    private static function allowed_access() {
        return current_user_can('ino_governance_view') ||
            current_user_can('ino_governance_edit') ||
            current_user_can('ino_governance_review') ||
            current_user_can('ino_governance_publish');
    }

    private static function admin_url() {
        return admin_url('admin.php?page=ino-governance');
    }

    private static function redirect_notice($message) {
        wp_safe_redirect(add_query_arg('ino_gov_notice', rawurlencode($message), self::admin_url()));
        exit;
    }

    private static function require_cap($cap, $nonce_action) {
        if (!is_user_logged_in() || !current_user_can($cap)) {
            wp_die(esc_html__('Access denied for this governance action.', 'ino-platform'), '', array('response'=>403));
        }
        check_admin_referer($nonce_action, 'ino_gov_nonce');
    }

    private static function date_field($name) {
        $value = isset($_POST[$name]) ? sanitize_text_field(wp_unslash($_POST[$name])) : '';
        if ($value === '') { return null; }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value)) {
            wp_die(esc_html__('Invalid governance date.', 'ino-platform'), '', array('response'=>400));
        }
        $parts = explode('-', $value);
        if (!checkdate((int)$parts[1], (int)$parts[2], (int)$parts[0])) {
            wp_die(esc_html__('Invalid governance date.', 'ino-platform'), '', array('response'=>400));
        }
        return $value;
    }

    public static function save() {
        self::require_cap('ino_governance_edit', 'ino_gov_save');
        global $wpdb;
        $type = isset($_POST['record_type']) ? sanitize_key(wp_unslash($_POST['record_type'])) : 'record';
        if (!in_array($type, array('constitution','office','record'), true)) {
            wp_die('Unsupported governance record type.', '', array('response'=>400));
        }
        $title = isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '';
        if ($title === '') { wp_die('Title is required.', '', array('response'=>400)); }

        $code = isset($_POST['record_code']) ? strtoupper(sanitize_text_field(wp_unslash($_POST['record_code']))) : '';
        if ($code === '') { $code = 'INO-GOV-' . gmdate('Ymd') . '-' . strtoupper(substr(wp_generate_uuid4(), 0, 8)); }
        if (strlen($code) > 90 || !preg_match('/^[A-Z0-9][A-Z0-9._-]*$/D', $code)) {
            wp_die('Invalid registry reference.', '', array('response'=>400));
        }
        $visibility = isset($_POST['visibility']) ? sanitize_key(wp_unslash($_POST['visibility'])) : 'restricted';
        if (!in_array($visibility, array('public','restricted'), true)) {
            wp_die('Invalid visibility classification.', '', array('response'=>400));
        }
        $parent_id = isset($_POST['parent_id']) ? absint($_POST['parent_id']) : 0;
        if ($parent_id) {
            if ($type !== 'office') { wp_die('Parent is available for offices only.', '', array('response'=>400)); }
            $parent = $wpdb->get_row($wpdb->prepare(
                "SELECT id FROM " . self::table() . " WHERE id = %d AND record_type = 'office'", $parent_id
            ));
            if (!$parent) { wp_die('Parent office not found.', '', array('response'=>400)); }
        }
        $source = isset($_POST['source_ref']) ? sanitize_text_field(wp_unslash($_POST['source_ref'])) : '';
        $attachment_id = isset($_POST['attachment_id']) ? absint($_POST['attachment_id']) : 0;
        $hash = '';
        if ($attachment_id) {
            // WP Media Library files are typically directly URL-accessible:
            // do not attach restricted documents without a private-file service.
            if ($visibility !== 'public' || $type !== 'constitution') {
                wp_die('Only already-public Constitution PDFs can be linked.', '', array('response'=>400));
            }
            if (get_post_type($attachment_id) !== 'attachment' ||
                get_post_mime_type($attachment_id) !== 'application/pdf' ||
                !current_user_can('edit_post', $attachment_id)) {
                wp_die('A PDF attachment you can manage is required.', '', array('response'=>400));
            }
            $path = get_attached_file($attachment_id);
            if (!$path || !is_file($path) || !is_readable($path)) {
                wp_die('PDF file cannot be accessed.', '', array('response'=>400));
            }
            $hash = hash_file('sha256', $path);
            if ($hash === false) { wp_die('PDF hashing failed.', '', array('response'=>500)); }
        }
        $now = current_time('mysql');
        $data = array(
            'record_code'=>$code, 'record_type'=>$type, 'title'=>$title,
            'version_label'=>isset($_POST['version_label']) ? sanitize_text_field(wp_unslash($_POST['version_label'])) : '',
            'summary'=>isset($_POST['summary']) ? sanitize_textarea_field(wp_unslash($_POST['summary'])) : '',
            'source_ref'=>$source, 'parent_id'=>$parent_id ?: null,
            'attachment_id'=>$attachment_id ?: null, 'document_hash'=>$hash,
            'adopted_on'=>self::date_field('adopted_on'),
            'effective_on'=>self::date_field('effective_on'),
            'visibility'=>$visibility, 'status'=>'draft', 'created_by'=>get_current_user_id(),
            'created_at'=>$now, 'updated_at'=>$now
        );
        $wpdb->query('START TRANSACTION');
        $result = $wpdb->insert(self::table(), $data);
        if (!$result) {
            $wpdb->query('ROLLBACK');
            wp_die('Record could not be saved; check the unique reference.', '', array('response'=>409));
        }
        if (!self::log_event((int)$wpdb->insert_id, 'created_draft', 'Draft record created. No governance authority is conferred.')) {
            $wpdb->query('ROLLBACK');
            wp_die('Audit recording failed; draft creation rolled back.', '', array('response'=>500));
        }
        $wpdb->query('COMMIT');
        self::redirect_notice('Draft saved; it has not been reviewed or published.');
    }

    public static function transition() {
        global $wpdb;
        $action = isset($_POST['transition']) ? sanitize_key(wp_unslash($_POST['transition'])) : '';
        $cap = $action === 'review' ? 'ino_governance_review' :
               ($action === 'publish' ? 'ino_governance_publish' :
               ($action === 'withdraw' ? 'ino_governance_edit' : ''));
        if (!$cap) { wp_die('Unknown governance action.', '', array('response'=>400)); }
        self::require_cap($cap, 'ino_gov_transition');
        $id = isset($_POST['record_id']) ? absint($_POST['record_id']) : 0;
        $record = $wpdb->get_row($wpdb->prepare("SELECT * FROM " . self::table() . " WHERE id = %d", $id));
        if (!$record) { wp_die('Governance record not found.', '', array('response'=>404)); }
        $note = isset($_POST['note']) ? sanitize_textarea_field(wp_unslash($_POST['note'])) : '';
        $now = current_time('mysql');
        if ($action === 'review') {
            if ($record->status !== 'draft') { wp_die('Only drafts can be reviewed.', '', array('response'=>409)); }
            if (strlen(trim($note)) < 12) { wp_die('Provide a substantive review note.', '', array('response'=>400)); }
            if (!$record->source_ref) {
                wp_die('Every reviewed governance record requires a documented source reference.', '', array('response'=>400));
            }
            $values = array('status'=>'reviewed','reviewed_by'=>get_current_user_id(),'reviewed_at'=>$now,'updated_at'=>$now);
        } elseif ($action === 'publish') {
            if (empty($_POST['publish_attestation']) || (string)$_POST['publish_attestation'] !== '1') {
                wp_die('Publication requires an explicit authority attestation.', '', array('response'=>400));
            }
            if ($record->status !== 'reviewed' || !$record->reviewed_by) {
                wp_die('Only reviewed records may be published.', '', array('response'=>409));
            }
            if ((int)$record->reviewed_by === get_current_user_id()) {
                wp_die('A separate authorized publisher is required after review.', '', array('response'=>403));
            }
            if ($record->record_type === 'constitution' &&
                (!$record->attachment_id || !$record->document_hash || !$record->adopted_on)) {
                wp_die('The Constitution requires an identified public PDF, checksum and recorded adoption date.', '', array('response'=>400));
            }
            if ($record->record_type === 'office' && $record->parent_id) {
                $parent = $wpdb->get_var($wpdb->prepare(
                    "SELECT status FROM " . self::table() . " WHERE id = %d AND record_type = 'office'",
                    (int)$record->parent_id
                ));
                if ($parent !== 'published') {
                    wp_die('The parent office must be published before a subordinate office.', '', array('response'=>400));
                }
            }
            if ($record->record_type === 'constitution') {
                $pdf_path = get_attached_file((int)$record->attachment_id);
                if (!$pdf_path || !is_file($pdf_path) || !is_readable($pdf_path) ||
                    get_post_mime_type((int)$record->attachment_id) !== 'application/pdf' ||
                    !hash_equals((string)$record->document_hash, (string)hash_file('sha256', $pdf_path))) {
                    wp_die('The constitutional attachment is missing or changed since it was registered.', '', array('response'=>409));
                }
            }
            $values = array('status'=>'published','published_by'=>get_current_user_id(),'published_at'=>$now,'updated_at'=>$now);
        } else {
            if ($record->status !== 'draft') { wp_die('Only drafts may be withdrawn in Phase 1.', '', array('response'=>409)); }
            $values = array('status'=>'withdrawn','updated_at'=>$now);
        }
        $wpdb->query('START TRANSACTION');
        $result = $wpdb->update(self::table(), $values, array(
            'id'=>$id,'status'=>$record->status
        ));
        if ($result !== 1) {
            $wpdb->query('ROLLBACK');
            wp_die('Record changed during processing. Reload the page.', '', array('response'=>409));
        }
        if (!self::log_event($id, $action, $note)) {
            $wpdb->query('ROLLBACK');
            wp_die('Audit recording failed; state transition rolled back.', '', array('response'=>500));
        }
        $wpdb->query('COMMIT');
        self::redirect_notice('Governance record moved to ' . ($action === 'review' ? 'reviewed' : ($action === 'publish' ? 'published' : 'withdrawn')) . '.');
    }

    public static function assets($hook) {
        if (strpos((string)$hook, 'ino-platform-governance') === false && strpos((string)$hook, 'ino-governance') === false) { return; }
        wp_enqueue_style('ino-governance-foundation', INO_PLATFORM_URL . 'assets/css/ino-governance-foundation.css',
            array('ino-platform-admin-command'), INO_PLATFORM_VERSION);
    }

    public static function public_assets() {
        wp_enqueue_style('ino-governance-foundation', INO_PLATFORM_URL . 'assets/css/ino-governance-foundation.css',
            array('ino-platform-public-design'), INO_PLATFORM_VERSION);
    }

    private static function counts() {
        global $wpdb;
        $rows = $wpdb->get_results("SELECT record_type,status,COUNT(*) AS total FROM " . self::table() . " GROUP BY record_type,status");
        $counts = array('constitution'=>array(), 'office'=>array(), 'record'=>array());
        foreach ((array)$rows as $row) {
            if (isset($counts[$row->record_type])) { $counts[$row->record_type][$row->status] = (int)$row->total; }
        }
        return $counts;
    }

    private static function public_items($type, $limit=100) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT id,record_code,title,version_label,summary,source_ref,parent_id,
                    adopted_on,effective_on,attachment_id,document_hash,published_at
             FROM " . self::table() . "
             WHERE record_type=%s AND status='published' AND visibility='public'
             ORDER BY published_at DESC,id DESC LIMIT %d", $type, (int)$limit
        ));
    }

    private static function public_link($record) {
        if (!$record->attachment_id || !$record->document_hash) { return ''; }
        $pdf_path = get_attached_file((int)$record->attachment_id);
        if (!$pdf_path || !is_file($pdf_path) || !is_readable($pdf_path) ||
            get_post_mime_type((int)$record->attachment_id) !== 'application/pdf' ||
            !hash_equals((string)$record->document_hash, (string)hash_file('sha256', $pdf_path))) {
            return '<span class="ino-member-notice">Publication document is unavailable or has changed; link withheld pending review.</span>';
        }
        $url = wp_get_attachment_url((int)$record->attachment_id);
        return $url ? '<a href="' . esc_url($url) . '" rel="noopener noreferrer" target="_blank">View public PDF ↗</a>' : '';
    }

    public static function governance_shortcode() {
        $counts = self::counts();
        $published = 0;
        foreach ($counts as $type_counts) { $published += isset($type_counts['published']) ? $type_counts['published'] : 0; }
        $html = '<section class="ino-shell ino-gov-public"><div class="ino-hero-public"><span class="ino-portal-eyebrow">INO Governance Foundation</span><h1>Institutional Governance &amp; Records</h1><p class="ino-portal-lead">Explore publicly authorized governance documents, institutional structure and registry records. Private drafts and unverified instruments are not displayed.</p></div>';
        $html .= '<div class="ino-gov-public-grid">';
        foreach (array(
            array('Constitution Registry','/ino-constitution/','Review published constitutional versions'),
            array('Organizational Structure','/ino-governance-structure/','Explore documented INO office structures'),
            array('Public Governance Records','/ino-public-records/','Browse published institutional record summaries'),
            array('ODIN Document Registry','/ino-odin-registry/','Browse source-linked public document versions and history')
        ) as $link) {
            $html .= '<article class="ino-gov-tile"><h2>' . esc_html($link[0]) . '</h2><p>' . esc_html($link[2]) . '</p><a href="' . esc_url(home_url($link[1])) . '">Explore records ↗</a></article>';
        }
        $html .= '</div><p class="ino-member-notice">This registry records internal religious and organizational governance. It does not independently confer public governmental authority, political sovereignty, or external tribal recognition.</p></section>';
        return $html;
    }

    public static function dashboard_shortcode() {
        // A public shortcode must not expose privileged counts or draft metadata.
        if (!self::allowed_access()) { return self::governance_shortcode(); }
        $counts = self::counts();
        $html = '<section class="ino-shell ino-gov-public"><h2>Restricted Governance Overview</h2><div class="ino-gov-public-grid">';
        foreach (array('constitution'=>'Constitution versions','office'=>'Offices','record'=>'Registry records') as $type=>$label) {
            $total = array_sum($counts[$type]);
            $html .= '<article class="ino-gov-tile"><h3>' . esc_html($label) . '</h3><p><strong>' . esc_html(number_format_i18n($total)) . '</strong> stored records</p></article>';
        }
        $html .= '</div><p><a href="' . esc_url(self::admin_url()) . '">Open restricted Governance Administration ↗</a></p></section>';
        return $html;
    }

    public static function constitution_shortcode() {
        $rows = self::public_items('constitution');
        $html = '<section class="ino-shell ino-gov-public"><div class="ino-heading"><span>INO Governance</span><h1>Constitution Registry</h1></div>';
        if (!$rows) { return $html . '<p class="ino-member-notice">No constitutional version has completed review and authorized publication in this registry.</p></section>'; }
        foreach ($rows as $record) {
            $html .= '<article class="ino-gov-tile"><h2>' . esc_html($record->title) . '</h2><p>Version: ' . esc_html($record->version_label ?: 'Not supplied') . ' · Recorded adoption date: ' . esc_html($record->adopted_on ?: 'Not supplied') . '</p><p>' . esc_html($record->summary) . '</p><p>Internal reference: ' . esc_html($record->record_code) . '</p><p>Document SHA-256: <code>' . esc_html($record->document_hash) . '</code></p><p>' . self::public_link($record) . '</p></article>';
        }
        return $html . '</section>';
    }

    public static function structure_shortcode() {
        $rows = self::public_items('office');
        $html = '<section class="ino-shell ino-gov-public"><div class="ino-heading"><span>INO Governance</span><h1>Institutional Structure</h1></div>';
        if (!$rows) { return $html . '<p class="ino-member-notice">No office structures have completed authorized review and publication.</p></section>'; }
        $names = array();
        foreach ($rows as $office) { $names[(int)$office->id] = $office->title; }
        foreach ($rows as $record) {
            $parent = $record->parent_id && isset($names[(int)$record->parent_id]) ? $names[(int)$record->parent_id] : '';
            $html .= '<article class="ino-gov-tile"><h2>' . esc_html($record->title) . '</h2><p>' . esc_html($record->summary) . '</p><p>Authority reference: ' . esc_html($record->source_ref) . '</p>';
            if ($parent) { $html .= '<p>Reports within: <strong>' . esc_html($parent) . '</strong></p>'; }
            $html .= '</article>';
        }
        return $html . '<p class="ino-member-notice">This directory describes documented offices, not independently verified current officeholders or appointments.</p></section>';
    }

    public static function records_shortcode() {
        $rows = self::public_items('record');
        $html = '<section class="ino-shell ino-gov-public"><div class="ino-heading"><span>INO Governance</span><h1>Public Governance Records</h1></div>';
        if (!$rows) { return $html . '<p class="ino-member-notice">No governance records have been published for public access.</p></section>'; }
        foreach ($rows as $record) {
            $html .= '<article class="ino-gov-tile"><h2>' . esc_html($record->title) . '</h2><p>' . esc_html($record->summary) . '</p><p>Registry: ' . esc_html($record->record_code) . '</p></article>';
        }
        return $html . '</section>';
    }

    public static function admin_page() {
        if (!self::allowed_access()) {
            wp_die('No governance portal access permission.', '', array('response'=>403));
        }
        global $wpdb;
        $counts = self::counts();
        $all = $wpdb->get_results("SELECT id,record_type,title,record_code,status,visibility,source_ref,created_by,reviewed_by,
                                         parent_id,created_at
                                  FROM " . self::table() . " ORDER BY created_at DESC,id DESC LIMIT 100");
        $offices = $wpdb->get_results("SELECT id,title,status FROM " . self::table() . " WHERE record_type='office' AND status != 'withdrawn' ORDER BY title ASC");
        echo '<main class="ino-admin ino-gov-admin"><div class="ino-hero"><span class="ino-kicker">Governance Foundation · Phase 1</span><h1>Governance Registry</h1><p>Document institutional structure, register Constitution editions, review evidence and preserve accountability. These are internal INO records—not automatic proof of external recognition.</p></div>';
        if (isset($_GET['ino_gov_notice'])) {
            echo '<div class="ino-note" role="status">' . esc_html(sanitize_text_field(wp_unslash($_GET['ino_gov_notice']))) . '</div>';
        }
        echo '<p><a class="ino-btn ino-btn-gold" href="' . esc_url(INO_Governance_Operations::url()) . '">Open Operational Governance ↗</a></p>';
        echo '<p><a class="ino-btn ino-btn-gold" href="' . esc_url(INO_Governance_ODIN::admin_url()) . '">Open ODIN Registry ↗</a></p>';
        echo '<div class="ino-section-heading"><div><h2>Governance records</h2><p>Live database counts separated by review status.</p></div></div><div class="ino-grid">';
        foreach (array('constitution'=>'Constitution editions','office'=>'Structure offices','record'=>'Other governance records') as $type=>$label) {
            $total = array_sum($counts[$type]);
            $published = isset($counts[$type]['published']) ? (int)$counts[$type]['published'] : 0;
            echo '<div class="ino-card"><span class="ino-value">' . esc_html(number_format_i18n($total)) . '</span><span class="ino-label">' . esc_html($label) . '</span><span class="ino-card-foot">' . esc_html($published) . ' published after review</span></div>';
        }
        echo '<div class="ino-card"><span class="ino-value">' . esc_html(number_format_i18n(count($all))) . '</span><span class="ino-label">Recent records loaded</span><span class="ino-card-foot">At most 100, not the overall record total</span></div></div>';
        echo '<section class="ino-panel"><h2>Governance workflow distribution</h2><p class="ino-panel-help">Stored record counts by state, not approvals, voting percentages or activity forecasts.</p><div class="ino-gov-status-chart" role="group" aria-label="Record status counts by category">';
        foreach (array('constitution'=>'Constitution','office'=>'Offices','record'=>'Other records') as $type=>$name) {
            $draft = isset($counts[$type]['draft']) ? (int)$counts[$type]['draft'] : 0;
            $reviewed = isset($counts[$type]['reviewed']) ? (int)$counts[$type]['reviewed'] : 0;
            $published = isset($counts[$type]['published']) ? (int)$counts[$type]['published'] : 0;
            $withdrawn = isset($counts[$type]['withdrawn']) ? (int)$counts[$type]['withdrawn'] : 0;
            $denominator = max(1,$draft+$reviewed+$published+$withdrawn);
            echo '<div class="ino-gov-status-row"><strong>' . esc_html($name) . '</strong><div class="ino-gov-status-track" aria-hidden="true">';
            foreach (array('draft'=>$draft,'reviewed'=>$reviewed,'published'=>$published,'withdrawn'=>$withdrawn) as $status=>$value) {
                if ($value) { echo '<span class="ino-gov-status-part ino-gov-status-' . esc_attr($status) . '" style="width:' . esc_attr(round($value*100/$denominator, 3)) . '%"></span>'; }
            }
            echo '</div><span class="ino-caption">' . esc_html(sprintf('Draft %d · Reviewed %d · Published %d · Withdrawn %d',$draft,$reviewed,$published,$withdrawn)) . '</span></div>';
        }
        echo '</div></section>';

        if (current_user_can('ino_governance_edit')) {
            echo '<section class="ino-panel"><h2>Create governance draft</h2><p class="ino-panel-help">Use only source-supported descriptions. New records are not adopted, authenticated, or published automatically. Documents must already be safe for public access before attaching through the WordPress Media Library.</p>';
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="ino-gov-form">';
            echo '<input type="hidden" name="action" value="ino_gov_save">';
            wp_nonce_field('ino_gov_save', 'ino_gov_nonce');
            echo '<label>Record type<select name="record_type"><option value="constitution">Constitution version</option><option value="office">Organizational office</option><option value="record">Governance record</option></select></label>';
            echo '<label>Title <input name="title" required maxlength="190"></label><label>Internal reference code (optional)<input name="record_code" maxlength="90" placeholder="Auto-generated if blank"></label>';
            echo '<label>Version / edition<input name="version_label" maxlength="60"></label><label>Authority or source reference<input name="source_ref" maxlength="190" placeholder="Actual document reference, not a claim of review"></label>';
            echo '<label>Summary<textarea name="summary" rows="3"></textarea></label><label>Parent office<select name="parent_id"><option value="0">None</option>';
            foreach ($offices as $office) {
                echo '<option value="' . esc_attr($office->id) . '">' . esc_html($office->title . ' (' . $office->status . ')') . '</option>';
            }
            echo '</select></label><label>Recorded adoption date<input type="date" name="adopted_on"></label><label>Effective date<input type="date" name="effective_on"></label>';
            echo '<label>Public PDF attachment ID (Constitution only)<input type="number" min="0" step="1" name="attachment_id" placeholder="Existing publicly accessible PDF only"></label>';
            echo '<label>Access classification<select name="visibility"><option value="restricted">Restricted</option><option value="public">Public (when authorized and published)</option></select></label>';
            echo '<button class="ino-btn ino-btn-primary" type="submit">Save draft record</button></form></section>';
        }

        echo '<section class="ino-panel"><h2>Recent governance records</h2><p class="ino-panel-help">Publication requires a separate reviewer and explicitly authorized publisher. No governance roles or offices are populated by assumption.</p><div class="ino-gov-table-wrap"><table class="ino-table"><thead><tr><th>Record</th><th>Type</th><th>Status</th><th>Access</th><th>Review / actions</th></tr></thead><tbody>';
        if (!$all) { echo '<tr><td colspan="5">No records yet. Add a documented draft to begin.</td></tr>'; }
        foreach ((array)$all as $item) {
            echo '<tr><td><strong>' . esc_html($item->title) . '</strong><br><small>' . esc_html($item->record_code) . '</small></td><td>' . esc_html($item->record_type) . '</td><td><span class="ino-badge">' . esc_html($item->status) . '</span></td><td>' . esc_html($item->visibility) . '</td><td>';
            if ($item->status === 'draft' && current_user_can('ino_governance_review')) {
                self::action_form($item->id, 'review', 'Record evidence reviewed', true);
            } elseif ($item->status === 'reviewed' && current_user_can('ino_governance_publish') &&
                      (int)$item->reviewed_by !== get_current_user_id()) {
                self::action_form($item->id, 'publish', 'Authorize publication', false);
            } elseif ($item->status === 'draft' && current_user_can('ino_governance_edit')) {
                self::action_form($item->id, 'withdraw', 'Withdraw draft', false);
            } else {
                echo '<span class="ino-caption">No available action</span>';
            }
            echo '</td></tr>';
        }
        echo '</tbody></table></div><p class="ino-note">WordPress administrators receive draft-entry rights for technical setup. Review and publication require separately assigned governance capabilities. Publishing does not determine the Constitution’s legal validity or authenticate historic signatures.</p></section>';
        $audit_rows = $wpdb->get_results("SELECT item_id,event,actor_id,note,occurred_at FROM " . $wpdb->prefix . self::AUDIT_SUFFIX . " ORDER BY id DESC LIMIT 25");
        echo '<section class="ino-panel"><h2>Recent institutional audit activity</h2><p class="ino-panel-help">Recorded governance events restricted to authorized INO personnel.</p><div class="ino-gov-table-wrap"><table class="ino-table"><thead><tr><th>When</th><th>Record ID</th><th>Event</th><th>Actor</th><th>Evidence note</th></tr></thead><tbody>';
        if (!$audit_rows) { echo '<tr><td colspan="5">No recorded governance events.</td></tr>'; }
        foreach ((array)$audit_rows as $event) {
            echo '<tr><td>' . esc_html($event->occurred_at) . '</td><td>' . esc_html($event->item_id) . '</td><td>' . esc_html(str_replace('_',' ',$event->event)) . '</td><td>' . esc_html($event->actor_id) . '</td><td>' . esc_html($event->note) . '</td></tr>';
        }
        echo '</tbody></table></div></section></main>';
    }

    private static function action_form($id, $action, $label, $note_required) {
        echo '<form class="ino-gov-mini-form" method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="ino_gov_transition"><input type="hidden" name="record_id" value="' . esc_attr($id) . '"><input type="hidden" name="transition" value="' . esc_attr($action) . '">';
        wp_nonce_field('ino_gov_transition', 'ino_gov_nonce');
        if ($note_required) { echo '<label class="screen-reader-text" for="gov-note-' . esc_attr($id) . '">Review note for record ' . esc_html($id) . '</label><textarea id="gov-note-' . esc_attr($id) . '" name="note" required minlength="12" placeholder="Evidence review note" rows="2"></textarea>'; }
        if ($action === 'publish') {
            echo '<label class="ino-gov-attestation"><input type="checkbox" name="publish_attestation" value="1" required> I attest I am authorized to publish this reviewed INO record.</label>';
        }
        echo '<button type="submit" class="ino-gov-action">' . esc_html($label) . '</button></form>';
    }
}
