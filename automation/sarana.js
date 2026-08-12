const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

async function runSaranaAutomation(listRAB = []) {

  if (!Array.isArray(listRAB) || listRAB.length === 0) {
    throw new Error('List RAB kosong');
  }

  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ acceptDownloads: true });
  const page = await context.newPage();

  // =========================
  // LOGIN
  // =========================
  await page.goto('http://172.24.18.242/PPSP/Account/Login', {
    waitUntil: 'commit', // lebih cepat, tidak nunggu semua resource
    timeout: 0
  });

  // tunggu input username (indikator halaman siap)
  await page.waitForSelector('#UserName', {
    timeout: 0
  });

  // isi username & password
  await page.fill('#UserName', 'REGION4');
  await page.fill('#Password', '123456');

  // klik login + tunggu redirect
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'networkidle' }),
    page.click('input[type="submit"]')
  ]);

  console.log('SARANA >> ✔ Login berhasil');

  // =========================
  // 2. MASUK HALAMAN REPORT
  // =========================
  await page.goto('http://172.24.18.242/PPSP/Laporan/MonitorSaranaToko', {
    waitUntil: 'domcontentloaded'
  });

  console.log("Sarana >> Masuk Halaman Monitoring Sarana");

  // tunggu elemen utama (ini jadi indikator halaman siap)
  await page.waitForSelector('#periode1RAB', {
    timeout: 0
  });

  const saranaDir = path.join(__dirname, 'sarana');

  if (!fs.existsSync(saranaDir)) {
    fs.mkdirSync(saranaDir, { recursive: true }); 
  }

  // Bersihkan file lama
  const files = fs.readdirSync(saranaDir);
  for (const file of files) {
    if (file.endsWith('.xlsx')) {
      fs.unlinkSync(path.join(saranaDir, file));
    }
  }

  const d1 = '2020-01-01';
  const today = new Date().toISOString().split('T')[0];

  await page.fill('#periode1RAB', d1);
  await page.fill('#periode2RAB', today);
  await page.fill('#periode1', d1);
  await page.fill('#periode2', today);

  await page.evaluate(() => {
    $('#periode1RAB,#periode2RAB,#periode1,#periode2').trigger('change');
  });

  // tunggu sebentar biar UI stabil
  await page.waitForTimeout(500);

  // ✅ isi UNIT
  await page.evaluate(() => {
    // helper select2
    const setSelect2 = (selector, values) => {
      const $el = $(selector);

      // pastikan array
      const valArray = Array.isArray(values) ? values : [values];

      // set value langsung (karena option sudah ada)
      $el.val(valArray).trigger('change');
    };

    // 🔹 set UNIT
    setSelect2('#kodeUnit', '03'); // PT.INDOMARCO PRISMATAMA
  });

  const cabangValues = [
    { id: 'G009', text: 'MEDAN' },
    { id: 'G089', text: 'SEMARANG 2' },
    { id: 'G259', text: 'DEPO ACEH' },
    { id: 'G801', text: 'SEMARANG' }
  ];

  await page.evaluate((values) => {
    const $select = $('#cabang');

    // reset dulu
    $select.empty();

    values.forEach(item => {
      const option = new Option(item.text, item.id, true, true);
      $select.append(option);
    });

    // WAJIB: trigger change + select2 event
    $select.trigger('change');
    $select.trigger({
      type: 'select2:select',
      params: { data: values }
    });

  }, cabangValues);

  console.log("Sarana >> Berhasil input form");
  // tunggu lagi biar select2 render
  await page.waitForTimeout(500);

  // ✅ DEBUG (opsional, tapi sangat membantu)
  await page.evaluate(() => {
    console.log('kodeUnit:', $('#kodeUnit').val());
    console.log('cabang:', $('#cabang').val());
  });

  for (const kode of listRAB) {
    console.log('Download:', kode);

    await page.evaluate((kode) => {
      $('#KodeRABs')
        .empty()
        .append(new Option(kode, kode, true, true))
        .trigger('change');
    }, kode);
    
    const [download] = await Promise.all([
      page.waitForEvent('download'),
      page.click('button[name="ReportExcel"]')
    ]);

    const fileName = `${kode.replace(/\//g, '_')}.xlsx`;
    const fullPath = path.join(saranaDir, fileName);

    await download.saveAs(fullPath);

    console.log('SARANA >> ✔ Disimpan : ', fileName);
  }

  await page.goto('http://172.24.18.242/PPSP/Account/LogOff', {
    waitUntil: 'networkidle'
  });

  await browser.close();
}

module.exports = { runSaranaAutomation };


// ===== MODE CLI (langsung jalan kalau dipanggil node) =====
if (require.main === module) {
  (async () => {
    try {
      const arg = process.argv[2];

      if (!arg) throw new Error('Parameter RAB tidak ada');

      let list;

      if (arg.startsWith('[')) {
        list = JSON.parse(arg);
      } else if (arg.includes(',')) {
        list = arg.split(',');
      } else {
        list = [arg];
      }

      await runSaranaAutomation(list);
      process.exit(0);

    } catch (err) {
      console.error(err.message);
      process.exit(1);
    }
  })();
}
