<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\LpdController;
use App\Http\Controllers\Autobank;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LpdDetailController;
use App\Http\Controllers\LpdModalAddController;
use App\Http\Controllers\LpdUpdate;
use App\Http\Controllers\LpdUpdateId;
use App\Http\Controllers\LpdDeleteId;
use App\Http\Controllers\MailController;
use App\Http\Controllers\PeriodeController;
use App\Http\Controllers\UsersController;
use App\Http\Controllers\StatementController;
use App\Http\Controllers\SaranaTokoController;
use App\Http\Controllers\DatPRController;
use App\Http\Controllers\LaporanLpdController;
use App\Http\Controllers\LegacyFileController;
use App\Http\Controllers\PDFMergeController;
use App\Http\Controllers\ReconciliationController;
use App\Http\Controllers\FTPController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Http\Controllers\GameController;

Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout']);
Route::get('/cabang', [AuthController::class, 'cabang']);
Route::get('/lpd-summary', [DashboardController::class, 'summary']);
Route::get('/lpd', [LpdController::class, 'lpdAll']);
Route::get('/lpd-modal', [LpdController::class, 'lpdModal']);
Route::get('/lpd-rab', [LpdController::class, 'lpdRab']);
Route::post('/lpd-modal-sync', [LpdController::class, 'lpdModalSync']);
Route::post('/lpd-estimasi', [LpdController::class, 'lpdEstSync']);
Route::get('/lpd-outs', [LpdController::class, 'getOuts']);
Route::get('/lpd-cs', [LpdController::class, 'getCS']);
Route::get('/lpd-final', [LpdController::class, 'getFinal']);
Route::put('/update-lpd', [LpdUpdate::class, 'update']);
Route::get('/estimasi/{no_rab}', [LpdController::class, 'show']);
Route::post('/lpd-finalisasi', [LpdController::class, 'finalisasi']);
Route::post('/unfinalisasi', [LpdController::class, 'unfinalisasi']);
Route::post('/discard-cs', [LpdController::class, 'discardCS']);
Route::put('/update-lpd-site', [LpdController::class, 'updateSite']);
Route::post('/update-keterangan', [LpdController::class, 'updateKeterangan']);
Route::post('/update-keterangan-rab', [LpdController::class, 'updateKeteranganRab']);
Route::post('/mail-add', [MailController::class, 'mailAdd']);
Route::get('/Mail', [MailController::class, 'mailList']);
Route::delete('/Mail/{id}', [MailController::class, 'mailDelete']);

Route::get('/periodelist', [PeriodeController::class, 'periodeList']);
Route::post('/periode', [PeriodeController::class, 'store']);
Route::put('/periode/{id}', [PeriodeController::class, 'update']);

