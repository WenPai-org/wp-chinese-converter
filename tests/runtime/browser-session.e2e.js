const { chromium } = require(process.env.WPCC_PLAYWRIGHT || '/home/parallels/Projects/wpbridge/node_modules/playwright');
const baseUrl = process.env.WPCC_SITE_URL;
const pageId = process.env.WPCC_PAGE_ID;
const executablePath = process.env.WPCC_CHROMIUM || '/home/parallels/.cache/ms-playwright/chromium-1234/chrome-linux/chrome';
if (!baseUrl || !pageId) throw new Error('WPCC_SITE_URL and WPCC_PAGE_ID are required');

(async () => {
  const browser = await chromium.launch({ headless: true, executablePath, args: ['--no-sandbox'] });
  const context = await browser.newContext({ locale: 'zh-TW' });
  const page = await context.newPage();
  const contentText = async () => {
    const content = page.locator('.entry-content, .wp-block-post-content');
    return (await content.count() ? content.first() : page.locator('body')).innerText();
  };

  await page.goto(`${baseUrl}/?page_id=${pageId}`, { waitUntil: 'networkidle' });
  if (!(await contentText()).includes('網路軟體')) throw new Error('zh-TW browser language did not trigger conversion');

  await page.locator('#wpcc_translate_type').selectOption('');
  await page.waitForLoadState('networkidle');
  if (!(await contentText()).includes('网络软件和后台打印机')) throw new Error('session no-conversion did not restore original DOM');

  const sessionCookie = (await context.cookies()).find((cookie) => cookie.name.startsWith('wpcc_is_redirect_'));
  if (!sessionCookie || sessionCookie.value !== '1') throw new Error('wpcc_is_redirect session cookie was not set');

  await page.reload({ waitUntil: 'networkidle' });
  if (!(await contentText()).includes('网络软件和后台打印机')) throw new Error('session cookie did not suppress browser reconversion');
  console.log(JSON.stringify({ browserRedirect: true, sessionCookie: true, reloadStayedOriginal: true, finalUrl: page.url() }));
  await browser.close();
})().catch((error) => { console.error(error); process.exit(1); });
