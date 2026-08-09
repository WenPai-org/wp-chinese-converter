#!/usr/bin/env bash
set -euo pipefail

root=$(cd "$(dirname "$0")/.." && pwd)
version=$(php -r '$s=file_get_contents($argv[1]); if (!preg_match("/^[ \t*#\/]*Version:\s*([^\r\n]+)/mi", $s, $m)) { exit(2); } echo trim($m[1]);' "$root/wp-chinese-converter.php")
out=${1:-"$root/dist/wp-chinese-converter-$version.zip"}
work=$(mktemp -d)
trap 'find "$work" -depth -delete' EXIT
mkdir -p "$work/source/wp-chinese-converter" "$work/package" "$(dirname "$out")"
rsync -a --exclude=.git --exclude='/dist/' "$root/" "$work/source/wp-chinese-converter/"
(
  cd "$work/source/wp-chinese-converter"
  composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
)
# Keep runtime code, parsed OpenCC dictionaries, and license texts. Remove only
# upstream development/benchmark material from the staged release tree.
find "$work/source/wp-chinese-converter/vendor" -type d \
  \( -name .github -o -name tests -o -name Tests -o -name benchmark -o -name benchmarks \) \
  -prune -exec find {} -depth -delete \;
find "$work/source/wp-chinese-converter/vendor" -type f \
  \( -iname 'README*' -o -iname 'CHANGELOG*' -o -name 'composer.json' -o -name 'psalm.xml*' \) \
  -delete
find "$work/source/wp-chinese-converter/vendor/overtrue/php-opencc/data/dictionary" -depth -delete
rsync -a --exclude-from="$root/.distignore" "$work/source/wp-chinese-converter/" "$work/package/wp-chinese-converter/"
test -f "$work/package/wp-chinese-converter/assets/dist/wpcc-variant.umd.js"
(
  cd "$work/package"
  zip -q -r "$work/wp-chinese-converter.zip" wp-chinese-converter
)
mv "$work/wp-chinese-converter.zip" "$out"
printf 'Built %s\n' "$out"
