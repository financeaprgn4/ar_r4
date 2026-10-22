import React, {
    useCallback,
    useEffect,
    useMemo,
    useRef,
    useState
} from "react";

import Swal from "sweetalert2";
import axios from "../config/axiosInstance";

import { useCabang } from "../contexts/CabangContext";
import { useSidebar } from "../components/SidebarContext";

import {
    FaUpload,
    FaFileExcel,
    FaSyncAlt,
    FaSearch,
    FaDownload,
    FaEdit,
    FaCheckCircle,
    FaExclamationTriangle,
    FaReceipt,
    FaMoneyBillWave,
    FaUniversity,
    FaExchangeAlt,
    FaChevronDown,
    FaChevronUp,
    FaTrash,
    FaCloudUploadAlt,
    FaCalendarAlt,
    FaTimesCircle,
    FaHistory,
    FaLink,
    FaInfoCircle,
} from "react-icons/fa";


/*
|--------------------------------------------------------------------------
| CONSTANT
|--------------------------------------------------------------------------
*/

const EMPTY_SUMMARY = {
    total: 0,
    match: 0,
    mutasi_only: 0,
    receipt_only: 0,
    nominal_different: 0,
    account_different: 0,
    mutasi_amount: 0,
    receipt_amount: 0,
    difference_amount: 0
};


/*
|--------------------------------------------------------------------------
| HELPER
|--------------------------------------------------------------------------
*/

const formatCurrency = (value) => {

    const number = Number(value || 0);

    return new Intl.NumberFormat("id-ID", {
        style: "currency",
        currency: "IDR",
        minimumFractionDigits: 0
    }).format(number);

};


const formatNumber = (value) => {

    return new Intl.NumberFormat("id-ID").format(
        Number(value || 0)
    );

};


