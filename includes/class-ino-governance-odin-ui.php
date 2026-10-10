<?php
if (!defined('ABSPATH')) { exit; }

/** Administrative and public views for the internal ODIN transparency registry. */
class INO_Governance_ODIN_UI {
    public static function admin_assets($hook) {
        if (strpos((string)$hook,'ino-governance-odin')===false) { return; }
        wp_enqueue_style('ino-odin-transparency',INO_PLATFORM_URL.'assets/css/ino-governance-odin.css',
            array('ino-platform-admin-command'),INO_PLATFORM_VERSION);
    }
    public static function public_assets() {
        wp_enqueue_style('ino-odin-transparency',INO_PLATFORM_URL.'assets/css/ino-governance-odin.css',
            array('ino-platform-public-design'),INO_PLATFORM_VERSION);
    }
    private static function admin_form($op) {
        echo '<form class="ino-odin-form" method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
        echo '<input type="hidden" name="action" value="ino_odin_action">';
        echo '<input type="hidden" name="odin_operation" value="'.esc_attr($op).'">';
        wp_nonce_field('ino_odin_'.$op,'ino_odin_nonce');
    }
    private static function end_form($text) {
        echo '<button type="submit" class="ino-btn ino-btn-primary">'.esc_html($text).'</button></form>';
    }
    private static function options($rows,$id_key,$label_key,$empty) {
        echo '<option value="0">'.esc_html($empty).'</option>';
        foreach ((array)$rows as $row) {
            echo '<option value="'.esc_attr((int)$row->$id_key).'">'.esc_html($row->$label_key).' (#'.esc_html($row->$id_key).')</option>';
        }
    }
    public static function admin_page() {
        if (!current_user_can('ino_governance_view')) {
            wp_die('Access denied to ODIN administration.','',array('response'=>403));
        }
        global $wpdb;
        $versions=INO_Governance_ODIN::admin_records();
        $counts=INO_Governance_ODIN::private_counts();
        $evidence=INO_Governance_ODIN::evidence_counts(array_map(function($v){return (int)$v->id;},$versions));
        $events=INO_Governance_ODIN::admin_audit();
        $evidence_details=INO_Governance_ODIN::admin_evidence();
        $source_table=$wpdb->prefix.'ino_governance_items';
        $sources=$wpdb->get_results("SELECT id,title FROM {$source_table} WHERE status='published' AND visibility='public' ORDER BY title ASC LIMIT 200");
        $latest=array();$seen=array();
        foreach ($versions as $version) {
            if (!isset($seen[(int)$version->record_id])) {
                $seen[(int)$version->record_id]=true; $latest[]=$version;
            }
        }
        $registries=$latest;
        $drafts=array_values(array_filter($latest,function($v){return $v->status==='draft';}));
        $reviewable=array_values(array_filter($drafts,function($v){
            return (int)$v->created_by!==get_current_user_id() && (int)$v->source_item_id>0;
        }));
        $publishable=array_values(array_filter($latest,function($v){
            return $v->status==='reviewed' && (int)$v->reviewed_by!==get_current_user_id()
                && (int)$v->created_by!==get_current_user_id();
        }));
        $revisable=array_values(array_filter($latest,function($v){
            return in_array($v->status,array('published','superseded','withdrawn'),true);
        }));
        echo '<main class="ino-admin ino-odin-admin"><header class="ino-hero"><span class="ino-kicker">INO Governance / ODIN Archives</span><h1>ODIN &amp; Transparency</h1><p>Controlled document registration, append-only version histories, source-bound public publication, signature-evidence references and verified public lookups. Registry publication is not legal or cryptographic certification.</p><div class="ino-actions"><a class="ino-btn ino-btn-gold" href="'.esc_url(home_url('/ino-odin-registry/')).'">Public Registry ↗</a><a class="ino-btn ino-btn-light" href="'.esc_url(home_url('/ino-odin-verify/')).'">Verify Registry ID ↗</a></div></header>';
        if (isset($_GET['ino_odin_notice']) && is_string($_GET['ino_odin_notice'])) {
            echo '<div class="ino-note" role="status">'.esc_html(sanitize_text_field(wp_unslash($_GET['ino_odin_notice']))).'</div>';
        }
        echo '<div class="ino-section-heading"><div><h2>Registry overview</h2><p>Counts are read from stored ODIN rows, not estimated activity.</p></div></div><div class="ino-grid">';
        foreach (array(
            array('Registry identities',$counts['registered']),
            array('Private drafts',$counts['draft']),
            array('Reviewed versions',$counts['reviewed']),
            array('Currently published',$counts['published']),
            array('Historical / superseded',$counts['superseded']),
            array('Withdrawn drafts',$counts['withdrawn'])
        ) as $metric) {
            echo '<article class="ino-card"><span class="ino-value">'.esc_html(number_format_i18n($metric[1])).'</span><span class="ino-label">'.esc_html($metric[0]).'</span></article>';
        }
        echo '</div><section class="ino-panel"><h2>Version lifecycle</h2><p class="ino-panel-help">A new version never silently replaces a public edition. Staff source-check and independently approve before publication.</p><div class="ino-odin-flow">';
        foreach (array('Register draft','Evidence references','Independent review','Independent publication','Verification & timeline') as $step) {
            echo '<div>'.esc_html($step).'</div>';
        }
        echo '</div></section>';

        if (current_user_can('ino_governance_edit')) {
            echo '<section class="ino-panel" id="ino-odin-register"><h2>Register a document</h2><p class="ino-panel-help">Creates a private internal ODIN identifier. A documentary source reference is mandatory; a public Phase 1 record must be linked before independent review.</p>';
            self::admin_form('register'); self::document_fields($sources);self::end_form('Create private registry draft');
            echo '</section><section class="ino-panel"><h2>Create a new version</h2><p class="ino-panel-help">Version history is retained. Only records without an active draft or reviewed edition can accept a new version.</p>';
            self::admin_form('revise');
            echo '<label>Registry identity<select name="record_id" required>';self::options($revisable,'record_id','odin_id','Select versioned record');echo '</select></label>';
            self::document_fields($sources);self::end_form('Create next private version');
            echo '</section><section class="ino-panel"><h2>Register signature evidence</h2><p class="ino-panel-help">Records an officer’s first-person observation and evidence location. It neither applies a digital signature nor verifies a signature cryptographically.</p>';
            self::admin_form('record_evidence');
            echo '<label>Draft version<select name="version_id" required>';self::options($drafts,'id','odin_id','Select draft');echo '</select></label>';
            echo '<label>Evidence reference<input type="text" name="evidence_ref" maxlength="190" required placeholder="Restricted archive record or signed-paper reference"></label><label class="ino-odin-wide">Evidence observed / relationship to source<textarea name="attestation" minlength="20" rows="3" required></textarea></label>';
            echo '<label class="ino-odin-wide ino-odin-check"><input type="checkbox" name="evidence_attestation" value="1" required> I am documenting my own observation of the cited evidence, not signing on another person’s behalf or asserting digital signature verification.</label>';
            self::end_form('Record evidence reference');
            echo '</section>';
        }
        if (current_user_can('ino_governance_review')) {
            echo '<section class="ino-panel"><h2>Independent version review</h2><p class="ino-panel-help">Reviewers cannot approve versions they authored. Expand the full version record below before attesting to its source.</p>';
            self::admin_form('review_version');
            echo '<label>Source-linked draft<select name="version_id" required>';self::options($reviewable,'id','odin_id','Select eligible draft');echo '</select></label>';
            echo '<label class="ino-odin-wide">Evidence review note<textarea name="review_note" required minlength="20" rows="3"></textarea></label>';
            self::end_form('Record independent review');echo '</section>';
        }
        if (current_user_can('ino_governance_publish')) {
            echo '<section class="ino-panel"><h2>Authorize public publication</h2><p class="ino-panel-help">Only a separately reviewed version with a currently published/public Phase 1 source can be released. Prior public versions are retained as superseded history.</p>';
            self::admin_form('publish_version');
            echo '<label>Reviewed version<select name="version_id" required>';self::options($publishable,'id','odin_id','Select reviewed edition');echo '</select></label>';
            echo '<label class="ino-odin-wide ino-odin-check"><input type="checkbox" name="public_attestation" value="1" required> I have verified the cited Phase 1 public record, my institutional authority and appropriateness of the public title/summary; I understand this is not external legal authentication.</label>';
            self::end_form('Publish reviewed version');echo '</section>';
        }
        if (current_user_can('ino_governance_edit')) {
            echo '<section class="ino-panel"><h2>Withdraw an unpublished draft</h2>';
            self::admin_form('withdraw_draft');
            echo '<label>Unpublished draft<select name="version_id" required>';self::options($drafts,'id','odin_id','Select draft to withdraw');echo '</select></label>';
            echo '<label>Reason<textarea name="withdrawal_note" required minlength="12"></textarea></label>';
            self::end_form('Withdraw draft only');echo '</section>';
        }

        echo '<section class="ino-panel"><h2>Document versions</h2><p class="ino-panel-help">Restricted metadata and full source citations. Public pages never display private drafts, reviewer identities or internal audit notes.</p><div class="ino-odin-table"><table class="ino-table"><thead><tr><th>ODIN ID / version</th><th>Title</th><th>State</th><th>Source</th><th>Evidence entries</th></tr></thead><tbody>';
        if (!$versions) { echo '<tr><td colspan="5">No registered documents.</td></tr>'; }
        foreach ($versions as $v) {
            echo '<tr><td><code>'.esc_html($v->odin_id).'</code><br>Version '.esc_html($v->version_no).'</td><td>'.esc_html($v->title).'<details><summary>Full recorded summary</summary><p>'.nl2br(esc_html($v->summary)).'</p></details></td><td>'.esc_html($v->status).'</td><td>'.esc_html($v->source_ref).'<br>Phase 1 #'.esc_html($v->source_item_id?:'—').'</td><td>'.esc_html(isset($evidence[(int)$v->id])?$evidence[(int)$v->id]:0).'</td></tr>';
        }
        echo '</tbody></table></div></section>';


        echo '<section class="ino-panel"><h2>Signature Evidence Ledger (restricted)</h2><p class="ino-panel-help">Staff observations and source references; NOT applied or cryptographically verified signatures.</p><div class="ino-odin-table"><table class="ino-table"><thead><tr><th>Version</th><th>Witness actor</th><th>Evidence reference</th><th>Recorded observation</th><th>Recorded at</th></tr></thead><tbody>';
        if (!$evidence_details) { echo '<tr><td colspan="5">No signature evidence references recorded.</td></tr>'; }
        foreach ($evidence_details as $item) {
            echo '<tr><td>'.esc_html($item->version_id).'</td><td>'.esc_html($item->witness_user_id).'</td><td>'.esc_html($item->evidence_ref).'</td><td>'.esc_html($item->attestation).'</td><td>'.esc_html($item->created_at).'</td></tr>';
        }
        echo '</tbody></table></div></section>';

        echo '<section class="ino-panel"><h2>Registry event timeline (restricted)</h2><p class="ino-panel-help">Application audit log: actor IDs, draft events and evidence notes are restricted to authorized governance staff.</p><div class="ino-odin-table"><table class="ino-table"><thead><tr><th>When</th><th>Record / version</th><th>Event</th><th>Actor</th><th>Note</th></tr></thead><tbody>';
        if (!$events) { echo '<tr><td colspan="5">No ODIN events logged.</td></tr>'; }
        foreach ($events as $event) {
            echo '<tr><td>'.esc_html($event->occurred_at).'</td><td>'.esc_html($event->record_id).' / '.esc_html($event->version_id).'</td><td>'.esc_html($event->event_key).'</td><td>'.esc_html($event->actor_id).'</td><td>'.esc_html($event->note).'</td></tr>';
        }
        echo '</tbody></table></div></section><div class="ino-note">Only publication tied to an independently reviewed and currently public Phase 1 source can appear in the public registry. No certificate, public digital signature, or governmental recognition is generated.</div></main>';
    }
    private static function document_fields($sources) {
        echo '<label>Title<input type="text" name="title" maxlength="190" required></label><label>Document source reference<input type="text" name="source_ref" maxlength="190" required placeholder="Precise archival or adopted instrument citation"></label>';
        echo '<label>Published Phase 1 source (required before review)<select name="source_item_id" required>';
        self::options($sources,'id','title','No public source selected yet');
        echo '</select></label><label class="ino-odin-wide">Public-safe summary (review before publication)<textarea name="summary" rows="3" maxlength="5000"></textarea></label>';
    }

