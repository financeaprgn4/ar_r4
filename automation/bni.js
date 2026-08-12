const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

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

    // ================= LOGIN =================
    console.log('🔐 Login BNI...');
    await page.goto('https://bnidirect.bni.co.id/corp/common/login.do?action=loginRequest');

    await page.fill('#corpId_placeholder1', 'INDOMARET');
    await page.fill('#userName_placeholder1', 'MDNCP005');
    await page.fill('#password_placeholder', 'Medan@009');

    await Promise.all([
      page.waitForLoadState('domcontentloaded'),
      page.click('input[name="submit1"]')
    ]);

    // ================= HANDLE MODAL LOGIN GANDA =================
    console.log('🔎 Cek modal session aktif...');

    await page.waitForTimeout(2000); // beri waktu modal render

    const modalVisible = await page.evaluate(() => {
      const el = document.querySelector('#myModal');
      return el && window.getComputedStyle(el).display !== 'none';
    });

    if (modalVisible) {
      console.log('⚠️ Modal terdeteksi → trigger onStillLoginClick()');

      await page.evaluate(() => {
        if (typeof onStillLoginClick === 'function') {
          onStillLoginClick();
        }
      });

      // tunggu modal hilang
      await page.waitForFunction(() => {
        const el = document.querySelector('#myModal');
        return !el || el.style.display === 'none';
      });

      console.log('✅ Modal berhasil dilewati');
    } else {
      console.log('👍 Tidak ada modal');
    }

    await page.waitForSelector('iframe#sidebar');

    console.log('✅ Login berhasil');

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

    console.log('📄 Masuk halaman Mutasi');

    const frameLocator = page.frameLocator('iframe[name="mainFrame"]');
    await frameLocator.locator('form[name="TransactionInquiryActionForm"]').waitFor();

    // ================= TANGGAL =================
    const yesterday = new Date();
    yesterday.setDate(yesterday.getDate() - 1);

    const dd = String(yesterday.getDate()).padStart(2, '0');
    const mm = String(yesterday.getMonth() + 1).padStart(2, '0');
    const yyyy = yesterday.getFullYear();

    const dateStr = `${dd}/${mm}/${yyyy}`;

    await frameLocator.locator('input[name="transferDateDisplay1"]').fill(dateStr);
    await frameLocator.locator('input[name="transferDateDisplay2"]').fill(dateStr);

    await frameLocator.locator('input[name="accountDisplay"]').fill('1388882629');
    await frameLocator.locator('input[name="accountDisplay"]').press('Tab');

    await frameLocator.locator('select[name="customFile"]').selectOption('CSV');

    // ================= REQUEST DOWNLOAD =================
    const requestTime = new Date();
    console.log('🕒 Request time:', requestTime.toISOString());

    await frameLocator.locator('input[name="download1"]').click();
    await frameLocator.locator('text=Permintaan anda').waitFor();

    console.log('📨 Request laporan terkirim');

    // ================= BUKA MENU UNDUH =================
    const sidebarFrame = await page.frame({ name: 'menuFrame' });
    await sidebarFrame.evaluate(() => {
      changePage('child-MNU_GCME_180800', 'MNU_GCME_180800');
    });

    const laporanFrame = page.frameLocator('iframe[name="mainFrame"]');
    await laporanFrame.locator('form[name="DownloadReportActionForm"]').waitFor();

    console.log('📂 Halaman Unduh Laporan siap');

    // ================= LOOP CARI REPORT =================
    const foundRow = await waitReportReady(laporanFrame, requestTime);

    // ================= DOWNLOAD FILE =================
    console.log('⬇️ Download file...');
    const link = foundRow.locator('a').first();

    const [download] = await Promise.all([
      page.waitForEvent('download'),
      link.click()
    ]);

    const yy = String(yyyy).slice(-2);
    // format ddmmyy
    const dateDDMMYY = `${dd}${mm}${yy}`

    const fileName = `(BNI)1388882629_${dateDDMMYY}.csv`;

    const statementDir = path.join(__dirname, 'statement');
    if (!fs.existsSync(statementDir)) {
      fs.mkdirSync(statementDir, { recursive: true });
    }

    const fullPath = path.join(statementDir, fileName);
    await download.saveAs(fullPath);

    console.log('✅ Download selesai:', fileName);
    await page.goto('https://bnidirect.bni.co.id/corp/common/login.do?action=logout');
    await gracefulClose();
  } catch (err) {
    console.error('❌ ERROR:', err);
    await gracefulClose();
  }
}

// ================= FUNCTION LOOP =================
async function waitReportReady(laporanFrame, requestTime) {

  const timeout = 120000;
  const start = Date.now();

  while (Date.now() - start < timeout) {

    console.log('🔍 Klik cari...');
    await laporanFrame.locator('input[name="search"]').click();

    await page.waitForTimeout(3000); // ✅ FIX

    const row = await findReportRow(laporanFrame, requestTime);

    if (!row) {
      console.log('⏳ Report belum ada...');
      continue;
    }

    const status = await row.locator('td').nth(5).innerText();
    console.log('📊 Status:', status);

    if (status.toLowerCase().includes('sukses')) {
      console.log('✅ Report siap');
      return row;
    }

    console.log('⌛ Menunggu report selesai...');
    await page.waitForTimeout(5000); // ✅ FIX
  }

  throw new Error('Report tidak ditemukan dalam batas waktu');
}

// ================= CARI ROW =================
async function findReportRow(laporanFrame, requestTime) {

  const rows = laporanFrame.locator('tr.BNI-Row-DataList');
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
      console.log('📌 Row ditemukan');
      return row;
    }
  }

  return null;
}

// ================= SAFE CLOSE =================
async function gracefulClose() {
  if (browser) await browser.close();
}

process.on('SIGINT', async () => {
  await gracefulClose();
  process.exit();
});

main();