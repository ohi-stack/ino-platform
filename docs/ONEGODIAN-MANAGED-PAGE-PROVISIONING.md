# OneGodian Managed Page Provisioning Standard

Status: Required architecture contract
Updated: 2026-10-05

## Governing rule

A production OneGodian WordPress plugin must not require an administrator to manually construct required shortcode, form, dashboard, portal, directory, registry, or workflow pages.

Required application surfaces must be declared in a versioned page manifest and automatically provisioned, mapped, reconciled, repairable, and production-verifiable.

## Required manifest fields

Each managed page record must define:

- page_key: permanent plugin-scoped identifier
- title
- slug
- shortcode or render contract
- page_type: public, dashboard, form, portal, directory, detail, or admin-linked
- parent/page hierarchy
- access policy
- menu/navigation policy
- post status
- template/layout
- required flag
- introduced/manifest version
- resolved WordPress page_id

## Provisioning lifecycle

Activation and eligible upgrades must:

1. load the plugin page manifest;
2. locate existing pages by permanent page metadata first, stored page ID second, and canonical slug only as a controlled fallback;
3. create missing required pages;
4. install the registered shortcode/form content;
5. establish parent/child hierarchy;
6. apply plugin-owned template, access, and navigation metadata;
7. store resolved page IDs;
8. verify that the declared shortcode/render contract is actually registered before publishing a managed page;
9. record provisioning/repair results;
10. expose health/status for missing, connected, and conflicting pages.

The process must be idempotent. Re-activation or upgrade must not create duplicate pages.

## Upgrade reconciliation

On manifest-version change:

- add newly required pages;
- migrate changed managed routes safely;
- preserve legitimate editor-authored content;
- repair plugin-controlled shortcode assignments only when the manifest owns them;
- flag deprecated pages instead of silently deleting them;
- refresh page-ID mappings and health state.

## Deactivation and uninstall

Deactivation must not delete pages or user/domain records.

Destructive removal is permitted only through an explicit uninstall/purge path with appropriate authorization.

## Page-generating versus embeddable shortcodes

Only primary application surfaces automatically create pages. Examples include dashboards, forms, portals, directories, registries, application workflows, account surfaces, and major user destinations.

Embeddable UI shortcodes such as buttons, badges, balances, profile cards, galleries, search boxes, status indicators, and other components do not independently require pages unless the plugin's own manifest explicitly promotes them to managed pages.

## Source-of-truth rule

The page provisioner creates presentation/application surfaces only. It must not transfer domain authority between systems. Members remains membership/profile authority; University remains learning authority; ODeFi remains financial application authority; ODIN remains registry authority; QR-V/OBP-1 remains verification/provenance authority; api.OneGodian.org remains the shared integration/service layer.

## Required admin controls

Substantial WordPress plugins should expose a Pages & Interfaces screen with:

- Required / Installed / Connected / Missing / Conflict counts
- View Page
- Edit Page
- Repair
- Recreate Missing Pages
- Sync Manifest
- Rebuild Navigation where supported
- Run Verification
- Production Check

## Implementation rule

Existing registered shortcodes are authoritative during retrofit. Proposed names must not be silently introduced as replacements. Inventory actual registrations first, map those real shortcodes into the manifest, then add new shortcodes only as explicit versioned product changes.

## Production gate

Managed-page provisioning is production-complete only when activation, upgrade reconciliation, duplicate prevention, shortcode registration verification, repair, deactivation preservation, and uninstall behavior are tested and documented.
