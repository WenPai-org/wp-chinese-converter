#!/usr/bin/env bash
# shellcheck disable=SC2016
set -Eeuo pipefail
ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
CONTAINER="${WPCC_CONTAINER:-}"
SITE_URL="${WPCC_SITE_URL:-}"
SITE_URL="${SITE_URL%/}"
MODE="${1:-preflight}"
EXPECT_MULTISITE="${WPCC_EXPECT_MULTISITE:-}"
TOPOLOGY="${WPCC_TOPOLOGY:-unknown}"
OUT="${WPCC_ARTIFACT_DIR:-/tmp/wpcc-runtime-${TOPOLOGY}-$(date +%s)}"
PLUGIN="${WPCC_PLUGIN_SLUG:-wp-chinese-converter}"
SOURCE_PLUGIN="${WPCC_SOURCE_SLUG:-wp-chinese-converter-codex-audit}"
PEER_SITE_URL="${WPCC_PEER_SITE_URL:-}"
CREATED_POST_IDS=()
APP_PASSWORD_UUID=''
APP_SUPPORT_FILE="/var/www/html/wp-content/mu-plugins/wpcc-runtime-app-passwords-$$.php"
TEMP_ADMIN_ID=''
OLD_OPTIONS_B64=''
INITIAL_YOAST_MODE='none'
OLD_HTACCESS_B64=''
HTACCESS_EXISTED=0
CLEANUP_ARMED=0
mkdir -p "$OUT"

die(){ echo "FAIL: $*" >&2; exit 1; }
contains(){ [[ "$1" == *"$2"* ]] || die "$3: missing [$2]"; }
wp_root(){ docker exec "$CONTAINER" php /tmp/wp-cli.phar --allow-root --path=/var/www/html "$@"; }
wp_at(){ local url="$1"; shift; docker exec "$CONTAINER" php /tmp/wp-cli.phar --allow-root --path=/var/www/html --url="$url" "$@"; }
wp_site(){ wp_at "$SITE_URL" "$@"; }

cleanup(){
  local rc=$? id
  trap - EXIT INT TERM
  if [[ "$CLEANUP_ARMED" == 1 ]] && [[ "$(docker inspect -f '{{.State.Running}}' "$CONTAINER" 2>/dev/null || true)" == true ]]; then
    for id in "${CREATED_POST_IDS[@]:-}"; do
      if [[ -n "$id" ]]; then wp_site post delete "$id" --force >/dev/null 2>&1 || true; fi
    done
    if [[ -n "$APP_PASSWORD_UUID" ]]; then
      wp_root eval "WP_Application_Passwords::delete_application_password(1,'$APP_PASSWORD_UUID');" >/dev/null 2>&1 || true
    fi
    docker exec "$CONTAINER" sh -c 'unlink "$1" 2>/dev/null || true' sh "$APP_SUPPORT_FILE" >/dev/null 2>&1 || true
    if [[ -n "$TEMP_ADMIN_ID" ]]; then
      wp_root eval "require_once ABSPATH.'wp-admin/includes/user.php'; if(is_multisite()){wpmu_delete_user($TEMP_ADMIN_ID);}else{wp_delete_user($TEMP_ADMIN_ID);}" >/dev/null 2>&1 || true
    fi
    if [[ -n "$OLD_OPTIONS_B64" ]]; then
      wp_site eval "\$s=unserialize(base64_decode('$OLD_OPTIONS_B64')); if(\$s['exists']){update_option('wpcc_options',\$s['value']);}else{delete_option('wpcc_options');}" >/dev/null 2>&1 || true
      wp_site rewrite flush --hard >/dev/null 2>&1 || true
      if [[ "$HTACCESS_EXISTED" == 1 ]]; then
        printf '%s' "$OLD_HTACCESS_B64" | base64 -d | docker exec -i "$CONTAINER" sh -c 'cat > /var/www/html/.htaccess' || true
      else
        docker exec "$CONTAINER" sh -c 'unlink /var/www/html/.htaccess 2>/dev/null || true' >/dev/null 2>&1 || true
      fi
    fi
    case "$INITIAL_YOAST_MODE" in
      network) wp_root plugin activate wordpress-seo --network >/dev/null 2>&1 || true;;
      site) wp_site plugin activate wordpress-seo >/dev/null 2>&1 || true;;
      none)
        wp_site plugin deactivate wordpress-seo >/dev/null 2>&1 || true
        if [[ "${EXPECT_MULTISITE:-0}" == 1 ]]; then wp_root plugin deactivate wordpress-seo --network >/dev/null 2>&1 || true; fi
        ;;
    esac
  fi
  printf '{"cleanup":true,"exit":%s,"posts":%s}\n' "$rc" "${#CREATED_POST_IDS[@]}" > "$OUT/cleanup.json"
  exit "$rc"
}
trap cleanup EXIT INT TERM

