import React, { useEffect, useState, useRef } from 'react';
import Swal from "sweetalert2";
import { FaTimes, FaSave } from "react-icons/fa";
import { HiPencil, HiTrash } from "react-icons/hi";
import ReusableTable from "../components/ReusableTable";

export default function Mail() {
  const cabang = sessionStorage.getItem("cabang");
  const [data, setData] = useState([]);
  const [fullData, setFullData] = useState([]);
    
  const [globalFilter, setGlobalFilter] = useState("");
  const searchRef = useRef(null);
  const [id, setId] = useState(null);
  const [email, setEmail] = useState("");
  const [untuk, setUntuk] = useState("");
  const [subjek, setSubjek] = useState("");

  useEffect(() => {
    searchRef.current?.focus();
  }, [])

  useEffect(() => {
    if (!cabang) return;

    const fetchData = async () => {
      try {
        const res = await fetch(`/api/Mail?cabang=${cabang}`);
        const result = await res.json();
        setData(result);
        setFullData(result.data);
      } catch (err) {
        console.error('Gagal mengambil data:', err);
      }
    };

    fetchData();
  }, [cabang]);

  const handleClear = () => {
    setEmail("");
    setUntuk("");
    setSubjek("");
  };

  const handleEdit = (item) => {
    setId(Number(item.id));
    setEmail(item.mail || "");
    setUntuk(item.untuk || "");
    setSubjek(item.sub || "");
  };

  const handleDelete = async (item) => {
    Swal.fire({
        title: "Yakin ingin menghapus?",
        html: `
        <div class="text-center">
            <p>Email : ${item.mail}</p>
            <p>Untuk : ${item.untuk}</p>
            <p>Sub : ${item.sub}</p>
            <p>ID : ${item.id}</p>
        </div>
        `,
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#d33",
        cancelButtonColor: "#3085d6",
        confirmButtonText: "Ya, Hapus!",
        cancelButtonText: "Batal",
    }).then(async (result) => {
        if (result.isConfirmed) {
        try {
            const response = await fetch(`/api/Mail/${item.id}`, {
                method: "DELETE",
                headers: {
                "Content-Type": "application/json",
                Accept: "application/json",
                },
            });

            const data = await response.json();

            if (response.ok) {
                Swal.fire("Berhasil!", data.message || "Data berhasil dihapus.", "success");
                setData((prev) => prev.filter((e) => e.id !== item.id));
            } else {
                Swal.fire("Gagal", data.message || "Gagal menghapus data.", "error");
            }
            } catch (error) {
            Swal.fire("Error", "Terjadi kesalahan saat menghapus data.", "error");
            console.error(error);
            }
        }
    });
  };

  const columns = [
    {
        accessorKey: "cabang",
        header: "Cabang",
        cell: info => info.getValue(),
    },
    {
        accessorKey: "mail",
        header: "Email",
        cell: info => info.getValue(),
    },
    {
        accessorKey: "untuk",
        header: "Untuk",
        cell: info => info.getValue(),
    },
    {
        accessorKey: "sub",
        header: "Subjek",
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
        <form onSubmit={handleSubmit} className="space-y-4">
            <div className="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                {/* Alamat Email */}
                <div>
                    <label className="block font-medium mb-1">Alamat Email :</label>
                    <input
                        type="email"
                        value={email}
                        onChange={(e) => setEmail(e.target.value)}
                        required
                        className="w-full border px-2 py-1 rounded"
                    />
                </div>

                {/* Untuk */}
                <div>
                    <label className="block font-medium mb-1">Untuk :</label>
                    <select
                        value={untuk}
                        onChange={(e) => setUntuk(e.target.value)}
                        required
                        className="w-full border px-2 py-1 rounded"
                    >
                        <option value="">-- Pilih Salah Satu --</option>
                        <option value="LPD"> LPD </option>
                        <option value="Mutasi"> Mutasi Harian </option>
                        <option value="Pendebetan"> Konfirm Pendebetan </option>
                        <option value="Rekon_bank"> Rekon Bank </option>
                        <option value="Confirm_Renov"> Konfirmasi Renovasi Fisik </option>
                        <option value="CS"> CS LPD Region </option>
                    </select>
                </div>

                {/* Sub */}
                <div>
                    <label className="block font-medium mb-1">Sub :</label>
                    <select
                        value={subjek}
                        onChange={(e) => setSubjek(e.target.value)}
                        required
                        className="w-full border px-2 py-1 rounded"
                    >
                        <option value="">-- Pilih Salah Satu --</option>
                        <option value="TO"> To </option>
                        <option value="CC"> Cc </option>
                        <option value="BCC"> Bcc </option>
                    </select>
                </div>

                <div className="flex gap-2">
                    <button
                        type="submit"
                        className="btn btn-success text-white rounded flex items-center gap-2"
                    >
                        <FaSave className="w-4 h-4" />
                        Save
                    </button>
                    
                    <button
                        type="button"
                        onClick={handleClear}
                        className="btn btn-warning text-white rounded flex items-center gap-2"
                    >
                        <FaTimes className="w-4 h-4" />
                        Clear
                    </button>
                </div>
            </div>

        </form>
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
