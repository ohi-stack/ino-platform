# INO Platform Voting & Consultation — v1.6.0-rc.1

## Status and scope

Production-target **candidate**, not approved for installation on the live IndigenousNations.org environment. The module is built on the canonical single-plugin release, not the historical INO Suite, and must not be concurrently activated with its incompatible plugin family.

This is a **nonbinding participation and consultation system**. It is not a statutory election service, electronic referendum authority, verified identity service, secret ballot implementation, or a replacement for the Indigenous Nation of Onegodia's authenticated Constitution, quorum, committee jurisdiction, governing-body approvals or formal adoption procedure.

### WordPress admin

`/wp-admin/admin.php?page=ino-platform-voting`

- Create a private **draft** poll about any topic; title, explanation, classification/category, electorate, ballot options and future start/end times.
- Choose single-answer or multi-answer up to a validated limit (2–20 options, 1–20 maximum selections).
- Restrict electorate to registered WordPress accounts, latest approved INO membership, or users with the existing INO governance-view capability.
- Define public aggregate results publication after close or administrator-only results.
- Edit a draft before opening; options, audience, rules and times become immutable when opened.
- Open a poll, close early, or cancel an unstarted draft. Expiration is enforced by current site-local time even if WordPress Cron does not run.
- Review real eligible participation counts and per-option aggregated selections, not manufactured turnout percentages.
- Export aggregate CSV from a privileged, nonce-protected admin action; protect against spreadsheet formula injection.
- View actor-attributed poll transitions and vote receipts without ballot selections in event descriptions.

### Frontend

- `/ino-voting/` — `[ino_voting]`: currently open poll cards, audience/closing time, nonce-protected ballot entry, one-vote notice, and eligibility reason.
- `/ino-vote-results/` — `[ino_vote_results]`: only closed/expired polls explicitly configured for public aggregate results, never unpublished drafts or individual voter identities.
- `/ino-my-votes/` — `[ino_my_votes]`: account-scoped participation receipts, **not** other voters' history or choices.

The INO navy, gold, cream, blue and responsive interface provides semantic fieldsets, linked labels, keyboard focus indicators, read-only HTML progress charts, compact mobile layout, and reduced-motion handling. An accessible no-JS ballot flow uses native WordPress POST and redirects.

### Storage and security model

**Five additional tables**, installed via dbDelta using WordPress site prefix:

- `ino_votes_polls`: immutable-on-opening poll rules, status, UTC-independent site-local time window and creators.
- `ino_votes_options`: published ballot choices, order and poll linkage.
- `ino_votes_ballots`: one row for each voting account, with UNIQUE(poll_id,voter_id).
- `ino_votes_choices`: one row per selected option and account ballot, UNIQUE(ballot_id,option_id).
- `ino_votes_events`: actor-attributed poll transitions and vote receipts, not ballot selections.

Voter identity is *not* anonymous: WordPress account ID and selected options are joined in the database, accessible to appropriately privileged database operators, despite public surfaces showing only aggregates. Never advertise this as a secret ballot or anonymous election.

Each authenticated vote checks a nonce, current electorate entitlement, active window, option membership and selection limits. The poll row is locked `FOR UPDATE`; inserting a ballot, choices and its audit record occurs under one database transaction. On any failure it rolls back. The unique ballot constraint prevents duplicate submissions for the **same WordPress account**; it does not guarantee that one human controls only one account. No IP/device fingerprinting is used.

Membership eligibility considers the *newest* INO membership record, not an obsolete approval. Private member participation does not require public-directory consent. This system does not alter underlying INO membership, BuddyPress profile data, officer roles, Phase 2 resolution statuses or published Phase 3 ODIN instruments.

### Explicitly unimplemented / deferred

- Legally validated INO officeholder elections, formal resolution enactment, authenticated council quorum and statutory election procedures.
- External identity proofing, individually verified unique people rather than WordPress user accounts, ranked-choice ballots, proxy/delegated voting, secrecy guarantees or cryptographic end-to-end verifiability.
- Public petition intake, guest voting, anonymous polling, ballot resubmission/editing, mobile app push, SMS/email campaigning, ballot scheduling notifications.
- Independent external audit of site operators' direct database access; the internal MySQL audit is *not* tamper-proof against a privileged DBA.

### Release acceptance

1. PHP 7.4/8.1/8.3 syntax matrix and source-contract checks pass at exact GitHub head.
2. Real disposable WordPress/PHP 8.2/MariaDB InnoDB tests provision the five tables and members with latest-row consent rules.
3. Admin-only creation/open/close and nonce rejection, frozen ballot edits, eligibility and invalid choice rejection.
4. Single- and multi-choice with over-selection denial; unique one-account/one-ballot and repeated request rejection.
5. Closed or elapsed time blocks voting; public results obey administrator-only vs after-close policy; private history is scoped.
6. Deliberately break the audit table during a vote to prove rollback leaves no orphan ballots/choices.
7. Reinstall the generated plugin ZIP into a WordPress database with synthetic ballots; verify counts, events, shortcodes and options remain intact.
8. Confirm no private account IDs or votes appear in public registry, search, REST, exported public HTML or cached page responses.
9. Hosted staging on IndigenousNations.org: reproduce with actual WordPress theme, BuddyPress, PHP/MariaDB, plugin set, role assignments, cache/CDN, backups and restore; inspect frontend keyboard and mobile flows.
10. Institutionally approve the voting policy: electorate categories, objection procedure, public result visibility, privacy notice, record retention, dispute process and acknowledgment of nonbinding status.

**Important:** The source archive may be reproducibly installable without being approved for production. Do not remove the existing Suite or bypass the production approval gate until site-specific migration and role acceptance are documented.
