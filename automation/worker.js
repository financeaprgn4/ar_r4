const express = require('express');
const axios = require('axios');

const { runSaranaAutomation } = require('./sarana');
const { runBRI }   = require('./bri-runner');
const { runBNI }   = require('./bni-runner');
const { runBSI, continueBSIProcess } = require('./bsi-runner');
const { runNIAGA } = require('./niaga-runner');

const app = express();
app.use(express.json());

const BACKEND_URL = 'http://127.0.0.1:8000/api/job-update';

// ================= SESSION STORE (UNTUK CAPTCHA BSI) =================
const sessionStore = {};


// ================= UPDATE JOB KE BACKEND =================
async function updateJob(jobId, status, message = null) {
  if (!jobId) return;

  try {
    await axios.post(BACKEND_URL, {
      job_id: jobId,
      status: status,
      message: message
    });

    console.log(`📝 Job ${jobId} updated → ${status}`);

  } catch (err) {
    console.error('❌ Gagal update job ke backend:', err.message);
  }
}


// ================= MAIN ROUTE =================
app.post('/run-job', async (req, res) => {
  console.log('📩 REQUEST MASUK:', req.body.type);

  const { type, payload, job_id } = req.body;

  if (!type) {
    return res.status(400).json({
      success: false,
      message: 'Job type tidak ada'
    });
  }

  console.log(`🚀 Job diterima: ${type} | Job ID: ${job_id}`);

  try {

    switch (type) {

      // ================= SARANA =================
      case 'sarana': {
        if (!payload || !payload.listRAB || payload.listRAB.length === 0) {
          throw new Error('List RAB kosong');
        }

        await runSaranaAutomation(payload.listRAB);
        break;
      }

      // ================= BRI =================
      case 'bri': {
        const { accounts, start_date, end_date, credential } = payload || {};

        if (!accounts || accounts.length === 0) {
          throw new Error('Accounts kosong');
        }

        runBRI(accounts, start_date, end_date, credential)
          .then(async () => {
            console.log(`BNI >> ✅ selesai job ${job_id}`);
            await updateJob(job_id, 'Success');
          })
          .catch(async (err) => {
            console.error(`BNI >> ❌ error job ${job_id}:`, err.message);
            await updateJob(job_id, 'Failed', err.message);
          });

        return res.json({
          success: true,
          message: 'BRI job accepted',
          job_id
        });
      }

      // ================= BNI =================
      case 'bni': {
        const { accounts, start_date, end_date, credential } = payload || {};

        if (!accounts || accounts.length === 0) {
          throw new Error('Accounts kosong');
        }

        runBNI(accounts, start_date, end_date, credential)
          .then(async () => {
            console.log(`✅ BNI selesai job ${job_id}`);
            await updateJob(job_id, 'Success');
          })
          .catch(async (err) => {
            console.error(`❌ BNI error job ${job_id}:`, err.message);
            await updateJob(job_id, 'Failed', err.message);
          });

        return res.json({
          success: true,
          message: 'BNI job accepted',
          job_id
        });
      }

      // ================= CIMB NIAGA =================
      case 'cimb niaga': {
        const { accounts, start_date, end_date, credential } = payload || {};

        if (!accounts || accounts.length === 0) {
          throw new Error('Accounts kosong');
        }

        runNIAGA(accounts, start_date, end_date, credential)
          .then(async () => {
            console.log(`NIAGA >> ✅ selesai job ${job_id}`);
            await updateJob(job_id, 'Success');
          })
          .catch(async (err) => {
            console.error(`NIAGA >> ❌ error job ${job_id}:`, err.message);
            await updateJob(job_id, 'Failed', err.message);
          });

        return res.json({
          success: true,
          message: 'CIMB NIAGA job accepted',
          job_id
        });
      }

      // ================= BSI (CAPTCHA FLOW) =================
      case 'bsi': {
        const { accounts, start_date, end_date, credential } = payload || {};

        if (!accounts || accounts.length === 0) {
          return res.status(400).json({
            success: false,
            message: 'Accounts kosong'
          });
        }

        // 🔥 STEP 1: ambil captcha + session
        const result = await runBSI(accounts, start_date, end_date, credential);

        // simpan session di memory
        sessionStore[job_id] = result.session;

        console.log(`BSI >> 🧠 Session disimpan untuk job ${job_id}`);
        // console.log("KIRIM CAPTCHA KE FRONTEND:", result.captcha?.slice(0, 50));
        // kirim captcha ke frontend
        return res.json({
          success: true,
          need_captcha: true,
          captcha: result.captcha,
          job_id
        });
      }

      default:
        throw new Error('Job type tidak dikenali');
    }

    // ================= UPDATE SUCCESS =================
    await updateJob(job_id, 'Success');

    return res.json({
      success: true,
      message: `${type} automation selesai`
    });

  } catch (err) {

    console.error('❌ WORKER ERROR:', err.message);

    await updateJob(job_id, 'Failed', err.message);

    return res.status(500).json({
      success: false,
      message: err.message
    });
  }
});


// ================= SUBMIT CAPTCHA =================
app.post('/submit-captcha', async (req, res) => {
  const { job_id, captcha } = req.body;

  console.log(`BSI >> 🔐 Submit captcha untuk job ${job_id}`);

  const session = sessionStore[job_id];

  if (!session) {
    return res.status(404).json({
      success: false,
      message: 'Session tidak ditemukan / expired'
    });
  }

  try {
    // 🔥 STEP 2: lanjutkan proses setelah captcha
    // 🔥 JALANKAN DI BACKGROUND
    continueBSIProcess(session, captcha)
      .then(async () => {
        console.log(`BSI >> ✅ BSI selesai job ${job_id}`);
        delete sessionStore[job_id];
        await updateJob(job_id, 'Success');
      })
      .catch(async (err) => {
        console.error(`❌ BSI error job ${job_id}:`, err.message);
        delete sessionStore[job_id];
        await updateJob(job_id, 'Failed', err.message);
      });

    // 🔥 LANGSUNG RESPONSE (INI PENTING)
    return res.json({
      success: true,
      message: 'Captcha diterima, proses dilanjutkan'
    });

  } catch (err) {

    console.error('❌ CAPTCHA ERROR:', err.message);

    delete sessionStore[job_id];

    await updateJob(job_id, 'Failed', err.message);

    return res.status(500).json({
      success: false,
      message: err.message
    });
  }
});


// ================= START SERVER =================
app.listen(5000, () => {
  console.log('🚀 Unified worker running on http://localhost:5000');
});