import React, { useState, useEffect, useRef } from "react";
import Swal from "sweetalert2";
import DatePicker from "react-datepicker";
import "react-datepicker/dist/react-datepicker.css";
import ReusableTable from "../components/ReusableTable";
import { useSidebar } from "../components/SidebarContext";
import { useCabang } from "../contexts/CabangContext";
import { formatDate, formatRupiah, formatDateForApi } from "../utility/textFormatter";

import {
  MagnifyingGlassIcon,
  ArrowPathIcon,
  CalendarDaysIcon,
} from "@heroicons/react/24/outline";

export default function MutasiSearch() {
  const { cabang } = useCabang();
  
  const [filter, setFilter] = useState({
    bank: "",
    noRek: "ALL",
  });

  const [searchType, setSearchType] = useState("");
  const [rekeningList, setRekeningList] = useState([]);
  const [loadingRekening, setLoadingRekening] = useState(false);
  const [processing, setProcessing] = useState(false);

  const [tglAwal, setTglAwal] = useState(null);
  const [tglAkhir, setTglAkhir] = useState(null);

  const [data, setData] = useState([]);
  const [loading, setLoading] = useState(false);
  const [globalFilter, setGlobalFilter] = useState("");
  const [selectedRows, setSelectedRows] = useState([]);
  const bankSelectRef = useRef(null);
  const searchInputRef = useRef(null);

  const [selectedAction, setSelectedAction] = useState("");
  const [rowSelection, setRowSelection] = useState({});

  // ================= LOAD REKENING =================
  const loadRekening = async (jenisBank) => {
    try {
        setLoadingRekening(true);

        const res = await fetch(
        `/api/bank-list?cabang=${encodeURIComponent(
            cabang
        )}&jns_bank=${encodeURIComponent(jenisBank)}`
        );

        if (!res.ok) {
        throw new Error(
            `HTTP ${res.status} - ${res.statusText}`
        );
        }

        const data = await res.json();

        setRekeningList(data || []);

    } catch (err) {

        console.error("loadRekening error:", err);

        Swal.fire({
        icon: "error",
        title: "Gagal",
        text: err.message || "Gagal memuat daftar rekening",
        });

    } finally {

        setLoadingRekening(false);

    }
  };

  // ================= FIRST LOAD =================
  useEffect(() => {
    setRekeningList([]);
  }, []);

  // ================= SEARCH =================
  const loadData = async () => {
    const params = new URLSearchParams({
        cabang,
        bank: filter.bank,
        no_rek: filter.noRek || "ALL",
        filter_type: searchType,
        tgl_awal: formatDateForApi(tglAwal),
        tgl_akhir: formatDateForApi(tglAkhir),
    });

    const res = await fetch(
        `/api/mutasi-search?${params.toString()}`
    );

    if (!res.ok) {
        throw new Error("Gagal mengambil data mutasi");
    }

    const result = await res.json();

    setData(result);
  };

  const handleSearch = async () => {
    try {
        setLoading(true);
        await loadData();
    } finally {
        setLoading(false);
    }
  };

  const columns = [
    {
        id: "select",
        enableSorting: false,
        enableGlobalFilter: false,
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
        size: 50,
    },

    {
        header: "Tanggal",
        accessorKey: "tgl",
        size: 110,
        cell: ({ getValue }) => {
        const value = getValue();
        return value ? formatDate(value) : "-";
        },
    },

    {
        header: "Jenis Bank",
        accessorKey: "jns_bank",
        size: 120,
        cell: ({ getValue }) => {
            return getValue() || "-";
        },
    },

    {
        header: "No Rekening",
    
        accessorFn: (row) =>
            row.site && row.site !== "REG"
                ? `${row.no_rek} - ${row.site}`
                : row.no_rek,
    
        id: "no_rekening",
    
        size: 180,
    
        cell: ({ row }) => {
            const noRek = row.original.no_rek;
            const kdToko = row.original.site;
    
            return kdToko && kdToko !== "REG"
                ? `${noRek} - ${kdToko}`
                : noRek;
        },
    },

    {
        header: "Status",
        accessorKey: "reconciled",
        size: 130,
        cell: ({ getValue }) => {
            const value = getValue();

            const isReconciled =
                value === "Y";

            return (
                <span
                    className={`px-2 py-1 rounded text-xs font-semibold ${
                        isReconciled
                            ? "bg-green-100 text-green-700"
                            : "bg-red-100 text-red-700"
                    }`}
                >
                    {isReconciled
                        ? "Reconciled"
                        : "Unreconciled"}
                </span>
            );
        },
    },

    {
        header: "Keterangan",

        accessorFn: (row) =>
            `${row.remark ?? ""} ${row.remark1 ?? ""}`,

        id: "keterangan",

        size: 500,

        cell: ({ row }) => {
            const remark = row.original.remark || "";
            const remark1 = row.original.remark1 || "";

            return (
            <div className="whitespace-normal break-words">
                <div>{remark}</div>

                {remark1 && (
                <div className="text-gray-500 text-xs mt-1">
                    {remark1}
                </div>
                )}
            </div>
            );
        },
    },

    {
        header: "Debit",
        accessorKey: "db",
        size: 140,
        cell: ({ getValue }) => (
        <div className="text-right">
            {formatRupiah(getValue() || 0)}
        </div>
        ),
    },

    {
        header: "Kredit",
        accessorKey: "cr",
        size: 140,
        cell: ({ getValue }) => (
        <div className="text-right">
            {formatRupiah(getValue() || 0)}
        </div>
        ),
    },
  ];

  // ================= RESET =================
  const handleReset = () => {
    // Filter utama
    setFilter({
        bank: "",
        noRek: "ALL",
    });

    // Dropdown rekening
    setRekeningList([]);

    // Tanggal
    setTglAwal(null);
    setTglAkhir(null);

    // Filter tambahan
    setSearchType("");

    // Search box ReusableTable
    setGlobalFilter("");

    // Kosongkan hasil pencarian
    setData([]);

    // Jika menggunakan row selection
    setSelectedRows([]);
    setRowSelection({});
  };
  
  const handleAction = async () => {
    if (selectedRows.length === 0) {
        Swal.fire(
            "Peringatan",
            "Pilih minimal satu record terlebih dahulu",
            "warning"
        );
        return;
    }

    if (!selectedAction) {
        Swal.fire(
            "Peringatan",
            "Pilih action terlebih dahulu",
            "warning"
        );
        return;
    }

    try {

        setProcessing(true);

        const ids = selectedRows.map(item => item.id);

        const response = await fetch(
            `/api/mutasi/${selectedAction}`,
            {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    Accept: "application/json",
                },
                body: JSON.stringify({
                    ids,
                    bank: filter.bank,
                    cabang,
                }),
            }
        );

        const result = await response.json();

        if (!response.ok) {
            throw new Error(
                result.message ||
                "Terjadi kesalahan"
            );
        }

        await Swal.fire(
            "Berhasil",
            result.message,
            "success"
        );

        // refresh data
        await handleSearch();

        // opsional: reset pilihan action
        setSelectedAction("");

        // opsional: kosongkan row terpilih
        setSelectedRows([]);

    } catch (err) {

        Swal.fire(
            "Error",
            err.message ||
            "Terjadi kesalahan",
            "error"
        );

    } finally {

        setProcessing(false);

    }
  };

  const { isCollapsed } = useSidebar();

  useEffect(() => {
    const handleShortcut = (e) => {
        if (!isCollapsed) {
            return;
        }
        
        // ALT + S => Fokus Jenis Bank
        if (e.altKey && e.key.toLowerCase() === "s") {
            e.preventDefault();

            bankSelectRef.current?.focus();
            return;
        }

        // ALT + R => Reset
        if (e.altKey && e.key.toLowerCase() === "r") {
            e.preventDefault();

            handleReset();
        return;
        }
    };

    window.addEventListener("keydown", handleShortcut);

    return () => {
        window.removeEventListener("keydown", handleShortcut);
    };
  }, [isCollapsed]);

  return (
    <main className="flex-1 px-4 py-2 z-10 text-white">
        <div className="h-[calc(100vh-50px)] bg-white/60 rounded-lg p-4 shadow-lg text-gray-800 w-full flex flex-col">

        {/* HEADER */}
        <div className="box-header text-white shadow-md flex items-center justify-center mx-auto mt-[-17px] mb-4 h-[60px] w-1/2 bg-blue-400 clip-path-custom flex-shrink-0">
            <h2 className="text-xl text-center font-semibold">
            Pencarian Record Mutasi Bank Cabang {cabang}
            </h2>
        </div>

        {/* FILTER */}
        <div className="bg-white rounded-xl shadow-md border border-gray-200 p-5 mb-4 flex-shrink-0">

            <div className="grid grid-cols-1 md:grid-cols-6 gap-4">

            {/* JENIS BANK */}
            <div>
                <label className="block text-sm font-semibold mb-2">
                Jenis Bank
                </label>

                <select
                value={filter.bank}
                ref={bankSelectRef}
                onChange={(e) => {
                    const value = e.target.value;

                    setFilter((prev) => ({
                    ...prev,
                    bank: value,
                    noRek: "ALL",
                    }));

                    if (value) {
                    loadRekening(value);
                    } else {
                    setRekeningList([]);
                    }
                }}
                className="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-400 outline-none"
                >
                <option value="">-- Pilih Jenis Bank --</option>
                <option value="REG">Reguler</option>
                <option value="BCA Frc">BCA Frc</option>
                <option value="MDR Frc">MANDIRI Frc</option>
                <option value="CIMB NIAGA Frc">CIMB NIAGA Frc</option>
                <option value="BRI Frc">BRI Frc</option>
                <option value="BNI Frc">BNI Frc</option>
                <option value="BSI Frc">BSI Frc</option>
                </select>
            </div>

            {/* NOMOR REKENING */}
            <div>
                <label className="block text-sm font-semibold mb-2">
                Nomor Rekening
                </label>

                <select
                value={filter.noRek}
                disabled={!filter.bank || loadingRekening}
                onChange={(e) =>
                    setFilter((prev) => ({
                    ...prev,
                    noRek: e.target.value,
                    }))
                }
                className={`
                    w-full border rounded-lg px-3 py-2 outline-none
                    ${
                    !filter.bank
                        ? "bg-gray-100 text-gray-400 border-gray-200 cursor-not-allowed"
                        : "border-gray-300 focus:ring-2 focus:ring-blue-400"
                    }
                `}
                >
                {!filter.bank ? (
                    <option value="">
                    Pilih jenis bank terlebih dahulu
                    </option>
                ) : (
                    rekeningList.map((item) => (
                    <option
                        key={`${item.no_rek}-${item.label}`}
                        value={item.no_rek}
                    >
                        {item.label}
                    </option>
                    ))
                )}
                </select>
            </div>

            {/* TANGGAL AWAL */}
            <div>
                <label className="block text-sm font-semibold mb-2">
                Tanggal Awal
                </label>

                <div className="relative">
                <DatePicker
                    selected={tglAwal}
                    onChange={(date) => {
                    setTglAwal(date);

                    if (
                        tglAkhir &&
                        date &&
                        tglAkhir < date
                    ) {
                        setTglAkhir(null);
                    }
                    }}
                    dateFormat="dd/MM/yyyy"
                    showMonthDropdown
                    showYearDropdown
                    dropdownMode="select"
                    placeholderText="Pilih tanggal awal"
                    autoComplete="off"
                    portalId="root"
                    popperPlacement="bottom-start"
                    popperClassName="datepicker-popper"
                    className="w-full border border-gray-300 rounded-lg px-3 py-2 pr-10 focus:ring-2 focus:ring-blue-400 outline-none"
                />

                <CalendarDaysIcon className="h-5 w-5 text-gray-500 absolute right-3 top-2.5 pointer-events-none" />
                </div>
            </div>

            {/* TANGGAL AKHIR */}
            <div>
                <label className="block text-sm font-semibold mb-2">
                Tanggal Akhir
                </label>

                <div className="relative">
                <DatePicker
                    selected={tglAkhir}
                    onChange={(date) => setTglAkhir(date)}
                    minDate={tglAwal}
                    disabled={!tglAwal}
                    dateFormat="dd/MM/yyyy"
                    showMonthDropdown
                    showYearDropdown
                    dropdownMode="select"
                    placeholderText={
                    !tglAwal
                        ? "Pilih tanggal awal dulu"
                        : "Pilih tanggal akhir"
                    }
                    autoComplete="off"
                    portalId="root"
                    popperPlacement="bottom-start"
                    popperClassName="datepicker-popper"
                    className={`
                    w-full rounded-lg px-3 py-2 pr-10 outline-none border
                    ${
                        !tglAwal
                        ? "bg-gray-100 text-gray-400 border-gray-200 cursor-not-allowed"
                        : "border-gray-300 focus:ring-2 focus:ring-blue-400"
                    }
                    `}
                />

                <CalendarDaysIcon className="h-5 w-5 text-gray-500 absolute right-3 top-2.5 pointer-events-none" />
                </div>
            </div>

            {/* FILTER */}
            <div>
                <label className="block text-sm font-semibold mb-2">
                Filter
                </label>

                <select
                value={searchType}
                onChange={(e) => setSearchType(e.target.value)}
                className="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-400 outline-none"
                >
                <option value="">All</option>
                <option value="debit_only">
                    Debet Only
                </option>
                <option value="credit_only">
                    Credit Only
                </option>
                <option value="debit_belum_jurnal">
                    Debet Belum Jurnal
                </option>
                </select>
            </div>

            {/* BUTTON */}
            <div className="flex items-end gap-2">
                <button
                onClick={handleSearch}
                disabled={loading}
                className={`
                    flex items-center gap-2 px-4 py-2 rounded-lg shadow transition text-white
                    ${
                    loading
                        ? "bg-blue-300 cursor-not-allowed"
                        : "bg-blue-500 hover:bg-blue-600"
                    }
                `}
                >
                <MagnifyingGlassIcon className="h-5 w-5" />
                {loading ? "Searching..." : "Search"}
                </button>

                <button
                onClick={handleReset}
                className="flex items-center gap-2 bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg shadow transition"
                >
                <ArrowPathIcon className="h-5 w-5" />
                Reset
                </button>
            </div>

            </div>
        </div>

        {/* AREA TABEL */}
        <div className="flex-1 min-h-0 overflow-auto">
            <ReusableTable
                data={data}
                columns={columns}
                pageSizeOptions={[15, 20, 50, 100, "all"]}
                globalFilter={globalFilter}
                setGlobalFilter={setGlobalFilter}
                onSelectionChange={setSelectedRows}
                setRowSelection={setRowSelection}
                rowSelection={rowSelection}
                searchInputRef={searchInputRef}
                rightElement={
                    <div className="flex items-center gap-2">

                        <select
                            value={selectedAction}
                            onChange={(e) => setSelectedAction(e.target.value)}
                            className="border border-gray-300 rounded px-3 py-2 text-sm min-w-[180px]"
                        >
                            <option value="">
                                Pilih Action
                            </option>

                            <option value="reconcile">
                                Reconcile Selected
                            </option>

                            <option value="unreconcile">
                                Unreconcile Selected
                            </option>

                            <option value="journal">
                                Journal
                            </option>
                        </select>

                        <button
                            onClick={handleAction}
                            disabled={
                                processing ||
                                !selectedAction ||
                                selectedRows.length === 0
                            }
                            className={`
                                flex items-center gap-2 px-4 py-2 rounded text-white text-sm transition
                                ${
                                    processing
                                        ? "bg-blue-300 cursor-wait"
                                        : selectedAction &&
                                        selectedRows.length > 0
                                            ? "bg-blue-500 hover:bg-blue-600"
                                            : "bg-gray-400 cursor-not-allowed"
                                }
                            `}
                        >
                            {processing && (
                                <div className="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin" />
                            )}

                            {processing
                                ? "Processing..."
                                : "Execute"}
                        </button>
                    </div>
                }
            />
        </div>

        </div>
    </main>
  );
}