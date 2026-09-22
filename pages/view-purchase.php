<?php

require_once "../db.php";

/*
|--------------------------------------------------------------------------
| Get Purchase ID
|--------------------------------------------------------------------------
*/

$purchase_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($purchase_id <= 0) {
    die("Invalid purchase ID.");
}


/*
|--------------------------------------------------------------------------
| Get Purchase Details
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        p.*,
        s.store_name
    FROM purchases p
    INNER JOIN stores s
        ON p.store_id = s.id
    WHERE p.id = ?
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $purchase_id);
$stmt->execute();

$purchase_result = $stmt->get_result();

if ($purchase_result->num_rows === 0) {
    die("Purchase not found.");
}

$purchase = $purchase_result->fetch_assoc();

$stmt->close();


/*
|--------------------------------------------------------------------------
| Get Purchase Items
|--------------------------------------------------------------------------
*/

$item_sql = "
    SELECT
        pi.*,
        c.category_name
    FROM purchase_items pi
    LEFT JOIN categories c
        ON pi.category_id = c.id
    WHERE pi.purchase_id = ?
    ORDER BY pi.id ASC
";

$item_stmt = $conn->prepare($item_sql);
$item_stmt->bind_param("i", $purchase_id);
$item_stmt->execute();

$items = $item_stmt->get_result();


/*
|--------------------------------------------------------------------------
| Get Bill Images
|--------------------------------------------------------------------------
*/

$image_sql = "
    SELECT *
    FROM bill_images
    WHERE purchase_id = ?
    ORDER BY id DESC
";

$image_stmt = $conn->prepare($image_sql);
$image_stmt->bind_param("i", $purchase_id);
$image_stmt->execute();

