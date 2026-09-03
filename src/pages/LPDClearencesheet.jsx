import React, { useEffect, useState, useMemo, useCallback, useRef } from "react";
import { useNavigate } from "react-router-dom";
import { formatDate, formatRupiah, formatAmount } from "../utility/textFormatter";
import Menu_LPD from '../components/Menu_LPD';
import Swal from "sweetalert2";
import ReusableTable from "../components/ReactTable";
import CenterDrawer from "../components/CenterDrawer";
import LpdIdentitas from "../components/LpdIdentitas";
import TableLoading from "../components/TableLoading";
import { fileUrl } from "../config/fileUrl"
import { useLpdDetail } from '../hooks/useLpdDetail';
import { useRightPanel } from '../contexts/RightPanelContext';
import { useSidebar } from "../components/SidebarContext";
import { useNoRab } from '../contexts/NoRabContext';
import { useCabang } from "../contexts/CabangContext";
import { motion } from "framer-motion";
import { HiUpload, HiClipboard, HiMenu, HiSearch, HiPencil, HiMail, HiX } from "react-icons/hi";
import { FaTrash, FaHardHat, FaCheck, FaTimes, FaFileAlt, FaFileExcel, FaFilePdf, FaPaperPlane, FaUpload, FaMoneyBill, FaTools, FaToolbox, FaMoneyCheck } from "react-icons/fa";

