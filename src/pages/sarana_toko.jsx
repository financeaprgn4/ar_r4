import React, { useEffect, useState, useRef, useMemo } from 'react';
import { useNavigate } from 'react-router-dom';
import { useSidebar } from "../components/SidebarContext";
import Swal from "sweetalert2";
import axios from "../config/axiosInstance";
import ReusableTable from "../components/ReusableTable";
import RightSidebar from '../components/RightSidebar';
import { useRightPanel } from '../contexts/RightPanelContext';
import Menu_LPD from '../components/Menu_LPD';
import BottomDrawer from "../components/BottomDrawer";
import TopDrawer from "../components/TopDrawer";
import CenterDrawer from "../components/CenterDrawer";
import { useNoRab } from "../contexts/NoRabContext";
import { useLpdDetail } from '../hooks/useLpdDetail';
import TableLoading from "../components/TableLoading";
import { formatDate, formatRupiah, formatAmount } from "../utility/textFormatter";
import { FaUndo, FaSave, FaFileImport, FaCheckCircle, FaCloudUploadAlt } from "react-icons/fa";
import { HiRefresh, HiSearch, HiCog, HiTrash, HiPaperAirplane, HiHome } from "react-icons/hi";
import Papa from "papaparse";
import {
  handleCreateLPD, handleUpdateketerangan, handleAutomatchSarana, handleSyncPP
} from '../utility/exportLPD';
import { useCabang } from "../contexts/CabangContext";

