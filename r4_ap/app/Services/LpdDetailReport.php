<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class LaporanLPDService
{
    public function generate($data = [])
    {
        $templatePath = 'C:/xampp/htdocs/AP_R4/File/draft/Form LPD.xlsx';
        $spreadsheet = IOFactory::load($templatePath);
        $sheet = $spreadsheet->getActiveSheet();

        // Masukkan data ke template
        $sheet->setCellValue('B2', $data['nama_toko'] ?? 'NAMA TOKO');
        $sheet->setCellValue('B3', 'No RAB: ' . ($data['no_rab'] ?? '123456'));
        $sheet->setCellValue('B4', now()->format('d-m-Y'));

        // Contoh baris dinamis jika ada data detail
        if (!empty($data['details'])) {
            $startRow = 6;
            foreach ($data['details'] as $i => $row) {
                $sheet->setCellValue("A" . ($startRow + $i), $row['keterangan']);
                $sheet->setCellValue("B" . ($startRow + $i), $row['estimasi']);
                $sheet->setCellValue("C" . ($startRow + $i), $row['realisasi']);
            }
        }

        // Simpan file sementara
        $fileName = 'Report_LPD.xlsx';
        $tempFile = tempnam(sys_get_temp_dir(), $fileName);
        $writer = new Xlsx($spreadsheet);
        $writer->save($tempFile);

        return $tempFile;
    }
}
