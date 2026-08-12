const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

function formatDate(dateStr) {
  const [y, m, d] = dateStr.split("-");
  return `${d}/${m}/${y}`;
}

async function runBNI(accounts, startDateRaw, endDateRaw, credential) {

  const CORP_ID = credential.client_id;
  const USER_ID = credential.user_id;
  const PASSWORD = credential.password;

  const startDate = formatDate(startDateRaw);
  const endDate = formatDate(endDateRaw);

  let browser;
  let page;

  try {

    console.log("BNI >> Proses Download Mutasi BNI");

    browser = await chromium.launch({
      headless: true,
      args: ['--ignore-certificate-errors']
    });

    const context = await browser.newContext({ acceptDownloads: true });
    page = await context.newPage();

    page.setDefaultTimeout(60000);
    page.setDefaultNavigationTimeout(60000);

    // ================= LOGIN =================
    console.log("BNI >> Login...");
    await page.goto('https://bnidirect.bni.co.id/corp/common/login.do?action=loginRequest');

    await page.fill('#corpId_placeholder1', CORP_ID);
    await page.fill('#userName_placeholder1', USER_ID);
    await page.fill('#password_placeholder', PASSWORD);

    await Promise.all([
      page.waitForLoadState('domcontentloaded'),
      page.click('input[name="submit1"]')
    ]);

    // ===== HANDLE MODAL SESSION =====
    await page.waitForTimeout(2000);

    const modalVisible = await page.evaluate(() => {
      const el = document.querySelector('#myModal');
      return el && window.getComputedStyle(el).display !== 'none';
    });

    if (modalVisible) {
      await page.evaluate(() => {
        if (typeof onStillLoginClick === 'function') {
          onStillLoginClick();
        }
      });
    }

    await page.waitForSelector('iframe#sidebar');

    console.log("BNI >> Login sukses");

    await page.waitForLoadState('networkidle');
    await page.waitForSelector('iframe#sidebar', { state: 'visible' });

    // ================= NAVIGASI MENU =================
    const menuFrame = page.frameLocator('iframe#sidebar');

    await menuFrame.locator('#section_two_menu').waitFor();

    const parentMenu = menuFrame.locator('#parent-MNU_GCME_040000');

    for (let i = 0; i < 3; i++) {
      await parentMenu.click({ force: true });
      const expanded = await parentMenu.getAttribute('aria-expanded');
      if (expanded === 'true') break;
      await page.waitForTimeout(500);
    }

    await menuFrame.locator('#sub-parent-MNU_GCME_040000').click({ force: true });
    await menuFrame.locator('#child-MNU_GCME_040200').click();

    const frameLocator = page.frameLocator('iframe[name="mainFrame"]');
    await frameLocator.locator('form[name="TransactionInquiryActionForm"]').waitFor();
    console.log("BNI >> Masuk Menu Account Statement");

    // ================= LOOP ACCOUNT =================
    for (const acc of accounts) {

      const noRek = acc.no_rek;
      console.log("BNI >> Proses rekening:", noRek);

      await frameLocator.locator('input[name="transferDateDisplay1"]').fill(startDate);
      await frameLocator.locator('input[name="transferDateDisplay2"]').fill(endDate);

      await frameLocator.locator('input[name="accountDisplay"]').fill(noRek);
      await frameLocator.locator('input[name="accountDisplay"]').press('Tab');

      await frameLocator.locator('select[name="customFile"]').selectOption('CSV');

      const requestTime = new Date();

      await frameLocator.locator('input[name="download1"]').click();
      console.log("BNI >> Berhasil Submit Mutasi, Preparing Download");

      await frameLocator.locator('text=Permintaan anda').waitFor();

      // ===== BUKA MENU DOWNLOAD =====
      const sidebarFrame = await page.frame({ name: 'menuFrame' });
      await sidebarFrame.evaluate(() => {
        changePage('child-MNU_GCME_180800', 'MNU_GCME_180800');
      });

      const laporanFrame = page.frameLocator('iframe[name="mainFrame"]');
      await laporanFrame.locator('form[name="DownloadReportActionForm"]').waitFor();

      const row = await waitReportReady(page, laporanFrame, requestTime);

      // ===== JIKA STATUS GAGAL =====
      if (!row) {
        console.log("BNI >> Proses dihentikan karena status report Gagal");
        break;
      }

      const link = row.locator('a').first();

      const [download] = await Promise.all([
        page.waitForEvent('download'),
        link.click()
      ]);

      const [yyyy, mm, dd] = endDateRaw.split('-');
      const yy = yyyy.slice(-2);

      const fileName = `(BNI)${noRek}_${dd}${mm}${yy}.csv`;

      const dir = path.join(__dirname, 'statement');
      if (!fs.existsSync(dir)) fs.mkdirSync(dir, { recursive: true });

      await download.saveAs(path.join(dir, fileName));

      console.log("BNI >> Download selesai:", fileName);
    }

    // ================= LOGOUT =================
    await page.goto('https://bnidirect.bni.co.id/corp/common/login.do?action=logout');
    console.log("BNI >> Logout");
    await browser.close();

  } catch (err) {
    console.error("BNI RUNNER ERROR:", err);
    if (browser) await browser.close();
    throw err;
  }
}

// ================= LOOP CARI REPORT =================
async function waitReportReady(page, frame, requestTime) {

  const timeout = 120000;
  const start = Date.now();

  while (Date.now() - start < timeout) {

    console.log('BNI >> 🔍 Klik cari...');
    await frame.locator('input[name="search"]').click();
    await page.waitForTimeout(3000);

    const row = await findReportRow(frame, requestTime);

    if (!row) continue;

    const status = await row.locator('td').nth(5).innerText();
    console.log('BNI >> 📊 Status:', status);

    // ===== STATUS SUKSES =====
    if (status.toLowerCase().includes('sukses')) {
      console.log('BNI >> ✅ Report siap');
      return row;
    }

    // ===== STATUS GAGAL =====
    if (status.toLowerCase().includes('gagal')) {
      console.log('BNI >> ❌ Report gagal dibuat');
      return null;
    }

    console.log('BNI >> ⌛ Menunggu report selesai...');
    await page.waitForTimeout(5000);
  }

  throw new Error("Report tidak ditemukan");
}

// ================= CARI ROW =================
async function findReportRow(frame, requestTime) {

  const rows = frame.locator('tr.BNI-Row-DataList');
  const count = await rows.count();

  const minTime = new Date(requestTime.getTime() - 10000);
  const maxTime = new Date(requestTime.getTime() + 10000);

  for (let i = 0; i < count; i++) {

    const row = rows.nth(i);

    const laporan = await row.locator('td').nth(2).innerText();
    const tanggal = await row.locator('td').nth(3).innerText();
    const format = await row.locator('td').nth(4).innerText();

    if (!laporan.includes('Mutasi Rekening')) continue;
    if (!format.toLowerCase().includes('csv')) continue;

    const parsedDate = new Date(tanggal.replace(/-/g, ' '));

    if (parsedDate >= minTime && parsedDate <= maxTime) {
      return row;
    }
  }

  return null;
}

module.exports = { runBNI };