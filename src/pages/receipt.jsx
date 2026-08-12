import React, { useRef, useEffect, useState } from 'react';
import Swal from "sweetalert2";
import axios from "../config/axiosInstance";
import { useCabang } from "../contexts/CabangContext";
import { useSidebar } from "../components/SidebarContext";
import BottomDrawer from "../components/BottomDrawer";
import TopDrawer from "../components/TopDrawer";
import ReusableTable from "../components/ReusableTable";
import { FaCheckCircle, FaCloudUploadAlt, FaUpload, FaRecycle } from "react-icons/fa";

export default function Receipt() {
  const { cabang } = useCabang();
  const { isCollapsed, setIsCollapsed } = useSidebar();
  const [uploading, setUploading] = useState(false);
  const [showDrawer, setDrawerOpen] = useState(false);
  const [openTopDrawer, setOpenTopDrawer] = useState(false);
  const labelRef = useRef(null);
  const [selectedFile, setSelectedFile] = useState(null);
  const fileInputRef = useRef(null);

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
    if (!showDrawer) {
        resetFilePicker();
    }
  }, [showDrawer]);

  const handleSubmit = async () => {
    if (uploading) return;

    // ==========================
    // Validasi File
    // ==========================
    if (!selectedFile || selectedFile.length === 0) {
        Swal.fire({
            icon: "warning",
            title: "Peringatan",
            text: "Silakan pilih minimal satu file."
        });
        return;
    }

    // ==========================
    // Validasi Cabang
    // ==========================
    if (!cabang) {
        Swal.fire({
            icon: "warning",
            title: "Peringatan",
            text: "Silakan pilih cabang terlebih dahulu."
        });
        return;
    }

    // ==========================
    // Tambahkan CSS Loader (sekali saja)
    // ==========================
    if (!document.getElementById("receipt-loader-style")) {

        const style = document.createElement("style");
        style.id = "receipt-loader-style";

        style.innerHTML = `
            .receipt-loader{
                border:4px solid #f3f3f3;
                border-top:4px solid #3498db;
                border-radius:50%;
                width:20px;
                height:20px;
                display:inline-block;
                animation:receipt-spin .8s linear infinite;
                margin-right:8px;
                vertical-align:middle;
            }

            @keyframes receipt-spin{
                0%{transform:rotate(0deg);}
                100%{transform:rotate(360deg);}
            }
        `;

        document.head.appendChild(style);
    }

    // ==========================
    // Form Data
    // ==========================
    const formData = new FormData();

    selectedFile.forEach(file => {
        formData.append("files[]", file);
    });

    formData.append("cabang", cabang);

    setUploading(true);

    // ==========================
    // Loading
    // ==========================
    Swal.fire({
        title: `Import Receipt - ${cabang}`,
        html: `
            <div style="margin-top:15px">

                <div style="margin-bottom:10px">
                    Cabang : <b>${cabang}</b>
                </div>

                <div id="upload-text" style="margin-bottom:12px">
                    Preparing upload...
                </div>

                <div id="processing" style="display:none">
                    <span class="receipt-loader"></span>
                    Memvalidasi file dan mengimpor data...
                </div>

            </div>
        `,
        allowOutsideClick: false,
        allowEscapeKey: false,
        showConfirmButton: false
    });

    try {

        const response = await axios.post(
            "/api/import-receipt",
            formData,
            {
                headers: {
                    "Content-Type": "multipart/form-data"
                },

                onUploadProgress: (progressEvent) => {

                    if (!progressEvent.total) return;

                    const percent = Math.round(
                        (progressEvent.loaded * 100) /
                        progressEvent.total
                    );

                    const container = Swal.getHtmlContainer();

                    if (!container) return;

                    const uploadText = container.querySelector("#upload-text");

                    if (uploadText) {
                        uploadText.innerHTML =
                            `Uploading File... <b>${percent}%</b>`;
                    }

                    if (percent >= 100) {

                        if (uploadText) {
                            uploadText.innerHTML =
                                `Memvalidasi data cabang <b>${cabang}</b>...`;
                        }

                        const processing = container.querySelector("#processing");

                        if (processing) {
                            processing.style.display = "block";
                        }
                    }
                }
            }
        );

        // ==========================
        // Error Per File
        // ==========================
        const errors = response?.data?.errors;

        if (Array.isArray(errors) && errors.length > 0) {

            let html = "<div style='text-align:left'><ul>";

            errors.forEach(err => {

                html += `<li style="margin-bottom:10px"><b>${err.file ?? "Unknown File"}</b>`;

                if (Array.isArray(err.issues)) {

                    html += "<ul>";

                    err.issues.forEach(issue => {
                        html += `<li>${issue}</li>`;
                    });

                    html += "</ul>";

                } else if (typeof err.issues === "string") {

                    html += `<br>${err.issues}`;

                } else if (err.message) {

                    html += `<br>${err.message}`;

                }

                html += "</li>";

            });

            html += "</ul></div>";

            Swal.fire({
                icon: "warning",
                title: "Sebagian File Gagal Diproses",
                html: html,
                width: 700
            }).then(() => {

                fetchData();
                resetFilePicker();
                setDrawerOpen(false);
            });

            return;
        }

        // ==========================
        // Success
        // ==========================
        Swal.fire({
            icon: "success",
            title: "Import Berhasil",
            html: `
                <p>${response.data.message ?? "Import Receipt berhasil."}</p>
            `,
            confirmButtonText: "OK"
        }).then(() => {
          resetFilePicker();
          setDrawerOpen(false);
        });

    } catch (err) {

        console.error(err);

        let message = "Terjadi kesalahan saat proses import.";

        if (err.response?.data?.message) {

            message = err.response.data.message;

        } else if (err.response?.data?.errors) {

            if (Array.isArray(err.response.data.errors)) {

                message = err.response.data.errors
                    .map(item => item.message || item.reason || JSON.stringify(item))
                    .join("\n");

            } else {

                message = String(err.response.data.errors);

            }

        } else if (err.message) {

            message = err.message;

        }

        Swal.fire({
            icon: "error",
            title: "Import Gagal",
            text: message,
            width: 650
        });

    } finally {

        setUploading(false);

    }
  };

  useEffect(() => {
    const handleKeyDown = (e) => {
        const { key, code, altKey } = e;
        if (key === 'Escape') {
            if (showDrawer) {
              setDrawerOpen(false);
            }
        }

        if (altKey) {
            switch (code) {
              case 'KeyU':
                e.preventDefault();
                
                setDrawerOpen(true);
                break;
            
              case 'KeyX':
                e.preventDefault();
                
                if(isCollapsed){
                  setIsCollapsed(false);
                }else{
                  setIsCollapsed(true);
                }
                break;

            default:
                break;
            }
        }

        if (
            key === 'Enter' &&
            document.activeElement === labelRef.current && fileInputRef.current
        ) {
            fileInputRef.current.click();
        }
    };

    window.addEventListener('keydown', handleKeyDown);

    return () => {
    window.removeEventListener('keydown', handleKeyDown);
    };
  }, [showDrawer, isCollapsed]);

  return (
    <main className="flex-1 px-4 py-2 z-10 text-white">
        <div className="h-[calc(100vh-50px)] bg-white/60 rounded-lg p-4 shadow-lg text-gray-800 overflow-hidden">
            {/* HEADER */}
            <div className="box-header text-white shadow-md flex items-center justify-center mx-auto mt-[-17px] mb-4 h-[60px] w-1/2 bg-blue-400 clip-path-custom">
                <h2 className="text-xl font-semibold">
                    Rekonsiliasi Bank AR Cabang {cabang}
                </h2>
            </div>

            <TopDrawer isOpen={openTopDrawer} onClose={() => setOpenTopDrawer(false)}>
              
            </TopDrawer>

            {showDrawer && (
              <BottomDrawer
                isOpen={showDrawer}
                onClose={() => setDrawerOpen(false)}
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
                        accept={".csv, .txt"}
                        onChange={handleFileChange}
                        multiple
                        className="hidden"
                        />
                    </label>
                    </div>
    
                    {/* === Footer === */}
                    <div className="fixed bottom-0 left-[-6%] right-0 border-t flex items-center justify-between z-10">
                      <div className="trapezium-box text-white text-3xl shadow-md mt-[-8px] flex items-center justify-center h-[50px] w-[280px] bg-yellow-400">
                          Upload Summary Receipt
                      </div>
      
                      <div className="flex gap-2 px-2">
                          <button
                            type="submit"
                            onClick={handleSubmit}
                            disabled={uploading}
                            className={`px-4 py-2 rounded flex items-center gap-2 text-white
                              ${uploading
                                  ? "bg-gray-500 cursor-not-allowed"
                                  : "bg-green-600 hover:bg-green-700"
                              }`}
                          >
                            <FaUpload className="w-4 h-4" />{uploading ? "Uploading..." : "Upload"}
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
        </div>
    </main>
    );
}
