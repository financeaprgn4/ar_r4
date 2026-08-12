import React, { useRef, useEffect, useState } from 'react';
import Swal from "sweetalert2";
import { FaPlus, FaTimes, FaSave, FaEye, FaEyeSlash } from "react-icons/fa";
import { useCabang } from "../contexts/CabangContext";
import { HiPencil, HiTrash } from "react-icons/hi";
import ReusableTable from "../components/ReusableTable";

export default function Bank() {
  const { cabang } = useCabang();
  
  return (
    <main className="flex-1 px-4 py-2 z-10 text-white">
        <div className="h-[calc(100vh-50px)] bg-white/60 rounded-lg p-4 shadow-lg text-gray-800 overflow-hidden">
            {/* HEADER */}
            <div className="box-header text-white shadow-md flex items-center justify-center mx-auto mt-[-17px] mb-4 h-[60px] w-1/2 bg-blue-400 clip-path-custom">
                <h2 className="text-xl font-semibold">
                    Daftar Rekening Bank Cabang {cabang}
                </h2>
            </div>
        </div>
    </main>
    );
}
