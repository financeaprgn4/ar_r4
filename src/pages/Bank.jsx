import React, { useEffect, useMemo, useState } from "react";
import axios from "axios";
import Swal from "sweetalert2";

import {
  FaPlus,
  FaTimes,
  FaSave,
} from "react-icons/fa";

import {
  HiPencil,
  HiTrash,
} from "react-icons/hi";

import { useCabang } from "../contexts/CabangContext";
import ReusableTableNew from "../components/ReusableTableNew";


export default function Bank() {

  const { cabang } = useCabang();

  // =========================================================
  // STATE DATA
  // =========================================================

  const [data, setData] = useState([]);

  const [loading, setLoading] = useState(false);

  const [saving, setSaving] = useState(false);

  const [showModal, setShowModal] = useState(false);

  const [editMode, setEditMode] = useState(false);

  const [selectedId, setSelectedId] = useState(null);

  // =========================================================
  // FILTER
  // =========================================================

  const [globalFilter, setGlobalFilter] = useState("");

  const [categoryFilter, setCategoryFilter] = useState("Reguler");

  // ReusableTableNew membutuhkan periodFilter
  // walaupun halaman Bank tidak menggunakan periode.
  const [periodFilter, setPeriodFilter] = useState("");

  // =========================================================
  // FORM
  // =========================================================

  const [form, setForm] = useState({
    cabang: "",
    bank: "",
    jns_bank: "REG",
    no_rek: "",
    akun: "",
    ce: "",
    site: "",
    bank_name: "",
    acc_num: "",
    receipt_method: "",
  });


  // =========================================================
  // LOAD DATA BANK
  // =========================================================

  const fetchBank = async () => {
    if (!cabang) {
        setData([]);
        return;
    }

    try {
        setLoading(true);

        const response = await axios.get(
            "http://127.0.0.1:8000/api/bank",
            {
                params: {
                    cabang: cabang,
                },
            }
        );

        console.log("BANK RESPONSE:", response.data);

        if (response.data?.success) {
            setData(response.data.data || []);
        } else {
            setData([]);

            Swal.fire({
                icon: "warning",
                title: "Data Bank",
                text:
                    response.data?.message ||
                    "Data bank tidak tersedia.",
            });
        }

    } catch (error) {

        console.error("BANK API ERROR:", error);

        console.error(
            "STATUS:",
            error.response?.status
        );

        console.error(
            "DATA:",
            error.response?.data
        );

        Swal.fire({
            icon: "error",
            title: "Gagal Memuat Data Bank",
            text:
                error.response?.data?.message ||
                error.message ||
                "Gagal menghubungi API bank.",
        });

        setData([]);

    } finally {
        setLoading(false);
    }
};


  // =========================================================
  // LOAD SAAT CABANG BERUBAH
  // =========================================================

  useEffect(() => {

    fetchBank();

  }, [cabang]);


  // =========================================================
  // RESET FORM
  // =========================================================

  const resetForm = () => {

    setForm({
      cabang: cabang || "",
      bank: "",
      jns_bank: "REG",
      no_rek: "",
      akun: "",
      ce: "",
      site: "",
      bank_name: "",
      acc_num: "",
      receipt_method: "",
    });

    setSelectedId(null);

    setEditMode(false);
  };


  // =========================================================
  // TAMBAH DATA
  // =========================================================

  const handleAdd = () => {

    resetForm();

    setShowModal(true);
  };


  // =========================================================
  // EDIT DATA
  // =========================================================

  const handleEdit = (row) => {

    setEditMode(true);

    setSelectedId(row.id);

    setForm({
      cabang: row.cabang ?? cabang ?? "",
      bank: row.bank ?? "",
      jns_bank: row.jns_bank ?? "REG",
      no_rek: row.no_rek ?? "",
      akun: row.akun ?? "",
      ce: row.ce ?? "",
      site: row.site ?? "",
      bank_name: row.bank_name ?? "",
      acc_num: row.acc_num ?? "",
      receipt_method: row.receipt_method ?? "",
    });

    setShowModal(true);
  };


  // =========================================================
  // TUTUP MODAL
  // =========================================================

  const handleCloseModal = () => {

    if (saving) {
      return;
    }

    setShowModal(false);

    resetForm();
  };


  // =========================================================
  // HANDLE FORM CHANGE
  // =========================================================

  const handleChange = (e) => {

    const { name, value } = e.target;

    setForm((prev) => ({
      ...prev,
      [name]: value,
    }));
  };


  // =========================================================
  // VALIDASI FORM
  // =========================================================

  const validateForm = () => {

    if (!form.bank.trim()) {

      Swal.fire({
        icon: "warning",
        title: "Data belum lengkap",
        text: "Bank wajib diisi.",
      });

      return false;
    }


    if (!form.jns_bank.trim()) {

      Swal.fire({
        icon: "warning",
        title: "Data belum lengkap",
        text: "Jenis bank wajib diisi.",
      });

      return false;
    }


    if (!form.no_rek.trim()) {

      Swal.fire({
        icon: "warning",
        title: "Data belum lengkap",
        text: "Nomor rekening wajib diisi.",
      });

      return false;
    }


    if (!form.akun.trim()) {

      Swal.fire({
        icon: "warning",
        title: "Data belum lengkap",
        text: "Akun wajib diisi.",
      });

      return false;
    }


    return true;
  };


  // =========================================================
  // SAVE / UPDATE
  // =========================================================

  const handleSubmit = async (e) => {

    e.preventDefault();

    if (!validateForm()) {
      return;
    }

    try {

      setSaving(true);

      const payload = {

        cabang: cabang,

        bank: form.bank.trim(),

        jns_bank: form.jns_bank.trim(),

        no_rek: form.no_rek.trim(),

        akun: form.akun.trim(),

        ce: form.ce.trim(),

        site: form.site.trim(),

        bank_name: form.bank_name.trim(),

        acc_num: form.acc_num.trim(),

        receipt_method: form.receipt_method.trim(),

      };


      let response;


      // =====================================================
      // UPDATE
      // =====================================================

      if (editMode) {

        response = await axios.put(
          `/api/bank/${selectedId}`,
          payload
        );

      }

      // =====================================================
      // INSERT
      // =====================================================

      else {

        response = await axios.post(
          "/api/bank",
          payload
        );

      }


      if (response.data?.success) {

        await Swal.fire({
          icon: "success",

          title: editMode
            ? "Berhasil Diubah"
            : "Berhasil Ditambahkan",

          text:
            response.data.message ||
            (
              editMode
                ? "Data rekening berhasil diubah."
                : "Data rekening berhasil ditambahkan."
            ),

          timer: 1500,

          showConfirmButton: false,
        });


        setShowModal(false);

        resetForm();

        await fetchBank();

      } else {

        throw new Error(
          response.data?.message ||
          "Proses penyimpanan gagal."
        );

      }

    } catch (error) {

      console.error(
        "Gagal menyimpan data bank:",
        error
      );

      Swal.fire({
        icon: "error",

        title: "Gagal",

        text:
          error.response?.data?.message ||
          error.message ||
          "Data rekening gagal disimpan.",
      });

    } finally {

      setSaving(false);

    }
  };


  // =========================================================
  // DELETE
  // =========================================================

  const handleDelete = async (row) => {

    const result = await Swal.fire({

      icon: "warning",

      title: "Hapus Rekening?",

      html: `
        <div style="text-align:left">

          <p>Data berikut akan dihapus:</p>

          <br>

          <b>Cabang:</b>
          ${row.cabang || "-"}
          <br>

          <b>Bank:</b>
          ${row.bank || "-"}
          <br>

          <b>Nomor Rekening:</b>
          ${row.no_rek || "-"}
          <br>

          <b>Akun:</b>
          ${row.akun || "-"}
          <br>

          <b>Jenis:</b>
          ${row.jns_bank || "-"}
          <br>

          <b>Site:</b>
          ${row.site || "-"}

        </div>
      `,

      showCancelButton: true,

      confirmButtonText: "Ya, Hapus",

      cancelButtonText: "Batal",

      confirmButtonColor: "#dc2626",

      cancelButtonColor: "#6b7280",

    });


    if (!result.isConfirmed) {
      return;
    }


    try {

      setLoading(true);

      const response = await axios.delete(
        `/api/bank/${row.id}`
      );


      if (response.data?.success) {

        await Swal.fire({

          icon: "success",

          title: "Berhasil",

          text:
            response.data.message ||
            "Data rekening berhasil dihapus.",

          timer: 1500,

          showConfirmButton: false,

        });


        await fetchBank();

      } else {

        throw new Error(
          response.data?.message ||
          "Data gagal dihapus."
        );

      }

    } catch (error) {

      console.error(
        "Gagal menghapus data bank:",
        error
      );

      Swal.fire({

        icon: "error",

        title: "Gagal",

        text:
          error.response?.data?.message ||
          error.message ||
          "Data rekening gagal dihapus.",

      });

    } finally {

      setLoading(false);

    }
  };


  // =========================================================
  // TABLE COLUMNS
  // =========================================================

  const columns = useMemo(() => [

    // -------------------------------------------------------
    // CABANG
    // -------------------------------------------------------

    {
      accessorKey: "cabang",

      header: "Cabang",

      meta: {
        className: "text-center whitespace-nowrap",
      },

      cell: ({ getValue }) =>
        getValue() || "-",
    },


    // -------------------------------------------------------
    // BANK
    // -------------------------------------------------------

    {
      accessorKey: "bank",

      header: "Bank",

      meta: {
        className: "text-left whitespace-nowrap",
      },

      cell: ({ getValue }) =>
        getValue() || "-",
    },


    // -------------------------------------------------------
    // JENIS BANK
    // -------------------------------------------------------

    {
      accessorKey: "jns_bank",

      header: "Jenis Bank",

      meta: {
        className: "text-center whitespace-nowrap",
      },

      cell: ({ getValue }) => {

        const value = getValue();

        const isReg =
          String(value || "").toUpperCase() === "REG";

        return (
          <span
            className={
              isReg
                ? "inline-flex px-2 py-1 rounded text-xs font-semibold bg-blue-100 text-blue-700"
                : "inline-flex px-2 py-1 rounded text-xs font-semibold bg-purple-100 text-purple-700"
            }
          >
            {value || "-"}
          </span>
        );
      },
    },


    // -------------------------------------------------------
    // NO REKENING
    // -------------------------------------------------------

    {
      accessorKey: "no_rek",

      header: "No. Rekening",

      meta: {
        className:
          "text-left whitespace-nowrap",
      },

      cell: ({ getValue }) => (

        <span className="font-mono">

          {getValue() || "-"}

        </span>

      ),
    },


    // -------------------------------------------------------
    // AKUN
    // -------------------------------------------------------

    {
      accessorKey: "akun",

      header: "Akun",

      meta: {
        className:
          "text-center whitespace-nowrap",
      },

      cell: ({ getValue }) =>
        getValue() || "-",
    },


    // -------------------------------------------------------
    // CE
    // -------------------------------------------------------

    {
      accessorKey: "ce",

      header: "CE",

      meta: {
        className:
          "text-center whitespace-nowrap",
      },

      cell: ({ getValue }) =>
        getValue() || "-",
    },


    // -------------------------------------------------------
    // SITE
    // -------------------------------------------------------

    {
      accessorKey: "site",

      header: "Site",

      meta: {
        className:
          "text-center whitespace-nowrap",
      },

      cell: ({ getValue }) =>
        getValue() || "-",
    },


    // -------------------------------------------------------
    // BANK NAME
    // -------------------------------------------------------

    {
      accessorKey: "bank_name",

      header: "Bank Name",

      meta: {
        className:
          "text-left whitespace-nowrap",
      },

      cell: ({ getValue }) =>
        getValue() || "-",
    },


    // -------------------------------------------------------
    // ACC NUM
    // -------------------------------------------------------

    {
      accessorKey: "acc_num",

      header: "Account Number",

      meta: {
        className:
          "text-left whitespace-nowrap",
      },

      cell: ({ getValue }) => (

        <span className="font-mono">

          {getValue() || "-"}

        </span>

      ),
    },


    // -------------------------------------------------------
    // RECEIPT METHOD
    // -------------------------------------------------------

    {
      accessorKey: "receipt_method",

      header: "Receipt Method",

      meta: {
        className:
          "text-left whitespace-nowrap",
      },

      cell: ({ getValue }) =>
        getValue() || "-",
    },


    // -------------------------------------------------------
    // AKSI
    // -------------------------------------------------------

    {
      id: "aksi",

      header: "Aksi",

      enableSorting: false,

      meta: {
        className:
          "text-center whitespace-nowrap",
      },

      cell: ({ row }) => {

        const item = row.original;

        return (

          <div className="flex items-center justify-center gap-2">

            <button
              type="button"
              onClick={() =>
                handleEdit(item)
              }
              className="
                inline-flex
                items-center
                gap-1
                px-3
                py-1.5
                rounded
                bg-yellow-500
                hover:bg-yellow-600
                text-white
                text-xs
                font-semibold
                shadow
              "
            >

              <HiPencil />

              Edit

            </button>


            <button
              type="button"
              onClick={() =>
                handleDelete(item)
              }
              className="
                inline-flex
                items-center
                gap-1
                px-3
                py-1.5
                rounded
                bg-red-500
                hover:bg-red-600
                text-white
                text-xs
                font-semibold
                shadow
              "
            >

              <HiTrash />

              Hapus

            </button>

          </div>

        );
      },
    },

  ], []);


  // =========================================================
  // FILTER REG / FRC
  // =========================================================

  const filteredData = useMemo(() => {
    if (!categoryFilter) {
        return data;
    }

    return data.filter((item) => {
        const site = String(item.site || "")
            .trim()
            .toUpperCase();

        if (categoryFilter === "Reguler") {
            return site === "REG";
        }

        if (categoryFilter === "Franchise") {
            return site !== "REG";
        }

        return true;
    });
}, [data, categoryFilter]);


  // =========================================================
  // RENDER
  // =========================================================

  return (

    <main
      className="
        flex-1
        px-4
        py-2
        z-10
        text-white
      "
    >

      <div
        className="
          h-[calc(100vh-50px)]
          bg-white/60
          rounded-lg
          p-4
          shadow-lg
          text-gray-800
          overflow-hidden
        "
      >

        {/* ===================================================
            HEADER
        ==================================================== */}

        <div
          className="
            box-header
            text-white
            shadow-md
            flex
            items-center
            justify-center
            mx-auto
            mt-[-17px]
            mb-4
            h-[60px]
            w-1/2
            bg-blue-400
            clip-path-custom
          "
        >

          <h2 className="text-xl font-semibold">

            Pengaturan Rekening Bank Cabang{" "}

            {cabang || "-"}

          </h2>

        </div>


        {/* ===================================================
            TOOLBAR
        ==================================================== */}

        <div
          className="
            flex
            items-center
            justify-between
            mb-4
          "
        >

          <div>

            <h3
              className="
                text-lg
                font-semibold
                text-gray-700
              "
            >

              Daftar Rekening Bank

            </h3>


            <p
              className="
                text-sm
                text-gray-500
              "
            >

              Kelola data rekening bank
              cabang {cabang || "-"}

            </p>

          </div>


          <button
            type="button"
            onClick={handleAdd}
            className="
              flex
              items-center
              gap-2
              px-4
              py-2
              bg-blue-500
              hover:bg-blue-600
              text-white
              rounded-lg
              shadow-md
              font-semibold
            "
          >

            <FaPlus />

            Tambah Rekening

          </button>

        </div>


        {/* ===================================================
            LOADING
        ==================================================== */}

        {loading && (

          <div
            className="
              flex
              items-center
              justify-center
              py-2
            "
          >

            <div
              className="
                flex
                items-center
                gap-2
                text-blue-600
                text-sm
                font-semibold
              "
            >

              <div
                className="
                  w-4
                  h-4
                  border-2
                  border-blue-600
                  border-t-transparent
                  rounded-full
                  animate-spin
                "
              />

              Memuat data rekening...

            </div>

          </div>

        )}


        {/* ===================================================
            TABLE
        ==================================================== */}

        <ReusableTableNew

          data={filteredData}

          columns={columns}


          // ReusableTableNew membutuhkan ini
          periodFilter={periodFilter}

          setPeriodFilter={
            setPeriodFilter
          }

          periodOptions={[]}


          // Filter REG / FRC
          categoryFilter={
            categoryFilter
          }

          setCategoryFilter={
            setCategoryFilter
          }


          // Search
          globalFilter={
            globalFilter
          }

          setGlobalFilter={
            setGlobalFilter
          }


          // Element sebelah kiri
          leftElement={

            <div
              className="
                text-sm
                text-gray-500
                font-medium
              "
            >

              Total:{" "}

              <span
                className="
                  font-bold
                  text-gray-700
                "
              >

                {filteredData.length}

              </span>

              {" "}rekening

            </div>

          }


          onSelectionChange={() => {}}

        />

      </div>


      {/* =====================================================
          MODAL TAMBAH / EDIT
      ====================================================== */}

      {showModal && (

        <div
          className="
            fixed
            inset-0
            z-50
            flex
            items-center
            justify-center
            bg-black/50
            px-4
          "
          onMouseDown={(e) => {

            if (
              e.target === e.currentTarget
            ) {

              handleCloseModal();

            }

          }}
        >

          <div
            className="
              w-full
              max-w-2xl
              bg-white
              rounded-xl
              shadow-2xl
              overflow-hidden
            "
            onMouseDown={(e) =>
              e.stopPropagation()
            }
          >

            {/* =================================================
                MODAL HEADER
            ================================================== */}

            <div
              className="
                bg-blue-500
                text-white
                px-5
                py-4
                flex
                items-center
                justify-between
              "
            >

              <div>

                <h3
                  className="
                    text-lg
                    font-semibold
                  "
                >

                  {editMode
                    ? "Edit Rekening Bank"
                    : "Tambah Rekening Bank"}

                </h3>


                <p
                  className="
                    text-xs
                    text-blue-100
                    mt-1
                  "
                >

                  Cabang: {cabang || "-"}

                </p>

              </div>


              <button
                type="button"
                onClick={
                  handleCloseModal
                }
                disabled={saving}
                className="
                  p-2
                  rounded-full
                  hover:bg-blue-600
                  disabled:opacity-50
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
                  grid
                  grid-cols-1
                  md:grid-cols-2
                  gap-4
                  max-h-[70vh]
                  overflow-y-auto
                "
              >

                {/* ===========================================
                    CABANG
                ============================================ */}

                <div>

                  <label
                    className="
                      block
                      text-sm
                      font-semibold
                      mb-1
                    "
                  >

                    Cabang

                  </label>


                  <input
                    type="text"
                    value={cabang || ""}
                    disabled
                    className="
                      w-full
                      border
                      rounded-lg
                      px-3
                      py-2
                      bg-gray-100
                      text-gray-600
                    "
                  />

                </div>


                {/* ===========================================
                    BANK
                ============================================ */}

                <div>

                  <label
                    className="
                      block
                      text-sm
                      font-semibold
                      mb-1
                    "
                  >

                    Bank{" "}

                    <span className="text-red-500">
                      *
                    </span>

                  </label>


                  <input
                    type="text"
                    name="bank"
                    value={form.bank}
                    onChange={handleChange}
                    maxLength={100}
                    placeholder="Contoh: BCA"
                    disabled={saving}
                    className="
                      w-full
                      border
                      rounded-lg
                      px-3
                      py-2
                      focus:outline-none
                      focus:ring-2
                      focus:ring-blue-400
                    "
                  />

                </div>


                {/* ===========================================
                    JNS BANK
                ============================================ */}

                <div>

                  <label
                    className="
                      block
                      text-sm
                      font-semibold
                      mb-1
                    "
                  >

                    Jenis Bank{" "}

                    <span className="text-red-500">
                      *
                    </span>

                  </label>


                  <select
                    name="jns_bank"
                    value={form.jns_bank}
                    onChange={handleChange}
                    disabled={saving}
                    className="
                      w-full
                      border
                      rounded-lg
                      px-3
                      py-2
                      focus:outline-none
                      focus:ring-2
                      focus:ring-blue-400
                    "
                  >

                    <option value="REG">
                      REG
                    </option>

                    <option value="FRC">
                      FRC
                    </option>

                  </select>

                </div>


                {/* ===========================================
                    NO REK
                ============================================ */}

                <div>

                  <label
                    className="
                      block
                      text-sm
                      font-semibold
                      mb-1
                    "
                  >

                    No. Rekening{" "}

                    <span className="text-red-500">
                      *
                    </span>

                  </label>


                  <input
                    type="text"
                    name="no_rek"
                    value={form.no_rek}
                    onChange={handleChange}
                    maxLength={100}
                    placeholder="Nomor rekening"
                    disabled={saving}
                    className="
                      w-full
                      border
                      rounded-lg
                      px-3
                      py-2
                      font-mono
                      focus:outline-none
                      focus:ring-2
                      focus:ring-blue-400
                    "
                  />

                </div>


                {/* ===========================================
                    AKUN
                ============================================ */}

                <div>

                  <label
                    className="
                      block
                      text-sm
                      font-semibold
                      mb-1
                    "
                  >

                    Akun{" "}

                    <span className="text-red-500">
                      *
                    </span>

                  </label>


                  <input
                    type="text"
                    name="akun"
                    value={form.akun}
                    onChange={handleChange}
                    maxLength={6}
                    placeholder="Kode akun"
                    disabled={saving}
                    className="
                      w-full
                      border
                      rounded-lg
                      px-3
                      py-2
                      font-mono
                      focus:outline-none
                      focus:ring-2
                      focus:ring-blue-400
                    "
                  />

                </div>


                {/* ===========================================
                    CE
                ============================================ */}

                <div>

                  <label
                    className="
                      block
                      text-sm
                      font-semibold
                      mb-1
                    "
                  >

                    CE

                  </label>


                  <input
                    type="text"
                    name="ce"
                    value={form.ce}
                    onChange={handleChange}
                    maxLength={1}
                    placeholder="CE"
                    disabled={saving}
                    className="
                      w-full
                      border
                      rounded-lg
                      px-3
                      py-2
                      focus:outline-none
                      focus:ring-2
                      focus:ring-blue-400
                    "
                  />

                </div>


                {/* ===========================================
                    SITE
                ============================================ */}

                <div>

                  <label
                    className="
                      block
                      text-sm
                      font-semibold
                      mb-1
                    "
                  >

                    Site

                  </label>


                  <input
                    type="text"
                    name="site"
                    value={form.site}
                    onChange={handleChange}
                    maxLength={4}
                    placeholder="Site"
                    disabled={saving}
                    className="
                      w-full
                      border
                      rounded-lg
                      px-3
                      py-2
                      uppercase
                      focus:outline-none
                      focus:ring-2
                      focus:ring-blue-400
                    "
                  />

                </div>


                {/* ===========================================
                    BANK NAME
                ============================================ */}

                <div>

                  <label
                    className="
                      block
                      text-sm
                      font-semibold
                      mb-1
                    "
                  >

                    Bank Name

                  </label>


                  <input
                    type="text"
                    name="bank_name"
                    value={form.bank_name}
                    onChange={handleChange}
                    placeholder="Nama bank"
                    disabled={saving}
                    className="
                      w-full
                      border
                      rounded-lg
                      px-3
                      py-2
                      focus:outline-none
                      focus:ring-2
                      focus:ring-blue-400
                    "
                  />

                </div>


                {/* ===========================================
                    ACC NUM
                ============================================ */}

                <div>

                  <label
                    className="
                      block
                      text-sm
                      font-semibold
                      mb-1
                    "
                  >

                    Account Number

                  </label>


                  <input
                    type="text"
                    name="acc_num"
                    value={form.acc_num}
                    onChange={handleChange}
                    placeholder="Account number"
                    disabled={saving}
                    className="
                      w-full
                      border
                      rounded-lg
                      px-3
                      py-2
                      font-mono
                      focus:outline-none
                      focus:ring-2
                      focus:ring-blue-400
                    "
                  />

                </div>


                {/* ===========================================
                    RECEIPT METHOD
                ============================================ */}

                <div
                  className="
                    md:col-span-2
                  "
                >

                  <label
                    className="
                      block
                      text-sm
                      font-semibold
                      mb-1
                    "
                  >

                    Receipt Method

                  </label>


                  <input
                    type="text"
                    name="receipt_method"
                    value={form.receipt_method}
                    onChange={handleChange}
                    placeholder="Receipt method"
                    disabled={saving}
                    className="
                      w-full
                      border
                      rounded-lg
                      px-3
                      py-2
                      focus:outline-none
                      focus:ring-2
                      focus:ring-blue-400
                    "
                  />

                </div>

              </div>


              {/* =================================================
                  FOOTER MODAL
              ================================================== */}

              <div
                className="
                  px-5
                  py-4
                  bg-gray-50
                  border-t
                  flex
                  justify-end
                  gap-2
                "
              >

                <button
                  type="button"
                  onClick={
                    handleCloseModal
                  }
                  disabled={saving}
                  className="
                    flex
                    items-center
                    gap-2
                    px-4
                    py-2
                    border
                    rounded-lg
                    bg-white
                    hover:bg-gray-100
                    text-gray-700
                    disabled:opacity-50
                  "
                >

                  <FaTimes />

                  Batal

                </button>


                <button
                  type="submit"
                  disabled={saving}
                  className="
                    flex
                    items-center
                    gap-2
                    px-4
                    py-2
                    rounded-lg
                    bg-blue-500
                    hover:bg-blue-600
                    text-white
                    font-semibold
                    disabled:opacity-50
                  "
                >

                  {saving ? (

                    <>

                      <div
                        className="
                          w-4
                          h-4
                          border-2
                          border-white
                          border-t-transparent
                          rounded-full
                          animate-spin
                        "
                      />

                      Menyimpan...

                    </>

                  ) : (

                    <>

                      <FaSave />

                      {editMode
                        ? "Simpan Perubahan"
                        : "Simpan"}

                    </>

                  )}

                </button>

              </div>

            </form>

          </div>

        </div>

      )}

    </main>
  );
}