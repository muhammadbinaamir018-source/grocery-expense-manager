<?php

require_once "db.php";

/*
|--------------------------------------------------------------------------
| DASHBOARD DATA
|--------------------------------------------------------------------------
*/

$today = date('Y-m-d');
$startOfWeek = date('Y-m-d', strtotime('monday this week'));
$startOfMonth = date('Y-m-01');
$nextMonth = date('Y-m-01', strtotime('+1 month'));

$monthName = date('F Y');
$formattedToday = date('l, d F Y');


/*
|--------------------------------------------------------------------------
| TODAY'S EXPENSE
|--------------------------------------------------------------------------
*/

$todayExpense = 0;
$todayPurchases = 0;

$query = "
    SELECT
        COUNT(*) AS purchase_count,
        COALESCE(SUM(total), 0) AS total_expense
    FROM purchases
    WHERE purchase_date = ?
";

$stmt = $conn->prepare($query);
$stmt->bind_param("s", $today);
$stmt->execute();

$result = $stmt->get_result();
$data = $result->fetch_assoc();

$todayExpense = (float)$data['total_expense'];
$todayPurchases = (int)$data['purchase_count'];

$stmt->close();


/*
|--------------------------------------------------------------------------
| THIS WEEK
|--------------------------------------------------------------------------
*/

$weekExpense = 0;
$weekPurchases = 0;

$query = "
    SELECT
        COUNT(*) AS purchase_count,
        COALESCE(SUM(total), 0) AS total_expense
    FROM purchases
    WHERE purchase_date >= ?
      AND purchase_date <= ?
";

$stmt = $conn->prepare($query);
$stmt->bind_param("ss", $startOfWeek, $today);
$stmt->execute();

$result = $stmt->get_result();
$data = $result->fetch_assoc();

$weekExpense = (float)$data['total_expense'];
$weekPurchases = (int)$data['purchase_count'];

$stmt->close();


/*
|--------------------------------------------------------------------------
| THIS MONTH
|--------------------------------------------------------------------------
*/

$monthExpense = 0;
$monthPurchases = 0;

$query = "
    SELECT
        COUNT(*) AS purchase_count,
        COALESCE(SUM(total), 0) AS total_expense
    FROM purchases
    WHERE purchase_date >= ?
      AND purchase_date < ?
";

$stmt = $conn->prepare($query);
$stmt->bind_param("ss", $startOfMonth, $nextMonth);
$stmt->execute();

$result = $stmt->get_result();
$data = $result->fetch_assoc();

$monthExpense = (float)$data['total_expense'];
$monthPurchases = (int)$data['purchase_count'];

$stmt->close();


/*
|--------------------------------------------------------------------------
| AVERAGE PURCHASE
|--------------------------------------------------------------------------
*/

$averagePurchase = 0;

if ($monthPurchases > 0) {
    $averagePurchase = $monthExpense / $monthPurchases;
}


/*
|--------------------------------------------------------------------------
| TOTAL STORES
|--------------------------------------------------------------------------
*/

$totalStores = 0;

$query = "
    SELECT COUNT(*) AS total_stores
    FROM stores
";

$result = mysqli_query($conn, $query);

if ($result) {
    $data = mysqli_fetch_assoc($result);
    $totalStores = (int)$data['total_stores'];
}


/*
|--------------------------------------------------------------------------
| DAILY EXPENSES FOR CURRENT MONTH
|--------------------------------------------------------------------------
*/

$dailyExpenses = [];

$daysInMonth = (int)date('t');

for ($day = 1; $day <= $daysInMonth; $day++) {
    $dailyExpenses[$day] = 0;
}

$query = "
    SELECT
        DAY(purchase_date) AS purchase_day,
        COALESCE(SUM(total), 0) AS daily_total
    FROM purchases
    WHERE purchase_date >= ?
      AND purchase_date < ?
    GROUP BY DAY(purchase_date)
    ORDER BY DAY(purchase_date)
";

$stmt = $conn->prepare($query);
$stmt->bind_param("ss", $startOfMonth, $nextMonth);
$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {

    $day = (int)$row['purchase_day'];

    if (isset($dailyExpenses[$day])) {
        $dailyExpenses[$day] = (float)$row['daily_total'];
    }
}

$stmt->close();


/*
|--------------------------------------------------------------------------
| MAX DAILY EXPENSE
|--------------------------------------------------------------------------
*/

$maxDailyExpense = max($dailyExpenses);

if ($maxDailyExpense <= 0) {
    $maxDailyExpense = 1;
}


