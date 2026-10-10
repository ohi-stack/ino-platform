#!/usr/bin/env bash
# Build an installable, staging-only WordPress ZIP from the checked-out Git commit.
set -Eeuo pipefail
cd "$(dirname "$0")/.."

SOURCE_SHA="${GITHUB_SHA:-$(git rev-parse HEAD)}"
VERSION="$(sed -n 's/^ \* Version: //p' ino-platform.php | head -n1 | tr -d '\r')"
test -n "$VERSION"
grep -Fq "define('INO_PLATFORM_VERSION', '$VERSION')" ino-platform.php
test -s includes/class-ino-platform-activator.php
test -s includes/class-ino-platform-governance.php
test -s includes/class-ino-governance-odin.php

OUTPUT="$PWD/release/dist"
mkdir -p "$OUTPUT"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT
mkdir -p "$TMP/ino-platform"

# Preserve exactly one WordPress plugin directory and its runtime assets.
# Deliberately exclude repository internals, CI tests, and packaging scripts.
rsync -a --exclude='/.git/' --exclude='/.github/' --exclude='/.gitignore' \
  --exclude='/tests/' --exclude='/scripts/' --exclude='/screenshots/' \
  --exclude='/release/' --exclude='*.log' ./ "$TMP/ino-platform/"

test -f "$TMP/ino-platform/ino-platform.php"
test -d "$TMP/ino-platform/includes"
test -d "$TMP/ino-platform/assets"

SHORT_SHA="${SOURCE_SHA:0:12}"
ZIP_NAME="INO-Platform-Plugin-${VERSION}-staging-${SHORT_SHA}.zip"
(cd "$TMP" && zip -q -r "$OUTPUT/$ZIP_NAME" ino-platform)
unzip -t "$OUTPUT/$ZIP_NAME" > "$OUTPUT/zip-integrity.txt"
unzip -Z -1 "$OUTPUT/$ZIP_NAME" | grep -Fxq 'ino-platform/ino-platform.php'

# Reject any misplaced files outside the single expected plugin directory.
if unzip -Z -1 "$OUTPUT/$ZIP_NAME" | grep -Ev '^ino-platform/'; then
  echo 'Unexpected top-level ZIP entries.' >&2; exit 1
fi
sha256sum "$OUTPUT/$ZIP_NAME" > "$OUTPUT/SHA256SUMS"
cat > "$OUTPUT/RELEASE-MANIFEST.txt" <<EOF
Indigenous Nation of Onegodia — INO WordPress Staging Candidate
Status: Staging-only / Not approved for production
Plugin version: $VERSION
Source repository: ohi-stack/ino-platform
Source commit: $SOURCE_SHA
ZIP: $ZIP_NAME
Source base: 50379ee309a95a69fbd896e68c119cdd42d81a70 (validated PR #17 head)
Release chain: PR #12 -> #13 -> #14 -> #15 -> #16 -> #17
Important: artifact build does not establish hosted staging acceptance.
Backup WordPress database and existing plugin before any staging upgrade.
Do not install this alongside an overlapping INO Core/INO Suite plugin.
EOF
echo "Built $OUTPUT/$ZIP_NAME"
cat "$OUTPUT/SHA256SUMS"
