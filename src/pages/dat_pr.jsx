import React, { useEffect, useState, useRef, useMemo } from 'react';
import Swal from "sweetalert2";
import axios from "../config/axiosInstance";
import ReusableTable from "../components/ReusableTable";
import RightSidebar from '../components/RightSidebar';
import Menu_LPD from '../components/Menu_LPD';
import { useRightPanel } from '../contexts/RightPanelContext';
import { useNavigate } from 'react-router-dom';
import BottomDrawer from "../components/BottomDrawer";
import TopDrawer from "../components/TopDrawer";
import CenterDrawer from "../components/CenterDrawer";
import { useNoRab } from "../contexts/NoRabContext";
import { useLpdDetail } from '../hooks/useLpdDetail';
import TableLoading from "../components/TableLoading";
import { useSidebar } from "../components/SidebarContext";
import { formatDate, formatRupiah, formatAmount } from "../utility/textFormatter";
import { FaPaperPlane, FaFileImport, FaCheckCircle, FaCloudUploadAlt } from "react-icons/fa";
import { HiRefresh, HiMenu, HiSearch, HiSave, HiTrash, HiX } from "react-icons/hi";
import Papa from "papaparse";
import {
  handleCreateLPD, handleUpdateketerangan, handleUpdateATPR, handleAutomatchDatpr
} from '../utility/exportLPD';

