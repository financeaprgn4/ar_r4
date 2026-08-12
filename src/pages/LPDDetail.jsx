import React, { useRef, useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import axios from "../config/axiosInstance";
import Swal from "sweetalert2";
import { formatDate, formatRupiah, formatAmount } from "../utility/textFormatter";
import RightSidebar from '../components/RightSidebar';
import Menu_LPD from '../components/Menu_LPD';
import {
  handleCreateLPD,handleUpdateketerangan
} from '../utility/exportLPD';
import { useRightPanel } from '../contexts/RightPanelContext';
import KategoriLPDRow from "../components/KategoriLPDRow";
import CenterDrawer from "../components/CenterDrawer";
import BottomDrawer from "../components/BottomDrawer";
import { useNoRab } from "../contexts/NoRabContext";
import { useLpdDetail } from '../hooks/useLpdDetail';
import TableLoading from "../components/TableLoading";
import LpdIdentitas from "../components/LpdIdentitas";
import { useSidebar } from "../components/SidebarContext";
import { FaFilePdf, FaMoneyBill, FaSave, FaFileImport, FaCheckCircle, FaCloudUploadAlt, FaTools, FaMoneyCheck, FaToolbox, FaMailBulk, FaPaperPlane } from "react-icons/fa";
import { HiPlus, HiRefresh, HiTrash, HiMenu, HiPencil } from "react-icons/hi";
import Papa from "papaparse";
import { pdfjs } from "react-pdf";
import { processEstimasiFromPdf } from "../utility/pdfEstimasi";
import { 
  handleCekPDF, 
  parseTableBBT 
} from "../utility/pdfModal";

export default function LPDDetail() {
  const { noRab: no_rab } = useNoRab();
  const { isCollapsed } = useSidebar();
  const tbodyRef = useRef(null);
  const [isDrawerOpen, setIsDrawerOpen] = useState(false);
  const [showDrawer, setShowDrawer] = useState(false);
  const [drawerMode, setDrawerMode] = useState('create');
  const [activeRowIndex, setActiveRowIndex] = useState(null);  
  const [shouldFocusLastRow, setShouldFocusLastRow] = useState(false);
  const [selectedFile, setSelectedFile] = useState(null);
  const labelRef = useRef(null);
  const fileInputRef = useRef(null);
  const { closePanel, openLPDPanel, refreshFlag } = useRightPanel();
  const rekapRef = useRef();
  const createBtnRef = useRef(null);
  const navigate = useNavigate();
  const [syncModal, setSyncModal] = useState(false);
  const [syncRab, setSyncRab] = useState(false);
  const [selectedIds, setSelectedIds] = useState([]);
  
  const {
    data,
    rekap,
    modalData,
    modaldetailData,
    identitas,
    berkas,
    estimasi,
    realisasi,
    flag_realisasi,
    totalToko,
    dppToko,
    ppnToko,
    totalModal,
    totalRenov,
    totalEstimasi,
    totalSarana,
    totalRealisasi,
    totalDatPR,
    fetchData,
    initialLoading,
  } = useLpdDetail(no_rab);
  
  const realisasiData = data?.[0] || {};
  const allRealisasi = [
    ...(realisasiData.listrik || []),
    ...(realisasiData.signage || []),
    ...(realisasiData.teralis || []),
    ...(realisasiData.ins_ac || []),
    ...(realisasiData.halaman || []),
    ...(realisasiData.kaca || []),
    ...(realisasiData.poly || []),
    ...(realisasiData.folding || []),
    ...(realisasiData.interior || []),
    ...(realisasiData.sipil || []),
    ...(realisasiData.urugan || []),
    ...(realisasiData.lift || []),
  ];
  
  const filteredRealisasi = allRealisasi.filter(item => item.kd_group === "030006");

  useEffect(() => {
    if (rekap) {
      rekapRef.current = rekap;
    }
  }, [rekap]);
  
  useEffect(() => {
    if (tbodyRef.current) {
      tbodyRef.current.focus();
    }
  }, []);

  useEffect(() => {
    if (isDrawerOpen && createBtnRef.current) {
      createBtnRef.current.focus();
    }
  }, [isDrawerOpen]);

  useEffect(() => {
    if (showDrawer && labelRef.current) {
      labelRef.current.focus();
    }
  }, [showDrawer]);
  
  const [selectedData, setSelectedData] = useState(null);
  const handleOpenDrawer = (data) => {
    setSelectedData(data);
    setDrawerMode('edit');
    setShowDrawer(true);
  };

  useEffect(() => {
    if (drawerMode === 'edit' && selectedData) {
      const formattedData = {
        ...selectedData,
        dpp: formatAmount(selectedData.dpp),
        ppn: formatAmount(selectedData.ppn),
      };
      setRows([formattedData]);
    }
  }, [selectedData, drawerMode]);

  const [isMenuOpen, setMenuOpen] = useState(false);
  const handleOpenMenu = () => setMenuOpen(true);
  const handleCloseMenu = () => setMenuOpen(false);
  
  const inputRefs = useRef([]);
  useEffect(() => {
    if (showDrawer) {
      setTimeout(() => {
        if (inputRefs.current[0]) {
          inputRefs.current[0].focus();
        }
      }, 0);
    }
  }, [showDrawer]);

  const [rightsidebarOpen, setRightSidebarOpen] = useState(false);
  const handleSwitch = () => {
    setRightSidebarOpen(true);
    setShowDrawer(false);
  };

  const closeRightSidebar = () => {
    setRightSidebarOpen(false);
  };

  useEffect(() => {
    if (no_rab) {
      fetchData();
    }
  }, [fetchData, no_rab, refreshFlag ]);
  
  const [rows, setRows] = useState([
    { inv_num: "", kd_group: "", plu: "", dpp: "", ppn: "0", keterangan: "" }
  ]);

  const handleInputChange = (index, field, value) => {
    const updated = [...rows];
    updated[index][field] = value;
    setRows(updated);
  };

  const handleCheckboxChange = (index, checked) => {
    const dpp = parseFloat(rows[index].dpp.toString().replace(/,/g, '')) || 0;
    const updatedRows = [...rows];

    if (checked) {
      const ppn = Math.round(dpp * 0.11);
      updatedRows[index].ppn = formatAmount(ppn.toString());
    } else {
      updatedRows[index].ppn = '0';
    }

    setRows(updatedRows);
  };

  const rowsRef = useRef(rows);
  useEffect(() => {
    rowsRef.current = rows;
  }, [rows]);
  
  const clearForm = () => {
    setRows([{ inv_num: "", kd_group: "", plu: "", dpp: "", ppn: "0", keterangan: "" }]);
    setDrawerMode("create");
  };

  useEffect(() => {
    const handleKeyDown = (e) => {
      if (!isCollapsed) {
        return;
      }
      
      if (e.key === 'Escape') {
        if (showDrawer) {
          setShowDrawer(false);
          if (drawerMode === 'create'){
            clearForm();
            tbodyRef.current.focus();
          }
          if (drawerMode === 'import' || drawerMode === 'upload'){
            resetFilePicker();
          }
        }

        if (rightsidebarOpen) {
          setRightSidebarOpen(false);
          tbodyRef.current.focus();
        }

        if (isMenuOpen) {
          setMenuOpen(false);
          tbodyRef.current.focus();
        }
        if (isDrawerOpen){
          setIsDrawerOpen(false);
          tbodyRef.current.focus();
        }
      }
      
      if (e.altKey && e.code === 'KeyA') {
        e.preventDefault();
        if (identitas?.status === "NEW") {
          setRightSidebarOpen(false);
          setDrawerMode('create');
          setShowDrawer(true);
        }else{
          Swal.fire({
            icon: "error",
            title: "Gagal",
            text: `Status LPD toko ini adalah ${identitas.status}, User tidak diperkenankan untuk melakukan perubahan`,
            confirmButtonText: "OK"
          });
        }
      }

      if (e.altKey && e.code === 'KeyC') {
        e.preventDefault();
        
        closePanel();
        setDrawerMode();
        setIsDrawerOpen(false);
        setShowDrawer(false);
        setMenuOpen(false);
        setRightSidebarOpen(true);
      }

      if (e.altKey && e.code === 'KeyK') {
        e.preventDefault();
        
        setMenuOpen(false);
        setRightSidebarOpen(false);
        setShowDrawer(false);
        
        handleRightPanelToggle(rekapRef.current, 'view');
      }

      if (e.altKey && e.code === 'KeyL') {
        e.preventDefault();
        navigate('/lpd-modal');
      }

      if (e.altKey && e.code === 'KeyM') {
        e.preventDefault();
        setMenuOpen(true);
        setRightSidebarOpen(false);
        setShowDrawer(false);
      }

      if (e.altKey && e.code === 'KeyR') {
        e.preventDefault();
        
        setRightSidebarOpen(false);
        setDrawerMode("renovasi");
        setIsDrawerOpen(true);
      }

      if (showDrawer && drawerMode === 'create') {
        if (e.altKey && e.code === 'KeyA') {
          e.preventDefault();
          handleAddRow(rowsRef.current);
          return;
        }

        if (e.altKey && e.code === 'KeyD') {
          e.preventDefault();
          if (activeRowIndex !== null) {
            handleRemoveRow(activeRowIndex);
          }
          return;
        }

        if (e.altKey && e.code === 'KeyR') {
          e.preventDefault();
          clearForm();
          return;
        }
      } else {
        if (e.altKey && e.code === 'KeyD') {
          e.preventDefault();
          navigate('/dat_pr');
        }
      }

      if (e.altKey && e.code === 'KeyI') {
        e.preventDefault();
        if (identitas?.status === "NEW") {
          setDrawerMode('import');
          setShowDrawer(true);
        }else{
          Swal.fire({
            icon: "error",
            title: "Gagal",
            text: `Status LPD toko ini adalah ${identitas.status}, User tidak diperkenankan untuk melakukan perubahan`,
            confirmButtonText: "OK"
          });
        }
      }
      if (e.altKey && e.code === 'KeyS') {
        e.preventDefault();
        navigate('/sarana_toko');
      }
      if (e.altKey && e.code === 'KeyT') {
        e.preventDefault();
        handleCreateLPD(identitas.no_rab);
      }
      if (e.altKey && e.code === 'KeyN') {
        e.preventDefault();
        handleUpdateketerangan(identitas.no_rab);
      }
      
      if (e.altKey && e.code === 'KeyU') {
        e.preventDefault();
        setDrawerMode('upload');
        setShowDrawer(true);
      }
      
      if (
        e.key === 'Enter' &&
        document.activeElement === labelRef.current &&
        fileInputRef.current
      ) {
        e.preventDefault();
        fileInputRef.current.click();
      }
    };

    window.addEventListener('keydown', handleKeyDown);
    return () => {
      window.removeEventListener('keydown', handleKeyDown);
    };
  }, [isCollapsed, showDrawer, rightsidebarOpen, isMenuOpen, activeRowIndex, identitas, isDrawerOpen]);

  const handleAddRow = (currentRowsParam) => {
    const currentRows = Array.isArray(currentRowsParam) ? currentRowsParam : rows;

    const isAnyEmpty = currentRows.some(
      (row) =>
        !row.inv_num?.trim() ||
        !row.no_sjf ||
        !row.line_num ||
        !row.kd_group ||
        !row.dpp ||
        !row.ppn ||
        !row.keterangan?.trim()
    );

    if (isAnyEmpty) {
      Swal.fire('Gagal!', "Mohon lengkapi semua kolom pada baris yang ada sebelum menambah baris baru.", 'error');
      return;
    }

    const newRow = {
      inv_num: '',
      no_sjf: '',
      line_num: '',
      kd_group: '',
      plu: '',
      dpp: '',
      ppn: '',
      keterangan: ''
    };

    setRows((prev) => [...prev, newRow]);

    setTimeout(() => {
      const lastIndex = currentRows.length;
      inputRefs.current[lastIndex]?.focus();
    }, 0);
  };

  useEffect(() => {
    if (shouldFocusLastRow && rows.length > 0) {
      const lastRef = inputRefs.current[rows.length - 1];
      if (lastRef) lastRef.focus();
      setShouldFocusLastRow(false);
    }
  }, [rows, shouldFocusLastRow]);

  const handleRemoveRow = (indexToRemove) => {
    if (rowsRef.current.length <= 1) {
      Swal.fire('Gagal!', "Minimal harus ada satu baris!", 'error');
      return;
    }
    
    setRows((prev) => {
      const updated = prev.filter((_, i) => i !== indexToRemove);
      inputRefs.current.splice(indexToRemove, 1);
      return updated;
    });

    setShouldFocusLastRow(true);
    setActiveRowIndex(null);
  };
  
  const handleDelete = async (trx) => {
    const result = await Swal.fire({
      title: 'Yakin ingin menghapus?',
      text: 'Data ini akan dihapus dari tabel dinamis.',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Ya, hapus!',
      cancelButtonText: 'Batal',
    });

    document.activeElement.blur();
    if (result.isConfirmed) {
      try {
        const res = await axios.delete(`/api/lpd-detail/delete`, {
          data: {
            id: trx.id,
            rab: trx.rab,
          },
        });

        Swal.fire('Berhasil!', res.data.message || 'Data berhasil dihapus.', 'success')
        .then(() => {
          fetchData();
          tbodyRef.current.focus();
        });
      } catch (error) {
        const msg = error.response?.data?.message || 'Terjadi kesalahan saat menghapus data.';
        Swal.fire('Gagal!', msg, 'error');
      }
    }
  };

  const handleSubmit = async (e) => {
    e.preventDefault();

    for (let i = 0; i < rows.length; i++) {
      const { inv_num, no_sjf, line_num, kd_group, dpp, ppn, keterangan } = rows[i];
      if (!inv_num || !no_sjf || !line_num || !kd_group || !dpp || !ppn || !keterangan) {
        Swal.fire({
          icon: 'error',
          title: 'Validasi Gagal',
          text: `Semua kolom pada baris ke-${i + 1} harus diisi!`,
        });
        return;
      }
    }

    try {
      const payload = rows.map((row) => ({
        id: row.id,
        rab: no_rab,
        no_sjf: row.no_sjf,
        line_num: row.line_num,
        kd_toko: identitas.kd_toko,
        inv_num: row.inv_num,
        kd_group: row.kd_group,
        plu: row.plu,
        dpp: parseFloat(row.dpp.replace(/[^0-9.-]+/g, "")),
        ppn: parseFloat(row.ppn.replace(/[^0-9.-]+/g, "")),
        keterangan: row.keterangan,
      }));
      
      let response;

      if (drawerMode === 'create') {
        response = await axios.post(`/api/add-lpd-trx`, payload);
      } else if (drawerMode === 'edit') {
        response = await axios.post(`/api/update-lpd-trx`, payload);
      }

      const message = response?.data?.message || 'Data berhasil disimpan.';

      Swal.fire({
        icon: 'success',
        title: 'Berhasil',
        text: message,
      }).then(() => {
        setShowDrawer(false);
        fetchData();
        clearForm();
        tbodyRef.current.focus();
      });
    } catch (error) {
      console.error('Gagal menyimpan:', error);

      if (error.response?.status === 422) {
        const { message, errors } = error.response.data;
        const allErrors = Object.values(errors).flat().join('\n');
        Swal.fire({
          icon: 'error',
          title: 'Validasi Gagal',
          text: `${message}\n${allErrors}`,
        });
      } else {
        const backendMessage = error.response?.data?.message || 'Silakan periksa koneksi atau hubungi admin.';
        Swal.fire({
          icon: 'error',
          title: 'Gagal menyimpan data',
          text: backendMessage,
        });
      }
    }
  };

  const handleFileChange = (e) => {
    const files = Array.from(e.target.files);

    if (drawerMode === 'upload') {
      // Validasi hanya file PDF
      const pdfFiles = files.filter(file => file.type === 'application/pdf');
      setSelectedFile(pdfFiles);
    } else {
      // Mode lain: hanya ambil satu file (CSV)
      setSelectedFile(files[0]);
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
        `/api/import-lpd-detail`,
        { data: rows }
      );

      if (response.data.success) {
        Swal.fire("Berhasil", response.data.message, "success").then(() => {
          setShowDrawer(false);
          fetchData();
          resetFilePicker();
          tbodyRef.current.focus();
        });
      } else {
        Swal.fire("Gagal", response.data.message || "Import gagal", "error");
      }
    } catch (error) {
      console.error("Import error:", error);
      Swal.fire("Error", error.message || "Terjadi kesalahan saat import", "error");
    }
  };

  const handleUploadPDF = async () => {
    if (!selectedFile || selectedFile.length === 0) {
      return Swal.fire("Peringatan", "Silakan pilih minimal satu file PDF untuk diunggah.", "warning");
    }

    Swal.fire({
      title: "UPLOADING FILE",
      html: "Please wait...",
      allowOutsideClick: false,
      didOpen: () => {
        Swal.showLoading();
      }
    });
    
    const formData = new FormData();
    for (let i = 0; i < selectedFile.length; i++) {
      formData.append("pdf_files[]", selectedFile[i]);
    }

    try {
      // --- Upload PDF ---
      const response = await fetch(`/api/upload-pdf`, {
        method: "POST",
        body: formData,
      });

      const contentType = response.headers.get("content-type");

      if (!response.ok) throw new Error(`Gagal: ${response.status}`);
      if (!contentType || !contentType.includes("application/json")) {
        const text = await response.text();
        throw new Error(`Bukan JSON valid: ${text}`);
      }

      const result = await response.json();
      let uploadSuccess = result.success;
      let syncRabSuccess = true;
      let syncModalSuccess = true;

      // --- Sync Estimasi ---
      if (uploadSuccess && syncRab) {
        for (let file of selectedFile) {
          try {
            const resultEst = await processEstimasiFromPdf(file);
            const payload = { no_rab: resultEst.noRAB, estimasi: resultEst.rows };

            const saveResponse = await fetch(`/api/lpd-estimasi`, {
              method: "POST",
              headers: { "Content-Type": "application/json" },
              body: JSON.stringify(payload),
            });

            const saveResult = await saveResponse.json();
            if (!saveResponse.ok || !saveResult.message?.includes("berhasil")) {
              syncRabSuccess = false;
              console.error("❌ Gagal simpan estimasi:", saveResult.message);
            }
          } catch (err) {
            syncRabSuccess = false;
            console.error("❌ Error proses estimasi:", err);
          }
        }
      }

      // --- Sync Modal ---
      if (uploadSuccess && syncModal) {
        for (let file of selectedFile) {
          const modalResult = await handleCekPDF({
            file,
            keywordRegex: /SET\s+INVESTASI/i,
            rowParser: parseTableBBT,
            apiUrl: `/api/lpd-modal-sync`,
            pdfjs,
          });

          if (!modalResult.success) {
            syncModalSuccess = false;
            console.error("❌ Gagal proses modal:", modalResult.message);
          }
        }
      }

      Swal.close();

      let alertPromise;
      if (uploadSuccess && syncRab && syncModal && syncRabSuccess && syncModalSuccess) {
        alertPromise = Swal.fire("Berhasil", "Upload + estimasi + modal berhasil.", "success");
      } else if (uploadSuccess && syncRab && !syncRabSuccess) {
        alertPromise = Swal.fire("Sebagian Berhasil", "Upload sukses, tapi sync estimasi gagal.", "warning");
      } else if (uploadSuccess && syncModal && !syncModalSuccess) {
        alertPromise = Swal.fire("Sebagian Berhasil", "Upload sukses, tapi sync modal gagal.", "warning");
      } else if (uploadSuccess) {
        alertPromise = Swal.fire("Berhasil", "File PDF berhasil diunggah.", "success");
      } else {
        alertPromise = Swal.fire("Gagal", result.message || "Proses gagal.", "error");
      }

      if (uploadSuccess) {
        alertPromise.then(() => {
          fetchData();
          setShowDrawer(false);
          resetFilePicker();
          setSelectedFile(null);
          setSyncRab(false);
          setSyncModal(false);

          setTimeout(() => {
            tbodyRef.current?.focus();
          }, 100);
        });
      }
    } catch (error) {
      console.error(error);
      Swal.fire("Error", error.message || "Tidak dapat terhubung ke server.", "error");
    }
  };

  const handleCloseDrawer = () => {
    setIsDrawerOpen(false);
  };

  const handleCreateClearenceSheet = () => {
    if (!no_rab || !identitas.kd_toko || !identitas.tgl_wrlb) {
      Swal.fire("Peringatan", "Lengkapi data RAB, Toko dan Tanggal Waralaba", "warning");
      return;
    }

    Swal.fire({
      title: "Konfirmasi",
      text: "Yakin ingin membuat Clearencesheet?",
      icon: "question",
      showCancelButton: true,
      confirmButtonText: "Ya, Buat!",
    }).then((result) => {
      if (result.isConfirmed) {
        fetch(`/api/generate-clearencesheet`, {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
          },
          body: JSON.stringify({
            no_rab: no_rab,
            kd_toko: identitas.kd_toko,
            tgl_wrlb: identitas.tgl_wrlb,
          }),
        })
          .then((res) => res.json())
          .then((res) => {
            if (res.success) {
              Swal.fire({
                icon: "success",
                title: "Berhasil",
                text: `PDF berhasil dibuat: ${res.filename}`,
              }).then(() => {
                fetchData();
                handleCloseDrawer();
                setTimeout(() => {
                  tbodyRef.current?.focus();
                }, 500);
              });
            } else {
              Swal.fire("Gagal", res.message || "Terjadi kesalahan", "error");
            }
          })
          .catch((err) => {
            console.error("Generate error:", err);
            Swal.fire("Error", "Terjadi kesalahan jaringan atau server", "error");
          });
      }
    });
  };

  const handleRightPanelToggle = (site = null, mode = null) => {
    setMenuOpen(false);
    openLPDPanel(mode, site);
  };

  const confirmrenov  = async () => {
    try {
      const response = await fetch(`/api/conf-Renov`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({ rab: no_rab }),
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
  const rowsrenov = [
    { ket: "Pekerjaan Folding Gate (0216030012)", field : "flag_realisasi_fg", est: estimasi.est_fg, real: realisasi.realisasi_fg, flag_real: flag_realisasi.flag_realisasi_fg },
    { ket: "Pekerjaan Kanopi (PJ00448)", field: "flag_realisasi_kanopi", est: estimasi.est_kanopi, real: realisasi.realisasi_kanopi, flag_real: flag_realisasi.flag_realisasi_kanopi },
    { ket: "Pekerjaan Instalasi AC (0216030016)", field: "flag_realisasi_ins_ac", est: estimasi.est_ins_ac, real: realisasi.realisasi_ins_ac, flag_real: flag_realisasi.flag_realisasi_ins_ac },
    { ket: "Pekerjaan Teralis (0216030014)", field : "flag_realisasi_teralis", est: estimasi.est_teralis, real: realisasi.realisasi_teralis, flag_real: flag_realisasi.flag_realisasi_teralis },
    { ket: "Pekerjaan Halaman (0216030010)", field : "flag_realisasi_halaman", est: estimasi.est_halaman, real: realisasi.realisasi_halaman, flag_real: flag_realisasi.flag_realisasi_halaman },
    { ket: "Pekerjaan Polycarbonate (0216030013)", field : "flag_realisasi_policarbonate", est: estimasi.est_policarbonate, real: realisasi.realisasi_policarbonate, flag_real: flag_realisasi.flag_realisasi_policarbonate },
    { ket: "Pekerjaan Listrik (0216030015)", field : "flag_realisasi_listrik", est: estimasi.est_listrik, real: realisasi.realisasi_listrik, flag_real: flag_realisasi.flag_realisasi_listrik },
    { ket: "Pekerjaan Aluminium & Kaca (0216030011)", field : "flag_realisasi_aluminium_kaca", est: estimasi.est_aluminium_kaca, real: realisasi.realisasi_aluminium_kaca, flag_real: flag_realisasi.flag_realisasi_aluminium_kaca },
    { ket: "Pekerjaan Signage (0211040090)", field : "flag_realisasi_signage", est: estimasi.est_signage, real: realisasi.realisasi_signage, flag_real: flag_realisasi.flag_realisasi_signage },
    { ket: "Pekerjaan Interior & Eksterior (PJ00089)", field : "flag_realisasi_interior", est: estimasi.est_interior, real: realisasi.realisasi_interior, flag_real: flag_realisasi.flag_realisasi_interior },
    { ket: "Pekerjaan Lift (PJ01741)", field : "flag_realisasi_lift", est: estimasi.est_lift, real: realisasi.realisasi_lift, flag_real: flag_realisasi.flag_realisasi_lift },
    { ket: "Pekerjaan Sipil (0216030008)", field : "flag_realisasi_sipil", est: estimasi.est_sipil, real: realisasi.realisasi_sipil, flag_real: flag_realisasi.flag_realisasi_sipil },
    { ket: "Pekerjaan Urugan & Pemadatan (0216030009)", field : "flag_realisasi_urugan", est: estimasi.est_urugan, real: realisasi.realisasi_urugan, flag_real: flag_realisasi.flag_realisasi_urugan },
  ];
  
  const [flags, setFlags] = useState({ ...flag_realisasi });
  useEffect(() => {
    setFlags({ ...flag_realisasi });
  }, [no_rab, flag_realisasi]);

  const handleSaveFlags = async () => {
    try {
      const response = await fetch(`/api/update-flag-real`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          no_rab,
          flags, // seluruh data flag dikirim sekaligus
        }),
      });

      const data = await response.json();

      if (data.success) {
        Swal.fire("Berhasil", data.message, "success");
      } else {
        Swal.fire("Gagal", data.message, "error");
      }
    } catch (error) {
      Swal.fire("Error", "Terjadi kesalahan saat menyimpan data.", "error");
    }
  };

  const handleCheckboxLPDPRJChange = (id) => {
    setSelectedIds((prev) =>
      prev.includes(id) ? prev.filter((val) => val !== id) : [...prev, id]
    );
  };

  const handleUplodLPDPRJ = async (e) => {
    e.preventDefault();

    if (selectedIds.length === 0) {
      Swal.fire("Peringatan", "Pilih minimal satu realisasi!", "warning");
      return;
    }
    if (!selectedFile) {
      Swal.fire("Peringatan", "Upload file PDF terlebih dahulu!", "warning");
      return;
    }

    try {
      const formData = new FormData();
      formData.append("no_rab", no_rab);
      formData.append("kd_toko", identitas.kd_toko);
      formData.append("wrlb", identitas.tgl_wrlb);
      formData.append("ids", JSON.stringify(selectedIds));
      formData.append("file", selectedFile);
      const response = await fetch(`/api/upload-lpdprj`, {
        method: "POST",
        body: formData,
      });

      if (!response.ok) throw new Error("Gagal submit");

      const result = await response.json();

      if (result.success) {
        Swal.fire({
          icon: "success",
          title: "Berhasil",
          text: result.message || "Data berhasil disimpan!",
        }).then(() => {
          fetchData();
          setSelectedIds([]);
          resetFilePicker();
        });
      } else {
        Swal.fire("Gagal", result.message || "Terjadi kesalahan pada server", "error");
      }
    } catch (err) {
      Swal.fire("Error", err.message, "error");
    }
  };

  return (
    <main className="flex-1 px-2 py-2 z-10 text-white">
      <div className="h-[calc(100vh-50px)] bg-white/60 rounded-lg p-4 shadow-lg text-gray-800 w-full">
        {initialLoading ? (
          <TableLoading text="Memuat Detail LPD..." />
        ) : (
          <>
            <div className="relative flex items-center justify-center mb-4">
              <h2 className="text-xl font-bold">Detail Laporan Penggunaan Dana</h2>
              
              <div className="absolute right-0">
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
                if (identitas?.status !== "NEW") {
                  Swal.fire({
                    icon: "error",
                    title: "Aksi Ditolak",
                    text: `Status LPD toko ini adalah ${identitas.status}, User tidak diperkenankan untuk melakukan perubahan`,
                  });
                  return;
                }

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
            />

            <LpdIdentitas
              identitas={identitas}
              no_rab={no_rab}
              onSwitch={handleSwitch}
            />
            
            <div className="max-h-[calc(100vh-300px)] overflow-auto rounded-lg border border-white-800">
              <table className="min-w-full table-auto border-collapse text-sm text-gray-800">
                <thead className="bg-white sticky top-0 z-10">
                  <tr>
                      <th colSpan={2} className="bg-blue-600 border px-2 py-1 text-center text-white">Data Estimasi LPD</th>
                      <th colSpan={5} className="bg-yellow-300 border px-2 py-1 text-center">Data Realisasi</th>
                      <th rowSpan={2} className="bg-red-500 border px-2 py-1 text-center text-white">RAB - Realisasi</th>
                  </tr>
                  <tr>
                      <th className="bg-blue-600 border px-2 py-1 text-center text-white">Keterangan</th>
                      <th className="bg-blue-600 border px-2 py-1 text-center text-white">Estimasi</th>
                      <th className="bg-gray-100 border px-2 py-1 text-center">Keterangan</th>
                      <th className="bg-gray-100 border px-2 py-1 text-center">DPP</th>
                      <th className="bg-gray-100 border px-2 py-1 text-center">PPn</th>
                      <th className="bg-gray-100 border px-2 py-1 text-center">Total</th>
                      <th className="bg-gray-100 border px-2 py-1 text-center">Inv Num</th>
                  </tr>
                </thead>
                <tbody
                  ref={tbodyRef}
                  tabIndex={-1} 
                >
                  <KategoriLPDRow 
                    judul="Franchise Fee" 
                    estimasi={estimasi.est_frc_fee}
                    transaksi={data}
                    field="frc_fee"
                    onEdit={handleOpenDrawer}
                    onDelete={handleDelete}
                    canEdit={identitas.status === 'NEW'}
                  />
                  
                  <KategoriLPDRow 
                    judul="Promosi GO" 
                    estimasi={estimasi.est_promosi}
                    transaksi={data}
                    field="promo"
                    onEdit={handleOpenDrawer}
                    onDelete={handleDelete}
                    canEdit={identitas.status === 'NEW'}
                  />
                  <KategoriLPDRow 
                    judul="Jasa Rekrut & Training" 
                    estimasi={estimasi.est_rekrut}
                    transaksi={data}
                    field="rekrut"
                    onEdit={handleOpenDrawer}
                    onDelete={handleDelete}
                    canEdit={identitas.status === 'NEW'}
                  />
                  <KategoriLPDRow 
                    judul="Biaya Sewa & PPh" 
                    estimasi={estimasi.est_sewa}
                    transaksi={data}
                    field="sewa"
                    onEdit={handleOpenDrawer}
                    onDelete={handleDelete}
                    canEdit={identitas.status === 'NEW'}
                  />
                  <KategoriLPDRow 
                    judul="Jasa Pihak Ketiga" 
                    estimasi={estimasi.est_jasa}
                    transaksi={data}
                    field="js_phk"
                    onEdit={handleOpenDrawer}
                    onDelete={handleDelete}
                    canEdit={identitas.status === 'NEW'}
                  />

                  <tr>
                    <td
                      colSpan={8}
                      className="bg-yellow-300 border px-2 py-1 text-center font-semibold text-blue-700 cursor-pointer hover:underline"
                      onClick={() => {
                        setDrawerMode("renovasi");
                        setIsDrawerOpen(true);
                      }}
                    >
                      Renovasi Fisik
                    </td>
                  </tr>
                  
                  <KategoriLPDRow 
                    judul="Pekerjaan Folding Gate (0216030012)" 
                    estimasi={estimasi.est_fg}
                    transaksi={data}
                    field="folding"
                    onEdit={handleOpenDrawer}
                    onDelete={handleDelete}
                    canEdit={identitas.status === 'NEW'}
                  />
                  <KategoriLPDRow 
                    judul="Pekerjaan Kanopi (PJ00448)" 
                    estimasi={estimasi.est_kanopi}
                    transaksi={data}
                    field="kanopi"
                    onEdit={handleOpenDrawer}
                    onDelete={handleDelete}
                    canEdit={identitas.status === 'NEW'}
                  />
                  <KategoriLPDRow 
                    judul="Pekerjaan Instalasi AC (0216030016)" 
                    estimasi={estimasi.est_ins_ac}
                    transaksi={data}
                    field="ins_ac"
                    onEdit={handleOpenDrawer}
                    onDelete={handleDelete}
                    canEdit={identitas.status === 'NEW'}
                  />
                  <KategoriLPDRow 
                    judul="Pekerjaan Teralis (0216030014)" 
                    estimasi={estimasi.est_teralis}
                    transaksi={data}
                    field="teralis"
                    onEdit={handleOpenDrawer}
                    onDelete={handleDelete}
                    canEdit={identitas.status === 'NEW'}
                  />
                  <KategoriLPDRow 
                    judul="Pekerjaan Halaman (0216030010)" 
                    estimasi={estimasi.est_halaman}
                    transaksi={data}
                    field="halaman"
                    onEdit={handleOpenDrawer}
                    onDelete={handleDelete}
                    canEdit={identitas.status === 'NEW'}
                  />
                  <KategoriLPDRow 
                    judul="Pekerjaan Polycarbonat (0216030013)" 
                    estimasi={estimasi.est_poli}
                    transaksi={data}
                    field="poly"
                    onEdit={handleOpenDrawer}
                    onDelete={handleDelete}
                    canEdit={identitas.status === 'NEW'}
                  />
                  <KategoriLPDRow 
                    judul="Pekerjaan Listrik (0216030015)" 
                    estimasi={estimasi.est_listrik}
                    transaksi={data}
                    field="listrik"
                    onEdit={handleOpenDrawer}
                    onDelete={handleDelete}
                    canEdit={identitas.status === 'NEW'}
                  />
                  <KategoriLPDRow 
                    judul="Pekerjaan Aluminium & Kaca (0216030011)" 
                    estimasi={estimasi.est_aluminium_kaca}
                    transaksi={data}
                    field="kaca"
                    onEdit={handleOpenDrawer}
                    onDelete={handleDelete}
                    canEdit={identitas.status === 'NEW'}
                  />
                  <KategoriLPDRow 
                    judul="Pekerjaan Signage (0211040090)" 
                    estimasi={estimasi.est_signage}
                    transaksi={data}
                    field="signage"
                    onEdit={handleOpenDrawer}
                    onDelete={handleDelete}
                    canEdit={identitas.status === 'NEW'}
                  />
                  <KategoriLPDRow 
                    judul="Pekerjaan Interior & Eksterior (PJ00089)" 
                    estimasi={estimasi.est_interior}
                    transaksi={data}
                    field="interior"
                    onEdit={handleOpenDrawer}
                    onDelete={handleDelete}
                    canEdit={identitas.status === 'NEW'}
                  />
                  <KategoriLPDRow 
                    judul="Pekerjaan Sipil (0216030008)" 
                    estimasi={estimasi.est_sipil}
                    transaksi={data}
                    field="sipil"
                    onEdit={handleOpenDrawer}
                    onDelete={handleDelete}
                    canEdit={identitas.status === 'NEW'}
                  />
                  <KategoriLPDRow 
                    judul="Pekerjaan Lift (PJ01741)" 
                    estimasi={estimasi.est_lift}
                    transaksi={data}
                    field="lift"
                    onEdit={handleOpenDrawer}
                    onDelete={handleDelete}
                    canEdit={identitas.status === 'NEW'}
                  />
                  <KategoriLPDRow 
                    judul="Pekerjaan Urugan & Pemadatan (0216030009)" 
                    estimasi={estimasi.est_urugan}
                    transaksi={data}
                    field="urugan"
                    onEdit={handleOpenDrawer}
                    onDelete={handleDelete}
                    canEdit={identitas.status === 'NEW'}
                  />

                  {(() => {
                    let totalDpp = 0;
                    let totalPpn = 0;
                    let totalAll = 0;

                    data.forEach((item) => {
                      if (item.renov && item.renov.length > 0) {
                        item.renov.forEach((ren) => {
                          totalDpp += Number(ren.dpp) || 0;
                          totalPpn += Number(ren.ppn) || 0;
                          totalAll += Number(ren.total) || 0;
                        });
                      }
                    });

                    return (
                      <tr className="bg-yellow-300 font-semibold text-blue-700">
                        <td className="border px-2 py-1 text-right">Total Renovasi Fisik</td>
                        <td className="border px-2 py-1 text-right">{formatRupiah(totalRenov)}</td>
                        <td className="border px-2 py-1 text-right"></td>
                        <td className="border px-2 py-1 text-right">{formatRupiah(totalDpp)}</td>
                        <td className="border px-2 py-1 text-right">{formatRupiah(totalPpn)}</td>
                        <td className="border px-2 py-1 text-right">{formatRupiah(totalAll)}</td>
                        <td className="border px-2 py-1 text-right"></td>
                        <td className="border px-2 py-1 text-right">{formatRupiah(totalRenov - totalAll)}</td>
                      </tr>
                    );
                  })()}
                  <KategoriLPDRow 
                    judul="Prasarana Bangunan" 
                    estimasi={estimasi.est_prasarana}
                    transaksi={data}
                    field="prasarana"
                    onEdit={handleOpenDrawer}
                    onDelete={handleDelete}
                    canEdit={identitas.status === 'NEW'}
                  />
                  <KategoriLPDRow 
                    judul="Peralatan Elektronik & Non Elektronik" 
                    estimasi={estimasi.est_peralatan}
                    transaksi={data}
                    field="peralatan"
                    onEdit={handleOpenDrawer}
                    onDelete={handleDelete}
                    canEdit={identitas.status === 'NEW'}
                  />

                  <tr className="bg-red-700 font-semibold text-white">
                    <td className="border px-2 py-1 text-right">GRAND TOTAL</td>
                    <td className="border px-2 py-1 text-right">{formatRupiah(totalEstimasi)}</td>
                    <td className="border px-2 py-1 text-right"></td>
                    <td className="border px-2 py-1 text-right">{formatRupiah(dppToko)}</td>
                    <td className="border px-2 py-1 text-right">{formatRupiah(ppnToko)}</td>
                    <td className="border px-2 py-1 text-right">{formatRupiah(totalToko)}</td>
                    <td className="border px-2 py-1 text-right"></td>
                    <td className="border px-2 py-1 text-right">{formatRupiah(totalEstimasi - totalToko)}</td>
                  </tr>
                </tbody>
              </table>
              
              <div className="flex gap-4 w-full overflow-x-auto mt-2 mb-2 items-start">
                <table className="w-1/2 border border-gray-300">
                  <thead className="bg-yellow-400 text-black text-center">
                    <tr>
                      <th colSpan="4" className="py-2 font-bold">DETAIL MODAL</th>
                    </tr>
                    <tr className="bg-yellow-300">
                      <th className="border px-2 py-1">BBT</th>
                      <th className="border px-2 py-1">Tanggal</th>
                      <th className="border px-2 py-1">Nilai</th>
                      <th className="border px-2 py-1">Keterangan</th>
                    </tr>
                  </thead>
                  <tbody className="text-sm">
                    {modaldetailData.map((item, index) => (
                      <tr key={index}>
                        <td className="border px-2 py-1">{item.bbt}</td>
                        <td className="border px-2 py-1">{formatDate(item.tgl_bbt)}</td>
                        <td className="border px-2 py-1 text-right">{formatRupiah(item.nilai)}</td>
                        <td className="border px-2 py-1">{item.keterangan}</td>
                      </tr>
                    ))}

                    <tr className="font-bold bg-gray-100">
                      <td className="border px-2 py-1 text-right" colSpan="2">Total Modal</td>
                      <td className="border px-2 py-1 text-right">
                        {formatRupiah(totalModal)}
                      </td>
                      <td className="border px-2 py-1"></td>
                    </tr>
                  </tbody>
                </table>

                <table className="w-1/2 border border-gray-300">
                  <thead className="bg-blue-700 text-white text-center">
                    <tr>
                      <th colSpan="2" className="py-2 font-bold">FINAL RESULT</th>
                    </tr>
                    <tr className="bg-blue-600">
                      <th className="border px-2 py-1 text-white">Keterangan</th>
                      <th className="border px-2 py-1 text-white">Nilai</th>
                    </tr>
                  </thead>
                  <tbody className="text-sm">
                    <tr>
                      <td className="border px-2 py-1">Total Modal</td>
                      <td className="border px-2 py-1 text-right">{formatRupiah(totalModal)}</td>
                    </tr>
                    <tr>
                      <td className="border px-2 py-1">Total Realisasi</td>
                      <td className="border px-2 py-1 text-right">{formatRupiah(totalToko)}</td>
                    </tr>
                    <tr className="bg-green-700 text-white font-bold">
                      <td className="border px-2 py-1">Sisa LPD</td>
                      <td className="border px-2 py-1 text-right">{formatRupiah(totalModal - totalToko)}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </>
        )}

        <RightSidebar isOpen={rightsidebarOpen} onClose={closeRightSidebar}>
        </RightSidebar>
        <CenterDrawer
          isOpen={isDrawerOpen}
          onClose={handleCloseDrawer}
          widthClass="max-w-full"
        >
          {drawerMode === "clearencesheet" && (
            <div className="p-4">
              <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
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
                      <h4 className="italic">Belum Realisasi : {formatAmount(totalSarana - totalRealisasi || 0)}</h4>
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

              {/* Tombol Create */}
              <div className="flex justify-center">
                <button
                  ref={createBtnRef}
                  onClick={handleCreateClearenceSheet}
                  className="flex items-center gap-2 bg-green-600 hover:bg-green-700 text-white font-semibold px-6 py-2 rounded-lg shadow"
                >
                  <HiPencil className="w-4 h-4" /> Create Clearencesheet
                </button>
              </div>
            </div>
          )}

          {drawerMode === "renovasi" && (
            <div className="p-4">
              <LpdIdentitas identitas={identitas} no_rab={no_rab} />

              <div className="overflow-x-auto">
                <table className="min-w-full border border-gray-300 bg-white rounded-lg shadow">
                  <thead>
                    <tr className="bg-blue-200 text-blue-900 font-semibold">
                      <th className="border px-4 py-2 text-left">Keterangan</th>
                      <th className="border px-4 py-2 text-center">Estimasi</th>
                      <th className="border px-4 py-2 text-center">Rencana Realisasi</th>
                      <th className="border px-4 py-2 text-center">Realisasi</th>
                    </tr>
                  </thead>
                  <tbody>
                    {rowsrenov.map((row, idx) => (
                      <tr key={idx} className="hover:bg-gray-50">
                        <td className="border px-4 py-2 text-blue-800">{row.ket}</td>
                        <td className="border px-4 py-2 text-right">
                          {formatRupiah(row.est)}
                        </td>
                        <td className="border px-4 py-2 text-center">
                          <select
                            value={flags[row.field] ?? "null"}
                            onChange={(e) => {
                              const val = e.target.value;
                              const newValue = val === "null" ? null : val;
                              setFlags((prev) => ({
                                ...prev,
                                [row.field]: newValue,
                              }));
                            }}
                            className="bg-transparent border-none focus:outline-none text-center"
                          >
                            <option value="null">-</option>
                            <option value="Y">Y</option>
                            <option value="N">N</option>
                          </select>
                        </td>
                        <td className="border px-4 py-2 text-right">
                          {formatRupiah(row.real)}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>

              {/* Tombol simpan di bagian bawah */}
              <div className="flex justify-center mt-4 gap-3">
                <button
                  onClick={handleSaveFlags}
                  className="flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white font-semibold px-6 py-2 rounded-lg shadow"
                >
                  <FaSave className="w-5 h-5" /> Simpan
                </button>

                <button
                  ref={createBtnRef}
                  onClick={confirmrenov}
                  className="flex items-center gap-2 bg-green-600 hover:bg-green-700 text-white font-semibold px-6 py-2 rounded-lg shadow"
                >
                  <FaPaperPlane className="w-5 h-5" /> Sent Mail Confirm
                </button>
              </div>
            </div>
          )}

          {drawerMode === "lpdprj" && (
            <div className="p-4">
              <LpdIdentitas identitas={identitas} no_rab={no_rab} />

              {/* Tabel Realisasi */}
              <div className="overflow-x-auto mt-4">
                <table className="min-w-full border border-gray-300 text-sm">
                  <thead>
                    <tr className="bg-gray-100">
                      <th className="px-3 py-2 border">Inv Num</th>
                      <th className="px-3 py-2 border">DPP</th>
                      <th className="px-3 py-2 border">PPN</th>
                      <th className="px-3 py-2 border">Total</th>
                      <th className="px-3 py-2 border">Keterangan</th>
                      <th className="px-3 py-2 border">Select</th>
                    </tr>
                  </thead>
                  <tbody>
                    {filteredRealisasi.length > 0 ? (
                      filteredRealisasi.map((item, index) => (
                        <tr key={index}>
                          <td className="px-3 py-2 border">{item.inv_num}</td>
                          <td className="px-3 py-2 border text-right">{item.dpp?.toLocaleString()}</td>
                          <td className="px-3 py-2 border text-right">{item.ppn?.toLocaleString()}</td>
                          <td className="px-3 py-2 border text-right">{item.total?.toLocaleString()}</td>
                          <td className="px-3 py-2 border">
                            <div className="flex items-center gap-2">
                              <span>{item.keterangan}</span>

                              {item.flag_renov && (
                                item.flag_renov === "ACL" ? (
                                  <span className="bg-yellow-400 text-black rounded px-2 py-1 text-xs font-semibold">
                                    ACL
                                  </span>
                                ) : (
                                  <button
                                    className="btn btn-warning flex items-center gap-1"
                                    title="Lihat LPD Project"
                                    onClick={() =>
                                      window.open(
                                        `/file/lpd_prj/${item.flag_renov}`,
                                        "_blank"
                                      )
                                    }
                                  >
                                    <FaFilePdf className="w-5 h-5" />
                                  </button>
                                )
                              )}
                            </div>
                          </td>

                          <td className="px-3 py-2 border text-center">
                            {item.flag_renov === '' && (
                              <input
                                type="checkbox"
                                value={item.id}
                                checked={selectedIds.includes(item.id)}
                                onChange={() => handleCheckboxLPDPRJChange(item.id)}
                              />
                            )}
                          </td>
                        </tr>
                      ))
                    ) : (
                      <tr>
                        <td colSpan="6" className="px-3 py-4 text-center text-gray-500">
                          Tidak ada data realisasi untuk kd_group 030006
                        </td>
                      </tr>
                    )}
                  </tbody>
                </table>
              </div>

              {/* Upload PDF + Submit */}
              <div className="mt-4">
                <form onSubmit={handleUplodLPDPRJ} className="mt-4">
                  <input
                    type="file"
                    accept="application/pdf"
                    onChange={handleFileChange}
                    className="block mb-2"
                  />
                  <button
                    type="submit"
                    className="flex bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded gap-1 mt-2 mb-2"
                  >
                    <FaSave className="w-5 h-5" /> Submit
                  </button>
                </form>
              </div>
            </div>
          )}
        </CenterDrawer>

        {showDrawer && (
          <BottomDrawer
            isOpen={showDrawer}
            onClose={() => setShowDrawer(false)}
            height={
              drawerMode === 'edit'
                ? '200px'
                : drawerMode === 'import' || drawerMode === 'upload'
                ? '300px'
                : '300px'
            }
          >
            <div className="flex flex-col h-full">
              {(drawerMode === 'import' || drawerMode === 'upload') ? (
                <div className="flex-1 flex flex-col items-center justify-center px-4 mb-12">
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
                        {Array.isArray(selectedFile) ? (
                          <>
                            <p className="text-green-300 text-center text-sm">
                              {selectedFile.length} File Dipilih
                            </p>
                            <div className="text-white text-xs mt-1 max-h-24 overflow-y-auto text-center">
                              {selectedFile.map((file, index) => (
                                <span key={index}>
                                  {file.name}{index < selectedFile.length - 1 ? ' | ' : ''}
                                </span>
                              ))}
                            </div>
                          </>
                        ) : (
                          <>
                            <p className="text-green-300 text-center text-sm">{selectedFile.name}</p>
                            <p className="text-white text-xs mt-1">File berhasil dipilih</p>
                          </>
                        )}
                      </>
                    ) : (
                      <>
                        <FaCloudUploadAlt className="h-12 w-12 text-white mb-2" />
                        <p className="text-center text-white">
                          <strong>Klik untuk Pilih atau unggah File</strong>
                          <br />
                          <span className="text-xs italic">
                            {drawerMode === 'upload'
                              ? 'Hanya file PDF diperbolehkan (multiple)'
                              : 'Hanya file CSV diperbolehkan'}
                          </span>
                        </p>
                      </>
                    )}
                    <input
                      id="fileUpload"
                      ref={fileInputRef}
                      type="file"
                      accept={drawerMode === 'upload' ? 'application/pdf' : '.csv, .txt'}
                      onChange={handleFileChange}
                      multiple={drawerMode === 'upload'}
                      className="hidden"
                    />
                  </label>

                  {/* === Tambahan Checkbox khusus upload === */}
                  {drawerMode === "upload" && (
                    <div className="mt-4 w-full max-w-md flex flex-row items-center justify-between text-white">
                      <label className="flex items-center gap-2 cursor-pointer">
                        <input
                          type="checkbox"
                          checked={syncModal}
                          onChange={(e) => setSyncModal(e.target.checked)}
                          className="w-4 h-4"
                        />
                        <span>Sync Modal</span>
                      </label>
                      <label className="flex items-center gap-2 cursor-pointer">
                        <input
                          type="checkbox"
                          checked={syncRab}
                          onChange={(e) => setSyncRab(e.target.checked)}
                          className="w-4 h-4"
                        />
                        <span>Sync RAB</span>
                      </label>
                    </div>
                  )}
                </div>
              ) : (
                <div className="overflow-y-auto flex-1 px-4 pt-4 pb-24">
                  <form className="space-y-4">
                    <div className="grid grid-cols-[auto_0.8fr_0.6fr_0.4fr_0.6fr_0.5fr_0.3fr_auto_0.5fr_2fr_auto] gap-2 border-b pb-2 font-semibold text-sm text-white text-center">
                      <div>No</div>
                      <div>Inv Num</div>
                      <div>Voucher Num</div>
                      <div>Line Num</div>
                      <div>Kode Group</div>
                      <div>PLU</div>
                      <div>DPP</div>
                      <div></div>
                      <div>PPn</div>
                      <div>Keterangan</div>
                      <div></div>
                    </div>

                    {rows.map((row, index) => (
                      <div
                        key={index}
                        className="grid grid-cols-[auto_0.5fr_0.3fr_0.3fr_0.3fr_0.3fr_0.2fr_auto_0.2fr_1.2fr_auto] gap-2 items-center"
                      >
                        <div className="text-center text-sm text-white">{index + 1}</div>
                        <input
                          type="text"
                          placeholder="Inv Num"
                          value={row.inv_num}
                          onChange={(e) =>
                            handleInputChange(index, 'inv_num', e.target.value)
                          }
                          onFocus={() => setActiveRowIndex(index)}
                          className="border px-2 py-1 rounded w-full"
                          ref={(el) => (inputRefs.current[index] = el)}
                        />
                        <input
                          type="text"
                          placeholder="Voucher Num"
                          value={row.no_sjf}
                          onChange={(e) =>
                            handleInputChange(index, 'no_sjf', e.target.value)
                          }
                          onFocus={() => setActiveRowIndex(index)}
                          className="border px-2 py-1 rounded w-full"
                        />
                        <input
                          type="text"
                          placeholder="Line Num"
                          value={row.line_num}
                          onChange={(e) =>
                            handleInputChange(index, 'line_num', e.target.value)
                          }
                          onFocus={() => setActiveRowIndex(index)}
                          className="border px-2 py-1 rounded w-full"
                        />
                        <input
                          type="text"
                          placeholder="Kode Group"
                          value={row.kd_group}
                          onChange={(e) =>
                            handleInputChange(index, 'kd_group', e.target.value)
                          }
                          onFocus={() => setActiveRowIndex(index)}
                          className="border px-2 py-1 rounded w-full"
                        />
                        <input
                          type="text"
                          placeholder="PLU"
                          value={row.plu}
                          onChange={(e) =>
                            handleInputChange(index, 'plu', e.target.value)
                          }
                          onFocus={() => setActiveRowIndex(index)}
                          className="border px-2 py-1 rounded text-left w-full"
                        />
                        <input
                          type="text"
                          placeholder="DPP"
                          value={row.dpp}
                          onChange={(e) =>
                            handleInputChange(index, 'dpp', formatAmount(e.target.value))
                          }
                          onFocus={() => setActiveRowIndex(index)}
                          className="border px-2 py-1 rounded text-right w-full"
                        />
                        <input
                          type="checkbox"
                          checked={parseFloat(row.ppn) > 0}
                          onChange={(e) =>
                            handleCheckboxChange(index, e.target.checked)
                          }
                        />
                        <input
                          type="text"
                          placeholder="PPn"
                          value={row.ppn}
                          onChange={(e) =>
                            handleInputChange(index, 'ppn', formatAmount(e.target.value))
                          }
                          onFocus={() => setActiveRowIndex(index)}
                          className="border px-2 py-1 rounded text-right w-full"
                        />
                        <input
                          type="text"
                          placeholder="Keterangan"
                          value={row.keterangan}
                          onChange={(e) =>
                            handleInputChange(index, 'keterangan', e.target.value)
                          }
                          onFocus={() => setActiveRowIndex(index)}
                          className="border px-2 py-1 rounded w-full"
                        />
                        {drawerMode !== 'edit' && (
                          <button
                            type="button"
                            onClick={() => handleRemoveRow(index)}
                            className="bg-red-500 text-white p-2 rounded hover:bg-red-600 flex items-center justify-center w-8"
                            title="Hapus Baris"
                          >
                            <HiTrash className="w-4 h-4" />
                          </button>
                        )}
                      </div>
                    ))}
                  </form>
                </div>
              )}

              {/* === Footer === */}
              <div className="fixed bottom-0 left-[-6%] right-0 border-t flex items-center justify-between z-10">
                <div className="trapezium-box text-white text-3xl shadow-md mt-[-8px] flex items-center justify-center h-[50px] w-[250px] bg-yellow-400">
                  {drawerMode === 'edit'
                    ? 'Edit Transaction'
                    : drawerMode === 'import'
                    ? 'Import Data'
                    : drawerMode === 'upload'
                    ? 'Upload Perhitungan PDF'
                    : 'ADD Transaction'}
                </div>

                <div className="flex gap-2 px-2">
                  {(drawerMode === 'import' || drawerMode === 'upload') ? (
                    <>
                      <button
                        type="button"
                        onClick={drawerMode === 'upload' ? handleUploadPDF : handleImport}
                        className="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 flex items-center gap-2"
                      >
                        <FaFileImport className="w-4 h-4" />
                        {drawerMode === 'upload' ? 'Upload' : 'Import'}
                      </button>
                      <button
                        type="button"
                        onClick={resetFilePicker}
                        className="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700 flex items-center gap-2"
                      >
                        <HiRefresh className="w-4 h-4" />
                        Reset
                      </button>
                    </>
                  ) : (
                    <>
                      {drawerMode !== 'edit' && (
                        <>
                          <button
                            type="button"
                            onClick={handleAddRow}
                            className="bg-yellow-500 text-white px-4 py-2 rounded hover:bg-yellow-600 flex items-center gap-2"
                          >
                            <HiPlus className="w-4 h-4" />
                            Add Row
                          </button>
                          <button
                            type="button"
                            onClick={clearForm}
                            className="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700 flex items-center gap-2"
                          >
                            <HiRefresh className="w-4 h-4" />
                            Reset
                          </button>
                        </>
                      )}
                      <button
                        type="submit"
                        onClick={handleSubmit}
                        className="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 flex items-center gap-2"
                      >
                        <FaSave className="w-4 h-4" />
                        {drawerMode === 'edit' ? 'Update' : 'Save'}
                      </button>
                    </>
                  )}
                </div>
              </div>
            </div>
          </BottomDrawer>
        )}

      </div>
    </main>
  );
}
