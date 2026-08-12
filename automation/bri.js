const { chromium } = require('playwright');

const fs = require('fs');
const path = require('path');

let browser;
let page;

async function main() {
  try {
    console.log('Memulai browser...');

    browser = await chromium.launch({
      headless: false,
      slowMo: 100
    });

    const context = await browser.newContext({
      acceptDownloads: true
    });

    page = await context.newPage();

    page.setDefaultTimeout(30000);
    page.setDefaultNavigationTimeout(30000);

    console.log('Membuka halaman login BRI...');
    await page.goto('https://ibank.bri.co.id/cms/Logon.aspx');

    await page.waitForSelector('#ClientID');

    // ===============================
    // Handle Popup Pengumuman (berdasarkan hidden field)
    // ===============================
    const isAda = await page.locator('#isAdaPengumunan').inputValue();

    if (isAda === 'ADA') {
      console.log('Popup pengumuman terdeteksi');

      // Tekan ESC untuk menutup thickbox
      await page.keyboard.press('Escape');

      await page.waitForTimeout(1000);
      console.log('Popup ditutup');
    }

    // ===============================
    // LOGIN
    // ===============================
    await page.fill('#ClientID', 'INDOMARCO PRISMATAMA');
    await page.fill('#UserID', 'mdnmeidy');
    await page.fill('#Password', 'Mdn12345');

    console.log('Klik login...');
    await page.click('#btnLogin');

    await page.waitForLoadState('networkidle');

    // Jika ada token
    const tokenVisible = await page.locator('#token').isVisible().catch(() => false);

    if (tokenVisible) {
      console.log('Token diperlukan. Silakan isi token manual...');
      await page.waitForTimeout(60000);
    }

    console.log('Login selesai.');

    // ===============================
    // 1. Klik "Account Information" dari HEADER FRAME
    // ===============================
    let headerFrame;

    for (let i = 0; i < 20; i++) {
      headerFrame = page.frames().find(f =>
        f.url().includes('Header.aspx')
      );

      if (headerFrame) break;

      await page.waitForTimeout(500);
    }

    if (!headerFrame) {
      throw new Error('Header frame tidak ditemukan setelah login');
    }

    console.log('Header frame:', headerFrame.url());

    await headerFrame.click('a:has-text("Account Information")');

    // Tunggu LeftMenu reload
    await page.waitForTimeout(2000);

    // ===============================
    // 2. Ambil LeftMenu Frame
    // ===============================

    const menuFrame = page.frames().find(f =>
      f.url().includes('LeftMenu.aspx')
    );

    if (!menuFrame) throw new Error('LeftMenu frame tidak ditemukan');

    console.log('Menu frame:', menuFrame.url());

    // ===============================
    // 3. Expand Parent "ACCOUNT INFORMATION"
    // ===============================

    await menuFrame.locator('td.menu', {
      hasText: 'ACCOUNT INFORMATION'
    }).click();

    console.log('Parent ACCOUNT INFORMATION diklik');

    // Tunggu submenu muncul
    await menuFrame.waitForSelector(
      'a:has-text("Account Statement")',
      { state: 'visible', timeout: 10000 }
    );

    console.log('Submenu terlihat');

    // ===============================
    // 4. Klik "Account Statement"
    // ===============================

    await menuFrame.locator('a', {
      hasText: 'Account Statement'
    }).click();

    console.log('Klik Account Statement');

    // ===============================
    // 5. Ambil Frame "channel"
    // ===============================
    let contentFrame;

    for (let i = 0; i < 20; i++) {
      contentFrame = page.frame({ name: 'channel' });
      if (contentFrame) break;
      await page.waitForTimeout(500);
    }

    if (!contentFrame) {
      throw new Error('Frame channel tidak ditemukan');
    }

    console.log('Masuk ke frame:', contentFrame.url());


    // ===============================
    // 6. Isi Account No
    // ===============================
    const noRek = '026601000795308';
    const startDate = '14/04/2026';
    const endDate = '14/04/2026';

    await contentFrame.fill(
      '#ctl00_TransactionForm_txtNoRek',
      noRek
    );

    // Trigger event (penting untuk ASP.NET)
    await contentFrame.dispatchEvent(
      '#ctl00_TransactionForm_txtNoRek',
      'change'
    );


    // ===============================
    // 7. Isi Start Date
    // ===============================
    await contentFrame.fill(
      '#ctl00_TransactionForm_txtstartdate',
      startDate
    );

    await contentFrame.dispatchEvent(
      '#ctl00_TransactionForm_txtstartdate',
      'change'
    );


    // ===============================
    // 8. Isi End Date
    // ===============================
    await contentFrame.fill(
      '#ctl00_TransactionForm_txtfindate',
      endDate
    );

    await contentFrame.dispatchEvent(
      '#ctl00_TransactionForm_txtfindate',
      'change'
    );


    // ===============================
    // 9. Pilih "With Ledger"
    // ===============================
    await contentFrame.check(
      '#ctl00_TransactionForm_rdioLedger'
    );

    console.log('Form terisi lengkap');


    // ===============================
    // 10. Submit
    // ===============================
    await contentFrame.click(
      '#ctl00_TransactionForm_btnSubmit'
    );

    console.log('Submit diklik');

    // ===============================
    // 11. Pilih Format CSV
    // ===============================
    await contentFrame.selectOption(
      '#ctl00_TransactionForm_ReportViewer1_ctl01_ctl05_ctl00',
      { value: 'CSV' }
    );

    console.log('Format CSV dipilih');


    // ===============================
    // 12. Klik Export & Tangkap Download
    // ===============================

    const [ download ] = await Promise.all([
      page.waitForEvent('download'),
      contentFrame.click(
        '#ctl00_TransactionForm_ReportViewer1_ctl01_ctl05_ctl01'
      )
    ]);

    console.log('Download dimulai...');

    // ===============================
    // 13. Generate nama file custom
    // ===============================

    // ubah 12/02/2026 → 120226
    const parts = endDate.split('/');
    const ddmmyy = parts[0] + parts[1] + parts[2].slice(-2);

    const fileName = `(BRI)${noRek}_${ddmmyy}.csv`;

    // ===============================
    // 14. Simpan file
    // ===============================    
    const statementDir = path.join(__dirname, 'statement');

    if (!fs.existsSync(statementDir)) {
      fs.mkdirSync(statementDir, { recursive: true });
    }

    const fullPath = path.join(statementDir, fileName);
    await download.saveAs(fullPath);

    console.log('File berhasil disimpan sebagai:', fileName);

    // ===============================
    // 15. Logout (via Header frame)
    // ===============================

    let headerFrameLogout;

    for (let i = 0; i < 20; i++) {
      headerFrameLogout = page.frames().find(f =>
        f.url().includes('Header.aspx')
      );

      if (headerFrameLogout) break;

      await page.waitForTimeout(500);
    }

    if (!headerFrameLogout) {
      throw new Error('Header frame untuk logout tidak ditemukan');
    }

    console.log('Melakukan logout...');

    await headerFrameLogout.click('#btnLogout');

    // Tunggu kembali ke login
    await page.waitForURL('**/Logon.aspx', { timeout: 20000 });

    console.log('Logout berhasil');

    await browser.close();
    console.log('Browser ditutup');

  } catch (err) {
    console.error('Terjadi error:', err);
    await gracefulClose();
  }
}

async function gracefulClose() {
  if (browser) {
    console.log('Menutup browser...');
    await browser.close();
  }
}

process.on('SIGINT', async () => {
  console.log('\nDihentikan manual (Ctrl+C)');
  await gracefulClose();
  process.exit();
});

main();