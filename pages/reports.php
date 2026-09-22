<?php

require_once "../db.php";


/*
|--------------------------------------------------------------------------
| DATE FILTER
|--------------------------------------------------------------------------
*/

$filter = $_GET['filter'] ?? 'month';

$today = date('Y-m-d');

$fromDate = date('Y-m-01');
$toDate = $today;


/*
|--------------------------------------------------------------------------
| FILTER LOGIC
|--------------------------------------------------------------------------
*/

if ($filter === 'week') {

    $fromDate = date(
        'Y-m-d',
        strtotime('monday this week')
    );

    $toDate = $today;

}


elseif ($filter === 'last_month') {

    $fromDate = date(
        'Y-m-01',
        strtotime('first day of last month')
    );

    $toDate = date(
        'Y-m-t',
        strtotime('last month')
    );

}


elseif ($filter === 'custom') {

    $fromDate = $_GET['from_date'] ?? $today;
    $toDate = $_GET['to_date'] ?? $today;

}


/*
|--------------------------------------------------------------------------
| SAFETY CHECK
|--------------------------------------------------------------------------
*/

if ($fromDate > $toDate) {

    $temp = $fromDate;
    $fromDate = $toDate;
    $toDate = $temp;

}


/*
|--------------------------------------------------------------------------
| TOTAL PURCHASE EXPENSE
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        COALESCE(SUM(total), 0) AS total_expense,
        COUNT(*) AS total_bills,
        COALESCE(AVG(total), 0) AS average_bill
    FROM purchases
    WHERE purchase_date BETWEEN ? AND ?
");

$stmt->bind_param(
    "ss",
    $fromDate,
    $toDate
);

$stmt->execute();

$summaryResult = $stmt->get_result();

$summary = $summaryResult->fetch_assoc();

$stmt->close();


$totalExpense = (float)$summary['total_expense'];
$totalBills = (int)$summary['total_bills'];
$averageBill = (float)$summary['average_bill'];


/*
|--------------------------------------------------------------------------
| CATEGORY REPORT
|--------------------------------------------------------------------------
*/

$categoryReports = [];

