const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

// ================= FORMAT REPORT NAME =================
function formatReportName(fromDate, toDate) {
  const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];

  const format = (d) => {
    return `${d.d.padStart(2,'0')}${months[parseInt(d.m)-1]}${d.y.slice(-2)}`;
  };

  return `${format(fromDate)}_to_${format(toDate)}`;
}

// ================= MAIN RUNNER =================
async function runNIAGA(accounts, startDateRaw, endDateRaw, credential) {
  
  const CORP_ID = credential.client_id;
  const USER_ID = credential.user_id;
  const PASSWORD = credential.password;

  let browser;
  let page;

  try {
    console.log("NIAGA >> Start Browser...");

    browser = await chromium.launch({
      headless: true,
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

    page = await context.newPage();

    page.setDefaultTimeout(60000);
    page.setDefaultNavigationTimeout(60000);

    // ================= LOGIN =================
    for (let i = 0; i < 5; i++) {
      try {
        await page.goto('https://bizchannel.cimbniaga.co.id/corp/common2/login.do?action=loginRequest', { waitUntil: 'domcontentloaded' });
        await page.waitForLoadState('networkidle');

        await page.waitForSelector('input[name="bizCorpId"]', {
          timeout: 15000
        });

        loaded = true;
        break;

      } catch {
        console.log('⏳ Retry load login (headless issue)');
        await page.waitForTimeout(3000);
      }
    }

    if (!loaded) throw new Error('❌ Login page gagal load di headless');

    await page.fill('input[name="bizCorpId"]', CORP_ID);
    await page.fill('input[name="bizUserName"]', USER_ID);

    const maxRetry = 5;
    let passwordVisible = false;

    for (let i = 0; i < maxRetry; i++) {
      console.log(`NIAGA >> Percobaan Continue ke-${i + 1}`);

      await page.click('input[name="submit1"]');

      try {
        await page.waitForSelector('input[name="bizPasswordEncryption"]', {
          timeout: 5000
        });
        passwordVisible = true;
        // console.log('✅ Halaman password sudah muncul');
        break;
      } catch {
        console.log('⏳ Password belum muncul, coba klik Continue lagi...');
      }
    }

    if (!passwordVisible) {
      throw new Error('❌ Halaman password tidak muncul setelah beberapa percobaan');
    }
    
    await page.fill('input[name="bizPasswordEncryption"]', PASSWORD);

    const maxRetryLogin = 5;
    let loginSuccess = false;

    for (let i = 0; i < maxRetryLogin; i++) {
      console.log(`NIAGA >> 🔁 Percobaan login ke-${i + 1}`);

      await page.click('input[name="submit1"][value="Login"]');

      try {
        // tunggu salah satu indikator login sukses
        await Promise.race([
          page.waitForSelector('frame[name="mainFrame"]', { timeout: 8000 }),
        ]);

        loginSuccess = true;
        console.log("NIAGA >> ✅ Login sukses");
        break;

      } catch {
        console.log('NIAGA >> ⏳ Halaman belum berubah, klik login lagi...');
      }
    }

    const menuFrame = page.frameLocator('frame[name="menuFrame"]');
    
    // ================= LOOP ACCOUNT =================
    for (const acc of accounts) {

      const norek = acc.no_rek;
      console.log("NIAGA >> Proses rekening:", norek);

      // ================= OPEN MENU =================
      const maxRetryMenu = 5;
      let menuOpened = false;

      for (let i = 0; i < maxRetryMenu; i++) {
        console.log(`NIAGA >> 🔁 Percobaan buka Account Information ke-${i + 1}`);

        const menu = menuFrame.locator('div.Menu', { hasText: 'Account Information' });

        try {
            await menu.scrollIntoViewIfNeeded();
            await menu.click({ force: true });

            // tunggu submenu muncul
            await menuFrame.locator('#subs8').waitFor({ timeout: 5000 });

            menuOpened = true;
            break;

        } catch {
            console.log('⏳ Menu belum terbuka, retry...');
            await page.waitForTimeout(1500);
        }
      }

      if (!menuOpened) {
        throw new Error('❌ Gagal membuka menu Account Information');
      }

      await menuFrame.locator('#subs8').click();
      const inquiryFrame = page.frame({ name: 'mainFrame' });

      await inquiryFrame.waitForSelector('#transferDateDisplay1', {
        state: 'visible',
        timeout: 60000
      });

      // ================= FORMAT DATE =================
      const [y1, m1, d1] = startDateRaw.split("-");
      const [y2, m2, d2] = endDateRaw.split("-");

      const fromDate = { d: d1, m: m1, y: y1 };
      const toDate   = { d: d2, m: m2, y: y2 };

      const reportNameTarget = formatReportName(fromDate, toDate);

      // ================= SET FORM =================
      await inquiryFrame.evaluate(({ fromDate, toDate, norek }) => {

        document.querySelector('#transferDateDay1').value = fromDate.d;
        document.querySelector('#transferDateMonth1').value = fromDate.m;
        document.querySelector('#transferDateYear1').value = fromDate.y;

        document.querySelector('#transferDateDay2').value = toDate.d;
        document.querySelector('#transferDateMonth2').value = toDate.m;
        document.querySelector('#transferDateYear2').value = toDate.y;

        document.querySelector('input[name="accountDisplay"]').value = norek;
        document.querySelector('input[name="accountNumber"]').value = norek;

      }, { fromDate, toDate, norek });

      await inquiryFrame.selectOption('select[name="customFile"]', 'CSV');

      // ================= SUBMIT =================
      const maxRetrySubmit = 5;
      let requestSent = false;

      for (let i = 0; i < maxRetrySubmit; i++) {
        console.log(`NIAGA >> 🔁 Percobaan klik download ke-${i + 1}`);

        await inquiryFrame.locator('input[name="download1"]').click();

        try {
            await inquiryFrame.waitForSelector(
            'text=Your Download Request is Being Process',
            { timeout: 5000 }
            );

            console.log('NIAGA >> ✅ Request download berhasil dikirim');
            requestSent = true;
            break;

        } catch {
            console.log('NIAGA >> ⏳ Notif belum muncul, coba klik lagi...');
        }
      }

      if (!requestSent) {
        throw new Error('NIAGA >> ❌ Gagal klik download setelah beberapa percobaan');
      }

      // ================= OPEN REPORT =================
      const menuFrameAfter = page.frameLocator('frame[name="menuFrame"]');
      const maxRetryOpenReport = 5;

      let reportOpened = false;
      let reportFrame;

      for (let i = 0; i < maxRetryOpenReport; i++) {
        console.log(`NIAGA >> 🔁 Percobaan buka report ke-${i + 1}`);

        // klik menu
        await menuFrameAfter.locator('#subs9').click();

        try {
            reportFrame = page.frame({ name: 'mainFrame' });

            // tunggu text muncul
            await reportFrame.waitForSelector('text=Transaction Inquiry Reports', {
            timeout: 8000
            });

            console.log('NIAGA >> ✅ Berhasil masuk ke halaman Transaction Inquiry Reports');
            reportOpened = true;
            break;

        } catch {
            console.log('⏳ Halaman belum terbuka, coba lagi...');
            
            // kasih jeda biar tidak terlalu cepat
            await page.waitForTimeout(2000);
        }
      }

      // validasi akhir
      if (!reportOpened) {
        throw new Error('❌ Gagal membuka halaman Transaction Inquiry Reports setelah beberapa percobaan');
      }

      // ================= CARI ROW =================
      const row = reportFrame
        .locator('tr')
        .filter({
            has: reportFrame.locator(`a:has-text("${reportNameTarget}")`)
        })
        .first();

      await row.waitFor({ timeout: 60000 });
      await row.locator('td:nth-child(8)').waitFor({ hasText: 'Complete' });
      
      console.log("NIAGA >> Report siap");

      // ================= DOWNLOAD =================
      const tglAkhir = `${toDate.d}${toDate.m}${toDate.y.slice(-2)}`;
      const fileName = `(NIAGA)${norek}_${tglAkhir}.csv`;
      
      const dir = path.join(__dirname, 'statement');
      if (!fs.existsSync(dir)) {
        fs.mkdirSync(dir, { recursive: true });
      }

      const filePath = path.join(dir, fileName);
      const maxRetryDownload = 5;
      let downloadSuccess = false;

      for (let i = 0; i < maxRetryDownload; i++) {
        console.log(`NIAGA >> 🔁 Percobaan download ke-${i + 1}`);

        try {
            const [download] = await Promise.all([
            page.waitForEvent('download', { timeout: 10000 }),
            row.locator('a').click({ force: true })
            ]);

            await download.saveAs(filePath);

            console.log("NIAGA >> ✅ Download selesai:", fileName);
            downloadSuccess = true;
            break;

        } catch (err) {
            console.log('NIAGA >> ⏳ Download gagal / tidak terdeteksi, retry...');

            // delay biar tidak terlalu cepat
            await page.waitForTimeout(2000);
        }
      }

      if (!downloadSuccess) {
        throw new Error('NIAGA >> ❌ Gagal download file setelah beberapa percobaan');
      }else{
        await page.goto('https://bizchannel.cimbniaga.co.id/corp/common2/login.do?action=logout');
        console.log('NIAGA >> ✅ Logout Berhasil');
        await browser.close();
      }
    }

  } catch (err) {
    console.error("NIAGA RUNNER ERROR:", err);
    await page.goto('https://bizchannel.cimbniaga.co.id/corp/common2/login.do?action=logout');
    await browser.close();
    throw err;
  }
}

module.exports = { runNIAGA };