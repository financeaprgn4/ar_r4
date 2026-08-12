import React, { useRef, useEffect, useState, useMemo, useCallback } from "react";
import { useNavigate } from "react-router-dom";
import Swal from "sweetalert2";
import { formatAmount, formatDate, formatRupiah } from "../utility/textFormatter";
import Menu_LPD from '../components/Menu_LPD';
import ReusableTable from "../components/ReactTable";
import CenterDrawer from "../components/CenterDrawer";
import { fileUrl } from "../config/fileUrl"
import { useLpdDetail } from '../hooks/useLpdDetail';
import { handleCreateAllLPD, handleUpdateAllKeterangan, handleAutomatchDatprAll } from '../utility/exportLPD';
import { useRightPanel } from '../contexts/RightPanelContext';
import { useSidebar } from "../components/SidebarContext";
import { useNoRab } from '../contexts/NoRabContext';
import { useCabang } from "../contexts/CabangContext";
import { motion } from "framer-motion";
import { HiClipboard, HiMenu, HiSearch, HiPencil, HiMail } from "react-icons/hi";
import { FaTools, FaMoneyCheck, FaPaperPlane, FaFileAlt, FaFileExcel, FaFilePdf, FaCheck, FaTimes } from "react-icons/fa";
import TableLoading from "../components/TableLoading";