$stmt = $conn->prepare("
    SELECT

        c.category_name,

        COUNT(pi.id) AS total_items,

        COALESCE(
            SUM(pi.total_price),
            0
        ) AS total_expense

    FROM categories c

    LEFT JOIN purchase_items pi
        ON c.id = pi.category_id

    LEFT JOIN purchases p
        ON pi.purchase_id = p.id
        AND p.purchase_date BETWEEN ? AND ?

    GROUP BY
        c.id,
        c.category_name

    ORDER BY total_expense DESC
");

$stmt->bind_param(
    "ss",
    $fromDate,
    $toDate
);

$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {

    $categoryReports[] = $row;

}

$stmt->close();


/*
|--------------------------------------------------------------------------
| STORE REPORT
|--------------------------------------------------------------------------
*/

$storeReports = [];

$stmt = $conn->prepare("
    SELECT

        s.store_name,

        COUNT(p.id) AS total_bills,

        COALESCE(
            SUM(p.total),
            0
        ) AS total_expense

    FROM stores s

    LEFT JOIN purchases p
        ON s.id = p.store_id
        AND p.purchase_date BETWEEN ? AND ?

    GROUP BY
        s.id,
        s.store_name

    ORDER BY total_expense DESC
");

$stmt->bind_param(
    "ss",
    $fromDate,
    $toDate
);

$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {

    $storeReports[] = $row;

}

$stmt->close();


/*
|--------------------------------------------------------------------------
| DAILY REPORT
|--------------------------------------------------------------------------
*/

$dailyReports = [];

$stmt = $conn->prepare("
    SELECT

        purchase_date,

        COUNT(*) AS total_bills,

        COALESCE(
            SUM(total),
            0
        ) AS total_expense

    FROM purchases

    WHERE purchase_date BETWEEN ? AND ?

    GROUP BY purchase_date

    ORDER BY purchase_date ASC
");

$stmt->bind_param(
    "ss",
    $fromDate,
    $toDate
);

$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {

    $dailyReports[] = $row;

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

    <title>
        Reports - Grocery Manager
    </title>

    <link
        rel="stylesheet"
        href="../assets/css/style.css"
    >


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


        /*
        |--------------------------------------------------------------------------
        | FILTERS
        |--------------------------------------------------------------------------
        */

        .report-filters {

            background: #181818;

            border: 1px solid #292929;

            border-radius: 14px;

            padding: 20px;

            display: flex;

            gap: 15px;

            margin-bottom: 22px;

            align-items: end;

            flex-wrap: wrap;

        }


        .filter-group {

            display: flex;

            flex-direction: column;

            gap: 6px;

        }


        .filter-group label {

            color: #999;

            font-size: 12px;

        }


        .report-filters select,
        .report-filters input {

            padding: 11px;

            background: #101010;

            color: white;

            border: 1px solid #333;

            border-radius: 7px;

        }


        .filter-btn {

            padding: 11px 18px;

            background: #d4af37;

            color: #111;

            border: none;

            border-radius: 7px;

            font-weight: bold;

            cursor: pointer;

        }


        /*
        |--------------------------------------------------------------------------
        | SUMMARY
        |--------------------------------------------------------------------------
        */

        .report-grid {

            display: grid;

            grid-template-columns:
                repeat(3, 1fr);

            gap: 18px;

        }


        .report-card {

            background: #181818;

            border: 1px solid #292929;

            border-radius: 14px;

            padding: 22px;

        }


        .report-card span {

            color: #999;

            font-size: 14px;

        }


        .report-card h2 {

            color: #d4af37;

            margin-top: 10px;

        }


        /*
        |--------------------------------------------------------------------------
        | TABLE BOX
        |--------------------------------------------------------------------------
        */

        .report-box {

            margin-top: 22px;

            background: #181818;

            border: 1px solid #292929;

            border-radius: 14px;

            padding: 22px;

            overflow-x: auto;

        }


        .report-box h3 {

            margin-top: 0;

        }


        table {

            width: 100%;

            border-collapse: collapse;

        }


        th {

            text-align: left;

            color: #999;

            font-size: 13px;

            padding: 12px;

            border-bottom: 1px solid #333;

        }


        td {

            padding: 13px 12px;

            border-bottom: 1px solid #292929;

        }


        .amount {

            color: #d4af37;

            font-weight: bold;

        }


        .empty {

            color: #777;

            text-align: center;

            padding: 25px;

        }


        /*
        |--------------------------------------------------------------------------
        | STORE GRID
        |--------------------------------------------------------------------------
        */

        .store-report-grid {

            display: grid;

            grid-template-columns:
                repeat(3, 1fr);

            gap: 15px;

        }


        .store-report-card {

            background: #111;

            border: 1px solid #292929;

            border-radius: 10px;

            padding: 18px;

        }


        .store-report-card h4 {

            margin-top: 0;

            margin-bottom: 10px;

        }


        .store-report-card p {

            color: #888;

            margin: 5px 0;

        }


        /*
        |--------------------------------------------------------------------------
        | RESPONSIVE
        |--------------------------------------------------------------------------
        */

        @media(max-width: 800px) {

            .report-grid {

                grid-template-columns: 1fr;

            }


            .store-report-grid {

                grid-template-columns: 1fr 1fr;

            }


            .report-filters {

                flex-direction: column;

                align-items: stretch;

            }

        }


        @media(max-width: 500px) {

            .page-content {

                padding: 18px;

            }


            .store-report-grid {

                grid-template-columns: 1fr;

            }

        }

    </style>

</head>


<body>


<div class="page-content">


    <!-- HEADER -->

    <div class="page-header">

        <h1>
            Expense Reports
        </h1>

        <p>
            Analyze restaurant grocery expenses.
        </p>

    </div>


    <!-- FILTER -->

    <form
        method="GET"
        class="report-filters"
    >


        <div class="filter-group">

            <label>
                Period
            </label>

            <select
                name="filter"
                id="filter"
                onchange="toggleCustomDates()"
            >

                <option
                    value="month"
                    <?php
                    echo $filter === 'month'
                        ? 'selected'
                        : '';
                    ?>
                >
                    This Month
                </option>


                <option
                    value="week"
                    <?php
                    echo $filter === 'week'
                        ? 'selected'
                        : '';
                    ?>
                >
                    This Week
                </option>


                <option
                    value="last_month"
                    <?php
                    echo $filter === 'last_month'
                        ? 'selected'
                        : '';
                    ?>
                >
                    Last Month
                </option>


                <option
                    value="custom"
                    <?php
                    echo $filter === 'custom'
                        ? 'selected'
                        : '';
                    ?>
                >
                    Custom
                </option>

            </select>

        </div>


        <div
            class="filter-group"
            id="fromGroup"
        >

            <label>
                From
            </label>

            <input
                type="date"
                name="from_date"
                value="<?php
                echo htmlspecialchars($fromDate);
                ?>"
            >

        </div>


        <div
            class="filter-group"
            id="toGroup"
        >

            <label>
                To
            </label>

            <input
                type="date"
                name="to_date"
                value="<?php
                echo htmlspecialchars($toDate);
                ?>"
            >

        </div>


        <button
            type="submit"
            class="filter-btn"
        >
            Apply
        </button>


    </form>


    <!-- SUMMARY -->

    <div class="report-grid">


        <div class="report-card">

            <span>
                Total Purchases
            </span>

            <h2>

                Rs.
                <?php

                echo number_format(
                    $totalExpense,
                    2
                );

                ?>

            </h2>

        </div>


        <div class="report-card">

            <span>
                Total Bills
            </span>

            <h2>

                <?php

                echo number_format(
                    $totalBills
                );

                ?>

            </h2>

        </div>


        <div class="report-card">

            <span>
                Average Bill
            </span>

            <h2>

                Rs.
                <?php

                echo number_format(
                    $averageBill,
                    2
                );

                ?>

            </h2>

        </div>


    </div>


    <!-- CATEGORY REPORT -->

    <div class="report-box">

        <h3>
            Expense By Category
        </h3>


        <table>

            <thead>

                <tr>

                    <th>
                        Category
                    </th>

                    <th>
                        Total Items
                    </th>

                    <th>
                        Total Expense
                    </th>

                </tr>

            </thead>


            <tbody>


                <?php

                $hasCategoryData = false;

                ?>


                <?php foreach (
                    $categoryReports
                    as $category
                ): ?>


                    <?php

                    if (
                        (int)$category['total_items']
                        > 0
                    ) {

                        $hasCategoryData = true;

                    }

                    ?>


                    <?php if (
                        (int)$category['total_items']
                        > 0
                    ): ?>

                        <tr>

                            <td>

                                <?php

                                echo htmlspecialchars(
                                    $category['category_name']
                                );

                                ?>

                            </td>


                            <td>

                                <?php

                                echo number_format(
                                    $category['total_items']
                                );

                                ?>

                            </td>


                            <td class="amount">

                                Rs.

                                <?php

                                echo number_format(
                                    (float)
                                    $category['total_expense'],
                                    2
                                );

                                ?>

                            </td>

                        </tr>

                    <?php endif; ?>


                <?php endforeach; ?>


                <?php if (!$hasCategoryData): ?>

                    <tr>

                        <td
                            colspan="3"
                            class="empty"
                        >

                            No category data for
                            this period.

                        </td>

                    </tr>

                <?php endif; ?>


            </tbody>

        </table>

    </div>


    <!-- STORE REPORT -->

    <div class="report-box">

        <h3>
            Expense By Store
        </h3>


        <div class="store-report-grid">


            <?php

            $hasStoreData = false;

            ?>


            <?php foreach (
                $storeReports
                as $store
            ): ?>


                <?php

                if (
                    (int)$store['total_bills']
                    > 0
                ) {

                    $hasStoreData = true;

                }

                ?>


                <?php if (
                    (int)$store['total_bills']
                    > 0
                ): ?>


                    <div
                        class="store-report-card"
                    >

                        <h4>

                            🛒

                            <?php

                            echo htmlspecialchars(
                                $store['store_name']
                            );

                            ?>

                        </h4>


                        <p>

                            Bills:

                            <?php

                            echo number_format(
                                $store['total_bills']
                            );

                            ?>

                        </p>


                        <p class="amount">

                            Rs.

                            <?php

                            echo number_format(
                                (float)
                                $store['total_expense'],
                                2
                            );

                            ?>

                        </p>

                    </div>


                <?php endif; ?>


            <?php endforeach; ?>


            <?php if (!$hasStoreData): ?>


                <p class="empty">

                    No store data for
                    this period.

                </p>


            <?php endif; ?>


        </div>

    </div>


    <!-- DAILY REPORT -->

    <div class="report-box">

        <h3>
            Daily Expense
        </h3>


        <table>

            <thead>

                <tr>

                    <th>
                        Date
                    </th>

                    <th>
                        Bills
                    </th>

                    <th>
                        Expense
                    </th>

                </tr>

            </thead>


            <tbody>


                <?php if (
                    count($dailyReports) > 0
                ): ?>


                    <?php foreach (
                        $dailyReports
                        as $day
                    ): ?>


                        <tr>

                            <td>

                                <?php

                                echo date(
                                    'd M Y',
                                    strtotime(
                                        $day['purchase_date']
                                    )
                                );

                                ?>

                            </td>


                            <td>

                                <?php

                                echo number_format(
                                    $day['total_bills']
                                );

                                ?>

                            </td>


                            <td class="amount">

                                Rs.

                                <?php

                                echo number_format(
                                    (float)
                                    $day['total_expense'],
                                    2
                                );

                                ?>

                            </td>

                        </tr>


                    <?php endforeach; ?>


                <?php else: ?>


                    <tr>

                        <td
                            colspan="3"
                            class="empty"
                        >

                            No purchases found
                            for this period.

                        </td>

                    </tr>


                <?php endif; ?>


            </tbody>

        </table>

    </div>


</div>


<script>

function toggleCustomDates() {

    const filter =
        document.getElementById(
            "filter"
        ).value;


    const fromGroup =
        document.getElementById(
            "fromGroup"
        );

    const toGroup =
        document.getElementById(
            "toGroup"
        );


    if (filter === "custom") {

        fromGroup.style.display =
            "flex";

        toGroup.style.display =
            "flex";

    } else {

        fromGroup.style.display =
            "none";

        toGroup.style.display =
            "none";

    }

}


/*
|--------------------------------------------------------------------------
| Initial state
|--------------------------------------------------------------------------
*/

toggleCustomDates();

</script>


</body>

</html>