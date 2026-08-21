<?php

namespace App\Services;

use App\Support\Halaman;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ExcelPreviewService
{
    public function getPreview(
        string $fullPath,
        int $limit = Halaman::SETIAP_MUKA
    ): array {

        $spreadsheet = IOFactory::load($fullPath);

        $sheet = null;

        if (
            $spreadsheet->sheetNameExists('MasterTable')
        ) {
            $sheet =
                $spreadsheet->getSheetByName(
                    'MasterTable'
                );
        } else {
            $sheet =
                $spreadsheet->getSheetByName(
                    'MasterTable_Risk'
                );
        }

        $rows = $sheet->toArray();

        return array_slice(
            $rows,
            0,
            $limit
        );
    }
}