assert_disposable(){
  [[ -n "$CONTAINER" ]] || die 'WPCC_CONTAINER is required; automatic container selection is forbidden'
  [[ -n "$SITE_URL" ]] || die 'WPCC_SITE_URL is required'
  [[ "$SITE_URL" =~ ^http://(localhost|127\.0\.0\.1):[0-9]+(/.*)?$ ]] || die "refusing non-local site URL: $SITE_URL"
  [[ "$CONTAINER" =~ ^wp-env-wp-chinese-converter-(codex-audit|single|multisite)-[a-z0-9]+-wordpress-1$ ]] || die "refusing non-disposable container: $CONTAINER"
  [[ "$(docker inspect -f '{{.State.Running}}' "$CONTAINER" 2>/dev/null)" == true ]] || die "container is not running: $CONTAINER"
  [[ "$EXPECT_MULTISITE" == 0 || "$EXPECT_MULTISITE" == 1 ]] || die 'WPCC_EXPECT_MULTISITE must be exactly 0 or 1'
  if [[ "$EXPECT_MULTISITE" == 1 ]]; then
    [[ -n "$PEER_SITE_URL" ]] || die 'WPCC_PEER_SITE_URL is required for multisite isolation checks'
    [[ "$PEER_SITE_URL" =~ ^http://(localhost|127\.0\.0\.1):[0-9]+(/.*)?$ ]] || die "refusing non-local peer URL: $PEER_SITE_URL"
  fi
  CLEANUP_ARMED=1
}

capture_state(){
  OLD_OPTIONS_B64="$(wp_site eval '$exists=get_option("wpcc_options","__wpcc_missing__")!=="__wpcc_missing__"; echo base64_encode(serialize(array("exists"=>$exists,"value"=>get_option("wpcc_options"))));')"
  if docker exec "$CONTAINER" test -f /var/www/html/.htaccess; then
    HTACCESS_EXISTED=1
    OLD_HTACCESS_B64="$(docker exec "$CONTAINER" base64 -w0 /var/www/html/.htaccess)"
  fi
  if wp_root plugin is-active wordpress-seo --network >/dev/null 2>&1; then INITIAL_YOAST_MODE=network
  elif wp_site plugin is-active wordpress-seo >/dev/null 2>&1; then INITIAL_YOAST_MODE=site
  else INITIAL_YOAST_MODE=none
  fi
}

preflight(){
  assert_disposable
  local multi wp php active
  multi="$(wp_root eval 'echo is_multisite() ? "yes" : "no";')"
  wp="$(wp_root core version)"
  php="$(wp_root eval 'echo PHP_VERSION;')"
  [[ "$php" == 8.1* || "$php" == 8.2* || "$php" == 8.3* || "$php" == 8.4* ]] || die "PHP 8.1+ required, got $php"
  if [[ "$EXPECT_MULTISITE" == 1 ]]; then
    [[ "$multi" == yes ]] || die 'multisite expected but runtime is standalone'
    wp_root plugin is-active "$PLUGIN" --network >/dev/null || wp_root plugin is-active "$SOURCE_PLUGIN" --network >/dev/null || die 'WPCC not network active'
    wp_root site list --field=url | tee "$OUT/sites.txt"
    [[ "$(sed '/^$/d' "$OUT/sites.txt" | wc -l)" -ge 2 ]] || die 'need at least two multisite sites'
    active=network
  else
    [[ "$multi" == no ]] || die 'standalone expected but runtime is multisite'
    wp_site plugin is-active "$PLUGIN" >/dev/null || wp_site plugin is-active "$SOURCE_PLUGIN" >/dev/null || die 'WPCC not site active'
    active=site
  fi
  capture_state
  printf '{"wordpress":"%s","php":"%s","multisite":"%s","activation":"%s","container":"%s","site":"%s","topology":"%s","head":"%s"}\n' \
    "$wp" "$php" "$multi" "$active" "$CONTAINER" "$SITE_URL" "$TOPOLOGY" "$(git -C "$ROOT_DIR" rev-parse HEAD)" | tee "$OUT/preflight.json"
  wp_root plugin list --format=json > "$OUT/plugins.json"
  wp_site option get permalink_structure > "$OUT/permalink-before.txt" 2>/dev/null || true
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
  ! grep -q '/assets/\.DS_Store' "$OUT/zip-list.out" || die '.DS_Store found in ZIP'
  sha256sum "$zip" | tee "$OUT/zip.sha256"
  printf '{"zip":"%s","bytes":%s,"entries":%s,"sha256":"%s"}\n' "$zip" "$(stat -c %s "$zip")" "$(zipinfo -1 "$zip" | wc -l)" "$(sha256sum "$zip" | awk '{print $1}')" | tee "$OUT/package.json"
  if [[ "${WPCC_INSTALL_ZIP:-0}" == 1 ]]; then
    wp_root plugin deactivate "$SOURCE_PLUGIN" --network >/dev/null 2>&1 || wp_root plugin deactivate "$SOURCE_PLUGIN" >/dev/null 2>&1 || true
    wp_root plugin deactivate "$PLUGIN" --network >/dev/null 2>&1 || wp_root plugin deactivate "$PLUGIN" >/dev/null 2>&1 || true
    docker cp "$zip" "$CONTAINER:/tmp/wpcc-release.zip"
    wp_root plugin install /tmp/wpcc-release.zip --force
    if [[ "$EXPECT_MULTISITE" == 1 ]]; then wp_root plugin activate "$PLUGIN" --network
    else wp_site plugin activate "$PLUGIN"
    fi
  fi
}

configure(){
  if [[ "$EXPECT_MULTISITE" == 1 ]]; then
    printf '%s' 'UmV3cml0ZUVuZ2luZSBPbgpSZXdyaXRlUnVsZSAuKiAtIFtFPUhUVFBfQVVUSE9SSVpBVElPTjole0hUVFA6QXV0aG9yaXphdGlvbn1dClJld3JpdGVCYXNlIC8KUmV3cml0ZVJ1bGUgXmluZGV4XC5waHAkIC0gW0xdClJld3JpdGVDb25kICV7UkVRVUVTVF9GSUxFTkFNRX0gLWYgW09SXQpSZXdyaXRlQ29uZCAle1JFUVVFU1RfRklMRU5BTUV9IC1kClJld3JpdGVSdWxlIF4gLSBbTF0KUmV3cml0ZVJ1bGUgXihbXzAtOWEtekEtWi1dKy8pPyh3cC0oY29udGVudHxhZG1pbnxpbmNsdWRlcykuKikgJDIgW0xdClJld3JpdGVSdWxlIF4oW18wLTlhLXpBLVotXSsvKT8oLipcLnBocCkkICQyIFtMXQpSZXdyaXRlUnVsZSBeIGluZGV4LnBocCBbTF0K' | base64 -d | docker exec -i "$CONTAINER" sh -c 'cat > /var/www/html/.htaccess'
  else
    printf '%s' 'UmV3cml0ZUVuZ2luZSBPbgpSZXdyaXRlUnVsZSAuKiAtIFtFPUhUVFBfQVVUSE9SSVpBVElPTjole0hUVFA6QXV0aG9yaXphdGlvbn1dClJld3JpdGVCYXNlIC8KUmV3cml0ZVJ1bGUgXmluZGV4XC5waHAkIC0gW0xdClJld3JpdGVDb25kICV7UkVRVUVTVF9GSUxFTkFNRX0gIS1mClJld3JpdGVDb25kICV7UkVRVUVTVF9GSUxFTkFNRX0gIS1kClJld3JpdGVSdWxlIC4gL2luZGV4LnBocCBbTF0K' | base64 -d | docker exec -i "$CONTAINER" sh -c 'cat > /var/www/html/.htaccess'
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
  local credential_json
  docker exec "$CONTAINER" sh -c 'mkdir -p /var/www/html/wp-content/mu-plugins && test ! -e "$1" && printf %s "<?php add_filter( \"wp_is_application_passwords_available\", \"__return_true\" );" > "$1"' sh "$APP_SUPPORT_FILE"
  [[ "$(wp_root eval 'echo wp_is_application_passwords_available() ? "yes" : "no";')" == yes ]] || die 'application passwords unavailable in disposable runtime'
  WPCC_ADMIN_USER="$(wp_root user get 1 --field=user_login)"
  WPCC_APP_LABEL="wpcc-runtime-$(date +%s)-$$"
  credential_json="$(wp_root eval "\$r=WP_Application_Passwords::create_new_application_password(1,array('name'=>'$WPCC_APP_LABEL')); if(is_wp_error(\$r)){echo wp_json_encode(array('error'=>\$r->get_error_code()));}else{echo wp_json_encode(array('password'=>\$r[0],'uuid'=>\$r[1]['uuid']));}")"
  WPCC_APP_PASSWORD="$(python3 -c 'import json,sys;print(json.load(sys.stdin)["password"])' <<<"$credential_json")"
  APP_PASSWORD_UUID="$(python3 -c 'import json,sys;print(json.load(sys.stdin)["uuid"])' <<<"$credential_json")"
  WPCC_BLOG_ID="$(wp_site eval 'echo get_current_blog_id();')"
  WPCC_XMLRPC_URL="$SITE_URL"
  export WPCC_ADMIN_USER WPCC_APP_PASSWORD WPCC_SITE_URL WPCC_BLOG_ID WPCC_XMLRPC_URL
}

history_json(){
  local id="$1"
  wp_site eval "wp_set_current_user(1); \$r=WPCC_Post_Conversion_Backup::get_history($id); echo is_wp_error(\$r)?wp_json_encode(array('error'=>\$r->get_error_code())):wp_json_encode(\$r);"
}
history_count(){ history_json "$1" | python3 -c 'import json,sys;d=json.load(sys.stdin);print(len(d.get("entries",d) if isinstance(d,dict) else d))'; }
history_first_id(){ history_json "$1" | python3 -c 'import json,sys;d=json.load(sys.stdin);a=d.get("entries",d) if isinstance(d,dict) else d;print(a[0]["id"])'; }
history_latest_id(){ history_json "$1" | python3 -c 'import json,sys;d=json.load(sys.stdin);a=d.get("entries",d) if isinstance(d,dict) else d;print(a[-1]["id"])'; }
post_hash(){ wp_site eval "\$p=get_post($1); echo WPCC_Post_Conversion_Backup::hash_post(\$p);"; }
restore_call(){
  local id="$1" backup_id="$2" expected="$3" dry="$4"
  [[ "$backup_id" =~ ^[A-Za-z0-9._:-]+$ ]] || die 'unsafe backup id returned by plugin'
  if [[ -n "$expected" ]]; then [[ "$expected" =~ ^[a-fA-F0-9]+$ ]] || die 'unsafe post hash returned by plugin'; fi
  wp_site eval "wp_set_current_user(1); \$r=wpcc_restore_post_conversion($id,'$backup_id','$expected',$dry); echo is_wp_error(\$r)?wp_json_encode(array('error'=>\$r->get_error_code(),'message'=>\$r->get_error_message())):wp_json_encode(\$r);"
}
reset_to_latest_converted(){
  local id="$1"
  wp_site eval "wp_set_current_user(1); global \$wpdb; \$h=WPCC_Post_Conversion_Backup::get_history($id); \$a=isset(\$h['entries'])?\$h['entries']:\$h; \$e=end(\$a); \$wpdb->update(\$wpdb->posts,array('post_title'=>\$e['converted']['post_title'],'post_content'=>\$e['converted']['post_content']),array('ID'=>$id),array('%s','%s'),array('%d')); clean_post_cache($id);"
}
set_conflicting_content(){
  local id="$1"
  wp_site eval "global \$wpdb; \$wpdb->update(\$wpdb->posts,array('post_content'=>'并发冲突编辑'),array('ID'=>$id),array('%s'),array('%d')); clean_post_cache($id);"
}

post_update_transport(){
  local kind="$1" id="$2" title="$3" content="$4" payload
  case "$kind" in
    cli) wp_site post update "$id" --post_title="$title" --post_content="$content" >/dev/null;;
    rest)
      payload="$(WPCC_POST_TITLE="$title" WPCC_POST_CONTENT="$content" python3 -c 'import json,os;print(json.dumps({"title":os.environ["WPCC_POST_TITLE"],"content":os.environ["WPCC_POST_CONTENT"]},ensure_ascii=False))')"
      curl -fsS -u "$WPCC_ADMIN_USER:$WPCC_APP_PASSWORD" -H 'Content-Type: application/json' -X POST --data "$payload" "$SITE_URL/wp-json/wp/v2/posts/$id" > "$OUT/rest-update-$id.json"
      ;;
    xmlrpc)
      WPCC_XMLRPC_ACTION=update WPCC_XMLRPC_POST_ID="$id" WPCC_POST_TITLE="$title" WPCC_POST_CONTENT="$content" python3 "$ROOT_DIR/tests/runtime/xmlrpc-create.py" > "$OUT/xmlrpc-update-$id.json"
      ;;
    *) die "unknown transport $kind";;
  esac
}