/*
|--------------------------------------------------------------------------
| RECENT PURCHASES
|--------------------------------------------------------------------------
*/

$recentPurchases = [];

$query = "
    SELECT
        p.id,
        p.bill_number,
        p.purchase_date,
        p.total,
        s.store_name,
        (
            SELECT c.category_name
            FROM purchase_items pi
            LEFT JOIN categories c
                ON pi.category_id = c.id
            WHERE pi.purchase_id = p.id
            ORDER BY pi.id ASC
            LIMIT 1
        ) AS category_name
    FROM purchases p
    INNER JOIN stores s
        ON p.store_id = s.id
    ORDER BY p.purchase_date DESC, p.id DESC
    LIMIT 5
";

$result = mysqli_query($conn, $query);

if ($result) {

    while ($row = mysqli_fetch_assoc($result)) {
        $recentPurchases[] = $row;
    }
}


/*
|--------------------------------------------------------------------------
| CATEGORY-WISE EXPENSE
|--------------------------------------------------------------------------
*/

$categoryExpenses = [];

$query = "
    SELECT
        COALESCE(c.category_name, 'Other') AS category_name,
        COALESCE(SUM(pi.total_price), 0) AS category_total
    FROM purchase_items pi
    INNER JOIN purchases p
        ON pi.purchase_id = p.id
    LEFT JOIN categories c
        ON pi.category_id = c.id
    WHERE p.purchase_date >= ?
      AND p.purchase_date < ?
    GROUP BY c.id, c.category_name
    ORDER BY category_total DESC
";

$stmt = $conn->prepare($query);
$stmt->bind_param("ss", $startOfMonth, $nextMonth);
$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {

    $categoryExpenses[] = [
        'name' => $row['category_name'],
        'total' => (float)$row['category_total']
    ];
}

$stmt->close();


/*
|--------------------------------------------------------------------------
| STORE-WISE EXPENSE
|--------------------------------------------------------------------------
*/

$storeExpenses = [];

$query = "
    SELECT
        s.store_name,
        COALESCE(SUM(p.total), 0) AS store_total
    FROM purchases p
    INNER JOIN stores s
        ON p.store_id = s.id
    WHERE p.purchase_date >= ?
      AND p.purchase_date < ?
    GROUP BY s.id, s.store_name
    ORDER BY store_total DESC
";

$stmt = $conn->prepare($query);
$stmt->bind_param("ss", $startOfMonth, $nextMonth);
$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {

    $storeExpenses[] = [
        'name' => $row['store_name'],
        'total' => (float)$row['store_total']
    ];
}

$stmt->close();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Grocery Manager</title>

    <link
        rel="stylesheet"
        href="assets/css/style.css"
    >

</head>


<body>

<div class="app">


    <!-- SIDEBAR -->

    <aside class="sidebar">

        <div class="brand">
            Grocery<span>Manager</span>
        </div>


        <nav class="navigation">

            <a href="index.php" class="nav-item active">
                <span>▦</span>
                Dashboard
            </a>


            <a href="pages/add-purchase.php" class="nav-item">
                <span>＋</span>
                Add Purchase
            </a>


            <a href="pages/purchase-history.php" class="nav-item">
                <span>▤</span>
                Purchase History
            </a>


            <a href="pages/reports.php" class="nav-item">
                <span>◫</span>
                Reports
            </a>


            <a href="pages/categories.php" class="nav-item">
                <span>◈</span>
                Categories
            </a>


            <a href="pages/stores.php" class="nav-item">
                <span>⌂</span>
                Stores
            </a>
            <a href="pages/expenses.php" class="nav-item">
    <span>₨</span>
    Expenses
