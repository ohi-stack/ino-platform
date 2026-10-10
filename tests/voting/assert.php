<?php
if (!defined('ABSPATH') || !defined('WP_CLI')) { exit(1); }
global $wpdb;
function vote_assert($bool,$message) { if (!$bool) { WP_CLI::error('Voting assertion failed: '.$message); } }
function vote_count($key) {
    global $wpdb;$t=INO_Platform_Voting::tables($key);
    return (int)$wpdb->get_var("SELECT COUNT(*) FROM {$t}");
}
function vote_row($id) {
    global $wpdb;$t=INO_Platform_Voting::tables('polls');
    return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t} WHERE id=%d",$id));
}
$state=getenv('INO_VOTE_ASSERT');
$viewer=get_user_by('login','ino_stage_viewer');
$outsider=get_user_by('login','ino_stage_outsider');
$p=vote_row(1);
switch ($state) {
case 'baseline':
    vote_assert(vote_count('polls')===0 && vote_count('ballots')===0,'empty registry');
    vote_assert(shortcode_exists('ino_voting') && shortcode_exists('ino_vote_results') &&
        shortcode_exists('ino_my_votes'),'front-end routes');
    break;
case 'draft':
    vote_assert($p && $p->status==='draft' && vote_count('options')===2,'draft question and options');
    vote_assert(strpos(do_shortcode('[ino_voting]'),'Approve a new garden')===false,'draft hidden from public');
    break;
case 'open':
    vote_assert($p->status==='open','poll opened');
    vote_assert(INO_Platform_Voting::eligible($p,$viewer->ID),'private approved member eligible');
    vote_assert(!INO_Platform_Voting::eligible($p,$outsider->ID),'pending user denied');
    vote_assert(!INO_Platform_Voting::eligible($p,0),'unknown user denied');
    break;
case 'ballot':
    vote_assert(vote_count('ballots')===1 && vote_count('choices')===1,'one ballot and one answer');
    $rows=INO_Platform_Voting::tallies(1);
    vote_assert($rows[1]===1,'one participating account');
    vote_assert(array_sum(array_map(function($o){return (int)$o->votes;},$rows[0]))===1,'exact tally');
    wp_set_current_user($viewer->ID);
    $html=do_shortcode('[ino_my_votes]');
    vote_assert(strpos($html,'Approve a new garden')!==false,'owner sees receipt');
    vote_assert(strpos($html,'My Voting History')!==false,'private history view');
    wp_set_current_user($outsider->ID);
    vote_assert(strpos(do_shortcode('[ino_my_votes]'),'Approve a new garden')===false,'other user cannot see participation');
    wp_set_current_user(0);
    vote_assert(strpos(do_shortcode('[ino_vote_results]'),'Approve a new garden')===false,'live results withheld');
    break;
case 'closed':
    vote_assert($p->status==='closed','poll was closed by admin');
    vote_assert(strpos(do_shortcode('[ino_vote_results]'),'Approve a new garden')!==false,'after-close aggregate released');
    break;
case 'multiple':
    vote_assert(vote_count('polls')===2 && vote_count('ballots')===2 && vote_count('choices')===3,
        'multi-choice poll has one ballot, two selections');
    $second=vote_row(2);
    vote_assert($second->ballot_type==='multiple' && $second->results_policy==='admins_only',
        'multiple-choice and restricted results persisted');
    $result=INO_Platform_Voting::tallies(2);
    vote_assert($result[1]===1,'one participating account in multi poll');
    vote_assert(array_sum(array_map(function($o){return (int)$o->votes;},$result[0]))===2,
        'two selected options represented');
    wp_set_current_user(0);
    vote_assert(strpos(do_shortcode('[ino_vote_results]'),'Which services first')===false,
        'administrator-only aggregate is not public');
    break;
case 'audit':
    vote_assert(vote_count('polls')===3 && vote_count('ballots')===2 && vote_count('choices')===3,
        'audit outage rejected and rolled back entire third ballot');
    $t=INO_Platform_Voting::tables('events');
    $events=$wpdb->get_col("SELECT event_key FROM {$t} ORDER BY id");
    vote_assert($events===array(
        'poll_drafted','poll_open','ballot_cast','poll_closed',
        'poll_drafted','poll_open','ballot_cast','poll_closed',
        'poll_drafted','poll_open'
    ),'complete actor audit, no failed attempts recorded');
    break;
default:
    WP_CLI::error('Unsupported voting assertion.');
}
WP_CLI::success('Voting database assertion: '.$state);
