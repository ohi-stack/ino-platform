#!/usr/bin/env bash
set -euo pipefail
: "${WP_ROOT:?Set WP_ROOT to the temporary WordPress installation path}"
RESULTS="staging-governance-results.txt"; : > "$RESULTS"
PASS=0; REJECTED=0
log(){ echo "$*" | tee -a "$RESULTS"; }
call(){
  local who="$1" op="$2" fields="$3" nonce="${4:-valid}"
  local b64="$(printf '%s' "$fields" | base64 | tr -d '\n')"
  env INO_CASE_USER="$who" INO_CASE_OP="$op" INO_CASE_FIELDS="$b64" INO_CASE_NONCE="$nonce" \
    wp eval-file tests/staging/dispatch.php --path="$WP_ROOT" --quiet
}
ok(){
  local name="$1" who="$2" op="$3" json="$4"
  local out
  if out="$(call "$who" "$op" "$json" 2>&1)"; then
    log "PASS authorized: $name"; PASS=$((PASS+1))
  else log "FAIL authorized: $name :: $out"; exit 1; fi
}
deny(){
  local name="$1" who="$2" op="$3" json="$4" nonce="${5:-valid}"
  local out
  if out="$(call "$who" "$op" "$json" "$nonce" 2>&1)"; then
    log "FAIL expected denial: $name :: $out"; exit 1
  else log "PASS rejected: $name"; REJECTED=$((REJECTED+1)); fi
}
dbtest(){
  env INO_ASSERT="$1" wp eval-file tests/staging/assert.php --path="$WP_ROOT" --quiet >> "$RESULTS"
  log "PASS WordPress database: $1"; PASS=$((PASS+1))
}
log "INO Phase 2: isolated real WordPress + MariaDB integration test"
wp eval-file tests/staging/seed.php --path="$WP_ROOT" --quiet
dbtest baseline
BASE='{"title":"Training meeting","meeting_at":"2026-10-01T09:00","source_ref":"TEST-RULE-NOT-ADOPTED"}'
deny "anonymous role denied" outsider create_meeting "$BASE"
deny "reviewer cannot draft" reviewer create_meeting "$BASE"
deny "missing nonce" records create_meeting "$BASE" missing
deny "invalid nonce" records create_meeting "$BASE" invalid
dbtest baseline
ok "meeting drafted" records create_meeting "$BASE"
dbtest draft
deny "schedule without agenda" records schedule_meeting '{"meeting_id":1}'
ok "agenda added" records add_agenda '{"meeting_id":1,"sequence_no":1,"topic":"Mock proposal","briefing":"Synthetic briefing only"}'
dbtest agenda
ok "meeting scheduled" records schedule_meeting '{"meeting_id":1}'
dbtest scheduled
deny "agenda locked after scheduling" records add_agenda '{"meeting_id":1,"sequence_no":2,"topic":"Late change"}'
dbtest locked
deny "zero attendance rejected" records hold_meeting '{"meeting_id":1,"attendance_count":0,"attendance_evidence":"Mock attendance document"}'
ok "held meeting with attendance" records hold_meeting '{"meeting_id":1,"attendance_count":3,"attendance_evidence":"Mock meeting attendance ledger, not verified quorum"}'
dbtest held
deny "short minutes rejected" records submit_minutes '{"meeting_id":1,"source_ref":"MOCK-01","minutes_text":"Short"}'
ok "minutes revision 1 submitted" records submit_minutes '{"meeting_id":1,"source_ref":"MOCK-01","minutes_text":"Test council met with three training participants and discussed the synthetic motion; this is not an adopted record."}'
dbtest minutes_v1
wp user add-cap ino_stage_records ino_governance_review --path="$WP_ROOT" --quiet
deny "minutes author cannot self-review" records review_minutes '{"minutes_id":1,"review_note":"Author attempts improper self-review."}'
wp user remove-cap ino_stage_records ino_governance_review --path="$WP_ROOT" --quiet
ok "minutes independently reviewed" reviewer review_minutes '{"minutes_id":1,"review_note":"Reviewer assessed the mock source material independently."}'
dbtest minutes_reviewed
ok "draft meeting-linked resolution" records create_resolution '{"title":"Training motion","meeting_id":1,"authority_ref":"DRAFT-RULE","motion_text":"This is solely a synthetic training motion with no official validity."}'
wp user add-cap ino_stage_records ino_governance_review --path="$WP_ROOT" --quiet
deny "motion author cannot self-review" records review_resolution '{"resolution_id":1,"review_note":"Attempted author review despite role capability."}'
wp user remove-cap ino_stage_records ino_governance_review --path="$WP_ROOT" --quiet
ok "motion independently reviewed" reviewer review_resolution '{"resolution_id":1,"review_note":"Motion linked to recorded meeting with source citation."}'
dbtest resolution_reviewed
ok "minutes revision 2 submitted" records submit_minutes '{"meeting_id":1,"source_ref":"MOCK-02","minutes_text":"Revised minutes after corrections to synthetic attendance notes. This report does not certify quorum or adoption."}'
dbtest minutes_v2
VOTE='{"resolution_id":1,"outcome":"carried","votes_for":2,"votes_against":1,"abstentions":0,"quorum_evidence":"Mock source and attendance assessment, not legal quorum validation.","decision_evidence":"MOCK-VOTE-001","decision_attestation":"1"}'
deny "cannot decide before newest minutes reviewed" publisher record_decision "$VOTE"
ok "newest minutes independently reviewed" reviewer review_minutes '{"minutes_id":2,"review_note":"Reviewed the corrected minute version independently."}'
dbtest minutes_v2_reviewed
deny "unauthorized outcome recorder" outsider record_decision "$VOTE"
deny "vote tally above attendance" publisher record_decision '{"resolution_id":1,"outcome":"carried","votes_for":4,"votes_against":0,"abstentions":0,"quorum_evidence":"Mock quorum evidence, not a valid legal determination.","decision_evidence":"MOCK-VOTE-001","decision_attestation":"1"}'
deny "negative vote tally" publisher record_decision '{"resolution_id":1,"outcome":"carried","votes_for":-1,"votes_against":1,"abstentions":0,"quorum_evidence":"Mock quorum evidence, not a valid legal determination.","decision_evidence":"MOCK-VOTE-001","decision_attestation":"1"}'
deny "explicit attestation required" publisher record_decision '{"resolution_id":1,"outcome":"carried","votes_for":2,"votes_against":1,"abstentions":0,"quorum_evidence":"Mock quorum evidence, not a valid legal determination.","decision_evidence":"MOCK-VOTE-001"}'
ok "record evidence-backed reported outcome" publisher record_decision "$VOTE"
dbtest outcome
deny "decision is not replayable" publisher record_decision "$VOTE"
OUTSIDER="$(wp user get ino_stage_outsider --field=ID --path="$WP_ROOT" --quiet)"
VIEWER="$(wp user get ino_stage_viewer --field=ID --path="$WP_ROOT" --quiet)"
deny "outsider cannot receive governance task" records create_task "{\"source_type\":\"resolution\",\"source_id\":1,\"title\":\"Illegal assignment\",\"assignee_id\":$OUTSIDER}"
ok "assigned task creates private notification" records create_task "{\"source_type\":\"resolution\",\"source_id\":1,\"title\":\"Archive synthetic resolution\",\"assignee_id\":$VIEWER,\"details\":\"Mock task restricted to governance staff\"}"
dbtest assignment
deny "other user cannot complete assignment" outsider complete_task '{"task_id":1,"completion_note":"Unauthorized completion evidence"}'
ok "assignee completes with evidence" viewer complete_task '{"task_id":1,"completion_note":"Archive action completed in staging with mock evidence."}'
dbtest completed
deny "other user cannot read private notice" outsider read_notice '{"notice_id":1}'
ok "recipient marks own notice read" viewer read_notice '{"notice_id":1}'
dbtest notice_read
dbtest privacy
dbtest audit
PREFIX="$(wp db prefix --path="$WP_ROOT" --quiet)"
wp db query "RENAME TABLE ${PREFIX}ino_gov_ops_events TO ${PREFIX}ino_gov_ops_events_offline" --path="$WP_ROOT" --quiet
deny "audit table outage forces rollback" records create_meeting '{"title":"Must roll back","meeting_at":"2026-10-01T10:00","source_ref":"ROLLBACK-TEST"}'
wp db query "RENAME TABLE ${PREFIX}ino_gov_ops_events_offline TO ${PREFIX}ino_gov_ops_events" --path="$WP_ROOT" --quiet
dbtest audit
log "FINAL PASS: $PASS verified data/authorized steps; $REJECTED expected authorization/contract denials."