create_transport_post(){
  local kind="$1" marker="$2"
  local title="$marker $kind O'Reilly C:\Docs 网络软件 第一版" content="$marker $kind <div data-json='{\"k\":\"网络软件\"}'>网络软件和后台打印机 C:\Docs O'Reilly</div> 第一版" id payload
  export WPCC_POST_TITLE="$title" WPCC_POST_CONTENT="$content"
  case "$kind" in
    cli) id="$(wp_site post create --post_type=post --post_status=publish --post_title="$title" --post_content="$content" --porcelain)";;
    rest)
      payload="$(python3 -c 'import json,os;print(json.dumps({"title":os.environ["WPCC_POST_TITLE"],"content":os.environ["WPCC_POST_CONTENT"],"status":"publish"},ensure_ascii=False))')"
      id="$(curl -fsS -u "$WPCC_ADMIN_USER:$WPCC_APP_PASSWORD" -H 'Content-Type: application/json' --data "$payload" "$SITE_URL/wp-json/wp/v2/posts" | tee "$OUT/rest-create.json" | python3 -c 'import json,sys;print(json.load(sys.stdin)["id"])')"
      ;;
    xmlrpc) id="$(WPCC_XMLRPC_ACTION=create python3 "$ROOT_DIR/tests/runtime/xmlrpc-create.py" | tee "$OUT/xmlrpc-create.json" | python3 -c 'import json,sys;print(json.load(sys.stdin)["post_id"])')";;
    *) die "unknown transport $kind";;
  esac
  CREATED_POST_IDS+=("$id")
  TRANSPORT_POST_ID="$id"
}

