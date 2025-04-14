<?php
ini_set('memory_limit', '6144M');
include 'db.php';

require 'vendor/autoload.php';
// $startTime = microtime(true);

use PhpOffice\PhpSpreadsheet\Reader\Xlsx;

if (isset($_POST['submit'])) {
    if ($_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        echo "File upload error. Code: " . $_FILES['file']['error'];
        return;
    }

    $allowedExtensions = ['xls', 'xlsx'];
    $fileExtension = pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);

    if (!empty($_FILES['file']['name']) && in_array(strtolower($fileExtension), $allowedExtensions)) {
        if (is_uploaded_file($_FILES['file']['tmp_name'])) {
            $reader = new Xlsx();
            $spreadsheet = $reader->load($_FILES['file']['tmp_name']);
            $sheet = $spreadsheet->getActiveSheet();
            $sheet_arr = $sheet->toArray();
            unset($sheet_arr[0]);

            $filtered_rows = array_filter($sheet_arr, function ($row) {
                foreach ($row as $cell) {
                    if (!empty($cell) || $cell === '0') {
                        return true;
                    }
                }
                return false;
            });

            $chunkSize = 1000;
            $len = count($filtered_rows);

            // print_r($filtered_rows);
            $conn->begin_transaction();

            try {
                for ($i = 0; $i < $len; $i += $chunkSize) {
                    $chunk = array_slice($filtered_rows, $i, $chunkSize);

                    $values = [];
                    foreach ($chunk as $itr) {
                        $date = $itr[11] ?? '';
                        $currentDate = date('Y-m-d');
                        $tenantOnairDate = (DateTime::createFromFormat('Y-m-d', $date) !== false) ?
                            $date  : $currentDate;

                        $row = [
                            $conn->real_escape_string($itr[1] ?? ''),
                            $conn->real_escape_string($itr[2] ?? ''),
                            $conn->real_escape_string($itr[3] ?? ''),
                            $conn->real_escape_string($itr[4] ?? ''),
                            $conn->real_escape_string($itr[5] ?? ''),
                            $conn->real_escape_string($itr[6] ?? ''),
                            $conn->real_escape_string($itr[7] ?? ''),
                            $conn->real_escape_string($itr[8] ?? ''),
                            $conn->real_escape_string($itr[9] ?? ''),
                            $conn->real_escape_string($itr[10] ?? ''),
                            $tenantOnairDate,
                            $conn->real_escape_string($itr[12] ?? ''),
                            $conn->real_escape_string($itr[13] ?? ''),
                            $conn->real_escape_string($itr[14] ?? ''),
                            $conn->real_escape_string($itr[15] ?? ''),
                            (float) ($itr[16] ?? 0),
                            $conn->real_escape_string($itr[17] ?? ''),
                            (float) ($itr[18] ?? 0),
                            (int) ($itr[19] ?? 0),
                            (float) ($itr[20] ?? 0),
                            (float) ($itr[21] ?? 0),
                            (float) ($itr[22] ?? 0),
                            (float) ($itr[23] ?? 0),
                            $conn->real_escape_string($itr[24] ?? '')
                        ];

                        $rowSql = array_map(function ($value) {
                            return is_numeric($value) ? $value : "'" . $value . "'";
                        }, $row);

                        $values[] = "(" . implode(",", $rowSql) . ")";
                    }

                    $columns = "Circle, Entity, OutageMonth, State, Cluster, SiteID, ERPID, SiteName, Category, SubCategory,
                    TenantOnairDate, OperatorName, OperatorProduct, OperatorID, VendorName, IPFee, SignoffIPSlab,
                    SignoffCircleCustomerUptime, SiteOutageMins, SignoffUptime, SignoffPenalty, SignoffReward,
                    CostOfFOLossOutageMins, Status";

                    $sql = "INSERT INTO outagedata ($columns) VALUES " . implode(',', $values);

                    if (!$conn->query($sql)) {
                        throw new Exception("Query failed: " . $conn->error);
                    }
                }

                $conn->commit();
                header("location:dashboard.php");
            } catch (Exception $e) {
                $conn->rollback();
                echo " Failed: " . $e->getMessage();
            }
        }
    } else {
        echo "Only .xls or .xlsx files are allowed.";
    }
} else {
    echo "Please upload a file.";
}

// $endTime = microtime(true);
// $executionTime = $endTime - $startTime;

// echo $executionTime;