    private static function public_header($section,$title,$explanation) {
        return '<section class="ino-shell ino-odin-public"><header class="ino-hero-public"><span class="ino-portal-eyebrow">Indigenous Nation of Onegodia / ODIN</span><h1>'.esc_html($title).'</h1><p class="ino-portal-lead">'.esc_html($explanation).'</p></header><div class="ino-odin-public-links"><a href="'.esc_url(home_url('/ino-odin-registry/')).'">Registry</a><a href="'.esc_url(home_url('/ino-odin-verify/')).'">Verify ID</a><a href="'.esc_url(home_url('/ino-odin-timeline/')).'">Historical Timeline</a></div>';
    }
    private static function notice() {
        return '<div class="ino-member-notice">ODIN verification confirms only an entry in the INO internal registry linked to a currently published public source. It is not government recognition, a notarial act, independent authenticity certification, cryptographic signature validation or land title.</div>';
    }
    private static function entry($item,$full=false) {
        $out='<article class="ino-odin-item"><div class="ino-odin-item-head"><span class="ino-odin-ref">'.esc_html($item->odin_id).'</span><span class="ino-odin-status">'.esc_html($item->status).'</span></div>';
        $out.='<h2>'.esc_html($item->title).'</h2><p>'.esc_html($item->summary).'</p>';
        $out.='<dl class="ino-odin-meta"><div><dt>Version</dt><dd>'.esc_html($item->version_no).'</dd></div><div><dt>Recorded publication</dt><dd>'.esc_html($item->published_at).'</dd></div><div><dt>Source</dt><dd>'.esc_html($item->source_title).'</dd></div></dl>';
        if ($full) {
            $out.='<p><strong>Published source registry code:</strong> '.esc_html($item->public_source_code).'</p>';
            if ($item->public_pdf_id && $item->source_sha256) {
                $url=wp_get_attachment_url((int)$item->public_pdf_id);
                if ($url) {
                    $out.='<p><a href="'.esc_url($url).'" target="_blank" rel="noopener noreferrer">Open published public PDF ↗</a></p>';
                }
                $out.='<p><strong>Stored document SHA-256:</strong> <code>'.esc_html($item->source_sha256).'</code></p>';
            } else {
                $out.='<p>Public metadata only; no PDF was registered with the Phase 1 source.</p>';
            }
        }
        return $out.'</article>';
    }

