import React, { useEffect, useState, useRef, useCallback } from 'react';
import Swal from "sweetalert2";
import ReusableTable from "../components/ReusableTable";
import Menu_LPD from '../components/Menu_LPD';
import { useNavigate } from 'react-router-dom';
import { useNoRab } from '../contexts/NoRabContext';
import CenterDrawer from "../components/CenterDrawer";
import { fileUrl } from "../config/fileUrl"
import { formatDate } from "../utility/textFormatter";
import { HiMenu, HiUpload, HiX } from "react-icons/hi";
import { FaFilePdf, FaTrash } from 'react-icons/fa';
import TableLoading from "../components/TableLoading";

export default function Berkas_lpd() {
  const [globalFilter, setGlobalFilter] = useState("");
  const { updateNoRab } = useNoRab();
  const navigate = useNavigate();
  const searchRef = useRef(null);
  const fileInputRef = useRef(null);
  const dropzoneRef = useRef(null);
  const uploadRef = useRef(null);
  const cabang = sessionStorage.getItem("cabang");
  const [data, setData] = useState([]);
  const [isMenuOpen, setMenuOpen] = useState(false);
  const [openCenterDrawer, setOpenCenterDrawer] = useState(false);
  const [selectedToko, setSelectedToko] = useState(null);
  const [tglWrlb, setTglWrlb] = useState(null);
  const [noRab, setNoRab] = useState(null);
  const [drawerLabel, setDrawerLabel] = useState("");
  const [selectedFile, setSelectedFile] = useState(null);
  const handleOpenMenu = () => setMenuOpen(true);
  const handleCloseMenu = () => setMenuOpen(false);
  
  useEffect(() => {
    searchRef.current?.focus();
  }, [])


  useEffect(() => {
    if (openCenterDrawer) {
        setTimeout(() => {
            fileInputRef.current?.focus();
            dropzoneRef.current?.focus();
        }, 100);
    }
  }, [openCenterDrawer]);

  useEffect(() => {
    if (selectedFile) {
        const timer = setTimeout(() => {
        uploadRef.current?.focus();
        }, 50);

        return () => clearTimeout(timer);
    }
  }, [selectedFile]);


  /* ================= PROSES MEMUAT DATA ================= */
  const [initialLoading, setInitialLoading] = useState(true);
  const [loading, setLoading] = useState(false);

  const fetchData = useCallback(() => {
    if (!cabang) return;
    
    if (initialLoading) {
      setInitialLoading(true);
    } else {
      setLoading(true);
    }

    fetch(`/api/lpd?cabang=${cabang}`)
        .then((res) => res.json())
        .then((res) => {
            setData(res.data);
        })
        .catch((err) => {
            console.error("❌ Fetch error:", err);
        })
        .finally(() => {
            setInitialLoading(false);
            setLoading(false);
        });
  }, [cabang, initialLoading]);

  useEffect(() => {
    fetchData();
  }, [cabang]);

  const handleOpenDrawer = (label, toko, tgl, rab) => {
    setDrawerLabel(label);
    setSelectedToko(toko);
    setTglWrlb(tgl);
    setNoRab(rab);
    setOpenCenterDrawer(true);
  };

  const renderFileLink = (folder, filename, label = 'Lihat File', onUploadClick, onDeleteClick) => {
    if (!filename) {
        return (
        <div className="flex items-center justify-center gap-2">
            <button
                onClick={onUploadClick}
                className="bg-blue-500 hover:bg-blue-600 text-white px-3 py-2 text-xs rounded"
            >
            <HiUpload className="w-4 h-4" />
            </button>
        </div>
        );
    }

    const encodedFilename = encodeURIComponent(filename);
    const fileUrlFull = fileUrl(`/file/${folder}/${encodedFilename}?v=${Date.now()}`);
    
    return (
        <div className="flex items-center justify-center gap-2">
        <a
            href={fileUrlFull}
            target="_blank"
            rel="noopener noreferrer"
            title={`Lihat ${label}`}
            className="bg-green-600 hover:bg-green-700 text-white px-3 py-2 text-xs rounded flex items-center gap-1 justify-center"
        >
            <FaFilePdf className="w-4 h-4" />
        </a>
        <button
            onClick={onDeleteClick}
            className="bg-red-500 hover:bg-red-600 text-white px-3 py-2 text-xs rounded"
        >
            <FaTrash className="w-4 h-4" />
        </button>
        </div>
    );
  };

  const handleEdit = (SiteData) => {
    updateNoRab(SiteData.no_rab);
    navigate('/lpd-detail');
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
    { header: 'Nama Toko', accessorKey: 'nama_toko' },
    { header: 'Jenis', accessorKey: 'jns_toko' },
    {
        header: 'Tanggal Waralaba',
        accessorKey: 'tgl_wrlb',
        cell: info => formatDate(info.getValue()),
    },
    { header: 'No RAB', accessorKey: 'no_rab' },
    {
        header: 'Proposal',
        accessorKey: 'proposal',
        cell: info =>
            renderFileLink(
                'proposal',
                info.getValue(),
                'Proposal',
                () => handleOpenDrawer('Proposal', info.row.original.kd_toko, info.row.original.tgl_wrlb, info.row.original.no_rab),
                () => handleDelete('Proposal', info.row.original.no_rab)
            )
    },
    {
        header: 'RAB Rekap',
        accessorKey: 'rab_rekap',
        cell: info =>
            renderFileLink(
                'rab_rekap',
                info.getValue(),
                'RAB Rekap',
                () => handleOpenDrawer('RAB Rekap', info.row.original.kd_toko, info.row.original.tgl_wrlb, info.row.original.no_rab),
                () => handleDelete('RAB Rekap', info.row.original.no_rab)
            )
    },
    {
        header: 'RAB Detail',
        accessorKey: 'rab_detail',
        cell: info =>
            renderFileLink(
                'rab_detail',
                info.getValue(),
                'RAB Detail',
                () => handleOpenDrawer('RAB Detail', info.row.original.kd_toko, info.row.original.tgl_wrlb, info.row.original.no_rab),
                () => handleDelete('RAB Detail', info.row.original.no_rab)
            )
    },
    {
        header: 'Termin Investasi',
        accessorKey: 'termin_invest',
        cell: info =>
            renderFileLink(
                'termin_invest',
                info.getValue(),
                'Termin Investasi',
                () => handleOpenDrawer('Termin Investasi', info.row.original.kd_toko, info.row.original.tgl_wrlb, info.row.original.no_rab),
                () => handleDelete('Termin Investasi', info.row.original.no_rab)
            )
    },
  ];

  const resetFilePicker = () => {
    setSelectedFile(null);
    const input = document.getElementById("fileUpload");
    if (input) {
        input.value = "";
    }
  };

  useEffect(() => {
    const handleKeyDown = (e) => {
        const { key, code, altKey } = e;

        if (key === 'Escape') {
            if (isMenuOpen) setMenuOpen(false);
            if (openCenterDrawer){
                setOpenCenterDrawer(false);
                resetFilePicker();
            }
        }

        if (altKey) {
            switch (code) {
                case 'KeyM':
                    e.preventDefault();
                    setMenuOpen(true);
                    setShowDrawer(false);
                    break;
            }
        }
    };

    window.addEventListener('keydown', handleKeyDown);
    return () => window.removeEventListener('keydown', handleKeyDown);
  }, [ isMenuOpen, openCenterDrawer ]);

  const handleSubmitUpload = () => {
    if (!selectedFile || !noRab || !drawerLabel || !selectedToko || !tglWrlb) {
        Swal.fire("Peringatan", "Lengkapi semua data sebelum mengunggah file.", "warning");
        return;
    }

    const formData = new FormData();
    formData.append("file", selectedFile);
    formData.append("no_rab", noRab);
    formData.append("label", drawerLabel);
    formData.append("kd_toko", selectedToko);
    formData.append("tgl_wrlb", tglWrlb);

    fetch(`/api/lpd-berkas`, {
        method: "POST",
        body: formData,
    })
        .then(res => res.json())
        .then(res => {
            if (res.success) {
                Swal.fire("Berhasil", `File ${res.filename} berhasil diunggah!`, "success");
                setOpenCenterDrawer(false);
                setSelectedFile(null);
                fetchData();
            } else {
                Swal.fire("Gagal", res.message || "Terjadi kesalahan saat upload", "error");
            }
        })
        .catch(err => {
            console.error("Upload error:", err);
            Swal.fire("Error", "Terjadi kesalahan jaringan atau server", "error");
        });
  };

  const handleDelete = (label, noRab) => {
    Swal.fire({
        title: "Konfirmasi",
        text: `Apakah kamu yakin ingin menghapus file ${label}?`,
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: "Ya, hapus",
        cancelButtonText: "Batal",
    }).then((result) => {
        if (result.isConfirmed) {
            fetch(`/api/lpd-berkas-delete`, {
                method: "POST",
                headers: {
                    "Accept": "application/json",
                    "Content-Type": "application/json",
                },
                body: JSON.stringify({ label, no_rab: noRab }),
            })
                .then((res) => res.json())
                .then((res) => {
                    if (res.success) {
                        Swal.fire("Berhasil", res.message, "success");
                        fetchData();
                    } else {
                        Swal.fire("Gagal", res.message, "error");
                    }
                })
                .catch((err) => {
                    console.error("Delete error:", err);
                    Swal.fire("Error", "Gagal menghubungi server", "error");
                });
        }
    });
  };

  return (
    <main className="flex-1 px-2 py-2 z-10 text-white h-[calc(100vh-40px)] overflow-hidden">
      <div className="h-full bg-white/60 rounded-lg shadow-lg text-gray-800 w-full flex flex-col">
        <div className="relative flex items-center justify-center mb-4 px-4 pt-4">
          <div className="mb-4 text-center">
            <h2 className="text-xl font-bold">BERKAS PENDUKUNG LPD</h2>
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
              setShowDrawer(true);
          }}
        />

        <div className="flex-1 overflow-y-auto px-4 pb-4 space-y-4 pt-1">
            {initialLoading ? (
              <TableLoading />
            ) : (
                <ReusableTable
                    columns={columns}
                    data={data}
                    globalFilter={globalFilter}
                    setGlobalFilter={setGlobalFilter}
                    searchInputRef={searchRef}
                    tableClassName="min-w-full border-collapse"
                    theadClassName="bg-gray-100 sticky top-0 z-10"
                    thClassName="p-2 border-b text-sm font-semibold text-left bg-white"
                    tdClassName="p-2 border-b text-sm text-gray-700"
                />
            )}
        </div>

        {openCenterDrawer && (
            <CenterDrawer
                isOpen={openCenterDrawer}
                onClose={() => setOpenCenterDrawer(false)}
                borderColor="rgba(0, 0, 0, 0.7)"
                bodyBg="rgba(255, 255, 255, 0.9)"
                title={`Upload ${drawerLabel}`}
                widthClass="max-w-full"
            >
                {/* WRAPPER RELATIVE */}
                <div className="relative">

                    {/* CLOSE BUTTON */}
                    <button
                        type="button"
                        onClick={() => setOpenCenterDrawer(false)}
                        className="absolute top-3 right-3 text-gray-500 hover:text-red-600 transition"
                        title="Tutup"
                    >
                        <HiX className="w-6 h-6" />
                    </button>

                    {/* CONTENT */}
                    <div className="flex flex-col items-center justify-center gap-4 p-4">
                        <p className="text-center text-gray-700">
                            Silakan unggah file untuk <strong>{drawerLabel}</strong>
                        </p>

                    <label
                        htmlFor="fileUpload"
                        ref={dropzoneRef}
                        tabIndex={0}
                        onKeyDown={(e) => {
                            if (e.key === "Enter") {
                            e.preventDefault();
                            fileInputRef.current?.click();
                            }
                        }}
                        className="w-full max-w-md border-2 border-dashed border-gray-300 rounded-lg p-6 text-center cursor-pointer hover:border-blue-400 transition duration-200"
                    >
                        <HiUpload className="w-10 h-10 mx-auto text-blue-500 mb-2" />
                        <p className="text-gray-600">
                        {selectedFile?.name || "Klik atau seret file PDF ke sini"}
                        </p>
                        <input
                            type="file"
                            ref={fileInputRef}
                            id="fileUpload"
                            accept=".pdf"
                            className="hidden"
                            onChange={(e) => {
                                const file = e.target.files?.[0];
                                if (file && file.type === "application/pdf") {
                                    setSelectedFile(file);
                                } else {
                                    Swal.fire(
                                        "Peringatan",
                                        "Hanya file PDF yang diperbolehkan!",
                                        "warning"
                                    );
                                e.target.value = null;
                                setSelectedFile(null);
                                }
                            }}
                        />
                    </label>

                    <div className="flex gap-4">
                        <button
                            type="button"
                            ref={uploadRef}
                            onClick={handleSubmitUpload}
                            disabled={!selectedFile}
                            className={`px-4 py-2 rounded text-white flex items-center gap-2 ${
                                selectedFile
                                ? "bg-blue-600 hover:bg-blue-700"
                                : "bg-gray-400 cursor-not-allowed"
                            }`}
                        >
                        <HiUpload className="w-4 h-4" />
                        Upload
                        </button>
                    </div>
                    </div>
                </div>
            </CenterDrawer>
        )}
      </div>
    </main>
  );
}