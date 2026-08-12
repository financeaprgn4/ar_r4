import {
  ChevronDownIcon,
  ArrowRightOnRectangleIcon,
  ArrowDownTrayIcon,
  Bars3Icon,
  BanknotesIcon,
  FolderIcon,
  CurrencyDollarIcon,
  BookOpenIcon,
  BuildingLibraryIcon,
  DocumentChartBarIcon,
  ReceiptRefundIcon,
  CalendarDaysIcon,
  UsersIcon,
  HomeIcon,
  ComputerDesktopIcon,
  CalculatorIcon,
  AdjustmentsVerticalIcon,
  DocumentArrowDownIcon,
  DocumentDuplicateIcon,
  DocumentMinusIcon,
  DocumentTextIcon,
  DocumentCheckIcon,
  PuzzlePieceIcon,
  MagnifyingGlassIcon,
} from "@heroicons/react/24/outline";
import { Link, useNavigate } from "react-router-dom";
import { useEffect, useState, useRef } from "react";
import Swal from "sweetalert2";
import { useSidebar } from "./SidebarContext";
import { useCabang } from "../contexts/CabangContext";
import { InboxIcon, MailIcon, SettingsIcon, PencilIcon } from "lucide-react";

const Sidebar = () => {
  const [openMenus, setOpenMenus] = useState({ lpd: false });
  const [username, setUsername] = useState("");
  const {cabang, changeCabang} = useCabang();
  const [foto, setFoto] = useState("");
  const navigate = useNavigate();
  const { isCollapsed, setIsCollapsed } = useSidebar();
  const cabangButtonRef = useRef(null);
  const firstCabangRef = useRef(null);
  const [selectedIndex, setSelectedIndex] = useState(0);

  const getFotoSrc = (foto) => {
    if (!foto || foto === "null" || foto.trim() === "") {
      return "/images/profil.jpg";
    }
    return `/images/${foto}`;
  };

  const handleDashboardClick = () => {
    if (isCollapsed) {
      setIsCollapsed(false);
    } else {
      navigate("/dashboard");
    }
  };

  const handleTasksClick = () => {
    if (isCollapsed) {
      setIsCollapsed(false);
    } else {
      navigate("/Tasks");
    }
  };

  const toggleMenu = (menu) => {
    if (isCollapsed) {
      setIsCollapsed(false);
    } else {
      setOpenMenus((prev) => ({
        ...prev,
        [menu]: !prev[menu],
      }));
    }
  };

  const toggleSidebar = () => {
    setIsCollapsed(!isCollapsed);
  };

  useEffect(() => {
    if (isCollapsed) {
      setOpenMenus((prev) =>
        Object.keys(prev).reduce((acc, key) => {
          acc[key] = false;
          return acc;
        }, {})
      );
    }
  }, [!isCollapsed]);

  const handleLogout = async () => {
    const result = await Swal.fire({
      title: "Yakin ingin logout?",
      text: "Kamu akan keluar dari sesi ini.",
      icon: "warning",
      showCancelButton: true,
      confirmButtonText: "Ya, logout!",
      cancelButtonText: "Batal"
    });

    if (result.isConfirmed) {
      await fetch(`${import.meta.env.VITE_API_URL}/api/logout`, {
        method: "POST",
      });

      sessionStorage.clear();

      Swal.fire({
        icon: "success",
        title: "Logout berhasil",
        timer: 1500,
        showConfirmButton: false
      });

      navigate("/login");
    }
  };

  useEffect(() => {
    const storedUser = sessionStorage.getItem("username");
    const storedFoto = sessionStorage.getItem("foto");

    setUsername(storedUser || "User");
    setFoto(storedFoto || "");
  }, []);

  const [listCabang, setListCabang] = useState([]);
  const [showDropdown, setShowDropdown] = useState(false);

  const handleToggleDropdown = async () => {

    const nextState = !showDropdown;

    if (nextState) {

      setSelectedIndex(0);

      try {

        const res = await fetch("/api/cabang");
        const data = await res.json();

        setListCabang(data);

      } catch (err) {

        console.error(
          "Gagal ambil cabang:",
          err
        );

      }
    }

    setShowDropdown(nextState);
  };

  const handleSelectCabang = (item) => {
    changeCabang(item.cabang);

    setShowDropdown(false);
  };

  const dropdownRef = useRef();
  useEffect(() => {
    const handleClickOutside = (event) => {
      if (dropdownRef.current && !dropdownRef.current.contains(event.target)) {
        setShowDropdown(false);
      }
    };

    document.addEventListener("mousedown", handleClickOutside);
    return () => {
      document.removeEventListener("mousedown", handleClickOutside);
    };
  }, []);

  useEffect(() => {
    const handleKeyDown = (e) => {
      if (isCollapsed) return;

      /*
      |--------------------------------------------------------------------------
      | ALT + S -> Ganti Cabang
      |--------------------------------------------------------------------------
      */
      if (
        e.altKey &&
        e.key.toLowerCase() === "s"
      ) {

        e.preventDefault();

        if (!showDropdown) {
          setSelectedIndex(0);
          handleToggleDropdown();
        }

        return;
      }

      /*
      |--------------------------------------------------------------------------
      | Navigasi Dropdown Cabang
      |--------------------------------------------------------------------------
      */
      if (!showDropdown) return;

      switch (e.key) {

        case "ArrowDown":

          e.preventDefault();

          setSelectedIndex(prev =>
            Math.min(
              prev + 1,
              listCabang.length - 1
            )
          );

          break;

        case "ArrowUp":
          e.preventDefault();

          setSelectedIndex(prev =>
            Math.max(
              prev - 1,
              0
            )
          );

          break;

        case "Enter":

          e.preventDefault();

          if (listCabang[selectedIndex]) {

            handleSelectCabang(
              listCabang[selectedIndex]
            );

            setShowDropdown(false);
          }

          break;

        case "Escape":

          e.preventDefault();

          setShowDropdown(false);

          break;
      }
    };

    window.addEventListener(
      "keydown",
      handleKeyDown
    );

    return () =>
      window.removeEventListener(
        "keydown",
        handleKeyDown
      );

  }, [
    isCollapsed,
    showDropdown,
    selectedIndex,
    listCabang
  ]);

  const [focusedMenuId, setFocusedMenuId] = useState("dashboard");
  const menuRefs = useRef({});

  const getMenuClass = (menuId) => `
    flex items-center justify-between
    w-full text-left font-semibold
    px-2 py-2 rounded transition
    focus:outline-none

    ${
      focusedMenuId === menuId
        ? "bg-yellow-400 text-black"
        : "hover:bg-white/20"
    }
  `;

  const getSubMenuClass = (menuId) => `
    flex items-center space-x-2
    px-2 py-1 rounded transition
    focus:outline-none
    ${
      focusedMenuId === menuId
        ? "bg-yellow-400 text-black"
        : "hover:bg-white/20"
    }
  `;

  const menuTree = [
    {
      id: "dashboard",
      type: "link",
    },

    {
      id: "lpd",
      type: "menu",
      children: [
        { id: "lpd-outs", path: "/lpd/outs" },
        { id: "lpd-cs", path: "/lpd-cs" },
        { id: "lpd-final", path: "/lpd-final" },
        { id: "berkas-lpd", path: "/Berkas_lpd" },
        { id: "monitoring-modal", path: "/monitoring-modal" },
        { id: "monitoring-rab", path: "/monitoring-RAB" },
        { id: "master-pr", path: "/Master_dat_pr" },
      ],
    },

    {
      id: "mail",
      type: "menu",
      children: [
        { id: "inbox", path: "/Inbox" },
        { id: "setting-mail", path: "/Mail" },
      ],
    },

    {
      id: "statement",
      type: "menu",
      children: [
        { id: "monitoring-saldo", path: "/monitoring-saldo" },
        { id: "mutasi-harian", path: "/daily_statement" },
        { id: "mutasi-search", path: "/mutasi_search" },
        { id: "reconciliation", path: "/reconciliation" },
        { id: "rekon_ar", path: "/Rekon_AR" },
        { id: "import-gl", path: "/gl" },
      ],
    },

    {
      id: "report",
      type: "menu",
      children: [
        { id: "hutang-dagang", path: "/hutang_dagang" },
        { id: "bank-statement", path: "/bank_statement" },
      ],
    },

    {
      id: "setting",
      type: "menu",
      children: [
        { id: "users", path: "/Users" },
        { id: "periode", path: "/Periode" },
        { id: "bank", path: "/bank" },
      ],
    },

    {
      id: "extras",
      type: "menu",
      children: [
        { id: "merge-pdf", path: "/Merge_pdf" },
        { id: "lpd-pdf", path: "/Lpd_pdf" },
      ],
    },

    {
      id: "tasks",
      type: "link",
    },
  ];

  const getVisibleMenu = () => {

    const visible = [];

    menuTree.forEach(menu => {

      visible.push({
        id: menu.id,
        type: menu.type,
      });

      if (
        menu.children &&
        openMenus[menu.id]
      ) {

        menu.children.forEach(sub => {

          visible.push({
            id: sub.id,
            type: "submenu",
            parent: menu.id,
            path: sub.path,
          });

        });

      }

    });

    return visible;
  };

  useEffect(() => {
    const el =
      menuRefs.current[focusedMenuId];

    if (el) {
      el.focus();
    }

  }, [focusedMenuId]);

  useEffect(() => {
    const handleSidebarNavigation = (e) => {

      /*
      |--------------------------------------------------------------------------
      | Sidebar harus terbuka
      |--------------------------------------------------------------------------
      */
      if (isCollapsed) return;

      /*
      |--------------------------------------------------------------------------
      | Jangan bentrok dengan dropdown cabang
      |--------------------------------------------------------------------------
      */
      if (showDropdown) return;

      const visibleMenu =
        getVisibleMenu();

      const currentIndex =
        visibleMenu.findIndex(
          item =>
            item.id === focusedMenuId
        );

      const current =
        visibleMenu[currentIndex];

      switch (e.key) {

        /*
        |--------------------------------------------------------------------------
        | ARROW DOWN
        |--------------------------------------------------------------------------
        */
        case "ArrowDown":

          e.preventDefault();

          if (
            currentIndex <
            visibleMenu.length - 1
          ) {

            setFocusedMenuId(
              visibleMenu[
                currentIndex + 1
              ].id
            );
          }

          break;

        /*
        |--------------------------------------------------------------------------
        | ARROW UP
        |--------------------------------------------------------------------------
        */
        case "ArrowUp":

          e.preventDefault();

          if (currentIndex > 0) {

            setFocusedMenuId(
              visibleMenu[
                currentIndex - 1
              ].id
            );
          }

          break;

        /*
        |--------------------------------------------------------------------------
        | ARROW RIGHT
        |--------------------------------------------------------------------------
        */
        case "ArrowRight":

          e.preventDefault();

          if (
            current?.type === "menu" &&
            !openMenus[current.id]
          ) {

            toggleMenu(current.id);
          }

          break;

        /*
        |--------------------------------------------------------------------------
        | ARROW LEFT
        |--------------------------------------------------------------------------
        */
        case "ArrowLeft":

          e.preventDefault();

          if (
            current?.type === "submenu"
          ) {

            toggleMenu(
              current.parent
            );

            setFocusedMenuId(
              current.parent
            );
          }

          else if (
            current?.type === "menu" &&
            openMenus[current.id]
          ) {

            toggleMenu(current.id);
          }

          break;

        /*
        |--------------------------------------------------------------------------
        | ENTER
        |--------------------------------------------------------------------------
        */
        case "Enter":

          e.preventDefault();

          if (!current) return;

          /*
          |--------------------------------------------------------------------------
          | Parent Menu
          |--------------------------------------------------------------------------
          */
          if (
            current.type === "menu"
          ) {

            toggleMenu(
              current.id
            );

            return;
          }

          /*
          |--------------------------------------------------------------------------
          | Sub Menu
          |--------------------------------------------------------------------------
          */
          if (
            current.type === "submenu"
          ) {

            navigate(
              current.path
            );

            return;
          }

          /*
          |--------------------------------------------------------------------------
          | Link biasa
          |--------------------------------------------------------------------------
          */
          if (
            current.type === "link" &&
            current.action
          ) {

            current.action();
          }

          break;

        /*
        |--------------------------------------------------------------------------
        | ESC
        |--------------------------------------------------------------------------
        */
        case "Escape":

          e.preventDefault();

          setIsCollapsed(true);

          break;

        default:

          break;
      }
    };

    window.addEventListener(
      "keydown",
      handleSidebarNavigation
    );

    return () =>
      window.removeEventListener(
        "keydown",
        handleSidebarNavigation
      );

  }, [
    focusedMenuId,
    openMenus,
    isCollapsed,
    showDropdown,
    navigate,
  ]);

  useEffect(() => {

    if (isCollapsed) {
      setFocusedMenuId(null);
    }

  }, [isCollapsed]);

  return (
    <aside
      className={`${
        !isCollapsed ? "w-72" : "w-16"
      } bg-white/20 backdrop-blur-sm text-white shadow-md z-10 flex flex-col transition-all duration-500 ease-in-out h-screen flex-shrink-0`}
    >
      {/* Toggle Sidebar Button */}
      <div className="relative mt-3 mr-3" style={{ height: "3.5rem" }}>
        <button
          onClick={toggleSidebar}
          className="absolute top-0 right-0 p-2 bg-white/20 text-white rounded-full backdrop-blur-md hover:bg-white/30"
        >
          <Bars3Icon className="h-6 w-6" />
        </button>
      </div>

      {/* Scrollable Content */}
      <div className="flex-1 overflow-y-auto p-4">
        {/* Profil */}
        {!isCollapsed && (
          <div className="flex flex-col items-center mb-4 border-b border-white pb-2 -mt-4">
            <img
              src={getFotoSrc(foto)}
              alt="Profile"
              className="w-40 h-40 rounded-lg object-cover mb-1 border-4 border-white"
            />
            <p className="text-sm font-semibold text-center">{username}</p>

            <div className="relative mb-1" ref={dropdownRef}>
              {/* Button */}
              <button
                ref={cabangButtonRef}
                onClick={handleToggleDropdown}
                className="flex items-center gap-2 bg-white text-gray-700 px-3 py-1 rounded-lg shadow hover:bg-gray-100 transition"
              >
                <span className="text-sm font-semibold">{cabang}</span>
                <PencilIcon className="text-xs" />
              </button>

              {/* Dropdown */}
              {showDropdown && (
                <div className="absolute left-0 mt-1 w-48 bg-white text-gray-800 border rounded-lg shadow-lg z-50">
                  <div className="max-h-60 overflow-y-auto">
                    {listCabang.map((item, index) => (
                      <div
                        key={index}
                        onClick={() => handleSelectCabang(item)}
                        className={`px-3 py-2 text-sm cursor-pointer
                          ${
                            index === selectedIndex
                              ? "bg-blue-500 text-white"
                              : item.cabang === cabang
                              ? "bg-gray-200 font-semibold"
                              : "hover:bg-gray-100"
                          }
                        `}
                      >
                        {item.cabang}
                      </div>
                    ))}
                  </div>
                </div>
              )}
            </div>
          </div>
        )}

        {/* Menu */}
        <nav className="space-y-1">
          {/* Dashboard Menu */}
          <div>
            <button
              ref={el =>
                menuRefs.current["dashboard"] = el
              }
              className={getMenuClass("dashboard")}
              onClick={handleDashboardClick}
              title={isCollapsed ? "Dashboard" : ""}
            >
              <span className="flex items-center space-x-2">
                <HomeIcon className="h-5 w-5" />
                {!isCollapsed && <span>Dashboard</span>}
              </span>
            </button>
            <div className="border-b border-white" />
          </div>

          {/* LPD Menu */}
          <div>
            <button
              ref={el =>
                menuRefs.current["lpd"] = el
              }
              className={getMenuClass("lpd")}
              onClick={() => toggleMenu("lpd")}
              title={isCollapsed ? "LPD" : ""}
            >
              <span className="flex items-center space-x-2">
                <FolderIcon className="h-5 w-5" />
                {!isCollapsed && <span>LPD</span>}
              </span>
              {!isCollapsed && (
                <ChevronDownIcon
                  className={`h-5 w-5 transform transition-transform duration-300 ${
                    openMenus.lpd ? "rotate-180" : "rotate-0"
                  }`}
                />
              )}
            </button>
            <div className="border-b border-white" />
            <div
              className={`overflow-hidden transition-all duration-500 ease-in-out ${
                openMenus.lpd && !isCollapsed ? "max-h-96" : "max-h-0"
              }`}
            >
              <div className="pl-8 space-y-1 mt-1">
                <Link
                  to="/lpd/outs"
                  ref={el =>
                    menuRefs.current["lpd-outs"] = el
                  }
                  className={getSubMenuClass("lpd-outs")}
                >
                  <ComputerDesktopIcon className="h-4 w-4" />
                  {!isCollapsed && <span>Data Outs LPD</span>}
                </Link>
                <div className="border-b border-white" />
                <Link
                  to="/lpd-cs"
                  ref={el =>
                    menuRefs.current["lpd-cs"] = el
                  }
                  className={getSubMenuClass("lpd-cs")}
                >
                  <DocumentTextIcon className="h-4 w-4" />
                  {!isCollapsed && <span>Clearencesheets</span>}
                </Link>
                <div className="border-b border-white" />
                <Link
                  to="/lpd-final"
                  ref={el =>
                    menuRefs.current["lpd-final"] = el
                  }
                  className={getSubMenuClass("lpd-final")}
                >
                  <DocumentTextIcon className="h-4 w-4" />
                  {!isCollapsed && <span>LPD Final</span>}
                </Link>
                <div className="border-b border-white" />
                <Link
                  to="/Berkas_lpd"
                  ref={el =>
                    menuRefs.current["berkas-lpd"] = el
                  }
                  className={getSubMenuClass("berkas-lpd")}
                >
                  <FolderIcon className="h-4 w-4" />
                  {!isCollapsed && <span>Berkas LPD</span>}
                </Link>
                <div className="border-b border-white" />
                <Link
                  to="/monitoring-modal"
                  ref={el =>
                    menuRefs.current["monitoring-modal"] = el
                  }
                  className={getSubMenuClass("monitoring-modal")}
                >
                  <CurrencyDollarIcon className="h-4 w-4" />
                  {!isCollapsed && <span>Monitoring Modal</span>}
                </Link>
                <div className="border-b border-white" />
                <Link
                  to="/monitoring-RAB"
                  ref={el =>
                    menuRefs.current["monitoring-rab"] = el
                  }
                  className={getSubMenuClass("monitoring-rab")}
                >
                  <DocumentTextIcon className="h-4 w-4" />
                  {!isCollapsed && <span>Monitoring RAB</span>}
                </Link>
                <div className="border-b border-white" />
                <Link
                  to="/Master_dat_pr"
                  ref={el =>
                    menuRefs.current["master-pr"] = el
                  }
                  className={getSubMenuClass("master-pr")}
                >
                  <DocumentCheckIcon className="h-4 w-4" />
                  {!isCollapsed && <span>Master AT/PR</span>}
                </Link>
                <div className="border-b border-white" />
              </div>
            </div>
          </div>

          {/* E-MAIL */}
          <div>
            <button
              ref={el =>
                menuRefs.current["mail"] = el
              }
              className={getMenuClass("mail")}
              onClick={() => toggleMenu("mail")}
              title={isCollapsed ? "E-Mail" : ""}
            >
              <span className="flex items-center space-x-2">
                <MailIcon className="h-5 w-5" />
                {!isCollapsed && <span>E-Mail</span>}
              </span>
              {!isCollapsed && (
                <ChevronDownIcon
                  className={`h-5 w-5 transform transition-transform duration-300 ${
                    openMenus.mail ? "rotate-180" : "rotate-0"
                  }`}
                />
              )}
            </button>
            <div className="border-b border-white" />
            <div
              className={`overflow-hidden transition-all duration-500 ease-in-out ${
                openMenus.mail && !isCollapsed ? "max-h-96" : "max-h-0"
              }`}
            >
              <div className="pl-8 space-y-1 mt-1">
                <Link
                  to="/Inbox"
                  ref={el =>
                    menuRefs.current["inbox"] = el
                  }
                  className={getSubMenuClass("inbox")}
                >
                  <InboxIcon className="h-4 w-4" />
                  {!isCollapsed && <span>Inbox</span>}
                </Link>
                <div className="border-b border-white" />
                <Link
                  to="/Mail"
                  ref={el =>
                    menuRefs.current["setting-mail"] = el
                  }
                  className={getSubMenuClass("setting-mail")}
                  title={isCollapsed ? "Mail" : ""}
                >
                  <SettingsIcon className="h-4 w-4" />
                  {!isCollapsed && <span>Setting Mail</span>}
                </Link>
                <div className="border-b border-white" />
              </div>
            </div>
          </div>

          {/* Bank Statement Menu */}
          <div>
            <button
              onClick={() => toggleMenu("statement")}
              ref={el =>
                menuRefs.current["statement"] = el
              }
              className={getMenuClass("statement")}
              title={isCollapsed ? "Bank Statement" : ""}
            >
              <span className="flex items-center space-x-2">
                <BanknotesIcon className="h-5 w-5" />
                {!isCollapsed && <span>Bank Statement</span>}
              </span>
              {!isCollapsed && (
                <ChevronDownIcon
                  className={`h-5 w-5 transform transition-transform duration-300 ${
                    openMenus.statement ? "rotate-180" : "rotate-0"
                  }`}
                />
              )}
            </button>
            <div className="border-b border-white" />
            <div
              className={`overflow-hidden transition-all duration-500 ease-in-out ${
                openMenus.statement && !isCollapsed ? "max-h-96" : "max-h-0"
              }`}
            >
              <div className="pl-8 space-y-1 mt-1">
                <Link
                  to="/monitoring-saldo"
                  ref={el =>
                    menuRefs.current["monitoring-saldo"] = el
                  }
                  className={getSubMenuClass("monitoring-saldo")}
                  title={isCollapsed ? "Monitoring Saldo" : ""}
                >
                  <ArrowDownTrayIcon className="h-4 w-4" />
                  {!isCollapsed && <span>Download Mutasi</span>}
                </Link>
                <div className="border-b border-white" />
                <Link
                  to="/daily_statement"
                  ref={el =>
                    menuRefs.current["mutasi-harian"] = el
                  }
                  className={getSubMenuClass("mutasi-harian")}
                  title={isCollapsed ? "Bank Statement" : ""}
                >
                  <DocumentDuplicateIcon className="h-4 w-4" />
                  {!isCollapsed && <span>Mutasi Harian</span>}
                </Link>
                <div className="border-b border-white" />
                <Link
                  to="/mutasi_search"
                  ref={el =>
                    menuRefs.current["mutasi-search"] = el
                  }
                  className={getSubMenuClass("mutasi-search")}
                  title={isCollapsed ? "Pencarian" : ""}
                >
                  <MagnifyingGlassIcon className="h-4 w-4" />
                  {!isCollapsed && <span>Pencarian Transaksi</span>}
                </Link>
                <div className="border-b border-white" />
                <Link
                  to="/reconciliation"
                  ref={el =>
                    menuRefs.current["reconciliation"] = el
                  }
                  className={getSubMenuClass("reconciliation")}
                  title={isCollapsed ? "Reconciliation" : ""}
                >
                  <CalculatorIcon className="h-4 w-4" />
                  {!isCollapsed && <span>Reconciliation</span>}
                </Link>
                <div className="border-b border-white" />
                <Link
                  to="/Rekon_AR"
                  ref={el =>
                    menuRefs.current["rekon_ar"] = el
                  }
                  className={getSubMenuClass("rekon_ar")}
                  title={isCollapsed ? "Rekon AR" : ""}
                >
                  <CalculatorIcon className="h-4 w-4" />
                  {!isCollapsed && <span>Rekon AR</span>}
                </Link>
                <div className="border-b border-white" />
                <Link
                  to="/gl"
                  ref={el =>
                    menuRefs.current["import-gl"] = el
                  }
                  className={getSubMenuClass("import-gl")}
                  title={isCollapsed ? "Import GL" : ""}
                >
                  <BookOpenIcon className="h-4 w-4" />
                  {!isCollapsed && <span>Import GL</span>}
                </Link>
                <div className="border-b border-white" />
              </div>
            </div>
          </div>
          
          {/* Report Menu */}
          <div>
            <button
              onClick={() => toggleMenu("report")}
              ref={el =>
                menuRefs.current["report"] = el
              }
              className={getMenuClass("report")}
              title={isCollapsed ? "Report" : ""}
            >
              <span className="flex items-center space-x-2">
                <DocumentChartBarIcon className="h-5 w-5" />
                {!isCollapsed && <span>Report</span>}
              </span>
              {!isCollapsed && (
                <ChevronDownIcon
                  className={`h-5 w-5 transform transition-transform duration-300 ${
                    openMenus.report ? "rotate-180" : "rotate-0"
                  }`}
                />
              )}
            </button>
            <div className="border-b border-white" />

            {/* Submenu */}
            <div
              className={`overflow-hidden transition-all duration-500 ease-in-out ${
                openMenus.report && !isCollapsed ? "max-h-96" : "max-h-0"
              }`}
            >
              <div className="pl-8 space-y-1 mt-1">
                <Link
                  to="/hutang_dagang"
                  ref={el =>
                    menuRefs.current["hutang-dagang"] = el
                  }
                  className={getSubMenuClass("hutang-dagang")}
                  title={isCollapsed ? "Hutang Dagang" : ""}
                >
                  <ReceiptRefundIcon className="h-4 w-4" />
                  {!isCollapsed && <span>Hutang Dagang</span>}
                </Link>
                <div className="border-b border-white" />
                <Link
                  to="/bank_statement"
                  ref={el =>
                    menuRefs.current["bank-statement"] = el
                  }
                  className={getSubMenuClass("bank-statement")}
                  title={isCollapsed ? "Bank Statement" : ""}
                >
                  <BanknotesIcon className="h-4 w-4" />
                  {!isCollapsed && <span>Bank Statment</span>}
                </Link>
                <div className="border-b border-white" />
                
              </div>
            </div>
          </div>
          
          {/* Setting Menu */}
          <div>
            <button
              onClick={() => toggleMenu("setting")}
              ref={el =>
                menuRefs.current["setting"] = el
              }
              className={getMenuClass("setting")}
              title={isCollapsed ? "setting" : ""}
            >
              <span className="flex items-center space-x-2">
                <AdjustmentsVerticalIcon className="h-5 w-5" />
                {!isCollapsed && <span>Setting</span>}
              </span>
              {!isCollapsed && (
                <ChevronDownIcon
                  className={`h-5 w-5 transform transition-transform duration-300 ${
                    openMenus.setting ? "rotate-180" : "rotate-0"
                  }`}
                />
              )}
            </button>
            <div className="border-b border-white" />

            {/* Submenu */}
            <div
              className={`overflow-hidden transition-all duration-500 ease-in-out ${
                openMenus.setting && !isCollapsed ? "max-h-96" : "max-h-0"
              }`}
            >
              <div className="pl-8 space-y-1 mt-1">
                <Link
                  to="/Users"
                  ref={el =>
                    menuRefs.current["users"] = el
                  }
                  className={getSubMenuClass("users")}
                  title={isCollapsed ? "Hutang Dagang" : ""}
                >
                  <UsersIcon className="h-4 w-4" />
                  {!isCollapsed && <span>Users</span>}
                </Link>
                <div className="border-b border-white" />
                <Link
                  to="/Periode"
                  ref={el =>
                    menuRefs.current["periode"] = el
                  }
                  className={getSubMenuClass("periode")}
                  title={isCollapsed ? "Periode" : ""}
                >
                  <CalendarDaysIcon className="h-4 w-4" />
                  {!isCollapsed && <span>Periode</span>}
                </Link>
                <div className="border-b border-white" />
                <Link
                  to="/bank"
                  ref={el =>
                    menuRefs.current["bank"] = el
                  }
                  className={getSubMenuClass("bank")}
                  title={isCollapsed ? "Bank" : ""}
                >
                  <BuildingLibraryIcon className="h-4 w-4" />
                  {!isCollapsed && <span>Bank</span>}
                </Link>
                <div className="border-b border-white" />
              </div>
            </div>
          </div>

          {/* Extras Menu */}
          <div>
            <button
              onClick={() => toggleMenu("extras")}
              ref={el =>
                menuRefs.current["extras"] = el
              }
              className={getMenuClass("extras")}
              title={isCollapsed ? "setting" : ""}
            >
              <span className="flex items-center space-x-2">
                <PuzzlePieceIcon className="h-5 w-5" />
                {!isCollapsed && <span>Extras</span>}
              </span>
              {!isCollapsed && (
                <ChevronDownIcon
                  className={`h-5 w-5 transform transition-transform duration-300 ${
                    openMenus.extras ? "rotate-180" : "rotate-0"
                  }`}
                />
              )}
            </button>
            <div className="border-b border-white" />

            {/* Submenu */}
            <div
              className={`overflow-hidden transition-all duration-500 ease-in-out ${
                openMenus.extras && !isCollapsed ? "max-h-96" : "max-h-0"
              }`}
            >
              <div className="pl-8 space-y-1 mt-1">
                <Link
                  to="/Merge_pdf"
                  ref={el =>
                    menuRefs.current["merge-pdf"] = el
                  }
                  className={getSubMenuClass("merge-pdf")}
                  title={isCollapsed ? "Merge Pdf" : ""}
                >
                  <DocumentArrowDownIcon className="h-4 w-4" />
                  {!isCollapsed && <span>Merge Pdf</span>}
                </Link>
                <div className="border-b border-white" />
                <Link
                  to="/Lpd_pdf"
                  ref={el =>
                    menuRefs.current["lpd-pdf"] = el
                  }
                  className={getSubMenuClass("lpd-pdf")}
                  title={isCollapsed ? "LPD Pdf" : ""}
                >
                  <DocumentMinusIcon className="h-4 w-4" />
                  {!isCollapsed && <span>LPD From PDF</span>}
                </Link>
                <div className="border-b border-white" />
              </div>
            </div>
          </div>

          {/* TASKS */}
          <div>
            <button
              onClick={handleTasksClick}
              ref={el =>
                menuRefs.current["tasks"] = el
              }
              className={getMenuClass("tasks")}
              title={isCollapsed ? "Tasks" : ""}
            >
              <span className="flex items-center space-x-2">
                <ComputerDesktopIcon className="h-5 w-5" />
                {!isCollapsed && <span>Tasks</span>}
              </span>
            </button>
            <div className="border-b border-white" />
          </div>
        </nav>
      </div>

      {/* Logout */}
      <div className="pt-4 border-t border-white px-4">
        <button
          onClick={handleLogout}
          className="flex items-center space-x-2 text-red-600 hover:bg-red-100/50 px-2 py-2 rounded transition font-semibold w-full"
          title={isCollapsed ? "Logout" : ""}
        >
          <ArrowRightOnRectangleIcon className="h-5 w-5" />
          {!isCollapsed && <span>Logout</span>}
        </button>
      </div>
    </aside>
  );
};

export default Sidebar;
