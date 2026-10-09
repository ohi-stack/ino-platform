# INO Governance Foundation — Phase 1 / 1.3.0-rc.1

Status: draft release candidate, not verified deployed. Branch: feat/ino-governance-foundation-20261009, stacked on UI PR #13 and privacy PR #12.

## Purpose

Provide a functional, internally controlled INO governance records foundation without pre-emptively claiming adoption, authentication, office appointments, external tribal recognition, or civil governmental powers.

The project material identifies a February 23, 2020 Constitution adoption date, but does not independently establish the current signed, authoritative instrument, all amendments, governing quorum, succession rules, or current appointment roster. No governance content is silently seeded.

## Actual components

* New table: wp_ino_governance_items (prefix resolved dynamically). Three types: constitution, office, record. Immutable draft creation, version labels, source references, public/restricted classification, optional parent office, optional public Constitution PDF attachment, SHA-256 hash, adoption/effective dates, creator/reviewer/publisher actor IDs, review and publication timestamps.
* New table: wp_ino_governance_audit. Event history for draft creation, review, publication and draft withdrawal. Creation and transitions use DB transactions so missing audit inserts roll back the action on transaction-supporting database tables.
* Governance-specific roles: ino_gov_records_officer (view/edit), ino_gov_reviewer (view/review) and ino_gov_publisher (view/publish). WordPress admins get technical governance view/edit only; they do not automatically receive review or publication powers.
* Restricted INO Governance administration menu accessible to explicitly privileged roles; WordPress administrative authority is distinct from authorized institutional decision-making.
* Three-phase human states: draft -> reviewed -> published, or draft -> withdrawn. There is no direct draft -> published action. Publisher must differ from reviewer; publisher explicitly attests its authority.
* Public information: only published AND explicitly public items. The public Constitution PDF link requires a valid attached PDF that still matches the recorded SHA-256 digest.
* Public office structure: parent-office relationships and documentary source references; no claims about current officeholders.
* Dashboard: live counts by type, draft/reviewed/published/withdrawn distributions, recent database events and action controls. No fabricated figures.
* Existing [ino_governance] shortcode becomes the public navigation portal.

## WordPress shortcodes

* [ino_governance] — public navigation landing.
* [ino_governance_dashboard] — privileged summary; if unauthenticated or unprivileged, renders the public landing instead.
* [ino_constitution] — published public Constitution editions.
* [ino_governance_structure] — published public offices and documented reporting parents.
* [ino_governance_records] — published public record summaries.

Automatic new WordPress pages (created only if the slug does not already exist): /ino-constitution/, /ino-governance-structure/, /ino-public-records/. Existing page content is not overwritten.

## Publication requirements

1. A record starts in draft status. Required fields: record type and title; system can generate an internal reference code if omitted.
2. Before review, all types require a documented source reference and a substantive review note.
3. Only a user with ino_governance_review may mark a draft reviewed.
4. Only a different user with ino_governance_publish and an explicit checked attestation may publish a reviewed record.
5. For a Constitution: an existing PUBLIC WordPress media-library PDF attachment, SHA-256 checksum and recorded adoption date are necessary.
6. For a subordinate office: the parent office must already be published.
7. Changes are recorded in an append-only application audit table, with conditional SQL status updates to avoid double publication.
8. Published records are immutable through this Phase 1 interface. Corrections require a new documented version and later supersession policy.

## Source and privacy boundaries

**WordPress Media Library is not private file storage.** Draft and restricted governance materials should not be uploaded to the standard public Media Library as a way of securing them. This candidate deliberately supports only already-public Constitution PDF attachments, and does not provide protected storage for signed originals or private legal evidence. Restricted record metadata stays in database access-controlled lists.

The registry is NOT a legal certification service; publication denotes a recorded internal workflow, not judicial validation of a document's authenticity or authority. Internal religious and organizational governance must be distinguished from governmental authority binding outsiders.

## Needed institutional source records

* Controlling signed Constitution or authenticated archival facsimile, adoption evidence and certified amendment chronology.
* Written office authority references, appointment records, authority matrix, and current leadership roster.
* Documented delegation of authority for reviewers and publishers, including applicable conflicts of interest.
* Approved retention and supersession policy, private-file system design, and accessibility/data-protection policy.

None of these is automatically invented from a planning document.

## Required staging checks

* Lint all PHP on PHP 7.4, 8.1 and 8.3 and run CI static contract tests.
* Verify plugin activation upgrade migration creates both tables with the configured WP prefix, retains legacy INO records and creates missing new pages without overwriting existing pages.
* Test administrator, records officer, reviewer, publisher, ordinary member and anonymous visitor across allowed and denied routes. Test nonces and direct POST bypass attempts.
* Verify reviewer versus publisher separation and that failed transitions cannot create orphaned audit records.
* Verify unauthenticated shortcodes show no drafts, restricted documents, audit notes or personal actor identities.
* Test PDF missing/changed hash and nonpublic attachment blocking, and verify that media URLs are treated as public.
* Test parent office publication requirement, status-chart reconciliation, keyboard and 320/768/1440px layout, WPBakery styling and reduced-motion settings.
* Confirm WordPress database tables use a transactional engine to support rollback guarantees; test during denied/replayed and concurrent requests.
* Validate office authority references and Constitution metadata against signed governing documents before public publication.
* Stage, back up, complete integration acceptance and separately authorize live deployment. Do not mark this version operational until these pass.

## Still out of scope

Voting and quorum engine, meeting minutes, officer appointment verification, signed Constitution ingestion/verification service, private secure document vault, public ODIN certified verification, governance task automation, amendment approval engine, elections and succession workflows. These require source-governed Phase 2 and beyond.
