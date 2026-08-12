<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

class Autobank extends Controller
{
    public function index(Request $request)
    {
        $cabang = $request->query('cabang');

        if (!$cabang) {
            return response()->json([
                'error' => 'Parameter cabang wajib diisi'
            ], 400);
        }

        $data = DB::table('auto_bank')
            ->where('cabang', $cabang)
            ->orderBy('bank')
            ->orderBy('no_rek')
            ->get();

        return response()->json($data);
    }

    public function tasks(Request $request)
    {
        try {

            // ================= VALIDASI
            $request->validate([
                'cabang' => 'required|string'
            ]);

            $cabang = $request->cabang;

            // ================= QUERY DATA
            $tasks = DB::table('job_queue')
                ->where('cabang', $cabang)
                ->orderByDesc('start_time')
                ->limit(100) // optional biar tidak berat
                ->get([
                    'id',
                    'job_name',
                    'parameters',
                    'status',
                    'start_time'
                ]);

            return response()->json([
                'data' => $tasks
            ]);

        } catch (\Throwable $e) {

            \Log::error('GET TASK ERROR: ' . $e->getMessage());

            return response()->json([
                'message' => 'Gagal mengambil data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function prosesMutasi(Request $request)
    {
        try {

            // ================= VALIDASI
            $request->validate([
                'cabang' => 'required|string',
                'accounts' => 'required|array|min:1',
                'start_date' => 'required|date',
                'end_date' => 'required|date'
            ]);

            $accounts = $request->accounts;
            $startDate = $request->start_date;
            $endDate = $request->end_date;
            $cabang = $request->cabang;
            $requester = $request->username;

            // ================= GROUP PER BANK
            $groupedAccounts = [];

            foreach ($accounts as $acc) {
                $bank = strtolower($acc['bank']);
                $groupedAccounts[$bank][] = $acc;
            }

            $results = [];

            // ================= LOOP PER BANK
            foreach ($groupedAccounts as $bank => $accList) {

                $jobName = 'Download Mutasi';
                $parameter = strtoupper($bank);

                // ================= CEK JOB ON PROCESS =================
                $existingJob = DB::table('job_queue')
                    ->where('cabang', $cabang)
                    ->where('job_name', $jobName)
                    ->where('parameters', $parameter)
                    ->where('status', 'On Process')
                    ->where('requester', $requester)
                    ->first();

                if ($existingJob) {
                    $results[$bank] = [
                        'status' => 'skipped',
                        'message' => "Job {$parameter} masih berjalan",
                        'job_id' => $existingJob->id
                    ];
                    continue;
                }

                // ================= AMBIL CREDENTIAL =================
                $credential = DB::table('user_bank')
                    ->where('cabang', $cabang)
                    ->where('bank', strtoupper($bank))
                    ->first();

                if (!$credential) {
                    $results[$bank] = [
                        'status' => 'error',
                        'message' => "Credential {$bank} tidak ditemukan"
                    ];
                    continue;
                }

                // ================= INSERT JOB =================
                $jobId = DB::table('job_queue')->insertGetId([
                    'cabang' => $cabang,
                    'job_name' => $jobName,
                    'parameters' => $parameter,
                    'status' => 'On Process',
                    'start_time' => now(),
                    'end_time' => null,
                    'requester' => $requester
                ]);

                // ================= PAYLOAD KE WORKER =================
                $payload = [
                    'type' => $bank,
                    'job_id' => $jobId,
                    'payload' => [
                        'accounts' => $accList,
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                        'credential' => [
                            'client_id' => $credential->corp_id,
                            'user_id' => $credential->user,
                            'password' => $credential->pass
                        ]
                    ]
                ];

                try {

                    $response = Http::timeout(120)
                        ->post('http://127.0.0.1:5000/run-job', $payload);

                    $data = $response->json();
                    \Log::info('RESPONSE WORKER:', $data);

                    if (isset($data['need_captcha']) && $data['need_captcha'] === true) {
                    
                        $results[$bank] = [
                            'status' => 'captcha_required',
                            'message' => 'Captcha diperlukan',
                            'job_id' => $jobId,
                            'captcha' => $data['captcha']
                        ];

                    } elseif ($response->successful()) {

                        $results[$bank] = [
                            'status' => 'success',
                            'message' => 'Job dikirim ke worker',
                            'job_id' => $jobId
                        ];

                    } else {

                        $results[$bank] = [
                            'status' => 'error',
                            'message' => $response->body()
                        ];
                    }

                    \Log::info('CAPTCHA LENGTH:', [
                        'length' => strlen($data['captcha'] ?? '')
                    ]);
                } catch (\Exception $e) {
                    $results[$bank] = [
                        'status' => 'error',
                        'message' => 'Worker tidak dapat diakses: ' . $e->getMessage()
                    ];
                }
            }

            \Log::info('RESULTS BACKEND:', $results);
            return response()->json([
                'message' => 'Permintaan automation diproses',
                'results' => $results
            ]);

        } catch (\Exception $e) {

            return response()->json([
                'message' => 'Terjadi kesalahan server',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function jobUpdate(Request $request)
    {
        $request->validate([
            'job_id' => 'required|integer',
            'status' => 'required|string',
            'message' => 'nullable|string'
        ]);

        DB::table('job_queue')
            ->where('id', $request->job_id)
            ->update([
                'status' => $request->status,
                'end_time' => now()
            ]);

        return response()->json([
            'success' => true
        ]);
    }

    public function jobStatus(Request $request)
    {
        try {
            // ================= VALIDASI INPUT
            $request->validate([
                'job_ids' => 'required|array|min:1'
            ]);

            $jobIds = $request->job_ids;

            // ================= AMBIL DATA JOB
            $jobs = DB::table('job_queue')
                ->whereIn('id', $jobIds)
                ->select('id', 'status', 'start_time', 'end_time')
                ->get();

            // ================= FORMAT RESPONSE
            $result = $jobs->map(function ($job) {
                return [
                    'id' => $job->id,
                    'status' => $job->status,
                    'start_time' => $job->start_time,
                    'end_time' => $job->end_time
                ];
            });

            return response()->json($result);

        } catch (\Throwable $e) {

            \Log::error('JOB STATUS ERROR: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil status job',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function submitCaptcha(Request $request)
    {
        try {

            // ================= VALIDASI =================
            $request->validate([
                'job_id' => 'required|integer',
                'captcha' => 'required|string'
            ]);

            $jobId = $request->job_id;
            $captcha = $request->captcha;

            // ================= KIRIM KE WORKER =================
            $response = Http::timeout(60)->post(
                'http://127.0.0.1:5000/submit-captcha',
                [
                    'job_id' => $jobId,
                    'captcha' => $captcha
                ]
            );

            $data = $response->json();

            // ================= HANDLE RESPONSE =================
            if ($response->successful()) {

                return response()->json([
                    'success' => true,
                    'message' => $data['message'] ?? 'Captcha berhasil dikirim ke worker'
                ]);

            } else {

                return response()->json([
                    'success' => false,
                    'message' => $data['message'] ?? 'Gagal memproses captcha'
                ], 500);
            }

        } catch (\Exception $e) {

            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }
}
