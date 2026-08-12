import { useState, useEffect, useRef } from "react";
import Swal from "sweetalert2";
import { pdfjs, Document, Page } from "react-pdf";
import { PDFDocument } from "pdf-lib";

import pdfWorker from "pdfjs-dist/build/pdf.worker.min.js?url";
pdfjs.GlobalWorkerOptions.workerSrc = pdfWorker;

import { HiTrash, HiRefresh, HiUpload } from "react-icons/hi";
import { FaDownload, FaPrint, FaCloudUploadAlt } from "react-icons/fa";
import "react-pdf/dist/esm/Page/AnnotationLayer.css";
import "react-pdf/dist/esm/Page/TextLayer.css";

export default function Merge_pdf() {
  const [file, setFile] = useState(null);
  const [numPages, setNumPages] = useState(null);
  const [deletedPages, setDeletedPages] = useState([]);
  const [currentIndex, setCurrentIndex] = useState(0);
  const fileInputRef = useRef(null);
  const insertFileInputRef = useRef(null);
  const [pageWidth, setPageWidth] = useState(window.innerWidth * 0.4);
  const [pageInput, setPageInput] = useState("1");
  const [isTyping, setIsTyping] = useState(false);

  const [insertFile, setInsertFile] = useState(null);
  const [insertNumPages, setInsertNumPages] = useState(null);
  const [insertCurrentPage, setInsertCurrentPage] = useState(1);
  const [insertTarget, setInsertTarget] = useState("after");
  const [insertPageRange, setInsertPageRange] = useState([]);
  const [pageRangeStart, setPageRangeStart] = useState(1);
  const [pageRangeEnd, setPageRangeEnd] = useState(1);
  const [insertPageInput, setInsertPageInput] = useState(insertCurrentPage);
  const [isTypingInsert, setIsTypingInsert] = useState(false);

  useEffect(() => {
    const handleResize = () => setPageWidth(window.innerWidth * 0.4);
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

  const handleInsertFileChange = (e) => {
    const sel = e.target.files[0];
    if (sel?.type === "application/pdf") {
      setInsertFile(sel);
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

  const handleInsertDrop = (e) => {
    e.preventDefault();
    const insertdropped = e.dataTransfer.files[0];
    if (insertdropped?.type === "application/pdf") {
      setInsertFile(insertdropped);
    }
  };

  const onDocumentLoadSuccess = ({ numPages }) => {
    setNumPages(numPages);
    setCurrentIndex(0);
  };

  const onInsertDocLoad = ({ numPages }) => {
    setInsertNumPages(numPages);
    setInsertCurrentPage(1);
    
    const pageOptions = [1];
    setInsertPageRange(pageOptions);
  };

  const visiblePages = numPages
    ? Array.from({ length: numPages }, (_, i) => i + 1).filter((p) => !deletedPages.includes(p))
    : [];

  const currentPage = visiblePages[currentIndex] ?? visiblePages[0] ?? null;

  const handleDeletePage = (pg) => {
    if (!deletedPages.includes(pg)) {
      const updated = [...deletedPages, pg].sort((a, b) => a - b);
      setDeletedPages(updated);
      const newVisible = Array.from({ length: numPages }, (_, i) => i + 1).filter(
        (p) => !updated.includes(p)
      );
      
      if (newVisible.length > 0) {
        const newIndex = newVisible.findIndex((p) => p > pg);
        setCurrentIndex(newIndex >= 0 ? newIndex : newVisible.length - 1);
      } else {
        setCurrentIndex(0);
      }
    }
  };

  useEffect(() => {
    if (!isTyping) {
      const idx = visiblePages.indexOf(currentPage);
      if (idx >= 0) setPageInput((idx + 1).toString());
    }
  }, [currentPage, visiblePages, isTyping]);

  useEffect(() => {
    if (!isTypingInsert) {
        setInsertPageInput(insertCurrentPage);
    }
  }, [insertCurrentPage, isTypingInsert]);

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

  const handleInsertPage = async () => {
    if (!insertFile || !file) return;

    try {
        const mainBytes = await file.arrayBuffer();
        const insertBytes = await insertFile.arrayBuffer();

        const mainDoc = await PDFDocument.load(mainBytes);
        const insertDoc = await PDFDocument.load(insertBytes);

        const totalPages = mainDoc.getPageCount();
        const insertDocPageCount = insertDoc.getPageCount();

        // Validasi rentang halaman
        if (
            pageRangeStart < 1 ||
            pageRangeEnd > insertDocPageCount ||
            pageRangeStart > pageRangeEnd
        ) {
            throw new Error("Rentang halaman yang dipilih tidak valid.");
        }

        // Hapus halaman berdasarkan deletedPages sebelum menyisipkan
        const keptPagesIndices = Array.from({ length: totalPages }, (_, i) => i)
            .filter(i => !deletedPages.includes(i + 1)); // 1-based to 0-based

        const newMainDoc = await PDFDocument.create();
        const pagesToKeep = await newMainDoc.copyPages(mainDoc, keptPagesIndices);
        pagesToKeep.forEach(page => newMainDoc.addPage(page));

        const updatedTotalPages = newMainDoc.getPageCount();

        // Hitung indeks halaman dari insertDoc yang akan disalin
        const indicesToCopy = Array.from(
            { length: pageRangeEnd - pageRangeStart + 1 },
            (_, i) => i + pageRangeStart - 1
        );

        const copiedPages = await newMainDoc.copyPages(insertDoc, indicesToCopy);

        // Tentukan posisi penyisipan
        let insertIndex = 0;
        switch (insertTarget) {
            case 'first':
                insertIndex = 0;
                break;
            case 'before':
                insertIndex = Math.max(0, currentPage - 1);
                break;
            case 'after':
                insertIndex = Math.min(updatedTotalPages, currentPage);
                break;
            case 'last':
            default:
                insertIndex = updatedTotalPages;
                break;
        }

        copiedPages.forEach((page, i) => {
            newMainDoc.insertPage(insertIndex + i, page);
        });

        const updatedPdfBytes = await newMainDoc.save();
        const updatedPdfBlob = new Blob([updatedPdfBytes], { type: 'application/pdf' });
        const updatedPdfFile = new File([updatedPdfBlob], 'updated.pdf', { type: 'application/pdf' });

        setFile(updatedPdfFile);
        setDeletedPages([]); // reset halaman terhapus karena PDF sudah baru

        Swal.fire({
            icon: 'success',
            title: 'Berhasil',
            text: 'Halaman berhasil disisipkan.',
        });
    } catch (err) {
        console.error("Gagal menyisipkan halaman:", err);
        Swal.fire({
            icon: 'error',
            title: 'Gagal',
            text: 'Terjadi kesalahan saat menyisipkan halaman.',
        });
    }
  };

  return (
    <main className="flex-1 px-4 py-2 z-10">
      <div className="h-[calc(100vh-50px)] bg-white/60 rounded-lg p-4 shadow-lg w-full flex">
        {/* LEFT */}
        <div className="w-1/2 h-full pr-4 flex flex-col">
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
                            className="w-12 text-center border rounded px-1 py-0.5 text-sm mx-2"
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
                        <button onClick={handleDownload} className="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded flex items-center gap-2 justify-center">
                            <FaDownload />
                        </button>
                        <button onClick={handlePrint} className="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded flex items-center gap-2 justify-center">
                            <FaPrint />
                        </button>
                        <button
                            onClick={() => {
                                setFile(null);
                                setInsertFile(null);
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
                        <div className="relative mb-4 border rounded-lg overflow-hidden shadow bg-white">
                        <Page pageNumber={currentPage} width={pageWidth} />
                        <button
                            onClick={() => handleDeletePage(currentPage)}
                            className="absolute top-2 right-2 bg-red-600 text-white p-2 rounded-full shadow hover:bg-red-700"
                        >
                            <HiTrash size={18} />
                        </button>
                        </div>
                    )}
                    </Document>
                </div>
                </>
            )}
        </div>

        {/* RIGHT */}
        <div className="w-1/2 h-full flex flex-col">
            {file ? (
                !insertFile ? (
                <div
                    onClick={() => insertFileInputRef.current.click()}
                    onDrop={handleInsertDrop}
                    onDragOver={(e) => e.preventDefault()}
                    className="cursor-pointer border-2 border-dashed border-white rounded-lg p-6 text-center bg-gradient-to-br from-blue-500 to-indigo-600 text-white h-full flex flex-col justify-center items-center"
                >
                    <p><strong>Klik untuk unggah</strong> atau seret dan lepas file ke sini</p>
                    <input
                        type="file"
                        accept="application/pdf"
                        onChange={handleInsertFileChange}
                        ref={insertFileInputRef}
                        className="hidden"
                    />
                </div>
                ) : (
                <>
                    <div className="flex flex-col h-full w-full">
                        <div className="flex-grow overflow-auto bg-white rounded-lg">
                            <Document file={insertFile} onLoadSuccess={onInsertDocLoad}>
                                <Page
                                    pageNumber={insertCurrentPage}
                                    width={pageWidth}
                                    className="border-b"
                                />
                            </Document>
                        </div>

                        <div className="bg-gray-100 border-t border-gray-300 px-4 py-3 mt-2">
                            <div className="flex justify-between items-center text-sm text-gray-800 flex-wrap gap-2">
                                <div className="flex items-center gap-2">
                                    <button
                                        onClick={() => setInsertCurrentPage((p) => Math.max(p - 1, 1))}
                                        disabled={insertCurrentPage <= 1}
                                        className="bg-gray-300 hover:bg-gray-400 px-3 py-1 rounded text-sm"
                                    >
                                        {"<"}
                                    </button>
                                    <div className="text-sm flex items-center">
                                        Halaman
                                        <input
                                            type="text"
                                            value={insertPageInput}
                                            onFocus={() => setIsTypingInsert(true)}
                                            onChange={(e) => {
                                                const raw = e.target.value;
                                                if (/^\d*$/.test(raw)) {
                                                    setInsertPageInput(raw);
                                                }
                                            }}
                                            onBlur={() => {
                                                setIsTypingInsert(false);
                                                const page = Number(insertPageInput);
                                                if (page >= 1 && page <= insertNumPages) {
                                                    setInsertCurrentPage(page);
                                                } else {
                                                    setInsertPageInput(insertCurrentPage); // reset jika tidak valid
                                                }
                                            }}
                                            onKeyDown={(e) => {
                                            if (e.key === "Enter") {
                                                setIsTypingInsert(false);
                                                const page = Number(insertPageInput);
                                                if (page >= 1 && page <= insertNumPages) {
                                                setInsertCurrentPage(page);
                                                } else {
                                                setInsertPageInput(insertCurrentPage);
                                                }
                                            }
                                            }}
                                            className="w-12 text-center border rounded px-1 py-0.5 text-sm mx-2"
                                        />
                                        dari <strong className="ml-1">{insertNumPages}</strong>
                                    </div>

                                    <button
                                        onClick={() =>
                                            setInsertCurrentPage((p) => Math.min(p + 1, insertNumPages))
                                        }
                                        disabled={insertCurrentPage >= insertNumPages}
                                        className="bg-gray-300 hover:bg-gray-400 px-3 py-1 rounded text-sm"
                                    >
                                        {">"}
                                    </button>
                                </div>

                                <div className="flex items-center gap-2 flex-wrap">
                                    <div className="flex items-center gap-1">
                                        <label className="text-sm">Page Range:</label>
                                        <input
                                            type="text"
                                            value={pageRangeStart}
                                            onChange={(e) => {
                                                const raw = e.target.value;
                                                if (/^\d*$/.test(raw)) {
                                                const val = Number(raw);
                                                if (raw === "") {
                                                    setPageRangeStart("");
                                                } else {
                                                    const clamped = Math.max(1, Math.min(insertNumPages, val));
                                                    setPageRangeStart(clamped);
                                                    if (clamped > pageRangeEnd) setPageRangeEnd(clamped);
                                                }
                                                }
                                            }}
                                            onBlur={() => {
                                                if (pageRangeStart === "" || pageRangeStart < 1) {
                                                    setPageRangeStart(1);
                                                }
                                            }}
                                            className="w-16 px-2 py-1 border border-gray-300 rounded text-sm"
                                        />
                                        <span className="text-sm">to</span>
                                        <input
                                            type="text"
                                            value={pageRangeEnd}
                                            onChange={(e) => {
                                                const raw = e.target.value;
                                                if (/^\d*$/.test(raw)) {
                                                    setPageRangeEnd(raw === "" ? "" : Number(raw));
                                                }
                                            }}
                                            onBlur={() => {
                                                if (pageRangeEnd === "" || pageRangeEnd < pageRangeStart) {
                                                    setPageRangeEnd(pageRangeStart);
                                                } else if (pageRangeEnd > insertNumPages) {
                                                    setPageRangeEnd(insertNumPages);
                                                }
                                            }}

                                            className="w-16 px-2 py-1 border border-gray-300 rounded text-sm"
                                        />
                                    </div>

                                    <select
                                        value={insertTarget}
                                        onChange={(e) => setInsertTarget(e.target.value)}
                                        className="px-2 py-1 border border-gray-300 rounded text-sm"
                                    >
                                        <option value="first">First Page</option>
                                        <option value="before">Before Shown Page</option>
                                        <option value="after">After Shown Page</option>
                                        <option value="last">End Page</option>
                                    </select>

                                    <button
                                        onClick={handleInsertPage}
                                        className="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 flex items-center gap-1"
                                        title="Insert Page"
                                    >
                                        <HiUpload className="w-4 h-4" />
                                    </button>

                                    <button
                                        onClick={() => {
                                        setInsertFile(null);
                                        setInsertCurrentPage(1);
                                        }}
                                        className="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700 flex items-center gap-2"
                                        title="Reset"
                                    >
                                        <HiRefresh className="w-4 h-4" />
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </>
                )
            ) : (
                <div className="flex-grow flex items-center justify-center text-gray-600 text-center">
                    <p>Silakan unggah file PDF terlebih dahulu.</p>
                </div>
            )}
        </div>
      </div>
    </main>
  );
}
