import Swal from 'sweetalert2';
import withReactContent from 'sweetalert2-react-content';
const MySwal = withReactContent(Swal);
import { createSSE } from "../config/sse"

export const handleCreateLPD = async (no_rab) => {
  if (!no_rab) return;

  MySwal.fire({
    title: 'Membuat Laporan...',
    text: 'Silakan tunggu beberapa saat',
    allowOutsideClick: false,
    allowEscapeKey: false,
    allowEnterKey: false,
    showConfirmButton: false,
    didOpen: () => {
      Swal.showLoading();
    },
  });

  try {
    const encodedNoRab = encodeURIComponent(no_rab);
    const response = await fetch(`/api/export-lpd?no_rab=${encodedNoRab}`);
    if (!response.ok) {
      throw new Error('Gagal mengunduh file.');
    }

    Swal.fire({
        icon: 'success',
        title: 'Success',
        text: 'LPD Detail berhasil dibuat!',
        confirmButtonText: 'OK',
    });
  } catch (error) {
    console.error(error);
    Swal.fire({
      icon: 'error',
      title: 'Gagal',
      text: error.message || 'Terjadi kesalahan saat membuat laporan.',
    });
  }
};

export const handleCreateAllLPD = async () => {
  const cabang = sessionStorage.getItem("cabang");
  MySwal.fire({
    title: 'Create Detail LPD',
    html: `
      <p id="progress-desc">Menginisialisasi...</p>
      <p id="progress-bar" style="margin-top: 1rem; background: #eee; width: 100%; height: 10px;">
        <span id="progress-fill" style="display:block; background:#4caf50; width:0%; height:100%"></span>
      </p>
    `,
    allowOutsideClick: false,
    allowEscapeKey: false,
    allowEnterKey: false,
    showConfirmButton: false,
    didOpen: () => Swal.showLoading(),
  });

  const results = [];

  try {
    const eventSource = createSSE(`/api/export-lpd-all?cabang=${encodeURIComponent(cabang)}`);

    eventSource.onmessage = (event) => {
      const data = JSON.parse(event.data);
      const { progress, total, no_rab, filename } = data;

      results.push(data);

      const desc = Swal.getHtmlContainer()?.querySelector('#progress-desc');
      if (desc) desc.innerHTML = `Create LPD Number ${progress} of ${total}<br>No RAB : ${no_rab}<br>${filename}`;

      const fill = Swal.getHtmlContainer()?.querySelector('#progress-fill');
      if (fill) fill.style.width = `${(progress / total) * 100}%`;
    };

    eventSource.addEventListener('done', () => {
      eventSource.close();

      Swal.fire({
        icon: 'success',
        title: 'Success',
        html: `
          <p>Semua Perhitungan LPD Berhasil Diproses</p>
        `,
        confirmButtonText: 'OK',
        width: 600,
      });
    });

    eventSource.onerror = (err) => {
      console.error('SSE error:', err);
      eventSource.close();
      Swal.fire({
        icon: 'error',
        title: 'Gagal',
        text: 'Gagal mendapatkan progress dari server.',
      });
    };
  } catch (err) {
    console.error(err);
    Swal.fire({
      icon: 'error',
      title: 'Gagal',
      text: err.message || 'Terjadi kesalahan saat membuat semua laporan.',
    });
  }
};

export const handleUpdateketerangan = async (no_rab) => {
  if (!no_rab) return;

  MySwal.fire({
    title: 'Update Keterangan...',
    text: 'Silakan tunggu beberapa saat',
    allowOutsideClick: false,
    allowEscapeKey: false,
    allowEnterKey: false,
    showConfirmButton: false,
    didOpen: () => {
      Swal.showLoading();
    },
  });

  try {
    const encodedNoRab = encodeURIComponent(no_rab);
    const response = await fetch(`/api/update-keterangan?no_rab=${encodedNoRab}`);
    if (!response.ok) {
      throw new Error('Gagal mengunduh file.');
    }

    Swal.fire({
        icon: 'success',
        title: 'Success',
        text: 'Keterangan Berhasil Diupdate!',
        confirmButtonText: 'OK',
    });
  } catch (error) {
    Swal.fire({
      icon: 'error',
      title: 'Gagal',
      text: error.message || 'Terjadi kesalahan saat membuat laporan.',
    });
  }
};

