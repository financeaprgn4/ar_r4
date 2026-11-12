
import { useEffect, useState } from "react";
import { useCabang } from "../contexts/CabangContext";

import {
  PieChart,
  Pie,
  Cell,
  Tooltip,
  ResponsiveContainer,
} from "recharts";

// ==========================================
// KONFIGURASI GRAFIK
// ==========================================

const COLORS = [
  "#8884d8",
  "#82ca9d",
  "#ffc658",
  "#ff8042",
  "#ffbb28",
];

const FRC_COLORS = {
  belum: "#ef4444",
  sudah: "#22c55e",
};

// ==========================================
// LABEL PIE CHART
// ==========================================

const renderCustomizedLabel = ({
  cx,
  cy,
  midAngle,
  innerRadius,
  outerRadius,
  percent,
  name,
}) => {
  if (percent < 0.08) return null;

  const RADIAN = Math.PI / 180;

  const radius =
    innerRadius + (outerRadius - innerRadius) * 0.55;

  const x = cx + radius * Math.cos(-midAngle * RADIAN);
  const y = cy + radius * Math.sin(-midAngle * RADIAN);

  return (
    <text
      x={x}
      y={y}
      fill="#333"
      textAnchor="middle"
      dominantBaseline="central"
      fontSize={11}
      fontWeight={500}
    >
      {`${name} ${(percent * 100).toFixed(0)}%`}
    </text>
  );
};

// ==========================================
// KOMPONEN PIE CHART UMUM
// ==========================================

const PieWidget = ({ data }) => {
  const hasData = data.some((item) => item.value > 0);

  if (!hasData) {
    return (
      <div className="h-[260px] flex items-center justify-center text-sm text-gray-400">
        Belum ada data
      </div>
    );
  }

  return (
    <ResponsiveContainer width="100%" height={260}>
      <PieChart>
        <Pie
          data={data}
          cx="50%"
          cy="50%"
          innerRadius="40%"
          outerRadius="80%"
          paddingAngle={3}
          dataKey="value"
          labelLine={false}
          label={renderCustomizedLabel}
        >
          {data.map((_, index) => (
            <Cell
              key={index}
              fill={COLORS[index % COLORS.length]}
            />
          ))}
        </Pie>

        <Tooltip />
      </PieChart>
    </ResponsiveContainer>
  );
};

// ==========================================
// WIDGET JENIS LPD
// ==========================================

const JenisLPD = ({ data }) => {
  const chartData = [
    {
      name: "Perpanjangan",
      value: Number(data?.ppj) || 0,
    },
    {
      name: "New Store",
      value: Number(data?.ns) || 0,
    },
    {
      name: "Upgrade",
      value: Number(data?.up) || 0,
    },
  ];

  return <PieWidget data={chartData} />;
};

// ==========================================
// WIDGET STATUS JT
// ==========================================

const StatusJT = ({ data }) => {
  const total = Number(data?.total) || 0;
  const jt = Number(data?.jatuh_tempo) || 0;

  const chartData = [
    {
      name: "Belum JT",
      value: Math.max(0, total - jt),
    },
    {
      name: "JT",
      value: jt,
    },
  ];

  return <PieWidget data={chartData} />;
};

// ==========================================
// WIDGET JT PER KATEGORI
// ==========================================

const JTKategori = ({ data }) => {
  const chartData = [
    {
      name: "JT NS",
      value: Number(data?.jatuh_tempo_ns) || 0,
    },
    {
      name: "JT PPJ",
      value: Number(data?.jatuh_tempo_ppj) || 0,
    },
    {
      name: "JT UP",
      value: Number(data?.jatuh_tempo_up) || 0,
    },
  ];

  return <PieWidget data={chartData} />;
};

// ==========================================
// WIDGET ABSEN MUTASI REGULER
// ==========================================

