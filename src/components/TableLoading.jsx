import React from "react";

export default function TableLoading({
  text = "Memuat Data...",
  size = 48,
  className = "",
}) {
  return (
    <div
      className={`flex flex-col items-center justify-center py-20 gap-4 ${className}`}
    >
      <div
        className="border-4 border-blue-500 border-t-transparent rounded-full animate-spin"
        style={{ width: size, height: size }}
      />
      <p className="text-gray-600 text-sm font-medium">
        {text}
      </p>
    </div>
  );
}
