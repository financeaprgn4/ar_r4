import React, { useEffect, useState, useRef } from 'react';
import Swal from "sweetalert2";
import ReusableTable from "../components/ReusableTable";
import Menu_LPD from '../components/Menu_LPD';
import { useNavigate } from 'react-router-dom';
import { useNoRab } from '../contexts/NoRabContext';
import { formatDate, formatRupiah } from "../utility/textFormatter";
import { HiMenu } from "react-icons/hi";
import { FaPencilAlt } from 'react-icons/fa';

export default function Modal_LPD() {
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
  
  useEffect(() => {
    searchRef.current?.focus();
  }, [])

  const fetchData = () => {
    if (!cabang) return;
    fetch(`/api/lpd-modal?cabang=${cabang}`)
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
      header: 'No RAB',
      accessorKey: 'no_rab',
      cell: info => <div className="text-center">{info.getValue()}</div>
    },
    {
      header: 'RAB Final',
      accessorKey: 'rab_final',
      cell: info => formatRupiah(info.getValue()),
      meta: { className: "text-right" },
    },
    {
      header: 'Modal Setor',
      accessorKey: 'setor',
      cell: info => formatRupiah(info.getValue()),
      meta: { className: "text-right" },
    },
    {
      header: 'Cadangan Dana',
      accessorKey: 'cad_dana',
      cell: info => formatRupiah(info.getValue()),
      meta: { className: "text-right" },
    },
    {
      header: 'Sewa By Fsee',
      accessorKey: 'sewa_by_frcsee',
      cell: info => formatRupiah(info.getValue()),
      meta: { className: "text-right" },
    },
    {
      header: 'Sewa AT (PRODSUS)',
      accessorKey: 'sewa_at',
      cell: info => formatRupiah(info.getValue()),
      meta: { className: "text-right" },
    },
    {
      header: 'Pek By Fsee',
      accessorKey: 'pek_by_frcsee',
      cell: info => formatRupiah(info.getValue()),
      meta: { className: "text-right" },
    },
    {
      header: 'Selisih',
      accessorFn: row =>
        (row.setor || 0) +
        (row.cad_dana || 0) +
        (row.sewa_by_frcsee || 0) +
        (row.sewa_at || 0) +
        (row.pek_by_frcsee || 0) -
        (row.rab_final || 0),
      cell: info => formatRupiah(info.getValue()),
      meta: { className: "text-right" },
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
                    setShowDrawer(false);
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
        const response = await fetch(`/api/update-keterangan`, {
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
    <main className="flex-1 px-2 py-2 z-10 text-white h-[calc(100vh-30px)] overflow-hidden">
      <div className="h-full bg-white/60 rounded-lg shadow-lg text-gray-800 w-full flex flex-col">
        <div className="relative flex items-center justify-center mb-4 px-4 pt-4">
          <div className="mb-4 text-center">
            <h2 className="text-xl font-bold">MONITORING MODAL LPD</h2>
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

        <div className="flex-1 overflow-y-auto px-4 pt-2 space-y-4">
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
        </div>
      </div>
    </main>
  );
}