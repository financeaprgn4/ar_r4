import React, { useRef, useEffect, useState } from 'react';
import Swal from "sweetalert2";
import { FaPlus, FaTimes, FaSave, FaEye, FaEyeSlash } from "react-icons/fa";
import { HiPencil, HiTrash } from "react-icons/hi";
import ReusableTable from "../components/ReusableTable";

export default function Hutang_dagang() {
    const cabang = sessionStorage.getItem("cabang");

    return (
        <main className="flex-1 px-4 py-2 z-10 text-white">
            <div className="h-[calc(100vh-50px)] bg-white/60 rounded-lg p-4 shadow-lg text-gray-800 w-full">
                <div className="box-header text-white shadow-md flex items-center justify-center mx-auto mt-[-17px] mb-4 h-[60px] w-1/2 bg-blue-400 clip-path-custom">
                    <h2 className="text-xl text-center font-semibold">Laporan Hutang Dagang Cabang {cabang}</h2>
                </div>

            </div>
        </main>
    );
}
