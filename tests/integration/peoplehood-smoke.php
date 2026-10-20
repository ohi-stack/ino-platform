<?php
/** Isolated WordPress/CLI smoke checks; synthetic records only. */
if (!defined('ABSPATH') || !defined('WP_CLI')) { exit(1); }
global $wpdb;
function ino_iap_assert($ok,$description) {
    if (!$ok) { WP_CLI::error('IAP failed: '.$description); }
    WP_CLI::log('PASS IAP: '.$description);
}
ino_iap_assert(class_exists('INO_Platform_Peoplehood'),'module loaded');
ino_iap_assert(shortcode_exists('ino_peoplehood_registry'),'private shortcode registered');
$claims=$wpdb->prefix.'ino_peoplehood_claims';
$evidence=$wpdb->prefix.'ino_peoplehood_evidence';
$events=$wpdb->prefix.'ino_peoplehood_events';
$consents=$wpdb->prefix.'ino_peoplehood_consents';
foreach (array($claims,$evidence,$events,$consents) as $table) {
    ino_iap_assert($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$table))===$table,
        'additive registry table exists: '.$table);
}
$member=get_user_by('login','ino_stage_viewer');
$other=get_user_by('login','ino_stage_outsider');
$admin=get_user_by('login','admin_test');
ino_iap_assert($member && $other && $admin,'synthetic user fixtures exist');
$prior_members=(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ino_members");
$prior_decl=(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ino_identity_declarations");
wp_set_current_user(0);
ino_iap_assert(strpos(do_shortcode('[ino_peoplehood_registry]'),'Log in')!==false,
    'guests receive no private claims');
wp_set_current_user($member->ID);
$id=INO_Platform_Peoplehood::submit($member->ID,'ancestry','PRIVATE-IAP-A-TEST',
    'family_attested','family_record','Synthetic family ledger','TEST-ARCHIVE-01');
ino_iap_assert(is_int($id) && $id>0,'owner submitted private claim');
$row=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$claims} WHERE id=%d",$id));
ino_iap_assert($row && $row->review_state==='submitted' && $row->basis==='family_attested',
    'intake does not self-certify or automatically approve');
ino_iap_assert((int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$evidence} WHERE claim_id=%d",$id))===1,
    'private source-reference metadata linked');
ino_iap_assert(strpos(do_shortcode('[ino_peoplehood_registry]'),'PRIVATE-IAP-A-TEST')!==false,
    'owner sees own statement');
wp_set_current_user($other->ID);
ino_iap_assert(strpos(do_shortcode('[ino_peoplehood_registry]'),'PRIVATE-IAP-A-TEST')===false,
    'another member cannot read statement');
ino_iap_assert(is_wp_error(INO_Platform_Peoplehood::submit($member->ID,'ancestry','FORGED')),
    'forged actor ID rejected');
ino_iap_assert(is_wp_error(INO_Platform_Peoplehood::consent($other->ID,$id,true)),
    'cross-account consent attempt rejected');
ino_iap_assert(is_wp_error(INO_Platform_Peoplehood::submit($other->ID,'ancestry','wrong amendment',
    'self_declared','','','',$id)),'cannot amend another member claim');
ino_iap_assert(is_wp_error(INO_Platform_Peoplehood::review($id,'record_examined','No authority')),
    'nonadministrator cannot review');
wp_set_current_user($member->ID);
ino_iap_assert(INO_Platform_Peoplehood::consent($member->ID,$id,true)===true,
    'owner consent event recorded');
ino_iap_assert(INO_Platform_Peoplehood::consent($member->ID,$id,false)===true,
    'owner withdrawal event recorded');
$latest=$wpdb->get_var($wpdb->prepare("SELECT granted FROM {$consents} WHERE claim_id=%d ORDER BY id DESC LIMIT 1",$id));
ino_iap_assert((int)$latest===0,'consent withdrawn; latest event controls');
ino_iap_assert(is_wp_error(INO_Platform_Peoplehood::review($id,'record_examined','Self')),
    'member cannot review own record');
$revised=INO_Platform_Peoplehood::submit($member->ID,'ancestry','PRIVATE-IAP-REVISION',
    'self_declared','','','',$id);
ino_iap_assert(is_int($revised) && $revised>$id,
    'append-only amendment creates new private claim');
$rev=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$claims} WHERE id=%d",$revised));
ino_iap_assert((int)$rev->supersedes_id===$id && $rev->review_state==='submitted',
    'amendment links old record, resets review and does not inherit consent');
wp_set_current_user($admin->ID);
ino_iap_assert(is_wp_error(INO_Platform_Peoplehood::review($revised,'record_examined','No source')),
    'document review requires a source reference');
ino_iap_assert(INO_Platform_Peoplehood::review($id,'record_examined','Examined supplied citation, not external identity')===true,
    'independent administrator can record limited source examination');
ino_iap_assert(is_wp_error(INO_Platform_Peoplehood::review($id,'record_examined','Again')),
    'review cannot overwrite completed decision silently');
$updated=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$claims} WHERE id=%d",$id));
ino_iap_assert($updated->review_state==='record_examined' && (int)$updated->reviewed_by===$admin->ID,
    'reviewer and decision stored');
ino_iap_assert((int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$events} WHERE claim_id=%d",$id))>=4,
    'submission, consent history and review are auditable');
ino_iap_assert((int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ino_members")===$prior_members,
    'membership records retained and unmodified');
ino_iap_assert((int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ino_identity_declarations")===$prior_decl,
    'legacy identity declarations retained and unmodified');
wp_set_current_user(0);
ino_iap_assert(strpos(do_shortcode('[ino_peoplehood_registry]'),'PRIVATE-IAP-A-TEST')===false,
    'no guest publication following grant and withdrawal');
WP_CLI::success('Private peoplehood claim, amendments, evidence, review and consent smoke passed.');
