# Indigenous Nation of Onegodia — INO Platform Plugin

**Version:** 1.5.1-rc.1 — production-target WordPress package, **not approved for live activation without actual-site acceptance**.  
**Requires:** WordPress 6.5+, PHP 7.4+, transactional InnoDB-capable MySQL/MariaDB for governance audits.  
**Installable ZIP root:** `ino-platform/ino-platform.php` (one plugin, not a nested deployment bundle).

## Current functional scope

| Component | What this package implements | Limit |
|---|---|---|
| Institutional Command Center | Scoped navy/gold/cream UI, live record counts, historical trend visualizations, accessible controls | Records are not decisions or proof of governmental authority |
| Membership & identity | Consent-gated public directory, private member profile/identity records, approved family relationship counts, `[ino_member_dashboard]` | No verified parity with the *separate Suite's* membership application/reconciliation engine |
| Governance foundation | Document/source registry, Constitution editions, office structure, reviewer/publisher privileges, audit history | Do not seed/declare controlling Constitution or real officeholder authority without evidence |
| Operational governance | Meetings, agenda locking, versioned minutes, independent review, reported resolution outcomes, assigned tasks | `outcome_recorded` is not formal legal adoption |
| Notices | Recipient-only in-platform task notices, read/unread and actor-attributed audit | No email/SMS notifications yet |
| ODIN public registry | Internal registry IDs, publication review, document hashes, version history, public exact-ID lookup | Not electronic signature certification, notarization, title record or external tribal recognition |
| Housing, grants, documents, programs | Existing record-oriented and informational sections | Not all workflows are independently verified/production-active |
| BuddyPress | Conditional integration and fallback links | Test with actual theme, configuration and plugin versions |

**Public/administrative interfaces:** `[ino_portal]`, `[ino_member_dashboard]`, `[ino_identity_dashboard]`, `[ino_governance]`, `[ino_governance_dashboard]`, `[ino_governance_operations]`, `[ino_constitution]`, `[ino_governance_structure]`, `[ino_governance_records]`, `[ino_odin_registry]`, `[ino_odin_verify]`, `[ino_odin_timeline]`.

## Installation and compatibility safety

**Do not install this plugin concurrently with the older `ino-platform-suite` or `ino-platform-core` plugins.** Their memberships, shortcodes, routes, schemas and status authority have not been fully reconciled. Back up the live site first and rehearse a replacement on a clone. The plugin blocks simultaneous activation of recognized conflicting plugins.

1. Take a **complete database and wp-content backup** and demonstrate successful restore in an isolated environment.
2. Clone the actual WordPress site to a protected staging domain with identical theme, PHP, MySQL/MariaDB, page builder and plugins.
3. Audit installed INO plugins, membership linkage/duplicates, user roles, public page edits, document privacy, notification handling and historical records.
4. **Deactivate (do not delete) older INO installations only in staging**, then upload the candidate ZIP through Plugins → Add New → Upload Plugin.
5. Activate and verify schema/roles/pages, compare existing records and run the meeting-to-decision and public privacy acceptance tests. Do not treat a successful activation as proof that organizational workflows are authorized.
6. Verify Command Center, member pages and ODIN in browsers at 320px/768px/1440px, including keyboard, focus and reduced motion; configure page cache/CDN to exclude private endpoints.
7. Obtain accountable written release approval and separately execute the migration/rollback plan.
8. Only **after real hosted staging acceptance**, the site owner can explicitly authorize production activation in wp-config.php with:
   `define('INO_PLATFORM_PRODUCTION_APPROVED', true);`
   Never set this solely to bypass failing tests.

The default production-activation gate prevents activating this release candidate on a site reporting `WP_ENVIRONMENT_TYPE=production` until that flag is explicitly true. A deactivated plugin is not the same as migration/record parity.

## Release-specific corrections

- Release schema lifecycle decoupled from presentation version changes. Public web requests no longer automatically perform heavy table migrations/rewrite flushes on every version change.
- Repeat activation/upgrade now uses WordPress `dbDelta()`-compatible one-field-per-line definitions, correcting observed database ALTER errors in earlier code.
- `[ino_member_dashboard]` is a backward-friendly alias to the *same user-scoped* Identity & Heritage dashboard; it does **not** silently create a parallel membership registry or merge existing Suite accounts.
- Preserves governance audit integrity, including private notification read acknowledgements.
- Every release ZIP is assembled from the canonical GitHub source and reinstalled over existing synthetic governance data in disposable WordPress/MariaDB staging CI.
- PHP 7.4/8.1/8.3 source lint, PHP 8.2 WordPress/MariaDB workflow tests and ZIP CRC/SHA-256 verification run before artifact delivery.

## Test evidence

- [Reproducible full WordPress integration workflow](.github/workflows/ino-governance-wordpress-staging.yml)
- [Integration test harness](tests/staging/run.sh)
- [Workflow audit evidence](docs/governance-phase2-wordpress-staging-evidence-20261010.md)
- [Phase 1 governance contracts](docs/governance-phase1-foundation.md)
- [Phase 2 operational boundaries](docs/governance-phase2-operations.md)
- [Phase 3 ODIN publication boundaries](docs/governance-phase3-odin-transparency.md)

**Status:** Disposable WordPress integration may pass while production is still unapproved. The older INO Platform Suite v0.1.2 UI build is **not** merged into this plugin wholesale; the GitHub Command Center and public UI provide the selected canonical interface. Membership application parity and hosted staging acceptance remain blockers to replacing a functioning Suite.

## Institutional separation

The Indigenous Nation of Onegodia administers its internal religious, cultural, membership, governance and community processes. ONEGODIAN, LLC is a distinct economic/commercial enterprise. Internal INO credentials and registry records do not themselves establish external governmental authority, tribal enrollment or land rights.
