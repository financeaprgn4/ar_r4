import React, { useState, useEffect, useRef, useMemo } from 'react';
import {
  handleCreateLPD, handleCreateAllLPD,
  handleUpdateketerangan, handleUpdateAllKeterangan,
  handleAutomatchSarana, handleAutomatchSaranaAll,
  handleUpdateATPR, handleUpdateATPRAll,
  handleAutomatchDatpr, handleAutomatchDatprAll,
  handleSyncPP, handleSyncPPAll
} from '../utility/exportLPD';
import { fileUrl } from "../config/fileUrl"
import { useNavigate, useLocation } from 'react-router-dom';
import {
  FaHome, FaPlus, FaFileAlt, FaFileExcel, FaBook,
  FaChartBar, FaFile, FaDownload, FaTimes, FaChevronDown,
  FaChevronUp, FaFilePdf, FaEdit, FaFileImport, FaDesktop,
  FaFileUpload, FaRecycle, FaSearch
} from 'react-icons/fa';
import { openDownload } from "../config/openDownload";

const Menu_LPD = ({ isOpen, onClose, onOpenDrawer, isDrawerOpen, onToggleRightPanel, berkas, identitas, data, onExportExcel, cabang, fetchData, fetchTables }) => {
  const [showPdfSubmenu, setShowPdfSubmenu] = useState(false);
  const [focusedIndex, setFocusedIndex] = useState(0);
  const menuRefs = useRef([]);
  const navigate = useNavigate();
  const location = useLocation();
  const pdfSubmenu = useMemo(() => {
    if (!berkas) return [];
    
    const submenu = [
      { key: 'lpd', label: 'LPD PDF' },
      { key: 'rab_rekap', label: 'RAB Rekap' },
      { key: 'rab_detail', label: 'RAB Detail' },
      { key: 'proposal', label: 'Proposal' },
      { key: 'termin_invest', label: 'Termin Investasi' },
      { key: 'draft_cs', label: 'Draft Clearencesheet' },
      { key: 'item_tdk_realisasi', label: 'BA Tidak Realisasi' },
      { key: 'pot_surkas', label: 'BA Pot Surkas' }
    ];
    
    return submenu
      .filter(item => berkas[item.key])
      .map(item => ({
        ...item,
        icon: <FaFilePdf className="w-4 h-4 text-red-500" />,
        action: () => {
          const url = fileUrl(`/file/${item.key}/${berkas[item.key]}?v=${Date.now()}`);
          window.open(url, "_blank");
        }
      }));
  }, [berkas]);

  const menuItems = useMemo(() => {
    const items = [];

    if (location.pathname !== '/lpd/outs')
      items.push({ icon: <FaHome className="w-4 h-4" />, label: 'Home', path: '/lpd/outs' });

    if (location.pathname === '/lpd/outs')
      items.push(
        { icon: <FaPlus className="w-4 h-4" />, label: 'Add Site', action: () => onToggleRightPanel(null, 'add') },
        { icon: <FaFileAlt className="w-4 h-4" />, label: 'Berkas LPD', path: '/Berkas_lpd' }
      );
    
    if (location.pathname === '/DATPR_Unmatch') {
      items.push(
        { icon: <FaFileExcel className="w-4 h-4 text-green-500" />, label: 'Export DAT/PR Unmatch', action: onExportExcel}
      );
    }

    if (location.pathname === '/Inv_Unmatch_datpr') {
      items.push(
        { icon: <FaFileExcel className="w-4 h-4 text-green-500" />, label: 'Export Inv Unmatch AT/PR', action: onExportExcel},
      );
    }

    if (!['/lpd-modal', '/lpd/outs', '/lpd-cs', '/lpd-final', '/Berkas_lpd', '/lpd-rab', '/monitoring-modal', '/monitoring-RAB', '/DATPR_Unmatch', '/Inv_Unmatch_datpr', '/Inv_Unmatch_sarana'].includes(location.pathname)) {
      items.push({ icon: <FaBook className="w-4 h-4" />, label: 'PDF Documents', isSubmenu: true });
      items.push({ icon: <FaFileAlt className="w-4 h-4" />, label: 'Modal Detail', path: '/lpd-modal' });
    }
    
    if (['/lpd/outs', '/lpd-cs', '/lpd-final', '/Berkas_lpd', '/DATPR_Unmatch', '/Inv_Unmatch_datpr', '/Inv_Unmatch_sarana'].includes(location.pathname)) {
      items.push(
        {
          icon: <FaRecycle className="w-4 h-4 text-yellow-400" />,
          label: 'Sync PP/SP All',
          action: () => handleSyncPPAll(fetchData),
        },
        {
          icon: <FaRecycle className="w-4 h-4 text-yellow-400" />,
          label: 'Matching Sarana All',
          action: () => handleAutomatchSaranaAll(fetchData),
        },
        {
          icon: <FaRecycle className="w-4 h-4 text-yellow-400" />,
          label: 'Update AT/PR All',
          action: () => handleUpdateATPRAll(fetchData),
        },
        {
          icon: <FaRecycle className="w-4 h-4 text-yellow-400" />,
          label: 'Matching AT/PR All',
          action: () => handleAutomatchDatprAll(fetchData),
        },
        {
          icon: <FaRecycle className="w-4 h-4 text-yellow-400" />,
          label: 'Update All Keterangan',
          action: () => handleUpdateAllKeterangan(fetchData),
        },
        {
          icon: <FaFileExcel className="w-4 h-4 text-green-500" />,
          label: 'Create Perhitungan LPD All',
          action: handleCreateAllLPD,
        },
        { icon: <FaDesktop className="w-4 h-4" />, label: 'RAB LPD VS RAB Final', path: '/monitoring-RAB' },
        { icon: <FaDesktop className="w-4 h-4" />, label: 'Monitoring Modal', path: '/monitoring-modal' },
        { icon: <FaDesktop className="w-4 h-4" />, label: 'Monitoring LPD Project', path: '/lpd-detail' },
        { icon: <FaDesktop className="w-4 h-4" />, label: 'Monitoring Sarana Toko', path: '/monitoring-sarana' },
        {
          icon: <FaBook className="w-4 h-4 text-green-500" />,
          label: 'Report Investasi',
          action: () => openDownload(`/api/report-investasi?cabang=${cabang}`)
        },
        {
          icon: <FaBook className="w-4 h-4 text-green-500" />,
          label: 'Report Outs LPD',
          action: () => openDownload(`/api/report-outs?cabang=${cabang}`)
        },
        {
          icon: <FaBook className="w-4 h-4 text-green-500" />,
          label: 'Report LPD Plus / Minus',
          action: () => openDownload(`/api/report-plus-minus?cabang=${cabang}`)
        },
        { icon: <FaFileAlt className="w-4 h-4" />, label: 'Inv Unmatch DAT/PR', path: '/Inv_Unmatch_datpr' },
        { icon: <FaFileAlt className="w-4 h-4" />, label: 'Inv Unmatch Sarana', path: '/Inv_Unmatch_sarana' },
        { icon: <FaFileAlt className="w-4 h-4" />, label: 'DAT/PR Unmatch Inv', path: '/DATPR_Unmatch' },
        {
          icon: <FaDownload className="w-4 h-4" />,
          label: 'Export DAT/PR',
          action: () => openDownload(`/export-datpr?cabang=${cabang}`)
        },
        { icon: <FaDownload className="w-4 h-4" />,
          label: 'Export Detail' ,
          action: () => openDownload(`/export-detail?cabang=${cabang}`)
        },
      );
    }

    if (location.pathname === '/monitoring-modal') {
      items.push(
        { icon: <FaDownload className="w-4 h-4 text-green-500" />, label: 'Export', path: '/export' }
      );
    }
    
    if (location.pathname === '/lpd-detail') {
      items.push(
        {
          icon: <FaFileExcel className="w-4 h-4 text-green-500" />,
          label: 'Create LPD Excel',
          action: (e) => {
            e?.preventDefault?.();
            handleCreateLPD(identitas.no_rab);
          },
        },
        {
          icon: <FaRecycle className="w-4 h-4 text-yellow-400" />,
          label: 'Sync PP/SP',
          action: (e) => {
            e?.preventDefault?.();
            handleSyncPP(identitas.no_rab);
          },
        },
        {
          icon: <FaRecycle className="w-4 h-4 text-yellow-400" />,
          label: 'Update Keterangan',
          action: (e) => {
            e?.preventDefault?.();
            handleUpdateketerangan(identitas.no_rab);
          },
        },
        { icon: <FaFileAlt className="w-4 h-4" />, label: 'Berkas LPD', path: '/Berkas_lpd' },
        { icon: <FaDesktop className="w-4 h-4" />, label: 'Monitoring Modal', path: '/monitoring-modal' },
        {
          icon: <FaSearch className="w-4 h-4" />,
          label: 'Preview LPD',
          action: () => onToggleRightPanel(data, 'view'),
        },
      );

      if (identitas?.status === 'NEW') {
        items.splice(1, 0, {
          icon: <FaFilePdf className="w-4 h-4 text-yellow-400" />,
          label: 'Create Clearencesheet',
          action: () => isDrawerOpen?.('clearencesheet')
        });
        items.splice(4, 0, {
          icon: <FaPlus className="w-4 h-4" />,
          label: 'Add Transaction',
          action: () => onOpenDrawer?.('create'),
        });
        items.splice(5, 0, {
          icon: <FaFileUpload className="w-4 h-4" />,
          label: 'Upload Perhitungan PDF',
          action: () => onOpenDrawer?.('upload'),
        });
        items.splice(6, 0, {
          icon: <FaFileImport className="w-4 h-4" />,
          label: 'Import Data Trx',
          action: () => onOpenDrawer?.('import'),
        });
      }
      items.push({ icon: <FaFileAlt className="w-4 h-4" />, label: 'LPD Project', action: () => isDrawerOpen?.('lpdprj') });
    }

    if (location.pathname === '/sarana_toko') {
      items.push(
        {
          icon: <FaFileExcel className="w-4 h-4 text-green-500" />,
          label: 'Create LPD Excel',
          action: (e) => {
            e?.preventDefault?.();
            handleCreateLPD(identitas.no_rab);
          },
        },
        {
          icon: <FaRecycle className="w-4 h-4 text-yellow-400" />,
          label: 'Sync PP/SP',
          action: (e) => {
            e?.preventDefault?.();
            handleSyncPP(
              identitas.no_rab,
              () => {
                fetchData();
                fetchTables();
              }
            );
          },
        },
        {
          icon: <FaRecycle className="w-4 h-4 text-yellow-400" />,
          label: 'Update Keterangan',
          action: (e) => {
            e?.preventDefault?.();
            handleUpdateketerangan(identitas.no_rab);
          },
        },
        { icon: <FaFileAlt className="w-4 h-4" />, label: 'Berkas LPD', path: '/Berkas_lpd' },
        { icon: <FaDesktop className="w-4 h-4" />, label: 'Monitoring Modal', path: '/monitoring-modal' },
        {
          icon: <FaSearch className="w-4 h-4" />,
          label: 'Preview LPD',
          action: () => {onToggleRightPanel(data, 'view')}
        },
        {
          icon: <FaFileImport className="w-4 h-4" />,
          label: 'Import Sarana Toko',
          action: () => onOpenDrawer?.('import')
        },
        { icon: <FaFileAlt className="w-4 h-4" />, label: 'Inv Unmatch DAT/PR', path: '/Inv_Unmatch_datpr' },
        { icon: <FaFileAlt className="w-4 h-4" />, label: 'Inv Unmatch Sarana', path: '/Inv_Unmatch_sarana' },
        { icon: <FaFileAlt className="w-4 h-4" />, label: 'DAT/PR Unmatch Inv', path: '/DATPR_Unmatch' },
        {
          icon: <FaRecycle className="w-4 h-4 text-yellow-400" />,
          label: 'Automatch Sarana',
          action: (e) => {
            e?.preventDefault?.();
            handleAutomatchSarana(
              identitas.no_rab,
              () => {
                fetchData();
                fetchTables();
              }
            );
          },
        },
      );
    }

    if (location.pathname === '/dat_pr') {
      items.push(
        {
          icon: <FaFileExcel className="w-4 h-4 text-green-500" />,
          label: 'Create LPD Excel',
          action: (e) => {
            e?.preventDefault?.();
            handleCreateLPD(identitas.no_rab);
          },
        },
        {
          icon: <FaRecycle className="w-4 h-4 text-yellow-400" />,
          label: 'Update Keterangan',
          action: (e) => {
            e?.preventDefault?.();
            handleUpdateketerangan(identitas.no_rab);
          },
        },
        { icon: <FaFileAlt className="w-4 h-4" />, label: 'Berkas LPD', path: '/Berkas_lpd' },
        { icon: <FaDesktop className="w-4 h-4" />, label: 'Monitoring Modal', path: '/monitoring-modal' },
        { icon: <FaFileAlt className="w-4 h-4" />, label: 'DAT/PR Unmatch Inv', path: '/DATPR_Unmatch' },
        {
          icon: <FaSearch className="w-4 h-4" />,
          label: 'Preview LPD',
          action: () => {onToggleRightPanel(data, 'view')}
        },
        {
          icon: <FaFileImport className="w-4 h-4" />,
          label: 'Import Dat/PR',
          action: () => onOpenDrawer?.('import')
        },
        {
          icon: <FaRecycle className="w-4 h-4 text-yellow-400" />,
          label: 'Update AT/PR',
          action: (e) => {
            e?.preventDefault?.();
            handleUpdateATPR(
              identitas.no_rab,
              () => {
                fetchData();
                fetchTables();
              }
            );
          },
        },
        {
          icon: <FaRecycle className="w-4 h-4 text-yellow-400" />,
          label: 'Automatch AT/PR',
          action: (e) => {
            e?.preventDefault?.();
            handleAutomatchDatpr(
              identitas.no_rab,
              () => {
                fetchData();
                fetchTables();
              }
            );
          },
        },
      );
    }

    if (location.pathname === '/lpd-modal')
      items.push(
        { icon: <FaDesktop className="w-4 h-4" />, label: 'Monitoring Modal', path: '/monitoring-modal' },
        { icon: <FaFileAlt className="w-4 h-4" />, label: 'Berkas LPD', path: '/Berkas_lpd' },
        { icon: <FaPlus className="w-4 h-4" />, label: 'Input Modal', action: () => onOpenDrawer?.('input') }
      );

    if (!['/lpd-cs', '/lpd-final', '/lpd/outs', '/sarana_toko', '/Berkas_lpd',, '/monitoring-modal', '/monitoring-RAB'].includes(location.pathname))
      items.push({ icon: <FaChartBar className="w-4 h-4" />, label: 'Sarana Toko VS Realisasi', path: '/sarana_toko' });
    if (!['/lpd-cs', '/lpd-final', '/lpd/outs', '/dat-pr', '/Berkas_lpd', '/monitoring-modal', '/monitoring-RAB'].includes(location.pathname))
      items.push({ icon: <FaFile className="w-4 h-4" />, label: 'DAT/PR', path: '/dat_pr' });
    
    if (!['/lpd-cs', '/lpd-final', '/lpd-detail', '/lpd/outs', '/Berkas_lpd', '/lpd-rab', '/DATPR_Unmatch', '/Inv_Unmatch_datpr', '/monitoring-modal', '/monitoring-RAB'].includes(location.pathname))
      items.push({ icon: <FaEdit className="w-4 h-4" />, label: 'Perhitungan Detail', path: '/lpd-detail' });

    if (location.pathname !== '/lpd-cs')
      items.push({ icon: <FaChartBar className="w-4 h-4" />, label: 'Clearencesheet On Process', path: '/lpd-cs' });

    if (location.pathname !== '/lpd-final')
      items.push({ icon: <FaChartBar className="w-4 h-4" />, label: 'LPD Final', path: '/lpd-final' });

    return items;
  }, [location.pathname, onToggleRightPanel, onOpenDrawer, cabang]);

  const flatMenu = useMemo(() => {
    let items = [];
    menuItems.forEach((item) => {
      items.push(item);
      if (item.isSubmenu && showPdfSubmenu) {
        items = items.concat(pdfSubmenu);
      }
    });
    return items;
  }, [menuItems, pdfSubmenu, showPdfSubmenu]);

  const handleMenuAction = (item) => {
    if (item.isSubmenu) {
      setShowPdfSubmenu((prev) => !prev);
    } else if (typeof item.action === 'function') {
      item.action();
      onClose();
    } else if (item.path) {
      navigate(item.path);
      onClose();
    }
  };

  useEffect(() => {
    if (isOpen && menuRefs.current[focusedIndex]) {
      menuRefs.current[focusedIndex].focus();
    }
  }, [focusedIndex, isOpen]);

  const handleKeyDown = (e) => {
    if (e.key === 'ArrowDown') {
      e.preventDefault();
      setFocusedIndex((prev) => (prev + 1) % flatMenu.length);
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      setFocusedIndex((prev) => (prev - 1 + flatMenu.length) % flatMenu.length);
    } else if (e.key === 'Enter') {
      const item = flatMenu[focusedIndex];
      if (item.isSubmenu) {
        setShowPdfSubmenu((prev) => !prev);
      } else if (typeof item.action === 'function') {
        item.action();
        onClose();
      } else if (item.path) {
        navigate(item.path);
        onClose();
      }
    }
  };

  return (
    <div
      className={`fixed top-0 right-0 h-full w-full sm:w-1/4 bg-black/90 shadow-lg transform transition-transform duration-300 z-50 ${
        isOpen ? 'translate-x-0' : 'translate-x-full'
      }`}
      onKeyDown={handleKeyDown}
      tabIndex={0}
    >
      <div className="relative flex justify-between items-center p-4 border-b">
        <button onClick={onClose} className="text-red-500 font-bold text-xl z-10">
          <FaTimes className="w-4 h-4" />
        </button>
        <h1 className="absolute left-1/2 transform -translate-x-1/2 text-lg font-semibold text-white">
          Menu
        </h1>
      </div>

      <ul className="p-4 overflow-y-auto h-full pb-20 space-y-1 outline-none">
        {flatMenu.map((item, index) => (
          <li
            key={item.label}
            className={`flex items-center gap-2 px-3 py-2 rounded cursor-pointer ${
              focusedIndex === index
                ? 'bg-white text-black'
                : 'bg-white/5 text-white hover:bg-white hover:text-black'
            }`}
            onClick={() => handleMenuAction(item)}
            onKeyDown={(e) => {
              if (e.key === 'Enter') {
                e.preventDefault();
                e.stopPropagation();
                handleMenuAction(item);
              }
            }}
            ref={(el) => (menuRefs.current[index] = el)}
            tabIndex={0}
          >
            {item.icon}
            <span>{item.label}</span>
            {item.isSubmenu && (showPdfSubmenu ? <FaChevronUp /> : <FaChevronDown />)}
          </li>
        ))}
      </ul>
    </div>
  );
};

export default Menu_LPD;
