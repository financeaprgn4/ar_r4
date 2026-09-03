import React, { useEffect, useRef } from 'react';
import { FaSave } from "react-icons/fa";
import { HiRefresh } from "react-icons/hi";
import { formatRupiah } from "../utility/textFormatter";
import { useRightPanel } from "../contexts/RightPanelContext";

export default function CreateLPD({ show, onClose, mode }) {
  const {
    formFields,
    inputValues,
    handleChange,
    handleReset,
    handleStore,
    handleUpdate,
    selectedSite,
  } = useRightPanel();
  
  const kodeTokoRef = useRef(null);

  useEffect(() => {
    if (
      show &&
      kodeTokoRef.current &&
      ((mode === "add") || (mode === "edit" && inputValues.kd_toko))
    ) {
      const timer = setTimeout(() => {
        kodeTokoRef.current?.focus();
      }, 200);

      return () => clearTimeout(timer);
    }
  }, [show, mode, inputValues.kd_toko]);


  const handleSubmit = (e) => {
    e.preventDefault();
    if (mode === "edit") {
      handleUpdate(selectedSite?.no_rab, { keterangan: inputValues.keterangan || "" });
    } else {
      handleStore(e);
    }
  };

  const selectOptionsMap = {
    jns_toko: [
      { value: "", label: "--=[ Pilih Jenis Toko ]=--" },
      { value: "PPJ", label: "Perpanjangan" },
      { value: "NS", label: "New Store" },
      { value: "UP", label: "Upgrade" },
      { value: "TO", label: "Take Over" },
    ],
    report: [
      { value: "", label: "--=[ Pilih Reporting ]=--" },
      { value: "Y", label: "Yes" },
      { value: "N", label: "No" },
    ],
  };

  return (
    <div>
      <div className="relative mb-4 h-10">
        <h2 className="absolute inset-0 flex justify-center items-center text-lg font-semibold">
          {mode === "edit" ? "Edit Data Toko" : "Input Data Toko Baru"}
        </h2>
      </div>

      <div className="space-y-1 my-4">
        <div className="h-px bg-gray-400" />
        <div className="h-px bg-gray-400" />
      </div>

      <form className="space-y-4" onSubmit={handleSubmit}>
        {formFields.map((field, index) => (
          <div key={index} className="flex items-center gap-2">
            <label className="w-32 text-sm font-medium">{field.label}</label>

            {field.type === "rupiah" ? (
              <input
                type="text"
                className={`border border-blue-300 rounded px-3 py-1 ${field.width}`}
                value={formatRupiah(inputValues[field.key] || "")}
                onChange={(e) => {
                  const numericValue = e.target.value.replace(/[^0-9]/g, "");
                  handleChange({ target: { value: numericValue } }, field.key, field.type);
                }}
                ref={field.key === "kd_toko" ? kodeTokoRef : null}
              />
            ) : field.type === "select" ? (
              <select
                className={`border border-blue-300 rounded px-3 py-1 ${field.width}`}
                value={inputValues[field.key] || ""}
                onChange={(e) => handleChange(e, field.key, field.type)}
              >
                {(selectOptionsMap[field.key] || []).map((opt) => (
                  <option key={opt.value} value={opt.value}>
                    {opt.label}
                  </option>
                ))}
              </select>
            ) : (
              <input
                type={field.type}
                className={`border border-blue-300 rounded px-3 py-1 ${field.width}`}
                value={inputValues[field.key] || ""}
                onChange={(e) => handleChange(e, field.key, field.type)}
                maxLength={field.maxLength || undefined}
                ref={field.key === "kd_toko" ? kodeTokoRef : null}
              />
            )}
          </div>
        ))}

        <div className="pt-4 flex justify-end gap-2">
          <button
            type="submit"
            className="bg-green-600 hover:bg-green-700 text-white px-3 py-1 rounded inline-flex items-center"
          >
            <FaSave className="mr-2" />
            {mode === "edit" ? "Update" : "Save"}
          </button>
          <button
            type="button"
            onClick={handleReset}
            className="bg-red-400 hover:bg-red-500 text-white px-3 py-1 rounded inline-flex items-center"
          >
            <HiRefresh className="mr-2" />
            Reset
          </button>
        </div>
      </form>
    </div>
  );
}
