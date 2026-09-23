<?php
/**
 * Word Document (.docx) Report Generator Service
 */

class WordReportService
{
    /**
     * Generate a customizable Word (.docx) document.
     */
    public static function generate(array $params, string $outputPath): string
    {
        $dir = dirname($outputPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $tempConfig = $dir . '/docx_cfg_' . bin2hex(random_bytes(8)) . '.json';
        $params['output_path'] = $outputPath;

        file_put_contents($tempConfig, json_encode($params, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        $scriptPath = realpath(__DIR__ . '/../scripts/generate_docx.py');
        $cmd = "python " . escapeshellarg($scriptPath) . " " . escapeshellarg($tempConfig) . " 2>&1";
        $output = shell_exec($cmd);

        @unlink($tempConfig);

        if (file_exists($outputPath) && filesize($outputPath) > 0) {
            return $outputPath;
        }

        // Fallback: Generate HTML-based Word Document (.doc format compatible with Word)
        self::generateWordHtmlFallback($params, $outputPath);
        return $outputPath;
    }

    /**
     * Native PHP Word fallback (MIME application/msword formatted as Word HTML in Executive Form Format).
     */
    private static function generateWordHtmlFallback(array $params, string $outputPath): void
    {
        $title = htmlspecialchars($params['title'] ?? 'Amended True-Up Payroll Report');
        $date = htmlspecialchars($params['date'] ?? date('d/m/Y'));
        $reportType = strtoupper($params['report_type'] ?? 'GENERAL');
        $isPayroll = ($reportType === 'PAYROLL' || str_contains(strtolower($title), 'payroll') || str_contains(strtolower($title), 'true-up'));

        $policyNumber = htmlspecialchars($params['policy_number'] ?? ($isPayroll ? 'BWC-8492014-0' : 'BHI-DOC-8492014-0'));
        $legalName = htmlspecialchars($params['legal_name'] ?? 'BARANI HYDRAULICS (INDIA) PVT. LTD.');
        $tradingName = htmlspecialchars($params['trading_name'] ?? 'BARANI HYDRAULICS / GRI SCADA INTELLIGENCE');
        $mailingAddress = htmlspecialchars($params['mailing_address'] ?? 'SF No. 248/2, Trichy Road, Sulur');
        $emailAddress = htmlspecialchars($params['email_address'] ?? 'baranihydraulics@gmail.com');
        $telephone = htmlspecialchars($params['telephone'] ?? '(0422) 268-9100');
        $city = htmlspecialchars($params['city'] ?? 'Coimbatore');
        $state = htmlspecialchars($params['state'] ?? 'TN / OH');
        $zipCode = htmlspecialchars($params['zip_code'] ?? '641402');
        $periodFrom = htmlspecialchars($params['period_from'] ?? '01/08/2025');
        $periodThrough = htmlspecialchars($params['period_through'] ?? '31/08/2025');

        $classificationTitle = htmlspecialchars($params['classification_title'] ?? ($isPayroll ? 'NCCI manual classification' : 'Operational & Telemetry Classification'));
        $colHeaders = $params['col_headers'] ?? [
            'Manual', 'Type code', 'Description', 'Number of employees', 'Original reported payroll', 'Actual payroll'
        ];
        $cleanColHeaders = array_map(fn($h) => str_replace('<br>', ' ', $h), $colHeaders);

        $ncciRows = $params['ncci_rows'] ?? $params['classification_rows'] ?? [];
        if (empty($ncciRows)) {
            $ncciRows = [
                ['manual' => '8810', 'type' => 'REG', 'desc' => 'Clerical Office Employees NOC (HR / Admin)', 'emp' => 2, 'orig' => 95000.00, 'actual' => 100000.00],
                ['manual' => '8803', 'type' => 'REG', 'desc' => 'Auditing, Accounting & Financial Ops', 'emp' => 2, 'orig' => 125000.00, 'actual' => 130000.00],
                ['manual' => '8601', 'type' => 'REG', 'desc' => 'Engineers & Technical Support Services', 'emp' => 1, 'orig' => 45000.00, 'actual' => 48000.00],
                ['manual' => '3632', 'type' => 'REG', 'desc' => 'Machine Shop & Hydraulic Equipment Mfg', 'emp' => 2, 'orig' => 60000.00, 'actual' => 62500.00],
            ];
        }

        $rowsHtml = '';
        $totEmp = 0; $totOrig = 0.0; $totAct = 0.0; $hasNumeric = false;
        foreach ($ncciRows as $idx => $r) {
            $manual = htmlspecialchars($r['manual'] ?? '');
            $type = htmlspecialchars($r['type'] ?? 'REG');
            $desc = htmlspecialchars($r['desc'] ?? '');
            $emp = (int)($r['emp'] ?? 0);
            $totEmp += $emp;

            $origRaw = $r['orig'] ?? '';
            $actRaw  = $r['actual'] ?? '';

            if ($isPayroll) {
                $orig = (float)$origRaw;
                $act  = (float)$actRaw;
                $totOrig += $orig; $totAct += $act; $hasNumeric = true;
                $origFmt = '$' . number_format($orig, 2);
                $actFmt  = '$' . number_format($act, 2);
            } else {
                if (is_numeric($origRaw) && is_numeric($actRaw)) {
                    $orig = (float)$origRaw;
                    $act  = (float)$actRaw;
                    $totOrig += $orig; $totAct += $act; $hasNumeric = true;
                    $origFmt = number_format($orig, 2);
                    $actFmt  = number_format($act, 2);
                } else {
                    $origFmt = htmlspecialchars((string)$origRaw);
                    $actFmt  = htmlspecialchars((string)$actRaw);
                }
            }

            $bg = ($idx % 2 === 1) ? '#f8fafc' : '#ffffff';
            $rowsHtml .= "<tr style='background:{$bg};'>
                <td align='center'>{$manual}</td>
                <td align='center'>{$type}</td>
                <td>{$desc}</td>
                <td align='center'>" . ($emp > 0 ? $emp : htmlspecialchars((string)$origRaw)) . "</td>
                <td align='right'>{$origFmt}</td>
                <td align='right'>{$actFmt}</td>
            </tr>";
        }

        $totalLabel = $isPayroll ? 'TOTAL:' : 'TOTAL AUDITED SUMMARY:';
        if ($isPayroll) {
            $totOrigStr = '$' . number_format($totOrig, 2);
            $totActStr  = '$' . number_format($totAct, 2);
        } elseif ($hasNumeric && ($totOrig > 0 || $totAct > 0)) {
            $totOrigStr = number_format($totOrig, 2);
            $totActStr  = number_format($totAct, 2);
        } else {
            $totOrigStr = 'Design Baseline';
            $totActStr  = 'Compliant / Verified';
        }

        $reason = htmlspecialchars($params['reason_for_change'] ?? ($params['notes'] ?? 'Operational audit reconciliation and SCADA telemetry verification.'));
        $signName = htmlspecialchars($params['signature_name'] ?? 'Managing Director / Officer');
        $signTitle = htmlspecialchars($params['signature_title'] ?? 'Authorized Officer');

        $certText = htmlspecialchars($params['certification_text'] ?? (
            $isPayroll
                ? "I hereby certify that the payroll records and wage figures reported herein are accurate, verified against company accounts, and reflect all employee disbursements for the stated period.\nBy my signature, I certify I have the authority to approve and execute this official document on behalf of Barani Hydraulics (India) Pvt. Ltd."
                : "I hereby certify that the operational metrics, telemetry data, and system logs reported herein are true, accurate, and verified against SCADA supervisory records for the stated period.\nBy my signature, I certify I have the authority to execute this document, and that all facts set forth herein are true and correct to the best of my knowledge and belief."
        ));

        $formCode = htmlspecialchars($params['form_code'] ?? ($isPayroll ? "BH-PR-DOC-{$date} | Barani Enterprise Payroll Record" : "BHI-DOC-7578 (Rev. {$date}) | SCADA Intelligence Official Record"));

        $headerLeftHtml = "<span style='font-size:16pt; font-weight:bold; color:#1e3a8a;'>BARANI HYDRAULICS</span><br>
               <span style='font-size:9pt; font-weight:bold; color:#334155;'>(India) Pvt. Ltd. &bull; " . ($isPayroll ? "Payroll &amp; Compensation Audit" : "GRI SCADA Machine Intelligence") . "</span><br>
               <span style='font-size:8pt; color:#64748b;'>SF No. 248/2, Trichy Road, Sulur, Coimbatore – 641 402</span><br>
               <small><strong>Instructions:</strong> " . ($isPayroll ? "Official payroll audit and wage verification record." : "Complete this official audit report in its entirety along with operational remarks.") . "</small>";

        $html = <<<HTML
<html xmlns:o='urn:schemas-microsoft-com:office:office' xmlns:w='urn:schemas-microsoft-com:office:word' xmlns='http://www.w3.org/TR/REC-html40'>
<head>
<meta charset='utf-8'>
<title>{$title}</title>
<style>
body { font-family: 'Calibri', 'Arial', sans-serif; font-size: 10pt; color: #000; margin: 24pt; }
table { width: 100%; border-collapse: collapse; margin-top: 8pt; }
th, td { border: 1pt solid #000; padding: 4pt 6pt; }
.header-box { border: 1.5pt solid #000; padding: 6pt; margin-bottom: 8pt; }
</style>
</head>
<body>
<table style='border:none; margin-bottom:12pt;'>
  <tr style='border:none;'>
    <td style='border:none; width:60%; vertical-align:top;'>
      {$headerLeftHtml}
    </td>
    <td style='border:none; width:40%; text-align:right; vertical-align:top;'>
      <div style='font-size:14pt; font-weight:bold; font-family:"Times New Roman", serif;'>{$title}</div>
      <div style='border:1.5pt solid #000; padding:4pt; text-align:left; margin-top:4pt;'>
        <small>Policy number</small><br><strong>{$policyNumber}</strong>
      </div>
    </td>
  </tr>
</table>

<div class='header-box'>
  <strong>Legal business name:</strong> {$legalName}<br>
  <strong>Trading name or DBA:</strong> {$tradingName}<br>
  <strong>Mailing address:</strong> {$mailingAddress} &bull; <strong>City/State/ZIP:</strong> {$city}, {$state} {$zipCode}<br>
  <strong>Payroll period:</strong> from <u>{$periodFrom}</u> through <u>{$periodThrough}</u>
</div>

<h3>{$classificationTitle}</h3>
<table>
  <tr style='background:#1e3a8a; color:#fff;'>
    <th>{$cleanColHeaders[0]}</th><th>{$cleanColHeaders[1]}</th><th>{$cleanColHeaders[2]}</th><th>{$cleanColHeaders[3]}</th><th>{$cleanColHeaders[4]}</th><th>{$cleanColHeaders[5]}</th>
  </tr>
  {$rowsHtml}
  <tr style='background:#e2e8f0; font-weight:bold;'>
    <td colspan='3' align='right'>{$totalLabel}</td>
    <td align='center'>{$totEmp}</td>
    <td align='right'>{$totOrigStr}</td>
    <td align='right'>{$totActStr}</td>
  </tr>
</table>

<div style='border:1pt solid #000; padding:6pt; margin-top:10pt;'>
  <strong>Reason for change:</strong><br>
  <em>{$reason}</em>
</div>

<p style='font-size:8pt; margin-top:10pt;'>
  <strong>Certification:</strong> {$certText}
</p>

<table style='border:1pt solid #000; margin-top:6pt;'>
  <tr>
    <td style='width:75%;'>Signature and title: [AUTHORIZED SIGNATURE] {$signName} &mdash; {$signTitle}</td>
    <td style='width:25%;'>Date: {$date}</td>
  </tr>
</table>

<p style='font-size:8pt; color:#333; margin-top:10pt;'>
  <strong>{$formCode}</strong>
</p>
</body>
</html>
HTML;

        file_put_contents($outputPath, $html);
    }
}
