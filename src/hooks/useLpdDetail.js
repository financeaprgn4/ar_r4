import { useCallback, useEffect, useMemo, useState } from 'react';
import axios from "../config/axiosInstance";

export const useLpdDetail = (no_rab) => {
  const [initialLoading, setInitialLoading] = useState(true);
  const [loading, setLoading] = useState(false);

  const [detailState, setDetailState] = useState({
    data: [],
    rekap: {},
    modalData: [],
    modaldetailData: [],
    saranaData: [],
    datprData: [],
    bapjRenovData: [],
    identitas: {},
    berkas: {},
    estimasi: {},
    realisasi: {},
    flag_realisasi: {},
    totalToko: 0,
    dppToko: 0,
    ppnToko: 0,
  });

  const fetchData = useCallback(async () => {
    if (!no_rab) return;
    
    if (initialLoading) {
      setInitialLoading(true);
    } else {
      setLoading(true);
    }

    try {
      const encodedNoRab = encodeURIComponent(no_rab);
      const response = await axios.get(`/api/lpd-detail?no_rab=${encodedNoRab}`);
      const result = response.data;

      if (result?.data?.length > 0) {
        const firstItem = result.data[0];
        const allData = firstItem.all_data || { rows: [], total_sum: 0, total_dpp: 0, total_ppn: 0 };

        const identitas = {
          kd_toko: firstItem.kd_toko,
          nama_toko: firstItem.nama_toko,
          badan: firstItem.badan,
          no_rab,
          jns_toko: firstItem.jns_toko,
          tgl_wrlb: firstItem.tgl_wrlb,
          tgl_jt: firstItem.tgl_jt,
          status: firstItem.status,
          keterangan: firstItem.keterangan,
          rab_final: firstItem.rab_final,
        };

        const berkas = {
          lpd_excel: firstItem.excel,
          lpd: firstItem.pdf,
          rab_rekap: firstItem.rab_rekap,
          rab_detail: firstItem.rab_detail,
          termin_invest: firstItem.termin_invest,
          proposal: firstItem.proposal,
          draft_cs: firstItem.draft_cs,
          item_tdk_realisasi: firstItem.item_tdk_realisasi,
          pot_surkas: firstItem.pot_surkas,
        };

        const estimasi = {
          est_frc_fee: firstItem.rab_frc_fee,
          est_promosi: firstItem.rab_promo,
          est_rekrut: firstItem.rab_rekrut_train,
          est_sewa: firstItem.rab_sw_pph,
          est_jasa: firstItem.rab_jasa_pihak3,
          est_fg: firstItem.rab_fg,
          est_kanopi: firstItem.rab_kanopi,
          est_ins_ac: firstItem.rab_ins_ac,
          est_teralis: firstItem.rab_teralis,
          est_halaman: firstItem.rab_halaman,
          est_policarbonate: firstItem.rab_policarbonate,
          est_listrik: firstItem.rab_listrik,
          est_aluminium_kaca: firstItem.rab_aluminium_kaca,
          est_signage: firstItem.rab_signage,
          est_sipil: firstItem.rab_sipil,
          est_urugan: firstItem.rab_urugan,
          est_interior: firstItem.rab_interior,
          est_lift: firstItem.rab_lift,
          est_prasarana: firstItem.rab_prasarana,
          est_peralatan: firstItem.rab_peralatan,
        };

        const realisasi = {
          realisasi_frc_fee: firstItem.realisasi_frc_fee,
          realisasi_promosi: firstItem.realisasi_promo,
          realisasi_rekrut: firstItem.realisasi_rekrut_train,
          realisasi_sewa: firstItem.realisasi_sw_pph,
          realisasi_jasa: firstItem.realisasi_jasa_pihak3,
          realisasi_fg: firstItem.realisasi_fg,
          realisasi_kanopi: firstItem.realisasi_kanopi,
          realisasi_ins_ac: firstItem.realisasi_ins_ac,
          realisasi_teralis: firstItem.realisasi_teralis,
          realisasi_halaman: firstItem.realisasi_halaman,
          realisasi_policarbonate: firstItem.realisasi_policarbonate,
          realisasi_listrik: firstItem.realisasi_listrik,
          realisasi_aluminium_kaca: firstItem.realisasi_aluminium_kaca,
          realisasi_signage: firstItem.realisasi_signage,
          realisasi_sipil: firstItem.realisasi_sipil,
          realisasi_urugan: firstItem.realisasi_urugan,
          realisasi_interior: firstItem.realisasi_interior,
          realisasi_lift: firstItem.realisasi_lift,
          realisasi_prasarana: firstItem.realisasi_prasarana,
          realisasi_peralatan: firstItem.realisasi_peralatan,
        };

        const flag_realisasi = {
          flag_realisasi_fg: firstItem.flag_realisasi_fg,
          flag_realisasi_kanopi: firstItem.flag_realisasi_kanopi,
          flag_realisasi_ins_ac: firstItem.flag_realisasi_ins_ac,
          flag_realisasi_teralis: firstItem.flag_realisasi_teralis,
          flag_realisasi_halaman: firstItem.flag_realisasi_halaman,
          flag_realisasi_policarbonate: firstItem.flag_realisasi_policarbonate,
          flag_realisasi_listrik: firstItem.flag_realisasi_listrik,
          flag_realisasi_aluminium_kaca: firstItem.flag_realisasi_aluminium_kaca,
          flag_realisasi_signage: firstItem.flag_realisasi_signage,
          flag_realisasi_sipil: firstItem.flag_realisasi_sipil,
          flag_realisasi_urugan: firstItem.flag_realisasi_urugan,
          flag_realisasi_interior: firstItem.flag_realisasi_interior,
          flag_realisasi_lift: firstItem.flag_realisasi_lift,
        };

        setDetailState({
          data: result.data,
          rekap: firstItem,
          modalData: result.modal || [],
          modaldetailData: result.modal_detail || [],
          saranaData: result.sarana || [],
          datprData: result.datpr || [],
          identitas,
          berkas,
          estimasi,
          realisasi,
          flag_realisasi,
          totalToko: allData.total_sum || 0,
          dppToko: allData.total_dpp || 0,
          ppnToko: allData.total_ppn || 0,
          bapjRenovData: result.bapj_renov || [],
        });
      }
    } catch (error) {
      console.error('Gagal mengambil data:', error);
    } finally {
      setInitialLoading(false);
      setLoading(false);
    }
  }, [no_rab, initialLoading]);

  useEffect(() => {
    fetchData();
  }, [fetchData]);

  // Gunakan useMemo untuk menghitung total
  const computed = useMemo(() => {
    const {
      modaldetailData,
      saranaData,
      datprData,
      estimasi,
    } = detailState;

    const totalModal = modaldetailData.reduce((sum, item) => sum + (item.nilai || 0), 0);

    const totalRenov =
      (Number(estimasi.est_fg) || 0) +
      (Number(estimasi.est_kanopi) || 0) +
      (Number(estimasi.est_ins_ac) || 0) +
      (Number(estimasi.est_teralis) || 0) +
      (Number(estimasi.est_halaman) || 0) +
      (Number(estimasi.est_poli) || 0) +
      (Number(estimasi.est_listrik) || 0) +
      (Number(estimasi.est_aluminium_kaca) || 0) +
      (Number(estimasi.est_signage) || 0) +
      (Number(estimasi.est_sipil) || 0) +
      (Number(estimasi.est_urugan) || 0) +
      (Number(estimasi.est_interior) || 0) +
      (Number(estimasi.est_lift) || 0);

    const totalEstimasi = Object.values(estimasi).reduce(
      (acc, curr) => acc + (Number(curr) || 0), 0
    );

    const totalSarana = saranaData.length;

    const totalRealisasi = saranaData.filter(
      item => item.flag_realisasi && item.flag_realisasi !== 'MKT'
    ).length;

    const SaranaBA = saranaData.filter(
      item => item.flag_realisasi && item.flag_realisasi === 'MKT'
    ).length;

    const totalDatPR = datprData
      .filter(item => item.surkas !== 'Y')
      .reduce((sum, item) => sum + (item.harga || 0), 0);

    return {
      totalModal,
      totalRenov,
      totalEstimasi,
      totalSarana,
      totalRealisasi,
      SaranaBA,
      totalDatPR,
    };
  }, [detailState]);

  return {
    ...detailState,
    ...computed,
    fetchData,
    initialLoading,
    loading,
  };
};
