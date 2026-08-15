#!/usr/bin/env bash
# shellcheck disable=SC2016
set -euo pipefail

root=$(cd "$(dirname "$0")/.." && pwd)
version=$(php -r '$s=file_get_contents($argv[1]); if (!preg_match("/^[ \t*#\/]*Version:\s*([^\r\n]+)/mi", $s, $m)) { exit(2); } echo trim($m[1]);' "$root/wp-chinese-converter.php")
out=${1:-"$root/dist/wp-chinese-converter-$version.zip"}
epoch=${SOURCE_DATE_EPOCH:-$(git -C "$root" log -1 --format=%ct)}

case "$epoch" in
  ''|*[!0-9]*) echo "SOURCE_DATE_EPOCH must be an integer" >&2; exit 2 ;;
esac
# The ZIP format cannot represent dates before 1980-01-01.
if [ "$epoch" -lt 315532800 ]; then
  echo "SOURCE_DATE_EPOCH must be at least 315532800" >&2
  exit 2
fi

work=$(mktemp -d)
cleanup() { find "$work" -depth -delete; }
trap cleanup EXIT
mkdir -p "$work/source/wp-chinese-converter" "$work/package" "$(dirname "$out")"

npm --prefix "$root" run check:build
rsync -a --exclude=.git --exclude='/dist/' --exclude='/node_modules/' --exclude='/vendor/' \
  "$root/" "$work/source/wp-chinese-converter/"
(
  cd "$work/source/wp-chinese-converter"
  composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
)

# Keep runtime code, parsed OpenCC dictionaries and license texts. Remove only
# upstream development, benchmark and documentation material.
find "$work/source/wp-chinese-converter/vendor" -type d \
  \( -name .github -o -name tests -o -name Tests -o -name benchmark -o -name benchmarks \) \
  -prune -exec find {} -depth -delete \;
find "$work/source/wp-chinese-converter/vendor" -type f \
  \( -iname 'README*' -o -iname 'CHANGELOG*' -o -name 'composer.json' -o -name 'psalm.xml*' \) \
  -delete
find "$work/source/wp-chinese-converter/vendor/overtrue/php-opencc/data/dictionary" -depth -delete

rsync -a --exclude-from="$root/.distignore" \
  "$work/source/wp-chinese-converter/" "$work/package/wp-chinese-converter/"
test -f "$work/package/wp-chinese-converter/assets/dist/wpcc-variant.umd.js"
test -f "$work/package/wp-chinese-converter/includes/blocks/build/language-switcher/index.js"

# Normalize metadata before archiving. Sorting and zip -X remove filesystem and
# host-specific ordering/extra-field differences.
find "$work/package" -type d -exec chmod 0755 {} +
find "$work/package" -type f -exec chmod 0644 {} +
TZ=UTC find "$work/package" -exec touch -h -d "@$epoch" {} +
archive="$work/wp-chinese-converter.zip"
(
  cd "$work/package"
  LC_ALL=C find wp-chinese-converter -print | LC_ALL=C sort | TZ=UTC zip -X -q "$archive" -@
)
mv "$archive" "$out"
printf 'Built %s (SOURCE_DATE_EPOCH=%s)\n' "$out" "$epoch"