export const handleUpdateAllKeterangan = async (onFinish) => {
  const cabang = sessionStorage.getItem("cabang");
  MySwal.fire({
    title: 'Update All Keterangan LPD',
    html: `
      <p id="progress-desc">Gathering Data...</p>
      <p id="progress-bar" style="margin-top: 1rem; background: #eee; width: 100%; height: 10px;">
        <span id="progress-fill" style="display:block; background:#4caf50; width:0%; height:100%"></span>
      </p>
    `,
    allowOutsideClick: false,
    allowEscapeKey: false,
    allowEnterKey: false,
    showConfirmButton: false,
    didOpen: () => Swal.showLoading(),
  });

  const results = [];

  try {
    const eventSource = createSSE(`/api/update-keterangan-all?cabang=${encodeURIComponent(cabang)}`);

    eventSource.onmessage = (event) => {
      const data = JSON.parse(event.data);
      const { progress, total, no_rab } = data;
      results.push(data);

      const desc = Swal.getHtmlContainer()?.querySelector('#progress-desc');
      if (desc) desc.innerHTML = `Update Keterangan LPD Number ${progress} of ${total}<br>No RAB : ${no_rab}`;

      const fill = Swal.getHtmlContainer()?.querySelector('#progress-fill');
      if (fill) fill.style.width = `${(progress / total) * 100}%`;
    };

    eventSource.addEventListener('done', () => {
      eventSource.close();

      Swal.fire({
        icon: 'success',
        title: 'Success',
        html: `<p>Berhasil Update Keterangan All LPD!</p>`,
        confirmButtonText: 'OK',
        width: 600,
      }).then((result) => {
        // ✅ Jalankan fetchData hanya setelah alert sukses ditutup
        if (result.isConfirmed && typeof onFinish === 'function') {
          onFinish();
        }
      });
    });

    eventSource.onerror = (err) => {
      console.error('SSE error:', err);
      eventSource.close();
      Swal.fire({
        icon: 'error',
        title: 'Gagal',
        text: 'Gagal mendapatkan progress dari server.',
      });
    };
  } catch (err) {
    console.error(err);
    Swal.fire({
      icon: 'error',
      title: 'Gagal',
      text: err.message || 'Terjadi kesalahan saat membuat semua laporan.',
    });
  }
};

export const handleAutomatchSarana = async (no_rab, onFinish) => {
  if (!no_rab) return;

  MySwal.fire({
    title: 'Matching Sarana toko...',
    text: 'Silakan tunggu beberapa saat',
    allowOutsideClick: false,
    allowEscapeKey: false,
    allowEnterKey: false,
    showConfirmButton: false,
    didOpen: () => {
      Swal.showLoading();
    },
  });

  try {
    const encodedNoRab = encodeURIComponent(no_rab);
    const response = await fetch(`/api/matching_sarana?no_rab=${encodedNoRab}`);
    if (!response.ok) {
      throw new Error('Gagal mengunduh file.');
    }

    const res = await response.json();
    Swal.fire({
      icon: 'success',
      title: 'Success',
      html: `
        <center>
          <p>${res.message || 'Auto Matching Sarana Berhasil!'}</p>
          <p><b>Jumlah Sarana Match: ${res.matched ?? 0}</b></p>
        </center>
      `,
      confirmButtonText: 'OK',
    }).then((result) => {
      // ✅ refresh table setelah alert ditutup
      if (result.isConfirmed && typeof onFinish === 'function') {
        onFinish();
      }
    });
  } catch (error) {
    Swal.fire({
      icon: 'error',
      title: 'Gagal',
      text: error.message || 'Terjadi kesalahan saat membuat laporan.',
    });
  }
};

