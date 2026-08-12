import * as pdfjs from "pdfjs-dist";

export const processEstimasiFromPdf = async (pdfFile) => {
  const pdf = await pdfjs.getDocument(await pdfFile.arrayBuffer()).promise;

  const keywords = [
    "FRANCHISE FEE",
    "BIAYA PROMOSI PEMBUKAAN",
    "BIAYA REKRUITMENT KARYAWAN",
    "BIAYA SEWA DAN PPH",
    "BIAYA JASA PIHAK KE 3",
    "PEKERJAAN HALAMAN",
    "PEKERJAAN TERALIS",
    "PEKERJAAN FOLDING GATE",
    "PEKERJAAN LISTRIK",
    "PEKERJAAN ALUMUNIUM + KACA",
    "PEKERJAAN KANOPI",
    "PEKERJAAN INSTALASI UTK AC",
    "PEKERJAAN SIPIL",
    "PEKERJAAN URUGAN DAN",
    "PEKERJAAN SIGNAGE",
    "PEKERJAAN INTERIOR DAN",
    "PEKERJAAN LIFT",
    "AC SPLIT",
    "SARANA UTILITAS",
    "TAMBAH DAYA DARIVA KEVA",
    "PASANG DAYA LISTRIK BARU",
    "PERALATAN ELEKTRONIK DAN",
  ];

  const found = {};
  let noRAB = null;
  const numberRegex = /[0-9]{1,3}(?:[.,][0-9]{3})*(?:[.,][0-9]+)?/g;

  for (let p = 1; p <= pdf.numPages; p++) {
    const page = await pdf.getPage(p);
    const textContent = await page.getTextContent();

    const items = textContent.items.map((it) => {
      const t = it.transform || [];
      const x = t[4] ?? it.x ?? 0;
      const y = t[5] ?? it.y ?? 0;
      return { str: it.str || "", x, y };
    });

    const buckets = {};
    for (const it of items) {
      const key = Math.round(it.y);
      if (!buckets[key]) buckets[key] = [];
      buckets[key].push(it);
    }

    const yKeys = Object.keys(buckets).map(Number).sort((a, b) => b - a);

    const lines = yKeys.map((yk) => {
      const arr = buckets[yk].sort((a, b) => a.x - b.x);
      return arr.map((a) => a.str).join(" ").replace(/\s+/g, " ").trim();
    });

    // Cari No RAB
    for (const line of lines) {
      const matchRAB = line.match(/NO RAB\s*:\s*([A-Z0-9/.-]+)/i);
      if (matchRAB && matchRAB[1]) {
        noRAB = matchRAB[1];
      }
    }

    // Cari kategori estimasi
    for (let i = 0; i < lines.length; i++) {
      const lineUpper = lines[i].toUpperCase();

      for (const kw of keywords) {
        if (found[kw]) continue;

        if (lineUpper.includes(kw)) {
          let matches = lines[i].match(numberRegex);

          if (!matches) {
            for (let k = 1; k <= 3 && !matches; k++) {
              if (i + k < lines.length) matches = lines[i + k].match(numberRegex);
            }
          }

          if (!matches) {
            for (let k = 1; k <= 3 && !matches; k++) {
              if (i - k >= 0) matches = lines[i - k].match(numberRegex);
            }
          }

          if (matches && matches.length > 0) {
            let best = null;
            let bestValue = 0;
            for (const m of matches) {
              const clean = m.replace(/[^\d]/g, "");
              const val = parseInt(clean, 10) || 0;
              if (val > 10 && val > bestValue) {
                best = m;
                bestValue = val;
              }
            }

            if (best) {
              found[kw] = {
                kategori: kw,
                estimasiNum: bestValue,
              };
            }
          } else {
            found[kw] = {
              kategori: kw,
              estimasiNum: 0,
            };
          }
        }
      }
    }
  }

  const rows = Object.values(found).map((r) => ({
    kategori: r.kategori,
    estimasi: r.estimasiNum,
  }));
  console.log("Payload ke backend:", rows);
  return { noRAB, rows };
};
