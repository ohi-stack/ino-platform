# INO Platform — Command UI design candidate

**Branch:** feat/ino-command-ui-20261009
**Scope:** Admin, public portal, private heritage dashboard; visual and data-read only.
**Base:** fix/ino-membership-privacy-gates-20261009 (draft security PR #12).
**Release status:** Candidate, not deployed; do not promote as production-ready.

## Branding

Canonical palette: #0D2B45 navy, #071B2D deep navy, #F3E7BD cream, #FAF4DF light cream, #C99A2E gold, #E2C56F light gold, #A5070A red, white.

## Implementation

- assets/css/ino-admin-command.css: scoped command-center layout, responsive grid, animated ornaments, charts, motion controls.
- assets/js/ino-admin-command.js: dependency-free SVG trend line; selector changes metric. Data from actual WordPress INO tables.
- assets/css/ino-public-platform.css: responsive portal, private-member widgets, decorative motion and reduced-motion support.
- includes/class-ino-platform-admin.php: admin-only record totals, six-month record creations, accessible fallback table, module navigation, explicit unverified states.
- includes/class-ino-platform-shortcodes.php: public portal and private identity dashboard; preserve existing privacy gates from base PR #12.

## Data contracts

Total-count tables: ino_members, ino_identity_declarations, ino_family_relationships, ino_connections, ino_grants, ino_housing_projects, ino_documents (prefixed by WordPress).

Trends: member, declaration, grant and document records CREATED each month. These are neither approved-record counts nor financial totals. Backfilled/migrated timestamps may affect apparent trend periods. Zero remains zero.

Private dashboard: logged-in user's declarations count, approved family-relationship count, and latest recorded membership status. No cross-user private records or new staff permissions.

## Staging acceptance gates

1. PHP 7.4, 8.1 and 8.3 syntax checks plus baseline privacy-style guards must pass on exact head.
2. WordPress staging: test empty tables, populated tables, and upgraded data; reconcile totals and monthly values with SQL.
3. Public/member view: test anonymous, account owner, unrelated member, and administrator for permission regressions.
4. Test at 320px, 768px and 1440px; keyboard navigation, contrast, and focus visibility.
5. Test chart toggles, no-JavaScript fallback table, screen reader descriptions, and reduced-motion settings.
6. Check compatibility with existing WPBakery page templates, theme headers, and protected records.
7. Verify there are no fictional actions, approvals, exports, activity events or financial data.
8. Check asset versioning and that existing page contents survive upgrade.
9. Merge security PR #12 or reconcile its controls onto the target base before release.
10. Back up, stage and verify the deployment after approval. This branch does NOT deploy production.

## Intentional exclusions

No donor financial data, fabricated charts, implied governmental recognition, site ownership assertions, sensitive archive previews, approval automation, unauthorized uploads, or placeholder actions labelled operational. Motion is decorative and optional, without external animation dependencies.
