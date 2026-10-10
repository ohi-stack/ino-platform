<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Public information page for an UNADOPTED proposed INO governance clarification.
 * This is not a law, certification, verified constitutional amendment or ODIN release.
 * No meeting, resolution, vote, minutes or officer signature is created automatically.
 */
class INO_Governance_Clarification {
    public static function init() {
        add_shortcode('ino_governance_clarification', array(__CLASS__, 'shortcode'));
    }

    private static function p($text) {
        return '<p>' . esc_html($text) . '</p>';
    }

    private static function article($title, $paragraphs) {
        $html = '<article class="ino-gov-tile"><h2>' . esc_html($title) . '</h2>';
        foreach ($paragraphs as $text) { $html .= self::p($text); }
        return $html . '</article>';
    }

    public static function shortcode() {
        $oas = 'https://www.oas.org/ext/en/main/oas/our-structure/gs/ssd/dar/gsv/moduleId/12230/id/1272/lang/1/controller/Item/action/Download';
        $ct = 'https://www.cga.ct.gov/current/pub/chap_598.htm';
        $html = '<section class="ino-shell ino-gov-public ino-gov-clarification">';
        $html .= '<div class="ino-hero-public"><span class="ino-portal-eyebrow">INO Governance • Proposal INO-GOV-CLAR-2026-001</span>';
        $html .= '<h1>Proposed Clarification on Internal Self-Government</h1>';
        $html .= '<p class="ino-portal-lead">For possible incorporation into the Constitution or bylaws. Status: DRAFT — NOT ADOPTED. No vote, amendment, officer appointment or public-law jurisdiction is asserted by this page.</p></div>';
        $html .= '<p class="ino-member-notice"><strong>Legal scope:</strong> Connecticut General Statutes § 33-264c(b) concerns a religious society\'s membership, affairs and government. International declarations are historical and policy references, not independent grants of Connecticut judicial or territorial authority.</p>';
        $html .= '<h2>Proposed text — submitted for authorized review</h2>';
        $html .= self::article('Preamble', array(
            'Recognizing the principles expressed in the United Nations Declaration on the Rights of Indigenous Peoples and the American Declaration on the Rights of Indigenous Peoples concerning Indigenous identity, cultural practices, institutions, participation and self-determination;',
            'Acknowledging the United States\' stated support for advancing the objectives of the United Nations Declaration within its constitutional and legal framework;',
            'Recognizing that Connecticut General Statutes § 33-264c(b) permits a religious society to adopt provisions concerning its membership, affairs and government;',
            'The Indigenous Nation of Onegodia proposes these principles for its internal religious, cultural, membership and administrative governance, subject to approval under its authenticated governing instruments and applicable law.'
        ));
        $articles = array(
            'Article I — Internal Self-Government' => array('INO may organize and administer its internal affairs through its Constitution, duly adopted bylaws, governing councils, appointed officers, membership procedures and authorized institutional policies, consistent with applicable law.'),
            'Article II — Institutional and Cultural Organization' => array('INO may establish internal structures responsible for religious observance, cultural education, historical preservation, membership administration, community development and institutional records.', 'Those structures derive organizational authority from applicable law and validly adopted governing instruments.'),
            'Article III — Internal Dispute Resolution' => array('INO may establish procedures for internal organizational, membership, religious and administrative disagreements, including voluntary mediation, review committees, disciplinary procedures and appeals.', 'Such procedures must respect applicable legal rights and are not an exercise of state judicial power unless separately authorized by law.'),
            'Article IV — Land, Heritage and Stewardship' => array('INO may conduct historical land research, cultural preservation, conservation, lawful property acquisition, landowner partnerships and community-development projects.', 'Internal declarations and cultural designations do not themselves establish ownership, possession, territorial jurisdiction or enforceable interests in real property.'),
            'Article V — Reservation of Legal Distinctions' => array('Nothing in this proposed clarification independently confers federal or state tribal recognition, public governmental authority, territorial sovereignty, criminal jurisdiction, compulsory jurisdiction over nonmembers, or immunity from applicable laws.', 'INO may pursue lawful recognition, cooperation, institutional agreements and remedies through established processes.'),
            'Article VI — Governing Authority' => array('Pursuant to Connecticut General Statutes § 33-264c(b), and subject to the INO Constitution and duly adopted governance procedures, the Governing Body may adopt, amend, administer and enforce provisions concerning membership, internal affairs and organizational government.')
        );
        foreach ($articles as $title => $paragraphs) { $html .= self::article($title, $paragraphs); }

        $html .= '<h2>Historical source register — separately attributed</h2>';
        $html .= '<article class="ino-gov-tile"><h3>INO-SRC-OAS-US-2016-01 — United States delegation</h3>';
        $html .= self::p('Source: Organization of American States, American Declaration on the Rights of Indigenous Peoples, AG/RES. 2888 (XLVI-O/16), footnote 1, printed pages 47–49 (PDF pages 47–49). The delegation supported continued implementation efforts concerning the UN Declaration but expressly objected to the American Declaration text and said it did not create new law.');
        $html .= '<p><a href="' . esc_url($oas) . '" target="_blank" rel="noopener noreferrer">Read the complete original U.S. statement in the OAS publication ↗</a></p></article>';
        $html .= '<article class="ino-gov-tile"><h3>INO-SRC-OAS-COL-2016-04 — Republic of Colombia delegation</h3>';
        $html .= self::p('Source: same OAS publication, Colombia statement in footnote 4, printed page 52 (PDF page 52). The discussion of Indigenous authorities as special public State authorities and Indigenous judicial jurisdiction describes Colombia\'s own constitutional framework. It does not describe INO, Connecticut or U.S. recognition.');
        $html .= '<p><a href="' . esc_url($oas) . '" target="_blank" rel="noopener noreferrer">Read the original Colombian statement in the OAS publication ↗</a></p></article>';
        $html .= '<article class="ino-gov-tile"><h3>INO-SRC-CT-CGS-33-264C-B — Connecticut statute</h3>';
        $html .= self::p('Source: Connecticut General Statutes, Title 33, Chapter 598, § 33-264c(b), on provisions a religious society may adopt respecting membership, affairs and government.');
        $html .= '<p><a href="' . esc_url($ct) . '" target="_blank" rel="noopener noreferrer">Read the Connecticut General Assembly statute ↗</a></p></article>';
        $html .= '<h2>Adoption and records safeguards</h2>';
        $html .= self::article('Required before any adoption claim', array(
            'Authenticate the currently controlling Constitution/bylaws and amendment rules; confirm who may propose, give notice, convene, vote, and attest.',
            'Create an actual meeting and agenda; document proper notice, attendance and the applicable quorum rule.',
            'Submit the exact proposed text as a resolution motion; record actual votes and decision, including abstentions, and prepare dated minutes with independent review.',
            'Retain original signed or otherwise authenticated resolution and minutes with source references and restricted-access controls; separately publish only an authorized public copy after verified institutional approval.',
            'Neither the page nor software automatically creates an adopted amendment, meeting, resolution, vote, signature, public-law jurisdiction or ODIN verification.'
        ));
        if (is_user_logged_in() && current_user_can('ino_governance_view')) {
            $html .= '<p class="ino-member-notice">Authorized governance staff: <a href="' . esc_url(admin_url('admin.php?page=ino-governance-operations')) . '">Open restricted Governance Operations ↗</a> for meeting, minutes, resolution and decision-evidence entries. This proposal is not a recorded resolution.</p>';
        }
        $html .= '</section>';
        return $html;
    }
}