export default function dat_pr() {
  const cabang = sessionStorage.getItem("cabang");
  const [setupChecked, setSetupChecked] = useState(false);
  const { isCollapsed } = useSidebar();
  const navigate = useNavigate();
  const { noRab: no_rab } = useNoRab();
  const [rightsidebarOpen, setRightSidebarOpen] = useState(false);
  const { openLPDPanel, refreshFlag } = useRightPanel();
  const [showDrawer, setShowDrawer] = useState(false);
  const [drawerMode, setDrawerMode] = useState('import');
  const [activeTab, setActiveTab] = useState('at-pr');
  const atprSearchRef = useRef(null);
  const atprMasterSearchRef = useRef(null);
  const atprYSearchRef = useRef(null);
  const realisasiSearchRef = useRef(null);
  const [searchRealisasiValue, setSearchRealisasiValue] = useState('');
  const [selectedFile, setSelectedFile] = useState(null);
  const checkboxRealisasiRef = useRef();
  const checkboxSurkasYRef = useRef();
  const [searchDatPRValue, setSearchDatPRValue] = useState('');
  const [searchDatPRSurkas, setSearchDatPRSurkas] = useState('');
  const [openTopDrawer, setOpenTopDrawer] = useState(false);
  const [selectedRows, setSelectedRows] = useState([]);
  const [checkedItems, setCheckedItems] = useState({});
  const closeRightSidebar = () => setRightSidebarOpen(false);
  const checkboxRef = useRef(null);
  const [selectedIds, setSelectedIds] = useState([]);
  const [visibleRows, setVisibleRows] = useState([]);
  const [visibleRowsRealisasi, setvisibleRowsRealisasi] = useState([]);
  const [visibleRowsSurkasY, setvisibleRowsSurkasY] = useState([]);
  const fileInputRef = useRef(null);
  const labelRef = useRef(null);
  const [selectedRealisasiIds, setSelectedRealisasiIds] = useState([]);
  const [selectedSurkasYIds, setSelectedSurkasYIds] = useState([]);
  const [isSurkasYOnly, setIsSurkasYOnly] = useState(false);
  const matchButtonTopRef = useRef(null);
  const matchButtonCenterRef = useRef(null);
  const [isDrawerOpen, setIsDrawerOpen] = useState(false);
  const [selectedRow, setSelectedRow] = useState(null);  
  const [dataDatPR, setDatPR] = useState([]);
  const [dataSarana, setDataSarana] = useState([]);
  const [dataRealisasi, setDataRealisasi] = useState([]);
  const [masterSarana, setMasterSarana] = useState([]);
  const [dataATPRSurkas, setDataATPRSurkas] = useState([]);
  const [groupedAtPR, setGroupedAtPR] = useState({});
  const [mismatchCount, setMismatchCount] = useState(0);
  const [isMenuOpen, setMenuOpen] = useState(false);
  const [tipeMatching, setTipeMatching] = useState('');
  const handleOpenMenu = () => setMenuOpen(true);
  const handleCloseMenu = () => setMenuOpen(false);
  const rekapRef = useRef();
  const {
    data,
    rekap,
    identitas,
    berkas,
    fetchData,
  } = useLpdDetail(no_rab);

  useEffect(() => {
    if (rekap) {
      rekapRef.current = rekap;
    }
  }, [rekap]);
  
  useEffect(() => {
    if (no_rab) {
      fetchData();
    }
  }, [fetchData, no_rab, refreshFlag]);
  
  const columns = [
    {
      header: '',
      id: 'select',
      cell: ({ row }) => (
      <input
          type="checkbox"
          checked={selectedIds.includes(row.original.id)}
          onChange={() => handleCheckboxChange(row.original.id)}
          className="form-checkbox"
      />
      ),
      size: 20,
      meta: { className: "text-center" },
    },
    { header: 'Seri', accessorKey: 'seri' },
    { header: 'Keterangan', accessorKey: 'keterangan' },
    { header: 'Invoice Num', accessorKey: 'inv_num' },
    {
      header: 'Tanggal',
      accessorKey: 'tgl_perolehan',
      cell: info => formatDate(info.getValue()),
    },
    {
      header: 'Nilai',
      accessorKey: 'harga',
      cell: info => formatRupiah(info.getValue()),
      meta: { className: "text-right" },
    },
  ];

  const handleSelectAll = (e = null) => {
    if (e?.target?.checked ?? !isAllSelected) {
      setSelectedIds(visibleRows.map((item) => item.id));
    } else {
      setSelectedIds([]);
    }
  };
  
  const isAllSelected = visibleRows.length > 0 && selectedIds.length === visibleRows.map(item => item.id).length;
  
  const handleSelectAllRealisasi = (e = null) => {
    if (e?.target?.checked ?? !isAllRealisasiSelected) {
      setSelectedRealisasiIds(visibleRowsRealisasi.map(row => row.id));
    } else {
      setSelectedRealisasiIds([]);
    }
  };

  const handleSelectAllSurkasY = (e = null) => {
    if (e?.target?.checked ?? !isAllSurkasYSelected) {
      setSelectedSurkasYIds(visibleRowsSurkasY.map(row => row.id));
    } else {
      setSelectedSurkasYIds([]);
    }
  };

  useEffect(() => {
    if (showDrawer && labelRef.current) {
      labelRef.current.focus();
    }
  }, [showDrawer]);
  
  useEffect(() => {
    if (openTopDrawer && matchButtonTopRef.current) {
      matchButtonTopRef.current.focus();
    }
  }, [openTopDrawer]);
  
  const isAllRealisasiSelected =
    visibleRowsRealisasi.length > 0 &&
    selectedRealisasiIds.length === visibleRowsRealisasi.map(row => row.id).length;

  const isAllSurkasYSelected =
    visibleRowsSurkasY.length > 0 &&
    selectedSurkasYIds.length === visibleRowsSurkasY.map(row => row.id).length;

  const handleCheckboxChange = (id) => {
    setSelectedIds((prev) =>
        prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id]
    );
  };

  /* ================= PROSES MEMUAT DATA ================= */
  const [loadingTab, setLoadingTab] = useState({
    'at-pr': false,
    realisasi: false,
    'at-pr-surkas': false,
  });
  
  const fetchTables = async () => {
    if (!no_rab || !identitas?.kd_toko) {
      console.warn('RAB atau KD_TOKO belum tersedia');
      return;
    }

    setLoadingTab(prev => ({ ...prev, ['at-pr']: true }));

    try {
      const encodedNoRab = encodeURIComponent(no_rab);
      const encodedKdToko = encodeURIComponent(identitas.kd_toko);
      const resSarana = await axios.get(
        `/api/dat_pr?rab=${encodedNoRab}&kd_toko=${encodedKdToko}`
      );

      const {
        data = [],
        master = [],
        sarana = [],
        realisasi = [],
        groupedAtPR = [],
        surkas = []
      } = resSarana.data;

      setDatPR(data);
      setDataSarana(sarana);
      setDataRealisasi(realisasi);
      setMasterSarana(master);
      setGroupedAtPR(groupedAtPR);
      setDataATPRSurkas(surkas);
    } catch (err) {
      console.error('Gagal mengambil data AT/PR', err);
    } finally {
      setLoadingTab(prev => ({ ...prev, ['at-pr']: false }));
    }
  };

  useEffect(() => {
    if (no_rab && identitas?.kd_toko) {
      fetchTables();
    }
  }, [no_rab, identitas?.kd_toko, refreshFlag]);
  
  useEffect(() => {
    if (activeTab === 'at-pr') {
        atprSearchRef.current?.focus();
    } else if (activeTab === 'realisasi') {
        realisasiSearchRef.current?.focus();
    } else if (activeTab === 'at-pr-surkas') {
        atprYSearchRef.current?.focus();
    }
  }, [activeTab]);

  const handleMatching = () => {
    const atprSelected = dataDatPR.filter(row => selectedIds.includes(row.id));
    const realisasiSelected = data[0].all_data.rows.filter(row => selectedRealisasiIds.includes(row.id));
    const surkasYSelected = dataATPRSurkas.filter(row => selectedSurkasYIds.includes(row.id));

    const atprSelectedIsSurkasY =
      surkasYSelected.length > 0 &&
      atprSelected.length === 0 &&
      realisasiSelected.length === 0;
    setIsSurkasYOnly(atprSelectedIsSurkasY);

    if (
      atprSelected.length === 0 &&
      realisasiSelected.length === 0 &&
      surkasYSelected.length === 0
    ) {
      console.log("Tidak ada data yang dipilih.");
      return;
    }

    const combined = {
      atpr: atprSelectedIsSurkasY ? surkasYSelected : atprSelected,
      realisasi: realisasiSelected,
    };

    setSelectedRows(combined);
    setOpenTopDrawer(true);
  };


  const handleOpenDrawer = (row, mode = "inv") => {
    setSelectedRow(row);
    setDrawerMode(mode);
    setIsDrawerOpen(true);
  };

  const handleCloseDrawer = () => {
    setIsDrawerOpen(false);
    setSelectedRow(null);
  };

  const handleSubmitMatching = async () => {
    const kd_toko = identitas?.kd_toko;
    const idATPR = selectedRows?.atpr?.map(row => row.id) || [];
    const idRealisasi = selectedRows?.realisasi?.map(row => row.id) || [];
    
    if (!kd_toko || !no_rab) {
      return Swal.fire('Gagal', 'Data Kode Toko atau RAB tidak ditemukan.', 'error');
    }

    try {
      const payload = {
        no_rab,
        site: kd_toko,
        pilih: idATPR,
        iden: idRealisasi,
        tipe: tipeMatching,
      };

      const response = await fetch(`/api/atpr-match`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });
      
      const result = await response.json();

      if (result.success) {
        Swal.fire('Berhasil', 'Matching data berhasil disimpan.', 'success').then(() => {
          setOpenTopDrawer(false);
          fetchTables();
          fetchData();
          setSearchDatPRValue('');
          setSearchRealisasiValue('');
          setSelectedIds([]);
          setSelectedRealisasiIds([]);
          setSelectedSurkasYIds([]);
          setSelectedRows({ atpr: [], realisasi: [] });
          setTipeMatching('');
        });
      } else {
        Swal.fire('Gagal', result.message || 'Terjadi kesalahan saat menyimpan data.', 'error');
      }
    } catch (error) {
      Swal.fire('Error', 'Tidak dapat terhubung ke server.', 'error');
    }
  };
  
  useEffect(() => {
    if (isDrawerOpen && matchButtonCenterRef.current) {
      matchButtonCenterRef.current.focus();
    }
  }, [isDrawerOpen]);

  const handleDelete = async (flag, rab, kd_toko) => {
    const result = await Swal.fire({
      title: 'Apakah kamu yakin?',
      text: `Data dengan flag "${flag}" akan dihapus.`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Ya, hapus!',
      cancelButtonText: 'Batal',
    });

    if (result.isConfirmed) {
      try {
        const response = await axios.delete(`/api/del_at_pr`, {
          data: { flag, rab, kd_toko },
        });

        Swal.fire({
          icon: 'success',
          title: 'Berhasil',
          text: response.data.message,
        }).then(() => {
          fetchTables();
          fetchData();
        });
      } catch (error) {
        console.error('Gagal menghapus data:', error);
        Swal.fire('Gagal!', error.response?.data?.error || 'Terjadi kesalahan.', 'error');
      }
    }
  };

  const handleSelectRealisasiRow = (id) => {
    setSelectedRealisasiIds(prev =>
      prev.includes(id) ? prev.filter(x => x !== id) : [...prev, id]
    );
  };
  
  const handleSelectSurkasYRow = (id) => {
    setSelectedSurkasYIds(prev =>
      prev.includes(id) ? prev.filter(x => x !== id) : [...prev, id]
    );
  };
  const totalAtprNilai = (selectedRows?.atpr ?? []).reduce((sum, row) => sum + (row.harga || 0), 0);

  const totalDpp = (selectedRows?.realisasi ?? []).reduce((sum, row) => sum + (row.dpp || 0), 0);
  const totalPpn = (selectedRows?.realisasi ?? []).reduce((sum, row) => sum + (row.ppn || 0), 0);
  const totalRealisasi = (selectedRows?.realisasi ?? []).reduce((sum, row) => sum + (row.total || 0), 0);

  const handleFileChange = (event) => {
    const file = event.target.files?.[0];
    if (file) {
      setSelectedFile(file);
    }
  };

  const resetFilePicker = () => {
    setSelectedFile(null);
    document.getElementById("fileUpload").value = null;
  };

  const handleImport = async () => {
    if (!selectedFile) {
      Swal.fire("Peringatan", "Silakan pilih file terlebih dahulu!", "warning");
      return;
    }

    try {
      const text = await selectedFile.text();

      const parsed = Papa.parse(text, {
        header: true,
        skipEmptyLines: true,
      });

      if (parsed.errors.length > 0) {
        console.error("Parsing errors:", parsed.errors);
        Swal.fire("Gagal", "Terjadi kesalahan saat parsing file CSV!", "error");
        return;
      }

      const rows = parsed.data;

      const response = await axios.post(
        `/api/import-datpr`,
        { data: rows }
      );

      if (response.data.success) {
        Swal.fire("Berhasil", response.data.message, "success").then(() => {
          setShowDrawer(false);
          fetchTables();
          resetFilePicker();
        });
      } else {
        Swal.fire("Gagal", response.data.message || "Import gagal", "error");
      }
    } catch (error) {
      console.error("Import error:", error);
      Swal.fire("Error", error.message || "Terjadi kesalahan saat import", "error");
    }
  };
  
  useEffect(() => {
    if (groupedAtPR) {
      const totalMismatch = Object.values(groupedAtPR).reduce((count, item) => {
        const totalHarga = item.items?.reduce((sum, i) => sum + Number(i.harga || 0), 0);
        const totalRealisasi = item.realisasi_items?.reduce((sum, i) => sum + Number(i.dpp || 0), 0);
        return count + (Math.abs(totalHarga - totalRealisasi) > 100 ? 1 : 0);
      }, 0);

      setMismatchCount(totalMismatch);
    }
  }, [groupedAtPR]);

  useEffect(() => {
    const matchedItems = {};

    dataDatPR
      .filter(item => item.flag_realisasi === '' || item.flag_realisasi === null)
      .forEach(item => {
        const realisasi = data[0].all_data.rows.find(r =>
          r.inv_num === item.inv_num &&
          r.rab === no_rab &&
          r.flag_realisasi !== null &&
          r.flag_realisasi !== '' &&
          Number(item.harga) === Number(r.dpp)
        );

        if (realisasi) {
          const key = `${item.id}_${realisasi.id}`;
          matchedItems[key] = true;
        }
      });

    setCheckedItems(prev => ({ ...matchedItems, ...prev }));
  }, [dataDatPR, data, no_rab]);

  const handleMatchingInv = () => {
    const matchedData = Object.entries(checkedItems)
      .filter(([key, isChecked]) => isChecked)
      .map(([key]) => {
        const [id_datpr, id_realisasi] = key.split('_');
        const kd_toko = identitas.kd_toko;
        return {
          id_datpr,
          id_realisasi,
          no_rab,
          kd_toko
        };
      });

      console.log("matchedData : ", matchedData);
    if (matchedData.length === 0) {
      Swal.fire({
        icon: 'warning',
        title: 'Peringatan',
        text: 'Tidak ada data yang dipilih untuk matching.',
      });
      return;
    }
    
    fetch(`/api/datpr-match-inv`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({ data: matchedData })
    })
      .then(response => response.json())
      .then(res => {
        if (res.success) {
          Swal.fire({
            icon: 'success',
            title: 'Berhasil',
            text: res.message,
          }).then(() => {
            handleCloseDrawer();
            fetchTables();
            fetchData();
            setCheckedItems({});
          });
        } else {
          Swal.fire({
            icon: 'error',
            title: 'Gagal',
            text: res.message,
          });
          console.warn("Respon dari server:", res);
        }
      })
      .catch(error => {
        console.error("Terjadi kesalahan saat mengirim data:", error);
        Swal.fire({
          icon: 'error',
          title: 'Kesalahan',
          text: 'Terjadi kesalahan saat matching.',
        });
      });
  };

  const handleRightPanelToggle = (site = null, mode = null) => {
    setMenuOpen(false);
    openLPDPanel(mode, site);
  };

  useEffect(() => {
    const fetchSetupValue = async () => {
      try {
        const response = await axios.get(
          `/api/setup-lpd/match-at-by-desc`,
          { params: { cabang } }
        );

        const isChecked = response.data.value === "Y";
        setSetupChecked(isChecked);
      } catch (error) {
        console.error("Gagal fetch setup_lpd:", error);
      }
    };

    fetchSetupValue();
  }, [cabang]);

  const handleToggleSetup = async (e) => {
    const checked = e.target.checked;
    setSetupChecked(checked);

    try {
      await axios.post(
        `/api/setup-lpd/update-match-at-by-desc`,
        {
          cabang,
          value: checked ? "Y" : "N",
        }
      );
    } catch (err) {
      console.error("Gagal update setup_lpd:", err);
    }
  };

  // ========================= Perhitungan Pencocokan AT/PR VS Realisasi
  const [filteredDatPR, setFilteredDatPR] = useState([]);
  const [totalATPR, setTotalATPR] = useState(0);
  const [totalDppRealisasi, setTotalDppRealisasi] = useState(0);
  const [selisih, setSelisih] = useState(0);
  const matchingButtonRef = useRef(null);

  useEffect(() => {
    if (drawerMode !== "keterangan") return;
    if (!selectedRow || !dataDatPR || !dataSarana || !masterSarana) return;

    const datPrRows = dataDatPR || [];
    const saranaRows = dataSarana || [];
    const masters = masterSarana || [];

    const escapeForLike = (s) => s.replace(/[.+?^${}()|[\]\\]/g, "\\$&");

    let datprFiltered = [];
    if (setupChecked) {
      const ketSelected = (selectedRow.keterangan || "").trim().toLowerCase();

      datprFiltered = datPrRows.filter((item) => {
        const ketItem = (item.keterangan || "").trim().toLowerCase();
        return ketItem.includes(ketSelected) || ketSelected.includes(ketItem);
      });

    } else {
      const flagSarana = selectedRow.flag_sarana ?? selectedRow.flag_sarana;

      const saranaMatched = saranaRows.find(
        (s) => String(s.flag_realisasi ?? "") === String(flagSarana ?? "")
      );

      const kode = saranaMatched?.kode ?? null;

      const masterRec = kode
        ? masters.find((m) => String(m.kode) === String(kode))
        : null;

      const keywordStr = (masterRec?.keyword ?? "").trim();

      const keywords = keywordStr
        .split(";")
        .map((k) => k.trim())
        .filter(Boolean);

      datprFiltered =
        keywords.length === 0
          ? []
          : datPrRows.filter((item) => {
              const ket = (item.keterangan || "").toString().toLowerCase();
              return keywords.some((rawK) => {
                const k = rawK.toLowerCase();
                if (k.includes("%")) {
                  const escaped = escapeForLike(k).replace(/%/g, ".*");
                  try {
                    const re = new RegExp(escaped, "i");
                    return re.test(item.keterangan ?? "");
                  } catch {
                    return false;
                  }
                } else {
                  return ket.includes(k);
                }
              });
            });
    }

    // SET hasil filter ke state
    setFilteredDatPR(datprFiltered);

    // ===========================
    // Update checkedItems
    // ===========================
    const newChecked = {};

    datprFiltered.forEach((item) => {
      newChecked[`datpr_${item.id}`] = true;
    });

    if (selectedRow) {
      newChecked[`realisasi_${selectedRow.id}`] = true;
    }

    setCheckedItems(newChecked);

  }, [dataDatPR, dataSarana, masterSarana, selectedRow, drawerMode, setupChecked]);

  useEffect(() => {
    if (!selectedRow || filteredDatPR.length === 0) {
      setTotalATPR(0);
      setTotalDppRealisasi(0);
      setSelisih(0);
      return;
    }

    const totalATPRValue = filteredDatPR
      .filter((item) => checkedItems[`datpr_${item.id}`])
      .reduce((sum, item) => sum + Number(item.harga || 0), 0);

    const totalRealisasiValue = selectedRow
      ? checkedItems[`realisasi_${selectedRow.id}`]
        ? Number(selectedRow.dpp || 0)
        : 0
      : 0;

    setTotalATPR(totalATPRValue);
    setTotalDppRealisasi(totalRealisasiValue);
    setSelisih(totalATPRValue - totalRealisasiValue);
  }, [checkedItems, filteredDatPR, selectedRow]);

  const atprCheckedCount = Object.entries(checkedItems)
    .filter(([key, val]) => key.startsWith("datpr_") && val === true).length;

  const realisasiCheckedCount = Object.entries(checkedItems)
    .filter(([key, val]) => key.startsWith("realisasi_") && val === true).length;

  const showMatching =
    atprCheckedCount > 0 &&
    realisasiCheckedCount > 0 &&
    selisih < 10 &&
    selisih > -10;

  useEffect(() => {
    if (showMatching && isDrawerOpen && matchingButtonRef.current) {
      matchingButtonRef.current.focus();
    }
  }, [showMatching, isDrawerOpen]);

  const handleMatchKeterangan = async () => {
    if (!selectedRow) {
      console.warn("⚠️ Tidak ada baris realisasi yang dipilih!");
      return;
    }

    const kd_toko = identitas?.kd_toko ?? "";
    if (!no_rab || !kd_toko) {
      return Swal.fire('Gagal', 'Data Kode Toko atau RAB tidak ditemukan.', 'error');
    }

    // Ambil semua id AT/PR yang dicentang
    const pilih = Object.entries(checkedItems)
      .filter(([key, val]) => key.startsWith("datpr_") && val === true)
      .map(([key]) => parseInt(key.replace("datpr_", ""), 10));

    // Ambil semua id realisasi yang dicentang
    const iden = Object.entries(checkedItems)
      .filter(([key, val]) => key.startsWith("realisasi_") && val === true)
      .map(([key]) => parseInt(key.replace("realisasi_", ""), 10));

    try {
      const payload = {
        no_rab,
        site: kd_toko,
        pilih,
        iden,
        tipe: tipeMatching,
      };
      
      const response = await fetch(`/api/atpr-match`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });

      const result = await response.json();

      if (result.success) {
        Swal.fire('Berhasil', 'Matching data berhasil disimpan.', 'success').then(() => {
          fetchTables();
          fetchData();
          setTotalATPR(0);
          setTotalDppRealisasi(0);
          setSelisih(0);
          setIsDrawerOpen(false);

          setSelectedIds([]);
          setSelectedRealisasiIds([]);
          setSelectedSurkasYIds([]);
          setSelectedRows({ atpr: [], realisasi: [] });
          setTipeMatching('');          
        });
      } else {
        Swal.fire('Gagal', result.message || 'Terjadi kesalahan saat menyimpan data.', 'error');
      }
    } catch (error) {
      Swal.fire('Error', 'Tidak dapat terhubung ke server.', 'error');
    }
  };

  const realisasiFilteredData = useMemo(() => {
    if (!data?.[0]?.all_data?.rows) return [];
    return data[0].all_data.rows.filter(
      item => item.flag_dat_pr === ""
    );
  }, [data]);

  // ========================= Cek AT/PR By Toko =======================
  const [atPrData, setAtPrData] = useState([]);
  const [loadingAtPr, setLoadingAtPr] = useState(false);
  const [selectedAtPrIds, setSelectedAtPrIds] = useState([]);
  const [searchMasterDatPRValue, setSearchMasterDatPRValue] = useState('');
  const [submittingAtPr, setSubmittingAtPr] = useState(false);

  const [filterTanggal, setFilterTanggal] = useState({
    start: "",
    end: "",
  });
  
  const [columnFilter, setColumnFilter] = useState({
    column: "",
    operator: "contains",
    value: "",
  });

  const isAllMasterSelected = useMemo(() => {
    if (!visibleRows || visibleRows.length === 0) return false;

    return visibleRows.every(row =>
      selectedAtPrIds.includes(row.id)
    );
  }, [visibleRows, selectedAtPrIds]);

  const fetchMasterAtPr = async () => {
    if (!identitas?.kd_toko) return;

    setLoadingAtPr(true);
    try {
      const res = await fetch(
        `/api/dat-pr-toko?kd_toko=${encodeURIComponent(identitas.kd_toko)}`
      );

      if (!res.ok) {
        throw new Error(`HTTP error ${res.status}`);
      }

      const json = await res.json();
      setAtPrData(json.data || []);
    } catch (err) {
      console.error("Gagal mengambil master AT/PR", err);
    } finally {
      setLoadingAtPr(false);
    }
  };

  useEffect(() => {
    if (isDrawerOpen && drawerMode === "at-pr") {
      fetchMasterAtPr();
    }
  }, [isDrawerOpen, drawerMode]);

  const handleOpenAtPrDrawer = () => {
    setDrawerMode("at-pr");
    setIsDrawerOpen(true);
  };

  const atPrColumns = useMemo(() => [
    {
      id: "select",
      header: () => (
        <input
          type="checkbox"
          className="form-checkbox"
          checked={isAllMasterSelected}
          onChange={(e) => handleSelectAllAtPr(e.target.checked)}
        />
      ),
      cell: ({ row }) => {
        const id = row.original.id;

        return (
          <input
            type="checkbox"
            className="form-checkbox"
            checked={selectedAtPrIds.includes(id)}
            onChange={(e) => {
              setSelectedAtPrIds(prev =>
                e.target.checked
                  ? [...prev, id]
                  : prev.filter(item => item !== id)
              );
            }}
          />
        );
      },
      meta: { className: "text-center" },
    },
    {
      header: "Status",
      accessorKey: "status",
    },
    {
      header: "No Seri",
      accessorKey: "seri",
    },
    {
      header: "Keterangan",
      accessorKey: "keterangan",
    },
    {
      header: "Surkas",
      accessorKey: "surkas",
      meta: { className: "text-center" },
    },
    {
      header: "Inv Num",
      accessorKey: "inv_num",
    },
    {
      header: "Tgl Mulai Susut",
      accessorKey: "tgl_mulai_susut",
    },
    {
      header: "Nilai",
      accessorKey: "harga_perolehan",
      cell: info => formatRupiah(info.getValue()),
      meta: { className: "text-right" },
    },
  ], [selectedAtPrIds, isAllMasterSelected]);


  const handleSelectAllAtPr = (checked) => {
    if (!visibleRows) return;

    const visibleIds = visibleRows.map(row => row.id);

    setSelectedAtPrIds(prev =>
      checked
        ? Array.from(new Set([...prev, ...visibleIds]))
        : prev.filter(id => !visibleIds.includes(id))
    );
  };

  const toggleSelectAllAtPr = () => {
    if (!visibleRows || visibleRows.length === 0) return;

    const visibleIds = visibleRows.map(row => row.id);

    const allSelected = visibleIds.every(id =>
      selectedAtPrIds.includes(id)
    );

    handleSelectAllAtPr(!allSelected);
  };


  const filteredAtPrData = useMemo(() => {
    return atPrData.filter(row => {
      // 🔸 Filter tanggal
      if (filterTanggal.start || filterTanggal.end) {
        const tgl = new Date(row.tgl_mulai_susut);
        if (filterTanggal.start && tgl < new Date(filterTanggal.start)) return false;
        if (filterTanggal.end && tgl > new Date(filterTanggal.end)) return false;
      }

      // 🔸 Filter kolom dinamis
      const { column, operator, value } = columnFilter;
      if (column && value) {
        const cellValue = String(row[column] ?? "").toLowerCase();
        const filterValue = value.toLowerCase();

        switch (operator) {
          case "equal":
            if (cellValue !== filterValue) return false;
            break;
          case "contains":
            if (!cellValue.includes(filterValue)) return false;
            break;
          case "not_contains":
            if (cellValue.includes(filterValue)) return false;
            break;
          case "begin":
            if (!cellValue.startsWith(filterValue)) return false;
            break;
          case "end":
            if (!cellValue.endsWith(filterValue)) return false;
            break;
          default:
            break;
        }
      }

      return true;
    });
  }, [atPrData, filterTanggal, columnFilter]);

  const { minDate, maxDate } = useMemo(() => {
    if (!atPrData.length) {
      return { minDate: "", maxDate: "" };
    }

    const dates = atPrData
      .map(r => r.tgl_mulai_susut)
      .filter(Boolean)
      .map(d => new Date(d));

    if (!dates.length) {
      return { minDate: "", maxDate: "" };
    }

    const min = new Date(Math.min(...dates));
    const max = new Date(Math.max(...dates));

    const toInputDate = (d) => d.toISOString().split("T")[0];

    return {
      minDate: toInputDate(min),
      maxDate: toInputDate(max),
    };
  }, [atPrData]);

  useEffect(() => {
    if (!minDate || !maxDate) return;

    setFilterTanggal(prev => {
      if (prev.start || prev.end) return prev;
      return {
        start: minDate,
        end: maxDate,
      };
    });

    // reset filter kolom
    setColumnFilter({
      column: "",
      operator: "equal",
      value: "",
    });
  }, [no_rab, minDate, maxDate]);

  const handleSubmitAtPr = async () => {
    if (!selectedAtPrIds.length || !no_rab) return;

    setSubmittingAtPr(true);

    try {
      const res = await fetch('/api/import-at-pr', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          no_rab,
          ids: selectedAtPrIds,
        }),
      });

      const json = await res.json();

      if (!res.ok || json.success === false) {
        throw new Error(json.message || 'Gagal submit AT/PR');
      }

      // ✅ Alert sukses (pesan dari backend)
      await Swal.fire({
        icon: 'success',
        title: 'Berhasil',
        text: json.message,
        confirmButtonText: 'OK',
      });

      setSelectedAtPrIds([]);
      fetchData();
      fetchTables();
    } catch (err) {
      console.error(err);

      // ❌ Alert error (pesan dari backend / exception)
      Swal.fire({
        icon: 'error',
        title: 'Gagal',
        text: err.message || 'Terjadi kesalahan',
      });
    } finally {
      setSubmittingAtPr(false);
    }
  };

  // ======================= SHORTCUT ==================
  useEffect(() => {
    const handleKeyDown = (e) => {
      if (!isCollapsed) {
        return;
      }
      
      const { key, code, altKey } = e;

      if (key === 'Escape') {
        if (rightsidebarOpen) setRightSidebarOpen(false);
        if (isMenuOpen) setMenuOpen(false);
        if (openTopDrawer) setOpenTopDrawer(false);
        if (isDrawerOpen) setIsDrawerOpen(false);
        if (showDrawer) {
          setShowDrawer(false);
          resetFilePicker();
        }
        if(drawerMode === 'at-pr') setFilterTanggal({ start: minDate, end: maxDate });
      }

      if (altKey) {
        switch (code) {
          case 'KeyC':
            e.preventDefault();
            setSelectedIds([]);
            setSelectedRealisasiIds([]);
            setSelectedSurkasYIds([]);

            setRightSidebarOpen(prev => !prev);
            setShowDrawer(false);
            setMenuOpen(false);
            setOpenTopDrawer(false);
            break;
          
          case 'KeyH':
            e.preventDefault();
            handleAutomatchDatpr(
              identitas.no_rab,
              () => {
                fetchData();
                fetchTables();
              }
            );
            break;

          case 'KeyI':
            e.preventDefault();
            setDrawerMode('import');
            setShowDrawer(true);
            setRightSidebarOpen(false);
            setMenuOpen(false);
            setOpenTopDrawer(false);
            break;

          case 'KeyK':
            e.preventDefault();
            setShowDrawer(false);
            setRightSidebarOpen(false);
            setMenuOpen(false);
            setOpenTopDrawer(false);
            handleRightPanelToggle(rekapRef.current, 'view');
            break;

          case 'KeyM':
            e.preventDefault();
            setMenuOpen(true);
            setShowDrawer(false);
            setRightSidebarOpen(false);
            setOpenTopDrawer(false);
            break;
          
          case 'KeyN':
            e.preventDefault();
            handleUpdateketerangan(identitas.no_rab);
            break;
          
          case 'KeyP':
            e.preventDefault();
            navigate('/lpd-detail');
            break;
          
          case 'KeyR':
            e.preventDefault();
            setDrawerMode('at-pr');
            setIsDrawerOpen(true);
            break;

          case 'KeyS':
            e.preventDefault();
            if (isDrawerOpen){
              if (selectedAtPrIds.length && !submittingAtPr) {
                handleSubmitAtPr();
              }    
            }else{
              navigate('/sarana_toko');
            }
            break;

          case 'KeyT':
            e.preventDefault();
            handleCreateLPD(identitas.no_rab);
            break;
          
          case 'KeyU':
            e.preventDefault();
            handleUpdateATPR(
              identitas.no_rab,
              () => {
                fetchData();
                fetchTables();
              }
            );
            break;

          case 'KeyA':
            e.preventDefault();
            if (activeTab === 'at-pr') {
              handleSelectAll();
              if (checkboxRef.current) checkboxRef.current.focus();
            }

            if (activeTab === 'realisasi') {
              handleSelectAllRealisasi();
              if (checkboxRef.current) checkboxRef.current.focus();
            }

            if (activeTab === 'at-pr-surkas') {
              handleSelectAllSurkasY();
              if (checkboxRef.current) checkboxRef.current.focus();
            }

            if (drawerMode === 'at-pr') {
              toggleSelectAllAtPr();
            }

            break;
          
          case 'KeyV':
            e.preventDefault();
            handleMatching();
            break;

          case 'Digit1':
            e.preventDefault();
            setActiveTab('at-pr');
            break;

          case 'Digit2':
            e.preventDefault();
            setActiveTab('realisasi');
            break;
          
          case 'Digit3':
            e.preventDefault();
            setActiveTab('at-pr-match');
            break;

          case 'Digit4':
            e.preventDefault();
            setActiveTab('at-pr-surkas');
            break;

          default:
            break;
        }
      }

      if (
        key === 'Enter' &&
        document.activeElement === labelRef.current && fileInputRef.current
      ) {
        fileInputRef.current.click();
      }
    };

    window.addEventListener('keydown', handleKeyDown);
    return () => window.removeEventListener('keydown', handleKeyDown);
  }, [
    isCollapsed,
    showDrawer,
    rightsidebarOpen,
    isMenuOpen,
    openTopDrawer,
    isDrawerOpen,
    drawerMode,
    selectedAtPrIds,
    visibleRows,
    submittingAtPr,
    handleSelectAll,
    handleSubmitAtPr,
    setDrawerMode,
    setActiveTab
  ]);

  return (
    <main className="flex-1 px-2 py-2 z-10 text-white h-[calc(100vh-40px)] overflow-hidden">
      <div className="h-full bg-white/60 rounded-lg shadow-lg text-gray-800 w-full flex flex-col">
        <div className="relative flex items-center justify-center mb-4 px-4 pt-4">
          <div className="mb-4 text-center">
            <h2 className="text-xl font-bold">AKTIVA - PREPAID</h2>
            <h4 className="font-bold">{identitas.jns_toko} - {identitas.nama_toko} - {identitas.kd_toko}</h4>
            <h4 className="font-bold">{no_rab}</h4>
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
          berkas={berkas}
          identitas={identitas}
          data={rekap}
          onToggleRightPanel={handleRightPanelToggle}
          fetchData={fetchData}
          fetchTables={fetchTables}
        />

        <RightSidebar isOpen={rightsidebarOpen} onClose={closeRightSidebar} />
        
        <TopDrawer isOpen={openTopDrawer} onClose={() => setOpenTopDrawer(false)}>
          <div className="flex gap-4 max-h-[28vh] overflow-hidden">
            {/* TABEL AT/PR */}
            {selectedRows?.atpr?.length > 0 && (
              <div className="flex-1 border border-gray-300 rounded overflow-auto max-h-[70vh]">
                <div className="bg-blue-100 font-bold text-center py-2 sticky top-0 z-20">
                  {isSurkasYOnly ? 'Data Surkas Y' : 'Data AT/PR'}
                </div>
                <table className="min-w-full text-sm text-gray-800 border-collapse">
                  <thead className="sticky top-8 z-10 bg-blue-50">
                    <tr>
                      {columns
                        .filter(col => col.accessorKey && !['inv_num', 'tgl_perolehan'].includes(col.accessorKey))
                        .map(col => (
                          <th
                            key={col.accessorKey}
                            className={`p-2 border text-center ${
                              ['nilai'].includes(col.accessorKey) ? 'text-right' : 'text-left'
                            }`}
                          >
                            {col.header}
                          </th>
                        ))}
                    </tr>
                  </thead>
                  <tbody>
                    {selectedRows.atpr.map((row) => (
                      <tr key={row.id} className="hover:bg-gray-50">
                        {columns
                          .filter(col => col.accessorKey && !['inv_num', 'tgl_perolehan'].includes(col.accessorKey))
                          .map(col => {
                            const value = row[col.accessorKey];
                            const isCurrency = ['harga'].includes(col.accessorKey);
                            return (
                              <td
                                key={col.accessorKey}
                                className={`p-2 border ${
                                  typeof value === 'number' ? 'text-right' : 'text-left'
                                }`}
                              >
                                {isCurrency ? formatRupiah(value) : value}
                              </td>
                            );
                          })}
                      </tr>
                    ))}
                  </tbody>
                  <tfoot className="bg-blue-100 font-semibold">
                    <tr>
                      {columns
                        .filter(col => col.accessorKey && !['inv_num', 'tgl_perolehan'].includes(col.accessorKey))
                        .map(col => (
                          <td
                            key={col.accessorKey}
                            className={`p-2 border ${
                              ['harga'].includes(col.accessorKey) ? 'text-right' : 'text-left'
                            }`}
                          >
                            {col.accessorKey === 'harga' ? formatRupiah(totalAtprNilai) : ''}
                          </td>
                        ))}
                    </tr>
                  </tfoot>
                </table>
              </div>
            )}

            {/* TABEL REALISASI */}
            {selectedRows?.realisasi?.length > 0 && (
              <div className="flex-1 border border-gray-300 rounded overflow-auto max-h-[70vh]">
                <div className="bg-green-100 font-bold text-center py-2 sticky top-0 z-20">Data Realisasi</div>
                <table className="min-w-full text-sm text-gray-800 border-collapse">
                  <thead className="sticky top-8 z-10 bg-green-50 text-center">
                    <tr>
                      <th className="p-2 border">Description</th>
                      <th className="p-2 border">DPP</th>
                      <th className="p-2 border">PPN</th>
                      <th className="p-2 border">Total</th>
                    </tr>
                  </thead>
                  <tbody>
                    {selectedRows.realisasi.map((row) => (
                      <tr key={row.id} className="hover:bg-gray-50">
                        <td className="p-2 border text-left">{row.keterangan}</td>
                        <td className="p-2 border text-right">{formatRupiah(row.dpp)}</td>
                        <td className="p-2 border text-right">{formatRupiah(row.ppn)}</td>
                        <td className="p-2 border text-right">{formatRupiah(row.total)}</td>
                      </tr>
                    ))}
                  </tbody>
                  <tfoot className="bg-green-100 font-semibold text-right">
                    <tr>
                      <td className="p-2 border text-left">Total</td>
                      <td className="p-2 border">{formatRupiah(totalDpp)}</td>
                      <td className="p-2 border">{formatRupiah(totalPpn)}</td>
                      <td className="p-2 border">{formatRupiah(totalRealisasi)}</td>
                    </tr>
                  </tfoot>
                </table>
              </div>
            )}
          </div>
          
          {selectedRows?.realisasi?.length === 0 && (
            <div className="flex items-center mt-4">
              <label className="text-sm font-semibold mr-2">Type Action :</label>
              <select
                value={tipeMatching}
                onChange={e => setTipeMatching(e.target.value)}
                className="border border-gray-300 rounded px-3 py-1 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400"
              >
                <option value="">-- Select --</option>
                <option value="retire">Retire</option>
                <option value="change">Ubah Flag Surkas</option>
                <option value="modal">Pencatatan Modal</option>
              </select>
            </div>
          )}

          <div className="flex justify-center mt-4">
            {!(selectedRows?.atpr?.length === 0 && selectedRows?.realisasi?.length > 0) && (
              <button
                type="button"
                onClick={handleSubmitMatching}
                ref={matchButtonTopRef}
                className="bg-green-600 text-white px-4 py-1 rounded hover:bg-green-700 flex items-center gap-2 focus:outline-none focus:ring-2 focus:ring-green-400"
              >
                {selectedRows?.atpr?.length > 0 && selectedRows?.realisasi?.length > 0 ? (
                  <>
                    <HiRefresh className="w-4 h-4" />
                    Match
                  </>
                ) : (
                  <>
                    <HiSave className="w-4 h-4" />
                    Save
                  </>
                )}
              </button>
            )}
          </div>
        </TopDrawer>
        
        <CenterDrawer
          isOpen={isDrawerOpen}
          onClose={handleCloseDrawer}
          widthClass="max-w-full"
        >
          {/* WRAPPER RELATIVE */}
          <div className="relative">
            {/* CLOSE BUTTON */}
            <button
                type="button"
                onClick={() => setIsDrawerOpen(false)}
                className="absolute top-2 right-3 text-gray-500 hover:text-red-600 transition"
                title="Tutup"
            >
                <HiX className="w-6 h-6" />
            </button>

            {drawerMode === "inv" && (
              <div>
                <div className="overflow-x-auto">
                  <table className="min-w-full border border-gray-300 text-sm">
                    <thead className="bg-gray-100 text-center">
                      <tr>
                        <th colSpan={4} className="bg-yellow-400 px-3 py-2 border">Data AT/PR</th>
                        <th colSpan={3} className="bg-green-600 px-3 py-2 border text-white">Data Realisasi</th>
                        <th rowSpan={2} className="bg-blue-600 px-3 py-2 border text-white">Match</th>
                      </tr>
                      <tr>
                        <th className="border px-3 py-2">No</th>
                        <th className="border px-3 py-2">No Seri</th>
                        <th className="border px-3 py-2">Uraian</th>
                        <th className="border px-3 py-2">Nilai</th>
                        <th className="border px-3 py-2">Deskripsi</th>
                        <th className="border px-3 py-2">DPP</th>
                        <th className="border px-3 py-2">Invoice Num</th>
                      </tr>
                    </thead>
                    <tbody>
                      {(() => {
                        const filteredData = dataDatPR
                          .filter(item => item.flag_realisasi === '' || item.flag_realisasi === null)
                          .filter(item => {
                            const realisasi = data[0].all_data.rows.find(r =>
                              r.inv_num === item.inv_num &&
                              r.rab === no_rab &&
                              r.flag_realisasi !== null &&
                              r.flag_realisasi !== '' &&
                              Number(item.harga) === Number(r.dpp)
                            );
                            return !!realisasi;
                          });

                        if (filteredData.length === 0) {
                          return (
                            <tr>
                              <td colSpan={8} className="border text-center py-2 text-gray-500 italic">
                                Tidak ada data yang match
                              </td>
                            </tr>
                          );
                        }

                        return filteredData.map((item, idx) => {
                          const realisasi = data[0].all_data.rows.find(r =>
                            r.inv_num === item.inv_num &&
                            r.rab === no_rab &&
                            r.flag_realisasi !== null &&
                            r.flag_realisasi !== '' &&
                            Number(item.harga) === Number(r.dpp)
                          );

                          const checkboxValue = `${item.id}_${realisasi?.id ?? 'null'}`;
                          const defaultChecked = Number(item.harga) === Number(realisasi?.dpp);
                          const isChecked = checkedItems[checkboxValue] ?? defaultChecked;

                          return (
                            <tr key={idx}>
                              <td className="border px-2 py-1 text-center">{idx + 1}</td>
                              <td className="border px-2 py-1">{item.seri}</td>
                              <td className="border px-2 py-1">{item.keterangan}</td>
                              <td className="border px-2 py-1 text-right">{formatRupiah(item.harga)}</td>
                              <td className="border px-2 py-1">{realisasi?.keterangan ?? '-'}</td>
                              <td className="border px-2 py-1 text-right">{formatRupiah(realisasi?.dpp)}</td>
                              <td className="border px-2 py-1">{realisasi?.inv_num ?? '-'}</td>
                              <td className="border px-2 py-1 text-center">
                                <input
                                  type="checkbox"
                                  value={checkboxValue}
                                  checked={isChecked}
                                  onChange={() =>
                                    setCheckedItems(prev => ({
                                      ...prev,
                                      [checkboxValue]: !prev[checkboxValue]
                                    }))
                                  }
                                />
                              </td>
                            </tr>
                          );
                        });
                      })()}
                    </tbody>
                  </table>
                </div>

                <div className="mt-4 text-center">
                  <button
                    ref={matchButtonCenterRef}
                    onClick={handleMatchingInv}
                    className="bg-green-600 hover:bg-green-700 text-white font-semibold py-2 px-6 rounded"
                  >
                    Matching
                  </button>
                </div>
              </div>
            )}

            {drawerMode === "keterangan" && (
              <div className="space-y-6">
                {/* ==================== TABEL AT/PR ==================== */}
                <div>
                  <h3 className="bg-yellow-400 text-center font-semibold py-2 border">
                    Data AT/PR
                  </h3>

                  <div className="overflow-x-auto">
                    <table className="min-w-full border border-gray-300 text-sm">
                      <thead className="bg-gray-100 text-center">
                        <tr>
                          <th className="border px-3 py-2 w-12">Pilih</th>
                          <th className="border px-3 py-2">No Seri</th>
                          <th className="border px-3 py-2">Uraian</th>
                          <th className="border px-3 py-2">Nilai</th>
                        </tr>
                      </thead>
                      <tbody>
                        {filteredDatPR.length === 0 ? (
                          <tr>
                            <td
                              colSpan={4}
                              className="border text-center py-2 text-gray-500 italic"
                            >
                              Tidak ada data AT/PR yang cocok dengan keyword
                            </td>
                          </tr>
                        ) : (
                          filteredDatPR.map((item) => {
                            const key = `datpr_${item.id}`;
                            const isChecked = checkedItems[key] ?? true;
                            return (
                              <tr key={item.id}>
                                <td className="border px-2 py-1 text-center">
                                  <input
                                    type="checkbox"
                                    value={item.id}
                                    checked={isChecked}
                                    onChange={() =>
                                      setCheckedItems((prev) => ({
                                        ...prev,
                                        [key]: !prev[key],
                                      }))
                                    }
                                  />
                                </td>
                                <td className="border px-2 py-1">{item.seri}</td>
                                <td className="border px-2 py-1">{item.keterangan}</td>
                                <td className="border px-2 py-1 text-right">
                                  {formatRupiah(item.harga)}
                                </td>
                              </tr>
                            );
                          })
                        )}

                        {/* 🔸 Total AT/PR */}
                        <tr className="bg-gray-50 font-semibold">
                          <td colSpan={3} className="border px-3 py-2 text-right">
                            Total AT/PR
                          </td>
                          <td className="border px-3 py-2 text-right">
                            {formatRupiah(totalATPR)}
                          </td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>

                {/* ==================== SELISIH ==================== */}
                <div className="text-center py-3 font-semibold border border-dashed border-gray-400 rounded-md bg-gray-50">
                  <span>Total AT/PR: {formatRupiah(totalATPR)} </span> |{" "}
                  <span>Total DPP Realisasi: {formatRupiah(totalDppRealisasi)} </span> |{" "}
                  <span>
                    Selisih:{" "}
                    <span
                      className={
                        selisih === 0
                          ? "text-green-600"
                          : selisih > 0
                          ? "text-blue-600"
                          : "text-red-600"
                      }
                    >
                      {formatRupiah(selisih)}
                    </span>
                  </span>
                </div>

                {/* ==================== TABEL REALISASI ==================== */}
                <div>
                  <h3 className="bg-green-600 text-center font-semibold text-white py-2 border">
                    Data Realisasi
                  </h3>

                  <table className="min-w-full border border-gray-300 text-sm">
                    <thead className="bg-gray-100 text-center">
                      <tr>
                        <th className="border px-3 py-2 w-12">Pilih</th>
                        <th className="border px-3 py-2">Inv Num</th>
                        <th className="border px-3 py-2">Keterangan</th>
                        <th className="border px-3 py-2">DPP</th>
                        <th className="border px-3 py-2">PPN</th>
                        <th className="border px-3 py-2">Total</th>
                      </tr>
                    </thead>
                    <tbody>
                      {selectedRow ? (
                        (() => {
                          const r = selectedRow;
                          const key = `realisasi_${r.id}`;
                          const isChecked = checkedItems[key] ?? true;
                          return (
                            <>
                              <tr key={r.id}>
                                <td className="border px-2 py-1 text-center">
                                  <input
                                    type="checkbox"
                                    value={r.id}
                                    checked={isChecked}
                                    onChange={() =>
                                      setCheckedItems((prev) => ({
                                        ...prev,
                                        [key]: !prev[key],
                                      }))
                                    }
                                  />
                                </td>
                                <td className="border px-2 py-1">{r.inv_num ?? "-"}</td>
                                <td className="border px-2 py-1">{r.keterangan ?? "-"}</td>
                                <td className="border px-2 py-1 text-right">
                                  {formatRupiah(r.dpp ?? 0)}
                                </td>
                                <td className="border px-2 py-1 text-right">
                                  {formatRupiah(r.ppn ?? 0)}
                                </td>
                                <td className="border px-2 py-1 text-right">
                                  {formatRupiah(r.total ?? 0)}
                                </td>
                              </tr>

                              {/* 🔸 Total Realisasi */}
                              <tr className="bg-gray-50 font-semibold">
                                <td colSpan={5} className="border px-3 py-2 text-right">
                                  Total Realisasi
                                </td>
                                <td className="border px-3 py-2 text-right">
                                  {formatRupiah(isChecked ? Number(r.dpp || 0) : 0)}
                                </td>
                              </tr>
                            </>
                          );
                        })()
                      ) : (
                        <tr>
                          <td
                            colSpan={6}
                            className="border text-center py-2 text-gray-500 italic"
                          >
                            Tidak ada data realisasi yang dipilih
                          </td>
                        </tr>
                      )}
                    </tbody>
                  </table>
                </div>

                {/* 🔘 Tombol Matching */}
                {showMatching && (
                  <div className="mt-4 flex justify-center">
                    <button
                      ref={matchingButtonRef}
                      className="flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-5 py-2 rounded-md shadow-md focus:ring-2 focus:ring-blue-400 transition-all duration-150"
                      onClick={handleMatchKeterangan}
                    >
                      <HiRefresh className="w-5 h-5" />
                      <span>Match</span>
                    </button>
                  </div>
                )}
              </div>
            )}

            {drawerMode === "at-pr" && (
              <div className="space-y-4">
                <h3 className="bg-blue-600 text-white text-center font-semibold py-2">
                  Data AT / PR Toko {identitas?.kd_toko}
                </h3>

                {loadingAtPr ? (
                  <div className="py-10">
                    <TableLoading text="Memuat Master AT/PR..." />
                  </div>
                ) : (
                  <ReusableTable
                    columns={atPrColumns}
                    data={filteredAtPrData}
                    globalFilter={searchMasterDatPRValue}
                    setGlobalFilter={setSearchMasterDatPRValue}
                    tableClassName="min-w-full border-collapse"
                    theadClassName="bg-gray-100 sticky top-0 z-10"
                    thClassName="p-2 border-b text-sm font-semibold text-left bg-white"
                    tdClassName="p-2 border-b text-sm text-gray-700"
                    searchInputRef={atprMasterSearchRef}
                    onVisibleDataChange={(rows) => setVisibleRows(rows)}
                    rightElement={
                      <div className="flex flex-wrap items-center gap-4">
                        {/* ✅ Filter Tanggal */}
                        <div className="flex items-center gap-2 text-sm">
                          <span>Tanggal:</span>
                          <input
                            type="date"
                            value={filterTanggal.start}
                            max={filterTanggal.end || maxDate}
                            onChange={(e) => {
                              const start = e.target.value;

                              setFilterTanggal(prev => ({
                                start,
                                end: prev.end && prev.end < start ? start : prev.end,
                              }));
                            }}
                            className="border rounded px-2 py-1 text-sm h-[42px]"
                          />

                          <span>-</span>

                          <input
                            type="date"
                            value={filterTanggal.end}
                            min={filterTanggal.start || minDate}
                            max={maxDate}
                            onChange={(e) =>
                              setFilterTanggal(prev => ({
                                ...prev,
                                end: e.target.value,
                              }))
                            }
                            className="border rounded px-2 py-1 text-sm h-[42px]"
                          />
                        </div>

                        {/* ✅ Filter Kolom */}
                        <div className="flex items-center gap-2 text-sm">
                          <select
                            value={ columnFilter.column}
                            onChange={(e) =>
                              setColumnFilter(prev => ({ ...prev, column: e.target.value }))
                            }
                            className="border rounded px-2 py-1 h-[42px]"
                          >
                            <option value="" disabled>-- Kolom --</option>
                            <option value="seri">No Seri</option>
                            <option value="keterangan">Keterangan</option>
                            <option value="surkas">surkas</option>
                            <option value="inv_num">Inv Num</option>
                            <option value="status">Status</option>
                          </select>

                          <select
                            value={columnFilter.operator}
                            onChange={(e) =>
                              setColumnFilter(prev => ({ ...prev, operator: e.target.value }))
                            }
                            className="border rounded px-2 py-1 h-[42px]"
                          >
                            <option value="equal">Is Equal</option>
                            <option value="contains">Contains</option>
                            <option value="not_contains">Not Contains</option>
                            <option value="begin">Begin With</option>
                            <option value="end">End With</option>
                          </select>

                          <input
                            type="text"
                            placeholder="Value..."
                            value={columnFilter.value}
                            onChange={(e) =>
                              setColumnFilter(prev => ({ ...prev, value: e.target.value }))
                            }
                            className="border rounded px-2 py-1 text-sm h-[42px]"
                          />
                        </div>

                        {/* ✅ SUBMIT BUTTON */}
                        <button
                          onClick={handleSubmitAtPr}
                          disabled={!selectedAtPrIds.length || submittingAtPr}
                          className={`flex items-center gap-2 px-5 py-2 rounded text-white text-sm font-semibold
                            ${
                              !selectedAtPrIds.length || submittingAtPr
                                ? 'bg-gray-400 cursor-not-allowed'
                                : 'bg-green-600 hover:bg-green-700'
                            }`}
                        >
                          <FaPaperPlane className="text-sm" />
                          {submittingAtPr ? 'Mengirim...' : 'Submit'}
                        </button>
                      </div>
                    }
                  />
                )}
              </div>
            )}
          </div>
        </CenterDrawer>

        {showDrawer && (
          <BottomDrawer
            isOpen={showDrawer}
            onClose={() => setShowDrawer(false)}
            height="300px"
          >
            <div className="flex flex-col h-full">
              <div className="flex-1 flex items-center justify-center px-4 mb-12">
                <label
                  ref={labelRef}
                  htmlFor="fileUpload"
                  tabIndex={0}
                  className={`w-full max-w-md flex flex-col items-center justify-center border-2 border-dashed rounded-xl cursor-pointer p-8 transition duration-300
                    ${selectedFile ? 'border-green-400 bg-green-100/10' : 'border-white hover:bg-white/10'}
                  `}
                >
                  {selectedFile ? (
                    <>
                      <FaCheckCircle className="h-12 w-12 text-green-400 mb-2" />
                      <p className="text-green-300 text-center text-sm">{selectedFile.name}</p>
                      <p className="text-white text-xs mt-1">File berhasil dipilih</p>
                    </>
                  ) : (
                    <>
                      <FaCloudUploadAlt className="h-12 w-12 text-white mb-2" />
                      <p className="text-center text-white">
                        <strong>Klik untuk unggah</strong> atau seret dan lepas file ke sini
                      </p>
                      <span className="text-xs italic text-white">Hanya file CSV diperbolehkan</span>
                    </>
                  )}
                  <input
                    id="fileUpload"
                    ref={fileInputRef}
                    type="file"
                    accept=".csv"
                    onChange={handleFileChange}
                    className="hidden"
                  />
                </label>
              </div>

              {/* Footer */}
              <div className="fixed bottom-0 left-[-6%] right-0 border-t flex items-center justify-between z-10">
                <div className="trapezium-box text-white text-3xl shadow-md mt-[-8px] flex items-center justify-center h-[50px] w-[250px] bg-yellow-400">
                  Import Data DAT/PR
                </div>

                <div className="flex gap-2 px-2">
                  <button
                    type="button"
                    onClick={handleImport}
                    className="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 flex items-center gap-2"
                  >
                    <FaFileImport className="w-4 h-4" />
                    Import
                  </button>
                  <button
                    type="button"
                    onClick={resetFilePicker}
                    className="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700 flex items-center gap-2"
                  >
                    <HiRefresh className="w-4 h-4" />
                    Reset
                  </button>
                </div>
              </div>
            </div>
          </BottomDrawer>
        )}

        <div className="flex-1 px-4 pb-4 space-y-4">
          <div className="sticky top-0 z-20 border-b">
            <div className="flex">
              <button
                className={`px-4 py-2 font-semibold ${activeTab === 'at-pr' ? 'border-b-2 border-blue-600 text-blue-600' : 'text-gray-600'}`}
                onClick={() => setActiveTab('at-pr')}
              >
                1. Aktiva & Prepaid
              </button>
              <button
                className={`px-4 py-2 font-semibold ${activeTab === 'realisasi' ? 'border-b-2 border-blue-600 text-blue-600' : 'text-gray-600'}`}
                onClick={() => setActiveTab('realisasi')}
              >
                2. Realisasi
              </button>
              <button
                className={`relative px-4 py-2 font-semibold ${
                  activeTab === 'at-pr-match'
                    ? 'border-b-2 border-blue-600 text-blue-600'
                    : 'text-gray-600'
                }`}
                onClick={() => setActiveTab('at-pr-match')}
              >
                3. AT/PR Match
                {mismatchCount > 0 && (
                  <span className="absolute -top-2 -right-2 bg-red-600 text-white text-xs rounded-full px-2 py-0.5">
                    {mismatchCount}
                  </span>
                )}
              </button>
              <button
                className={`px-4 py-2 font-semibold ${activeTab === 'at-pr-surkas' ? 'border-b-2 border-blue-600 text-blue-600' : 'text-gray-600'}`}
                onClick={() => setActiveTab('at-pr-surkas')}
              >
                4. AT/PR Surkas Y
              </button>
            </div>
          </div>

          <div className="overflow-y-auto max-h-[calc(88vh-160px)] space-y-4 pt-1 pl-1">
            {activeTab === 'at-pr' && (
              loadingTab['at-pr'] ? (
                <TableLoading text="Memuat AT/PR..." />
              ) : (
                <ReusableTable
                  columns={columns}
                  data={dataDatPR}
                  globalFilter={searchDatPRValue}
                  setGlobalFilter={setSearchDatPRValue}
                  tableClassName="min-w-full border-collapse"
                  theadClassName="bg-gray-100 sticky top-0 z-10"
                  thClassName="p-2 border-b text-sm font-semibold text-left bg-white"
                  tdClassName="p-2 border-b text-sm text-gray-700"
                  searchInputRef={atprSearchRef}
                  onVisibleDataChange={(rows) => setVisibleRows(rows)}
                  rightElement={
                      <div className="flex items-center gap-4">
                        <label className="flex items-center gap-2 text-sm">
                            <input
                              type="checkbox"
                              ref={checkboxRef} 
                              checked={isAllSelected}
                              onChange={handleSelectAll}
                              className="form-checkbox"
                            />
                            Select All
                        </label>

                        <button
                          onClick={handleMatching}
                          disabled={selectedIds.length === 0}
                          className={`flex items-center gap-2 px-4 py-2 rounded text-white text-sm 
                            ${selectedIds.length === 0 
                              ? 'bg-gray-400 cursor-not-allowed' 
                              : 'bg-blue-600 hover:bg-blue-700'
                            }`}
                        >
                          <HiSearch className="w-4 h-4" />
                          Preview
                        </button>
                      </div>
                  }
                />
              )
            )}

            {activeTab === 'realisasi' && data?.[0]?.all_data?.rows && (
              loadingTab.realisasi ? (
                <TableLoading text="Memuat Data Realisasi..." />
              ) : (
                <ReusableTable
                  columns={[
                    {
                      id: 'select',
                      header: '',
                      cell: ({ row }) => (
                        <input
                          type="checkbox"
                          checked={selectedRealisasiIds.includes(row.original.id)}
                          onChange={() => handleSelectRealisasiRow(row.original.id)}
                          className="form-checkbox"
                        />
                      ),
                      meta: { align: 'center' },
                    },
                    { header: 'Group', accessorKey: 'kd_group' },
                    { header: 'PLU', accessorKey: 'plu' },
                    {
                      header: 'Inv Num',
                      accessorKey: 'inv_num',
                      cell: ({ row }) => (
                        <button
                          onClick={() => handleOpenDrawer(row.original, "inv")}
                          className="text-blue-600 hover:text-green-500"
                        >
                          {row.original.inv_num}
                        </button>
                      )
                    },
                    {
                      header: 'Description',
                      accessorKey: 'keterangan',
                      cell: ({ row }) => (
                        <button
                          onClick={() => handleOpenDrawer(row.original, "keterangan")}
                          className="text-blue-600 hover:text-green-500"
                        >
                          {row.original.keterangan}
                        </button>
                      )
                    },
                    {
                      header: 'DPP',
                      accessorKey: 'dpp',
                      cell: info => formatAmount(info.getValue()),
                      meta: { className: "text-right" },
                    },
                    {
                      header: 'PPn',
                      accessorKey: 'ppn',
                      cell: info => formatRupiah(info.getValue()),
                      meta: { className: "text-right" },
                    },
                    {
                      header: 'Total',
                      accessorKey: 'total',
                      cell: info => formatRupiah(info.getValue()),
                      meta: { className: "text-right" },
                    },
                  ]}
                  data={realisasiFilteredData}
                  globalFilter={searchRealisasiValue}
                  setGlobalFilter={setSearchRealisasiValue}
                  tableClassName="min-w-full border-collapse"
                  theadClassName="bg-blue-100 sticky top-0 z-10"
                  thClassName="p-2 border-b text-sm font-semibold text-left bg-white"
                  tdClassName="p-2 border-b text-sm text-gray-700"
                  searchInputRef={realisasiSearchRef}
                  onVisibleDataChange={(rows) => setvisibleRowsRealisasi(rows)}
                  rightElement={
                    <div className="flex items-center gap-4">
                      <label className="flex items-center gap-2 text-sm">
                        <input
                          type="checkbox"
                          checked={setupChecked}
                          onChange={handleToggleSetup}
                          className="form-checkbox"
                        />
                        Match By Desc
                      </label>
                      <label className="flex items-center gap-2 text-sm">
                        <input
                          type="checkbox"
                          ref={checkboxRealisasiRef}
                          checked={isAllRealisasiSelected}
                          onChange={handleSelectAllRealisasi}
                          className="form-checkbox"
                        />
                        Select All
                      </label>
                      <button
                        onClick={handleMatching}
                        disabled={selectedRealisasiIds.length === 0}
                        className={`flex items-center gap-2 px-4 py-2 rounded text-white text-sm 
                          ${selectedRealisasiIds.length === 0 
                            ? 'bg-gray-400 cursor-not-allowed' 
                            : 'bg-blue-600 hover:bg-blue-700'
                          }`}
                      >
                        <HiSearch className="w-4 h-4" />
                        Preview
                      </button>
                    </div>
                  }
                />
              )
            )}

            {activeTab === 'at-pr-match' && (
              <div className="mt-4 space-y-8">
                {groupedAtPR &&
                  Object.entries(groupedAtPR).map(([flag, item]) => {
                    const totalHarga = item.items?.reduce((sum, i) => sum + Number(i.harga || 0), 0);
                    const totalRealisasi = item.realisasi_items?.reduce((sum, i) => sum + Number(i.dpp || 0), 0);
                    const isMismatch = Math.abs(totalHarga - totalRealisasi) > 100;

                    return (
                      <div
                        key={flag}
                        className="border border-gray-300 rounded-md overflow-x-auto max-h-[520px]"
                      >
                        <div className="w-full min-w-[800px]">
                          <table className="table-auto text-sm border-collapse w-full">
                            <thead className="bg-gray-100 sticky top-0 z-10">
                              <tr>
                                <th className="p-2 border w-[100px] whitespace-nowrap">No Seri</th>
                                <th className="p-2 border w-[180px] whitespace-nowrap">Keterangan</th>
                                <th className="p-2 border w-[120px] text-right">Harga</th>
                                <th className="p-2 border w-[180px] whitespace-nowrap">Description</th>
                                <th className="p-2 border w-[120px] text-right">Amount</th>
                                <th className="p-2 border w-[150px] whitespace-nowrap">Inv Num</th>
                              </tr>
                            </thead>
                            <tbody>
                              <tr className="hover:bg-gray-50 align-top">
                                <td className="p-2 border">
                                  {item.items?.map((itm, idx) => (
                                    <div key={idx}>{itm.seri}</div>
                                  ))}
                                </td>
                                <td className="p-2 border whitespace-nowrap">
                                  {item.items?.map((itm, idx) => (
                                    <div key={idx}>
                                      {itm.keterangan?.length > 80
                                        ? itm.keterangan.slice(0, 80) + '...'
                                        : itm.keterangan}
                                    </div>
                                  ))}
                                </td>
                                <td className="p-2 border text-right">
                                  {item.items?.map((itm, idx) => (
                                    <div key={idx}>{formatRupiah(itm.harga)}</div>
                                  ))}
                                </td>
                                <td className="p-2 border whitespace-nowrap">
                                  {item.realisasi_items?.map((itm, idx) => (
                                    <div key={idx}>
                                      {itm.keterangan?.length > 80
                                        ? itm.keterangan.slice(0, 80) + '...'
                                        : itm.keterangan}
                                    </div>
                                  ))}
                                </td>
                                <td className="p-2 border text-right">
                                  {item.realisasi_items?.map((itm, idx) => (
                                    <div key={idx}>{formatRupiah(itm.dpp)}</div>
                                  ))}
                                </td>
                                <td className="p-2 border whitespace-nowrap">
                                  {item.realisasi_items?.map((itm, idx) => (
                                    <div key={idx}>{itm.inv_num}</div>
                                  ))}
                                </td>
                              </tr>
                            </tbody>
                            <tfoot
                              className={`font-semibold text-sm ${
                                isMismatch ? 'bg-red-200' : 'bg-gray-50'
                              }`}
                            >
                              <tr>
                                <td className="p-2 border text-right" colSpan={2}>Total</td>
                                <td className="p-2 border text-right">{formatRupiah(totalHarga)}</td>
                                <td className="p-2 border"></td>
                                <td className="p-2 border text-right">{formatRupiah(totalRealisasi)}</td>
                                <td className="p-2 border" colSpan={2}>
                                  <button
                                    onClick={() => handleDelete(flag, no_rab, identitas.kd_toko)}
                                    className="text-red-600 hover:text-red-800"
                                    title="Hapus"
                                  >
                                    <HiTrash className="w-5 h-5" />
                                  </button>
                                </td>
                              </tr>
                            </tfoot>
                          </table>
                        </div>
                      </div>
                    );
                  })}
              </div>
            )}

            {activeTab === 'at-pr-surkas' && (
              loadingTab['at-pr'] ? (
                <TableLoading text="Memuat AT/PR Potong Surkas..." />
              ) : (
                <ReusableTable
                  columns={[
                    {
                      header: '',
                      id: 'select',
                      cell: ({ row }) => (
                      <input
                          type="checkbox"
                          checked={selectedSurkasYIds.includes(row.original.id)}
                          onChange={() => handleSelectSurkasYRow(row.original.id)}
                          className="form-checkbox"
                      />
                      ),
                      size: 20,
                      meta: { align: 'center' }
                    },
                    { header: 'Seri', accessorKey: 'seri' },
                    { header: 'Keterangan', accessorKey: 'keterangan' },
                    { header: 'Invoice Num', accessorKey: 'inv_num' },
                    {
                      header: 'Tanggal',
                      accessorKey: 'tgl_perolehan',
                      cell: info => formatDate(info.getValue()),
                    },
                    {
                      header: 'Nilai',
                      accessorKey: 'harga',
                      cell: info => formatRupiah(info.getValue()),
                      meta: { className: "text-right" },
                    },
                  ]}
                  data={dataATPRSurkas}
                  globalFilter={searchDatPRSurkas}
                  setGlobalFilter={setSearchDatPRSurkas}
                  tableClassName="min-w-full border-collapse"
                  theadClassName="bg-gray-100 sticky top-0 z-10"
                  thClassName="p-2 border-b text-sm font-semibold text-left bg-white"
                  tdClassName="p-2 border-b text-sm text-gray-700"
                  searchInputRef={atprYSearchRef}
                  onVisibleDataChange={(rows) => setvisibleRowsSurkasY(rows)}
                  rightElement={
                      <div className="flex items-center gap-4">
                      <label className="flex items-center gap-2 text-sm">
                          <input
                            type="checkbox"
                            ref={checkboxSurkasYRef}
                            checked={isAllSurkasYSelected}
                            onChange={handleSelectAllSurkasY}
                            className="form-checkbox"
                          />
                          Select All
                      </label>

                      <button
                        onClick={handleMatching}
                        disabled={selectedSurkasYIds.length === 0}
                        className={`flex items-center gap-2 px-4 py-2 rounded text-white text-sm 
                          ${selectedSurkasYIds.length === 0 
                            ? 'bg-gray-400 cursor-not-allowed' 
                            : 'bg-blue-600 hover:bg-blue-700'
                          }`}
                      >
                        <HiSearch className="w-4 h-4" />
                        Preview
                      </button>
                      </div>
                  }
                />
              )
            )}
          </div>
        </div>
      </div>
    </main>
  );
}