const AbsenMutasiReg = ({ cabang }) => {
  const [absensi, setAbsensi] = useState({});
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");

  useEffect(() => {
    if (!cabang) {
      setAbsensi({});
      setLoading(false);
      return;
    }

    const controller = new AbortController();

    const loadAbsensi = async () => {
      try {
        setLoading(true);
        setError("");

        const response = await fetch(
          `/api/absen-mutasi-reg?cabang=${encodeURIComponent(cabang)}`,
          {
            signal: controller.signal,
            headers: {
              Accept: "application/json",
            },
          }
        );

        if (!response.ok) {
          throw new Error(
            `Gagal mengambil data absensi mutasi (${response.status})`
          );
        }

        const result = await response.json();

        if (!result.success) {
          throw new Error(
            result.message || "Gagal mengambil data absensi"
          );
        }

        setAbsensi(result.data || {});

      } catch (err) {
        if (err.name !== "AbortError") {
          console.error("Absen Mutasi REG Error:", err);
          setError(err.message || "Terjadi kesalahan");
        }
      } finally {
        if (!controller.signal.aborted) {
          setLoading(false);
        }
      }
    };

    loadAbsensi();

    return () => controller.abort();
  }, [cabang]);

  if (loading) {
    return (
      <div className="py-8 text-center text-sm text-gray-500">
        Memuat absensi mutasi reguler...
      </div>
    );
  }

  if (error) {
    return (
      <div className="py-6 text-center text-sm text-red-600">
        {error}
      </div>
    );
  }

  const tanggal = absensi.tanggal || [];
  const rekening = absensi.rekening || [];

  if (tanggal.length === 0) {
    return (
      <div className="py-8 text-center text-sm text-gray-400">
        Periode mutasi aktif belum tersedia.
      </div>
    );
  }

  if (rekening.length === 0) {
    return (
      <div className="py-8 text-center text-sm text-gray-400">
        Belum ada rekening reguler untuk cabang ini.
      </div>
    );
  }

  return (
    <div className="w-full">

      <div className="flex flex-wrap justify-between items-center gap-2 mb-3 text-xs text-gray-600">

        <div>
          Periode:{" "}
          <strong>
            {absensi.tgl_awal} s/d {absensi.tgl_akhir}
          </strong>
        </div>

        <div className="flex gap-3">
          <span className="flex items-center gap-1">
            <span className="w-3 h-3 rounded-sm bg-green-500" />
            Sudah upload
          </span>

          <span className="flex items-center gap-1">
            <span className="w-3 h-3 rounded-sm bg-red-500" />
            Belum upload
          </span>
        </div>

      </div>

      <div className="overflow-auto max-h-[550px] border border-gray-200 rounded-lg">

        <table className="min-w-max w-full text-xs text-gray-700 border-collapse">

          <thead className="sticky top-0 z-20 bg-gray-100">

            <tr>

              <th className="sticky left-0 z-30 bg-gray-100 border px-3 py-2 text-center">
                No
              </th>

              <th className="sticky left-[42px] z-30 bg-gray-100 border px-3 py-2 text-left">
                No. Rekening
              </th>

              <th className="sticky left-[160px] z-30 bg-gray-100 border px-3 py-2 text-left">
                Bank
              </th>

              {tanggal.map((tgl) => {
                const namaHari = [
                  "Min",
                  "Sen",
                  "Sel",
                  "Rab",
                  "Kam",
                  "Jum",
                  "Sab",
                ];

                const hari = new Date(
                  `${tgl.tanggal}T00:00:00`
                ).getDay();

                return (
                  <th
                    key={tgl.tanggal}
                    className="border px-1 py-1 text-center min-w-[42px]"
                  >
                    <div className="flex flex-col items-center gap-1">
                      <span className="font-semibold">
                        {tgl.label}
                      </span>

                      <span className="text-[10px] font-normal text-gray-500">
                        {namaHari[hari]}
                      </span>
                    </div>
                  </th>
                );
              })}

            </tr>

          </thead>

          <tbody>

            {rekening.map((item, index) => (

              <tr
                key={`${item.nama_bank}-${item.no_rek}`}
                className="hover:bg-gray-50"
              >

                <td className="sticky left-0 z-10 bg-white border px-3 py-2 text-center">
                  {index + 1}
                </td>

                <td className="sticky left-[42px] z-10 bg-white border px-3 py-2 font-medium whitespace-nowrap">
                  {item.no_rek}
                </td>

                <td className="sticky left-[160px] z-10 bg-white border px-3 py-2 whitespace-nowrap">
                  {item.nama_bank}
                </td>

                {tanggal.map((tgl) => {

                  const sudahAda =
                    item.absensi?.[tgl.tanggal] === true ||
                    item.absensi?.[tgl.tanggal] === 1 ||
                    item.absensi?.[tgl.tanggal] === "1";

                  return (
                    <td
                      key={tgl.tanggal}
                      className="border px-2 py-2 text-center"
                    >
                      <span
                        title={
                          sudahAda
                            ? "Mutasi sudah tersedia"
                            : "Mutasi belum tersedia"
                        }
                        className={`inline-block w-5 h-5 rounded-sm ${
                          sudahAda
                            ? "bg-green-500"
                            : "bg-red-500"
                        }`}
                      />
                    </td>
                  );

                })}

              </tr>

            ))}

          </tbody>

        </table>

      </div>

      <div className="mt-2 text-xs text-gray-500">
        Total rekening: {rekening.length}
      </div>

    </div>
  );
};

