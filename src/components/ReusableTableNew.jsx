import { useRef, useState, useEffect } from "react";
import {
  useReactTable,
  getCoreRowModel,
  getFilteredRowModel,
  getSortedRowModel,
  getPaginationRowModel,
  flexRender,
} from "@tanstack/react-table";

export default function ReusableTableNew({
  data = [],
  columns = [],
  periodFilter,
  setPeriodFilter,
  periodOptions = [],
  categoryFilter,
  setCategoryFilter,
  globalFilter,
  setGlobalFilter,
  leftElement,
  rightElement,
  onSelectionChange,
}) {
  const [sorting, setSorting] = useState([]);
  const [pageSizeValue, setPageSizeValue] = useState(15);
  const [internalSearch, setInternalSearch] = useState(globalFilter);
  const [rowSelection, setRowSelection] = useState({});
  const selectRef = useRef(null);
  const searchInputRef = useRef(null);

  useEffect(() => {
    const delay = setTimeout(() => {
      setGlobalFilter(internalSearch);
    }, 250);

    return () => clearTimeout(delay);
  }, [internalSearch]);

  const table = useReactTable({
    data,
    columns,
    state: {
      sorting,
      globalFilter,
      rowSelection,
    },
    initialState: {
      pagination: {
        pageSize: 15,
      },
    },
    onSortingChange: setSorting,
    onGlobalFilterChange: setGlobalFilter,
    enableRowSelection: true,
    onRowSelectionChange: setRowSelection,

    getCoreRowModel: getCoreRowModel(),
    getFilteredRowModel: getFilteredRowModel(),
    getSortedRowModel: getSortedRowModel(),
    getPaginationRowModel: getPaginationRowModel(),
  });

  useEffect(() => {
    const selected = table.getSelectedRowModel().rows.map((row) => row.original);
    onSelectionChange?.(selected);
  }, [table.getSelectedRowModel().rows]);

  useEffect(() => {
    const handleKeyDown = (e) => {
      if (e.altKey && e.key.toLowerCase() === "f") {
        e.preventDefault();
        searchInputRef?.current?.focus();
      }
      if (e.altKey && e.key.toLowerCase() === "e") {
        e.preventDefault();
        selectRef?.current?.focus();
      }
    };

    document.addEventListener("keydown", handleKeyDown);
    return () => {
      document.removeEventListener("keydown", handleKeyDown);
    };
  }, [searchInputRef, selectRef]);

  return (
    <div className="w-full">
      {/* FILTER AREA */}
      <div className="p-3 bg-white/70 rounded-lg shadow flex flex-wrap items-center gap-4 mb-4">
        {/* Filter Periode */}
        <label className="font-semibold">Periode:</label>
        <select
          className="border px-3 py-1 rounded"
          value={periodFilter}
          onChange={(e) => setPeriodFilter(e.target.value)}
        >
          <option value="">Semua Periode</option>
          {periodOptions.map((p) => (
            <option key={p.id} value={p.periode}>
              {p.periode}
            </option>
          ))}
        </select>

        {/* Filter Kategori */}
        <label className="font-semibold">Kategori:</label>
        <select
          className="border px-3 py-1 rounded"
          value={categoryFilter}
          onChange={(e) => setCategoryFilter(e.target.value)}
        >
          <option value="Reguler">Reguler</option>
          <option value="Franchise">Franchise</option>
        </select>
        {leftElement}

        {/* GLOBAL SEARCH */}
        <input
          type="text"
          ref={searchInputRef}
          className="border px-3 py-1 rounded w-60 ml-auto"
          placeholder="Cari..."
          value={internalSearch}
          onChange={(e) => setInternalSearch(e.target.value)}
        />

        {/* RIGHT ELEMENT */}
        {rightElement}
      </div>

      {/* TABLE */}
      <div className="overflow-x-auto max-h-[calc(100vh-260px)] border rounded">
        <table className="min-w-full text-sm border">
          <thead className="bg-gray-100 sticky top-0 z-10">
            {table.getHeaderGroups().map((hg) => (
              <tr key={hg.id}>
                <th className="px-3 py-2 border text-center">No</th>

                {hg.headers.map((header) => {
                  const sorted = header.column.getIsSorted();
                  return (
                    <th
                      key={header.id}
                      className="px-3 py-2 border cursor-pointer text-center"
                      onClick={header.column.getToggleSortingHandler()}
                    >
                      <div className="flex items-center justify-center gap-1">
                        {flexRender(header.column.columnDef.header, header.getContext())}
                        {sorted === "asc" && "▲"}
                        {sorted === "desc" && "▼"}
                      </div>
                    </th>
                  );
                })}
              </tr>
            ))}
          </thead>

          <tbody>
            {table.getRowModel().rows.length > 0 ? (
              table.getRowModel().rows.map((row, i) => (
                <tr key={row.id} className="hover:bg-gray-50">
                  <td className="px-3 py-2 border text-center">
                    {table.getState().pagination.pageIndex *
                      table.getState().pagination.pageSize +
                      i +
                      1}
                  </td>

                  {row.getVisibleCells().map((cell) => (
                    <td
                      key={cell.id}
                      className={`px-3 py-2 border ${
                        cell.column.columnDef.meta?.className ?? ""
                      }`}
                    >
                      {flexRender(cell.column.columnDef.cell, cell.getContext())}
                    </td>
                  ))}
                </tr>
              ))
            ) : (
              <tr>
                <td
                  colSpan={columns.length + 1}
                  className="text-center py-4 text-gray-500"
                >
                  Tidak ada data
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>

      {/* PAGINATION */}
      <div className="flex items-center justify-between mt-4">
        <div className="flex gap-1">
          <button onClick={() => table.setPageIndex(0)} disabled={!table.getCanPreviousPage()} className="px-2 py-1 border rounded">{"<<"}</button>
          <button onClick={() => table.previousPage()} disabled={!table.getCanPreviousPage()} className="px-2 py-1 border rounded">{"<"}</button>
          <button onClick={() => table.nextPage()} disabled={!table.getCanNextPage()} className="px-2 py-1 border rounded">{">"}</button>
          <button onClick={() => table.setPageIndex(table.getPageCount() - 1)} disabled={!table.getCanNextPage()} className="px-2 py-1 border rounded">{">>"}</button>
        </div>

        <span>
          Halaman{" "}
          <strong>{table.getState().pagination.pageIndex + 1} / {table.getPageCount()}</strong>
        </span>

        <select
          className="border px-2 py-1 rounded"
          ref={selectRef}
          value={pageSizeValue}
          onChange={(e) => {
            const size = Number(e.target.value);
            setPageSizeValue(size);
            table.setPageSize(size === -1 ? data.length : size);
          }}
        >
          {[15, 30, 50, 100].map((s) => (
            <option key={s} value={s}>
              Tampilkan {s}
            </option>
          ))}
          <option value={-1}>Tampilkan Semua</option>
        </select>
      </div>
    </div>
  );
}
