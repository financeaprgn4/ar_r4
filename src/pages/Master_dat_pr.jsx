import React, { useEffect, useState, useMemo, useRef } from "react";
import Swal from "sweetalert2";
import ReusableTable from "../components/ReusableTable";
import BottomDrawer from "../components/BottomDrawer";
import { useCabang } from "../contexts/CabangContext";
import { HiRefresh, HiSearch } from "react-icons/hi";
import { FaRecycle, FaFileImport, FaCheckCircle, FaCloudUploadAlt, FaPencilAlt, FaFilePdf, FaUpload, FaTrash } from "react-icons/fa";
import { formatDate, formatRupiah } from "../utility/textFormatter";

export default function Master_dat_pr() {
  const { cabang } = useCabang();

  /* ================= FILTER MASTER ================= */
  const [statusList, setStatusList] = useState([]);
  const [tokoList, setTokoList] = useState([]);
  const [minDate, setMinDate] = useState("");
  const [maxDate, setMaxDate] = useState("");
  const statusRef = useRef(null);
  const tokoSearchRef = useRef(null);
  const searchInputRef = useRef(null);

  useEffect(() => {
    statusRef.current?.focus();
  }, []);

  /* ================= FORM ================= */
  const [status, setStatus] = useState("");
  const [selectedToko, setSelectedToko] = useState([]);
  const [openTokoFilter, setOpenTokoFilter] = useState(false);
  const [searchToko, setSearchToko] = useState("");
  const [tglAwal, setTglAwal] = useState("");
  const [tglAkhir, setTglAkhir] = useState("");

  const filteredTokoList = tokoList.filter((t) =>
    t.toLowerCase().includes(searchToko.toLowerCase())
  );

  const selectAllToko = () => {
    setSelectedToko((prev) => {
      // jika semua sudah terpilih → unselect all
      if (filteredTokoList.every((t) => prev.includes(t))) {
        return [];
      }

      // jika belum semua → select all
      return [...filteredTokoList];
    });
  };

  /* ================= RESULT ================= */
  const [data, setData] = useState([]);
  const [loading, setLoading] = useState(false);
  const [searched, setSearched] = useState(false);
  const [selectedIds, setSelectedIds] = useState([]);
  const [visibleRows, setVisibleRows] = useState([]);
  const [searchValue, setSearchValue] = useState('');

  const toggleSelectAll = (rows) => {
    const allIds = rows.map((r) => r.id);

    setSelectedIds((prev) =>
      allIds.every(id => prev.includes(id)) ? [] : allIds
    );
  };

  const toggleSelectOne = (id) => {
    setSelectedIds((prev) =>
      prev.includes(id)
        ? prev.filter(x => x !== id)
        : [...prev, id]
    );
  };

  /* ================= LOAD FILTER MASTER ================= */
  const loadFilterMaster = async () => {
    try {
      const res = await fetch(
        `/api/master-dat-pr/filter?cabang=${encodeURIComponent(cabang)}`
      );
      const data = await res.json();

      setStatusList(data?.status ?? []);
      setTokoList(data?.kd_toko ?? []);
      setMinDate(data?.min_tgl ?? "");
      setMaxDate(data?.max_tgl ?? "");

      setTglAwal(data?.min_tgl ?? "");
      setTglAkhir(data?.max_tgl ?? "");
    } catch {
      Swal.fire("Error", "Gagal memuat filter master", "error");
    }
  };

  useEffect(() => {
    loadFilterMaster();
  }, []);

  const handleResetAll = () => {
    setStatus("");
    setSelectedToko([]);
    setTglAwal(minDate || "");
    setTglAkhir(maxDate || "");
    setData([]);
    setSearched(false);
  };

  /* ================= SEARCH ================= */
  const handleSearch = async (e) => {
    e.preventDefault();

    if (!status) {
      return Swal.fire("Validasi", "Status wajib dipilih", "warning");
    }

    if (!selectedToko || selectedToko.length === 0) {
      return Swal.fire("Validasi", "Minimal satu Kode Toko harus dipilih", "warning");
    }

    if (!tglAwal) {
      return Swal.fire("Validasi", "Tanggal awal wajib diisi", "warning");
    }

    if (!tglAkhir) {
      return Swal.fire("Validasi", "Tanggal akhir wajib diisi", "warning");
    }

    if (tglAwal && tglAkhir && tglAwal > tglAkhir) {
      return Swal.fire(
        "Validasi",
        "Tanggal awal tidak boleh lebih dari tanggal akhir",
        "warning"
      );
    }

    setLoading(true);
    setSearched(true);
    setData([]);

    try {
      const res = await fetch(
        `/api/master-dat-pr/search`,
        {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({
            status,
            kd_toko: selectedToko,
            tgl_awal: tglAwal || null,
            tgl_akhir: tglAkhir || null,
          }),
        }
      );

      const result = await res.json();

      if (res.ok) {
        setData(Array.isArray(result.data) ? result.data : []);
      } else {
        Swal.fire("Error", result.message || "Gagal mengambil data", "error");
      }
    } catch (error) {
      Swal.fire("Error", error.message || "Kesalahan jaringan", "error");
    } finally {
      setLoading(false);
    }
  };

  /* ================= TABLE ================= */
  const columns = useMemo(
    () => [
      /* ================= CHECKBOX COLUMN ================= */
      {
        id: "select",
        header: () => (
          <input
            type="checkbox"
            className="cursor-pointer"
            checked={
              visibleRows.length > 0 &&
              visibleRows.every((row) =>
                selectedIds.includes(row.id)
              )
            }
            onChange={() => toggleSelectAll(visibleRows)}
          />
        ),
        cell: ({ row }) => (
          <input
            type="checkbox"
            className="cursor-pointer"
            checked={selectedIds.includes(row.original.id)}
            onChange={() => toggleSelectOne(row.original.id)}
          />
        ),
        meta: { className: "text-center w-[50px]" },
      },

      /* ================= DATA COLUMNS ================= */
      {
        header: "Status",
        accessorKey: "status",
        meta: { className: "text-center" },
      },
      {
        header: "Kode Toko",
        accessorKey: "kd_toko",
      },
      {
        header: "Jns",
        accessorKey: "jns",
        meta: { className: "text-center" },
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
        cell: (info) => formatDate(info.getValue()),
        meta: { className: "text-center" },
      },
      {
        header: "Nilai",
        accessorKey: "harga_perolehan",
        cell: (info) => formatRupiah(info.getValue() ?? 0),
        meta: { className: "text-right" },
      },
    ],
    [selectedIds]
  );

  /* ================= ACTION ================= */
  const [selectedAction, setSelectedAction] = useState('');
  const handleSubmitAction = () => {
    // =============================
    // VALIDASI AWAL
    // =============================
    if (!selectedAction) {
      Swal.fire({
        icon: 'warning',
        title: 'Validasi',
        text: 'Silakan pilih action terlebih dahulu',
      });
      return;
    }

    if (!Array.isArray(selectedIds) || selectedIds.length === 0) {
      Swal.fire({
        icon: 'warning',
        title: 'Validasi',
        text: 'Silakan pilih minimal satu baris data',
      });
      return;
    }

    // =============================
    // KONFIRMASI
    // =============================
    Swal.fire({
      title: 'Konfirmasi',
      text: `Yakin menjalankan action "${selectedAction}" pada ${selectedIds.length} data?`,
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: 'Ya, Lanjutkan',
      cancelButtonText: 'Batal',
    }).then(async (res) => {
      if (!res.isConfirmed) return;

      // =============================
      // PAYLOAD
      // =============================
      const cabang = sessionStorage.getItem('cabang');

      const payload = {
        cabang,
        action: selectedAction,
        ids: selectedIds,
      };

      // Debug (opsional)
      console.group('ATPRACTION PAYLOAD');
      console.log(payload);
      console.groupEnd();

      try {
        const response = await fetch(
          `/api/atpr-action`,
          {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-TOKEN': document
                .querySelector('meta[name="csrf-token"]')
                ?.getAttribute('content'),
            },
            body: JSON.stringify(payload),
          }
        );

        const result = await response.json();

        // =============================
        // HANDLE ERROR DARI BACKEND
        // =============================
        if (!response.ok) {
          Swal.fire({
            icon: 'error',
            title: 'Gagal',
            text: result.message || 'Terjadi kesalahan saat memproses data',
          });
          return;
        }

        // =============================
        // HANDLE SUCCESS
        // =============================
        Swal.fire({
          icon: 'success',
          title: 'Berhasil',
          text: result.message,
        });

        // Optional: reset state setelah sukses
        // setSelectedIds([]);
        // setSelectedAction('');

      } catch (error) {
        console.error('ATPR ACTION ERROR:', error);

        Swal.fire({
          icon: 'error',
          title: 'Error',
          text: 'Tidak dapat terhubung ke server',
        });
      }
    });
  };


  /* ================= UPLOAD ================= */
  const fileInputRef = useRef(null);
  const labelRef = useRef(null);
  const [showDrawer, setShowDrawer] = useState(false);
  const [selectedFile, setSelectedFile] = useState(null);

  const handleFileChange = (e) => {
    const files = Array.from(e.target.files);
    if (files.length > 0) {
      setSelectedFile(files);
    }
  };

  const resetFilePicker = () => {
    setSelectedFile(null);
    const fileInput = document.getElementById("fileUpload");
    if (fileInput) fileInput.value = null;
  };

  useEffect(() => {
    if (showDrawer && labelRef.current) {
      labelRef.current.focus();
    }
  }, [showDrawer]);
  
  const handleImport = async () => {
    if (!selectedFile || selectedFile.length === 0) {
      return Swal.fire("Validasi", "File belum dipilih", "warning");
    }

    const cabang = sessionStorage.getItem("cabang");

    if (!cabang) {
      return Swal.fire(
        "Session Error",
        "Cabang tidak ditemukan di session",
        "error"
      );
    }

    const formData = new FormData();
    formData.append("cabang", cabang);

    selectedFile.forEach((file) => {
      formData.append("files[]", file);
    });

    Swal.fire({
      title: "Import sedang berjalan...",
      text: "Mohon tunggu",
      allowOutsideClick: false,
      allowEscapeKey: false,
      didOpen: () => Swal.showLoading(),
    });

    try {
      const res = await fetch(
        `/api/import-atpr`,
        {
          method: "POST",
          body: formData,
        }
      );

      const result = await res.json();

      Swal.close();

      if (!res.ok) {
        throw new Error(result.message || "Gagal import data");
      }

      // ✅ ALERT SUKSES (GANTI LOADING)
      Swal.fire({
        icon: "success",
        title: "Berhasil",
        text: result.message || "Data berhasil diimpor",
        confirmButtonText: "OK",
      }).then(async () => {
        resetFilePicker();
        setShowDrawer(false);

        // 🔥 RESET STATE TABEL
        setData([]);
        setSearched(false);
        setSelectedIds([]);
        setSelectedToko([]);
        setSearchToko("");

        // 🔥 RELOAD FILTER MASTER (TERMASUK TOKO)
        await loadFilterMaster();
        
        statusRef.current?.focus();
      });
    } catch (err) {
      Swal.close();
      Swal.fire("Error", "Gagal koneksi ke server", "error");
    }
  };

  /* =========== FILTER RIGHTELEMENT ============ */
  const [columnFilter, setColumnFilter] = useState({
    column: "",
    operator: "contains",
    value: "",
  });

  const filteredDataByColumn = useMemo(() => {
    const { column, operator, value } = columnFilter;

    if (!column || !value) return data;

    return data.filter((row) => {
      const cellValue = String(row[column] ?? "").toLowerCase();
      const filterValue = value.toLowerCase();

      switch (operator) {
        case "equals":
          return cellValue === filterValue;
        case "starts":
          return cellValue.startsWith(filterValue);
        case "ends":
          return cellValue.endsWith(filterValue);
        case "contains":
        default:
          return cellValue.includes(filterValue);
      }
    });
  }, [data, columnFilter]);

  const columnFilterElement = (
    <div className="flex items-stretch gap-2 h-full">
      {/* SELECT COLUMN */}
      <select
        className="border px-3 rounded text-sm h-[38px]"
        value={columnFilter.column}
        onChange={(e) =>
          setColumnFilter((prev) => ({ ...prev, column: e.target.value }))
        }
      >
        <option value="" disabled>Kolom</option>
        {columns
          .filter((c) => c.accessorKey)
          .map((c) => (
            <option key={c.accessorKey} value={c.accessorKey}>
              {c.header}
            </option>
          ))}
      </select>

      {/* OPERATOR */}
      <select
        className="border px-3 rounded text-sm h-[38px]"
        value={columnFilter.operator}
        onChange={(e) =>
          setColumnFilter((prev) => ({ ...prev, operator: e.target.value }))
        }
      >
        <option value="contains">Contains</option>
        <option value="equals">Is Equal</option>
        <option value="starts">Begin With</option>
        <option value="ends">End With</option>
      </select>

      {/* VALUE */}
      <input
        type="text"
        className="border px-3 rounded text-sm h-[38px] w-40"
        placeholder="Nilai..."
        value={columnFilter.value}
        onChange={(e) =>
          setColumnFilter((prev) => ({ ...prev, value: e.target.value }))
        }
      />

      {/* RESET */}
      <button
        type="button"
        className="border px-3 rounded text-sm h-[38px] bg-gray-100 hover:bg-gray-200"
        onClick={() =>
          setColumnFilter({ column: "", operator: "contains", value: "" })
        }
      >
        Reset
      </button>
    </div>
  );

  /* ================= SHORTCUT ================= */
  useEffect(() => {
    const getFocusableTokoItems = () =>
      document.querySelectorAll('[data-toko-item="true"]');

    const moveTokoFocus = (direction) => {
      const items = getFocusableTokoItems();
      if (!items.length) return;

      const currentIndex = Array.from(items).findIndex(
        (el) => el === document.activeElement
      );

      let nextIndex = 0;

      if (currentIndex === -1) {
        nextIndex = 0;
      } else {
        nextIndex =
          direction === "down"
            ? Math.min(currentIndex + 1, items.length - 1)
            : Math.max(currentIndex - 1, 0);
      }

      items[nextIndex]?.focus();
    };

    const toggleFocusedToko = () => {
      const el = document.activeElement;
      if (el?.dataset?.tokoValue) {
        const toko = el.dataset.tokoValue;
        setSelectedToko((prev) =>
          prev.includes(toko)
            ? prev.filter((t) => t !== toko)
            : [...prev, toko]
        );
      }
    };

    const handleAltShortcut = (code, e) => {
      e.preventDefault();

      switch (code) {
        case "KeyA":
          if (openTokoFilter) {
            selectAllToko();
          }else{
            toggleSelectAll(visibleRows);
          }
          break;

        case "KeyG":
          setOpenTokoFilter(true);
          setTimeout(() => tokoSearchRef.current?.focus(), 50);
          break;

        case "KeyR":
          setSelectedToko([]);
          setSearchToko("");
          break;

        case "KeyU":
          setShowDrawer(true);
          break;

        default:
          break;
      }
    };

    const handleKeyDown = (e) => {
      const { key, code, altKey } = e;

      /* ESC */
      if (key === "Escape") {
        resetFilePicker();
        setShowDrawer(false);
        setOpenTokoFilter(false);
        return;
      }

      /* ALT SHORTCUT */
      if (altKey && code) {
        handleAltShortcut(code, e);
        return;
      }

      /* FILE PICKER ENTER */
      if (
        key === "Enter" &&
        document.activeElement === labelRef.current &&
        fileInputRef.current
      ) {
        e.preventDefault();
        fileInputRef.current.click();
        return;
      }

      /* DROPDOWN TOKO NAVIGATION */
      if (openTokoFilter) {
        if (key === "ArrowDown") {
          e.preventDefault();
          moveTokoFocus("down");
        }

        if (key === "ArrowUp") {
          e.preventDefault();
          moveTokoFocus("up");
        }

        if (key === "Enter" || key === " ") {
          e.preventDefault();
          toggleFocusedToko();
        }
      }
    };

    window.addEventListener("keydown", handleKeyDown);
    return () => window.removeEventListener("keydown", handleKeyDown);
  }, [
    openTokoFilter,
    visibleRows,
    resetFilePicker,
    setSelectedToko,
    setSearchToko,
  ]);

  return (
    <main className="flex-1 px-4 py-2 z-10 text-white flex flex-col">
      <div className="min-h-[calc(100vh-50px)] bg-white/60 rounded-lg p-4 shadow-lg text-gray-800 w-full">
        {/* HEADER */}
        <div className="shrink-0">
          <div className="box-header text-white shadow-md flex items-center justify-center mx-auto mt-[-17px] mb-2 h-[60px] w-1/2 bg-blue-400 clip-path-custom">
            <h2 className="text-xl text-center font-semibold">
              Master Data Aktiva & Prepaid Cabang {cabang}
            </h2>
          </div>
        </div>
        
        {/* ================= FILTER ================= */}
        <div className="shrink-0 px-4 pb-4 border-b">
          <form
            onSubmit={handleSearch}
            className="grid grid-cols-1 md:grid-cols-5 gap-4 bg-gray-100 p-4 rounded-lg items-end"
          >
            {/* STATUS */}
            <div>
              <label className="text-sm font-semibold">Status</label>
              <select
                ref={statusRef}
                className="w-full p-2 border rounded h-[42px]"
                value={status}
                onChange={(e) => setStatus(e.target.value)}
              >
                <option value="">-- Pilih Status --</option>
                {statusList.map((s) => (
                  <option key={s} value={s}>{s}</option>
                ))}
              </select>
            </div>

            {/* TOKO */}
            <div className="relative">
              <label className="text-sm font-semibold">Kode Toko</label>

              {/* INPUT DISPLAY */}
              <div
                tabIndex={0}
                role="button"
                aria-haspopup="listbox"
                aria-expanded={openTokoFilter}
                className="w-full p-2 border rounded h-[42px] cursor-pointer bg-white flex items-center justify-between focus:outline-none focus:ring-2 focus:ring-blue-400"
                onClick={() => setOpenTokoFilter((prev) => !prev)}
                onKeyDown={(e) => {
                  if (e.key === "Enter" || e.key === " ") {
                    e.preventDefault();
                    setOpenTokoFilter(true);
                  }
                }}
              >
                <span className="text-sm text-gray-700 truncate">
                  {selectedToko.length > 0
                    ? `${selectedToko.length} toko dipilih`
                    : "Pilih Kode Toko"}
                </span>
                <span className="text-gray-400 text-xs">▼</span>
              </div>

              {/* DROPDOWN */}
              {openTokoFilter && (
                <div className="absolute z-50 mt-1 w-full bg-white border rounded shadow-lg">

                  {/* SEARCH + SELECT ALL */}
                  <div className="p-2 border-b flex items-center gap-2">
                    {/* SELECT ALL */}
                    <label className="flex items-center gap-1 text-sm cursor-pointer">
                      <input
                        type="checkbox"
                        checked={
                          filteredTokoList.length > 0 &&
                          filteredTokoList.every((t) => selectedToko.includes(t))
                        }
                        onChange={(e) => {
                          if (e.target.checked) {
                            setSelectedToko((prev) => [
                              ...new Set([...prev, ...filteredTokoList]),
                            ]);
                          } else {
                            setSelectedToko((prev) =>
                              prev.filter((t) => !filteredTokoList.includes(t))
                            );
                          }
                        }}
                      />
                      All
                    </label>

                    {/* SEARCH INPUT */}
                    <input
                      ref={tokoSearchRef}
                      type="text"
                      placeholder="Cari toko..."
                      value={searchToko}
                      onChange={(e) => setSearchToko(e.target.value)}
                      className="flex-1 p-2 border rounded text-sm"
                    />
                  </div>

                  {/* LIST */}
                  <div className="max-h-48 overflow-auto">
                    {filteredTokoList.length === 0 && (
                      <div className="p-3 text-sm text-gray-500 text-center">
                        Tidak ditemukan
                      </div>
                    )}

                    {filteredTokoList.map((toko) => (
                      <label
                        key={toko}
                        data-toko-item="true"
                        data-toko-value={toko}
                        tabIndex={0}
                        className="flex items-center gap-2 px-3 py-2 hover:bg-gray-100 cursor-pointer text-sm focus:bg-blue-100 outline-none"
                      >
                        <input
                          type="checkbox"
                          checked={selectedToko.includes(toko)}
                          readOnly
                        />
                        {toko}
                      </label>
                    ))}
                  </div>

                  {/* FOOTER */}
                  <div className="flex justify-between p-2 border-t text-sm">
                    <button
                      type="button"
                      className="text-blue-600 hover:underline"
                      onClick={() => setSelectedToko([])}
                    >
                      Reset
                    </button>
                    <button
                      type="button"
                      className="text-green-600 hover:underline"
                      onClick={() => setOpenTokoFilter(false)}
                    >
                      OK
                    </button>
                  </div>
                </div>
              )}
            </div>

            {/* TGL AWAL */}
            <div>
              <label className="text-sm font-semibold">Tanggal Awal</label>
              <input
                type="date"
                className="w-full p-2 border rounded h-[42px]"
                min={minDate || undefined}
                max={tglAkhir || maxDate || undefined}
                value={tglAwal}
                onChange={(e) => setTglAwal(e.target.value)}
              />
            </div>

            {/* TGL AKHIR */}
            <div>
              <label className="text-sm font-semibold">Tanggal Akhir</label>
              <input
                type="date"
                className="w-full p-2 border rounded h-[42px]"
                min={tglAwal || minDate || undefined}
                max={maxDate || undefined}
                value={tglAkhir}
                onChange={(e) => setTglAkhir(e.target.value)}
              />
            </div>

            {/* BUTTON GROUP */}
            <div className="flex gap-2">
              <button
                type="submit"
                className="btn btn-primary flex items-center gap-2 h-[42px]"
                disabled={loading}
              >
                <HiSearch />
                {loading ? "Mencari..." : "Search"}
              </button>

              <button
                type="button"
                onClick={() => setShowDrawer(true)}
                className="btn btn-success flex items-center gap-2 h-[42px]"
                title="Upload Data (Alt + U)"
              >
                <FaUpload />
                Upload
              </button>

              <button
                type="button"
                onClick={handleResetAll}
                className="btn btn-danger flex items-center gap-2 h-[42px]"
                title="Upload Data (Alt + U)"
              >
                <FaRecycle />
                Reset
              </button>
            </div>

          </form>
        </div>

        {/* ================= RESULT ================= */}
        <div className="flex-1 min-h-0 flex flex-col px-2 pt-2">
          {/* SCROLL TABLE */}
          <div className="flex-1 overflow-auto">
            {searched && !loading && data.length === 0 && (
              <div className="text-center text-red-500 mt-6">
                Data tidak ditemukan
              </div>
            )}

            {data.length > 0 && (
              <ReusableTable
                data={filteredDataByColumn}
                columns={columns}
                globalFilter={searchValue}
                setGlobalFilter={setSearchValue}
                searchInputRef={searchInputRef}
                onVisibleDataChange={setVisibleRows}
                rightElement={columnFilterElement}
              />
            )}
          </div>

          {/* ACTION BAR (TIDAK IKUT SCROLL) */}
          {data.length > 0 && (
            <div className="shrink-0 mt-3 mb-3 flex items-center gap-4 bg-gray-100 border rounded-lg px-4 py-3">
              
              <div className="flex items-center gap-3">
                <label className="text-sm font-semibold text-gray-700">
                  Action
                </label>

                <select
                  className="border rounded px-3 py-2 text-sm min-w-[180px]"
                  value={selectedAction}
                  onChange={(e) => setSelectedAction(e.target.value)}
                >
                  <option value="">-- Pilih Action --</option>
                  <option value="use">Use Data</option>
                  <option value="surkas">Change Flag Surkas</option>
                  <option value="retire">Retire</option>
                </select>

                {selectedIds.length > 0 && (
                  <span className="text-xs text-gray-600">
                    {selectedIds.length} data terpilih
                  </span>
                )}
              </div>

              <button
                type="button"
                onClick={handleSubmitAction}
                disabled={!selectedAction || selectedIds.length === 0}
                className="bg-blue-600 text-white px-5 py-2 rounded-md text-sm font-semibold
                          hover:bg-blue-700 disabled:bg-gray-400"
              >
                Submit
              </button>
            </div>
          )}
        </div>

        {/* ================= UPLOAD DRAWER ================= */}
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
                    accept=".txt"
                    onChange={handleFileChange}
                    className="hidden"
                  />
                </label>
              </div>

              {/* === Footer === */}
              <div className="fixed bottom-0 left-[-6%] right-0 border-t flex items-center justify-between z-10">
                <div className="trapezium-box text-white text-3xl shadow-md mt-[-8px] flex items-center justify-center h-[50px] w-[280px] bg-yellow-400">
                  Import AT/PR
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

      </div>
    </main>
  );
}
