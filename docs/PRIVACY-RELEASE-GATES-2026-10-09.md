# INO Platform privacy and authority acceptance gates

This patch addresses immediate privacy and authority defects in the current v1.2.0 source. It is not the complete membership workflow.

## Expected behaviors
- Public directory lists only approved records with explicit public consent and a linked WordPress user.
- Public member profile is inaccessible without that approved consent record.
- Public profiles do not disclose membership numbers, lineage, homeland, or ancestry fields merely because directory consent exists.
- Member sees only own private family-tree records, and only approved relationships; administrators may review others.
- Anonymous viewers cannot access any family tree.
- Members cannot change their membership class or membership number through WordPress profile editing.
- Member, volunteer, and program manager roles cannot upload arbitrary public Media Library files; program managers cannot edit normal public posts by default.
- Identity declarations receive Self-declared evidence classification regardless of client-submitted values.

## Required manual tests
1. Anonymous: member directory empty when no approved public consents.
2. Anonymous: private member-profile URL does not reveal name or profile fields.
3. Anonymous: family-tree shortcode with arbitrary user_id shows access notice.
4. Member A: can see own approved family relationships but not Member B's relationships.
5. Member A: POST a crafted ino_membership_number change to profile; value remains unchanged.
6. Member A: submit identity declaration with crafted verification_status=Document-supported; stored value is Self-declared.
7. Admin: can update protected membership fields; separately review audit history.
8. Upgraded installation: legacy upload_files/edit_posts caps removed.
9. BuddyPress profile: embedded family tree does not disclose another member's relationships.
10. Run php -l on changed files, then test on clean and upgraded WordPress 6.5+.

## Remaining blockers
Application intake and approvals; unique member-number authority; certificate issuance and revocation; protected uploads; record-level RBAC; audit trail; Forms Bridge delivery; privacy export/erasure; versioned migrations; staging authentication and end-to-end acceptance; admin placeholders.

Do not describe the plugin as operationally complete until tests pass and human signoff is recorded.
