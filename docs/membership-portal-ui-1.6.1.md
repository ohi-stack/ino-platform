# INO Membership Portal UI — v1.6.1-rc.1

**Status:** Staging/review candidate. This release upgrades the canonical INO Platform v1.6 membership presentation; it is **not** a second membership registry and **not** a separately verified applicant onboarding/approval engine.

## Surfaces

**Admin (protected)**: `/wp-admin/admin.php?page=ino-platform-members`

- INO Membership Command Center replaces the previous "interface in development" placeholder.
- Four current-account KPIs: distinct linked WordPress membership accounts, current approved, current pending, and approved accounts with the latest recorded directory consent.
- Status distribution with numerical/bar visualizations, classifications from existing `member_type` values (not hard-coded fabricated breakdowns), six-month records-created activity, responsive SVG charts, no-JS data table fallback.
- Searchable current-account roster: case-insensitive status filter, member ID/name/category search and server-side 15-row pagination. Displays only latest stored record per linked WordPress account.
- Read-only navigation into WordPress user accounts. No add, edit, delete, approve, credential issuance, financial workflow, or PII export is implied or added.
- An unlinked-row count is explicitly shown as a data-quality caveat. Counts are stored-state summaries, NOT proof of external legal identity/tribal or civil citizenship.

**Private member dashboard**: `/ino-member-dashboard/` → `[ino_member_dashboard]`

- Sign-in required, no private data returned for guests.
- Personal membership identifier, latest status/category, avatar, and self-scoped declaration/relationship/voting counts.
- Six-month self-recorded activity trends (declarations and account's recorded ballot receipts), accessible source table and no fabricated data.
- Quick links to pre-existing records, family tree, voting and history views.
- Private last-six declaration status/date table (no ancestry narrative or private evidence copied to dashboard).
- Internal governance unread-notice count restricted by recipient WordPress account; does not reveal notification message text.

**Public membership landing**: Existing `[ino_membership]` and new `[ino_membership_portal]`

- Professional native shortcode view that explains INO membership and links to the existing account portal.
- Does **not** claim verified application intake, enrollment certification or external tribal recognition.

## Design and motion

- Institutional colors: navy `#071B2D`, blue `#0D2B45`, gold `#C99A2E`, cream `#FAF4DF`, deep red `#A5070A`.
- Responsive 4-to-2-to-1 KPI layout, 2-to-1 panels, accessible readable tables with horizontal scroll when required.
- Animated hero concentric-ring motion, progressive card entry and chart-path reveal. Respects CSS `prefers-reduced-motion: reduce`; core content and tables are usable without JavaScript.
- No external chart library, tracking request, API credential or font dependency.

## Security and data semantics

- Administrator-only roster (WordPress `manage_options`); non-admins cannot load it.
- Per-user database queries use `$wpdb->prepare` and `get_current_user_id()`; no query parameter can select another person's dashboard.
- Current membership status and public directory consent come from the **most recent** row per WordPress user. A later opt-out/pending decision hides a profile even if an older record was approved and public.
- Only successful WordPress login receives dashboard details. Private pages keep release-level pre-render no-cache handling and require CDN exclusion on hosted sites.
- Charts get only six month-level numeric aggregates of *the current user's own records*, or administrator metrics when in protected wp-admin.
- This version creates **no new database schema**, and does not alter governance role/council/approval workflows.

## Real WordPress regression gate

The disposable WordPress + MariaDB + BuddyPress integration pipeline must demonstrate:
1. The legacy 33 valid/20 rejected governance workflow and 17 valid/14 rejected voting lifecycle continue passing.
2. Member and admin shortcode routes, UI assets and actual account-specific data appear.
3. Anonymous users see a login state only, with no member IDs or private narratives.
4. Two separate WordPress members cannot read one another's member ID, declaration details or private activity rows.
5. Admin roster search/status filters use server-side prepared SQL; no custom GET filter can query another member's *private dashboard*.
6. Latest status/consent revocation removes the account from public directory and blocks direct public profile.
7. Privileged WordPress administrator sees aggregate KPI, classification, status, activity and roster tables; unprivileged users cannot load the admin view.
8. The exact-source plugin ZIP builds and is reinstalled on the populated isolated database, retaining all governance, voting and membership test data.
9. PHP 7.4/8.1/8.3 lint, CSS/JS asset presence, ZIP integrity, SHA-256 all pass.

**Hosted-site acceptance required:** existing INO Suite migration parity, actual indigenousnations.org theme/WPBakery/BuddyPress display, role permission ownership, website caching, WordPress/MySQL version, backup/restore, and accessibility testing on desktop/mobile. No live deployment is implied.

**Institutional distinction:** INO membership is voluntary internal cultural/religious/institutional participation and cannot independently confer civil citizenship, government identification, external tribal enrollment or jurisdiction.
