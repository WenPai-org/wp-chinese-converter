const { chromium } = require(process.env.WPCC_PLAYWRIGHT || '/home/parallels/Projects/wpbridge/node_modules/playwright');

const baseUrl = process.env.WPCC_SITE_URL;
const postId = process.env.WPCC_ADMIN_RECOVERY_POST_ID;
const username = process.env.WPCC_ADMIN_LOGIN;
const password = process.env.WPCC_ADMIN_PASSWORD;
const executablePath = process.env.WPCC_CHROMIUM || '/home/parallels/.cache/ms-playwright/chromium-1234/chrome-linux/chrome';
const artifactDir = process.env.WPCC_ARTIFACT_DIR;
const appUser = process.env.WPCC_ADMIN_USER;
const appPassword = process.env.WPCC_APP_PASSWORD;
if (!baseUrl || !postId || !username || !password) {
  throw new Error('WPCC_SITE_URL, WPCC_ADMIN_RECOVERY_POST_ID, WPCC_ADMIN_LOGIN and WPCC_ADMIN_PASSWORD are required');
}

async function readRawPost() {
  if (!appUser || !appPassword) throw new Error('application password credentials are required for DB-neutral E2E reads');
  const response = await fetch(`${baseUrl}/wp-json/wp/v2/posts/${postId}?context=edit`, {
    headers: { Authorization: `Basic ${Buffer.from(`${appUser}:${appPassword}`).toString('base64')}` },
  });
  if (!response.ok) throw new Error(`post snapshot failed: HTTP ${response.status}`);
  const post = await response.json();
  return JSON.stringify({ title: post.title.raw, content: post.content.raw, modified_gmt: post.modified_gmt });
}

async function submitAndWait(page, selector) {
  const navigation = page.waitForNavigation({ waitUntil: 'domcontentloaded' });
  await page.locator(selector).evaluate((button) => {
    if (!button.form) throw new Error('recovery control has no associated form');
    button.form.requestSubmit(button);
  });
  await navigation;
}
async function captureScreenshot(page, path) {
  try {
    await page.screenshot({ path, fullPage: false, timeout: 10000 });
  } catch (error) {
    console.error(`screenshot warning: ${error.message}`);
  }
}

async function revealRecoveryForm(page) {
  const box = page.locator('#wpcc-conversion-meta-box');
  await box.waitFor({ state: 'attached' });
  if (await box.evaluate((element) => element.classList.contains('closed'))) {
    await box.locator('.handlediv').click();
  }
  const form = page.locator('#wpcc-conversion-restore-form');
  await form.waitFor({ state: 'attached' });
  if (await form.isVisible()) throw new Error('footer recovery form should remain hidden');
  for (const selector of ['#wpcc-conversion-backup-id', '#wpcc-conversion-dry-run', '#wpcc-conversion-restore']) {
    const control = page.locator(selector);
    await control.waitFor({ state: 'visible' });
    if (await control.getAttribute('form') !== 'wpcc-conversion-restore-form') {
      throw new Error(`${selector} is not associated with the footer recovery form`);
    }
  }
}

