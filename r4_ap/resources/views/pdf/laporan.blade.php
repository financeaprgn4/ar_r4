<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Laporan Clearence Sheet</title>
    <style>
        body { font-family: sans-serif; font-size: 12px; }
        h1 { text-align: center; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid black; padding: 5px; }
    </style>
</head>
<body>
    <h1>Clearence Sheet</h1>
    <p>No RAB: {{ $data->no_rab }}</p>
    <p>Nama Toko: {{ $data->nama_toko }}</p>

    {{-- Tambahkan isi laporan lain di sini --}}
</body>
</html>
