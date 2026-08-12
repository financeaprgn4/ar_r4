<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class RabService
{
    public function fetchRabDetail(string $kodeRab): array
    {
        $url = 'http://sd5web1.indomaret.lan/WS_RAB/WebServiceRABEstimasiNew.asmx/GetKdRAB';

        $response = Http::timeout(120)->asForm()->post($url, [
            'KodeRAB' => $kodeRab
        ]);

        if (!$response->successful()) {
            throw new \Exception('Request WebService gagal');
        }

        $xml = $response->body();

        // 📁 simpan XML asli
        $rawPath = storage_path('app/rab_debug.xml');
        file_put_contents($rawPath, $xml);
        // ==========================
        // \Log::info('XML disimpan', ['path' => $rawPath]);
        // ==========================
        
        // 🔥 bersihkan namespace
        $cleanXml = preg_replace('/xmlns(:\w+)?="[^"]+"/', '', $xml);

        // 🔥 ubah prefix node jadi normal (diffgr: → diffgr_)
        $cleanXml = preg_replace('/(<\/?)(\w+):([^>]+)/', '$1$2_$3', $cleanXml);

        // 📁 simpan XML bersih
        $cleanPath = storage_path('app/rab_clean.xml');
        file_put_contents($cleanPath, $cleanXml);
        // ==========================
        // \Log::info('XML cleaned', ['path' => $cleanPath]);
        // ==========================

        return $this->parseXmlFromFile($cleanPath);
    }

    private function parseXmlFromFile(string $filePath)
    {
        libxml_use_internal_errors(true);

        $sxml = simplexml_load_file($filePath);

        if (!$sxml) {
            throw new \Exception('XML clean tidak bisa dibaca');
        }

        // 🔎 cari node diffgram secara dinamis
        if (!isset($sxml->diffgr_diffgram)) {
            throw new \Exception('Node diffgram tidak ditemukan');
        }

        $document = $sxml->diffgr_diffgram->DocumentElement;

        if (!$document) {
            throw new \Exception('DocumentElement tidak ditemukan');
        }

        $rows = [];

        foreach ($document->RABEstimasi as $item) {
            $rows[] = [
                'no_rab'          => (string) $item->no_rab,
                'tgl_rab'         => (string) $item->tgl_rab,
                'group_code'      => (string) $item->group_code,
                'rab_detail_code' => (string) $item->rab_detail_code,
                'description'     => trim((string) $item->description),
                'qty'             => (float) $item->qty,
                'nilai_hpp'       => (float) $item->nilai_hpp,
                'nilai_ppn'       => (float) $item->nilai_ppn,
                'nilai_estimasi'  => (float) $item->nilai_estimasi,
                'flag_code'       => (string) $item->flag_code,
            ];
        }

        \Log::info('RAB ROW COUNT', ['count' => count($rows)]);

        return $rows;
    }

}