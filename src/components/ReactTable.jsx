import React, { useRef, useEffect, useState } from "react";
import {
  useReactTable,
  getCoreRowModel,
  getFilteredRowModel,
  getPaginationRowModel,
  flexRender,
} from "@tanstack/react-table";

export default function ReusableTable({ columns, data, renderSubComponent }) {
  const [globalFilter, setGlobalFilter] = useState("");
  const [pageSizeValue, setPageSizeValue] = useState(10); 
  const inputRef = useRef(null);
  const selectRef = useRef(null);

  useEffect(() => {
    if (inputRef.current) {
      inputRef.current.focus();
    }
  }, []);

  const table = useReactTable({
    data,
    columns,
    state: {
      globalFilter,
    },
    onGlobalFilterChange: setGlobalFilter,
    getCoreRowModel: getCoreRowModel(),
    getFilteredRowModel: getFilteredRowModel(),
    getPaginationRowModel: getPaginationRowModel(),
  });

  useEffect(() => {
    setPageSizeValue(
      table.getState().pagination.pageSize === data.length
        ? "all"
        : table.getState().pagination.pageSize
    );
  }, [table.getState().pagination.pageSize, data.length]);

  useEffect(() => {
    const handleKeyDown = (e) => {
      if (e.altKey && e.key.toLowerCase() === 'f') {
        e.preventDefault();
        inputRef.current?.focus();
      }
      if (e.altKey && e.key.toLowerCase() === 'e') {
        e.preventDefault();
        selectRef.current?.focus();
      }
    };

    document.addEventListener("keydown", handleKeyDown);
    return () => {
      document.removeEventListener("keydown", handleKeyDown);
    };
  }, []);

  return (
    <div >
      <input
        ref={inputRef}
        type="text"
          placeholder="Cari data..."
          value={globalFilter ?? ""}
          onChange={(e) => setGlobalFilter(e.target.value)}
          className="border px-2 py-1 rounded w-full md:w-1/4 mb-3"
      />
      
      <div className="overflow-x-auto max-h-[calc(100vh-230px)] border rounded">
        <table className="table-auto w-full text-sm text-left border">
          <thead className="bg-gray-200 sticky top-0 z-10">
            {table.getHeaderGroups().map((headerGroup) => (
              <tr key={headerGroup.id}>
                {headerGroup.headers.map((header) => (
                  <th
                    key={header.id}
                    className={`px-3 py-2 border text-center ${header.column.columnDef.meta?.className || ""}`}
                  >
                    {flexRender(
                      header.column.columnDef.header,
                      header.getContext()
                    )}
                  </th>
                ))}
              </tr>
            ))}
          </thead>
          <tbody>
            {table.getRowModel().rows.length === 0 ? (
              <tr>
                <td
                  colSpan={columns.length}
                  className="text-center py-4 text-gray-500"
                >
                  Tidak ada data yang tersedia.
                </td>
              </tr>
            ) : (
              table.getRowModel().rows.map((row) => (
                <React.Fragment key={row.id}>
                  <tr className="hover:bg-gray-100">
                    {row.getVisibleCells().map((cell) => (
                      <td
                        key={cell.id}
                        className={`px-3 py-2 border ${cell.column.columnDef.meta?.className || ""}`}
                      >
                        {flexRender(cell.column.columnDef.cell, cell.getContext())}
                      </td>
                    ))}
                  </tr>
                  {renderSubComponent && renderSubComponent(row)}
                </React.Fragment>
              ))
            )}
          </tbody>
        </table>
      </div>

      {/* Pagination dan kontrol lainnya */}
      <div className="flex items-center justify-between mt-4">
        <div>
        <button
            onClick={() => table.setPageIndex(0)}
            disabled={!table.getCanPreviousPage()}
            className="px-2 py-1 border rounded mr-2"
        >{'<<'}</button>
        <button
            onClick={() => table.previousPage()}
            disabled={!table.getCanPreviousPage()}
            className="px-2 py-1 border rounded mr-2"
        >{'<'}</button>
        <button
            onClick={() => table.nextPage()}
            disabled={!table.getCanNextPage()}
            className="px-2 py-1 border rounded mr-2"
        >{'>'}</button>
        <button
            onClick={() => table.setPageIndex(table.getPageCount() - 1)}
            disabled={!table.getCanNextPage()}
            className="px-2 py-1 border rounded"
        >{'>>'}</button>
        </div>
        <span>
            Halaman{' '}
            <strong>
                {table.getState().pagination.pageIndex + 1} dari {table.getPageCount()}
            </strong>
        </span>
        
        <select
          ref={selectRef}
          value={pageSizeValue === data.length ? "all" : pageSizeValue}
          onChange={(e) => {
              const val = e.target.value;
              const newSize = val === "all" ? data.length : Number(val);
              setPageSizeValue(val === "all" ? data.length : newSize);
              table.setPageSize(newSize);
          }}
          className="border px-2 py-1 rounded"
          >
          {[10, 20, 50, 100].map((pageSize) => (
              <option key={pageSize} value={pageSize}>
                Tampilkan {pageSize}
              </option>
          ))}
          <option value="all">Tampilkan Semua</option>
        </select>
      </div>
    </div>
  );
}
