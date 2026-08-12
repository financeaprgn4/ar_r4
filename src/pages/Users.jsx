import React, { useRef, useEffect, useState } from 'react';
import Swal from "sweetalert2";
import { FaPlus, FaTimes, FaSave, FaEye, FaEyeSlash } from "react-icons/fa";
import { HiPencil, HiTrash } from "react-icons/hi";
import ReusableTable from "../components/ReusableTable";

export default function Users() {
  const cabang = sessionStorage.getItem("cabang");
  const [showForm, setShowForm] = useState(false);
  const [data, setData] = useState([]);
  const [fullData, setFullData] = useState([]);
  
  const [showPassword, setShowPassword] = useState(false);
  const [selectedImage, setSelectedImage] = useState(null);
  const [imageError, setImageError] = useState('');
  const fileInputRef = useRef(null);
  const [password, setPassword] = useState('')
  const [passwordError, setPasswordError] = useState('');

  const [nama, setNama] = useState("");
  const [username, setUsername] = useState("");
  const [level, setLevel] = useState("");
  const [ip_komp, setIpKomp] = useState("");
  const [foto, setFoto] = useState(null);

  const [form, setForm] = useState({
    nama: '',
    username: '',
    pass: '',
    level: '',
    cabang: '',
    ip_komp: '',
    password: '',
  });

  const handleImageChange = (e) => {
    const file = e.target.files[0];
    if (file) {
        const allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
        if (!allowedTypes.includes(file.type)) {
            setImageError('Format harus JPG, JPEG, atau PNG');
            setSelectedImage(null);
            return;
        }
    
        if (file.size > 1024 * 1024) {
            setImageError('Ukuran maksimal 1MB');
            setSelectedImage(null);
            return;
        }

        setImageError('');
        const reader = new FileReader();
        reader.onloadend = () => setSelectedImage(reader.result);
        reader.readAsDataURL(file);
    }
  };

  const handleResetImage = () => {
    setSelectedImage(null);
    setImageError('');
    if (fileInputRef.current) {
        fileInputRef.current.value = null;
    }
  };

  useEffect(() => {
    if (!cabang) return;

    const fetchData = async () => {
    try {
        const res = await fetch(`/api/Users?cabang=${cabang}`);
        const result = await res.json();
        setData(result);
        setFullData(result.data);
    } catch (err) {
        console.error('Gagal mengambil data:', err);
    }
    };

    fetchData();
  }, [cabang]);
  
  const handleChange = (e) => {
    const { name, value } = e.target;

    if (name === 'password') {
        if (value.includes(' ')) {
            setPasswordError("Password tidak boleh mengandung spasi.");
        } else if (value.length < 6) {
            setPasswordError("Password minimal 6 karakter.");
        } else {
            setPasswordError("");
        }
    }

    setForm((prev) => ({
        ...prev,
        [name]: value
    }));
  };

  const handleSubmit = async (e) => {
    e.preventDefault();

    const formData = new FormData();
    for (const key in form) {
        formData.append(key, form[key]);
    }
    if (foto) {
        formData.append("foto", foto);
    }
    
    for (let pair of formData.entries()) {
        console.log(pair[0], pair[1]);
    }
    try {
        const res = await fetch(`/api/Users-add`, {
        method: "POST",
        body: formData,
        credentials: "include",
        });

        let result;
        const contentType = res.headers.get("content-type");

        if (contentType && contentType.includes("application/json")) {
        result = await res.json();
        } else {
        result = { message: await res.text() };
        }

        if (!res.ok) throw new Error(result.message || "Gagal menyimpan");

        Swal.fire("Berhasil", result.message, "success");

        // Buat objek baru dari hasil simpan
        const updatedItem = {
            id: result.data?.id || form.id || Date.now(),
            nama: result.data?.nama || form.nama,
            username: result.data?.username || form.username,
            pass: result.data?.pass || form.pass,         // ✅ ambil dari response
            level_user: result.data?.level_user || form.level,
            cabang: result.data?.cabang || form.cabang,
            ip: result.data?.ip || form.ip_komp,
            foto: result.data?.foto || null,
        };

        // Update state data
        setData((prevData = []) => {
        const index = prevData.findIndex(item => item.id === updatedItem.id);
        if (index !== -1) {
            const updated = [...prevData];
            updated[index] = updatedItem;
            return updated;
        }
        return [...prevData, updatedItem];
        });

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
        setForm({
            nama: "",
            username: "",
            password: "",
            level: "",
            cabang: "",
            ip_komp: "",
        });

        handleResetImage();
    } catch (error) {
        console.error("Error simpan:", error);
        Swal.fire("Error", error.message || "Terjadi kesalahan", "error");
    }
  };

  const columns = [
    {
        accessorKey: "cabang",
        header: "Cabang",
        cell: info => info.getValue(),
    },
    {
        accessorKey: "nama",
        header: "Nama",
        cell: info => info.getValue(),
    },
    {
        accessorKey: "username",
        header: "Username",
        cell: info => info.getValue(),
    },
    {
        accessorKey: "level_user",
        header: "Level",
        cell: info => info.getValue(),
    },
    {
        accessorKey: "ip",
        header: "IP Komp",
        cell: info => info.getValue(),
    },
    {
        accessorKey: "pass",
        header: "Password",
        cell: info => info.getValue(),
    },
    {
        accessorKey: "foto",
        header: "Foto",
        cell: info => info.getValue(),
    },
  ];

  return (
    <main className="flex-1 px-4 py-2 z-10 text-white">
      <div className="h-[calc(100vh-50px)] bg-white/60 rounded-lg p-4 shadow-lg text-gray-800 w-full">
        <div className="relative flex items-center justify-center mb-4">
          <h2 className="text-xl text-center font-semibold mb-3">Daftar User Program Cabang {cabang}</h2>
        </div>

        <div className={`${showForm ? "w-3/4" : "w-full"} transition-all duration-300`}>
            <ReusableTable
                data={data}
                columns={columns}
                rightElement={
                    <button
                        onClick={() => setShowForm(true)}
                        className="flex items-center gap-2 bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded"
                        >
                        <FaPlus className="w-4 h-4" /> Add
                    </button>
                }
            />
        </div>

        {showForm && (
            <div className="w-1/4 bg-white/60 p-4 rounded-xl shadow-md mt-4">
                <button
                    type="button"
                    onClick={() => setShowForm(false)}
                    className="absolute text-gray-600 hover:text-gray-900"
                    title="Sembunyikan Form"
                >
                    <FaTimes className="w-4 h-4" />
                </button>
                <h2 className="text-lg font-semibold mb-4 text-center text-blue-800">Form User</h2>
                {/* Konten form akan kamu isi sendiri di sini */}
                <form className="text-black" onSubmit={handleSubmit}>
                    {/* Nama */}
                    <div className="mb-3">
                        <label className="block text-sm text-blue-800">Nama</label>
                        <input
                        type="text"
                        className="w-full p-2 rounded"
                        name="nama"
                        value={form.nama}
                        onChange={handleChange}
                        required
                        />
                    </div>

                    {/* Username */}
                    <div className="mb-3">
                        <label className="block text-sm text-blue-800">Username</label>
                        <input
                        type="text"
                        className="w-full p-2 rounded"
                        name="username"
                        value={form.username}
                        onChange={handleChange}
                        required
                        />
                    </div>

                    {/* Level */}
                    <div className="mb-3">
                        <label className="block text-sm text-blue-800">Level</label>
                        <select
                        className="w-full p-2 rounded"
                        name="level"
                        value={form.level}
                        onChange={handleChange}
                        required
                        >
                        <option value="">Pilih Level</option>
                        <option value="Cabang">Cabang</option>
                        <option value="Region">Region</option>
                        </select>
                    </div>

                    {/* Cabang */}
                    <div className="mb-3">
                        <label className="block text-sm text-blue-800">Cabang</label>
                        <select
                        className="w-full p-2 rounded"
                        name="cabang"
                        value={form.cabang}
                        onChange={handleChange}
                        required
                        >
                        <option value="">Pilih Cabang</option>
                        <option value="Semarang">Semarang</option>
                        <option value="Klaten">Klaten</option>
                        <option value="Yogyakarta">Yogyakarta</option>
                        <option value="Medan">Medan</option>
                        <option value="Pontianak">Pontianak</option>
                        </select>
                    </div>

                    {/* IP Komp */}
                    <div className="mb-3">
                        <label className="block text-sm text-blue-800">IP Komp</label>
                        <input
                        type="text"
                        className="w-full p-2 rounded"
                        name="ip_komp"
                        value={form.ip_komp}
                        onChange={handleChange}
                        required
                        />
                    </div>

                    {/* Password */}
                    <div className="mb-3">
                        <label className="block text-sm text-blue-800">Password</label>
                        <div className="relative">
                        <input
                            type={showPassword ? "text" : "password"}
                            name="password"
                            value={form.password}
                            onChange={handleChange}
                            className="w-full p-2 rounded pr-10"
                            required
                        />
                        <button
                            type="button"
                            onClick={() => setShowPassword(!showPassword)}
                            className="absolute top-1/2 right-3 transform -translate-y-1/2 text-gray-600"
                        >
                            {showPassword ? <FaEyeSlash /> : <FaEye />}
                        </button>
                        </div>
                        {passwordError && (
                        <p className="text-red-600 text-sm mt-1">{passwordError}</p>
                        )}
                    </div>

                    {/* Foto */}
                    <div className="mb-3 relative">
                        <label className="block text-sm text-blue-800">Foto</label>
                        <input
                            type="file"
                            accept=".jpg,.jpeg,.png"
                            onChange={handleImageChange}
                            ref={fileInputRef}
                            className="w-full p-2 rounded bg-white"
                        />
                        {imageError && (
                        <p className="text-red-600 text-sm mt-1">{imageError}</p>
                        )}
                        <p className="text-xs text-red-600 mt-1">
                        Note: File yang didukung (.jpg/.jpeg/.png), ukuran max 1MB
                        </p>
                        {selectedImage && (
                        <div className="relative mt-2 inline-block">
                            <img
                                src={selectedImage}
                                alt="Preview"
                                className="rounded shadow max-h-40"
                            />
                            <button
                                type="button"
                                onClick={handleResetImage}
                                className="absolute top-1 right-1 bg-white text-red-600 rounded-full w-6 h-6 flex items-center justify-center text-xs font-bold shadow leading-none"
                                title="Hapus foto"
                            >
                            <FaTimes className="w-4 h-4" />
                            </button>
                        </div>
                        )}
                    </div>

                    {/* Tombol Submit */}
                    <button
                        type="submit"
                        className="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 flex items-center justify-center gap-2 rounded mt-2 w-full"
                    >
                        <FaSave className="w-4 h-4" /> Simpan
                    </button>
                </form>
            </div>
        )}
      </div>
    </main>
    );
}