Route::get('/Users', [UsersController::class, 'usersList']);
Route::get('/periode', [StatementController::class, 'getPeriode']);
Route::get('/Statement', [StatementController::class, 'StatementList']);
Route::get('/mutasi', [StatementController::class, 'getMutasi']);
Route::delete('mutasi/{file}/{cabang}', [StatementController::class, 'deleteMutasi']);
Route::post('/import-mutasi', [StatementController::class, 'importMutasi']);
Route::post('/import-mutasi/upload', [StatementController::class, 'upload']);
Route::get('/import-mutasi/stream', [StatementController::class, 'stream']);
Route::get('/bank-list', [StatementController::class, 'getBankList']);
Route::get('/mutasi-search', [StatementController::class, 'search']);
Route::post('/mutasi/reconcile', [StatementController::class, 'reconcileSelected']);
Route::post('/mutasi/unreconcile', [StatementController::class, 'unreconcileSelected']);
Route::post('/mutasi/journal', [StatementController::class, 'journalSelected']);
Route::post('/import-receipt', [StatementController::class, 'importReceipt']);
Route::post('/upload-rk', [StatementController::class, 'uploadRK']);
Route::delete('/bank-statement/{id}', [StatementController::class, 'deleteRK']);
Route::post('/Users-add', [UsersController::class, 'usersAdd']);
Route::get('/lpd-detail', [LpdDetailController::class, 'detail']);
Route::post('/update-lpd-trx', [LpdUpdateId::class, 'update_id']);
Route::post('/add-lpd-trx', [LpdUpdateId::class, 'lpdtrxAdd']);
Route::delete('/lpd-detail/delete', [LpdDeleteId::class, 'destroy']);
Route::post('/lpd-modal-add', [LpdModalAddController::class, 'modalAdd']);
Route::post('/lpd-modal-edit', [LpdModalAddController::class, 'modalEdit']);
Route::post('/delete-bbt', [LpdModalAddController::class, 'deleteBBT']);
Route::get('/file-lpd/{filename}', [LegacyFileController::class, 'showPdf']);
Route::post('/lpd-berkas', [LpdController::class, 'uploadFile']);
Route::post('/lpd-cs-final', [LpdController::class, 'uploadCSFinal']);
Route::post('/upload-pdf', [LpdController::class, 'upload']);
Route::post('/lpd-berkas-delete', [LpdController::class, 'deleteFile']);
Route::post('/import-lpd-detail', [LpdController::class, 'importDetail']);
Route::post('/store-lpd', [LpdController::class, 'storeAdd']);
Route::post('/generate-clearencesheet', [LpdController::class, 'GenerateClearenceSheet']);
Route::post('/send-lpd', [LpdController::class, 'sendLPD']);
Route::post('/upload-lpdprj', [LpdController::class, 'uploadLPDPrj']);
Route::post('/send-lpd-fad', [LpdController::class, 'sendLPDFAD']);
Route::post('/conf-Renov', [LpdController::class, 'confRenov']);
Route::get('/lpd-datpr-unmatch', [DatPRController::class, 'datprunmatch']);
Route::get('/setup-lpd/match-at-by-desc', [DatPRController::class, 'getMatchSetting']);
Route::post('/setup-lpd/update-match-at-by-desc', [DatPRController::class, 'updateMatchSetting']);
Route::get('/lpd-inv-unmatch', [LpdController::class, 'invunmatch']);
Route::get('/inv-unmatch-sarana', [LpdController::class, 'invunmatchsarana']);
Route::post('/update-flag-real', [LpdController::class, 'updateFlagReal']);
Route::post('/delete-clearancesheet', [LpdController::class, 'deleteClearanceSheet']);
Route::get('/sarana_toko', [SaranaTokoController::class, 'index']);
Route::post('/update-surkas', [SaranaTokoController::class, 'updateSurkas']);
Route::post('/upload-ba', [SaranaTokoController::class, 'uploadBA']);
Route::post('/merge-pdf', [PDFMergeController::class, 'merge']);
Route::post('/import-sarana', [SaranaTokoController::class, 'import']);
Route::post('/sarana-from-ws', [SaranaTokoController::class, 'importSarana']);
Route::post('/import-pp', [SaranaTokoController::class, 'importpp']);
Route::post('/sarana-match', [SaranaTokoController::class, 'match']);
Route::post('/sarana-match-plu', [SaranaTokoController::class, 'matchplu']);
Route::get('/master-sarana', [SaranaTokoController::class, 'masterSarana']);
Route::get('/rab-detail', [SaranaTokoController::class, 'getRabDetail']);
Route::delete('/realisasi', [SaranaTokoController::class, 'destroy']);
Route::post('/fsee-process', [SaranaTokoController::class, 'Fseeprocess']);
Route::post('/surkas-process', [SaranaTokoController::class, 'Surkasprocess']);
Route::post('/nonpp-process', [SaranaTokoController::class, 'Nonppprocess']);
Route::get('/dat_pr', [DatPRController::class, 'index']);
Route::delete('/del_at_pr', [DatPRController::class, 'destroy']);
Route::post('/atpr-match', [DatPRController::class, 'match']);
Route::post('/import-datpr', [DatPRController::class, 'import']);
Route::post('/import-datpr', [DatPRController::class, 'import']);
Route::post('/datpr-match-inv', [DatPRController::class, 'matchInv']);
Route::post('/import-atpr', [DatPRController::class, 'importATPR']);
Route::post('/atpr-action', [DatPRController::class, 'atprAction']);
Route::get('/dat-pr-toko', [DatPRController::class, 'atprToko']);
Route::post('/import-at-pr', [DatPRController::class, 'atprImport']);
Route::get('/export-lpd', [LaporanLpdController::class, 'export']);
Route::get('/export-lpd-all', [LaporanLpdController::class, 'exportAll']);
Route::get('/update-keterangan', [LaporanLpdController::class, 'updateKeterangan']);
Route::get('/update-keterangan-all', [LaporanLpdController::class, 'updateKeteranganAll']);
Route::get('/matching-sarana-all', [LaporanLpdController::class, 'automatchSaranaAll']);
Route::get('/matching_sarana', [LaporanLpdController::class, 'automatchSarana']);
Route::get('/update-atpr-all', [LaporanLpdController::class, 'update_atprAll']);
Route::get('/update_atpr', [LaporanLpdController::class, 'update_atpr']);
Route::get('/matching_atpr', [LaporanLpdController::class, 'automatchDatpr']);
Route::get('/matching-atpr-all', [LaporanLpdController::class, 'automatchDatprAll']);
Route::get('/sync_pp', [LaporanLpdController::class, 'syncPP']);
Route::get('/sync-pp-all', [LaporanLpdController::class, 'syncPPAll']);
Route::get('/report-investasi', [LaporanLPDController::class, 'reportInvest']);
Route::get('/report-plus-minus', [LaporanLPDController::class, 'reportPlusMinus']);
Route::get('/report-outs', [LaporanLPDController::class, 'reportOuts']);

