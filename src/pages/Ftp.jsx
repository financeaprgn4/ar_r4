import React, { useEffect, useState } from "react";
import Swal from "sweetalert2";

import {
  FaPlus,
  FaTimes,
  FaSave,
  FaEye,
  FaEyeSlash,
  FaEdit,
  FaTrash,
  FaServer,
  FaDatabase,
  FaUser,
  FaKey,
  FaSyncAlt,
} from "react-icons/fa";

import { useCabang } from "../contexts/CabangContext";

export default function Ftp() {
  const { cabang } = useCabang();

  // =========================================================
  // STATE
  // =========================================================
  const [data, setData] = useState([]);
  const [loading, setLoading] = useState(false);

  const [showModal, setShowModal] = useState(false);
  const [modalMode, setModalMode] = useState("add");

  const [showPassword, setShowPassword] = useState({});

  const [formData, setFormData] = useState({
    id: null,
    server: "",
    user: "",
    pass: "",
  });

  const [search, setSearch] = useState("");

  // =========================================================
  // GET DATA
  // =========================================================
  const fetchData = async () => {
    try {
      setLoading(true);

      const response = await fetch("/api/ftp", {
        method: "GET",
        headers: {
          Accept: "application/json",
        },
      });

      if (!response.ok) {
        throw new Error("Gagal mengambil data FTP");
      }

      const result = await response.json();

      /*
       * Menyesuaikan beberapa kemungkinan format response Laravel:
       *
       * 1. [{...}, {...}]
       * 2. { data: [{...}, {...}] }
       * 3. { success: true, data: [...] }
       */

      let ftpData = [];

      if (Array.isArray(result)) {
        ftpData = result;
      } else if (Array.isArray(result.data)) {
        ftpData = result.data;
      }

      setData(ftpData);
    } catch (error) {
      console.error("FETCH FTP ERROR:", error);

      Swal.fire({
        icon: "error",
        title: "Gagal",
        text: error.message || "Gagal mengambil data FTP.",
        confirmButtonColor: "#2563eb",
      });
    } finally {
      setLoading(false);
    }
  };

  // =========================================================
  // LOAD DATA
  // =========================================================
  useEffect(() => {
    fetchData();
  }, []);

  // =========================================================
  // FORM CHANGE
  // =========================================================
  const handleChange = (e) => {
    const { name, value } = e.target;

    setFormData((prev) => ({
      ...prev,
      [name]: value,
    }));
  };

  // =========================================================
  // OPEN ADD MODAL
  // =========================================================
  const handleAdd = () => {
    setModalMode("add");

    setFormData({
      id: null,
      server: "",
      user: "",
      pass: "",
    });

    setShowModal(true);
  };

  // =========================================================
  // OPEN EDIT MODAL
  // =========================================================
  const handleEdit = (row) => {
    setModalMode("edit");

    setFormData({
      id: row.id,
      server: row.server || "",
      user: row.user || "",
      pass: row.pass || "",
    });

    setShowModal(true);
  };

  // =========================================================
  // CLOSE MODAL
  // =========================================================
  const handleCloseModal = () => {
    setShowModal(false);

    setFormData({
      id: null,
      server: "",
      user: "",
      pass: "",
    });
  };

  // =========================================================
  // SAVE DATA
  // =========================================================
  const handleSubmit = async (e) => {
    e.preventDefault();

    // Validasi
    if (!formData.server.trim()) {
      Swal.fire({
        icon: "warning",
        title: "Server belum diisi",
        text: "Silakan masukkan alamat server FTP.",
        confirmButtonColor: "#2563eb",
      });
      return;
    }

    if (!formData.user.trim()) {
      Swal.fire({
        icon: "warning",
        title: "Username belum diisi",
        text: "Silakan masukkan username FTP.",
        confirmButtonColor: "#2563eb",
      });
      return;
    }

    if (!formData.pass.trim()) {
      Swal.fire({
        icon: "warning",
        title: "Password belum diisi",
        text: "Silakan masukkan password FTP.",
        confirmButtonColor: "#2563eb",
      });
      return;
    }

    try {
      Swal.fire({
        title: modalMode === "add" ? "Menyimpan data..." : "Memperbarui data...",
        text: "Mohon tunggu.",
        allowOutsideClick: false,
        allowEscapeKey: false,
        didOpen: () => {
          Swal.showLoading();
        },
      });

      const url =
        modalMode === "add"
          ? "/api/ftp"
          : `/api/ftp/${formData.id}`;

      const method = modalMode === "add" ? "POST" : "PUT";

      const payload = {
        server: formData.server,
        user: formData.user,
        pass: formData.pass,
      };

      const response = await fetch(url, {
        method,
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
        },
        body: JSON.stringify(payload),
      });

      const result = await response.json();

      if (!response.ok) {
        throw new Error(
          result.message ||
            "Terjadi kesalahan saat menyimpan data FTP."
        );
      }

      Swal.close();

      await Swal.fire({
        icon: "success",
        title: "Berhasil",
        text:
          modalMode === "add"
            ? "Data FTP berhasil ditambahkan."
            : "Data FTP berhasil diperbarui.",
        timer: 1500,
        showConfirmButton: false,
      });

      handleCloseModal();

      fetchData();
    } catch (error) {
      console.error("SAVE FTP ERROR:", error);

      Swal.close();

      Swal.fire({
        icon: "error",
        title: "Gagal",
        text: error.message || "Gagal menyimpan data FTP.",
        confirmButtonColor: "#2563eb",
      });
    }
  };

  // =========================================================
  // DELETE DATA
  // =========================================================
  const handleDelete = async (row) => {
    const result = await Swal.fire({
      icon: "warning",
      title: "Hapus Data FTP?",
      html: `
        <div style="text-align:center">
          <p style="margin-bottom:8px;">
            Data FTP berikut akan dihapus:
          </p>

          <div style="
            background:#f3f4f6;
            border-radius:8px;
            padding:10px;
            margin-top:10px;
          ">
            <strong>${row.server || "-"}</strong>
            <br/>
            <span style="color:#6b7280;">
              ${row.user || "-"}
            </span>
          </div>

          <p style="
            margin-top:12px;
            color:#dc2626;
            font-size:13px;
          ">
            Data yang sudah dihapus tidak dapat dikembalikan.
          </p>
        </div>
      `,
      showCancelButton: true,
      confirmButtonText: "Ya, Hapus",
      cancelButtonText: "Batal",
      confirmButtonColor: "#dc2626",
      cancelButtonColor: "#6b7280",
      reverseButtons: true,
    });

    if (!result.isConfirmed) {
      return;
    }

    try {
      Swal.fire({
        title: "Menghapus...",
        text: "Mohon tunggu.",
        allowOutsideClick: false,
        allowEscapeKey: false,
        didOpen: () => {
          Swal.showLoading();
        },
      });

      const response = await fetch(`/api/ftp/${row.id}`, {
        method: "DELETE",
        headers: {
          Accept: "application/json",
        },
      });

      const responseData = await response.json();

      if (!response.ok) {
        throw new Error(
          responseData.message || "Gagal menghapus data."
        );
      }

      Swal.close();

      await Swal.fire({
        icon: "success",
        title: "Berhasil",
        text: "Data FTP berhasil dihapus.",
        timer: 1500,
        showConfirmButton: false,
      });

      fetchData();
    } catch (error) {
      console.error("DELETE FTP ERROR:", error);

      Swal.close();

      Swal.fire({
        icon: "error",
        title: "Gagal",
        text: error.message || "Gagal menghapus data FTP.",
        confirmButtonColor: "#2563eb",
      });
    }
  };

  // =========================================================
  // TEST FTP CONNECTION
  // =========================================================
  const handleTest = async (row) => {
    try {
    // Loading
    Swal.fire({
        title: "Testing FTP...",
        html: `
        <div style="margin-top:10px;text-align:center">
            <div style="font-size:13px;color:#64748b;">Server</div>
            <div style="font-weight:600;margin-top:4px;">${row.server}</div>
            <div style="font-size:12px;color:#94a3b8;margin-top:4px;">
            User : ${row.user}
            </div>
        </div>
        `,
        allowOutsideClick: false,
        allowEscapeKey: false,
        showConfirmButton: false,
        didOpen: () => Swal.showLoading(),
    });

    const response = await fetch(`/api/ftp/${row.id}/test`, {
        method: "GET",
        headers: {
        Accept: "application/json",
        },
    });

    const result = await response.json();

    Swal.close();

    // ================= SUCCESS =================
    if (response.ok && result.success) {
        return Swal.fire({
        icon: "success",
        title: "FTP Berhasil Terhubung",
        html: `
            <div style="text-align:left;font-size:14px">
            <table style="width:100%;line-height:1.8">
                <tr>
                <td><b>Server</b></td>
                <td>: ${result.server}</td>
                </tr>
                <tr>
                <td><b>Port</b></td>
                <td>: ${result.port}</td>
                </tr>
                <tr>
                <td><b>User</b></td>
                <td>: ${result.user}</td>
                </tr>
                <tr>
                <td><b>Directory</b></td>
                <td>: ${result.directory}</td>
                </tr>
            </table>
            </div>
        `,
        confirmButtonColor: "#16a34a",
        });
    }

    // ================= FAILED =================
    return Swal.fire({
        icon: "error",
        title: "FTP Gagal Terhubung",
        html: `
        <div style="text-align:left;font-size:14px">
            <p style="margin-bottom:12px;">
            ${result.message || "Koneksi FTP gagal."}
            </p>

            <table style="width:100%;line-height:1.8">
            <tr>
                <td><b>Server</b></td>
                <td>: ${row.server}</td>
            </tr>
            <tr>
                <td><b>User</b></td>
                <td>: ${row.user}</td>
            </tr>
            </table>
        </div>
        `,
        confirmButtonColor: "#dc2626",
    });

    } catch (error) {
    console.error("FTP TEST ERROR:", error);

    Swal.close();

    Swal.fire({
        icon: "error",
        title: "Testing Gagal",
        text: error.message || "Tidak dapat melakukan koneksi ke FTP.",
        confirmButtonColor: "#dc2626",
    });
    }
  };

  // =========================================================
  // TOGGLE PASSWORD
  // =========================================================
  const togglePassword = (id) => {
    setShowPassword((prev) => ({
      ...prev,
      [id]: !prev[id],
    }));
  };

  // =========================================================
  // FILTER SEARCH
  // =========================================================
  const filteredData = data.filter((row) => {
    const keyword = search.toLowerCase();

    return (
      String(row.id || "")
        .toLowerCase()
        .includes(keyword) ||
      String(row.server || "")
        .toLowerCase()
        .includes(keyword) ||
      String(row.user || "")
        .toLowerCase()
        .includes(keyword)
    );
  });

  // =========================================================
  // RENDER
  // =========================================================
  return (
    <main className="flex-1 px-3 sm:px-4 py-2 z-10 text-gray-800">
      <div className="h-[calc(100vh-50px)] bg-white/70 backdrop-blur-sm rounded-xl shadow-lg overflow-hidden flex flex-col">

        {/* =====================================================
            HEADER
        ====================================================== */}
        <div className="px-4 sm:px-6 py-4 border-b border-gray-200 bg-white/80">

          <div className="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">

            {/* TITLE */}
            <div className="flex items-center gap-3">

              <div className="
                w-11 h-11
                rounded-xl
                bg-blue-100
                text-blue-600
                flex
                items-center
                justify-center
                shadow-sm
              ">
                <FaServer className="text-xl" />
              </div>

              <div>
                <h1 className="text-lg sm:text-xl font-bold text-gray-800">
                  FTP Server
                </h1>

                <p className="text-xs sm:text-sm text-gray-500">
                  Kelola konfigurasi koneksi FTP server
                </p>
              </div>

            </div>

            {/* ACTION */}
            <div className="flex items-center gap-2">

              <button
                type="button"
                onClick={fetchData}
                disabled={loading}
                className="
                  flex items-center justify-center gap-2
                  px-3 py-2
                  rounded-lg
                  border border-gray-300
                  bg-white
                  text-gray-600
                  hover:bg-gray-50
                  transition
                  disabled:opacity-50
                "
                title="Refresh"
              >
                <FaSyncAlt
                  className={loading ? "animate-spin" : ""}
                />

                <span className="hidden sm:inline">
                  Refresh
                </span>
              </button>

              <button
                type="button"
                onClick={handleAdd}
                className="
                  flex items-center justify-center gap-2
                  px-4 py-2
                  rounded-lg
                  bg-blue-600
                  hover:bg-blue-700
                  text-white
                  font-medium
                  shadow-sm
                  transition
                "
              >
                <FaPlus />

                <span>
                  Tambah FTP
                </span>
              </button>

            </div>
          </div>
        </div>

        {/* =====================================================
            TOOLBAR
        ====================================================== */}
        <div className="px-4 sm:px-6 py-3 border-b border-gray-200 bg-gray-50/80">

          <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">

            {/* SEARCH */}
            <div className="relative w-full sm:w-80">

              <input
                type="text"
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                placeholder="Cari server atau username..."
                className="
                  w-full
                  pl-4 pr-4 py-2
                  text-sm
                  border border-gray-300
                  rounded-lg
                  bg-white
                  focus:outline-none
                  focus:ring-2
                  focus:ring-blue-500/30
                  focus:border-blue-500
                "
              />

            </div>

            {/* TOTAL */}
            <div className="text-xs sm:text-sm text-gray-500">
              Menampilkan{" "}
              <span className="font-semibold text-gray-700">
                {filteredData.length}
              </span>{" "}
              dari{" "}
              <span className="font-semibold text-gray-700">
                {data.length}
              </span>{" "}
              data
            </div>

          </div>
        </div>

        {/* =====================================================
            TABLE
        ====================================================== */}
        <div className="flex-1 overflow-auto">

          <table className="w-full min-w-[700px] border-collapse">

            <thead className="
              sticky
              top-0
              z-10
              bg-gray-100
              border-b
              border-gray-200
            ">
              <tr>

                <th className="
                  px-4 py-3
                  text-center
                  text-xs
                  font-semibold
                  text-gray-500
                  uppercase
                  tracking-wider
                  w-16
                ">
                  No
                </th>

                <th className="
                  px-4 py-3
                  text-left
                  text-xs
                  font-semibold
                  text-gray-500
                  uppercase
                  tracking-wider
                ">
                  Server
                </th>

                <th className="
                  px-4 py-3
                  text-left
                  text-xs
                  font-semibold
                  text-gray-500
                  uppercase
                  tracking-wider
                ">
                  Username
                </th>

                <th className="
                  px-4 py-3
                  text-left
                  text-xs
                  font-semibold
                  text-gray-500
                  uppercase
                  tracking-wider
                ">
                  Password
                </th>

                <th className="
                  px-4 py-3
                  text-center
                  text-xs
                  font-semibold
                  text-gray-500
                  uppercase
                  tracking-wider
                  w-32
                ">
                  Aksi
                </th>

              </tr>
            </thead>

            <tbody className="divide-y divide-gray-100">

              {/* LOADING */}
              {loading && (
                <tr>
                  <td
                    colSpan="5"
                    className="px-4 py-12 text-center"
                  >
                    <div className="
                      flex
                      flex-col
                      items-center
                      justify-center
                      gap-3
                    ">
                      <FaSyncAlt
                        className="
                          text-2xl
                          text-blue-600
                          animate-spin
                        "
                      />

                      <span className="text-sm text-gray-500">
                        Memuat data FTP...
                      </span>
                    </div>
                  </td>
                </tr>
              )}

              {/* EMPTY */}
              {!loading && filteredData.length === 0 && (
                <tr>
                  <td
                    colSpan="5"
                    className="px-4 py-12 text-center"
                  >

                    <div className="
                      flex
                      flex-col
                      items-center
                      justify-center
                      gap-3
                    ">

                      <div className="
                        w-14 h-14
                        rounded-full
                        bg-gray-100
                        flex
                        items-center
                        justify-center
                        text-gray-400
                      ">
                        <FaServer className="text-xl" />
                      </div>

                      <div>
                        <p className="
                          text-sm
                          font-semibold
                          text-gray-600
                        ">
                          Tidak ada data FTP
                        </p>

                        <p className="
                          text-xs
                          text-gray-400
                          mt-1
                        ">
                          {search
                            ? "Data yang dicari tidak ditemukan."
                            : "Belum terdapat konfigurasi FTP."}
                        </p>
                      </div>

                      {!search && (
                        <button
                          type="button"
                          onClick={handleAdd}
                          className="
                            mt-2
                            px-3 py-2
                            rounded-lg
                            bg-blue-600
                            hover:bg-blue-700
                            text-white
                            text-sm
                            flex
                            items-center
                            gap-2
                          "
                        >
                          <FaPlus />
                          Tambah FTP
                        </button>
                      )}

                    </div>

                  </td>
                </tr>
              )}

              {/* DATA */}
              {!loading &&
                filteredData.map((row, index) => (
                  <tr
                    key={row.id}
                    className="
                      hover:bg-blue-50/50
                      transition-colors
                    "
                  >

                    {/* NO */}
                    <td className="
                      px-4 py-3
                      text-center
                      text-sm
                      text-gray-500
                    ">
                      {index + 1}
                    </td>

                    {/* SERVER */}
                    <td className="px-4 py-3">

                      <div className="flex items-center gap-3">

                        <div className="
                          w-9 h-9
                          rounded-lg
                          bg-blue-100
                          text-blue-600
                          flex
                          items-center
                          justify-center
                          flex-shrink-0
                        ">
                          <FaServer />
                        </div>

                        <div className="min-w-0">

                          <div className="
                            font-medium
                            text-gray-800
                            truncate
                          ">
                            {row.server || "-"}
                          </div>

                          <div className="
                            text-xs
                            text-gray-400
                          ">
                            FTP Server
                          </div>

                        </div>

                      </div>

                    </td>

                    {/* USERNAME */}
                    <td className="px-4 py-3">

                      <div className="flex items-center gap-2">

                        <div className="
                          w-7 h-7
                          rounded-md
                          bg-gray-100
                          text-gray-500
                          flex
                          items-center
                          justify-center
                        ">
                          <FaUser className="text-xs" />
                        </div>

                        <span className="
                          text-sm
                          text-gray-700
                          font-medium
                        ">
                          {row.user || "-"}
                        </span>

                      </div>

                    </td>

                    {/* PASSWORD */}
                    <td className="px-4 py-3">

                      <div className="flex items-center gap-2">

                        <div className="
                          w-7 h-7
                          rounded-md
                          bg-gray-100
                          text-gray-500
                          flex
                          items-center
                          justify-center
                        ">
                          <FaKey className="text-xs" />
                        </div>

                        <span className="
                          font-mono
                          text-sm
                          text-gray-700
                          min-w-[100px]
                        ">
                          {showPassword[row.id]
                            ? row.pass || "-"
                            : "••••••••"}
                        </span>

                        <button
                          type="button"
                          onClick={() =>
                            togglePassword(row.id)
                          }
                          className="
                            p-1.5
                            rounded-md
                            text-gray-400
                            hover:text-blue-600
                            hover:bg-blue-50
                            transition
                          "
                          title={
                            showPassword[row.id]
                              ? "Sembunyikan password"
                              : "Tampilkan password"
                          }
                        >
                          {showPassword[row.id] ? (
                            <FaEyeSlash />
                          ) : (
                            <FaEye />
                          )}
                        </button>

                      </div>

                    </td>

                    {/* ACTION */}
                    <td className="px-4 py-3">

                      <div className="
                        flex
                        items-center
                        justify-center
                        gap-2
                      ">
                        <button
                            type="button"
                            onClick={() => handleTest(row)}
                            className="
                                h-8
                                px-3
                                flex
                                items-center
                                justify-center
                                gap-1.5
                                rounded-lg
                                bg-blue-50
                                text-blue-600
                                hover:bg-blue-100
                                transition
                                text-xs
                                font-medium
                            "
                            title="Test koneksi FTP"
                        >
                            <FaServer className="text-xs" />
                            <span>Tes</span>
                        </button>
                            
                        <button
                          type="button"
                          onClick={() => handleEdit(row)}
                          className="
                            w-8 h-8
                            flex
                            items-center
                            justify-center
                            rounded-lg
                            bg-amber-50
                            text-amber-600
                            hover:bg-amber-100
                            transition
                          "
                          title="Edit"
                        >
                          <FaEdit className="text-sm" />
                        </button>

                        <button
                          type="button"
                          onClick={() => handleDelete(row)}
                          className="
                            w-8 h-8
                            flex
                            items-center
                            justify-center
                            rounded-lg
                            bg-red-50
                            text-red-600
                            hover:bg-red-100
                            transition
                          "
                          title="Delete"
                        >
                          <FaTrash className="text-sm" />
                        </button>

                      </div>

                    </td>

                  </tr>
                ))}

            </tbody>

          </table>

        </div>

        {/* =====================================================
            FOOTER
        ====================================================== */}
        <div className="
          px-4 sm:px-6
          py-3
          border-t
          border-gray-200
          bg-gray-50/80
          flex
          items-center
          justify-between
        ">

          <div className="
            flex
            items-center
            gap-2
            text-xs
            text-gray-500
          ">
            <FaDatabase />

            <span>
              FTP Configuration
            </span>
          </div>

          <span className="text-xs text-gray-400">
            Total {data.length} server
          </span>

        </div>

      </div>

      {/* =======================================================
          MODAL ADD / EDIT
      ======================================================== */}
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
            if (e.target === e.currentTarget) {
              handleCloseModal();
            }
          }}
        >

          <div className="
            w-full
            max-w-lg
            bg-white
            rounded-2xl
            shadow-2xl
            overflow-hidden
            animate-[fadeIn_0.2s_ease-out]
          ">

            {/* MODAL HEADER */}
            <div className="
              px-5 py-4
              bg-gradient-to-r
              from-blue-600
              to-blue-700
              text-white
              flex
              items-center
              justify-between
            ">

              <div className="flex items-center gap-3">

                <div className="
                  w-10 h-10
                  rounded-lg
                  bg-white/20
                  flex
                  items-center
                  justify-center
                ">
                  <FaServer />
                </div>

                <div>

                  <h2 className="
                    font-semibold
                    text-base
                  ">
                    {modalMode === "add"
                      ? "Tambah FTP Server"
                      : "Edit FTP Server"}
                  </h2>

                  <p className="
                    text-xs
                    text-blue-100
                  ">
                    Masukkan konfigurasi koneksi FTP
                  </p>

                </div>

              </div>

              <button
                type="button"
                onClick={handleCloseModal}
                className="
                  w-8 h-8
                  rounded-lg
                  flex
                  items-center
                  justify-center
                  hover:bg-white/20
                  transition
                "
              >
                <FaTimes />
              </button>

            </div>

            {/* MODAL BODY */}
            <form onSubmit={handleSubmit}>

              <div className="p-5 space-y-4">

                {/* SERVER */}
                <div>

                  <label className="
                    block
                    text-sm
                    font-medium
                    text-gray-700
                    mb-1.5
                  ">
                    Server
                  </label>

                  <div className="relative">

                    <FaServer className="
                      absolute
                      left-3
                      top-1/2
                      -translate-y-1/2
                      text-gray-400
                    " />

                    <input
                      type="text"
                      name="server"
                      value={formData.server}
                      onChange={handleChange}
                      placeholder="Contoh: ftpserver.domain.lan"
                      className="
                        w-full
                        pl-10
                        pr-3
                        py-2.5
                        border
                        border-gray-300
                        rounded-lg
                        text-sm
                        focus:outline-none
                        focus:ring-2
                        focus:ring-blue-500/30
                        focus:border-blue-500
                      "
                    />

                  </div>

                </div>

                {/* USER */}
                <div>

                  <label className="
                    block
                    text-sm
                    font-medium
                    text-gray-700
                    mb-1.5
                  ">
                    Username
                  </label>

                  <div className="relative">

                    <FaUser className="
                      absolute
                      left-3
                      top-1/2
                      -translate-y-1/2
                      text-gray-400
                    " />

                    <input
                      type="text"
                      name="user"
                      value={formData.user}
                      onChange={handleChange}
                      placeholder="Masukkan username FTP"
                      className="
                        w-full
                        pl-10
                        pr-3
                        py-2.5
                        border
                        border-gray-300
                        rounded-lg
                        text-sm
                        focus:outline-none
                        focus:ring-2
                        focus:ring-blue-500/30
                        focus:border-blue-500
                      "
                    />

                  </div>

                </div>

                {/* PASSWORD */}
                <div>

                  <label className="
                    block
                    text-sm
                    font-medium
                    text-gray-700
                    mb-1.5
                  ">
                    Password
                  </label>

                  <div className="relative">

                    <FaKey className="
                      absolute
                      left-3
                      top-1/2
                      -translate-y-1/2
                      text-gray-400
                    " />

                    <input
                      type={
                        showPassword.form
                          ? "text"
                          : "password"
                      }
                      name="pass"
                      value={formData.pass}
                      onChange={handleChange}
                      placeholder="Masukkan password FTP"
                      className="
                        w-full
                        pl-10
                        pr-11
                        py-2.5
                        border
                        border-gray-300
                        rounded-lg
                        text-sm
                        focus:outline-none
                        focus:ring-2
                        focus:ring-blue-500/30
                        focus:border-blue-500
                      "
                    />

                    <button
                      type="button"
                      onClick={() =>
                        setShowPassword((prev) => ({
                          ...prev,
                          form: !prev.form,
                        }))
                      }
                      className="
                        absolute
                        right-3
                        top-1/2
                        -translate-y-1/2
                        text-gray-400
                        hover:text-blue-600
                      "
                    >
                      {showPassword.form ? (
                        <FaEyeSlash />
                      ) : (
                        <FaEye />
                      )}
                    </button>

                  </div>

                </div>

              </div>

              {/* MODAL FOOTER */}
              <div className="
                px-5 py-4
                border-t
                border-gray-200
                bg-gray-50
                flex
                flex-col-reverse
                sm:flex-row
                sm:justify-end
                gap-2
              ">

                <button
                  type="button"
                  onClick={handleCloseModal}
                  className="
                    px-4 py-2.5
                    rounded-lg
                    border
                    border-gray-300
                    bg-white
                    text-gray-600
                    text-sm
                    font-medium
                    hover:bg-gray-100
                    transition
                  "
                >
                  Batal
                </button>

                <button
                  type="submit"
                  className="
                    px-4 py-2.5
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
                    transition
                  "
                >
                  <FaSave />

                  {modalMode === "add"
                    ? "Simpan"
                    : "Update"}
                </button>

              </div>

            </form>

          </div>

        </div>
      )}
    </main>
  );
}