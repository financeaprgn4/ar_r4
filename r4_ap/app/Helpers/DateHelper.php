<?php

namespace App\Helpers;

use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class DateHelper
{
    public static function formatTanggalExcelLabel($label, $tanggal)
    {
        $tgl = self::safeFormat($tanggal);
        return "{$label} : {$tgl}";
    }

    public static function formatTanggalExcel($tanggal)
    {
        return self::safeFormat($tanggal);
    }

    public static function formatRupiah($angka)
    {
        try {
            return number_format($angka, 0, ',', ',');
        } catch (\Exception $e) {
            return '-';
        }
    }

    public static function excelDateToDatabase($value)
    {
        if (empty($value)) {
            return null;
        }

        if (is_numeric($value)) {
            return Carbon::instance(
                ExcelDate::excelToDateTimeObject($value)
            )->format('Y-m-d');
        }

        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }

    private static function safeFormat($tanggal)
    {
        if (
            empty($tanggal) ||
            $tanggal === '0000-00-00' ||
            $tanggal === '0000-00-00 00:00:00' ||
            $tanggal === null
        ) {
            return '-';
        }

        try {
            return Carbon::parse($tanggal)->format('d-M-y');
        } catch (\Exception $e) {
            return '-';
        }
    }

    public static function excelDateToDate($value)
    {
        if (empty($value)) {
            return null;
        }

        // Jika format numeric (Excel serial date)
        if (is_numeric($value)) {
            return Carbon::instance(
                ExcelDate::excelToDateTimeObject($value)
            )->format('Y-m-d');
        }

        // Jika sudah string tanggal
        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }

}