assert_peer_isolation(){
  local marker="$1" marker_b64 peer_json peer_count
  [[ "$EXPECT_MULTISITE" == 1 ]] || return 0
  marker_b64="$(printf %s "$marker" | base64 -w0)"
  peer_json="$(wp_at "$PEER_SITE_URL" eval "global \$wpdb; \$m=base64_decode('$marker_b64'); \$like='%'.\$wpdb->esc_like(\$m).'%'; \$rows=\$wpdb->get_results(\$wpdb->prepare('SELECT ID, post_title FROM '.\$wpdb->posts.' WHERE post_type=%s AND (post_title LIKE %s OR post_content LIKE %s)', 'post', \$like, \$like), ARRAY_A); echo wp_json_encode(\$rows);")"
  printf '%s\n' "$peer_json" > "$OUT/peer-isolation.json"
  peer_count="$(python3 -c 'import json,sys;print(len(json.load(sys.stdin)))' <<<"$peer_json")"
  [[ "$peer_count" == 0 ]] || die "multisite isolation failed: marker visible at peer site $PEER_SITE_URL: $peer_json"
}

exercise_history(){
  local kind="$1" marker="$2" id current_title current_content title1 content1 title2 content2 selected expected before after dry stale changed restored repeat count
  create_transport_post "$kind" "$marker"
  id="$TRANSPORT_POST_ID"
  [[ "$(history_count "$id")" == 1 ]] || die "$kind first save did not create exactly one history entry"
  current_title="$(wp_site post get "$id" --field=post_title)"
  current_content="$(wp_site post get "$id" --field=post_content)"
  post_update_transport "$kind" "$id" "$current_title" "$current_content"
  [[ "$(history_count "$id")" == 1 ]] || die "$kind idempotent save added history"

  title1="$marker $kind O'Reilly C:\Docs 网络软件 第一版"
  content1="$marker $kind <div data-json='{\"k\":\"网络软件\"}'>网络软件和后台打印机 C:\Docs O'Reilly</div> 第一版"
  title2="$marker $kind O'Reilly C:\Docs 网络软件 第二版"
  content2="$marker $kind <div data-json='{\"k\":\"网络软件\"}'>网络软件和后台打印机 C:\Docs O'Reilly</div> 第二版"
  post_update_transport "$kind" "$id" "$title2" "$content2"
  [[ "$(history_count "$id")" == 2 ]] || die "$kind second changed save did not create history"
  selected="$(history_first_id "$id")"
  expected="$(post_hash "$id")"
  before="$(wp_site post get "$id" --fields=post_title,post_content,post_modified_gmt --format=json)"
  dry="$(restore_call "$id" "$selected" "$expected" true)"
  echo "$dry" > "$OUT/$kind-dry-run.json"
  python3 -c 'import json,sys;d=json.load(sys.stdin);assert d.get("success") is True and d.get("dry_run") is True; diff=d["diff"]; assert diff["post_title"]["changed"] and diff["post_content"]["changed"]' <<<"$dry" || die "$kind dry-run result mismatch"
  after="$(wp_site post get "$id" --fields=post_title,post_content,post_modified_gmt --format=json)"
  [[ "$before" == "$after" ]] || die "$kind dry-run mutated post"
  [[ "$(history_count "$id")" == 2 ]] || die "$kind dry-run mutated history"

  set_conflicting_content "$id"
  stale="$(restore_call "$id" "$selected" "$expected" false)"
  echo "$stale" > "$OUT/$kind-stale.json"
  [[ "$(python3 -c 'import json,sys;print(json.load(sys.stdin).get("error",""))' <<<"$stale")" == wpcc_restore_stale ]] || die "$kind stale hash was not rejected"
  changed="$(restore_call "$id" "$selected" "" false)"
  echo "$changed" > "$OUT/$kind-post-changed.json"
  [[ "$(python3 -c 'import json,sys;print(json.load(sys.stdin).get("error",""))' <<<"$changed")" == wpcc_post_changed ]] || die "$kind later edit was not rejected"

  reset_to_latest_converted "$id"
  expected="$(post_hash "$id")"
  restored="$(restore_call "$id" "$selected" "$expected" false)"
  echo "$restored" > "$OUT/$kind-restored.json"
  python3 -c 'import json,sys;d=json.load(sys.stdin);assert d.get("success") is True and not d.get("dry_run")' <<<"$restored" || die "$kind restore failed"
  [[ "$(wp_site post get "$id" --field=post_title)" == "$title1" ]] || die "$kind selected old title was not restored exactly"
  [[ "$(wp_site post get "$id" --field=post_content)" == "$content1" ]] || die "$kind selected old content was not restored exactly"
  repeat="$(restore_call "$id" "$selected" "$(post_hash "$id")" false)"
  echo "$repeat" > "$OUT/$kind-repeat.json"
  python3 -c 'import json,sys;d=json.load(sys.stdin);assert d.get("success") is True and d.get("already_restored") is True' <<<"$repeat" || die "$kind repeated restore was not idempotent"
  count="$(history_count "$id")"
  [[ "$count" == 2 ]] || die "$kind restore changed history count to $count"
  assert_peer_isolation "$marker"
  printf '{"transport":"%s","post_id":%s,"history":2,"dry_run":true,"stale_rejected":true,"changed_rejected":true,"selected_old_restore":true,"restored":true,"repeat_noop":true}\n' "$kind" "$id" | tee "$OUT/$kind-history.json"
  WPCC_ADMIN_RECOVERY_POST_ID="$id"
}