export const handleAutomatchSaranaAll = async (onFinish) => {
  const cabang = sessionStorage.getItem("cabang");
  MySwal.fire({
    title: 'Matching Sarana All Toko',
    html: `
      <p id="progress-desc">Gathering Data...</p>
      <p id="progress-bar" style="margin-top: 1rem; background: #eee; width: 100%; height: 10px;">
        <span id="progress-fill" style="display:block; background:#4caf50; width:0%; height:100%"></span>
      </p>
    `,
    allowOutsideClick: false,
    allowEscapeKey: false,
    allowEnterKey: false,
    showConfirmButton: false,
    didOpen: () => Swal.showLoading(),
  });

  const results = [];

  try {
    const eventSource = createSSE(`/api/matching-sarana-all?cabang=${encodeURIComponent(cabang)}`);

    eventSource.onmessage = (event) => {
      const data = JSON.parse(event.data);
      const { progress, total, no_rab } = data;
      results.push(data);

      const desc = Swal.getHtmlContainer()?.querySelector('#progress-desc');
      if (desc) desc.innerHTML = `Rekonsiliasi Sarana Toko Number ${progress} of ${total}<br>No RAB : ${no_rab}`;

      const fill = Swal.getHtmlContainer()?.querySelector('#progress-fill');
      if (fill) fill.style.width = `${(progress / total) * 100}%`;
    };

    eventSource.addEventListener('done', () => {
      eventSource.close();

      Swal.fire({
        icon: 'success',
        title: 'Success',
        html: `<p>Berhasil Matching Sarana All Toko!</p>`,
        confirmButtonText: 'OK',
        width: 600,
      }).then((result) => {
        // ✅ Jalankan fetchData hanya setelah alert sukses ditutup
        if (result.isConfirmed && typeof onFinish === 'function') {
          onFinish();
        }
      });
    });

    eventSource.onerror = (err) => {
      console.error('SSE error:', err);
      eventSource.close();
      Swal.fire({
        icon: 'error',
        title: 'Gagal',
        text: 'Gagal mendapatkan progress dari server.',
      });
    };
  } catch (err) {
    console.error(err);
    Swal.fire({
      icon: 'error',
      title: 'Gagal',
      text: err.message || 'Terjadi kesalahan saat membuat semua laporan.',
    });
  }
};

export const handleUpdateATPR = async (no_rab, onFinish) => {
  if (!no_rab) return;

  MySwal.fire({
    title: 'Updating AT/PR',
    text: 'Silakan tunggu beberapa saat',
    allowOutsideClick: false,
    allowEscapeKey: false,
    allowEnterKey: false,
    showConfirmButton: false,
    didOpen: () => {
      Swal.showLoading();
    },
  });

  try {
    const encodedNoRab = encodeURIComponent(no_rab);
    const response = await fetch(`/api/update_atpr?no_rab=${encodedNoRab}`);
    if (!response.ok) {
      throw new Error('Gagal mengunduh file.');
    }

    const res = await response.json();
    Swal.fire({
      icon: 'success',
      title: 'Success',
      html: `
        <center>
          <p>${res.message || 'Update AT/PR Berhasil!'}</p>
          <p><b>AR/PR Diproses : ${res.matched ?? 0}</b></p>
        </center>
      `,
      confirmButtonText: 'OK',
    }).then((result) => {
      // ✅ refresh table setelah alert ditutup
      if (result.isConfirmed && typeof onFinish === 'function') {
        onFinish();
      }
    });
  } catch (error) {
    Swal.fire({
      icon: 'error',
      title: 'Gagal',
      text: error.message || 'Terjadi kesalahan saat membuat laporan.',
    });
  }
};

