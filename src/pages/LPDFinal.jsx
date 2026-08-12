import React, { useEffect, useState, useMemo, useCallback } from "react";
import { useNavigate } from "react-router-dom";
import { formatDate } from "../utility/textFormatter";
import Swal from "sweetalert2";
import Menu_LPD from '../components/Menu_LPD';
import ReusableTable from "../components/ReactTable";
import { fileUrl } from "../config/fileUrl"
import { useRightPanel } from '../contexts/RightPanelContext';
import { useSidebar } from "../components/SidebarContext";
import { useNoRab } from '../contexts/NoRabContext';
import { useCabang } from "../contexts/CabangContext";
import TableLoading from "../components/TableLoading";
import { motion } from "framer-motion";
import { HiClipboard, HiMenu, HiSearch, HiPencil, HiX } from "react-icons/hi";
import { FaFileAlt, FaFileExcel, FaFilePdf } from "react-icons/fa";

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
  
  const [isMenuOpen, setMenuOpen] = useState(false);
  const handleOpenMenu = () => setMenuOpen(true);
  const handleCloseMenu = () => setMenuOpen(false);
  
  const { openLPDPanel, refreshFlag } = useRightPanel();

  /* ================= PROSES MEMUAT DATA ================= */
  const [loading, setLoading] = useState(false);

  const fetchData = useCallback(() => {
    if (!cabang) return;
    
    setLoading(true);
    
    fetch(`/api/lpd-final?cabang=${cabang}`)
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
        setLoading(false);
      });
  }, [cabang]);

  useEffect(() => {
    fetchData();
  }, [cabang]);

  useEffect(() => {
    fetchData();
  }, [refreshFlag]);

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
        const { openLPDPanel } = useRightPanel();
        const endDate = periode[0]?.end_date ? new Date(periode[0].end_date) : null;
        const tglJT = new Date(row.original.tgl_jt);
        const isLate = endDate && tglJT <= endDate;

        return (
          <button
            className={`text-blue-600 hover:underline ${isLate ? "text-red-600 font-semibold" : ""}`}
            onClick={() => openLPDPanel("copy", row.original)}
          >
            {row.original.kd_toko}
          </button>
        );
      },
    },
    {
      header: "Nama Toko",
      accessorKey: "nama_toko",
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
      accessorKey: "catatan_final",
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
        if (isMenuOpen) {
          setMenuOpen(false);
        }
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
      }
    };

    window.addEventListener('keydown', handleKeyDown);

    return () => {
      window.removeEventListener('keydown', handleKeyDown);
    };
  }, [isMenuOpen, isCollapsed]);
  
  const handleUnfinalisasi = async (noRab) => {
    const confirm = await Swal.fire({
      title: 'Yakin Unfinalisasi?',
      text: `Anda akan mengubah status LPD : ${noRab} menjadi Clrearencesheet?`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Ya, Unfinalisasi',
      cancelButtonText: 'Batal',
      confirmButtonColor: '#d33',
      cancelButtonColor: '#3085d6',
    });

    if (confirm.isConfirmed) {
      try {
        const response = await fetch(`/api/unfinalisasi`, {
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

  return (
    <main className="flex-1 px-4 py-2 z-10 text-white">
      <div className="h-[calc(100vh-50px)] bg-white/60 rounded-lg p-4 shadow-lg text-gray-800 w-full">
        <div className="relative flex items-center justify-center mb-4">
          <div className="box-header text-white shadow-md flex items-center justify-center mx-auto mt-[-17px] mb-2 h-[60px] w-1/2 bg-blue-400 clip-path-custom">
            <h2 className="text-xl text-center font-semibold mb-3">Daftar LPD Final Cabang {cabang}</h2>
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
        
        {loading ? (
          <TableLoading text="Memuat Data LPD Final..." />
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
                            title="Unfinalisasi LPD"
                            onClick={() => handleUnfinalisasi(row.original.no_rab)}
                          >
                            <HiX className="w-5 h-5" />
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
                          {row.original.status === 'Final' && row.original.cs_final && (
                            <button
                              className="btn btn-success flex items-center gap-1"
                              title={row.original.cs_final}
                              onClick={() =>
                                window.open(
                                  fileUrl(`/file/clearencesheet/${row.original.cs_final}?v=${Date.now()}`),
                                  "_blank"
                                )
                              }
                            >
                              <FaFileAlt className="w-5 h-5" />CS Final
                            </button>
                          )}
                          {row.original.status === 'Final' && row.original.pot_surkas && (
                            <button
                              className="btn btn-warning flex items-center gap-1"
                              title={row.original.pot_surkas}
                              onClick={() =>
                                window.open(
                                  fileUrl(`/file/pot_surkas/${row.original.pot_surkas}?v=${Date.now()}`),
                                  "_blank"
                                )
                              }
                            >
                              <FaFilePdf className="w-5 h-5" />BA Pot Surkas
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
    </main>
  );
}