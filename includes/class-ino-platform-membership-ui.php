<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Read-only membership portal views. No secondary enrollment authority, approval
 * transitions, bulk member export, or personally identifying public endpoints.
 * Aggregates use the latest INO membership row for each WordPress account.
 */
final class INO_Platform_Membership_UI {
    const PAGE = 'ino-platform-members';

    public static function init() {
        add_action('admin_enqueue_scripts',array(__CLASS__,'admin_assets'));
        add_action('wp_enqueue_scripts',array(__CLASS__,'public_assets'));
        add_shortcode('ino_membership_portal',array(__CLASS__,'landing'));
    }
    public static function admin_assets($hook) {
        if (strpos((string)$hook,self::PAGE)===false) { return; }
        self::styles(true);
    }
    public static function public_assets() {
        // Public assets load without a private payload; payloads only occur
        // inside the authenticated member's shortcode response.
        self::styles(false);
    }
    private static function styles($admin) {
        $style='assets/css/ino-membership-ui.css';
        $script='assets/js/ino-membership-ui.js';
        wp_enqueue_style('ino-membership-ui',INO_PLATFORM_URL.$style,
            $admin?array('ino-platform-admin-command'):array('ino-platform-public-design'),
            INO_PLATFORM_VERSION);
        wp_enqueue_script('ino-membership-ui',INO_PLATFORM_URL.$script,array(),
            INO_PLATFORM_VERSION,true);
    }
    private static function months() {
        $start=new DateTimeImmutable('first day of this month 00:00:00',wp_timezone());
        $months=array();
        for ($i=5;$i>=0;$i--) { $months[]=$start->modify('-'.$i.' months')->format('Y-m'); }
        return $months;
    }
    private static function latest_join() {
        global $wpdb;
        // ONLY internal constant table names are interpolated.
        $t=$wpdb->prefix.'ino_members';
        return "FROM {$t} m INNER JOIN
            (SELECT user_id,MAX(id) AS last_id FROM {$t}
             WHERE user_id IS NOT NULL GROUP BY user_id) lm ON lm.last_id=m.id";
    }
    public static function stats() {
        global $wpdb;
        $from=self::latest_join();
        $rows=$wpdb->get_results("SELECT LOWER(COALESCE(NULLIF(status,''),'unclassified')) AS state,COUNT(*) AS total
            {$from} GROUP BY LOWER(COALESCE(NULLIF(status,''),'unclassified'))");
        $states=array();
        $total=0;
        foreach ((array)$rows as $r) {
            $states[$r->state]=(int)$r->total;
            $total+=(int)$r->total;
        }
        $approved=(int)($states['approved']??0);
        $pending=(int)($states['pending']??0);
        $consented=(int)$wpdb->get_var("SELECT COUNT(*) {$from}
            WHERE LOWER(m.status)='approved' AND m.public_consent=1");
        $unlinked=(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ino_members WHERE user_id IS NULL");
        return array('total'=>$total,'approved'=>$approved,'pending'=>$pending,'consented'=>$consented,
            'unlinked'=>$unlinked,'states'=>$states);
    }
    private static function classifications() {
        global $wpdb;
        $from=self::latest_join();
        $rows=$wpdb->get_results("SELECT COALESCE(NULLIF(TRIM(m.member_type),''),'Unclassified') AS label,
            COUNT(*) AS total {$from} GROUP BY COALESCE(NULLIF(TRIM(m.member_type),''),'Unclassified')
            ORDER BY total DESC,label ASC LIMIT 12");
        return is_array($rows)?$rows:array();
    }
    private static function month_counts($table,$field,$uid=0) {
        global $wpdb;
        $allowed=array('ino_members'=>'created_at','ino_identity_declarations'=>'created_at',
            'ino_family_relationships'=>'created_at','ino_votes_ballots'=>'created_at');
        if (!isset($allowed[$table]) || $allowed[$table]!==$field) { return array_fill(0,6,0); }
        $months=self::months();
        $t=$wpdb->prefix.$table;
        $sql="SELECT DATE_FORMAT({$field},'%Y-%m') AS period,COUNT(*) AS total FROM {$t}
            WHERE {$field} >= %s";
        $args=array($months[0].'-01 00:00:00');
        if ($uid) {
            $column=$table==='ino_votes_ballots'?'voter_id':'user_id';
            // Ancestry/family counts are not part of this time-series.
            if ($table==='ino_family_relationships') { return array_fill(0,6,0); }
            $sql.=" AND {$column}=%d";
            $args[]=$uid;
        }
        $sql.=" GROUP BY DATE_FORMAT({$field},'%Y-%m')";
        $rows=$wpdb->get_results($wpdb->prepare($sql,$args));
        $lookup=array_fill_keys($months,0);
        foreach ((array)$rows as $r) {
            if (isset($lookup[$r->period])) { $lookup[$r->period]=(int)$r->total; }
        }
        return array_values($lookup);
    }
    private static function chart($series,$labels,$title) {
        // Values are emitted by PHP and a normal HTML table remains visible in
        // details even when JS is missing or reduced-motion is configured.
        $months=self::months();
        $payload=array('months'=>$months,'series'=>$series,'labels'=>$labels);
        $html='<div class="ino-membership-chart" data-ino-membership-chart data-series="'.
            esc_attr(wp_json_encode($payload)).'">';
        $html.='<div class="ino-membership-chart-head"><h3>'.esc_html($title).'</h3><div class="ino-membership-chart-controls" role="group" aria-label="Chart data series">';
        $n=0;
        foreach ($labels as $key=>$label) {
            $html.='<button type="button" data-series-key="'.esc_attr($key).
                '" aria-pressed="'.($n===0?'true':'false').'">'.esc_html($label).'</button>';
            $n++;
        }
        $html.='</div></div><div class="ino-membership-plot" data-chart-plot><p>Chart display requires JavaScript. Source numbers remain available below.</p></div>';
        $html.='<p class="ino-membership-chart-summary" data-chart-summary aria-live="polite">Verified database values only.</p>';
        $html.='<details><summary>View the source data table</summary><div class="ino-membership-table-scroll"><table><thead><tr><th>Month</th>';
        foreach ($labels as $label) { $html.='<th>'.esc_html($label).'</th>'; }
        $html.='</tr></thead><tbody>';
        foreach ($months as $i=>$month) {
            $html.='<tr><th scope="row">'.esc_html($month).'</th>';
            foreach (array_keys($labels) as $key) {
                $value=isset($series[$key][$i])?(int)$series[$key][$i]:0;
                $html.='<td>'.esc_html(number_format_i18n($value)).'</td>';
            }
            $html.='</tr>';
        }
        return $html.'</tbody></table></div></details></div>';
    }
    private static function status_bars($states,$total) {
        $html='<div class="ino-membership-bars">';
        arsort($states,SORT_NUMERIC);
        foreach (array_slice($states,0,9,true) as $status=>$number) {
            $percent=$total?round(100*$number/$total,1):0;
            $html.='<div class="ino-membership-bar"><div><span>'.esc_html(ucwords(str_replace('_',' ',$status))).
                '</span><strong>'.esc_html(number_format_i18n($number)).'</strong></div>'.
                '<progress value="'.esc_attr($percent).'" max="100">'.esc_html($percent).'%</progress></div>';
        }
        if (!$states) { $html.='<p class="ino-membership-empty">No membership records available.</p>'; }
        return $html.'</div>';
    }
    private static function card($value,$label,$helper,$icon) {
        return '<article class="ino-membership-kpi"><span class="ino-membership-kpi-icon" aria-hidden="true">'.
            esc_html($icon).'</span><strong>'.esc_html((string)$value).'</strong><span>'.
            esc_html($label).'</span><small>'.esc_html($helper).'</small></article>';
    }
    public static function admin_page() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Membership reports require administrator access.','ino-platform'),'',array('response'=>403));
        }
        global $wpdb;
        $stats=self::stats();
        $series=array('members'=>self::month_counts('ino_members','created_at'),
            'declarations'=>self::month_counts('ino_identity_declarations','created_at'));
        $labels=array('members'=>'Membership entries','declarations'=>'Identity declarations');
        $types=self::classifications();
        $search=isset($_GET['ino_member_search']) && is_string($_GET['ino_member_search']) ?
            sanitize_text_field(wp_unslash($_GET['ino_member_search'])) : '';
        $search=substr($search,0,100);
        $filter=isset($_GET['ino_member_state']) && is_string($_GET['ino_member_state']) ?
            sanitize_key(wp_unslash($_GET['ino_member_state'])) : '';
        if ($filter && !preg_match('/^[a-z0-9_-]{1,40}$/D',$filter)) { $filter=''; }
        $page=isset($_GET['ino_member_page'])?max(1,min(1000000,absint($_GET['ino_member_page']))):1;
        $per_page=15;$offset=($page-1)*$per_page;
        $conditions=array();$args=array();$from=self::latest_join();
        if ($search!=='') {
            $like='%'.$wpdb->esc_like($search).'%';
            $conditions[]='(m.member_id LIKE %s OR m.first_name LIKE %s OR m.last_name LIKE %s OR m.member_type LIKE %s)';
            array_push($args,$like,$like,$like,$like);
        }
        if ($filter!=='') { $conditions[]='LOWER(m.status)=%s';$args[]=$filter; }
        $where=$conditions?' WHERE '.implode(' AND ',$conditions):'';
        $query="SELECT COUNT(*) {$from}{$where}";
        $matches=(int)$wpdb->get_var($args?$wpdb->prepare($query,$args):$query);
        $fields="SELECT m.id,m.user_id,m.member_id,m.first_name,m.last_name,m.member_type,
            m.status,m.public_consent,m.created_at {$from}{$where} ORDER BY m.id DESC LIMIT %d OFFSET %d";
        $rows=$wpdb->get_results($wpdb->prepare($fields,array_merge($args,array($per_page,$offset))));
        echo '<main class="ino-admin ino-membership-admin" data-ino-membership-view>';
        echo '<header class="ino-membership-hero"><span class="ino-membership-eyebrow">INO Platform · Membership Administration</span><h1>Membership Command Center</h1><p>Review account-linked membership records, classifications and trends. Metrics reflect the existing WordPress database; they are not approvals, verified enrollment, or external identity determinations.</p><div class="ino-membership-actions"><a href="'.esc_url(home_url('/ino-member-dashboard/')).'">Member dashboard ↗</a><a href="'.esc_url(admin_url('admin.php?page=ino-platform-reports')).'">Institutional reports ↗</a></div></header>';
        echo '<div class="ino-membership-kpis">';
        echo self::card(number_format_i18n($stats['total']),'Linked member accounts','Latest stored membership row per user','◉');
        echo self::card(number_format_i18n($stats['approved']),'Stored as approved','Not independent eligibility verification','✓');
        echo self::card(number_format_i18n($stats['pending']),'Stored as pending','No decision is performed here','◷');
        echo self::card(number_format_i18n($stats['consented']),'Directory opt-ins','Approved with latest explicit consent','◇');
        echo '</div><div class="ino-membership-main-grid">';
        echo '<section class="ino-membership-panel">'.self::chart($series,$labels,'Six-month record activity').'</section>';
        echo '<section class="ino-membership-panel"><h2>Current membership statuses</h2><p>Based on the latest linked record for each account.</p>'.self::status_bars($stats['states'],$stats['total']).'</section></div>';
        echo '<section class="ino-membership-panel"><h2>Membership pathways</h2><p>Recorded categories from the current membership entries, not predefined or assumed entitlements.</p><div class="ino-membership-category-grid">';
        if (!$types) { echo '<p class="ino-membership-empty">No classifications recorded.</p>'; }
        foreach ($types as $type) { echo '<div class="ino-membership-category"><strong>'.
            esc_html(number_format_i18n((int)$type->total)).'</strong><span>'.esc_html($type->label).'</span></div>'; }
        echo '</div></section>';
        echo '<section class="ino-membership-panel" id="ino-membership-roster"><div class="ino-membership-section-row"><div><h2>Membership records</h2><p>Read-only current entries. Search results and records are restricted to WordPress administrators.</p></div><span>'.esc_html(number_format_i18n($matches)).' matching account(s)</span></div>';
        echo '<form method="get" action="'.esc_url(admin_url('admin.php')).'" class="ino-membership-search"><input type="hidden" name="page" value="ino-platform-members"><label>Search member ID, name, or classification<input type="search" name="ino_member_search" maxlength="100" value="'.esc_attr($search).'" placeholder="Search membership records"></label><label>Stored status<select name="ino_member_state"><option value="">All statuses</option>';
        foreach (array_keys($stats['states']) as $key) {
            echo '<option value="'.esc_attr($key).'" '.selected($filter,$key,false).'>'.esc_html(ucwords($key)).'</option>';
        }
        echo '</select></label><button type="submit">Filter records</button></form>';
        echo '<div class="ino-membership-table-scroll"><table class="ino-membership-table"><thead><tr><th>Member ID</th><th>Account</th><th>Classification</th><th>Stored status</th><th>Directory consent</th><th>Record date</th><th>Open</th></tr></thead><tbody>';
        foreach ((array)$rows as $m) {
            $user=get_userdata((int)$m->user_id);
            $name=trim($m->first_name.' '.$m->last_name);
            if ($name==='') { $name=$user?$user->display_name:'No recorded name'; }
            $url=$user?get_edit_user_link($user->ID):'';
            echo '<tr><td><code>'.esc_html($m->member_id).'</code></td><td>'.esc_html($name).'</td><td>'.
                esc_html($m->member_type).'</td><td><span class="ino-membership-status">'.
                esc_html($m->status).'</span></td><td>'.((int)$m->public_consent===1?'Recorded':'Not recorded').
                '</td><td>'.esc_html($m->created_at).'</td><td>'.
                ($url?'<a href="'.esc_url($url).'">Account ↗</a>':'Unlinked').'</td></tr>';
        }
        if (!$rows) { echo '<tr><td colspan="7">No matching records.</td></tr>'; }
        echo '</tbody></table></div>';
        $base=add_query_arg(array('page'=>self::PAGE,'ino_member_search'=>$search,'ino_member_state'=>$filter),admin_url('admin.php'));
        echo '<nav class="ino-membership-pagination" aria-label="Membership table pagination">';
        if ($page>1) { echo '<a href="'.esc_url(add_query_arg('ino_member_page',$page-1,$base)).'">← Previous</a>'; }
        echo '<span>Page '.esc_html(number_format_i18n($page)).' of '.esc_html(number_format_i18n(max(1,(int)ceil($matches/$per_page)))).'</span>';
        if ($page*$per_page<$matches) { echo '<a href="'.esc_url(add_query_arg('ino_member_page',$page+1,$base)).'">Next →</a>'; }
        echo '</nav></section>';
        echo '<p class="ino-membership-policy">Data quality: '.esc_html(number_format_i18n($stats['unlinked'])).
            ' membership row(s) lack a linked WordPress user ID and are excluded from the current-account dashboard. This screen does not create, approve, export, or alter membership records.</p></main>';
    }
    public static function member_dashboard() {
        if (!is_user_logged_in()) {
            return '<section class="ino-membership-portal"><div class="ino-membership-guest"><span>Member access</span><h1>Membership Dashboard</h1><p>Sign in to see only your INO membership record, participation and private documents.</p><a href="'.esc_url(wp_login_url(home_url('/ino-member-dashboard/'))).'">Sign in to your workspace ↗</a></div></section>';
        }
        global $wpdb;
        $uid=get_current_user_id();
        $u=get_userdata($uid);
        $member=$wpdb->get_row($wpdb->prepare(
            "SELECT member_id,member_type,status,verification_status,created_at FROM {$wpdb->prefix}ino_members
             WHERE user_id=%d ORDER BY id DESC LIMIT 1",$uid));
        $decl=(int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ino_identity_declarations WHERE user_id=%d",$uid));
        $reviewed=(int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ino_identity_declarations WHERE user_id=%d AND LOWER(review_status)='approved'",$uid));
        $relationships=(int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ino_family_relationships
             WHERE (person_a=%d OR person_b=%d) AND status='approved'",$uid,$uid));
        $votes=(int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ino_votes_ballots WHERE voter_id=%d",$uid));
        $notices=(int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ino_gov_notifications WHERE recipient_id=%d AND is_read=0",$uid));
        $series=array('declarations'=>self::month_counts('ino_identity_declarations','created_at',$uid),
            'votes'=>self::month_counts('ino_votes_ballots','created_at',$uid));
        $recent=$wpdb->get_results($wpdb->prepare(
            "SELECT review_status,verification_status,created_at FROM {$wpdb->prefix}ino_identity_declarations
             WHERE user_id=%d ORDER BY id DESC LIMIT 6",$uid));
        $status=$member?(string)$member->status:'No INO membership record';
        $name=$u?$u->display_name:'Member';
        $html='<section class="ino-membership-portal ino-membership-member" data-ino-membership-view>';
        $html.='<header class="ino-membership-hero"><span class="ino-membership-eyebrow">INDIGENOUS NATION OF ONEGODIA · MEMBERSHIP</span><h1>Welcome to Your Membership Portal</h1><p>Private records and participation for '.esc_html($name).'. The status shown is your latest stored INO membership classification, not a civil credential.</p>';
        $html.='<div class="ino-membership-actions"><a href="'.esc_url(home_url('/identity-declaration/')).'">Identity records ↗</a><a href="'.esc_url(home_url('/ino-voting/')).'">Voting &amp; consultation ↗</a><a href="'.esc_url(home_url('/ino-membership/')).'">Membership information ↗</a></div></header>';
        $html.='<section class="ino-membership-profile-panel"><div class="ino-membership-profile"><div class="ino-membership-avatar" aria-hidden="true">'.get_avatar($uid,74,'','',array('class'=>'ino-membership-avatar-img')).'</div><div><span>Private account overview</span><h2>'.esc_html($name).'</h2><p>Member ID: <strong>'.esc_html($member?$member->member_id:'Not assigned').'</strong></p></div></div><div class="ino-membership-profile-state"><span>Membership status</span><strong>'.esc_html($status).'</strong><small>'.esc_html($member?$member->member_type:'No classification stored').'</small></div></section>';
        $html.='<div class="ino-membership-kpis">';
        $html.=self::card(number_format_i18n($decl),'Identity declarations','Stored for your account','◉');
        $html.=self::card(number_format_i18n($reviewed),'Reviewed as approved','Administrative record status','✓');
        $html.=self::card(number_format_i18n($relationships),'Approved relationships','Only your connected records','◇');
        $html.=self::card(number_format_i18n($votes),'Ballots submitted','Your participation receipts','▣');
        $html.='</div>';
        $html.='<div class="ino-membership-main-grid"><section class="ino-membership-panel">'.
            self::chart($series,array('declarations'=>'Declarations','votes'=>'Ballot receipts'),'Your six-month activity').
            '</section><section class="ino-membership-panel"><h2>Your next steps</h2><p>Open the existing INO pages appropriate to your account.</p><div class="ino-membership-quick-links">';
        foreach (array(
            array('Identity & heritage','Review your personal declarations','/identity-declaration/'),
            array('Family records','Open private approved relationship records','/family-tree/'),
            array('Community voting','Check which current ballots you are eligible to participate in','/ino-voting/'),
            array('Voting history','View only your ballot participation receipts','/ino-my-votes/')
        ) as $item) {
            $html.='<a href="'.esc_url(home_url($item[2])).'"><strong>'.esc_html($item[0]).'</strong><small>'.
                esc_html($item[1]).'</small><span aria-hidden="true">↗</span></a>';
        }
        $html.='</div>';
        $html.='<p class="ino-membership-inline-stat"><strong>'.esc_html(number_format_i18n($notices)).
            '</strong> unread internal governance notification(s), where applicable. Only eligible officers receive these notices.</p></section></div>';
        $html.='<section class="ino-membership-panel"><h2>Recent private identity activity</h2><p>Latest stored declarations, visible only to this account. The table contains classifications, not detailed ancestry or family statements.</p><div class="ino-membership-table-scroll"><table class="ino-membership-table"><thead><tr><th>Recorded date</th><th>Review status</th><th>Verification classification</th></tr></thead><tbody>';
        foreach ((array)$recent as $r) {
            $html.='<tr><td>'.esc_html($r->created_at).'</td><td>'.esc_html($r->review_status).
                '</td><td>'.esc_html($r->verification_status).'</td></tr>';
        }
        if (!$recent) { $html.='<tr><td colspan="3">No private identity declarations yet.</td></tr>'; }
        $html.='</tbody></table></div></section><p class="ino-membership-policy">Membership, identity, and participation information is internal to INO and does not by itself change nationality, immigration status, civil citizenship, tribal enrollment outside INO, or legal identity. This read-only dashboard does not approve applications or issue credentials.</p></section>';
        return $html;
    }
    public static function landing() {
        $html='<section class="ino-membership-portal"><header class="ino-membership-hero"><span class="ino-membership-eyebrow">INDIGENOUS NATION OF ONEGODIA · MEMBERSHIP</span><h1>Membership &amp; Belonging</h1><p>Explore voluntary participation, preserve your identity, and access the INO membership workspace. Membership classifications and approvals are maintained separately from civil status and other tribal enrollment.</p><div class="ino-membership-actions"><a href="'.esc_url(home_url('/ino-member-dashboard/')).'">Open member dashboard ↗</a><a href="'.esc_url(home_url('/ino-membership/')).'">Learn about membership ↗</a></div></header><div class="ino-membership-paths">';
        foreach (array(
            array('01','Learn','Understand INO membership pathways and participation requirements.'),
            array('02','Document','Preserve personal identity statements and supporting records.'),
            array('03','Review','See the current stored status of your own membership record.'),
            array('04','Participate','Explore eligible community activities and nonbinding voting.')
        ) as $item) { $html.='<article><span>'.esc_html($item[0]).'</span><h2>'.esc_html($item[1]).'</h2><p>'.esc_html($item[2]).'</p></article>'; }
        $html.='</div><p class="ino-membership-policy">The portal presents existing functionality; it does not independently submit, approve or certify a new membership application.</p></section>';
        return $html;
    }
}
