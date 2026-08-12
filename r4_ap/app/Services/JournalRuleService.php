<?php

namespace App\Services;

use Carbon\Carbon;

    class JournalRuleService
{
    public static function generate(object $row): ?array
    {
        $keterangan = trim(
            ($row->remark ?? '') .
            ' ' .
            ($row->remark1 ?? '')
        );

        return match ($row->jns_bank) {
            'BSI' => self::ruleBSI($row, $keterangan),
            'BNI' => self::ruleBNI($row, $keterangan),
            'INA PERDANA' => self::ruleINA($row, $keterangan),
            'CIMB NIAGA' => self::ruleCIMB($row, $keterangan),
            'BRI REG' => self::ruleBRIReg($row, $keterangan),
            'BCA OPERASIONAL' => self::ruleBCAOperasional($row, $keterangan),
            'BCA DEP MEDAN' => self::ruleBCADepMedan($row, $keterangan),
            'BCA Syariah' => self::ruleBCASyariah($row, $keterangan),
            'BCA DEP ACEH' => self::ruleBCADepAceh($row, $keterangan),
            'BCA FLAZZ REG' => self::ruleBCAFlazzReg($row, $keterangan),
            'MANDIRI OPERASIONAL' => self::ruleMandiriOperasional($row, $keterangan),
            'MANDIRI TBM FRC' => self::ruleMandiriTBMFRC($row, $keterangan),
            'MANDIRI TBM REG' => self::ruleMandiriTBMREG($row, $keterangan),
            'MANDIRI DEPOSITORY' => self::ruleMandiriDepository($row, $keterangan),
            'BSI Frc' => self::ruleBSIFRC($row, $keterangan),
            'BCA Frc' => self::ruleBCAFRC($row, $keterangan),
            default => null,
        };
    }

    private static function ruleBSI($mutasi, $keterangan)
    {
        $tgl = date('d/m/y', strtotime($mutasi->tgl));
        $tglInv = date('dmy', strtotime($mutasi->tgl));

        if (str_contains($keterangan, 'CPCASH')) {

            return [
                'desc_header' => "TARIK DANA HO BANK BSI {$tgl}",
                'account'     => '910000',
                'cost_center' => 'G009',
                'inv_num'     => "HOBSI-{$tglInv}",
            ];
        }

        if (
            str_contains($keterangan, 'Admin') ||
            str_contains($keterangan, 'By. Adm')
        ) {

            return [
                'desc_header' => "ADM BANK BSI {$tgl}",
                'account'     => '510077',
                'cost_center' => 'KTR',
                'inv_num'     => "ADMBSI-{$tglInv}",
            ];
        }

        if (
            str_contains($keterangan, 'KOREKSI') ||
            str_contains($keterangan, 'koreksi')
        ) {

            return [
                'desc_header' => $keterangan,
                'account'     => '218006',
                'cost_center' => 'KTR',
                'inv_num'     => "DBBSI-{$tglInv}",
            ];
        }

        if (str_contains($keterangan, 'PCP')) {

            return [
                'desc_header' => "TARIK DANA HO BANK BSI {$tgl}",
                'account'     => '910000',
                'cost_center' => 'G009',
                'inv_num'     => "HOBSI-{$tglInv}",
            ];
        }

        return null;
    }

    private static function ruleBNI($mutasi, $keterangan)
    {
        $tgl = date('d/m/y', strtotime($mutasi->tgl));
        $tglInv = date('dmy', strtotime($mutasi->tgl));

        if (str_contains($keterangan, 'Cash Pool')) {

            return [
                'desc_header' => "TARIK DANA HO BANK BNI {$tgl}",
                'account'     => '910000',
                'cost_center' => 'G009',
                'inv_num'     => "HOBNI-{$tglInv}",
            ];

        } elseif (str_contains($keterangan, 'PPH')) {

            return [
                'desc_header' => "PAJAK BUNGA BANK BNI {$tgl}",
                'account'     => '510103',
                'cost_center' => 'KTR',
                'inv_num'     => "PJKBNI-{$tglInv}",
            ];

        } elseif (
            str_contains($keterangan, 'BIAYA ADM') ||
            str_contains($keterangan, 'Biaya Adm')
        ) {

            return [
                'desc_header' => "ADM BANK BNI {$tgl}",
                'account'     => '510077',
                'cost_center' => 'KTR',
                'inv_num'     => "ADMBNI-{$tglInv}",
            ];
        }

        return null;
    }

