# INO Platform Shortcode Manager — v1.6.5-rc.1

**Originator:** One Gregory Onegodian™  
**Source branch:** `feat/ino-shortcode-manager-20261018`  
**Stacked onto:** `feat/ino-declarations-affidavits-20261018` (unmerged; includes Documents and Genealogy ancestors)  
**Status:** Unmerged staging candidate. Not evidence of a live installation.

## Scope and access

The INO Platform → **Shortcode Manager** is a **site administrator only**, read-only diagnostics and navigation area. It registers **no public shortcode** of its own, does not execute handlers, mutate shortcode registrations, automatically create or publish pages, or expose page content.

It inventories the exact 39 catalogued INO core shortcode tags from the v1.6.4-rc.1 source, preserving explicit metadata for:
- Purpose
- Module owner and category
- **Source maturity**: `Informational / placeholder`, `Interface coded — unverified`, or `Workflow coded — unverified`
- **Intended audience / access** (informational; actual permissions must be tested)
- Current runtime `shortcode_exists()` registration and `is_callable()` callback status
- Callback owner class and unexpected owner/collision flag
- WordPress page title, post status, page ID and administrator edit link for each assigned shortcode

Any **additional registered** `ino_` shortcode from a connected add-on is discovered automatically, but labeled `Not classified` and `Not assessed` until its code and access controls are reviewed.

**Do not conflate** callback availability with rendering success, authenticated behavior, permission safety, data persistence, external integration or production readiness. None is claimed by this manager.

## Page-mapping behavior

The admin-only index searches the WordPress `wp_posts` page records, including statuses publish, private, draft, pending, future. It looks for literal `[ino_` tags in `post_content` and confirms each catalogued tag with WordPress `has_shortcode()`. It works for native shortcode blocks or WPBakery text where the tag appears directly in saved content.

- It intentionally **does not scan or render** widget areas, reusable block bodies, theme templates, client-side dynamic builders, post types other than `page`, or plugin-generated pages without literal shortcode text.
- Scanning is bounded to 2,000 candidate pages per admin page load; a prominent partial-results warning appears when the limit is exceeded. It does not pretend that a bounded scan is exhaustive.
- A shortcode in two pages is shown in both places; an unassigned shortcode is still a valid registered handler.
- Page titles and unpublished page IDs are restricted to administrators.

## UX and operations

- Five live, database/runtime-derived overview counts: tracked, callable, assigned, missing/invalid, unexpected owners.
- Search by shortcode, purpose, group, intended access or callback owner.
- Filter by module, source maturity, page placement and handler health.
- Copy each shortcode with accessible status message and honest fallback when clipboard permission is unavailable.
- Open an assigned WordPress page for editing when the administrator has `edit_post` rights.
- Export an authenticated/nonced CSV of the complete inventory, including assigned page IDs and statuses; spreadsheet-formula-prefixed cells are escaped. Export is for administrative use, not public distribution.

## Staging / production acceptance

1. Run `php -l` for all PHP files on 7.4, 8.1 and 8.3.
2. Run `php tests/release/shortcode-manager-smoke.php` to assert 39 explicit catalog entries, unique names, source maturity, registered and missing handlers, unknown add-on discovery, collision warning and page placements.
3. In **real WordPress staging**, test a known assigned public page, a private/draft page, a page with multiple tags, and a WordPress builder reference; confirm accurate classification and no false live-readiness claims.
4. Confirm duplicates and overridden callbacks are identified correctly, without executing shortcode callbacks.
5. Disable an optional shortcode-owning module in a safe staging setup; verify missing handler status without fatal error.
6. Validate all 39 catalogue names against the effective runtime registry and validate extra names from installed add-ons separately.
7. Ensure shortcode manager menu and the command center card resolve to one page, are hidden and inaccessible for unauthenticated / nonadministrator users, and page-title / unpublished-record metadata cannot leak.
8. Check source maturity, intended access, and incident handling against the module's actual current implementation. These are **maintained manifest assertions**, not automatic runtime certification.
9. Validate admin GET search/filters, reset, copy fallback and keyboard interactions; confirm CSV nonce, permissions and spreadsheet escaping, including malicious post titles.
10. Test 320px–desktop responsive UI, screen-reader controls, reduced motion and no-JavaScript fallback.
11. Run WordPress staging with the complete dependency stack (Genealogy PR #26, Documents PR #27, Declarations PR #28) before merge or production promotion.
12. Preserve the production approval gate, backup/rollback procedure and documented post-deployment smoke evidence.

This version makes **no database schema change**. Only the plugin candidate version increases to **v1.6.5-rc.1**; the existing schema version remains **2026-10-18.3**.
