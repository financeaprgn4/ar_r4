import React, { useEffect, useState } from 'react';
import { Copy } from "lucide-react";
import Swal from "sweetalert2";
import { formatRupiah } from "../utility/textFormatter";
import { HiClipboard, HiTrash, HiPencil } from "react-icons/hi";

export default function KategoriLPDRow({ judul, estimasi, transaksi, field, onEdit, onDelete, canEdit }) {
  // Hitung total realisasi
  let totalDpp = 0, totalPpn = 0, totalAll = 0;

  transaksi?.forEach(item => {
    item[field]?.forEach(trx => {
      totalDpp += Number(trx.dpp) || 0;
      totalPpn += Number(trx.ppn) || 0;
      totalAll += Number(trx.total) || 0;
    });
  });

  const [copiedId, setCopiedId] = useState(null);
  
  const handleCopy = (invNum) => {
    if (!invNum) return;
    console.log(invNum);
    navigator.clipboard.writeText(invNum)
    .then(() => {
      setCopiedId(invNum);
      setTimeout(() => setCopiedId(null), 2000);
    })
    .catch((err) => {
      console.error('Copy failed:', err);
    });
  };

  const hasData = transaksi.some(item => item[field] && item[field].length > 0);
  
  return (
    <>
      {/* Header baris kategori */}
      <tr>
        <td className="border px-2 py-1 font-semibold">{judul}</td>
        <td className="border px-2 py-1 text-right">{formatRupiah(estimasi)}</td>
        <td colSpan={6} className="border px-2 py-1"></td>
      </tr>

      {/* Data realisasi */}
      {hasData ? (
        transaksi.map(item =>
          item[field]?.map((trx, idx) => (
            <tr key={`${field}-${item.id}-${idx}`}>
              <td className="border px-2 py-1"></td>
              <td className="border px-2 py-1"></td>
              <td className="border px-2 py-1">{trx.keterangan}</td>
              <td className="border px-2 py-1 text-right">{formatRupiah(trx.dpp)}</td>
              <td className="border px-2 py-1 text-right">{formatRupiah(trx.ppn)}</td>
              <td className="border px-2 py-1 text-right">{formatRupiah(trx.total)}</td>
              <td className="border px-2 py-1">
                <div className="flex items-center">
                  <span className="mr-1">{trx.inv_num || '-'}</span>
                    <button
                        onClick={() => handleCopy(trx.inv_num)}
                        className="rounded bg-blue-600 hover:bg-blue-700 text-white"
                        title="Copy Inv Num"
                    >
                        <HiClipboard className="w-4 h-4" />
                    </button>
                    {copiedId === trx.inv_num && (
                        <span className="text-green-600 text-xs transition-opacity duration-300 ml-2">
                            Copied!
                  </span>
                    )}

                    {canEdit && (
                      <>
                      <button
                          onClick={() => onEdit({ ...trx, kategori: field })}
                          className="rounded bg-green-600 hover:bg-green-700 text-white ml-1"
                          title="Edit Detail"
                      >
                          <HiPencil className="w-4 h-4" />
                      </button>
                      <button
                          onClick={() => onDelete(trx)}
                          className="rounded bg-red-600 hover:bg-red-700 text-white ml-1"
                          title="Delete Trx"
                      >
                          <HiTrash className="w-4 h-4" />
                      </button>
                      </>
                    )}
                </div>
              </td>
              <td className="border px-2 py-1"></td>
            </tr>
          ))
        )
      ) : (
        <tr>
          <td colSpan={8} className="border px-2 py-1 text-center text-red-600">Tidak ada transaksi</td>
        </tr>
      )}

      {/* Total baris */}
      <tr className="bg-green-500 font-semibold text-white">
        <td className="border px-2 py-1 text-right">Total {judul.replace(/\s*\(.*?\)\s*/g, '').trim()}</td>
        <td className="border px-2 py-1 text-right">{formatRupiah(estimasi)}</td>
        <td className="border px-2 py-1 text-right"></td>
        <td className="border px-2 py-1 text-right">{formatRupiah(totalDpp)}</td>
        <td className="border px-2 py-1 text-right">{formatRupiah(totalPpn)}</td>
        <td className="border px-2 py-1 text-right">{formatRupiah(totalAll)}</td>
        <td className="border px-2 py-1 text-right"></td>
        <td className="border px-2 py-1 text-right">{formatRupiah((estimasi || 0) - totalAll)}</td>
      </tr>
    </>
  );
}
