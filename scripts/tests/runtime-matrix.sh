#!/usr/bin/env bash
# shellcheck disable=SC2016
set -Eeuo pipefail
ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
CONTAINER="${WPCC_CONTAINER:-}"
SITE_URL="${WPCC_SITE_URL:-http://localhost:8890}"
SITE_URL="${SITE_URL%/}"
MODE="${1:-preflight}"
OUT="${WPCC_ARTIFACT_DIR:-/tmp/wpcc-runtime-matrix}"
PLUGIN="${WPCC_PLUGIN_SLUG:-wp-chinese-converter}"
SOURCE_PLUGIN="${WPCC_SOURCE_SLUG:-wp-chinese-converter-codex-audit}"
mkdir -p "$OUT"
die(){ echo "FAIL: $*" >&2; exit 1; }
contains(){ [[ "$1" == *"$2"* ]] || die "$3: missing [$2]"; }

if [[ -z "$CONTAINER" ]]; then
  CONTAINER="$(docker ps --format '{{.Names}}' | grep '^wp-env-wp-chinese-converter.*-wordpress-1$' | head -1 || true)"
fi
[[ -n "$CONTAINER" ]] || die 'set WPCC_CONTAINER to the running disposable WordPress container'
[[ "$CONTAINER" =~ ^wp-env-wp-chinese-converter-codex-audit-[a-z0-9]+-wordpress-1$ ]] || die "refusing non-disposable container: $CONTAINER"
[[ "$(docker inspect -f '{{.State.Running}}' "$CONTAINER" 2>/dev/null)" == true ]] || die "container is not running: $CONTAINER"
wp_root(){ docker exec "$CONTAINER" php /tmp/wp-cli.phar --allow-root --path=/var/www/html "$@"; }
wp_site(){ docker exec "$CONTAINER" php /tmp/wp-cli.phar --allow-root --path=/var/www/html --url="$SITE_URL" "$@"; }

preflight(){
  local multi
  multi="$(wp_root eval 'echo is_multisite() ? "yes" : "no";')"
  printf '{"wordpress":"%s","multisite":"%s","container":"%s","site":"%s"}\n'     "$(wp_root core version)" "$multi" "$CONTAINER" "$SITE_URL" | tee "$OUT/preflight.json"
  wp_root plugin list --format=json > "$OUT/plugins.json"
  wp_site option get permalink_structure > "$OUT/permalink.txt"
  if [[ "${WPCC_EXPECT_MULTISITE:-0}" == 1 ]]; then
    [[ "$multi" == yes ]] || die 'multisite expected'
    wp_root plugin is-active "$PLUGIN" --network >/dev/null ||
      wp_root plugin is-active "$SOURCE_PLUGIN" --network >/dev/null || die 'WPCC not network active'
    wp_root site list --field=url | tee "$OUT/sites.txt"
    [[ "$(sed '/^$/d' "$OUT/sites.txt" | wc -l)" -ge 2 ]] || die 'need at least two multisite sites'
  fi
}

package_check(){
  cd "$ROOT_DIR"
  ./scripts/build-release.sh | tee "$OUT/build-release.out"
  local zip
  zip="$(find dist -maxdepth 1 -name 'wp-chinese-converter-*.zip' -type f -printf '%T@ %p\n' | sort -nr | head -1 | cut -d' ' -f2-)"
  [[ -f "$zip" ]] || die 'release ZIP missing'
  unzip -t "$zip" > "$OUT/zip-integrity.out"
  unzip -l "$zip" > "$OUT/zip-list.out"
  grep -q 'wp-chinese-converter/assets/dist/wpcc-variant.umd.js' "$OUT/zip-list.out" || die 'frontend runtime missing from ZIP'
  grep -Eq '(^|/)(tests|\.git|node_modules)/' "$OUT/zip-list.out" && die 'dev paths found in ZIP'
  printf '{"zip":"%s","bytes":%s,"entries":%s}\n' "$zip" "$(stat -c %s "$zip")" "$(zipinfo -1 "$zip" | wc -l)" | tee "$OUT/package.json"
  if [[ "${WPCC_INSTALL_ZIP:-0}" == 1 ]]; then
    wp_root plugin deactivate "$SOURCE_PLUGIN" --network >/dev/null 2>&1 ||
      wp_root plugin deactivate "$SOURCE_PLUGIN" >/dev/null 2>&1 || true
    wp_root plugin deactivate "$PLUGIN" --network >/dev/null 2>&1 ||
      wp_root plugin deactivate "$PLUGIN" >/dev/null 2>&1 || true
    docker cp "$zip" "$CONTAINER:/tmp/wpcc-release.zip"
    wp_root plugin install /tmp/wpcc-release.zip --force
    if [[ "$(wp_root eval 'echo is_multisite() ? "yes" : "no";')" == yes ]]; then
      wp_root plugin activate "$PLUGIN" --network
    else
      wp_root plugin activate "$PLUGIN"
    fi
  fi
}

