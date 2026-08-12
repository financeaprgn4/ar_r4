import React, { useEffect, useState, useRef } from 'react';
import Swal from "sweetalert2";
import { FaTimes, FaSave } from "react-icons/fa";
import { HiPencil, HiTrash } from "react-icons/hi";
import ReusableTable from "../components/ReusableTable";

export default function periode() {
  const cabang = sessionStorage.getItem("cabang");
  const [data, setData] = useState([]);
  const [fullData, setFullData] = useState([]);
    
  const [globalFilter, setGlobalFilter] = useState("");
  const searchRef = useRef(null);
  const [id, setId] = useState(null);
  const [kategori, setKategori] = useState("");
  const [periode, setPeriode] = useState("");
  const [startdate, setStartdate] = useState("");
  const [enddate, setEnddate] = useState("");
  const [status, setStatus] = useState("");

  useEffect(() => {
    searchRef.current?.focus();
  }, [])

  useEffect(() => {
    if (!cabang) return;

    const fetchData = async () => {
      try {
        const res = await fetch(`/api/periodelist?cabang=${cabang}`);
        const result = await res.json();
        setData(result);
        setFullData(result.data);
        console.log('Data : ', data);
      } catch (err) {
        console.error('Gagal mengambil data:', err);
      }
    };

    fetchData();
  }, [cabang]);

  const handleClear = () => {
    setKategori("");
    setPeriode("");
    setStartdate("");
    setEnddate("");
    setStatus("");
  };

  const handleEdit = (item) => {
    setId(Number(item.id));
    setKategori(item.kategori || "");
    setPeriode(item.periode || "");
    setStartdate(item.start_date || "");
    setEnddate(item.end_date || "");
    setStatus(item.status || "");
  };

  const columns = [
    {
        accessorKey: "Cabang",
        header: "Cabang",
        cell: info => info.getValue(),
    },
    {
        accessorKey: "kategori",
        header: "Kategori",
        cell: info => info.getValue(),
    },
    {
        accessorKey: "periode",
        header: "Periode",
        cell: info => info.getValue(),
    },
    {
        accessorKey: "start_date",
        header: "Tgl Awal",
        cell: info => info.getValue(),
    },
    {
        accessorKey: "end_date",
        header: "Tgl Akhir",
        cell: info => info.getValue(),
    },
    {
        accessorKey: "status",
        header: "Status",
        cell: info => info.getValue(),
    },
    {
        header: "Opsi",
        id: "actions",
        cell: ({ row }) => (
        <div className="flex space-x-2 justify-center">
            <button
            onClick={() => handleEdit(row.original)}
            className="px-2 py-1 bg-blue-500 text-white rounded flex items-center space-x-1"
            >
            <HiPencil className="w-5 h-5" />Edit
            </button>
            <button
            onClick={() => handleDelete(row.original)}
            className="px-2 py-1 bg-red-500 text-white rounded flex items-center space-x-1"
            >
            <HiTrash className="w-5 h-5" /> Hapus
            </button>
        </div>
        ),
    },
  ];

  const handleSubmit = async (e) => {
    e.preventDefault();

    const payload = {
        id: id ? Number(id) : null,
        cabang: cabang,
        mail: email,
        untuk: untuk,
        sub: subjek,
    };
    console.log("payload final:", payload, typeof payload.id);
    try {
        const res = await fetch(`/api/mail-add`, {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(payload),
        });

        const result = await res.json();
        if (!res.ok) throw new Error(result.message || "Gagal menyimpan");

        Swal.fire("Berhasil", result.message, "success");

        // Buat objek data baru yang sesuai dengan payload + id dari backend jika ada
        const updatedItem = {
            id: result.data?.id || payload.id || Date.now(),
            cabang: payload.cabang,
            mail: payload.mail,
            untuk: payload.untuk,
            sub: payload.sub,
        };

        // Update state data, ganti jika id sudah ada, atau tambah jika belum ada
        setData((prevData = []) => {
        const index = prevData.findIndex(item => item.id === updatedItem.id);
        if (index !== -1) {
            const updated = [...prevData];
            updated[index] = updatedItem;
            return updated;
        }
        return [...prevData, updatedItem];
        });

        // Update juga fullData untuk keperluan pencarian
        setFullData((prevData = []) => {
        const index = prevData.findIndex(item => item.id === updatedItem.id);
        if (index !== -1) {
            const updated = [...prevData];
            updated[index] = updatedItem;
            return updated;
        }
        return [...prevData, updatedItem];
        });

        // Reset form
        setId(null);
        setEmail("");
        setUntuk("");
        setSubjek("");
    } catch (error) {
        console.error("Error simpan:", error);
        Swal.fire("Error", error.message || "Terjadi kesalahan", "error");
    }
  };

  return (
    <main className="flex-1 px-4 py-2 z-10 text-white">
      <div className="bg-white/60 rounded-lg p-4 shadow-lg text-gray-800 w-full">
        

      </div>

      <div className="h-[calc(100vh-150px)] bg-white/60 rounded-lg p-4 shadow-lg text-gray-800 w-full mt-2">
        <ReusableTable
            data={data}
            columns={columns}
            globalFilter={globalFilter}
            setGlobalFilter={setGlobalFilter}
            searchInputRef={searchRef}
        />
      </div>
    </main>
    );

}
