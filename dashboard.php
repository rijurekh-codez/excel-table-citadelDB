<?php

include 'db.php';

$limit = 100;

if (isset($_GET['page'])) {
    $page = $_GET['page'];
} else {
    $page = 1;
}

$offset = ($page - 1) * $limit;
$regions = ['East', 'Central', 'Upper North', 'West', 'South'];
$selected_regions = isset($_GET['region']) ? $_GET['region'] : [];
$siteid = isset($_GET['siteid']) ? $_GET['siteid'] : "";
$region_filter = "";
$siteid_fiter = "";
$params = [];
$types = "";


if (!empty($selected_regions)) {
    $selected_regions = "'" . implode("','", $selected_regions) . "'";
    $region_filter = " AND tr.fld_name IN ($selected_regions)";
}

if (!empty($siteid)) {
    $siteid_fiter = "AND ts.fld_tvi_site_id = '$siteid'";
}

$sql = "
    SELECT 
        tr.fld_name AS Region, 
        tc.fld_name AS Circle, 
        tc2.fld_name AS Cluster,
        ts.fld_tvi_site_id AS `TVI Site ID`,
        ts.fld_name AS Site,
        MAX(CASE 
            WHEN tusrm.fld_role_id = 1 
            THEN CONCAT(tu.fld_first_name, ' ', tu.fld_last_name, ' (', tu.fld_phone , ')') 
        END) AS Technician,
        MAX(CASE 
            WHEN tusrm.fld_role_id = 2 
            THEN CONCAT(tu.fld_first_name, ' ', tu.fld_last_name, ' (', tu.fld_phone, ')') 
        END) AS `Cluster In-Charge`,
        MAX(CASE 
            WHEN tusrm.fld_role_id = 8 
            THEN CONCAT(tu.fld_first_name, ' ', tu.fld_last_name, ' (', tu.fld_phone, ')') 
        END) AS Supervisor
    FROM tbl_site AS ts 
    LEFT JOIN tbl_circle AS tc ON ts.fld_circle_id = tc.fld_ai_id AND tc.fld_is_active = '1'
    LEFT JOIN tbl_cluster AS tc2 ON ts.fld_cluster_id = tc2.fld_ai_id AND tc2.fld_is_active = '1'
    LEFT JOIN tbl_region AS tr ON tr.fld_ai_id = tc.fld_region_id AND tr.fld_is_active = '1'
    LEFT JOIN tbl_user_site_role_map AS tusrm ON tusrm.fld_site_id = ts.fld_ai_id AND tusrm.fld_is_active = '1'
    LEFT JOIN tbl_users AS tu ON tusrm.fld_user_id = tu.fld_ai_id AND tu.fld_is_active = '1'
    WHERE ts.fld_is_active = '1' 
    $siteid_fiter
    $region_filter
    GROUP BY 
        tr.fld_name, 
        tc.fld_name, 
        tc2.fld_name, 
        ts.fld_tvi_site_id,
        ts.fld_name
    LIMIT {$offset}, {$limit}";

$stmt = $conn->prepare($sql);
$stmt->execute();
$result = $stmt->get_result();

