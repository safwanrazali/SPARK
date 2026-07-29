<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\IOFactory;

class ExcelValidationService
{
    public function validateSheetNames(string $fullPath): bool
    {
        $spreadsheet = IOFactory::load($fullPath);

        $sheetNames = $spreadsheet->getSheetNames();

        return in_array('MasterTable', $sheetNames)
            || in_array('MasterTable_Risk', $sheetNames);
    }
}
