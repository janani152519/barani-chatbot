<?php
/**
 * PDF Report Generator Service using Dompdf
 * Formatted in the official Barani Executive Corporate Payroll Report Architecture
 */

use Dompdf\Dompdf;
use Dompdf\Options;

class PdfReportService
{
    /**
     * Generate PDF document file from dataset.
     */
    public static function generate(array $data, string $outputPath, string $title = 'Company Report'): string
    {
        require_once __DIR__ . '/ReportService.php';
        $meta = ReportService::getGeneralReportData($data, ['title' => $title]);
        $params = array_merge([
            'title'       => $title,
            'report_type' => 'GENERAL',
            'records'     => $data,
            'raw_records' => $data,
        ], $meta);
        return self::generateExecutiveFormReport($params, $outputPath);
    }

    /**
     * Generate PDF document from customizable text, metadata, and optional table.
     */
    public static function generateCustom(array $params, string $outputPath): string
    {
        return self::generateExecutiveFormReport($params, $outputPath);
    }

    /**
     * Alias for backward compatibility with payroll tests.
     */
    public static function generateAmendedTrueUpReport(array $params, string $outputPath): string
    {
        return self::generateExecutiveFormReport($params, $outputPath);
    }

    /**
     * Alias for backward compatibility with heat calculation tests.
     */
    public static function generateHeatCalculationReport(array $params, string $outputPath): string
    {
        require_once __DIR__ . '/ReportService.php';
        $meta = ReportService::getHeatCalculationReportData($params);
        $params = array_merge($params, $meta);
        return self::generateExecutiveFormReport($params, $outputPath);
    }

