<?php
if (!defined('ABSPATH')) { exit; }

class INO_Platform_Community {
    public static function init() {
        self::install();
        add_shortcode('ino_community', array(__CLASS__, 'render_page'));
        add_action('wp_enqueue_scripts', array(__CLASS__, 'assets'));
    }

    private static function can_manage() {
        return current_user_can('manage_options') || current_user_can('ino_manage_programs') || current_user_can('edit_posts');
    }

    public static function install() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $cc = $wpdb->get_charset_collate();
        $p = $wpdb->prefix;

        dbDelta("CREATE TABLE {$p}ino_programs (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            program_code VARCHAR(60) NOT NULL,
            title VARCHAR(190) NOT NULL,
            category VARCHAR(100) DEFAULT 'Community Support',
            audience VARCHAR(190) DEFAULT '',
            summary TEXT NULL,
            eligibility TEXT NULL,
            required_documents TEXT NULL,
            administrator_user_id BIGINT UNSIGNED NULL,
            application_open DATE NULL,
            application_close DATE NULL,
            capacity INT UNSIGNED DEFAULT 0,
            status VARCHAR(40) DEFAULT 'Draft',
            visibility VARCHAR(30) DEFAULT 'public',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id), UNIQUE KEY program_code (program_code), KEY status (status)
        ) $cc;");

        dbDelta("CREATE TABLE {$p}ino_program_applications (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            application_code VARCHAR(60) NOT NULL,
            program_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NULL,
            applicant_name VARCHAR(190) NOT NULL,
            applicant_email VARCHAR(190) NOT NULL,
            phone VARCHAR(60) DEFAULT '',
            household_size INT UNSIGNED DEFAULT 1,
            statement TEXT NULL,
            requested_support TEXT NULL,
            status VARCHAR(40) DEFAULT 'Submitted',
            assigned_to BIGINT UNSIGNED NULL,
            decision_notes TEXT NULL,
            submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id), UNIQUE KEY application_code (application_code), KEY program_id (program_id), KEY user_id (user_id), KEY status (status)
        ) $cc;");

        dbDelta("CREATE TABLE {$p}ino_program_workflow (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            application_id BIGINT UNSIGNED NOT NULL,
            from_status VARCHAR(40) DEFAULT '',
            to_status VARCHAR(40) NOT NULL,
            action_note TEXT NULL,
            acted_by BIGINT UNSIGNED NULL,
            acted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id), KEY application_id (application_id), KEY to_status (to_status)
        ) $cc;");

        dbDelta("CREATE TABLE {$p}ino_program_documents (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            application_id BIGINT UNSIGNED NOT NULL,
            document_type VARCHAR(100) NOT NULL,
            attachment_id BIGINT UNSIGNED NULL,
            review_status VARCHAR(40) DEFAULT 'Pending',
            reviewer_notes TEXT NULL,
            uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id), KEY application_id (application_id), KEY review_status (review_status)
        ) $cc;");

        $role = get_role('ino_program_manager');
        if ($role) { $role->add_cap('ino_manage_programs'); }
        $admin = get_role('administrator');
        if ($admin) { $admin->add_cap('ino_manage_programs'); }

        if (!get_option('ino_community_seeded')) {
            $seed = array(
                array('INO-CP-001','Family Support','Family Support','Individuals and families','Practical referrals, resource navigation, and coordinated family-support services.','Open to eligible INO members and approved community participants.','Identity, household, and supporting need documentation as applicable.'),
                array('INO-CP-002','Youth Development','Youth','Youth and young adults','Mentoring, leadership, cultural learning, education support, and positive-development activities.','Program-specific age and participation requirements apply.','Guardian consent and participation records where required.'),
                array('INO-CP-003','Elder Support','Elders','Elders and caregivers','Connection, wellness, resource navigation, transportation coordination, and cultural participation.','Available according to program capacity and service area.','Basic intake and service-consent records.'),
                array('INO-CP-004','Education & Learning','Education','Members, families, and community learners','Courses, workshops, financial education, workforce preparation, and leadership development.','Course or workshop requirements vary.','Registration and prerequisite records when applicable.'),
                array('INO-CP-005','Cultural Preservation','Culture','Members, families, researchers, and cultural participants','Oral history, language, family archives, cultural documentation, and community heritage projects.','Participation must follow privacy, consent, and records standards.','Release, consent, and supporting historical records.'),
                array('INO-CP-006','Volunteer Service','Volunteer','Members and approved volunteers','Structured service opportunities, assignments, hour tracking, supervision, and recognition.','Application, screening, and assignment approval may be required.','Volunteer application, consent, qualifications, and service records.'),
                array('INO-CP-007','Community Development','Development','Residents, partners, and program participants','Neighborhood improvement, institutional capacity, partnership development, and community initiatives.','Eligibility depends on project scope and funding terms.','Project intake and partnership documentation.'),
                array('INO-CP-008','Economic & Workforce Development','Economic Development','Entrepreneurs, workers, and learners','Career pathways, entrepreneurship, digital skills, financial capability, and business development.','Program-specific readiness and participation requirements apply.','Resume, business concept, or training records as applicable.')
            );
            foreach ($seed as $row) {
                $wpdb->insert($p.'ino_programs', array('program_code'=>$row[0],'title'=>$row[1],'category'=>$row[2],'audience'=>$row[3],'summary'=>$row[4],'eligibility'=>$row[5],'required_documents'=>$row[6],'status'=>'Active','visibility'=>'public'));
            }
            update_option('ino_community_seeded', 1);
        }
    }

    public static function assets() {
        wp_register_style('ino-community-module', false, array(), INO_PLATFORM_VERSION);
        wp_enqueue_style('ino-community-module');
        wp_add_inline_style('ino-community-module', self::css());
    }

    private static function css() {
        return '.ino-community{--navy:#071b2d;--navy2:#0d2b45;--gold:#c99a2e;--cream:#f6e9bf;--red:#8f1d2c;--white:#fff;max-width:1180px;margin:0 auto;padding:32px 18px;color:#13263a}.ino-community *{box-sizing:border-box}.ino-cp-hero{background:linear-gradient(135deg,#071b2d 0%,#0d2b45 68%,#8f1d2c 150%);color:#fff;border:1px solid rgba(201,154,46,.45);border-radius:24px;padding:54px;box-shadow:0 18px 50px rgba(7,27,45,.18)}.ino-cp-kicker{display:inline-block;border:1px solid rgba(201,154,46,.7);border-radius:999px;padding:9px 15px;color:#f6d77b;font-weight:800;letter-spacing:.08em;text-transform:uppercase;font-size:12px}.ino-cp-hero h1{color:#fff;font-size:48px;line-height:1.05;margin:22px 0 16px}.ino-cp-hero p{font-size:18px;line-height:1.7;max-width:820px}.ino-cp-actions{display:flex;gap:12px;flex-wrap:wrap;margin-top:26px}.ino-cp-btn{display:inline-block;padding:13px 18px;border-radius:9px;text-decoration:none;font-weight:800;border:0;cursor:pointer}.ino-cp-btn.gold{background:#c99a2e;color:#071b2d}.ino-cp-btn.light{background:#fff;color:#071b2d}.ino-cp-section{padding:42px 0}.ino-cp-section h2{color:#071b2d;font-size:34px;margin:0 0 12px}.ino-cp-lead{max-width:860px;line-height:1.75}.ino-cp-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px;margin-top:22px}.ino-cp-card{background:#fff;border:1px solid #d9e1e7;border-top:4px solid #c99a2e;border-radius:15px;padding:24px;box-shadow:0 8px 24px rgba(7,27,45,.07)}.ino-cp-card h3{color:#071b2d;margin-top:0}.ino-cp-meta{font-size:13px;color:#5a6a78}.ino-cp-status{display:inline-block;padding:5px 10px;border-radius:999px;background:#eef4f7;color:#0d2b45;font-weight:800;font-size:12px}.ino-cp-panel{background:#f9fbfc;border:1px solid #d9e1e7;border-radius:18px;padding:26px;margin-top:22px}.ino-cp-metrics{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}.ino-cp-metric{background:#071b2d;color:#fff;border-radius:14px;padding:20px}.ino-cp-metric strong{display:block;color:#f6d77b;font-size:28px}.ino-cp-form{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:15px}.ino-cp-form .full{grid-column:1/-1}.ino-cp-form label{font-weight:800;color:#071b2d}.ino-cp-form input,.ino-cp-form textarea,.ino-cp-form select{width:100%;margin-top:7px;padding:12px;border:1px solid #bfcbd4;border-radius:8px;background:#fff}.ino-cp-table{width:100%;border-collapse:collapse;margin-top:15px}.ino-cp-table th,.ino-cp-table td{text-align:left;padding:12px;border-bottom:1px solid #d9e1e7;vertical-align:top}.ino-cp-table th{color:#071b2d;background:#f6e9bf}.ino-cp-notice{padding:15px 17px;background:#fff8df;border-left:4px solid #c99a2e;margin:18px 0}.ino-cp-workflow{display:grid;grid-template-columns:repeat(6,1fr);gap:8px;margin-top:18px}.ino-cp-step{padding:12px 8px;text-align:center;background:#071b2d;color:#fff;border-radius:8px;font-size:12px;font-weight:800}@media(max-width:900px){.ino-cp-grid,.ino-cp-metrics{grid-template-columns:repeat(2,1fr)}.ino-cp-workflow{grid-template-columns:repeat(3,1fr)}}@media(max-width:620px){.ino-cp-hero{padding:32px 24px}.ino-cp-hero h1{font-size:38px}.ino-cp-grid,.ino-cp-metrics,.ino-cp-form{grid-template-columns:1fr}.ino-cp-form .full{grid-column:auto}.ino-cp-table{display:block;overflow-x:auto}}';
    }

    private static function handle_application() {
        if (empty($_POST['ino_program_apply']) || !isset($_POST['_ino_program_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_ino_program_nonce'])), 'ino_program_apply')) { return ''; }
        global $wpdb;
        $code = 'INO-APP-' . gmdate('Ymd') . '-' . strtoupper(wp_generate_password(6, false, false));
        $ok = $wpdb->insert($wpdb->prefix.'ino_program_applications', array(
            'application_code'=>$code,
            'program_id'=>absint($_POST['program_id'] ?? 0),
            'user_id'=>get_current_user_id() ?: null,
            'applicant_name'=>sanitize_text_field(wp_unslash($_POST['applicant_name'] ?? '')),
            'applicant_email'=>sanitize_email(wp_unslash($_POST['applicant_email'] ?? '')),
            'phone'=>sanitize_text_field(wp_unslash($_POST['phone'] ?? '')),
            'household_size'=>max(1, absint($_POST['household_size'] ?? 1)),
            'statement'=>sanitize_textarea_field(wp_unslash($_POST['statement'] ?? '')),
            'requested_support'=>sanitize_textarea_field(wp_unslash($_POST['requested_support'] ?? '')),
            'status'=>'Submitted'
        ));
        if ($ok) {
            $id = (int) $wpdb->insert_id;
            $wpdb->insert($wpdb->prefix.'ino_program_workflow', array('application_id'=>$id,'from_status'=>'','to_status'=>'Submitted','action_note'=>'Application received through public Community Programs page.','acted_by'=>get_current_user_id() ?: null));
            return '<div class="ino-cp-notice"><strong>Application received.</strong> Save your tracking code: '.esc_html($code).'. Submission does not guarantee eligibility, acceptance, funding, service, or placement.</div>';
        }
        return '<div class="ino-cp-notice">The application could not be saved. Please review the required fields and try again.</div>';
    }

    private static function handle_status_update() {
        if (!self::can_manage() || empty($_POST['ino_program_status_update']) || !isset($_POST['_ino_status_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_ino_status_nonce'])), 'ino_program_status_update')) { return ''; }
        global $wpdb;
        $id = absint($_POST['application_id'] ?? 0);
        $new = sanitize_text_field(wp_unslash($_POST['new_status'] ?? 'Under Review'));
        $old = $wpdb->get_var($wpdb->prepare("SELECT status FROM {$wpdb->prefix}ino_program_applications WHERE id=%d", $id));
        if (!$old) { return ''; }
        $wpdb->update($wpdb->prefix.'ino_program_applications', array('status'=>$new,'assigned_to'=>get_current_user_id(),'decision_notes'=>sanitize_textarea_field(wp_unslash($_POST['decision_notes'] ?? ''))), array('id'=>$id));
        $wpdb->insert($wpdb->prefix.'ino_program_workflow', array('application_id'=>$id,'from_status'=>$old,'to_status'=>$new,'action_note'=>sanitize_textarea_field(wp_unslash($_POST['decision_notes'] ?? '')),'acted_by'=>get_current_user_id()));
        return '<div class="ino-cp-notice">Application status updated.</div>';
    }

    public static function render_page() {
        global $wpdb;
        $message = self::handle_application() . self::handle_status_update();
        $programs = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}ino_programs WHERE status IN ('Active','Open') AND visibility='public' ORDER BY title ASC");
        $user_apps = is_user_logged_in() ? $wpdb->get_results($wpdb->prepare("SELECT a.*,p.title program_title FROM {$wpdb->prefix}ino_program_applications a LEFT JOIN {$wpdb->prefix}ino_programs p ON p.id=a.program_id WHERE a.user_id=%d ORDER BY a.submitted_at DESC", get_current_user_id())) : array();
        $all_apps = self::can_manage() ? $wpdb->get_results("SELECT a.*,p.title program_title FROM {$wpdb->prefix}ino_program_applications a LEFT JOIN {$wpdb->prefix}ino_programs p ON p.id=a.program_id ORDER BY a.submitted_at DESC LIMIT 100") : array();
        $counts = array(
            'programs'=>(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ino_programs WHERE status IN ('Active','Open')"),
            'submitted'=>(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ino_program_applications"),
            'review'=>(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ino_program_applications WHERE status IN ('Submitted','Screening','Under Review','Documents Requested')"),
            'approved'=>(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ino_program_applications WHERE status IN ('Approved','Enrolled','Completed')")
        );
        ob_start(); ?>
        <main class="ino-community">
            <section class="ino-cp-hero">
                <span class="ino-cp-kicker">Indigenous Nation of Onegodia</span>
                <h1>Community Programs</h1>
                <p>INO Community Programs strengthen individuals, families, elders, youth, volunteers, learners, and community partners through organized services, education, cultural preservation, development, and accountable participation.</p>
                <div class="ino-cp-actions"><a class="ino-cp-btn gold" href="#ino-program-application">Apply for a Program</a><a class="ino-cp-btn light" href="#ino-program-dashboard">View Dashboard</a></div>
            </section>
            <?php echo wp_kses_post($message); ?>

            <section class="ino-cp-section">
                <h2>Our Commitment</h2>
                <p class="ino-cp-lead">INO is committed to transparent administration, responsible stewardship, accurate recordkeeping, ethical service delivery, and consistent program management that supports its religious, cultural, educational, and community mission. Programs are administered through defined eligibility standards, documented applications, review workflows, privacy controls, status histories, and outcome reporting.</p>
            </section>

            <section class="ino-cp-section">
                <h2>Program Registry</h2>
                <p class="ino-cp-lead">Each active program has an institutional code, defined purpose, audience, eligibility terms, document requirements, status, and administrative record.</p>
                <div class="ino-cp-grid">
                    <?php foreach ($programs as $program): ?>
                    <article class="ino-cp-card">
                        <span class="ino-cp-status"><?php echo esc_html($program->status); ?></span>
                        <h3><?php echo esc_html($program->title); ?></h3>
                        <p><?php echo esc_html($program->summary); ?></p>
                        <p class="ino-cp-meta"><strong>Code:</strong> <?php echo esc_html($program->program_code); ?><br><strong>Audience:</strong> <?php echo esc_html($program->audience); ?></p>
                        <details><summary><strong>Eligibility & documents</strong></summary><p><?php echo esc_html($program->eligibility); ?></p><p><?php echo esc_html($program->required_documents); ?></p></details>
                    </article>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="ino-cp-section" id="ino-program-application">
                <h2>Program Application Form</h2>
                <div class="ino-cp-panel">
                    <form method="post" class="ino-cp-form">
                        <?php wp_nonce_field('ino_program_apply','_ino_program_nonce'); ?>
                        <input type="hidden" name="ino_program_apply" value="1">
                        <label>Program<select name="program_id" required><option value="">Select a program</option><?php foreach ($programs as $program): ?><option value="<?php echo esc_attr($program->id); ?>"><?php echo esc_html($program->title); ?></option><?php endforeach; ?></select></label>
                        <label>Full name<input name="applicant_name" required value="<?php echo esc_attr(is_user_logged_in() ? wp_get_current_user()->display_name : ''); ?>"></label>
                        <label>Email<input type="email" name="applicant_email" required value="<?php echo esc_attr(is_user_logged_in() ? wp_get_current_user()->user_email : ''); ?>"></label>
                        <label>Phone<input name="phone"></label>
                        <label>Household size<input type="number" min="1" name="household_size" value="1"></label>
                        <label class="full">Why are you applying?<textarea name="statement" rows="5" required></textarea></label>
                        <label class="full">Support or outcome requested<textarea name="requested_support" rows="4"></textarea></label>
                        <div class="full ino-cp-notice">Submitting this form creates an institutional application record. It does not guarantee eligibility, acceptance, funding, housing, employment, payment, service availability, or placement.</div>
                        <div class="full"><button class="ino-cp-btn gold" type="submit">Submit Application</button></div>
                    </form>
                </div>
            </section>

            <section class="ino-cp-section" id="ino-program-dashboard">
                <h2>Program Dashboard</h2>
                <div class="ino-cp-metrics"><div class="ino-cp-metric"><strong><?php echo esc_html($counts['programs']); ?></strong>Active programs</div><div class="ino-cp-metric"><strong><?php echo esc_html($counts['submitted']); ?></strong>Applications</div><div class="ino-cp-metric"><strong><?php echo esc_html($counts['review']); ?></strong>In review</div><div class="ino-cp-metric"><strong><?php echo esc_html($counts['approved']); ?></strong>Approved or completed</div></div>
                <div class="ino-cp-workflow"><div class="ino-cp-step">Submitted</div><div class="ino-cp-step">Screening</div><div class="ino-cp-step">Documents</div><div class="ino-cp-step">Review</div><div class="ino-cp-step">Decision</div><div class="ino-cp-step">Completion</div></div>

                <?php if (is_user_logged_in()): ?>
                <div class="ino-cp-panel"><h3>My Program Applications</h3>
                    <?php if (!$user_apps): ?><p>No program applications are connected to this account.</p><?php else: ?>
                    <table class="ino-cp-table"><thead><tr><th>Tracking code</th><th>Program</th><th>Status</th><th>Submitted</th></tr></thead><tbody><?php foreach ($user_apps as $app): ?><tr><td><?php echo esc_html($app->application_code); ?></td><td><?php echo esc_html($app->program_title); ?></td><td><?php echo esc_html($app->status); ?></td><td><?php echo esc_html($app->submitted_at); ?></td></tr><?php endforeach; ?></tbody></table><?php endif; ?>
                </div>
                <?php endif; ?>

                <?php if (self::can_manage()): ?>
                <div class="ino-cp-panel"><h3>Program Administration</h3><p>Authorized administrators can review applications, document decisions, assign responsibility, and maintain an immutable status history.</p>
                    <table class="ino-cp-table"><thead><tr><th>Application</th><th>Applicant</th><th>Program</th><th>Current status</th><th>Workflow action</th></tr></thead><tbody>
                    <?php foreach ($all_apps as $app): ?><tr><td><?php echo esc_html($app->application_code); ?></td><td><?php echo esc_html($app->applicant_name); ?><br><small><?php echo esc_html($app->applicant_email); ?></small></td><td><?php echo esc_html($app->program_title); ?></td><td><?php echo esc_html($app->status); ?></td><td><form method="post"><?php wp_nonce_field('ino_program_status_update','_ino_status_nonce'); ?><input type="hidden" name="ino_program_status_update" value="1"><input type="hidden" name="application_id" value="<?php echo esc_attr($app->id); ?>"><select name="new_status"><option>Screening</option><option>Documents Requested</option><option>Under Review</option><option>Approved</option><option>Denied</option><option>Waitlisted</option><option>Enrolled</option><option>Completed</option><option>Closed</option></select><textarea name="decision_notes" rows="2" placeholder="Administrative note"></textarea><button class="ino-cp-btn gold" type="submit">Update</button></form></td></tr><?php endforeach; ?>
                    </tbody></table>
                </div>
                <?php endif; ?>
            </section>

            <section class="ino-cp-section"><h2>Institutional Controls</h2><div class="ino-cp-grid"><div class="ino-cp-card"><h3>Records</h3><p>Program codes, applications, workflow histories, documents, decisions, timestamps, and responsible users are maintained as structured records.</p></div><div class="ino-cp-card"><h3>Privacy & Access</h3><p>Public program information is separated from protected applicant, household, eligibility, and decision records through role-based access.</p></div><div class="ino-cp-card"><h3>Reporting</h3><p>Administrators can measure active programs, applications, review queues, approvals, enrollments, completion, participation, and program outcomes.</p></div></div></section>
        </main>
        <?php return ob_get_clean();
    }
}
