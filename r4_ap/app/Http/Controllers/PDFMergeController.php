<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use setasign\Fpdi\Fpdi;
use Illuminate\Support\Facades\Storage;

class PDFMergeController extends Controller
{
    public function merge(Request $request)
    {
        $pdf = new Fpdi();

        // Ambil file dan halaman dari request
        $sources = $request->input('sources', []);

        foreach ($sources as $source) {
            $filePath = storage_path('app/pdfs/' . $source['file']);
            $page = $source['page'];

            $pageCount = $pdf->setSourceFile($filePath);
            if ($page > $pageCount) {
                continue;
            }

            $tpl = $pdf->importPage($page);
            $pdf->addPage();
            $pdf->useTemplate($tpl);
        }

        $outputPath = storage_path('app/pdfs/output.pdf');
        $pdf->Output('F', $outputPath);

        return response()->json([
            'success' => true,
            'url' => asset('storage/pdfs/output.pdf')
        ]);
    }
}
