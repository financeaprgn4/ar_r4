<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\LegacyFileController;
use App\Http\Controllers\ReportController;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Http\Controllers\LaporanLPDController;

Route::get('/file/{folder}/{filename}', [LegacyFileController::class, 'showFile']);
Route::get('/rk/{cabang}/{filename}', [LegacyFileController::class, 'statement']);
Route::get('/generate-cs', [ReportController::class, 'generateCS']);
Route::get('/export-datpr', [LaporanLPDController::class, 'exportDatPrCsv']);
Route::get('/export-detail', [LaporanLPDController::class, 'exportDetailCsv']);