    public static function registry_shortcode() {
        $rows=INO_Governance_ODIN::public_versions(40,false);
        $html=self::public_header('registry','ODIN Public Registry','Browse only reviewed and published public record versions backed by a currently public governance source.');
        if (!$rows) { $html.='<div class="ino-member-notice">No currently verifiable ODIN records have been published.</div>'; }
        foreach ($rows as $item) {
            $html.=self::entry($item);
            $html.='<p class="ino-odin-view"><a href="'.esc_url(add_query_arg('odin_id',$item->odin_id,home_url('/ino-odin-verify/'))).'">View public verification ↗</a></p>';
        }
        return $html.self::notice().'</section>';
    }
    public static function verify_shortcode() {
        $id=INO_Governance_ODIN::public_id_from_request();
        $attempted=isset($_GET['odin_id']);
        $html=self::public_header('verify','Verify an ODIN Registry ID','Search by the exact public INO-ODIN identifier. This lookup cannot validate document signatures or establish outside legal status.');
        $html.='<form method="get" action="'.esc_url(home_url('/ino-odin-verify/')).'" class="ino-odin-verify"><label for="ino-odin-code">Registry identifier</label><div><input type="text" id="ino-odin-code" name="odin_id" maxlength="41" value="'.esc_attr($id).'" placeholder="INO-ODIN-..." required><button type="submit">Look up public record</button></div></form>';
        if ($attempted) {
            $found=$id?INO_Governance_ODIN::public_versions(1,false,$id):array();
            if ($found) {
                $html.='<p class="ino-odin-match">A currently published INO internal registry entry was found.</p>'.self::entry($found[0],true);
                $html.='<p><a href="'.esc_url(home_url('/ino-odin-timeline/')).'">Explore published historical editions ↗</a></p>';
            } else {
                $html.='<p class="ino-member-notice" role="status">No publicly verifiable ODIN registry entry was found for that identifier. Private drafts, withdrawn entries and inaccessible records are not disclosed.</p>';
            }
        }
        return $html.self::notice().'</section>';
    }

    public static function timeline_shortcode() {
        $rows=INO_Governance_ODIN::public_versions(60,true);
        $html=self::public_header('timeline','Public Historical Timeline','Review the publication sequence of public ODIN editions. Superseded versions remain historical records while access restrictions continue to apply.');
        if (!$rows) { $html.='<p class="ino-member-notice">No public version-history entries have been released.</p>'; }
        $html.='<ol class="ino-odin-timeline">';
        foreach ($rows as $item) {
            $html.='<li><span class="ino-odin-time">'.esc_html($item->published_at).'</span>'.self::entry($item);
            $html.='</li>';
        }
        return $html.'</ol>'.self::notice().'</section>';
    }
}