(async () => {
  const browser = await chromium.launch({ headless: true, executablePath, args: ['--no-sandbox'] });
  const context = await browser.newContext();
  const page = await context.newPage();
  page.on('dialog', (dialog) => dialog.accept());

  await page.goto(`${baseUrl}/wp-login.php`, { waitUntil: 'domcontentloaded' });
  await page.locator('#user_login').fill(username);
  await page.locator('#user_pass').fill(password);
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
    page.locator('#wp-submit').click(),
  ]);
  if (page.url().includes('wp-login.php')) throw new Error('temporary administrator login failed');

  await page.goto(`${baseUrl}/wp-admin/post.php?post=${postId}&action=edit`, { waitUntil: 'domcontentloaded' });
  await revealRecoveryForm(page);
  const beforeRows = await page.locator('.wpcc-conversion-history tr[data-wpcc-backup-id]').count();
  if (beforeRows < 2) throw new Error(`expected at least two history rows, got ${beforeRows}`);
  const selectedBackup = await page.locator('#wpcc-conversion-backup-id').inputValue();
  if (!selectedBackup) throw new Error('history selector has no selected backup');

  const postBeforeDryRun = await readRawPost();
  await submitAndWait(page, '#wpcc-conversion-dry-run');
  await page.locator('#wpcc-restore-dry-run').waitFor();
  for (const field of ['post_title', 'post_content']) {
    const row = page.locator(`tr[data-wpcc-field="${field}"]`);
    await row.waitFor();
    if (!(await row.innerText()).trim()) throw new Error(`empty ${field} diff row`);
  }
  if (artifactDir) await captureScreenshot(page, `${artifactDir}/admin-recovery-dry-run.png`);
  const postAfterDryRun = await readRawPost();
  if (postAfterDryRun !== postBeforeDryRun) throw new Error('admin dry-run mutated the post');
  await page.goBack({ waitUntil: 'domcontentloaded' });
  await revealRecoveryForm(page);
  const rowsAfterDryRun = await page.locator('.wpcc-conversion-history tr[data-wpcc-backup-id]').count();
  if (rowsAfterDryRun !== beforeRows) throw new Error('dry-run changed history row count');
  await page.locator('#wpcc-conversion-backup-id').selectOption(selectedBackup);

  await submitAndWait(page, '#wpcc-conversion-restore');
  if (!page.url().includes('wpcc_restore_status=restored')) throw new Error(`restore status missing: ${page.url()}`);
  await page.locator('#wpcc-conversion-meta-box').waitFor();
  if (artifactDir) await captureScreenshot(page, `${artifactDir}/admin-recovery-restored.png`);
  const rowsAfterRestore = await page.locator('.wpcc-conversion-history tr[data-wpcc-backup-id]').count();
  if (rowsAfterRestore !== beforeRows) throw new Error('restore changed history row count');

  await submitAndWait(page, '#wpcc-conversion-restore');
  if (!page.url().includes('wpcc_restore_status=already-restored')) {
    throw new Error(`repeat restore was not an explicit no-op: ${page.url()}`);
  }
  const rowsAfterRepeat = await page.locator('.wpcc-conversion-history tr[data-wpcc-backup-id]').count();
  if (rowsAfterRepeat !== beforeRows) throw new Error('repeat restore changed history row count');

  const hasUnfilteredHtml = process.env.WPCC_ADMIN_HAS_UNFILTERED_HTML === '1';
  const ksesResult = await page.evaluate(async (hasUnfilteredHtml) => {
    const settings = window.wpApiSettings;
    if (!settings || !settings.root || !settings.nonce) throw new Error('wpApiSettings unavailable');
    const endpoint = `${settings.root}wp/v2/posts`;
    const headers = { 'Content-Type': 'application/json', 'X-WP-Nonce': settings.nonce };
    const meResponse = await fetch(`${settings.root}wp/v2/users/me?context=edit`, { credentials: 'same-origin', headers });
    const me = await meResponse.json();
    if (!me || !me.id) throw new Error('REST current-user capability probe failed');
    const createdResponse = await fetch(endpoint, {
      method: 'POST', credentials: 'same-origin', headers,
      body: JSON.stringify({ status: 'draft', title: 'WPCC KSES follow-up', content: '<p>safe</p><script>window.wpccUnsafe=1</script>' }),
    });
    const created = await createdResponse.json();
    if (!createdResponse.ok) return { error: `create HTTP ${createdResponse.status}`, created };
    const raw = created.content && created.content.raw ? created.content.raw : '';
    const deletedResponse = await fetch(`${endpoint}/${created.id}?force=true`, { method: 'DELETE', credentials: 'same-origin', headers });
    return { id: created.id, raw, deleted: deletedResponse.ok, hasUnfilteredHtml };
  }, hasUnfilteredHtml);
  const scriptPreserved = (ksesResult.raw || '').includes('<script');
  if (ksesResult.error || !ksesResult.deleted || (!ksesResult.hasUnfilteredHtml && scriptPreserved)) {
    throw new Error(`post-restore save policy mismatch: ${JSON.stringify(ksesResult)}`);
  }

  console.log(JSON.stringify({
    adminLogin: true,
    postId: Number(postId),
    historyRows: beforeRows,
    selectedBackup,
    dryRunDiff: true,
    restored: true,
    repeatNoop: true,
    subsequentSaveKsesProtected: ksesResult.hasUnfilteredHtml ? null : !scriptPreserved,
    subsequentSaveKsesCheck: ksesResult.hasUnfilteredHtml ? 'not-applicable-unfiltered-html' : 'passed',
  }));
  await browser.close();
})().catch((error) => { console.error(error); process.exit(1); });
