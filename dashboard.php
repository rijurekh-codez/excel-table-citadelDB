<?php

include 'db.php';

$limit = 100;

if (isset($_GET['page'])) {
    $page = $_GET['page'];
} else {
    $page = 1;
}

$offset = ($page - 1) * $limit;

$fetchRegions = "SELECT fld_name FROM tbl_region ORDER BY fld_name";
$fetchRegionsResult = mysqli_query($conn, $fetchRegions);
$regions = [];
if (mysqli_num_rows($fetchRegionsResult) > 0) {
    while ($row = mysqli_fetch_assoc($fetchRegionsResult)) {
        array_push($regions, $row['fld_name']);
    }
}

$fetchCircles = "SELECT fld_fullname from tbl_circle ORDER BY fld_fullname";
$fetchCirclesResult = mysqli_query($conn, $fetchCircles);
$circles = [];
if (mysqli_num_rows($fetchCirclesResult) > 0) {
    while ($row = mysqli_fetch_assoc($fetchCirclesResult)) {
        array_push($circles, $row['fld_fullname']);
    }
}

$clusters = [];
$siteid = trim(isset($_GET['siteid']) ? $_GET['siteid'] : "");
$selected_regions = isset($_GET['region']) ? $_GET['region'] : [];
$selected_circles = isset($_GET['circle']) ? $_GET['circle'] : [];
$selected_clusters = isset($_GET['circle']) ? $_GET['circle'] : [];
$region_filter = "";
$siteid_filter = "";
$circle_filter = "";


if (!empty($selected_regions)) {
    $selected_regions = "'" . implode("','", $selected_regions) . "'";
    $region_filter = " AND tr.fld_name IN ($selected_regions)";
}
if (!empty($selected_circles)) {
    $selected_circles = "'" . implode("','", $selected_circles) . "'";
    $circle_filter = " AND tc.fld_fullname IN ($selected_circles)";
}

