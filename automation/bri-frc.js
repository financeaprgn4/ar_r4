const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

let browser;
let page;
let isLoggedIn = false;

async function main() {
  try {

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

    console.log('BRI FRC >> Membuka halaman login...');

    await page.goto(
      'https://ibank.bri.co.id/cms/Logon.aspx',
      {
        waitUntil: 'domcontentloaded'
      }
    );

    await page.waitForSelector('#ClientID');

    // ==========================================
    // HANDLE POPUP PENGUMUMAN
    // ==========================================

    const isAda = await page
      .locator('#isAdaPengumunan')
      .inputValue()
      .catch(() => '');

    if (isAda === 'ADA') {
      console.log('BRI FRC >> Popup pengumuman terdeteksi');

      await page.keyboard.press('Escape');
      await page.waitForTimeout(1000);
    }

    // ==========================================
    // LOGIN
    // ==========================================

    await page.fill('#ClientID', 'INDOMARCO PRISMATAMA');
    await page.fill('#UserID', 'PTK007');
    await page.fill('#Password', 'Ptk12345');

    await page.click('#btnLogin');

    await page.waitForLoadState('networkidle');

    const tokenVisible = await page
      .locator('#token')
      .isVisible()
      .catch(() => false);

    if (tokenVisible) {
      console.log(
        'BRI FRC >> Token diperlukan, isi token manual...'
      );

      await page.waitForTimeout(60000);
    }

    console.log('BRI FRC >> Login berhasil');
    isLoggedIn = true;

    // ==========================================
    // HEADER FRAME
    // ==========================================

    let headerFrame;

    for (let i = 0; i < 20; i++) {

      headerFrame = page.frames().find(frame =>
        frame.url().includes('Header.aspx')
      );

      if (headerFrame) {
        break;
      }

      await page.waitForTimeout(500);
    }

    if (!headerFrame) {
      throw new Error(
        'Header frame tidak ditemukan'
      );
    }

    await headerFrame.click(
      'a:has-text("Account Information")'
    );

    await page.waitForTimeout(2000);

    // ==========================================
    // LEFT MENU FRAME
    // ==========================================

    const menuFrame = page.frames().find(frame =>
      frame.url().includes('LeftMenu.aspx')
    );

    if (!menuFrame) {
      throw new Error(
        'LeftMenu frame tidak ditemukan'
      );
    }

    await menuFrame
      .locator('td.menu', {
        hasText: 'ACCOUNT INFORMATION'
      })
      .click();

    await menuFrame.waitForSelector(
      'a:has-text("Account Statement")',
      {
        timeout: 10000
      }
    );

    await menuFrame
      .locator('a', {
        hasText: 'Account Statement'
      })
      .click();

    // ==========================================
    // FRAME CHANNEL
    // ==========================================

    let contentFrame;

    for (let i = 0; i < 20; i++) {

      contentFrame = page.frame({
        name: 'channel'
      });

      if (contentFrame) {
        break;
      }

      await page.waitForTimeout(500);
    }

    if (!contentFrame) {
      throw new Error(
        'Frame channel tidak ditemukan'
      );
    }

    console.log(
      'BRI FRC >> Frame channel ditemukan'
    );

    // ==========================================
    // DATA REKENING
    // ==========================================

    const rekeningList = [
      {
        no_rek: '020601013466306',
        kd_toko: 'FAJH'
      },
      {
        no_rek: '020601013463308',
        kd_toko: 'FB8R'
      },
      {
        no_rek: '020601013469304',
        kd_toko: 'FCBE'
      },
      {
        no_rek: '020601013468308',
        kd_toko: 'FDQG'
      },
      {
        no_rek: '020601013464304',
        kd_toko: 'FIOY'
      },
      {
        no_rek: '020601013467302',
        kd_toko: 'FNZS'
      },
      {
        no_rek: '020601013465300',
        kd_toko: 'FZ2L'
      },
      {
        no_rek: '020601014097304',
        kd_toko: 'FH5G'
      },
      {
        no_rek: '020601014098300',
        kd_toko: 'FJB0'
      },
      {
        no_rek: '020601014099306',
        kd_toko: 'FHND'
      },
      {
        no_rek: '020601014100301',
        kd_toko: 'FG8U'
      },
      {
        no_rek: '020601014101307',
        kd_toko: 'FFCM'
      },
      {
        no_rek: '020601014102303',
        kd_toko: 'F0I2'
      },
      {
        no_rek: '020601014103309',
        kd_toko: 'F2YB'
      },
      {
        no_rek: '020601014104305',
        kd_toko: 'F3HT'
      },
      {
        no_rek: '020601014105301',
        kd_toko: 'F45X'
      },
      {
        no_rek: '020601014106307',
        kd_toko: 'F9LP'
      },
      {
        no_rek: '020601014107303',
        kd_toko: 'FJCM'
      },
      {
        no_rek: '020601014108309',
        kd_toko: 'FLYQ'
      },
      {
        no_rek: '020601014109305',
        kd_toko: 'FUU6'
      },
      {
        no_rek: '020601014422305',
        kd_toko: 'FRE2'
      },
      {
        no_rek: '020601014110306',
        kd_toko: 'FVL4'
      },
      {
        no_rek: '020601014423301',
        kd_toko: 'FTJV'
      },
      {
        no_rek: '020601014111302',
        kd_toko: 'FZLY'
      },
      {
        no_rek: '020601014416304',
        kd_toko: 'FZ44'
      },
      {
        no_rek: '020601014112308',
        kd_toko: 'FYX9'
      },
      {
        no_rek: '020601014819308',
        kd_toko: 'FG40'
      },
      {
        no_rek: '020601015159303',
        kd_toko: 'F8BT'
      },
      {
        no_rek: '020601015322304',
        kd_toko: 'FYYM'
      }
    ];

    const startDate = '01/06/2026';
    const endDate = '30/06/2026';

    const statementDir = path.join(
      __dirname,
      'statement'
    );

    if (!fs.existsSync(statementDir)) {
      fs.mkdirSync(statementDir, {
        recursive: true
      });
    }

    const parts = endDate.split('/');

    const ddmmyy =
      parts[0] +
      parts[1] +
      parts[2].slice(-2);

    // ==========================================
    // LOOP DOWNLOAD
    // ==========================================

    for (const rekening of rekeningList) {
      const noRek = rekening.no_rek;
      const kdToko = rekening.kd_toko;

      try {
        console.log(`BRI FRC >> Proses ${kdToko} - ${noRek}`);

        // Bersihkan field
        await contentFrame
          .locator(
            '#ctl00_TransactionForm_txtNoRek'
          )
          .fill('');

        // Isi rekening
        await contentFrame.fill(
          '#ctl00_TransactionForm_txtNoRek',
          noRek
        );

        await contentFrame.dispatchEvent(
          '#ctl00_TransactionForm_txtNoRek',
          'change'
        );

        // Tanggal awal
        await contentFrame.fill(
          '#ctl00_TransactionForm_txtstartdate',
          startDate
        );

        await contentFrame.dispatchEvent(
          '#ctl00_TransactionForm_txtstartdate',
          'change'
        );

        // Tanggal akhir
        await contentFrame.fill(
          '#ctl00_TransactionForm_txtfindate',
          endDate
        );

        await contentFrame.dispatchEvent(
          '#ctl00_TransactionForm_txtfindate',
          'change'
        );

        // Ledger
        const checked =
          await contentFrame.isChecked(
            '#ctl00_TransactionForm_rdioLedger'
          );

        if (!checked) {
          await contentFrame.check(
            '#ctl00_TransactionForm_rdioLedger'
          );
        }

        // Submit
        await contentFrame.click(
          '#ctl00_TransactionForm_btnSubmit'
        );

        // Tunggu report
        await page.waitForTimeout(7000);

        // ISI SESUAI FORMAT YANG DIINGINKAN
        await contentFrame.selectOption(
          '#ctl00_TransactionForm_ReportViewer1_ctl01_ctl05_ctl00',
          {
            value: 'PDF'
          }
        );

        // PASTIKA EXTENSI SAMA DENGAN VALUE TIPE DOC
        const fileName =
          `BRI_MTS_IDM_${kdToko}_${ddmmyy}.pdf`;

        const fullPath = path.join(
          statementDir,
          fileName
        );

        console.log(
          `BRI FRC >> Download ${fileName}`
        );

        const [download] =
          await Promise.all([
            page.waitForEvent('download'),
            contentFrame.click(
              '#ctl00_TransactionForm_ReportViewer1_ctl01_ctl05_ctl01'
            )
          ]);

        await download.saveAs(fullPath);

        console.log(
          `BRI FRC >> Sukses ${fileName}`
        );

        await page.waitForTimeout(2000);

      } catch (err) {

        console.error(
          `Gagal ${noRek}`,
          err.message
        );

        continue;
      }
    }

    console.log(
      'BRI FRC >> Semua rekening selesai diproses'
    );

    // ==========================================
    // LOGOUT
    // ==========================================

    let headerFrameLogout;

    for (let i = 0; i < 20; i++) {

      headerFrameLogout =
        page.frames().find(frame =>
          frame.url().includes('Header.aspx')
        );

      if (headerFrameLogout) {
        break;
      }

      await page.waitForTimeout(500);
    }

    if (headerFrameLogout) {

      await headerFrameLogout.click(
        '#btnLogout'
      );

      await page.waitForURL(
        '**/Logon.aspx',
        {
          timeout: 20000
        }
      );

      console.log('Logout berhasil');
    }

    await browser.close();

    console.log('Browser ditutup');

  } catch (err) {

    console.error(err);

    await gracefulClose();
  }
}

async function doLogout() {

  try {

    if (!isLoggedIn || !page) {
      return;
    }

    console.log('BRI FRC >> Logout otomatis...');

    let headerFrameLogout;

    for (let i = 0; i < 20; i++) {

      headerFrameLogout = page.frames().find(
        frame => frame.url().includes('Header.aspx')
      );

      if (headerFrameLogout) {
        break;
      }

      await page.waitForTimeout(500);
    }

    if (headerFrameLogout) {

      await headerFrameLogout.click('#btnLogout');

      await page.waitForURL(
        '**/Logon.aspx',
        {
          timeout: 10000
        }
      ).catch(() => {});

      console.log(
        'BRI FRC >> Logout berhasil'
      );
    }

    isLoggedIn = false;

  } catch (err) {

    console.log(
      'BRI FRC >> Logout gagal:',
      err.message
    );
  }
}

async function gracefulClose() {

  try {

    await doLogout();

  } catch (err) {}

  if (browser) {

    try {

      await browser.close();

    } catch (err) {}

  }
}

process.on('SIGINT', async () => {

  console.log(
    '\nBRI FRC >> Ctrl+C terdeteksi'
  );

  await gracefulClose();

  process.exit(0);
});

main();