export const handleUpdateATPRAll = async (onFinish) => {
  const cabang = sessionStorage.getItem("cabang");
  MySwal.fire({
    title: 'Update AT/PR All Toko',
    html: `
      <p id="progress-desc">Gathering Data...</p>
      <p id="progress-bar" style="margin-top: 1rem; background: #eee; width: 100%; height: 10px;">
        <span id="progress-fill" style="display:block; background:#4caf50; width:0%; height:100%"></span>
      </p>
    `,
    allowOutsideClick: false,
    allowEscapeKey: false,
    allowEnterKey: false,
    showConfirmButton: false,
    didOpen: () => Swal.showLoading(),
  });

  const results = [];

  try {
    const eventSource = createSSE(`/api/update-atpr-all?cabang=${encodeURIComponent(cabang)}`);

    eventSource.onmessage = (event) => {
      const data = JSON.parse(event.data);
      const { progress, total, no_rab } = data;
      results.push(data);

      const desc = Swal.getHtmlContainer()?.querySelector('#progress-desc');
      if (desc) desc.innerHTML = `Update AT/PR Number ${progress} of ${total}<br>No RAB : ${no_rab}`;

      const fill = Swal.getHtmlContainer()?.querySelector('#progress-fill');
      if (fill) fill.style.width = `${(progress / total) * 100}%`;
    };

    eventSource.addEventListener('done', () => {
      eventSource.close();

      Swal.fire({
        icon: 'success',
        title: 'Success',
        html: `<p>Berhasil Update AT/PR All Toko!</p>`,
        confirmButtonText: 'OK',
        width: 600,
      }).then((result) => {
        // ✅ Jalankan fetchData hanya setelah alert sukses ditutup
        if (result.isConfirmed && typeof onFinish === 'function') {
          onFinish();
        }
      });
    });

    eventSource.onerror = (err) => {
      console.error('SSE error:', err);
      eventSource.close();
      Swal.fire({
        icon: 'error',
        title: 'Gagal',
        text: 'Gagal mendapatkan progress dari server.',
      });
    };
  } catch (err) {
    console.error(err);
    Swal.fire({
      icon: 'error',
      title: 'Gagal',
      text: err.message || 'Terjadi kesalahan saat membuat semua laporan.',
    });
  }
};

export const handleAutomatchDatpr = async (no_rab, onFinish) => {
  if (!no_rab) return;

  MySwal.fire({
    title: 'Matching AT/PR...',
    text: 'Silakan tunggu beberapa saat',
    allowOutsideClick: false,
    allowEscapeKey: false,
    allowEnterKey: false,
    showConfirmButton: false,
    didOpen: () => {
      Swal.showLoading();
    },
  });

  try {
    const encodedNoRab = encodeURIComponent(no_rab);
    const response = await fetch(`/api/matching_atpr?no_rab=${encodedNoRab}`);
    if (!response.ok) {
      throw new Error('Gagal mengunduh file.');
    }

    const res = await response.json();
    Swal.fire({
      icon: 'success',
      title: 'Success',
      html: `
        <center>
          <p>${res.message || 'Auto Matching AT/PR Berhasil!'}</p>
          <p><b>Jumlah AT/PR Match: ${res.matched ?? 0}</b></p>
        </center>
      `,
      confirmButtonText: 'OK',
    }).then((result) => {
      // ✅ refresh table setelah alert ditutup
      if (result.isConfirmed && typeof onFinish === 'function') {
        onFinish();
      }
    });
  } catch (error) {
    Swal.fire({
      icon: 'error',
      title: 'Gagal',
      text: error.message || 'Terjadi kesalahan saat membuat laporan.',
    });
  }
};

export const handleAutomatchDatprAll = async (onFinish) => {
  const cabang = sessionStorage.getItem("cabang");
  MySwal.fire({
    title: 'Matching AT/PR All Toko',
    html: `
      <p id="progress-desc">Gathering Data...</p>
      <p id="progress-bar" style="margin-top: 1rem; background: #eee; width: 100%; height: 10px;">
        <span id="progress-fill" style="display:block; background:#4caf50; width:0%; height:100%"></span>
      </p>
    `,
    allowOutsideClick: false,
    allowEscapeKey: false,
    allowEnterKey: false,
    showConfirmButton: false,
    didOpen: () => Swal.showLoading(),
  });

  const results = [];

  try {
    const eventSource = createSSE(`/api/matching-atpr-all?cabang=${encodeURIComponent(cabang)}`);

    eventSource.onmessage = (event) => {
      const data = JSON.parse(event.data);
      const { progress, total, no_rab } = data;
      results.push(data);

      const desc = Swal.getHtmlContainer()?.querySelector('#progress-desc');
      if (desc) desc.innerHTML = `Rekonsiliasi AT/PR Number ${progress} of ${total}<br>No RAB : ${no_rab}`;

      const fill = Swal.getHtmlContainer()?.querySelector('#progress-fill');
      if (fill) fill.style.width = `${(progress / total) * 100}%`;
    };

    eventSource.addEventListener('done', () => {
      eventSource.close();

      Swal.fire({
        icon: 'success',
        title: 'Success',
        html: `<p>Berhasil Matching AT/PR All Toko!</p>`,
        confirmButtonText: 'OK',
        width: 600,
      }).then((result) => {
        // ✅ Jalankan fetchData hanya setelah alert sukses ditutup
        if (result.isConfirmed && typeof onFinish === 'function') {
          onFinish();
        }
      });
    });

    eventSource.onerror = (err) => {
      console.error('SSE error:', err);
      eventSource.close();
      Swal.fire({
        icon: 'error',
        title: 'Gagal',
        text: 'Gagal mendapatkan progress dari server.',
      });
    };
  } catch (err) {
    console.error(err);
    Swal.fire({
      icon: 'error',
      title: 'Gagal',
      text: err.message || 'Terjadi kesalahan saat membuat semua laporan.',
    });
  }
};

