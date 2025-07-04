<?php
// includes/report_functions.php
require_once 'db_connect.php';

function generateReport($type, $format = 'pdf', $dateRange = null) {
    switch ($type) {
        case 'monthly_income':
            $data = getMonthlyIncomeData($dateRange);
            $title = "Monthly Income Report";
            break;
        case 'occupancy':
            $data = getOccupancyData();
            $title = "House Occupancy Report";
            break;
        case 'demographics':
            $data = getDemographicsData();
            $title = "Tenant Demographics Report";
            break;
        case 'income_by_landlord':
            $data = getIncomeByLandlordData($dateRange);
            $title = "Income by Landlord Report";
            break;
        case 'tenant_history':
            $data = getTenantHistoryData($dateRange);
            $title = "Tenant History Report";
            break;
        case 'payment_history':
            $data = getPaymentHistoryData($dateRange);
            $title = "Payment History Report";
            break;
        case 'house_performance':
            $data = getHousePerformanceData($dateRange);
            $title = "House Performance Report";
            break;
        default:
            die("Invalid report type");
    }

    switch ($format) {
        case 'pdf':
            generatePdf($title, $data);
            break;
        case 'excel':
            generateExcel($title, $data);
            break;
        case 'csv':
            generateCsv($title, $data);
            break;
        default:
            generatePdf($title, $data);
    }
}

// Data fetching functions
function getMonthlyIncomeData($dateRange) {
    global $pdo;
    
    // Parse date range if provided
    $where = "";
    $params = [];
    if ($dateRange) {
        $dates = explode(" - ", $dateRange);
        $where = "WHERE payment_date BETWEEN ? AND ?";
        $params = [$dates[0], $dates[1]];
    }
    
    $stmt = $pdo->prepare("SELECT * FROM payments $where ORDER BY payment_date DESC");
    $stmt->execute($params);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process data for report
    $reportData = [
        'title' => 'Monthly Income Report',
        'headers' => ['ID', 'Tenant', 'Amount', 'Payment Date', 'Payment Method'],
        'rows' => [],
        'summary' => [
            'total_amount' => 0,
            'payment_methods' => []
        ]
    ];
    
    foreach ($payments as $payment) {
        $reportData['rows'][] = [
            $payment['id'],
            getTenantName($payment['tenant_id']),
            number_format($payment['amount'], 2),
            $payment['payment_date'],
            $payment['payment_method']
        ];
        
        $reportData['summary']['total_amount'] += $payment['amount'];
        
        if (!isset($reportData['summary']['payment_methods'][$payment['payment_method']])) {
            $reportData['summary']['payment_methods'][$payment['payment_method']] = 0;
        }
        $reportData['summary']['payment_methods'][$payment['payment_method']] += $payment['amount'];
    }
    
    return $reportData;
}

// Add similar functions for other report types (getOccupancyData, getDemographicsData, etc.)

