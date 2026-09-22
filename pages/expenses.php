<?php
require_once "../db.php";

$message = "";
$error = "";

/* =========================
   DELETE EXPENSE
========================= */
if (isset($_GET['delete'])) {

    $delete_id = (int) $_GET['delete'];

    // Get receipt image first
    $stmt = $conn->prepare("SELECT receipt_image FROM expenses WHERE id = ?");
    $stmt->bind_param("i", $delete_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $expense = $result->fetch_assoc();
    $stmt->close();

    if ($expense) {

        // Delete database record
        $stmt = $conn->prepare("DELETE FROM expenses WHERE id = ?");
        $stmt->bind_param("i", $delete_id);

        if ($stmt->execute()) {

            // Delete image if exists
            if (!empty($expense['receipt_image'])) {
                $image_path = "../uploads/expenses/" . $expense['receipt_image'];

                if (file_exists($image_path)) {
                    unlink($image_path);
                }
            }

            $message = "Expense deleted successfully.";
        } else {
            $error = "Failed to delete expense.";
        }

        $stmt->close();
    }
}


/* =========================
   ADD / UPDATE EXPENSE
========================= */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $expense_id = isset($_POST['expense_id']) ? (int) $_POST['expense_id'] : 0;

    $expense_title = trim($_POST['expense_title'] ?? "");
    $expense_category = trim($_POST['expense_category'] ?? "");
    $amount = (float) ($_POST['amount'] ?? 0);
    $expense_date = $_POST['expense_date'] ?? "";
    $payment_method = $_POST['payment_method'] ?? "Cash";
    $description = trim($_POST['description'] ?? "");

    if (
        empty($expense_title) ||
        empty($expense_category) ||
        $amount <= 0 ||
        empty($expense_date)
    ) {
        $error = "Please fill all required fields correctly.";
    } else {

        /* =========================
           IMAGE UPLOAD
        ========================= */

        $receipt_image = "";

        if (
            isset($_FILES['receipt_image']) &&
            $_FILES['receipt_image']['error'] === UPLOAD_ERR_OK
        ) {

            $upload_dir = "../uploads/expenses/";

            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $allowed_types = [
                "image/jpeg",
                "image/png",
                "image/webp"
            ];

            $file_type = $_FILES['receipt_image']['type'];
            $file_size = $_FILES['receipt_image']['size'];

            if (!in_array($file_type, $allowed_types)) {

                $error = "Only JPG, PNG and WEBP images are allowed.";

            } elseif ($file_size > 5 * 1024 * 1024) {

                $error = "Image size must be less than 5MB.";

            } else {

                $extension = pathinfo(
                    $_FILES['receipt_image']['name'],
                    PATHINFO_EXTENSION
                );

                $receipt_image =
                    "expense_" . time() . "_" . uniqid() . "." . $extension;

                $destination = $upload_dir . $receipt_image;

                if (!move_uploaded_file(
                    $_FILES['receipt_image']['tmp_name'],
                    $destination
                )) {
                    $error = "Failed to upload receipt image.";
                    $receipt_image = "";
                }
            }
        }


        /* =========================
           INSERT / UPDATE
        ========================= */

        if (empty($error)) {

            if ($expense_id > 0) {

                // Get old image
                $old_image = "";

                $stmt = $conn->prepare(
                    "SELECT receipt_image FROM expenses WHERE id = ?"
                );

                $stmt->bind_param("i", $expense_id);
                $stmt->execute();

                $result = $stmt->get_result();
                $old_expense = $result->fetch_assoc();

                if ($old_expense) {
                    $old_image = $old_expense['receipt_image'];
                }

                $stmt->close();


                // If new image uploaded
                if (!empty($receipt_image)) {

                    $stmt = $conn->prepare("
                        UPDATE expenses
                        SET
                            expense_title = ?,
                            expense_category = ?,
                            amount = ?,
                            expense_date = ?,
                            payment_method = ?,
                            description = ?,
                            receipt_image = ?
                        WHERE id = ?
                    ");

                    $stmt->bind_param(
                        "ssdssssi",
                        $expense_title,
                        $expense_category,
                        $amount,
                        $expense_date,
                        $payment_method,
                        $description,
                        $receipt_image,
                        $expense_id
                    );

                    if ($stmt->execute()) {

                        // Delete old image
                        if (!empty($old_image)) {

                            $old_path = "../uploads/expenses/" . $old_image;

                            if (file_exists($old_path)) {
                                unlink($old_path);
                            }
                        }

                        $message = "Expense updated successfully.";

                    } else {
                        $error = "Failed to update expense.";
                    }

                    $stmt->close();

                } else {

                    // Update without changing image
                    $stmt = $conn->prepare("
                        UPDATE expenses
                        SET
                            expense_title = ?,
                            expense_category = ?,
                            amount = ?,
                            expense_date = ?,
                            payment_method = ?,
                            description = ?
                        WHERE id = ?
                    ");

                    $stmt->bind_param(
                        "ssdsssi",
                        $expense_title,
                        $expense_category,
                        $amount,
                        $expense_date,
                        $payment_method,
                        $description,
                        $expense_id
                    );

                    if ($stmt->execute()) {
                        $message = "Expense updated successfully.";
                    } else {
                        $error = "Failed to update expense.";
                    }

                    $stmt->close();
                }

            } else {

                // ADD NEW EXPENSE
                $stmt = $conn->prepare("
                    INSERT INTO expenses
                    (
                        expense_title,
                        expense_category,
                        amount,
                        expense_date,
                        payment_method,
                        description,
                        receipt_image
                    )
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");

                $stmt->bind_param(
                    "ssdssss",
                    $expense_title,
                    $expense_category,
                    $amount,
                    $expense_date,
                    $payment_method,
                    $description,
                    $receipt_image
                );

                if ($stmt->execute()) {
                    $message = "Expense added successfully.";
                } else {
                    $error = "Failed to add expense.";
                }

                $stmt->close();
            }
        }
    }
}


/* =========================
   EDIT EXPENSE DATA
========================= */

$edit_expense = null;

if (isset($_GET['edit'])) {

    $edit_id = (int) $_GET['edit'];

    $stmt = $conn->prepare("
        SELECT *
        FROM expenses
        WHERE id = ?
    ");

    $stmt->bind_param("i", $edit_id);
    $stmt->execute();

    $result = $stmt->get_result();
    $edit_expense = $result->fetch_assoc();

    $stmt->close();
}


/* =========================
   GET EXPENSES
========================= */

$expenses = [];

$result = $conn->query("
    SELECT *
    FROM expenses
    ORDER BY expense_date DESC, id DESC
");

if ($result) {

    while ($row = $result->fetch_assoc()) {
        $expenses[] = $row;
    }
}


/* =========================
   TOTAL EXPENSES
========================= */

$total_expenses = 0;

$result = $conn->query("
    SELECT COALESCE(SUM(amount), 0) AS total
    FROM expenses
");

if ($result) {

    $row = $result->fetch_assoc();
    $total_expenses = (float) $row['total'];
}


/* =========================
   THIS MONTH EXPENSE
========================= */

$month_expense = 0;

$result = $conn->query("
    SELECT COALESCE(SUM(amount), 0) AS total
    FROM expenses
    WHERE MONTH(expense_date) = MONTH(CURDATE())
    AND YEAR(expense_date) = YEAR(CURDATE())
");

if ($result) {

    $row = $result->fetch_assoc();
    $month_expense = (float) $row['total'];
}


/* =========================
   TODAY EXPENSE
========================= */

$today_expense = 0;

$result = $conn->query("
    SELECT COALESCE(SUM(amount), 0) AS total
    FROM expenses
    WHERE expense_date = CURDATE()
");

if ($result) {

    $row = $result->fetch_assoc();
    $today_expense = (float) $row['total'];
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Expenses - Grocery Manager</title>

    <style>

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: Arial, sans-serif;
            background: #151515;
            color: #ffffff;
        }

        .container {
            max-width: 1250px;
            margin: auto;
            padding: 30px;
        }

        h1 {
            margin-bottom: 8px;
        }

        .subtitle {
            color: #999;
            margin-bottom: 25px;
        }

        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            gap: 15px;
        }

        .btn {
            border: none;
            background: #d4af37;
            color: #111;
            padding: 12px 20px;
            border-radius: 8px;
            font-weight: bold;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }

        .btn:hover {
            background: #f0cc55;
        }

        .btn-danger {
            background: #b83232;
            color: white;
        }

        .btn-danger:hover {
            background: #d64242;
        }

        .btn-edit {
            background: #444;
            color: white;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 18px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: #202020;
            border: 1px solid #333;
            border-radius: 12px;
            padding: 22px;
        }

        .stat-card span {
            color: #aaa;
            font-size: 14px;
        }

        .stat-card h2 {
            color: #d4af37;
            margin-top: 10px;
        }

        .form-box,
        .table-box {
            background: #202020;
            border: 1px solid #333;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 30px;
        }

        .form-box h2,
        .table-box h2 {
            margin-bottom: 20px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 18px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group.full {
            grid-column: 1 / -1;
        }

        label {
            margin-bottom: 7px;
            color: #ccc;
            font-size: 14px;
        }

        input,
        select,
        textarea {
            background: #111;
            border: 1px solid #444;
            color: white;
            padding: 12px;
            border-radius: 7px;
            outline: none;
        }

        input:focus,
        select:focus,
        textarea:focus {
            border-color: #d4af37;
        }

        textarea {
            resize: vertical;
            min-height: 90px;
        }

        .form-actions {
            margin-top: 20px;
            display: flex;
            gap: 10px;
        }

        .message {
            background: #173d25;
            border: 1px solid #286b3e;
            color: #7ee69a;
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .error {
            background: #421b1b;
            border: 1px solid #803333;
            color: #ff8c8c;
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 800px;
        }

        th,
        td {
            padding: 14px 12px;
            text-align: left;
            border-bottom: 1px solid #333;
        }

        th {
            color: #d4af37;
            font-size: 14px;
        }

        td {
            color: #ddd;
        }

        .amount {
            color: #d4af37;
            font-weight: bold;
        }

        .actions {
            display: flex;
            gap: 8px;
        }

        .actions a {
            padding: 7px 11px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 13px;
        }

        .edit {
            background: #444;
            color: white;
        }

        .delete {
            background: #7d2525;
            color: white;
        }

        .receipt {
            color: #d4af37;
            text-decoration: none;
        }

        .receipt:hover {
            text-decoration: underline;
        }

        @media (max-width: 800px) {

            .container {
                padding: 18px;
            }

            .stats {
                grid-template-columns: 1fr;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .form-group.full {
                grid-column: auto;
            }

            .top-bar {
                flex-direction: column;
                align-items: flex-start;
            }
        }

    </style>

</head>

<body>

<div class="container">

    <div class="top-bar">

        <div>
            <h1>Expenses</h1>
            <p class="subtitle">
                Manage restaurant expenses and other business costs.
            </p>
        </div>

        <a href="../index.php" class="btn">
            ← Dashboard
        </a>

    </div>


    <?php if (!empty($message)): ?>

        <div class="message">
            <?= htmlspecialchars($message) ?>
        </div>

    <?php endif; ?>


    <?php if (!empty($error)): ?>

        <div class="error">
            <?= htmlspecialchars($error) ?>
        </div>

    <?php endif; ?>


    <!-- =========================
         STAT CARDS
    ========================= -->

    <div class="stats">

        <div class="stat-card">

            <span>Total Expenses</span>

            <h2>
                Rs. <?= number_format($total_expenses, 2) ?>
            </h2>

        </div>


        <div class="stat-card">

            <span>This Month</span>

            <h2>
                Rs. <?= number_format($month_expense, 2) ?>
            </h2>

        </div>


        <div class="stat-card">

            <span>Today</span>

            <h2>
                Rs. <?= number_format($today_expense, 2) ?>
            </h2>

        </div>

    </div>


    <!-- =========================
         ADD / EDIT FORM
    ========================= -->

    <div class="form-box">

        <h2>
            <?= $edit_expense ? "Edit Expense" : "Add New Expense" ?>
        </h2>

        <form method="POST"
              enctype="multipart/form-data">

            <?php if ($edit_expense): ?>

                <input
                    type="hidden"
                    name="expense_id"
                    value="<?= $edit_expense['id'] ?>"
                >

            <?php endif; ?>


            <div class="form-grid">


                <div class="form-group">

                    <label>Expense Title *</label>

                    <input
                        type="text"
                        name="expense_title"
                        placeholder="e.g. Electricity Bill"
                        required
                        value="<?= htmlspecialchars($edit_expense['expense_title'] ?? '') ?>"
                    >

                </div>


                <div class="form-group">

                    <label>Expense Category *</label>

                    <select
                        name="expense_category"
                        required
                    >

                        <?php

                        $categories = [
                            "Electricity",
                            "Rent",
                            "Salaries",
                            "Transport",
                            "Maintenance",
                            "Internet",
                            "Gas",
                            "Water",
                            "Other"
                        ];

                        ?>

                        <option value="">Select Category</option>

                        <?php foreach ($categories as $category): ?>

                            <option
                                value="<?= htmlspecialchars($category) ?>"
                                <?= (
                                    ($edit_expense['expense_category'] ?? '') === $category
                                ) ? 'selected' : '' ?>
                            >

                                <?= htmlspecialchars($category) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div class="form-group">

                    <label>Amount *</label>

                    <input
                        type="number"
                        name="amount"
                        step="0.01"
                        min="0"
                        placeholder="0.00"
                        required
                        value="<?= htmlspecialchars($edit_expense['amount'] ?? '') ?>"
                    >

                </div>


                <div class="form-group">

                    <label>Expense Date *</label>

                    <input
                        type="date"
                        name="expense_date"
                        required
                        value="<?= htmlspecialchars(
                            $edit_expense['expense_date'] ?? date('Y-m-d')
                        ) ?>"
                    >

                </div>


                <div class="form-group">

                    <label>Payment Method</label>

                    <select name="payment_method">

                        <?php

                        $methods = [
                            "Cash",
                            "Card",
                            "Bank",
                            "Other"
                        ];

                        ?>

                        <?php foreach ($methods as $method): ?>

                            <option
                                value="<?= $method ?>"
                                <?= (
                                    ($edit_expense['payment_method'] ?? 'Cash') === $method
                                ) ? 'selected' : '' ?>
                            >

                                <?= $method ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div class="form-group">

                    <label>Receipt Image</label>

                    <input
                        type="file"
                        name="receipt_image"
                        accept="image/jpeg,image/png,image/webp"
                    >

                    <?php if (
                        $edit_expense &&
                        !empty($edit_expense['receipt_image'])
                    ): ?>

                        <small style="color:#aaa;margin-top:7px;">

                            Existing receipt:
                            <?= htmlspecialchars($edit_expense['receipt_image']) ?>

                        </small>

                    <?php endif; ?>

                </div>


                <div class="form-group full">

                    <label>Description</label>

                    <textarea
                        name="description"
                        placeholder="Additional details..."
                    ><?= htmlspecialchars($edit_expense['description'] ?? '') ?></textarea>

                </div>

            </div>


            <div class="form-actions">

                <button type="submit" class="btn">

                    <?= $edit_expense ? "Update Expense" : "Add Expense" ?>

                </button>


                <?php if ($edit_expense): ?>

                    <a
                        href="expenses.php"
                        class="btn btn-edit"
                    >
                        Cancel
                    </a>

                <?php endif; ?>

            </div>

        </form>

    </div>


    <!-- =========================
         EXPENSE LIST
    ========================= -->

    <div class="table-box">

        <h2>Expense History</h2>

        <div class="table-wrapper">

            <table>

                <thead>

                    <tr>

                        <th>Date</th>

                        <th>Title</th>

                        <th>Category</th>

                        <th>Amount</th>

                        <th>Payment</th>

                        <th>Receipt</th>

                        <th>Actions</th>

                    </tr>

                </thead>

                <tbody>

                <?php if (count($expenses) > 0): ?>

                    <?php foreach ($expenses as $expense): ?>

                        <tr>

                            <td>
                                <?= date(
                                    "d M Y",
                                    strtotime($expense['expense_date'])
                                ) ?>
                            </td>


                            <td>
                                <?= htmlspecialchars($expense['expense_title']) ?>
                            </td>


                            <td>
                                <?= htmlspecialchars($expense['expense_category']) ?>
                            </td>


                            <td class="amount">

                                Rs.
                                <?= number_format(
                                    $expense['amount'],
                                    2
                                ) ?>

                            </td>


                            <td>
                                <?= htmlspecialchars(
                                    $expense['payment_method']
                                ) ?>
                            </td>


                            <td>

                                <?php if (!empty($expense['receipt_image'])): ?>

                                    <a
                                        class="receipt"
                                        href="../uploads/expenses/<?= htmlspecialchars($expense['receipt_image']) ?>"
                                        target="_blank"
                                    >
                                        View
                                    </a>

                                <?php else: ?>

                                    <span style="color:#777;">
                                        No receipt
                                    </span>

                                <?php endif; ?>

                            </td>


                            <td>

                                <div class="actions">

                                    <a
                                        class="edit"
                                        href="expenses.php?edit=<?= $expense['id'] ?>"
                                    >
                                        Edit
                                    </a>


                                    <a
                                        class="delete"
                                        href="expenses.php?delete=<?= $expense['id'] ?>"
                                        onclick="return confirm('Are you sure you want to delete this expense?');"
                                    >
                                        Delete
                                    </a>

                                </div>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>

                        <td
                            colspan="7"
                            style="text-align:center;color:#777;padding:30px;"
                        >
                            No expenses found.

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