// ==========================================
// WIDGET REKAP REKONSILIASI BANK FRC
// ==========================================

const RekapMutasiFrc = ({ cabang }) => {
  const [rekap, setRekap] = useState({});
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");

  useEffect(() => {
    if (!cabang) {
      setRekap({});
      setLoading(false);
      return;
    }

    const controller = new AbortController();

    const loadRekap = async () => {
      try {
        setLoading(true);
        setError("");

        const response = await fetch(
          `/api/rekap-mutasi-frc?cabang=${encodeURIComponent(cabang)}`,
          {
            signal: controller.signal,
            headers: {
              Accept: "application/json",
            },
          }
        );

        if (!response.ok) {
          throw new Error(
            `Gagal mengambil rekap mutasi FRC (${response.status})`
          );
        }

        const result = await response.json();

        if (!result.success) {
          throw new Error(
            result.message || "Gagal mengambil rekap mutasi FRC"
          );
        }

        setRekap(result.data || {});

      } catch (err) {
        if (err.name !== "AbortError") {
          console.error("Rekap Mutasi FRC Error:", err);
          setError(err.message || "Terjadi kesalahan");
        }
      } finally {
        if (!controller.signal.aborted) {
          setLoading(false);
        }
      }
    };

    loadRekap();

    return () => controller.abort();
  }, [cabang]);

  if (loading) {
    return (
      <div className="py-8 text-center text-sm text-gray-500">
        Memuat rekap rekonsiliasi FRC...
      </div>
    );
  }

  if (error) {
    return (
      <div className="py-6 text-center text-sm text-red-600">
        {error}
      </div>
    );
  }

  const items = rekap.items || [];

  if (items.length === 0) {
    return (
      <div className="py-8 text-center text-sm text-gray-400">
        Belum ada data rekening FRC pada periode aktif.
      </div>
    );
  }

  return (
    <div className="w-full">

      {/* INFORMASI PERIODE */}

      <div className="text-xs text-gray-600 mb-4">
        Periode:{" "}
        <strong>
          {rekap.tgl_awal} s/d {rekap.tgl_akhir}
        </strong>
      </div>

      {/* LEGENDA */}

      <div className="flex flex-wrap justify-center gap-5 mb-4 text-xs text-gray-600">

        <span className="flex items-center gap-2">
          <span className="w-3 h-3 rounded-sm bg-red-500" />
          Mutasi Belum Jurnal
        </span>

        <span className="flex items-center gap-2">
          <span className="w-3 h-3 rounded-sm bg-green-500" />
          Mutasi Sudah Jurnal
        </span>

      </div>

      {/* GRID DIAGRAM PER JENIS BANK */}

      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">

        {items.map((item, index) => {

          const belumJurnal =
            Number(item.belum_jurnal) || 0;

          const sudahJurnal =
            Number(item.sudah_jurnal) || 0;

          const receiptUnmatch =
            Number(item.receipt_unmatch) || 0;

          const total =
            belumJurnal + sudahJurnal;

          const chartData = [
            {
              name: "Belum Jurnal",
              value: belumJurnal,
            },
            {
              name: "Sudah Jurnal",
              value: sudahJurnal,
            },
          ];

          return (
            <div
              key={`${item.jns_bank}-${index}`}
              className="bg-white rounded-lg shadow p-3 border border-gray-100"
            >

              {/* NAMA JENIS BANK */}

              <h3 className="text-sm font-semibold text-center text-gray-800">
                {item.jns_bank}
              </h3>

              <div className="text-center text-xs text-gray-500">
                Total Mutasi:{" "}
                <strong>{total.toLocaleString("id-ID")}</strong>
              </div>

              {/* DIAGRAM PIE */}

              {total > 0 ? (
                <ResponsiveContainer width="100%" height={200}>
                  <PieChart>

                    <Pie
                      data={chartData}
                      cx="50%"
                      cy="50%"
                      innerRadius="42%"
                      outerRadius="75%"
                      paddingAngle={3}
                      dataKey="value"
                      labelLine={false}
                      label={renderCustomizedLabel}
                    >
                      <Cell fill={FRC_COLORS.belum} />
                      <Cell fill={FRC_COLORS.sudah} />
                    </Pie>

                    <Tooltip
                      formatter={(value, name) => [
                        Number(value).toLocaleString("id-ID"),
                        name,
                      ]}
                    />

                  </PieChart>
                </ResponsiveContainer>
              ) : (
                <div className="h-[240px] flex items-center justify-center text-sm text-gray-400">
                  Belum ada mutasi
                </div>
              )}

              {/* RINCIAN JUMLAH MUTASI */}

              <div className="grid grid-cols-2 gap-2 text-xs">

                <div className="bg-red-50 rounded-md p-2 text-center">
                  <div className="text-red-600">
                    Belum Jurnal
                  </div>

                  <div className="font-bold text-red-700 text-base">
                    {belumJurnal.toLocaleString("id-ID")}
                  </div>
                </div>

                <div className="bg-green-50 rounded-md p-2 text-center">
                  <div className="text-green-600">
                    Sudah Jurnal
                  </div>

                  <div className="font-bold text-green-700 text-base">
                    {sudahJurnal.toLocaleString("id-ID")}
                  </div>
                </div>

              </div>

              {/* RECEIPT UNMATCH */}

              <div className="mt-3 border-t pt-3 flex justify-between items-center gap-2">

                <span className="text-xs text-gray-600">
                  Receipt Unmatch
                </span>

                <span className="text-sm font-bold text-orange-600">
                  {receiptUnmatch.toLocaleString("id-ID")}
                </span>

              </div>

            </div>
          );
        })}

      </div>

      {/* TOTAL KESELURUHAN */}

      <div className="mt-4 bg-gray-50 rounded-lg p-3">

        <div className="grid grid-cols-3 gap-3 text-center">

          <div>
            <div className="text-xs text-gray-500">
              Total Mutasi
            </div>

            <div className="font-bold text-gray-800">
              {items.reduce(
                (sum, item) =>
                  sum +
                  (Number(item.belum_jurnal) || 0) +
                  (Number(item.sudah_jurnal) || 0),
                0
              ).toLocaleString("id-ID")}
            </div>
          </div>

          <div>
            <div className="text-xs text-red-500">
              Belum Jurnal
            </div>

            <div className="font-bold text-red-600">
              {items.reduce(
                (sum, item) =>
                  sum + (Number(item.belum_jurnal) || 0),
                0
              ).toLocaleString("id-ID")}
            </div>
          </div>

          <div>
            <div className="text-xs text-green-600">
              Sudah Jurnal
            </div>

            <div className="font-bold text-green-700">
              {items.reduce(
                (sum, item) =>
                  sum + (Number(item.sudah_jurnal) || 0),
                0
              ).toLocaleString("id-ID")}
            </div>
          </div>

        </div>

      </div>

    </div>
  );
};

