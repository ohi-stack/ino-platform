INO Platform Plugin — 1.5.2-rc.1
Requires: WordPress 6.5+, PHP 7.4+, transactional InnoDB-capable MySQL/MariaDB.

INSTALLABLE SINGLE WORDPRESS PLUGIN PACKAGE.
STATUS: Production-target candidate; actual hosted WordPress staging acceptance NOT established.

Included:
- Operational BuddyPress admin panel: diagnostics, real component checks, settings, alignment lookup, audit history.
- Permission and nonce protected bridge settings; consent-gated friend requests.
- No automatic public xProfile fields from private INO identity data.
- Updated INO Command Center UI, navy/gold branding, public portal and member dashboard.
- Privacy-gated member directory, authenticated self-record display and approved relationships.
- Constitution registry and internal governance authority structure.
- Recorded meetings, locked agendas, versioned minutes, evidence-backed outcomes.
- Restricted assignments, internal notices and audit logging.
- Source-linked ODIN document registry, history and public ID verification.
- Corrected dbDelta upgrade formatting, version-independent schema migration checks.
- Explicit Suite/Core installation collision and production approval safeguards.

Install in a fully backed-up, protected STAGING CLONE first.
Do not run concurrently with INO Platform Suite or legacy INO Core.
The full Suite membership application engine is NOT consolidated here.
No external-signature certification, legal adoption automation or email/SMS notices.

Production activation is disabled unless the authorized operator sets
define('INO_PLATFORM_PRODUCTION_APPROVED', true);
in wp-config.php AFTER written hosted staging acceptance and release review.

Governance recorded decisions are not legal adoption by themselves.
See README.md for workflows, exact release boundaries, rollback and testing.