$tmp = [];
while ($row = $result->fetch_assoc()) {
    $tmp[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="pagination.css">
</head>

<body class="bg-gray-100 p-2">

    <?php
    $regions = ['East', 'Central', 'Upper North', 'West', 'South'];
    $selected_regions = isset($_GET['region']) ? $_GET['region'] : [];

    ?>
    <form method="get" class="mb-4 inline-block relative">
        <div class="flex flex-col sm:flex-row sm:items-center gap-2 mr-8">

            <label class="font-semibold">Site:</label>
            <input type="text" class="bg-white text-black border border-gray-300 px-2" name="siteid" placeholder="TVI Site ID" value="<?php echo $siteid; ?>">
            <label class=" font-semibold">Region:</label>
            <div class="relative inline-block text-left">
                <button type="button" id="dropdownButton" class="inline-flex justify-center w-full border border-gray-300 shadow-sm px-2 py-1 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">
                    Select Regions
                    <svg class="-mr-1 ml-2 h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 10.94l3.71-3.71a.75.75 0 111.06 1.06l-4.25 4.25a.75.75 0 01-1.06 0L5.25 8.27a.75.75 0 01-.02-1.06z" clip-rule="evenodd" />
                    </svg>
                </button>

                <div id="dropdownMenu" class="hidden absolute z-10 mt-2 w-56 bg-white ring-1 ring-black ring-opacity-5 p-4 max-h-64 overflow-y-auto">
                    <label class="block mb-2">
                        <input type="checkbox" id="selectAll" class="mr-1">
                        <strong>Select All</strong>
                    </label>
                    <?php foreach ($regions as $region): ?>
                        <label class="block">
                            <input type="checkbox" name="region[]" value="<?php echo $region; ?>"
                                <?php echo in_array($region, $selected_regions) ? 'checked' : ''; ?>>
                            <?php echo $region; ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <button type="submit" class="bg-blue-700 text-white px-3 py-1 text-sm">Search</button>
        </div>
    </form>




    <form action="import_sheet.php" method="post" enctype="multipart/form-data" style="display: inline;">
        <input type="file" name="file" accept=".xls,.xlsx" required class="p-1 border border-gray-500">
        <button class="bg-black text-white px-3 py-1  rounded-sm text-[14px] mt-4 inline-block" type="submit" name="submit">Import Excel</button>
    </form>
    <form action="export_sheet.php" method="post" enctype="multipart/form-data" style="display: inline;">
        <button class="bg-green-600 text-white px-3 py-1  rounded-sm text-[16px] mt-4 inline-block" type="submit" name="export_submit">Export Excel</button>
    </form>

    <div class="overflow-auto max-h-[70vh] max-w-full border border-gray-300 ">
        <table class="min-w-full table-auto bg-white border-collapse shadow-lg rounded-md">
            <thead>
                <tr class="bg-gray-200">
                    <th class="px-3 py-1 text-left text-sm font-semibold text-gray-600 border border-gray-400 border-t-0 border-l-0 border-r-1 border-b-0">Region</th>
                    <th class="px-3 py-1 text-left text-sm font-semibold text-gray-600 border border-gray-400 border-t-0 border-l-0 border-r-1 border-b-0">Circle</th>
                    <th class="px-3 py-1 text-left text-sm font-semibold text-gray-600 border border-gray-400 border-t-0 border-l-0 border-r-1 border-b-0">Cluster</th>
                    <th class="px-3 py-1 text-left text-sm font-semibold text-gray-600 border border-gray-400 border-t-0 border-l-0 border-r-1 border-b-0">TVI Site ID</th>
                    <th class="px-3 py-1 text-left text-sm font-semibold text-gray-600 border border-gray-400 border-t-0 border-l-0 border-r-1 border-b-0">Site</th>
                    <th class="px-3 py-1 text-left text-sm font-semibold text-gray-600 border border-gray-400 border-t-0 border-l-0 border-r-1 border-b-0">Technician(s)</th>
                    <th class="px-3 py-1 text-left text-sm font-semibold text-gray-600 border border-gray-400 border-t-0 border-l-0 border-r-1 border-b-0">Supervisor(s)</th>
                    <th class="px-3 py-1 text-left text-sm font-semibold text-gray-600 border border-gray-400 border-t-0 border-l-0 border-r-1 border-b-0">CI - Cluster in charge(s)</th>
                </tr>
            </thead>
            <tbody>


                <?php foreach ($tmp as $row): ?>
                    <tr class="border-t border-gray-200">
                        <td class="px-3 py-1 text-sm text-gray-700 border border-gray-300 border-t-0 border-l-0 border-r-1 border-b-0"><?php echo htmlspecialchars($row['Region']); ?></td>
                        <td class="px-3 py-1 text-sm text-gray-700 border border-gray-300 border-t-0 border-l-0 border-r-1 border-b-0"><?php echo htmlspecialchars($row['Circle']); ?></td>
                        <td class="px-3 py-1 text-sm text-gray-700 border border-gray-300 border-t-0 border-l-0 border-r-1 border-b-0"><?php echo htmlspecialchars($row['Cluster']); ?></td>
                        <td class="px-3 py-1 text-sm text-gray-700 border border-gray-300 border-t-0 border-l-0 border-r-1 border-b-0"><?php echo htmlspecialchars($row['TVI Site ID']); ?></td>
                        <td class="px-3 py-1 text-sm text-gray-700 border border-gray-300 border-t-0 border-l-0 border-r-1 border-b-0"><?php echo htmlspecialchars($row['Site']); ?></td>
                        <td class="px-3 py-1 text-sm text-gray-700 border border-gray-300 border-t-0 border-l-0 border-r-1 border-b-0">
                            <?php echo !empty($row['Technician']) ? htmlspecialchars($row['Technician']) : '—'; ?>
                        </td>
                        <td class="px-3 py-1 text-sm text-gray-700 border border-gray-300 border-t-0 border-l-0 border-r-1 border-b-0">
                            <?php echo !empty($row['Supervisor']) ? htmlspecialchars($row['Supervisor']) : '—'; ?>
                        </td>
                        <td class="px-3 py-1 text-sm text-gray-700 border border-gray-300 border-t-0 border-l-0 border-r-1 border-b-0">
                            <?php echo !empty($row['Cluster In-Charge']) ? htmlspecialchars($row['Cluster In-Charge']) : '—'; ?>
                        </td>

                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php if (count($tmp) == 0) { ?>
            <p class="text-center font-medium py-4 bg-gray-300">No records found</p>
        <?php } ?>
    </div>

    <div class="pagination-container">
        <ul class="pagination">
            <?php
            $sql1 =  "
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
    $siteid_fiter
    $region_filter

GROUP BY 
	tr.fld_name, 
	tc.fld_name, 
	tc2.fld_name, 
	ts.fld_tvi_site_id,
	ts.fld_name;
";

            $result1 = mysqli_query($conn, $sql1) or die("Query failed");

            if (mysqli_num_rows($result1) > 0) {
                $total_records = mysqli_num_rows($result1);
                $total_page = ceil($total_records / $limit);
                $current_page = isset($_GET['page']) ? $_GET['page'] : 1;

                echo "<div class='px-2 rounded-0 mr-10 mt-1 font-medium'>";
                $start = ($current_page - 1) * $limit + 1;
                $end = min($start + $limit - 1, $total_records);

                echo "Showing " . $start . " to " . $end . " of " . $total_records . " Site";
                echo "</div>";

                if ($current_page > 1) {
                    echo '<li><a href="dashboard.php?page=' . ($current_page - 1) . ' &' . http_build_query(array("region" => $selected_regions)) . '"><< Prev</a></li>';
                }

                $start = max(1, $current_page - 1);
                $end = min($total_page, $current_page + 1);

                for ($i = $start; $i <= $end; $i++) {
                    $active = ($i == $current_page) ? 'active' : '';
                    echo '<li><a class="' . $active . '" href="dashboard.php?page=' . $i . '&' . http_build_query(array("region" => $selected_regions)) . '">' . $i . '</a></li>';
                }

                if ($current_page < $total_page) {
                    print_r('<li><a href="dashboard.php?page=' . ($current_page + 1) . '&' . http_build_query(array("region" => $selected_regions)) . '">Next >></a></li>');
                }
            }
            ?>
        </ul>
    </div>


    <script>
        document.querySelector('form').addEventListener('submit', function(e) {
            const siteInput = this.querySelector('input[name="siteid"]');
            if (!siteInput.value.trim()) {
                siteInput.remove();
            }
        });

        const dropdownButton = document.getElementById('dropdownButton');
        const dropdownMenu = document.getElementById('dropdownMenu');
        const selectAllCheckbox = document.getElementById('selectAll');

        dropdownButton.addEventListener('click', function(e) {
            e.preventDefault();
            dropdownMenu.classList.toggle('hidden');
        });

        document.addEventListener('click', function(e) {
            if (!dropdownButton.contains(e.target) && !dropdownMenu.contains(e.target)) {
                dropdownMenu.classList.add('hidden');
            }
        });

        selectAllCheckbox.addEventListener('change', function() {
            const checkboxes = dropdownMenu.querySelectorAll('input[type="checkbox"][name="region[]"]');
            checkboxes.forEach(cb => cb.checked = this.checked);
            updateDropdownLabel();
        });

        function updateDropdownLabel() {
            const checkboxes = dropdownMenu.querySelectorAll('input[type="checkbox"][name="region[]"]');
            const selected = Array.from(checkboxes).filter(cb => cb.checked);
            const total = checkboxes.length;

            if (selected.length === 0) {
                dropdownButton.innerHTML = 'Select Regions' + dropdownIcon();
            } else if (selected.length === total) {
                dropdownButton.innerHTML = 'All selected' + ` (${total})` + dropdownIcon();
            } else if (selected.length <= 3) {
                const names = selected.map(cb => cb.value).join(', ');
                dropdownButton.innerHTML = names + dropdownIcon();
            } else {
                dropdownButton.innerHTML = `${selected.length} selected` + dropdownIcon();
            }
        }

        dropdownMenu.addEventListener('change', function(e) {
            if (e.target.name === "region[]") {
                const checkboxes = dropdownMenu.querySelectorAll('input[type="checkbox"][name="region[]"]');
                const selected = Array.from(checkboxes).filter(cb => cb.checked);
                selectAllCheckbox.checked = selected.length === checkboxes.length;
                updateDropdownLabel();
            }
        });

        function dropdownIcon() {
            return `<svg class="-mr-1 ml-2 h-5 w-5 inline" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 10.94l3.71-3.71a.75.75 0 111.06 1.06l-4.25 4.25a.75.75 0 01-1.06 0L5.25 8.27a.75.75 0 01-.02-1.06z" clip-rule="evenodd" />
                </svg>`;
        }

        window.addEventListener('DOMContentLoaded', () => {
            updateDropdownLabel();
            const checkboxes = dropdownMenu.querySelectorAll('input[type="checkbox"][name="region[]"]');
            const selected = Array.from(checkboxes).filter(cb => cb.checked);
            selectAllCheckbox.checked = selected.length === checkboxes.length;
        });
    </script>


</body>

</html>