export default function Sarana_toko() {
  const { cabang } = useCabang();
  const { isCollapsed } = useSidebar();
  const navigate = useNavigate();
  const { noRab: no_rab } = useNoRab();
  const [rightsidebarOpen, setRightSidebarOpen] = useState(false);
  const { openLPDPanel, refreshFlag } = useRightPanel();
  const [showDrawer, setShowDrawer] = useState(false);
  const [drawerMode, setDrawerMode] = useState('create');
  const [activeTab, setActiveTab] = useState('sarana');
  const saranaSearchRef = useRef(null);
  const realisasiSearchRef = useRef(null);
  const saranafseeSearchRef = useRef(null);
  const saranasurkasSearchRef = useRef(null);
  const sarananonppSearchRef = useRef(null);
  const saranamktSearchRef = useRef(null);
  const saranasewaSearchRef = useRef(null);
  const saranappSearchRef = useRef(null);
  const textAreaRef = useRef(null);
  const [selectedFile, setSelectedFile] = useState(null);
  const [openTopDrawer, setOpenTopDrawer] = useState(false);
  const [selectedRows, setSelectedRows] = useState([]);
  const closeRightSidebar = () => setRightSidebarOpen(false);
  const checkboxRef = useRef(null);  
  const [dataSarana, setDataSarana] = useState([]);
  const [masterSarana, setMasterSarana] = useState([]);
  const [dataRealisasi, setDataRealisasi] = useState([]);
  const [groupedRealisasi, setGroupedRealisasi] = useState({});
  const [dataFsee, setDataFsee] = useState([]);
  const [dataSurkas, setDataSurkas] = useState([]);
  const [dataNONPP, setDataNONPP] = useState([]);
  const [dataMKT, setDataMKT] = useState([]);
  const [dataSewa, setDataSewa] = useState([]);
  const [dataPP, setDataPP] = useState([]);
  const [selectedIds, setSelectedIds] = useState([]);
  const [visibleRows, setVisibleRows] = useState([]);
  const [visibleRowsRealisasi, setvisibleRowsRealisasi] = useState([]);
  const [visibleRowsFsee, setvisibleRowsFsee] = useState([]);
  const [visibleRowsSurkas, setvisibleRowsSurkas] = useState([]);
  const [visibleRowsNonpp, setvisibleRowsNonpp] = useState([]);
  const fileInputRef = useRef(null);
  const labelRef = useRef(null);
  const [selectedRealisasiIds, setSelectedRealisasiIds] = useState([]);
  const [selectedFseeIds, setSelectedFseeIds] = useState([]);
  const [selectedSurkasIds, setSelectedSurkasIds] = useState([]);
  const [selectedNonppIds, setSelectedNonppIds] = useState([]);
  const checkboxRealisasiRef = useRef();
  const checkboxNonppRef = useRef();
  const matchButtonTopRef = useRef(null);
  const matchButtonCenterRef = useRef(null);
  const [searchSaranaValue, setSearchSaranaValue] = useState('');
  const [searchRealisasiValue, setSearchRealisasiValue] = useState('');
  const [searchFseeValue, setSearchFseeValue] = useState('');
  const [searchSurkasValue, setSearchSurkasValue] = useState('');
  const [searchNonPPValue, setSearchNonPPValue] = useState('');
  const [searchPPValue, setSearchPPValue] = useState('');
  const [searchMktValue, setSearchMktValue] = useState('');
  const [searchSewaValue, setSearchSewaValue] = useState('');
  const [isDrawerOpen, setIsDrawerOpen] = useState(false);
  const [selectedRow, setSelectedRow] = useState(null);
  const [checkedItems, setCheckedItems] = useState({});
  const [tipeMatching, setTipeMatching] = useState('');
  const [dropdownFseeValue, setDropdownFseeValue] = useState('');
  const [dropdownSurkasValue, setDropdownSurkasValue] = useState('');
  const [dropdownNonppValue, setDropdownNonppValue] = useState('');
  const rekapRef = useRef();
  const [text, setText] = useState("");
  const [rows, setRows] = useState([]);
  const [checkedRows, setCheckedRows] = useState({});
  const [matchedSarana, setMatchedSarana] = useState([]);
  const [matchedRealisasi, setMatchedRealisasi] = useState([]);
  const [selectedMaster, setSelectedMaster] = useState(null);
  const matchingButtonRef = useRef(null);

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
  
  const isAllSelected = visibleRows.length > 0 && selectedIds.length === visibleRows.map(item => item.id).length;
  const handleSelectAll = (e = null) => {
    if (e?.target?.checked ?? !isAllSelected) {
      setSelectedIds(visibleRows.map((item) => item.id));
    } else {
      setSelectedIds([]);
    }
  };

  const isAllRealisasiSelected =
    visibleRowsRealisasi.length > 0 &&
    selectedRealisasiIds.length === visibleRowsRealisasi.map(row => row.id).length;

  const handleSelectAllRealisasi = (e = null) => {
    if (e?.target?.checked ?? !isAllRealisasiSelected) {
      setSelectedRealisasiIds(visibleRowsRealisasi.map(row => row.id));
    } else {
      setSelectedRealisasiIds([]);
    }
  };

  const isAllFseeSelected =
    visibleRowsFsee.length > 0 &&
    selectedFseeIds.length === visibleRowsFsee.map(row => row.id).length;
    
  const handleSelectAllFsee = (e = null) => {
    if (e?.target?.checked ?? !isAllFseeSelected) {
      setSelectedFseeIds(visibleRowsFsee.map(row => row.id));
    } else {
      setSelectedFseeIds([]);
    }
  };

  const isAllSurkasSelected =
    visibleRowsSurkas.length > 0 &&
    selectedSurkasIds.length === visibleRowsSurkas.map(row => row.id).length;
    
  const handleSelectAllSurkas = (e = null) => {
    if (e?.target?.checked ?? !isAllSurkasSelected) {
      setSelectedSurkasIds(visibleRowsSurkas.map(row => row.id));
    } else {
      setSelectedSurkasIds([]);
    }
  };

  const isAllNonppSelected =
    visibleRowsNonpp.length > 0 &&
    selectedNonppIds.length === visibleRowsNonpp.map(row => row.id).length;

  const handleSelectAllNonpp = (e = null) => {
    if (e?.target?.checked ?? !isAllNonppSelected) {
      setSelectedNonppIds(visibleRowsNonpp.map(row => row.id));
    } else {
      setSelectedNonppIds([]);
    }
  };

  const handleCheckboxChange = (id) => {
    setSelectedIds((prev) =>
        prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id]
    );
  };

  const handleMatching = () => {
    const saranaSelected = dataSarana.filter(row => selectedIds.includes(row.id));
    const realisasiSelected = data[0].all_data.rows
      .filter(item => item.kd_group === '030008')
      .filter(row => selectedRealisasiIds.includes(row.id));

    if (saranaSelected.length === 0 && realisasiSelected.length === 0) {
      Swal.fire('Peringatan', 'Pilih minimal 1 data sarana atau realisasi terlebih dahulu.', 'warning');
      return;
    }

    const combined = {
      sarana: saranaSelected,
      realisasi: realisasiSelected,
    };

    setSelectedRows(combined);
    setOpenTopDrawer(true);
  };

  useEffect(() => {
    setCheckedItems({});
  }, [drawerMode]);

  useEffect(() => {
    const initChecked = {};

    if (drawerMode === "kode") {
      matchedSarana.forEach(item => {
        if (!item._realisasi) return;
        initChecked[`${item.id}_${item._realisasi.id}`] = true;
      });
    }

    if (drawerMode === "uraian") {
      matchedSarana.forEach(s => {
        initChecked[`sarana_${s.id}`] = true;
      });

      matchedRealisasi.forEach(r => {
        initChecked[`realisasi_${r.id}`] = true;
      });
    }

    setCheckedItems(initChecked);
  }, [drawerMode, matchedSarana, matchedRealisasi]);

  useEffect(() => {
    if (!selectedRow) return;

    const realisasiData = data?.[0]?.all_data?.rows || [];
    const ms = masterSarana.find(m => m.kode === selectedRow.kode) || null;

    setSelectedMaster(ms);

    let saranaList = [];
    let realisasiList = [];

    // =========================
    // MODE URAIAN (TIDAK DIUBAH)
    // =========================
    if (drawerMode === "uraian") {
      const groupName = ms?.group ?? "";

      if (groupName.trim() !== "") {
        const groupKodes = masterSarana
          .filter(m => m.group === groupName.trim())
          .map(m => m.kode);

        saranaList = dataSarana.filter(
          item =>
            groupKodes.includes(item.kode) &&
            item.rab === selectedRow.rab
        );
      } else {
        saranaList = [selectedRow];
      }

      const exceptionParts = ms?.exception
        ? ms.exception.split(";").map(e => e.trim().toLowerCase())
        : [];

      const keywords = ms?.keyword
        ? ms.keyword.split(";").map(k => k.trim().toLowerCase()).filter(Boolean)
        : [];

      realisasiList = realisasiData.filter(r => {
        if (r.rab !== no_rab) return false;
        if (r.flag_sarana) return false;

        const ket = (r.keterangan || "").toLowerCase();

        if (exceptionParts.some(exc => ket.includes(exc))) return false;

        return keywords.some(k =>
          new RegExp(k.replace(/%/g, ".*"), "i").test(ket)
        );
      });
    }

    // =========================
    // MODE KODE
    // =========================
    if (drawerMode === "kode") {
      realisasiList = realisasiData.filter(
        r => r.rab === no_rab && !r.flag_sarana && r.plu
      );

      saranaList = dataSarana
        .filter(
          s =>
            s.rab === no_rab &&
            (s.flag_realisasi === null || s.flag_realisasi === "")
        )
        .map(s => {
          const r = realisasiList.find(x => x.plu === s.kode);
          return r ? { ...s, _realisasi: r } : null;
        })
        .filter(Boolean);
    }

    // ===========================
    // console.log("MODE:", drawerMode);
    // console.log("MATCHED SARANA:", saranaList);
    // console.log("MATCHED REALISASI:", realisasiList);
    // ===========================
    setMatchedSarana(saranaList);
    setMatchedRealisasi(realisasiList);
  }, [
    drawerMode,
    selectedRow,
    dataSarana,
    data,
    masterSarana,
    no_rab
  ]);

  // ===========================
  // LOGIKA UNTUK BUTTON MATCHING
  // ===========================
  const hasSelectedSarana = Object.keys(checkedItems).some(
    key => key.startsWith("sarana_") && checkedItems[key]
  );
  const hasSelectedRealisasi = Object.keys(checkedItems).some(
    key => key.startsWith("realisasi_") && checkedItems[key]
  );
  const canShowMatchingButton = hasSelectedSarana && hasSelectedRealisasi;

  useEffect(() => {
    if (canShowMatchingButton && matchingButtonRef.current) {
      matchingButtonRef.current.focus();
    }
  }, [canShowMatchingButton]);

  // ===========================
  // HANDLE MATCHING
  // ===========================
  const handleMatchingUraian = async () => {
    try {
      const selectedKeys = Object.keys(checkedItems).filter(key => checkedItems[key]);

      // SARANA (tidak ada exception di sarana)
      const pilih = selectedKeys
        .filter(key => key.startsWith("sarana_"))
        .map(key => Number(key.replace("sarana_", "")));

      // REALISASI (hanya yang sudah difilter matchedRealisasi)
      const realisasiIdSet = new Set(matchedRealisasi.map(r => r.id));
      const iden = selectedKeys
        .filter(key => key.startsWith("realisasi_"))
        .map(key => Number(key.replace("realisasi_", "")))
        .filter(id => realisasiIdSet.has(id)); // ❌ pastikan hanya ID yang lolos exception

      if (pilih.length === 0 && iden.length === 0) {
        Swal.fire({
          icon: "warning",
          title: "Tidak Ada Data Dipilih",
          text: "Silakan pilih minimal satu data sarana atau realisasi.",
        });
        return;
      }

      const payload = { no_rab, site: identitas.kd_toko, pilih, iden };

      const response = await fetch(`/api/sarana-match`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload),
      });

      const result = await response.json();

      if (result.success) {
        Swal.fire("Berhasil", "Matching data berhasil disimpan.", "success").then(() => {
          handleCloseDrawer();
          fetchTables();
          fetchData();
          setCheckedItems({});

          setSelectedIds([]);
          setSelectedRealisasiIds([]);
          setSearchRealisasiValue('');
          setSearchSaranaValue('');
          setTipeMatching('');
          setSelectedRows('');
        });
      } else {
        Swal.fire("Gagal", result.message || "Terjadi kesalahan saat menyimpan data.", "error");
      }
    } catch (error) {
      Swal.fire("Error", "Tidak dapat terhubung ke server.", "error");
    }
  };

  const handleOpenDrawer = (row, by = "kode") => {
    let selected;

    if (by === "uraian") {
      selected = dataSarana.find(item => item.uraian === row.uraian);
    } else {
      selected = dataSarana.find(item => item.kode === row.kode);
    }

    if (!selected) return;

    setSelectedRow(selected);
    setDrawerMode(by);
    setIsDrawerOpen(true);

    // ❌ JANGAN setCheckedItems di sini
  };

  const handleCloseDrawer = () => {
    setIsDrawerOpen(false);
    setSelectedRow(null);
  };

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
      meta: { align: 'center' }
    },
    { header: 'Kategori', accessorKey: 'kategori' },
    {
      header: 'PLU',
      accessorKey: 'kode',
      cell: ({ row }) => (
        <button
          onClick={() => handleOpenDrawer(row.original, "kode")}
          className="text-blue-600 hover:text-green-500"
        >
          {row.original.kode}
        </button>
      )
    },
    {
      header: 'Uraian',
      accessorKey: 'uraian',
      cell: ({ row }) => (
        <button
          onClick={() => handleOpenDrawer(row.original, "uraian")}
          className="text-magenta-800 hover:text-yellow-500"
        >
          {row.original.uraian}
        </button>
      )
    },
    { header: 'Satuan', accessorKey: 'satuan' },
    { header: 'Qty', accessorKey: 'qty' },
    {
      header: 'DPP',
      accessorKey: 'dpp',
      cell: info => formatRupiah(info.getValue()),
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
  ];

  /* ================= PROSES MEMUAT DATA ================= */
  const [loadingTab, setLoadingTab] = useState({
    sarana: false,
    realisasi: false,
    'sarana-match': false,
    'sarana-fsee': false,
    'sarana-surkas': false,
    'sarana-nonpp': false,
    'sarana-mkt': false,
    'sarana-monitoring': false,
  });

  const fetchTables = async () => {
    if (!no_rab || !identitas?.kd_toko) return;

    setLoadingTab(prev => ({ ...prev, sarana: true }));

    try {
      const encodedNoRab = encodeURIComponent(no_rab);
      const encodedKdToko = encodeURIComponent(identitas.kd_toko);

      const resSarana = await axios.get(
        `/api/sarana_toko?rab=${encodedNoRab}&kd_toko=${encodedKdToko}`
      );

      const {
        data = [],
        master = [],
        realisasi = [],
        groupedRealisasi = [],
        fsee = [],
        surkas = [],
        nonpp = [],
        mkt = [],
        sewa = [],
        pp = [],
      } = resSarana.data;

      setDataSarana(data);
      setMasterSarana(master);
      setDataRealisasi(realisasi);
      setGroupedRealisasi(groupedRealisasi);
      setDataFsee(fsee);
      setDataSurkas(surkas);
      setDataNONPP(nonpp);
      setDataMKT(mkt);
      setDataSewa(sewa);
      setDataPP(pp);
    } catch (err) {
      console.error("Gagal mengambil data sarana", err);
    } finally {
      setLoadingTab(prev => ({ ...prev, sarana: false }));
    }
  };

  useEffect(() => {
    if (no_rab && identitas?.kd_toko) {
      fetchTables();
    }
  }, [no_rab, identitas?.kd_toko, refreshFlag]);
  
  useEffect(() => {
    if (isDrawerOpen && selectedRow) {
      const newChecked = {};

      const realisasiData = data?.[0]?.all_data?.rows || [];

      let saranaList = [];

      const ms = masterSarana.find((m) => m.kode === selectedRow.kode);
      const groupName = ms?.group?.trim() ?? "";

      if (groupName !== "") {
        const groupMaster = masterSarana.filter((m) => m.group === groupName);
        const groupKodes = groupMaster.map((g) => g.kode);

        saranaList = dataSarana.filter(
          (item) =>
            groupKodes.includes(item.kode) && item.rab === selectedRow.rab
        );
      } else {
        saranaList = [selectedRow];
      }

      saranaList.forEach((item) => {
        newChecked[`sarana_${item.id}`] = true; // ✅ otomatis centang semua sarana

        const ms = masterSarana.find((m) => m.kode === item.kode);
        const keywords = ms?.keyword
          ?.split(";")
          .map((k) => k.trim().toLowerCase())
          .filter(Boolean);

        if (keywords && keywords.length > 0) {
          realisasiData.forEach((r) => {
            const ket = (r.keterangan || "").toLowerCase();
            if (
              r.rab === no_rab &&
              !r.flag_sarana &&
              keywords.some((k) =>
                new RegExp(k.replace(/%/g, ".*"), "i").test(ket)
              )
            ) {
              newChecked[`realisasi_${r.id}`] = true;
            }
          });
        }
      });

      setCheckedItems(newChecked);
    } else if (!isDrawerOpen) {
      setCheckedItems({});
    }
  }, [isDrawerOpen, selectedRow, drawerMode, data, dataSarana, masterSarana, no_rab]);
  
  const handleMatchingPLU = () => {
    const matchedData = Object.entries(checkedItems)
      .filter(([, isChecked]) => isChecked)
      .map(([value]) => {
        const [id_sarana, id_realisasi] = value.split('_');
        const kd_toko = identitas.kd_toko;
        return {
          id_sarana,
          id_realisasi,
          no_rab,
          kd_toko
        };
      });

    if (matchedData.length === 0) {
      Swal.fire({
        icon: 'warning',
        title: 'Peringatan',
        text: 'Tidak ada data yang dipilih untuk matching.',
      });
      return;
    }
    
    fetch(`/api/sarana-match-plu`, {
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

            setSelectedIds([]);
            setSelectedRealisasiIds([]);
            setSearchRealisasiValue('');
            setSearchSaranaValue('');
            setTipeMatching('');
            setSelectedRows('');
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

  const handleChangeFsee = async () => {
    if (dropdownFseeValue === '') {
      return Swal.fire('Peringatan', 'Silakan pilih tipe proses terlebih dahulu.', 'warning');
    }

    if (selectedFseeIds.length === 0) {
      return Swal.fire('Peringatan', 'Pilih minimal satu data untuk diproses.', 'warning');
    }

    try {
      const response = await fetch(`/api/fsee-process`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          ids: selectedFseeIds,
          tipe: dropdownFseeValue,
          rab: no_rab
        }),
      });

      let result = {};
      if (response.ok) {
        try {
          result = await response.json();
        } catch (jsonError) {
          // Jika tidak ada body JSON sama sekali (misalnya status 204)
          result = { success: true, message: 'Data berhasil diproses (tanpa respons JSON).' };
        }

        if (result.success) {
          Swal.fire('Berhasil', result.message || 'Data berhasil diproses.', 'success');
          setSelectedFseeIds([]);
          setDropdownFseeValue('');
          fetchData();
          fetchTables();
        } else {
          Swal.fire('Gagal', result.message || 'Terjadi kesalahan saat memproses data.', 'error');
        }
      } else {
        const errorText = await response.text(); // untuk debug error HTML/text
        Swal.fire('Error', `Server error (${response.status}): ${errorText}`, 'error');
      }
    } catch (error) {
      Swal.fire('Error', error.message || 'Tidak dapat terhubung ke server.', 'error');
    }
  };

  const handleChangeSurkas = async () => {
    if (dropdownSurkasValue === '') {
      return Swal.fire('Peringatan', 'Silakan pilih tipe proses terlebih dahulu.', 'warning');
    }

    if (selectedSurkasIds.length === 0) {
      return Swal.fire('Peringatan', 'Pilih minimal satu data untuk diproses.', 'warning');
    }

    try {
      const response = await fetch(`/api/surkas-process`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          ids: selectedSurkasIds,
          tipe: dropdownSurkasValue,
          rab: no_rab
        }),
      });

      let result = {};
      if (response.ok) {
        try {
          result = await response.json();
        } catch (jsonError) {
          result = { success: true, message: 'Data berhasil diproses (tanpa respons JSON).' };
        }

        if (result.success) {
          Swal.fire('Berhasil', result.message || 'Data berhasil diproses.', 'success');
          setSelectedSurkasIds([]);
          setDropdownSurkasValue('');
          fetchData();
          fetchTables();
        } else {
          Swal.fire('Gagal', result.message || 'Terjadi kesalahan saat memproses data.', 'error');
        }
      } else {
        const errorText = await response.text();
        Swal.fire('Error', `Server error (${response.status}): ${errorText}`, 'error');
      }
    } catch (error) {
      Swal.fire('Error', error.message || 'Tidak dapat terhubung ke server.', 'error');
    }
  };

  const handleChangeNonpp = async () => {
    if (dropdownNonppValue === '') {
      return Swal.fire('Peringatan', 'Silakan pilih tipe proses terlebih dahulu.', 'warning');
    }

    if (selectedNonppIds.length === 0) {
      return Swal.fire('Peringatan', 'Pilih minimal satu data untuk diproses.', 'warning');
    }

    try {
      const response = await fetch(`/api/nonpp-process`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          ids: selectedNonppIds,
          tipe: dropdownNonppValue,
          rab: no_rab
        }),
      });

      let result = {};
      if (response.ok) {
        try {
          result = await response.json();
        } catch (jsonError) {
          result = { success: true, message: 'Data berhasil diproses (tanpa respons JSON).' };
        }

        if (result.success) {
          Swal.fire('Berhasil', result.message || 'Data berhasil diproses.', 'success');
          setSelectedNonppIds([]);
          setDropdownNonppValue('');
          fetchData();
          fetchTables();
        } else {
          Swal.fire('Gagal', result.message || 'Terjadi kesalahan saat memproses data.', 'error');
        }
      } else {
        const errorText = await response.text();
        Swal.fire('Error', `Server error (${response.status}): ${errorText}`, 'error');
      }
    } catch (error) {
      Swal.fire('Error', error.message || 'Tidak dapat terhubung ke server.', 'error');
    }
  };

  useEffect(() => {
    if (no_rab) {
      fetchData();
    }
  }, [fetchData, no_rab, refreshFlag]);

  const [isMenuOpen, setMenuOpen] = useState(false);
  const handleOpenMenu = () => setMenuOpen(true);
  const handleCloseMenu = () => setMenuOpen(false);

  const handleRightPanelToggle = (site = null, mode = null) => {
    setMenuOpen(false);
    openLPDPanel(mode, site);
  };

  const resetFilePicker = () => {
    setSelectedFile(null);
    const fileInput = document.getElementById("fileUpload");
    if (fileInput) fileInput.value = null;
  };

  useEffect(() => {

    const closeAllPanels = () => {
      setRightSidebarOpen(false);
      setMenuOpen(false);
      setOpenTopDrawer(false);
      setShowDrawer(false);
    };

    const handleEscape = () => {
      if (rightsidebarOpen || isMenuOpen || openTopDrawer || isDrawerOpen || showDrawer) {
        if (isDrawerOpen) {
          setCheckedItems({});
          setSelectedRow(null);
          setIsDrawerOpen(false);
        }
        if (showDrawer) resetFilePicker();
        closeAllPanels();
      }
    };

    const handleAltShortcut = (code, e) => {
      e.preventDefault();

      const resetUI = () => {
        setShowDrawer(false);
        setRightSidebarOpen(false);
        setMenuOpen(false);
        setOpenTopDrawer(false);
      };

      switch (code) {
        case 'KeyC':
          setRightSidebarOpen(prev => !prev);
          setShowDrawer(false);
          setMenuOpen(false);
          setOpenTopDrawer(false);
          break;

        case 'KeyD':
          navigate('/dat_pr');
          break;

        case 'KeyH':
          e.preventDefault();
          handleAutomatchSarana(
            identitas.no_rab,
            () => {
              fetchData();
              fetchTables();
            }
          );
          break;

        case 'KeyI':
          resetUI();
          if (activeTab === 'sarana') {
            setDrawerMode('import');
            setShowDrawer(true);
          } else if (activeTab === 'sarana-monitoring') {
            setDrawerMode('sarana');
            setShowDrawer(true);
          } else if (activeTab === 'import-sarana') {
            handleImportSelected();
          }

          break;

        case 'KeyK':
          resetUI();
          handleRightPanelToggle(rekapRef.current, 'view');
          break;
        
        case 'KeyL':
          navigate('/lpd-modal');
          break;
          
        case 'KeyM':
          resetUI();
          setMenuOpen(true);
          break;
        
        case 'KeyN':
          e.preventDefault();
          handleUpdateketerangan(identitas.no_rab);
          break;

        case 'KeyP':
          navigate('/lpd-detail');
          break;
        
        case 'KeyS':
          e.preventDefault();
          handleSyncPP(
            identitas.no_rab,
            () => {
              fetchData();
              fetchTables();
            }
          );
          break;

        case 'KeyT':
          e.preventDefault();
          handleCreateLPD(identitas.no_rab);
          break;

        case 'KeyU':
          resetUI();
          setDrawerMode('upload');
          setShowDrawer(true);
          break;

        case 'KeyV':
          handleMatching();
          break;

        case 'KeyA':
          if (checkboxRef.current) checkboxRef.current.focus();
          const tabActions = {
            sarana: handleSelectAll,
            realisasi: handleSelectAllRealisasi,
            'sarana-fsee': handleSelectAllFsee,
            'sarana-surkas': handleSelectAllSurkas,
            'sarana-nonpp': handleSelectAllNonpp,
          };
          tabActions[activeTab]?.();
          break;

        case 'Digit1': setActiveTab('sarana'); break;
        case 'Digit2': setActiveTab('realisasi'); break;
        case 'Digit3': setActiveTab('sarana-match'); break;
        case 'Digit4': setActiveTab('sarana-fsee'); break;
        case 'Digit5': setActiveTab('sarana-surkas'); break;
        case 'Digit6': setActiveTab('sarana-nonpp'); break;
        case 'Digit7': setActiveTab('sarana-mkt'); break;
        case 'Digit8': setActiveTab('sarana-sewa'); break;
        case 'Digit9': setActiveTab('sarana-monitoring'); break;
        case 'Digit0': setActiveTab('import-sarana'); break;

        default:
          break;
      }
    };

    const handleKeyDown = (e) => {
      if (!isCollapsed) {
        return;
      }

      const { key, code, altKey } = e;

      if (key === 'Escape') {
        handleEscape();
        return;
      }

      if (altKey && code) {
        handleAltShortcut(code, e);
        return;
      }

      if (key === 'Enter' && document.activeElement === labelRef.current && fileInputRef.current) {
        e.preventDefault();
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
    activeTab,
    handleMatching,
    handleSelectAll,
    handleSelectAllRealisasi,
    handleSelectAllFsee,
    handleSelectAllSurkas,
    handleSelectAllNonpp,
    handleRightPanelToggle,
    navigate,
    resetFilePicker,
    setActiveTab,
    setDrawerMode,
  ]);
  
  useEffect(() => {
    if (activeTab === 'sarana') {
      saranaSearchRef.current?.focus();
    } else if (activeTab === 'realisasi') {
      realisasiSearchRef.current?.focus();
    } else if (activeTab === 'sarana-fsee') {
      saranafseeSearchRef.current?.focus();
    } else if (activeTab === 'sarana-surkas') {
      saranasurkasSearchRef.current?.focus();
    } else if (activeTab === 'sarana-nonpp') {
      sarananonppSearchRef.current?.focus();
    } else if (activeTab === 'sarana-mkt') {
      saranamktSearchRef.current?.focus();
    } else if (activeTab === 'sarana-sewa') {
      saranasewaSearchRef.current?.focus();
    } else if (activeTab === 'sarana-monitoring') {
      saranappSearchRef.current?.focus();
    } else if (activeTab === 'import-sarana') {
      textAreaRef.current.focus();
    }
  }, [activeTab]);

  const handleFileChange = (e) => {
    const files = Array.from(e.target.files);
    if (files.length > 0) {
      setSelectedFile(files);
    }
  };

  const handleImport = async () => {
    if (!selectedFile || selectedFile.length === 0) {
      Swal.fire("Peringatan", "Silakan pilih file terlebih dahulu!", "warning");
      return;
    }

    try {
      // loop semua file yang dipilih
      for (let i = 0; i < selectedFile.length; i++) {
        const file = selectedFile[i];
        const fileName = file.name;
        const fileExt = fileName.split(".").pop().toLowerCase();

        // baca isi file (hanya csv/txt yang bisa diparse langsung text)
        const text = await file.text();

        const parsed = Papa.parse(text, {
          header: true,
          skipEmptyLines: true,
        });

        if (parsed.errors.length > 0) {
          console.error("Parsing errors:", parsed.errors);
          Swal.fire("Gagal", `Kesalahan parsing file ${fileName}`, "error");
          continue; // skip file ini, lanjut file berikutnya
        }

        const rows = parsed.data;

        const response = await axios.post(
          `/api/import-sarana`,
          { data: rows }
        );

        if (response.data.success) {
          Swal.fire("Berhasil", response.data.message, "success");
        } else {
          Swal.fire("Gagal", response.data.message || `Import gagal untuk ${fileName}`, "error");
        }
      }

      setShowDrawer(false);
      fetchTables();
      resetFilePicker();

    } catch (error) {
      console.error("Import error:", error);
      Swal.fire("Error", error.message || "Terjadi kesalahan saat import", "error");
    }
  };

  const handleImportSarana = async () => {
    if (!selectedFile || selectedFile.length === 0) {
      Swal.fire("Peringatan", "Silakan pilih minimal satu file!", "warning");
      return;
    }

    const formData = new FormData();
    for (let i = 0; i < selectedFile.length; i++) {
      formData.append("files[]", selectedFile[i]);
    }
    formData.append("cabang", cabang);

    try {
      Swal.fire({
        title: "Mengunggah...",
        allowOutsideClick: false,
        showConfirmButton: false,
        didOpen: () => {
          Swal.showLoading();
        }
      });

      const response = await axios.post(
        `/api/import-pp`,
        formData,
        {
          headers: { "Content-Type": "multipart/form-data" },
        }
      );

      Swal.close();

      if (response.data.success) {
        Swal.fire("Berhasil", response.data.message || "Import Sarana berhasil disimpan!", "success");
        setSelectedFile();
        fetchTables();
        fetchData();
        setDrawerMode();
        setShowDrawer(false);
      } else {
        Swal.fire("Gagal", response.data.message || "Terjadi kesalahan", "error");
      }
    } catch (error) {
      console.error(error);
      Swal.fire("Error", "Terjadi kesalahan saat upload file", "error");
    }
  };

  const handleUpload = async () => {
    if (!selectedFile) {
      Swal.fire("Peringatan", "Silakan pilih file PDF terlebih dahulu!", "warning");
      return;
    }

    const formData = new FormData();
    formData.append("file", selectedFile[0]);
    formData.append("rab", no_rab);

    try {
      const response = await fetch(`/api/upload-ba`, {
        method: "POST",
        body: formData,
      });

      const result = await response.json();

      if (response.ok) {
        Swal.fire("Berhasil", result.message, "success").then(() => {
          setShowDrawer(false);
          resetFilePicker();
          fetchTables();
        });
      } else {
        Swal.fire("Gagal", result.message || "Upload gagal", "error");
      }
    } catch (error) {
      console.error("Upload error:", error);
      Swal.fire("Error", error.message || "Terjadi kesalahan saat upload", "error");
    }
  };

  useEffect(() => {
    if (showDrawer && labelRef.current) {
      labelRef.current.focus();
    }
  }, [showDrawer]);

  const handleSelectRealisasiRow = (id) => {
    setSelectedRealisasiIds(prev =>
      prev.includes(id) ? prev.filter(x => x !== id) : [...prev, id]
    );
  };

  const handleSelectFseeRow = (id) => {
    setSelectedFseeIds(prev =>
      prev.includes(id) ? prev.filter(x => x !== id) : [...prev, id]
    );
  };

  const handleSelectNonppRow = (id) => {
    setSelectedNonppIds(prev =>
      prev.includes(id) ? prev.filter(x => x !== id) : [...prev, id]
    );
  };

  useEffect(() => {
    if (openTopDrawer && matchButtonTopRef.current) {
      matchButtonTopRef.current.focus();
    }
  }, [openTopDrawer]);
  
  const realisasiFilteredData = useMemo(() => {
    if (!data?.[0]?.all_data?.rows) return [];
    return data[0].all_data.rows.filter(
      item => item.kd_group === "030008" && item.flag_sarana === ""
    );
  }, [data]);

  const TableSarana = ({ data, columns }) => (
    <div className="flex-1 border border-gray-300 rounded overflow-auto max-h-[70vh]">
      <div className="bg-blue-100 font-bold text-center py-2 sticky top-0 z-20">
        Data Sarana
      </div>
      <table className="min-w-full text-sm text-gray-800 border-collapse">
        <thead className="sticky top-8 z-10 bg-blue-50">
          <tr>
            {columns
              .filter(
                col => col.accessorKey && !['kategori', 'satuan'].includes(col.accessorKey)
              )
              .map(col => (
                <th
                  key={col.accessorKey}
                  className={`p-2 border text-center ${
                    ['harga_satuan', '@price'].includes(col.accessorKey)
                      ? 'text-right'
                      : 'text-left'
                  }`}
                >
                  {col.header}
                </th>
              ))}
          </tr>
        </thead>
        <tbody>
          {data.map(row => (
            <tr key={row.id} className="hover:bg-gray-50">
              {columns
                .filter(
                  col => col.accessorKey && !['kategori', 'satuan'].includes(col.accessorKey)
                )
                .map(col => {
                  const value = row[col.accessorKey];
                  const isCurrency = ['harga_satuan', 'dpp', 'ppn', 'total'].includes(col.accessorKey);
                  return (
                    <td
                      key={col.accessorKey}
                      className={`p-2 border ${typeof value === 'number' ? 'text-right' : 'text-left'}`}
                    >
                      {isCurrency ? formatRupiah(value) : value}
                    </td>
                  );
                })}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );

  // Komponen Tabel Realisasi
  const TableRealisasi = ({ data }) => (
    <div className="flex-1 border border-gray-300 rounded overflow-auto max-h-[70vh]">
      <div className="bg-green-100 font-bold text-center py-2 sticky top-0 z-20">
        Data Realisasi
      </div>
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
          {data.map(row => (
            <tr key={row.id} className="hover:bg-gray-50">
              <td className="p-2 border text-left">{row.keterangan || row.description}</td>
              <td className="p-2 border text-right">{formatRupiah(row.dpp)}</td>
              <td className="p-2 border text-right">{formatRupiah(row.ppn)}</td>
              <td className="p-2 border text-right">{formatRupiah(row.total)}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );

  // Komponen Dropdown
  const MatchingSelect = ({ value, onChange, options }) => (
    <div className="flex items-center mt-4">
      <label className="text-sm font-semibold mr-2">Tipe Matching:</label>
      <select
        value={value}
        onChange={e => onChange(e.target.value)}
        className="border border-gray-300 rounded px-3 py-1 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400"
      >
        <option value="">-- Select --</option>
        {options.map(opt => (
          <option key={opt.value} value={opt.value}>
            {opt.label}
          </option>
        ))}
      </select>
    </div>
  );

  const handleSubmitMatching = async () => {
    const kd_toko = identitas?.kd_toko;
    const idSarana = selectedRows?.sarana?.map(row => row.id) || [];
    const idRealisasi = selectedRows?.realisasi?.map(row => row.id) || [];
    
    if (!kd_toko || !no_rab) {
      return Swal.fire('Gagal', 'Data Kode Toko atau RAB tidak ditemukan.', 'error');
    }

    try {
      const payload = {
        no_rab,
        site: kd_toko,
        pilih: idSarana,
        iden: idRealisasi,
        tipe: tipeMatching,
      };

      const response = await fetch(`/api/sarana-match`, {
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
          setSelectedIds([]);
          setSelectedRealisasiIds([]);
          setSearchRealisasiValue('');
          setSearchSaranaValue('');
          setTipeMatching('');
          setSelectedRows('');
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
        const response = await axios.delete(`/api/realisasi`, {
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
        Swal.fire('Gagal!', error.response?.data?.error || 'Terjadi kesalahan.', 'error');
      }
    }
  };

  const tags = [
    "no_rab",
    "tgl_rab",
    "group_code",
    "rab_detail_code",
    "description",
    "qty",
    "nilai_hpp",
    "nilai_ppn",
    "nilai_estimasi",
    "flag_code",
  ];

  function normalizeWhitespace(s) {
    if (!s) return "";
    return s.replace(/\s+/g, " ").trim();
  }

  function extractBlocks(txt) {
    const firstAngle = txt.indexOf("<");
    const clean = firstAngle >= 0 ? txt.slice(firstAngle) : txt;

    const reBlock = /<RABEstimasi\b[^>]*>([\s\S]*?)<\/RABEstimasi>/gi;
    const blocks = [];
    let m;
    while ((m = reBlock.exec(clean)) !== null) {
      blocks.push(m[0]);
    }
    return blocks;
  }

  function getTagValue(block, tag) {
    const re = new RegExp(`<${tag}[^>]*>([\\s\\S]*?)<\\/${tag}>`, "i");
    const m = block.match(re);
    if (!m) return "";
    return normalizeWhitespace(m[1]);
  }

  const processRabRows = async (rawRows) => {
    if (!rawRows || rawRows.length === 0) {
      Swal.fire("Info", "Data RAB kosong.", "warning");
      setRows([]);
      return;
    }

    try {
      // 🔹 ambil master_sarana
      const res = await fetch(`/api/master-sarana`);
      if (!res.ok) throw new Error("Gagal load master_sarana");

      const data = await res.json();
      const saranaCodes = data.map((d) => d.kode);

      // 🔹 mapping rows
      const parsed = rawRows.map((row, idx) => ({
        __id: idx + 1,
        ...row,
        __checked: saranaCodes.includes(row.rab_detail_code),
      }));

      setRows(parsed);

      const initChecked = {};
      parsed.forEach((r) => {
        initChecked[r.__id] = r.__checked;
      });

      setCheckedRows(initChecked);

    } catch (err) {
      console.error(err);
      Swal.fire("Error", "Gagal memproses data sarana", "error");
    }
  };

  const handleImportSaranaSubmit = async (e) => {
    e?.preventDefault();

    if (!text || !text.trim()) {
      Swal.fire("Peringatan", "Textarea kosong — tempelkan isi XML dulu.", "warning");
      return;
    }

    try {
      const blocks = extractBlocks(text);

      if (!blocks.length) {
        Swal.fire("Info", "Tidak ditemukan tag <RABEstimasi>.", "warning");
        setRows([]);
        return;
      }

      const parsed = blocks.map((blk, idx) => {
        const obj = {};
        for (const t of tags) {
          obj[t] = getTagValue(blk, t);
        }
        return obj;
      });

      await processRabRows(parsed);

    } catch (err) {
      console.error(err);
      Swal.fire("Error", "Terjadi kesalahan parsing XML.", "error");
    }
  };

  const handleSyncRAB = async () => {
    if (!no_rab) {
      Swal.fire("Peringatan", "No RAB tidak tersedia.", "warning");
      return;
    }

    Swal.fire({
      title: "Sync RAB",
      text: "Mengambil data dari server...",
      allowOutsideClick: false,
      didOpen: () => Swal.showLoading(),
    });

    try {
      const res = await fetch(`/api/rab-detail?kode_rab=${encodeURIComponent(no_rab)}`);
      if (!res.ok) throw new Error("Gagal mengambil data RAB");

      const data = await res.json();

      Swal.close();

      await processRabRows(data);

    } catch (err) {
      Swal.fire("Error", err.message, "error");
    }
  };

  const handleReset = () => {
    setText("");
    setRows([]);
  };

  const handleCheckboxsaranaChange = (id) => {
    setCheckedRows((prev) => ({
      ...prev,
      [id]: !prev[id],
    }));
  };

  const totalEstimasi = rows.reduce((sum, r) => {
    if (checkedRows[r.__id] && r.nilai_estimasi) {
      const num = parseFloat(r.nilai_estimasi);
      if (!isNaN(num)) {
        return sum + num;
      }
    }
    return sum;
  }, 0);

  const handleImportSelected = async () => {
    const selectedRows = rows.filter((r) => checkedRows[r.__id]);
    if (selectedRows.length === 0) {
      Swal.fire("Info", "Tidak ada baris yang dicentang untuk diimport.", "info");
      return;
    }

    Swal.fire({
      title: "Import Sarana Toko",
      html: "PLease Wait...",
      allowOutsideClick: false,
      didOpen: () => {
        Swal.showLoading();
      }
    });
    
    try {
      const payload = selectedRows.map((r) => ({
        rab: r.no_rab,
        kode: r.rab_detail_code,
        uraian: r.description,
        qty: r.qty,
        dpp: r.nilai_hpp,
        ppn: r.nilai_ppn,
        total: r.nilai_estimasi,
      }));

      const res = await fetch(`/api/sarana-from-ws`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ data: payload }),
      });

      Swal.close();

      if (!res.ok) throw new Error("Gagal import sarana_toko");
      const result = await res.json();

      Swal.fire({
        icon: 'success',
        title: 'Sukses',
        text: result.message,
      }).then(() => {
        fetchTables();
        fetchData();
        handleReset();
      });
    } catch (err) {
      console.error(err);
      Swal.fire("Error", "Terjadi kesalahan saat import data.", "error");
    }
  };

  const handleSurkasChange = (row, checked) => {
    const newValue = checked ? "Y" : null;

    // Update di state lokal
    setDataPP(prev =>
      prev.map(item =>
        item.id === row.id
          ? { ...item, surkas: newValue }
          : item
      )
    );

    // Kirim perubahan ke backend
    fetch(`/api/update-surkas`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ id: row.id, surkas: newValue }),
    })
      .then(res => res.json())
      .then(res => {
        if (!res.success) {
          Swal.fire("Error", res.message ?? "Gagal update surkas", "error");
        }
      })
      .catch(() =>
        Swal.fire("Error", "Tidak dapat terhubung ke server", "error")
      );
  };

  return (
    <main
      className={`px-2 py-2 z-10 text-white h-[calc(100vh-40px)] overflow-hidden transition-all duration-300 ease-in-out
        ${!isCollapsed
          ? 'max-w-[calc(100vw-288px)]'
          : 'max-w-[calc(100vw-64px)]'
        } w-full`}
    >
      <div className="h-full bg-white/60 rounded-lg shadow-lg text-gray-800 flex flex-col">
        <div className="px-4 pt-4 mb-4">
          <div className="relative px-4 pt-4 mb-4">
            <div className="flex justify-center items-center relative">
              <h2 className="text-xl font-bold">MONITORING SARANA TOKO</h2>
              <button
                onClick={handleOpenMenu}
                className="absolute right-0 rounded bg-gray-700 hover:bg-gray-800 text-white px-2 py-1"
                title="Buka Menu"
              >
                <HiHome className="w-5 h-5" />
              </button>
            </div>

            <div className="text-center mt-2">
              <h4 className="font-bold">
                {identitas.jns_toko} - {identitas.nama_toko} - {identitas.kd_toko}
              </h4>
              <h4 className="font-bold">Tgl Waralaba : {formatDate(identitas.tgl_wrlb)}</h4>
              <h4 className="font-bold">{no_rab}</h4>
            </div>
          </div>
        </div>

        <Menu_LPD
          isOpen={isMenuOpen}
          onClose={handleCloseMenu}
          onOpenDrawer={(mode) => {
            setDrawerMode(mode);
            setShowDrawer(true);
          }}
          isDrawerOpen={(mode) => {
            setDrawerMode(mode);
            setIsDrawerOpen(true);
          }}
          berkas={berkas}
          identitas={identitas}
          data={rekap}
          onToggleRightPanel={handleRightPanelToggle}
          fetchData={fetchData}
          fetchTables={fetchTables}
          
        />

        <RightSidebar isOpen={rightsidebarOpen} onClose={closeRightSidebar} />
        
        <div className="flex-1 overflow-y-auto px-4 pb-4 space-y-4">
          {/* Tabs */}
          <div className="flex border-b mb-4">
            <button
              className={`px-4 py-2 font-semibold ${activeTab === 'sarana' ? 'border-b-2 border-blue-600 text-blue-600' : 'text-gray-600'}`}
              onClick={() => setActiveTab('sarana')}
            >
              1. Sarana Toko
            </button>
            <button
              className={`px-4 py-2 font-semibold ${activeTab === 'realisasi' ? 'border-b-2 border-blue-600 text-blue-600' : 'text-gray-600'}`}
              onClick={() => setActiveTab('realisasi')}
            >
              2. Realisasi
            </button>
            <button
              className={`px-4 py-2 font-semibold ${activeTab === 'sarana-match' ? 'border-b-2 border-blue-600 text-blue-600' : 'text-gray-600'}`}
              onClick={() => setActiveTab('sarana-match')}
            >
              3. Sarana Toko VS Realisasi
            </button>
            <button
              className={`px-4 py-2 font-semibold ${activeTab === 'sarana-fsee' ? 'border-b-2 border-blue-600 text-blue-600' : 'text-gray-600'}`}
              onClick={() => setActiveTab('sarana-fsee')}
            >
              4. Sarana By Fsee
            </button>
            <button
              className={`px-4 py-2 font-semibold ${activeTab === 'sarana-surkas' ? 'border-b-2 border-blue-600 text-blue-600' : 'text-gray-600'}`}
              onClick={() => setActiveTab('sarana-surkas')}
            >
              5. Sarana Pot Surkas
            </button>
            <button
              className={`px-4 py-2 font-semibold ${activeTab === 'sarana-nonpp' ? 'border-b-2 border-blue-600 text-blue-600' : 'text-gray-600'}`}
              onClick={() => setActiveTab('sarana-nonpp')}
            >
              6. Sarana Non PP
            </button>
            <button
              className={`px-4 py-2 font-semibold ${activeTab === 'sarana-mkt' ? 'border-b-2 border-blue-600 text-blue-600' : 'text-gray-600'}`}
              onClick={() => setActiveTab('sarana-mkt')}
            >
              7. Sarana Tidak Realisasi (BA)
            </button>
            <button
              className={`px-4 py-2 font-semibold ${activeTab === 'sarana-sewa' ? 'border-b-2 border-blue-600 text-blue-600' : 'text-gray-600'}`}
              onClick={() => setActiveTab('sarana-sewa')}
            >
              8. Sarana Sewa
            </button>
            <button
              className={`px-4 py-2 font-semibold ${activeTab === 'sarana-monitoring' ? 'border-b-2 border-blue-600 text-blue-600' : 'text-gray-600'}`}
              onClick={() => setActiveTab('sarana-monitoring')}
            >
              9. Monitoring Sarana
            </button>
            <button
              className={`px-4 py-2 font-semibold ${activeTab === 'import-sarana' ? 'border-b-2 border-blue-600 text-blue-600' : 'text-gray-600'}`}
              onClick={() => setActiveTab('import-sarana')}
            >
              0. Sarana From WS
            </button>
          </div>

          {/* Tab Content */}
          {activeTab === 'sarana' && (
            loadingTab.sarana ? (
              <TableLoading text="Memuat Sarana Toko..." />
            ) : (
              <ReusableTable
                  columns={columns}
                  data={dataSarana}
                  globalFilter={searchSaranaValue}
                  setGlobalFilter={setSearchSaranaValue}
                  tableClassName="min-w-full border-collapse"
                  theadClassName="bg-gray-100 sticky top-0 z-10"
                  thClassName="p-2 border-b text-sm font-semibold text-left bg-white"
                  tdClassName="p-2 border-b text-sm text-gray-700"
                  searchInputRef={saranaSearchRef}
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
                  { header: 'Inv Num', accessorKey: 'inv_num' },
                  { header: 'Description', accessorKey: 'keterangan' },
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

          {activeTab === 'sarana-match' && (
            <div className="mt-4 border border-gray-300 rounded-md max-h-[520px] overflow-x-auto">
              <div className="w-full">
                <table className="table-auto text-sm border-collapse w-full">
                  <thead className="bg-gray-100 sticky top-0 z-10">
                    <tr>
                      <th className="p-2 border whitespace-nowrap">Kategori</th>
                      <th className="p-2 border whitespace-nowrap">PLU</th>
                      <th className="p-2 border whitespace-nowrap">Uraian</th>
                      <th className="p-2 border whitespace-nowrap">Total</th>
                      <th className="p-2 border whitespace-nowrap">Keterangan</th>
                      <th className="p-2 border whitespace-nowrap">Amount</th>
                      <th className="p-2 border whitespace-nowrap">Inv Num</th>
                      <th className="p-2 border whitespace-nowrap text-center w-10">
                        <HiCog className="w-4 h-4 inline" />
                      </th>
                    </tr>
                  </thead>
                  <tbody>
                    {groupedRealisasi && Object.entries(groupedRealisasi).map(([flag, item]) => (
                      <tr key={flag} className="hover:bg-gray-50 align-top">
                        <td className="p-2 border whitespace-nowrap">
                          {item.items?.map((itm, idx) => (
                            <div key={idx}>{itm.kategori}</div>
                          ))}
                        </td>
                        <td className="p-2 border">
                          {item.items?.map((itm, idx) => (
                            <div key={idx}>{itm.kode}</div>
                          ))}
                        </td>
                        <td className="p-2 border whitespace-nowrap">
                          {item.items?.map((itm, idx) => (
                            <div key={idx}>{itm.uraian}</div>
                          ))}
                        </td>
                        <td className="p-2 border text-right">
                          {item.items?.map((itm, idx) => (
                            <div key={idx}>{formatRupiah(itm.total)}</div>
                          ))}
                        </td>
                        <td className="p-2 border whitespace-nowrap">
                          {item.realisasi_items?.map((itm, idx) => (
                            <div key={idx}>{itm.keterangan}</div>
                          ))}
                        </td>
                        <td className="p-2 border text-right">
                          {item.realisasi_items?.map((itm, idx) => (
                            <div key={idx}>{formatRupiah(itm.total)}</div>
                          ))}
                        </td>
                        <td className="p-2 border whitespace-nowrap">
                          {item.realisasi_items?.map((itm, idx) => (
                            <div key={idx}>{itm.inv_num}</div>
                          ))}
                        </td>
                        <td className="p-2 border text-center">
                          <button
                            onClick={() => handleDelete(flag, no_rab, identitas.kd_toko)}
                            className="text-red-600 hover:text-red-800"
                            title="Hapus"
                          >
                            <HiTrash className="w-5 h-5" />
                          </button>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          )}

          {activeTab === 'sarana-fsee' && (
            loadingTab['sarana-fsee'] ? (
              <TableLoading text="Memuat Data Sarana By Fsee..." />
            ) : (
              <ReusableTable
                columns={[
                  {
                    header: '',
                    id: 'select',
                    cell: ({ row }) => (
                      <input
                        type="checkbox"
                        checked={selectedFseeIds.includes(row.original.id)}
                        onChange={() => handleSelectFseeRow(row.original.id)}
                        className="form-checkbox"
                      />
                    ),
                    size: 20,
                    meta: { align: 'center' },
                  },
                  { header: 'Kategori', accessorKey: 'kategori' },
                  { header: 'PLU', accessorKey: 'kode' },
                  { header: 'Uraian', accessorKey: 'uraian' },
                  { header: 'Satuan', accessorKey: 'satuan' },
                  { header: 'Qty', accessorKey: 'qty' },
                  {
                    header: '@Price',
                    accessorKey: 'harga_satuan',
                    cell: info => formatRupiah(info.getValue()),
                    meta: { className: "text-right" },
                  },
                  {
                    header: 'DPP',
                    accessorKey: 'dpp',
                    cell: info => formatRupiah(info.getValue()),
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
                data={dataFsee}
                searchInputRef={saranafseeSearchRef}
                globalFilter={searchFseeValue}
                setGlobalFilter={setSearchFseeValue}
                tableClassName="min-w-full border-collapse"
                theadClassName="bg-gray-100 sticky top-0 z-10"
                thClassName="p-2 border-b text-sm font-semibold text-left bg-white"
                tdClassName="p-2 border-b text-sm text-gray-700"
                onVisibleDataChange={(rows) => setvisibleRowsFsee(rows)}
                rightElement={
                  <div className="flex items-center gap-4">
                    {/* Checkbox Select All */}
                    <label className="flex items-center gap-2 text-sm">
                      <input
                        type="checkbox"
                        ref={checkboxRef}
                        checked={isAllFseeSelected}
                        onChange={handleSelectAllFsee}
                        className="form-checkbox"
                      />
                      Select All
                    </label>

                    <select
                      value={dropdownFseeValue}
                      onChange={e => setDropdownFseeValue(e.target.value)}
                      className="border border-gray-300 rounded px-2 py-1 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400"
                    >
                      <option value="">-- Pilih Tipe --</option>
                      <option value="UNREALIZED">UNREALIZED</option>
                      <option value="NONPP">NON PP</option>
                      <option value="SEWA">SEWA</option>
                      <option value="SURKAS">SURKAS</option>
                    </select>

                    <button
                      onClick={handleChangeFsee}
                      disabled={selectedFseeIds.length === 0}
                      className={`flex items-center gap-2 px-4 py-2 rounded text-white text-sm 
                        ${selectedFseeIds.length === 0
                          ? 'bg-gray-400 cursor-not-allowed'
                          : 'bg-green-500 hover:bg-green-600'
                        }`}
                    >
                      <HiPaperAirplane className="w-4 h-4" />
                      Proses
                    </button>
                  </div>
                }
              />
            )
          )}

          {activeTab === 'sarana-surkas' && (
            loadingTab['sarana-surkas'] ? (
              <TableLoading text="Memuat Data Sarana Pot Surkas..." />
            ) : (
              <ReusableTable
                columns={[
                  {
                    header: '',
                    id: 'select',
                    cell: ({ row }) => (
                      <input
                        type="checkbox"
                        checked={selectedSurkasIds.includes(row.original.id)}
                        onChange={() => handleSelectSurkasRow(row.original.id)}
                        className="form-checkbox"
                      />
                    ),
                    size: 20,
                    meta: { align: 'center' },
                  },
                  { header: 'Kategori', accessorKey: 'kategori' },
                  { header: 'PLU', accessorKey: 'kode' },
                  { header: 'Uraian', accessorKey: 'uraian' },
                  { header: 'Satuan', accessorKey: 'satuan' },
                  { header: 'Qty', accessorKey: 'qty' },
                  {
                    header: '@Price',
                    accessorKey: 'harga_satuan',
                    cell: info => formatRupiah(info.getValue()),
                    meta: { className: "text-right" },
                  },
                  {
                    header: 'DPP',
                    accessorKey: 'dpp',
                    cell: info => formatRupiah(info.getValue()),
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
                data={dataSurkas}
                searchInputRef={saranasurkasSearchRef}
                globalFilter={searchSurkasValue}
                setGlobalFilter={setSearchSurkasValue}
                tableClassName="min-w-full border-collapse"
                theadClassName="bg-gray-100 sticky top-0 z-10"
                thClassName="p-2 border-b text-sm font-semibold text-left bg-white"
                tdClassName="p-2 border-b text-sm text-gray-700"
                onVisibleDataChange={(rows) => setvisibleRowsSurkas(rows)}
                rightElement={
                  <div className="flex items-center gap-4">
                    {/* Checkbox Select All */}
                    <label className="flex items-center gap-2 text-sm">
                      <input
                        type="checkbox"
                        ref={checkboxRef}
                        checked={isAllSurkasSelected}
                        onChange={handleSelectAllSurkas}
                        className="form-checkbox"
                      />
                      Select All
                    </label>

                    <select
                      value={dropdownSurkasValue}
                      onChange={e => setDropdownSurkasValue(e.target.value)}
                      className="border border-gray-300 rounded px-2 py-1 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400"
                    >
                      <option value="">-- Pilih Tipe --</option>
                      <option value="UNREALIZED">UNREALIZED</option>
                      <option value="NONPP">NON PP</option>
                      <option value="SEWA">SEWA</option>
                      <option value="FSEE">BY FRCSEE</option>
                    </select>

                    <button
                      onClick={handleChangeSurkas}
                      disabled={selectedSurkasIds.length === 0}
                      className={`flex items-center gap-2 px-4 py-2 rounded text-white text-sm 
                        ${selectedSurkasIds.length === 0
                          ? 'bg-gray-400 cursor-not-allowed'
                          : 'bg-green-500 hover:bg-green-600'
                        }`}
                    >
                      <HiPaperAirplane className="w-4 h-4" />
                      Proses
                    </button>
                  </div>
                }
              />
            )
          )}

          {activeTab === 'sarana-nonpp' && (
            loadingTab['sarana-nonpp'] ? (
              <TableLoading text="Memuat Data Sarana Non PP..." />
            ) : (
              <ReusableTable
                columns={[
                  {
                    id: 'select',
                    header: '',
                    cell: ({ row }) => (
                      <input
                        type="checkbox"
                        checked={selectedNonppIds.includes(row.original.id)}
                        onChange={() => handleSelectNonppRow(row.original.id)}
                        className="form-checkbox"
                      />
                    ),
                    meta: { align: 'center' },
                  },
                  { header: 'Kategori', accessorKey: 'kategori' },
                  { header: 'PLU', accessorKey: 'kode' },
                  { header: 'Uraian', accessorKey: 'uraian' },
                  { header: 'Satuan', accessorKey: 'satuan' },
                  { header: 'Qty', accessorKey: 'qty' },
                  {
                    header: '@Price',
                    accessorKey: 'harga_satuan',
                    cell: info => formatRupiah(info.getValue()),
                    meta: { className: "text-right" },
                  },
                  {
                    header: 'DPP',
                    accessorKey: 'dpp',
                    cell: info => formatRupiah(info.getValue()),
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
                data={dataNONPP}
                globalFilter={searchNonPPValue}
                setGlobalFilter={setSearchNonPPValue}
                tableClassName="min-w-full border-collapse"
                theadClassName="bg-gray-100 sticky top-0 z-10"
                thClassName="p-2 border-b text-sm font-semibold text-left bg-white"
                tdClassName="p-2 border-b text-sm text-gray-700"
                searchInputRef={sarananonppSearchRef}
                onVisibleDataChange={(rows) => setvisibleRowsNonpp(rows)}
                rightElement={
                  <div className="flex items-center gap-4">
                    <label className="flex items-center gap-2 text-sm">
                        <input
                          type="checkbox"
                          ref={checkboxNonppRef} 
                          checked={isAllNonppSelected}
                          onChange={handleSelectAllNonpp}
                          className="form-checkbox"
                        />
                        Select All
                    </label>

                    <select
                      value={dropdownNonppValue}
                      onChange={e => setDropdownNonppValue(e.target.value)}
                      className="border border-gray-300 rounded px-2 py-1 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400"
                    >
                      <option value="">-- Pilih Tipe --</option>
                      <option value="UNREALIZED">UNREALIZED</option>
                      <option value="FSEE">BY FRCSEE</option>
                      <option value="SEWA">SEWA</option>
                      <option value="SURKAS">SURKAS</option>
                    </select>

                    <button
                      onClick={handleChangeNonpp}
                      disabled={selectedNonppIds.length === 0}
                      className={`flex items-center gap-2 px-4 py-2 rounded text-white text-sm 
                        ${selectedNonppIds.length === 0
                          ? 'bg-gray-400 cursor-not-allowed'
                          : 'bg-green-500 hover:bg-green-600'
                        }`}
                    >
                      <HiPaperAirplane className="w-4 h-4" />
                      Proses
                    </button>
                  </div>
                }
              />
            )
          )}

          {activeTab === 'sarana-mkt' && (
            loadingTab['sarana-mkt'] ? (
              <TableLoading text="Memuat Data Sarana Tidak Realisasi..." />
            ) : (
              <ReusableTable
                columns={[
                  { header: 'Kategori', accessorKey: 'kategori' },
                  { header: 'PLU', accessorKey: 'kode' },
                  { header: 'Uraian', accessorKey: 'uraian' },
                  { header: 'Satuan', accessorKey: 'satuan' },
                  { header: 'Qty', accessorKey: 'qty' },
                  {
                    header: '@Price',
                    accessorKey: 'harga_satuan',
                    cell: info => formatRupiah(info.getValue()),
                    meta: { className: "text-right" },
                  },
                  {
                    header: 'DPP',
                    accessorKey: 'dpp',
                    cell: info => formatRupiah(info.getValue()),
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
                data={dataMKT}
                searchInputRef={saranamktSearchRef}
                globalFilter={searchMktValue}
                setGlobalFilter={setSearchMktValue}
                tableClassName="min-w-full border-collapse"
                theadClassName="bg-gray-100 sticky top-0 z-10"
                thClassName="p-2 border-b text-sm font-semibold text-left bg-white"
                tdClassName="p-2 border-b text-sm text-gray-700"
              />
            )
          )}

          {activeTab === 'sarana-sewa' && (
            loadingTab['sarana-sewa'] ? (
              <TableLoading text="Memuat Data Sarana Sewa..." />
            ) : (
              <ReusableTable
                columns={[
                  { header: 'Kategori', accessorKey: 'kategori' },
                  { header: 'PLU', accessorKey: 'kode' },
                  { header: 'Uraian', accessorKey: 'uraian' },
                  { header: 'Satuan', accessorKey: 'satuan' },
                  { header: 'Qty', accessorKey: 'qty' },
                  {
                    header: '@Price',
                    accessorKey: 'harga_satuan',
                    cell: info => formatRupiah(info.getValue()),
                    meta: { className: "text-right" },
                  },
                  {
                    header: 'DPP',
                    accessorKey: 'dpp',
                    cell: info => formatRupiah(info.getValue()),
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
                data={dataSewa}
                searchInputRef={saranasewaSearchRef}
                globalFilter={searchSewaValue}
                setGlobalFilter={setSearchSewaValue}
                tableClassName="min-w-full border-collapse"
                theadClassName="bg-gray-100 sticky top-0 z-10"
                thClassName="p-2 border-b text-sm font-semibold text-left bg-white"
                tdClassName="p-2 border-b text-sm text-gray-700"
              />
            )
          )}

          {activeTab === 'sarana-monitoring' && (
            loadingTab['sarana-monitoring'] ? (
              <TableLoading text="Memuat Data Monitoring Sarana Toko..." />
            ) : (
              <ReusableTable
                columns={[
                  { header: 'RAB', accessorKey: 'rab' },
                  { header: 'PLU', accessorKey: 'plu' },
                  { header: 'Nama Barang', accessorKey: 'barang' },
                  { header: 'No PP', accessorKey: 'pp' },
                  { header: 'No SP', accessorKey: 'sp' },
                  { header: 'Tgl Exp SP', accessorKey: 'exp_sp', cell: info => formatDate(info.getValue()) },
                  { header: 'SP Awal', accessorKey: 'sp_awal' },
                  { header: 'No BTB', accessorKey: 'btb' },
                  { header: 'Tgl BTB', accessorKey: 'tgl_btb', cell: info => info.getValue() === '0000-00-00' ? '' : formatDate(info.getValue()) },
                  {
                    header: 'Invoice',
                    accessorKey: 'invoice_matched',
                    cell: info => info.getValue() ? <FaCheckCircle className="text-green-500" /> : null
                  },
                  {
                    header: 'Surkas',
                    accessorKey: 'surkas',
                    cell: ({ row }) => {
                      const value = row.original.surkas === "Y";
                      return (
                        <input
                          type="checkbox"
                          checked={value}
                          onChange={(e) => handleSurkasChange(row.original, e.target.checked)}
                          className="w-4 h-4 cursor-pointer"
                        />
                      );
                    },
                    meta: { className: "text-center" },
                  }
                ]}
                data={dataPP}
                searchInputRef={saranappSearchRef}
                globalFilter={searchPPValue}
                setGlobalFilter={setSearchPPValue}
                tableClassName="min-w-full border-collapse"
                theadClassName="bg-gray-100 sticky top-0 z-10"
                thClassName="p-2 border-b text-sm font-semibold text-left bg-white"
                tdClassName="p-2 border-b text-sm text-gray-700"
              />
            )
          )}

          {activeTab === "import-sarana" && (
            <div className="p-4">
              <form onSubmit={handleImportSaranaSubmit} className="space-y-4">
                <div>
                  <textarea
                    id="note"
                    name="note"
                    rows="15"
                    ref={textAreaRef}
                    className="w-full border rounded p-2 focus:ring-2 focus:ring-blue-500 focus:outline-none"
                    value={text}
                    onChange={(e) => setText(e.target.value)}
                  ></textarea>
                </div>

                <div className="flex gap-2">
                  <button
                    type="submit"
                    className="flex items-center gap-2 bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded shadow"
                  >
                    <FaSave className="w-5 h-5" />
                    Submit
                  </button>

                  <button
                    type="button"
                    onClick={handleSyncRAB}
                    className="flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded shadow"
                  >
                    🔄 Sync
                  </button>

                  <button
                    onClick={handleReset}
                    type="button"
                    className="flex items-center gap-2 bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded shadow"
                  >
                    <FaUndo className="w-5 h-5" />
                    Reset
                  </button>
                </div>
              </form>

              {rows.length > 0 && (
                <div className="mt-4 overflow-auto">
                  <table className="min-w-full border-collapse border border-gray-300 text-sm">
                    <thead>
                      <tr className="bg-gray-100">
                        <th className="border px-2 py-1">#</th>
                        <th className="border px-2 py-1 text-center">✔</th>
                        {tags.map((t) => (
                          <th key={t} className="border px-2 py-1 text-left">
                            {t}
                          </th>
                        ))}
                      </tr>
                    </thead>
                    <tbody>
                      {rows.map((r) => (
                        <tr key={r.__id} className="odd:bg-white even:bg-gray-50">
                          <td className="border px-2 py-1 text-center">{r.__id}</td>
                          <td className="border px-2 py-1 text-center">
                            <input
                              type="checkbox"
                              checked={checkedRows[r.__id] || false}
                              onChange={() => handleCheckboxsaranaChange(r.__id)}
                            />
                          </td>
                          {tags.map((t) => {
                            let val = r[t] || "-";

                            if (["nilai_hpp", "nilai_ppn", "nilai_estimasi"].includes(t) && val !== "-") {
                              const num = parseFloat(val);
                              if (!isNaN(num)) {
                                val = num.toLocaleString("id-ID", { maximumFractionDigits: 0 });
                              }
                            }

                            const cellClass =
                              ["nilai_hpp", "nilai_ppn", "nilai_estimasi"].includes(t)
                                ? "border px-2 py-1 break-words text-right"
                                : "border px-2 py-1 break-words";

                            return (
                              <td key={t} className={cellClass}>
                                {val}
                              </td>
                            );
                          })}
                        </tr>
                      ))}
                    </tbody>
                  </table>

                  <div className="mt-2 text-right font-semibold">
                    Jumlah Sarana Elektronik dan Non Elektronik :{" "}
                    {totalEstimasi.toLocaleString("id-ID", { maximumFractionDigits: 0 })}
                  </div>

                  {/* 🔹 Tombol Import */}
                  <div className="mt-4 flex justify-end">
                    <button
                      onClick={handleImportSelected}
                      type="button"
                      className="flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded shadow"
                    >
                      <FaSave className="w-5 h-5" />
                      Import Sarana
                    </button>
                  </div>
                </div>
              )}
            </div>
          )}
        </div>

        {showDrawer && (
          <BottomDrawer
            isOpen={showDrawer}
            onClose={() => setShowDrawer(false)}
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
                    accept={
                      drawerMode === "import" || drawerMode === "sarana"
                        ? ".csv, .txt, .xls, .pdf"
                        : ".pdf"
                    }
                    onChange={handleFileChange}
                    multiple={drawerMode === 'sarana'}
                    className="hidden"
                  />
                </label>
              </div>

              {/* === Footer === */}
              <div className="fixed bottom-0 left-[-6%] right-0 border-t flex items-center justify-between z-10">
                <div className="trapezium-box text-white text-3xl shadow-md mt-[-8px] flex items-center justify-center h-[50px] w-[280px] bg-yellow-400">
                  {drawerMode === "import" && "Import Data Sarana"}
                  {drawerMode === "sarana" && "Import Register BTB"}
                  {drawerMode === "upload" && "Upload BA Sarana Tidak Realisasi"}
                </div>

                <div className="flex gap-2 px-2">
                  <button
                    type="button"
                    onClick={
                      drawerMode === "upload"
                        ? handleUpload
                        : drawerMode === "sarana"
                        ? handleImportSarana
                        : handleImport
                    }
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
        
        <CenterDrawer
          isOpen={isDrawerOpen}
          onClose={handleCloseDrawer}
          widthClass="max-w-full"
        >
          <div>
            {!selectedRow ? (
              <div className="p-4 text-center text-gray-500 italic">
                Tidak ada data yang dipilih.
              </div>
            ) : (
              <>
                {drawerMode === "kode" ? (
                  <div className="overflow-x-auto">
                    <table className="min-w-full border border-gray-300 text-sm">
                      <thead className="bg-gray-100 text-center">
                        <tr>
                          <th colSpan={5} className="bg-yellow-400 px-3 py-2 border">
                            Data Sarana
                          </th>
                          <th colSpan={5} className="bg-green-600 px-3 py-2 border text-white">
                            Data Realisasi
                          </th>
                          <th
                            rowSpan={2}
                            className="bg-blue-600 px-3 py-2 border text-white"
                          >
                            Match
                          </th>
                        </tr>
                        <tr>
                          <th className="border px-3 py-2">No</th>
                          <th className="border px-3 py-2">PLU</th>
                          <th className="border px-3 py-2">Uraian</th>
                          <th className="border px-3 py-2">Qty</th>
                          <th className="border px-3 py-2">Total</th>
                          <th className="border px-3 py-2">Deskripsi</th>
                          <th className="border px-3 py-2">DPP</th>
                          <th className="border px-3 py-2">PPN</th>
                          <th className="border px-3 py-2">Total</th>
                          <th className="border px-3 py-2">Invoice Num</th>
                        </tr>
                      </thead>

                      <tbody>
                        {matchedSarana.length === 0 ? (
                          <tr>
                            <td
                              colSpan={11}
                              className="border text-center py-2 text-gray-500 italic"
                            >
                              Tidak ada data yang match
                            </td>
                          </tr>
                        ) : (
                          matchedSarana.map((item, idx) => {
                            const realisasi =
                              drawerMode === "kode"
                                ? item._realisasi
                                : matchedRealisasi.find(
                                    r => r.plu === item.kode && r.rab === item.rab
                                  ) || {};

                            const checkboxKey =
                              drawerMode === "kode"
                                ? `${item.id}_${item._realisasi.id}`
                                : `sarana_${item.id}`;

                            const isChecked = !!checkedItems[checkboxKey];
                            
                            console.log("ROW:", {
                              itemId: item.id,
                              realisasiId: item._realisasi?.id,
                              checkboxKey,
                              checkedItems,
                              isChecked,
                            });

                            return (
                              <tr key={`${item.id}_${idx}`}>
                                <td className="border px-2 py-1 text-center">{idx + 1}</td>
                                <td className="border px-2 py-1">{item.kode}</td>
                                <td className="border px-2 py-1">{item.uraian}</td>
                                <td className="border px-2 py-1 text-right">{item.qty}</td>
                                <td className="border px-2 py-1 text-right">
                                  {formatRupiah(item.total)}
                                </td>
                                <td className="border px-2 py-1">
                                  {realisasi.keterangan || "-"}
                                </td>
                                <td className="border px-2 py-1 text-right">
                                  {formatRupiah(realisasi.dpp)}
                                </td>
                                <td className="border px-2 py-1 text-right">
                                  {formatRupiah(realisasi.ppn)}
                                </td>
                                <td className="border px-2 py-1 text-right">
                                  {formatRupiah(realisasi.total)}
                                </td>
                                <td className="border px-2 py-1">
                                  {realisasi.inv_num || "-"}
                                </td>
                                <td className="border px-2 py-1 text-center">
                                  <input
                                    type="checkbox"
                                    checked={isChecked}
                                    onChange={() =>
                                      setCheckedItems(prev => ({
                                        ...prev,
                                        [checkboxKey]: !prev[checkboxKey],
                                      }))
                                    }
                                  />
                                </td>
                              </tr>
                            );
                          })
                        )}
                      </tbody>

                    </table>

                    <div className="mt-4 text-center">
                      <button
                        ref={matchButtonCenterRef}
                        onClick={handleMatchingPLU}
                        className="bg-green-600 hover:bg-green-700 text-white font-semibold py-2 px-6 rounded"
                      >
                        Matching
                      </button>
                    </div>
                  </div>
                ) : (
                  <div className="overflow-x-auto space-y-6 p-2">
                    {/* DATA SARANA */}
                    <div>
                      <h3 className="bg-yellow-400 text-center font-semibold py-2 border">Data Sarana</h3>
                      {matchedSarana.length === 0 ? (
                        <div className="p-4 text-center text-gray-500 italic">
                          Tidak ada data sarana untuk group ini.
                        </div>
                      ) : (
                        <table className="min-w-full border border-gray-300 text-sm">
                          <thead className="bg-gray-100 text-center">
                            <tr>
                              <th className="border px-3 py-2">Pilih</th>
                              <th className="border px-3 py-2">PLU</th>
                              <th className="border px-3 py-2">Uraian</th>
                              <th className="border px-3 py-2">Qty</th>
                              <th className="border px-3 py-2">Total</th>
                              <th className="border px-3 py-2">Group</th>
                            </tr>
                          </thead>
                          <tbody>
                            {matchedSarana.map((item, idx) => (
                              <tr key={idx}>
                                <td className="border text-center">
                                  <input
                                    type="checkbox"
                                    checked={checkedItems[`sarana_${item.id}`] || false}
                                    onChange={() =>
                                      setCheckedItems(prev => ({
                                        ...prev,
                                        [`sarana_${item.id}`]: !prev[`sarana_${item.id}`],
                                      }))
                                    }
                                  />
                                </td>
                                <td className="border px-2 py-1">{item.kode}</td>
                                <td className="border px-2 py-1">{item.uraian}</td>
                                <td className="border px-2 py-1 text-right">{item.qty}</td>
                                <td className="border px-2 py-1 text-right">{formatRupiah(item.total)}</td>
                                <td className="border px-2 py-1 text-center">{selectedMaster?.group ?? "-"}</td>
                              </tr>
                            ))}
                          </tbody>
                        </table>
                      )}
                    </div>

                    {/* DATA REALISASI */}
                    <div>
                      <h3 className="bg-green-600 text-white text-center font-semibold py-2 border">Data Realisasi</h3>
                      {matchedRealisasi.length === 0 ? (
                        <div className="p-4 text-center text-gray-500 italic">Tidak ada data realisasi cocok.</div>
                      ) : (
                        <table className="min-w-full border border-gray-300 text-sm">
                          <thead className="bg-gray-100 text-center">
                            <tr>
                              <th className="border px-3 py-2">Pilih</th>
                              <th className="border px-3 py-2">Deskripsi</th>
                              <th className="border px-3 py-2">DPP</th>
                              <th className="border px-3 py-2">PPN</th>
                              <th className="border px-3 py-2">Total</th>
                              <th className="border px-3 py-2">Invoice Num</th>
                            </tr>
                          </thead>
                          <tbody>
                            {matchedRealisasi.map((r, idx) => {
                              const key = `realisasi_${r.id}`;
                              return (
                                <tr key={idx}>
                                  <td className="border text-center">
                                    <input
                                      type="checkbox"
                                      checked={checkedItems[key] || false}
                                      onChange={() =>
                                        setCheckedItems(prev => ({
                                          ...prev,
                                          [key]: !prev[key],
                                        }))
                                      }
                                    />
                                  </td>
                                  <td className="border px-2 py-1">{r.keterangan}</td>
                                  <td className="border px-2 py-1 text-right">{formatRupiah(r.dpp)}</td>
                                  <td className="border px-2 py-1 text-right">{formatRupiah(r.ppn)}</td>
                                  <td className="border px-2 py-1 text-right">{formatRupiah(r.total)}</td>
                                  <td className="border px-2 py-1">{r.inv_num ?? "-"}</td>
                                </tr>
                              );
                            })}
                          </tbody>
                        </table>
                      )}
                    </div>

                    {/* BUTTON MATCHING */}
                    {canShowMatchingButton && (
                      <div className="mt-4 text-center">
                        <button
                          ref={matchingButtonRef}
                          onClick={handleMatchingUraian}
                          className="bg-green-600 hover:bg-green-700 text-white font-semibold py-2 px-6 rounded focus:ring-2 focus:ring-offset-2 focus:ring-green-400"
                        >
                          Matching
                        </button>
                      </div>
                    )}
                  </div>
                )}
              </>
            )}
          </div>
        </CenterDrawer>

        <TopDrawer isOpen={openTopDrawer} onClose={() => setOpenTopDrawer(false)}>
          <div className="flex gap-4 max-h-[28vh] overflow-hidden">
            {selectedRows?.sarana?.length > 0 && <TableSarana data={selectedRows.sarana} columns={columns} />}
            {selectedRows?.realisasi?.length > 0 && <TableRealisasi data={selectedRows.realisasi} />}
          </div>

          {/* Dropdown hanya sarana */}
          {selectedRows?.realisasi?.length === 0 && (
            <MatchingSelect
              value={tipeMatching}
              onChange={setTipeMatching}
              options={[
                { value: 'FRCSEE', label: 'By Frcsee' },
                { value: 'NONPP', label: 'Non PP' },
                { value: 'SEWA', label: 'Sewa' },
                { value: 'SURKAS', label: 'Pot Surkas' },
                { value: 'RENOVASI', label: 'Renovasi Fisik' },
              ]}
            />
          )}

          {/* Dropdown hanya realisasi */}
          {selectedRows?.sarana?.length === 0 && (
            <MatchingSelect
              value={tipeMatching}
              onChange={setTipeMatching}
              options={[
                { value: 'GO', label: 'Sarana GO' },
                { value: 'NONRAB', label: 'Non RAB' },
              ]}
            />
          )}

          <div className="flex justify-center mt-4">
            <button
              type="button"
              onClick={handleSubmitMatching}
              ref={matchButtonTopRef}
              className="bg-green-600 text-white px-4 py-1 rounded hover:bg-green-700 flex items-center gap-2 focus:outline-none focus:ring-2 focus:ring-green-400"
            >
              <HiRefresh className="w-4 h-4" />
              Proccess
            </button>
          </div>
        </TopDrawer>

      </div>
    </main>
  );
}
