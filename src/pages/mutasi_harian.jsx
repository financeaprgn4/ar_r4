import React, { useEffect, useState, useRef } from "react";
import axios from "../config/axiosInstance";
import DatePicker from "react-datepicker";
import ReusableTableNew from "../components/ReusableTableNew";
import BottomDrawer from "../components/BottomDrawer";
import TopDrawer from "../components/TopDrawer";
import Swal from "sweetalert2";
import {
  FaTimes,
  FaCheckCircle,
  FaTrash,
  FaDownload,
  FaUpload,
  FaSitemap,
  FaFileImport,
  FaCloudUploadAlt,
  FaRecycle
} from "react-icons/fa";

import { formatDate } from "../utility/textFormatter";
import { useSidebar } from "../components/SidebarContext";
import { openDownload } from "../config/openDownload";
import { useCabang } from "../contexts/CabangContext";

export default function Mutasi_harian() {
  const { cabang } = useCabang();
  const [data, setData] = useState([]);
  const [periodeList, setPeriodeList] = useState([]);
  const [globalFilter, setGlobalFilter] = useState("");
  const [selectedPeriode, setSelectedPeriode] = useState("");
  const [selectedKategori, setSelectedKategori] = useState("Reguler");
  
  const [activeDrawer, setActiveDrawer] = useState(null);
  const showDrawer = activeDrawer === 'bottom';
  const openTopDrawer = activeDrawer === 'top';
  
  const fileInputRef = useRef(null);
  const [selectedFile, setSelectedFile] = useState(null);
  const labelRef = useRef(null);
  const { isCollapsed, setIsCollapsed } = useSidebar();
  const [selectedRows, setSelectedRows] = useState([]);

  const [jenisBank, setJenisBank] = useState("");
  const [tanggalAwal, setTanggalAwal] = useState(null);
  const [tanggalAkhir, setTanggalAkhir] = useState(null);

  // ===============================
  // 1. LOAD PERIODE DARI BACKEND
  // ===============================
  useEffect(() => {
    if (!cabang) return;
    
    const fetchPeriode = async () => {
      try {
        const url = `/api/periode?cabang=${cabang}&kategori=Mutasi`;
        const res = await fetch(url);
        const result = await res.json();

        setPeriodeList(Array.isArray(result) ? result : []);
        const openPeriod = result.find((p) => p.status === "open");

        if (openPeriod) {
          setSelectedPeriode(openPeriod.periode);
        } else {
          setSelectedPeriode(result.length > 0 ? result[0].periode : "");
        }
      } catch (err) {
        console.error("Gagal mengambil periode:", err);
      }
    };

    fetchPeriode();
  }, [cabang]);

  // ===============================
  // 2. LOAD DATA MUTASI BERDASARKAN PERIODE
  // ===============================
  const fetchData = async () => {
    if (!cabang || !selectedPeriode) return;

    try {
      const url = `/api/mutasi?cabang=${cabang}&periode=${selectedPeriode}${
        selectedKategori ? `&kategori=${selectedKategori}` : ""
      }`;

      const res = await fetch(url);
      const result = await res.json();

      setData(Array.isArray(result.data) ? result.data : []);
    } catch (err) {
      console.error("Gagal mengambil data mutasi:", err);
      Swal.fire("Error", "Tidak dapat memuat data mutasi", "error");
    }
  };

  useEffect(() => {
    fetchData();
  }, [cabang, selectedPeriode, selectedKategori]);

  // =======================
  // TABLE COLUMNS
  // =======================
  const columns = [
    {
      id: "select",
      header: ({ table }) => (
        <div className="flex justify-center items-center">
          <input
            type="checkbox"
            checked={table.getIsAllPageRowsSelected()}
            onChange={table.getToggleAllPageRowsSelectedHandler()}
          />
        </div>
      ),
      cell: ({ row }) => (
        <div className="flex justify-center items-center">
          <input
            type="checkbox"
            checked={row.getIsSelected()}
            disabled={!row.getCanSelect()}
            onChange={row.getToggleSelectedHandler()}
          />
        </div>
      ),
      enableSorting: false,
      enableHiding: false,
      size: 30,
    },
    { accessorKey: "cabang", header: "Cabang" },
    { accessorKey: "bank", header: "Bank" },
    { accessorKey: "no_rek", header: "Nomor Rekening" },
    { accessorKey: "tgl_awal", header: "Tanggal Awal", cell: info => formatDate(info.getValue())},
    { accessorKey: "tgl_akhir", header: "Tanggal Akhir", cell: info => formatDate(info.getValue())},
    { accessorKey: "file", header: "File" },
    {
      header: "Opsi",
      id: "actions",
      cell: ({ row }) => (
        <div className="flex space-x-2 justify-center">

          {row.original.file && (
            <button
              onClick={() =>
                openDownload(
                  `/rk/${row.original.cabang}/${row.original.file}`,
                  "_blank"
                )
              }
              className="px-2 py-1 bg-green-500 hover:bg-green-700 text-white rounded flex items-center"
            >
              <FaDownload className="w-5 h-5" />
            </button>
          )}

          <button
            onClick={() => handleDeleteAction(row.original)}
            className="px-2 py-1 bg-red-500 hover:bg-red-700 text-white rounded flex items-center"
          >
            <FaTrash className="w-5 h-5" />
          </button>
        </div>
      ),
    },
  ];

  const handleDeleteAction = async (rows) => {
    // Normalisasi: jadikan array
    const rowsToDelete = Array.isArray(rows) ? rows : [rows];

    if (rowsToDelete.length === 0) return;

    // Ambil daftar file
    const files = rowsToDelete.map(r => r.file);
    const cabang = rowsToDelete[0].cabang;

    // Teks ditampilkan di konfirmasi
    const listFiles = files.join("\n");

    // Konfirmasi
    const confirm = await Swal.fire({
      title: rowsToDelete.length === 1 ? "Hapus Mutasi" : "Hapus Banyak Mutasi",
      html: `Apakah anda yakin ingin menghapus file:<br><pre style="text-align:left">${listFiles}</pre>`,
      icon: "warning",
      showCancelButton: true,
      confirmButtonText: "Ya, Hapus!",
      cancelButtonText: "Batal",
      width: 500,
    });

    if (!confirm.isConfirmed) return;

    // Loading
    Swal.fire({
      title: "Deleting...",
      html: "Mohon tunggu sebentar",
      allowOutsideClick: false,
      allowEscapeKey: false,
      didOpen: () => Swal.showLoading(),
    });

    // Eksekusi delete
    let successCount = 0;
    let failCount = 0;

    for (const file of files) {
      try {
        const res = await fetch(
          `/api/mutasi/${encodeURIComponent(file)}/${cabang}`,
          { method: "DELETE" }
        );

        const result = await res.json();

        if (!res.ok) {
            throw new Error(result.error || result.message);
        }

        if (result.success) {
            successCount++;
        } else {
            failCount++;
        }
      } catch (err) {
        console.error(err);
        failCount++;
      }
    }

    // Hasil akhir
    Swal.fire({
      icon: "success",
      title: "Proses Selesai",
      html: `
        <b>${successCount}</b> file berhasil dihapus<br>
        <b>${failCount}</b> gagal dihapus
      `,
    }).then(() => {
      fetchData();
      setSelectedRows([]);
    });
  };

  const resetFilePicker = () => {
    setSelectedFile(null);
    const fileInput = document.getElementById("fileUpload");
    if (fileInput) fileInput.value = null;
  };

  const handleFileChange = (e) => {
    const files = Array.from(e.target.files);
    if (files.length > 0) {
      setSelectedFile(files);
    }
  };

  useEffect(() => {
    if (!showDrawer) {
      resetFilePicker();
    }
  }, [showDrawer]);

  useEffect(() => {
    if (showDrawer && labelRef.current) {
      labelRef.current.focus();
    }
  }, [showDrawer]);
  
  const resetTopDrawer = () => {
    setSelectedFile(null);
    setJenisBank("");
    setTanggalAwal(null);
    setTanggalAkhir(null);

    // Reset nilai input file
    if (fileInputRef.current) {
        fileInputRef.current.value = "";
    }
  };

  const closeTopDrawer = () => {
    resetTopDrawer();
    setActiveDrawer(null);
  };

  const toggleImportMutasi = () => {
    setIsCollapsed(true);
    setActiveDrawer(prev =>
        prev === 'bottom' ? null : 'bottom'
    );
  };

  useEffect(() => {
    const handleKeyDown = (e) => {
        const { key, code, altKey } = e;

        // ESC
        if (key === 'Escape') {
          if (activeDrawer === 'top') {
              e.preventDefault();
              closeTopDrawer();
          } else if (activeDrawer === 'bottom') {
              e.preventDefault();
              setActiveDrawer(null);
          }
          return;
        }

        // ALT + Shortcut
        if (altKey) {
            switch (code) {
                case 'KeyT':
                    e.preventDefault();

                    setIsCollapsed(true);
                    if (activeDrawer === 'top') {
                      closeTopDrawer();
                    } else {
                        setIsCollapsed(true);
                        setActiveDrawer('top');
                    }
                    return;

                case 'KeyU':
                  e.preventDefault();
                  toggleImportMutasi();
                  return;

                case 'KeyX':
                    e.preventDefault();

                    if (activeDrawer === null) {
                        setIsCollapsed(prev => !prev);
                    }
                    return;

                default:
                    break;
            }
        }

        // ENTER pada label upload
        if (
            key === 'Enter' &&
            document.activeElement === labelRef.current &&
            fileInputRef.current
        ) {
            fileInputRef.current.click();
        }
    };

    window.addEventListener('keydown', handleKeyDown);

    return () => {
        window.removeEventListener('keydown', handleKeyDown);
    };
  }, [activeDrawer, isCollapsed]);
  
  const handleSubmit = async () => {
    if (!selectedFile || selectedFile.length === 0) {
      Swal.fire("Peringatan", "Silakan pilih minimal satu file!", "warning");
      return;
    }

    if (!cabang) {
      Swal.fire("Peringatan", "Cabang belum dipilih!", "warning");
      return;
    }

    const formData = new FormData();
    for (let i = 0; i < selectedFile.length; i++) {
      formData.append("files[]", selectedFile[i]);
    }
    formData.append("cabang", cabang);

    // === POPUP TUNGGU ===
    Swal.fire({
      title: `Import Mutasi Cabang ${cabang}`,
      html: `
        <div id="anim-import" style="display:none; margin-top:15px; font-size:20px;">
          <span class="loader"></span> Importing...
        </div>
      `,
      allowOutsideClick: false,
      allowEscapeKey: false,
      showConfirmButton: false,
    });

    // CSS loader (spinner)
    const style = document.createElement("style");
    style.innerHTML = `
      .loader {
        border: 4px solid #f3f3f3;
        border-top: 4px solid #3498db;
        border-radius: 50%;
        width: 18px;
        height: 18px;
        display: inline-block;
        animation: spin 0.9s linear infinite;
        margin-right: 6px;
        vertical-align: middle;
      }
      @keyframes spin { 
        0% { transform: rotate(0deg); } 
        100% { transform: rotate(360deg); } 
      }
    `;
    document.head.appendChild(style);

    try {
      const response = await axios.post(
        `/api/import-mutasi`,
        formData,
        {
          headers: { "Content-Type": "multipart/form-data" },
          onUploadProgress: (e) => {
            const percent = Math.round((e.loaded * 100) / e.total);
            const container = Swal.getHtmlContainer();
            const textEl = container?.querySelector("#upload-text");

            if (textEl) textEl.innerHTML = `Mengunggah file... ${percent}%`;

            if (percent === 100) {
              if (textEl) textEl.innerHTML = "Upload selesai ✓";
              const anim = container?.querySelector("#anim-import");
              if (anim) anim.style.display = "block";
            }
          },
        }
      );

      // === CEK ERROR PER-FILE ===
      const errors = response?.data?.errors;
      if (Array.isArray(errors) && errors.length > 0) {
        let daftarError = "<ul style='text-align:left'>";

        errors.forEach((err) => {
          let listReason = [];

          if (Array.isArray(err.issues)) listReason = err.issues;
          else if (typeof err.issues === "string") listReason = err.issues.split(",").map((x) => x.trim());
          else if (Array.isArray(err.reason)) listReason = err.reason;
          else if (typeof err.reason === "string") listReason = err.reason.split(",").map((x) => x.trim());
          else listReason = [String(err.message || err)];

          let detailHTML = "<ul style='padding-left:20px'>";
          listReason.forEach((r) => (detailHTML += `<li>- ${r}</li>`));
          detailHTML += "</ul>";

          daftarError += `
            <li style="margin-bottom:10px;">
              <b>${err.file ?? "Unknown file"}</b>:
              ${detailHTML}
            </li>
          `;
        });

        daftarError += "</ul>";

        Swal.fire({
          icon: "warning",
          title: "Beberapa File Tidak Bisa Diproses",
          html: daftarError,
          width: 700,
          confirmButtonText: "OK",
        }).then(() => {
          fetchData();
          setActiveDrawer(null);
          resetFilePicker();
        });

        return;
      }

      // === SUKSES IMPORT ===
      Swal.fire({
        icon: "success",
        title: "Berhasil",
        html: `<p>${response.data?.message ?? "Proses import selesai."}</p>`,
        confirmButtonText: "OK",
      }).then(() => {
        fetchData();
        setActiveDrawer(null);
        resetFilePicker();
      });

    } catch (err) {
      console.error("upload error:", err);

      let message = "Terjadi kesalahan saat mengupload/import file.";
      if (err?.response?.data?.message) message = err.response.data.message;
      else if (err?.response?.data?.errors) {
        try {
          const e = err.response.data.errors;
          if (Array.isArray(e)) {
            message = e
              .map((it) => it.reason ?? (Array.isArray(it.issues) ? it.issues.join("; ") : it.message ?? JSON.stringify(it)))
              .join(" | ");
          } else {
            message = String(e);
          }
        } catch (ex) {}
      }

      Swal.fire({
        icon: "error",
        title: "Gagal",
        text: message,
        width: 600,
      });
    }
  };

  useEffect(() => {
    if (openTopDrawer) {
        const timer = setTimeout(() => {
            fileInputRef.current?.focus();
        }, 300); // sesuaikan dengan durasi animasi
        return () => clearTimeout(timer);
    }
  }, [openTopDrawer]);

  return (
    <main className="flex-1 px-4 py-2 z-10 text-white">
      <div className="h-[calc(100vh-50px)] bg-white/60 rounded-lg p-4 shadow-lg text-gray-800 w-full">
        
        {/* HEADER */}
        <div className="box-header text-white shadow-md flex items-center justify-center mx-auto mt-[-17px] mb-4 h-[60px] w-1/2 bg-blue-400 clip-path-custom">
          <h2 className="text-xl text-center font-semibold">
            Mutasi Harian Cabang {cabang}
          </h2>
        </div>
        
        {openTopDrawer && (
          <TopDrawer
              isOpen={openTopDrawer}
              onClose={closeTopDrawer}
              height="180px"
          >
              <div className="grid grid-cols-12 gap-4 items-end">

                  {/* File */}
                  <div className="col-span-4">
                      <label className="block text-sm font-semibold mb-2">
                          Pilih File
                      </label>

                      <input
                          ref={fileInputRef}
                          type="file"
                          className="w-full border rounded-md text-sm
                                    file:bg-gray-100
                                    file:border-0
                                    file:px-4
                                    file:py-2
                                    file:mr-3
                                    file:cursor-pointer"
                          onChange={(e) => setSelectedFile(e.target.files[0])}
                      />
                  </div>

                  {/* Jenis Bank */}
                  <div className="col-span-2">
                      <label className="block text-sm font-semibold mb-2">
                          Jenis Bank
                      </label>

                      <select
                          value={jenisBank}
                          onChange={(e) => setJenisBank(e.target.value)}
                          className="w-full border rounded-md px-3 py-2"
                      >
                          <option value="">-= Pilih =-</option>
                          <option value="BCA">BCA</option>
                          <option value="BNI">BNI</option>
                          <option value="BRI">BRI</option>
                          <option value="MANDIRI">MANDIRI</option>
                          <option value="BSI">BSI</option>
                          <option value="CIMB">CIMB NIAGA</option>
                      </select>
                  </div>

                  {/* Periode */}
                  <div className="col-span-2">
                      <div className="flex items-end gap-2">

                          <div className="flex-1">
                              <label className="block text-sm font-semibold mb-2">
                                  Tanggal Awal
                              </label>

                              <DatePicker
                                  selected={tanggalAwal}
                                  onChange={setTanggalAwal}
                                  dateFormat="dd/MM/yyyy"
                                  placeholderText="dd/MM/yyyy"
                                  className="w-full border rounded-md px-3 py-2"
                                  popperPlacement="bottom-start"
                                  popperProps={{ strategy: "fixed" }}
                              />
                          </div>

                          <div className="flex-1">
                              <label className="block text-sm font-semibold mb-2">
                                  Tanggal Akhir
                              </label>

                              <DatePicker
                                  selected={tanggalAkhir}
                                  onChange={setTanggalAkhir}
                                  dateFormat="dd/MM/yyyy"
                                  placeholderText="dd/MM/yyyy"
                                  className="w-full border rounded-md px-3 py-2"
                                  popperPlacement="bottom-start"
                                  popperProps={{ strategy: "fixed" }}
                                  minDate={tanggalAwal}
                              />
                          </div>

                      </div>
                  </div>

                  {/* Button */}
                  <div className="col-span-2 flex gap-2 justify-start">
                    <button
                        //onClick={handleUpload}
                        className="bg-green-600 hover:bg-green-700 text-white px-5 py-2 rounded-md flex items-center gap-2 shadow"
                    >
                        <FaUpload />
                        Upload
                    </button>

                    <button
                        onClick={closeTopDrawer}
                        className="bg-red-500 hover:bg-red-600 text-white px-5 py-2 rounded-md flex items-center gap-2 shadow"
                    >
                        <FaTimes />
                        Close
                    </button>
                  </div>
              </div>
          </TopDrawer>
        )}

        {showDrawer && (
          <BottomDrawer
            isOpen={showDrawer}
            onClose={() => setActiveDrawer(null)}
            height="300px"
          >
            <div className="flex flex-col h-full">
              {/* === File Picker === */}
              <div className="flex-1 flex items-center justify-center px-4 mb-12">
                <label
                  ref={labelRef}
                  htmlFor="fileUpload"
                  tabIndex={0}
                  className={`w-full max-w-md flex flex-col items-center justify-center border-2 border-dashed rounded-xl cursor-pointer p-8 transition duration-300
                    ${selectedFile ? 'border-green-400 bg-green-100/10' : 'border-white hover:bg-white/10'}
                  `}
                >
                  {selectedFile && selectedFile.length > 0 ? (
                    <>
                      <FaCheckCircle className="h-12 w-12 text-green-400 mb-2" />
                      {selectedFile.length === 1 ? (
                        <>
                          <p className="text-green-300 text-center text-sm">
                            {selectedFile[0].name}
                          </p>
                          <p className="text-white text-xs mt-1">File berhasil dipilih</p>
                        </>
                      ) : (
                        <>
                          <p className="text-green-300 text-center text-sm">
                            {selectedFile.length} File dipilih
                          </p>
                          <div className="text-white text-xs mt-1 max-h-24 overflow-y-auto text-center">
                            {selectedFile.map((file, index) => (
                              <span key={index}>
                                {file.name}
                                {index < selectedFile.length - 1 ? " | " : ""}
                              </span>
                            ))}
                          </div>
                        </>
                      )}
                    </>
                  ) : (
                    <>
                      <FaCloudUploadAlt className="h-12 w-12 text-white mb-2" />
                      <p className="text-center text-white">
                        <strong>Klik untuk unggah / Pilih File</strong>
                      </p>
                    </>
                  )}

                  <input
                    id="fileUpload"
                    ref={fileInputRef}
                    type="file"
                    accept={".csv, .txt"}
                    onChange={handleFileChange}
                    multiple
                    className="hidden"
                  />
                </label>
              </div>

              {/* === Footer === */}
              <div className="fixed bottom-0 left-[-6%] right-0 border-t flex items-center justify-between z-10">
                <div className="trapezium-box text-white text-3xl shadow-md mt-[-8px] flex items-center justify-center h-[50px] w-[280px] bg-yellow-400">
                  Upload Bank Statement
                </div>

                <div className="flex gap-2 px-2">
                  <button
                    type="submit"
                    onClick={handleSubmit}
                    className="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 flex items-center gap-2"
                  >
                    <FaUpload className="w-4 h-4" />Upload
                  </button>

                  <button
                    onClick={resetFilePicker}
                    className="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700 flex items-center gap-2"
                  >
                    <FaRecycle className="w-4 h-4" />Reset
                  </button>
                </div>
              </div>
            </div>
          </BottomDrawer>
        )}

        {/* TABLE */}
        <ReusableTableNew
          data={data}
          columns={columns}
          periodFilter={selectedPeriode}
          setPeriodFilter={setSelectedPeriode}
          periodOptions={periodeList}
          globalFilter={globalFilter}
          setGlobalFilter={setGlobalFilter}
          categoryFilter={selectedKategori}
          setCategoryFilter={setSelectedKategori}
          onSelectionChange={setSelectedRows} 
          leftElement={
            <div className="flex gap-2">
              <button
                onClick={toggleImportMutasi}
                className="flex items-center gap-2 bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded"
                title="Upload Bank Statement"
                >
                <FaFileImport className="w-5 h-5" /> Import Mutasi
              </button>

              <button
                onClick={() => setDrawerOpen(true)}
                className="flex items-center gap-2 bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded"
                title="Upload Bank Statement"
                >
                <FaSitemap className="w-5 h-5" /> Import GL
              </button>

              <button
                onClick={() => handleDeleteAction(selectedRows)}
                disabled={selectedRows.length === 0}
                className={`flex items-center gap-2 px-3 py-1 rounded text-white
                  ${selectedRows.length > 0
                    ? "bg-red-500 hover:bg-red-600 cursor-pointer"
                    : "bg-gray-400 cursor-not-allowed"
                  }`}
                title="Hapus Mutasi"
              >
                <FaTrash className="w-5 h-5" /> Delete
              </button>
            </div>
          }
          rightElement={null}
        />
      </div>
    </main>
  );
}
