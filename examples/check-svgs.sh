#!/usr/bin/env sh
# Check every SVG in a theme or design-system folder before it ships.
#
#   sh examples/check-svgs.sh wp-content/themes/my-theme/assets/icons
#
# Exits non-zero when any file contains something SVG Safe would remove,
# so it can run in CI next to your other linters. Add --output="$f" to the
# wp command to fix the files in place instead.
set -eu

dir="${1:-.}"
status=0
for f in $(find "$dir" -type f \( -name '*.svg' -o -name '*.svgz' \)); do
  if ! wp svg-safe check "$f"; then
    status=1
  fi
done
exit "$status"