configure(){
  if [[ "$(wp_root eval 'echo is_multisite() ? "yes" : "no";')" == yes ]]; then
    printf '%s' 'UmV3cml0ZUVuZ2luZSBPbgpSZXdyaXRlUnVsZSAuKiAtIFtFPUhUVFBfQVVUSE9SSVpBVElPTjole0hUVFA6QXV0aG9yaXphdGlvbn1dClJld3JpdGVCYXNlIC8KUmV3cml0ZVJ1bGUgXmluZGV4XC5waHAkIC0gW0xdClJld3JpdGVSdWxlIF4oW18wLTlhLXpBLVotXSsvKT93cC1hZG1pbiQgJDF3cC1hZG1pbi8gW1I9MzAxLExdClJld3JpdGVDb25kICV7UkVRVUVTVF9GSUxFTkFNRX0gLWYgW09SXQpSZXdyaXRlQ29uZCAle1JFUVVFU1RfRklMRU5BTUV9IC1kClJld3JpdGVSdWxlIF4gLSBbTF0KUmV3cml0ZVJ1bGUgXihbXzAtOWEtekEtWi1dKy8pPyh3cC0oY29udGVudHxhZG1pbnxpbmNsdWRlcykuKikgJDIgW0xdClJld3JpdGVSdWxlIF4oW18wLTlhLXpBLVotXSsvKT8oLipcLnBocCkkICQyIFtMXQpSZXdyaXRlUnVsZSAuIGluZGV4LnBocCBbTF0K' | base64 -d | docker exec -i "$CONTAINER" sh -c 'cat > /var/www/html/.htaccess'
  fi
  wp_site rewrite structure '/%postname%/' --hard >/dev/null
  wp_site eval '
    $o=(array)get_option("wpcc_options",array());
    $o["wpcc_used_langs"]=array("zh-cn","zh-tw","zh-hk");
    $o["wpcc_engine"]="mediawiki"; $o["wpcc_translate_type"]=0;
    $o["wpcc_enable_post_conversion"]=1; $o["wpcc_post_conversion_target"]="zh-tw";
    $o["wpcco_use_sitemap"]=1; update_option("wpcc_options",$o);
    delete_option("wpcc_sitemap_rewrite_rules_flushed");
  '
  wp_site rewrite flush --hard >/dev/null
}

credentials(){
  WPCC_ADMIN_USER="$(wp_root user get 1 --field=user_login)"
  WPCC_APP_PASSWORD="$(wp_root user application-password create 1 "wpcc-runtime-$(date +%s)" --porcelain)"
  WPCC_BLOG_ID="$(wp_site eval 'echo get_current_blog_id();')"
  WPCC_XMLRPC_URL="$SITE_URL"
  export WPCC_ADMIN_USER WPCC_APP_PASSWORD WPCC_SITE_URL WPCC_BLOG_ID WPCC_XMLRPC_URL
}

