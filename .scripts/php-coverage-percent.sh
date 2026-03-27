#!/usr/bin/env sh
set -eu

RAW_FILE="${1:-coverage-php.txt}"

if [ ! -f "$RAW_FILE" ]; then
  echo "ERROR: coverage output file not found: $RAW_FILE" >&2
  exit 1
fi

VALUE="$(sed 's/\x1B\[[0-9;]*[A-Za-z]//g' "$RAW_FILE" | awk '/^[[:space:]]*Lines:[[:space:]]+/ { gsub(/%/, "", $2); print $2; exit }')"

if [ -z "${VALUE:-}" ]; then
  echo "ERROR: Could not extract PHP Lines coverage percentage from ${RAW_FILE}" >&2
  exit 1
fi

echo "Global PHP coverage (Lines): ${VALUE}%"
