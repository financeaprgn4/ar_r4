import { useEffect, useRef } from 'react';
import axios from 'axios';
import { createChart } from 'lightweight-charts';

export default function Koin_Crypto() {
  const chartRef = useRef();

  useEffect(() => {
    const fetchData = async () => {
      try {
        const res = await axios.get(`${import.meta.env.VITE_API_URL}/api/crypto/binance/btcusdt`);
        const candlestickData = res.data;

        const chart = createChart(chartRef.current, {
          width: chartRef.current.clientWidth,
          height: 400,
          layout: {
            background: { color: '#ffffff' },
            textColor: '#1f2937',
          },
          grid: {
            vertLines: { color: '#e5e7eb' },
            horzLines: { color: '#e5e7eb' },
          },
          timeScale: {
            timeVisible: true,
            secondsVisible: true,
          },
        });

        const candleSeries = chart.addCandlestickSeries({
          upColor: '#26a69a',
          downColor: '#ef5350',
          borderVisible: true,
          wickUpColor: '#26a69a',
          wickDownColor: '#ef5350',
        });

        candleSeries.setData(candlestickData);
        chart.timeScale().fitContent();

        // Cleanup chart saat komponen unmount
        return () => chart.remove();
      } catch (error) {
        console.error('Gagal memuat data candlestick:', error);
      }
    };

    fetchData();
  }, []);

  return (
    <main className="flex-1 px-2 py-2 z-10 text-white h-[calc(100vh-40px)] overflow-hidden">
      <div className="h-full bg-white/60 rounded-lg shadow-lg text-gray-800 w-full flex flex-col p-4">
        <h2 className="text-xl font-semibold mb-4">Grafik Candlestick Bitcoin (USD - Binance)</h2>
        <div ref={chartRef} className="w-full h-[400px]" />
      </div>
    </main>
  );
}
