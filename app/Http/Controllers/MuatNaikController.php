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

    public function destroy(MuatNaik $muatNaik)
    {
        if ($muatNaik->lokasi_fail && Storage::exists($muatNaik->lokasi_fail)) {
            Storage::delete($muatNaik->lokasi_fail);
        }

        $muatNaik->delete();

        return redirect()
            ->route('muat-naik.history')
            ->with('success', 'Rekod muat naik dan fail telah dipadamkan.');
    }

    public function store(Request $request)
    {
        $request->validate([
            'lokasi' => ['required', 'string'],
            'nama_fail' => ['required', 'string'],
            'sector_code' => ['required', 'string'],
            'sector_name' => ['required', 'string'],
            'agency_code' => ['required', 'string'],
            'agency_name' => ['required', 'string'],
        ]);

        $lokasi = $request->input('lokasi');

        $fullPath = storage_path('app/private/'.$lokasi);

        if (! Storage::exists($lokasi)) {
            return back()->withErrors([
                'fail_excel' => 'Fail muat naik sementara tidak dijumpai. Sila cuba semula.',
            ]);
        }

        MuatNaik::create([
            'nama_fail' => $request->input('nama_fail'),
            'lokasi_fail' => $lokasi,
            'status' => 'Berjaya',
            'tarikh_import' => now(),
            'sector_code' => $request->input('sector_code'),
            'sector_name' => $request->input('sector_name'),
            'agency_code' => $request->input('agency_code'),
            'agency_name' => $request->input('agency_name'),
        ]);

        return redirect()
            ->route('muat-naik.history')
            ->with('success', 'Fail berjaya disimpan bersama metadata sektor dan agensi.');
    }

    public function preview(
        Request $request,
        ExcelValidationService $excelValidationService,
        ExcelPreviewService $excelPreviewService
    ) {
        $request->validate([
            'fail_excel' => ['required', 'file', 'mimes:xlsx,xls'],
            'sector_code' => ['required', 'string'],
            'agency_code' => ['required', 'string'],
        ]);

        $sectorCode = $request->input('sector_code');
        $agencyCode = $request->input('agency_code');
        $sectorConfig = config('sektor');

        if (! isset($sectorConfig[$sectorCode])) {
            return back()->withErrors([
                'sector_code' => 'Sektor tidak sah. Sila pilih semula.',
            ]);
        }

        $sector = $sectorConfig[$sectorCode];
        $agency = collect($sector['agencies'])->firstWhere('code', $agencyCode);

        if (! $agency) {
            return back()->withErrors([
                'agency_code' => 'Agensi tidak sah untuk sektor yang dipilih.',
            ]);
        }

        $fail = $request->file('fail_excel');
        $namaFail = time().'_'.$fail->getClientOriginalName();
        $lokasi = $fail->storeAs('uploads', $namaFail);
        $fullPath = storage_path('app/private/'.$lokasi);

        if (! $excelValidationService->validateSheetNames($fullPath)) {
            Storage::delete($lokasi);

            return back()->withErrors([
                'fail_excel' => 'Helaian MasterTable atau MasterTable_Risk tidak ditemui.',
            ]);
        }

        $preview = $excelPreviewService->getPreview($fullPath);

        return view('uploads.preview', compact(
            'preview',
            'namaFail',
            'lokasi',
            'sectorCode',
            'sector',
            'agency'
        ));
    }
}
