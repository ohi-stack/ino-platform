<?php
if (!defined('ABSPATH')) { exit; }

class INO_Platform_Activator {
    public static function activate() {
        self::create_tables();
        self::create_roles();
        self::create_pages();
        update_option('ino_platform_version', INO_PLATFORM_VERSION);
        flush_rewrite_rules();
    }

    private static function create_roles() {
        add_role('ino_member', 'INO Member', array('read' => true, 'upload_files' => true));
        add_role('ino_volunteer', 'INO Volunteer', array('read' => true, 'upload_files' => true));
        add_role('ino_program_manager', 'INO Program Manager', array('read' => true, 'upload_files' => true, 'edit_posts' => true));
    }

    private static function create_tables() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $cc = $wpdb->get_charset_collate();
        $p = $wpdb->prefix;
        $tables = array(
            "CREATE TABLE {$p}ino_members (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id BIGINT UNSIGNED NULL,
                member_id VARCHAR(50) NOT NULL,
                first_name VARCHAR(100) DEFAULT '',
                last_name VARCHAR(100) DEFAULT '',
                email VARCHAR(190) DEFAULT '',
                member_type VARCHAR(80) DEFAULT 'INO Member',
                status VARCHAR(40) DEFAULT 'Pending',
                ancestral_identity VARCHAR(190) DEFAULT '',
                clan_family VARCHAR(190) DEFAULT '',
                verification_status VARCHAR(80) DEFAULT 'Self-declared',
                public_consent TINYINT(1) DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id), UNIQUE KEY member_id (member_id), KEY user_id (user_id)
            ) $cc;",
            "CREATE TABLE {$p}ino_connections (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                requester_id BIGINT UNSIGNED NOT NULL,
                recipient_id BIGINT UNSIGNED NOT NULL,
                connection_type VARCHAR(60) DEFAULT 'community',
                status VARCHAR(30) DEFAULT 'pending',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id), UNIQUE KEY connection_pair (requester_id,recipient_id,connection_type), KEY recipient_status (recipient_id,status)
            ) $cc;",
            "CREATE TABLE {$p}ino_family_relationships (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                person_a BIGINT UNSIGNED NOT NULL,
                person_b BIGINT UNSIGNED NOT NULL,
                relationship_type VARCHAR(60) NOT NULL,
                inverse_type VARCHAR(60) DEFAULT '',
                lineage_side VARCHAR(30) DEFAULT '',
                verification_status VARCHAR(80) DEFAULT 'Self-declared',
                evidence_notes TEXT NULL,
                visibility VARCHAR(30) DEFAULT 'members',
                status VARCHAR(30) DEFAULT 'pending',
                created_by BIGINT UNSIGNED NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id), KEY person_a (person_a), KEY person_b (person_b), KEY status (status)
            ) $cc;",
            "CREATE TABLE {$p}ino_identity_declarations (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id BIGINT UNSIGNED NOT NULL,
                ancestral_people VARCHAR(190) DEFAULT '',
                tribe_nation_clan VARCHAR(190) DEFAULT '',
                family_lineage VARCHAR(190) DEFAULT '',
                homeland VARCHAR(190) DEFAULT '',
                language_tradition VARCHAR(190) DEFAULT '',
                maternal_lineage TEXT NULL,
                paternal_lineage TEXT NULL,
                family_narrative LONGTEXT NULL,
                preferred_wording TEXT NULL,
                verification_status VARCHAR(80) DEFAULT 'Self-declared',
                privacy_level VARCHAR(40) DEFAULT 'private',
                review_status VARCHAR(40) DEFAULT 'pending',
                reviewed_by BIGINT UNSIGNED NULL,
                reviewed_at DATETIME NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id), KEY user_id (user_id), KEY review_status (review_status)
            ) $cc;",
            "CREATE TABLE {$p}ino_grants (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                tracking_id VARCHAR(50) NOT NULL,
                funding_source VARCHAR(190) DEFAULT '', program_name VARCHAR(190) DEFAULT '', funding_type VARCHAR(100) DEFAULT '',
                amount_available VARCHAR(100) DEFAULT '', deadline DATE NULL, status VARCHAR(60) DEFAULT 'Identified', assigned_to VARCHAR(190) DEFAULT '', notes TEXT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id), UNIQUE KEY tracking_id (tracking_id)
            ) $cc;",
            "CREATE TABLE {$p}ino_housing_projects (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, project_code VARCHAR(50) NOT NULL, title VARCHAR(190) NOT NULL, location VARCHAR(190) DEFAULT '',
                status VARCHAR(80) DEFAULT 'Planning', funding_status VARCHAR(80) DEFAULT 'Not Funded', notes TEXT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id), UNIQUE KEY project_code (project_code)
            ) $cc;",
            "CREATE TABLE {$p}ino_documents (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, document_title VARCHAR(190) NOT NULL, document_category VARCHAR(100) DEFAULT 'General', document_url TEXT NULL,
                version VARCHAR(40) DEFAULT '1.0', status VARCHAR(50) DEFAULT 'Filed', created_at DATETIME DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id)
            ) $cc;"
        );
        foreach ($tables as $sql) { dbDelta($sql); }
    }

    private static function create_pages() {
        $pages = array(
            'ino-portal' => array('INO Portal','[ino_portal]'),
            'about-ino-platform' => array('About the INO Platform','[ino_about]'),
            'ino-membership' => array('INO Membership','[ino_membership]'),
            'ino-citizenship' => array('INO Citizenship','[ino_citizenship]'),
            'member-directory' => array('Member Directory','[ino_member_directory]'),
            'member-profile' => array('Member Profile','[ino_member_profile]'),
            'people-network' => array('People Network','[ino_people_network]'),
            'family-tree' => array('Family Tree','[ino_family_tree]'),
            'choose-your-identity' => array('Choose Your Identity','[ino_identity_intro]'),
            'honor-your-ancestry' => array('Honor Your Ancestry','[ino_identity_intro]'),
            'identity-declaration' => array('Identity Declaration','[ino_identity_declaration]'),
            'identity-heritage-dashboard' => array('Identity and Heritage Dashboard','[ino_identity_dashboard]'),
            'peoples-book-of-names' => array("People's Book of Names",'[ino_book_of_names]'),
            'tribal-family-archives' => array('Tribal and Family Archives','[ino_family_archives]'),
            'dual-identity-certificates' => array('Dual Identity Certificates','[ino_certificates]'),
            'verify-identity-record' => array('Verify Identity Record','[ino_verify_identity]'),
            'identity-standards' => array('Identity Standards and Disclosures','[ino_identity_standards]'),
            'ino-treasury-grants' => array('INO Treasury & Grants','[ino_treasury_grants]'),
            'ino-housing-development' => array('INO Housing Development','[ino_housing]'),
            'ino-document-registry' => array('INO Document Registry','[ino_documents]'),
            'ino-governance' => array('INO Governance','[ino_governance]'),
            'ino-community-programs' => array('INO Community Programs','[ino_community]'),
            'ino-contact' => array('Contact INO','[ino_contact_form]')
        );
        $ids = (array) get_option('ino_platform_page_ids', array());
        foreach ($pages as $slug => $data) {
            $page = get_page_by_path($slug);
            if (!$page) {
                $id = wp_insert_post(array('post_title'=>$data[0],'post_name'=>$slug,'post_content'=>$data[1],'post_status'=>'publish','post_type'=>'page','comment_status'=>'closed'));
                if (!is_wp_error($id)) { $ids[$slug] = (int) $id; }
            } else { $ids[$slug] = (int) $page->ID; }
        }
        update_option('ino_platform_page_ids', $ids);
    }
}
