const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

function formatDate(dateStr) {
  const [y, m, d] = dateStr.split("-");
  return `${d}/${m}/${y}`;
}

// ======================================================
// ================= START PROCESS ======================
// ======================================================
async function runBSI(accounts, startDateRaw, endDateRaw, credential) {

  const CORP_ID = credential.client_id;
  const USER_ID = credential.user_id;
  const PASSWORD = credential.password;

  console.log("BSI >> Start browser...");

  browser = await chromium.launch({
    headless: false,
    args: [
      '--no-sandbox',
      '--disable-setuid-sandbox',
      '--disable-dev-shm-usage',
      '--disable-blink-features=AutomationControlled'
    ]
  });

  const context = await browser.newContext({
    acceptDownloads: true,
    viewport: { width: 1366, height: 768 },
    userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120 Safari/537.36'
  });

  const page = await context.newPage();

  page.setDefaultTimeout(300000);
  page.setDefaultNavigationTimeout(300000);

  console.log("BSI >> Membuka halaman login...");
    await page.goto('https://cuz.bankbsi.co.id/', {
    waitUntil: 'domcontentloaded'
  });

  await page.waitForSelector('#custId', { state: 'visible' });

  await page.fill('#custId', CORP_ID);
  await page.fill('#userId', USER_ID);
  await page.fill('#password', PASSWORD);

  // ================= AMBIL CAPTCHA =================
  const captchaBuffer = await page.locator('#captcha img').screenshot();
  const captchaBase64 = captchaBuffer.toString('base64');

  return {
    captcha: `data:image/png;base64,${captchaBase64}`,
    session: {
      browser,
      page,
      accounts,
      startDateRaw,
      endDateRaw
    }
  };
}

// ======================================================
// ================= CONTINUE PROCESS ===================
// ======================================================
async function continueBSIProcess(session, captchaInput) {

  const { browser, page, accounts, startDateRaw, endDateRaw } = session;

  const startDate = formatDate(startDateRaw);
  const endDate = formatDate(endDateRaw);

  try {

    console.log("BSI >> Mengirim captcha...");

    await page.fill('#capInput', captchaInput);
    await page.click('.button-login');
    
    await page.waitForURL('**/dashboard**', { timeout: 120000 });
    console.log('BSI >> Login berhasil!');

    // ================= LOOP ACCOUNT =================
    for (const acc of accounts) {

      const noRek = acc.no_rek;
      console.log("BSI >> Proses rekening:", noRek);

      await page.goto('https://cuz.bankbsi.co.id/accountstatement/viewreport');
      await page.waitForLoadState('networkidle');
      await page.waitForSelector('#formInput');
      console.log("BSI >> Account Statements Page Loaded");

      // Type Regular
      await page.selectOption('#acctype', '1');

      // Filter by account
      await page.locator('input[name="accfilter"][value="1"]').check();

      // ================= SELECT2 =================
      await page.click('#select2-acctarget-container');

      const searchBox = page.locator('.select2-search__field');
      await searchBox.waitFor({ state: 'visible' });
      await searchBox.fill(noRek);

      await page.locator('.select2-results__option', {
        hasText: noRek
      }).click();

      console.log("BSI >> Rekening dipilih:", noRek);

      // ================= ISI TANGGAL =================
      await page.evaluate(() => {
        document.querySelector('#accdatefrom').removeAttribute('readonly');
        document.querySelector('#accdateto').removeAttribute('readonly');
      });

      await page.fill('#accdatefrom', startDate);
      await page.fill('#accdateto', endDate);

      console.log("BSI >> Tanggal:", startDate, "-", endDate);

      // ================= FILE NAME =================
      const [yyyy, mm, dd] = endDateRaw.split("-");
      const yy = yyyy.slice(-2);

      const fileName = `(BSI)${noRek}_${dd}${mm}${yy}.csv`;

      // ================= SUBMIT =================
      await Promise.all([
        page.waitForResponse(resp =>
          resp.url().includes('/viewreport') && resp.status() === 200
        ),
        page.click('#submitButton')
      ]);

      console.log("BSI >> Submit berhasil");

      // ================= EXPORT =================
      const exportToggle = page.locator('a.dropdown-toggle:has-text("Export File")');

      await exportToggle.waitFor({ state: 'visible', timeout: 60000 });
      await exportToggle.click();

      const [download] = await Promise.all([
        page.waitForEvent('download'),
        page.locator('.dropdown-menu .dropdown-item', {
          hasText: 'CSV'
        }).click()
      ]);

      const dir = path.join(__dirname, 'statement');
      if (!fs.existsSync(dir)) fs.mkdirSync(dir, { recursive: true });

      const filePath = path.join(dir, fileName);

      await download.saveAs(filePath);

      console.log("BSI >> Download selesai:", fileName);
    }

    // ================= LOGOUT =================
    console.log("BSI >> Logout...");

    await page.locator('a.nav-link[href="#modalLogout"]').click();
    const modal = page.locator('#modalLogout');
    await modal.waitFor({ state: 'visible' });

    await Promise.all([
      page.waitForNavigation({ waitUntil: 'load' }),
      modal.locator('button[type="submit"]').click()
    ]);

    console.log("BSI >> Logout berhasil");

    await browser.close();

  } catch (err) {

    console.error("BSI >> ERROR:", err.message);
    await page.locator('a.nav-link[href="#modalLogout"]').click();
    const modal = page.locator('#modalLogout');
    await modal.waitFor({ state: 'visible' });

    await Promise.all([
      page.waitForNavigation({ waitUntil: 'load' }),
      modal.locator('button[type="submit"]').click()
    ]);

    console.log("BSI >> Logout berhasil");
    await browser.close();
    throw err;
  }
}


module.exports = {
  runBSI,
  continueBSIProcess
};