export default function LPDOuts() {
  const [data, setData] = useState([]);
  const [periode, setPeriode] = useState([]);
  const [expandedRow, setExpandedRow] = useState(null);
  const { cabang } = useCabang();
  const navigate = useNavigate();
  const { updateNoRab } = useNoRab();
  const toggleExpand = (rowId) => {
    setExpandedRow((prev) => (prev === rowId ? null : rowId));
  };
  
  const createBtnRef = useRef(null);
  const [isMenuOpen, setMenuOpen] = useState(false);
  const handleOpenMenu = () => setMenuOpen(true);
  const handleCloseMenu = () => setMenuOpen(false);
  
  const { openLPDPanel, refreshFlag, closePanel } = useRightPanel();
  const [isDrawerOpen, setIsDrawerOpen] = useState(false);
  const [selectedNoRab, setSelectedNoRab] = useState("");
  const {
    totalToko,
    totalModal,
    berkas,
    totalSarana,
    totalRealisasi
  } = useLpdDetail(selectedNoRab);
  
  const handleOpenDrawer = (no_rab) => {
    setSelectedNoRab(no_rab);
    setIsDrawerOpen(true);
  };
  const handleCloseDrawer = () => setIsDrawerOpen(false);
  
  useEffect(() => {
    if (isDrawerOpen && createBtnRef.current) {
      createBtnRef.current.focus();
    }
  }, [isDrawerOpen]);

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

    fetch(`/api/lpd-outs?cabang=${cabang}`)
      .then((res) => {
        if (!res.ok) throw new Error("Gagal mengambil data");
        return res.json();
      })
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

  /* ================= DATA TABLE ================= */
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
          <button
            className={`text-blue-600 hover:underline ${isLate ? "text-red-600 font-semibold" : ""}`}
            onClick={() => openLPDPanel("edit", row.original)}
          >
            {row.original.kd_toko}
          </button>
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

      accessorFn: (row) =>
        row.keterangan
          ? row.keterangan
              .toLowerCase()
              .replace(/[^a-z0-9\s/]/g, " ")
              .replace(/\s+/g, " ")
              .trim()
          : "",

      cell: ({ row }) => (
        <span className="whitespace-pre-line">
          {row.original.keterangan}
        </span>
      ),
    }
  ], [cabang, periode]);
  
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
      if (!isCollapsed) {
        return;
      }
      
      if (e.key === 'Escape') {
        if (isMenuOpen) {
          setMenuOpen(false);
        }
        if (isDrawerOpen){
          setIsDrawerOpen(false);
        }
      }
      
      if (e.altKey && e.code === 'KeyA') {
        e.preventDefault();
        setMenuOpen(false);
        openLPDPanel('add', null);
      }
      if (e.altKey && e.code === 'KeyC') {
        e.preventDefault();
        setMenuOpen(false);
        handleCreateAllLPD();
      }
      if (e.altKey && e.code === 'KeyD') {
        e.preventDefault();
        setMenuOpen(false);
        handleAutomatchDatprAll();
      }
      if (e.altKey && e.code === 'KeyK') {
        e.preventDefault();
        setMenuOpen(false);
        handleUpdateAllKeterangan();
      }
      if (e.altKey && e.code === 'KeyM') {
        e.preventDefault();
        setMenuOpen(true);
      }

      if (e.altKey && e.code === 'KeyX') {
        e.preventDefault();
        if(isCollapsed){
          setIsCollapsed(false);
        }else{
          setIsCollapsed(true);
        }
        
        closePanel();
      }
    };

    window.addEventListener('keydown', handleKeyDown);

    return () => {
      window.removeEventListener('keydown', handleKeyDown);
    };
  }, [isMenuOpen, openLPDPanel, isDrawerOpen, isCollapsed]);
  
  const handleSendLPD  = async () => {
    try {
      const response = await fetch(`/api/send-lpd`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({ rab: selectedNoRab }),
      });

      const result = await response.json();

      if (response.ok) {
        Swal.fire({
          icon: 'success',
          title: 'Sukses!',
          text: result.message || 'Email berhasil dikirim.',
        }).then(() => {
          setIsDrawerOpen(false);
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
    <main className="flex-1 px-4 py-2 text-white">
      <div className="min-h-[calc(100vh-50px)] bg-white/60 rounded-lg p-4 shadow-lg text-gray-800 w-full">
        <div className="relative flex items-center justify-center mb-4 flex-wrap">
          <div className="box-header text-white shadow-md flex items-center justify-center mx-auto mt-[-17px] mb-2 h-[60px] w-1/2 bg-blue-400 clip-path-custom">
            <h2 className="text-xl text-center font-semibold mb-3">Daftar Outs LPD Cabang {cabang}</h2>
          </div>

          <div className="absolute right-0 flex items-center space-x-2">
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
            cabang={cabang}
            fetchData={fetchData}
          />
        </div>
        
        <div className="w-full overflow-x-auto pt-1">
          <div className="min-w-[1200px] ml-1">
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
                          <div className="flex flex-col md:flex-row gap-4 p-4 bg-gray-200 border-t">
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
                                title="Email Perhitungan"
                                onClick={() => handleOpenDrawer(row.original.no_rab)}
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
                              {row.original.status === 'CS' && row.original.draft_cs && (
                                <button
                                  className="btn btn-success flex items-center gap-1"
                                  title={row.original.draft_cs}
                                  onClick={() =>
                                    window.open(
                                      fileUrl(`/file/draft_cs/${row.original.draft_cs}?v=${Date.now()}`),
                                      "_blank"
                                    )
                                  }
                                >
                                  <FaFileAlt className="w-5 h-5" />Clearencesheet
                                </button>
                              )}
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
          </div>
        </div>

        <CenterDrawer
          isOpen={isDrawerOpen}
          onClose={handleCloseDrawer}
          widthClass="max-w-full"
        >
          <div className="p-4">
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
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

                <h3 className="text-red-600 font-semibold mt-1 text-center">Summary LPD</h3>
              </div>

              <div className="bg-blue-100 shadow rounded-lg p-4 text-blue-800 flex flex-col gap-1 border border-blue-300">
                <div className="flex flex-row justify-between items-start border-b border-blue-300 pb-1">
                  <div className="flex flex-col items-start gap-2">
                    <FaFileExcel className="w-28 h-28 text-green-600" />
                  </div>

                  <div className="flex flex-col items-end gap-1 text-green-600">
                    {berkas.lpd_excel ? (
                      <FaCheck className="w-14 h-14 text-green-600" />
                    ) : (
                      <FaTimes className="w-14 h-14 text-red-600" />
                    )}
                    <h4 className="italic">{berkas.lpd_excel || '-'}</h4>
                  </div>
                </div>

                <h3 className="text-green-600 font-semibold mt-1 text-center">Detail Perhitungan Excel</h3>
              </div>

              <div className="bg-blue-100 shadow rounded-lg p-4 text-blue-800 flex flex-col gap-1 border border-blue-300">
                <div className="flex flex-row justify-between items-start border-b border-blue-300 pb-1">
                  <div className="flex flex-col items-start gap-2">
                    <FaFilePdf className="w-28 h-28 text-yellow-400" />
                  </div>

                  <div className="flex flex-col items-end text-right gap-1 text-yellow-700">
                    {berkas.lpd ? (
                      <FaCheck className="w-14 h-14 text-green-600" />
                    ) : (
                      <FaTimes className="w-14 h-14 text-red-600" />
                    )}
                    <h4 className="italic">{berkas.lpd || '-'}</h4>
                  </div>
                </div>

                <h3 className="text-yellow-700 font-semibold mt-1 text-center">Perhitungan PDF</h3>
              </div>

              <div className="bg-blue-100 shadow rounded-lg p-4 text-blue-800 flex flex-col gap-1 border border-blue-300">
                <div className="flex flex-row justify-between items-start border-b border-blue-300 pb-1">
                  <div className="flex flex-col items-start gap-2">
                    <FaTools className="w-28 h-28 text-blue-600" />
                  </div>

                  <div className="flex flex-col items-end text-right gap-1 text-blue-600">
                    <h4 className="italic">Total Sarana : {formatAmount(totalSarana || 0)}</h4>
                    <h4 className="italic">Sarana Realisasi : {formatAmount(totalRealisasi || 0)}</h4>
                    <h4 className="italic">Belum Realisasi : {formatAmount(totalSarana - totalRealisasi || 0)}</h4>
                  </div>
                </div>

                <h3 className="text-blue-600 font-semibold mt-1 text-center">Monitoring Sarana</h3>
              </div>
            </div>
            
            <div className="flex justify-center">
              {berkas.lpd && berkas.lpd_excel && (
                <button
                  ref={createBtnRef}
                  onClick={handleSendLPD}
                  className="flex items-center gap-2 bg-green-600 hover:bg-green-700 text-white font-semibold px-6 py-2 rounded-lg shadow"
                >
                  <FaPaperPlane className="w-4 h-4" /> Sent Mail
                </button>
              )}
            </div>  
          </div>
        </CenterDrawer>
      </div>
    </main>
  );
}