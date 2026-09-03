import React, { useEffect, useState, useRef } from "react";
import BottomDrawer from "../components/BottomDrawer";
import ReusableTable from "../components/ReusableTable";
import axios from "../config/axiosInstance";
import Swal from "sweetalert2";
import { FaRecycle, FaTrash, FaDownload, FaUpload, FaCloudUploadAlt, FaCheckCircle } from "react-icons/fa";
import { fileUrl } from "../config/fileUrl"
import { useCabang } from "../contexts/CabangContext";

export default function Rek_koran() {
  const [showDrawer, setDrawerOpen] = useState(false);
  const { cabang } = useCabang();
  const [data, setData] = useState([]);
  const [globalFilter, setGlobalFilter] = useState("");
  const labelRef = useRef(null);
  const searchRef = useRef(null);
  const fileInputRef = useRef(null);
  const [selectedFile, setSelectedFile] = useState(null);

  const resetFilePicker = () => {
    setSelectedFile(null);
    const fileInput = document.getElementById("fileUpload");
    if (fileInput) fileInput.value = null;
  };

  const handleFileChange = (e) => {
    const files = Array.from(e.target.files || []);
  
    if (files.length === 0) {
      return;
    }
  
    const invalidFiles = files.filter((file) => {
      return (
        file.type !== "application/pdf" &&
        !file.name.toLowerCase().endsWith(".pdf")
      );
    });
  
    if (invalidFiles.length > 0) {
      Swal.fire({
        icon: "error",
        title: "File tidak valid",
        html: `
          <p>Hanya file <b>PDF</b> yang diperbolehkan.</p>
          <p class="mt-2">File tidak valid:</p>
          <ul style="text-align:left;">
            ${invalidFiles
              .map((file) => `<li>• ${file.name}</li>`)
              .join("")}
          </ul>
        `,
        confirmButtonText: "OK",
      });
  
      // Hanya ambil file PDF
      const validFiles = files.filter((file) => {
        return (
          file.type === "application/pdf" ||
          file.name.toLowerCase().endsWith(".pdf")
        );
      });
  
      setSelectedFile(validFiles);
  
      return;
    }
  
    setSelectedFile(files);
  };

  useEffect(() => {
    if (!showDrawer) {
      resetFilePicker();
    }
  }, [showDrawer]);

  useEffect(() => {
    if (showDrawer && labelRef.current) {
      labelRef.current.focus();
    }
  }, [showDrawer]);

  const fetchStatementData = async () => {
    if (!cabang) return;
  
    try {
      const res = await fetch(`/api/Statement?cabang=${cabang}`);
  
      if (!res.ok) {
        throw new Error(
          `Gagal mengambil data. Status: ${res.status}`
        );
      }
  
      const result = await res.json();
  
      setData(result);
      
  
    } catch (err) {
      console.error(
        "Gagal mengambil/update data tabel:",
        err
      );
    }
  };

  useEffect(() => {
    if (!cabang) return;
  
    fetchStatementData();
  }, [cabang]);

  const columns = [
    {
        accessorKey: "cabang",
        header: "Cabang",
        cell: info => info.getValue(),
    },
    {
        accessorKey: "nama_bank",
        header: "Nama Bank",
        cell: info => info.getValue(),
    },
    {
        accessorKey: "jns_rek",
        header: "Jenis Rekenig Bank",
        cell: info => info.getValue(),
    },
    {
        accessorKey: "no_rek",
        header: "Nomor Rekening",
        cell: info => info.getValue(),
    },
    {
        accessorKey: "periode",
        header: "Periode",
        cell: info => info.getValue(),
    },
    {
        accessorKey: "file",
        header: "File",
        cell: info => info.getValue(),
    },
    {
        header: "Opsi",
        id: "actions",
        cell: ({ row }) => (
            <div className="flex space-x-2 justify-center">
                {row.original.file && (
                  <button
                      onClick={() =>
                        window.open(
                            fileUrl(
                                `/rk/${row.original.cabang}/${row.original.file}?v=${Date.now()}`
                            ),
                            '_blank'
                        )
                      }

                      className="px-2 py-1 bg-blue-500 text-white rounded flex items-center space-x-1"
                  >
                      <FaDownload className="w-5 h-5" />
                  </button>
                )}

                <button
                  onClick={() => handleDelete(row.original)}
                  className="px-2 py-1 bg-red-500 text-white rounded flex items-center space-x-1"
                >
                  <FaTrash className="w-5 h-5" />
                </button>
            </div>
        ),
    },    
  ];

  useEffect(() => {
    const handleKeyDown = (e) => {
        const { key, code, altKey } = e;

        // ESC
        if (key === 'Escape') {
          if (showDrawer) {
            setDrawerOpen(false);
          }

          return;
        }

        // ALT + Shortcut
        if (altKey) {
            switch (code) {
                case 'KeyU':
                  e.preventDefault();
                  setDrawerOpen(true);
                  return;

                default:
                    break;
            }
        }

        // ENTER pada label upload
        if (
            key === 'Enter' &&
            document.activeElement === labelRef.current &&
            fileInputRef.current
        ) {
            fileInputRef.current.click();
        }
    };

    window.addEventListener('keydown', handleKeyDown);

    return () => {
        window.removeEventListener('keydown', handleKeyDown);
    };
  }, [showDrawer]);
  
  
  const handleSubmit = async () => {
    if (!selectedFile || selectedFile.length === 0) {
  
      await Swal.fire({
        icon: "warning",
        title: "Peringatan",
        text: "Silakan pilih minimal satu file PDF!",
        confirmButtonText: "OK",
      });
  
      return;
    }
  
    if (!cabang) {
  
      await Swal.fire({
        icon: "warning",
        title: "Peringatan",
        text: "Cabang belum dipilih!",
        confirmButtonText: "OK",
      });
  
      return;
    }
  
    const invalidFiles = selectedFile.filter((file) => {
  
      const validType =
        file.type === "application/pdf";
  
      const validExtension =
        file.name
          .toLowerCase()
          .endsWith(".pdf");
  
      return !validType && !validExtension;
    });
  
  
    if (invalidFiles.length > 0) {
  
      let html = `
        <div style="text-align:left">
          <p>
            Hanya file <b>PDF</b> yang diperbolehkan.
          </p>
  
          <p>
            File berikut tidak valid:
          </p>
  
          <ul style="padding-left:20px">
      `;
  
      invalidFiles.forEach((file) => {
  
        html += `
          <li>
            ${file.name}
          </li>
        `;
  
      });
  
      html += `
          </ul>
        </div>
      `;
  
  
      await Swal.fire({
        icon: "error",
        title: "File Tidak Valid",
        html: html,
        width: 600,
        confirmButtonText: "OK",
      });
  
      return;
    }
  
    const confirmResult = await Swal.fire({
  
      icon: "question",
  
      title: "Upload Bank Statement",
  
      html: `
        <div style="text-align:left">
  
          <p>
            Cabang :
            <b>${cabang}</b>
          </p>
  
          <p>
            Jumlah File :
            <b>${selectedFile.length}</b>
          </p>
  
        </div>
      `,
  
      showCancelButton: true,
  
      confirmButtonText: "Ya, Upload",
  
      cancelButtonText: "Batal",
  
      reverseButtons: true,
  
    });
  
  
    if (!confirmResult.isConfirmed) {
      return;
    }
  
    const formData = new FormData();
    selectedFile.forEach((file) => {
      formData.append(
        "files[]",
        file
      );
  
    });
  
    formData.append(
      "cabang",
      cabang
    );
  
    Swal.fire({
      title: `Upload RK Cabang ${cabang}`,
      html: `
        <div style="margin-top:10px">
  
          <div id="upload-text">
            Menyiapkan upload...
          </div>
  
          <div style="
            width:100%;
            height:10px;
            background:#e5e7eb;
            border-radius:10px;
            margin-top:15px;
            overflow:hidden;
          ">
  
            <div
              id="progress-bar"
              style="
                width:0%;
                height:100%;
                background:#2563eb;
                transition:width .3s ease;
              "
            ></div>
  
          </div>
  
          <div
            id="progress-percent"
            style="
              margin-top:8px;
              font-weight:bold;
            "
          >
            0%
          </div>
  
          <div
            id="anim-import"
            style="
              display:none;
              margin-top:18px;
              color:#2563eb;
              font-weight:bold;
            "
          >
            <span class="loader"></span>
            Memproses file di server...
          </div>
  
        </div>
      `,
  
      allowOutsideClick: false,
  
      allowEscapeKey: false,
  
      showConfirmButton: false,
  
    });
  
    if (!document.getElementById("rk-upload-style")) {
      const style =
        document.createElement("style");
  
      style.id =
        "rk-upload-style";
  
      style.innerHTML = `
  
        .loader {
  
          border:4px solid #e5e7eb;
  
          border-top:4px solid #2563eb;
  
          border-radius:50%;
  
          width:20px;
  
          height:20px;
  
          display:inline-block;
  
          animation:spin .8s linear infinite;
  
          vertical-align:middle;
  
          margin-right:8px;
  
        }
  
  
        @keyframes spin {
  
          0% {
            transform:rotate(0deg);
          }
  
          100% {
            transform:rotate(360deg);
          }
  
        }
  
      `;
  
  
      document.head.appendChild(style);
    }
  
    let response;
  
    try {
  
      response = await axios.post(
        "/api/upload-rk",
        formData,
        {
          headers: {
            "Content-Type":
              "multipart/form-data",
          },
  
          onUploadProgress: (event) => {
  
            if (!event.total) {
              return;
            }
  
            const percent =
              Math.round(
                (event.loaded * 100) /
                event.total
              );

            const container =
              Swal.getHtmlContainer();
  
            const text =
              container?.querySelector(
                "#upload-text"
              );
  
            const bar =
              container?.querySelector(
                "#progress-bar"
              );
  
            const percentText =
              container?.querySelector(
                "#progress-percent"
              );
  
            if (text) {
              text.innerHTML =
                `Mengunggah file... ${percent}%`;
  
            }
  
            if (bar) {
              bar.style.width =
                `${percent}%`;
            }
  
  
            if (percentText) {
              percentText.innerHTML =
                `${percent}%`;
  
            }
  
            if (percent >= 100) {
  
              if (text) {
  
                text.innerHTML =
                  "Upload selesai ✓";
  
              }
  
  
              const anim =
                container?.querySelector(
                  "#anim-import"
                );
  
  
              if (anim) {
  
                anim.style.display =
                  "block";
  
              }
  
            }
  
          },
  
        }
      );
  
  
    } catch (err) {
      console.error(
        "Upload RK error:",
        err
      );
  
  
      Swal.close();
  
  
      let message =
        "Terjadi kesalahan saat mengupload file.";
  
  
      const responseData =
        err?.response?.data;
  
      if (responseData?.message) {
  
        message =
          responseData.message;
  
      }
  
      if (responseData?.errors) {
  
        const validationErrors =
          responseData.errors;
  
  
        if (
          typeof validationErrors ===
          "object"
        ) {
  
          const result = [];
  
  
          Object.keys(
            validationErrors
          ).forEach((key) => {
  
            const value =
              validationErrors[key];
  
  
            if (Array.isArray(value)) {
  
              value.forEach((item) => {
  
                result.push(item);
  
              });
  
            } else {
  
              result.push(value);
  
            }
  
          });
  
  
          if (result.length > 0) {
  
            message =
              result.join("<br>");
  
          }
  
        }
  
      }
  
  
      await Swal.fire({
  
        icon: "error",
  
        title: "Upload Gagal",
  
        html: message,
  
        width: 600,
  
        confirmButtonText: "OK",
  
      });
  
      return;
    }
  
    const data =
      response?.data || {};
  
  
    const berhasil =
      Array.isArray(data.berhasil)
        ? data.berhasil
        : [];
  
  
    const errors =
      Array.isArray(data.error)
        ? data.error
        : [];

    Swal.close();
    
    let html = `
      <div style="
        text-align:left;
        max-height:500px;
        overflow-y:auto;
      ">
  
        <div style="
          padding:12px;
          background:#f3f4f6;
          border-radius:8px;
          margin-bottom:15px;
        ">
  
          <div>
            <b>Cabang :</b>
            ${cabang}
          </div>
  
          <div>
            <b>Total File :</b>
            ${selectedFile.length}
          </div>
  
          <div style="
            color:#16a34a;
          ">
            <b>Berhasil :</b>
            ${berhasil.length}
          </div>
  
          <div style="
            color:#dc2626;
          ">
            <b>Gagal :</b>
            ${errors.length}
          </div>
  
        </div>
  
    `;
  
    if (berhasil.length > 0) {
  
      html += `
  
        <h4 style="
          color:#16a34a;
          margin-bottom:10px;
        ">
          ✓ File Berhasil
        </h4>
  
      `;
  
  
      berhasil.forEach((item) => {
  
        html += `
  
          <div style="
            padding:10px;
            border:1px solid #bbf7d0;
            background:#f0fdf4;
            border-radius:8px;
            margin-bottom:8px;
          ">
  
            <div>
              <b>
                ${item.file_asli ?? "-"}
              </b>
            </div>
  
            <div style="
              color:#166534;
              font-size:12px;
              margin-top:4px;
            ">
              ${item.file_baru ?? "-"}
            </div>
  
            <div style="
              color:#6b7280;
              font-size:11px;
              margin-top:3px;
            ">
  
              Bank :
              ${item.nama_bank ?? "-"}
  
              <br/>
  
              No Rek :
              ${item.no_rek ?? "-"}
  
              <br/>
  
              Periode :
              ${item.periode ?? "-"}
  
            </div>
  
          </div>
  
        `;
  
      });
  
    }

    if (errors.length > 0) {
  
      html += `
  
        <h4 style="
          color:#dc2626;
          margin-top:15px;
          margin-bottom:10px;
        ">
          ✕ File Gagal / Dilewati
        </h4>
  
      `;
  
  
      errors.forEach((item) => {
  
        html += `
  
          <div style="
            padding:10px;
            border:1px solid #fecaca;
            background:#fef2f2;
            border-radius:8px;
            margin-bottom:8px;
          ">
  
            <div>
              <b>
                ${item.file ?? "-"}
              </b>
            </div>
  
            <div style="
              color:#b91c1c;
              font-size:12px;
              margin-top:4px;
            ">
              ${item.message ??
              "File gagal diproses."}
            </div>
  
          </div>
  
        `;
  
      });
  
    }
  
  
    html += `</div>`;
  
    let icon = "success";
  
    let title =
      "Upload Berhasil";
  
  
    if (
      berhasil.length > 0 &&
      errors.length > 0
    ) {
  
      icon = "warning";
  
      title =
        "Upload Selesai dengan Catatan";
  
    }
  
  
    if (
      berhasil.length === 0 &&
      errors.length > 0
    ) {
  
      icon = "error";
  
      title =
        "Upload Gagal";
  
    }
  
    await Swal.fire({
      icon,
      title,
      html,
      width: 750,
      confirmButtonText: "OK",
    });
  
    if (berhasil.length > 0) {
  
      try {
  
        await fetchStatementData();
  
      } catch (refreshError) {
  
        console.error(
          "Gagal refresh data setelah upload:",
          refreshError
        );
  
      }
  
    }
    
    try {
  
      resetFilePicker();
  
    } catch (resetError) {
  
      console.error(
        "Gagal reset file picker:",
        resetError
      );
  
    }

    setDrawerOpen(false);
    return;
  };

  const handleDelete = async (row) => {
    // =====================================================
    // 1. VALIDASI DATA
    // =====================================================
    if (!row?.id) {
      await Swal.fire({
        icon: "error",
        title: "Data tidak valid",
        text: "ID rekening koran tidak ditemukan.",
        confirmButtonText: "OK",
      });
  
      return;
    }
  
    // =====================================================
    // 2. KONFIRMASI HAPUS
    // =====================================================
    const confirmResult = await Swal.fire({
      icon: "warning",
      title: "Hapus Rekening Koran?",
      html: `
        <div style="text-align:left">
          <p>Anda akan menghapus rekening koran:</p>
  
          <p>
            <b>${row.file ?? "-"}</b>
          </p>
  
          <p>
            Cabang: <b>${row.cabang ?? "-"}</b>
          </p>
  
          <p style="color:#dc2626; margin-top:15px;">
            File dan data rekening koran akan dihapus.
          </p>
        </div>
      `,
      showCancelButton: true,
      confirmButtonText: "Ya, Hapus",
      cancelButtonText: "Batal",
      reverseButtons: true,
      confirmButtonColor: "#dc2626",
    });
  
    // User memilih Batal
    if (!confirmResult.isConfirmed) {
      return;
    }
  
    // =====================================================
    // 3. LOADING
    // =====================================================
    Swal.fire({
      title: "Menghapus...",
      text: "Mohon tunggu, rekening koran sedang dihapus.",
      allowOutsideClick: false,
      allowEscapeKey: false,
      showConfirmButton: false,
      didOpen: () => {
        Swal.showLoading();
      },
    });
  
    // =====================================================
    // 4. PROSES DELETE
    // =====================================================
    try {
      const response = await axios.delete(
        `/api/bank-statement/${row.id}`
      );
  
      // ===================================================
      // Pastikan backend menyatakan berhasil
      // ===================================================
      if (response.data?.success === false) {
        throw new Error(
          response.data?.message ||
          "Rekening koran gagal dihapus."
        );
      }
  
      // ===================================================
      // 5. REFRESH DATA TABEL
      //
      // Jika refresh gagal, JANGAN dianggap sebagai
      // kegagalan proses DELETE.
      // ===================================================
      try {
        const res = await fetch(
          `/api/Statement?cabang=${encodeURIComponent(cabang)}`
        );
  
        if (!res.ok) {
          throw new Error(
            `Gagal mengambil data tabel. Status: ${res.status}`
          );
        }
  
        const resultData = await res.json();
  
        setData(resultData);
        setFullData(resultData?.data ?? []);
  
      } catch (refreshError) {
        console.error(
          "Data berhasil dihapus, tetapi gagal refresh tabel:",
          refreshError
        );
  
        // Tidak menampilkan Swal error di sini.
        // Karena DELETE sudah berhasil.
      }
  
      // ===================================================
      // 6. SATU-SATUNYA ALERT SUKSES
      // ===================================================
      await Swal.fire({
        icon: "success",
        title: "Berhasil",
        text:
          response.data?.message ||
          "Rekening koran berhasil dihapus.",
        confirmButtonText: "OK",
      });
  
    } catch (error) {
  
      // ===================================================
      // 7. ERROR DELETE
      // ===================================================
      console.error("Delete RK error:", error);
  
      const message =
        error?.response?.data?.message ||
        error?.response?.data?.error ||
        error?.message ||
        "Terjadi kesalahan saat menghapus rekening koran.";
  
      // ===================================================
      // 8. SATU-SATUNYA ALERT GAGAL
      // ===================================================
      await Swal.fire({
        icon: "error",
        title: "Hapus Gagal",
        text: message,
        confirmButtonText: "OK",
      });
    }
  };

  return (
    <main className="flex-1 px-4 py-2 z-10 text-white">
      <div className="h-[calc(100vh-50px)] bg-white/60 rounded-lg p-4 shadow-lg text-gray-800 w-full">
        <div className="box-header text-white shadow-md flex items-center justify-center mx-auto mt-[-17px] mb-4 h-[60px] w-1/2 bg-blue-400 clip-path-custom">
            <h2 className="text-xl text-center font-semibold">Daftar Rekening Koran Cabang {cabang}</h2>
        </div>
        
        {showDrawer && (
          <BottomDrawer 
            isOpen={showDrawer} 
            onClose={() => setDrawerOpen(false)}
            height="300px"
          >
            <div className="flex flex-col h-full">
                <div className="overflow-y-auto flex-1 px-4 pt-4 pb-24">
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
                        accept={".pdf"}
                        onChange={handleFileChange}
                        multiple
                        className="hidden"
                      />
                    </label>
                  </div>
                </div>

                {/* === Footer === */}
                <div className="fixed bottom-0 left-[-6%] right-0 border-t flex items-center justify-between z-10">
                    <div className="trapezium-box text-white text-3xl shadow-md mt-[-8px] flex items-center justify-center h-[50px] w-[250px] bg-yellow-400">
                    Upload Bank Statement
                    </div>
                    
                    <div className="flex gap-2 px-2 ">
                      <button
                          type="submit"
                          onClick={handleSubmit}
                          className="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 flex items-center gap-2"
                      >
                          <FaUpload className="w-4 h-4" />Upload
                      </button>

                      <button
                        onClick={resetFilePicker}
                        className="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700 flex items-center gap-2"
                      >
                        <FaRecycle className="w-4 h-4" />Reset
                      </button>
                    </div>
                </div>
            </div>
          </BottomDrawer>
        )}

        <ReusableTable
            data={data}
            columns={columns}
            globalFilter={globalFilter}
            setGlobalFilter={setGlobalFilter}
            searchInputRef={searchRef}
            pageSizeOptions={[15, 20, 50, 100, "all"]}
            rightElement={
                <button
                    onClick={() => setDrawerOpen(true)}
                    className="flex items-center gap-2 bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded"
                    title="Upload Bank Statement"
                    >
                    <FaUpload className="w-5 h-5" />
                </button>
            }
        />
        
      </div>
    </main>
  );
}