if (!empty($siteid)) {
    $siteid_filter = "AND ts.fld_tvi_site_id = '$siteid'";
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
    $siteid_filter
    $region_filter
    $circle_filter
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
    $selected_regions = isset($_GET['region']) ? $_GET['region'] : [];
    ?>
    <?php
    $selected_circles = isset($_GET['circle']) ? $_GET['circle'] : [];
    ?>
    <form method="get" class=" inline-block relative">
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
                    <input type="text" placeholder="Search" class="px-2 py-1 border border-gray-500 mb-2 rounded-md" id="searchRegion">

                    <label class="block mb-2">
                        <input type="checkbox" id="selectAll" class="mr-1">
                        <strong>Select All</strong>
                    </label>

                    <?php foreach ($regions as $region): ?>
                        <label class="block region-item">
                            <input type="checkbox" name="region[]" value="<?php echo $region; ?>"
                                <?php echo in_array($region, $selected_regions) ? 'checked' : ''; ?>>
                            <?php echo $region; ?>
                        </label>
                    <?php endforeach; ?>
                </div>

            </div>

            <label class=" font-semibold">Circle:</label>
            <div class="relative inline-block text-left">
                <button type="button" id="dropdownButtonCircle" class="inline-flex justify-center w-full border border-gray-300 shadow-sm px-2 py-1 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">
                    Select Circles
                    <svg class="-mr-1 ml-2 h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 10.94l3.71-3.71a.75.75 0 111.06 1.06l-4.25 4.25a.75.75 0 01-1.06 0L5.25 8.27a.75.75 0 01-.02-1.06z" clip-rule="evenodd" />
                    </svg>
                </button>

                <div id="dropdownMenuCircle" class="hidden absolute z-10 mt-2 w-56 bg-white ring-1 ring-black ring-opacity-5 p-4 max-h-64 overflow-y-auto">
                    <input type="text" placeholder="Search" class="px-1 py-1 border border-gray-500 mb-2 rounded-md" id="searchCircle">
                    <label class="block mb-2">
                        <input type="checkbox" id="selectAllCircle" class="mr-1">
                        <strong>Select All</strong>
                    </label>
                    <?php foreach ($circles as $circle): ?>
                        <label class="block circle-item">
                            <input type="checkbox" name="circle[]" value="<?php echo $circle; ?>"
                                <?php echo in_array($circle, $selected_circles) ? 'checked' : ''; ?>>
                            <?php echo $circle; ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <label class=" font-semibold">Cluster:</label>
            <div class="relative inline-block text-left">
                <button type="button" id="dropdownButtoncluster" class="inline-flex justify-center w-full border border-gray-300 shadow-sm px-2 py-1 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">
                    Select Clusters
                    <svg class="-mr-1 ml-2 h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 10.94l3.71-3.71a.75.75 0 111.06 1.06l-4.25 4.25a.75.75 0 01-1.06 0L5.25 8.27a.75.75 0 01-.02-1.06z" clip-rule="evenodd" />
                    </svg>
                </button>

                <div id="dropdownMenuCluster" class="hidden absolute z-10 mt-2 w-56 bg-white ring-1 ring-black ring-opacity-5 p-4 max-h-64 overflow-y-auto">
                    <input type="text" placeholder="Search" class="px-1 py-1 border border-gray-500 mb-2 rounded-md" id="searchCluster">
                    <label class="block mb-2">
                        <input type="checkbox" id="selectAllCircle" class="mr-1">
                        <strong>Select All</strong>
                    </label>
                    <?php foreach ($clusters as $cluster): ?>
                        <label class="block cluster-item">
                            <input type="checkbox" name="cluster[]" value="<?php echo $cluster; ?>"
                                <?php echo in_array($cluster, $selected_clusters) ? 'checked' : ''; ?>>
                            <?php echo $cluster; ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>


            <button type="submit" class="bg-blue-700 text-white px-3 py-1 text-sm">Search</button>
        </div>
    </form>
    <br>
    <form action="import_sheet.php" method="post" enctype="multipart/form-data" style="display: inline;">
        <input type="file" name="file" accept=".xls,.xlsx" required class="p-1 border border-gray-500">
        <button class="bg-black text-white px-3 py-1  rounded-sm text-[14px] mt-4 inline-block" type="submit" name="submit">Import Excel</button>
    </form>
    <form action="export_sheet.php?<?php echo http_build_query(array('data' => $_GET)) ?>" method="post" enctype="multipart/form-data" style="display: inline;">
        <button class="bg-green-600 text-white px-3 py-1  rounded-sm text-[16px] mt-4 inline-block" type="submit" name="export_submit">Export Excel</button>
    </form>

    <div class="overflow-auto max-h-[70vh] max-w-full border border-gray-300 mt-3">
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
        $siteid_filter
        $region_filter
        $circle_filter
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

                $separator1 = empty($selected_regions) ? '' : '&';
                $separator2 = empty($selected_circles) ? '' : '&';
                if ($current_page > 1) {
                    print_r('<li><a href="dashboard.php?page=' . ($current_page - 1) . $separator1 . http_build_query(array("region" => $selected_regions)) . $separator2 . http_build_query(array("circle" => $selected_circles)) . '"><< Prev</a></li>');
                }

                $start = max(1, $current_page - 1);
                $end = min($total_page, $current_page + 1);



                for ($i = $start; $i <= $end; $i++) {
                    $active = ($i == $current_page) ? 'active' : '';
                    print_r('<li><a class="' . $active . '" href="dashboard.php?page=' . $i . $separator1 . http_build_query(array("region" => $selected_regions)) . $separator2 . http_build_query(array("circle" => $selected_circles)) . '">' . $i . '</a></li>');
                }

                if ($current_page < $total_page) {
                    print_r('<li><a href="dashboard.php?page=' . ($current_page + 1) . $separator1 . http_build_query(array("region" => $selected_regions)) . $separator2 . http_build_query(array("circle" => $selected_circles)) . '">Next >></a></li>');
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
        const searchInput = document.getElementById("searchRegion");

        searchInput.addEventListener("input", function() {
            let filter = this.value.toLowerCase();
            let items = document.querySelectorAll(".region-item");

            let visibleItems = [];

            items.forEach(function(item) {
                let text = item.textContent.toLowerCase();
                if (text.includes(filter)) {
                    item.style.display = "block";
                    visibleItems.push(item);
                } else {
                    item.style.display = "none";
                }
            });

            const visibleCheckboxes = visibleItems.map(item => item.querySelector('input[type="checkbox"][name="region[]"]'));
            const allChecked = visibleCheckboxes.length > 0 && visibleCheckboxes.every(cb => cb.checked);
            selectAllCheckbox.checked = allChecked;
        });

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
            const items = dropdownMenu.querySelectorAll('.region-item');
            let hasVisible = false;

            items.forEach(item => {
                if (item.style.display !== "none") {
                    const cb = item.querySelector('input[type="checkbox"][name="region[]"]');
                    if (cb) {
                        cb.checked = selectAllCheckbox.checked;
                        hasVisible = true;
                    }
                }
            });

            if (!hasVisible && selectAllCheckbox.checked) {
                selectAllCheckbox.checked = false;
            }

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
                const items = dropdownMenu.querySelectorAll('.region-item');
                const visibleItems = Array.from(items).filter(item => item.style.display !== "none");
                const visibleCheckboxes = visibleItems.map(item => item.querySelector('input[type="checkbox"][name="region[]"]'));
                const checkedVisible = visibleCheckboxes.filter(cb => cb.checked);

                selectAllCheckbox.checked = visibleCheckboxes.length > 0 && visibleCheckboxes.length === checkedVisible.length;
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


        const dropdownButtonCircle = document.getElementById('dropdownButtonCircle');
        const dropdownMenuCircle = document.getElementById('dropdownMenuCircle');
        const selectAllCheckboxCircle = document.getElementById('selectAllCircle');
        const searchInputCircle = document.getElementById("searchCircle");

        searchInputCircle.addEventListener("input", function() {
            let filterCircle = this.value.toLowerCase();
            let itemsCircle = document.querySelectorAll(".circle-item");

            let visibleItemsCircle = [];

            itemsCircle.forEach(function(item) {
                let textCircle = item.textContent.toLowerCase();
                if (textCircle.includes(filterCircle)) {
                    item.style.display = "block";
                    visibleItemsCircle.push(item);
                } else {
                    item.style.display = "none";
                }
            });

            const visibleCheckboxesCircle = visibleItemsCircle.map(item => item.querySelector('input[type="checkbox"][name="circle[]"]'));
            const allCheckedCircle = visibleCheckboxesCircle.length > 0 && visibleCheckboxesCircle.every(cb => cb.checked);
            selectAllCheckboxCircle.checked = allCheckedCircle;
        });

        dropdownButtonCircle.addEventListener('click', function(e) {
            e.preventDefault();
            dropdownMenuCircle.classList.toggle('hidden');
        });

        document.addEventListener('click', function(e) {
            if (!dropdownButtonCircle.contains(e.target) && !dropdownMenuCircle.contains(e.target)) {
                dropdownMenuCircle.classList.add('hidden');
            }
        });

        selectAllCheckboxCircle.addEventListener('change', function() {
            const itemsCircle = dropdownMenuCircle.querySelectorAll('.circle-item');
            let hasVisibleCircle = false;

            itemsCircle.forEach(item => {
                if (item.style.display !== "none") {
                    const cb = item.querySelector('input[type="checkbox"][name="circle[]"]');
                    if (cb) {
                        cb.checked = selectAllCheckboxCircle.checked;
                        hasVisibleCircle = true;
                    }
                }
            });

            if (!hasVisibleCircle && selectAllCheckboxCircle.checked) {
                selectAllCheckboxCircle.checked = false;
            }

            updateDropdownLabelCircle();
        });

        function updateDropdownLabelCircle() {
            const checkboxesCircle = dropdownMenuCircle.querySelectorAll('input[type="checkbox"][name="circle[]"]');
            const selectedCircle = Array.from(checkboxesCircle).filter(cb => cb.checked);
            const totalCircle = checkboxesCircle.length;

            if (selectedCircle.length === 0) {
                dropdownButtonCircle.innerHTML = 'Select Circles' + dropdownIcon();
            } else if (selectedCircle.length === totalCircle) {
                dropdownButtonCircle.innerHTML = 'All selected' + ` (${totalCircle})` + dropdownIcon();
            } else if (selectedCircle.length <= 3) {
                const namesCircle = selectedCircle.map(cb => cb.value).join(', ');
                dropdownButtonCircle.innerHTML = namesCircle + dropdownIcon();
            } else {
                dropdownButtonCircle.innerHTML = `${selectedCircle.length} selected` + dropdownIcon();
            }
        }

        dropdownMenuCircle.addEventListener('change', function(e) {
            if (e.target.name === "circle[]") {
                const itemsCircle = dropdownMenuCircle.querySelectorAll('.circle-item');
                const visibleItemsCircle = Array.from(itemsCircle).filter(item => item.style.display !== "none");
                const visibleCheckboxesCircle = visibleItemsCircle.map(item => item.querySelector('input[type="checkbox"][name="circle[]"]'));
                const checkedVisibleCircle = visibleCheckboxesCircle.filter(cb => cb.checked);

                selectAllCheckboxCircle.checked = visibleCheckboxesCircle.length > 0 && visibleCheckboxesCircle.length === checkedVisibleCircle.length;
                updateDropdownLabelCircle();
            }
        });

        window.addEventListener('DOMContentLoaded', () => {
            updateDropdownLabelCircle();
            const checkboxesCircle = dropdownMenuCircle.querySelectorAll('input[type="checkbox"][name="circle[]"]');
            const selectedCircle = Array.from(checkboxesCircle).filter(cb => cb.checked);
            selectAllCheckboxCircle.checked = selectedCircle.length === checkboxesCircle.length;
        });
    </script>


</body>

</html>