// Output generation functions
function generatePdf($title, $data) {
    require_once('../vendor/autoload.php'); // Require TCPDF or other PDF library
    
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor('Boarding House Management');
    $pdf->SetTitle($title);
    $pdf->SetHeaderData('', 0, $title, 'Generated on ' . date('Y-m-d H:i:s'));
    $pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
    $pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));
    $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
    $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
    $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
    $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
    $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
    $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
    $pdf->AddPage();
    
    // Generate HTML content
    $html = '<h1>'.$title.'</h1>';
    $html .= '<p>Generated on: '.date('Y-m-d H:i:s').'</p>';
    
    if (!empty($data['headers']) && !empty($data['rows'])) {
        $html .= '<table border="1" cellpadding="4">';
        $html .= '<tr>';
        foreach ($data['headers'] as $header) {
            $html .= '<th style="font-weight:bold;background-color:#f2f2f2;">'.$header.'</th>';
        }
        $html .= '</tr>';
        
        foreach ($data['rows'] as $row) {
            $html .= '<tr>';
            foreach ($row as $cell) {
                $html .= '<td>'.$cell.'</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</table>';
    }
    
    // Add summary if available
    if (!empty($data['summary'])) {
        $html .= '<h3>Summary</h3>';
        $html .= '<p>Total Amount: '.number_format($data['summary']['total_amount'], 2).'</p>';
        
        if (!empty($data['summary']['payment_methods'])) {
            $html .= '<h4>By Payment Method:</h4>';
            foreach ($data['summary']['payment_methods'] as $method => $amount) {
                $html .= '<p>'.$method.': '.number_format($amount, 2).'</p>';
            }
        }
    }
    
    $pdf->writeHTML($html, true, false, true, false, '');
    $pdf->Output($title.'.pdf', 'D');
}

function generateExcel($title, $data) {
    require_once('../vendor/autoload.php'); // Require PhpSpreadsheet
    
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle(substr($title, 0, 31));
    
    // Set headers
    $sheet->setCellValue('A1', $title);
    $sheet->mergeCells('A1:E1');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
    
    $sheet->setCellValue('A2', 'Generated on: '.date('Y-m-d H:i:s'));
    $sheet->mergeCells('A2:E2');
    
    // Add data headers
    if (!empty($data['headers'])) {
        $col = 'A';
        $row = 3;
        
        foreach ($data['headers'] as $header) {
            $sheet->setCellValue($col.$row, $header);
            $sheet->getStyle($col.$row)->getFont()->setBold(true);
            $col++;
        }
        
        // Add data rows
        $row++;
        foreach ($data['rows'] as $dataRow) {
            $col = 'A';
            foreach ($dataRow as $cell) {
                $sheet->setCellValue($col.$row, $cell);
                $col++;
            }
            $row++;
        }
        
        // Auto-size columns
        foreach (range('A', $col) as $columnID) {
            $sheet->getColumnDimension($columnID)->setAutoSize(true);
        }
    }
    
    // Add summary
    if (!empty($data['summary'])) {
        $row++;
        $sheet->setCellValue('A'.$row, 'Summary');
        $sheet->getStyle('A'.$row)->getFont()->setBold(true);
        $row++;
        
        $sheet->setCellValue('A'.$row, 'Total Amount:');
        $sheet->setCellValue('B'.$row, number_format($data['summary']['total_amount'], 2));
        $row++;
        
        if (!empty($data['summary']['payment_methods'])) {
            $sheet->setCellValue('A'.$row, 'By Payment Method:');
            $sheet->getStyle('A'.$row)->getFont()->setBold(true);
            $row++;
            
            foreach ($data['summary']['payment_methods'] as $method => $amount) {
                $sheet->setCellValue('A'.$row, $method);
                $sheet->setCellValue('B'.$row, number_format($amount, 2));
                $row++;
            }
        }
    }
    
    // Output the file
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="'.$title.'.xlsx"');
    header('Cache-Control: max-age=0');
    
    $writer->save('php://output');
    exit;
}

function generateCsv($title, $data) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment;filename="'.$title.'.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Add title and generation date
    fputcsv($output, [$title]);
    fputcsv($output, ['Generated on: '.date('Y-m-d H:i:s')]);
    fputcsv($output, []); // Empty row
    
    // Add headers if available
    if (!empty($data['headers'])) {
        fputcsv($output, $data['headers']);
    }
    
    // Add data rows
    if (!empty($data['rows'])) {
        foreach ($data['rows'] as $row) {
            fputcsv($output, $row);
        }
    }
    
    // Add summary if available
    if (!empty($data['summary'])) {
        fputcsv($output, []); // Empty row
        fputcsv($output, ['Summary']);
        fputcsv($output, ['Total Amount:', number_format($data['summary']['total_amount'], 2)]);
        
        if (!empty($data['summary']['payment_methods'])) {
            fputcsv($output, []); // Empty row
            fputcsv($output, ['By Payment Method:']);
            
            foreach ($data['summary']['payment_methods'] as $method => $amount) {
                fputcsv($output, [$method, number_format($amount, 2)]);
            }
        }
    }
    
    fclose($output);
    exit;
}

// Helper function
function getTenantName($tenant_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT CONCAT(first_name, ' ', last_name) AS name FROM tenants WHERE id = ?");
    $stmt->execute([$tenant_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? $result['name'] : 'Unknown';
}
?>