import React, { useEffect, useState, useRef } from 'react';
import Menu_LPD from '../components/Menu_LPD';
import { useNavigate } from 'react-router-dom';
import { useNoRab } from '../contexts/NoRabContext';
import { formatDate, formatRupiah } from "../utility/textFormatter";
import { HiMenu } from "react-icons/hi";
import ExcelJS from 'exceljs';

export default function Inv_unmatch_sarana() {
  const { updateNoRab } = useNoRab();
  const navigate = useNavigate();
  const cabang = sessionStorage.getItem("cabang");
  const [data, setData] = useState([]);
  const [isMenuOpen, setMenuOpen] = useState(false);
  const handleOpenMenu = () => setMenuOpen(true);
  const handleCloseMenu = () => setMenuOpen(false);
  
  const fetchData = () => {
    if (!cabang) return;
    fetch(`/api/inv-unmatch-sarana?cabang=${cabang}`)
    .then((res) => res.json())
    .then((res) => {
        setData(res.data);
    })
    .catch((err) => console.error("Fetch error:", err));
  };

  useEffect(() => {
    fetchData();
  }, [cabang]);

  const handleEdit = (SiteData) => {
    updateNoRab(SiteData.no_rab);
    navigate('/sarana_toko');
  };

  const columns = [
    {
        header: 'Site',
        accessorKey: 'kd_toko',
        cell: ({ row }) => {
            return (
            <button
                className="text-blue-600 hover:underline font-semibold"
                onClick={() => handleEdit(row.original)}
            >
                {row.original.kd_toko}
            </button>
            );
        }
    },
    {accessorKey: 'nama_toko'},
    {accessorKey: 'jns_toko'},
    {accessorKey: 'status'},
    {accessorKey: 'tgl_wrlb'},
    {accessorKey: 'no_rab'},
    {accessorKey: 'inv_num'},
    {accessorKey: 'keterangan'},
    {accessorKey: 'dpp'},
    {accessorKey: 'ppn'},
    {accessorKey: 'total'},
  ];

  useEffect(() => {
    const handleKeyDown = (e) => {
        const { key, code, altKey } = e;
        if (key === 'Escape') {
            if (isMenuOpen) setMenuOpen(false);
        }

        if (altKey) {
            switch (code) {
                case 'KeyM':
                    e.preventDefault();
                    setMenuOpen(true);
                    break;
            }
        }
    };

    window.addEventListener('keydown', handleKeyDown);
    return () => window.removeEventListener('keydown', handleKeyDown);
  }, [ isMenuOpen ]);

  const exportToExcel = async () => {
    if (!data || data.length === 0) return;

    const workbook = new ExcelJS.Workbook();
    const worksheet = workbook.addWorksheet('Inv Unmatch ATPR');

    // Header columns
    worksheet.columns = [
        { header: 'Site', key: 'kd_toko' },
        { header: 'Nama Toko', key: 'nama_toko' },
        { header: 'Jenis', key: 'jns_toko' },
        { header: 'Status', key: 'status' },
        { header: 'Tanggal Waralaba', key: 'tgl_wrlb' },
        { header: 'No RAB', key: 'no_rab' },
        { header: 'Invoice Num', key: 'inv_num' },
        { header: 'Keterangan', key: 'keterangan' },
        { header: 'DPP', key: 'dpp' },
        { header: 'PPn', key: 'ppn' },
        { header: 'Total', key: 'total' },
    ];

    // Tambahkan data baris
    data.forEach(row => {
        worksheet.addRow({
            kd_toko: row.kd_toko,
            nama_toko: row.nama_toko,
            jns_toko: row.jns_toko,
            status: row.status,
            tgl_wrlb: new Date(row.tgl_wrlb),
            no_rab: row.no_rab,
            inv_num: row.inv_num,
            keterangan: row.keterangan,
            dpp: row.dpp,
            ppn: row.ppn,
            total: row.total,
        });
    });

    // Style untuk header
    const headerRow = worksheet.getRow(1);
    headerRow.eachCell((cell) => {
        cell.font = { bold: true };
        cell.alignment = { vertical: 'middle', horizontal: 'center' };
        cell.fill = {
        type: 'pattern',
        pattern: 'solid',
        fgColor: { argb: 'FFFF00' },
        };
        cell.border = {
        top: { style: 'thin' },
        left: { style: 'thin' },
        bottom: { style: 'thin' },
        right: { style: 'thin' },
        };
    });

    // Border dan format untuk semua cell
    worksheet.eachRow((row, rowNumber) => {
      if (rowNumber === 1) return; // skip header
        row.eachCell((cell, colNumber) => {
        const columnKey = worksheet.getColumn(colNumber).key;

        // Apply border
        cell.border = {
            top: { style: 'thin' },
            left: { style: 'thin' },
            bottom: { style: 'thin' },
            right: { style: 'thin' }
        };

        if (['dpp', 'ppn', 'total'].includes(columnKey)) {
            cell.numFmt = '#,##0';
            cell.alignment = { horizontal: 'right' };
        }

        if (columnKey === 'tgl_wrlb' || columnKey === 'tgl_perolehan') {
            cell.numFmt = 'dd-mmm-yy';
            cell.alignment = { horizontal: 'center' };
        }
      });
    });

    // Autofit columns (hitung max width per kolom)
    worksheet.columns.forEach(column => {
        let maxLength = 10;
        column.eachCell({ includeEmpty: true }, cell => {
        const columnLength = cell.value ? cell.value.toString().length : 10;
        if (columnLength > maxLength) {
            maxLength = columnLength;
        }
        });
        column.width = maxLength + 2;
    });

    // Simpan file
    const buffer = await workbook.xlsx.writeBuffer();
    const blob = new Blob([buffer], {
        type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `Inv_Unmatch_${cabang}.xlsx`;
    a.click();
    window.URL.revokeObjectURL(url);
  };

  return (
    <main className="flex-1 px-2 py-2 z-10 text-white h-[calc(100vh-40px)] overflow-hidden">
      <div className="h-full bg-white/60 rounded-lg shadow-lg text-gray-800 w-full flex flex-col">
        <div className="relative flex items-center justify-center mb-4 px-4 pt-4">
          <div className="mb-4 text-center">
            <h2 className="text-xl font-bold">Daftar Inv Unmatch AT/PR</h2>
            <h4 className="text-xl font-bold">Cabang : {cabang}</h4>
          </div>

          <div className="absolute right-4">
            <button
              onClick={handleOpenMenu}
              className="rounded bg-gray-700 hover:bg-gray-800 text-white px-2 py-1"
              title="Buka Menu"
            >
              <HiMenu className="w-5 h-5" />
            </button>
          </div>
        </div>

        <Menu_LPD
          isOpen={isMenuOpen}
          onClose={handleCloseMenu}
          onOpenDrawer={(mode) => {
              setDrawerMode(mode);
          }}
          onExportExcel={exportToExcel}
        />

        <div className="flex-1 overflow-y-auto px-4 pb-4 space-y-4">
            <table className="min-w-full border-collapse">
                <thead className="bg-gray-100 sticky top-0 z-10 text-sm font-semibold text-center bg-white">
                    <tr>
                    <th className="p-2 border-b">Site</th>
                    <th className="p-2 border-b">Nama Toko</th>
                    <th className="p-2 border-b">Jenis</th>
                    <th className="p-2 border-b">Status</th>
                    <th className="p-2 border-b">Tanggal Waralaba</th>
                    <th className="p-2 border-b">No RAB</th>
                    <th className="p-2 border-b">Invoice Num</th>
                    <th className="p-2 border-b">Keterangan</th>
                    <th className="p-2 border-b">DPP</th>
                    <th className="p-2 border-b">PPn</th>
                    <th className="p-2 border-b">Total</th>
                    </tr>
                </thead>
                <tbody>
                    {data.length === 0 ? (
                        <tr>
                        <td colSpan={11} rowSpan={11} className="text-center p-4 text-sm text-gray-500">
                            Tidak ada record ditemukan
                        </td>
                        </tr>
                    ) : (
                        data.map((row, index) => (
                        <tr key={index}>
                            <td className="p-2 border-b text-sm text-blue-600 font-semibold hover:underline cursor-pointer" onClick={() => handleEdit(row)}>
                            {row.kd_toko}
                            </td>
                            <td className="p-2 border-b text-sm">{row.nama_toko}</td>
                            <td className="p-2 border-b text-sm text-center">{row.jns_toko}</td>
                            <td className="p-2 border-b text-sm text-center">{row.status}</td>
                            <td className="p-2 border-b text-sm text-center">{formatDate(row.tgl_wrlb)}</td>
                            <td className="p-2 border-b text-sm text-center">{row.no_rab}</td>
                            <td className="p-2 border-b text-sm text-left">{row.inv_num}</td>
                            <td className="p-2 border-b text-sm text-left">{row.keterangan}</td>
                            <td className="p-2 border-b text-sm text-right">{formatRupiah(row.dpp)}</td>
                            <td className="p-2 border-b text-sm text-right">{formatRupiah(row.ppn)}</td>
                            <td className="p-2 border-b text-sm text-right">{formatRupiah(row.total)}</td>
                        </tr>
                        ))
                    )}
                </tbody>
            </table>
        </div>
      </div>
    </main>
  );
}