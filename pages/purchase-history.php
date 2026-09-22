<?php

require_once "../db.php";

/*
|--------------------------------------------------------------------------
| Filters
|--------------------------------------------------------------------------
*/

$from_date = $_GET['from_date'] ?? '';
$to_date   = $_GET['to_date'] ?? '';
$store_id  = $_GET['store_id'] ?? '';
$search    = $_GET['search'] ?? '';

/*
|--------------------------------------------------------------------------
| Stores for dropdown
|--------------------------------------------------------------------------
*/

$stores = [];

$store_query = $conn->query("
    SELECT id, store_name
    FROM stores
    ORDER BY store_name ASC
");

if ($store_query) {
    while ($store = $store_query->fetch_assoc()) {
        $stores[] = $store;
    }
}

/*
|--------------------------------------------------------------------------
| Purchase History Query
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT 
        p.id,
        p.purchase_date,
        p.purchase_time,
        p.bill_number,
        p.total,
        p.bill_image,
        s.store_name,
        COUNT(pi.id) AS item_count
    FROM purchases p
    INNER JOIN stores s 
        ON p.store_id = s.id
    LEFT JOIN purchase_items pi 
        ON p.id = pi.purchase_id
    WHERE 1=1
";

$params = [];
$types = "";

/*
|--------------------------------------------------------------------------
| Date Filter
|--------------------------------------------------------------------------
*/

if (!empty($from_date)) {
    $sql .= " AND p.purchase_date >= ?";
    $params[] = $from_date;
    $types .= "s";
}

if (!empty($to_date)) {
    $sql .= " AND p.purchase_date <= ?";
    $params[] = $to_date;
    $types .= "s";
}

/*
|--------------------------------------------------------------------------
| Store Filter
|--------------------------------------------------------------------------
*/

if (!empty($store_id)) {
    $sql .= " AND p.store_id = ?";
    $params[] = $store_id;
    $types .= "i";
}

/*
|--------------------------------------------------------------------------
| Bill Search
|--------------------------------------------------------------------------
*/

if (!empty($search)) {
    $sql .= " AND p.bill_number LIKE ?";
    $params[] = "%" . $search . "%";
    $types .= "s";
}

$sql .= "
    GROUP BY 
        p.id,
        p.purchase_date,
        p.purchase_time,
        p.bill_number,
        p.total,
        p.bill_image,
        s.store_name
    ORDER BY p.purchase_date DESC, p.id DESC
";

$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();

$result = $stmt->get_result();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Purchase History - Grocery Manager</title>

    <link rel="stylesheet" href="../assets/css/style.css">

    <style>

        .page-content {
            padding: 30px;
        }

        .page-header {
            margin-bottom: 25px;
        }

        .page-header h1 {
            margin: 0;
        }

        .page-header p {
            color: #999;
        }

        .filter-card,
        .table-card {
            background: #181818;
            border: 1px solid #292929;
            border-radius: 14px;
            padding: 22px;
            margin-bottom: 20px;
        }

        .filters {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
        }

        .filters label {
            display: block;
            color: #aaa;
            font-size: 13px;
            margin-bottom: 6px;
        }

        .filters input,
        .filters select {
            width: 100%;
            padding: 11px;
            background: #101010;
            border: 1px solid #333;
            color: white;
            border-radius: 7px;
            box-sizing: border-box;
        }

        .filter-actions {
            margin-top: 18px;
            display: flex;
            gap: 10px;
        }

        .filter-btn {
            background: #d4af37;
            color: #111;
            border: none;
            padding: 10px 18px;
            border-radius: 7px;
            cursor: pointer;
            font-weight: 600;
        }

        .clear-btn {
            background: #242424;
            color: #ddd;
            border: 1px solid #444;
            padding: 10px 18px;
            border-radius: 7px;
            text-decoration: none;
        }

        .history-table {
            width: 100%;
            border-collapse: collapse;
        }

        .history-table th {
            color: #999;
            text-align: left;
            font-size: 13px;
            padding: 13px;
            border-bottom: 1px solid #333;
        }

        .history-table td {
            padding: 14px 13px;
            border-bottom: 1px solid #292929;
        }

        .store-badge {
            color: #d4af37;
        }

        .view-btn {
            background: #242424;
            color: #ddd;
            border: 1px solid #444;
            padding: 7px 11px;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }

        .view-btn:hover {
            border-color: #d4af37;
            color: #d4af37;
        }

        .total {
            color: #d4af37;
            font-weight: bold;
        }

        .no-data {
            text-align: center;
            color: #888;
            padding: 35px !important;
        }

        .bill-time {
            color: #777;
            font-size: 12px;
            margin-top: 3px;
        }

        @media(max-width: 800px) {

            .filters {
                grid-template-columns: 1fr 1fr;
            }

            .table-wrapper {
                overflow-x: auto;
            }

            .history-table {
                min-width: 700px;
            }

        }

        @media(max-width: 550px) {

            .page-content {
                padding: 18px;
            }

            .filters {
                grid-template-columns: 1fr;
            }

        }

    </style>

</head>

<body>

<div class="page-content">

    <div class="page-header">

        <h1>Purchase History</h1>

        <p>
            View and manage all grocery purchases.
        </p>

    </div>


    <!-- FILTERS -->

    <div class="filter-card">

        <form method="GET">

            <div class="filters">

                <div>

                    <label>From Date</label>

                    <input
                        type="date"
                        name="from_date"
                        value="<?php echo htmlspecialchars($from_date); ?>"
                    >

                </div>


                <div>

                    <label>To Date</label>

                    <input
                        type="date"
                        name="to_date"
                        value="<?php echo htmlspecialchars($to_date); ?>"
                    >

                </div>


                <div>

                    <label>Store</label>

                    <select name="store_id">

                        <option value="">All Stores</option>

                        <?php foreach ($stores as $store): ?>

                            <option
                                value="<?php echo $store['id']; ?>"
                                <?php echo ($store_id == $store['id']) ? 'selected' : ''; ?>
                            >

                                <?php echo htmlspecialchars($store['store_name']); ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div>

                    <label>Search</label>

                    <input
                        type="text"
                        name="search"
                        placeholder="Bill number..."
                        value="<?php echo htmlspecialchars($search); ?>"
                    >

                </div>

            </div>


            <div class="filter-actions">

                <button type="submit" class="filter-btn">
                    Apply Filters
                </button>

                <a href="purchase-history.php" class="clear-btn">
                    Clear
                </a>

            </div>

        </form>

    </div>


    <!-- PURCHASE TABLE -->

    <div class="table-card">

        <div class="table-wrapper">

            <table class="history-table">

                <thead>

                    <tr>

                        <th>Date</th>

                        <th>Store</th>

                        <th>Bill No.</th>

                        <th>Items</th>

                        <th>Total</th>

                        <th>Action</th>

                    </tr>

                </thead>


                <tbody>

                <?php if ($result->num_rows > 0): ?>

                    <?php while ($purchase = $result->fetch_assoc()): ?>

                        <tr>

                            <!-- DATE -->

                            <td>

                                <?php
                                echo date(
                                    "d M Y",
                                    strtotime($purchase['purchase_date'])
                                );
                                ?>

                                <?php if (!empty($purchase['purchase_time'])): ?>

                                    <div class="bill-time">

                                        <?php
                                        echo date(
                                            "h:i A",
                                            strtotime($purchase['purchase_time'])
                                        );
                                        ?>

                                    </div>

                                <?php endif; ?>

                            </td>


                            <!-- STORE -->

                            <td class="store-badge">

                                <?php
                                echo htmlspecialchars(
                                    $purchase['store_name']
                                );
                                ?>

                            </td>


                            <!-- BILL NUMBER -->

                            <td>

                                <?php
                                echo !empty($purchase['bill_number'])
                                    ? htmlspecialchars($purchase['bill_number'])
                                    : '-';
                                ?>

                            </td>


                            <!-- ITEMS -->

                            <td>

                                <?php
                                echo (int)$purchase['item_count'];
                                ?>

                            </td>


                            <!-- TOTAL -->

                            <td class="total">

                                Rs.
                                <?php
                                echo number_format(
                                    (float)$purchase['total'],
                                    2
                                );
                                ?>

                            </td>


                            <!-- ACTION -->

                            <td>

                                <a
                                    href="view-purchase.php?id=<?php echo $purchase['id']; ?>"
                                    class="view-btn"
                                >
                                    View
                                </a>

                            </td>

                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>

                        <td colspan="6" class="no-data">

                            No purchases found.

                        </td>

                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>

</body>

</html>

<?php

$stmt->close();

?>