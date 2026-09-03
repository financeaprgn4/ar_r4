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
    await page.goto('https://bizchannel.cimbniaga.co.id/corp/common2/login.do?action=loginRequest');

    await page.waitForSelector('input[name="bizCorpId"]');

    await page.fill('input[name="bizCorpId"]', 'id04154ipm');
    await page.fill('input[name="bizUserName"]', 'PRYATAX');

    console.log('✍️ Form company & user terisi');

    const maxRetry = 5;
    let passwordVisible = false;
    console.log('➡️ Klik Continue dengan pengecekan berkala...');

    for (let i = 0; i < maxRetry; i++) {
      console.log(`Percobaan Continue ke-${i + 1}`);

      await page.click('input[name="submit1"]');

      try {
        await page.waitForSelector('input[name="bizPasswordEncryption"]', {
          timeout: 5000
        });
        passwordVisible = true;
        console.log('✅ Halaman password sudah muncul');
        break;
      } catch {
        console.log('⏳ Password belum muncul, coba klik Continue lagi...');
      }
    }

    if (!passwordVisible) {
      throw new Error('❌ Halaman password tidak muncul setelah beberapa percobaan');
    }

    console.log('🔐 Mengisi password...');

    await page.fill('input[name="bizPasswordEncryption"]', 'Tax@111111');

    console.log('🔐 Mengirim login password dengan retry...');

    const maxRetryLogin = 5;
    let loginSuccess = false;

    for (let i = 0; i < maxRetryLogin; i++) {
      console.log(`➡️ Percobaan login ke-${i + 1}`);

      await page.click('input[name="submit1"][value="Login"]');

      try {
        // tunggu salah satu indikator login sukses
        await Promise.race([
          page.waitForSelector('frame[name="mainFrame"]', { timeout: 8000 }),
        ]);

        loginSuccess = true;
        console.log('✅ Login sukses, halaman berubah');
        break;

      } catch {
        console.log('⏳ Halaman belum berubah, klik login lagi...');
      }
    }

    console.log('⏳ Menunggu menuFrame siap...');

    const menuFrame = page.frameLocator('frame[name="menuFrame"]');

    const maxRetryMenu = 5;
    let menuOpened = false;

    for (let i = 0; i < maxRetryMenu; i++) {
      console.log(`🔁 Percobaan buka Account Information ke-${i + 1}`);

      const menu = menuFrame.locator('div.Menu', { hasText: 'Account Information' });

      try {
        await menu.scrollIntoViewIfNeeded();
        await menu.click({ force: true });

        // tunggu submenu muncul
        await menuFrame.locator('#subs8').waitFor({ timeout: 5000 });

        console.log('✅ Submenu tampil');
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

    // ================= KLIK TRANSACTION INQUIRY =================
    console.log('➡️ Membuka Transaction Inquiry...');
    await menuFrame.locator('#subs8').click();
    console.log('🎉 Transaction Inquiry diklik');


    // ================= AMBIL FRAME KONTEN =================
    const inquiryFrame = page.frame({ name: 'mainFrame' });

    console.log('🔎 Menunggu form Transaction Inquiry siap...');

    await inquiryFrame.waitForSelector('#transferDateDisplay1', {
      state: 'visible',
      timeout: 60000
    });

    console.log('✅ Form Transaction Inquiry sudah dimuat');

    // ================= 2️⃣ ISI TANGGAL =================
    const fromDate = { d: '22', m: '04', y: '2026' };
    const toDate   = { d: '22', m: '04', y: '2026' };

    function formatReportName(fromDate, toDate) {
      const months = [
        'Jan','Feb','Mar','Apr','May','Jun',
        'Jul','Aug','Sep','Oct','Nov','Dec'
      ];

      function format(d) {
        const day = d.d.padStart(2, '0');
        const month = months[parseInt(d.m) - 1];
        const year = d.y.slice(-2);
        return `${day}${month}${year}`;
      }

      return `${format(fromDate)}_to_${format(toDate)}`;
    }

    const reportNameTarget = formatReportName(fromDate, toDate);

    console.log('🎯 Target Report Name:', reportNameTarget);

    await inquiryFrame.evaluate(({ fromDate, toDate }) => {
      document.querySelector('#transferDateDay1').value = fromDate.d;
      document.querySelector('#transferDateMonth1').value = fromDate.m;
      document.querySelector('#transferDateYear1').value = fromDate.y;

      document.querySelector('#transferDateDay2').value = toDate.d;
      document.querySelector('#transferDateMonth2').value = toDate.m;
      document.querySelector('#transferDateYear2').value = toDate.y;
    }, { fromDate, toDate });

    console.log('📅 Tanggal berhasil diisi');


    // ================= 3️⃣ ISI ACCOUNT =================
    // user hanya punya nomor rekening
    const norek = '800071315100';

    await inquiryFrame.evaluate(() => {
      const norek = '800071315100';
      const nama  = 'INDOMARCO PRISMATAMA MDN REG 2420100665003';

      // isi semua hidden field seperti hasil picklist
      document.querySelector('input[name="accountDisplay"]').value = norek;
      document.querySelector('input[name="accountNumber"]').value = norek;
      document.querySelector('input[name="accountNm"]').value = nama;
      document.querySelector('input[name="currDisplay"]').value = 'IDR';
      document.querySelector('input[name="curr"]').value = 'IDR';
      document.querySelector('input[name="frOrganizationUnit"]').value = '27501';
      document.querySelector('input[name="accountTypeCode"]').value = 'D';

      // isi label pickList (INI KRUSIAL)
      document.querySelector('#pickList').innerText =
        ` - ${nama}(IDR)`;

      // trigger blur supaya event jalan
      document.querySelector('input[name="accountDisplay"]')
        .dispatchEvent(new Event('blur', { bubbles: true }));
    });

    console.log('🏦 Account berhasil diisi:', norek);

    // ================= 5️⃣ PILIH CUSTOM FILE FORMAT CSV =================
    console.log('⚙️ Memilih Custom File Format CSV...');
    await inquiryFrame.selectOption('select[name="customFile"]', 'CSV');
    console.log('✅ CSV berhasil dipilih');

    const maxRetrySubmit = 5;
    let requestSent = false;

    for (let i = 0; i < maxRetrySubmit; i++) {
      console.log(`⬇️ Percobaan klik download ke-${i + 1}`);

      await inquiryFrame.locator('input[name="download1"]').click();

      try {
        await inquiryFrame.waitForSelector(
          'text=Your Download Request is Being Process',
          { timeout: 5000 }
        );

        console.log('✅ Request download berhasil dikirim');
        requestSent = true;
        break;

      } catch {
        console.log('⏳ Notif belum muncul, coba klik lagi...');
      }
    }

    if (!requestSent) {
      throw new Error('❌ Gagal klik download setelah beberapa percobaan');
    }

    // ================= LANJUT KE TRANSACTION INQUIRY REPORT =================
    console.log('➡️ Masuk ke Transaction Inquiry Reports...');

    const menuFrameAfter = page.frameLocator('frame[name="menuFrame"]');
    const maxRetryOpenReport = 5;

    let reportOpened = false;
    let reportFrame;

    for (let i = 0; i < maxRetryOpenReport; i++) {
      console.log(`🔁 Percobaan buka report ke-${i + 1}`);

      // klik menu
      await menuFrameAfter.locator('#subs9').click();

      try {
        reportFrame = page.frame({ name: 'mainFrame' });

        // tunggu text muncul
        await reportFrame.waitForSelector('text=Transaction Inquiry Reports', {
          timeout: 8000
        });

        console.log('✅ Berhasil masuk ke halaman Transaction Inquiry Reports');
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

    const row = reportFrame
      .locator('tr')
      .filter({
        has: reportFrame.locator(`a:has-text("${reportNameTarget}")`)
      })
      .first();

    // tunggu row muncul
    await row.waitFor({ timeout: 60000 });

    // pastikan status Complete
    await row.locator('td:nth-child(8)').waitFor({ hasText: 'Complete' });

    console.log('✅ Row ditemukan & status Complete');

    // ===============================
    // DOWNLOAD + SIMPAN FILE (RETRY)
    // ===============================
    const fs = require('fs');
    const path = require('path');

    const tglAkhir = `${toDate.d.padStart(2,'0')}${toDate.m.padStart(2,'0')}${toDate.y.slice(-2)}`;
    const fileName = `(NIAGA)${norek}_${tglAkhir}.csv`;

    const dir = path.join(__dirname, 'statement');
    if (!fs.existsSync(dir)) {
      fs.mkdirSync(dir, { recursive: true });
    }

    const filePath = path.join(dir, fileName);

    // ===============================
    // RETRY DOWNLOAD
    // ===============================
    const maxRetryDownload = 5;
    let downloadSuccess = false;

    for (let i = 0; i < maxRetryDownload; i++) {
      console.log(`⬇️ Percobaan download ke-${i + 1}`);

      try {
        const [download] = await Promise.all([
          page.waitForEvent('download', { timeout: 10000 }),
          row.locator('a').click({ force: true })
        ]);

        console.log('✅ Download terdeteksi');

        await download.saveAs(filePath);

        console.log('✅ File berhasil disimpan:', filePath);
        downloadSuccess = true;
        break;

      } catch (err) {
        console.log('⏳ Download gagal / tidak terdeteksi, retry...');

        // delay biar tidak terlalu cepat
        await page.waitForTimeout(2000);
      }
    }

    // ===============================
    // VALIDASI
    // ===============================
    if (!downloadSuccess) {
      throw new Error('❌ Gagal download file setelah beberapa percobaan');
    }else{
      await page.goto('https://bizchannel.cimbniaga.co.id/corp/common2/login.do?action=logout');
      console.log('✅ Logout Berhasil');
      await gracefulClose();
      process.exit();
    }
  } catch (err) {
    console.error('❌ ERROR:', err);
    // await gracefulClose();
  }
}

async function gracefulClose() {
  if (browser) {
    console.log('Menutup browser...');
    await browser.close();
  }
  console.log('Script selesai.');
}

process.on('SIGINT', async () => {
  console.log('\nDihentikan manual (Ctrl+C)');
  await gracefulClose();
  process.exit();
});

main();