</a>

        </nav>


        <div class="sidebar-bottom">

            <a href="#" class="nav-item">
                <span>⚙</span>
                Settings
            </a>

        </div>

    </aside>



    <!-- MAIN CONTENT -->

    <main class="main">


        <!-- TOP BAR -->

        <header class="topbar">

            <div>

                <p class="eyebrow">
                    Restaurant Expense Management
                </p>


                <h1>
                    Grocery Dashboard
                </h1>


                <p class="date">
                    <?php
                    echo htmlspecialchars($formattedToday);
                    ?>
                </p>

            </div>


            <div class="profile">

                <div class="avatar">
                    MA
                </div>

            </div>

        </header>



        <!-- SUMMARY CARDS -->

        <section class="stats-grid">


            <!-- TODAY -->

            <div class="stat-card">

                <div class="stat-label">
                    Today's Expense
                </div>


                <div class="stat-value">

                    Rs.
                    <?php
                    echo number_format($todayExpense);
                    ?>

                </div>


                <div class="stat-description">

                    <?php
                    echo $todayPurchases;
                    ?>

                    <?php
                    echo ($todayPurchases == 1)
                        ? 'purchase'
                        : 'purchases';
                    ?>

                </div>

            </div>



            <!-- WEEK -->

            <div class="stat-card">

                <div class="stat-label">
                    This Week
                </div>


                <div class="stat-value">

                    Rs.
                    <?php
                    echo number_format($weekExpense);
                    ?>

                </div>


                <div class="stat-description">

                    <?php
                    echo $weekPurchases;
                    ?>

                    <?php
                    echo ($weekPurchases == 1)
                        ? 'purchase'
                        : 'purchases';
                    ?>

                </div>

            </div>



            <!-- MONTH -->

            <div class="stat-card">

                <div class="stat-label">
                    This Month
                </div>


                <div class="stat-value">

                    Rs.
                    <?php
                    echo number_format($monthExpense);
                    ?>

                </div>


                <div class="stat-description">

                    <?php
                    echo $monthPurchases;
                    ?>

                    <?php
                    echo ($monthPurchases == 1)
                        ? 'purchase'
                        : 'purchases';
                    ?>

                </div>

            </div>



            <!-- AVERAGE -->

            <div class="stat-card">

                <div class="stat-label">
                    Average Purchase
                </div>


                <div class="stat-value">

                    Rs.
                    <?php
                    echo number_format($averagePurchase);
                    ?>

                </div>


                <div class="stat-description">
                    Per grocery bill
                </div>

            </div>

        </section>



        <!-- CONTENT GRID -->

        <section class="content-grid">


            <!-- LEFT -->

            <div>


                <!-- CHART -->

                <div class="panel">

                    <div class="panel-header">

                        <div>

                            <h2>
                                Monthly Grocery Expense
                            </h2>


                            <p>
                                <?php
                                echo htmlspecialchars($monthName);
                                ?>
                            </p>

                        </div>


                        <span class="amount-badge">

                            Rs.
                            <?php
                            echo number_format($monthExpense);
                            ?>

                        </span>

                    </div>



                    <div class="chart">

                        <?php

                        for (
                            $day = 1;
                            $day <= $daysInMonth;
                            $day++
                        ) {

                            $expense = $dailyExpenses[$day];

                            $height =
                                ($expense / $maxDailyExpense) * 100;


                            if (
                                $expense > 0 &&
                                $height < 8
                            ) {
                                $height = 8;
                            }


                            $highlight =
                                ($day == (int)date('j'));

                        ?>

                            <div
                                class="bar <?php
                                echo $highlight
                                    ? 'highlight'
                                    : '';
                                ?>"
                                style="height:
                                <?php
                                echo $height;
                                ?>%;"
                                title="<?php
                                echo $day .
                                    ' ' .
                                    $monthName .
                                    ' - Rs. ' .
                                    number_format($expense);
                                ?>"
                            ></div>

                        <?php

                        }

                        ?>

                    </div>



                    <div class="chart-labels">

                        <span>1</span>
                        <span>5</span>
                        <span>10</span>
                        <span>15</span>
                        <span>20</span>
                        <span>25</span>
                        <span>
                            <?php echo $daysInMonth; ?>
                        </span>

                    </div>

                </div>



                <!-- RECENT PURCHASES -->

                <div class="panel recent-panel">

                    <div class="panel-header">

                        <div>

                            <h2>
                                Recent Purchases
                            </h2>


                            <p>
                                Latest grocery expenses
                            </p>

                        </div>


                        <a
                            href="pages/purchase-history.php"
                            class="view-link"
                        >
                            View All
                        </a>

                    </div>



                    <div class="table-wrapper">

                        <table>

                            <thead>

                                <tr>

                                    <th>
                                        Store / Bill
                                    </th>

                                    <th>
                                        Date
                                    </th>

                                    <th>
                                        Category
                                    </th>

                                    <th>
                                        Amount
                                    </th>

                                </tr>

                            </thead>


                            <tbody>

                            <?php if (count($recentPurchases) > 0): ?>

                                <?php foreach (
                                    $recentPurchases
                                    as $purchase
                                ): ?>

                                    <tr>

                                        <td>

                                            <strong>

                                                <?php
                                                echo htmlspecialchars(
                                                    $purchase['store_name']
                                                );
                                                ?>

                                            </strong>


                                            <small>

                                                #

                                                <?php

                                                echo !empty(
                                                    $purchase['bill_number']
                                                )
                                                    ? htmlspecialchars(
                                                        $purchase['bill_number']
                                                    )
                                                    : $purchase['id'];

                                                ?>

                                            </small>

                                        </td>


                                        <td>

                                            <?php

                                            echo date(
                                                'd M Y',
                                                strtotime(
                                                    $purchase['purchase_date']
                                                )
                                            );

                                            ?>

                                        </td>


                                        <td>

                                            <span class="tag">

                                                <?php

                                                echo htmlspecialchars(
                                                    $purchase['category_name']
                                                    ?? 'Other'
                                                );

                                                ?>

                                            </span>

                                        </td>


                                        <td>

                                            <strong>

                                                Rs.

                                                <?php

                                                echo number_format(
                                                    $purchase['total']
                                                );

                                                ?>

                                            </strong>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            <?php else: ?>

                                <tr>

                                    <td
                                        colspan="4"
                                        style="text-align:center;"
                                    >

                                        No purchases found.

                                    </td>

                                </tr>

                            <?php endif; ?>

                            </tbody>

                        </table>

                    </div>

                </div>

            </div>



            <!-- RIGHT -->

            <aside class="right-column">


                <!-- QUICK ACTIONS -->

                <div class="panel quick-panel">

                    <h2>
                        Quick Actions
                    </h2>


                    <p>
                        Manage restaurant purchases
                    </p>


                    <a
                        href="pages/add-purchase.php"
                        class="quick-action primary"
                    >

                        <div class="action-icon">
                            ＋
                        </div>


                        <div>

                            <strong>
                                Add Grocery Bill
                            </strong>


                            <small>
                                Upload bill and scan with OCR
                            </small>

                        </div>

                    </a>


                    <a
                        href="pages/purchase-history.php"
                        class="quick-action"
                    >

                        <div class="action-icon">
                            ▤
                        </div>


                        <div>

                            <strong>
                                Purchase History
                            </strong>


                            <small>
                                Search previous bills
                            </small>

                        </div>

                    </a>


                    <a
                        href="pages/reports.php"
                        class="quick-action"
                    >

                        <div class="action-icon">
                            ◫
                        </div>


                        <div>

                            <strong>
                                Monthly Report
                            </strong>


                            <small>
                                View expense breakdown
                            </small>

                        </div>

                    </a>
