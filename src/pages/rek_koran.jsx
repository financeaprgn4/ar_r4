import React, { useEffect, useState, useMemo } from "react";
import BottomDrawer from "../components/BottomDrawer";
import ReusableTable from "../components/ReusableTable";
import Swal from "sweetalert2";
import { FaTrash, FaDownload, FaUpload } from "react-icons/fa";
import { fileUrl } from "../config/fileUrl"

export default function Rek_koran() {
  const [showDrawer, setDrawerOpen] = useState(false);
  const cabang = sessionStorage.getItem("cabang");
  const [data, setData] = useState([]);
  const [fullData, setFullData] = useState([]);
  useEffect(() => {
    if (!cabang) return;

    const fetchData = async () => {
    try {
        const res = await fetch(`/api/Statement?cabang=${cabang}`);
        const result = await res.json();
        setData(result);
        setFullData(result.data);
    } catch (err) {
        console.error('Gagal mengambil data:', err);
    }
    };

    fetchData();
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
                // onClick={() => handleDelete(row.original)}
                className="px-2 py-1 bg-red-500 text-white rounded flex items-center space-x-1"
                >
                <FaTrash className="w-5 h-5" />
                </button>
            </div>
        ),
    },    
  ];

  const handleChange = (e, key, type = "text") => {
    let value = e.target.value;

    if (type === "rupiah") {
      value = parseRupiah(value);
      if (value.length > 1 && value.startsWith("0")) {
        value = value.replace(/^0+/, "");
      }
    }

    setInputValues((prev) => ({
      ...prev,
      [key]: value,
    }));
  };

  useEffect(() => {
    const handleKeyDown = (e) => {
      if (e.key === 'Escape') {
        if (showDrawer) {
          setDrawerOpen(false);
        }
      }
    };

    window.addEventListener('keydown', handleKeyDown);

    return () => {
      window.removeEventListener('keydown', handleKeyDown);
    };
  }, [showDrawer]);
  
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
            height = {'200px'}
          >
            <div className="flex flex-col h-full">
                <div className="overflow-y-auto flex-1 px-4 pt-4 pb-24">

                </div>

                <div className="fixed bottom-0 left-[-6%] right-0 border-t flex items-center justify-between z-10">
                    <div className="trapezium-box text-white text-3xl shadow-md mt-[-8px] flex items-center justify-center h-[50px] w-[250px] bg-yellow-400">
                    Upload Bank Statement
                    </div>
                    
                    <div className="flex gap-2 px-2 ">
                      <button
                          type="submit"
                          //onClick={handleSubmit}
                          className="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 flex items-center gap-2"
                      >
                          <FaUpload className="w-4 h-4" />Upload
                      </button>
                    </div>
                </div>
            </div>
          </BottomDrawer>
        )}

        <ReusableTable
            data={data}
            columns={columns}
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