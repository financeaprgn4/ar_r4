import React, { useEffect, useMemo, useState, useCallback } from 'react';
import Swal from "sweetalert2";
import { FaPlus, FaTimes, FaSave, FaPaperPlane } from "react-icons/fa";
import { formatDate } from "../utility/textFormatter";
import { HiPencil, HiTrash } from "react-icons/hi";
import ReusableTable from "../components/ReusableTable";

export default function Saldo() {
    // ============================= Indexing
    const [dataBank, setDataBank] = useState([]);
    const [loading, setLoading] = useState(true);
    const cabang = sessionStorage.getItem("cabang");
    const [data, setData] = useState([]);

    useEffect(() => {
        const fetchData = async () => {
            try {
                const res = await fetch(`/api/auto-bank?cabang=${cabang}`);
                if (!res.ok) throw new Error("Gagal mengambil data");

                const data = await res.json();
                setDataBank(data);
            } catch (err) {
                Swal.fire("Error", err.message, "error");
            } finally {
                setLoading(false);
            }
        };

        if (cabang) fetchData();
    }, [cabang]);

    // ============================= Checkbox
    const [selectedRows, setSelectedRows] = useState([]);

    const handleSelectAll = (e) => {
        if (e.target.checked) {
            setSelectedRows(dataBank.map((row) => ({
                no_rek: row.no_rek,
                bank: row.bank
            })));
        } else {
            setSelectedRows([]);
        }
    };

    const handleSelectRow = (row) => {
        setSelectedRows((prev) => {
            const exists = prev.find(r => r.no_rek === row.no_rek);
            if (exists) {
                return prev.filter(r => r.no_rek !== row.no_rek);
            }
            return [...prev, { no_rek: row.no_rek, bank: row.bank }];
        });
    };

    // ============================= Filtering Date & Proses
    const [startDate, setStartDate] = useState("");
    const [endDate, setEndDate] = useState("");

    const waitJobFinished = async (jobIds) => {
        const interval = 5000;
        const timeout = 1000 * 60 * 30;
        const start = Date.now();

        return new Promise((resolve, reject) => {

            const timer = setInterval(async () => {

                if (Date.now() - start > timeout) {
                    clearInterval(timer);
                    reject(new Error("Timeout menunggu job selesai"));
                    return;
                }

                try {
                    const res = await fetch("/api/job-status", {
                        method: "POST",
                        headers: { "Content-Type": "application/json" },
                        body: JSON.stringify({ job_ids: jobIds })
                    });

                    if (!res.ok) throw new Error("Gagal cek status job");

                    const data = await res.json();

                    if (!Array.isArray(data)) {
                        throw new Error("Format response job tidak valid");
                    }

                    const hasFailed = data.some(job => job.status === "Failed");
                    if (hasFailed) {
                        clearInterval(timer);
                        reject(new Error("Ada job yang gagal"));
                        return;
                    }

                    const allDone = data.every(job => job.status === "Success");
                    if (allDone) {
                        clearInterval(timer);
                        resolve();
                    }

                } catch (err) {
                    console.error("Polling error:", err);
                }

            }, interval);
        });
    };

    const handleProses = async () => {
        const username = sessionStorage.getItem("username");

        // ================= VALIDASI
        if (selectedRows.length === 0) {
            Swal.fire("Validasi", "Pilih minimal satu rekening", "warning");
            return;
        }

        if (!startDate || !endDate) {
            Swal.fire("Validasi", "Start date dan end date wajib diisi", "warning");
            return;
        }

        const payload = {
            cabang,
            username,
            accounts: selectedRows,
            start_date: startDate,
            end_date: endDate
        };

        try {

            Swal.fire({
                title: "Memproses",
                text: "Job sedang dikirim ke worker...",
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });

            // ================= KIRIM JOB
            const res = await fetch("/api/proses-mutasi", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify(payload)
            });

            const result = await res.json();

            if (!res.ok) throw new Error(result.message);

            // ================= HANDLE CAPTCHA BSI =================
            const bsi = result.results?.bsi;

            if (bsi && bsi.status === 'captcha_required') {

                Swal.close();

                const { value: captchaInput } = await Swal.fire({
                    title: 'Masukkan Captcha BSI',
                    html: `
                        <img src="${bsi.captcha}" style="width:150px;margin-bottom:10px"/>
                        <input id="captchaInput" class="swal2-input" placeholder="Masukkan captcha">
                    `,
                    confirmButtonText: 'Submit',
                    allowOutsideClick: false,
                    preConfirm: () => {
                        const val = document.getElementById('captchaInput').value;
                        if (!val) {
                            Swal.showValidationMessage('Captcha wajib diisi');
                        }
                        return val;
                    }
                });

                if (!captchaInput) {
                    Swal.fire("Batal", "Captcha tidak diisi", "warning");
                    return;
                }

                // ================= DEBUG =================
                console.log("Submit captcha:", {
                    job_id: bsi.job_id,
                    captcha: captchaInput
                });

                Swal.fire({
                    title: "Mengirim captcha...",
                    allowOutsideClick: false,
                    didOpen: () => Swal.showLoading()
                });

                // ================= KIRIM KE BACKEND =================
                const captchaRes = await fetch('/api/submit-captcha', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        job_id: bsi.job_id,
                        captcha: captchaInput
                    })
                });

                let captchaResult;

                try {
                    captchaResult = await captchaRes.json();
                } catch {
                    throw new Error("Response bukan JSON dari server");
                }

                console.log("Response captcha:", captchaResult);

                if (!captchaRes.ok) {
                    throw new Error(captchaResult.message || "Captcha gagal");
                }

                Swal.fire({
                    title: "Melanjutkan proses",
                    text: "Menunggu job selesai...",
                    allowOutsideClick: false,
                    didOpen: () => Swal.showLoading()
                });
            }

            console.log("FULL RESPONSE:", result);
            let jobIds = [];

            // ================= HANDLE MULTI BANK (BSI)
            if (result.results && Object.keys(result.results).length > 0) {
                jobIds = Object.values(result.results)
                    .map(r => r.job_id)
                    .filter(Boolean);
            }

            // ================= HANDLE SINGLE BANK
            else if (result.job_id) {
                jobIds = [result.job_id];
            }

            // ================= HANDLE FORMAT LAIN
            else if (result.data?.job_id) {
                jobIds = [result.data.job_id];
            }

            // ================= ERROR
            if (jobIds.length === 0) {
                console.warn("RESULT TIDAK SESUAI FORMAT:", result);

                Swal.fire("Error", "Format response tidak dikenali", "error");
                return;
            }

            // ================= POLLING STATUS
            await waitJobFinished(jobIds);

            Swal.fire("Selesai", "Semua mutasi berhasil diproses", "success");

        } catch (err) {
            console.error("ERROR HANDLE PROSES:", err);
            Swal.fire("Error", err.message, "error");
        }
    };

    /* ================= PROSES MEMUAT DATA ================= */
    const [initialLoading, setInitialLoading] = useState(true);
    
    const fetchData = useCallback(() => {
        if (!cabang) return;
    
        if (initialLoading) {
          setInitialLoading(true);
        } else {
          setLoading(true);
        }
    
        fetch(`/api/tasks?cabang=${cabang}`)
          .then((res) => {
            if (!res.ok) throw new Error("Gagal mengambil data");
            return res.json();
          })
          .then((res) => {
            const copiedData = res.data.map(item => ({ ...item }));
            setData(copiedData);
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
    }, [cabang]);

    const columns = useMemo(() => [
        {
          header: "Job ID",
          accessorKey: "id",
        },
        {
          header: "Name",
          accessorKey: "job_name",
        },
        {
          header: "Parameters",
          accessorKey: "parameters",
        },
        {
            header: "Status",
            accessorKey: "status",
            cell: ({ getValue }) => {
                const status = getValue();

                let className = "px-2 py-1 rounded text-xs font-semibold";

                if (status === "Success") {
                className += " bg-green-500 text-white";
                } else if (status === "Failed") {
                className += " bg-red-500 text-white";
                } else if (status === "On Process") {
                className += " bg-yellow-400 text-black";
                } else {
                className += " bg-gray-300 text-black";
                }

                return (
                <span className={className}>
                    {status}
                </span>
                );
            }
        },
        {
          header: "Date Submitted",
          accessorKey: "start_time",
          cell: ({ getValue }) => formatDate(getValue()),
          meta: { className: "text-center whitespace-nowrap w-[110px]" },
        }
    ], [cabang]);

    return (
        <main className="flex-1 px-4 py-3 z-10 text-white">
            <div className="h-[calc(100vh-55px)] bg-white/60 rounded-lg p-4 shadow-lg text-gray-800 w-full flex flex-col min-h-0">

                {/* HEADER */}
                <div className="relative flex items-center justify-center mb-4 shrink-0">
                    <div className="box-header text-white shadow-md flex items-center justify-center mx-auto mt-[-16px] h-[60px] w-1/2 bg-blue-400 clip-path-custom">
                        <h2 className="text-xl text-center font-bold">
                        MONITORING SALDO & DOWNLOAD MUTASI BANK
                        </h2>
                    </div>
                </div>

                <div className="flex flex-col flex-1 gap-4 min-h-0">

                {/* ROW ATAS */}
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4 flex-1 min-h-0">

                    {/* ================= BOX 1 ================= */}
                    <div className="bg-white rounded-xl shadow p-4 flex flex-col min-h-0">
                        <h3 className="font-semibold mb-3 shrink-0">
                            Daftar Auto Bank ({cabang})
                        </h3>

                        {/* AREA SCROLL */}
                        <div className="flex-1 overflow-auto rounded-lg border border-gray-200 min-h-0">
                            {loading ? (
                            <div className="flex items-center justify-center h-full text-gray-500">
                                Loading data...
                            </div>
                            ) : (
                            <table className="min-w-full text-sm">
                                <thead className="bg-gray-100 sticky top-0 z-10">
                                <tr className="text-left">
                                    <th className="px-3 py-2">
                                    <input
                                        type="checkbox"
                                        onChange={handleSelectAll}
                                        checked={
                                        dataBank.length > 0 &&
                                        selectedRows.length === dataBank.length
                                        }
                                    />
                                    </th>
                                    <th className="px-4 py-2 font-semibold">Cabang</th>
                                    <th className="px-4 py-2 font-semibold">Bank</th>
                                    <th className="px-4 py-2 font-semibold">No Rekening</th>
                                    <th className="px-4 py-2 font-semibold">Balance</th>
                                </tr>
                                </thead>

                                <tbody>
                                {dataBank.length === 0 ? (
                                    <tr>
                                    <td colSpan="5" className="text-center py-6 text-gray-400">
                                        Tidak ada data
                                    </td>
                                    </tr>
                                ) : (
                                    dataBank.map((row, i) => (
                                    <tr key={i} className="border-t hover:bg-blue-50 transition">
                                        <td className="px-3 py-2">
                                        <input
                                            type="checkbox"
                                            checked={selectedRows.some(r => r.no_rek === row.no_rek)}
                                            onChange={() => handleSelectRow(row)}
                                        />
                                        </td>

                                        <td className="px-4 py-2">{row.cabang}</td>
                                        <td className="px-4 py-2">{row.bank}</td>
                                        <td className="px-4 py-2">{row.no_rek}</td>
                                        <td className="px-4 py-2">
                                        <span className={`px-2 py-1 text-xs rounded-full font-semibold
                                            ${row.balance === 'Y'
                                            ? 'bg-green-100 text-green-700'
                                            : 'bg-red-100 text-red-700'
                                            }`}>
                                            {row.balance}
                                        </span>
                                        </td>
                                    </tr>
                                    ))
                                )}
                                </tbody>
                            </table>
                            )}
                        </div>

                        {/* FILTER + BUTTON (FIXED HEIGHT) */}
                        <div className="shrink-0">
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-3 mt-4">
                            <div className="flex flex-col">
                                <label className="text-sm font-medium mb-1">Start Date</label>
                                <input
                                type="date"
                                className="border rounded-lg px-3 py-2 text-sm"
                                value={startDate}
                                onChange={(e) => setStartDate(e.target.value)}
                                />
                            </div>

                            <div className="flex flex-col">
                                <label className="text-sm font-medium mb-1">End Date</label>
                                <input
                                type="date"
                                className="border rounded-lg px-3 py-2 text-sm"
                                value={endDate}
                                onChange={(e) => setEndDate(e.target.value)}
                                />
                            </div>
                            </div>

                            <div className="flex justify-end gap-3 mt-4">
                            <button
                                className="flex items-center gap-2 px-4 py-2 rounded-lg bg-yellow-500 text-white"
                                onClick={() => Swal.fire("Edit", "Fitur edit belum dibuat", "info")}
                            >
                                <HiPencil /> Edit
                            </button>

                            <button
                                className="flex items-center gap-2 px-4 py-2 rounded-lg bg-blue-500 text-white"
                                onClick={handleProses}
                            >
                                <FaPaperPlane /> Proses
                            </button>
                            </div>
                        </div>
                    </div>

                    {/* ================= BOX 2 ================= */}
                    <div className="bg-white rounded-xl shadow p-4 flex flex-col min-h-0">
                        <h3 className="font-semibold mb-2 shrink-0">
                            TASKS SCHEDULE
                        </h3>

                        <div className="flex-1 overflow-auto min-h-0">
                            <ReusableTable
                            columns={columns}
                            data={data}
                            />
                        </div>
                    </div>

                </div>
                </div>
            </div>
        </main>
    );
}
