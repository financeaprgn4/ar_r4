import { useState, useEffect, useRef } from "react";
import Swal from "sweetalert2";
import { pdfjs, Document, Page } from "react-pdf";
import { PDFDocument } from 'pdf-lib'
import { HiRefresh } from "react-icons/hi";
import { FaFile, FaDownload, FaPrint, FaCloudUploadAlt } from "react-icons/fa";
import "react-pdf/dist/esm/Page/AnnotationLayer.css";
import "react-pdf/dist/esm/Page/TextLayer.css";
import { processEstimasiFromPdf } from "../utility/pdfEstimasi";
import { 
  handleCekPDF, 
  parseTableBBT 
} from "../utility/pdfModal";

import pdfWorker from "pdfjs-dist/build/pdf.worker.min.js?url";
pdfjs.GlobalWorkerOptions.workerSrc = pdfWorker;

export default function Lpd_pdf() {
  const [file, setFile] = useState(null);
  const [numPages, setNumPages] = useState(null);
  const [deletedPages, setDeletedPages] = useState([]);
  const [currentIndex, setCurrentIndex] = useState(0);
  const fileInputRef = useRef(null);
  const [pageWidth, setPageWidth] = useState(window.innerWidth * 0.9);
  const [pageInput, setPageInput] = useState("1");
  const [isTyping, setIsTyping] = useState(false);

  const handleCekModal = async () => {
    try {
      const rows = await handleCekPDF({
        file,
        keywordRegex: /SET\s+INVESTASI/i,
        rowParser: parseTableBBT,
        pdfjs,
      });

      // Simpan ke backend
      const response = await fetch(
        `/api/lpd-modal-sync`,
        {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify(rows),
        }
      );

      if (!response.ok) throw new Error("Gagal simpan ke server");
      const result = await response.json();

      Swal.fire({
        icon: "success",
        title: "Berhasil",
        text: result.message || "Data berhasil disimpan ke server!",
      });
    } catch (err) {
      Swal.fire({
        icon: "error",
        title: "Gagal",
        text: err.message || "Terjadi kesalahan.",
      });
    }
  };

  const handleCekEst = async () => {
    try {
      if (!file) {
        Swal.fire("Oops", "Silakan unggah PDF dulu.", "info");
        return;
      }

      const { noRAB, rows } = await processEstimasiFromPdf(file);

      if (rows.length === 0 && !noRAB) {
        Swal.fire("Data tidak ditemukan", "Tidak ada kategori estimasi atau No RAB yang cocok.", "warning");
        return;
      }

      const payload = { no_rab: noRAB, estimasi: rows };

      const response = await fetch(`/api/lpd-estimasi`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload),
      });

      const result = await response.json();
      Swal.fire({ icon: "success", title: "Berhasil", text: result.message });
    } catch (err) {
      console.error("handleCekEst error:", err);
      Swal.fire("Error", err.message || "Terjadi kesalahan saat membaca PDF", "error");
    }
  };

  useEffect(() => {
    const handleResize = () => setPageWidth(window.innerWidth * 0.9);
    window.addEventListener("resize", handleResize);
    return () => window.removeEventListener("resize", handleResize);
  }, []);

  const handleFileChange = (e) => {
    const sel = e.target.files[0];
    if (sel?.type === "application/pdf") {
        setFile(sel);
        setDeletedPages([]);
        setCurrentIndex(0);
    }
  };

  const handleDrop = (e) => {
    e.preventDefault();
    const dropped = e.dataTransfer.files[0];
    if (dropped?.type === "application/pdf") {
        setFile(dropped);
        setDeletedPages([]);
        setCurrentIndex(0);
    }
  };

  const onDocumentLoadSuccess = ({ numPages }) => {
    setNumPages(numPages);
    setCurrentIndex(0);
  };

  const visiblePages = numPages
    ? Array.from({ length: numPages }, (_, i) => i + 1).filter((p) => !deletedPages.includes(p))
    : [];

  const currentPage = visiblePages[currentIndex] ?? visiblePages[0] ?? null;

  useEffect(() => {
    if (!isTyping) {
      const idx = visiblePages.indexOf(currentPage);
      if (idx >= 0) setPageInput((idx + 1).toString());
    }
  }, [currentPage, visiblePages, isTyping]);

  const handleDownload = async () => {
    try {
        const existingPdfBytes = await (file instanceof File
            ? file.arrayBuffer()
            : fetch(file).then((res) => res.arrayBuffer()));

        const existingPdf = await PDFDocument.load(existingPdfBytes);
        const totalPages = existingPdf.getPageCount();

        const newPdf = await PDFDocument.create();

        for (let i = 0; i < totalPages; i++) {
        const pageNumber = i + 1; // 1-based index untuk preview
        if (!deletedPages.includes(pageNumber)) {
            const [copiedPage] = await newPdf.copyPages(existingPdf, [i]);
            newPdf.addPage(copiedPage);
        }
        }

        const newPdfBytes = await newPdf.save();
        const blob = new Blob([newPdfBytes], { type: "application/pdf" });
        const blobUrl = URL.createObjectURL(blob);

        const link = document.createElement("a");
        link.href = blobUrl;
        link.download = "Merge_File.pdf";
        link.click();
    } catch (error) {
        console.error("Gagal mengunduh PDF:", error);
        alert("Terjadi kesalahan saat mengunduh PDF.");
    }
  };

  const handlePrint = () => {
    const iframe = document.createElement("iframe");
    iframe.style.display = "none";
    iframe.src = file;
    document.body.appendChild(iframe);
    iframe.onload = () => iframe.contentWindow.print();
  };

  const goToPrevPage = () => {
    if (currentIndex > 0) setCurrentIndex((prev) => prev - 1);
  };

  const goToNextPage = () => {
    if (currentIndex < visiblePages.length - 1) setCurrentIndex((prev) => prev + 1);
  };

  const handlePageInputChange = (e) => {
    const val = e.target.value;
    if (/^\d*$/.test(val)) {
      setIsTyping(true);
      setPageInput(val);
    }
  };

  const handlePageInputConfirm = () => {
    const num = parseInt(pageInput);
    if (!isNaN(num) && num >= 1 && num <= visiblePages.length) {
      setCurrentIndex(num - 1);
    } else {
      const safeIdx = visiblePages.indexOf(currentPage);
      if (safeIdx >= 0) setPageInput((safeIdx + 1).toString());
    }
  };

  return (
    <main className="flex-1 px-4 py-2 z-10">
      <div className="h-[calc(100vh-50px)] bg-white/60 rounded-lg p-4 shadow-lg w-full flex">
        {/* LEFT */}
        <div className="w-full h-full pr-4 flex flex-col">
            {!file ? (
                <div
                    onClick={() => fileInputRef.current.click()}
                    onDrop={handleDrop}
                    onDragOver={(e) => e.preventDefault()}
                    className="cursor-pointer border-2 border-white rounded-lg p-6 text-center text-blue-500 h-full flex flex-col justify-center items-center"
                >
                    <FaCloudUploadAlt className="h-12 w-12 text-blue-500 mb-2" />
                    <p><strong>Klik untuk unggah</strong> atau seret dan lepas file ke sini</p>
                    <input
                        type="file"
                        accept="application/pdf"
                        onChange={handleFileChange}
                        ref={fileInputRef}
                        className="hidden"
                    />
                </div>
            ) : (
                <>
                <div className="w-full bg-gray-100 border border-gray-300 rounded px-4 py-3 mb-2 flex flex-wrap items-center justify-between">
                    <div className="text-sm flex items-center gap-1">
                        <button
                            onClick={() => {
                                if (visiblePages.length > 0) {
                                setCurrentIndex(0);
                                }
                            }}
                            className="bg-gray-300 hover:bg-gray-400 px-3 py-1 rounded text-sm"
                            disabled={currentIndex === 0}
                            >
                            {'<<'}
                        </button>
                        <button
                            onClick={goToPrevPage}
                            className="bg-gray-300 hover:bg-gray-400 px-3 py-1 rounded"
                            disabled={currentIndex <= 0}
                            >
                            {'<'}
                        </button>
                        <span>Halaman</span>
                        <input
                            type="text"
                            value={pageInput}
                            onFocus={() => setIsTyping(true)}
                            onChange={handlePageInputChange}
                            onBlur={() => {
                                setIsTyping(false);
                                handlePageInputConfirm();
                            }}
                            onKeyDown={(e) => {
                                if (e.key === "Enter") {
                                setIsTyping(false);
                                handlePageInputConfirm();
                                }
                            }}
                            className="w-12 text-center border rounded px-1 py-0.9 text-sm mx-2"
                        />
                        dari <strong className="ml-1">{visiblePages.length}</strong>
                        <button
                            onClick={goToNextPage}
                            className="bg-gray-300 hover:bg-gray-400 px-3 py-1 rounded text-sm"
                            disabled={currentIndex >= visiblePages.length - 1}
                            >
                            {'>'}
                        </button>
                        <button
                            onClick={() => {
                                if (visiblePages.length > 0) {
                                setCurrentIndex(visiblePages.length - 1);
                                }
                            }}
                            className="bg-gray-300 hover:bg-gray-400 px-3 py-1 rounded text-sm"
                            disabled={currentIndex >= visiblePages.length - 1}
                            >
                            {'>>'}
                        </button>
                    </div>
                    
                    <div className="flex gap-1">
                      <button onClick={handleCekEst} className="bg-cyan-600 hover:bg-cyan-700 text-white px-4 py-2 rounded flex items-center gap-2 justify-center">
                        <FaFile /> Import Estimasi
                      </button>
                      <button onClick={handleCekModal} className="bg-yellow-600 hover:bg-yellow-700 text-white px-4 py-2 rounded flex items-center gap-2 justify-center">
                          <FaFile /> Import Modal
                      </button>
                      <button onClick={handleDownload} className="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded flex items-center gap-2 justify-center">
                          <FaDownload />
                      </button>
                      <button onClick={handlePrint} className="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded flex items-center gap-2 justify-center">
                          <FaPrint />
                      </button>
                      <button
                          onClick={() => {
                              setFile(null);
                          }}
                          className="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700 flex items-center gap-2"
                          title="Reset"
                      >
                          <HiRefresh className="w-4 h-4" />
                      </button>
                    </div>
                </div>

                <div className="flex-1 overflow-auto">
                    <Document file={file} onLoadSuccess={onDocumentLoadSuccess}>
                    {currentPage && (
                        <div className="relative border rounded-lg overflow-hidden shadow bg-white">
                            <Page pageNumber={currentPage} width={pageWidth} />
                        </div>
                    )}
                    </Document>
                </div>
                </>
            )}
        </div>
      </div>
    </main>
  );
}
