const { chromium } = require(process.env.WPCC_PLAYWRIGHT || '/home/parallels/Projects/wpbridge/node_modules/playwright');
const baseUrl = process.env.WPCC_SITE_URL || 'http://localhost:8890';
const pageId = process.env.WPCC_PAGE_ID;
const executablePath = process.env.WPCC_CHROMIUM || '/home/parallels/.cache/ms-playwright/chromium-1234/chrome-linux/chrome';
if (!pageId) throw new Error('WPCC_PAGE_ID is required');

(async () => {
  const browser = await chromium.launch({ headless: true, executablePath, args: ['--no-sandbox'] });
  const page = await browser.newPage();
  const contentText = async () => {
    const content = page.locator('.entry-content, .wp-block-post-content');
    return (await content.count() ? content.first() : page.locator('body')).innerText();
  };

  await page.goto(`${baseUrl}/?page_id=${pageId}`, { waitUntil: 'networkidle' });
  await page.locator('#wpcc_translate_type').waitFor();
  const originalText = await contentText();
  if (!originalText.includes('网络软件和后台打印机')) throw new Error('original simplified DOM missing');

  await page.locator('#wpcc_translate_type').selectOption('zh-tw');
  await page.waitForLoadState('networkidle');
  if (!(await contentText()).includes('網路軟體和後臺印表機')) throw new Error('traditional DOM missing');
  if (!page.url().includes('variant=zh-tw')) throw new Error('traditional URL missing variant');

  await page.locator('#wpcc_translate_type').selectOption('');
  await page.waitForLoadState('networkidle');
  const restoredText = await contentText();
  if (restoredText !== originalText) throw new Error('no-conversion did not exactly restore original content DOM text');
  if (page.url().includes('variant=zh-tw')) throw new Error('no-conversion kept zh-tw URL');

  await page.locator('#wpcc_translate_type').selectOption('zh-tw');
  await page.waitForLoadState('networkidle');
  await page.locator('#wpcc_translate_type').selectOption('zh-cn');
  await page.waitForLoadState('networkidle');
  if (!(await contentText()).includes('网络软件和后台打印机')) throw new Error('zh-cn switch did not restore simplified DOM');
  if (!page.url().includes('variant=zh-cn')) throw new Error('zh-cn URL missing variant');

  console.log(JSON.stringify({ noConversionExactRestore: true, zhCnRestored: true, finalUrl: page.url() }));
  await browser.close();
})().catch((error) => { console.error(error); process.exit(1); });
