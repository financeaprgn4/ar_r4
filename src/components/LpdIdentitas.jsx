import React, { useState } from 'react';
import { HiSwitchHorizontal, HiClipboard } from 'react-icons/hi';
import { formatDate } from "../utility/textFormatter";

const LpdIdentitas = ({ no_rab, identitas, onSwitch }) => {
  const [copied, setCopied] = useState(false);

  const handleCopy = () => {
    navigator.clipboard.writeText(no_rab);
    setCopied(true);
    setTimeout(() => setCopied(false), 1000);
  };

  return (
    <div className="grid grid-cols-2 font-semibold gap-x-2 gap-y-1 text-sm text-gray-700 mb-4">
      <div className="text-right font-medium">Site :</div>
      <div className="flex items-center">
        <span className="mr-2">{identitas.kd_toko || '-'}</span>
        {onSwitch && (
          <button
            onClick={onSwitch}
            className="rounded bg-blue-600 hover:bg-blue-700 text-white p-1"
            title="Ganti Toko"
          >
            <HiSwitchHorizontal className="w-4 h-4" />
          </button>
        )}
      </div>

      <div className="text-right font-medium">Nama Toko :</div>
      <div>{identitas.nama_toko || '-'}</div>

      <div className="text-right font-medium">No RAB :</div>
      <div className="flex items-center">
        <span className="mr-2">{no_rab || '-'}</span>
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
      <div>{identitas.jns_toko || '-'}</div>

      <div className="text-right font-medium">Tanggal Waralaba :</div>
      <div>{formatDate(identitas.tgl_wrlb)}</div>

      <div className="text-right font-medium">Tanggal Jatuh Tempo :</div>
      <div>{formatDate(identitas.tgl_jt)}</div>

      <div className="text-right font-medium">Status :</div>
      <div>{identitas.status || '-'}</div>
    </div>
  );
};

export default LpdIdentitas;
