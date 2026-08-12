const { chromium } = require('playwright');

let browser;
let page;

async function main() {
  try {
    console.log('🚀 Memulai browser...');
    
    browser = await chromium.launch({
        headless: false,
        slowMo: 100
    });

    const context = await browser.newContext({ acceptDownloads: true });
    page = await context.newPage();

    page.setDefaultTimeout(300000);
    page.setDefaultNavigationTimeout(300000);

    // ================= LOGIN STEP 1 =================
    console.log('🔐 Login CIMB NIAGA');
    await page.goto('https://vpn.klikbca.com/+CSCOE+/logon.html');

    // ================= TUNGGU FORM SIAP =================
    await page.waitForSelector('#unicorn_form', { state: 'visible' });
    console.log('✅ Form login siap');

    // ================= ISI USERNAME & PASSWORD =================
    const username = 'KBCINDOMARMDN082';
    const password = '45671409';

    await page.fill('#username', username);
    await page.fill('#password_input', password);

    console.log('✍️ Username & password terisi');

    // ================= KLIK LOGIN =================
    await Promise.all([
      page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
      page.click('#submit')
    ]);

    console.log('🎉 Login dikirim, menunggu halaman setelah login...');

  } catch (err) {
    console.error('❌ ERROR:', err);
    // await gracefulClose();
  }
}

async function gracefulClose() {
  if (browser) await browser.close();
}

process.on('SIGINT', async () => {
  await gracefulClose();
  process.exit();
});

main();