const express = require('express');
const { runBRI } = require('./bri-runner');

const app = express();
app.use(express.json());

app.post('/run', async (req, res) => {
  try {
    const { accounts, start_date, end_date, credential } = req.body;

    console.log('Request diterima:', req.body);

    await runBRI(accounts, start_date, end_date, credential);

    res.json({
      status: 'success',
      message: 'BRI automation selesai'
    });

  } catch (err) {
    console.error(err);
    res.status(500).json({
      status: 'error',
      message: err.message
    });
  }
});

app.listen(4567, () => {
  console.log('BRI worker running on http://localhost:4567');
});