bounded_history_check(){
  local marker="$1-bounded" id i json
  id="$(wp_site post create --post_type=post --post_status=publish --post_title="$marker 网络软件 版本 0" --post_content="$marker 网络软件和后台打印机 版本 0" --porcelain)"
  CREATED_POST_IDS+=("$id")
  for i in $(seq 1 11); do
    wp_site post update "$id" --post_title="$marker 网络软件 版本 $i" --post_content="$marker 网络软件和后台打印机 版本 $i" >/dev/null
  done
  json="$(history_json "$id")"
  echo "$json" > "$OUT/bounded-history.json"
  WPCC_BOUND_MARKER="$marker" python3 -c 'import json,os,sys;d=json.load(sys.stdin);a=d.get("entries",d) if isinstance(d,dict) else d;assert len(a)==10, len(a);assert len({e["id"] for e in a})==10;assert os.environ["WPCC_BOUND_MARKER"] in a[-1]["original"]["post_content"] and "版本 11" in a[-1]["original"]["post_content"]' <<<"$json" || die 'bounded history did not retain exactly the latest 10 unique entries'
  assert_peer_isolation "$marker"
}

transport_checks(){
  WPCC_MARKER="wpcc-runtime-$(date +%s)-$$"; export WPCC_MARKER
  local anon auth
  anon="$(curl -sS -o "$OUT/rest-anon.json" -w '%{http_code}' -H 'Content-Type: application/json' --data '{"text":"网络软件","target":"zh-tw"}' "$SITE_URL/wp-json/wpcc/v1/convert")"
  [[ "$anon" == 401 || "$anon" == 403 ]] || die "anonymous REST got $anon"
  auth="$(curl -fsS -u "$WPCC_ADMIN_USER:$WPCC_APP_PASSWORD" -H 'Content-Type: application/json' --data '{"text":"网络软件","target":"zh-tw","engine":"mediawiki"}' "$SITE_URL/wp-json/wpcc/v1/convert")"
  echo "$auth" > "$OUT/rest-auth.json"
  python3 -c 'import json,sys;d=json.load(sys.stdin);assert d["success"] and d["data"]["converted"] == "網路軟體"' <<<"$auth" || die 'authenticated REST conversion mismatch'
  exercise_history cli "$WPCC_MARKER"
  exercise_history rest "$WPCC_MARKER"
  exercise_history xmlrpc "$WPCC_MARKER"
  bounded_history_check "$WPCC_MARKER"
  export WPCC_ADMIN_RECOVERY_POST_ID
}

