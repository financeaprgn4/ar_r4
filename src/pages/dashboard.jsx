import { useEffect, useState } from "react";
import { useCabang } from "../contexts/CabangContext";
import {
  PieChart,
  Pie,
  Cell,
  Tooltip,
  ResponsiveContainer,
} from "recharts";

const COLORS = ['#8884d8', '#82ca9d', '#ffc658', '#ff8042', '#ffbb28'];

const renderCustomizedLabel = ({
  cx,
  cy,
  midAngle,
  innerRadius,
  outerRadius,
  percent,
  name,
}) => {
  // sembunyikan label kecil
  if (percent < 0.08) return null;

  const RADIAN = Math.PI / 180;
  const radius = innerRadius + (outerRadius - innerRadius) * 0.55;
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

const Dashboard = () => {
  const [chartData, setChartData] = useState([]);
  const [chartDataJt, setChartDataJt] = useState([]);
  const [chartDataJtCat, setChartDataJtCat] = useState([]);
  const [summary, setSummary] = useState({ total: 0 });

  const { cabang } = useCabang();

  useEffect(() => {
    if (!cabang) return;

    fetch(`/api/lpd-summary?cabang=${cabang}`)
      .then((res) => res.json())
      .then((data) => {
        setSummary({
          total: Number(data.total) || 0,
        });

        setChartData([
          { name: "Perpanjangan", value: Number(data.ppj) || 0 },
          { name: "New Store", value: Number(data.ns) || 0 },
          { name: "Upgrade", value: Number(data.up) || 0 },
        ]);

        setChartDataJt([
          {
            name: "Belum JT",
            value: Math.max(
              0,
              (Number(data.total) || 0) - (Number(data.jatuh_tempo) || 0)
            ),
          },
          {
            name: "JT",
            value: Number(data.jatuh_tempo) || 0,
          },
        ]);

        setChartDataJtCat([
          { name: "JT NS", value: Number(data.jatuh_tempo_ns) || 0 },
          { name: "JT PPJ", value: Number(data.jatuh_tempo_ppj) || 0 },
          { name: "JT UP", value: Number(data.jatuh_tempo_up) || 0 },
        ]);
      });
  }, [cabang]);

  const renderPie = (data) => (
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
            <Cell key={index} fill={COLORS[index % COLORS.length]} />
          ))}
        </Pie>
        <Tooltip />
      </PieChart>
    </ResponsiveContainer>
  );

  return (
    <main className="flex-1 px-3 py-3 text-white">
      <div className="bg-white/70 rounded-xl p-4 shadow-lg text-gray-800 w-full">
        <h2 className="text-lg sm:text-xl text-center font-semibold mb-4">
          Outs LPD Cabang {cabang}
        </h2>

        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
          <div className="bg-white rounded-lg shadow p-3">
            <h3 className="text-sm font-semibold text-center mb-2">
              Jenis LPD
            </h3>
            {renderPie(chartData)}
          </div>

          <div className="bg-white rounded-lg shadow p-3">
            <h3 className="text-sm font-semibold text-center mb-2">
              Status Jatuh Tempo
            </h3>
            {renderPie(chartDataJt)}
          </div>

          <div className="bg-white rounded-lg shadow p-3">
            <h3 className="text-sm font-semibold text-center mb-2">
              Jatuh Tempo per Kategori
            </h3>
            {renderPie(chartDataJtCat)}
          </div>
        </div>

        <div className="text-center text-xs sm:text-sm mt-4 text-gray-600 font-semibold">
          Total Data : {summary.total} toko
        </div>
      </div>
    </main>
  );
};

export default Dashboard;
