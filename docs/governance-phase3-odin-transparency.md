# INO Governance — Phase 3 ODIN & Transparency

**Version:** 1.5.0-rc.1 (development candidate).
**Branch:** feat/ino-odin-transparency-phase3-20261010.
**Base:** Phase 2 Governance Operations PR #15; stacked behind PR #14, #13 and #12.
**Deployment:** Not deployed. Requires WordPress staging acceptance, source-document validation, and approved institutional authority.

## Scope

Document registration, append-only edition histories, internal signature-evidence references, authorization-controlled public publication, privacy-preserving exact-ID verification, and public publication timelines.

"ODIN" in this implementation means an internal **INO document and event registry**. It is not a blockchain service, e-signature engine, notarization service, source of legal authenticity, or proof of governmental/tribal recognition. Only published public Phase 1 source records are eligible for public ODIN release.

## Data model and migration

- `ino_odin_records`: randomly generated, non-sequential 122-bit-entropy UUIDv4-based `INO-ODIN-<32 HEX>` registry IDs; creator and creation date.
- `ino_odin_versions`: immutable editions, source reference, linked Phase 1 document record ID, summary, reviewer and publisher, recorded hash, draft/reviewed/published/superseded/withdrawn status.
- `ino_odin_signature_evidence`: append-only observation by a logged-in staff user, with source/evidence reference, date and first-person statement. No signed keys or cryptographic validation.
- `ino_odin_events`: internal actor-attributed event trail for registration, revision, evidence, review, publication, supersession, withdrawal.
- A version-controlled schema marker `ino_odin_schema_version` governs replay-safe installation, names using the current WordPress table prefix. Migrations must be tested with transactional InnoDB storage.

## Operations

1. Authorized INO records officer / administrator with `ino_governance_edit` creates a private document registry draft with descriptive title, protected source reference and optional Phase 1 item ID. No governance approval is created.
2. Any internal evidence observed by that officer may be recorded as a first-person signature-evidence reference. The application does not issue a signature and cannot authenticate a historic signer's identity.
3. Reviewer with `ino_governance_review` (not the draft author) records a substantive note. A linked Phase 1 item is mandatory for review.
4. Publisher with `ino_governance_publish` (not the draft author or reviewer) attests permission and source verification. Underlying Phase 1 source MUST be published, explicitly public, and its public PDF (if any) MUST pass SHA-256 validation.
5. Publishing a new edition atomically supersedes the older public edition. No draft revision alters the displayed public edition.
6. Public record lookup uses an exact, hard-to-enumerate UUID-based ODIN ID and returns only public metadata, the current edition and public Phase 1 PDF where accessible and intact. Invalid, unknown, withdrawn, draft, or restricted IDs yield the same unavailable response. No private source notes, witness identities or review notes are disclosed.
7. Public historical timeline displays release dates for published/superseded editions only, filtering every linked source against CURRENT Phase 1 public status and recorded hash integrity. It is not an immutable blockchain archive.
8. Internal staff see versions, signature-evidence observations and audit entries. Public users never access those internal administrative screens.

## Pages / shortcodes

- WordPress admin: `/wp-admin/admin.php?page=ino-governance-odin`
- Public `/ino-odin-registry/` → `[ino_odin_registry]`
- Public `/ino-odin-verify/` → `[ino_odin_verify]`
- Public `/ino-odin-timeline/` → `[ino_odin_timeline]`

Automatic page creation does not replace existing WordPress page content.

## Release blockers and staging acceptance

1. Verify exact-head PHP lint for PHP 7.4/8.1/8.3 and static contract/route guards in GitHub Actions.
2. Install on clean and upgraded WP staging; test full migration, prefixing, indexes, InnoDB, rollback and option-marker behavior. Ensure existing Phase 1/2 tables and pages are not modified or reset.
3. Verify no shortcode collision with existing site plugins or the separate INO Platform Core package (including the shared `[ino_contact_form]` conflict).
4. Exercise direct POST actions with missing/invalid nonce, missing capabilities, self-review/self-publication, stale revisions and mismatched source records.
5. Test new draft, first review, authorized publication, new version, supersession, and attempt competing concurrent publishers. No public exposure before committed authorization.
6. Test PDF tampering/removal, Phase 1 source withdrawal or visibility change, missing public attachment and wrong document SHA. Lookup must fail closed or withhold PDF.
7. Test a private Phase 1 item, or a merely `outcome_recorded` Phase 2 resolution, cannot become public via ODIN. No bypass of underlying authorizations.
8. Test exact IDs versus partial/invalid IDs; prevent discovery of private, unreviewed or invalid records via registry, verify, timeline, WordPress REST/search and caches.
9. Check public output for user names, emails, actor IDs, signature evidence, private source references, historical drafts or restricted records.
10. Verify keyboard/screen-reader flows, 320px/768px/1440px layout, focus styles, contrast, and `prefers-reduced-motion`.
11. Test retained public release after drafting/reviewing a new edition. Confirm old public edition status becomes superseded only on publish.
12. Review retention, correction/supersession, signature-handling and published-document claims with authorized INO staff and relevant counsel before releasing.
13. Back up WordPress data, reconcile PR #12→#13→#14→#15 and rebase the Phase 3 branch; obtain independent review and WordPress staging verification before any production deployment.

## Non-goals / known limitations

- Not a legally validated electronic/digital signing process; signature **evidence references** only. Any real e-signature service, certificate authority, custody, signer identity verification and timestamp authority require separate compliant components.
- No encrypted private file vault. The WordPress Media Library is generally public and is not used here for restricted source files.
- No blockchain commit, decentralized nodes, external governmental recognition verification, cryptographic notarization, permanent external storage or tamper-proof event log.
- No automatic legal publication of carried Phase 2 resolutions, and no automatic inheritance of governance powers from a WordPress admin account.
- No mass import of archived Constitution text, leadership names, historical dates or signed documents without source authentication.
- No public indexing of members' personal identity, genealogical or health data.
