import React, { createContext, useContext, useState, useEffect, useMemo } from "react";
import ViewLPD from "../components/ViewLPD";
import CreateLPD from "../components/CreateLPD";
import Swal from "sweetalert2";
const RightPanelContext = createContext();

export const RightPanelProvider = ({ children }) => {
  const [show, setShow] = useState(false);
  const [mode, setMode] = useState(null);
  const [content, setContent] = useState(null);
  const [refreshFlag, setRefreshFlag] = useState(false);
  const [selectedSite, setSelectedSite] = useState(null);
  const [inputValues, setInputValues] = useState({});
  const [keterangan, setKeterangan] = useState("");
  const cabang = sessionStorage.getItem("cabang");

  const formFields = [
    { label: "Kode Toko", key: "kd_toko", type: "text", width: "w-[100px]", maxLength: 4 },
    { label: "Nama Toko", key: "nama_toko", type: "text", width: "w-[550px]" },
    { label: "Tipe", key: "jns_toko", type: "select", width: "w-[250px]" },
    { label: "Tgl Proposal", key: "tgl_proposal", type: "date", width: "w-[180px]" },
    { label: "Tgl Waralaba", key: "tgl_wrlb", type: "date", width: "w-[180px]" },
    { label: "Reporting", key: "report", type: "select", width: "w-[210px]" },
    { label: "No RAB", key: "no_rab", type: "text", width: "w-[300px]" },
    { label: "Nilai RAB", key: "rab_final", type: "rupiah", width: "w-[200px]" },
    { label: "Sewa By Frcsee", key: "sewa_frc", type: "rupiah", width: "w-[200px]" },
    { label: "Sewa AT", key: "sewa_at", type: "rupiah", width: "w-[200px]" },
    { label: "Pek By Frcsee", key: "pek_frc", type: "rupiah", width: "w-[200px]" },
    { label: "Nama Badan", key: "badan", type: "text", width: "w-[500px]" },
  ];

  const dataMap = [
    { label: 'Frc Fee', rabKey: 'rab_frc_fee', realKey: 'realisasi_frc_fee' },
    { label: 'Promosi GO', rabKey: 'rab_promo', realKey: 'realisasi_promo' },
    { label: 'Jasa Rekrut & Training', rabKey: 'rab_rekrut_train', realKey: 'realisasi_rekrut_train' },
    { label: 'Sewa Lahan & PPh', rabKey: 'rab_sw_pph', realKey: 'realisasi_sw_pph' },
    { label: 'Jasa Pihak Ke-3', rabKey: 'rab_jasa_pihak3', realKey: 'realisasi_jasa_pihak3' },
    { label: 'Pekerjaan Folding Gate', rabKey: 'rab_fg', realKey: 'realisasi_fg', className: 'text-right' },
    { label: 'Pekerjaan Kanopi', rabKey: 'rab_kanopi', realKey: 'realisasi_kanopi', className: 'text-right' },
    { label: 'Pekerjaan Instalasi AC', rabKey: 'rab_ins_ac', realKey: 'realisasi_ins_ac', className: 'text-right' },
    { label: 'Pekerjaan Teralis', rabKey: 'rab_teralis', realKey: 'realisasi_teralis', className: 'text-right' },
    { label: 'Pekerjaan Halaman', rabKey: 'rab_halaman', realKey: 'realisasi_halaman', className: 'text-right' },
    { label: 'Pekerjaan Polycarbonate', rabKey: 'rab_policarbonate', realKey: 'realisasi_policarbonate', className: 'text-right' },
    { label: 'Pekerjaan Listrik', rabKey: 'rab_listrik', realKey: 'realisasi_listrik', className: 'text-right' },
    { label: 'Pekerjaan Aluminium & Kaca', rabKey: 'rab_aluminium_kaca', realKey: 'realisasi_aluminium_kaca', className: 'text-right' },
    { label: 'Pekerjaan Signage', rabKey: 'rab_signage', realKey: 'realisasi_signage', className: 'text-right' },
    { label: 'Pekerjaan Interior Eksterior', rabKey: 'rab_interior', realKey: 'realisasi_interior', className: 'text-right' },
    { label: 'Pekerjaan Sipil', rabKey: 'rab_sipil', realKey: 'realisasi_sipil', className: 'text-right' },
    { label: 'Pekerjaan Urugan & Pemadatan', rabKey: 'rab_urugan', realKey: 'realisasi_urugan', className: 'text-right' },
    { label: 'Pekerjaan Lift', rabKey: 'rab_lift', realKey: 'realisasi_lift', className: 'text-right' },
    { label: 'Prasarana Bangunan', rabKey: 'rab_prasarana', realKey: 'realisasi_prasarana' },
    { label: 'Peralatan Elektronik & Non Elektronik', rabKey: 'rab_peralatan', realKey: 'realisasi_peralatan' },
  ];

  useEffect(() => {
    if (selectedSite && mode !== 'edit') {
      const initialValues = {};
      dataMap.forEach(({ rabKey }) => {
        initialValues[rabKey] = selectedSite[rabKey] || "";
      });
      setInputValues((prev) => ({ ...prev, ...initialValues }));
      setKeterangan(selectedSite.keterangan || "");
    }
  }, [selectedSite]);


  const parseRupiah = (val) => val.replace(/[^0-9]/g, "");

  const handleChange = (e, key, type = "text") => {
    let value = e.target.value;
    if (type === "rupiah") {
      value = parseRupiah(value);
      if (value.length > 1 && value.startsWith("0")) {
        value = value.replace(/^0+/, "");
      }
    }
    setInputValues((prev) => ({ ...prev, [key]: value }));
  };

  const estimasi_renov = useMemo(() =>
    [
      'rab_fg', 'rab_kanopi', 'rab_ins_ac', 'rab_teralis', 'rab_halaman',
      'rab_policarbonate', 'rab_listrik', 'rab_aluminium_kaca',
      'rab_signage', 'rab_sipil', 'rab_urugan', 'rab_lift', 'rab_interior'
    ].reduce((sum, key) => sum + (Number(selectedSite?.[key]) || 0), 0)
  , [selectedSite]);

  const total_estimasi = useMemo(() =>
    [
      'rab_frc_fee', 'rab_promo', 'rab_rekrut_train', 'rab_sw_pph', 'rab_jasa_pihak3',
      'rab_prasarana', 'rab_peralatan'
    ].reduce((sum, key) => sum + (Number(selectedSite?.[key]) || 0), 0) + estimasi_renov
  , [selectedSite, estimasi_renov]);

  const realisasi_renov = useMemo(() =>
    [
      'realisasi_fg', 'realisasi_kanopi', 'realisasi_ins_ac', 'realisasi_teralis',
      'realisasi_halaman', 'realisasi_policarbonate', 'realisasi_listrik',
      'realisasi_aluminium_kaca', 'realisasi_signage', 'realisasi_sipil', 'realisasi_urugan', 'realisasi_lift', 'realisasi_interior'
    ].reduce((sum, key) => sum + (selectedSite?.[key] ?? 0), 0)
  , [selectedSite]);

  const total_realisasi = useMemo(() =>
    [
      'realisasi_frc_fee', 'realisasi_promo', 'realisasi_rekrut_train',
      'realisasi_sw_pph', 'realisasi_jasa_pihak3',
      'realisasi_prasarana', 'realisasi_peralatan'
    ].reduce((sum, key) => sum + (selectedSite?.[key] ?? 0), 0) + realisasi_renov
  , [selectedSite, realisasi_renov]);

  const openPanel = (panelMode, panelContent) => {
    setMode(panelMode);
    setContent(panelContent);
    setShow(true);
  };

  const openLPDPanel = async (panelMode, site) => {
    const fetchLPDDetail = async (no_rab) => {
      try {
        const res = await fetch(`/api/lpd-detail?no_rab=${no_rab}`);
        const data = await res.json();
        return res.ok && data?.data?.length > 0 ? data.data[0] : null;
      } catch (err) {
        console.error("Gagal fetch detail LPD:", err);
        return null;
      }
    };

    const mapDetailToInput = (data, isCopy = false) => {
      const base = {
        kd_toko: data.kd_toko ?? "",
        nama_toko: data.nama_toko ?? "",
        jns_toko: data.jns_toko ?? "",
        tgl_proposal: data.tgl_proposal ?? "",
        tgl_wrlb: data.tgl_wrlb ?? "",
        report: data.report ?? "",
        no_rab: data.no_rab ?? "",
        rab_final: data.rab_final ?? 0,
        sewa_frc: data.sewa_frc ?? 0,
        sewa_at: data.sewa_at ?? 0,
        pek_frc: data.pek_frc ?? 0,
        badan: data.badan ?? "",
      };

      if (isCopy) {
        const newData = {
          ...base,
          no_rab: "",
          jns_toko: "",
          report: "",
          rab_final: 0,
          sewa_frc: 0,
          sewa_at: 0,
          pek_frc: 0,
          tgl_proposal: "",
          tgl_wrlb: data.tgl_wrlb
            ? (() => {
                const d = new Date(data.tgl_wrlb);
                d.setFullYear(d.getFullYear() + 5);
                return d.toISOString().split("T")[0];
              })()
            : "",
        };
        return newData;
      }

      return base;
    };

    let detail = site;

    if (site?.no_rab) {
      const fetched = await fetchLPDDetail(site.no_rab);
      if (fetched) detail = fetched;
    }

    setSelectedSite(detail);

    switch (panelMode) {
      case "view":
        openPanel(panelMode, <ViewLPD mode="view" show={true} />);
        break;

      case "edit":
      case "copy": {
        const mappedInput = mapDetailToInput(detail, panelMode === "copy");
        setInputValues(mappedInput);
        openPanel(panelMode, <CreateLPD mode={panelMode} show={true} />);
        break;
      }

      default:
        setInputValues({});
        openPanel("add", <CreateLPD mode="add" show={true} />);
    }
  };

  const closePanel = () => {
    setShow(false);
    setContent(null);
    setMode(null);
    setSelectedSite(null);
    setInputValues({});
    setKeterangan("");
  };

  const handleReset = () => {
    setInputValues({});
  };
  
  const handleSave = async (no_rab, formData) => {
    try {
      const estimasiData = {};
      dataMap.forEach((item) => {
        estimasiData[item.rabKey] = Number(inputValues[item.rabKey]) || 0;
      });
    
      const payload = {
          no_rab,
          estimasi: estimasiData,
      };
      
      if (formData.status?.toLowerCase() === "final") {
        payload.catatan_final = formData.keterangan || "";
      } else {
        payload.keterangan = formData.keterangan || "";
      }

      const response = await fetch(`/api/update-lpd`, {
          method: "PUT",
          headers: {
          "Content-Type": "application/json",
          },
          body: JSON.stringify(payload),
      });
    
      const result = await response.json();
      if (response.ok) {
          Swal.fire({
          icon: 'success',
          title: 'Sukses!',
          text: result.message,
          }).then(() => {
              closePanel();
              setRefreshFlag(prev => !prev);
          });
      } else {
          Swal.fire({
          icon: 'error',
          title: 'Gagal!',
          text: result.message || 'Terjadi kesalahan saat menyimpan.',
          });
      }
    } catch (error) {
      Swal.fire({
          icon: 'error',
          title: 'Error!',
          text: error.message || 'Terjadi kesalahan jaringan.',
      });
    }
  };

  const handleUpdate = async (no_rab, formData) => {
    try {
      const beforeChanges = {};
      const afterChanges = {};

      const editableFields = [
        "kd_toko", "nama_toko", "jns_toko", "tgl_proposal", "tgl_wrlb", "report",
        "no_rab", "rab_final", "sewa_frc", "sewa_at", "pek_frc", "badan"
      ];

      editableFields.forEach((field) => {
        const before = selectedSite?.[field] ?? "";
        const after = inputValues[field] ?? "";
        if (before !== after) {
          beforeChanges[field] = before;
          afterChanges[field] = after;
        }
      });

      const payload = {
        no_rab,
        changes: editableFields.reduce((acc, field) => {
          acc[field] = {
            before: selectedSite[field] ?? "",
            after: inputValues[field] ?? "",
          };
          return acc;
        }, {}),
      };
      
      const response = await fetch(`/api/update-lpd-site`, {
        method: "PUT",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify(payload),
      });

      const result = await response.json();

      if (response.ok) {
        Swal.fire({
          icon: 'success',
          title: 'Sukses!',
          text: result.message,
        }).then(() => {
          closePanel();
          setRefreshFlag((prev) => !prev);
        });
      } else {
        Swal.fire({
          icon: 'error',
          title: 'Gagal!',
          text: result.message || 'Terjadi kesalahan saat menyimpan.',
        });
      }
    } catch (error) {
      Swal.fire({
        icon: 'error',
        title: 'Error!',
        text: error.message || 'Terjadi kesalahan jaringan.',
      });
    }
  };

  const handleStore = async (e) => {
    e.preventDefault();

    try {
      const payload = {};

      formFields.forEach(({ key, type }) => {
        let value = inputValues[key] || "";

        if (type === "rupiah") {
          value = Number(value.toString().replace(/\D/g, ""));
        }

        payload[key] = value;
      });

      payload.cabang = cabang;

      const response = await fetch(`/api/store-lpd`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify(payload),
      });

      const data = await response.json();

      if (!response.ok) {
        throw new Error(data.message || "Gagal menyimpan data");
      }

      Swal.fire({
        icon: 'success',
        title: 'Berhasil',
        html: `<pre style="text-align:left;white-space:pre-wrap">${data.message}</pre>`,
      }).then(() => {
        handleReset();
        setRefreshFlag(prev => !prev);
        setShow(false);
      });

    } catch (err) {
      Swal.fire({
        icon: 'error',
        title: 'Gagal!',
        text: `Terjadi kesalahan: ${err.message}`,
      });
    }
  };
  
  return (
    <RightPanelContext.Provider
      value={{
        show,
        content,
        mode,
        openPanel,
        openLPDPanel,
        closePanel,
        selectedSite,
        inputValues,
        handleChange,
        dataMap,
        total_estimasi,
        total_realisasi,
        keterangan,
        handleReset,
        handleSave,
        handleUpdate,
        handleStore,
        refreshFlag,
        formFields,
      }}
    >
      {children}
    </RightPanelContext.Provider>
  );
};

export const useRightPanel = () => useContext(RightPanelContext);
