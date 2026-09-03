const { chromium } = require('playwright');

let browser;
let page;

async function main() {
  try {
    console.log('Memulai browser...');

    browser = await chromium.launch({
      headless: false,
      slowMo: 100
    });

    const context = await browser.newContext();
    page = await context.newPage();

    page.setDefaultTimeout(30000);
    page.setDefaultNavigationTimeout(30000);

    console.log('Membuka halaman login...');
    await page.goto('https://cuz.bankbsi.co.id/', {
      waitUntil: 'commit'
    });

    await page.waitForSelector('#custId', {
      state: 'visible',
      timeout: 0
    });

    // ===============================
    // LOGIN
    // ===============================
    await page.fill('#custId', '11690');
    await page.fill('#userId', 'IDM006');
    await page.fill('#password', 'Mdn1234');

    console.log('Silakan isi CAPTCHA lalu klik LOGIN...');

    await page.waitForURL('**/dashboard**', { timeout: 60000 });

    console.log('Login berhasil!');

    // ===============================
    // Masuk Account Statement
    // ===============================
    await page.goto('https://cuz.bankbsi.co.id/accountstatement/viewreport');
    await page.waitForLoadState('networkidle');

    console.log('Masuk halaman Account Statement');

    // ===============================
    // TUNGGU FORM SIAP
    // ===============================
    await page.waitForSelector('#formInput', { state: 'visible' });
    console.log('Form Account Statement siap');

    // ===============================
    // TYPE (Regular Account)
    // ===============================
    await page.selectOption('#acctype', '1');
    console.log('Type dipilih: Regular Account');


    // ===============================
    // FILTER BY → Account
    // ===============================
    await page.locator('input[name="accfilter"][value="1"]').check();
    console.log('Filter: Account');


    // ===============================
    // PILIH TARGET ACCOUNT
    // (contoh pilih berdasarkan text rekening)
    // ===============================
    const targetSelect = page.locator('#acctarget');

    // tunggu option load
    await targetSelect.waitFor();

    // ===============================
    // PILIH REKENING VIA UI SELECT2
    // ===============================
    const rekeningTujuan = '7169677268';

    // buka dropdown select2
    await page.click('#select2-acctarget-container');

    // tunggu field search muncul
    const searchBox = page.locator('.select2-search__field');
    await searchBox.waitFor({ state: 'visible' });

    // ketik nomor rekening
    await searchBox.fill(rekeningTujuan);

    // tunggu hasil muncul lalu klik
    await page.locator('.select2-results__option', {
      hasText: rekeningTujuan
    }).click();

    console.log('Rekening dipilih via UI:', rekeningTujuan);

    // ===============================
    // ISI TANGGAL
    // (field readonly → pakai evaluate)
    // ===============================
    const tanggalInput = '17/05/2026';

    await page.evaluate(() => {
      document.querySelector('#accdatefrom').removeAttribute('readonly');
      document.querySelector('#accdateto').removeAttribute('readonly');
    });

    await page.fill('#accdatefrom', '15/05/2026');
    await page.fill('#accdateto', '17/05/2026');

    console.log('Tanggal berhasil diisi');

    // 👉 simpan untuk nama file sebelum submit
    const [dd, mm, yyyy] = tanggalInput.split('/');
    const tglAkhir = `${dd}${mm}${yyyy.slice(-2)}`;

    const fileName = `(BSI)${rekeningTujuan}_${tglAkhir}.csv`;

    console.log('Nama file:', fileName);

    // ===============================
    // SUBMIT
    // ===============================
    await Promise.all([
      page.waitForResponse(resp =>
        resp.url().includes('/viewreport') && resp.status() === 200
      ),
      page.click('#submitButton')
    ]);

    console.log('Submit berhasil diklik, menunggu hasil...');

    // ===============================
    // CEK TOMBOL EXPORT FILE VISIBLE
    // ===============================
    console.log('Menunggu tombol Export File...');

    const exportToggle = page.locator('a.dropdown-toggle:has-text("Export File")');

    await exportToggle.waitFor({
      state: 'visible',
      timeout: 60000
    });

    console.log('Tombol Export File sudah muncul');

    // ===============================
    // KLIK EXPORT → CSV + DOWNLOAD
    // ===============================
    
    await exportToggle.click();

    console.log('Dropdown export terbuka');

    // klik CSV dan tangkap download
    const [download] = await Promise.all([
      page.waitForEvent('download'),
      page.locator('.dropdown-menu .dropdown-item', { hasText: 'CSV' }).click()
    ]);

    console.log('Download dimulai');

    // ===============================
    // SIMPAN FILE
    // ===============================
    const fs = require('fs');
    const path = require('path');

    const dir = path.join(__dirname, 'statement');
    if (!fs.existsSync(dir)) fs.mkdirSync(dir);

    const filePath = path.join(dir, fileName);

    await download.saveAs(filePath);

    console.log('File berhasil disimpan:', filePath);

    // ===============================
    // LOGOUT
    // ===============================
    console.log('Memulai proses logout...');

    // klik icon logout di navbar (lebih spesifik)
    await page.locator('a.nav-link[href="#modalLogout"]').click();

    // tunggu modal muncul
    const modal = page.locator('#modalLogout');
    await modal.waitFor({ state: 'visible' });

    console.log('Modal logout muncul');

    // klik tombol logout
    await Promise.all([
      page.waitForNavigation({ waitUntil: 'load' }),
      modal.locator('button[type="submit"]').click()
    ]);

    console.log('Logout berhasil');
    
    await gracefulClose();
    process.exit();
  } catch (err) {
    console.error('\n❌ TERJADI ERROR:');
    console.error(err);

    return; // penting → hentikan tanpa close browser
  }
}

process.on('SIGINT', async () => {
  console.log('\nDihentikan manual (Ctrl+C)');
  await gracefulClose();
  process.exit();
});

async function gracefulClose() {
  if (browser) {
    console.log('Menutup browser...');
    await browser.close();
  }
  console.log('Script selesai.');
}

main();