export const handleSyncPP = async (no_rab, onFinish) => {
  if (!no_rab) return;
  const cabang = sessionStorage.getItem("cabang");
  
  MySwal.fire({
    title: 'Synchronize PP/SP',
    text: 'Please Wait...',
    allowOutsideClick: false,
    allowEscapeKey: false,
    allowEnterKey: false,
    showConfirmButton: false,
    didOpen: () => {
      Swal.showLoading();
    },
  });

  try {
    const encodedNoRab = encodeURIComponent(no_rab);
    const encodedCabang = encodeURIComponent(cabang);
    const response = await fetch(`/api/sync_pp?no_rab=${encodedNoRab}&cabang=${encodedCabang}`);  
    if (!response.ok) {
      throw new Error('Gagal mengunduh file.');
    }

    Swal.fire({
      icon: 'success',
      title: 'Success',
      text: 'Sync PP Berhasil Dilakukan!',
      confirmButtonText: 'OK',
    }).then((result) => {
      if (result.isConfirmed && typeof onFinish === 'function') {
        onFinish();
      }
    });
  } catch (error) {
    Swal.fire({
      icon: 'error',
      title: 'Gagal',
      text: error.message || 'Terjadi kesalahan saat membuat laporan.',
    });
  }
};

export const handleSyncPPAll = async (onFinish) => {
  const cabang = sessionStorage.getItem("cabang");
  MySwal.fire({
    title: 'Synchronize PP/SP All Toko',
    html: `
      <p id="progress-desc">Gathering Data...</p>
      <p id="progress-bar" style="margin-top: 1rem; background: #eee; width: 100%; height: 10px;">
        <span id="progress-fill" style="display:block; background:#4caf50; width:0%; height:100%"></span>
      </p>
    `,
    allowOutsideClick: false,
    allowEscapeKey: false,
    allowEnterKey: false,
    showConfirmButton: false,
    didOpen: () => Swal.showLoading(),
  });

  const results = [];

  try {
    const cabang = sessionStorage.getItem("cabang");
    const eventSource = createSSE(`/api/sync-pp-all?cabang=${cabang}`);

    eventSource.onmessage = (event) => {
      const data = JSON.parse(event.data);
      const { progress, total, no_rab } = data;
      results.push(data);

      const desc = Swal.getHtmlContainer()?.querySelector('#progress-desc');
      if (desc) desc.innerHTML = `Sync PP/SP Number ${progress} of ${total}<br>No RAB : ${no_rab}`;

      const fill = Swal.getHtmlContainer()?.querySelector('#progress-fill');
      if (fill) fill.style.width = `${(progress / total) * 100}%`;
    };

    eventSource.addEventListener('done', () => {
      eventSource.close();

      Swal.fire({
        icon: 'success',
        title: 'Success',
        html: `<p>Berhasil Sync PP/SP All Toko!</p>`,
        confirmButtonText: 'OK',
        width: 600,
      }).then((result) => {
        if (result.isConfirmed && typeof onFinish === 'function') {
          onFinish();
        }
      });
    });

    eventSource.onerror = (err) => {
      console.error('SSE error:', err);
      eventSource.close();
      Swal.fire({
        icon: 'error',
        title: 'Gagal',
        text: 'Gagal mendapatkan progress dari server.',
      });
    };
  } catch (err) {
    console.error(err);
    Swal.fire({
      icon: 'error',
      title: 'Gagal',
      text: err.message || 'Terjadi kesalahan saat membuat semua laporan.',
    });
  }
};