const formatDate = (value) => {

    if (!value) {
        return "-";
    }

    const valueString = String(value);

    const dateOnly = valueString.match(
        /^(\d{4})-(\d{2})-(\d{2})$/
    );

    if (dateOnly) {

        return `${dateOnly[3]}/${dateOnly[2]}/${dateOnly[1]}`;

    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {

        return value;

    }

    return date.toLocaleDateString(
        "id-ID",
        {
            day: "2-digit",
            month: "2-digit",
            year: "numeric"
        }
    );

};


const getErrorMessage = (
    error,
    fallback = "Terjadi kesalahan."
) => {

    return (
        error?.response?.data?.message ||
        error?.message ||
        fallback
    );

};


/*
|--------------------------------------------------------------------------
| RESULT CATEGORY
|--------------------------------------------------------------------------
|
| Backend boleh tetap mengirim:
|
| MATCH
| MUTASI_ONLY
| RECEIPT_ONLY
| NOMINAL_DIFFERENT
| ACCOUNT_DIFFERENT
|
| Frontend mengelompokkannya menjadi:
|
| MATCH
| MUTASI_ONLY
| RECEIPT_ONLY
| MATCH_SELISIH
|
*/

const getResultCategory = (item) => {

    const status =
        String(item?.status || "")
            .trim()
            .toUpperCase();

    if (status === "MATCH") {

        return "MATCH";

    }

    if (status === "MUTASI_ONLY") {

        return "MUTASI_ONLY";

    }

    if (status === "RECEIPT_ONLY") {

        return "RECEIPT_ONLY";

    }

    if (
        status === "NOMINAL_DIFFERENT" ||
        status === "ACCOUNT_DIFFERENT" ||
        status === "MATCH_SELISIH"
    ) {

        return "MATCH_SELISIH";

    }

    return status;

};


/*
|--------------------------------------------------------------------------
| STATUS BADGE
|--------------------------------------------------------------------------
*/

function StatusBadge({ status }) {

    const category =
        getResultCategory({
            status
        });

    const config = {

        MATCH: {

            label: "DATA MATCH",

            icon: (
                <FaCheckCircle />
            ),

            className:
                "bg-green-100 text-green-700 border-green-200"

        },

        MUTASI_ONLY: {

            label: "MUTASI ONLY",

            icon: (
                <FaExclamationTriangle />
            ),

            className:
                "bg-yellow-100 text-yellow-700 border-yellow-200"

        },

        RECEIPT_ONLY: {

            label: "RECEIPT ONLY",

            icon: (
                <FaReceipt />
            ),

            className:
                "bg-orange-100 text-orange-700 border-orange-200"

        },

        MATCH_SELISIH: {

            label: "MATCH SELISIH",

            icon: (
                <FaMoneyBillWave />
            ),

            className:
                "bg-red-100 text-red-700 border-red-200"

        }

    };


    const item =
        config[category] || {

            label:
                status || "UNKNOWN",

            icon: (
                <FaExchangeAlt />
            ),

            className:
                "bg-gray-100 text-gray-600 border-gray-200"

        };


    return (

        <span
            className={`
                inline-flex
                items-center
                gap-1.5
                px-2.5
                py-1
                rounded-full
                border
                text-xs
                font-semibold
                whitespace-nowrap
                ${item.className}
            `}
        >

            {item.icon}

            {item.label}

        </span>

    );

}


/*
|--------------------------------------------------------------------------
| SUMMARY CARD
|--------------------------------------------------------------------------
*/

function SummaryCard({
    title,
    value,
    subtitle,
    icon,
    className = ""
}) {

    return (

        <div
            className={`
                bg-white
                border
                rounded-xl
                shadow-sm
                p-4
                ${className}
            `}
        >

            <div className="
                flex
                items-start
                justify-between
                gap-3
            ">

                <div className="min-w-0">

                    <p className="
                        text-xs
                        uppercase
                        tracking-wide
                        font-semibold
                        text-gray-500
                    ">
                        {title}
                    </p>

                    <p className="
                        mt-1
                        text-xl
                        sm:text-2xl
                        font-bold
                        text-gray-800
                    ">
                        {value}
                    </p>

                    {subtitle && (

                        <p className="
                            mt-1
                            text-xs
                            text-gray-500
                        ">
                            {subtitle}
                        </p>

                    )}

                </div>


                <div className="
                    w-10
                    h-10
                    rounded-lg
                    bg-gray-100
                    flex
                    items-center
                    justify-center
                    text-gray-600
                    shrink-0
                ">
                    {icon}
                </div>

            </div>

        </div>

    );

}

/*
|--------------------------------------------------------------------------
| MAIN COMPONENT
|--------------------------------------------------------------------------
*/

export default function Receipt() {

    /*
    |--------------------------------------------------------------------------
    | CONTEXT
    |--------------------------------------------------------------------------
    */

    const { cabang } = useCabang();

    const {
        isCollapsed,
        setIsCollapsed
    } = useSidebar();


    /*
    |--------------------------------------------------------------------------
    | TAB
    |--------------------------------------------------------------------------
    */

    const [activeTab, setActiveTab] =
        useState("reconciliation");


    /*
    |--------------------------------------------------------------------------
    | PERIOD
    |--------------------------------------------------------------------------
    */

    const [period, setPeriod] =
        useState(null);

    const [loadingPeriod, setLoadingPeriod] =
        useState(false);


    /*
    |--------------------------------------------------------------------------
    | RECONCILIATION DATA
    |--------------------------------------------------------------------------
    */

    const [data, setData] =
        useState([]);

    const [summary, setSummary] =
        useState({
            ...EMPTY_SUMMARY
        });


    /*
    |--------------------------------------------------------------------------
    | LOADING
    |--------------------------------------------------------------------------
    */

    const [loading, setLoading] =
        useState(false);

    const [uploading, setUploading] =
        useState(false);

    const [exporting, setExporting] =
        useState(false);

    const [downloadLoading, setDownloadLoading] =
        useState({
            FRC: false,
            REG: false
        });

    const [loadingAccounts, setLoadingAccounts] =
        useState(false);


    /*
    |--------------------------------------------------------------------------
    | UPLOAD
    |--------------------------------------------------------------------------
    */

    const fileInputRef =
        useRef(null);

    const [selectedFiles, setSelectedFiles] =
        useState([]);

    const [updateMutasiText, setUpdateMutasiText] = useState("");
    const [updateMutasiRows, setUpdateMutasiRows] = useState([]);
    const [updateMutasiHeaders, setUpdateMutasiHeaders] = useState([]);
    const [updatingMutasi, setUpdatingMutasi] = useState(false);

    /*
    |--------------------------------------------------------------------------
    | PARSE DATA EXCEL KE TABEL HTML
    |--------------------------------------------------------------------------
    */

    const handleParseUpdateMutasi = (value) => {
        setUpdateMutasiText(value);

        if (!value.trim()) {
            setUpdateMutasiHeaders([]);
            setUpdateMutasiRows([]);
            return;
        }

        const lines = value
            .replace(/\r\n/g, "\n")
            .replace(/\r/g, "\n")
            .split("\n")
            .filter((line) => line.trim() !== "");

        const parsedRows = lines.map((line) =>
            line.split("\t").map((cell) => cell.trim())
        );

        if (parsedRows.length === 0) {
            setUpdateMutasiHeaders([]);
            setUpdateMutasiRows([]);
            return;
        }

        const headers = parsedRows[0];
        const rows = parsedRows.slice(1).map((cells, index) => ({
            _rowNumber: index + 1,
            cells: headers.map((_, columnIndex) =>
                cells[columnIndex] ?? ""
            ),
        }));

        setUpdateMutasiHeaders(headers);
        setUpdateMutasiRows(rows);
    };

    const resetUpdateMutasi = () => {
        setUpdateMutasiText("");
        setUpdateMutasiHeaders([]);
        setUpdateMutasiRows([]);
    };
    
    const handleProcessUpdateMutasi = async () => {
        if (!cabang) {
            await Swal.fire(
                "Validasi Gagal",
                "Cabang belum tersedia.",
                "warning"
            );
            return;
        }

        if (!updateMutasiHeaders.length || !updateMutasiRows.length) {
            await Swal.fire(
                "Data Kosong",
                "Tempelkan data Excel dan tampilkan tabel terlebih dahulu.",
                "warning"
            );
            return;
        }

        const normalizeHeader = (value) =>
            String(value ?? "")
                .trim()
                .toLowerCase()
                .replace(/[_\s]+/g, " ");

        const headerMap = {};

        updateMutasiHeaders.forEach((header, index) => {
            const normalized = normalizeHeader(header);

            // Jika header duplikat, gunakan posisi kolom pertama.
            if (
                normalized &&
                !Object.prototype.hasOwnProperty.call(
                    headerMap,
                    normalized
                )
            ) {
                headerMap[normalized] = index;
            }
        });

        const aliases = {
            mutasi: {
                no_rek: [
                    "no rek",
                    "no rekening",
                    "no_rek",
                    "nomor rekening",
                ],
                tgl: ["tgl", "tanggal"],
                remark: ["remark", "keterangan"],
                cr: ["cr", "credit", "kredit"],
                dept: ["dept", "kategori", "category"],
                site: ["site"],
                remark1: [
                    "remark1",
                    "remark 1",
                    "keterangan1",
                    "keterangan 1",
                ],
            },

            receipt: {
                remittance_bank_account: [
                    "remittance_bank_account",
                    "remittance bank account",
                ],
                receipt_number: [
                    "receipt_number",
                    "receipt number",
                ],
                receipt_date: [
                    "receipt_date",
                    "receipt date",
                    "tgl",
                    "tanggal",
                ],
                category: ["category", "kategori"],
                site: ["site"],
                note: ["note", "catatan"],
            },
        };

        const buildColumnIndex = (fieldAliases) => {
            const result = {};

            for (const [field, names] of Object.entries(fieldAliases)) {
                const matchedName = names.find((name) =>
                    Object.prototype.hasOwnProperty.call(
                        headerMap,
                        normalizeHeader(name)
                    )
                );

                if (matchedName !== undefined) {
                    result[field] = headerMap[normalizeHeader(matchedName)];
                }
            }

            return result;
        };

        const mutasiColumns = buildColumnIndex(aliases.mutasi);
        const receiptColumns = buildColumnIndex(aliases.receipt);

        const hasRequiredColumns = (columns, requiredFields) =>
            requiredFields.every(
                (field) => columns[field] !== undefined
            );

        // SITE tidak wajib untuk mutasi_detail.
        const mutasiRequired = [
            "no_rek",
            "tgl",
            "remark",
            "cr",
            "dept",
        ];

        // Category, site, dan note tidak wajib untuk receipt.
        const receiptRequired = [
            "remittance_bank_account",
            "receipt_number",
            "receipt_date",
        ];

        let type;
        let columnIndex;
        let requiredFields;
        let endpoint;
        let processTitle;
        let successTitle;

        if (hasRequiredColumns(mutasiColumns, mutasiRequired)) {
            type = "mutasi_detail";
            columnIndex = mutasiColumns;
            requiredFields = mutasiRequired;
            endpoint = "/api/rekon/update-mutasi";
            processTitle = "Proses Update Mutasi?";
            successTitle = "Update Mutasi Selesai";
        } else if (
            hasRequiredColumns(receiptColumns, receiptRequired)
        ) {
            type = "receipt";
            columnIndex = receiptColumns;
            requiredFields = receiptRequired;
            endpoint = "/api/rekon/update-receipt";
            processTitle = "Proses Update Receipt?";
            successTitle = "Update Receipt Selesai";
        } else {
            await Swal.fire({
                icon: "warning",
                title: "Header Tidak Sesuai",
                html: `
                    <div style="text-align:left;line-height:1.8">
                        <p><strong>Untuk mutasi_detail:</strong></p>
                        <p>NO REK, TGL, REMARK, CR, DEPT (SITE opsional)</p>
                        <hr style="margin:10px 0">
                        <p><strong>Untuk receipt:</strong></p>
                        <p>
                            REMITTANCE_BANK_ACCOUNT, RECEIPT_NUMBER,
                            RECEIPT_DATE
                        </p>
                        <p>
                            CATEGORY, SITE, dan NOTE/CATATAN bersifat opsional.
                        </p>
                    </div>
                `,
            });
            return;
        }

        const records = [];
        const invalidRows = [];

        updateMutasiRows.forEach((row) => {
            const getValue = (field) => {
                const index = columnIndex[field];

                if (index === undefined) return "";

                return String(row.cells[index] ?? "").trim();
            };

            let record;

            if (type === "mutasi_detail") {
                record = {
                    no_rek: getValue("no_rek"),
                    tgl: getValue("tgl"),
                    remark: getValue("remark"),
                    cr: getValue("cr"),
                    dept: getValue("dept"),
                    site: getValue("site"),
                };

                // REMARK1 opsional.
                if (columnIndex.remark1 !== undefined) {
                    record.remark1 = getValue("remark1");
                }
            } else {
                record = {
                    remittance_bank_account: getValue(
                        "remittance_bank_account"
                    ),
                    receipt_number: getValue("receipt_number"),
                    receipt_date: getValue("receipt_date"),
                };

                /*
                * Field opsional receipt:
                * hanya dikirim jika kolom tersedia dan nilainya
                * tidak kosong. Sel kosong tidak akan menghapus data lama.
                */
                ["category", "site", "note"].forEach((field) => {
                    if (columnIndex[field] === undefined) {
                        return;
                    }

                    const value = getValue(field);

                    if (value !== "") {
                        record[field] = value;
                    }
                });
            }

            const missingFields = requiredFields.filter(
                (field) => !String(record[field] ?? "").trim()
            );

            if (missingFields.length > 0) {
                invalidRows.push({
                    row: row._rowNumber,
                    fields: missingFields,
                });
                return;
            }

            records.push(record);
        });

        const escapeHtml = (value) =>
            String(value).replace(/[&<>"']/g, (char) => ({
                "&": "&amp;",
                "<": "&lt;",
                ">": "&gt;",
                '"': "&quot;",
                "'": "&#39;",
            })[char]);

        if (invalidRows.length > 0) {
            const details = invalidRows
                .slice(0, 20)
                .map(
                    (item) =>
                        `Baris ${item.row}: ${item.fields.join(", ")}`
                );

            await Swal.fire({
                icon: "warning",
                title: "Data Belum Lengkap",
                html: `
                    <div style="text-align:left;line-height:1.8">
                        ${details
                            .map(
                                (item) =>
                                    `<div>${escapeHtml(item)}</div>`
                            )
                            .join("")}
                        ${
                            invalidRows.length > 20
                                ? `<div>Dan ${
                                    invalidRows.length - 20
                                } baris lainnya.</div>`
                                : ""
                        }
                    </div>
                `,
            });

            return;
        }

        if (records.length === 0) {
            await Swal.fire(
                "Data Kosong",
                "Tidak ada baris valid untuk diproses.",
                "warning"
            );
            return;
        }

        const escapedCabang = escapeHtml(cabang);

        const optionalFieldsInfo = type === "receipt"
            ? `
                <div style="margin-top:10px">
                    Category, Site, dan Note hanya akan diperbarui
                    jika kolom tersedia dan selnya berisi nilai.
                    Sel kosong tidak mengubah nilai lama.
                </div>
            `
            : `
                <div style="margin-top:10px">
                    Jika SITE diisi, DEPT dan SITE akan diperbarui
                    sesuai aturan backend. Jika SITE kosong, hanya
                    DEPT yang akan diperbarui.
                </div>
            `;

        const confirmation = await Swal.fire({
            icon: "question",
            title: processTitle,
            html: `
                <div style="text-align:left;line-height:1.8">
                    <div>Jenis data:
                        <strong>${
                            type === "mutasi_detail"
                                ? "Mutasi Detail"
                                : "Receipt"
                        }</strong>
                    </div>
                    <div>Cabang: <strong>${escapedCabang}</strong></div>
                    <div>Jumlah data: <strong>${records.length}</strong></div>
                    ${optionalFieldsInfo}
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: "Ya, Proses Update",
            cancelButtonText: "Batal",
            confirmButtonColor: "#2563eb",
        });

        if (!confirmation.isConfirmed) return;

        setUpdatingMutasi(true);

        try {
            const response = await axios.post(endpoint, {
                cabang,
                records,
            });

            const result = response?.data ?? {};

            await Swal.fire({
                icon: result.success ? "success" : "warning",
                title: result.success
                    ? successTitle
                    : "Update Selesai dengan Catatan",
                width: 650,
                html: `
                    <div style="text-align:left;line-height:1.8">
                        <div>Total data:
                            <strong>${result.total ?? records.length}</strong>
                        </div>
                        <div>Berhasil diperbarui:
                            <strong>${result.updated ?? 0}</strong>
                        </div>
                        <div>Dilewati:
                            <strong>${result.skipped ?? 0}</strong>
                        </div>
                        <div>Gagal:
                            <strong>${result.failed ?? 0}</strong>
                        </div>
                        ${
                            Array.isArray(result.errors) &&
                            result.errors.length
                                ? `
                                    <hr style="margin:12px 0">
                                    <div><strong>
                                        Detail error (maksimal 10):
                                    </strong></div>
                                    <ul style="padding-left:20px;
                                        max-height:250px;overflow-y:auto">
                                        ${result.errors
                                            .slice(0, 10)
                                            .map((error) => {
                                                const message =
                                                    typeof error === "string"
                                                        ? error
                                                        : `Baris ${error.row}: ${error.message}`;

                                                return `<li>${escapeHtml(message)}</li>`;
                                            })
                                            .join("")}
                                    </ul>
                                    ${
                                        result.errors.length > 10
                                            ? `<div>Dan ${
                                                result.errors.length - 10
                                            } error lainnya.</div>`
                                            : ""
                                    }
                                `
                                : ""
                        }
                    </div>
                `,
            });
        } catch (error) {
            console.error(
                `Update ${type} error:`,
                error.response?.status,
                error.response?.data || error.message
            );

            const data = error.response?.data;

            const message =
                data?.message ||
                data?.error ||
                error.message ||
                `Terjadi kesalahan saat memperbarui ${type}.`;

            await Swal.fire({
                icon: "error",
                title: `Update Gagal (${
                    error.response?.status || "Network Error"
                })`,
                text: message,
                footer: data?.errors
                    ? Object.values(data.errors).flat().join(" | ")
                    : undefined,
            });
        } finally {
            setUpdatingMutasi(false);
        }
    };


    /*
    |--------------------------------------------------------------------------
    | RECON FILTER
    |--------------------------------------------------------------------------
    */

    const [reconType, setReconType] =
        useState("REG");


    /*
    | Daftar rekening berdasarkan jenis rekon
    */

    const [accounts, setAccounts] =
        useState([]);


    /*
    | Rekening yang dipilih untuk menjalankan rekon
    */

    const [reconAccount, setReconAccount] =
        useState("ALL");


    /*
    |--------------------------------------------------------------------------
    | RESULT FILTER
    |--------------------------------------------------------------------------
    */

    const [resultFilter, setResultFilter] =
        useState("ALL");


    /*
    |--------------------------------------------------------------------------
    | SEARCH
    |--------------------------------------------------------------------------
    */

    const [search, setSearch] =
        useState("");


    /*
    |--------------------------------------------------------------------------
    | FILTER PANEL
    |--------------------------------------------------------------------------
    */

    const [showFilter, setShowFilter] =
        useState(false);


    /*
    |--------------------------------------------------------------------------
    | DETAIL
    |--------------------------------------------------------------------------
    */

    const [expandedRow, setExpandedRow] =
        useState(null);


    /*
    |--------------------------------------------------------------------------
    | RECON STATUS
    |--------------------------------------------------------------------------
    |
    | false = belum menjalankan rekon
    | true  = hasil rekon tersedia
    |
    */

    const [
        reconciliationExecuted,
        setReconciliationExecuted
    ] = useState(false);


    /*
    |--------------------------------------------------------------------------
    | RESET RECONCILIATION
    |--------------------------------------------------------------------------
    */

    const resetReconciliation =
        useCallback(() => {

            setData([]);

            setSummary({
                ...EMPTY_SUMMARY
            });

            setExpandedRow(null);

            setReconciliationExecuted(
                false
            );

        }, []);


    /*
    |--------------------------------------------------------------------------
    | RESET RESULT FILTER
    |--------------------------------------------------------------------------
    */

    const resetResultFilter = () => {

        setResultFilter("ALL");

        setSearch("");

        setExpandedRow(null);

    };


    /*
    |--------------------------------------------------------------------------
    | GET ACTIVE PERIOD
    |--------------------------------------------------------------------------
    */

    const fetchActivePeriod =
        useCallback(
            async () => {

                if (!cabang) {

                    setPeriod(null);

                    resetReconciliation();

                    setAccounts([]);

                    return;

                }


                setLoadingPeriod(true);


                /*
                |--------------------------------------------------------------------------
                | Bersihkan data lama ketika cabang berubah
                |--------------------------------------------------------------------------
                */

                setPeriod(null);

                resetReconciliation();

                setAccounts([]);

                setReconAccount("ALL");


                try {

                    const response =
                        await axios.get(
                            "/api/rekon/period",
                            {
                                params: {
                                    cabang
                                }
                            }
                        );


                    const result =
                        response?.data || {};


                    if (
                        !result.success ||
                        !result.period
                    ) {

                        setPeriod(null);

                        await Swal.fire({

                            icon: "warning",

                            title:
                                "Periode Tidak Ditemukan",

                            text:
                                result.message ||
                                `Tidak ditemukan periode Mutasi aktif untuk cabang ${cabang}.`

                        });

                        return;

                    }


                    setPeriod(
                        result.period
                    );

                } catch (error) {

                    setPeriod(null);

                    await Swal.fire({

                        icon: "error",

                        title:
                            "Gagal Memuat Periode",

                        text:
                            getErrorMessage(
                                error,
                                "Periode aktif gagal dimuat."
                            )

                    });

                } finally {

                    setLoadingPeriod(false);

                }

            },
            [
                cabang,
                resetReconciliation
            ]
        );


    /*
    |--------------------------------------------------------------------------
    | LOAD PERIOD WHEN CABANG CHANGES
    |--------------------------------------------------------------------------
    */

    useEffect(() => {

        fetchActivePeriod();

    }, [
        fetchActivePeriod
    ]);

    const [availableMethods, setAvailableMethods] = useState([]);
    const [selectedMethods, setSelectedMethods] = useState([]);
    const [loadingMethods, setLoadingMethods] = useState(false);

    const methodRequestRef = useRef(0);

    const fetchReconciliationMethods = async () => {
        const requestId = ++methodRequestRef.current;

        // Metode hanya digunakan untuk rekonsiliasi Reguler
        if (
            reconType !== "REG" ||
            reconAccount === "ALL" ||
            !cabang
        ) {
            setAvailableMethods([]);
            setSelectedMethods([]);
            setLoadingMethods(false);
            return;
        }

        setLoadingMethods(true);
        setAvailableMethods([]);
        setSelectedMethods([]);

        try {
            const response = await axios.get(
                "/api/rekon/metode",
                {
                    params: {
                        cabang,
                        no_rek: reconAccount,
                    },
                }
            );

            // Abaikan response dari rekening yang sudah tidak dipilih
            if (requestId !== methodRequestRef.current) {
                return;
            }

            const result = response?.data || {};

            if (!result.success) {
                throw new Error(
                    result.message || "Gagal mengambil metode rekonsiliasi."
                );
            }

            const methods = Array.isArray(result.data)
                ? result.data
                : [];

            setAvailableMethods(methods);

            // Awalnya semua metode tersedia dipilih sesuai urutan dari backend
            setSelectedMethods(
                methods.map((method, index) => ({
                    ...method,
                    order: index + 1,
                }))
            );
        } catch (error) {
            if (requestId !== methodRequestRef.current) {
                return;
            }

            console.error(
                "Fetch reconciliation methods error:",
                error
            );

            setAvailableMethods([]);
            setSelectedMethods([]);

            await Swal.fire({
                icon: "error",
                title: "Gagal Memuat Metode",
                text:
                    error?.response?.data?.message ||
                    error?.message ||
                    "Daftar metode rekonsiliasi gagal dimuat.",
            });
        } finally {
            if (requestId === methodRequestRef.current) {
                setLoadingMethods(false);
            }
        }
    };

    useEffect(() => {
        fetchReconciliationMethods();

        return () => {
            // Membatalkan pemakaian response lama
            methodRequestRef.current += 1;
        };
    }, [cabang, reconType, reconAccount]);

    const removeReconciliationMethod = (methodId) => {
        setSelectedMethods((current) =>
            current
                .filter((method) => method.id !== methodId)
                .map((method, index) => ({
                    ...method,
                    order: index + 1,
                }))
        );
    };

    const reorderReconciliationMethods = (sourceId, targetId) => {
        if (sourceId === targetId) return;

        setSelectedMethods((current) => {
            const items = [...current];

            const sourceIndex = items.findIndex(
                (method) => String(method.id) === String(sourceId)
            );

            const targetIndex = items.findIndex(
                (method) => String(method.id) === String(targetId)
            );

            if (sourceIndex < 0 || targetIndex < 0) {
                return current;
            }

            const [movedItem] = items.splice(sourceIndex, 1);
            items.splice(targetIndex, 0, movedItem);

            return items.map((method, index) => ({
                ...method,
                order: index + 1,
            }));
        });
    };

    
    const runReconciliation = useCallback(
        async () => {
            if (loading) {
                return;
            }

            // ==================================================
            // VALIDASI CABANG
            // ==================================================
            if (!cabang) {
                await Swal.fire({
                    icon: "warning",
                    title: "Cabang Belum Tersedia",
                    text: "Cabang belum tersedia.",
                });

                return;
            }

            // ==================================================
            // VALIDASI PERIODE
            // ==================================================
            if (!period?.id) {
                await Swal.fire({
                    icon: "warning",
                    title: "Periode Belum Tersedia",
                    text: "Periode Mutasi aktif belum tersedia.",
                });

                return;
            }

            // ==================================================
            // VALIDASI JENIS REKONSILIASI
            // ==================================================
            if (!reconType) {
                await Swal.fire({
                    icon: "warning",
                    title: "Jenis Rekonsiliasi",
                    text: "Silakan pilih jenis rekonsiliasi.",
                });

                return;
            }

            // ==================================================
            // VALIDASI KHUSUS REGULER
            // Tidak mengubah perilaku Franchise
            // ==================================================
            if (reconType === "REG") {
                if (!reconAccount || reconAccount === "ALL") {
                    await Swal.fire({
                        icon: "warning",
                        title: "Rekening Belum Dipilih",
                        text:
                            "Pilih satu rekening Reguler sebelum menjalankan rekonsiliasi.",
                    });

                    return;
                }

                if (
                    !Array.isArray(selectedMethods) ||
                    selectedMethods.length === 0
                ) {
                    await Swal.fire({
                        icon: "warning",
                        title: "Metode Belum Dipilih",
                        text:
                            "Pilih minimal satu metode rekonsiliasi Reguler.",
                    });

                    return;
                }
            }

            // ==================================================
            // SUSUN PAYLOAD METODE
            // Hanya REG yang menggunakan metode
            // Urutan mengikuti posisi kartu saat ini
            // ==================================================
            const methodsPayload =
                reconType === "REG"
                    ? selectedMethods.map((method, index) => ({
                        id: method.id,
                        value: method.value,
                        order: index + 1,
                    }))
                    : [];

            // ==================================================
            // LOADING DAN BERSIHKAN HASIL SEBELUMNYA
            // ==================================================
            setLoading(true);

            setData([]);

            setSummary({
                ...EMPTY_SUMMARY,
            });

            setExpandedRow(null);

            setReconciliationExecuted(false);

            try {
                // ==================================================
                // PARAMETER DASAR
                // Parameter Franchise tetap sama seperti sebelumnya
                // ==================================================
                const params = {
                    cabang,
                    period_id: period.id,
                    type: reconType,
                    account:
                        reconAccount !== "ALL"
                            ? reconAccount
                            : "",
                };

                // ==================================================
                // TAMBAHAN KHUSUS REGULER
                // Franchise tidak menerima parameter methods
                // ==================================================
                if (reconType === "REG") {
                    params.methods = JSON.stringify(methodsPayload);
                }

                // ==================================================
                // REQUEST REKONSILIASI
                // Endpoint dan metode HTTP tetap sama
                // ==================================================
                const response = await axios.get(
                    "/api/rekon/receipt",
                    {
                        params,
                    }
                );

                const result = response?.data || {};

                // ==================================================
                // AMBIL DATA HASIL
                // ==================================================
                const resultData = Array.isArray(result.data)
                    ? result.data
                    : [];

                setData(resultData);

                // ==================================================
                // UPDATE SUMMARY
                // Struktur tetap seperti fungsi sebelumnya
                // ==================================================
                setSummary({
                    total: Number(
                        result.summary?.total ??
                            resultData.length ??
                            0
                    ),

                    match: Number(
                        result.summary?.match ?? 0
                    ),

                    mutasi_only: Number(
                        result.summary?.mutasi_only ?? 0
                    ),

                    receipt_only: Number(
                        result.summary?.receipt_only ?? 0
                    ),

                    nominal_different: Number(
                        result.summary?.nominal_different ?? 0
                    ),

                    account_different: Number(
                        result.summary?.account_different ?? 0
                    ),

                    mutasi_amount: Number(
                        result.summary?.mutasi_amount ?? 0
                    ),

                    receipt_amount: Number(
                        result.summary?.receipt_amount ?? 0
                    ),

                    difference_amount: Number(
                        result.summary?.difference_amount ?? 0
                    ),
                });

                setResultFilter("ALL");

                setReconciliationExecuted(true);
            } catch (error) {
                console.error(
                    "Reconciliation error:",
                    error
                );

                setReconciliationExecuted(false);

                await Swal.fire({
                    icon: "error",
                    title: "Rekonsiliasi Gagal",
                    text: getErrorMessage(
                        error,
                        "Data rekonsiliasi gagal diproses."
                    ),
                });
            } finally {
                setLoading(false);
            }
        },
        [
            cabang,
            period?.id,
            reconType,
            reconAccount,
            selectedMethods,
            loading,
        ]
    );



    /*
    |--------------------------------------------------------------------------
    | FILE PICKER
    |--------------------------------------------------------------------------
    |
    | Tidak lagi menggunakan label htmlFor.
    |
    */

    const openFilePicker =
        () => {

            if (
                !period?.id
            ) {

                return;

            }


            if (
                uploading
            ) {

                return;

            }


            fileInputRef.current?.click();

        };


    /*
    |--------------------------------------------------------------------------
    | FILE CHANGE
    |--------------------------------------------------------------------------
    */

    const handleFileChange =
        (event) => {

            const files =
                Array.from(
                    event.target.files || []
                );


            if (!files.length) {

                return;

            }


            /*
            |--------------------------------------------------------------------------
            | Validasi ekstensi
            |--------------------------------------------------------------------------
            */

            const invalidFiles =
                files.filter(
                    file => {

                        const name =
                            String(
                                file.name ||
                                ""
                            ).toLowerCase();

                        return !(
                            name.endsWith(".csv") ||
                            name.endsWith(".txt")
                        );

                    }
                );


            if (
                invalidFiles.length
            ) {

                Swal.fire({

                    icon: "warning",

                    title:
                        "Format File Tidak Valid",

                    text:
                        "Hanya file CSV atau TXT yang dapat dipilih."

                });


                event.target.value =
                    "";

                return;

            }


            setSelectedFiles(
                files
            );

        };


    /*
    |--------------------------------------------------------------------------
    | REMOVE FILE
    |--------------------------------------------------------------------------
    */

    const removeFile =
        (index) => {

            setSelectedFiles(
                current =>
                    current.filter(
                        (_, i) =>
                            i !== index
                    )
            );

        };


    /*
    |--------------------------------------------------------------------------
    | RESET UPLOAD
    |--------------------------------------------------------------------------
    */

    const resetUpload =
        () => {

            setSelectedFiles([]);

            if (
                fileInputRef.current
            ) {

                fileInputRef.current.value =
                    "";

            }

        };


    /*
    |--------------------------------------------------------------------------
    | IMPORT RECEIPT
    |--------------------------------------------------------------------------
    */

    const handleImport = async () => {

        if (uploading) {
            return;
        }
    
        if (!cabang) {    
            await Swal.fire({
                icon: "warning",
                title: "Cabang Belum Dipilih",
                text: "Cabang harus tersedia sebelum melakukan import."
            });
    
            return;
        }
    
    
        /*
        |--------------------------------------------------------------------------
        | PERIOD
        |--------------------------------------------------------------------------
        */
    
        if (!period?.id) {
    
            await Swal.fire({
                icon: "warning",
                title: "Periode Belum Tersedia",
                text: "Periode Mutasi aktif belum tersedia untuk cabang ini."
            });
    
            return;
        }
    
    
        /*
        |--------------------------------------------------------------------------
        | FILE
        |--------------------------------------------------------------------------
        */
    
        if (!selectedFiles.length) {
    
            await Swal.fire({
                icon: "warning",
                title: "File Belum Dipilih",
                text: "Silakan pilih minimal satu file Receipt."
            });
    
            return;
        }
    
    
        /*
        |--------------------------------------------------------------------------
        | KONFIRMASI IMPORT
        |--------------------------------------------------------------------------
        */
    
        const confirm = await Swal.fire({
    
            icon: "question",
    
            title: "Import Receipt?",
    
            html: `
                <div style="text-align:left">
    
                    <p>
                        Cabang:
                        <strong>
                            ${cabang}
                        </strong>
                    </p>
    
                    <p>
                        Periode:
                        <strong>
                            ${period?.periode || "-"}
                        </strong>
                    </p>
    
                    <p>
                        Tanggal:
                        <strong>
                            ${formatDate(period?.start_date)}
                            -
                            ${formatDate(period?.end_date)}
                        </strong>
                    </p>
    
                    <p>
                        File:
                        <strong>
                            ${selectedFiles.length}
                        </strong>
                    </p>
    
                </div>
            `,
    
            showCancelButton: true,
    
            confirmButtonText: "Ya, Import",
    
            cancelButtonText: "Batal"
    
        });
    
    
        if (!confirm.isConfirmed) {
            return;
        }
    
    
        /*
        |--------------------------------------------------------------------------
        | FORMDATA
        |--------------------------------------------------------------------------
        */
    
        const formData = new FormData();
    
    
        selectedFiles.forEach(file => {
    
            formData.append(
                "files[]",
                file
            );
    
        });
    
    
        formData.append(
            "cabang",
            cabang
        );
    
    
        formData.append(
            "period_id",
            period.id
        );
    
    
        setUploading(true);
    
    
        /*
        |--------------------------------------------------------------------------
        | LOADING DIALOG
        |--------------------------------------------------------------------------
        */
    
        Swal.fire({
    
            title: "Import Receipt",
    
            html: `
                <div style="
                    margin-top:15px;
                    text-align:left;
                ">
    
                    <div>
                        Cabang:
                        <strong>
                            ${cabang}
                        </strong>
                    </div>
    
                    <div style="margin-top:8px">
                        Periode:
                        <strong>
                            ${period?.periode || "-"}
                        </strong>
                    </div>
    
                    <div
                        id="receipt-upload-status"
                        style="
                            margin-top:15px;
                            text-align:center;
                        "
                    >
                        Menyiapkan upload...
                    </div>
    
                </div>
            `,
    
            allowOutsideClick: false,
    
            allowEscapeKey: false,
    
            showConfirmButton: false
    
        });
    
    
        try {
    
            /*
            |--------------------------------------------------------------------------
            | UPLOAD KE BACKEND
            |--------------------------------------------------------------------------
            */
    
            const response = await axios.post(
    
                "/api/import-receipt",
    
                formData,
    
                {
                    headers: {
                        "Content-Type": "multipart/form-data"
                    },
    
                    onUploadProgress: (progressEvent) => {
    
                        if (!progressEvent.total) {
                            return;
                        }
    
    
                        const percent = Math.round(
                            (
                                progressEvent.loaded * 100
                            ) /
                            progressEvent.total
                        );
    
    
                        const container =
                            Swal.getHtmlContainer();
    
    
                        if (!container) {
                            return;
                        }
    
    
                        const status =
                            container.querySelector(
                                "#receipt-upload-status"
                            );
    
    
                        if (status) {
    
                            status.innerHTML =
                                percent >= 100
    
                                    ? `
                                        <div>
                                            Memvalidasi dan menyimpan data...
                                        </div>
                                    `
    
                                    : `
                                        <div>
                                            Mengupload file...
                                            <strong>
                                                ${percent}%
                                            </strong>
                                        </div>
                                    `;
                        }
    
                    }
    
                }
    
            );
    
    
            /*
            |--------------------------------------------------------------------------
            | RESPONSE BACKEND
            |--------------------------------------------------------------------------
            */
    
            const result =
                response?.data || {};
    
    
            /*
            |--------------------------------------------------------------------------
            | AMBIL HASIL IMPORT
            |--------------------------------------------------------------------------
            */
    
            const inserted =
                Number(result.inserted ?? 0);
    
            const updated =
                Number(result.updated ?? 0);
    
            const skipped =
                Number(result.skipped ?? 0);
    
            const errors =
                Array.isArray(result.errors)
                    ? result.errors
                    : [];
    
    
            /*
            |--------------------------------------------------------------------------
            | JIKA ADA ERROR PER FILE / BARIS
            |--------------------------------------------------------------------------
            */
    
            if (errors.length > 0) {
    
                let html = `
    
                    <div style="
                        text-align:left;
                    ">
    
                        <div style="
                            margin-bottom:18px;
                            padding:14px;
                            background:#f8f9fa;
                            border-radius:8px;
                        ">
    
                            <div style="
                                font-size:15px;
                                margin-bottom:10px;
                            ">
                                <strong>
                                    Import Receipt selesai dengan catatan.
                                </strong>
                            </div>
    
                            <div style="
                                line-height:1.8;
                            ">
    
                                <div>
                                    Insert :
                                    <strong>
                                        ${inserted}
                                    </strong>
                                    data
                                </div>
    
                                <div>
                                    Update :
                                    <strong>
                                        ${updated}
                                    </strong>
                                    data
                                </div>
    
                                <div>
                                    Skip :
                                    <strong>
                                        ${skipped}
                                    </strong>
                                    data
                                </div>
    
                                <div>
                                    Error :
                                    <strong>
                                        ${errors.length}
                                    </strong>
                                    item
                                </div>
    
                            </div>
    
                        </div>
    
                        <div style="
                            margin-bottom:10px;
                            font-weight:600;
                        ">
                            Detail Error:
                        </div>
    
                `;
    
    
                /*
                |--------------------------------------------------------------------------
                | LOOP ERROR
                |--------------------------------------------------------------------------
                */
    
                errors.forEach(error => {
    
                    html += `
    
                        <div style="
                            margin-bottom:12px;
                            padding:12px;
                            border:1px solid #ddd;
                            border-radius:8px;
                            background:#fff;
                        ">
    
                            <strong>
                                ${error.file || "Unknown File"}
                            </strong>
    
                    `;
    
    
                    /*
                    |--------------------------------------------------------------------------
                    | ERROR ISSUES ARRAY
                    |--------------------------------------------------------------------------
                    */
    
                    if (
                        Array.isArray(error.issues) &&
                        error.issues.length > 0
                    ) {
    
                        html += `
    
                            <ul style="
                                margin-top:8px;
                                margin-bottom:0;
                                padding-left:20px;
                            ">
    
                        `;
    
    
                        error.issues.forEach(issue => {
    
                            html += `
    
                                <li style="
                                    margin-bottom:5px;
                                ">
                                    ${issue}
                                </li>
    
                            `;
    
                        });
    
    
                        html += `
                            </ul>
                        `;
    
                    }
    
                    /*
                    |--------------------------------------------------------------------------
                    | ERROR ISSUES STRING
                    |--------------------------------------------------------------------------
                    */
    
                    else if (error.issues) {
    
                        html += `
    
                            <div style="
                                margin-top:8px;
                            ">
                                ${error.issues}
                            </div>
    
                        `;
    
                    }
    
    
                    html += `
    
                        </div>
    
                    `;
    
                });
    
    
                html += `
                    </div>
                `;
    
    
                /*
                |--------------------------------------------------------------------------
                | ALERT WARNING
                |--------------------------------------------------------------------------
                */
    
                await Swal.fire({
    
                    icon: "warning",
    
                    title: "Import Selesai Dengan Catatan",
    
                    html: html,
    
                    width: 750,
    
                    confirmButtonText: "OK"
    
                });
    
            }
    
    
            /*
            |--------------------------------------------------------------------------
            | IMPORT BERHASIL TANPA ERROR
            |--------------------------------------------------------------------------
            */
    
            else {
    
                await Swal.fire({
    
                    icon: "success",
    
                    title: "Import Berhasil",
    
                    html: `
    
                        <div style="
                            font-size:16px;
                            line-height:1.9;
                            text-align:center;
                        ">
    
                            <div style="
                                margin-bottom:12px;
                            ">
                                Import Receipt selesai.
                            </div>
    
                            <div>
                                Insert :
                                <strong>
                                    ${inserted}
                                </strong>
                                data
                            </div>
    
                            <div>
                                Update :
                                <strong>
                                    ${updated}
                                </strong>
                                data
                            </div>
    
                            <div>
                                Skip :
                                <strong>
                                    ${skipped}
                                </strong>
                                data
                            </div>
    
                        </div>
    
                    `,
    
                    confirmButtonText: "OK"
    
                });
    
            }
    
    
            /*
            |--------------------------------------------------------------------------
            | RESET UPLOAD
            |--------------------------------------------------------------------------
            */
    
            resetUpload();
    
    
            /*
            |--------------------------------------------------------------------------
            | JANGAN JALANKAN REKONSILIASI OTOMATIS
            |--------------------------------------------------------------------------
            |
            | Setelah import selesai, user harus memilih:
            |
            | 1. REG / FRC
            | 2. Rekening / ALL
            | 3. Klik Jalankan Rekonsiliasi
            |
            */
    
            resetReconciliation();
    
    
            /*
            |--------------------------------------------------------------------------
            | KEMBALI KE TAB REKONSILIASI
            |--------------------------------------------------------------------------
            */
    
            setActiveTab(
                "reconciliation"
            );
    
    
            /*
            |--------------------------------------------------------------------------
            | INFORMASI USER
            |--------------------------------------------------------------------------
            */
    
            await Swal.fire({
    
                icon: "info",
    
                title: "Silakan Jalankan Rekonsiliasi",
    
                text:
                    "Pilih jenis rekonsiliasi dan rekening, kemudian klik tombol Jalankan Rekonsiliasi.",
    
                confirmButtonText: "OK"
    
            });
    
        }
    
    
        /*
        |--------------------------------------------------------------------------
        | ERROR REQUEST / SERVER
        |--------------------------------------------------------------------------
        */
    
        catch (error) {
    
            console.error(
                "Import error:",
                error
            );
    
    
            /*
            |--------------------------------------------------------------------------
            | TUTUP LOADING
            |--------------------------------------------------------------------------
            */
    
            if (Swal.isVisible()) {
                Swal.close();
            }
    
    
            /*
            |--------------------------------------------------------------------------
            | AMBIL PESAN ERROR
            |--------------------------------------------------------------------------
            */
    
            await Swal.fire({
    
                icon: "error",
    
                title: "Import Gagal",
    
                text:
                    getErrorMessage(
                        error,
                        "Terjadi kesalahan saat proses import."
                    ),
    
                confirmButtonText: "OK"
    
            });
    
        }
    
    
        /*
        |--------------------------------------------------------------------------
        | SELESAI UPLOAD
        |--------------------------------------------------------------------------
        */
    
        finally {
    
            setUploading(false);
    
        }
    
    };


    /*
    |--------------------------------------------------------------------------
    | FILTERED DATA
    |--------------------------------------------------------------------------
    */

    const filteredData =
        useMemo(() => {

            const keyword =
                search
                    .trim()
                    .toLowerCase();


            return data.filter(
                item => {

                    /*
                    |--------------------------------------------------------------------------
                    | RESULT CATEGORY
                    |--------------------------------------------------------------------------
                    */

                    const category =
                        getResultCategory(
                            item
                        );


                    if (
                        resultFilter !==
                        "ALL" &&
                        category !==
                        resultFilter
                    ) {

                        return false;

                    }


                    /*
                    |--------------------------------------------------------------------------
                    | SEARCH
                    |--------------------------------------------------------------------------
                    */

                    if (
                        !keyword
                    ) {

                        return true;

                    }


                    const searchable = [

                        item.mutation_number,

                        item.receipt_number,

                        item.remittance_bank_account,

                        item.no_rek,

                        item.jns_bank,

                        item.description,

                        item.comments,
                        ...(item.receipts || []).map(receipt => receipt.comments),

                        item.receipt_status,

                        item.receipt_state,

                        item.reff

                    ]
                        .filter(Boolean)
                        .join(" ")
                        .toLowerCase();


                    return searchable.includes(
                        keyword
                    );

                }
            );

        }, [
            data,
            resultFilter,
            search
        ]);

    /*
    |--------------------------------------------------------------------------
    | EXPORT EXCEL
    |--------------------------------------------------------------------------
    */

    const handleExport =
        async () => {

            if (!cabang) {

                await Swal.fire({

                    icon: "warning",

                    title:
                        "Cabang Belum Tersedia",

                    text:
                        "Cabang belum tersedia."

                });

                return;

            }


            if (!period?.id) {

                await Swal.fire({

                    icon: "warning",

                    title:
                        "Periode Belum Tersedia",

                    text:
                        "Periode aktif belum tersedia."

                });

                return;

            }


            if (
                !reconciliationExecuted
            ) {

                await Swal.fire({

                    icon: "info",

                    title:
                        "Rekonsiliasi Belum Dijalankan",

                    text:
                        "Jalankan rekonsiliasi terlebih dahulu."

                });

                return;

            }


            if (!data.length) {

                await Swal.fire({

                    icon: "info",

                    title:
                        "Tidak Ada Data",

                    text:
                        "Tidak ada data rekonsiliasi yang dapat diekspor."

                });

                return;

            }


            setExporting(
                true
            );


            try {

                const response =
                    await axios.get(
                        "/api/rekon/receipt/export",
                        {

                            params: {

                                cabang,

                                period_id:
                                    period.id,

                                type:
                                    reconType,

                                account:
                                    reconAccount !==
                                    "ALL"
                                        ? reconAccount
                                        : "",

                                result:
                                    resultFilter !==
                                    "ALL"
                                        ? resultFilter
                                        : "",

                                search:
                                    search || ""

                            },

                            responseType:
                                "blob"

                        }
                    );


                const blob =
                    new Blob(
                        [
                            response.data
                        ],
                        {

                            type:
                                "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"

                        }
                    );


                const url =
                    window.URL.createObjectURL(
                        blob
                    );


                const link =
                    document.createElement(
                        "a"
                    );


                link.href =
                    url;


                const typeName =
                    reconType === "FRC"
                        ? "Franchise"
                        : "Reguler";


                const accountName =
                    reconAccount === "ALL"
                        ? "ALL"
                        : reconAccount;


                link.download =
                    `Rekonsiliasi_${typeName}_${accountName}_${cabang}_${period?.periode || "Periode"}.xlsx`;


                document.body.appendChild(
                    link
                );


                link.click();


                document.body.removeChild(
                    link
                );


                window.URL.revokeObjectURL(
                    url
                );


            } catch (error) {

                console.error(
                    "Export error:",
                    error
                );


                await Swal.fire({

                    icon: "error",

                    title:
                        "Export Gagal",

                    text:
                        getErrorMessage(
                            error,
                            "File Excel gagal dibuat."
                        )

                });

            } finally {

                setExporting(
                    false
                );

            }

        };
    

    /*
    |--------------------------------------------------------------------------
    | DOWNLOAD HASIL REKONSILIASI
    |--------------------------------------------------------------------------
    */

    const handleDownloadReconciliation = async (type) => {

        /*
        |--------------------------------------------------------------------------
        | VALIDASI CABANG
        |--------------------------------------------------------------------------
        */
    
        if (!cabang) {
    
            await Swal.fire({
                icon: "warning",
                title: "Cabang Belum Tersedia",
                text: "Cabang belum tersedia."
            });
    
            return;
        }
    
    
        /*
        |--------------------------------------------------------------------------
        | VALIDASI PERIODE
        |--------------------------------------------------------------------------
        */
    
        if (!period?.id) {
    
            await Swal.fire({
                icon: "warning",
                title: "Periode Belum Tersedia",
                text: "Periode aktif belum tersedia."
            });
    
            return;
        }
    
    
        /*
        |--------------------------------------------------------------------------
        | VALIDASI TYPE
        |--------------------------------------------------------------------------
        */
    
        if (!["FRC", "REG"].includes(type)) {
    
            await Swal.fire({
                icon: "error",
                title: "Jenis Rekonsiliasi Tidak Valid",
                text: "Jenis rekonsiliasi tidak dikenali."
            });
    
            return;
        }
    
    
        /*
        |--------------------------------------------------------------------------
        | NAMA TYPE
        |--------------------------------------------------------------------------
        */
    
        const typeName =
            type === "FRC"
                ? "Franchise"
                : "Reguler";
    
    
        /*
        |--------------------------------------------------------------------------
        | SET LOADING
        |--------------------------------------------------------------------------
        */
    
        setDownloadLoading(prev => ({
            ...prev,
            [type]: true
        }));
    
    
        /*
        |--------------------------------------------------------------------------
        | LOADING ALERT
        |--------------------------------------------------------------------------
        */
    
        Swal.fire({
            title: "Menyiapkan File...",
            text: `Sedang menyiapkan hasil rekonsiliasi ${typeName}.`,
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            didOpen: () => {
    
                Swal.showLoading();
    
            }
        });
    
    
        try {
    
            /*
            |--------------------------------------------------------------------------
            | REQUEST EXPORT
            |--------------------------------------------------------------------------
            */
    
            const response =
                await axios.get(
                    "/api/rekon/receipt/export",
                    {
                        params: {
    
                            /*
                            |--------------------------------------------------------------------------
                            | CABANG
                            |--------------------------------------------------------------------------
                            */
    
                            cabang: cabang,
    
    
                            /*
                            |--------------------------------------------------------------------------
                            | PERIODE
                            |--------------------------------------------------------------------------
                            */
    
                            period_id:
                                period.id,
    
    
                            /*
                            |--------------------------------------------------------------------------
                            | TYPE
                            |--------------------------------------------------------------------------
                            */
    
                            type: type,
    
    
                            /*
                            |--------------------------------------------------------------------------
                            | SEMUA REKENING
                            |--------------------------------------------------------------------------
                            */
    
                            account: "",
    
    
                            /*
                            |--------------------------------------------------------------------------
                            | SEMUA HASIL
                            |--------------------------------------------------------------------------
                            */
    
                            result: "",
    
    
                            /*
                            |--------------------------------------------------------------------------
                            | TANPA SEARCH
                            |--------------------------------------------------------------------------
                            */
    
                            search: ""
                        },
    
    
                        /*
                        |--------------------------------------------------------------------------
                        | RESPONSE EXCEL
                        |--------------------------------------------------------------------------
                        */
    
                        responseType: "blob"
                    }
                );
    
    
            /*
            |--------------------------------------------------------------------------
            | CEK RESPONSE
            |--------------------------------------------------------------------------
            |
            | Karena responseType = blob, response.data selalu berupa Blob.
            |
            | Kita harus memastikan backend benar-benar mengirim file Excel.
            |
            */
    
            const contentType =
                response?.headers?.["content-type"] || "";
    
    
            /*
            |--------------------------------------------------------------------------
            | JIKA BACKEND MENGIRIM ERROR JSON
            |--------------------------------------------------------------------------
            |
            | Misalnya Laravel mengirim:
            |
            | {
            |     success: false,
            |     message: "...",
            |     error: "SQLSTATE..."
            | }
            |
            | Karena axios menggunakan responseType blob,
            | response tersebut tetap diterima sebagai Blob.
            |
            */
    
            if (
                contentType.includes("application/json") ||
                contentType.includes("text/json")
            ) {
    
                const text =
                    await response.data.text();
    
    
                let json = null;
    
    
                try {
    
                    json =
                        JSON.parse(text);
    
                } catch (parseError) {
    
                    console.error(
                        "Gagal parse response JSON:",
                        parseError
                    );
    
                }
    
    
                const backendMessage =
                    json?.error ||
                    json?.message ||
                    "Backend mengembalikan response error.";
    
    
                throw new Error(
                    backendMessage
                );
            }
    
    
            /*
            |--------------------------------------------------------------------------
            | VALIDASI BLOB
            |--------------------------------------------------------------------------
            */
    
            if (!(response.data instanceof Blob)) {
    
                throw new Error(
                    "Response server bukan file Excel."
                );
            }
    
    
            /*
            |--------------------------------------------------------------------------
            | VALIDASI UKURAN FILE
            |--------------------------------------------------------------------------
            */
    
            if (response.data.size === 0) {
    
                throw new Error(
                    "File Excel yang diterima kosong."
                );
            }
    
    
            /*
            |--------------------------------------------------------------------------
            | BUAT BLOB EXCEL
            |--------------------------------------------------------------------------
            */
    
            const blob =
                new Blob(
                    [
                        response.data
                    ],
                    {
                        type:
                            "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                    }
                );
    
    
            /*
            |--------------------------------------------------------------------------
            | CREATE OBJECT URL
            |--------------------------------------------------------------------------
            */
    
            const url =
                window.URL.createObjectURL(
                    blob
                );
    
    
            /*
            |--------------------------------------------------------------------------
            | CREATE DOWNLOAD LINK
            |--------------------------------------------------------------------------
            */
    
            const link =
                document.createElement(
                    "a"
                );
    
    
            link.href =
                url;
    
    
            /*
            |--------------------------------------------------------------------------
            | NAMA FILE
            |--------------------------------------------------------------------------
            */
    
            const periodeName =
                period?.periode ||
                "Periode";
    
    
            link.download =
                `Rekonsiliasi_${typeName}_${cabang}_${periodeName}.xlsx`;
    
    
            /*
            |--------------------------------------------------------------------------
            | TRIGGER DOWNLOAD
            |--------------------------------------------------------------------------
            */
    
            document.body.appendChild(
                link
            );
    
            link.click();
    
            document.body.removeChild(
                link
            );
    
    
            /*
            |--------------------------------------------------------------------------
            | RELEASE OBJECT URL
            |--------------------------------------------------------------------------
            */
    
            window.URL.revokeObjectURL(
                url
            );
    
    
            /*
            |--------------------------------------------------------------------------
            | TUTUP LOADING
            |--------------------------------------------------------------------------
            */
    
            Swal.close();
    
    
            /*
            |--------------------------------------------------------------------------
            | SUCCESS
            |--------------------------------------------------------------------------
            */
    
            await Swal.fire({
                icon: "success",
                title: "Download Berhasil",
                text:
                    `Hasil rekonsiliasi ${typeName} berhasil diunduh.`,
                timer: 1800,
                showConfirmButton: false
            });
    
    
        } catch (error) {
    
            /*
            |--------------------------------------------------------------------------
            | LOG ERROR
            |--------------------------------------------------------------------------
            */
    
            console.error(
                "Download rekonsiliasi error:",
                error
            );
    
    
            /*
            |--------------------------------------------------------------------------
            | DEFAULT ERROR MESSAGE
            |--------------------------------------------------------------------------
            */
    
            let message =
                "File Excel gagal diunduh.";
    
    
            /*
            |--------------------------------------------------------------------------
            | RESPONSE DARI SERVER
            |--------------------------------------------------------------------------
            */
    
            const response =
                error?.response;
    
    
            if (response?.data) {
    
                try {
    
                    /*
                    |--------------------------------------------------------------------------
                    | RESPONSE BLOB
                    |--------------------------------------------------------------------------
                    */
    
                    if (
                        response.data instanceof Blob
                    ) {
    
                        const text =
                            await response.data.text();
    
    
                        console.error(
                            "Response error dari backend:",
                            text
                        );
    
    
                        /*
                        |--------------------------------------------------------------------------
                        | COBA PARSE JSON
                        |--------------------------------------------------------------------------
                        */
    
                        try {
    
                            const json =
                                JSON.parse(text);
    
    
                            /*
                            |--------------------------------------------------------------------------
                            | PRIORITASKAN ERROR DETAIL
                            |--------------------------------------------------------------------------
                            */
    
                            message =
                                json?.error ||
                                json?.message ||
                                message;
    
    
                            /*
                            |--------------------------------------------------------------------------
                            | JIKA ADA LINE ERROR
                            |--------------------------------------------------------------------------
                            */
    
                            if (
                                json?.line &&
                                json?.file
                            ) {
    
                                message +=
                                    ` (${json.file}:${json.line})`;
    
                            }
    
                        } catch (jsonError) {
    
                            /*
                            |--------------------------------------------------------------------------
                            | RESPONSE BUKAN JSON
                            |--------------------------------------------------------------------------
                            */
    
                            console.error(
                                "Response bukan JSON:",
                                jsonError
                            );
    
    
                            if (text) {
    
                                message =
                                    text;
    
                            }
    
                        }
    
                    }
    
                    /*
                    |--------------------------------------------------------------------------
                    | RESPONSE OBJECT BIASA
                    |--------------------------------------------------------------------------
                    */
    
                    else if (
                        typeof response.data === "object"
                    ) {
    
                        message =
                            response.data?.error ||
                            response.data?.message ||
                            message;
    
                    }
    
                } catch (readError) {
    
                    console.error(
                        "Gagal membaca response error:",
                        readError
                    );
    
                }
    
            }
    
    
            /*
            |--------------------------------------------------------------------------
            | ERROR DARI AXIOS
            |--------------------------------------------------------------------------
            */
    
            if (
                message ===
                "File Excel gagal diunduh."
            ) {
    
                if (
                    error?.message
                ) {
    
                    message =
                        error.message;
    
                }
    
            }
    
    
            /*
            |--------------------------------------------------------------------------
            | TUTUP LOADING
            |--------------------------------------------------------------------------
            */
    
            Swal.close();
    
    
            /*
            |--------------------------------------------------------------------------
            | TAMPILKAN ERROR
            |--------------------------------------------------------------------------
            */
    
            await Swal.fire({
                icon: "error",
                title: "Download Gagal",
                text: message,
                confirmButtonText: "OK"
            });
    
    
        } finally {
    
            /*
            |--------------------------------------------------------------------------
            | RESET LOADING
            |--------------------------------------------------------------------------
            */
    
            setDownloadLoading(prev => ({
                ...prev,
                [type]: false
            }));
    
        }
    
    };
    
    /*
    |--------------------------------------------------------------------------
    | CHANGE RECON TYPE
    |--------------------------------------------------------------------------
    */
    
    const handleReconTypeChange = async (type) => {

        /*
        |--------------------------------------------------------------------------
        | JANGAN PROSES JIKA SEDANG REKONSILIASI
        |--------------------------------------------------------------------------
        */
    
        if (loading) {
            return;
        }
    
    
        /*
        |--------------------------------------------------------------------------
        | NORMALISASI TYPE
        |--------------------------------------------------------------------------
        */
    
        const newType =
            type === "FRC"
                ? "FRC"
                : "REG";
    
    
        console.log(
            "Jenis rekonsiliasi berubah:",
            newType
        );
    
    
        /*
        |--------------------------------------------------------------------------
        | SET TYPE
        |--------------------------------------------------------------------------
        */
    
        setReconType(
            newType
        );
    
    
        /*
        |--------------------------------------------------------------------------
        | RESET HASIL
        |--------------------------------------------------------------------------
        */
    
        resetReconciliation();
    
    
        /*
        |--------------------------------------------------------------------------
        | RESET REKENING
        |--------------------------------------------------------------------------
        */
    
        setReconAccount(
            "ALL"
        );
    
        /*
        |--------------------------------------------------------------------------
        | LOAD REKENING
        |--------------------------------------------------------------------------
        */
    
        await fetchReconciliationAccounts(
            newType
        );
    
    };

    const accountRequestRef = useRef(0);

    const fetchReconciliationAccounts = async (type = reconType) => {
        const requestId = ++accountRequestRef.current;

        if (!cabang) {
            setAccounts([]);
            setReconAccount("ALL");
            return;
        }

        const selectedType = String(type || "")
            .trim()
            .toUpperCase();

        setLoadingAccounts(true);

        try {
            const response = await axios.get(
                "/api/rekon/rekening",
                {
                    params: {
                        cabang: cabang,
                        type: selectedType,
                    },
                }
            );

            const result = response?.data || {};

            if (!result.success) {
                throw new Error(
                    result.message ||
                    "Gagal mengambil daftar rekening."
                );
            }

            const rawList = Array.isArray(result.data)
                ? result.data
                : [];

            console.log("Data rekening dari API:", rawList);

            /*
            |--------------------------------------------------------------------------
            | FORMAT DATA REKENING
            |--------------------------------------------------------------------------
            |
            | FRC:
            |   ["BCA", "BRI", ...]
            |
            | REG:
            |   [
            |       {
            |           jns_bank: "BCA",
            |           no_rek: "0015201300001151",
            |           akun: "Akun BCA"
            |       }
            |   ]
            |
            */

            let uniqueAccounts = [];

            if (selectedType === "REG") {
                uniqueAccounts = [
                    ...new Map(
                        rawList
                            .map((item) => {
                                const noRek = String(
                                    item?.no_rek ?? ""
                                ).trim();

                                const jnsBank = String(
                                    item?.jns_bank ?? ""
                                ).trim();

                                const akun = String(
                                    item?.akun ?? ""
                                ).trim();

                                if (!noRek) {
                                    return null;
                                }

                                return [
                                    noRek,
                                    {
                                        no_rek: noRek,
                                        jns_bank: jnsBank,
                                        akun: akun,
                                    },
                                ];
                            })
                            .filter(Boolean)
                    ).values(),
                ];
            } else {
                uniqueAccounts = [
                    ...new Set(
                        rawList
                            .map((item) =>
                                String(item ?? "").trim()
                            )
                            .filter(Boolean)
                    ),
                ];
            }

            console.log(
                "HASIL ACCOUNT FINAL:",
                uniqueAccounts
            );

            console.log(
                "JUMLAH ACCOUNT:",
                uniqueAccounts.length
            );

            /*
            |--------------------------------------------------------------------------
            | CEK REQUEST TERBARU
            |--------------------------------------------------------------------------
            */

            if (requestId !== accountRequestRef.current) {
                console.log(
                    "Response rekening diabaikan karena request sudah tidak terbaru."
                );

                return;
            }

            /*
            |--------------------------------------------------------------------------
            | SIMPAN DATA KE STATE
            |--------------------------------------------------------------------------
            */

            setAccounts(uniqueAccounts);

            /*
            |--------------------------------------------------------------------------
            | RESET PILIHAN REKENING
            |--------------------------------------------------------------------------
            */

            setReconAccount("ALL");

        } catch (error) {
            console.error(
                "Fetch reconciliation accounts error:",
                error
            );

            if (requestId !== accountRequestRef.current) {
                console.log(
                    "Error rekening diabaikan karena request sudah tidak terbaru."
                );

                return;
            }

            setAccounts([]);
            setReconAccount("ALL");

            await Swal.fire({
                icon: "error",
                title: "Gagal Memuat Rekening",
                text: getErrorMessage(
                    error,
                    "Daftar rekening rekonsiliasi tidak dapat dimuat."
                ),
                confirmButtonText: "OK",
            });

        } finally {
            if (requestId === accountRequestRef.current) {
                setLoadingAccounts(false);
            }
        }
    };

    useEffect(() => {

        console.log(
            "================================="
        );
    
        console.log(
            "STATE accounts BERUBAH"
        );
    
        console.log(
            "reconType:",
            reconType
        );
    
        console.log(
            "accounts:",
            accounts
        );
    
        console.log(
            "jumlah accounts:",
            accounts?.length || 0
        );
    
        console.log(
            "================================="
    
        );
    
    }, [accounts, reconType]);
    
    useEffect(() => {

        if (!cabang) {
    
            setAccounts([]);
    
            setReconAccount("ALL");
    
            return;
    
        }
    
    
        /*
        |--------------------------------------------------------------------------
        | LOAD ACCOUNT BERDASARKAN TYPE AKTIF
        |--------------------------------------------------------------------------
        */
    
        fetchReconciliationAccounts(
            reconType
        );
    
    }, [
        cabang
    ]);

    /*
    |--------------------------------------------------------------------------
    | KEYBOARD SHORTCUT
    |--------------------------------------------------------------------------
    */

    useEffect(() => {

        const handleKeyDown =
            event => {
                if (
                    event.altKey &&
                    event.code ===
                        "KeyU"
                ) {

                    event.preventDefault();

                    setActiveTab(
                        "import"
                    );

                }


                /*
                |--------------------------------------------------------------------------
                | Alt + R = Jalankan Rekonsiliasi
                |--------------------------------------------------------------------------
                */

                if (
                    event.altKey &&
                    event.code ===
                        "KeyR"
                ) {

                    event.preventDefault();

                    if (
                        activeTab ===
                        "reconciliation"
                    ) {

                        runReconciliation();

                    }

                }


                /*
                |--------------------------------------------------------------------------
                | Alt + X = Sidebar
                |--------------------------------------------------------------------------
                */

                if (
                    event.altKey &&
                    event.code ===
                        "KeyX"
                ) {

                    e.preventDefault();

                    setIsCollapsed(prev => !prev);
                    
                    return;

                }

            };


        window.addEventListener(
            "keydown",
            handleKeyDown
        );


        return () => {

            window.removeEventListener(
                "keydown",
                handleKeyDown
            );

        };

    }, [
        activeTab,
        runReconciliation,
        isCollapsed
    ]);

    /*
    |--------------------------------------------------------------------------
    | RIWAYAT REKONSILIASI
    |--------------------------------------------------------------------------
    */

    const [
        historyData,
        setHistoryData
    ] = useState([]);

    const [
        historyLoading,
        setHistoryLoading
    ] = useState(false);

    const [
        historyExecuted,
        setHistoryExecuted
    ] = useState(false);


    /*
    |--------------------------------------------------------------------------
    | FILTER RIWAYAT
    |--------------------------------------------------------------------------
    */

    const [
        historyType,
        setHistoryType
    ] = useState("FRC");

    const [
        historyBank,
        setHistoryBank
    ] = useState("ALL");

    const [
        historyAccount,
        setHistoryAccount
    ] = useState("ALL");

    const [
        historyCategory,
        setHistoryCategory
    ] = useState("ALL");

    const [
        historyDateStart,
        setHistoryDateStart
    ] = useState("");

    const [
        historyDateEnd,
        setHistoryDateEnd
    ] = useState("");

    const [
        historySearch,
        setHistorySearch
    ] = useState("");

    const [
        historyAccounts,
        setHistoryAccounts
    ] = useState([]);

    const [
        historyBanks,
        setHistoryBanks
    ] = useState([]);


    /*
    |--------------------------------------------------------------------------
    | DETAIL RIWAYAT
    |--------------------------------------------------------------------------
    */

    const [
        historyExpandedRow,
        setHistoryExpandedRow
    ] = useState(null);


    /*
    |--------------------------------------------------------------------------
    | MANUAL RECONCILIATION
    |--------------------------------------------------------------------------
    */

    const [
        manualReceipt,
        setManualReceipt
    ] = useState(null);

    const [
        manualMutasi,
        setManualMutasi
    ] = useState(null);

    const [
        manualReconLoading,
        setManualReconLoading
    ] = useState(false);

    const resetHistoryFilter = () => {

        setHistoryBank("ALL");

        setHistoryAccount("ALL");

        setHistoryCategory("ALL");

        setHistoryDateStart("");

        setHistoryDateEnd("");

        setHistorySearch("");

        setHistoryExpandedRow(null);

        setManualReceipt(null);

        setManualMutasi(null);
    };

    /*
    |--------------------------------------------------------------------------
    | RESET SEMUA FORM KETIKA CABANG BERUBAH
    |--------------------------------------------------------------------------
    */

    const previousCabangRef = useRef(cabang);

    useEffect(() => {
        if (previousCabangRef.current === cabang) {
            return;
        }

        previousCabangRef.current = cabang;

        /*
        |--------------------------------------------------------------------------
        | RESET FORM IMPORT
        |--------------------------------------------------------------------------
        */

        resetUpload();

        /*
        |--------------------------------------------------------------------------
        | RESET FORM REKONSILIASI
        |--------------------------------------------------------------------------
        */

        resetReconciliation();

        setReconType("REG");
        setReconAccount("ALL");
        setResultFilter("ALL");
        setSearch("");
        setShowFilter(false);
        setExpandedRow(null);

        // Hapus daftar rekening cabang sebelumnya.
        setAccounts([]);

        /*
        |--------------------------------------------------------------------------
        | RESET HASIL RIWAYAT REKONSILIASI
        |--------------------------------------------------------------------------
        */

        setHistoryData([]);
        setHistoryExecuted(false);
        setHistoryExpandedRow(null);

        /*
        |--------------------------------------------------------------------------
        | RESET FILTER RIWAYAT
        |--------------------------------------------------------------------------
        */

        setHistoryType("FRC");
        setHistoryBank("ALL");
        setHistoryAccount("ALL");
        setHistoryCategory("ALL");

        setHistoryDateStart("");
        setHistoryDateEnd("");
        setHistorySearch("");

        // Hapus pilihan filter milik cabang sebelumnya.
        setHistoryBanks([]);
        setHistoryAccounts([]);

        /*
        |--------------------------------------------------------------------------
        | RESET DETAIL REKONSILIASI MANUAL
        |--------------------------------------------------------------------------
        */

        setManualReceipt(null);
        setManualMutasi(null);

    }, [
        cabang,
        resetReconciliation
    ]);


    const fetchHistoryFilters = async () => {

        if (!cabang) {
            setHistoryBanks([]);
            setHistoryAccounts([]);
            return;
        }

        try {

            const response =
                await axios.get(
                    "/api/rekon/history/filters",
                    {
                        params: {
                            cabang,
                            type: historyType
                        }
                    }
                );

            const result =
                response?.data || {};

            if (!result.success) {

                throw new Error(
                    result.message ||
                    "Filter histori gagal dimuat."
                );
            }

            setHistoryBanks(
                Array.isArray(result.banks)
                    ? result.banks
                    : []
            );

            setHistoryAccounts(
                Array.isArray(result.accounts)
                    ? result.accounts
                    : []
            );

        } catch (error) {

            console.error(
                "History filter error:",
                error
            );

            setHistoryBanks([]);
            setHistoryAccounts([]);

            await Swal.fire({
                icon: "error",
                title: "Gagal Memuat Filter",
                text: getErrorMessage(
                    error,
                    "Daftar filter histori gagal dimuat."
                )
            });
        }
    };

    useEffect(() => {

        if (
            activeTab !==
            "history"
        ) {
            return;
        }

        fetchHistoryFilters();

    }, [
        activeTab,
        cabang,
        historyType
    ]);

    const searchReconciliationHistory =
        async () => {

            if (historyLoading) {
                return;
            }

            if (!cabang) {

                await Swal.fire({
                    icon: "warning",
                    title: "Cabang Belum Tersedia",
                    text: "Cabang belum tersedia."
                });

                return;
            }


            setHistoryLoading(true);

            setHistoryData([]);

            setHistoryExpandedRow(null);

            try {

                const response =
                    await axios.get(
                        "/api/rekon/history",
                        {
                            params: {

                                cabang,

                                type:
                                    historyType,

                                jns_bank:
                                    historyBank !== "ALL"
                                        ? historyBank
                                        : "",

                                no_rek:
                                    historyAccount !== "ALL"
                                        ? historyAccount
                                        : "",

                                category:
                                    historyCategory !== "ALL"
                                        ? historyCategory
                                        : "",

                                date_start:
                                    historyDateStart || "",

                                date_end:
                                    historyDateEnd || "",

                                search:
                                    historySearch.trim()
                            }
                        }
                    );


                const result =
                    response?.data || {};


                if (!result.success) {

                    throw new Error(
                        result.message ||
                        "Data histori gagal dimuat."
                    );
                }


                setHistoryData(
                    Array.isArray(
                        result.data
                    )
                        ? result.data
                        : []
                );


                setHistoryExecuted(
                    true
                );

            } catch (error) {

                console.error(
                    "History reconciliation error:",
                    error
                );

                setHistoryExecuted(
                    false
                );

                await Swal.fire({
                    icon: "error",
                    title: "Gagal Memuat Riwayat",
                    text: getErrorMessage(
                        error,
                        "Data riwayat rekonsiliasi gagal dimuat."
                    )
                });

            } finally {

                setHistoryLoading(
                    false
                );
            }
        };

    const filteredHistoryData =
        useMemo(() => {

            const keyword =
                historySearch
                    .trim()
                    .toLowerCase();


            return historyData.filter(
                item => {

                    const category =
                        getResultCategory(
                            item
                        );


                    if (
                        historyCategory !==
                        "ALL" &&
                        category !==
                        historyCategory
                    ) {

                        return false;
                    }


                    if (
                        historyBank !==
                        "ALL" &&
                        String(
                            item.jns_bank || ""
                        ).trim()
                        !==
                        String(
                            historyBank
                        ).trim()
                    ) {

                        return false;
                    }


                    const account =
                        item.no_rek ||
                        item.remittance_bank_account ||
                        "";


                    if (
                        historyAccount !==
                        "ALL" &&
                        String(account).trim()
                        !==
                        String(historyAccount).trim()
                    ) {

                        return false;
                    }


                    if (
                        historyDateStart
                    ) {

                        const date =
                            String(
                                item.mutasi_date ||
                                item.receipt_date ||
                                ""
                            ).substring(0, 10);


                        if (
                            date <
                            historyDateStart
                        ) {

                            return false;
                        }
                    }


                    if (
                        historyDateEnd
                    ) {

                        const date =
                            String(
                                item.mutasi_date ||
                                item.receipt_date ||
                                ""
                            ).substring(0, 10);


                        if (
                            date >
                            historyDateEnd
                        ) {

                            return false;
                        }
                    }


                    if (!keyword) {
                        return true;
                    }


                    const searchable = [

                        item.reff,

                        item.no_rek,

                        item.remittance_bank_account,

                        item.jns_bank,

                        item.receipt_number,

                        item.mutation_number,

                        item.description,

                        item.comments,

                        item.receipt_status,

                        item.receipt_state

                    ]
                        .filter(Boolean)
                        .join(" ")
                        .toLowerCase();


                    return searchable.includes(
                        keyword
                    );
                }
            );

        }, [
            historyData,
            historySearch,
            historyCategory,
            historyBank,
            historyAccount,
            historyDateStart,
            historyDateEnd
        ]);

    const selectManualReceipt = (
        item
    ) => {

        setManualReceipt(
            current =>
                current?.id === item.id
                    ? null
                    : item
        );
    };

    const selectManualMutasi = (
        item
    ) => {

        setManualMutasi(
            current =>
                current?.id === item.id
                    ? null
                    : item
        );
    };

    const executeManualReconciliation =
        async () => {

            if (manualReconLoading) {
                return;
            }


            if (!manualReceipt) {

                await Swal.fire({
                    icon: "warning",
                    title: "Receipt Belum Dipilih",
                    text: "Pilih data Receipt terlebih dahulu."
                });

                return;
            }


            if (!manualMutasi) {

                await Swal.fire({
                    icon: "warning",
                    title: "Mutasi Belum Dipilih",
                    text: "Pilih data Mutasi terlebih dahulu."
                });

                return;
            }


            const receiptAmount =
                Number(
                    manualReceipt.receipt_amount ||
                    0
                );


            const mutasiAmount =
                Number(
                    manualMutasi.mutasi_amount ||
                    manualMutasi.cr ||
                    0
                );


            const difference =
                receiptAmount -
                mutasiAmount;


            const confirm =
                await Swal.fire({

                    icon:
                        difference === 0
                            ? "question"
                            : "warning",

                    title:
                        "Konfirmasi Rekonsiliasi Manual",

                    html: `
                        <div style="text-align:left">

                            <p>
                                <strong>Receipt</strong>
                            </p>

                            <p>
                                No:
                                ${manualReceipt.receipt_number || "-"}
                            </p>

                            <p>
                                Nominal:
                                ${formatCurrency(receiptAmount)}
                            </p>

                            <hr style="margin:12px 0">

                            <p>
                                <strong>Mutasi</strong>
                            </p>

                            <p>
                                ID:
                                ${manualMutasi.id}
                            </p>

                            <p>
                                Nominal:
                                ${formatCurrency(mutasiAmount)}
                            </p>

                            <hr style="margin:12px 0">

                            <p>
                                Selisih:
                                <strong>
                                    ${formatCurrency(difference)}
                                </strong>
                            </p>

                        </div>
                    `,

                    showCancelButton:
                        true,

                    confirmButtonText:
                        "Ya, Rekonsiliasi",

                    cancelButtonText:
                        "Batal"
                });


            if (
                !confirm.isConfirmed
            ) {

                return;
            }


            setManualReconLoading(
                true
            );


            try {

                const response =
                    await axios.post(
                        "/api/rekon/manual",
                        {

                            cabang,

                            type:
                                historyType,

                            receipt_id:
                                manualReceipt.id,

                            mutasi_id:
                                manualMutasi.id
                        }
                    );


                const result =
                    response?.data || {};


                if (
                    !result.success
                ) {

                    throw new Error(
                        result.message ||
                        "Rekonsiliasi manual gagal."
                    );
                }


                await Swal.fire({
                    icon: "success",
                    title: "Berhasil",
                    text:
                        result.message ||
                        "Rekonsiliasi manual berhasil dilakukan.",
                    confirmButtonText: "OK"
                });


                setManualReceipt(
                    null
                );

                setManualMutasi(
                    null
                );


                /*
                |--------------------------------------------------------------------------
                | REFRESH HASIL HISTORI
                |--------------------------------------------------------------------------
                */

                await searchReconciliationHistory();


            } catch (error) {

                console.error(
                    "Manual reconciliation error:",
                    error
                );


                await Swal.fire({
                    icon: "error",
                    title: "Rekonsiliasi Manual Gagal",
                    text: getErrorMessage(
                        error,
                        "Rekonsiliasi manual gagal dilakukan."
                    )
                });

            } finally {

                setManualReconLoading(
                    false
                );
            }
        };

    return (

        <main className="
            flex-1
            px-3
            py-2
            z-10
            text-gray-800
        ">

            <div className="
                min-h-[calc(100vh-50px)]
                bg-white/60
                rounded-lg
                shadow-lg
                overflow-hidden
                overflow-y: auto
            ">


                {/* ==========================================================
                    HEADER
                =========================================================== */}

                <div className="
                    bg-white
                    border-b
                    px-4
                    sm:px-6
                    py-4
                ">

                    <div className="
                        flex
                        flex-col
                        lg:flex-row
                        lg:items-center
                        lg:justify-between
                        gap-4
                    ">

                        <div>

                            <div className="
                                flex
                                items-center
                                gap-3
                            ">

                                <div className="
                                    w-11
                                    h-11
                                    rounded-xl
                                    bg-blue-100
                                    text-blue-600
                                    flex
                                    items-center
                                    justify-center
                                    shrink-0
                                ">

                                    <FaExchangeAlt
                                        className="
                                            text-xl
                                        "
                                    />

                                </div>


                                <div>

                                    <h1 className="
                                        text-xl
                                        sm:text-2xl
                                        font-bold
                                    ">
                                        Rekonsiliasi Bank AR
                                    </h1>


                                    <p className="
                                        text-sm
                                        text-gray-500
                                        mt-0.5
                                    ">

                                        Cabang{" "}

                                        <strong>
                                            {
                                                cabang ||
                                                "-"
                                            }
                                        </strong>

                                    </p>

                                </div>

                            </div>

                        </div>


                        {/* ==================================================
                            HEADER ACTION
                        =================================================== */}

                        <div className="
                            flex
                            flex-wrap
                            gap-2
                        ">

                            <button
                                type="button"
                                onClick={
                                    fetchActivePeriod
                                }
                                disabled={
                                    loadingPeriod ||
                                    loading
                                }
                                className="
                                    inline-flex
                                    items-center
                                    gap-2
                                    px-3
                                    py-2
                                    rounded-lg
                                    border
                                    bg-white
                                    hover:bg-gray-50
                                    text-sm
                                    font-medium
                                    disabled:opacity-50
                                "
                            >

                                <FaSyncAlt
                                    className={
                                        loadingPeriod
                                            ? "animate-spin"
                                            : ""
                                    }
                                />

                                Refresh Periode

                            </button>

                        </div>

                    </div>


                    {/* ======================================================
                        PERIOD INFORMATION
                    ======================================================= */}

                    <div className="
                        mt-4
                        grid
                        grid-cols-1
                        md:grid-cols-3
                        gap-3
                    ">


                        {/* CABANG */}

                        <div className="
                            rounded-xl
                            border
                            bg-gray-50
                            p-3
                        ">

                            <div className="
                                flex
                                items-center
                                gap-2
                                text-xs
                                text-gray-500
                            ">

                                <FaUniversity />

                                Cabang

                            </div>


                            <p className="
                                mt-1
                                font-bold
                                text-gray-800
                            ">
                                {cabang || "-"}
                            </p>

                        </div>


                        {/* PERIOD */}

                        <div className="
                            rounded-xl
                            border
                            bg-blue-50
                            border-blue-100
                            p-3
                        ">

                            <div className="
                                flex
                                items-center
                                gap-2
                                text-xs
                                text-blue-600
                            ">

                                <FaCalendarAlt />

                                Periode Mutasi Aktif

                            </div>


                            {loadingPeriod ? (

                                <div className="
                                    mt-2
                                    flex
                                    items-center
                                    gap-2
                                    text-sm
                                    text-blue-600
                                ">

                                    <FaSyncAlt
                                        className="
                                            animate-spin
                                        "
                                    />

                                    Memuat periode...

                                </div>

                            ) : period ? (

                                <>

                                    <p className="
                                        mt-1
                                        font-bold
                                        text-blue-800
                                    ">
                                        {
                                            period.periode ||
                                            "-"
                                        }
                                    </p>


                                    <p className="
                                        text-xs
                                        text-blue-600
                                        mt-1
                                    ">

                                        {
                                            formatDate(
                                                period.start_date
                                            )
                                        }

                                        {" - "}

                                        {
                                            formatDate(
                                                period.end_date
                                            )
                                        }

                                    </p>

                                </>

                            ) : (

                                <div className="
                                    mt-1
                                    flex
                                    items-center
                                    gap-2
                                    text-sm
                                    font-semibold
                                    text-red-600
                                ">

                                    <FaTimesCircle />

                                    Tidak ada periode aktif

                                </div>

                            )}

                        </div>


                        {/* STATUS PERIODE */}

                        <div className="
                            rounded-xl
                            border
                            bg-gray-50
                            p-3
                        ">

                            <div className="
                                flex
                                items-center
                                gap-2
                                text-xs
                                text-gray-500
                            ">

                                <FaExchangeAlt />

                                Status Periode

                            </div>


                            <p className="
                                mt-1
                                font-bold
                                text-gray-800
                            ">

                                {
                                    period?.status ||
                                    "-"
                                }

                            </p>


                            {period?.id && (

                                <p className="
                                    text-xs
                                    text-gray-500
                                    mt-1
                                ">

                                    Period ID:{" "}

                                    {
                                        period.id
                                    }

                                </p>

                            )}

                        </div>

                    </div>

                </div>


                {/* ==========================================================
                    TAB NAVIGATION
                =========================================================== */}

                <div className="
                    bg-white
                    border-b
                    px-3
                    sm:px-6
                ">

                    <div className="
                        flex
                        overflow-x-auto
                        gap-1
                    ">


                        {/* IMPORT */}

                        <button
                            type="button"
                            onClick={() =>
                                setActiveTab(
                                    "import"
                                )
                            }
                            className={`
                                shrink-0
                                px-4
                                py-3
                                text-sm
                                font-semibold
                                border-b-2
                                transition

                                ${
                                    activeTab ===
                                    "import"

                                        ? `
                                            border-blue-600
                                            text-blue-600
                                        `

                                        : `
                                            border-transparent
                                            text-gray-500
                                            hover:text-gray-700
                                        `
                                }
                            `}
                        >

                            <span className="
                                inline-flex
                                items-center
                                gap-2
                            ">

                                <FaUpload />

                                Import Receipt

                            </span>

                        </button>

                        {/* UPDATE MUTASI */}

                        <button
                            type="button"
                            onClick={() => setActiveTab("update-mutasi")}
                            className={`
                                shrink-0
                                px-4
                                py-3
                                text-sm
                                font-semibold
                                border-b-2
                                transition
                                ${
                                    activeTab === "update-mutasi"
                                        ? "border-blue-600 text-blue-600"
                                        : "border-transparent text-gray-500 hover:text-gray-700"
                                }
                            `}
                        >
                            <span className="inline-flex items-center gap-2">
                                <FaEdit />
                                Update Mutasi / Receipt
                            </span>
                        </button>

                        {/* RECONCILIATION */}

                        <button
                            type="button"
                            onClick={() =>
                                setActiveTab(
                                    "reconciliation"
                                )
                            }
                            className={`
                                shrink-0
                                px-4
                                py-3
                                text-sm
                                font-semibold
                                border-b-2
                                transition

                                ${
                                    activeTab ===
                                    "reconciliation"

                                        ? `
                                            border-blue-600
                                            text-blue-600
                                        `

                                        : `
                                            border-transparent
                                            text-gray-500
                                            hover:text-gray-700
                                        `
                                }
                            `}
                        >

                            <span className="
                                inline-flex
                                items-center
                                gap-2
                            ">

                                <FaExchangeAlt />

                                Rekonsiliasi

                            </span>

                        </button>
                            
                        {/* HISTORY */}

                        <button
                            type="button"
                            onClick={() =>
                                setActiveTab(
                                    "history"
                                )
                            }
                            className={`
                                shrink-0
                                px-4
                                py-3
                                text-sm
                                font-semibold
                                border-b-2
                                transition

                                ${
                                    activeTab ===
                                    "history"

                                        ? `
                                            border-blue-600
                                            text-blue-600
                                        `

                                        : `
                                            border-transparent
                                            text-gray-500
                                            hover:text-gray-700
                                        `
                                }
                            `}
                        >

                            <span className="
                                inline-flex
                                items-center
                                gap-2
                            ">

                                <FaHistory />

                                Riwayat Rekonsiliasi

                            </span>

                        </button>

                        {/* DOWNLOAD RECONCILIATION */}

                        <button
                            type="button"
                            onClick={() =>
                                setActiveTab(
                                    "download"
                                )
                            }
                            className={`
                                shrink-0
                                px-4
                                py-3
                                text-sm
                                font-semibold
                                border-b-2
                                transition

                                ${
                                    activeTab ===
                                    "download"

                                        ? `
                                            border-blue-600
                                            text-blue-600
                                        `

                                        : `
                                            border-transparent
                                            text-gray-500
                                            hover:text-gray-700
                                        `
                                }
                            `}
                        >
                            <span className="
                                inline-flex
                                items-center
                                gap-2
                            ">
                                <FaDownload />

                                Download Rekonsiliasi
                            </span>
                        </button>
                    </div>

                </div>


                {/* ==========================================================
                    CONTENT
                =========================================================== */}

                <div className="
                    p-3
                    sm:p-4
                ">


                    {/* ======================================================
                        IMPORT
                    ======================================================= */}

                    {activeTab === "import" && (

                        <div className="
                            max-w
                            mx-auto
                        ">

                            <div className="
                                bg-white
                                rounded-xl
                                border
                                shadow-sm
                                overflow-hidden
                            ">

                                <div className="
                                    p-4
                                    border-b
                                ">

                                    <h2 className="
                                        text-lg
                                        font-bold
                                    ">
                                        Import Data Receipt
                                    </h2>


                                    <p className="
                                        text-sm
                                        text-gray-500
                                        mt-1
                                    ">
                                        Import file Receipt
                                        untuk cabang dan
                                        periode Mutasi aktif.
                                    </p>

                                </div>


                                <div className="
                                    p-4
                                    sm:p-6
                                ">


                                    {/* PERIOD INFORMATION */}

                                    <div className="
                                        grid
                                        grid-cols-1
                                        sm:grid-cols-2
                                        gap-3
                                        mb-5
                                    ">

                                        <div className="
                                            rounded-lg
                                            bg-gray-50
                                            border
                                            p-3
                                        ">

                                            <p className="
                                                text-xs
                                                text-gray-500
                                            ">
                                                Cabang
                                            </p>


                                            <p className="
                                                mt-1
                                                font-semibold
                                            ">
                                                {cabang || "-"}
                                            </p>

                                        </div>


                                        <div className="
                                            rounded-lg
                                            bg-blue-50
                                            border
                                            border-blue-100
                                            p-3
                                        ">

                                            <p className="
                                                text-xs
                                                text-blue-600
                                            ">
                                                Periode Mutasi Aktif
                                            </p>


                                            <p className="
                                                mt-1
                                                font-semibold
                                                text-blue-800
                                            ">
                                                {
                                                    period?.periode ||
                                                    "-"
                                                }
                                            </p>


                                            {period?.start_date && (

                                                <p className="
                                                    text-xs
                                                    text-blue-600
                                                    mt-1
                                                ">

                                                    {
                                                        formatDate(
                                                            period.start_date
                                                        )
                                                    }

                                                    {" - "}

                                                    {
                                                        formatDate(
                                                            period.end_date
                                                        )
                                                    }

                                                </p>

                                            )}

                                        </div>

                                    </div>


                                    {/* PERIOD WARNING */}

                                    {!period?.id &&
                                        !loadingPeriod && (

                                            <div className="
                                                mb-4
                                                rounded-lg
                                                border
                                                border-red-200
                                                bg-red-50
                                                p-3
                                                text-sm
                                                text-red-700
                                                flex
                                                gap-2
                                            ">

                                                <FaTimesCircle
                                                    className="
                                                        mt-0.5
                                                        shrink-0
                                                    "
                                                />

                                                Tidak terdapat
                                                periode Mutasi
                                                aktif untuk
                                                cabang ini.
                                                Import dinonaktifkan
                                                sampai periode
                                                tersedia.

                                            </div>

                                        )}


                                    {/* ==================================================
                                        DROP ZONE
                                    =================================================== */}

                                    <div
                                        onClick={
                                            openFilePicker
                                        }
                                        role="button"
                                        tabIndex={0}
                                        onKeyDown={
                                            event => {

                                                if (
                                                    event.key ===
                                                        "Enter" ||
                                                    event.key ===
                                                        " "
                                                ) {

                                                    event.preventDefault();

                                                    openFilePicker();

                                                }

                                            }
                                        }
                                        className={`
                                            block
                                            border-2
                                            border-dashed
                                            rounded-xl
                                            p-8
                                            sm:p-12
                                            text-center
                                            transition
                                            select-none

                                            ${
                                                period?.id &&
                                                !uploading

                                                    ? "cursor-pointer"

                                                    : "cursor-not-allowed opacity-60"
                                            }

                                            ${
                                                selectedFiles.length

                                                    ? `
                                                        border-green-400
                                                        bg-green-50
                                                    `

                                                    : `
                                                        border-gray-300
                                                        hover:border-blue-400
                                                        hover:bg-blue-50
                                                    `
                                            }
                                        `}
                                    >

                                        {selectedFiles.length ? (

                                            <>

                                                <FaCheckCircle
                                                    className="
                                                        mx-auto
                                                        text-5xl
                                                        text-green-500
                                                        mb-3
                                                    "
                                                />


                                                <p className="
                                                    font-semibold
                                                    text-green-700
                                                ">

                                                    {
                                                        selectedFiles.length
                                                    }

                                                    {" "}

                                                    file dipilih

                                                </p>


                                                <p className="
                                                    text-xs
                                                    text-gray-500
                                                    mt-1
                                                ">
                                                    Klik untuk
                                                    mengganti file.
                                                </p>

                                            </>

                                        ) : (

                                            <>

                                                <FaCloudUploadAlt
                                                    className="
                                                        mx-auto
                                                        text-5xl
                                                        text-blue-500
                                                        mb-3
                                                    "
                                                />


                                                <p className="
                                                    font-semibold
                                                    text-gray-700
                                                ">
                                                    Pilih File Receipt
                                                </p>


                                                <p className="
                                                    text-sm
                                                    text-gray-500
                                                    mt-1
                                                ">
                                                    CSV / TXT
                                                </p>

                                            </>

                                        )}


                                        {/* ==================================================
                                            FILE INPUT
                                        =================================================== */}

                                        <input
                                            ref={
                                                fileInputRef
                                            }
                                            type="file"
                                            multiple
                                            accept=".csv,.txt"
                                            onChange={
                                                handleFileChange
                                            }
                                            disabled={
                                                !period?.id ||
                                                uploading
                                            }
                                            className="
                                                hidden
                                            "
                                        />

                                    </div>


                                    {/* ==================================================
                                        FILE LIST
                                    =================================================== */}

                                    {selectedFiles.length > 0 && (

                                        <div className="
                                            mt-4
                                            border
                                            rounded-lg
                                            divide-y
                                        ">

                                            {selectedFiles.map(
                                                (
                                                    file,
                                                    index
                                                ) => (

                                                    <div
                                                        key={`
                                                            ${file.name}
                                                            -
                                                            ${index}
                                                        `}
                                                        className="
                                                            flex
                                                            items-center
                                                            justify-between
                                                            gap-3
                                                            px-3
                                                            py-2.5
                                                        "
                                                    >

                                                        <div className="
                                                            min-w-0
                                                        ">

                                                            <p className="
                                                                text-sm
                                                                font-medium
                                                                truncate
                                                            ">
                                                                {
                                                                    file.name
                                                                }
                                                            </p>


                                                            <p className="
                                                                text-xs
                                                                text-gray-500
                                                            ">

                                                                {
                                                                    (
                                                                        file.size /
                                                                        1024
                                                                    ).toFixed(
                                                                        1
                                                                    )
                                                                }

                                                                {" KB"}

                                                            </p>

                                                        </div>


                                                        <button
                                                            type="button"
                                                            onClick={() =>
                                                                removeFile(
                                                                    index
                                                                )
                                                            }
                                                            disabled={
                                                                uploading
                                                            }
                                                            className="
                                                                w-8
                                                                h-8
                                                                shrink-0
                                                                rounded-lg
                                                                text-red-500
                                                                hover:bg-red-50
                                                                flex
                                                                items-center
                                                                justify-center
                                                                disabled:opacity-50
                                                            "
                                                        >

                                                            <FaTrash />

                                                        </button>

                                                    </div>

                                                )
                                            )}

                                        </div>

                                    )}


                                    {/* ==================================================
                                        ACTION
                                    =================================================== */}

                                    <div className="
                                        mt-5
                                        flex
                                        flex-col-reverse
                                        sm:flex-row
                                        sm:justify-end
                                        gap-2
                                    ">

                                        <button
                                            type="button"
                                            onClick={
                                                resetUpload
                                            }
                                            disabled={
                                                uploading
                                            }
                                            className="
                                                px-4
                                                py-2.5
                                                rounded-lg
                                                border
                                                text-gray-700
                                                hover:bg-gray-50
                                                disabled:opacity-50
                                            "
                                        >
                                            Reset
                                        </button>


                                        <button
                                            type="button"
                                            onClick={
                                                handleImport
                                            }
                                            disabled={
                                                uploading ||
                                                !selectedFiles.length ||
                                                !period?.id
                                            }
                                            className="
                                                px-5
                                                py-2.5
                                                rounded-lg
                                                bg-blue-600
                                                hover:bg-blue-700
                                                text-white
                                                font-semibold
                                                inline-flex
                                                items-center
                                                justify-center
                                                gap-2
                                                disabled:bg-gray-400
                                            "
                                        >

                                            {uploading ? (

                                                <>

                                                    <FaSyncAlt
                                                        className="
                                                            animate-spin
                                                        "
                                                    />

                                                    Memproses...

                                                </>

                                            ) : (

                                                <>

                                                    <FaUpload />

                                                    Import Receipt

                                                </>

                                            )}

                                        </button>

                                    </div>

                                </div>

                            </div>

                        </div>

                    )}

                    {/* ======================================================
                        UPDATE MUTASI
                    ====================================================== */}

                    {activeTab === "update-mutasi" && (
                        <div className="w-full space-y-4">
                            {/* TEXTAREA */}

                            <div className="bg-white rounded-xl border shadow-sm p-4 space-y-3">

                                <div className="flex items-center justify-between gap-3 flex-wrap">
                                    <label className="text-sm font-semibold text-gray-700">
                                        Paste Data From Excel
                                    </label>

                                    <span className="text-xs text-gray-500">
                                        {updateMutasiText
                                            ? `${updateMutasiText.split(/\r\n|\r|\n/).filter(line => line.trim() !== "").length} baris ditempel`
                                            : "Belum ada data"}
                                    </span>
                                </div>

                                <textarea
                                    value={updateMutasiText}
                                    onChange={(event) =>
                                        handleParseUpdateMutasi(event.target.value)
                                    }
                                    placeholder={`Salin tabel dari Excel, termasuk judul kolom, kemudian tempelkan di sini.

                    Contoh:
                    id    no_rek    tgl    dept    site
                    101   0306584744    2026-10-03    SLS    TH7M`}
                                    rows={8}
                                    spellCheck={false}
                                    className="
                                        w-full
                                        border border-gray-300
                                        rounded-lg
                                        p-3
                                        text-sm
                                        font-mono
                                        focus:outline-none
                                        focus:ring-2
                                        focus:ring-blue-500
                                        focus:border-blue-500
                                        resize-y
                                    "
                                />

                                <div className="flex gap-2 flex-wrap">
                                    <button
                                        type="button"
                                        onClick={() => {
                                            if (!updateMutasiText.trim()) return;

                                            handleParseUpdateMutasi(updateMutasiText);
                                        }}
                                        disabled={!updateMutasiText.trim()}
                                        className="
                                            inline-flex items-center gap-2
                                            px-4 py-2 rounded-lg
                                            bg-blue-600 text-white
                                            text-sm font-semibold
                                            hover:bg-blue-700
                                            disabled:opacity-50 disabled:cursor-not-allowed
                                        "
                                    >
                                        <FaSyncAlt />
                                        Tampilkan Tabel
                                    </button>

                                    <button
                                        type="button"
                                        onClick={resetUpdateMutasi}
                                        disabled={!updateMutasiText}
                                        className="
                                            inline-flex items-center gap-2
                                            px-4 py-2 rounded-lg
                                            border border-gray-300
                                            text-gray-700
                                            text-sm font-semibold
                                            hover:bg-gray-50
                                            disabled:opacity-50 disabled:cursor-not-allowed
                                        "
                                    >
                                        <FaTrash />
                                        Bersihkan
                                    </button>

                                    <button
                                        type="button"
                                        onClick={handleProcessUpdateMutasi}
                                        disabled={
                                            updatingMutasi ||
                                            updateMutasiHeaders.length === 0 ||
                                            updateMutasiRows.length === 0
                                        }
                                        className="
                                            inline-flex items-center gap-2
                                            px-4 py-2 rounded-lg
                                            bg-emerald-600 text-white
                                            text-sm font-semibold
                                            hover:bg-emerald-700
                                            disabled:opacity-50 disabled:cursor-not-allowed
                                        "
                                    >
                                        {updatingMutasi ? (
                                            <>
                                                <FaSyncAlt className="animate-spin" />
                                                Memproses Update...
                                            </>
                                        ) : (
                                            <>
                                                <FaEdit />
                                                Update
                                            </>
                                        )}
                                    </button>
                                </div>
                            </div>

                            {/* HTML TABLE PREVIEW */}

                            {updateMutasiHeaders.length > 0 && (
                                <div className="bg-white rounded-xl border shadow-sm overflow-hidden">

                                    <div className="p-4 border-b flex items-center justify-between gap-3 flex-wrap">
                                        <div>
                                            <h3 className="font-bold text-gray-800">
                                                Pratinjau Data Mutasi
                                            </h3>

                                            <p className="text-xs text-gray-500 mt-1">
                                                Periksa kolom dan data sebelum proses update
                                                dikembangkan.
                                            </p>
                                        </div>

                                        <span className="px-3 py-1 rounded-full bg-blue-50 text-blue-700 text-xs font-semibold">
                                            {updateMutasiRows.length} data
                                        </span>
                                    </div>

                                    <div className="w-full overflow-auto max-h-[600px]">
                                        <table className="min-w-full border-collapse text-sm">

                                            <thead className="sticky top-0 z-10 bg-gray-100">
                                                <tr>
                                                    <th className="border px-3 py-2 text-center whitespace-nowrap">
                                                        No.
                                                    </th>

                                                    {updateMutasiHeaders.map((header, index) => (
                                                        <th
                                                            key={`header-${index}`}
                                                            className="
                                                                border px-3 py-2
                                                                text-left whitespace-nowrap
                                                                font-semibold text-gray-700
                                                            "
                                                        >
                                                            {header || `Kolom ${index + 1}`}
                                                        </th>
                                                    ))}
                                                </tr>
                                            </thead>

                                            <tbody>
                                                {updateMutasiRows.map((row, rowIndex) => (
                                                    <tr
                                                        key={`row-${row._rowNumber}`}
                                                        className={
                                                            rowIndex % 2 === 0
                                                                ? "bg-white hover:bg-blue-50"
                                                                : "bg-gray-50 hover:bg-blue-50"
                                                        }
                                                    >
                                                        <td className="border px-3 py-2 text-center text-gray-500">
                                                            {row._rowNumber}
                                                        </td>

                                                        {row.cells.map((cell, cellIndex) => (
                                                            <td
                                                                key={`cell-${row._rowNumber}-${cellIndex}`}
                                                                className="
                                                                    border px-3 py-2
                                                                    whitespace-nowrap
                                                                    text-gray-700
                                                                "
                                                            >
                                                                {cell || (
                                                                    <span className="text-gray-300">
                                                                        -
                                                                    </span>
                                                                )}
                                                            </td>
                                                        ))}
                                                    </tr>
                                                ))}

                                                {updateMutasiRows.length === 0 && (
                                                    <tr>
                                                        <td
                                                            colSpan={updateMutasiHeaders.length + 1}
                                                            className="border px-4 py-8 text-center text-gray-500"
                                                        >
                                                            Tidak ada baris data untuk ditampilkan.
                                                        </td>
                                                    </tr>
                                                )}
                                            </tbody>

                                        </table>
                                    </div>
                                </div>
                            )}
                        </div>
                    )}

                    {/* ======================================================
                        RECONCILIATION
                    ======================================================= */}

                    {activeTab === "reconciliation" && (
                    <>

                        {/* ==================================================
                            RECON CONTROL
                        =================================================== */}

                        <div className="
                            bg-white
                            rounded-xl
                            border
                            shadow-sm
                            p-4
                        ">

                            {/* ==================================================
                                HEADER
                            =================================================== */}

                            <div className="
                                flex
                                items-center
                                gap-2
                                mb-4
                            ">

                                <div className="
                                    w-9
                                    h-9
                                    rounded-lg
                                    bg-blue-100
                                    text-blue-600
                                    flex
                                    items-center
                                    justify-center
                                ">

                                    <FaExchangeAlt />

                                </div>


                                <div>

                                    <h2 className="
                                        font-bold
                                        text-lg
                                    ">
                                        Rekonsiliasi Receipt
                                    </h2>


                                    <p className="
                                        text-xs
                                        text-gray-500
                                    ">
                                        Pilih jenis rekonsiliasi dan rekening
                                        sebelum proses dijalankan.
                                    </p>

                                </div>

                            </div>
                            
                            {/* ==================================================
                                CONTROL REKONSILIASI
                            =================================================== */}

                            <div className="space-y-5">

                                {/* BARIS UTAMA: JENIS, REKENING, TOMBOL */}
                                <div className="grid grid-cols-1 gap-4 lg:grid-cols-12 lg:items-end">

                                    {/* JENIS REKONSILIASI */}
                                    <div className="min-w-0 lg:col-span-4">
                                        <label className="mb-2 block text-sm font-semibold text-gray-700">
                                            Jenis Rekonsiliasi
                                        </label>

                                        <div className="grid grid-cols-2 gap-2">
                                            <button
                                                type="button"
                                                onClick={() => handleReconTypeChange("FRC")}
                                                disabled={loading}
                                                className={`flex min-h-11 items-center justify-center rounded-lg border px-3 py-2.5 text-sm font-semibold transition disabled:cursor-not-allowed disabled:opacity-50 ${
                                                    reconType === "FRC"
                                                        ? "border-blue-600 bg-blue-600 text-white shadow-sm"
                                                        : "border-gray-200 bg-white text-gray-700 hover:border-blue-300 hover:bg-blue-50"
                                                }`}
                                            >
                                                Franchise
                                            </button>

                                            <button
                                                type="button"
                                                onClick={() => handleReconTypeChange("REG")}
                                                disabled={loading}
                                                className={`flex min-h-11 items-center justify-center rounded-lg border px-3 py-2.5 text-sm font-semibold transition disabled:cursor-not-allowed disabled:opacity-50 ${
                                                    reconType === "REG"
                                                        ? "border-blue-600 bg-blue-600 text-white shadow-sm"
                                                        : "border-gray-200 bg-white text-gray-700 hover:border-blue-300 hover:bg-blue-50"
                                                }`}
                                            >
                                                Reguler
                                            </button>
                                        </div>
                                    </div>

                                    {/* REKENING / JENIS BANK */}
                                    <div className="min-w-0 lg:col-span-4">
                                        <label className="mb-2 flex min-h-5 flex-wrap items-center justify-between gap-2 text-sm font-semibold text-gray-700">
                                            <span>{reconType === "FRC" ? "Jenis Bank" : "Nomor Rekening"}</span>
                                            {!loadingAccounts && accounts.length > 0 && (
                                                <span className="text-xs font-medium text-green-600">
                                                    {accounts.length} {reconType === "FRC" ? "jenis bank" : "rekening"} tersedia
                                                </span>
                                            )}
                                        </label>

                                        <select
                                            value={reconAccount}
                                            onChange={(event) => {
                                                const value = event.target.value;
                                                setReconAccount(value);
                                                resetReconciliation();
                                            }}
                                            disabled={loading || loadingAccounts || !period?.id}
                                            className="min-h-11 w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-800 outline-none transition focus:border-blue-400 focus:ring-2 focus:ring-blue-100 disabled:cursor-not-allowed disabled:bg-gray-100"
                                        >
                                            <option value="ALL">
                                                {reconType === "FRC" ? "Semua Jenis Bank" : "Semua Rekening"}
                                            </option>

                                            {Array.isArray(accounts) && accounts.map((account, index) => {
                                                const isRegular = reconType === "REG";
                                                const noRek = isRegular
                                                    ? String(account?.no_rek ?? "").trim()
                                                    : String(account ?? "").trim();
                                                const jnsBank = isRegular ? account?.jns_bank : "";
                                                const akun = isRegular ? account?.akun : "";

                                                if (!noRek) return null;

                                                const label = isRegular
                                                    ? `${jnsBank || "-"} - ${noRek}${akun ? ` (${akun})` : ""}`
                                                    : noRek;

                                                return (
                                                    <option key={`${reconType}-${noRek}-${index}`} value={noRek}>
                                                        {label}
                                                    </option>
                                                );
                                            })}
                                        </select>

                                        {loadingAccounts && (
                                            <p className="mt-2 flex items-center gap-2 text-xs text-blue-600">
                                                <FaSyncAlt className="animate-spin" />
                                                Memuat {reconType === "FRC" ? "jenis bank" : "rekening"}...
                                            </p>
                                        )}

                                        {!loadingAccounts && period?.id && accounts.length === 0 && (
                                            <p className="mt-2 text-xs text-red-500">
                                                {reconType === "FRC"
                                                    ? "Tidak ada jenis bank untuk cabang ini."
                                                    : "Tidak ada rekening REG untuk cabang ini."}
                                            </p>
                                        )}
                                    </div>

                                    {/* TOMBOL JALANKAN REKONSILIASI */}
                                    <div className="lg:col-span-3 min-w-0">
                                        <button
                                            type="button"
                                            onClick={runReconciliation}
                                            disabled={
                                                loading ||
                                                loadingAccounts ||
                                                loadingMethods ||
                                                !period?.id ||
                                                !reconAccount ||
                                                reconAccount === "ALL" ||
                                                (
                                                    reconType === "REG" &&
                                                    (
                                                        !Array.isArray(selectedMethods) ||
                                                        selectedMethods.length === 0
                                                    )
                                                )
                                            }
                                            className="inline-flex min-h-11 w-full items-center justify-center gap-2 whitespace-nowrap rounded-lg bg-blue-600 px-5 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:bg-gray-400 lg:w-auto"
                                        >
                                            {loading ? (
                                                <>
                                                    <FaSyncAlt className="animate-spin" />
                                                    Memproses...
                                                </>
                                            ) : loadingAccounts ? (
                                                <>
                                                    <FaSyncAlt className="animate-spin" />
                                                    Memuat Rekening...
                                                </>
                                            ) : loadingMethods && reconType === "REG" ? (
                                                <>
                                                    <FaSyncAlt className="animate-spin" />
                                                    Memuat Metode...
                                                </>
                                            ) : (
                                                <>
                                                    <FaExchangeAlt />
                                                    Proses Rekonsiliasi
                                                </>
                                            )}
                                        </button>
                                    </div>
                                </div>

                                {/* METODE REGULER: DI BAWAH BARIS KONTROL */}
                                {reconType === "REG" && reconAccount !== "ALL" && (
                                    <section className="rounded-xl border border-gray-200 bg-gray-50/70 p-3 sm:p-4">
                                        <div className="mb-3 flex flex-wrap items-start justify-between gap-3">
                                            <div>
                                                <h3 className="text-sm font-bold text-gray-800">Metode Rekonsiliasi</h3>
                                                <p className="mt-1 text-xs leading-5 text-gray-500">
                                                    Seret kartu untuk mengubah urutan. Hapus kartu untuk mengeluarkannya dari proses ini.
                                                </p>
                                            </div>
                                            <span className="inline-flex shrink-0 items-center rounded-full bg-blue-100 px-3 py-1 text-xs font-semibold text-blue-700">
                                                {selectedMethods.length} metode dipilih
                                            </span>
                                        </div>

                                        {loadingMethods ? (
                                            <div className="flex min-h-20 items-center justify-center gap-2 rounded-lg border border-dashed border-gray-300 bg-white px-4 py-6 text-sm text-gray-500">
                                                <FaSyncAlt className="animate-spin text-blue-600" />
                                                Memuat metode rekonsiliasi...
                                            </div>
                                        ) : selectedMethods.length === 0 ? (
                                            <div className="rounded-lg border border-dashed border-gray-300 bg-white px-4 py-6 text-center">
                                                <p className="text-sm font-medium text-gray-700">Tidak ada metode yang dipilih.</p>
                                                <p className="mt-1 text-xs leading-5 text-gray-500">
                                                    Periksa konfigurasi metode pada tabel setting atau pilih rekening lain.
                                                </p>
                                            </div>
                                        ) : (
                                            <div className="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-5">
                                                {selectedMethods.map((method, index) => (
                                                    <div
                                                        key={method.id}
                                                        draggable={!loading}
                                                        onDragStart={(event) => {
                                                            event.dataTransfer.setData("text/plain", String(method.id));
                                                            event.dataTransfer.effectAllowed = "move";
                                                        }}
                                                        onDragOver={(event) => {
                                                            event.preventDefault();
                                                            event.dataTransfer.dropEffect = "move";
                                                        }}
                                                        onDrop={(event) => {
                                                            event.preventDefault();
                                                            const sourceId = event.dataTransfer.getData("text/plain");
                                                            reorderReconciliationMethods(sourceId, method.id);
                                                        }}
                                                        className="flex min-w-0 items-center gap-3 rounded-lg border border-gray-200 bg-white p-3 shadow-sm transition hover:border-blue-300 hover:shadow-md active:cursor-grabbing"
                                                    >
                                                        <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-gray-100 text-gray-500" title="Seret kartu untuk mengubah urutan">
                                                            <FaExchangeAlt />
                                                        </div>

                                                        <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-blue-50 text-sm font-bold text-blue-700">
                                                            {index + 1}
                                                        </div>

                                                        <div className="min-w-0 flex-1">
                                                            <p className="break-words text-sm font-semibold leading-5 text-gray-800">
                                                                {method.value}
                                                            </p>
                                                            <p className="mt-1 truncate text-xs text-gray-500" title={method.sub_category}>
                                                                {method.sub_category}
                                                            </p>
                                                        </div>

                                                        <button
                                                            type="button"
                                                            title="Hapus metode dari pilihan"
                                                            aria-label={`Hapus metode ${method.value}`}
                                                            disabled={loading}
                                                            onClick={() => removeReconciliationMethod(method.id)}
                                                            className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-red-500 transition hover:bg-red-50 hover:text-red-700 disabled:cursor-not-allowed disabled:opacity-50"
                                                        >
                                                            <FaTrash />
                                                        </button>
                                                    </div>
                                                ))}
                                            </div>
                                        )}
                                    </section>
                                )}

                                {/* KETERANGAN METODE TERPILIH */}
                                {reconType === "REG" && reconAccount !== "ALL" && !loadingMethods && selectedMethods.length > 0 && (
                                    <p className="text-xs text-gray-500">
                                        {selectedMethods.length} metode akan digunakan sesuai urutan kartu.
                                    </p>
                                )}

                            </div>


                            {/* ==================================================
                                CURRENT RECON INFO
                            =================================================== */}

                            {reconciliationExecuted && (

                                <div className="
                                    mt-4
                                    rounded-lg
                                    border
                                    border-blue-100
                                    bg-blue-50
                                    p-3
                                    text-sm
                                    text-blue-800
                                ">

                                    <div className="
                                        flex
                                        flex-wrap
                                        gap-x-5
                                        gap-y-2
                                    ">


                                        {/* JENIS */}

                                        <span>

                                            <strong>
                                                Jenis:
                                            </strong>{" "}

                                            {
                                                reconType === "FRC"
                                                    ? "Franchise"
                                                    : "Reguler"
                                            }

                                        </span>


                                        {/* REKENING */}

                                        <span>

                                            <strong>
                                                {
                                                    reconType === "FRC"
                                                        ? "Jenis Bank:"
                                                        : "Rekening:"
                                                }
                                            </strong>{" "}

                                            {
                                                reconAccount === "ALL"
                                                    ? "Semua Rekening"
                                                    : reconAccount
                                            }

                                        </span>


                                        {/* PERIODE */}

                                        <span>

                                            <strong>
                                                Periode:
                                            </strong>{" "}

                                            {
                                                period?.periode ||
                                                "-"
                                            }

                                        </span>

                                    </div>

                                </div>

                            )}

                        </div>


                        {/* ==================================================
                            BELUM DIJALANKAN
                        =================================================== */}

                        {!reconciliationExecuted &&
                            !loading && (

                                <div className="
                                    mt-4
                                    bg-white
                                    rounded-xl
                                    border
                                    shadow-sm
                                    min-h-[300px]
                                    flex
                                    flex-col
                                    items-center
                                    justify-center
                                    text-center
                                    px-4
                                ">

                                    <div className="
                                        w-16
                                        h-16
                                        rounded-full
                                        bg-blue-50
                                        text-blue-500
                                        flex
                                        items-center
                                        justify-center
                                        mb-4
                                    ">

                                        <FaExchangeAlt
                                            className="
                                                text-2xl
                                            "
                                        />

                                    </div>


                                    <p className="
                                        text-base
                                        font-semibold
                                        text-gray-700
                                    ">

                                        Rekonsiliasi belum dijalankan

                                    </p>


                                    <p className="
                                        text-sm
                                        text-gray-500
                                        mt-1
                                        max-w-md
                                    ">

                                        Pilih Franchise atau Reguler,
                                        pilih rekening atau Semua Rekening,
                                        kemudian klik Rekonsiliasi.

                                    </p>

                                </div>

                            )}


                        {/* ==================================================
                            HASIL REKONSILIASI
                        =================================================== */}

                        {reconciliationExecuted && (

                            <>

                                {/* ==================================================
                                    SUMMARY
                                =================================================== */}

                                <div className="
                                    mt-4
                                    grid
                                    grid-cols-2
                                    md:grid-cols-3
                                    xl:grid-cols-5
                                    gap-3
                                ">


                                    {/* TOTAL */}

                                    <SummaryCard
                                        title="Total"

                                        value={
                                            formatNumber(
                                                summary.total
                                            )
                                        }

                                        subtitle="Data"

                                        icon={
                                            <FaExchangeAlt />
                                        }
                                    />


                                    {/* DATA MATCH */}

                                    <SummaryCard
                                        title="Data Match"

                                        value={
                                            formatNumber(
                                                summary.match
                                            )
                                        }

                                        subtitle="Mutasi dan Receipt sesuai"

                                        icon={
                                            <FaCheckCircle />
                                        }

                                        className="
                                            border-green-200
                                        "
                                    />


                                    {/* MUTASI ONLY */}

                                    <SummaryCard
                                        title="Mutasi Only"

                                        value={
                                            formatNumber(
                                                summary.mutasi_only
                                            )
                                        }

                                        subtitle="Belum ada Receipt"

                                        icon={
                                            <FaExclamationTriangle />
                                        }

                                        className="
                                            border-yellow-200
                                        "
                                    />


                                    {/* RECEIPT ONLY */}

                                    <SummaryCard
                                        title="Receipt Only"

                                        value={
                                            formatNumber(
                                                summary.receipt_only
                                            )
                                        }

                                        subtitle="Tidak ada Mutasi"

                                        icon={
                                            <FaReceipt />
                                        }

                                        className="
                                            border-orange-200
                                        "
                                    />


                                    {/* MATCH SELISIH */}

                                    <SummaryCard
                                        title="Match Selisih"

                                        value={
                                            formatNumber(
                                                Number(
                                                    summary.nominal_different ||
                                                    0
                                                ) +
                                                Number(
                                                    summary.account_different ||
                                                    0
                                                )
                                            )
                                        }

                                        subtitle="Nominal / rekening berbeda"

                                        icon={
                                            <FaMoneyBillWave />
                                        }

                                        className="
                                            border-red-200
                                        "
                                    />

                                </div>


                                {/* ==================================================
                                    AMOUNT SUMMARY
                                =================================================== */}

                                <div className="
                                    mt-3
                                    bg-white
                                    rounded-xl
                                    border
                                    shadow-sm
                                    p-4
                                ">

                                    <div className="
                                        grid
                                        grid-cols-1
                                        md:grid-cols-3
                                        gap-4
                                    ">


                                        {/* MUTASI */}

                                        <div>

                                            <p className="
                                                text-xs
                                                text-gray-500
                                            ">
                                                Total Mutasi Kredit
                                            </p>


                                            <p className="
                                                mt-1
                                                text-lg
                                                font-bold
                                            ">

                                                {
                                                    formatCurrency(
                                                        summary.mutasi_amount
                                                    )
                                                }

                                            </p>

                                        </div>


                                        {/* RECEIPT */}

                                        <div>

                                            <p className="
                                                text-xs
                                                text-gray-500
                                            ">
                                                Total Receipt
                                            </p>


                                            <p className="
                                                mt-1
                                                text-lg
                                                font-bold
                                            ">

                                                {
                                                    formatCurrency(
                                                        summary.receipt_amount
                                                    )
                                                }

                                            </p>

                                        </div>


                                        {/* DIFFERENCE */}

                                        <div>

                                            <p className="
                                                text-xs
                                                text-gray-500
                                            ">
                                                Total Selisih
                                            </p>


                                            <p
                                                className={`
                                                    mt-1
                                                    text-lg
                                                    font-bold

                                                    ${
                                                        Number(
                                                            summary.difference_amount
                                                        ) === 0

                                                            ? "text-green-600"

                                                            : "text-red-600"
                                                    }
                                                `}
                                            >

                                                {
                                                    formatCurrency(
                                                        summary.difference_amount
                                                    )
                                                }

                                            </p>

                                        </div>

                                    </div>

                                </div>

                            </>

                        )}

                    </>

                    )}

                    {/* ======================================================
                        HISTORY / SEARCH RECONCILIATION
                    ======================================================= */}

                    {activeTab === "history" && (

                        <div className="
                            space-y-3
                        ">

                            {/* ==================================================
                                FILTER CARD
                            =================================================== */}

                            <div className="
                                bg-white
                                rounded-xl
                                border
                                shadow-sm
                                p-4
                            ">

                                <div className="
                                    flex
                                    items-center
                                    gap-2
                                    mb-4
                                ">

                                    <div className="
                                        w-9
                                        h-9
                                        rounded-lg
                                        bg-blue-100
                                        text-blue-600
                                        flex
                                        items-center
                                        justify-center
                                    ">

                                        <FaHistory />

                                    </div>

                                    <div>

                                        <h2 className="
                                            font-bold
                                            text-lg
                                        ">
                                            Riwayat Rekonsiliasi
                                        </h2>

                                        <p className="
                                            text-xs
                                            text-gray-500
                                        ">
                                            Cari hasil rekonsiliasi yang sudah pernah dilakukan.
                                        </p>

                                    </div>

                                </div>


                                {/* ==================================================
                                    FILTER
                                =================================================== */}

                                <div className="
                                    grid
                                    grid-cols-1
                                    md:grid-cols-2
                                    xl:grid-cols-4
                                    gap-3
                                ">

                                    {/* JENIS REKON */}

                                    <div>

                                        <label className="
                                            block
                                            text-xs
                                            font-semibold
                                            text-gray-600
                                            mb-1
                                        ">
                                            Jenis Rekonsiliasi
                                        </label>

                                        <select
                                            value={
                                                historyType
                                            }

                                            onChange={
                                                event => {

                                                    setHistoryType(
                                                        event.target.value
                                                    );

                                                    setHistoryBank(
                                                        "ALL"
                                                    );

                                                    setHistoryAccount(
                                                        "ALL"
                                                    );
                                                }
                                            }

                                            className="
                                                w-full
                                                px-3
                                                py-2.5
                                                border
                                                rounded-lg
                                                bg-white
                                                text-sm
                                            "
                                        >

                                            <option value="FRC">
                                                Franchise
                                            </option>

                                            <option value="REG">
                                                Reguler
                                            </option>

                                        </select>

                                    </div>


                                    {/* JENIS BANK */}

                                    <div>

                                        <label className="
                                            block
                                            text-xs
                                            font-semibold
                                            text-gray-600
                                            mb-1
                                        ">
                                            Jenis Bank
                                        </label>

                                        <select
                                            value={
                                                historyBank
                                            }

                                            onChange={
                                                event =>
                                                    setHistoryBank(
                                                        event.target.value
                                                    )
                                            }

                                            className="
                                                w-full
                                                px-3
                                                py-2.5
                                                border
                                                rounded-lg
                                                bg-white
                                                text-sm
                                            "
                                        >

                                            <option value="ALL">
                                                Semua Jenis Bank
                                            </option>

                                            {historyBanks.map(
                                                (
                                                    bank,
                                                    index
                                                ) => (

                                                    <option
                                                        key={
                                                            `${bank}-${index}`
                                                        }
                                                        value={
                                                            bank
                                                        }
                                                    >
                                                        {bank}
                                                    </option>

                                                )
                                            )}

                                        </select>

                                    </div>


                                    {/* NO REKENING */}

                                    <div>

                                        <label className="
                                            block
                                            text-xs
                                            font-semibold
                                            text-gray-600
                                            mb-1
                                        ">
                                            No. Rekening
                                        </label>

                                        <select
                                            value={
                                                historyAccount
                                            }

                                            onChange={
                                                event =>
                                                    setHistoryAccount(
                                                        event.target.value
                                                    )
                                            }

                                            className="
                                                w-full
                                                px-3
                                                py-2.5
                                                border
                                                rounded-lg
                                                bg-white
                                                text-sm
                                            "
                                        >

                                            <option value="ALL">
                                                Semua Rekening
                                            </option>

                                            {historyAccounts.map(
                                                (
                                                    account,
                                                    index
                                                ) => (

                                                    <option
                                                        key={
                                                            `${account}-${index}`
                                                        }
                                                        value={
                                                            account
                                                        }
                                                    >
                                                        {account}
                                                    </option>

                                                )
                                            )}

                                        </select>

                                    </div>


                                    {/* KATEGORI */}

                                    <div>

                                        <label className="
                                            block
                                            text-xs
                                            font-semibold
                                            text-gray-600
                                            mb-1
                                        ">
                                            Kategori
                                        </label>

                                        <select
                                            value={
                                                historyCategory
                                            }

                                            onChange={
                                                event =>
                                                    setHistoryCategory(
                                                        event.target.value
                                                    )
                                            }

                                            className="
                                                w-full
                                                px-3
                                                py-2.5
                                                border
                                                rounded-lg
                                                bg-white
                                                text-sm
                                            "
                                        >

                                            <option value="ALL">
                                                Semua Data
                                            </option>

                                            <option value="MATCH">
                                                Match
                                            </option>

                                            <option value="MATCH_SELISIH">
                                                Match tapi Selisih
                                            </option>

                                            <option value="MUTASI_ONLY">
                                                Mutasi Only
                                            </option>

                                            <option value="RECEIPT_ONLY">
                                                Receipt Only
                                            </option>

                                        </select>

                                    </div>


                                    {/* TANGGAL AWAL */}

                                    <div>

                                        <label className="
                                            block
                                            text-xs
                                            font-semibold
                                            text-gray-600
                                            mb-1
                                        ">
                                            Tanggal Awal
                                        </label>

                                        <input
                                            type="date"
                                            value={
                                                historyDateStart
                                            }

                                            onChange={
                                                event =>
                                                    setHistoryDateStart(
                                                        event.target.value
                                                    )
                                            }

                                            className="
                                                w-full
                                                px-3
                                                py-2.5
                                                border
                                                rounded-lg
                                                bg-white
                                                text-sm
                                            "
                                        />

                                    </div>


                                    {/* TANGGAL AKHIR */}

                                    <div>

                                        <label className="
                                            block
                                            text-xs
                                            font-semibold
                                            text-gray-600
                                            mb-1
                                        ">
                                            Tanggal Akhir
                                        </label>

                                        <input
                                            type="date"
                                            value={
                                                historyDateEnd
                                            }

                                            onChange={
                                                event =>
                                                    setHistoryDateEnd(
                                                        event.target.value
                                                    )
                                            }

                                            className="
                                                w-full
                                                px-3
                                                py-2.5
                                                border
                                                rounded-lg
                                                bg-white
                                                text-sm
                                            "
                                        />

                                    </div>


                                    {/* SEARCH */}

                                    <div className="
                                        md:col-span-2
                                    ">

                                        <label className="
                                            block
                                            text-xs
                                            font-semibold
                                            text-gray-600
                                            mb-1
                                        ">
                                            Pencarian
                                        </label>

                                        <div className="
                                            relative
                                        ">

                                            <FaSearch className="
                                                absolute
                                                left-3
                                                top-1/2
                                                -translate-y-1/2
                                                text-gray-400
                                            " />

                                            <input
                                                type="text"
                                                value={
                                                    historySearch
                                                }

                                                onChange={
                                                    event =>
                                                        setHistorySearch(
                                                            event.target.value
                                                        )
                                                }

                                                onKeyDown={
                                                    event => {

                                                        if (
                                                            event.key ===
                                                            "Enter"
                                                        ) {

                                                            searchReconciliationHistory();

                                                        }

                                                    }
                                                }

                                                placeholder="Cari..."

                                                className="
                                                    w-full
                                                    pl-9
                                                    pr-3
                                                    py-2.5
                                                    border
                                                    rounded-lg
                                                    bg-white
                                                    text-sm
                                                "
                                            />

                                        </div>

                                    </div>

                                </div>


                                {/* ACTION */}

                                <div className="
                                    mt-4
                                    flex
                                    flex-col-reverse
                                    sm:flex-row
                                    sm:justify-end
                                    gap-2
                                ">

                                    <button
                                        type="button"
                                        onClick={
                                            resetHistoryFilter
                                        }

                                        className="
                                            px-4
                                            py-2.5
                                            rounded-lg
                                            border
                                            text-gray-700
                                            hover:bg-gray-50
                                        "
                                    >
                                        Reset
                                    </button>


                                    <button
                                        type="button"
                                        onClick={
                                            searchReconciliationHistory
                                        }

                                        disabled={
                                            historyLoading
                                        }

                                        className="
                                            px-5
                                            py-2.5
                                            rounded-lg
                                            bg-blue-600
                                            hover:bg-blue-700
                                            text-white
                                            font-semibold
                                            inline-flex
                                            items-center
                                            justify-center
                                            gap-2
                                            disabled:bg-gray-400
                                        "
                                    >

                                        {historyLoading ? (

                                            <>
                                                <FaSyncAlt className="
                                                    animate-spin
                                                " />

                                                Mencari...

                                            </>

                                        ) : (

                                            <>
                                                <FaSearch />

                                                Cari Riwayat

                                            </>

                                        )}

                                    </button>

                                </div>

                            </div>


                            {/* ==================================================
                                RESULT
                            =================================================== */}

                            <div className="
                                bg-white
                                rounded-xl
                                border
                                shadow-sm
                                overflow-hidden
                            ">

                                <div className="
                                    px-4
                                    py-3
                                    border-b
                                    flex
                                    flex-col
                                    sm:flex-row
                                    sm:items-center
                                    sm:justify-between
                                    gap-2
                                ">

                                    <div>

                                        <h2 className="
                                            font-semibold
                                        ">
                                            Hasil Riwayat Rekonsiliasi
                                        </h2>

                                        <p className="
                                            text-xs
                                            text-gray-500
                                            mt-1
                                        ">
                                            {formatNumber(
                                                filteredHistoryData.length
                                            )}
                                            {" "}
                                            data ditemukan
                                        </p>

                                    </div>


                                    {historyExecuted && (

                                        <span className="
                                            px-2.5
                                            py-1
                                            rounded-full
                                            bg-blue-50
                                            text-blue-700
                                            text-xs
                                            font-semibold
                                        ">
                                            {historyType === "FRC"
                                                ? "FRANCHISE"
                                                : "REGULER"
                                            }
                                        </span>

                                    )}

                                </div>


                                {filteredHistoryData.length === 0 ? (

                                    <div className="
                                        min-h-[250px]
                                        flex
                                        flex-col
                                        items-center
                                        justify-center
                                        text-gray-400
                                        px-4
                                        text-center
                                    ">

                                        <FaHistory className="
                                            text-4xl
                                            text-gray-300
                                            mb-3
                                        " />

                                        <p className="
                                            text-sm
                                            font-medium
                                            text-gray-600
                                        ">
                                            Tidak ada data
                                        </p>

                                        <p className="
                                            text-xs
                                            mt-1
                                        ">
                                            Silakan gunakan filter lalu klik
                                            Cari Riwayat.
                                        </p>

                                    </div>

                                ) : (

                                    <div className="
                                        max-h-[calc(100vh-700px)]
                                        overflow-auto
                                    ">

                                        <table className="
                                            w-full
                                            min-w-[1200px]
                                            text-sm
                                        ">

                                            <thead className="
                                                bg-gray-50
                                                border-b
                                            ">

                                                <tr>

                                                    <th className="
                                                        px-4
                                                        py-3
                                                        text-left
                                                        text-xs
                                                        font-semibold
                                                        text-gray-500
                                                    ">
                                                        Status
                                                    </th>

                                                    <th className="
                                                        px-4
                                                        py-3
                                                        text-left
                                                        text-xs
                                                        font-semibold
                                                        text-gray-500
                                                    ">
                                                        Tanggal
                                                    </th>

                                                    <th className="
                                                        px-4
                                                        py-3
                                                        text-left
                                                        text-xs
                                                        font-semibold
                                                        text-gray-500
                                                    ">
                                                        Jenis Bank
                                                    </th>

                                                    <th className="
                                                        px-4
                                                        py-3
                                                        text-left
                                                        text-xs
                                                        font-semibold
                                                        text-gray-500
                                                    ">
                                                        No. Rekening
                                                    </th>

                                                    <th className="
                                                        px-4
                                                        py-3
                                                        text-left
                                                        text-xs
                                                        font-semibold
                                                        text-gray-500
                                                    ">
                                                        Receipt
                                                    </th>

                                                    <th className="
                                                        px-4
                                                        py-3
                                                        text-right
                                                        text-xs
                                                        font-semibold
                                                        text-gray-500
                                                    ">
                                                        Mutasi
                                                    </th>

                                                    <th className="
                                                        px-4
                                                        py-3
                                                        text-right
                                                        text-xs
                                                        font-semibold
                                                        text-gray-500
                                                    ">
                                                        Receipt
                                                    </th>

                                                    <th className="
                                                        px-4
                                                        py-3
                                                        text-right
                                                        text-xs
                                                        font-semibold
                                                        text-gray-500
                                                    ">
                                                        Selisih
                                                    </th>

                                                    <th className="
                                                        px-4
                                                        py-3
                                                        text-center
                                                        text-xs
                                                        font-semibold
                                                        text-gray-500
                                                    ">
                                                        Detail
                                                    </th>

                                                </tr>

                                            </thead>


                                            <tbody className="
                                                divide-y
                                            ">

                                                {filteredHistoryData.map(
                                                    (
                                                        item,
                                                        index
                                                    ) => {

                                                        const expanded =
                                                            historyExpandedRow ===
                                                            index;


                                                        const mutasiAmount =
                                                            Number(
                                                                item.mutasi_amount ||
                                                                0
                                                            );


                                                        const receiptAmount =
                                                            Number(
                                                                item.receipt_amount ||
                                                                0
                                                            );


                                                        const difference =
                                                            Number(
                                                                item.difference ??
                                                                (
                                                                    receiptAmount -
                                                                    mutasiAmount
                                                                )
                                                            );


                                                        return (

                                                            <React.Fragment
                                                                key={
                                                                    item.reff ||
                                                                    item.id ||
                                                                    index
                                                                }
                                                            >

                                                                <tr className="
                                                                    hover:bg-gray-50
                                                                ">

                                                                    <td className="
                                                                        px-4
                                                                        py-3
                                                                    ">

                                                                        <StatusBadge
                                                                            status={
                                                                                item.status
                                                                            }
                                                                        />

                                                                    </td>


                                                                    <td className="
                                                                        px-4
                                                                        py-3
                                                                        whitespace-nowrap
                                                                    ">
                                                                        {formatDate(
                                                                            item.mutasi_date ||
                                                                            item.receipt_date
                                                                        )}
                                                                    </td>


                                                                    <td className="
                                                                        px-4
                                                                        py-3
                                                                    ">
                                                                        {item.jns_bank ||
                                                                            "-"
                                                                        }
                                                                    </td>


                                                                    <td className="
                                                                        px-4
                                                                        py-3
                                                                    ">
                                                                        {item.no_rek ||
                                                                            item.remittance_bank_account ||
                                                                            "-"
                                                                        }
                                                                    </td>


                                                                    <td className="
                                                                        px-4
                                                                        py-3
                                                                    ">
                                                                        {item.receipt_number ||
                                                                            "-"
                                                                        }
                                                                    </td>


                                                                    <td className="
                                                                        px-4
                                                                        py-3
                                                                        text-right
                                                                    ">
                                                                        {formatCurrency(
                                                                            mutasiAmount
                                                                        )}
                                                                    </td>


                                                                    <td className="
                                                                        px-4
                                                                        py-3
                                                                        text-right
                                                                    ">
                                                                        {formatCurrency(
                                                                            receiptAmount
                                                                        )}
                                                                    </td>


                                                                    <td className={`
                                                                        px-4
                                                                        py-3
                                                                        text-right
                                                                        font-semibold

                                                                        ${
                                                                            difference === 0
                                                                                ? "text-green-600"
                                                                                : "text-red-600"
                                                                        }
                                                                    `}>
                                                                        {formatCurrency(
                                                                            difference
                                                                        )}
                                                                    </td>


                                                                    <td className="
                                                                        px-4
                                                                        py-3
                                                                        text-center
                                                                    ">

                                                                        <button
                                                                            type="button"
                                                                            onClick={() =>
                                                                                setHistoryExpandedRow(
                                                                                    expanded
                                                                                        ? null
                                                                                        : index
                                                                                )
                                                                            }

                                                                            className="
                                                                                w-8
                                                                                h-8
                                                                                rounded-lg
                                                                                hover:bg-gray-100
                                                                                inline-flex
                                                                                items-center
                                                                                justify-center
                                                                            "
                                                                        >

                                                                            {expanded ? (
                                                                                <FaChevronUp />
                                                                            ) : (
                                                                                <FaChevronDown />
                                                                            )}

                                                                        </button>

                                                                    </td>

                                                                </tr>


                                                                {expanded && (

                                                                    <tr>

                                                                        <td
                                                                            colSpan="9"
                                                                            className="
                                                                                bg-gray-50
                                                                                px-4
                                                                                py-4
                                                                            "
                                                                        >

                                                                            <div className="
                                                                                grid
                                                                                grid-cols-1
                                                                                lg:grid-cols-2
                                                                                gap-3
                                                                            ">

                                                                                {/* MUTASI */}

                                                                                <div className="
                                                                                    bg-white
                                                                                    border
                                                                                    rounded-lg
                                                                                    p-4
                                                                                ">

                                                                                    <div className="
                                                                                        flex
                                                                                        items-center
                                                                                        justify-between
                                                                                    ">

                                                                                        <h3 className="
                                                                                            font-semibold
                                                                                            text-sm
                                                                                        ">
                                                                                            Data Mutasi
                                                                                        </h3>


                                                                                        {item.mutasi_id && (

                                                                                            <button
                                                                                                type="button"

                                                                                                onClick={() =>
                                                                                                    selectManualMutasi(
                                                                                                        item
                                                                                                    )
                                                                                                }

                                                                                                className={`
                                                                                                    px-3
                                                                                                    py-1.5
                                                                                                    rounded-lg
                                                                                                    text-xs
                                                                                                    font-semibold
                                                                                                    border

                                                                                                    ${
                                                                                                        manualMutasi?.id ===
                                                                                                        item.mutasi_id

                                                                                                            ? `
                                                                                                                bg-blue-600
                                                                                                                text-white
                                                                                                                border-blue-600
                                                                                                            `

                                                                                                            : `
                                                                                                                bg-white
                                                                                                                text-gray-700
                                                                                                                hover:bg-gray-50
                                                                                                            `
                                                                                                    }
                                                                                                `}
                                                                                            >

                                                                                                {manualMutasi?.id ===
                                                                                                item.mutasi_id

                                                                                                    ? "Dipilih"

                                                                                                    : "Pilih Mutasi"
                                                                                                }

                                                                                            </button>

                                                                                        )}

                                                                                    </div>


                                                                                    <div className="
                                                                                        mt-3
                                                                                        space-y-2
                                                                                        text-sm
                                                                                    ">

                                                                                        <div>
                                                                                            ID:
                                                                                            {" "}
                                                                                            <strong>
                                                                                                {
                                                                                                    item.mutasi_id ||
                                                                                                    "-"
                                                                                                }
                                                                                            </strong>
                                                                                        </div>

                                                                                        <div>
                                                                                            Tanggal:
                                                                                            {" "}
                                                                                            {
                                                                                                formatDate(
                                                                                                    item.mutasi_date
                                                                                                )
                                                                                            }
                                                                                        </div>

                                                                                        <div>
                                                                                            Rekening:
                                                                                            {" "}
                                                                                            {
                                                                                                item.no_rek ||
                                                                                                "-"
                                                                                            }
                                                                                        </div>

                                                                                        <div>
                                                                                            Bank:
                                                                                            {" "}
                                                                                            {
                                                                                                item.jns_bank ||
                                                                                                "-"
                                                                                            }
                                                                                        </div>

                                                                                        <div>
                                                                                            Nominal:
                                                                                            {" "}
                                                                                            <strong>
                                                                                                {
                                                                                                    formatCurrency(
                                                                                                        item.mutasi_amount
                                                                                                    )
                                                                                                }
                                                                                            </strong>
                                                                                        </div>

                                                                                        <div>
                                                                                            Reff:
                                                                                            {" "}
                                                                                            <strong>
                                                                                                {
                                                                                                    item.mutation_reff ||
                                                                                                    item.reff ||
                                                                                                    "-"
                                                                                                }
                                                                                            </strong>
                                                                                        </div>

                                                                                        <div>
                                                                                            Keterangan:
                                                                                            {" "}
                                                                                            {
                                                                                                item.description ||
                                                                                                "-"
                                                                                            }
                                                                                        </div>

                                                                                    </div>

                                                                                </div>


                                                                                {/* RECEIPT */}

                                                                                <div className="
                                                                                    bg-white
                                                                                    border
                                                                                    rounded-lg
                                                                                    p-4
                                                                                ">

                                                                                    <div className="
                                                                                        flex
                                                                                        items-center
                                                                                        justify-between
                                                                                    ">

                                                                                        <h3 className="
                                                                                            font-semibold
                                                                                            text-sm
                                                                                        ">
                                                                                            Data Receipt
                                                                                        </h3>


                                                                                        {item.receipt_id && (

                                                                                            <button
                                                                                                type="button"

                                                                                                onClick={() =>
                                                                                                    selectManualReceipt(
                                                                                                        item
                                                                                                    )
                                                                                                }

                                                                                                className={`
                                                                                                    px-3
                                                                                                    py-1.5
                                                                                                    rounded-lg
                                                                                                    text-xs
                                                                                                    font-semibold
                                                                                                    border

                                                                                                    ${
                                                                                                        manualReceipt?.id ===
                                                                                                        item.receipt_id

                                                                                                            ? `
                                                                                                                bg-blue-600
                                                                                                                text-white
                                                                                                                border-blue-600
                                                                                                            `

                                                                                                            : `
                                                                                                                bg-white
                                                                                                                text-gray-700
                                                                                                                hover:bg-gray-50
                                                                                                            `
                                                                                                    }
                                                                                                `}
                                                                                            >

                                                                                                {manualReceipt?.id ===
                                                                                                item.receipt_id

                                                                                                    ? "Dipilih"

                                                                                                    : "Pilih Receipt"
                                                                                                }

                                                                                            </button>

                                                                                        )}

                                                                                    </div>


                                                                                    <div className="
                                                                                        mt-3
                                                                                        space-y-2
                                                                                        text-sm
                                                                                    ">

                                                                                        <div>
                                                                                            Receipt Number :
                                                                                            {" "}
                                                                                            <strong>
                                                                                                {
                                                                                                    item.receipt_number ||
                                                                                                    "-"
                                                                                                }
                                                                                            </strong>
                                                                                        </div>

                                                                                        <div>
                                                                                            Receipt Date :
                                                                                            {" "}
                                                                                            {
                                                                                                formatDate(
                                                                                                    item.receipt_date
                                                                                                )
                                                                                            }
                                                                                        </div>

                                                                                        <div>
                                                                                            No Rekening :
                                                                                            {" "}
                                                                                            {
                                                                                                item.no_rek ||
                                                                                                "-"
                                                                                            }
                                                                                        </div>

                                                                                        <div>
                                                                                            Amount :
                                                                                            {" "}
                                                                                            <strong>
                                                                                                {
                                                                                                    formatCurrency(
                                                                                                        item.receipt_amount
                                                                                                    )
                                                                                                }
                                                                                            </strong>
                                                                                        </div>

                                                                                        <div>
                                                                                            Receipt Status :
                                                                                            {" "}
                                                                                            {
                                                                                                item.receipt_status ||
                                                                                                "-"
                                                                                            }
                                                                                        </div>

                                                                                        <div>
                                                                                            Receipt State :
                                                                                            {" "}
                                                                                            {
                                                                                                item.receipt_state ||
                                                                                                "-"
                                                                                            }
                                                                                        </div>
                                                                                        
                                                                                        <div>
                                                                                            comments :
                                                                                            {" "}
                                                                                            {item.receipts?.[0]?.comments || "-"}
                                                                                        </div>

                                                                                    </div>

                                                                                </div>

                                                                            </div>


                                                                            {/* MANUAL ACTION */}

                                                                            {(manualReceipt ||
                                                                                manualMutasi) && (

                                                                                <div className="
                                                                                    mt-3
                                                                                    bg-white
                                                                                    border
                                                                                    rounded-lg
                                                                                    p-4
                                                                                ">

                                                                                    <div className="
                                                                                        flex
                                                                                        flex-col
                                                                                        sm:flex-row
                                                                                        sm:items-center
                                                                                        sm:justify-between
                                                                                        gap-3
                                                                                    ">

                                                                                        <div>

                                                                                            <h3 className="
                                                                                                font-semibold
                                                                                                text-sm
                                                                                            ">
                                                                                                Rekonsiliasi Manual
                                                                                            </h3>

                                                                                            <p className="
                                                                                                text-xs
                                                                                                text-gray-500
                                                                                                mt-1
                                                                                            ">

                                                                                                {manualReceipt
                                                                                                    ? "Receipt sudah dipilih."
                                                                                                    : "Pilih Receipt."
                                                                                                }

                                                                                                {" "}

                                                                                                {manualMutasi
                                                                                                    ? "Mutasi sudah dipilih."
                                                                                                    : "Pilih Mutasi."
                                                                                                }

                                                                                            </p>

                                                                                        </div>


                                                                                        <button
                                                                                            type="button"

                                                                                            onClick={
                                                                                                executeManualReconciliation
                                                                                            }

                                                                                            disabled={
                                                                                                !manualReceipt ||
                                                                                                !manualMutasi ||
                                                                                                manualReconLoading
                                                                                            }

                                                                                            className="
                                                                                                px-4
                                                                                                py-2.5
                                                                                                rounded-lg
                                                                                                bg-blue-600
                                                                                                hover:bg-blue-700
                                                                                                text-white
                                                                                                font-semibold
                                                                                                inline-flex
                                                                                                items-center
                                                                                                justify-center
                                                                                                gap-2
                                                                                                disabled:bg-gray-400
                                                                                            "
                                                                                        >

                                                                                            {manualReconLoading ? (

                                                                                                <>
                                                                                                    <FaSyncAlt className="
                                                                                                        animate-spin
                                                                                                    " />

                                                                                                    Memproses...

                                                                                                </>

                                                                                            ) : (

                                                                                                <>
                                                                                                    <FaLink />

                                                                                                    Rekonsiliasi Manual

                                                                                                </>

                                                                                            )}

                                                                                        </button>

                                                                                    </div>

                                                                                </div>

                                                                            )}

                                                                        </td>

                                                                    </tr>

                                                                )}

                                                            </React.Fragment>

                                                        );

                                                    }
                                                )}

                                            </tbody>

                                        </table>

                                    </div>

                                )}

                            </div>

                        </div>
                    )}

                    {/* ======================================================
                        DOWNLOAD RECONCILIATION
                    ======================================================= */}

                    {activeTab === "download" && (
                        <div className="w-full px-1 space-y-2">

                            {/* HEADER */}
                            <div className="bg-white rounded-xl border shadow-sm p-5">
                                <div className="flex items-center gap-3">
                                    <div className="
                                        w-11 h-11 rounded-xl
                                        bg-green-100 text-green-600
                                        flex items-center justify-center
                                    ">
                                        <FaDownload className="text-xl" />
                                    </div>

                                    <div>
                                        <h2 className="font-bold text-lg">
                                            Download Hasil Rekonsiliasi
                                        </h2>

                                        <p className="text-sm text-gray-500 mt-0.5">
                                            Download hasil rekonsiliasi berdasarkan jenis bank.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            {/* DOWNLOAD OPTIONS */}
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">

                                {/* FRANCHISE */}
                                <div className="
                                    bg-white rounded-xl border
                                    shadow-sm overflow-hidden
                                ">
                                    <div className="px-5 py-4 border-b bg-purple-50">
                                        <div className="flex items-center gap-3">
                                            <div className="
                                                w-10 h-10 rounded-lg
                                                bg-purple-100 text-purple-600
                                                flex items-center justify-center
                                            ">
                                                <FaExchangeAlt />
                                            </div>

                                            <div>
                                                <h3 className="font-bold text-gray-800">
                                                    Rekonsiliasi Franchise
                                                </h3>

                                                <p className="text-xs text-gray-500 mt-0.5">
                                                    Hasil rekonsiliasi Franchise
                                                </p>
                                            </div>
                                        </div>
                                    </div>

                                    <div className="p-5">
                                        <p className="text-sm text-gray-600 mb-4">
                                            Download hasil rekonsiliasi Franchise
                                            untuk periode aktif.
                                        </p>

                                        <button
                                            type="button"
                                            onClick={() =>
                                                handleDownloadReconciliation("FRC")
                                            }
                                            disabled={
                                                downloadLoading.FRC ||
                                                downloadLoading.REG ||
                                                !cabang ||
                                                !period?.id
                                            }
                                            className="
                                                w-full inline-flex items-center
                                                justify-center gap-2 px-4 py-2.5
                                                rounded-lg bg-purple-600
                                                hover:bg-purple-700 text-white
                                                text-sm font-semibold transition
                                                disabled:bg-gray-400
                                                disabled:cursor-not-allowed
                                            "
                                        >
                                            {downloadLoading.FRC ? (
                                                <>
                                                    <FaSyncAlt className="animate-spin" />
                                                    Menyiapkan File Franchise...
                                                </>
                                            ) : (
                                                <>
                                                    <FaFileExcel />
                                                    Download Franchise
                                                </>
                                            )}
                                        </button>
                                    </div>
                                </div>

                                {/* REGULER */}
                                <div className="
                                    bg-white rounded-xl border
                                    shadow-sm overflow-hidden
                                ">
                                    <div className="px-5 py-4 border-b bg-blue-50">
                                        <div className="flex items-center gap-3">
                                            <div className="
                                                w-10 h-10 rounded-lg
                                                bg-blue-100 text-blue-600
                                                flex items-center justify-center
                                            ">
                                                <FaExchangeAlt />
                                            </div>

                                            <div>
                                                <h3 className="font-bold text-gray-800">
                                                    Rekonsiliasi Reguler
                                                </h3>

                                                <p className="text-xs text-gray-500 mt-0.5">
                                                    Hasil rekonsiliasi Reguler
                                                </p>
                                            </div>
                                        </div>
                                    </div>

                                    <div className="p-5">
                                        <p className="text-sm text-gray-600 mb-4">
                                            Download hasil rekonsiliasi Reguler
                                            untuk periode aktif.
                                        </p>

                                        <button
                                            type="button"
                                            onClick={() =>
                                                handleDownloadReconciliation("REG")
                                            }
                                            disabled={
                                                downloadLoading.REG ||
                                                downloadLoading.FRC ||
                                                !cabang ||
                                                !period?.id
                                            }
                                            className="
                                                w-full inline-flex items-center
                                                justify-center gap-2 px-4 py-2.5
                                                rounded-lg bg-blue-600
                                                hover:bg-blue-700 text-white
                                                text-sm font-semibold transition
                                                disabled:bg-gray-400
                                                disabled:cursor-not-allowed
                                            "
                                        >
                                            {downloadLoading.REG ? (
                                                <>
                                                    <FaSyncAlt className="animate-spin" />
                                                    Menyiapkan File Reguler...
                                                </>
                                            ) : (
                                                <>
                                                    <FaFileExcel />
                                                    Download Reguler
                                                </>
                                            )}
                                        </button>
                                    </div>
                                </div>

                            </div>

                            {/* VALIDASI */}
                            {(!cabang || !period?.id) && (
                                <div className="
                                    rounded-lg border border-amber-200
                                    bg-amber-50 p-3 text-sm text-amber-800
                                ">
                                    <FaInfoCircle className="inline mr-2" />
                                    Pilih cabang dan pastikan periode aktif tersedia
                                    sebelum mengunduh hasil rekonsiliasi.
                                </div>
                            )}

                        </div>
                    )}

                </div>

            </div>

        </main>

    );

}