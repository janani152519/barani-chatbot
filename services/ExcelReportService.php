<?php
/**
 * Excel Report Generator Service using PhpSpreadsheet
 */

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class ExcelReportService
{
    /**
     * Generate XLSX spreadsheet file.
     */
    public static function generate(array $data, string $outputPath, string $title = 'Company Report'): string
    {
        if (!is_dir(dirname($outputPath))) {
            mkdir(dirname($outputPath), 0755, true);
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(substr($title, 0, 31));

        // 1. Report Title Header Banner
        $sheet->setCellValue('A1', strtoupper($title));
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFFFFF'));
        $sheet->getStyle('A1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF1F2937'); // Dark slate
        $sheet->getRowDimension(1)->setRowHeight(35);
        $sheet->getStyle('A1')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setHorizontal(Alignment::HORIZONTAL_LEFT);

        if (empty($data)) {
            $sheet->setCellValue('A3', 'No records found for this report.');
        } else {
            // 2. Table Column Headers
            $headers = array_keys($data[0]);
            $colChar = 'A';
            foreach ($headers as $h) {
                $sheet->setCellValue($colChar . '3', ucwords(str_replace('_', ' ', $h)));
                $sheet->getStyle($colChar . '3')->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFFFFF'));
                $sheet->getStyle($colChar . '3')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF3B82F6'); // Accent blue
                $sheet->getStyle($colChar . '3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $colChar++;
            }
            $lastColChar = chr(ord('A') + count($headers) - 1);
            $sheet->mergeCells("A1:{$lastColChar}1");

            // 3. Write Data Rows
            $rowNum = 4;
            foreach ($data as $row) {
                $cChar = 'A';
                foreach ($row as $val) {
                    $sheet->setCellValue($cChar . $rowNum, $val);
                    $cChar++;
                }
                $rowNum++;
            }

            // Auto-fit Column Widths
            $currCol = 'A';
            for ($i = 0; $i < count($headers); $i++) {
                $sheet->getColumnDimension($currCol)->setAutoSize(true);
                $currCol++;
            }
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($outputPath);

        return $outputPath;
    }
}
