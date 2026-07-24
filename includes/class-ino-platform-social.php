<?php
if (!defined('ABSPATH')) { exit; }

class INO_Platform_Social {
    public static function init() {
        add_action('init', array(__CLASS__, 'handle_actions'));
        add_action('show_user_profile', array(__CLASS__, 'profile_fields'));
        add_action('edit_user_profile', array(__CLASS__, 'profile_fields'));
        add_action('personal_options_update', array(__CLASS__, 'save_profile_fields'));
        add_action('edit_user_profile_update', array(__CLASS__, 'save_profile_fields'));
        add_action('bp_setup_nav', array(__CLASS__, 'bp_nav'));
        add_action('bp_init', array(__CLASS__, 'bp_profile_group'));
    }

    public static function buddyPress_active() {
        return function_exists('buddypress');
    }

    public static function handle_actions() {
        if (!is_user_logged_in() || empty($_POST['ino_social_action'])) { return; }
        if (!isset($_POST['_ino_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_ino_nonce'])), 'ino_social_action')) { return; }
        global $wpdb;
        $uid = get_current_user_id();
        $action = sanitize_key($_POST['ino_social_action']);
        $target = isset($_POST['target_user']) ? absint($_POST['target_user']) : 0;
        if (!$target || $target === $uid) { return; }

        if ($action === 'connect') {
            if (self::buddyPress_active() && function_exists('friends_add_friend')) {
                friends_add_friend($uid, $target);
            } else {
                $wpdb->query($wpdb->prepare("INSERT IGNORE INTO {$wpdb->prefix}ino_connections (requester_id,recipient_id,connection_type,status) VALUES (%d,%d,%s,%s)", $uid, $target, 'community', 'pending'));
            }
        }
        if ($action === 'family_request') {
            $type = isset($_POST['relationship_type']) ? sanitize_text_field(wp_unslash($_POST['relationship_type'])) : 'relative';
            $allowed = array('parent','child','sibling','spouse','grandparent','grandchild','aunt_uncle','niece_nephew','cousin','relative','clan_connection');
            if (!in_array($type, $allowed, true)) { $type = 'relative'; }
            $wpdb->insert($wpdb->prefix . 'ino_family_relationships', array(
                'person_a'=>$uid,'person_b'=>$target,'relationship_type'=>$type,'verification_status'=>'Self-declared','visibility'=>'members','status'=>'pending','created_by'=>$uid
            ), array('%d','%d','%s','%s','%s','%s','%d'));
        }
        wp_safe_redirect(wp_get_referer() ? wp_get_referer() : home_url('/member-directory/'));
        exit;
    }

    public static function profile_fields($user) { ?>
        <h2>INO Modern Profile</h2>
        <table class="form-table"><tbody>
        <?php self::row('ino_preferred_name','Preferred public name',$user); ?>
        <?php self::row('ino_ancestral_identity','Declared ancestral identity',$user); ?>
        <?php self::row('ino_tribe_clan','Tribe, nation, clan, or community',$user); ?>
        <?php self::row('ino_family_lineage','Family or lineage name',$user); ?>
        <?php self::row('ino_homeland','Homeland or country of origin',$user); ?>
        <?php self::row('ino_language','Language or cultural tradition',$user); ?>
        <?php self::row('ino_membership_class','INO membership classification',$user); ?>
        <?php self::row('ino_membership_number','INO membership number',$user); ?>
        <tr><th><label for="ino_cover_image">Cover image URL</label></th><td><input class="regular-text" type="url" name="ino_cover_image" id="ino_cover_image" value="<?php echo esc_attr(get_user_meta($user->ID,'ino_cover_image',true)); ?>"><p class="description">Used by the fallback profile. BuddyPress cover images take precedence when available.</p></td></tr>
        </tbody></table>
    <?php }

    private static function row($key,$label,$user) {
        echo '<tr><th><label for="'.esc_attr($key).'">'.esc_html($label).'</label></th><td><input class="regular-text" type="text" name="'.esc_attr($key).'" id="'.esc_attr($key).'" value="'.esc_attr(get_user_meta($user->ID,$key,true)).'"></td></tr>';
    }

    public static function save_profile_fields($user_id) {
        if (!current_user_can('edit_user', $user_id)) { return; }
        $keys = array('ino_preferred_name','ino_ancestral_identity','ino_tribe_clan','ino_family_lineage','ino_homeland','ino_language','ino_membership_class','ino_membership_number','ino_cover_image');
        foreach ($keys as $key) {
            if (isset($_POST[$key])) {
                $value = $key === 'ino_cover_image' ? esc_url_raw(wp_unslash($_POST[$key])) : sanitize_text_field(wp_unslash($_POST[$key]));
                update_user_meta($user_id, $key, $value);
            }
        }
    }

    public static function bp_nav() {
        if (!self::buddyPress_active() || !function_exists('bp_core_new_nav_item')) { return; }
        bp_core_new_nav_item(array(
            'name'=>'Family & Connections','slug'=>'ino-family-connections','screen_function'=>array(__CLASS__,'bp_screen'),'position'=>65,'default_subnav_slug'=>'ino-family-connections','show_for_displayed_user'=>true
        ));
    }

    public static function bp_screen() {
        add_action('bp_template_content', array(__CLASS__, 'bp_screen_content'));
        bp_core_load_template(apply_filters('bp_core_template_plugin','members/single/plugins'));
    }

    public static function bp_screen_content() {
        echo do_shortcode('[ino_family_tree user_id="'.absint(bp_displayed_user_id()).'"]');
    }

    public static function bp_profile_group() {
        if (!self::buddyPress_active() || !function_exists('xprofile_insert_field')) { return; }
        if (get_option('ino_bp_fields_created')) { return; }
        $group_id = xprofile_insert_field(array('field_group_id'=>1,'name'=>'INO Identity & Heritage','description'=>'INO identity, heritage, and membership profile information','can_delete'=>false,'type'=>'option'));
        update_option('ino_bp_fields_created', $group_id ? 1 : 0);
    }

    public static function avatar($user_id, $size=160) {
        if (self::buddyPress_active() && function_exists('bp_core_fetch_avatar')) {
            return bp_core_fetch_avatar(array('item_id'=>$user_id,'type'=>'full','width'=>$size,'height'=>$size,'html'=>true));
        }
        return get_avatar($user_id, $size, '', '', array('class'=>'ino-avatar'));
    }

    public static function cover($user_id) {
        if (self::buddyPress_active() && function_exists('bp_attachments_get_attachment')) {
            $url = bp_attachments_get_attachment('url', array('item_id'=>$user_id,'object_dir'=>'members'));
            if ($url) { return $url; }
        }
        return get_user_meta($user_id, 'ino_cover_image', true);
    }
}