$images = $image_stmt->get_result();

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
        View Purchase - Grocery Manager
    </title>

    <link
        rel="stylesheet"
        href="../assets/css/style.css"
    >

    <style>

        .page-content {
            padding: 30px;
        }

        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .top-bar h1 {
            margin: 0;
        }

        .back-btn {
            background: #242424;
            color: #ddd;
            border: 1px solid #444;
            padding: 9px 15px;
            border-radius: 7px;
            text-decoration: none;
        }

        .back-btn:hover {
            border-color: #d4af37;
            color: #d4af37;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }

        .info-card {
            background: #181818;
            border: 1px solid #292929;
            border-radius: 14px;
            padding: 20px;
        }

        .info-card .label {
            color: #888;
            font-size: 13px;
            margin-bottom: 7px;
        }

        .info-card .value {
            font-size: 17px;
            font-weight: 600;
        }

        .gold {
            color: #d4af37;
        }

        .card {
            background: #181818;
            border: 1px solid #292929;
            border-radius: 14px;
            padding: 22px;
            margin-bottom: 20px;
        }

        .card h2 {
            margin-top: 0;
            margin-bottom: 18px;
            font-size: 19px;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
        }

        .items-table th {
            text-align: left;
            color: #999;
            font-size: 13px;
            padding: 12px;
            border-bottom: 1px solid #333;
        }

        .items-table td {
            padding: 13px 12px;
            border-bottom: 1px solid #292929;
        }

        .category {
            color: #999;
            font-size: 12px;
        }

        .item-total {
            color: #d4af37;
            font-weight: bold;
        }

        .summary {
            max-width: 400px;
            margin-left: auto;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #292929;
        }

        .summary-row span:first-child {
            color: #999;
        }

        .grand-total {
            font-size: 20px;
            font-weight: bold;
            color: #d4af37;
            border-bottom: none;
            padding-top: 15px;
        }

        .bill-images {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
        }

        .bill-image {
            width: 220px;
            height: 280px;
            object-fit: cover;
            border-radius: 10px;
            border: 1px solid #333;
            cursor: pointer;
        }

        .no-image {
            color: #888;
        }

        .notes {
            color: #bbb;
            line-height: 1.6;
        }

        .no-items {
            text-align: center;
            color: #888;
            padding: 25px;
        }

        @media(max-width: 900px) {

            .info-grid {
                grid-template-columns: 1fr 1fr;
            }

        }

        @media(max-width: 600px) {

            .page-content {
                padding: 18px;
            }

            .top-bar {
                align-items: flex-start;
                gap: 15px;
                flex-direction: column;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }

            .table-wrapper {
                overflow-x: auto;
            }

            .items-table {
                min-width: 650px;
            }

            .bill-image {
                width: 100%;
                height: auto;
            }

        }

    </style>

</head>

<body>

<div class="page-content">


    <!-- HEADER -->

    <div class="top-bar">

        <div>

            <h1>Purchase Details</h1>

            <p style="color:#888;">
                Complete information about this purchase.
            </p>

        </div>

        <a
            href="purchase-history.php"
            class="back-btn"
        >
            ← Back to History
        </a>

    </div>


    <!-- PURCHASE INFO -->

    <div class="info-grid">

        <div class="info-card">

            <div class="label">
                Store
            </div>

            <div class="value gold">

                <?php
                echo htmlspecialchars(
                    $purchase['store_name']
                );
                ?>

            </div>

        </div>


        <div class="info-card">

            <div class="label">
                Bill Number
            </div>

            <div class="value">

                <?php
                echo !empty($purchase['bill_number'])
                    ? htmlspecialchars($purchase['bill_number'])
                    : '-';
                ?>

            </div>

        </div>


        <div class="info-card">

            <div class="label">
                Purchase Date
            </div>

            <div class="value">

                <?php
                echo date(
                    "d M Y",
                    strtotime($purchase['purchase_date'])
                );
                ?>

            </div>

        </div>


        <div class="info-card">

            <div class="label">
                Total
            </div>

            <div class="value gold">

                Rs.
                <?php
                echo number_format(
                    $purchase['total'],
                    2
                );
                ?>

            </div>

        </div>

    </div>


    <!-- ITEMS -->

    <div class="card">

        <h2>
            Purchased Items
        </h2>

        <div class="table-wrapper">

            <table class="items-table">

                <thead>

                    <tr>

                        <th>#</th>

                        <th>Item</th>

                        <th>Category</th>

                        <th>Quantity</th>

                        <th>Unit Price</th>

                        <th>Total</th>

                    </tr>

                </thead>

                <tbody>

                <?php if ($items->num_rows > 0): ?>

                    <?php
                    $counter = 1;
                    ?>

                    <?php while ($item = $items->fetch_assoc()): ?>

                        <tr>

                            <td>
                                <?php echo $counter++; ?>
                            </td>

                            <td>
                                <?php
                                echo htmlspecialchars(
                                    $item['item_name']
                                );
                                ?>
                            </td>

                            <td>

                                <span class="category">

                                    <?php
                                    echo !empty($item['category_name'])
                                        ? htmlspecialchars(
                                            $item['category_name']
                                        )
                                        : '-';
                                    ?>

                                </span>

                            </td>

                            <td>
                                <?php
                                echo number_format(
                                    $item['quantity'],
                                    2
                                );
                                ?>
                            </td>

                            <td>
                                Rs.
                                <?php
                                echo number_format(
                                    $item['unit_price'],
                                    2
                                );
                                ?>
                            </td>

                            <td class="item-total">

                                Rs.
                                <?php
                                echo number_format(
                                    $item['total_price'],
                                    2
                                );
                                ?>

                            </td>

                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>

                        <td
                            colspan="6"
                            class="no-items"
                        >
                            No items found.
                        </td>

                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>


    <!-- SUMMARY -->

    <div class="card">

        <h2>
            Bill Summary
        </h2>

        <div class="summary">

            <div class="summary-row">

                <span>
                    Subtotal
                </span>

                <span>
                    Rs.
                    <?php
                    echo number_format(
                        $purchase['subtotal'],
                        2
                    );
                    ?>
                </span>

            </div>


            <div class="summary-row">

                <span>
                    Discount
                </span>

                <span>
                    Rs.
                    <?php
                    echo number_format(
                        $purchase['discount'],
                        2
                    );
                    ?>
                </span>

            </div>


            <div class="summary-row">

                <span>
                    Tax
                </span>

                <span>
                    Rs.
                    <?php
                    echo number_format(
                        $purchase['tax'],
                        2
                    );
                    ?>
                </span>

            </div>


            <div class="summary-row grand-total">

                <span>
                    Grand Total
                </span>

                <span>
                    Rs.
                    <?php
                    echo number_format(
                        $purchase['total'],
                        2
                    );
                    ?>
                </span>

            </div>

        </div>

    </div>


    <!-- BILL IMAGE -->

    <div class="card">

        <h2>
            Bill Image
        </h2>

        <?php if ($images->num_rows > 0): ?>

            <div class="bill-images">

                <?php while ($image = $images->fetch_assoc()): ?>

                    <a
                        href="<?php echo htmlspecialchars(
                            "../" . $image['file_path']
                        ); ?>"
                        target="_blank"
                    >

                        <img
                            src="<?php echo htmlspecialchars(
                                "../" . $image['file_path']
                            ); ?>"
                            class="bill-image"
                            alt="Bill Image"
                        >

                    </a>

                <?php endwhile; ?>

            </div>

        <?php elseif (!empty($purchase['bill_image'])): ?>

            <div class="bill-images">

                <a
                    href="<?php echo htmlspecialchars(
                        "../" . $purchase['bill_image']
                    ); ?>"
                    target="_blank"
                >

                    <img
                        src="<?php echo htmlspecialchars(
                            "../" . $purchase['bill_image']
                        ); ?>"
                        class="bill-image"
                        alt="Bill Image"
                    >

                </a>

            </div>

        <?php else: ?>

            <p class="no-image">
                No bill image uploaded.
            </p>

        <?php endif; ?>

    </div>


    <!-- NOTES -->

    <?php if (!empty($purchase['notes'])): ?>

        <div class="card">

            <h2>
                Notes
            </h2>

            <div class="notes">

                <?php
                echo nl2br(
                    htmlspecialchars(
                        $purchase['notes']
                    )
                );
                ?>

            </div>

        </div>

    <?php endif; ?>


</div>

</body>

</html>

<?php

$item_stmt->close();
$image_stmt->close();

?>