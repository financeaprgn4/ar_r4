import { Link } from "react-router-dom";
import { useState } from "react";
import {
  HomeIcon,
  BuildingLibraryIcon,
  BuildingStorefrontIcon,
  BanknotesIcon,
  DocumentChartBarIcon,
  ArrowRightOnRectangleIcon,
  CurrencyDollarIcon,
  ClipboardDocumentListIcon,
} from "@heroicons/react/24/outline";

const Home = () => {
  const [showDropdown, setShowDropdown] = useState(false);

  const menuItemClass =
    "flex items-center space-x-2 px-4 py-2 rounded text-lg hover:bg-white hover:text-blue-600 transition duration-200";

  const iconClass = "h-5 w-5";

  return (
    <div className="min-h-screen bg-blue-50">
      {/* Navbar */}
      <nav className="custom-gradient shadow-md px-6 py-4 flex justify-between items-center">
        {/* Kiri: Menu */}
        <div className="flex space-x-4 items-center">
          <Link to="/" className="text-black font-bold text-2xl">
            FIN AP R4
          </Link>

          <Link to="/" className={menuItemClass}>
            <HomeIcon className={iconClass} />
            <span>Home</span>
          </Link>

          <Link to="/lpd" className={menuItemClass}>
            <BuildingLibraryIcon className={iconClass} />
            <span>LPD</span>
          </Link>

          <Link to="/store" className={menuItemClass}>
            <BuildingStorefrontIcon className={iconClass} />
            <span>Store</span>
          </Link>

          <Link to="/bank" className={menuItemClass}>
            <BanknotesIcon className={iconClass} />
            <span>Bank</span>
          </Link>

          {/* Report Dropdown */}
          <div
            className="relative"
            onMouseEnter={() => setShowDropdown(true)}
            onMouseLeave={() => setShowDropdown(false)}
          >
            <button className={menuItemClass}>
              <DocumentChartBarIcon className={iconClass} />
              <span>Report ▾</span>
            </button>

            {showDropdown && (
              <div className="absolute z-10 mt-2 bg-white rounded shadow-lg overflow-hidden whitespace-nowrap">
                <Link
                  to="/report/hutang-dagang"
                  className="flex items-center space-x-2 px-4 py-2 text-gray-700 text-base hover:bg-blue-100 hover:text-blue-600"
                >
                  <CurrencyDollarIcon className={iconClass} />
                  <span>Hutang Dagang</span>
                </Link>
                <Link
                  to="/report/rekening-koran"
                  className="flex items-center space-x-2 px-4 py-2 text-gray-700 text-base hover:bg-blue-100 hover:text-blue-600"
                >
                  <ClipboardDocumentListIcon className={iconClass} />
                  <span>Rekening Koran</span>
                </Link>
              </div>
            )}
          </div>
        </div>

        {/* Kanan: Login */}
        <Link
          to="/login"
          className="flex items-center space-x-2 border-2 border-black text-black px-4 py-2 rounded hover:bg-white hover:text-blue-600 transition text-lg"
        >
          <ArrowRightOnRectangleIcon className="h-5 w-5" />
          <span>Login</span>
        </Link>
      </nav>

      {/* Hero Section */}
      <section className="flex items-center justify-center h-[80vh] px-4">
        <div className="bg-white p-10 rounded-xl shadow-lg max-w-xl text-center">
          <h1 className="text-4xl font-bold text-blue-600 mb-4">
            Selamat Datang di Sistem Administrasi Kantor
          </h1>
          <p className="text-gray-600 mb-6">
            Kelola data kantor Anda dengan lebih efisien dan terorganisir melalui platform ini.
          </p>
          <Link
            to="/login"
            className="inline-block bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 transition"
          >
            Masuk Sekarang
          </Link>
        </div>
      </section>
    </div>
  );
};

export default Home;