transport_checks(){
  WPCC_MARKER="wpcc-runtime-$(date +%s)-$$"; export WPCC_MARKER
  local cli rest anon auth xml ids id content title backups restore
  cli="$(wp_site post create --post_type=post --post_status=publish --post_title="$WPCC_MARKER CLI 网络软件" --post_content='网络软件和后台打印机' --porcelain)"
  anon="$(curl -sS -o "$OUT/rest-anon.json" -w '%{http_code}' -H 'Content-Type: application/json' --data '{"text":"网络软件","target":"zh-tw"}' "$SITE_URL/wp-json/wpcc/v1/convert")"
  [[ "$anon" == 401 || "$anon" == 403 ]] || die "anonymous REST got $anon"
  auth="$(curl -fsS -u "$WPCC_ADMIN_USER:$WPCC_APP_PASSWORD" -H 'Content-Type: application/json' --data '{"text":"网络软件","target":"zh-tw","engine":"mediawiki"}' "$SITE_URL/wp-json/wpcc/v1/convert")"
  echo "$auth" > "$OUT/rest-auth.json"
  python3 -c 'import json,sys; d=json.load(sys.stdin); assert d["success"] and d["data"]["converted"] == "網路軟體"' <<<"$auth" || die 'authenticated REST conversion mismatch'
  rest="$(curl -fsS -u "$WPCC_ADMIN_USER:$WPCC_APP_PASSWORD" -H 'Content-Type: application/json' --data "{\"title\":\"$WPCC_MARKER REST 网络软件\",\"content\":\"网络软件和后台打印机\",\"status\":\"publish\"}" "$SITE_URL/wp-json/wp/v2/posts" | python3 -c 'import json,sys;print(json.load(sys.stdin)["id"])')"
  xml="$(python3 "$ROOT_DIR/tests/runtime/xmlrpc-create.py" | python3 -c 'import json,sys;print(json.load(sys.stdin)["post_id"])')"
  ids=("$cli" "$rest" "$xml")
  for id in "${ids[@]}"; do
    title="$(wp_site post get "$id" --field=post_title)"; contains "$title" "$WPCC_MARKER" "transport post site identity $id"
    content="$(wp_site post get "$id" --field=post_content)"; contains "$content" '網路軟體' "saved conversion $id"
    [[ "$content" != *'网络软件'* ]] || die "saved content $id retained simplified source"
    backups="$(wp_site eval "echo count(get_post_meta($id,'_wpcc_conversion_backup',false));")"
    [[ "$backups" == 1 ]] || die "backup count for $id is $backups"
  done
  content="$(wp_site post get "$cli" --field=post_content)"
  wp_site post update "$cli" --post_content="$content" >/dev/null
  [[ "$(wp_site eval "echo count(get_post_meta($cli,'_wpcc_conversion_backup',false));")" == 1 ]] || die 'idempotent save added backup'
  restore="$(wp_site eval "wp_set_current_user(1);\$r=wpcc_restore_latest_post_conversion($cli);echo is_wp_error(\$r)?\$r->get_error_code():(\$r?'restored':'false');")"
  [[ "$restore" == restored ]] || die "rollback failed: $restore"
  contains "$(wp_site post get "$cli" --field=post_content)" '网络软件和后台打印机' 'rollback'
  printf '{"cli":%s,"rest":%s,"xmlrpc":%s,"rollback":"restored","backups":1}\n' "$cli" "$rest" "$xml" | tee "$OUT/transports.json"
}

sitemaps(){
  local core variant yoast_mode=none
  if wp_root plugin is-active wordpress-seo --network >/dev/null 2>&1; then
    yoast_mode=network
    wp_root plugin deactivate wordpress-seo --network >/dev/null
  elif wp_site plugin is-active wordpress-seo >/dev/null 2>&1; then
    yoast_mode=site
    wp_site plugin deactivate wordpress-seo >/dev/null
  fi
  wp_site rewrite structure '/%postname%/' --hard >/dev/null
  core="$(curl -sS -L -o "$OUT/wp-sitemap.xml" -w '%{http_code}' "${SITE_URL%/}/wp-sitemap.xml")"
  variant="$(curl -sS -L -o "$OUT/variant-sitemap.xml" -w '%{http_code}' "${SITE_URL%/}/zh-tw/wp-sitemap.xml")"
  if [[ "$yoast_mode" == network ]]; then
    wp_root plugin activate wordpress-seo --network >/dev/null
  elif [[ "$yoast_mode" == site ]]; then
    wp_site plugin activate wordpress-seo >/dev/null
  fi
  [[ "$core" == 200 ]] || die "wp-sitemap.xml got $core"
  [[ "$variant" == 200 ]] || die "variant sitemap got $variant"
  grep -Eq '<(sitemapindex|urlset)' "$OUT/wp-sitemap.xml" || die 'core sitemap not XML'
  grep -Eq '<(sitemapindex|urlset)' "$OUT/variant-sitemap.xml" || die 'variant sitemap not XML'
  printf '{"core":%s,"variant":%s}\n' "$core" "$variant" | tee "$OUT/sitemaps.json"
}

