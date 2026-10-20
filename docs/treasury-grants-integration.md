# Treasury & Grants — additive INO Platform integration (October 20, 2026)

## Source of truth

This change extends the existing WordPress plugin in `ohi-stack/ino-platform` and does **not** replace its membership, BuddyPress/social, genealogy, identity, or public-page code. A separately distributed v1.2.0 grants ZIP and the repository's v1.2.0 are divergent codebases; installing one over the other without reconciliation risks losing existing modules.

## Scope (draft implementation)

- Upgrade-safe extension of existing `wp_ino_grants` (uses the actual WP table prefix) with eligibility, geographic fit, priority, assigned party, verification, and publication state.
- New project, project/opportunity match, application, compliance, and audit tables.
- Dedicated `INO Grants Administrator` capability set; existing site administrators receive grant capabilities.
- Separate `INO Funding` menu so grants staff do not need WordPress-wide `manage_options`.
- Nonce-protected, capability-checked server-side forms for opportunities, projects, matches, applications, and compliance.
- Restricted CSV export and intentionally limited public listing/API fields.
- Reference-only program library; not a list of verified active notices.
- Public shortcodes: `[ino_treasury_grants]`, `[ino_grant_opportunities]`.
- REST GET: `/wp-json/ino-platform/v1/funding/public` (verified public records only), `/wp-json/ino-platform/v1/funding/summary` (grants staff only).

## Applicant and land-title controls

Applicant entity must be explicitly documented. INO religious-society status is not proof of federal/state tribal recognition or eligibility for tribal-restricted programs. ONEGODIAN, LLC is a separate commercial applicant only when an award permits it. STEAP, CDBG, HOME, AHP, and other intermediary-funded programs require individual applicant/partner-pathway review. UCC filings do not by themselves establish ownership or site control. Research, opportunity reported, owner contact, and site control under review are *not* equivalent to acquired land.

Public grant records require both `verification_status=Verified` and `is_public=1`. No member identity, internal due diligence, private notes, applicant files, awards, or compliance data is returned by the public grants endpoint.

## Existing-data migration

1. Back up WordPress files and database. Inventory existing `ino_grants` record count and schema.
2. Activate/update on a staging clone with the current full INO Platform installed.
3. Confirm original grant IDs/tracking IDs and records remain unchanged.
4. Confirm previously existing member, identity, relationship, document, and social tables and user-facing shortcodes still work.
5. Verify newly added columns default to unpublished / unverified, even when older rows existed.
6. Verify project, match, application, and compliance rows survive a second installation pass.
7. Compare database row counts before/after repeated activations. No data may be deleted.
8. Reconcile any tables installed by the separately distributed grants-focused v1.2.0 ZIP before shipping a production release.

## WordPress staging acceptance

- Administrator can add and edit records; non-staff cannot access the dashboard, CSV, REST summary, or write actions.
- Dedicated grants role can access INO Funding but not unrelated platform administration.
- Nonce missing/expired and fabricated foreign keys are rejected.
- Public-only API/shortcode reveals no internal notes, review decisions, site info, or unverified lead.
- Test malicious input including XSS in program names, URLs, notes, and CSV formula prefixes.
- Test all states: Go/No-Go, submitted, awarded, declined, compliance, and closeout.
- Compare application totals and award amounts against the real database before any public claim.
- Verify desktop/mobile admin forms and keyboard navigation.
- Complete full plugin PHP lint, WordPress staging/browser tests, backups, and rollback rehearsal.
- Obtain INO approval before release; no production deployment is claimed.

## Status

**Source integration candidate / staging required.** Program availability, applicant eligibility, and live functionality on `indigenousnations.org` are not verified by this PR.
