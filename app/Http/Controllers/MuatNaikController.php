<?php

namespace App\Http\Controllers;

use App\Models\MuatNaik;
use App\Services\ExcelPreviewService;
use App\Services\ExcelValidationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MuatNaikController extends Controller
{
    public function index()
    {
        return view('uploads.index');
    }

    public function history()
    {
        $rekod = MuatNaik::latest()->paginate(10);

        return view(
            'uploads.history',
            compact('rekod')
        );
    }

    public function store(
        Request $request,
        ExcelValidationService $excelValidationService
    ) {
        $request->validate([
            'fail_excel' => [
                'required',
                'file',
                'mimes:xlsx,xls',
            ],
        ]);

        $fail = $request->file('fail_excel');

        $namaFail = time().'_'.
                    $fail->getClientOriginalName();

        $lokasi = $fail->storeAs(
            'uploads',
            $namaFail
        );

        $fullPath = storage_path(
            'app/private/'.$lokasi
        );

        if (
            ! $excelValidationService
                ->validateSheetNames($fullPath)
        ) {

            Storage::delete($lokasi);

            return back()->withErrors([
                'fail_excel' => 'Helaian MasterTable atau MasterTable_Risk tidak ditemui.',
            ]);
        }

        MuatNaik::create([
            'nama_fail' => $namaFail,
            'lokasi_fail' => $lokasi,
            'status' => 'Berjaya',
            'tarikh_import' => now(),
        ]);

        return redirect()
            ->route('muat-naik.history')
            ->with(
                'success',
                'Fail berjaya dimuat naik.'
            );
    }

    public function preview(
        Request $request,
        ExcelValidationService $excelValidationService,
        ExcelPreviewService $excelPreviewService
    ) {
        $request->validate([
            'fail_excel' => [
                'required',
                'file',
                'mimes:xlsx,xls',
            ],
        ]);

        $fail = $request->file('fail_excel');

        $namaFail =
            time().'_'.$fail->getClientOriginalName();

        $lokasi = $fail->storeAs(
            'uploads',
            $namaFail
        );

        $fullPath =
            storage_path('app/private/'.$lokasi);

        if (
            ! $excelValidationService
                ->validateSheetNames($fullPath)
        ) {

            Storage::delete($lokasi);

            return back()->withErrors([
                'fail_excel' => 'Helaian MasterTable atau MasterTable_Risk tidak ditemui.',
            ]);
        }

        $preview =
            $excelPreviewService
                ->getPreview($fullPath);

        return view(
            'uploads.preview',
            compact(
                'preview',
                'namaFail',
                'lokasi'
            )
        );
    }
}
