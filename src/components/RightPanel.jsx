import React from 'react';
import { FaBars } from "react-icons/fa";

export default function RightPanel({ show, onClose, children }) {
  return (
    <div
      className={`fixed top-0 right-0 h-full w-[48rem] bg-white/70 backdrop-blur-sm shadow-lg z-50 p-4
        transition-all duration-500 ease-in-out transform overflow-hidden
        ${show ? "translate-x-0" : "translate-x-full"}
      `}
    >
      <button
        className="absolute top-2 left-2 text-gray-600 hover:text-black"
        onClick={onClose}
      >
        <FaBars className="h-6 w-6" />
      </button>

      <div className="overflow-y-auto max-h-[calc(100vh-80px)] pr-2">
        {children}
      </div>
    </div>
  );
}