sitemaps(){
  local core variant yoast_mode=none
  if wp_root plugin is-active wordpress-seo --network >/dev/null 2>&1; then yoast_mode=network; wp_root plugin deactivate wordpress-seo --network >/dev/null
  elif wp_site plugin is-active wordpress-seo >/dev/null 2>&1; then yoast_mode=site; wp_site plugin deactivate wordpress-seo >/dev/null
  fi
  wp_site rewrite structure '/%postname%/' --hard >/dev/null
  core="$(curl -sS -L -o "$OUT/wp-sitemap.xml" -w '%{http_code}' "${SITE_URL%/}/wp-sitemap.xml")"
  variant="$(curl -sS -L -o "$OUT/variant-sitemap.xml" -w '%{http_code}' "${SITE_URL%/}/zh-tw/wp-sitemap.xml")"
  if [[ "$yoast_mode" == network ]]; then wp_root plugin activate wordpress-seo --network >/dev/null
  elif [[ "$yoast_mode" == site ]]; then wp_site plugin activate wordpress-seo >/dev/null
  fi
  [[ "$core" == 200 ]] || die "wp-sitemap.xml got $core"
  [[ "$variant" == 200 ]] || die "variant sitemap got $variant"
  grep -Eq '<(sitemapindex|urlset)' "$OUT/wp-sitemap.xml" || die 'core sitemap not XML'
  grep -Eq '<(sitemapindex|urlset)' "$OUT/variant-sitemap.xml" || die 'variant sitemap not XML'
  printf '{"core":%s,"variant":%s}\n' "$core" "$variant" | tee "$OUT/sitemaps.json"
}

