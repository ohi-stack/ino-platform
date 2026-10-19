# INO Documents & Records — Administrative Register v1.6.3-rc.1

**Institution:** Indigenous Nation of Onegodia (INO)  
**Originator:** One Gregory Onegodian™  
**Branch:** `feat/ino-documents-registry-20261018`  
**Parent:** `feat/ino-genealogy-workspace-20261018` (unmerged)  
**Status:** DEVELOPMENT / STAGING CANDIDATE — not deployed or production approved.

## Purpose

Replace the **Documents** administrative placeholder ("Module overview and administrative routing") with:
1. A source-based, 57-item required-document inventory spanning seven administrative groups.
2. An actual database-backed document metadata register (creation, editing, controlled review, publication, archive).
3. Auditable role-separated workflow and administrative CSV export.
4. A separate public shortcode that shows explicitly published/public metadata only.

**Important:** an inventory item is NOT a signed, approved, filed, executed, notarized, owned, granted, adopted or externally recognized document. The module **does not automatically import** any PDF or other file from the project archives. The source materials have different evidentiary significance and may include sensitive or superseded statements.

## Inventory

- **Formation & Institutional Status (6):** Connecticut Certificate, status/standing, founding chronology, purpose, religious-society file, external tax-status evidence when applicable.
- **Constitution & Governance (10):** constitution, charter/bylaws, leadership authority, councils, meeting notices, minutes, resolutions, authentication, conflicts and appeals.
- **Membership, Identity & Family (8):** enrollment rules, applications, written consents, administrative decisions, directory consent, credential policies, genealogy evidence, personal declarations.
- **Treasury, Banking & Grants (11):** Treasury Charter, finance manual, internal controls, annual budget, banking resolution, treasury manual, grant policy/register, grant authority, grant award/reporting, procurement, reconciliation.
- **Land, Housing & Conservation (9):** historical evidence, deeds/title, landowner permission, conservation agreements, site review, housing assessment, readiness binder, site control/finance, municipal permits.
- **Programs, Community & Culture (6):** program policies, volunteer records, language attribution, testimony, partnerships, publication licenses.
- **Records, Privacy & Compliance (7):** retention, classification/access, privacy, incident response, record request log, provenance/versions, public notices.

The inventory draws on the INO document-header classification, Treasury & Grants documentation, governance records, heritage and housing planning materials. Exact document titles are workflow types, not claims that final approved documents exist.

## Operative records

The current `ino_documents` table is evolved additively with fields for document title, category, version, requirement mapping, issuing office, execution date, source reference, description, confidentiality, workflow status, internal evidence classification, actor/time provenance and a generated internal reference `INO-DOC-######`. Existing `Filed` rows retain their original status and are marked **Legacy Filed / Unassessed** in the interface.

New `ino_document_events` captures actor, before/after states, action and time. This log is not yet a cryptographically signed or immutable audit trail.

Workflow: **Draft → Under Review → Internally Reviewed → Published → Archived**. A reviewer can return a submitted record for revision. The creator cannot review their own record; publisher cannot be the creator or reviewer. Publishing requires the record to be classified for public disclosure. Reviews require a source/provenance reference. Edits reset the review classification, require re-review, and do not silently alter already published records.

Records roles:
- `ino_records_officer`: edit drafts and submit review (`ino_documents_manage`).
- `ino_records_reviewer`: return or internally review a submitted record (`ino_documents_review`).
- `ino_records_publisher`: publish or archive eligible records (`ino_documents_publish`).
- Administrators retain access, but the creator/reviewer/publisher separation remains enforced by actor IDs.

Public `[ino_documents]` (and `[ino_documents_registry]`) shows *metadata only* for rows with status `published` and classification `public`. Private content, source references, original documents, and restricted metadata are not returned to anonymous visitors. Sensitive page and CDN caching should be assessed during staging.

### Current limitations

1. No secure original-file upload, protected binary storage, file virus scanning, per-file download authorization, document hash, or PDF preview is implemented. Source reference is an admin-only textual descriptor, **not** an attachment.
2. No signing service, electronic notarization, identity validation, external filing, government approval, or automated legal certification.
3. No automatic migration of other archives (e.g. Constitution/ODIN governance registries), nor bulk Project-file import.
4. CSV exports contain metadata only and are restricted to records managers. The CSV cells are sanitized to reduce spreadsheet formula execution; exported files still contain internal information and require secure handling.
5. Limited 200-row administrative listing (filtered) and an all-record metadata CSV; large collections need pagination, exports with scoped access and archive retention.
6. No standalone approval resolution is inferred by an internal review state.

## Test and acceptance sequence

- [ ] On isolated staging, back up WP database and current plugin and verify single-plugin conflict guards.
- [ ] Run PHP 7.4/8.1/8.3 lint and CI structural checks.
- [ ] Run dbDelta on fresh install and v1.6.2 upgrade. Confirm existing `ino_documents` rows, IDs, versions, statuses and `document_url` are preserved; no checklist entries become filed documents.
- [ ] Review all 57 stable requirement codes. None should show as registered without a linked metadata record.
- [ ] Create draft for formation certificate, edit/return to draft, map requirement, record issuer/version/source.
- [ ] Attempt edit of published or in-review record — rejected; fresh reviewers and changed source require new workflow.
- [ ] Creator tries self-review — rejected. Reviewer tries review without source — rejected. Reviewer with source may mark internally reviewed.
- [ ] Publisher tries to publish restricted/confidential record — rejected. Public record requires a third distinct actor for publication.
- [ ] Public shortcode, anonymous and logged-out browser show only explicit published/public metadata; no source notes or restricted records.
- [ ] Archive published record and verify removal from public shortcode and retention in staff register and audit.
- [ ] Validate legacy Filed records are not automatically public, reviewed or treated as current authority.
- [ ] Test each document role alone, site admin, unauthorized member, direct admin-post crafted requests, CSRF nonce mismatch and row concurrency.
- [ ] Export CSV and verify correct field scope, escaping of HTML/text, formula injection attempts, encoding and restricted access.
- [ ] Test admin navigation, 320px/mobile responsiveness, keyboard input, color contrast and no-JavaScript fallbacks.
- [ ] Review semantics of actual INO documents individually; separate drafts, self-authored declarations, Connecticut filed records and externally issued evidence.
- [ ] Verify full WordPress on production-equivalent staging and obtain authorized institutional deployment approval before merge/promote.

**Compliance:** This system is an internal records-management tool. It does not independently create legal authority, tax exemption, governmental or tribal recognition, real-property ownership, financial authority, notarization or certified copies.