<a
    href="pages/expenses.php"
    class="quick-action"
>

    <div class="action-icon">
        ₨
    </div>

    <div>

        <strong>
            Manage Expenses
        </strong>

        <small>
            Add and manage other expenses
        </small>

    </div>

</a>
                </div>



                <!-- EXPENSE OVERVIEW -->

                <div class="panel expense-panel">

                    <h2>
                        Expense Overview
                    </h2>


                    <?php if (count($categoryExpenses) > 0): ?>

                        <?php foreach (
                            $categoryExpenses
                            as $category
                        ): ?>

                            <div class="expense-row">

                                <span>

                                    <?php
                                    echo htmlspecialchars(
                                        $category['name']
                                    );
                                    ?>

                                </span>


                                <strong>

                                    Rs.

                                    <?php

                                    echo number_format(
                                        $category['total']
                                    );

                                    ?>

                                </strong>

                            </div>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <div class="expense-row">

                            <span>
                                No expenses
                            </span>

                            <strong>
                                Rs. 0
                            </strong>

                        </div>

                    <?php endif; ?>

                </div>


                <!-- STORE OVERVIEW -->

                <div class="panel expense-panel">

                    <h2>
                        Store Overview
                    </h2>


                    <?php if (count($storeExpenses) > 0): ?>

                        <?php foreach (
                            $storeExpenses
                            as $store
                        ): ?>

                            <div class="expense-row">

                                <span>

                                    <?php
                                    echo htmlspecialchars(
                                        $store['name']
                                    );
                                    ?>

                                </span>


                                <strong>

                                    Rs.

                                    <?php

                                    echo number_format(
                                        $store['total']
                                    );

                                    ?>

                                </strong>

                            </div>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <div class="expense-row">

                            <span>
                                No purchases
                            </span>

                            <strong>
                                Rs. 0
                            </strong>

                        </div>

                    <?php endif; ?>

                </div>

            </aside>

        </section>

    </main>

</div>


<script src="assets/js/app.js"></script>

</body>

</html>