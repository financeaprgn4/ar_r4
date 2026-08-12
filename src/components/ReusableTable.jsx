import { useRef, useState, useEffect } from "react";
import {
  useReactTable,
  getCoreRowModel,
  getFilteredRowModel,
  getSortedRowModel,
  getPaginationRowModel,
  flexRender,
} from "@tanstack/react-table";

export default function ReusableTable({
  data = [],
  columns = [],
  globalFilter,
  setGlobalFilter,
  rightElement = null,
  searchInputRef = null,
  onVisibleDataChange,
  onSelectionChange = () => {},
  rowSelection = {},
  setRowSelection = () => {},
}) {
  const [sorting, setSorting] = useState([]);
  const [pageSizeValue, setPageSizeValue] = useState(10);
  const selectRef = useRef(null);
  const lastVisibleRowsRef = useRef([]);

  /* ================= TABLE INSTANCE ================= */
  const table = useReactTable({
    data,
    columns,
    state: {
      sorting,
      globalFilter,
      rowSelection,
    },

    onSortingChange: setSorting,
    onGlobalFilterChange: setGlobalFilter,
    enableRowSelection: true,
    onRowSelectionChange: setRowSelection,
    getCoreRowModel: getCoreRowModel(),
    getFilteredRowModel: getFilteredRowModel(),
    getSortedRowModel: getSortedRowModel(),
    getPaginationRowModel: getPaginationRowModel(),

    enableSortingRemoval: true,
  });

  useEffect(() => {

    const selectedData = table
        .getFilteredSelectedRowModel()
        .rows
        .map(row => row.original);

    onSelectionChange?.(selectedData);

  }, [rowSelection, table, onSelectionChange]);

  /* ================= PAGE SIZE SYNC ================= */
  useEffect(() => {
    setPageSizeValue(
      table.getState().pagination.pageSize === data.length
        ? "all"
        : table.getState().pagination.pageSize
    );
  }, [table.getState().pagination.pageSize, data.length]);

  /* ================= VISIBLE DATA CALLBACK ================= */
  useEffect(() => {
    const visible = table.getRowModel().rows.map((r) => r.original);
    if (JSON.stringify(visible) !== JSON.stringify(lastVisibleRowsRef.current)) {
      lastVisibleRowsRef.current = visible;
      onVisibleDataChange?.(visible);
    }
  }, [table.getRowModel().rows, onVisibleDataChange]);

  /* ================= KEYBOARD SHORTCUT ================= */
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
      if (e.altKey && e.key.toLowerCase() === "a") {
        e.preventDefault();

        table.toggleAllRowsSelected(
          !table.getIsAllRowsSelected()
        );
      }
    };

    document.addEventListener("keydown", handleKeyDown);
    return () => document.removeEventListener("keydown", handleKeyDown);
  }, [table, searchInputRef]);

  return (
    <div className="w-full">
      {/* ================= SEARCH ================= */}
      <div className="mb-2 flex flex-col md:flex-row md:items-stretch md:justify-between ml-1 mt-1">
        <input
          ref={searchInputRef}
          type="text"
          placeholder="Cari data..."
          value={globalFilter ?? ""}
          onChange={(e) => setGlobalFilter(e.target.value)}
          className="border px-3 rounded w-full md:w-1/4 h-[38px]"
        />
        {rightElement && (
          <div className="flex items-stretch h-full mr-1">
            {rightElement}
          </div>
        )}
      </div>

      {/* ================= TABLE ================= */}
      <div className="overflow-x-auto max-h-[calc(100vh-230px)] border rounded">
        <table className="min-w-full text-sm border border-collapse">
          <thead className="bg-gray-200 sticky top-0 z-10">
            {table.getHeaderGroups().map((hg) => (
              <tr key={hg.id}>
                <th className="px-4 py-2 border text-center">No</th>

                {hg.headers.map((header) => {
                  const canSort = header.column.getCanSort();
                  const sortDir = header.column.getIsSorted();

                  return (
                    <th
                      key={header.id}
                      onClick={canSort ? header.column.getToggleSortingHandler() : undefined}
                      className={`px-4 py-2 border select-none
                        ${canSort ? "cursor-pointer hover:bg-gray-300" : ""}
                        text-center`}
                    >
                      <div className="flex items-center justify-center gap-1">
                        {flexRender(
                          header.column.columnDef.header,
                          header.getContext()
                        )}
                        {sortDir === "asc" && "▲"}
                        {sortDir === "desc" && "▼"}
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
                <tr key={row.id} className="hover:bg-gray-100">
                  <td className="px-4 py-2 border text-center">
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
                  Data Tidak Ditemukan
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>

      {/* ================= PAGINATION ================= */}
      <div className="flex items-center justify-between mt-4">
        <div className="flex gap-1">
          <button onClick={() => table.setPageIndex(0)} disabled={!table.getCanPreviousPage()} className="px-2 py-1 border rounded">{"<<"}</button>
          <button onClick={() => table.previousPage()} disabled={!table.getCanPreviousPage()} className="px-2 py-1 border rounded">{"<"}</button>
          <button onClick={() => table.nextPage()} disabled={!table.getCanNextPage()} className="px-2 py-1 border rounded">{">"}</button>
          <button onClick={() => table.setPageIndex(table.getPageCount() - 1)} disabled={!table.getCanNextPage()} className="px-2 py-1 border rounded">{">>"}</button>
        </div>

        <span>
          Halaman{" "}
          <strong>
            {table.getState().pagination.pageIndex + 1} / {table.getPageCount()}
          </strong>
        </span>

        <select
          ref={selectRef}
          value={pageSizeValue}
          onChange={(e) => {
            const val = e.target.value;
            const size = val === "all" ? data.length : Number(val);
            setPageSizeValue(size);
            table.setPageSize(size);
          }}
          className="border px-2 py-1 rounded"
        >
          {[10, 20, 50, 100].map((s) => (
            <option key={s} value={s}>
              Tampilkan {s}
            </option>
          ))}
          <option value="all">Tampilkan Semua</option>
        </select>
      </div>
    </div>
  );
}