yoast(){
  if ! wp_root plugin is-active wordpress-seo --network >/dev/null 2>&1 && ! wp_site plugin is-active wordpress-seo >/dev/null 2>&1; then
    [[ "${WPCC_REQUIRE_YOAST:-1}" == 0 ]] && { echo '{"yoast":"skip"}' | tee "$OUT/yoast.json"; return; }
    die 'Yoast is not active'
  fi
  local id url canonical_count hreflang_count
  id="$(wp_site post create --post_type=post --post_status=publish --post_title='网络软件 SEO 标题' --post_content='网络软件 SEO 正文' --porcelain)"
  CREATED_POST_IDS+=("$id")
  wp_site post meta update "$id" _yoast_wpseo_title '网络软件 SEO 标题' >/dev/null
  wp_site post meta update "$id" _yoast_wpseo_metadesc '网络软件 SEO 描述' >/dev/null
  url="$(wp_site post url "$id")"
  curl -fsS "$url?variant=zh-tw" > "$OUT/yoast.html"
  canonical_count="$(grep -oi 'rel="canonical"' "$OUT/yoast.html" | wc -l)"
  hreflang_count="$(grep -oi 'hreflang=' "$OUT/yoast.html" | wc -l)"
  [[ "$canonical_count" == 1 ]] || die "canonical count $canonical_count"
  [[ "$hreflang_count" -ge 2 ]] || die "hreflang count $hreflang_count"
  grep -q '網路軟體 SEO 標題' "$OUT/yoast.html" || die 'Yoast title not converted'
  grep -q '網路軟體 SEO 描述' "$OUT/yoast.html" || die 'Yoast description not converted'
  python3 - "$OUT/yoast.html" <<'PY'
import re,sys
s=open(sys.argv[1],encoding='utf-8').read()
langs=re.findall(r'hreflang=["\']([^"\']+)',s,re.I)
assert len(langs)==len(set(langs)), f'duplicate hreflang: {langs}'
PY
  printf '{"canonical":1,"converted":true,"hreflang_count":%s,"hreflang_unique":true}\n' "$hreflang_count" | tee "$OUT/yoast.json"
}

