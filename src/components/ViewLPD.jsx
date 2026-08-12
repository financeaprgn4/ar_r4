import React, { useRef, useState, useEffect } from 'react';
import { FaSave } from "react-icons/fa";
import { useRightPanel } from "../contexts/RightPanelContext";
import { useNoRab } from "../contexts/NoRabContext";
import { useLpdDetail } from '../hooks/useLpdDetail';
import { HiClipboard } from 'react-icons/hi';
import { formatDate } from "../utility/textFormatter";
export default function ViewLPD({ show, mode }) {
  const { noRab: no_rab } = useNoRab();
  const {
    selectedSite,
    dataMap,
    inputValues,
    total_estimasi,
    total_realisasi,
    handleChange,
    handleSave,
    refreshFlag,
  } = useRightPanel();

  const inputRef = useRef(null);
  const [keterangan, setKeterangan] = useState("");
  
  useEffect(() => {
    const timer = setTimeout(() => {
      if (inputRef.current) inputRef.current.focus();
    }, 100);

    return () => clearTimeout(timer);
  }, []);

  useEffect(() => {
    if (selectedSite.status?.toLowerCase() === "final") {
      setKeterangan(selectedSite.catatan_final || "");
    } else {
      setKeterangan(selectedSite.keterangan || "");
    }
  }, [ selectedSite, refreshFlag ]);

  if (!selectedSite) return null;

  const formatRupiah = (value) => {
    const num = Number(value) || 0;
    return num.toLocaleString("id-ID", {
      style: "currency",
      currency: "IDR",
      minimumFractionDigits: 0,
    });
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    const formData = new FormData(e.target);
    const data = Object.fromEntries(formData.entries());
    data.status = selectedSite.status;
    await handleSave(selectedSite.no_rab, data);
  };

  const [copied, setCopied] = useState(false);
  
  const handleCopy = () => {
    navigator.clipboard.writeText(selectedSite.no_rab);
    setCopied(true);
    setTimeout(() => setCopied(false), 1000);
  };

  return (
    <div>
      <div className="relative mb-4 h-10">
        <h2 className="absolute inset-0 flex justify-center items-center text-lg font-semibold">
          Rekap Detail LPD Per Toko
        </h2>
      </div>

      {/* Informasi toko */}
      <div className="grid grid-cols-2 font-semibold gap-x-2 gap-y-1 text-sm text-gray-700 mb-4">
        <div className="text-right font-medium">Site :</div>
        <div className="flex items-center">
          <span className="mr-2">{selectedSite.kd_toko || '-'}</span>
        </div>
  
        <div className="text-right font-medium">Nama Toko :</div>
        <div>{selectedSite.nama_toko || '-'}</div>
  
        <div className="text-right font-medium">No RAB :</div>
        <div className="flex items-center">
          <span className="mr-2">{selectedSite.no_rab || '-'}</span>
          <button
            onClick={handleCopy}
            className="rounded bg-blue-600 hover:bg-blue-700 text-white p-1"
            title="Copy to clipboard"
          >
            <HiClipboard className="w-4 h-4" />
          </button>
          {copied && (
            <span className="text-green-600 text-xs ml-2 transition-opacity duration-300">
              Copied!
            </span>
          )}
        </div>
  
        <div className="text-right font-medium">Jenis :</div>
        <div>{selectedSite.jns_toko || '-'}</div>
  
        <div className="text-right font-medium">Tanggal Waralaba :</div>
        <div>{formatDate(selectedSite.tgl_wrlb)}</div>
  
        <div className="text-right font-medium">Tanggal Jatuh Tempo :</div>
        <div>{formatDate(selectedSite.tgl_jt)}</div>
  
        <div className="text-right font-medium">Status :</div>
        <div>{selectedSite.status || '-'}</div>
      </div>

      <div className="space-y-1 my-4">
        <div className="h-px bg-gray-400" />
        <div className="h-px bg-gray-400" />
      </div>
      
      <form className="space-y-4" onSubmit={handleSubmit}>
        {/* Tabel input estimasi */}
        <div className="overflow-y-auto max-h-[calc(100vh-280px)] pr-2">
          <table className="min-w-full border border-gray-300 text-sm text-gray-700">
            <thead className="bg-black text-white text-center">
              <tr>
                <th className="px-3 py-2 border">Keterangan</th>
                <th className="px-3 py-2 border">RAB / Estimasi</th>
                <th className="px-3 py-2 border">Realisasi</th>
              </tr>
            </thead>
            <tbody>
              {dataMap.map((item, idx) => (
                <tr key={idx}>
                  <td className={`px-3 py-2 border ${item.className || ''}`}>{item.label}</td>
                  <td className="px-3 py-2 border">
                    <input
                      type="text"
                      className="w-full border rounded px-2 py-1 text-right"
                      placeholder="0"
                      value={formatRupiah(inputValues[item.rabKey] || "")}
                      onChange={(e) => {
                        const numericValue = e.target.value.replace(/[^0-9]/g, "");
                        handleChange({ target: { value: numericValue } }, item.rabKey, "rupiah");
                      }}
                      disabled={selectedSite.status !== "NEW"}
                    />
                  </td>
                  <td className="px-3 py-2 border text-right">
                    {formatRupiah(selectedSite[item.realKey] || 0)}
                  </td>
                </tr>
              ))}
              <tr className="font-bold bg-gray-200">
                <td className="px-3 py-2 border">TOTAL</td>
                <td className="px-3 py-2 border text-right">{formatRupiah(total_estimasi)}</td>
                <td className="px-3 py-2 border text-right">{formatRupiah(total_realisasi)}</td>
              </tr>
            </tbody>
          </table>

          {/* Ringkasan */}
          <div className="mt-6">
            <table className="min-w-full border border-gray-300 text-sm text-gray-700">
              <thead className="bg-black text-white text-center">
                <tr>
                  <th className="px-3 py-2 border">Keterangan</th>
                  <th className="px-3 py-2 border">Modal</th>
                  <th className="px-3 py-2 border">Realisasi</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td className="px-3 py-2 border">Setoran Modal</td>
                  <td className="px-3 py-2 border text-right">{formatRupiah(selectedSite.total_setor)}</td>
                  <td className="px-3 py-2 border"></td>
                </tr>
                <tr>
                  <td className="px-3 py-2 border">Cadangan Dana</td>
                  <td className="px-3 py-2 border text-right">{formatRupiah(selectedSite.total_cad_dana)}</td>
                  <td className="px-3 py-2 border"></td>
                </tr>
                <tr>
                  <td className="px-3 py-2 border">Total Realisasi</td>
                  <td className="px-3 py-2 border"></td>
                  <td className="px-3 py-2 border text-right">{formatRupiah(total_realisasi)}</td>
                </tr>
                <tr className="bg-yellow-300 font-bold">
                  <td className="px-3 py-2 border">Sisa LPD</td>
                  <td colSpan={2} className="px-3 py-2 border text-right">
                    {formatRupiah(
                      (selectedSite.total_setor || 0) +
                      (selectedSite.total_cad_dana || 0) -
                      total_realisasi
                    )}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          {/* Keterangan */}
          <div className="mt-6">
            <label htmlFor="keterangan" className="block text-sm font-medium text-gray-700 ml-1 mb-1">
              Keterangan
            </label>
            <textarea
              ref={inputRef}
              id="keterangan"
              name="keterangan"
              className="mt-1 text-sm text-gray-600 w-full border rounded p-2 ml-1"
              rows={5}
              value={keterangan}
              onChange={(e) => setKeterangan(e.target.value)}
            />
          </div>

          <div className="flex justify-center mt-4">
            <button
              type="submit"
              className="flex bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded gap-1 mt-2 mb-2"
            >
              <FaSave className="w-5 h-5" /> Simpan
            </button>
          </div>
        </div>
      </form>
    </div>
  );
}
