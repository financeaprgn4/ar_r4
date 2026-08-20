import React, {
  useEffect,
  useMemo,
  useRef,
  useState,
} from "react";

import Swal from "sweetalert2";

import DatePicker, {
  registerLocale,
} from "react-datepicker";

import "react-datepicker/dist/react-datepicker.css";
import { formatDate, dateToString, stringToDate, dateToPeriode } from "../utility/textFormatter";

import { id } from "date-fns/locale";

import {
  FaPlus,
  FaTimes,
  FaSave,
  FaEdit,
  FaCalendarAlt,
  FaCheckCircle,
  FaLock,
  FaSyncAlt,
  FaFilter,
  FaChartBar,
  FaDatabase,
} from "react-icons/fa";

import ReusableTable from "../components/ReusableTable";
import { useCabang } from "../contexts/CabangContext";


// ============================================================
// REGISTER LOCALE INDONESIA
// ============================================================

registerLocale("id", id);


// ============================================================
// COMPONENT
// ============================================================

export default function Periode() {

  const { cabang } = useCabang();

  // ==========================================================
  // DATA
  // ==========================================================

  const [data, setData] = useState([]);
  const [loading, setLoading] = useState(false);

  // ==========================================================
  // FILTER
  // ==========================================================

  const [globalFilter, setGlobalFilter] = useState("");
  const [filterKategori, setFilterKategori] = useState("");
  const [filterStatus, setFilterStatus] = useState("");

  const searchRef = useRef(null);

  // ==========================================================
  // MODAL
  // ==========================================================

  const [showModal, setShowModal] = useState(false);
  const [modalMode, setModalMode] = useState("add");

  // ==========================================================
  // FORM
  // ==========================================================

  const emptyForm = {
    id: null,
    cabang: cabang || "",
    kategori: "",
    periode: "",
    start_date: "",
    end_date: "",
    status: "Aktif",
  };

  const [formData, setFormData] = useState(emptyForm);

  // ==========================================================
  // SAVING
  // ==========================================================

  const [saving, setSaving] = useState(false);


  // ==========================================================
  // CATEGORY
  // ==========================================================

  const kategoriOptions = [
    {
      value: "Mutasi",
      label: "Mutasi",
      description: "Periode transaksi / mutasi bank",
      className:
        "bg-blue-50 text-blue-700 border-blue-200",
    },
    {
      value: "HD",
      label: "HD",
      description: "Periode HD",
      className:
        "bg-purple-50 text-purple-700 border-purple-200",
    },
    {
      value: "LPD",
      label: "LPD",
      description: "Periode LPD",
      className:
        "bg-emerald-50 text-emerald-700 border-emerald-200",
    },
  ];


  // ==========================================================
  // FOCUS SEARCH
  // ==========================================================

  useEffect(() => {
    searchRef.current?.focus();
  }, []);

  /*
   * Mendapatkan tanggal pertama bulan
   */
  const getStartOfMonth = (date) => {

    if (!date) {
      return null;
    }

    return new Date(
      date.getFullYear(),
      date.getMonth(),
      1
    );
  };


  /*
   * Mendapatkan tanggal terakhir bulan
   */
  const getEndOfMonth = (date) => {

    if (!date) {
      return null;
    }

    return new Date(
      date.getFullYear(),
      date.getMonth() + 1,
      0
    );
  };


  // ==========================================================
  // GET DATA
  // ==========================================================

  const fetchData = async (
    cabangAktif = cabang,
    signal = undefined
  ) => {

    if (!cabangAktif) {

      setData([]);

      return;
    }

    try {

      setLoading(true);

      const res = await fetch(
        `/api/periodelist?cabang=${encodeURIComponent(
          cabangAktif
        )}`,
        {
          method: "GET",

          headers: {
            Accept: "application/json",
          },

          signal,
        }
      );


      if (!res.ok) {

        throw new Error(
          "Gagal mengambil data periode."
        );
      }


      const result = await res.json();


      let periodeData = [];


      if (Array.isArray(result)) {

        periodeData = result;

      } else if (
        Array.isArray(result.data)
      ) {

        periodeData = result.data;
      }


      setData(periodeData);

    } catch (error) {

      if (
        error.name === "AbortError"
      ) {
        return;
      }


      console.error(
        "GET PERIODE ERROR:",
        error
      );


      setData([]);


      Swal.fire({
        icon: "error",
        title: "Gagal",
        text:
          error.message ||
          "Gagal mengambil data periode.",
        confirmButtonColor: "#2563eb",
      });

    } finally {

      /*
       * Jangan ubah loading jika request
       * sudah dibatalkan.
       */
      if (
        !signal ||
        !signal.aborted
      ) {
        setLoading(false);
      }
    }
  };


  // ==========================================================
  // LOAD DATA SAAT CABANG BERUBAH
  // ==========================================================

  useEffect(() => {

    const controller =
      new AbortController();


    setData([]);

    setGlobalFilter("");
    setFilterKategori("");
    setFilterStatus("");


    if (!cabang) {

      setLoading(false);

      return () => {
        controller.abort();
      };
    }


    fetchData(
      cabang,
      controller.signal
    );


    return () => {

      controller.abort();

    };

  }, [cabang]);


  // ==========================================================
  // ADD
  // ==========================================================

  const handleAdd = () => {

    setModalMode("add");

    setFormData({
      ...emptyForm,
      cabang: cabang || "",
    });

    setShowModal(true);
  };


  // ==========================================================
  // EDIT
  // ==========================================================

  const handleEdit = (item) => {

    setModalMode("edit");


    setFormData({
      id: Number(item.id),

      cabang:
        item.Cabang ||
        item.cabang ||
        cabang ||
        "",

      kategori:
        item.kategori || "",

      /*
       * PERIODE LANGSUNG DISIMPAN
       * SEBAGAI STRING DARI DATABASE
       *
       * Contoh:
       * Aug-2026
       */
      periode:
        item.periode || "",

      start_date:
        item.start_date || "",

      end_date:
        item.end_date || "",

      status:
        item.status || "Close",
    });


    setShowModal(true);
  };


  // ==========================================================
  // CLOSE MODAL
  // ==========================================================

  const handleCloseModal = () => {

    if (saving) {
      return;
    }


    setShowModal(false);


    setFormData({
      ...emptyForm,
      cabang: cabang || "",
    });
  };


  // ==========================================================
  // CHANGE FORM
  // ==========================================================

  const handleChange = (e) => {

    const {
      name,
      value,
    } = e.target;


    setFormData((prev) => ({
      ...prev,
      [name]: value,
    }));
  };

  const handlePeriodeChange = (date) => {

    if (!date) {

      setFormData((prev) => ({
        ...prev,

        periode: "",
        start_date: "",
        end_date: "",
      }));

      return;
    }


    const startDate =
      getStartOfMonth(date);

    const endDate =
      getEndOfMonth(date);


    setFormData((prev) => ({
      ...prev,

      /*
       * PERIODE ADALAH STRING
       */
      periode:
        dateToPeriode(date),

      /*
       * TANGGAL DISIMPAN
       * DALAM FORMAT DATABASE
       */
      start_date:
        dateToString(startDate),

      end_date:
        dateToString(endDate),
    }));
  };


  // ==========================================================
  // CHECK DUPLICATE
  // ==========================================================

  const isDuplicate = () => {

    return data.some((item) => {

      const itemCabang =
        item.Cabang ||
        item.cabang ||
        "";


      return (

        Number(item.id) !==
          Number(formData.id)

        &&

        String(itemCabang)
          .toLowerCase() ===
          String(formData.cabang)
            .toLowerCase()

        &&

        String(item.kategori || "")
          .toLowerCase() ===
          String(formData.kategori || "")
            .toLowerCase()

        &&

        String(item.periode || "") ===
          String(formData.periode || "")
      );
    });
  };


  // ==========================================================
  // SUBMIT
  // ==========================================================

  const handleSubmit = async (e) => {

    e.preventDefault();


    // ========================================================
    // VALIDASI KATEGORI
    // ========================================================

    if (!formData.kategori) {

      Swal.fire({
        icon: "warning",
        title: "Kategori belum dipilih",
        text:
          "Silakan pilih kategori Mutasi, HD, atau LPD.",
        confirmButtonColor: "#2563eb",
      });

      return;
    }


    // ========================================================
    // VALIDASI PERIODE
    // ========================================================

    if (!formData.periode) {

      Swal.fire({
        icon: "warning",
        title: "Periode belum dipilih",
        text:
          "Silakan pilih bulan periode.",
        confirmButtonColor: "#2563eb",
      });

      return;
    }


    // ========================================================
    // VALIDASI TANGGAL
    // ========================================================

    if (
      !formData.start_date ||
      !formData.end_date
    ) {

      Swal.fire({
        icon: "warning",
        title:
          "Tanggal periode belum lengkap",
        text:
          "Tanggal awal dan tanggal akhir harus tersedia.",
        confirmButtonColor: "#2563eb",
      });

      return;
    }


    // ========================================================
    // CEK DUPLIKAT
    // ========================================================

    /*
     * Saat edit, pengecekan ini tidak masalah
     * karena ID sendiri dikecualikan.
     */
    if (isDuplicate()) {

      Swal.fire({
        icon: "warning",
        title: "Periode sudah ada",

        text:
          `Periode ${formData.periode} ` +
          `untuk kategori ${formData.kategori} ` +
          "sudah tersedia.",

        confirmButtonColor: "#2563eb",
      });

      return;
    }


    try {

      setSaving(true);


      // ======================================================
      // LOADING
      // ======================================================

      Swal.fire({
        title:
          modalMode === "add"
            ? "Menambahkan periode..."
            : "Memperbarui periode...",

        text: "Mohon tunggu.",

        allowOutsideClick: false,
        allowEscapeKey: false,

        showConfirmButton: false,

        didOpen: () => {
          Swal.showLoading();
        },
      });


      // ======================================================
      // PAYLOAD
      // ======================================================

      const payload = {

        id: formData.id
          ? Number(formData.id)
          : null,

        cabang:
          formData.cabang,

        kategori:
          formData.kategori,

        /*
         * STRING
         *
         * Contoh:
         * Aug-2026
         */
        periode:
          formData.periode,

        start_date:
          formData.start_date,

        end_date:
          formData.end_date,

        status:
          formData.status,
      };


      // ======================================================
      // URL
      // ======================================================

      const url =
        modalMode === "add"
          ? "/api/periode"
          : `/api/periode/${formData.id}`;


      const method =
        modalMode === "add"
          ? "POST"
          : "PUT";


      // ======================================================
      // REQUEST
      // ======================================================

      const response =
        await fetch(
          url,
          {
            method,

            headers: {
              "Content-Type":
                "application/json",

              Accept:
                "application/json",
            },

            body:
              JSON.stringify(payload),
          }
        );


      const result =
        await response.json();


      if (!response.ok) {

        throw new Error(
          result.message ||
          "Gagal menyimpan periode."
        );
      }


      // ======================================================
      // SUCCESS
      // ======================================================

      Swal.close();


      await Swal.fire({
        icon: "success",

        title: "Berhasil",

        text:
          modalMode === "add"
            ? "Periode berhasil ditambahkan."
            : "Periode berhasil diperbarui.",

        timer: 1500,

        showConfirmButton: false,
      });


      // ======================================================
      // CLOSE
      // ======================================================

      setShowModal(false);


      setFormData({
        ...emptyForm,
        cabang: cabang || "",
      });


      // ======================================================
      // REFRESH DATA
      // ======================================================

      await fetchData(cabang);


    } catch (error) {

      console.error(
        "SAVE PERIODE ERROR:",
        error
      );


      Swal.close();


      Swal.fire({
        icon: "error",

        title: "Gagal",

        text:
          error.message ||
          "Gagal menyimpan periode.",

        confirmButtonColor:
          "#2563eb",
      });

    } finally {

      setSaving(false);
    }
  };


  // ==========================================================
  // FILTER DATA
  // ==========================================================

  const filteredData = useMemo(() => {

    const search =
      globalFilter
        .toLowerCase()
        .trim();


    return data.filter((item) => {

      const itemCabang =
        item.Cabang ||
        item.cabang ||
        "";


      const matchSearch =

        String(itemCabang)
          .toLowerCase()
          .includes(search)

        ||

        String(item.kategori || "")
          .toLowerCase()
          .includes(search)

        ||

        String(item.periode || "")
          .toLowerCase()
          .includes(search)

        ||

        String(item.start_date || "")
          .toLowerCase()
          .includes(search)

        ||

        String(item.end_date || "")
          .toLowerCase()
          .includes(search);


      const matchKategori =
        !filterKategori ||

        String(item.kategori || "")
          .toLowerCase() ===
          filterKategori.toLowerCase();


      const matchStatus =
        !filterStatus ||

        String(item.status || "")
          .toLowerCase() ===
          filterStatus.toLowerCase();


      return (
        matchSearch &&
        matchKategori &&
        matchStatus
      );
    });

  }, [
    data,
    globalFilter,
    filterKategori,
    filterStatus,
  ]);


  // ==========================================================
  // STATISTICS
  // ==========================================================

  const totalPeriode =
    data.length;


  const totalAktif =
    data.filter(
      (item) =>
        String(item.status || "")
          .toLowerCase() ===
        "aktif"
    ).length;


  const totalClose =
    data.filter(
      (item) =>
        String(item.status || "")
          .toLowerCase() ===
        "close"
    ).length;


  const totalMutasi =
    data.filter(
      (item) =>
        String(item.kategori || "")
          .toLowerCase() ===
        "mutasi"
    ).length;


  const totalHD =
    data.filter(
      (item) =>
        String(item.kategori || "")
          .toLowerCase() ===
        "hd"
    ).length;


  const totalLPD =
    data.filter(
      (item) =>
        String(item.kategori || "")
          .toLowerCase() ===
        "lpd"
    ).length;


  // ==========================================================
  // TABLE COLUMNS
  // ==========================================================

  const columns = [

    // ========================================================
    // CABANG
    // ========================================================

    {
      accessorKey: "Cabang",

      header: "Cabang",

      cell: ({ row }) =>
        row.original.Cabang ||
        row.original.cabang ||
        "-",
    },


    // ========================================================
    // KATEGORI
    // ========================================================

    {
      accessorKey: "kategori",

      header: "Kategori",

      cell: ({ row }) => {

        const kategori =
          row.original.kategori;


        let className =
          "bg-gray-100 text-gray-600 border-gray-200";


        if (kategori === "Mutasi") {

          className =
            "bg-blue-50 text-blue-700 border-blue-200";
        }


        if (kategori === "HD") {

          className =
            "bg-purple-50 text-purple-700 border-purple-200";
        }


        if (kategori === "LPD") {

          className =
            "bg-emerald-50 text-emerald-700 border-emerald-200";
        }


        return (

          <span
            className={`
              inline-flex
              items-center
              px-2.5
              py-1
              rounded-full
              border
              text-xs
              font-semibold
              ${className}
            `}
          >
            {kategori || "-"}
          </span>

        );
      },
    },


    // ========================================================
    // PERIODE
    // ========================================================

    {
      accessorKey: "periode",

      header: "Periode",

      cell: ({ row }) => (

        <div
          className="
            flex
            items-center
            gap-2
          "
        >

          <FaCalendarAlt
            className="
              text-gray-400
              text-xs
            "
          />

          <span
            className="
              font-medium
              text-gray-700
            "
          >

            {/*
             * LANGSUNG TAMPILKAN STRING
             *
             * Database:
             * Jul-2026
             *
             * Tidak ada formatPeriode()
             */}

            {row.original.periode || "-"}

          </span>

        </div>
      ),
    },


    // ========================================================
    // TANGGAL AWAL
    // ========================================================

    {
      accessorKey: "start_date",
      header: "Tgl Awal",

      cell: ({ row }) =>
        formatDate(row.original.start_date),
    },


    // ========================================================
    // TANGGAL AKHIR
    // ========================================================

    {
      accessorKey: "end_date",
      header: "Tgl Akhir",

      cell: ({ row }) =>
        formatDate(row.original.end_date),
    },

    // ========================================================
    // STATUS
    // ========================================================

    {
      accessorKey: "status",

      header: "Status",

      cell: ({ row }) => {

        const status =
          row.original.status;


        const aktif =
          String(status || "")
            .toLowerCase() ===
          "aktif";


        return (

          <span
            className={`
              inline-flex
              items-center
              gap-1.5
              px-2.5
              py-1
              rounded-full
              text-xs
              font-semibold

              ${
                aktif

                  ? `
                    bg-emerald-50
                    text-emerald-700
                    border
                    border-emerald-200
                  `

                  : `
                    bg-gray-100
                    text-gray-600
                    border
                    border-gray-200
                  `
              }
            `}
          >

            {aktif ? (
              <FaCheckCircle />
            ) : (
              <FaLock />
            )}

            {aktif
              ? "Aktif"
              : "Close"}

          </span>

        );
      },
    },


    // ========================================================
    // AKSI
    // ========================================================

    {
      header: "Opsi",

      id: "actions",

      cell: ({ row }) => (

        <div
          className="
            flex
            justify-center
          "
        >

          <button
            type="button"

            onClick={() =>
              handleEdit(
                row.original
              )
            }

            className="
              inline-flex
              items-center
              justify-center
              gap-1.5

              px-3
              py-1.5

              rounded-lg

              bg-amber-50
              text-amber-600

              border
              border-amber-200

              hover:bg-amber-100

              transition

              text-xs
              font-medium
            "
          >

            <FaEdit />

            Edit

          </button>

        </div>
      ),
    },
  ];


  // ==========================================================
  // RENDER
  // ==========================================================

  return (

    <main
      className="
        flex-1
        px-3
        sm:px-4
        py-2
        z-10
        text-gray-800
      "
    >

      <div
        className="
          w-full
          h-[calc(100vh-50px)]

          bg-white/70
          backdrop-blur-sm

          rounded-xl
          shadow-lg

          overflow-hidden
        "
      >

        {/* ====================================================
            HEADER
        ===================================================== */}

        <div
          className="
            px-4
            sm:px-6
            py-4

            border-b
            border-gray-200

            bg-white/80
          "
        >

          <div
            className="
              flex
              flex-col

              lg:flex-row
              lg:items-center
              lg:justify-between

              gap-4
            "
          >

            {/* TITLE */}

            <div
              className="
                flex
                items-center
                gap-3
              "
            >

              <div
                className="
                  w-11
                  h-11

                  rounded-xl

                  bg-blue-100
                  text-blue-600

                  flex
                  items-center
                  justify-center
                "
              >

                <FaCalendarAlt
                  className="text-xl"
                />

              </div>


              <div>

                <h1
                  className="
                    text-lg
                    sm:text-xl

                    font-bold
                    text-gray-800
                  "
                >
                  Manajemen Periode
                </h1>


                <p
                  className="
                    text-xs
                    sm:text-sm
                    text-gray-500
                  "
                >
                  Pengaturan periode berdasarkan
                  cabang dan kategori
                </p>

              </div>

            </div>


            {/* CABANG + BUTTON */}

            <div
              className="
                flex
                flex-col
                sm:flex-row

                items-stretch
                sm:items-center

                gap-2
              "
            >

              <div
                className="
                  px-3
                  py-2

                  rounded-lg

                  bg-gray-100
                  border
                  border-gray-200

                  text-sm
                "
              >

                <span
                  className="
                    text-gray-400
                    mr-1
                  "
                >
                  Cabang:
                </span>


                <span
                  className="
                    font-semibold
                    text-gray-700
                  "
                >
                  {cabang || "-"}
                </span>

              </div>


              {/* REFRESH */}

              <button
                type="button"

                onClick={() =>
                  fetchData(cabang)
                }

                disabled={
                  loading ||
                  !cabang
                }

                className="
                  flex
                  items-center
                  justify-center

                  gap-2

                  px-3
                  py-2

                  rounded-lg

                  border
                  border-gray-300

                  bg-white
                  text-gray-600

                  hover:bg-gray-50

                  transition

                  disabled:opacity-50
                "
              >

                <FaSyncAlt
                  className={
                    loading
                      ? "animate-spin"
                      : ""
                  }
                />

                <span>
                  Refresh
                </span>

              </button>


              {/* ADD */}

              <button
                type="button"

                onClick={handleAdd}

                disabled={!cabang}

                className="
                  flex
                  items-center
                  justify-center

                  gap-2

                  px-4
                  py-2

                  rounded-lg

                  bg-blue-600
                  hover:bg-blue-700

                  text-white

                  font-medium

                  shadow-sm

                  transition

                  disabled:opacity-50
                "
              >

                <FaPlus />

                Tambah Periode

              </button>

            </div>

          </div>

        </div>


        {/* ====================================================
            STATISTICS
        ===================================================== */}

        <div
          className="
            p-4
            sm:p-5

            bg-gray-50/60
          "
        >

          <div
            className="
              grid

              grid-cols-2
              sm:grid-cols-3
              lg:grid-cols-6

              gap-3
            "
          >

            {/* TOTAL */}

            <div
              className="
                bg-white
                rounded-xl

                border
                border-gray-200

                p-3

                shadow-sm
              "
            >

              <div
                className="
                  flex
                  items-center
                  justify-between
                "
              >

                <div>

                  <p
                    className="
                      text-xs
                      text-gray-400
                    "
                  >
                    Total
                  </p>


                  <p
                    className="
                      text-xl
                      font-bold
                      text-gray-800

                      mt-1
                    "
                  >
                    {totalPeriode}
                  </p>

                </div>


                <FaDatabase
                  className="text-blue-500"
                />

              </div>

            </div>


            {/* AKTIF */}

            <div
              className="
                bg-white
                rounded-xl

                border
                border-gray-200

                p-3

                shadow-sm
              "
            >

              <div
                className="
                  flex
                  items-center
                  justify-between
                "
              >

                <div>

                  <p
                    className="
                      text-xs
                      text-gray-400
                    "
                  >
                    Aktif
                  </p>


                  <p
                    className="
                      text-xl
                      font-bold
                      text-emerald-600

                      mt-1
                    "
                  >
                    {totalAktif}
                  </p>

                </div>


                <FaCheckCircle
                  className="text-emerald-500"
                />

              </div>

            </div>


            {/* CLOSE */}

            <div
              className="
                bg-white
                rounded-xl

                border
                border-gray-200

                p-3

                shadow-sm
              "
            >

              <div
                className="
                  flex
                  items-center
                  justify-between
                "
              >

                <div>

                  <p
                    className="
                      text-xs
                      text-gray-400
                    "
                  >
                    Close
                  </p>


                  <p
                    className="
                      text-xl
                      font-bold
                      text-gray-600

                      mt-1
                    "
                  >
                    {totalClose}
                  </p>

                </div>


                <FaLock
                  className="text-gray-400"
                />

              </div>

            </div>


            {/* MUTASI */}

            <div
              className="
                bg-white
                rounded-xl

                border
                border-blue-100

                p-3

                shadow-sm
              "
            >

              <div
                className="
                  flex
                  items-center
                  justify-between
                "
              >

                <div>

                  <p
                    className="
                      text-xs
                      text-gray-400
                    "
                  >
                    Mutasi
                  </p>


                  <p
                    className="
                      text-xl
                      font-bold
                      text-blue-600

                      mt-1
                    "
                  >
                    {totalMutasi}
                  </p>

                </div>


                <FaChartBar
                  className="text-blue-500"
                />

              </div>

            </div>


            {/* HD */}

            <div
              className="
                bg-white
                rounded-xl

                border
                border-purple-100

                p-3

                shadow-sm
              "
            >

              <div
                className="
                  flex
                  items-center
                  justify-between
                "
              >

                <div>

                  <p
                    className="
                      text-xs
                      text-gray-400
                    "
                  >
                    HD
                  </p>


                  <p
                    className="
                      text-xl
                      font-bold
                      text-purple-600

                      mt-1
                    "
                  >
                    {totalHD}
                  </p>

                </div>


                <FaCalendarAlt
                  className="text-purple-500"
                />

              </div>

            </div>


            {/* LPD */}

            <div
              className="
                bg-white
                rounded-xl

                border
                border-emerald-100

                p-3

                shadow-sm
              "
            >

              <div
                className="
                  flex
                  items-center
                  justify-between
                "
              >

                <div>

                  <p
                    className="
                      text-xs
                      text-gray-400
                    "
                  >
                    LPD
                  </p>


                  <p
                    className="
                      text-xl
                      font-bold
                      text-emerald-600

                      mt-1
                    "
                  >
                    {totalLPD}
                  </p>

                </div>


                <FaCalendarAlt
                  className="text-emerald-500"
                />

              </div>

            </div>

          </div>

        </div>


        {/* ====================================================
            FILTER
        ===================================================== */}

        <div
          className="
            px-4
            sm:px-6
            py-3

            border-y
            border-gray-200

            bg-white
          "
        >

          <div
            className="
              flex
              flex-col

              lg:flex-row

              gap-3

              lg:items-center
              lg:justify-between
            "
          >

            <div
              className="
                flex
                items-center
                gap-2

                text-sm
                font-medium
                text-gray-600
              "
            >

              <FaFilter
                className="text-blue-500"
              />

              Filter Periode

            </div>


            <div
              className="
                flex
                flex-col
                sm:flex-row

                gap-2

                w-full
                lg:w-auto
              "
            >

              {/* KATEGORI */}

              <select
                value={filterKategori}

                onChange={(e) =>
                  setFilterKategori(
                    e.target.value
                  )
                }

                className="
                  px-3
                  py-2

                  border
                  border-gray-300

                  rounded-lg

                  bg-white

                  text-sm

                  focus:outline-none
                  focus:ring-2
                  focus:ring-blue-500/30
                "
              >

                <option value="">
                  Semua Kategori
                </option>

                <option value="Mutasi">
                  Mutasi
                </option>

                <option value="HD">
                  HD
                </option>

                <option value="LPD">
                  LPD
                </option>

              </select>


              {/* STATUS */}

              <select
                value={filterStatus}

                onChange={(e) =>
                  setFilterStatus(
                    e.target.value
                  )
                }

                className="
                  px-3
                  py-2

                  border
                  border-gray-300

                  rounded-lg

                  bg-white

                  text-sm

                  focus:outline-none
                  focus:ring-2
                  focus:ring-blue-500/30
                "
              >

                <option value="">
                  Semua Status
                </option>

                <option value="Aktif">
                  Aktif
                </option>

                <option value="Close">
                  Close
                </option>

              </select>


              {/* RESET */}

              {(filterKategori ||
                filterStatus) && (

                <button
                  type="button"

                  onClick={() => {

                    setFilterKategori("");
                    setFilterStatus("");

                  }}

                  className="
                    px-3
                    py-2

                    rounded-lg

                    bg-gray-100
                    text-gray-600

                    hover:bg-gray-200

                    text-sm
                  "
                >
                  Reset
                </button>

              )}

            </div>

          </div>

        </div>


        {/* ====================================================
            TABLE
        ===================================================== */}

        <div
          className="
            p-3
            sm:p-4

            overflow-hidden
          "
        >

          <div
            className="
              overflow-x-auto
            "
          >

            <ReusableTable
              data={filteredData}

              columns={columns}

              globalFilter={
                globalFilter
              }

              setGlobalFilter={
                setGlobalFilter
              }

              searchInputRef={
                searchRef
              }
            />

          </div>

        </div>

      </div>


      {/* ======================================================
          MODAL ADD / EDIT
      ======================================================= */}

      {showModal && (

        <div
          className="
            fixed
            inset-0

            z-[100]

            flex
            items-center
            justify-center

            p-4

            bg-black/40
            backdrop-blur-sm
          "

          onMouseDown={(e) => {

            if (
              e.target ===
              e.currentTarget
            ) {

              handleCloseModal();
            }
          }}
        >

          <div
            className="
              w-full
              max-w-xl

              bg-white

              rounded-2xl

              shadow-2xl

              overflow-visible
            "
          >

            {/* =================================================
                MODAL HEADER
            ================================================== */}

            <div
              className="
                px-5
                py-4

                bg-gradient-to-r
                from-blue-600
                to-blue-700

                text-white

                flex
                items-center
                justify-between

                rounded-t-2xl
              "
            >

              <div
                className="
                  flex
                  items-center
                  gap-3
                "
              >

                <div
                  className="
                    w-10
                    h-10

                    rounded-lg

                    bg-white/20

                    flex
                    items-center
                    justify-center
                  "
                >

                  <FaCalendarAlt />

                </div>


                <div>

                  <h2
                    className="
                      font-semibold
                      text-base
                    "
                  >

                    {modalMode === "add"
                      ? "Tambah Periode"
                      : "Edit Periode"}

                  </h2>


                  <p
                    className="
                      text-xs
                      text-blue-100
                    "
                  >
                    Cabang {formData.cabang}
                  </p>

                </div>

              </div>


              <button
                type="button"

                onClick={
                  handleCloseModal
                }

                disabled={saving}

                className="
                  w-8
                  h-8

                  rounded-lg

                  flex
                  items-center
                  justify-center

                  hover:bg-white/20
                "
              >

                <FaTimes />

              </button>

            </div>


            {/* =================================================
                FORM
            ================================================== */}

            <form
              onSubmit={handleSubmit}
            >

              <div
                className="
                  p-5

                  space-y-4

                  max-h-[70vh]
                  overflow-y-auto
                "
              >

                {/* =============================================
                    CABANG
                ============================================== */}

                <div>

                  <label
                    className="
                      block

                      text-sm
                      font-medium
                      text-gray-700

                      mb-1.5
                    "
                  >
                    Cabang
                  </label>


                  <input
                    type="text"

                    value={
                      formData.cabang
                    }

                    readOnly

                    className="
                      w-full

                      px-3
                      py-2.5

                      rounded-lg

                      border
                      border-gray-200

                      bg-gray-100

                      text-gray-500
                      text-sm
                    "
                  />

                </div>


                {/* =============================================
                    KATEGORI
                ============================================== */}

                <div>

                  <label
                    className="
                      block

                      text-sm
                      font-medium
                      text-gray-700

                      mb-2
                    "
                  >
                    Kategori
                  </label>


                  <div
                    className="
                      grid
                      grid-cols-3

                      gap-2
                    "
                  >

                    {kategoriOptions.map(
                      (item) => (

                        <button
                          key={
                            item.value
                          }

                          type="button"

                          /*
                           * Saat EDIT kategori
                           * sebaiknya tidak diubah.
                           *
                           * Karena proses edit
                           * lama hanya mengubah
                           * status.
                           */
                          disabled={
                            modalMode ===
                            "edit"
                          }

                          onClick={() =>
                            setFormData(
                              (prev) => ({
                                ...prev,

                                kategori:
                                  item.value,
                              })
                            )
                          }

                          className={`
                            p-3

                            rounded-xl

                            border

                            text-left

                            transition

                            ${
                              formData.kategori ===
                              item.value

                                ? item.className +
                                  " ring-2 ring-blue-400"

                                : "bg-white border-gray-200 hover:bg-gray-50"
                            }

                            ${
                              modalMode ===
                              "edit"
                                ? "opacity-70 cursor-not-allowed"
                                : ""
                            }
                          `}
                        >

                          <div
                            className="
                              font-semibold
                              text-sm
                            "
                          >
                            {item.label}
                          </div>


                          <div
                            className="
                              text-[10px]

                              mt-1

                              opacity-70
                            "
                          >
                            {
                              item.description
                            }
                          </div>

                        </button>

                      )
                    )}

                  </div>

                </div>


                {/* =============================================
                    PERIODE
                ============================================== */}

                <div>

                  <label
                    className="
                      block

                      text-sm
                      font-medium
                      text-gray-700

                      mb-1.5
                    "
                  >
                    Periode
                  </label>


                  <DatePicker

                    /*
                     * PERIODE DATABASE
                     * TETAP STRING.
                     *
                     * DatePicker hanya digunakan
                     * sebagai UI pemilih bulan.
                     */

                    selected={

                      formData.periode
                        ? (() => {

                            /*
                             * Contoh:
                             *
                             * Aug-2026
                             *
                             * menjadi Date
                             * hanya untuk UI.
                             */

                            const parts =
                              formData.periode.split(
                                "-"
                              );

                            if (
                              parts.length !==
                              2
                            ) {
                              return null;
                            }

                            const monthText =
                              parts[0];

                            const year =
                              Number(
                                parts[1]
                              );

                            const months = [
                              "Jan",
                              "Feb",
                              "Mar",
                              "Apr",
                              "May",
                              "Jun",
                              "Jul",
                              "Aug",
                              "Sep",
                              "Oct",
                              "Nov",
                              "Dec",
                            ];

                            const month =
                              months.indexOf(
                                monthText
                              );

                            if (
                              month < 0 ||
                              !year
                            ) {
                              return null;
                            }

                            return new Date(
                              year,
                              month,
                              1
                            );

                          })()
                        : null
                    }


                    onChange={
                      handlePeriodeChange
                    }


                    /*
                     * Pilih bulan + tahun
                     */
                    showMonthYearPicker

                    showFullMonthYearPicker

                    showFourColumnMonthYearPicker


                    /*
                     * Tampilan UI
                     */
                    dateFormat="MMMM yyyy"

                    locale="id"

                    placeholderText={
                      "Pilih bulan dan tahun"
                    }


                    disabled={
                      modalMode ===
                      "edit"
                    }


                    className={`
                      w-full

                      px-3
                      py-2.5

                      border

                      rounded-lg

                      text-sm

                      focus:outline-none

                      focus:ring-2
                      focus:ring-blue-500/30

                      focus:border-blue-500

                      ${
                        modalMode ===
                        "edit"

                          ? `
                            bg-gray-100
                            border-gray-200
                            text-gray-500
                            cursor-not-allowed
                          `

                          : `
                            bg-white
                            border-gray-300
                          `
                      }
                    `}


                    wrapperClassName="w-full"
                  />

                </div>


                {/* =============================================
                    TANGGAL
                ============================================== */}

                <div
                  className="
                    grid

                    grid-cols-1
                    sm:grid-cols-2

                    gap-3
                  "
                >

                  {/* =========================================
                      TANGGAL AWAL
                  ========================================== */}

                  <div>

                    <label
                      className="
                        block

                        text-sm
                        font-medium
                        text-gray-700

                        mb-1.5
                      "
                    >
                      Tanggal Awal
                    </label>


                    <DatePicker

                      selected={
                        stringToDate(
                          formData.start_date
                        )
                      }


                      onChange={(date) => {

                        /*
                         * Saat ADD:
                         * tanggal bisa mengikuti
                         * pilihan user.
                         *
                         * Saat EDIT:
                         * tidak diubah.
                         */

                        if (
                          modalMode ===
                          "edit"
                        ) {
                          return;
                        }


                        setFormData(
                          (prev) => ({
                            ...prev,

                            start_date:
                              dateToString(
                                date
                              ),
                          })
                        );

                      }}


                      dateFormat="dd/MM/yyyy"

                      locale="id"

                      placeholderText={
                        "Pilih tanggal awal"
                      }


                      disabled={
                        modalMode ===
                        "edit"
                      }


                      className={`
                        w-full

                        px-3
                        py-2.5

                        border

                        rounded-lg

                        text-sm

                        focus:outline-none

                        focus:ring-2
                        focus:ring-blue-500/30

                        focus:border-blue-500

                        ${
                          modalMode ===
                          "edit"

                            ? `
                              bg-gray-100
                              border-gray-200
                              text-gray-500
                              cursor-not-allowed
                            `

                            : `
                              bg-white
                              border-gray-300
                            `
                        }
                      `}


                      wrapperClassName="w-full"
                    />

                  </div>


                  {/* =========================================
                      TANGGAL AKHIR
                  ========================================== */}

                  <div>

                    <label
                      className="
                        block

                        text-sm
                        font-medium
                        text-gray-700

                        mb-1.5
                      "
                    >
                      Tanggal Akhir
                    </label>


                    <DatePicker

                      selected={
                        stringToDate(
                          formData.end_date
                        )
                      }


                      onChange={(date) => {

                        if (
                          modalMode ===
                          "edit"
                        ) {
                          return;
                        }


                        setFormData(
                          (prev) => ({
                            ...prev,

                            end_date:
                              dateToString(
                                date
                              ),
                          })
                        );

                      }}


                      dateFormat="dd/MM/yyyy"

                      locale="id"

                      placeholderText={
                        "Pilih tanggal akhir"
                      }


                      disabled={
                        modalMode ===
                        "edit"
                      }


                      minDate={
                        formData.start_date
                          ? stringToDate(
                              formData.start_date
                            )
                          : null
                      }


                      className={`
                        w-full

                        px-3
                        py-2.5

                        border

                        rounded-lg

                        text-sm

                        focus:outline-none

                        focus:ring-2
                        focus:ring-blue-500/30

                        focus:border-blue-500

                        ${
                          modalMode ===
                          "edit"

                            ? `
                              bg-gray-100
                              border-gray-200
                              text-gray-500
                              cursor-not-allowed
                            `

                            : `
                              bg-white
                              border-gray-300
                            `
                        }
                      `}


                      wrapperClassName="w-full"
                    />

                  </div>

                </div>


                {/* =============================================
                    INFO PERIODE
                ============================================== */}

                {formData.periode && (

                  <div
                    className="
                      flex
                      items-start
                      gap-2

                      px-3
                      py-2.5

                      rounded-lg

                      bg-blue-50

                      border
                      border-blue-100

                      text-xs
                      text-blue-700
                    "
                  >

                    <FaCalendarAlt
                      className="
                        mt-0.5
                        flex-shrink-0
                      "
                    />

                    <div>

                      <div
                        className="
                          font-semibold
                        "
                      >
                        Periode:{" "}
                        {formData.periode}
                      </div>


                      <div
                        className="
                          mt-0.5
                          text-blue-600
                        "
                      >

                        {formData.start_date}
                        {" sampai "}
                        {formData.end_date}

                      </div>

                    </div>

                  </div>

                )}


                {/* =============================================
                    STATUS
                ============================================== */}

                <div>

                  <label
                    className="
                      block

                      text-sm
                      font-medium
                      text-gray-700

                      mb-2
                    "
                  >
                    Status Periode
                  </label>


                  <div
                    className="
                      grid
                      grid-cols-2

                      gap-2
                    "
                  >

                    {/* AKTIF */}

                    <button
                      type="button"

                      onClick={() =>
                        setFormData(
                          (prev) => ({
                            ...prev,

                            status:
                              "Aktif",
                          })
                        )
                      }

                      className={`
                        px-3
                        py-2.5

                        rounded-lg

                        border

                        flex
                        items-center
                        justify-center

                        gap-2

                        text-sm
                        font-medium

                        transition

                        ${
                          formData.status ===
                          "Aktif"

                            ? `
                              bg-emerald-50
                              text-emerald-700
                              border-emerald-300
                              ring-2
                              ring-emerald-200
                            `

                            : `
                              bg-white
                              text-gray-500
                              border-gray-300
                            `
                        }
                      `}
                    >

                      <FaCheckCircle />

                      Aktif

                    </button>


                    {/* CLOSE */}

                    <button
                      type="button"

                      onClick={() =>
                        setFormData(
                          (prev) => ({
                            ...prev,

                            status:
                              "Close",
                          })
                        )
                      }

                      className={`
                        px-3
                        py-2.5

                        rounded-lg

                        border

                        flex
                        items-center
                        justify-center

                        gap-2

                        text-sm
                        font-medium

                        transition

                        ${
                          formData.status ===
                          "Close"

                            ? `
                              bg-gray-100
                              text-gray-700
                              border-gray-400
                              ring-2
                              ring-gray-200
                            `

                            : `
                              bg-white
                              text-gray-500
                              border-gray-300
                            `
                        }
                      `}
                    >

                      <FaLock />

                      Close

                    </button>

                  </div>

                </div>

              </div>


              {/* =================================================
                  FOOTER
              ================================================== */}

              <div
                className="
                  px-5
                  py-4

                  border-t
                  border-gray-200

                  bg-gray-50

                  flex
                  flex-col-reverse

                  sm:flex-row
                  sm:justify-end

                  gap-2

                  rounded-b-2xl
                "
              >

                {/* BATAL */}

                <button
                  type="button"

                  onClick={
                    handleCloseModal
                  }

                  disabled={saving}

                  className="
                    px-4
                    py-2.5

                    rounded-lg

                    border
                    border-gray-300

                    bg-white
                    text-gray-600

                    text-sm
                    font-medium

                    hover:bg-gray-100

                    disabled:opacity-50
                  "
                >
                  Batal
                </button>


                {/* SIMPAN */}

                <button
                  type="submit"

                  disabled={saving}

                  className="
                    px-4
                    py-2.5

                    rounded-lg

                    bg-blue-600
                    hover:bg-blue-700

                    text-white

                    text-sm
                    font-medium

                    flex
                    items-center
                    justify-center

                    gap-2

                    disabled:opacity-50
                  "
                >

                  {saving ? (

                    <FaSyncAlt
                      className="animate-spin"
                    />

                  ) : (

                    <FaSave />

                  )}


                  {modalMode === "add"
                    ? "Simpan Periode"
                    : "Simpan Perubahan"}

                </button>

              </div>

            </form>

          </div>

        </div>

      )}

    </main>
  );
}