frontend_e2e(){
  local old_post old_cookie old_browser standard_rc session_rc page_id
  old_post="$(wp_site eval '$o=(array)get_option("wpcc_options",array());echo (int)($o["wpcc_enable_post_conversion"]??0);')"
  old_cookie="$(wp_site eval '$o=(array)get_option("wpcc_options",array());echo (int)($o["wpcc_use_cookie_variant"]??0);')"
  old_browser="$(wp_site eval '$o=(array)get_option("wpcc_options",array());echo (int)($o["wpcc_browser_redirect"]??0);')"
  wp_site eval '$o=(array)get_option("wpcc_options",array());$o["wpcc_enable_post_conversion"]=0;$o["wpcc_translate_type"]=0;$o["wpcc_use_cookie_variant"]=1;$o["wpcc_browser_redirect"]=0;update_option("wpcc_options",$o);'
  page_id="$(wp_site post create --post_type=page --post_status=publish --post_title='WPCC Runtime Switcher' --post_content='[wp-chinese-converter] 网络软件和后台打印机' --porcelain)"
  CREATED_POST_IDS+=("$page_id")
  WPCC_PAGE_ID="$page_id"; export WPCC_PAGE_ID WPCC_SITE_URL
  set +e; node "$ROOT_DIR/tests/runtime/switcher.e2e.js" | tee "$OUT/e2e.json"; standard_rc=${PIPESTATUS[0]}; set -e
  wp_site eval '$o=(array)get_option("wpcc_options",array());$o["wpcc_use_cookie_variant"]=0;$o["wpcc_browser_redirect"]=1;update_option("wpcc_options",$o);'
  set +e; node "$ROOT_DIR/tests/runtime/browser-session.e2e.js" | tee "$OUT/e2e-browser-session.json"; session_rc=${PIPESTATUS[0]}; set -e
  wp_site eval "\$o=(array)get_option('wpcc_options',array());\$o['wpcc_enable_post_conversion']=$old_post;\$o['wpcc_use_cookie_variant']=$old_cookie;\$o['wpcc_browser_redirect']=$old_browser;update_option('wpcc_options',\$o);"
  [[ "$standard_rc" == 0 ]] || die "standard switcher E2E failed: $standard_rc"
  [[ "$session_rc" == 0 ]] || die "browser session E2E failed: $session_rc"
}

admin_recovery_e2e(){
  [[ "${WPCC_RUN_ADMIN_E2E:-1}" == 1 ]] || { echo '{"admin_recovery":"skip"}' | tee "$OUT/admin-recovery.json"; return; }
  local login pass email id
  login="wpccruntimeadmin$(date +%s)$$"
  pass="WPCC-runtime-$(date +%s)-$$-Strong!"
  email="$login@example.invalid"
  TEMP_ADMIN_ID="$(wp_site user create "$login" "$email" --role=administrator --user_pass="$pass" --porcelain)"
  id="$WPCC_ADMIN_RECOVERY_POST_ID"
  reset_to_latest_converted "$id"
  WPCC_ADMIN_LOGIN="$login" WPCC_ADMIN_PASSWORD="$pass" WPCC_ADMIN_RECOVERY_POST_ID="$id" WPCC_ARTIFACT_DIR="$OUT" \
    node "$ROOT_DIR/tests/runtime/admin-recovery.e2e.js" | tee "$OUT/admin-recovery.json"
  wp_site eval "wp_set_current_user(1); \$h=WPCC_Post_Conversion_Backup::get_history($id); \$a=isset(\$h['entries'])?\$h['entries']:\$h; \$e=end(\$a); \$p=get_post($id); if(\$p->post_title!==\$e['original']['post_title']||\$p->post_content!==\$e['original']['post_content']){exit(3);}" || die 'admin E2E did not restore exact selected original'
}

site_matrix(){ preflight; configure; credentials; transport_checks; sitemaps; yoast; frontend_e2e; admin_recovery_e2e; }
admin_matrix(){
  preflight; configure; credentials
  WPCC_MARKER="wpcc-admin-runtime-$(date +%s)-$$"; export WPCC_MARKER
  exercise_history cli "$WPCC_MARKER"
  export WPCC_ADMIN_RECOVERY_POST_ID
  admin_recovery_e2e
}
case "$MODE" in
  preflight) preflight;;
  package) assert_disposable; package_check;;
  site) site_matrix;;
  admin) admin_matrix;;
  all) assert_disposable; package_check; site_matrix;;
  *) die 'usage: runtime-matrix.sh [preflight|package|site|admin|all]';;
esac
