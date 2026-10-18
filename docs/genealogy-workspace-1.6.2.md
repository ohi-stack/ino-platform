# INO Genealogy Workspace — 1.6.2-rc.1

**Branch:** `feat/ino-genealogy-workspace-20261018`  
**Base:** unmerged `feat/ino-membership-portal-ui-1.6.1-20261010`  
**Classification:** staging candidate, NOT deployed or production approved.  
**Originator:** One Gregory Onegodian™  
**Institution:** Indigenous Nation of Onegodia (INO); separate from ONEGODIAN, LLC.

## Problem addressed

The prior `[ino_family_tree]` shows only approved relationships where both endpoints are registered WordPress accounts. It displays "No approved relationships are available" even when the family researcher wishes to create their own ancestor record or record an unverified research lead.

## Functional contract

- `[ino_family_tree]`: logged-in research workspace, limited to current member and site administrators, replacing the older display-only shortcode.
- Add and edit person records without requiring a WordPress account for an ancestor.
- Record name, approximate birth/death, locality, living/deceased/unknown, citations, and private/public preference.
- Link two people with clear parent-of / sibling / spouse / other direction, source notes, and duplicate detection.
- No self-links and no cross-user ancestor references. All updates and removals enforce owner_id.
- Person edits reset evidence review status; changed person links become private and unverified.
- Unverified research is visible privately without waiting for an institutional approval.
- Authorized administrators can record reviewed/unverified status using evidence notes. Review does **not** certify external genealogy, indigenous affiliation, or civil status.
- All newly added records are **unverified** until documented human review.
- Historic approved INO member-family relationships remain private and separately displayed without automatic migration into ancestor records.
- Shared read-only public tree uses a 40-character revocable token, enabled only by an explicit owner action.
- Published tree contains **only** deceased person records individually opted into public visibility; public links require both persons to be individually public and deceased and the link itself to be marked public.
- Never include source notes, living persons, unknown-status persons, protected membership metadata, or private relationships in public output.
- Public records may still be unverified, but clearly display **Unverified** labels.

## Data and migration

Two additive tables: `ino_genealogy_people` and `ino_genealogy_links`, both keyed by `owner_id`. They do **not** replace or delete `ino_family_relationships`.

The single-plugin release process invokes installation during activation and authenticated administrator-triggered schema upgrades. Upgrade requires complete database backup, a staging rehearsal, and no simultaneous legacy INO Suite/Core plugin.

## Security boundaries

- WordPress login required for editing and private viewing; shortcode `user_id` cannot expose other members' records.
- POST nonce verification and owner checks for all member write paths; admin review requires `manage_options`.
- Existing approved WordPress-member relationships are displayed only to the authorized current member/admin.
- Source notes are text, not uploaded public media; all published text is escaped.
- No automatic ancestor identification, family matching, DNA inference, external-tribal status decision, or public publication of living persons.
- Share links are bearer URLs: anyone holding an enabled public link can view its filtered content. Revocation deletes the token; treat forwarded/copied tokens as public.

## Staging acceptance checklist (must be evidenced before merge/release)

1. Clean install and upgrade from 1.6.1: tables created, existing `ino_family_relationships` preserved, old WordPress pages untouched.
2. Anonymous request: private tree hidden, member shortcode `user_id=other` cannot disclose private information.
3. Member A adds Jane Avery (deceased) and another ancestor, writes notes, links them; Member B cannot read/edit/delete either person's data by crafted IDs.
4. Member A can view unverified records immediately in private tree; public view is absent by default.
5. Mark living and unknown-status persons "public" in crafted POSTs: visibility stays private.
6. Mark deceased person public; public share URL still shows only records selected public, not all ancestors or source notes.
7. Public connection is suppressed if either endpoint becomes private/living/unknown, or the link itself remains private.
8. Revoke token: prior URL stops returning people or connections.
9. Member may NOT submit a forged `verification=verified` field to certify self; review requires authorized administrator and a source note.
10. Admin verifies a cited record; editing the person resets it to unverified and relevant links to unverified/private.
11. Duplicate/same-person links denied; reversed directional parent-of remains a separate explicit claim.
12. After deletion, no orphan links are exposed; legacy membership relationships remain unmodified.
13. Nonces, invalid IDs, unauthorized access, missing-source verification, and public-link cache behavior tested.
14. Chrome/Firefox/Safari and mobile: keyboard navigation, headings, contrast, focus, reduced-motion, form error notices, 320px layout.
15. PHP 7.4, 8.1, 8.3 lint and WordPress staging with real users; verify no conflicting shortcode owners.
16. Snapshot live plugin version and database backup, deploy only after explicit INO operator signoff and rollback rehearsals.

## Known limits — remain under development

- Not a drag-and-drop multigenerational graph or visual ancestor chart yet; currently cards and directional relationships.
- Does not yet implement GEDCOM import/export, source-file repository, image upload, household grouping, evidence conflict adjudication, automatic hints, or record-level sharing permissions for living relatives.
- Public sharing is deceased-only even if a living relative wants to opt in. Future living-person publication requires independently recorded explicit consent.
- New people are not implicitly connected to WordPress member identities.
- Review queue displays recent records; it is not a complete audit/event-sourcing history.
- Historical user-family links remain in their original table and are not duplicated.

## Priority follow-on functionality

**Phase 2:** genealogy canvas (pedigree and descendant charts), GEDCOM 7.0 import/export with privacy preview and conflict handling, archival-source attachments in access-controlled storage, person merges and duplicate suggestions, birth/marriage/death citations, relationship evidence versions, audit logging and reviewer queues.

**Phase 3:** collaborative family invitations, living-person consent workflow, ownership transfers with acceptance, scoped sharing, conflicting testimony, ancestral surname/geography timelines, indexed location research, and privacy-preserving DNA match references (not automated identity or tribe determination).
