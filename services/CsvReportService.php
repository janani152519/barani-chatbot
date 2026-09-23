<?php
/**
 * CSV Report Generator Service
 */

class CsvReportService
{
    /**
     * Generate CSV file from data rows.
     */
    public static function generate(array $data, string $outputPath, string $title = 'Report'): string
    {
        if (!is_dir(dirname($outputPath))) {
            mkdir(dirname($outputPath), 0755, true);
        }

        $fp = fopen($outputPath, 'w');
        // Add UTF-8 BOM for Excel compatibility
        fprintf($fp, chr(0xEF).chr(0xBB).chr(0xBF));

        if (!empty($data)) {
            // Write Header Row
            $headers = array_keys($data[0]);
            fputcsv($fp, array_map('ucwords', array_map(fn($h) => str_replace('_', ' ', $h), $headers)));

            // Write Data Rows
            foreach ($data as $row) {
                fputcsv($fp, $row);
            }
        } else {
            fputcsv($fp, ["No data available for {$title}"]);
        }

        fclose($fp);
        return $outputPath;
    }
}