// ==========================================
// WIDGET REGISTRY
// ==========================================

const WIDGETS = {
  jenis_lpd: JenisLPD,
  status_jt: StatusJT,
  jt_kategori: JTKategori,
  absen_mutasi_reg: AbsenMutasiReg,
  rekap_mutasi_frc: RekapMutasiFrc,
};

// ==========================================
// KONFIGURASI LEBAR WIDGET
// ==========================================

const SPAN_CLASSES = {
  1: "col-span-1 sm:col-span-1 lg:col-span-1",
  2: "col-span-1 sm:col-span-2 lg:col-span-2",
  3: "col-span-1 sm:col-span-2 lg:col-span-3",
};

// ==========================================
// DASHBOARD UTAMA
// ==========================================

const Dashboard = () => {
  const { cabang, role } = useCabang();

  const [widgets, setWidgets] = useState([]);
  const [summary, setSummary] = useState({ total: 0 });

  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");

  // ========================================
  // LOAD DASHBOARD
  // ========================================

  useEffect(() => {

    if (!cabang || !role) {
      setWidgets([]);
      setSummary({ total: 0 });
      setLoading(false);
      return;
    }

    const controller = new AbortController();

    const loadDashboard = async () => {

      try {
        setLoading(true);
        setError("");

        // ========================================
        // 1. KONFIGURASI DASHBOARD
        // ========================================

        const dashboardResponse = await fetch(
          `/api/dashboard?role=${encodeURIComponent(role)}`,
          {
            signal: controller.signal,
            headers: {
              Accept: "application/json",
            },
          }
        );

        if (!dashboardResponse.ok) {
          throw new Error(
            "Gagal mengambil konfigurasi dashboard"
          );
        }

        const dashboardData = await dashboardResponse.json();

        const activeWidgets = (
          dashboardData.widgets || []
        )
          .filter(
            (widget) =>
              widget.is_active !== false &&
              widget.is_active !== 0
          )
          .sort(
            (a, b) =>
              Number(a.position) - Number(b.position)
          );

        setWidgets(activeWidgets);

        // ========================================
        // 2. SUMMARY LPD
        // ========================================

        try {

          const summaryResponse = await fetch(
            `/api/lpd-summary?cabang=${encodeURIComponent(cabang)}`,
            {
              signal: controller.signal,
              headers: {
                Accept: "application/json",
              },
            }
          );

          if (!summaryResponse.ok) {
            throw new Error(
              "Gagal mengambil data summary LPD"
            );
          }

          const summaryData = await summaryResponse.json();

          setSummary(summaryData);

        } catch (summaryError) {

          if (summaryError.name === "AbortError") {
            throw summaryError;
          }

          console.error(
            "Summary LPD Error:",
            summaryError
          );

          setSummary({ total: 0 });
        }

      } catch (err) {

        if (err.name !== "AbortError") {
          console.error("Dashboard Error:", err);
          setError(err.message || "Terjadi kesalahan");
        }

      } finally {

        if (!controller.signal.aborted) {
          setLoading(false);
        }

      }
    };

    loadDashboard();

    return () => controller.abort();

  }, [cabang, role]);

  // ========================================
  // LOADING
  // ========================================

  if (loading) {
    return (
      <main className="flex-1 min-h-screen overflow-y-auto px-3 py-3">
        <div className="bg-white rounded-xl p-8 shadow text-center text-gray-500">
          Memuat dashboard...
        </div>
      </main>
    );
  }

  // ========================================
  // ERROR
  // ========================================

  if (error) {
    return (
      <main className="flex-1 min-h-screen overflow-y-auto px-3 py-3">
        <div className="bg-white rounded-xl p-6 shadow text-center text-red-600">
          {error}
        </div>
      </main>
    );
  }

  // ========================================
  // PISAHKAN WIDGET BERDASARKAN JENIS
  // ========================================

  const isAdmin =
    String(role).trim().toUpperCase() === "ADMIN";

  // Widget grafik LPD
  const lpdWidgets = widgets.filter((widget) => {
    return (
      widget.widget_key !== "absen_mutasi_reg" &&
      widget.widget_key !== "rekap_mutasi_frc" &&
      WIDGETS[widget.widget_key]
    );
  });

  // Widget absensi mutasi REG khusus ADMIN
  const absenMutasiWidget = isAdmin
    ? widgets.find(
        (widget) =>
          widget.widget_key === "absen_mutasi_reg"
      )
    : null;

  // Widget rekap FRC
  const rekapFrcWidget = widgets.find(
    (widget) =>
      widget.widget_key === "rekap_mutasi_frc"
  );

  // ========================================
  // RENDER DASHBOARD
  // ========================================

  return (
    <main className="flex-1 min-h-screen overflow-y-auto overflow-x-hidden px-3 py-3">

      <div className="w-full flex flex-col gap-2 mb-4">

        {/* ==================================
            BOX 1 : OUTS LPD
        ================================== */}

        <section className="bg-white/70 rounded-xl p-4 shadow-lg text-gray-800 w-full">

          <h2 className="text-lg sm:text-xl text-center font-semibold mb-4">
            Outs LPD Cabang {cabang}
          </h2>

          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">

            {lpdWidgets.map((widget) => {

              const WidgetComponent =
                WIDGETS[widget.widget_key];

              const span = Math.min(
                3,
                Math.max(1, Number(widget.col_span) || 1)
              );

              const spanClass = SPAN_CLASSES[span];

              return (
                <div
                  key={widget.id}
                  className={`bg-white rounded-lg shadow p-3 ${spanClass}`}
                >
                  <h3 className="text-sm font-semibold text-center mb-2">
                    {widget.title}
                  </h3>

                  <WidgetComponent data={summary} />
                </div>
              );
            })}

            {lpdWidgets.length === 0 && (
              <div className="col-span-full bg-white rounded-lg p-8 text-center text-gray-500">
                Belum ada widget Outs LPD yang diaktifkan.
              </div>
            )}

          </div>

          <div className="text-center text-xs sm:text-sm mt-4 text-gray-600 font-semibold">
            Total Data : {Number(summary.total) || 0} toko
          </div>

        </section>

        {/* ==================================
            BOX 2 : ABSENSI MUTASI REG
            KHUSUS ADMIN
        ================================== */}

        {absenMutasiWidget && (

          <section className="bg-white/70 rounded-xl p-4 shadow-lg text-gray-800 w-full">

            <h2 className="text-lg sm:text-xl text-center font-semibold mb-4">
              {absenMutasiWidget.title || "Absensi Mutasi Reguler"}
            </h2>

            <div className="bg-white rounded-lg shadow p-3 w-full">
              <AbsenMutasiReg cabang={cabang} />
            </div>

          </section>

        )}

        {/* ==================================
            BOX 3 : REKAP REKONSILIASI FRC
        ================================== */}

        {rekapFrcWidget && (

          <section className="bg-white/70 rounded-xl p-4 shadow-lg text-gray-800 w-full">

            <h2 className="text-lg sm:text-xl text-center font-semibold mb-4">
              {rekapFrcWidget.title || "Rekap Rekonsiliasi Bank Franchise"}
            </h2>

            <div className="bg-white rounded-lg shadow p-3 w-full">
              <RekapMutasiFrc cabang={cabang} />
            </div>

          </section>

        )}

      </div>

    </main>
  );
};

export default Dashboard;