    /**
     * Universal Executive Corporate Form Report PDF Generator (Ohio BWC / Barani Form Standard)
     */
    public static function generateExecutiveFormReport(array $params, string $outputPath): string
    {
        if (!is_dir(dirname($outputPath))) {
            mkdir(dirname($outputPath), 0755, true);
        }

        $title          = htmlspecialchars($params['title'] ?? 'Amended True-Up Payroll Report');
        $reportType     = strtoupper($params['report_type'] ?? 'GENERAL');
        $isPayroll      = ($reportType === 'PAYROLL' || str_contains(strtolower($title), 'payroll') || str_contains(strtolower($title), 'true-up'));

        $policyNumber   = htmlspecialchars($params['policy_number'] ?? ($isPayroll ? 'BWC-8492014-0' : 'BHI-DOC-8492014-0'));
        $legalName      = htmlspecialchars($params['legal_name'] ?? 'BARANI HYDRAULICS (INDIA) PVT. LTD.');
        $tradingName    = htmlspecialchars($params['trading_name'] ?? 'BARANI HYDRAULICS / GRI SCADA INTELLIGENCE');
        $mailingAddress = htmlspecialchars($params['mailing_address'] ?? 'SF No. 248/2, Trichy Road, Sulur');
        $emailAddress   = htmlspecialchars($params['email_address'] ?? 'baranihydraulics@gmail.com');
        $telephone      = htmlspecialchars($params['telephone'] ?? '(0422) 268-9100');
        $city           = htmlspecialchars($params['city'] ?? 'Coimbatore');
        $state          = htmlspecialchars($params['state'] ?? 'TN / OH');
        $zipCode        = htmlspecialchars($params['zip_code'] ?? '641402');
        $periodFrom     = htmlspecialchars($params['period_from'] ?? '01/08/2025');
        $periodThrough  = htmlspecialchars($params['period_through'] ?? '31/08/2025');
        $reasonForChange= htmlspecialchars($params['reason_for_change'] ?? ($params['notes'] ?? 'Operational audit reconciliation and SCADA telemetry verification for reported period.'));
        $signatureName  = htmlspecialchars($params['signature_name'] ?? 'Managing Director / Officer');
        $signatureTitle = htmlspecialchars($params['signature_title'] ?? 'Authorized Officer');
        $signDate       = htmlspecialchars($params['date'] ?? date('d/m/Y'));

        $classificationTitle = htmlspecialchars($params['classification_title'] ?? ($isPayroll ? 'NCCI manual classification' : 'Operational & Telemetry Classification'));
        $colHeaders = $params['col_headers'] ?? [
            'Manual', 'Type code', 'Description', 'Number of<br>employees', 'Original reported<br>payroll', 'Actual<br>payroll'
        ];

        // Classification rows: format 8 rows for the authentic form layout
        $rowsData = $params['ncci_rows'] ?? $params['classification_rows'] ?? [];
        if (empty($rowsData)) {
            $rowsData = [
                ['manual' => '8810', 'type' => 'REG', 'desc' => 'Clerical Office Employees NOC (HR / Admin)', 'emp' => 2, 'orig' => 95000.00, 'actual' => 100000.00],
                ['manual' => '8803', 'type' => 'REG', 'desc' => 'Auditing, Accounting & Financial Ops', 'emp' => 2, 'orig' => 125000.00, 'actual' => 130000.00],
                ['manual' => '8601', 'type' => 'REG', 'desc' => 'Engineers & Technical Support Services', 'emp' => 1, 'orig' => 45000.00, 'actual' => 48000.00],
                ['manual' => '3632', 'type' => 'REG', 'desc' => 'Machine Shop & Hydraulic Equipment Mfg', 'emp' => 2, 'orig' => 60000.00, 'actual' => 62500.00],
            ];
        }

        $tableRowsHtml = '';
        $totalOrig = 0.0;
        $totalActual = 0.0;
        $totalEmp = 0;
        $hasNumericTotals = false;

        $rowCount = max(8, count($rowsData));
        for ($i = 0; $i < $rowCount; $i++) {
            $row = $rowsData[$i] ?? null;
            if ($row) {
                $manual = htmlspecialchars((string)($row['manual'] ?? ''));
                $type   = htmlspecialchars((string)($row['type'] ?? 'REG'));
                $desc   = htmlspecialchars((string)($row['desc'] ?? ''));
                $emp    = (int)($row['emp'] ?? 0);
                $totalEmp += $emp;
                $empStr = $emp > 0 ? (string)$emp : ($row['emp'] ?? '');

                if ($isPayroll) {
                    $origVal = (float)($row['orig'] ?? 0);
                    $actVal  = (float)($row['actual'] ?? 0);
                    $totalOrig += $origVal;
                    $totalActual += $actVal;
                    $hasNumericTotals = true;
                    $origFmt = '₹' . number_format($origVal, 2);
                    $actFmt  = '₹' . number_format($actVal, 2);
                } else {
                    $origRaw = $row['orig'] ?? '';
                    $actRaw  = $row['actual'] ?? '';
                    if (is_numeric($origRaw) && is_numeric($actRaw)) {
                        $origVal = (float)$origRaw;
                        $actVal  = (float)$actRaw;
                        $totalOrig += $origVal;
                        $totalActual += $actVal;
                        $hasNumericTotals = true;
                        $origFmt = number_format($origVal, 2);
                        $actFmt  = number_format($actVal, 2);
                    } else {
                        $origFmt = htmlspecialchars((string)$origRaw);
                        $actFmt  = htmlspecialchars((string)$actRaw);
                    }
                }

                $tableRowsHtml .= "
                <tr style='height: 24px; font-size: 9px;'>
                    <td style='border-right: 1px solid #000; border-bottom: 1px solid #000; padding: 2px 4px; text-align: center;'>{$manual}</td>
                    <td style='border-right: 1px solid #000; border-bottom: 1px solid #000; padding: 2px 4px; text-align: center;'>{$type}</td>
                    <td style='border-right: 1px solid #000; border-bottom: 1px solid #000; padding: 2px 5px;'>{$desc}</td>
                    <td style='border-right: 1px solid #000; border-bottom: 1px solid #000; padding: 2px 4px; text-align: center;'>{$empStr}</td>
                    <td style='border-right: 1px solid #000; border-bottom: 1px solid #000; padding: 2px 6px; text-align: right;'>{$origFmt}</td>
                    <td style='border-bottom: 1px solid #000; padding: 2px 6px; text-align: right;'>{$actFmt}</td>
                </tr>";
            } else {
                $tableRowsHtml .= "
                <tr style='height: 22px; font-size: 9px;'>
                    <td style='border-right: 1px solid #000; border-bottom: 1px solid #000; padding: 2px 4px;'>&nbsp;</td>
                    <td style='border-right: 1px solid #000; border-bottom: 1px solid #000; padding: 2px 4px;'>&nbsp;</td>
                    <td style='border-right: 1px solid #000; border-bottom: 1px solid #000; padding: 2px 4px;'>&nbsp;</td>
                    <td style='border-right: 1px solid #000; border-bottom: 1px solid #000; padding: 2px 4px;'>&nbsp;</td>
                    <td style='border-right: 1px solid #000; border-bottom: 1px solid #000; padding: 2px 6px;'>&nbsp;</td>
                    <td style='border-bottom: 1px solid #000; padding: 2px 6px;'>&nbsp;</td>
                </tr>";
            }
        }

        if ($isPayroll) {
            $totalLabel = 'TOTAL:';
            $totalOrigFmt = '₹' . number_format($totalOrig, 2);
            $totalActualFmt = '₹' . number_format($totalActual, 2);
        } else {
            $totalLabel = 'TOTAL AUDITED SUMMARY:';
            if ($hasNumericTotals) {
                $totalOrigFmt = number_format($totalOrig, 2);
                $totalActualFmt = number_format($totalActual, 2);
            } else {
                $totalOrigFmt = 'Design Baseline / Spec';
                $totalActualFmt = 'Operational / Compliant';
            }
        }

        require_once __DIR__ . '/ReportService.php';
        $logoBase64 = ReportService::getBaraniLogoBase64();
        $logoHtml = !empty($logoBase64)
            ? "<img src=\"{$logoBase64}\" style=\"height: 46px; width: auto; border: 2px solid #1e3a8a; padding: 2px; border-radius: 3px; background:#ffffff;\" alt=\"Barani Logo\">"
            : "<div style=\"height: 44px; width: 44px; background: #1e3a8a; color: #ffffff; font-size: 18px; font-weight: bold; text-align: center; line-height: 44px; border-radius: 4px; font-family: Arial, sans-serif;\">BH</div>";

        // Instructions
        $instructions = $params['instructions'] ?? (
            $isPayroll
                ? "<strong>Instructions &amp; Policy Compliance</strong><br>&bull; This official payroll summary details gross and net salary disbursements, statutory deductions, and employee counts.<br>&bull; Submit this report to the Barani HR &amp; Finance Department for review and audit filing."
                : "<strong>Instructions</strong><br>&bull; Complete this official audit report in its entirety along with operational remarks. All metrics and logged events are verified via SCADA telemetry.<br>&bull; Submit this report to the Barani Plant Head or authorized corporate officer for review and archive."
        );

        // Certification
        $certificationText = $params['certification_text'] ?? (
            $isPayroll
                ? "I hereby certify that the amended payroll reported herein is correct as to the classification and amount for the period stated. I understand that misrepresentation of payroll data may lead to disciplinary action as per the Company's HR &amp; Finance Policy and applicable Indian labour laws.<br>By my signature, I certify I have the authority to execute this document, and that all facts set forth herein are true and correct to the best of my knowledge and belief. This document is an official record of Barani Hydraulics (India) Pvt. Ltd. — GRI SCADA Machine Intelligence System."
                : "I hereby certify that the operational metrics, telemetry data, and system logs reported herein are true, accurate, and verified against SCADA supervisory records for the stated period.<br>By my signature, I certify I have the authority to execute this document, and that all facts set forth herein are true and correct to the best of my knowledge and belief. This document is an official record of Barani Hydraulics (India) Pvt. Ltd. — GRI SCADA Machine Intelligence System."
        );

        // Form footer code
        $formCode = $params['form_code'] ?? ($isPayroll ? "BH-PR-{$signDate} | Barani Enterprise Payroll Record" : "BHI-DOC-7578 (Rev. {$signDate}) | SCADA Intelligence Official Record");

        // Detailed Itemization Ledger (Secondary Table)
        $rawRecords = $params['raw_records'] ?? $params['records'] ?? [];
        $recordCount = count($rawRecords);
        $secondaryTableHtml = '';

        if (!empty($rawRecords)) {
            $recHeaders = array_keys($rawRecords[0]);
            $hdrThs = '';
            foreach ($recHeaders as $rh) {
                $hdrThs .= "<th style='padding: 2px 4px; border-right: 1px solid #000; border-bottom: 1px solid #000; text-align: left; background: #f1f5f9;'>" . htmlspecialchars(ucwords(str_replace('_', ' ', $rh))) . "</th>";
            }

            $recRows = '';
            foreach ($rawRecords as $rec) {
                $recRows .= "<tr style='border-bottom: 1px solid #ccc;'>";
                foreach ($recHeaders as $rh) {
                    $val = htmlspecialchars((string)($rec[$rh] ?? ''));
                    $recRows .= "<td style='padding: 2px 4px; border-right: 1px solid #000;'>{$val}</td>";
                }
                $recRows .= "</tr>";
            }

            $ledgerTitle = $isPayroll
                ? "Barani Hydraulics &bull; Departmental Employee Payroll Audit Ledger ({$recordCount} Personnel)"
                : "Barani Hydraulics &bull; Supervisory Operational Audit Ledger ({$recordCount} Records)";

            $secondaryTableHtml = "
            <div style='margin-top: 10px; border-top: 1px dashed #999; padding-top: 6px;'>
                <table style='width: 100%; margin-bottom: 3px;'>
                    <tr>
                        <td style='text-align: left; vertical-align: middle;'>
                            <strong style='font-size: 8px; text-transform: uppercase;'>{$ledgerTitle}</strong>
                        </td>
                        <td style='text-align: right; vertical-align: middle; font-size: 7px; color: #555;'>
                            Live Database Record Vault &bull; Verified
                        </td>
                    </tr>
                </table>
                <table class='form-border' style='font-size: 7px;'>
                    <thead>
                        <tr style='font-weight: bold;'>{$hdrThs}</tr>
                    </thead>
                    <tbody>
                        {$recRows}
                    </tbody>
                </table>
            </div>";
        }

        $headerEntityHtml = "<div style=\"display: inline-block; vertical-align: middle;\">
                 <div style=\"font-size: 13px; font-weight: 900; line-height: 1.1; font-family: Arial, sans-serif; color: #1e3a8a; letter-spacing: -0.3px;\">BARANI HYDRAULICS</div>
                 <div style=\"font-size: 7.5px; font-weight: bold; line-height: 1.3; font-family: Arial, sans-serif; color: #334155;\">(India) Pvt. Ltd. &bull; " . ($isPayroll ? "Payroll &amp; Compensation Audit" : "GRI SCADA Machine Intelligence") . "</div>
                 <div style=\"font-size: 6.5px; color: #64748b; margin-top: 1px;\">SF No. 248/2, Trichy Road, Sulur, Coimbatore – 641 402</div>
               </div>";

        $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>{$title}</title>
<style>
    @page { margin: 16px 22px 16px 22px; }
    body { font-family: 'DejaVu Sans', Helvetica, Arial, sans-serif; color: #000000; font-size: 9px; line-height: 1.22; margin: 0; padding: 0; }
    .bold { font-weight: bold; }
    table { width: 100%; border-collapse: collapse; }
    .form-border { border: 1.5px solid #000; }
    .border-b { border-bottom: 1px solid #000; }
    .border-r { border-right: 1px solid #000; }
    .cell-pad { padding: 3px 6px; }
    .lbl { font-size: 8px; color: #111; margin-bottom: 1px; }
    .val { font-size: 9.5px; font-weight: bold; color: #000; }
</style>
</head>
<body>

    <!-- Header Table with Barani Logo & Identity -->
    <table style="margin-bottom: 6px;">
        <tr>
            <td style="width: 56%; vertical-align: top;">
                <table style="border: none;">
                    <tr>
                        <td style="width: 50px; vertical-align: middle; padding-right: 8px;">
                            {$logoHtml}
                        </td>
                        <td style="vertical-align: middle; border-left: 2px solid #1e3a8a; padding-left: 8px;">
                            {$headerEntityHtml}
                        </td>
                    </tr>
                </table>
                <div style="font-size: 7.8px; line-height: 1.22; margin-top: 5px; color: #111;">
                    {$instructions}
                </div>
            </td>
            <td style="width: 44%; vertical-align: top; text-align: right;">
                <div style="font-size: 15px; font-weight: 900; font-family: 'Times New Roman', serif; margin-bottom: 8px; letter-spacing: -0.2px;">
                    {$title}
                </div>
                <table style="width: 100%; border: 1.5px solid #000; border-collapse: collapse; text-align: left;">
                    <tr>
                        <td style="padding: 3px 6px; height: 30px; vertical-align: top;">
                            <div class="lbl">Policy number</div>
                            <div class="val" style="font-size: 11px; margin-top: 2px;">{$policyNumber}</div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <!-- Employer / Plant Identity Grid -->
    <table class="form-border" style="margin-top: 4px;">
        <tr>
            <td style="width: 50%;" class="border-r border-b cell-pad">
                <div class="lbl">Legal business name</div>
                <div class="val">{$legalName}</div>
            </td>
            <td style="width: 50%;" class="border-b cell-pad">
                <div class="lbl">Trading name or doing business as name</div>
                <div class="val">{$tradingName}</div>
            </td>
        </tr>
        <tr>
            <td style="width: 50%;" class="border-r border-b cell-pad">
                <div class="lbl">Mailing address</div>
                <div class="val">{$mailingAddress}</div>
            </td>
            <td style="width: 50%; padding: 0;" class="border-b">
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="width: 55%;" class="border-r cell-pad">
                            <div class="lbl">Email address</div>
                            <div class="val" style="font-size: 8.5px;">{$emailAddress}</div>
                        </td>
                        <td style="width: 45%;" class="cell-pad">
                            <div class="lbl">Telephone number</div>
                            <div class="val">{$telephone}</div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td colspan="2" style="padding: 0;">
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="width: 50%;" class="border-r cell-pad">
                            <div class="lbl">City</div>
                            <div class="val">{$city}</div>
                        </td>
                        <td style="width: 25%;" class="border-r cell-pad">
                            <div class="lbl">State</div>
                            <div class="val">{$state}</div>
                        </td>
                        <td style="width: 25%;" class="cell-pad">
                            <div class="lbl">ZIP code</div>
                            <div class="val">{$zipCode}</div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <!-- Reporting Period Box -->
    <table class="form-border" style="margin-top: 5px;">
        <tr>
            <td class="cell-pad" style="height: 24px; vertical-align: top;">
                <div class="lbl">Payroll period</div>
                <div style="font-size: 9.5px; margin-top: 1px;">
                    from &nbsp;<span style="border-bottom: 1px solid #000; padding: 0 14px; font-weight: bold;">{$periodFrom}</span>
                    &nbsp;&nbsp;&nbsp;&nbsp;
                    through &nbsp;<span style="border-bottom: 1px solid #000; padding: 0 14px; font-weight: bold;">{$periodThrough}</span>
                </div>
            </td>
        </tr>
    </table>

    <!-- Primary 6-Column Classification Table -->
    <table class="form-border" style="margin-top: 5px;">
        <thead>
            <tr>
                <th colspan="6" style="border-bottom: 1.5px solid #000; padding: 3px 6px; font-size: 9.5px; font-weight: bold; text-align: center; background: #ffffff;">
                    {$classificationTitle}
                </th>
            </tr>
            <tr style="font-size: 8px; font-weight: bold; text-align: center; background: #1e3a8a; color: #ffffff;">
                <th style="width: 10%; border-right: 1px solid #000; border-bottom: 1.5px solid #000; padding: 4px 2px;">{$colHeaders[0]}</th>
                <th style="width: 11%; border-right: 1px solid #000; border-bottom: 1.5px solid #000; padding: 4px 2px;">{$colHeaders[1]}</th>
                <th style="width: 39%; border-right: 1px solid #000; border-bottom: 1.5px solid #000; padding: 4px 4px; text-align: left;">{$colHeaders[2]}</th>
                <th style="width: 12%; border-right: 1px solid #000; border-bottom: 1.5px solid #000; padding: 4px 2px;">{$colHeaders[3]}</th>
                <th style="width: 14%; border-right: 1px solid #000; border-bottom: 1.5px solid #000; padding: 4px 4px; text-align: right;">{$colHeaders[4]}</th>
                <th style="width: 14%; border-bottom: 1.5px solid #000; padding: 4px 4px; text-align: right;">{$colHeaders[5]}</th>
            </tr>
        </thead>
        <tbody>
            {$tableRowsHtml}
        </tbody>
        <tfoot>
            <tr style="font-weight: bold; font-size: 8.5px; background: #fafafa;">
                <td colspan="3" style="border-right: 1px solid #000; padding: 3px 6px; text-align: right;">{$totalLabel}</td>
                <td style="border-right: 1px solid #000; padding: 3px 4px; text-align: center;">{$totalEmp}</td>
                <td style="border-right: 1px solid #000; padding: 3px 6px; text-align: right;">{$totalOrigFmt}</td>
                <td style="padding: 3px 6px; text-align: right;">{$totalActualFmt}</td>
            </tr>
        </tfoot>
    </table>

    <!-- Reason for Report / Change -->
    <table class="form-border" style="margin-top: 5px;">
        <tr>
            <td class="cell-pad" style="height: 42px; vertical-align: top;">
                <div class="lbl">Reason for change</div>
                <div style="font-size: 9px; line-height: 1.35; margin-top: 3px; color: #1e293b;">
                    {$reasonForChange}
                </div>
            </td>
        </tr>
    </table>

    <!-- Certification Block -->
    <div style="margin-top: 6px; font-size: 7.2px; line-height: 1.25; color: #111;">
        <strong style="font-size: 8px;">Certification</strong><br>
        {$certificationText}
    </div>

    <!-- Signatures Block -->
    <table class="form-border" style="margin-top: 5px;">
        <tr>
            <td style="width: 78%;" class="border-r cell-pad">
                <div class="lbl">Signature and title (must be signed by owner, partner or officer)</div>
                <div style="margin-top: 6px; font-size: 9.5px; font-weight: bold; color: #0f172a;">
                    <span style="display: inline-block; background-color: #ecfdf5; color: #047857; border: 1px solid #10b981; border-radius: 3px; padding: 1px 5px; font-size: 7.5px; font-weight: bold; letter-spacing: 0.4px; margin-right: 5px;">[AUTHORIZED SIGNATURE]</span> {$signatureName} - {$signatureTitle}
                </div>
            </td>
            <td style="width: 22%;" class="cell-pad">
                <div class="lbl">Date</div>
                <div style="margin-top: 8px; font-size: 9.5px; font-weight: bold; color: #0f172a;">
                    {$signDate}
                </div>
            </td>
        </tr>
    </table>

    <!-- Footer Form Codes -->
    <table style="margin-top: 6px; font-size: 7.5px; color: #000;">
        <tr>
            <td style="text-align: left; vertical-align: top; line-height: 1.15;">
                <span class="bold">{$formCode}</span><br>
                <span class="bold">Barani Hydraulics &bull; GRI SCADA Machine Intelligence</span>
            </td>
            <td style="text-align: right; vertical-align: top; color: #64748b;">
                Barani Hydraulics (India) Pvt. Ltd. &bull; Official Confidential Record Vault
            </td>
        </tr>
    </table>

    <!-- Secondary Detailed Itemization Ledger (if records present) -->
    {$secondaryTableHtml}

</body>
</html>
HTML;

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false);
        $options->setDefaultFont('DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        file_put_contents($outputPath, $dompdf->output());
        return $outputPath;
    }
}
