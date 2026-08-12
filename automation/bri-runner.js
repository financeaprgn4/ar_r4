const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

function formatDate(dateStr) {
  const [y, m, d] = dateStr.split("-");
  return `${d}/${m}/${y}`;
}

async function runBRI(accounts, startDateRaw, endDateRaw, credential) {

  const CLIENT_ID = credential.client_id;
  const USER_ID = credential.user_id;
  const PASSWORD = credential.password;

  const startDate = formatDate(startDateRaw);
  const endDate = formatDate(endDateRaw);

  let browser;
  let page;

  try {
    console.log("BRI >> Proses Download Mutasi BRI");

    browser = await chromium.launch({
      headless: true,
      args: ['--ignore-certificate-errors']
    });

    const context = await browser.newContext({ acceptDownloads: true });
    page = await context.newPage();

    page.setDefaultTimeout(60000);
    page.setDefaultNavigationTimeout(60000);

    // ================= LOGIN
    console.log("BRI >> Login...");

    await page.goto("https://ibank.bri.co.id/cms/Logon.aspx");
    await page.waitForSelector("#ClientID");

    const isAda = await page.locator("#isAdaPengumunan").inputValue().catch(() => null);

    if (isAda === "ADA") {
      await page.keyboard.press("Escape");
      await page.waitForTimeout(1000);
    }

    await page.fill("#ClientID", CLIENT_ID);
    await page.fill("#UserID", USER_ID);
    await page.fill("#Password", PASSWORD);

    await page.click("#btnLogin");
    await page.waitForLoadState("networkidle");

    const tokenVisible = await page.locator("#token").isVisible().catch(() => false);
    if (tokenVisible) {
      console.log("Token diperlukan — menunggu 60 detik");
      await page.waitForTimeout(60000);
    }

    console.log("BRI >> Login sukses");

    // ================= NAVIGASI MENU
    let headerFrame;

    for (let i = 0; i < 20; i++) {
      headerFrame = page.frames().find(f => f.url().includes("Header.aspx"));
      if (headerFrame) break;
      await page.waitForTimeout(500);
    }

    if (!headerFrame) throw new Error("Header frame tidak ditemukan");

    await headerFrame.click('a:has-text("Account Information")');
    await page.waitForTimeout(2000);

    const menuFrame = page.frames().find(f => f.url().includes("LeftMenu.aspx"));
    if (!menuFrame) throw new Error("Menu frame tidak ditemukan");

    await menuFrame.locator("td.menu", { hasText: "ACCOUNT INFORMATION" }).click();

    await menuFrame.waitForSelector('a:has-text("Account Statement")');
    await menuFrame.locator("a", { hasText: "Account Statement" }).click();

    console.log("BRI >> Masuk menu Account Statement");

    // ================= LOOP REKENING
    for (const acc of accounts) {

      const noRek = acc.no_rek;
      console.log(`BRI >> Memproses rekening: ${noRek}`);

      let contentFrame;

      for (let i = 0; i < 20; i++) {
        contentFrame = page.frame({ name: "channel" });
        if (contentFrame) break;
        await page.waitForTimeout(500);
      }

      if (!contentFrame) throw new Error("Frame channel tidak ditemukan");

      await contentFrame.fill("#ctl00_TransactionForm_txtNoRek", noRek);
      await contentFrame.dispatchEvent("#ctl00_TransactionForm_txtNoRek", "change");

      await contentFrame.fill("#ctl00_TransactionForm_txtstartdate", startDate);
      await contentFrame.dispatchEvent("#ctl00_TransactionForm_txtstartdate", "change");

      await contentFrame.fill("#ctl00_TransactionForm_txtfindate", endDate);
      await contentFrame.dispatchEvent("#ctl00_TransactionForm_txtfindate", "change");

      await contentFrame.check("#ctl00_TransactionForm_rdioLedger");

      await contentFrame.click("#ctl00_TransactionForm_btnSubmit");

      await contentFrame.selectOption(
        "#ctl00_TransactionForm_ReportViewer1_ctl01_ctl05_ctl00",
        { value: "CSV" }
      );
      
      console.log("BRI >> Berhasil submit mutasi, Preparing Download...");

      const [download] = await Promise.all([
        page.waitForEvent("download"),
        contentFrame.click("#ctl00_TransactionForm_ReportViewer1_ctl01_ctl05_ctl01")
      ]);

      const parts = endDate.split("/");
      const ddmmyy = parts[0] + parts[1] + parts[2].slice(-2);

      const fileName = `(BRI)${noRek}_${ddmmyy}.csv`;
      const statementDir = path.join(__dirname, "statement");

      if (!fs.existsSync(statementDir)) {
        fs.mkdirSync(statementDir, { recursive: true });
      }

      await download.saveAs(path.join(statementDir, fileName));

      console.log(`BRI >> Download selesai: ${fileName}`);
    }

    // ================= LOGOUT
    let headerFrameLogout;

    for (let i = 0; i < 20; i++) {
      headerFrameLogout = page.frames().find(f => f.url().includes("Header.aspx"));
      if (headerFrameLogout) break;
      await page.waitForTimeout(500);
    }

    if (!headerFrameLogout) throw new Error("Frame logout tidak ditemukan");

    await headerFrameLogout.click("#btnLogout");
    await page.waitForURL("**/Logon.aspx", { timeout: 20000 });

    console.log("BRI >> Logout");

    await browser.close();
  } catch (err) {
    console.error("BRI RUNNER ERROR:", err);
    if (browser) await browser.close();
    throw err;
  }
}

module.exports = { runBRI };
