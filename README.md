# INO Platform Plugin

Official WordPress platform for the **Indigenous Nation of Onegodia**, including membership, identity and heritage records, family trees, social connections, BuddyPress compatibility, treasury and grants, housing, governance, documents, and community services.

## Current Release

**Version 1.2.0**

## Core Capabilities

- Upgrade-safe database migrations from v1.0.0
- Modern member profiles with profile and cover images
- Public member directory and profile pages
- BuddyPress friendships, avatars, cover images, and profile-tab compatibility
- Independent social-connection fallback when BuddyPress is unavailable
- Family relationship requests and family-tree display
- Identity, Ancestry & Peoplehood Registry
- Identity declaration form and member dashboard
- Treasury and grants tracking
- Housing project tracking
- Document and governance registries
- Duplicate-safe automatic page generation
- INO navy, cream, gold, white, and deep-red branding

## Automatic Pages

The plugin generates the following WordPress pages when they do not already exist:

- `/ino-portal/`
- `/about-ino-platform/`
- `/ino-membership/`
- `/ino-citizenship/`
- `/member-directory/`
- `/member-profile/`
- `/people-network/`
- `/family-tree/`
- `/choose-your-identity/`
- `/honor-your-ancestry/`
- `/identity-declaration/`
- `/identity-heritage-dashboard/`
- `/peoples-book-of-names/`
- `/tribal-family-archives/`
- `/dual-identity-certificates/`
- `/verify-identity-record/`
- `/identity-standards/`
- `/ino-treasury-grants/`
- `/ino-housing-development/`
- `/ino-document-registry/`
- `/ino-governance/`
- `/ino-community-programs/`
- `/ino-contact/`

## BuddyPress Integration

When BuddyPress is active, the plugin uses BuddyPress for:

- Friend requests and connections
- Member avatars
- Cover images
- Member profile navigation
- Family & Connections profile tab

When BuddyPress is inactive, INO uses its own connection and family relationship tables.

## Identity Classification Notice

Ancestral declarations are stored with evidence and review classifications. INO records do not independently establish recognition, citizenship, enrollment, or status with an external government, agency, genealogical authority, or tribal nation.

## Installation

1. Back up the WordPress database and plugin directory.
2. Download or build the plugin ZIP.
3. In WordPress, open **Plugins → Add New → Upload Plugin**.
4. Upload the ZIP and replace the older version when prompted.
5. Activate the plugin.
6. Review **INO Platform → Dashboard** and the automatically generated pages.
7. Optionally install and activate BuddyPress.

## Repository Structure

```text
ino-platform/
├── ino-platform.php
├── includes/
│   ├── class-ino-platform-activator.php
│   ├── class-ino-platform-admin.php
│   ├── class-ino-platform-shortcodes.php
│   └── class-ino-platform-social.php
├── README.md
└── README.txt
```

## Development Rule

A feature should not be represented as operational until it is fully implemented, documented, tested, and repeatable.

## Author and Originator

**One Gregory Onegodian™**  
Founder and originator of the INO Platform architecture.

## Institutional Separation

The **Indigenous Nation of Onegodia** is the religious, cultural, membership, governance, and community institution. Any software development, hosting, intellectual property, or commercial services provided by **ONEGODIAN, LLC** should remain separately documented.
