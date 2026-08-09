#!/usr/bin/env bash
set -euo pipefail

root=$(cd "$(dirname "$0")/.." && pwd)
version=$(php -r '$s=file_get_contents($argv[1]); if (!preg_match("/^[ \t*#\/]*Version:\s*([^\r\n]+)/mi", $s, $m)) { exit(2); } echo trim($m[1]);' "$root/wp-chinese-converter.php")
out=${1:-"$root/dist/wp-chinese-converter-$version.zip"}
work=$(mktemp -d)
trap 'find "$work" -depth -delete' EXIT
mkdir -p "$work/source/wp-chinese-converter" "$work/package" "$(dirname "$out")"
rsync -a --exclude=.git --exclude=dist "$root/" "$work/source/wp-chinese-converter/"
(
  cd "$work/source/wp-chinese-converter"
  composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
)
rsync -a --exclude-from="$root/.distignore" "$work/source/wp-chinese-converter/" "$work/package/wp-chinese-converter/"
(
  cd "$work/package"
  zip -q -r "$work/wp-chinese-converter.zip" wp-chinese-converter
)
mv "$work/wp-chinese-converter.zip" "$out"
printf 'Built %s\n' "$out"