export default function LPDClearencesheet() {
  const [data, setData] = useState([]);
  const [periode, setPeriode] = useState([]);
  const [expandedRow, setExpandedRow] = useState(null);
  const { cabang } = useCabang();
  const navigate = useNavigate();
  const { updateNoRab } = useNoRab();
  const toggleExpand = (rowId) => {
    setExpandedRow((prev) => (prev === rowId ? null : rowId));
  };
  
  const [openCenterDrawer, setOpenCenterDrawer] = useState(false);
  const [selectedFile, setSelectedFile] = useState(null);
  const [selectedRab, setSelectedRab] = useState(null);
  const [selectedToko, setSelectedToko] = useState(null);
  const [selectedTglWrlb, setSelectedTglWrlb] = useState(null);
  const [drawerMode, setDrawerMode] = useState(null);
  const penyelesaianRef = useRef();
  const [isMenuOpen, setMenuOpen] = useState(false);
  const handleOpenMenu = () => setMenuOpen(true);
  const handleCloseMenu = () => setMenuOpen(false);
  const sendMailButtonRef = useRef(null);
  const { openLPDPanel, refreshFlag } = useRightPanel();
  const [fileSurkas, setFileSurkas] = useState(null);
  
  const {
    modalData,
    identitas,
    totalToko,
    dppToko,
    totalModal,
    totalEstimasi,
    totalSarana,
    totalRealisasi,
    SaranaBA,
    totalDatPR,
    berkas,
    bapjRenovData,
  } = useLpdDetail(selectedRab);
  
  const sisaSarana = totalSarana - totalRealisasi - SaranaBA;
  const selisihEstimasi = totalEstimasi - identitas.rab_final;
  const allRenovFilled = bapjRenovData.length === 0 || bapjRenovData.length > 0 && bapjRenovData.every(item => item.flag_renov && item.flag_renov.trim() !== '');
  const canSendMail = sisaSarana === 0 && selisihEstimasi < 5000 && allRenovFilled;

  useEffect(() => {
    if (openCenterDrawer && canSendMail) {
      setTimeout(() => {
        sendMailButtonRef.current?.focus();
      }, 100);
    }
  }, [openCenterDrawer, canSendMail]);

  const [penyelesaian, setPenyelesaian] = useState("");
  const [tanggalFinal, setTanggalFinal] = useState("");
  const [keterangan, setKeterangan] = useState("");
  useEffect(() => {
    if (identitas?.keterangan) {
      setKeterangan(identitas.keterangan);
    }
  }, [identitas.keterangan]);

  useEffect(() => {
    if (openCenterDrawer && drawerMode === "finalisasi" && penyelesaianRef.current) {
      penyelesaianRef.current.focus();
    }
  }, [openCenterDrawer]);
  
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

    fetch(`/api/lpd-cs?cabang=${cabang}`)
      .then((res) => res.json())
      .then((res) => {
        const copiedData = res.data.map(item => ({ ...item }));
        setData(copiedData);
        setPeriode(res.periode);
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
  }, [cabang, refreshFlag]);

  const columns = useMemo(() => [
    {
      header: "No",
      cell: (info) => info.row.index + 1,
      meta: { className: "text-center" },
    },
    {
      header: "Kode",
      accessorKey: "kd_toko",
      cell: ({ row }) => {
        const endDate = periode[0]?.end_date ? new Date(periode[0].end_date) : null;
        const tglJT = new Date(row.original.tgl_jt);
        const isLate = endDate && tglJT <= endDate;

        return (
          <span className={isLate ? "text-red-600" : ""}>
            {row.original.kd_toko}
          </span>
        );
      },
    },
    {
      header: "Nama Toko",
      accessorKey: "nama_toko",
      meta: { className: "whitespace-nowrap" },
      cell: ({ row }) => {
        const endDate = periode[0]?.end_date ? new Date(periode[0].end_date) : null;
        const tglJT = new Date(row.original.tgl_jt);
        const isLate = endDate && tglJT <= endDate;
        const isCS = row.original.status === "CS";
        return (
          <span className={isLate ? "text-red-600" : ""}>
            {row.original.nama_toko}
            {isCS && <span className="ml-1 text-yellow-500">⭐</span>}
          </span>
        );
      },
    },
    {
      header: "Jenis",
      accessorKey: "jns_toko",
    },
    {
      header: "No RAB",
      accessorKey: "no_rab",
      cell: ({ row }) => {
        const [copied, setCopied] = useState(false);

        const handleCopy = () => {
          navigator.clipboard.writeText(row.original.no_rab);
          setCopied(true);
          setTimeout(() => setCopied(false), 2000);
        };

        return (
          <div className="flex items-center gap-2 whitespace-nowrap">
            <button
              onClick={() => toggleExpand(row.id)}
              className="btn btn-success flex items-center"
              title="Show Detail Menu"
            >
              {row.original.no_rab}
            </button>
            <button
              onClick={handleCopy}
              className="btn btn-primary flex items-center"
              title="Copy to clipboard"
            >
              <HiClipboard className="w-5 h-5" />
            </button>
            {copied && (
              <span className="text-green-600 text-xs transition-opacity duration-300">
                Copied!
              </span>
            )}
          </div>
        );
      },
    },
    {
      header: "Tgl Waralaba",
      accessorKey: "tgl_wrlb",
      cell: ({ getValue }) => formatDate(getValue()),
      meta: { className: "text-center whitespace-nowrap w-[110px]" },
    },
    {
      header: "Tgl JT",
      accessorKey: "tgl_jt",
      cell: ({ getValue }) => formatDate(getValue()),
      meta: { className: "text-center whitespace-nowrap w-[110px]" },
    },
    {
      header: "Investasi",
      accessorKey: "total_investasi",
      cell: (info) =>
        Number(info.getValue() ?? 0).toLocaleString("en-US"),
      meta: { className: "text-right" },
    },
    {
      header: "Realisasi",
      accessorKey: "total_realisasi",
      cell: (info) =>
        Number(info.getValue() ?? 0).toLocaleString("en-US"),
      meta: { className: "text-right" },
    },
    {
      header: "Sisa LPD",
      accessorKey: "sisa_lpd",
      cell: (info) =>
        Number(info.getValue() ?? 0).toLocaleString("en-US"),
      meta: { className: "text-right" },
    },
    {
      header: "Keterangan",
      accessorKey: "keterangan",
      cell: (info) => (
        <span className="whitespace-pre-line">{info.getValue()}</span>
      ),
    },
  ], [periode]);
  
  const { isCollapsed, setIsCollapsed } = useSidebar();

  const handleRightPanelToggle = (site = null, mode = null) => {
    setIsCollapsed(true);
    setMenuOpen(false);
    openLPDPanel(mode, site);
  };

  const handleEdit = (SiteData) => {
    updateNoRab(SiteData.no_rab);
    navigate('/lpd-detail');
  };

  useEffect(() => {
    const handleKeyDown = (e) => {
      if (e.key === 'Escape') {
        if (isMenuOpen) setMenuOpen(false);
        if (openCenterDrawer){
          setOpenCenterDrawer(false);
          resetFilePicker();
        }
      }
      
      if (e.altKey && e.code === 'KeyM') {
        e.preventDefault();
        setMenuOpen(true);
      }
    };

    window.addEventListener('keydown', handleKeyDown);

    return () => {
      window.removeEventListener('keydown', handleKeyDown);
    };
  }, [isMenuOpen, openCenterDrawer]);
  
  const handleDiscardCS = async (noRab) => {
    const confirm = await Swal.fire({
      title: 'Yakin Unfinalisasi?',
      text: `Anda akan mengubah status LPD: ${noRab} menjadi New?`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Ya, Discard CS',
      cancelButtonText: 'Batal',
      confirmButtonColor: '#d33',
      cancelButtonColor: '#3085d6',
    });

    if (confirm.isConfirmed) {
      try {
        const response = await fetch(`/api/discard-cs`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ no_rab: noRab }),
        });

        const result = await response.json();

        if (result.success) {
          Swal.fire('Berhasil', result.message, 'success');
          fetchData();
        } else {
          Swal.fire('Gagal', result.message || 'Terjadi kesalahan.', 'error');
        }
      } catch (error) {
        Swal.fire('Error', 'Tidak dapat terhubung ke server.', 'error');
      }
    }
  };
  
  const handleOpenUploadDrawer = (no_rab, kd_toko, tgl_wrlb, mode) => {
    setSelectedRab(no_rab);
    setSelectedToko(kd_toko);
    setSelectedTglWrlb(tgl_wrlb);
    setDrawerMode(mode);
    setOpenCenterDrawer(true);
  };

  const handleSubmitUpload = async () => {
    if (!selectedRab || !selectedToko) {
        Swal.fire("Peringatan", "Tidak ada Data Yang Dipilih", "warning");
        return;
    }

    const formData = new FormData();
    formData.append("file", selectedFile);
    formData.append("no_rab", selectedRab);
    formData.append("kd_toko", selectedToko);
    formData.append("tgl_wrlb", selectedTglWrlb);
    
    try {
        const response = await fetch(`/api/lpd-cs-final`, {
            method: "POST",
            body: formData,
        });

        const res = await response.json();

        if (res.success) {
            await Swal.fire("Berhasil", `File ${res.filename} berhasil diunggah!`, "success");
            setOpenCenterDrawer(false);
            setSelectedFile(null);
            fetchData();
        } else {
            Swal.fire("Gagal", "Terjadi kesalahan saat upload", "error");
        }
    } catch (err) {
        console.error("Upload error:", err);
        Swal.fire("Error", "Terjadi kesalahan jaringan atau server", "error");
    }
  };
  
  const resetFilePicker = () => {
    setSelectedFile(null);
    const input = document.getElementById("fileUpload");
    if (input) {
        input.value = "";
    }
  };

  const handleDeleteClearanceSheet = async (noRAB, kdToko, namaToko) => {
    Swal.fire({
      title: "Konfirmasi",
      html: `Apakah Anda yakin ingin menghapus Clearancesheet Final<b> ${namaToko} ${kdToko}</b>?`,
      icon: "warning",
      showCancelButton: true,
      confirmButtonText: "Ya, Hapus",
      cancelButtonText: "Batal",
      confirmButtonColor: "#d33",
      cancelButtonColor: "#3085d6",
    }).then(async (result) => {
      if (result.isConfirmed) {
        try {
          const response = await fetch(
            `/api/delete-clearancesheet`,
            {
              method: "POST",
              headers: { "Content-Type": "application/json" },
              body: JSON.stringify({ rab: noRAB }),
            }
          );

          const data = await response.json();

          if (response.ok) {
            Swal.fire({
              icon: "success",
              title: "Berhasil",
              html: data.message || "Clearancesheet Final berhasil dihapus.",
              timer: 1800,
              showConfirmButton: false,
            });

            fetchData();
          } else {
            Swal.fire({
              icon: "error",
              title: "Gagal",
              html: data.error || data.message || "Gagal menghapus Clearancesheet Final.",
            });
          }
        } catch (err) {
          Swal.fire({
            icon: "error",
            title: "Error",
            text: "Terjadi kesalahan saat menghubungi server.",
          });
        }
      }
    });
  };

  const handleFinalisasi = async (e) => {
    e.preventDefault();

    /* ================= VALIDATION ================= */
    if (!selectedRab || !penyelesaian || !tanggalFinal || !keterangan) {
      Swal.fire(
        "Peringatan",
        "Lengkapi semua data finalisasi",
        "warning"
      );
      return;
    }

    if (penyelesaian === "Pot Surkas" && !fileSurkas) {
      Swal.fire(
        "Peringatan",
        "Pot Surkas wajib melampirkan bukti file",
        "warning"
      );
      return;
    }

    /* ================= CONFIRMATION ================= */
    const confirm = await Swal.fire({
      title: "Konfirmasi Finalisasi",
      text: "Yakin ingin Finalisasi LPD ini?",
      icon: "question",
      showCancelButton: true,
      confirmButtonText: "Ya, Finalisasi",
      cancelButtonText: "Batal",
    });

    if (!confirm.isConfirmed) return;

    /* ================= BUILD PAYLOAD ================= */
    const formData = new FormData();
    formData.append("no_rab", selectedRab);
    formData.append("penyelesaian", penyelesaian);
    formData.append("tgl_final", tanggalFinal);
    formData.append("keterangan", keterangan);

    if (penyelesaian === "Pot Surkas") {
      formData.append("file", fileSurkas);
    }

    /* ================= REQUEST ================= */
    try {
      const response = await fetch(`/api/lpd-finalisasi`, {
        method: "POST",
        body: formData, // multipart/form-data (auto)
      });

      const result = await response.json();

      if (!response.ok || !result.success) {
        throw new Error(result.message || "Terjadi kesalahan saat finalisasi");
      }

      /* ================= SUCCESS ================= */
      await Swal.fire("Berhasil", result.message, "success");

      setOpenCenterDrawer(false);
      setPenyelesaian("");
      setTanggalFinal("");
      setKeterangan("");
      setFileSurkas(null);

      fetchData();

    } catch (error) {
      console.error("Finalisasi error:", error);

      Swal.fire(
        "Error",
        error.message || "Terjadi kesalahan jaringan atau server",
        "error"
      );
    }
  };

  const handleSendLPD  = async () => {
    try {
      const response = await fetch(`/api/send-lpd-fad`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({ rab: selectedRab }),
      });

      const result = await response.json();

      if (response.ok) {
        Swal.fire({
          icon: 'success',
          title: 'Sukses!',
          text: result.message || 'Email berhasil dikirim.',
        }).then(() => {
          setOpenCenterDrawer(false);
          fetchData();
        });
      } else {
        Swal.fire({
          icon: 'error',
          title: 'Gagal!',
          text: result.message || 'Gagal mengirim email.',
        });
      }
    } catch (error) {
      Swal.fire({
        icon: 'error',
        title: 'Error!',
        text: error.message || 'Terjadi kesalahan jaringan.',
      });
    }
  }
  
  return (
    <main className="flex-1 px-4 py-2 z-10 text-white">
      <div className="h-[calc(100vh-50px)] bg-white/60 rounded-lg p-4 shadow-lg text-gray-800 w-full">
        <div className="relative flex items-center justify-center mb-4">
          <div className="box-header text-white shadow-md flex items-center justify-center mx-auto mt-[-17px] mb-2 h-[60px] w-1/2 bg-blue-400 clip-path-custom">
            <h2 className="text-xl text-center font-semibold mb-3">Clearencesheet LPD Cabang {cabang}</h2>
          </div>

          <div className="absolute right-0">
            <button
              onClick={handleOpenMenu}
              className="rounded bg-gray-700 hover:bg-gray-800 text-white px-2 py-1"
              title="Buka Menu"
            >
              <HiMenu className="w-5 h-5" />
            </button>
          </div>

          <Menu_LPD
            isOpen={isMenuOpen}
            onClose={handleCloseMenu}
            onToggleRightPanel={handleRightPanelToggle}
          />
        </div>
        
        {initialLoading ? (
          <TableLoading text="Memuat Data Outs LPD..." />
        ) : (
          <ReusableTable
            columns={columns}
            data={data}
            expandedRow={expandedRow}
            toggleExpand={toggleExpand}
            renderSubComponent={(row) => (
              expandedRow === row.id && (
                <tr>
                  <td colSpan={columns.length} className="p-0">
                    <div
                      className={`transition-all duration-500 ease-in-out overflow-hidden`}
                      style={{
                        maxHeight: expandedRow === row.id ? '200px' : '0',
                        opacity: expandedRow === row.id ? 1 : 0,
                      }}
                    >
                      <div className="flex gap-4 p-4 bg-gray-200 border-t">
                        {/* Kiri */}
                        <motion.div
                          initial={{ opacity: 0, x: 100 }}
                          animate={{ opacity: 1, x: 0 }}
                          transition={{ duration: 0.4, ease: "easeOut" }}
                          className="w-1/2 flex gap-2"
                        >
                          <button
                            className="btn btn-primary flex items-center gap-1"
                            title="View Rekap"
                            onClick={() => handleRightPanelToggle(row.original, "view")}
                          >
                            <HiSearch className="w-5 h-5" />
                          </button>

                          <button
                            className="btn btn-info flex items-center gap-1"
                            title="Edit Perhitungan"
                            onClick={() => handleEdit(row.original)}
                          >
                            <HiPencil className="w-5 h-5" />
                          </button>
                          <button
                            className="btn btn-danger flex items-center gap-1"
                            onClick={() => handleOpenUploadDrawer(row.original.no_rab, row.original.kd_toko, row.original.tgl_wrlb, "mail")}
                            title="Email Perhitungan"
                          >
                            <HiMail className="w-5 h-5" />
                          </button>

                          {row.original.excel && (
                            <button
                              className="btn btn-success flex items-center gap-1"
                              title={row.original.excel}
                              onClick={() =>
                                window.open(
                                  fileUrl(`/file/lpd/${row.original.excel}?v=${Date.now()}`),
                                  "_blank"
                                )
                              }
                            >
                              <FaFileExcel className="w-5 h-5" />
                            </button>
                          )}
                          {row.original.pdf && (
                            <button
                              className="btn btn-warning flex items-center gap-1"
                              title={row.original.pdf}
                              onClick={() =>
                                window.open(
                                  fileUrl(`/file/lpd/${row.original.pdf}?v=${Date.now()}`),
                                  "_blank"
                                )
                              }
                            >
                              <FaFilePdf className="w-5 h-5" />
                            </button>
                          )}
                          {row.original.status === 'CS' && (
                            (() => {
                              let fileName = '';
                              let folder = '';

                              if (row.original.cs_final && row.original.cs_final.trim() !== '') {
                                fileName = row.original.cs_final;
                                folder = 'file/clearencesheet';
                              } else if (row.original.draft_cs && row.original.draft_cs.trim() !== '') {
                                fileName = row.original.draft_cs;
                                folder = 'file/draft_cs';
                              }

                              return fileName ? (
                                <button
                                  className="btn btn-success flex items-center gap-1"
                                  title={fileName}
                                  onClick={() =>
                                    window.open(
                                      fileUrl(`/${folder}/${fileName}?v=${Date.now()}`),
                                      "_blank"
                                    )
                                  }
                                >
                                  <FaFileAlt className="w-5 h-5" />Clearencesheet
                                </button>
                              ) : null;
                            })()
                          )}
                          {(row.original.cs_final === '' || row.original.cs_final === null) && (
                            <button
                              className="btn btn-info flex items-center gap-1"
                              title="Upload CS Final"
                              onClick={() => handleOpenUploadDrawer(row.original.no_rab, row.original.kd_toko, row.original.tgl_wrlb, "upload")}
                            >
                              <FaUpload className="w-5 h-5" />
                            </button>
                          )}
                          {row.original.cs_final && (
                            <div className="flex items-center gap-2">
                              {/* Tombol Finalisasi */}
                              <button
                                className="btn btn-info flex items-center gap-1"
                                title="Finalisasi LPD"
                                onClick={() =>
                                  handleOpenUploadDrawer(
                                    row.original.no_rab,
                                    row.original.kd_toko,
                                    row.original.tgl_wrlb,
                                    "finalisasi"
                                  )
                                }
                              >
                                <FaPaperPlane className="w-5 h-5" />
                              </button>

                              {/* Tombol Hapus Clearance Sheet */}
                              <button
                                className="btn btn-danger flex items-center gap-1"
                                title="Hapus Clearancesheet Final"
                                onClick={() =>
                                  handleDeleteClearanceSheet(
                                    row.original.no_rab,
                                    row.original.kd_toko,
                                    row.original.nama_toko
                                  )
                                }
                              >
                                <FaTrash className="w-5 h-5" />
                              </button>

                            </div>
                          )}
                          <button
                            className="btn btn-warning flex items-center gap-1"
                            title="Discard CS LPD"
                            onClick={() => handleDiscardCS(row.original.no_rab)}
                          >
                            <HiX className="w-5 h-5" />
                          </button>
                        </motion.div>

                        {/* Kanan */}
                        <motion.div
                          initial={{ opacity: 0, x: -100 }}
                          animate={{ opacity: 1, x: 0 }}
                          transition={{ duration: 0.4, ease: "easeOut" }}
                          className="w-1/2 flex justify-end gap-2"
                        >
                          {row.original.rab_rekap && (
                            <button
                              className="btn btn-warning flex items-center gap-1"
                              title={row.original.rab_rekap}
                              onClick={() =>
                                window.open(
                                  fileUrl(`/file/rab_rekap/${row.original.rab_rekap}?v=${Date.now()}`),
                                  "_blank"
                                )
                              }
                            >
                              <FaFilePdf className="w-5 h-5" /> RAB Rekap
                            </button>
                          )}
                          {row.original.rab_detail && (
                            <button
                              className="btn btn-info flex items-center gap-1"
                              title={row.original.rab_detail}
                              onClick={() =>
                                window.open(
                                  fileUrl(`/file/rab_detail/${row.original.rab_detail}?v=${Date.now()}`),
                                  "_blank"
                                )
                              }
                            >
                              <FaFilePdf className="w-5 h-5" /> RAB Detail
                            </button>
                          )}
                          {row.original.termin_invest && (
                            <button
                              className="btn btn-danger flex items-center gap-1"
                              title={row.original.termin_invest}
                              onClick={() =>
                                window.open(
                                  fileUrl(`/file/termin_invest/${row.original.termin_invest}?v=${Date.now()}`),
                                  "_blank"
                                )
                              }
                            >
                              <FaFilePdf className="w-5 h-5" /> Termin Investasi
                            </button>
                          )}
                          {row.original.proposal && (
                            <button
                              className="btn btn-primary flex items-center gap-1"
                              title={row.original.proposal}
                              onClick={() =>
                                window.open(
                                  fileUrl(`/file/proposal/${row.original.proposal}?v=${Date.now()}`),
                                  "_blank"
                                )
                              }
                            >
                              <FaFilePdf className="w-5 h-5" /> Proposal
                            </button>
                          )}
                        </motion.div>
                      </div>
                    </div>

                  </td>
                </tr>
              )
            )}
          />
        )}

        {openCenterDrawer && (
          <CenterDrawer
            isOpen={openCenterDrawer}
            onClose={() => setOpenCenterDrawer(false)}
            borderColor="rgba(0, 0, 0, 0.7)"
            bodyBg="rgba(255, 255, 255, 0.9)"
            title={
              drawerMode === "upload"
                ? "Upload Clearencesheet Final"
                : drawerMode === "finalisasi"
                ? "Finalisasi LPD"
                : "Pesan Masuk"
            }
            widthClass="max-w-full"
          >
            {drawerMode === "upload" ? (
              // === MODE UPLOAD ===
              <div className="flex flex-col items-center justify-center gap-4 p-4">
                <p className="text-center text-gray-700">
                  Silakan Unggah Clearencesheet Final RAB : <strong>{selectedRab}</strong>
                </p>

                <label
                  htmlFor="fileUpload"
                  className="w-full max-w-md border-2 border-dashed border-gray-300 rounded-lg p-6 text-center cursor-pointer hover:border-blue-400 transition duration-200"
                >
                  <HiUpload className="w-10 h-10 mx-auto text-blue-500 mb-2" />
                  <p className="text-gray-600">
                    {selectedFile?.name || "Klik atau seret file PDF ke sini"}
                  </p>
                  <input
                    type="file"
                    id="fileUpload"
                    accept=".pdf"
                    className="hidden"
                    onChange={(e) => {
                      const file = e.target.files?.[0];
                      if (file && file.type === "application/pdf") {
                        setSelectedFile(file);
                      } else {
                        Swal.fire("Peringatan", "Hanya file PDF yang diperbolehkan!", "warning");
                        e.target.value = null;
                        setSelectedFile(null);
                      }
                    }}
                  />
                </label>

                <div className="flex gap-4">
                  <button
                    type="button"
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
            ) : drawerMode === "finalisasi" ? (
              // === MODE FINALISASI ===
              <>
                <LpdIdentitas
                  identitas={identitas}
                  no_rab={selectedRab}
                />
        
                <div className="space-y-1 my-4">
                  <div className="h-px bg-gray-400" />
                  <div className="h-px bg-gray-400" />
                </div>
              
                <div className="p-2">
                  <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    {/* Card 1 */}
                    <div className="bg-blue-100 shadow rounded-lg p-4 text-blue-800 flex flex-col gap-1 border border-blue-300">
                      <div className="flex flex-row justify-between items-start border-b border-blue-300 pb-1">
                        <div className="flex flex-col items-start gap-2">
                          <FaMoneyBill className="w-28 h-28 text-yellow-400 -rotate-45" />
                        </div>
      
                        <div className="flex flex-col items-end text-right gap-1 text-yellow-700">
                          <h4 className="italic">RAB : {formatRupiah(totalEstimasi)}</h4>
                          <h4 className="italic">Modal : {formatRupiah(totalModal)}</h4>
                          <h4 className="italic">Pek By Fsee : {formatRupiah(modalData[0]?.pek_by_frcsee || 0)}</h4>
                          <h4 className="italic">Selisih Investasi : {formatRupiah(totalModal + modalData[0]?.pek_by_frcsee - totalEstimasi)}</h4>
                        </div>
                      </div>
      
                      <h3 className="text-yellow-700 font-semibold mt-1">Investasi</h3>
                    </div>
      
                    {/* Card 2 */}
                    <div className="bg-blue-100 shadow rounded-lg p-4 text-blue-800 flex flex-col gap-1 border border-blue-300">
                      <div className="flex flex-row justify-between items-start border-b border-blue-300 pb-1">
                        <div className="flex flex-col items-start gap-2">
                          <FaTools className="w-28 h-28 text-green-600" />
                        </div>
      
                        <div className="flex flex-col items-end text-right gap-1 text-green-600">
                          <h4 className="italic">Total Sarana : {formatAmount(totalSarana || 0)}</h4>
                          <h4 className="italic">Sarana Realisasi : {formatAmount(totalRealisasi || 0)}</h4>
                          <h4 className="italic">Sarana Tidak Realisasi (BA) : {formatAmount(SaranaBA || 0)}</h4>
                          <h4 className="italic">Belum Realisasi : {formatAmount(totalSarana - totalRealisasi - SaranaBA || 0)}</h4>
                        </div>
                      </div>
      
                      <h3 className="text-green-600 font-semibold mt-1">Monitoring Sarana</h3>
                    </div>
      
                    {/* Card 3 */}
                    <div className="bg-blue-100 shadow rounded-lg p-4 text-blue-800 flex flex-col gap-1 border border-blue-300">
                      <div className="flex flex-row justify-between items-start border-b border-blue-300 pb-1">
                        <div className="flex flex-col items-start gap-2">
                          <FaToolbox className="w-28 h-28 text-cyan-600" />
                        </div>
      
                        <div className="flex flex-col items-end text-right gap-1 text-cyan-600">
                          <h4 className="italic">DAT/PR Terinput : {formatAmount(totalDatPR || 0)}</h4>
                          <h4 className="italic">DAT/PR Realisasi : {formatAmount(dppToko || 0)}</h4>
                          <h4 className="italic">DAT/PR Belum Input : {formatAmount(dppToko - totalDatPR || 0)}</h4>
                        </div>
                      </div>
      
                      <h3 className="text-cyan-600 font-semibold mt-1">DAT - PR</h3>
                    </div>
      
                    {/* Card 4 */}
                    <div className="bg-blue-100 shadow rounded-lg p-4 text-blue-800 flex flex-col gap-1 border border-blue-300">
                      <div className="flex flex-row justify-between items-start border-b border-blue-300 pb-1">
                        <div className="flex flex-col items-start gap-2">
                          <FaMoneyCheck className="w-28 h-28 text-red-600" />
                        </div>
      
                        <div className="flex flex-col items-end text-right gap-1 text-red-600">
                          <h4 className="italic">Modal : {formatRupiah(totalModal || 0)}</h4>
                          <h4 className="italic">Total Realisasi : {formatRupiah(totalToko || 0)}</h4>
                          <h4 className="italic">Sisa LPD : {formatRupiah(totalModal - totalToko)}</h4>
                        </div>
                      </div>
      
                      <h3 className="text-red-600 font-semibold mt-1">Summary LPD</h3>
                    </div>
                  </div>
                </div>

                <div className="space-y-1 my-4">
                  <div className="h-px bg-gray-400" />
                  <div className="h-px bg-gray-400" />
                </div>
                
                <div className="flex justify-center">
                  <form
                    onSubmit={handleFinalisasi}
                    className="w-full max-w-2xl"
                  >
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                      <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Penyelesaian</label>
                        <select
                          name="penyelesaian"
                          value={penyelesaian}
                          ref={penyelesaianRef}
                          onChange={(e) => setPenyelesaian(e.target.value)}
                          className="w-full border border-gray-300 rounded px-3 py-2"
                          required
                        >
                          <option value="">Pilih</option>
                          <option value="Trf">Transfer</option>
                          <option value="Pot Surkas">Pot Surkas</option>
                          <option value="Fsee">Transfer Frcsee</option>
                        </select>
                      </div>

                      <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Tanggal Final</label>
                        <input
                          type="date"
                          name="tanggal_final"
                          value={tanggalFinal}
                          onChange={(e) => setTanggalFinal(e.target.value)}
                          className="w-full border border-gray-300 rounded px-3 py-2"
                          required
                        />
                      </div>
                    </div>

                    {/* FILE PICKER – hanya jika Pot Surkas */}
                    <div className="mb-4">
                      <label className="block text-sm font-medium text-gray-700 mb-1">
                        Upload BA Pot Surkas (PDF)
                      </label>

                      <input
                        type="file"
                        accept="application/pdf"
                        disabled={penyelesaian !== "Pot Surkas"}
                        onChange={(e) => {
                          const file = e.target.files[0];
                          console.log("FILE PICKED:", file);
                          setFileSurkas(file);
                        }}
                        className={`w-full border rounded px-3 py-2 ${
                          penyelesaian !== "Pot Surkas"
                            ? "bg-gray-100 cursor-not-allowed"
                            : "border-gray-300"
                        }`}
                      />

                      {penyelesaian !== "Pot Surkas" && (
                        <p className="text-xs text-gray-500 mt-1">
                          Aktif jika penyelesaian = Pot Surkas
                        </p>
                      )}
                    </div>


                    {/* Keterangan */}
                    <div className="mb-4">
                      <label className="block text-sm font-medium text-gray-700 mb-1">
                        Keterangan
                      </label>
                      <input
                        type="text"
                        name="keterangan"
                        value={keterangan}
                        onChange={(e) => setKeterangan(e.target.value)}
                        className="w-full border border-gray-300 rounded px-3 py-2"
                      />
                    </div>


                    <div className="flex justify-center">
                      <button
                        type="submit"
                        className="flex items-center gap-2 bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded shadow"
                      >
                        <FaPaperPlane className="w-4 h-4" />
                        Finalisasi
                      </button>
                    </div>
                  </form>
                </div>
              </>
            ) : drawerMode === "mail" ? (
              // === MODE MAIL ===
              <>
                <LpdIdentitas
                  identitas={identitas}
                  no_rab={selectedRab}
                />
        
                <div className="space-y-1 my-4">
                  <div className="h-px bg-gray-400" />
                  <div className="h-px bg-gray-400" />
                </div>
              
                <div className="p-1">
                  <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    {/* Card 1 */}
                    <div className="bg-blue-100 shadow rounded-lg p-4 text-blue-800 flex flex-col gap-1 border border-blue-300">
                      <div className="flex flex-row justify-between items-start border-b border-blue-300 pb-1">
                        <div className="flex flex-col items-start gap-2">
                          <FaMoneyBill className="w-28 h-28 text-blue-600 -rotate-45" />
                        </div>
      
                        <div className="flex flex-col items-end text-right gap-1 text-yellow-700">
                          <h4 className="italic">RAB : {formatRupiah(totalEstimasi)}</h4>
                          <h4 className="italic">Modal : {formatRupiah(totalModal)}</h4>
                          <h4 className="italic">Pek By Fsee : {formatRupiah(modalData[0]?.pek_by_frcsee || 0)}</h4>
                          <h4 className="italic">Selisih Investasi : {formatRupiah(totalModal + modalData[0]?.pek_by_frcsee - totalEstimasi)}</h4>
                        </div>
                      </div>
      
                      <h3 className="text-yellow-700 font-semibold mt-1">Investasi</h3>
                    </div>
      
                    {/* Card 2 */}
                    <div className="bg-blue-100 shadow rounded-lg p-4 text-blue-800 flex flex-col gap-1 border border-blue-300">
                      <div className="flex flex-row justify-between items-start border-b border-blue-300 pb-1">
                        <div className="flex flex-col items-start gap-2">
                          <FaTools className="w-28 h-28 text-green-600" />
                        </div>
      
                        <div className="flex flex-col items-end text-right gap-1 text-green-600">
                          <h4 className="italic">Total Sarana : {formatAmount(totalSarana || 0)}</h4>
                          <h4 className="italic">Sarana Realisasi : {formatAmount(totalRealisasi || 0)}</h4>
                          <h4 className="italic">Sarana Tidak Realisasi (BA) : {formatAmount(SaranaBA || 0)}</h4>
                          <h4 className="italic">Belum Realisasi : {formatAmount(totalSarana - totalRealisasi - SaranaBA || 0)}</h4>
                        </div>
                      </div>
      
                      <h3 className="text-green-600 font-semibold mt-1">Monitoring Sarana</h3>
                    </div>
      
                    {/* Card 3 */}
                    <div className="bg-blue-100 shadow rounded-lg p-4 text-blue-800 flex flex-col gap-1 border border-blue-300">
                      <div className="flex flex-row justify-between items-start border-b border-blue-300 pb-1">
                        <div className="flex flex-col items-start gap-2">
                          <FaToolbox className="w-28 h-28 text-cyan-600" />
                        </div>
      
                        <div className="flex flex-col items-end text-right gap-1 text-cyan-600">
                          <h4 className="italic">DAT/PR Terinput : {formatAmount(totalDatPR || 0)}</h4>
                          <h4 className="italic">DAT/PR Realisasi : {formatAmount(dppToko || 0)}</h4>
                          <h4 className="italic">DAT/PR Belum Input : {formatAmount(dppToko - totalDatPR || 0)}</h4>
                        </div>
                      </div>
      
                      <h3 className="text-cyan-600 font-semibold mt-1">DAT - PR</h3>
                    </div>
      
                    {/* Card 4 */}
                    <div className="bg-blue-100 shadow rounded-lg p-4 text-blue-800 flex flex-col gap-1 border border-blue-300">
                      <div className="flex flex-row justify-between items-start border-b border-blue-300 pb-1">
                        <div className="flex flex-col items-start gap-2">
                          <FaMoneyCheck className="w-28 h-28 text-red-600 -rotate-45" />
                        </div>
      
                        <div className="flex flex-col items-end text-right gap-1 text-red-600">
                          <h4 className="italic">Modal : {formatRupiah(totalModal || 0)}</h4>
                          <h4 className="italic">Total Realisasi : {formatRupiah(totalToko || 0)}</h4>
                          <h4 className="italic">Sisa LPD : {formatRupiah(totalModal - totalToko)}</h4>
                        </div>
                      </div>
      
                      <h3 className="text-red-600 font-semibold mt-1">Summary LPD</h3>
                    </div>
                  </div>
                </div>

                <div className="space-y-1 my-4">
                  <div className="h-px bg-gray-400" />
                  <div className="h-px bg-gray-400" />
                </div>

                <div className="p-1">
                  <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-1">
                    <div className="bg-blue-100 shadow rounded-lg p-4 text-blue-800 flex flex-col gap-1 border border-blue-300">
                      <div className="flex flex-row justify-between items-start border-b border-blue-300 pb-1">
                        <div className="flex flex-col items-start gap-2">
                          <FaFilePdf className="w-28 h-28 text-yellow-400" />
                        </div>
      
                        <div className="flex flex-col items-end text-right gap-1 text-yellow-700">
                          <h4 className="flex items-center gap-1">
                            {berkas.rab_rekap || 'RAB Rekap'}
                            {berkas.rab_rekap ? (
                              <FaCheck className="w-4 h-4 text-green-600" />
                            ) : (
                              <FaTimes className="w-4 h-4 text-red-600" />
                            )}
                          </h4>
                          <h4 className="flex items-center gap-1">
                            {berkas.rab_detail || 'RAB Detail'}
                            {berkas.rab_detail ? (
                              <FaCheck className="w-4 h-4 text-green-600" />
                            ) : (
                              <FaTimes className="w-4 h-4 text-red-600" />
                            )}
                          </h4>
                          <h4 className="flex items-center gap-1">
                            {berkas.proposal || 'Proposal'}
                            {berkas.proposal ? (
                              <FaCheck className="w-4 h-4 text-green-600" />
                            ) : (
                              <FaTimes className="w-4 h-4 text-red-600" />
                            )}
                          </h4>
                          <h4 className="flex items-center gap-1">
                            {berkas.termin_invest || 'Termin Investasi'}
                            {berkas.termin_invest ? (
                              <FaCheck className="w-4 h-4 text-green-600" />
                            ) : (
                              <FaTimes className="w-4 h-4 text-red-600" />
                            )}
                          </h4>
                        </div>
                      </div>
      
                      <h3 className="text-yellow-700 font-semibold mt-1 text-center">Berkas</h3>
                    </div>
      
                    <div className="bg-blue-100 shadow rounded-lg p-4 text-blue-800 flex flex-col gap-1 border border-blue-300">
                      <div className="flex flex-row justify-between items-start border-b border-blue-300 pb-1">
                        <div className="flex flex-col items-start gap-2">
                          <FaHardHat className="w-28 h-28 text-cyan-400" />
                        </div>
      
                        <div className="flex flex-col items-end text-right gap-1 text-cyan-700">
                          {bapjRenovData.length > 0 ? (
                            bapjRenovData.map((item, idx) => (
                              <span key={idx} className="flex items-center gap-1">
                                {item.inv_num} (Rp {formatRupiah(item.total)})
                                {item.flag_renov && item.flag_renov.trim() !== '' ? (
                                  <FaCheck className="text-green-600 w-4 h-4" />
                                ) : (
                                  <FaTimes className="text-red-600 w-4 h-4" />
                                )}
                              </span>
                            ))
                          ) : (
                            <span>-</span>
                          )}
                        </div>
                      </div>
      
                      <h3 className="text-yellow-700 font-semibold mt-1 text-center">BAPJ</h3>
                    </div>
      
                    <div className="bg-blue-100 shadow rounded-lg p-4 text-blue-800 flex flex-col gap-1 border border-blue-300">
                      <div className="flex flex-row justify-between items-start border-b border-blue-300 pb-1">
                        <div className="flex flex-col items-start gap-2">
                          <FaFileAlt className="w-28 h-28 text-purple-400" />
                        </div>
      
                        <div className="flex flex-col items-end text-right gap-1 text-purple-700">
                          <h4>RAB Program LPD : {formatRupiah(totalEstimasi)}</h4>
                          <h4>RAB Final : {formatRupiah(identitas.rab_final)}</h4>
                          <h4>Selisih RAB : {formatRupiah(totalEstimasi - identitas.rab_final)}</h4>
                        </div>
                      </div>
      
                      <h3 className="text-yellow-700 font-semibold mt-1 text-center">RAB</h3>
                    </div>
                  </div> 
                </div>

                <div className="space-y-1 my-4">
                  <div className="h-px bg-gray-400" />
                  <div className="h-px bg-gray-400" />
                </div>

                {canSendMail && (
                  <div className="flex justify-center">
                    <button
                      ref={sendMailButtonRef}
                      onClick={handleSendLPD}
                      className="flex items-center gap-2 bg-green-600 hover:bg-green-700 text-white font-semibold px-6 py-2 rounded-lg shadow"
                    >
                      <FaPaperPlane className="w-4 h-4" /> Sent Mail
                    </button>
                  </div>
                )}
              </>
            ) : null}
          </CenterDrawer>
        )}

      </div>
    </main>
  );
}