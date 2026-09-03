import React, { useRef, useEffect, useState } from 'react';
import axios from "../config/axiosInstance";
import Swal from "sweetalert2";
import { useNavigate } from 'react-router-dom';
import { formatDate, formatRupiah } from "../utility/textFormatter";
import { useSidebar } from "../components/SidebarContext";
import RightSidebar from '../components/RightSidebar';
import Menu_LPD from '../components/Menu_LPD';
import BottomDrawer from "../components/BottomDrawer";
import { useNoRab } from "../contexts/NoRabContext";
import { useLpdDetail } from '../hooks/useLpdDetail';
import LpdIdentitas from "../components/LpdIdentitas";
import { HiPencil, HiRefresh, HiTrash, HiMenu, HiPlus,HiClipboard, HiSwitchHorizontal } from "react-icons/hi";
import { useSpring, animated } from '@react-spring/web';
import { FaSave, FaTools, FaWallet, FaPiggyBank } from 'react-icons/fa';
import TableLoading from "../components/TableLoading";

export default function LPDDetail() {
  const navigate = useNavigate();
  const { noRab: no_rab } = useNoRab();
  const [showDrawer, setShowDrawer] = useState(false);
  const rightSidebarFirstFocusableRef = useRef(null);
  const menuFirstFocusableRef = useRef(null);
  const inputRefs = useRef([]);
  const [isMenuOpen, setMenuOpen] = useState(false);
  const handleOpenMenu = () => setMenuOpen(true);
  const handleCloseMenu = () => setMenuOpen(false);
  const [activeRowIndex, setActiveRowIndex] = useState(null);  
  const { setIsCollapsed } = useSidebar();
  const [drawerMode, setDrawerMode] = useState('add');
  const [editingData, setEditingData] = useState(null);

  const {
    modalData,
    modaldetailData,
    identitas,
    berkas,
    totalModal,
    totalEstimasi,
    fetchData,
    initialLoading
  } = useLpdDetail(no_rab);
  
  useEffect(() => {
    if (no_rab) {
      fetchData();
    }
  }, [fetchData, no_rab]);

  useEffect(() => {
    if (drawerMode === 'edit' && editingData) {
      const formattedRows = [{
        id: editingData.id || '',
        no_bbt: editingData.bbt || '',
        tgl_bbt: editingData.tgl_bbt || '',
        keterangan: editingData.keterangan || '',
        amount: formatAmount(editingData.nilai || ''),
      }];

      setRows(formattedRows);
      setActiveRowIndex(0);
    } else {
      clearForm();
    }
  }, [drawerMode, editingData]);

  useEffect(() => {
    setIsCollapsed(true);
  }, [setIsCollapsed]);

  const [rightsidebarOpen, setRightSidebarOpen] = useState(false);
  const handleSwitch = () => {
    setRightSidebarOpen(true);
  };

  const closeRightSidebar = () => {
    setRightSidebarOpen(false);
  };
  
  const openDrawerToEdit = (item) => {
    setDrawerMode("edit");
    setEditingData(item);   // <-- trigger useEffect
    setShowDrawer(true);
  };

  useEffect(() => {
    const handleKeyDown = (e) => {
      if (e.key === 'Escape') {
        if (showDrawer) {
          setShowDrawer(false);
          clearForm();
        }
        if (rightsidebarOpen) {
          setRightSidebarOpen(false);
        }
        if (isMenuOpen) {
          setMenuOpen(false);
        }
      }

      if (e.altKey && e.code === 'KeyC') {
        e.preventDefault();
        setRightSidebarOpen(true);
        setShowDrawer(false);
        setTimeout(() => {
          rightSidebarFirstFocusableRef.current?.focus();
        }, 0);
      }

      if (e.altKey && e.code === 'KeyM') {
        e.preventDefault();
        setMenuOpen(true);
        setRightSidebarOpen(false);
        setShowDrawer(false);
        setTimeout(() => {
          menuFirstFocusableRef.current?.focus();
        }, 0);
      }

      if (e.altKey && e.code === 'KeyA') {
        e.preventDefault();
        
        // hanya boleh jalan kalau status NEW
        if (identitas?.status === "NEW") {
          if (showDrawer) {
            handleAddRow(rowsRef.current);
          } else {
            setShowDrawer(true);
            setDrawerMode('input');
          }
        } else {
          Swal.fire({
            icon: "error",
            title: "Gagal",
            text: `Status LPD toko ini adalah ${identitas.status}, User tidak diperkenankan untuk melakukan perubahan`,
            confirmButtonText: "OK"
          });
        }
      }
      
      if (e.altKey && e.code === 'KeyD') {
        e.preventDefault();
        if (activeRowIndex !== null) {
          handleRemoveRow(activeRowIndex);
        }
      }

      if (e.altKey && e.code === 'KeyR') {
        e.preventDefault();
        clearForm();
      }

      if (e.altKey && e.code === 'KeyP') {
        e.preventDefault();
        navigate('/lpd-detail');
      }

      if (e.altKey && e.code === 'KeyS') {
        e.preventDefault();
        navigate('/sarana_toko');
      }
    };

    window.addEventListener('keydown', handleKeyDown);

    return () => {
      window.removeEventListener('keydown', handleKeyDown);
    };
  }, [showDrawer, rightsidebarOpen, isMenuOpen, activeRowIndex, identitas]);
  
  const springPekByFrcsee = useSpring({
    from: { val: 0 },
    to: { val: modalData[0]?.pek_by_frcsee || 0 },
    config: { duration: 1000 }
  });

  const springSetor = useSpring({
    from: { val: 0 },
    to: { val: modalData[0]?.setor || 0 },
    config: { duration: 1000 }
  });

  const springCadangan = useSpring({
    from: { val: 0 },
    to: { val: modalData[0]?.cad_dana || 0 },
    config: { duration: 1000 }
  });

  const [rows, setRows] = useState([
    { no_bbt: "", tgl_bbt: "", keterangan: "", amount: "" }
  ]);
  
  const rowsRef = useRef(rows);
  useEffect(() => {
    rowsRef.current = rows;
  }, [rows]);
  
  const clearForm = () => {
    setRows([{ no_bbt: "", tgl_bbt: "", keterangan: "", amount: "" }]);
    setEditingData(null);
    setDrawerMode('add');
  };

  const handleInputChange = (index, field, value) => {
    const updated = [...rows];
    updated[index][field] = value;
    setRows(updated);
  };

  const formatAmount = (value) => {
    const str = String(value || '');
    let num = str.replace(/\D/g, '');
    num = num.replace(/^0+/, '');
    return num.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
  };

  const handleChange = (index, field, value) => {
    const newRows = [...rows];
    newRows[index][field] = value;
    setRows(newRows);
  };

  const handleAddRow = (currentRows = rows) => {
    const isAnyEmpty = currentRows.some(
      (row) =>
        !row.no_bbt?.trim() ||
        !row.tgl_bbt ||
        !row.keterangan?.trim() ||
        !row.amount?.trim()
    );

    if (isAnyEmpty) {
      Swal.fire('Gagal!', "Mohon lengkapi semua kolom pada baris yang ada sebelum menambah baris baru.", 'error');
      return;
    }

    const newRow = {
      no_bbt: '',
      tgl_bbt: '',
      keterangan: '',
      amount: ''
    };

    setRows((prev) => [...prev, newRow]);

    setTimeout(() => {
      const lastIndex = currentRows.length;
      inputRefs.current[lastIndex]?.focus();
    }, 0);
  };


  const handleRemoveRow = (indexToRemove) => {
    if (rowsRef.current.length <= 1) {
      Swal.fire('Gagal!', "Minimal harus ada satu baris!", 'error');
      return;
    }
    
    setRows((prev) => prev.filter((_, i) => i !== indexToRemove));
    inputRefs.current.splice(indexToRemove, 1);
    setActiveRowIndex(null);
  };

  useEffect(() => {
    if (showDrawer) {
      setTimeout(() => {
        if (inputRefs.current[0]) {
          inputRefs.current[0].focus();
        }
      }, 0);
    }
  }, [showDrawer]);
  
  const handleSubmit = async (e) => {
    e.preventDefault();

    for (let i = 0; i < rows.length; i++) {
      const { no_bbt, tgl_bbt, keterangan, amount } = rows[i];
      if (!no_bbt || !tgl_bbt || !keterangan || !amount) {
        Swal.fire({
          icon: 'error',
          title: 'Validasi Gagal',
          text: `Semua kolom pada baris ke-${i + 1} harus diisi!`,
        });
        return;
      }
    }

    try {
      const payload = rows.map((row) => ({
        id: row.id,
        rab: no_rab,
        bbt: row.no_bbt,
        tgl_bbt: row.tgl_bbt,
        nilai: parseFloat(row.amount.replace(/[^0-9.-]+/g, "")),
        keterangan: row.keterangan,
      }));
      
      if (drawerMode === 'add') {
        await axios.post(`/api/lpd-modal-add`, payload);
      } else if (drawerMode === 'edit') {
        await axios.post(`/api/lpd-modal-edit`, payload);
      }
      // Tampilkan notifikasi sukses
      Swal.fire({
        icon: 'success',
        title: 'Data Tersimpan',
        text: 'Semua baris berhasil disimpan ke server.',
      });

      setShowDrawer(false);
      fetchData();
      clearForm();
    } catch (error) {
      console.error('Gagal menyimpan:', error);
      Swal.fire({
        icon: 'error',
        title: 'Gagal menyimpan data',
        text: 'Silakan periksa koneksi atau hubungi admin.',
      });
    }
  };

  const handleDeleteBBT = async (bbtValue) => {
    const confirm = await Swal.fire({
      title: 'Yakin ingin menghapus?',
      text: `Data BBT ${bbtValue} akan dihapus.`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Ya, hapus!',
      cancelButtonText: 'Batal',
    });

    if (confirm.isConfirmed) {
      try {
        const response = await axios.post(`/api/delete-bbt`, {
          bbt: bbtValue,
          rab: no_rab,
        });

        if (response.data.success) {
          Swal.fire('Berhasil!', response.data.message, 'success');

          setModalDetailData((prev) =>
            prev.filter((item) => item.bbt !== bbtValue)
          );

          fetchData();
        } else {
          Swal.fire('Gagal!', response.data.message, 'error');
        }
      } catch (error) {
        Swal.fire('Error!', 'Terjadi kesalahan koneksi ke server.', 'error');
      }
    }
  };

  return (
    <main className="flex-1 px-2 py-2 z-10 text-white">
      <div className="h-[calc(100vh-50px)] bg-white/60 rounded-lg p-4 shadow-lg text-gray-800 w-full overflow-y-auto">
        <Menu_LPD
          isOpen={isMenuOpen}
          onClose={handleCloseMenu}
          onOpenDrawer={(mode) => {
            if (identitas?.status !== "NEW") {
              Swal.fire({
                icon: "error",
                title: "Gagal",
                text: `Status LPD toko ini adalah ${identitas.status}, User tidak diperkenankan untuk melakukan perubahan`,
              });
              return;
            }

            setDrawerMode(mode);
            setShowDrawer(true);
          }}
          berkas={berkas}
        />
        
        <RightSidebar
          isOpen={rightsidebarOpen}
          onClose={closeRightSidebar}>
        </RightSidebar>
        
        {initialLoading ? (
          <TableLoading text="Memuat Detail LPD..." />
        ) : (
          <>
            <div className="relative flex items-center justify-center mb-4">
              <h2 className="text-xl font-bold">Modal Detail</h2>
              
              <div className="absolute right-0">
                <button
                  onClick={handleOpenMenu}
                  className="rounded bg-gray-700 hover:bg-gray-800 text-white px-2 py-1"
                  title="Buka Menu"
                >
                  <HiMenu className="w-5 h-5" />
                </button>
              </div>
            </div>
            
            <LpdIdentitas
              identitas={identitas}
              no_rab={no_rab}
              onSwitch={handleSwitch}
            />
            
            <div className="grid grid-cols-4 gap-4">
              {/* Kolom Kiri - 1/4 */}
              <div className="col-span-1 space-y-4">
                {/* Card 1 */}
                <div className="relative bg-blue-100 p-4 rounded shadow h-32 transform transition duration-300 ease-in-out hover:scale-105 hover:shadow-xl">
                  <animated.p style={springPekByFrcsee} className="absolute top-2 left-4 text-4xl font-extrabold text-blue-900">
                    {springPekByFrcsee.val.to((val) => formatRupiah(Math.floor(val)))}
                  </animated.p>
                  <div className="absolute bottom-2 right-4 flex flex-col items-end text-blue-800">
                    <FaTools className="w-20 h-20 text-2xl mb-1" />
                    <h3 className="text-sm font-semibold">Pekerjaan Oleh Frcsee</h3>
                  </div>
                </div>

                {/* Card 2 */}
                <div className="relative bg-green-100 p-4 rounded shadow h-32 transform transition duration-300 ease-in-out hover:scale-105 hover:shadow-xl">
                  <animated.p style={springSetor} className="absolute top-2 left-4 text-3xl font-extrabold text-green-900">
                    {springSetor.val.to((val) => formatRupiah(Math.floor(val)))}
                  </animated.p>
                  <div className="absolute bottom-2 right-4 flex flex-col items-end text-green-800">
                    <FaWallet className="w-20 h-20 text-2xl mb-1" />
                    <h3 className="text-sm font-semibold">Setor</h3>
                  </div>
                </div>

                {/* Card 3 */}
                <div className="relative bg-yellow-100 p-4 rounded shadow h-32 transform transition duration-300 ease-in-out hover:scale-105 hover:shadow-xl">
                  <animated.p style={springCadangan} className="absolute top-2 left-4 text-3xl font-extrabold text-yellow-900">
                    {springCadangan.val.to((val) => formatRupiah(Math.floor(val)))}
                  </animated.p>
                  <div className="absolute bottom-2 right-4 flex flex-col items-end text-yellow-800">
                    <FaPiggyBank className="w-20 h-20 text-2xl mb-1" />
                    <h3 className="text-sm font-semibold">Cadangan Dana</h3>
                  </div>
                </div>
              </div>

              {/* Kolom Kanan - 3/4 */}
              <div className="col-span-3 overflow-x-auto">
                <table className="min-w-full border border-gray-300 rounded text-sm">
                    <thead className="bg-blue-400 text-center">
                        <tr>
                            <th className="px-3 py-2 border">No</th>
                            <th className="px-3 py-2 border">No BBT</th>
                            <th className="px-3 py-2 border">Tgl BBT</th>
                            <th className="px-3 py-2 border">Keterangan</th>
                            <th className="px-3 py-2 border">Nilai</th>
                        </tr>
                    </thead>
                    <tbody>
                      {modaldetailData.length > 0 ? (
                      modaldetailData.map((item, index) => (
                          <tr key={index}>
                              <td className="px-3 py-2 border text-center">{index + 1}</td>
                              <td className="px-3 py-2 border">
                                {item.bbt}

                                {identitas?.status === "NEW" && (
                                  <>
                                  <button
                                    type='button'
                                    className="bg-red-600 px-1 text-white rounded hover:bg-red-700 ml-2"
                                    title='Hapus BBT'
                                    onClick={() => handleDeleteBBT(item.bbt)}
                                  >
                                    <HiTrash className="w-4 h-4" />
                                  </button>

                                  <button
                                    type='button'
                                    className="bg-yellow-400 px-1 text-white rounded hover:bg-yellow-500 ml-2"
                                    title='Edit BBT'
                                    onClick={() => openDrawerToEdit(item)}
                                  >
                                    <HiPencil className="w-4 h-4" />
                                  </button>
                                  </>
                                )}
                              </td>
                              <td className="px-3 py-2 border">{formatDate(item.tgl_bbt)}</td>
                              <td className="px-3 py-2 border">{item.keterangan}</td>
                              <td className="px-3 py-2 border text-right">{formatRupiah(item.nilai)}</td>
                          </tr>
                      ))
                      ) : (
                      <tr>
                          <td colSpan="5" className="px-3 py-4 text-center text-gray-500">Tidak ada data modal.</td>
                      </tr>
                      )}
                    </tbody>
                    <tfoot>
                        <tr className="bg-blue-400 font-semibold">
                            <td colSpan="4" className="px-3 py-2 text-right">Total Cash</td>
                            <td className="px-3 py-2 border text-right">{formatRupiah(totalModal)}</td>
                        </tr>
                        <tr className="bg-yellow-300 font-semibold">
                            <td colSpan="4" className="px-3 py-2 text-right">Pekerjaan By Frcsee</td>
                            <td className="px-3 py-2 border text-right">{formatRupiah(modalData[0]?.pek_by_frcsee || 0)}</td>
                        </tr>
                        <tr className="bg-cyan-300 font-semibold">
                            <td colSpan="4" className="px-3 py-2 text-right">Total Modal</td>
                            <td className="px-3 py-2 border text-right">{formatRupiah(modalData[0]?.pek_by_frcsee + totalModal || 0)}</td>
                        </tr>
                        <tr className="bg-green-400 font-semibold">
                            <td colSpan="4" className="px-3 py-2 text-right">Total RAB</td>
                            <td className="px-3 py-2 border text-right">{formatRupiah(totalEstimasi)}</td>
                        </tr>
                        <tr className="bg-red-600 font-semibold text-white">
                            <td colSpan="4" className="px-3 py-2 text-right">Selisih</td>
                            <td className="px-3 py-2 border text-right">{formatRupiah(modalData[0]?.pek_by_frcsee + totalModal - totalEstimasi)}</td>
                        </tr>
                    </tfoot>
                </table>
              </div>
            </div>
          </>
        )}
        
        {showDrawer && (
          <BottomDrawer isOpen={showDrawer} onClose={() => setShowDrawer(false)} height = '290px' >
            <div className="flex flex-col h-full">
              <div className="overflow-y-auto flex-1 px-4 pt-4 pb-24">
                <form className="space-y-4">
                  <div className="grid grid-cols-[auto_1fr_1fr_1fr_1fr_auto] gap-2 border-b pb-2 font-semibold text-sm text-white text-center">
                    <div className="w-6">No</div>
                    <div>No BBT</div>
                    <div>Tanggal</div>
                    <div>Keterangan</div>
                    <div>Amount</div>
                  </div>

                  {rows.map((row, index) => (
                    <div key={index} className="grid grid-cols-[auto_1fr_1fr_1fr_1fr_auto] gap-2 items-center">
                      {/* Nomor urut */}
                      <div className="text-center text-sm text-white">{index + 1}</div>

                      {/* Input No BBT */}
                      <input
                        type="text"
                        placeholder="No BBT"
                        value={row.no_bbt}
                        onChange={(e) => handleInputChange(index, 'no_bbt', e.target.value)}
                        onFocus={() => setActiveRowIndex(index)}
                        className="border px-2 py-1 rounded w-full"
                        ref={(el) => (inputRefs.current[index] = el)}
                      />

                      {/* Input Tanggal */}
                      <input
                        type="date"
                        className="border px-2 py-1 rounded w-full"
                        value={row.tgl_bbt}
                        onChange={(e) => handleChange(index, "tgl_bbt", e.target.value)}
                        onFocus={() => setActiveRowIndex(index)}
                      />

                      {/* Select Keterangan */}
                      <select
                        value={row.keterangan}
                        onChange={(e) => handleInputChange(index, 'keterangan', e.target.value)}
                        onFocus={() => setActiveRowIndex(index)}
                        className="border px-2 py-1 rounded w-full"
                      >
                        <option value="">Pilih</option>
                        <option value="Setor">Setor</option>
                        <option value="Cadangan">Cadangan Dana</option>
                      </select>

                      {/* Input Amount */}
                      <input
                        type="text"
                        placeholder="Amount"
                        value={row.amount}
                        onChange={(e) =>
                          handleInputChange(index, 'amount', formatAmount(e.target.value))
                        }
                        onFocus={() => setActiveRowIndex(index)}
                        className="border px-2 py-1 rounded text-right w-full"
                      />

                      {drawerMode !== 'edit' && (
                        <button
                          type="button"
                          onClick={() => handleRemoveRow(index)}
                          className="bg-red-500 text-white p-2 rounded hover:bg-red-600 flex items-center justify-center w-8"
                          title="Hapus Baris"
                        >
                          <HiTrash className="w-4 h-4" />
                        </button>
                      )}
                    </div>
                  ))}
                </form>
              </div>

              <div className="fixed bottom-0 left-[-6%] right-0 border-t flex items-center justify-between z-10">
                <div className="trapezium-box text-white text-3xl shadow-md mt-[-8px] flex items-center justify-center h-[50px] w-[250px] bg-yellow-400">
                  {drawerMode === 'edit' ? 'Edit Modal' : 'Input Modal'}
                </div>
                
                <div className="flex gap-2 px-2">
                  <button
                    type="submit"
                    onClick={handleSubmit}
                    className="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 flex items-center gap-2"
                  >
                    <FaSave className="w-4 h-4" />{drawerMode === 'edit' ? 'Update' : 'Save'}
                  </button>
                  {drawerMode !== 'edit' && (
                    <>
                      <button
                        type="button"
                        onClick={handleAddRow}
                        className="bg-yellow-500 text-white px-4 py-2 rounded hover:bg-yellow-600 flex items-center gap-2"
                      >
                        <HiPlus className="w-4 h-4" />Add Row
                      </button>

                      <button
                        type="button"
                        onClick={clearForm}
                        className="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700 flex items-center gap-2"
                      >
                        <HiRefresh className="w-4 h-4" />Reset
                      </button>
                    </>
                  )}
                </div>
              </div>
            </div>
          </BottomDrawer>
        )}
      </div>
    </main>
  );
}
