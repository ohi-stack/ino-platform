## 1.6.0-rc.1 — Nonbinding Voting & Consultation

- New WordPress administration and frontend voting modules and three shortcodes.
- Draft scheduling, audience rules, options, voting receipts, administrator-only/public-after-close results, CSV and event audit.
- One ballot per eligible WordPress account with transactional inserts, unique constraints, record locking, vote-window enforcement.
- Real WordPress/MariaDB voting regression suite and reinstall retention assertions.
- Nonbinding only; no identity-proofed elections, secret ballot or constitutional adoption.

## 1.5.2-rc.1 — BuddyPress Integration Upgrade

- Replaced the BuddyPress Integration placeholder with an operational WordPress admin workspace at `ino-platform-buddypress`.
- Added component/runtime health checks, stored diagnostics, opt-in private profile tab, media and friend-request settings, member ID alignment, and administrative audit.
- Enforced approved-public-directory consent before new connection requests.
- Prevented implicit exposure of private INO identity and genealogy data via BuddyPress xProfile or cross-user profile tabs.
- Added real BuddyPress-on-WordPress integration smoke tests to staging CI, including nonce and role restrictions.
- Kept canonical 1.5.x membership, governance and ODIN data model; no duplicate users or bulk export.
- WP host integration and BuddyPress global directory privacy require separate staging acceptance.

# INO Platform Plugin Changelog

## 1.5.1-rc.1 — Production-target package, NOT live-approved

- Added single-plugin release/activation gate; blocks known legacy INO Suite/Core collisions.
- Added explicit approval constant for actual production activation.
- Separated one-time schema installation and administrative upgrades from front-end views and UI versioning.
- Fixed repeated-activation dbDelta SQL parser defects in seven canonical tables.
- Added compatible `[ino_member_dashboard]` shortcode and automatically provisioned member page using the existing private Identity & Heritage records.
- Maintained Governance Phases 1–3, modern institutional styling and security gates from the stacked GitHub development branch.
- Full synthetic WordPress/MariaDB meeting-to-decision regression tests: 33 positive/data assertions and 20 expected denials, including audit outage rollback and notices.
- Reproducible installable ZIP package and SHA-256 from exact tested source, followed by deactivate/install/activate retention checks.
- Actual hosted staging, membership-Suite reconciliation, browser UI and production sign-off remain mandatory.

## 1.5.0-rc.1 — ODIN & Transparency

- Source-linked public ODIN registry IDs, document edition history, independent publication and restricted signature-evidence references.
- Public exact-ID verification and published historical timeline.
- Internal registry publication is not legal/cryptographic authentication.

## 1.4.0-rc.1 — Operational governance

- Evidence-backed meetings, agendas, minutes revisions, resolution review and reported decision outcomes.
- Private assignments, notifications and event audit trail.

## 1.3.0-rc.1 — Governance foundation

- Constitution records, institutional office hierarchy, reviewer/publisher roles and audit history.

## 1.2.x / 1.3.0-dev

- Membership privacy guards, identity, ancestry and family relationships.
- Modern WordPress Command Center, public portal and member presentation.
- Identity modules, social/BuddyPress compatibility and grants/housing foundations.
- Modular repository scaffolding retained for later verification.
