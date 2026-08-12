export async function getPageLines(pdf, pageNum, yTol = 2) {
  const page = await pdf.getPage(pageNum);
  const content = await page.getTextContent();

  const items = content.items.map((it) => ({
    text: it.str,
    x: it.transform[4],
    y: it.transform[5],
  }));

  items.sort((a, b) => (b.y - a.y) || (a.x - b.x));

  const lines = [];
  for (const it of items) {
    const last = lines[lines.length - 1];
    if (!last || Math.abs(it.y - last.y) > yTol) {
      lines.push({ y: it.y, row: [it] });
    } else {
      last.row.push(it);
    }
  }

  return lines.map((l) =>
    l.row.sort((a, b) => a.x - b.x).map((t) => t.text).join(" ")
  );
}

export async function findPageByPhrase(pdf, phraseRegex) {
  for (let i = 1; i <= pdf.numPages; i++) {
    const lines = await getPageLines(pdf, i);
    const joined = lines.join(" ");
    if (phraseRegex.test(joined)) {
      return { page: i, lines, joined };
    }
  }
  return null;
}

export const monthMap = {
  JAN: "01", FEB: "02", MAR: "03", APR: "04", MAY: "05", JUN: "06",
  JUL: "07", AUG: "08", SEP: "09", OCT: "10", NOV: "11", DEC: "12",
};

export function parseDate(rawDate, monthMap) {
  const [day, mon, year2] = rawDate.split("-");
  const year = "20" + year2;
  const month = monthMap[mon.toUpperCase()];
  return `${year}-${month}-${day.padStart(2, "0")}`;
}

export function parseTableBBT(lines, noRAB, monthMap) {
  const headerIdx = lines.findIndex(
    (l) => /NO\s*BBT/i.test(l) && /Tanggal/i.test(l) && /Nilai/i.test(l)
  );
  if (headerIdx === -1) return [];

  const endIdx = lines.findIndex(
    (l, idx) => idx > headerIdx && /TOTAL\s+KAS\s+MASUK/i.test(l)
  );

  const dataLines = lines.slice(headerIdx + 1, endIdx === -1 ? lines.length : endIdx);
  const rowRegex = /^(.*?)\s+(\d{1,2}\s*-\s*[A-Z]{3}\s*-\s*\d{2})\s+([0-9][0-9\s.,]*)$/i;

  const rows = [];
  for (const line of dataLines) {
    if (/TOTAL\s+KAS\s+MASUK/i.test(line)) break;

    const m = line.match(rowRegex);
    if (!m) continue;

    const noBBT = m[1].replace(/\s+/g, " ").trim();
    const rawDate = m[2].replace(/\s*/g, "");
    const nilaiStr = m[3].replace(/\s+/g, "").trim();
    const nilaiNum = parseInt(nilaiStr.replace(/[^\d]/g, ""), 10);

    const tglBBT = parseDate(rawDate, monthMap);

    rows.push({ noRAB, noBBT, tglBBT, nilaiNum });
  }
  return rows;
}

export async function handleCekPDF({ file, keywordRegex, rowParser, apiUrl, pdfjs }) {
  if (!file) {
    return { success: false, message: "Silakan unggah PDF dulu." };
  }

  const pdf = await pdfjs.getDocument(await file.arrayBuffer()).promise;
  const found = await findPageByPhrase(pdf, keywordRegex);
  if (!found) {
    return { success: false, message: "Halaman tidak sesuai keyword." };
  }

  const { lines, joined } = found;
  const rabMatch = joined.match(/No\s*RAB\s*:\s*([A-Z0-9\/.\-]+)/i);
  const noRAB = rabMatch ? rabMatch[1].trim() : "(tidak ditemukan)";

  const rows = rowParser(lines, noRAB, monthMap);
  if (!rows.length) {
    return { success: false, message: "Tidak ada baris valid yang terparse." };
  }

  try {
    const response = await fetch(apiUrl, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(rows),
    });

    if (!response.ok) {
      return { success: false, message: "Gagal simpan ke server" };
    }

    const result = await response.json();
    return { success: true, message: result.message || "Data berhasil disimpan ke server!" };
  } catch (err) {
    console.error("Error kirim data:", err);
    return { success: false, message: err.message || "Terjadi kesalahan saat menyimpan data." };
  }
}