    private static function ruleINA($mutasi, $keterangan)
    {
        $tgl = date('d/m/y', strtotime($mutasi->tgl));
        $tglInv = date('dmy', strtotime($mutasi->tgl));

        if (str_contains($keterangan, 'Biaya Admin')) {

            return [
                'desc_header' => "ADM BANK INA PERDANA {$tgl}",
                'account'     => '510077',
                'cost_center' => 'KTR',
                'inv_num'     => "ADMINA-{$tglInv}",
            ];

        } elseif (str_contains($keterangan, 'Pajak Bunga')) {

            return [
                'desc_header' => "PAJAK BUNGA BANK INA PERDANA {$tgl}",
                'account'     => '510103',
                'cost_center' => 'KTR',
                'inv_num'     => "PJKINA-{$tglInv}",
            ];

        } elseif (
            str_contains($keterangan, 'SEL KRG') ||
            str_contains($keterangan, 'SEL  KRG')
        ) {

            return [
                'desc_header' => $keterangan,
                'account'     => '111802',
                'cost_center' => '',
                'inv_num'     => "DBINA-{$tglInv}",
            ];

        } elseif (str_contains($keterangan, 'PINBUK')) {

            return [
                'desc_header' => "TARIK DANA HO BANK INA PERDANA {$tgl}",
                'account'     => '910000',
                'cost_center' => 'G009',
                'inv_num'     => "HOINA-{$tglInv}",
            ];
        }

        return null;
    }

    private static function ruleCIMB($mutasi, $keterangan)
    {
        $tgl = date('d/m/y', strtotime($mutasi->tgl));
        $tglInv = date('dmy', strtotime($mutasi->tgl));

        if (
            str_contains($keterangan, 'Biaya Statement') ||
            str_contains($keterangan, 'MONTHLY ADMIN FEE')
        ) {

            return [
                'desc_header' => "ADM BANK CIMB NIAGA {$tgl}",
                'account'     => '510077',
                'cost_center' => 'KTR',
                'inv_num'     => "ADMCIMB-{$tglInv}",
            ];
        }

        return null;
    }

    private static function ruleBRIReg($mutasi, $keterangan)
    {
        $tgl = date('d/m/y', strtotime($mutasi->tgl));
        $tglInv = date('dmy', strtotime($mutasi->tgl));

        if (str_contains($keterangan, 'Fee Pooling')) {

            return [
                'desc_header' => "ADM BANK BRI REG TRANSFER DANA {$tgl}",
                'account'     => '510077',
                'cost_center' => 'KTR',
                'inv_num'     => "ADMBRIREG-{$tglInv}",
            ];

        } elseif (str_contains($keterangan, 'Tax')) {

            return [
                'desc_header' => "PAJAK BUNGA BANK BRI REG {$tgl}",
                'account'     => '510103',
                'cost_center' => 'KTR',
                'inv_num'     => "PJKBRIREG-{$tglInv}",
            ];
        }

        return null;
    }

    private static function ruleBCAOperasional($mutasi, $keterangan)
    {
        $tgl = date('d/m/y', strtotime($mutasi->tgl));
        $tglInv = date('dmy', strtotime($mutasi->tgl));

        if (str_contains($keterangan, 'BA JASA E-BANKING')) {

            return [
                'desc_header' => "ADM BANK BCA OPR TRANSFER DANA {$tgl}",
                'account'     => '510077',
                'cost_center' => 'KTR',
                'inv_num'     => "ADMBCAOPR-{$tglInv}",
            ];

        } elseif (str_contains($keterangan, 'PAJAK GIRO')) {

            return [
                'desc_header' => $keterangan,
                'account'     => '510103',
                'cost_center' => 'KTR',
                'inv_num'     => "PJKGIROBCAOPR-{$tglInv}",
            ];

        } elseif (
            str_contains($keterangan, 'LAINNYA KOR CPU') ||
            str_contains($keterangan, 'ND - LAINNYA KOR DBL')
        ) {

            return [
                'desc_header' => $keterangan,
                'account'     => '111802',
                'cost_center' => 'FIN',
                'inv_num'     => "DBBCAOPR-{$tglInv}",
            ];

        } elseif (
            str_contains($keterangan, 'BY PICKUP') ||
            str_contains($keterangan, 'ND- BUKU CEK')
        ) {

            return [
                'desc_header' => $keterangan,
                'account'     => '510077',
                'cost_center' => 'KTR',
                'inv_num'     => "DBBCAOPR-{$tglInv}",
            ];

        } elseif (str_contains($keterangan, 'BIAYA ADMIN BDC')) {

            return [
                'desc_header' => $keterangan,
                'account'     => '510077',
                'cost_center' => 'KTR',
                'inv_num'     => "ADMBCAOPR-{$tglInv}",
            ];

        } elseif (str_contains($keterangan, 'BIAYA ADM')) {

            return [
                'desc_header' => "ADM BANK BCA OPR {$tgl}",
                'account'     => '510077',
                'cost_center' => 'KTR',
                'inv_num'     => "ADMBCAOPR-{$tglInv}",
            ];
        }

        return null;
    }

