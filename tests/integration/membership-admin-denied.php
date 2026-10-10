<?php
if (!defined('ABSPATH') || !defined('WP_CLI')) { exit(1); }
$actor=get_user_by('login','ino_stage_outsider');
if (!$actor) { WP_CLI::error('Missing unauthorized-member fixture.'); }
wp_set_current_user($actor->ID);
INO_Platform_Membership_UI::admin_page();
WP_CLI::error('Member unexpectedly entered admin membership roster.');
