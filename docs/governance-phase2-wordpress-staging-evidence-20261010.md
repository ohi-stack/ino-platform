# INO Governance — WordPress Meeting-to-Decision Integration Evidence

**Date:** 2026-10-10 (Gregorian)  
**Branch:** `feat/ino-wp-staging-workflow-20261010`  
**Parent:** Phase 3 ODIN PR #16, stacked on Phase 2 PR #15 and Phase 1 PR #14  
**Scope:** Phase 2 governance workflow, consent/authorization, in-app notifications and MySQL audit integrity.  
**Environment:** Fresh disposable WordPress installation, WP-CLI, PHP 8.2, MariaDB 10.11 InnoDB, synthetic officers and synthetic meetings. **Not the hosted IndigenousNations.org staging site.**

## CI evidence

- [Successful end-to-end WordPress integration run 38053036896](https://github.com/ohi-stack/ino-platform/actions/runs/38053036896)
- Exact-head workflow file: `.github/workflows/ino-governance-wordpress-staging.yml`
- Test orchestration: `tests/staging/run.sh`
- Authenticated admin-post test dispatcher: `tests/staging/dispatch.php`
- Real MySQL and WordPress assertions: `tests/staging/assert.php`
- Synthetic accounts and transactional table checks: `tests/staging/seed.php`
- Result: **33 authorized actions/database assertions passed; 20 expected permission/contract denials passed.**
- Workflow artifact: `staging-governance-results.txt`, uploaded by the run.

## Observed workflow

1. Verified fresh plugin activation/migration created all seven Phase 2 tables using **InnoDB**.
2. Tested visitor-like account, restricted records officer, independent reviewer, independent publisher, private-notice recipient and administrative authority boundaries. Verified WordPress site administrator does not automatically gain governance review/publication authority.
3. Rejected missing and invalid WordPress action nonces.
4. Created one synthetic meeting draft. Scheduling before a recorded agenda was rejected.
5. Inserted initial agenda, scheduled meeting, and verified the agenda becomes locked. A post-scheduling agenda insert was rejected.
6. Rejected zero attendance; recorded a past meeting as held with attendance count 3 and an evidence reference.
7. Rejected incomplete minutes; submitted revision one, rejected author self-review even after temporarily assigning the review capability, and accepted review by a separate reviewer.
8. Created a meeting-linked draft resolution, rejected author self-review, then independently reviewed it.
9. Submitted second minutes revision. Prevented the publisher from recording a decision until the **latest** minutes were independently reviewed; verified revision one remained intact.
10. Rejected unauthorized recorder, totals exceeding meeting attendance, negative vote counts and missing attestation. Accepted one evidence-backed *reported* outcome: `carried`, 2 votes for, 1 against.
11. Confirmed outcome status `outcome_recorded`, not `adopted`, and no Phase 1 governance record was automatically published.
12. Prevented duplicate recording of the same reported decision.
13. Rejected task assignment to user lacking governance access. Assigned one task to an authorized officer, creating exactly one private notification in the same transaction.
14. Rejected a different user's task completion and notification-read attempts. Assignee completed with evidence and recipient marked the notice read.
15. Verified notification-read is now included as an actor-attributed governance audit event.
16. Verified public/unprivileged `[ino_governance_operations]` output contains no private meeting minutes or workflow contents.
17. Inspected chronological audit events for all successful operations with nonempty actor identifiers and no extra events from denied operations.
18. Deliberately made the audit table temporarily unavailable in this isolated database; rejected a new meeting creation, restored the audit table and confirmed that the meeting insert was **rolled back** and the audit ledger remained intact.

## Corrections made during verification

- Corrected the staging harness's database-prefix lookup; the first run passed all prior assertions but failed to target the prefixed audit table during deliberate outage simulation.
- Updated `INO_Governance_Operations::read_notice()` so the recipient-only read-state change and its audit event are transactionally committed together.
- Strengthened database assertions to detect orphan meetings/tasks after expected denials and an audit outage.

## Known limits / remaining acceptance

This is a **disposable real WordPress integration environment**, not a hosted site deployment. No WordPress browser/UI accessibility QA was performed. No production credentials, genuine officer appointments, signed governing Constitution, actual council votes, legally recognized quorum, or private member content was used.

Before a production promotion:
1. Connect or provide authenticated **actual INO staging** access and deploy the exact stacked candidate to that *non-production* environment.
2. Re-run the suite against the actual host’s PHP version, MariaDB/MySQL engine, WordPress theme/WPBakery combination, and existing plugin set (especially previously noted shortcode collisions).
3. Test browser forms, cookie authentication, cross-user authorization, nonces, responsive layout, REST/search/caching privacy and rollback behavior without altering production.
4. Perform upgraded-site migration with a backup/restore rehearsal and existing governance records.
5. Verify delegated officer appointments, authorized approval procedures, quorum/voting requirements, and controlling constitutional documents against signed source evidence.
6. Do not present `outcome_recorded` as a legally adopted resolution; formal adoption remains outside this implementation.
7. No email/SMS notifications are provided; the verified notification is an **internal WordPress database inbox** only.

**Status:** Isolated WordPress database integration = **PASS**. Hosted WordPress staging / production = **UNVERIFIED**.
