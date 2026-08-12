import React, { useEffect, useRef, useState, useMemo } from "react";
import Swal from "sweetalert2";
import Select from "react-select";

import ReactDatePicker from "react-datepicker";
import "react-datepicker/dist/react-datepicker.css";
import DatePicker from "react-multi-date-picker";
import "react-multi-date-picker/styles/layouts/mobile.css";

import { formatDate, formatRupiah } from "../utility/textFormatter";
import { useSidebar } from "../components/SidebarContext";
import CenterDrawer from "../components/CenterDrawer";
import ReusableTable from "../components/ReusableTable";
import { useCabang } from "../contexts/CabangContext";
import { FaUndoAlt, FaFileImport, FaSyncAlt, FaHome, FaPencilAlt, FaTimes } from "react-icons/fa";

export default function Rekon_bank() {
    const { cabang } = useCabang();
    const drawerSearchRef = useRef(null);
    const inputSearchRef = useRef(null);
    const { isCollapsed } = useSidebar();
    const [drawerInfo, setDrawerInfo] = useState(null);
    const [drawerRowSelection, setDrawerRowSelection] = useState({});
    const [selectedDrawerRows, setSelectedDrawerRows] = useState([]);

    const jenisBankRef = useRef(null);

    useEffect(() => {
        jenisBankRef.current?.focus();
    }, []);

    const [jenisBank, setJenisBank] = useState("");
    const [typeBank, setTypeBank] = useState("");
    const [rekening, setRekening] = useState("");

    const [typeBankList, setTypeBankList] = useState([]);
    const [rekeningList, setRekeningList] = useState([]);

    const [loading, setLoading] = useState(false);
    const [loadingRekon, setLoadingRekon] = useState(false);

    const [showResult, setShowResult] = useState(false);
    const [selectedRekeningInfo, setSelectedRekeningInfo] = useState(null);

    const [modeRekon, setModeRekon] = useState("");
    const [detailFromSummary, setDetailFromSummary] = useState(false);

    const [dataSummary, setDataSummary] = useState([]);
    const [dataDetail, setDataDetail] = useState([]);

    const [bulkAction, setBulkAction] = useState(null);
    const [processingBulk, setProcessingBulk] = useState(false)
    const bulkActionOptions = useMemo(() => {

        const options = [
            {
                value: "RECON_ALL",
                label:
                    drawerInfo?.jenis === "db"
                        ? "Reconcile Debet"
                        : "Reconcile Kredit"
            },

            {
                value: "RECON_SELECTED",
                label: "Reconcile Selected"
            },
    
            {
                value: "RECON_EXC_SELECTED",
                label: "Reconcile Exc Selected"
            },
    
            {
                value: "UNRECON_SELECTED",
                label: "Unreconcile Selected"
            },
    
            {
                value: "UNRECON_EXC_SELECTED",
                label: "Unreconcile Exc Selected"
            }
        ];
    
        return options;
    
    }, [drawerInfo]);

    const isReconAll =
        bulkAction?.value === "RECON_ALL";

    const disableProcess =
        processingBulk ||
        !bulkAction ||
        (
            !isReconAll &&
            selectedDrawerRows.length === 0
        );
        
    const prosesBulkAction = async () => {

        if (disableProcess) {
            return;
        }
    
        setProcessingBulk(true);
    
        const t0 = performance.now();
    
        try {
    
            Swal.fire({
                title: "Memproses...",
                text: "Mohon tunggu",
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: () => Swal.showLoading()
            });
    
            console.log("======================================");
            console.log("BULK ACTION START");
            console.log("======================================");
    
            /*
            |--------------------------------------------------------------------------
            | REQUEST
            |--------------------------------------------------------------------------
            */
    
            console.time("1. Fetch Request");
    
            const response = await fetch(
                "/api/rekon/bulk-action",
                {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json"
                    },
                    body: JSON.stringify({
                        cabang: cabang,
                        action: bulkAction.value,
                        bank: jenisBank,
                        no_rek: selectedRekeningInfo.no_rek,
                        jenis: drawerInfo.jenis,
                        tgl: drawerInfo.tanggal,
                        rows: selectedDrawerRows
                    })
                }
            );
            
            console.timeEnd("1. Fetch Request");
    
            console.log(
                "Response diterima browser :",
                (performance.now() - t0).toFixed(2),
                "ms"
            );
    
            /*
            |--------------------------------------------------------------------------
            | PARSE JSON
            |--------------------------------------------------------------------------
            */
    
            console.time("2. response.json");
    
            const result = await response.json();
    
            console.timeEnd("2. response.json");
    
            console.log(
                "JSON selesai diparse :",
                (performance.now() - t0).toFixed(2),
                "ms"
            );
    
            if (!result.success) {
    
                throw new Error(
                    result.message ||
                    "Gagal memproses data"
                );
    
            }
    
            /*
            |--------------------------------------------------------------------------
            | UPDATE STATE
            |--------------------------------------------------------------------------
            */
    
            console.time("3. Update React State");
    
            setDataDetail(oldData =>
    
                oldData.map(row =>
    
                    row.tanggal === result.detail_row.tanggal
    
                        ? result.detail_row
    
                        : row
    
                )
    
            );
    
            setDrawerSummary(
                result.drawer.summary
            );
    
            setDrawerData(
                result.drawer.data
            );
    
            setDrawerRowSelection({});
    
            setSelectedDrawerRows([]);
    
            setBulkAction(null);
    
            console.timeEnd("3. Update React State");
    
            console.log(
                "setState selesai dipanggil :",
                (performance.now() - t0).toFixed(2),
                "ms"
            );
    
            /*
            |--------------------------------------------------------------------------
            | REACT RENDER
            |--------------------------------------------------------------------------
            */
    
            requestAnimationFrame(() => {
    
                console.log(
                    "React selesai render :",
                    (performance.now() - t0).toFixed(2),
                    "ms"
                );
    
            });
    
            Swal.fire({
                icon: "success",
                title: "Berhasil",
                text: result.message || "Data berhasil diproses"
            });
    
            console.log(
                "Swal dipanggil :",
                (performance.now() - t0).toFixed(2),
                "ms"
            );
    
        }
        catch (error) {
    
            Swal.fire({
                icon: "error",
                title: "Gagal",
                text: error.message || "Terjadi kesalahan"
            });
    
        }
        finally {
    
            setProcessingBulk(false);
    
            console.log(
                "TOTAL",
                (performance.now() - t0).toFixed(2),
                "ms"
            );
    
            console.log("======================================");
    
        }
    
    };
    
    const detailTableRef = useRef(null);
    
    useEffect(() => {
        if (
            modeRekon === "DETAIL" &&
            detailTableRef.current
        ) {
    
            detailTableRef.current.focus();
    
            detailTableRef.current.scrollTo({
                top: 0,
                behavior: "smooth"
            });
        }
    }, [modeRekon]);

    // =========================
    // LOAD TYPE BANK FRC
    // =========================
    useEffect(() => {
        if (jenisBank !== "FRC") return;

        loadTypeBank();
    }, [jenisBank]);

    // =========================
    // LOAD REKENING REG
    // =========================
    useEffect(() => {
        if (jenisBank !== "REG") return;

        loadRekeningREG();
    }, [jenisBank]);

    // =========================
    // LOAD REKENING FRC
    // =========================
    useEffect(() => {
        if (jenisBank !== "FRC") return;
        if (!typeBank) return;

        loadRekeningFRC();
    }, [typeBank]);

    const loadTypeBank = async () => {
        try {
            setLoading(true);
            const res = await fetch(
                `/api/rekon/type-bank?cabang=${cabang}`
            );

            const data = await res.json();

            setTypeBankList(data);
        } catch (err) {
            console.error(err);
        } finally {
            setLoading(false);
        }
    };

    const loadRekeningREG = async () => {
        try {
            setLoading(true);

            const res = await fetch(
                `/api/rekon/rekening-reg?cabang=${cabang}`
            );

            const data = await res.json();

            setRekeningList(data);
        } catch (err) {
            console.error(err);
        } finally {
            setLoading(false);
        }
    };

    const loadRekeningFRC = async () => {
        try {
            setLoading(true);

            const res = await fetch(
                `/api/rekon/rekening-frc?cabang=${cabang}&jns_bank=${typeBank}`
            );

            const data = await res.json();

            setRekeningList(data);
        } catch (err) {
            console.error(err);
        } finally {
            setLoading(false);
        }
    };

    const rekeningRegOptions = [

        {
            value: "ALL",
            label: "ALL Rekening"
        },
    
        ...rekeningList.map(item => ({
    
            value: item.no_rek,
    
            label:
                `${item.akun} - ${item.no_rek}`
    
        }))
    ];

    const rekeningFrcOptions = [

        {
            value: "ALL",
            label: "ALL Rekening"
        },
    
        ...rekeningList.map(item => ({
    
            value: item.no_rek,
    
            label:
                `${item.site} - ${item.no_rek}`
    
        }))
    ];

    /*
    |--------------------------------------------------------------------------
    | LOAD SUMMARY
    |--------------------------------------------------------------------------
    */

    const loadSummary = async () => {

        const response = await fetch(
            "/api/rekon/summary",
            {
                method: "POST",
                headers: {
                    "Content-Type": "application/json"
                },
                body: JSON.stringify({
                    cabang,
                    jenis_bank: jenisBank,
                    type_bank: typeBank
                })
            }
        );

        const result = await response.json();

        if (!result.success) {

            throw new Error(
                result.message ||
                "Gagal memuat data summary"
            );

        }

        setDataSummary(result.data || []);

        setModeRekon("SUMMARY");

        setShowResult(false);

        return result;

    };

    /*
    |--------------------------------------------------------------------------
    | LOAD DETAIL
    |--------------------------------------------------------------------------
    */

    const loadDetail = async (noRek) => {
        const response = await fetch(
            "/api/rekon/detail",
            {
                method: "POST",
                headers: {
                    "Content-Type": "application/json"
                },
                body: JSON.stringify({
                    cabang,
                    jenis_bank: jenisBank,
                    type_bank: typeBank,
                    no_rek: noRek
                })
            }
        );

        const result = await response.json();

        if (!result.success) {

            throw new Error(
                result.message ||
                "Gagal memuat data detail"
            );

        }

        /*
        |--------------------------------------------------------------------------
        | Cari informasi rekening
        |--------------------------------------------------------------------------
        */

        let rekeningInfo =
            rekeningList.find(
                x => x.no_rek === noRek
            );

        if (!rekeningInfo) {

            rekeningInfo =
                dataSummary.find(
                    x => x.no_rek === noRek
                );

        }

        setSelectedRekeningInfo(
            rekeningInfo || null
        );

        setDataDetail(
            result.data || []
        );
        
        setModeRekon("DETAIL");

        setShowResult(true);

        return result;

    };

    /*
    |--------------------------------------------------------------------------
    | PROSES REKONSILIASI
    |--------------------------------------------------------------------------
    */

    const prosesRekon = async (selectedNoRek = null) => {
        try {
            Swal.fire({
                title: "Proses Rekonsiliasi",
                text: "Mohon tunggu, sedang memproses data...",
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            setLoadingRekon(true);

            /*
            |--------------------------------------------------------------------------
            | Tentukan rekening
            |--------------------------------------------------------------------------
            */

            const noRek =
                selectedNoRek || rekening;

            /*
            |--------------------------------------------------------------------------
            | Apakah berasal dari Summary
            |--------------------------------------------------------------------------
            */

            setDetailFromSummary(
                selectedNoRek !== null
            );

            /*
            |--------------------------------------------------------------------------
            | SUMMARY
            |--------------------------------------------------------------------------
            */

            if (noRek === "ALL") {

                await loadSummary();

                return;

            }

            /*
            |--------------------------------------------------------------------------
            | DETAIL
            |--------------------------------------------------------------------------
            */

            await loadDetail(noRek);

        } catch (error) {

            console.error(error);

            Swal.fire({
                icon: "error",
                title: "Gagal",
                text:
                    error.message ||
                    "Terjadi kesalahan saat proses rekonsiliasi"
            });

        } finally {

            Swal.close();

            setLoadingRekon(false);

        }

    };

    const handleReset = () => {
        setModeRekon("");
        setShowResult(false);
    
        setJenisBank("");
        setTypeBank("");
        setRekening("");
    
        setTypeBankList([]);
        setRekeningList([]);
    
        setDataSummary([]);
        setDataDetail([]);
        setSelectedRekeningInfo(null);
    
        setDetailFromSummary(false);
    };

    const isFilterComplete =
        (jenisBank === "REG" &&
            rekening != null &&
            rekening !== undefined &&
            rekening !== "") ||
        (jenisBank === "FRC" &&
            typeBank != null &&
            typeBank !== "" &&
            rekening != null &&
            rekening !== "");
    
    const totalDetail = dataDetail.reduce(
        (acc, row) => ({
            debet: acc.debet + Number(row.debet || 0),
            kredit: acc.kredit + Number(row.kredit || 0),
            gl_payables: acc.gl_payables + Number(row.gl_payables || 0),
            gl_receivables: acc.gl_receivables + Number(row.gl_receivables || 0),
            cash_in: acc.cash_in + Number(row.cash_in || 0),
            cash_out: acc.cash_out + Number(row.cash_out || 0),
            unrec_debet: acc.unrec_debet + Number(row.unrec_debet || 0),
            unrec_kredit: acc.unrec_kredit + Number(row.unrec_kredit || 0),
            selisih_in: acc.selisih_in + Number(row.selisih_in || 0),
            selisih_out: acc.selisih_out + Number(row.selisih_out || 0),
        }),
        {
            debet: 0,
            kredit: 0,
            gl_payables: 0,
            gl_receivables: 0,
            cash_in: 0,
            cash_out: 0,
            unrec_debet: 0,
            unrec_kredit: 0,
            selisih_in: 0,
            selisih_out: 0,
        }
    );

    const [showDrawer, setShowDrawer] = useState(false);
    const [drawerTitle, setDrawerTitle] = useState("");
    const [drawerData, setDrawerData] = useState([]);
    const [drawerLoading, setDrawerLoading] = useState(false);
    const [drawerFilter, setDrawerFilter] = useState("");
    const [drawerSummary, setDrawerSummary] = useState(null);

    const openUnrecDetail = async (row, jenis) => {

        try {
    
            /*
            |--------------------------------------------------------------------------
            | Simpan informasi drawer
            |--------------------------------------------------------------------------
            */
    
            setDrawerInfo({
                jenis,
                tanggal: row.tanggal,
                cash_in: row.cash_in,
                cash_out: row.cash_out,
                unrec_debet: row.unrec_debet,
                unrec_kredit: row.unrec_kredit
            });
    
            /*
            |--------------------------------------------------------------------------
            | Tampilkan drawer
            |--------------------------------------------------------------------------
            */
    
            setShowDrawer(true);
    
            setDrawerLoading(true);
    
            setDrawerTitle(
                `${jenis === "db" ? "DEBET" : "KREDIT"} - ${formatDate(row.tanggal)}`
            );

            const response = await fetch(
                "/api/rekon/unrec-detail",
                {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json"
                    },
                    body: JSON.stringify({
                        no_rek: selectedRekeningInfo.no_rek,
                        tgl: row.tanggal,
                        jenis,
                        bank: jenisBank
                    })
                }
            );
    
            const result = await response.json();
    
            if (!result.success) {
    
                throw new Error(
                    result.message ||
                    "Gagal mengambil data detail"
                );
    
            }
            
            setBulkAction(null);
            setDrawerSummary(result.summary || null);
            setDrawerData(result.data || []);
            setDrawerRowSelection({});
            setSelectedDrawerRows([]);
    
        } catch (error) {
            Swal.fire({
                icon: "error",
                title: "Gagal",
                text:
                    error.message ||
                    "Gagal memuat detail mutasi"
            });
    
        } finally {
    
            setDrawerLoading(false);
    
        }
    };

    const totalMutasiUnrec = drawerSummary?.total_unrec ?? 0;

    const selisihMutasiVsGL =
        drawerInfo?.jenis === "db"
            ? Number(drawerInfo?.cash_in || 0)
            : Number(drawerInfo?.cash_out || 0);

    const totalMutasiDipilih = selectedDrawerRows.reduce(
        (total, row) => {
    
            if (drawerInfo?.jenis === "db") {
                return total + Number(row.db || 0);
            }
    
            return total + Number(row.cr || 0);
    
        },
        0
    );
    
    const selisihAkhir =
        totalMutasiUnrec -
        totalMutasiDipilih;

    const drawerColumns = [
        {
            id: "select",
    
            header: ({ table }) => (
                <input
                    type="checkbox"
                    checked={table.getIsAllPageRowsSelected()}
                    onChange={table.getToggleAllPageRowsSelectedHandler()}
                />
            ),
    
            cell: ({ row }) => (
                <input
                    type="checkbox"
                    checked={row.getIsSelected()}
                    onChange={row.getToggleSelectedHandler()}
                />
            ),
    
            enableSorting: false,
        },

        {
            accessorKey: "tgl",
            header: "Tanggal",
        },
    
        {
            accessorKey: "no_rek",
            header: "No Rekening",
        },
    
        {
            accessorFn: row =>
                `${row.remark || ""} ${row.remark1 || ""}`,
            id: "keterangan",
            header: "Keterangan",
        },
    
        {
            accessorKey: "db",
            header: "Debet",
            cell: ({ row }) =>
                formatRupiah(row.original.db),
            meta: {
                className: "text-right"
            }
        },
    
        {
            accessorKey: "cr",
            header: "Kredit",
            cell: ({ row }) =>
                formatRupiah(row.original.cr),
            meta: {
                className: "text-right"
            }
        },
    
        {
            id: "status",
            header: "Status",
        
            accessorFn: (row) => {
        
                return row.reconciled === "Y"
                    ? "Reconciled"
                    : "Unreconciled";
        
            },
        
            cell: ({ row }) => {
        
                const status =
                    row.original.reconciled === "Y";
        
                return (
                    <span
                        className={
                            status
                                ? "text-green-600 font-semibold"
                                : "text-red-600 font-semibold"
                        }
                    >
                        {
                            status
                                ? "Reconciled"
                                : "Unreconciled"
                        }
                    </span>
                );
            }
        },
    ];
    
    useEffect(() => {
        const handleShortcut = (e) => {
            if (!isCollapsed) {
                return;
            }
            
            if (e.altKey && e.key.toLowerCase() === "s") {
                e.preventDefault();
                jenisBankRef.current?.focus();
            }

            if (
                e.altKey &&
                e.key.toLowerCase() === "b" &&
                detailFromSummary &&
                modeRekon === "DETAIL"
            ) {
                e.preventDefault();
    
                setModeRekon("SUMMARY");
                setShowResult(false);
                setDetailFromSummary(false);
            }

            if (e.altKey && e.key.toLowerCase() === "f") {
                e.preventDefault();
                inputSearchRef.current?.focus();
            }

            if (
                e.altKey &&
                e.key.toLowerCase() === "t" &&
                modeRekon === "DETAIL"
            ) {
                e.preventDefault();
                detailTableRef.current?.focus();
            }

            if (
                e.key === "Escape" &&
                showDrawer
            ) {
                e.preventDefault();
                setShowDrawer(false);
            }
        };
    
        window.addEventListener("keydown", handleShortcut);
    
        return () => {
            window.removeEventListener("keydown", handleShortcut);
        };
    
    }, [detailFromSummary, modeRekon, showDrawer, isCollapsed]);
    
    const [ftpFiles, setFtpFiles] = useState([]);
    const [loadingFtp, setLoadingFtp] = useState(false);
    const [currentPath, setCurrentPath] = useState("export/gltrans");

    const handleImportGL = async () => {
        // Hide tampilan rekon
        setShowResult(false);
        setModeRekon("IMPORT_GL");
    
        // Kosongkan data lama
        setDataSummary([]);
        setDataDetail([]);
        setSelectedRekeningInfo(null);
    
        try {
    
            setLoadingFtp(true);
    
            const params = new URLSearchParams({
                cabang: cabang
            });
    
            const response = await fetch(`/api/gl?${params.toString()}`);
    
            const result = await response.json();
    
            if (!result.success) {
                throw new Error(result.message);
            }
    
            setCurrentPath(result.path);
            setFtpFiles(result.data || []);
    
        } catch (error) {
    
            console.error(error);
    
            Swal.fire({
                icon: "error",
                title: "Gagal",
                text: error.message
            });
    
        } finally {
    
            setLoadingFtp(false);
    
        }
    
    };

    const handleOpenFolder = async (path) => {
        try {
    
            setLoadingFtp(true);
    
            const response = await fetch(
                `/api/gl?path=${encodeURIComponent(path)}`
            );
    
            const result = await response.json();
    
            if (!result.success) {
                throw new Error(result.message);
            }
    
            setCurrentPath(path);
            setFtpFiles(result.data);
    
        } catch (err) {
    
            Swal.fire({
                icon: "error",
                title: "Gagal",
                text: err.message
            });
    
        } finally {
    
            setLoadingFtp(false);
    
        }
    };

    const [search, setSearch] = useState("");

    const [sortConfig, setSortConfig] = useState({
        key: "name",
        direction: "asc",
    });

    const handleSort = (key) => {
        setSortConfig((prev) => ({
            key,
            direction:
                prev.key === key && prev.direction === "asc"
                    ? "desc"
                    : "asc",
        }));
    };

    const filteredFiles = ftpFiles.filter((item) =>
        item.name.toLowerCase().includes(search.toLowerCase())
    );

    const sortedFiles = [...filteredFiles].sort((a, b) => {

        // Folder selalu di atas
        if (a.type !== b.type) {
            return a.type === "folder" ? -1 : 1;
        }

        let valueA = a[sortConfig.key];
        let valueB = b[sortConfig.key];

        if (sortConfig.key === "size") {
            valueA = valueA ?? 0;
            valueB = valueB ?? 0;
        }

        if (typeof valueA === "string") {
            valueA = valueA.toLowerCase();
            valueB = valueB.toLowerCase();
        }

        if (valueA < valueB) {
            return sortConfig.direction === "asc" ? -1 : 1;
        }

        if (valueA > valueB) {
            return sortConfig.direction === "asc" ? 1 : -1;
        }

        return 0;
    });

    const handleImport = async (item) => {

        const confirm = await Swal.fire({
            icon: "question",
            title: "Import GL",
            html: `
                <div style="text-align:left">
                    <b>Cabang :</b> ${cabang}<br>
                    <b>File :</b> ${item.name}<br><br>
                    Data GL periode aktif akan diperbarui.
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: "Import",
            cancelButtonText: "Batal",
            confirmButtonColor: "#16a34a"
        });
    
        if (!confirm.isConfirmed) {
            return;
        }
    
        try {
    
            Swal.fire({
                title: "Import GL",
                text: "Sedang memproses...",
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: () => Swal.showLoading()
            });
    
            const response = await fetch("/api/gl/import", {
    
                method: "POST",
    
                headers: {
                    "Content-Type": "application/json",
                    "Accept": "application/json"
                },
    
                body: JSON.stringify({
    
                    cabang,
    
                    file: item.name,
    
                    path: item.path
    
                })
    
            });
    
            const result = await response.json();
    
            if (!result.success) {
                throw new Error(result.message);
            }
    
            Swal.fire({
                icon: "success",
                title: "Selesai",
                text: result.message
            });
    
        } catch (error) {
    
            Swal.fire({
                icon: "error",
                title: "Gagal",
                text: error.message
            });
    
        }
    
    };

    useEffect(() => {
        setModeRekon("");
        setJenisBank("");
    }, [cabang]);
    
    const [dateMode, setDateMode] = useState("RANGE");
    const [startDate, setStartDate] = useState(null);
    const [endDate, setEndDate] = useState(null);
    const [selectedDates, setSelectedDates] = useState([]);

    return (
        <main className="flex-1 px-4 py-2 z-10 text-white">
            <div className="h-[calc(100vh-50px)] bg-white/60 rounded-lg p-4 shadow-lg text-gray-800 overflow-hidden">
                {/* HEADER */}
                <div className="box-header text-white shadow-md flex items-center justify-center mx-auto mt-[-17px] mb-4 h-[60px] w-1/2 bg-blue-400 clip-path-custom">
                    <h2 className="text-xl font-semibold">
                        Rekon Bank Cabang {cabang}
                    </h2>
                </div>

                {/* FILTER */}
                <div className="bg-white border border-gray-300 rounded-lg shadow-md p-4 mb-4">
                    <div className="flex justify-between items-end">

                        <div className="flex flex-wrap gap-4 items-end">
                            {/* JENIS BANK */}
                            <div className="w-64">
                                <label className="block text-sm font-semibold mb-1">
                                    Jenis Bank
                                </label>

                                <select
                                    ref={jenisBankRef}
                                    value={jenisBank}
                                    onChange={(e) => {
                                        setModeRekon("");
                                        setShowResult(false);

                                        setJenisBank(e.target.value);

                                        setTypeBank("");
                                        setRekening("");

                                        setTypeBankList([]);
                                        setRekeningList([]);
                                    }}
                                    className="w-full border border-gray-400 rounded px-3 py-2"
                                >
                                    <option value="">
                                        -- Pilih Jenis Bank --
                                    </option>

                                    <option value="REG">
                                        REG
                                    </option>

                                    <option value="FRC">
                                        FRC
                                    </option>
                                </select>
                            </div>

                            {/* TYPE BANK */}
                            {jenisBank === "FRC" && (
                                <div className="w-64">
                                    <label className="block text-sm font-semibold mb-1">
                                        Type Bank
                                    </label>

                                    <select
                                        disabled={loading}
                                        value={typeBank}
                                        onChange={(e) => {
                                            setTypeBank(e.target.value);
                                            setRekening("");
                                        }}
                                        className="w-full border border-gray-400 rounded px-3 py-2"
                                    >
                                        <option value="">
                                            -- Pilih Type Bank --
                                        </option>

                                        {typeBankList.map((item, index) => (
                                            <option
                                                key={index}
                                                value={item.jns_bank}
                                            >
                                                {item.jns_bank}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                            )}

                            {/* REKENING REG */}
                            {jenisBank === "REG" && (
                                <div className="w-72">
                                    <label className="block text-sm font-semibold mb-1">
                                        No Rekening
                                    </label>

                                    <Select
                                        options={rekeningRegOptions}
                                        value={
                                            rekeningRegOptions.find(
                                                x => x.value === rekening
                                            ) || null
                                        }
                                        onChange={(selected) =>
                                            setRekening(
                                                selected?.value || ""
                                            )
                                        }
                                        placeholder="Select No Rek"
                                        isLoading={loading}
                                        isDisabled={loading}
                                        noOptionsMessage={() =>
                                            "Data tidak ditemukan"
                                        }
                                        menuPortalTarget={document.body}
                                        styles={{
                                            menuPortal: (base) => ({
                                                ...base,
                                                zIndex: 9999
                                            }),
                                    
                                            menu: (base) => ({
                                                ...base,
                                                zIndex: 9999
                                            })
                                        }}
                                    />
                                </div>
                            )}

                            {/* REKENING FRC */}
                            {jenisBank === "FRC" && typeBank && (
                                <div className="w-72">

                                    <label className="block text-sm font-semibold mb-1">
                                        No Rekening
                                    </label>

                                    <Select

                                        options={rekeningFrcOptions}

                                        value={
                                            rekeningFrcOptions.find(
                                                x => x.value === rekening
                                            ) || null
                                        }

                                        onChange={(selected) =>
                                            setRekening(
                                                selected?.value || ""
                                            )
                                        }

                                        placeholder="Select No Rek"
                                        isLoading={loading}
                                        isDisabled={loading}
                                        noOptionsMessage={() =>
                                            "Data tidak ditemukan"
                                        }

                                        menuPortalTarget={document.body}
                                        styles={{
                                            menuPortal: (base) => ({
                                                ...base,
                                                zIndex: 9999
                                            }),
                                    
                                            menu: (base) => ({
                                                ...base,
                                                zIndex: 9999
                                            })
                                        }}

                                    />

                                </div>
                            )}

                            {/* BUTTON */}
                            {isFilterComplete && (
                                <div className="flex gap-2">
                                    <button
                                        onClick={() => prosesRekon()}
                                        disabled={loadingRekon}
                                        className="bg-green-600 hover:bg-green-700 disabled:bg-gray-400 text-white px-6 py-2 rounded flex items-center gap-2"
                                    >
                                        <FaSyncAlt className={loadingRekon ? "animate-spin" : ""} />
                                        {loadingRekon ? "Memproses..." : "Rekonsiliasi"}
                                    </button>

                                    <button
                                        onClick={handleReset}
                                        disabled={loadingRekon}
                                        className="bg-gray-500 hover:bg-gray-600 disabled:bg-gray-400 text-white px-6 py-2 rounded flex items-center gap-2"
                                    >
                                        <FaUndoAlt />
                                        Reset
                                    </button>
                                </div>
                            )}
                        </div>

                        {modeRekon === "" && (
                            <button
                                onClick={handleImportGL}
                                className="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded flex items-center gap-2"
                            >
                                <FaFileImport />
                                Import GL
                            </button>
                        )}

                        {modeRekon === "DETAIL" && (
                            <div className="flex items-center gap-3">

                                {/* Dropdown */}
                                <select
                                    value={dateMode}
                                    onChange={(e) => setDateMode(e.target.value)}
                                    className="border rounded px-3 py-2"
                                >
                                    <option value="RANGE">Range Date</option>
                                    <option value="MULTI">Multi Date</option>
                                </select>

                                {/* Range Date */}
                                {dateMode === "RANGE" && (
                                    <>
                                        <ReactDatePicker
                                            selected={startDate}
                                            onChange={setStartDate}
                                            dateFormat="dd/MM/yyyy"
                                            placeholderText="Tanggal Awal"
                                            className="border rounded px-3 py-2 w-40"
                                        />

                                        <span>s/d</span>

                                        <ReactDatePicker
                                            selected={endDate}
                                            onChange={setEndDate}
                                            minDate={startDate}
                                            dateFormat="dd/MM/yyyy"
                                            placeholderText="Tanggal Akhir"
                                            className="border rounded px-3 py-2 w-40"
                                        />
                                    </>
                                )}

                                {/* Multi Date */}
                                {dateMode === "MULTI" && (
                                    <DatePicker
                                        multiple
                                        value={selectedDates}
                                        onChange={setSelectedDates}
                                        format="DD/MM/YYYY"
                                        placeholder="Pilih tanggal"
                                        inputClass="border rounded px-3 py-2 w-72"
                                    />
                                )}

                                {/* Tombol proses */}
                                <button
                                    className="bg-yellow-400 hover:bg-yellow-500 px-6 py-2 rounded flex items-center gap-2 text-white font-medium"
                                >
                                    <FaFileImport />
                                    Proses
                                </button>

                            </div>
                        )}

                    </div>
                </div>

                {/* TABLE */}
                {modeRekon === "SUMMARY" && (
                    <div className="bg-white border border-gray-300 rounded-lg overflow-hidden">
                        <div className="overflow-auto max-h-[620px]">
                            <table className="w-full border-separate border-spacing-0 min-w-[2000px] text-sm mb-5">
                                <thead>
                                    {/* HEADER LEVEL 1 */}
                                    <tr>
                                        <th
                                            colSpan="7"
                                            className="sticky top-0 z-50 bg-black text-white border border-white px-2 py-1 text-center whitespace-nowrap"
                                        >
                                            Data Mutasi
                                        </th>

                                        <th
                                            colSpan="2"
                                            className="sticky top-0 z-50 bg-blue-700 text-white border border-white px-2 py-2 text-center whitespace-nowrap"
                                        >
                                            Data GL
                                        </th>

                                        <th
                                            colSpan="2"
                                            className="sticky top-0 z-50 bg-red-600 text-white border border-white px-2 py-2 text-center whitespace-nowrap"
                                        >
                                            Mutasi VS GL
                                        </th>

                                        <th
                                            colSpan="2"
                                            className="sticky top-0 z-50 bg-gray-500 text-white border border-white px-2 py-2 text-center whitespace-nowrap"
                                        >
                                            Mutasi Unreconciled
                                        </th>

                                        <th
                                            colSpan="2"
                                            className="sticky top-0 z-50 bg-red-600 text-white border border-white px-2 py-2 text-center whitespace-nowrap"
                                        >
                                            Selisih
                                        </th>

                                    </tr>

                                    {/* HEADER LEVEL 2 */}
                                    <tr>

                                        <th className="sticky top-[32px] z-40 bg-black text-white border border-white px-2 py-2 whitespace-nowrap">
                                            Cabang
                                        </th>

                                        <th className="sticky top-[32px] z-40 bg-black text-white border border-white px-2 py-2 whitespace-nowrap">
                                            Jenis Bank
                                        </th>

                                        <th className="sticky top-[32px] z-40 bg-black text-white border border-white px-2 py-2 whitespace-nowrap">
                                            No Rek
                                        </th>

                                        <th className="sticky top-[32px] z-40 bg-black text-white border border-white px-2 py-2 whitespace-nowrap">
                                            Tgl Awal
                                        </th>

                                        <th className="sticky top-[32px] z-40 bg-black text-white border border-white px-2 py-2 whitespace-nowrap">
                                            Tgl Akhir
                                        </th>

                                        <th className="sticky top-[32px] z-40 bg-black text-white border border-white px-2 py-2 whitespace-nowrap">
                                            Debet
                                        </th>

                                        <th className="sticky top-[32px] z-40 bg-black text-white border border-white px-2 py-2 whitespace-nowrap">
                                            Kredit
                                        </th>

                                        <th className="sticky top-[32px] z-40 bg-blue-700 text-white border border-white px-2 py-2 whitespace-nowrap">
                                            GL Payables
                                        </th>

                                        <th className="sticky top-[32px] z-40 bg-blue-700 text-white border border-white px-2 py-2 whitespace-nowrap">
                                            GL Receivables
                                        </th>

                                        <th className="sticky top-[32px] z-40 bg-red-600 text-white border border-white px-2 py-2 whitespace-nowrap">
                                            Sel Cash Out
                                        </th>

                                        <th className="sticky top-[32px] z-40 bg-red-600 text-white border border-white px-2 py-2 whitespace-nowrap">
                                            Sel Cash In
                                        </th>

                                        <th className="sticky top-[32px] z-40 bg-gray-500 text-white border border-white px-2 py-2 whitespace-nowrap">
                                            Debet
                                        </th>

                                        <th className="sticky top-[32px] z-40 bg-gray-500 text-white border border-white px-2 py-2 whitespace-nowrap">
                                            Kredit
                                        </th>

                                        <th className="sticky top-[32px] z-40 bg-red-600 text-white border border-white px-2 py-2 whitespace-nowrap">
                                            Sel Cash Out
                                        </th>

                                        <th className="sticky top-[32px] z-40 bg-red-600 text-white border border-white px-2 py-2 whitespace-nowrap">
                                            Sel Cash In
                                        </th>

                                    </tr>

                                </thead>

                                <tbody>

                                    {dataSummary.length === 0 ? (

                                        <tr>
                                            <td
                                                colSpan="15"
                                                className="text-center py-8 border"
                                            >
                                                Tidak ada data
                                            </td>
                                        </tr>

                                    ) : (

                                        dataSummary.map((row, index) => (

                                            <tr
                                                key={index}
                                                className="hover:bg-gray-50"
                                            >

                                                <td className="border px-2 py-1 whitespace-nowrap">
                                                    {row.cabang}
                                                </td>

                                                <td className="border px-2 py-1 whitespace-nowrap">
                                                    {row.jns_bank}
                                                </td>

                                                <td className="border px-2 py-1 whitespace-nowrap">
                                                    <button
                                                        type="button"
                                                        onClick={() => prosesRekon(row.no_rek)}
                                                        className="
                                                            text-blue-600
                                                            hover:text-blue-800
                                                            hover:underline
                                                            font-medium
                                                        "
                                                    >
                                                        {row.display_rek}
                                                    </button>
                                                </td>

                                                <td className="border px-2 py-1 whitespace-nowrap">
                                                    {row.tgl_awal}
                                                </td>

                                                <td className="border px-2 py-1 whitespace-nowrap">
                                                    {row.tgl_akhir}
                                                </td>

                                                <td className="border px-2 py-1 text-right whitespace-nowrap">
                                                    {formatRupiah(row.debet)}
                                                </td>

                                                <td className="border px-2 py-1 text-right whitespace-nowrap">
                                                    {formatRupiah(row.kredit)}
                                                </td>

                                                <td className="border px-2 py-1 text-right whitespace-nowrap">
                                                    {formatRupiah(row.gl_payables)}
                                                </td>

                                                <td className="border px-2 py-1 text-right whitespace-nowrap">
                                                    {formatRupiah(row.gl_receivables)}
                                                </td>

                                                <td className="bg-red-200 border px-2 py-1 text-right whitespace-nowrap">
                                                    {formatRupiah(row.debet + row.gl_payables)}
                                                </td>

                                                <td className="bg-red-200 border px-2 py-1 text-right whitespace-nowrap">
                                                    {formatRupiah(row.kredit - row.gl_receivables)}
                                                </td>

                                                <td className="border px-2 py-1 text-right whitespace-nowrap">
                                                    {formatRupiah(row.unrec_debet)}
                                                </td>

                                                <td className="border px-2 py-1 text-right whitespace-nowrap">
                                                    {formatRupiah(row.unrec_kredit)}
                                                </td>

                                                <td className="bg-red-200 border px-2 py-1 text-right whitespace-nowrap">
                                                    {formatRupiah(row.debet + row.gl_payables - row.unrec_debet)}
                                                </td>

                                                <td className="bg-red-200 border px-2 py-1 text-right whitespace-nowrap">
                                                    {formatRupiah(row.kredit - row.gl_receivables - row.unrec_kredit)}
                                                </td>

                                            </tr>

                                        ))
                                    )}

                                </tbody>

                            </table>

                        </div>

                    </div>
                )}

                {modeRekon === "DETAIL" && (
                    <div className="relative bg-white border border-gray-300 rounded-lg overflow-hidden">
                        {/* TABEL */}
                        <div
                            ref={detailTableRef}
                            tabIndex={0}
                            className="overflow-auto"
                            style={{
                                height: "calc(100vh - 250px)",
                                paddingBottom: "60px"
                            }}
                        >
                            <table className="w-full border-collapse min-w-[1600px] text-sm">
                                <thead>
                                    {/* HEADER LEVEL 1 */}
                                    <tr>
                                        <th
                                            colSpan="3"
                                            className="sticky top-0 z-40 bg-black text-white border border-white p-2"
                                        >
                                            Data Mutasi
                                        </th>

                                        <th
                                            colSpan="2"
                                            className="sticky top-0 z-40 bg-blue-700 text-white border border-white p-2"
                                        >
                                            Data GL
                                        </th>

                                        <th
                                            colSpan="2"
                                            className="sticky top-0 z-40 bg-red-600 text-white border border-white p-2"
                                        >
                                            Mutasi VS GL
                                        </th>

                                        <th
                                            colSpan="2"
                                            className="sticky top-0 z-40 bg-gray-500 text-white border border-white p-2"
                                        >
                                            Mutasi Unreconciled
                                        </th>

                                        <th
                                            colSpan="2"
                                            className="sticky top-0 z-40 bg-red-600 text-white border border-white p-2"
                                        >
                                            Selisih
                                        </th>

                                    </tr>

                                    {/* HEADER LEVEL 2 */}
                                    <tr>

                                        <th className="sticky top-[37px] z-40 bg-black text-white border border-white p-2">
                                            Tanggal
                                        </th>

                                        <th className="sticky top-[37px] z-50 bg-black text-white border border-white p-2">
                                            Debet
                                        </th>

                                        <th className="sticky top-[37px] z-50 bg-black text-white border border-white p-2">
                                            Kredit
                                        </th>

                                        <th className="sticky top-[37px] z-50 bg-blue-700 text-white border border-white p-2">
                                            GL Payables
                                        </th>

                                        <th className="sticky top-[37px] z-50 bg-blue-700 text-white border border-white p-2">
                                            GL Receivables
                                        </th>

                                        <th className="sticky top-[37px] z-50 bg-red-600 text-white border border-white p-2">
                                            Cash In
                                        </th>

                                        <th className="sticky top-[37px] z-50 bg-red-600 text-white border border-white p-2">
                                            Cash Out
                                        </th>

                                        <th className="sticky top-[37px] z-50 bg-gray-500 text-white border border-white p-2">
                                            Debet
                                        </th>

                                        <th className="sticky top-[37px] z-50 bg-gray-500 text-white border border-white p-2">
                                            Kredit
                                        </th>

                                        <th className="sticky top-[37px] z-50 bg-red-600 text-white border border-white p-2">
                                            Cash In
                                        </th>

                                        <th className="sticky top-[37px] z-50 bg-red-600 text-white border border-white p-2">
                                            Cash Out
                                        </th>

                                    </tr>

                                </thead>

                                <tbody>

                                    {dataDetail.length === 0 ? (

                                        <tr>
                                            <td
                                                colSpan="11"
                                                className="text-center py-8 border"
                                            >
                                                Tidak ada data
                                            </td>
                                        </tr>

                                    ) : (

                                        <>
                                            {dataDetail.map((row, index) => (

                                                <tr key={index}>

                                                    <td className="border p-2 whitespace-nowrap">
                                                        {formatDate(row.tanggal)}
                                                    </td>

                                                    <td className="border p-2 text-right whitespace-nowrap">
                                                        {formatRupiah(row.debet)}
                                                    </td>

                                                    <td className="border p-2 text-right whitespace-nowrap">
                                                        {formatRupiah(row.kredit)}
                                                    </td>

                                                    <td className="border p-2 text-right whitespace-nowrap">
                                                        {formatRupiah(row.gl_payables)}
                                                    </td>

                                                    <td className="border p-2 text-right whitespace-nowrap">
                                                        {formatRupiah(row.gl_receivables)}
                                                    </td>

                                                    <td className="bg-red-200 border p-2 text-right whitespace-nowrap">
                                                        {formatRupiah(row.cash_in)}
                                                    </td>

                                                    <td className="bg-red-200 border p-2 text-right whitespace-nowrap">
                                                        {formatRupiah(row.cash_out)}
                                                    </td>

                                                    <td className="border p-2 whitespace-nowrap">
                                                        <div className="flex justify-end items-center gap-2">
                                                            <span>
                                                                {formatRupiah(row.unrec_debet)}
                                                            </span>
                                                            <button
                                                                onClick={() =>
                                                                    openUnrecDetail(
                                                                        row,
                                                                        "db"
                                                                    )
                                                                }
                                                                className="
                                                                    text-blue-600
                                                                    hover:text-blue-800
                                                                "
                                                                title="Lihat Detail Debet"
                                                            >
                                                                <FaPencilAlt />
                                                            </button>
                                                        </div>
                                                    </td>

                                                    <td className="border p-2 whitespace-nowrap">
                                                        <div className="flex justify-end items-center gap-2">
                                                            <span>
                                                                {formatRupiah(row.unrec_kredit)}
                                                            </span>
                                                            <button
                                                                onClick={() =>
                                                                    openUnrecDetail(
                                                                        row,
                                                                        "cr"
                                                                    )
                                                                }
                                                                className="
                                                                    text-blue-600
                                                                    hover:text-blue-800
                                                                "
                                                                title="Lihat Detail Kredit"
                                                            >
                                                                <FaPencilAlt />
                                                            </button>
                                                        </div>
                                                    </td>

                                                    <td className="bg-red-200 border p-2 text-right whitespace-nowrap">
                                                        {formatRupiah(row.selisih_in)}
                                                    </td>

                                                    <td className="bg-red-200 border p-2 text-right whitespace-nowrap">
                                                        {formatRupiah(row.selisih_out)}
                                                    </td>

                                                </tr>

                                            ))}

                                            {/* TOTAL */}
                                            <tr className="bg-yellow-100 font-bold">

                                                <td className="border p-2">
                                                    TOTAL
                                                </td>

                                                <td className="border p-2 text-right">
                                                    {formatRupiah(totalDetail.debet)}
                                                </td>

                                                <td className="border p-2 text-right">
                                                    {formatRupiah(totalDetail.kredit)}
                                                </td>

                                                <td className="border p-2 text-right">
                                                    {formatRupiah(totalDetail.gl_payables)}
                                                </td>

                                                <td className="border p-2 text-right">
                                                    {formatRupiah(totalDetail.gl_receivables)}
                                                </td>

                                                <td className="border p-2 text-right">
                                                    {formatRupiah(totalDetail.cash_in)}
                                                </td>

                                                <td className="border p-2 text-right">
                                                    {formatRupiah(totalDetail.cash_out)}
                                                </td>

                                                <td className="border p-2 text-right">
                                                    {formatRupiah(totalDetail.unrec_debet)}
                                                </td>

                                                <td className="border p-2 text-right">
                                                    {formatRupiah(totalDetail.unrec_kredit)}
                                                </td>

                                                <td className="border p-2 text-right">
                                                    {formatRupiah(totalDetail.selisih_in)}
                                                </td>

                                                <td className="border p-2 text-right">
                                                    {formatRupiah(totalDetail.selisih_out)}
                                                </td>
                                            </tr>
                                        </>
                                    )}
                                </tbody>
                            </table>
                        </div>

                        {/* STATUS BAR */}
                        {showResult && selectedRekeningInfo && (
                            <div className="absolute bottom-0 left-0 right-0 z-50 bg-blue-100 border-t border-blue-300 px-4 py-2">
                                <div className="flex items-center justify-between">
                                    {/* Tombol kembali */}
                                    <div className="w-40">
                                        {detailFromSummary && (
                                            <button
                                                onClick={() => {

                                                    setModeRekon("SUMMARY");
                                                    setShowResult(false);
                                                    setDetailFromSummary(false);

                                                }}
                                                className="flex items-center gap-2 text-blue-700 hover:text-blue-900 font-medium"
                                            >
                                                <FaHome />
                                                <span>Kembali</span>
                                            </button>
                                        )}
                                    </div>

                                    {/* Informasi rekening */}
                                    <div className="flex justify-center gap-10 text-sm font-medium flex-1">
                                        <div>
                                            Akun :
                                            <b> {selectedRekeningInfo.akun}</b>
                                        </div>

                                        <div>
                                            Jenis Bank :
                                            <b> {selectedRekeningInfo.jns_bank}</b>
                                        </div>

                                        {selectedRekeningInfo.site && (
                                            <div>
                                                Site :
                                                <b> {selectedRekeningInfo.site}</b>
                                            </div>
                                        )}

                                        <div>
                                            No Rek :
                                            <b> {selectedRekeningInfo.no_rek}</b>
                                        </div>
                                    </div>

                                    {/* Spacer kanan agar posisi info tetap center */}
                                    <div className="w-40"></div>
                                </div>
                            </div>
                        )}

                        <CenterDrawer
                            isOpen={showDrawer}
                            onClose={() => setShowDrawer(false)}
                            widthClass="max-w-full"
                        >
                            {/* HEADER */}
                            <div className="flex justify-between items-center mb-4 border-b pb-3">
                                <div>
                                    <h2 className="text-xl font-bold">
                                        Mutasi Detail
                                    </h2>

                                    <div className="text-sm text-gray-500">
                                        {drawerTitle}
                                    </div>

                                </div>

                                <button
                                    onClick={() => setShowDrawer(false)}
                                    className="
                                        flex items-center gap-2
                                        text-red-600
                                        hover:text-red-800
                                        font-medium
                                        transition-colors
                                    "
                                    title="Tutup (Esc)"
                                >
                                    <FaTimes />
                                </button>
                            </div>

                            {drawerInfo && (
                                <div className="grid grid-cols-4 gap-4 mb-4">
                                    {/* Selisih Mutasi vs GL */}
                                    <div className="
                                        bg-blue-50
                                        border
                                        border-blue-200
                                        rounded-lg
                                        p-4
                                    ">
                                        <div className="
                                            text-xs
                                            text-gray-500
                                            uppercase
                                        ">
                                            Selisih Mutasi vs GL
                                        </div>

                                        <div className="
                                            text-xl
                                            font-bold
                                            text-blue-700
                                            mt-1
                                        ">
                                            {formatRupiah(selisihMutasiVsGL)}
                                        </div>

                                    </div>

                                    {/* Total Mutasi /tgl */}
                                    <div className="
                                        bg-gray-50
                                        border
                                        border-gray-200
                                        rounded-lg
                                        p-4
                                    ">

                                        <div className="
                                            text-xs
                                            text-gray-500
                                            uppercase
                                        ">
                                            Total Mutasi Unreconciled
                                        </div>

                                        <div className="
                                            text-xl
                                            font-bold
                                            text-gray-700
                                            mt-1
                                        ">
                                            {formatRupiah(totalMutasiUnrec)}
                                        </div>

                                    </div>

                                    {/* Total Mutasi */}
                                    <div className="
                                        bg-yellow-50
                                        border
                                        border-yellow-200
                                        rounded-lg
                                        p-4
                                    ">

                                        <div className="
                                            text-xs
                                            text-gray-500
                                            uppercase
                                        ">
                                            Total Mutasi Dipilih
                                        </div>

                                        <div className="
                                            text-xl
                                            font-bold
                                            text-yellow-700
                                            mt-1
                                        ">
                                            {formatRupiah(totalMutasiDipilih)}
                                        </div>

                                    </div>

                                    {/* Selisih Akhir */}
                                    <div className="
                                        bg-green-50
                                        border
                                        border-green-200
                                        rounded-lg
                                        p-4
                                    ">

                                        <div className="
                                            text-xs
                                            text-gray-500
                                            uppercase
                                        ">
                                            Selisih Akhir
                                        </div>

                                        <div className="
                                            text-xl
                                            font-bold
                                            text-green-700
                                            mt-1
                                        ">
                                            {formatRupiah(selisihAkhir)}
                                        </div>

                                    </div>
                                </div>

                            )}

                            {/* CONTENT */}

                            {drawerLoading ? (
                                <div className="text-center py-10">
                                    Loading...
                                </div>

                            ) : (

                                <div className="overflow-auto max-h-[70vh]">
                                    <ReusableTable
                                        data={drawerData}
                                        columns={drawerColumns}
                                        searchInputRef={drawerSearchRef}
                                        globalFilter={drawerFilter}
                                        setGlobalFilter={setDrawerFilter}
                                        rowSelection={drawerRowSelection}
                                        setRowSelection={setDrawerRowSelection}
                                        onSelectionChange={setSelectedDrawerRows}
                                        rightElement={

                                            <div className="flex gap-2 items-center">
                                                <div className="w-72">
                                                    <Select
                                                        value={bulkAction}
                                                        onChange={setBulkAction}
                                                        options={bulkActionOptions}
                                                        placeholder="Pilih Aksi..."
                                                        isClearable
                                                        menuPortalTarget={document.body}
                                                        styles={{
                                                            menuPortal: (base) => ({
                                                                ...base,
                                                                zIndex: 9999
                                                            })
                                                        }}
                                                    />
                                
                                                </div>
                                
                                                <button
                                                    onClick={prosesBulkAction}
                                                    disabled={disableProcess}
                                                    className={`
                                                        px-4 py-2 rounded text-white
                                                        ${
                                                            disableProcess
                                                                ? "bg-gray-400 cursor-not-allowed"
                                                                : "bg-green-600 hover:bg-green-700"
                                                        }
                                                    `}
                                                >
                                                    Proses
                                                </button>
                                
                                            </div>
                                
                                        }
                                    />
                                </div>
                            )}
                        </CenterDrawer>
                    </div>
                )}

                {modeRekon === "IMPORT_GL" && (
                    <div className="relative bg-white border border-gray-300 rounded-lg overflow-hidden">

                        {/* HEADER */}
                        <div className="flex justify-between items-center border-b px-4 py-3 bg-gray-50">

                            <div className="flex items-center gap-3">

                                <span className="font-semibold">
                                    {currentPath}
                                </span>

                            </div>

                        </div>

                        {/* TABLE */}
                        <div className="space-y-3">

                            {/* Search */}

                            <div className="flex justify-between items-center px-4 py-2">
                                <input
                                    ref={inputSearchRef}
                                    type="text"
                                    placeholder="Cari nama file"
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    className="border rounded px-3 py-2 w-80"
                                />
                            </div>

                            <div
                                className="overflow-auto border rounded"
                                style={{
                                    height: "calc(100vh - 250px)",
                                    paddingBottom: "60px"
                                }}
                            >

                                <table className="min-w-full border-collapse">

                                    <thead className="sticky top-0 bg-gray-100 z-10">

                                        <tr>

                                            <th className="border px-3 py-2 w-16">
                                                No
                                            </th>

                                            <th
                                                className="border px-3 py-2 text-left cursor-pointer select-none"
                                                onClick={() => handleSort("name")}
                                            >
                                                Nama{" "}
                                                {sortConfig.key === "name" &&
                                                    (sortConfig.direction === "asc" ? "▲" : "▼")}
                                            </th>

                                            <th
                                                className="border px-3 py-2 cursor-pointer select-none"
                                                onClick={() => handleSort("type")}
                                            >
                                                Type{" "}
                                                {sortConfig.key === "type" &&
                                                    (sortConfig.direction === "asc" ? "▲" : "▼")}
                                            </th>

                                            <th
                                                className="border px-3 py-2 cursor-pointer select-none"
                                                onClick={() => handleSort("size")}
                                            >
                                                Size{" "}
                                                {sortConfig.key === "size" &&
                                                    (sortConfig.direction === "asc" ? "▲" : "▼")}
                                            </th>

                                            <th
                                                className="border px-3 py-2 cursor-pointer select-none"
                                                onClick={() => handleSort("modified")}
                                            >
                                                Modified{" "}
                                                {sortConfig.key === "modified" &&
                                                    (sortConfig.direction === "asc" ? "▲" : "▼")}
                                            </th>

                                            <th className="border px-3 py-2 w-40">
                                                Action
                                            </th>

                                        </tr>

                                    </thead>

                                    <tbody>

                                        {loadingFtp && (

                                            <tr>

                                                <td
                                                    colSpan={6}
                                                    className="text-center py-5"
                                                >
                                                    Loading...
                                                </td>

                                            </tr>

                                        )}

                                        {!loadingFtp && sortedFiles.length === 0 && (

                                            <tr>

                                                <td
                                                    colSpan={6}
                                                    className="text-center py-5 text-gray-500"
                                                >
                                                    Tidak ada data.
                                                </td>

                                            </tr>

                                        )}

                                        {!loadingFtp &&
                                            sortedFiles.map((item, index) => (

                                                <tr
                                                    key={item.path}
                                                    className="hover:bg-gray-50"
                                                >

                                                    <td className="border px-3 py-2 text-center">
                                                        {index + 1}
                                                    </td>

                                                    <td className="border px-3 py-2">

                                                        {item.type === "folder"
                                                            ? "📁 "
                                                            : "📄 "}

                                                        {item.name}

                                                    </td>

                                                    <td className="border px-3 py-2 text-center">
                                                        {item.type}
                                                    </td>

                                                    <td className="border px-3 py-2 text-right">

                                                        {item.type === "file"
                                                            ? Number(item.size).toLocaleString()
                                                            : "-"}

                                                    </td>

                                                    <td className="border px-3 py-2 text-center">
                                                        {item.modified}
                                                    </td>

                                                    <td className="border px-3 py-2 text-center">

                                                        {item.type === "folder" ? (

                                                            <button
                                                                className="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded"
                                                                onClick={() =>
                                                                    handleOpenFolder(item.path)
                                                                }
                                                            >
                                                                Open
                                                            </button>

                                                        ) : (

                                                            <button
                                                                className="bg-green-600 hover:bg-green-700 text-white px-3 py-1 rounded"
                                                                onClick={() => handleImport(item)}
                                                            >
                                                                Import
                                                            </button>

                                                        )}

                                                    </td>

                                                </tr>

                                            ))}

                                    </tbody>

                                </table>

                            </div>

                        </div>

                    </div>
                )}
            </div>
        </main>
    );
}