yoast(){
  if ! wp_root plugin is-active wordpress-seo --network >/dev/null 2>&1 && ! wp_site plugin is-active wordpress-seo >/dev/null 2>&1; then
    [[ "${WPCC_REQUIRE_YOAST:-1}" == 0 ]] && { echo 'SKIP Yoast'; return; }
    die 'Yoast is not active'
  fi
  local id url count
  id="$(wp_site post create --post_type=post --post_status=publish --post_title='网络软件 SEO 标题' --post_content='网络软件 SEO 正文' --porcelain)"
  wp_site post meta update "$id" _yoast_wpseo_title '网络软件 SEO 标题' >/dev/null
  wp_site post meta update "$id" _yoast_wpseo_metadesc '网络软件 SEO 描述' >/dev/null
  url="$(wp_site post url "$id")"
  curl -fsS "$url?variant=zh-tw" > "$OUT/yoast.html"
  count="$(grep -oi 'rel="canonical"' "$OUT/yoast.html" | wc -l)"
  [[ "$count" == 1 ]] || die "canonical count $count"
  grep -q '網路軟體 SEO 標題' "$OUT/yoast.html" || die 'Yoast title not converted'
  grep -q '網路軟體 SEO 描述' "$OUT/yoast.html" || die 'Yoast description not converted'
  grep -qi 'hreflang=' "$OUT/yoast.html" || die 'hreflang missing'
  echo '{"canonical":1,"converted":true,"hreflang":true}' | tee "$OUT/yoast.json"
}

e2e(){
  local old_post old_cookie old_browser standard_rc session_rc
  old_post="$(wp_site eval '$o=(array)get_option("wpcc_options",array());echo (int)($o["wpcc_enable_post_conversion"]??0);')"
  old_cookie="$(wp_site eval '$o=(array)get_option("wpcc_options",array());echo (int)($o["wpcc_use_cookie_variant"]??0);')"
  old_browser="$(wp_site eval '$o=(array)get_option("wpcc_options",array());echo (int)($o["wpcc_browser_redirect"]??0);')"
  wp_site eval '$o=(array)get_option("wpcc_options",array());$o["wpcc_enable_post_conversion"]=0;$o["wpcc_translate_type"]=0;$o["wpcc_use_cookie_variant"]=1;$o["wpcc_browser_redirect"]=0;update_option("wpcc_options",$o);'
  WPCC_PAGE_ID="$(wp_site post create --post_type=page --post_status=publish --post_title='WPCC Runtime Switcher' --post_content='[wp-chinese-converter] 网络软件和后台打印机' --porcelain)"
  export WPCC_PAGE_ID WPCC_SITE_URL
  set +e
  node "$ROOT_DIR/tests/runtime/switcher.e2e.js" | tee "$OUT/e2e.json"
  standard_rc=${PIPESTATUS[0]}
  set -e
  wp_site eval '$o=(array)get_option("wpcc_options",array());$o["wpcc_use_cookie_variant"]=0;$o["wpcc_browser_redirect"]=1;update_option("wpcc_options",$o);'
  set +e
  node "$ROOT_DIR/tests/runtime/browser-session.e2e.js" | tee "$OUT/e2e-browser-session.json"
  session_rc=${PIPESTATUS[0]}
  set -e
  wp_site eval "\$o=(array)get_option('wpcc_options',array());\$o['wpcc_enable_post_conversion']=$old_post;\$o['wpcc_use_cookie_variant']=$old_cookie;\$o['wpcc_browser_redirect']=$old_browser;update_option('wpcc_options',\$o);"
  [[ "$standard_rc" == 0 ]] || die "standard switcher E2E failed: $standard_rc"
  [[ "$session_rc" == 0 ]] || die "browser session E2E failed: $session_rc"
}

site_matrix(){ preflight; configure; credentials; transport_checks; sitemaps; yoast; e2e; }
case "$MODE" in
  preflight) preflight;;
  package) package_check;;
  site) site_matrix;;
  all) package_check; site_matrix;;
  *) die 'usage: runtime-matrix.sh [preflight|package|site|all]';;
esac
