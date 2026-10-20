# INO Identity, Ancestry & Peoplehood Registry — integration candidate

**Date:** 2026-10-20  
**Classification:** source-level staging candidate. Not live, adopted policy, governmental recognition or validated external ancestry.  
**Base:** stacked INO membership PR #23, which includes privacy PR #12 through the existing PR chain. Do not merge or deploy outside that sequence.

## Scope

- Adds four new tables under the WordPress database prefix: `ino_peoplehood_claims`, `ino_peoplehood_evidence`, `ino_peoplehood_consents`, `ino_peoplehood_events`.
- Preserves `ino_members`, `ino_identity_declarations`, `ino_family_relationships`, genealogy, governance, voting, BuddyPress, and existing pages without destructive conversion or automatic records.
- Private authenticated `[ino_peoplehood_registry]` and `[ino_peoplehood_profile]` aliases, with a new `/peoplehood-registry/` page created only if absent.
- Voluntary, separate self-description: civil citizenship, ancestry, cultural peoplehood, religious identity, external affiliation. The existing membership table remains sole membership source.
- Declaration versioning via `supersedes_id`: prior statements are retained. All new versions start unreviewed; consent does not transfer.
- Source metadata reference intake; **no unrestricted file uploads**, biometric processing, DNA inference, public peoplehood directory, civil status verification or automatic ancestral certification.
- Human-only administrator review requires rationale, forbids review of one's own entry, and requires a cited source before `record_examined`. Administrative review is not external genealogical proof.
- Consent and revocation are append-only events under `future_public_summary_only`. There is intentionally **no public projection**; recording consent never causes public release.
- Audit events store actor ID, claim ID, operation and UTC timestamp without duplicating sensitive narratives.
- All user claims, evidence and review notes are private and owner/admin scoped; no PII in querystrings or notification URLs.

## PR reconciliation

- **PR #12 privacy:** member public directory uses latest approved record and consent; private genealogy is not exposed. This module never infers consent from that directory's `public_consent` flag.
- **PR #23 membership:** preserve the read-only, account-scoped membership dashboard and roster; this module neither grants nor changes membership nor alters dashboard counts.
- **Genealogy v1.6.2:** preserve independently owned ancestor records and deceased-only sharing; no linkage to identity claims without a future consent-reviewed design.
- **Governance/ODIN:** no automatic publication, certificates, blockchain or "verified ancestry" transition. A later authorized bridge may index redacted, approved registry metadata only.

## Security and limits

- Login, owner checks, nonce and per-record SQL scope on all member actions; `manage_options` and submitter separation for review; source references are not externally authenticated.
- No API to return private claims publicly; a consented record remains private. Existing unrelated public member directory remains governed by membership's separate opt-in.
- New WordPress shortcode pages require a no-cache response and CDN bypass in hosted staging.
- Personal sensitive data should be minimized. A future archival evidence upload needs controlled, non-public storage, media validation, malware scanning, retention rules, and explicit legal review.
- Consent event history is preserved for audit; retention, subject-access, deletion and appeal procedures require adopted INO policy before production.
- Source-title and reference are supplied metadata, not verified originals. Internal `record_examined` means a reviewer documented inspection of submitted evidence; it is not a finding of legal or historical entitlement.

## Validation plan

1. Rebase/reconcile open ancestry privacy and membership PR chain. Ensure no shortcode/DB conflict with separate legacy INO Suite/Core packages.
2. PHP 7.4, 8.1, 8.3 lint and static contract checks. Run disposable WordPress + MariaDB + BuddyPress integration workflows, including `tests/integration/peoplehood-smoke.php`.
3. Reinstall the **exact** packaged ZIP on populated isolated database and assert older membership, declarations and governance audit history unchanged.
4. Exercise guest / Member A / Member B / administrator and submitter-admin access; cross-account record IDs, forged actor, wrong nonce, self review, duplicate review, review without evidence, revision and consent revocation.
5. Perform hosted WordPress staging: WPBakery, login state, CDN caching, migrations from site snapshot, backups and rollback, mobile accessibility, no private search or REST exposure.
6. Obtain appointed INO records-custodian/security sign-off and explicit governance approval before public release. No production deployment without acceptance evidence.

**Known limitations:** no binary evidence upload, appeal adjudication, public summary generation, data erasure implementation, stand-alone external status adjudication, membership issuance, or operational ODIN linkage. Preserve this staging status until separately implemented and verified.