    private static function ruleBCADepMedan($mutasi, $keterangan)
    {
        $tgl = date('d/m/y', strtotime($mutasi->tgl));
        $tglInv = date('dmy', strtotime($mutasi->tgl));

        if (
            str_contains($keterangan, 'BIAYA KARTU BDC') ||
            str_contains($keterangan, 'BIAYA ADM')
        ) {

            return [
                'desc_header' => "ADM BANK BCA DEP MEDAN {$tgl}",
                'account'     => '510077',
                'cost_center' => 'KTR',
                'inv_num'     => "ADMBCADEPMDN-{$tglInv}",
            ];

        } elseif (str_contains($keterangan, 'PAJAK GIRO')) {

            return [
                'desc_header' => $keterangan,
                'account'     => '510103',
                'cost_center' => 'KTR',
                'inv_num'     => "PJKGIROBCADEPMDN-{$tglInv}",
            ];

        } elseif (str_contains($keterangan, 'PAJAK')) {

            return [
                'desc_header' => "PAJAK BUNGA BANK BCA DEP MEDAN {$tgl}",
                'account'     => '510103',
                'cost_center' => 'KTR',
                'inv_num'     => "PJKBCADEPMDN-{$tglInv}",
            ];
        }

        return null;
    }

    private static function ruleBCASyariah($mutasi, $keterangan)
    {
        $tgl = date('d/m/y', strtotime($mutasi->tgl));
        $tglInv = date('dmy', strtotime($mutasi->tgl));

        if (str_contains($keterangan, 'ADM')) {

            return [
                'desc_header' => "ADM BANK BCA SYARIAH {$tgl}",
                'account'     => '510077',
                'cost_center' => 'KTR',
                'inv_num'     => "ADMBCASYARIAH-{$tglInv}",
            ];
        }

        return null;
    }

    private static function ruleBCADepAceh($mutasi, $keterangan)
    {
        $tgl = date('d/m/y', strtotime($mutasi->tgl));
        $tglInv = date('dmy', strtotime($mutasi->tgl));

        if (
            str_contains($keterangan, 'BIAYA KARTU BDC') ||
            str_contains($keterangan, 'BIAYA ADM')
        ) {

            return [
                'desc_header' => "ADM BANK BCA DEP ACEH {$tgl}",
                'account'     => '510077',
                'cost_center' => 'KTR',
                'inv_num'     => "ADMBCADEPACEH-{$tglInv}",
            ];

        } elseif (str_contains($keterangan, 'PAJAK GIRO')) {

            return [
                'desc_header' => $keterangan,
                'account'     => '510103',
                'cost_center' => 'KTR',
                'inv_num'     => "PJKGIROBCADEPACEH-{$tglInv}",
            ];

        } elseif (str_contains($keterangan, 'PAJAK')) {

            return [
                'desc_header' => "PAJAK BUNGA BANK BCA DEP ACEH {$tgl}",
                'account'     => '510103',
                'cost_center' => 'KTR',
                'inv_num'     => "PJKBCADEPACEH-{$tglInv}",
            ];
        }

        return null;
    }

    private static function ruleBCAFlazzReg($mutasi, $keterangan)
    {
        $tgl = date('d/m/y', strtotime($mutasi->tgl));
        $tglInv = date('dmy', strtotime($mutasi->tgl));

        if (str_contains($keterangan, 'BIAYA ADM')) {

            return [
                'desc_header' => "ADM BANK BCA FLAZZ REG {$tgl}",
                'account'     => '510077',
                'cost_center' => 'KTR',
                'inv_num'     => "ADMBCAFLAZZREG-{$tglInv}",
            ];

        } elseif (str_contains($keterangan, 'BIAYA KARTU BDC')) {

            return [
                'desc_header' => "ADM BANK BCA FLAZZ REG {$tgl}",
                'account'     => '510077',
                'cost_center' => 'KTR',
                'inv_num'     => "DBBCAFLAZZREG-{$tglInv}",
            ];

        } elseif (str_contains($keterangan, 'BUNGA')) {

            return [
                'desc_header' => "KOREKSI PAJAK BUNGA BANK BCA FLAZZ REG {$tgl}",
                'account'     => '620000',
                'cost_center' => 'KTR',
                'inv_num'     => "KORINTBCAFLAZZREG-{$tglInv}",
            ];
        }

        return null;
    }

