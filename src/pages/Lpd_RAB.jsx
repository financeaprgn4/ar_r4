import React, { useEffect, useState, useRef } from 'react';
import Swal from "sweetalert2";
import ReusableTable from "../components/ReactTable";
import Menu_LPD from '../components/Menu_LPD';
import { useNavigate } from 'react-router-dom';
import { useNoRab } from '../contexts/NoRabContext';
import { fileUrl } from "../config/fileUrl"
import { motion } from "framer-motion";
import { formatDate, formatRupiah } from "../utility/textFormatter";
import { HiClipboard, HiMenu } from "react-icons/hi";
import { FaPencilAlt, FaFilePdf } from 'react-icons/fa';

export default function Lpd_RAB() {
  const [globalFilter, setGlobalFilter] = useState("");
  const { updateNoRab } = useNoRab();
  const navigate = useNavigate();
  const searchRef = useRef(null);
  const cabang = sessionStorage.getItem("cabang");
  const [data, setData] = useState([]);
  const [isMenuOpen, setMenuOpen] = useState(false);
  const [openCenterDrawer, setOpenCenterDrawer] = useState(false);
  const handleOpenMenu = () => setMenuOpen(true);
  const handleCloseMenu = () => setMenuOpen(false);
  const [expandedRow, setExpandedRow] = useState(null);  
  const toggleExpand = (rowId) => {
    setExpandedRow((prev) => (prev === rowId ? null : rowId));
  };

  useEffect(() => {
    searchRef.current?.focus();
  }, [])

  const fetchData = () => {
    if (!cabang) return;
    fetch(`/api/lpd-rab?cabang=${cabang}`)
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
    navigate('/lpd-detail');
  };

  const columns = [
    {
      header: "No",
      cell: (info) => info.row.index + 1,
      meta: { className: "text-center" },
    },
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
    { 
      header: 'Jenis',
      accessorKey: 'jns_toko',
      cell: info => <div className="text-center">{info.getValue()}</div>
    },
    {
      header: 'Tanggal Waralaba',
      accessorKey: 'tgl_wrlb',
      cell: info => (
        <div className="text-center">
            {formatDate(info.getValue())}
        </div>
      ),
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
          <div className="flex items-center justify-center gap-2 whitespace-nowrap">
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
      }
    },
    {
      header: 'RAB Final',
      accessorKey: 'rab_final',
      meta: { align: 'right' },
      cell: info => (
        <div className="text-right w-full">
          {formatRupiah(info.getValue())}
        </div>
      )
    },
    {
      header: 'RAB LPD',
      accessorKey: 'total_rab_detail',
      meta: { align: 'right' },
      cell: info => (
        <div className="text-right w-full">
          {formatRupiah(info.getValue())}
        </div>
      )
    },
    {
      header: 'Selisih',
      accessorFn: row =>
        (row.total_rab_detail || 0) -
        (row.rab_final || 0),
      cell: info => {
        const value = info.getValue();
        const isWarning = value < -100 || value > 100;

        return (
          <div
            className={`text-right w-full ${
              isWarning ? 'text-red-600 font-semibold' : ''
            }`}
          >
            {formatRupiah(value)}
          </div>
        );
      }
    },
    {
      header: 'Keterangan',
      accessorKey: 'keterangan',
      cell: ({ row }) => (
        <div className="flex items-center justify-between">
          <span>{row.original.keterangan}</span>
          <FaPencilAlt
            className="ml-2 text-blue-600 cursor-pointer"
            onClick={() =>
              handleEditKeterangan(row.original.id, row.original.keterangan)
            }
          />
        </div>
      ),
    }
  ];

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
                    break;
            }
        }
    };

    window.addEventListener('keydown', handleKeyDown);
    return () => window.removeEventListener('keydown', handleKeyDown);
  }, [ isMenuOpen, openCenterDrawer ]);

  const handleEditKeterangan = async (selectedId, currentKeterangan) => {
    const { value: keterangan } = await Swal.fire({
      title: "Edit Keterangan",
      input: "textarea",
      inputValue: currentKeterangan || "",
      inputPlaceholder: "Masukkan keterangan...",
      showCancelButton: true,
      confirmButtonText: "Simpan",
      cancelButtonText: "Batal",
    });

    if (keterangan !== undefined) {
      try {
        console.log("ID yang dikirim ke backend:", selectedId);
        console.log("Keterangan yang dikirim ke backend:", keterangan);
        const response = await fetch(`/api/update-keterangan-rab`, {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
          },
          body: JSON.stringify({
            id: selectedId,
            keterangan,
          }),
        });

        const data = await response.json();

        if (response.ok) {
          Swal.fire("Sukses", data.message || "Keterangan berhasil diperbarui", "success");
          fetchData();
        } else {
          Swal.fire("Error", data.message || "Gagal memperbarui keterangan", "error");
        }
      } catch (error) {
        Swal.fire("Error", "Terjadi kesalahan saat menyimpan", "error");
      }
    }
  };  

  return (
    <main className="flex-1 px-2 py-2 z-10 text-white h-[calc(100vh-40px)] overflow-hidden">
      <div className="h-full bg-white/60 rounded-lg shadow-lg text-gray-800 w-full flex flex-col">
        <div className="relative flex items-center justify-center mb-4 px-4 pt-4">
          <div className="mb-4 text-center">
            <h2 className="text-xl font-bold">MONITORING RAB LPD</h2>
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
        />

        <div className="flex-1 overflow-y-auto px-4 pb-4 space-y-4">
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
                      <div className="flex p-4 bg-gray-200 border-t justify-end">
                        <motion.div
                          initial={{ opacity: 0, x: -100 }}
                          animate={{ opacity: 1, x: 0 }}
                          transition={{ duration: 0.4, ease: "easeOut" }}
                          className="w-full flex gap-2 justify-end flex-wrap"
                        >
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
                              <FaFilePdf className="w-5 h-5" /> Perhitungan LPD
                            </button>
                          )}
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
                          {row.original.lampiran && (
                            <button
                              className="btn btn-danger flex items-center gap-1"
                              title={row.original.proposal}
                              onClick={() => window.open(`/file/ba_rab/${row.original.lampiran}`, "_blank")}
                            >
                              <FaFilePdf className="w-5 h-5" /> BA Selisih RAB
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
        </div>
      </div>
    </main>
  );
}