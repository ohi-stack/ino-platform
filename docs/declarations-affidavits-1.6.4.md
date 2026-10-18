# INO Declarations & Affidavits — Public Records Center v1.6.4-rc.1

**Originator:** One Gregory Onegodian™  
**Institution:** Indigenous Nation of Onegodia (INO)  
**Public page:** `/documents/affidavits-and-declarations/`  
**Shortcodes:** `[ino_declarations_affidavits]` and `[ino_declarations_directory]`  
**Parent branch:** `feat/ino-documents-registry-20261018` (unmerged staging candidate)  
**Status:** DRAFT / not deployed and not approved for production.

## Source basis

Content and classification follow the existing INO *Affidavits & Declarations* page draft in **INO Legal Document Header.txt**:
- Affidavit = written statement sworn or affirmed before a person legally authorized to administer an oath or affirmation.
- Declaration = written statement by a person or organizational representative; specific legal effect depends on applicable law.
- Attestation = confirmation of a fact, event, relationship, activity or record.
- Six INO classifications: Identity & Personal; Ancestry & Heritage; Religious & Ecclesiastical; Land & Conservation; Governance & Institutional; Witness & Historical Testimony.
- Do not confuse an internal INO identifier with a notarization, court disposition, real-property title, tribal recognition, or governmental status.
- Do not automatically make family identity, property dealings, private witnesses or other personal testimony public.

## Implementation in this candidate

1. A responsive navy/black, purple-and-gold institutional page with intro, instrument distinctions, six category cards, review workflow, and final documents/legal notice.
2. Published-record directory with a document-type filter and a genuine empty state.
3. Additive `ino_documents` metadata columns: `declaration_kind`, `declaration_area`, `statement_basis`, `execution_method`, `oath_reference`, and `public_listing` (default 0). The privately stored oath source reference is never returned to public visitors.
4. Six new "Declarations & Affidavits" document-requirement types appended to the *end* of the existing required-documents catalog. Existing requirement identifiers are preserved.
5. Separate administrator fields to record instrument type, subject, statement basis, reported signature/oath execution, private jurat/oath evidence reference, and explicit public-directory opt-in.
6. Publication requires `status=published`, `confidentiality=public`, `public_listing=1`, and a known declaration type.
7. Affidavit internal review **additionally requires** `execution_method=sworn_affirmed`, a nonempty protected `oath_reference`, a nonempty source reference, and an independent reviewer. Publication also requires a separate publisher.
8. The public record directory displays only title, INO reference, category, statement basis, version and execution date when recorded. No evidence file download, sworn official name, source file, family details or raw private testimony is exposed.
9. Provision WordPress `/documents/` parent and `/documents/affidavits-and-declarations/` child if absent, without overwriting existing pages. If the path already exists, insert the shortcode in that preexisting page manually after staging review.

## Differences between evidence and authority

- Entering "oath / affirmation reported" is a contributor-supplied administrative assertion; a human reviewer must evaluate the actual authorized oath evidence independently.
- "Internally reviewed" means that an authorized INO reviewer reviewed the source. It does **not** establish a valid notarization, judicial or governmental acceptance, formal tribal enrollment, property title, immunity or external recognition.
- An affidavit missing oath/affirmation evidence must not be reviewed/published under the affidavit label; use an unsigned declaration or witness account classification where factually accurate.
- INO internal statements should not be presented as decisions by another government or nation.
- The page does not accept anonymous uploads or solicit personally sensitive statements via a public web form.

## Staging acceptance gates

- [ ] Fresh WordPress install and upgrade preserve all existing `ino_documents` rows and the first 57 requirement IDs; six new requirements are appended.
- [ ] `/documents/` and `/documents/affidavits-and-declarations/` created only if absent. Existing page content remains untouched; shortcode resolves.
- [ ] Public anonymous page renders six categories and no approved documents when none are published; no invented examples.
- [ ] Source-only draft, "Legacy Filed", restricted, confidential, in-review and archived metadata cannot appear in the public directory even with URL filter tampering.
- [ ] Publisher cannot expose a document without author-requested public listing or `confidentiality=public`.
- [ ] Forged POST `execution_method=sworn_affirmed` without proper `oath_reference` cannot advance an affidavit through review.
- [ ] Creator cannot self-review; reviewer cannot self-publish; action nonce and permissions are enforced.
- [ ] Test all six subject groups, four instrument kinds, six statement bases, filters and UTF-8 escaping.
- [ ] Changing an existing draft resets review and requires fresh approval. Editing a published record remains forbidden.
- [ ] Archive a published declaration and verify that the public projection stops showing it.
- [ ] Check mobile 320px, keyboard navigation, reduced motion, WPBakery and no-JS operation.
- [ ] Verify no PII, document URLs, source reference, oath reference, phone/email, or private identity details leak through public HTML, REST, cache or archive exports.
- [ ] Run PHP lint on PHP 7.4, 8.1 and 8.3 plus a production-equivalent WordPress staging test with distinct creator/reviewer/publisher accounts.
- [ ] Approve full dependency stack before merge and deployment.

## Exclusions

No signed-document upload, protected PDF, oath official credential verification, e-notary integration, legally effective signature service, automatic court filing, external validation, public submission form, or certificate of recognition. Those are future work, and their absence must not be mistaken for production readiness.
