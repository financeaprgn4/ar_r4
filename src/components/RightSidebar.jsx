import React, { useEffect, useState, useRef } from 'react';
import { FaTimes } from "react-icons/fa";
import { useNoRab } from '../contexts/NoRabContext';
import { useSidebar } from "./SidebarContext";

const RightSidebar = ({ isOpen, onClose }) => {
  const { isCollapsed } = useSidebar();
  const [data, setData] = useState([]);
  const [searchTerm, setSearchTerm] = useState("");
  const [selectedIndex, setSelectedIndex] = useState(0);
  const listRef = useRef(null);
  const searchInputRef = useRef(null);
  const cabang = sessionStorage.getItem("cabang");
  const { updateNoRab } = useNoRab();
  
  const filteredData = data.filter((item) =>
    item.nama_toko.toLowerCase().includes(searchTerm.toLowerCase()) ||
    item.no_rab.toLowerCase().includes(searchTerm.toLowerCase()) ||
    item.kd_toko.toLowerCase().includes(searchTerm.toLowerCase())
  );

  useEffect(() => {
    if (!cabang) return;
    fetch(`${import.meta.env.VITE_API_URL}/api/lpd-outs?cabang=${cabang}`)
      .then((res) => res.json())
      .then((res) => {
        setData(res.data);
      })
      .catch((err) => console.error("Fetch error:", err));
  }, [cabang]);

  useEffect(() => {
    if (isOpen && searchInputRef.current) {
      searchInputRef.current.focus();
    }
  }, [isOpen]);

  useEffect(() => {
    if (!isOpen) return;
  
    const handleKeyDown = (e) => {
      if (!isCollapsed) {
        return;
      }
  
      if (filteredData.length === 0) return;
  
      if (
        document.activeElement === searchInputRef.current &&
        e.key === "Tab"
      ) {
        e.preventDefault();
  
        const firstItem =
          listRef.current?.children[0];
  
        if (firstItem) {
          firstItem.focus();
        }
  
        setSelectedIndex(0);
        return;
      }
  
      if (e.key === "ArrowDown") {
        e.preventDefault();
  
        setSelectedIndex(prev =>
          (prev + 1) % filteredData.length
        );
  
      } else if (e.key === "ArrowUp") {
  
        e.preventDefault();
  
        setSelectedIndex(prev =>
          (prev - 1 + filteredData.length) %
          filteredData.length
        );
  
      } else if (e.key === "Enter") {
  
        const selected =
          filteredData[selectedIndex];
  
        if (selected) {
          updateNoRab(selected.no_rab);
        }
      }
    };
  
    window.addEventListener(
      "keydown",
      handleKeyDown
    );
  
    return () =>
      window.removeEventListener(
        "keydown",
        handleKeyDown
      );
  
  }, [
    filteredData,
    selectedIndex,
    updateNoRab,
    isOpen,
    isCollapsed
  ]);

  useEffect(() => {
    const el = listRef.current?.children[selectedIndex];
    el?.scrollIntoView({ behavior: 'smooth', block: 'center' });
  }, [selectedIndex]);

  useEffect(() => {
    const handleKeyDown = (e) => {
      if (!isCollapsed) {
        return;
      }

      if (e.altKey && e.key.toLowerCase() === 'c') {
        e.preventDefault();
        searchInputRef.current?.focus();
      }
    };

    document.addEventListener("keydown", handleKeyDown);
    return () => {
      document.removeEventListener("keydown", handleKeyDown);
    };
  }, [isCollapsed]);

  return (
    <div
      className={`fixed top-0 right-0 h-full w-full sm:w-1/3 bg-black/90 shadow-lg transform transition-transform duration-300 z-50 ${
        isOpen ? 'translate-x-0' : 'translate-x-full'
      }`}
    >
      <div className="relative flex justify-between items-center p-4 border-b border-gray-600">
        <button onClick={onClose} className="text-red-500 font-bold text-xl z-10">
          <FaTimes className="w-4 h-4" />
        </button>
        <h2 className="absolute left-1/2 transform -translate-x-1/2 text-lg font-semibold text-white">
          Daftar Toko Outs LPD
        </h2>
      </div>

      <div className="p-2 h-full flex flex-col">
        <input
          type="text"
          ref={searchInputRef}
          className="w-full p-2 mb-2 rounded text-sm bg-white text-black"
          placeholder="Cari nama toko atau no RAB..."
          value={searchTerm}
          onChange={(e) => {
            setSearchTerm(e.target.value);
            setSelectedIndex(0);
          }}
        />

        <ul
          ref={listRef}
          className="space-y-1 overflow-y-auto flex-1 focus:outline-none mb-12"
        >
          {filteredData.map((item, idx) => (
            <li
              key={idx}
              tabIndex={0}
              onClick={() => updateNoRab(item.no_rab)}
              className={`cursor-pointer p-3 rounded border text-sm transition-colors outline-none ${
                selectedIndex === idx ? 'bg-white text-black font-semibold' : 'text-white hover:bg-gray-700'
              }`}
              onFocus={() => setSelectedIndex(idx)}
            >
              <div><strong>{item.nama_toko}</strong> ({item.kd_toko})</div>
              <div className="text-xs opacity-80">Jenis: {item.jns_toko} — No RAB: {item.no_rab} — Status: {item.status}</div>
            </li>
          ))}
        </ul>
      </div>
    </div>
  );
};

export default RightSidebar;