    private static function ruleMandiriOperasional($mutasi, $keterangan)
    {
        $tgl = date('d/m/y', strtotime($mutasi->tgl));
        $tglInv = date('dmy', strtotime($mutasi->tgl));

        if (str_contains($keterangan, 'Clearing Fee')) {

            return [
                'desc_header' => "CLEARING FEE BANK MANDIRI OPR {$tgl}",
                'account'     => '510077',
                'cost_center' => 'KTR',
                'inv_num'     => "CLEARINGFEEMDROPR-{$tglInv}",
            ];

        } elseif (str_contains($keterangan, 'Biaya Adm')) {

            return [
                'desc_header' => "ADM BANK MANDIRI OPR {$tgl}",
                'account'     => '510077',
                'cost_center' => 'KTR',
                'inv_num'     => "ADMMDROPR-{$tglInv}",
            ];
        
        } elseif (str_contains($keterangan, 'Transfer Fee')) {

            return [
                'desc_header' => "ADM BANK MANDIRI OPR TRF DANA {$tgl}",
                'account'     => '510077',
                'cost_center' => 'KTR',
                'inv_num'     => "ADMMDROPR-{$tglInv}",
            ];

        } elseif (str_contains($keterangan, 'Pajak')) {

            return [
                'desc_header' => "PAJAK BUNGA BANK MANDIRI OPR {$tgl}",
                'account'     => '510103',
                'cost_center' => 'KTR',
                'inv_num'     => "PJKMDROPR-{$tglInv}",
            ];

        } elseif (str_contains($keterangan, 'RTGS Fee')) {

            return [
                'desc_header' => "RTGS FEE BANK MANDIRI OPR {$tgl}",
                'account'     => '510077',
                'cost_center' => 'KTR',
                'inv_num'     => "RTGSFEEMDROPR-{$tglInv}",
            ];
        }

        return null;
    }

    private static function ruleMandiriDepository($mutasi, $keterangan)
    {
        $tgl = date('d/m/y', strtotime($mutasi->tgl));
        $tglInv = date('dmy', strtotime($mutasi->tgl));

        if (str_contains($keterangan, 'Biaya Adm')) {

            return [
                'desc_header' => "ADM BANK MANDIRI DEP {$tgl}",
                'account'     => '510077',
                'cost_center' => 'KTR',
                'inv_num'     => "ADMMDRDEP-{$tglInv}",
            ];

        } elseif (str_contains($keterangan, 'Pajak')) {

            return [
                'desc_header' => "PAJAK BUNGA BANK MANDIRI DEP {$tgl}",
                'account'     => '510103',
                'cost_center' => 'KTR',
                'inv_num'     => "PJKMDRDEP-{$tglInv}",
            ];
        }

        return null;
    }

    private static function ruleBSIFRC($mutasi, $keterangan)
    {
        $tgl = date('d/m/y', strtotime($mutasi->tgl));
        $tglInv = date('dmy', strtotime($mutasi->tgl));

        if (
            str_contains($keterangan, 'PCP2') ||
            str_contains($keterangan, 'CPCASH POOLING')
        ) {

            return [
                'desc_header' => "ATS BSI FRC KE 7169677268 {$tgl}",
                'account'     => '000010',
                'cost_center' => $mutasi->site,
                'inv_num'     => "ATS-BSIFRC-{$tglInv}-{$mutasi->site}",
            ];

        } elseif (str_contains($keterangan, 'Administrasi')) {

            return [
                'desc_header' => "ADM BANK BSI FRC {$mutasi->site} {$tgl}",
                'account'     => '510077',
                'cost_center' => $mutasi->site,
                'inv_num'     => "ADMBSIFRC-{$tglInv}-{$mutasi->site}",
            ];

        } elseif (
            str_contains($keterangan, 'KOREKSI') ||
            str_contains($keterangan, 'koreksi')
        ) {

            return [
                'desc_header' => $keterangan,
                'account'     => '218006',
                'cost_center' => $mutasi->site,
                'inv_num'     => "DB-BSIFRC-{$tglInv}-{$mutasi->site}",
            ];
        }

        return null;
    }

    private static function ruleBCAFRC($mutasi, $keterangan)
    {
        $tgl = date('d/m/y', strtotime($mutasi->tgl));
        $tglInv = date('dmy', strtotime($mutasi->tgl));

        if (str_contains($keterangan, 'BIAYA KARTU BDC')) {

            return [
                'desc_header' => $keterangan,
                'account'     => '510077',
                'cost_center' => $mutasi->site,
                'inv_num'     => "DB-BCAFRC-{$tglInv}-{$mutasi->site}",
            ];
        }

        return null;
    }
}