Route::get('/auto-bank', [Autobank::class, 'index']);
Route::get('/tasks', [Autobank::class, 'tasks']);
Route::post('/proses-mutasi', [Autobank::class, 'prosesMutasi']);
Route::post('/job-update', [Autobank::class, 'jobUpdate']);
Route::post('/job-status', [Autobank::class, 'jobStatus']);
Route::post('/submit-captcha', [Autobank::class, 'submitCaptcha']);

Route::prefix('master-dat-pr')->group(function () {
    Route::get('/filter', [DatPRController::class, 'filter']);
    Route::post('/search', [DatPRController::class, 'search']);
});

Route::prefix('rekon')->group(function () {
    Route::get('/type-bank', [ReconciliationController::class, 'getTypeBank']);
    Route::get('/rekening-reg', [ReconciliationController::class, 'getRekeningReg']);
    Route::get('/rekening-frc', [ReconciliationController::class, 'getRekeningFrc']);
    Route::post('/summary', [ReconciliationController::class, 'summary']);
    Route::post('/detail', [ReconciliationController::class, 'detail']);
    Route::post('/unrec-detail', [ReconciliationController::class, 'unrecDetail']);
    Route::post('/bulk-action',[ReconciliationController::class, 'bulkAction']);
});

Route::get('/gl', [FTPController::class, 'listFile']);
Route::post('/gl/import', [FTPController::class, 'GLimport']);
Route::get('/ftp', [FTPController::class, 'index']);
Route::post('/ftp', [FTPController::class, 'store']);
Route::put('/ftp/{id}', [FTPController::class, 'update']);
Route::delete('/ftp/{id}', [FTPController::class, 'destroy']);
Route::get('/ftp/{id}/test', [FTPController::class, 'testConnection']);

Route::get('/crypto/binance/btcusdt', function () {
    $response = Http::get('https://api.binance.com/api/v3/klines', [
        'symbol' => 'BTCUSDT',
        'interval' => '1m',
        'limit' => 1000
    ]);

    if ($response->failed()) {
        return response()->json(['error' => 'Gagal ambil data dari Binance'], 500);
    }

    $data = collect($response->json())->map(function ($item) {
        return [
            'time' => intval($item[0] / 1000),
            'open' => floatval($item[1]),
            'high' => floatval($item[2]),
            'low' => floatval($item[3]),
            'close' => floatval($item[4]),
        ];
    });

    return response()->json($data);
});



// Game Kakak
Route::get('/game-session/check', [GameController::class, 'check']);
Route::post('/game-session/new', [GameController::class, 'store']);
Route::get('/game-session', [GameController::class, 'index']);
Route::get('/bg-style', [GameController::class, 'getBgStyle']);
Route::get('/game-question', [GameController::class, 'getRandomQuestion']);
Route::post('/check-answer', [GameController::class, 'checkAnswer']);
Route::get('/game-session-detail', [GameController::class, 'getSessionDetail']);
Route::get('/game-board-setting', [GameController::class, 'boardSetting']);
Route::get('/characters', [GameController::class, 'getCharacters']);
