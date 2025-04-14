<?php
ini_set('memory_limit', '-1');

require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

include 'db.php';

if (isset($_POST['export_submit'])) {

    $downloadQuery = "
    SELECT 
	tr.fld_name AS Region, 
	tc.fld_name AS Circle, 
	tc2.fld_name AS Cluster,
	ts.fld_tvi_site_id AS 'TVI Site ID',
	ts.fld_name AS Site,
	MAX(CASE 
		WHEN tusrm.fld_role_id = 1 
		THEN CONCAT(tu.fld_first_name, ' ', tu.fld_last_name, ' (', tu.fld_phone , ')') 
	END) AS 'Technician',
	MAX(CASE 
		WHEN tusrm.fld_role_id = 2
		THEN CONCAT(tu.fld_first_name, ' ', tu.fld_last_name, ' (', tu.fld_phone, ')') 
	END) AS 'Cluster In-Charge',
	MAX(CASE 
		WHEN tusrm.fld_role_id = 8 
		THEN CONCAT(tu.fld_first_name, ' ', tu.fld_last_name, ' (', tu.fld_phone, ')') 
	END) AS 'Supervisor'
FROM tbl_site AS ts 
LEFT JOIN tbl_circle AS tc 
	ON ts.fld_circle_id = tc.fld_ai_id 
	AND tc.fld_is_active = '1'
LEFT JOIN tbl_cluster AS tc2  
	ON ts.fld_cluster_id = tc2.fld_ai_id 
	AND tc2.fld_is_active = '1'
LEFT JOIN tbl_region AS tr 
	ON tr.fld_ai_id = tc.fld_region_id 
	AND tr.fld_is_active = '1'
LEFT JOIN tbl_user_site_role_map AS tusrm 
	ON tusrm.fld_site_id = ts.fld_ai_id 
	AND tusrm.fld_is_active = '1'
LEFT JOIN tbl_users AS tu 
	ON tusrm.fld_user_id = tu.fld_ai_id 
	AND tu.fld_is_active = '1'
WHERE 
	ts.fld_is_active = '1'
GROUP BY 
	tr.fld_name, 
	tc.fld_name, 
	tc2.fld_name, 
	ts.fld_tvi_site_id,
	ts.fld_name;
";

    $stmt = $conn->prepare($downloadQuery);
    $stmt->execute();
    $result = $stmt->get_result();

    $tmp = [];


    while ($row = $result->fetch_assoc()) {
        $tmp[] = $row;
    }

    $spreadsheet = new Spreadsheet();
    $activeWorksheet = $spreadsheet->getActiveSheet();

    $activeWorksheet->setCellValue('A1', 'Region');
    $activeWorksheet->setCellValue('B1', 'Circle');
    $activeWorksheet->setCellValue('C1', 'Cluster');
    $activeWorksheet->setCellValue('D1', 'TVI Site ID');
    $activeWorksheet->setCellValue('E1', 'Site');
    $activeWorksheet->setCellValue('F1', 'Technician(s)');
    $activeWorksheet->setCellValue('G1', 'Supervisor(s)');
    $activeWorksheet->setCellValue('H1', 'CI - Cluster in charge(s)');
    $row = 2;

    foreach ($tmp as $value) {
        $activeWorksheet->setCellValue('A' . $row, $value['Region']);
        $activeWorksheet->setCellValue('B' . $row, $value['Circle']);
        $activeWorksheet->setCellValue('C' . $row, $value['Cluster']);
        $activeWorksheet->setCellValue('D' . $row, $value['TVI Site ID']);
        $activeWorksheet->setCellValue('E' . $row, $value['Site']);
        $activeWorksheet->setCellValue('F' . $row, $value['Technician']);
        $activeWorksheet->setCellValue('G' . $row, $value['Supervisor']);
        $activeWorksheet->setCellValue('H' . $row, $value['Cluster In-Charge']);
        $row++;
    }

    $filename = "Site_data.xlsx";
    $writer = new Xlsx($spreadsheet);
    $writer->save("output/" . $filename);
    header("location:output/" . $filename);
}
