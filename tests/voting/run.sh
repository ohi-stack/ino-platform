#!/usr/bin/env bash
set -euo pipefail
: "${WP_ROOT:?Must use isolated WordPress root}"
RESULTS="voting-integration-results.txt"; : > "$RESULTS"
PASSED=0; DENIED=0
log(){ printf '%s\n' "$*" | tee -a "$RESULTS"; }
dispatch(){
  local actor="$1" mode="$2" op="$3" json="$4" nonce="${5:-valid}" encoded
  encoded="$(printf '%s' "$json" | base64 | tr -d '\n')"
  env INO_VOTE_USER="$actor" INO_VOTE_KIND="$mode" INO_VOTE_ACTION="$op" \
    INO_VOTE_FIELDS="$encoded" INO_VOTE_NONCE="$nonce" \
    wp eval-file tests/voting/dispatch.php --path="$WP_ROOT" --quiet
}
allow(){
  local name="$1" actor="$2" kind="$3" op="$4" data="$5" output
  if output="$(dispatch "$actor" "$kind" "$op" "$data" 2>&1)"; then
    log "PASS: $name"; PASSED=$((PASSED+1))
  else log "FAIL authorized $name :: $output"; exit 1; fi
}
reject(){
  local name="$1" actor="$2" kind="$3" op="$4" data="$5" nonce="${6:-valid}" output
  if output="$(dispatch "$actor" "$kind" "$op" "$data" "$nonce" 2>&1)"; then
    log "FAIL unexpected permission: $name :: $output"; exit 1
  else log "PASS rejected: $name"; DENIED=$((DENIED+1)); fi
}
check(){
  env INO_VOTE_ASSERT="$1" wp eval-file tests/voting/assert.php --path="$WP_ROOT" --quiet >> "$RESULTS"
  log "PASS WordPress assertion: $1"; PASSED=$((PASSED+1))
}
# Use actual runtime-relative dates, independent of the calendar month.
START="$(date -u -d 'yesterday' '+%Y-%m-%dT%H:%M')"
END="$(date -u -d 'tomorrow' '+%Y-%m-%dT%H:%M')"
admin1="$(printf '{"title":"Approve a new garden","description":"Synthetic, nonbinding community consultation only.","category":"Community","electorate":"approved_members","ballot_type":"single","max_choices":1,"results_policy":"after_close","opens_at":"%s","closes_at":"%s","option_lines":"Yes\nNo"}' "$START" "$END")"
admin2="$(printf '{"title":"Which services first","description":"Nonbinding multiple-choice test.","category":"Services","electorate":"registered","ballot_type":"multiple","max_choices":2,"results_policy":"admins_only","opens_at":"%s","closes_at":"%s","option_lines":"Housing\nEducation\nTransport"}' "$START" "$END")"
admin3="$(printf '{"title":"Audit rollback test","description":"Transient poll used to test write failures.","category":"Testing","electorate":"registered","ballot_type":"single","max_choices":1,"results_policy":"admins_only","opens_at":"%s","closes_at":"%s","option_lines":"Accept\nReject"}' "$START" "$END")"
# printf '%b' not used; encode literal newlines as escaped JSON sequences.
admin1="$(printf '%s' "$admin1" | python3 -c 'import sys; x=sys.stdin.read(); print(x.replace("\"option_lines\":\"Yes\nNo\"", "\"option_lines\":\"Yes\\\\nNo\""))')"
admin2="$(printf '%s' "$admin2" | python3 -c 'import sys; x=sys.stdin.read(); print(x.replace("\"option_lines\":\"Housing\nEducation\nTransport\"", "\"option_lines\":\"Housing\\\\nEducation\\\\nTransport\""))')"
admin3="$(printf '%s' "$admin3" | python3 -c 'import sys; x=sys.stdin.read(); print(x.replace("\"option_lines\":\"Accept\nReject\"", "\"option_lines\":\"Accept\\\\nReject\""))')"
wp eval-file tests/voting/seed.php --path="$WP_ROOT" --quiet
check baseline
reject 'ordinary user cannot create poll' ino_stage_outsider manage create "$admin1"
reject 'missing administration nonce' admin_test manage create "$admin1" missing
reject 'invalid administration nonce' admin_test manage create "$admin1" invalid
allow 'admin creates draft' admin_test manage create "$admin1"
check draft
reject 'cannot submit before opening' ino_stage_viewer cast cast '{"poll_id":1,"selected":[1]}'
allow 'admin opens poll' admin_test manage open '{"poll_id":1}'
check open
reject 'ineligible member cannot participate' ino_stage_outsider cast cast '{"poll_id":1,"selected":[1]}'
reject 'forged option from another poll rejected' ino_stage_viewer cast cast '{"poll_id":1,"selected":[99999]}'
reject 'missing ballot nonce rejected' ino_stage_viewer cast cast '{"poll_id":1,"selected":[1]}' missing
reject 'too many options for single-choice ballot rejected' ino_stage_viewer cast cast '{"poll_id":1,"selected":[1,2]}'
reject 'opened ballot options cannot be edited' admin_test manage edit "$admin1"
allow 'approved member casts private ballot' ino_stage_viewer cast cast '{"poll_id":1,"selected":[1]}'
reject 'one-account one-ballot enforced' ino_stage_viewer cast cast '{"poll_id":1,"selected":[2]}'
check ballot
allow 'admin closes poll' admin_test manage close '{"poll_id":1}'
reject 'closed poll rejects submission' ino_stage_outsider cast cast '{"poll_id":1,"selected":[2]}'
check closed
allow 'admin creates multi-choice poll with private results' admin_test manage create "$admin2"
allow 'admin opens multiple-choice poll' admin_test manage open '{"poll_id":2}'
reject 'limit multiple-choice selections' ino_stage_outsider cast cast '{"poll_id":2,"selected":[3,4,5]}'
allow 'registered account casts two-option ballot' ino_stage_outsider cast cast '{"poll_id":2,"selected":[3,5]}'
reject 'same registered account cannot submit again' ino_stage_outsider cast cast '{"poll_id":2,"selected":[4]}'
allow 'admin closes restricted-results ballot' admin_test manage close '{"poll_id":2}'
check multiple
allow 'admin creates audit-failure ballot' admin_test manage create "$admin3"
allow 'admin opens audit-failure ballot' admin_test manage open '{"poll_id":3}'
PREFIX="$(wp db prefix --path="$WP_ROOT")"
wp db query "RENAME TABLE ${PREFIX}ino_votes_events TO ${PREFIX}ino_votes_events_offline" --path="$WP_ROOT" --quiet
reject 'unavailable audit storage rolls back ballot transaction' ino_stage_viewer cast cast '{"poll_id":3,"selected":[6]}'
wp db query "RENAME TABLE ${PREFIX}ino_votes_events_offline TO ${PREFIX}ino_votes_events" --path="$WP_ROOT" --quiet
check audit
log "FINAL PASS: $PASSED authorized/data assertions and $DENIED expected denials"
