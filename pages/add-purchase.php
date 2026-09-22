<?php

require_once "../db.php";

$message = "";
$messageType = "";


/* =========================
   SAVE PURCHASE
========================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $store_id       = intval($_POST["store_id"]);
    $purchase_date  = $_POST["purchase_date"];
    $purchase_time  = $_POST["purchase_time"];
    $bill_number    = trim($_POST["bill_number"]);

    $subtotal       = floatval($_POST["subtotal"]);
    $tax            = floatval($_POST["tax"]);
    $discount       = floatval($_POST["discount"]);
    $total          = floatval($_POST["total"]);

    $billImagePath = null;

    try {

        /* =========================
           BILL IMAGE UPLOAD
        ========================= */

        if (
            isset($_FILES["bill_image"]) &&
            $_FILES["bill_image"]["error"] === UPLOAD_ERR_OK
        ) {

            $uploadDir = "../uploads/bills/";

            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $allowedTypes = [
                "image/jpeg",
                "image/png",
                "image/jpg",
                "image/webp"
            ];

            $fileType = $_FILES["bill_image"]["type"];

            if (!in_array($fileType, $allowedTypes)) {
                throw new Exception(
                    "Only JPG, PNG or WEBP images are allowed."
                );
            }

            if ($_FILES["bill_image"]["size"] > 5 * 1024 * 1024) {
                throw new Exception(
                    "Bill image must be less than 5MB."
                );
            }

            $extension = strtolower(
                pathinfo(
                    $_FILES["bill_image"]["name"],
                    PATHINFO_EXTENSION
                )
            );

            $fileName =
                "bill_" .
                date("Ymd_His") .
                "_" .
                uniqid() .
                "." .
                $extension;

            $destination = $uploadDir . $fileName;

            if (
                !move_uploaded_file(
                    $_FILES["bill_image"]["tmp_name"],
                    $destination
                )
            ) {
                throw new Exception(
                    "Failed to upload bill image."
                );
            }

            $billImagePath =
                "uploads/bills/" . $fileName;
        }


        /* =========================
           START DATABASE TRANSACTION
        ========================= */

        $conn->begin_transaction();


        /* =========================
           INSERT PURCHASE
        ========================= */

        $sql = "INSERT INTO purchases
                (
                    store_id,
                    purchase_date,
                    purchase_time,
                    bill_number,
                    subtotal,
                    tax,
                    discount,
                    total,
                    bill_image
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            throw new Exception($conn->error);
        }

        $stmt->bind_param(
            "isssdddds",
            $store_id,
            $purchase_date,
            $purchase_time,
            $bill_number,
            $subtotal,
            $tax,
            $discount,
            $total,
            $billImagePath
        );

        $stmt->execute();

        $purchase_id = $stmt->insert_id;

        $stmt->close();


        /* =========================
           INSERT BILL IMAGE
        ========================= */

        if ($billImagePath !== null) {

            $sqlImage = "INSERT INTO bill_images
                         (
                             purchase_id,
                             file_name,
                             file_path,
                             file_type
                         )
                         VALUES (?, ?, ?, ?)";

            $stmtImage =
                $conn->prepare($sqlImage);

            $originalName =
                $_FILES["bill_image"]["name"];

            $stmtImage->bind_param(
                "isss",
                $purchase_id,
                $originalName,
                $billImagePath,
                $fileType
            );

            $stmtImage->execute();

            $stmtImage->close();
        }


        /* =========================
           INSERT PURCHASE ITEMS
        ========================= */

        if (
            isset($_POST["item_name"]) &&
            is_array($_POST["item_name"])
        ) {

            $itemNames   = $_POST["item_name"];
            $quantities  = $_POST["quantity"];
            $unitPrices  = $_POST["unit_price"];
            $itemTotals  = $_POST["item_total"];
            $categoryIds = $_POST["category_id"];

            $sqlItem = "INSERT INTO purchase_items
                        (
                            purchase_id,
                            category_id,
                            item_name,
                            quantity,
                            unit_price,
                            total_price
                        )
                        VALUES (?, ?, ?, ?, ?, ?)";

            $stmtItem =
                $conn->prepare($sqlItem);

            for (
                $i = 0;
                $i < count($itemNames);
                $i++
            ) {

                $itemName =
                    trim($itemNames[$i]);

                if ($itemName === "") {
                    continue;
                }

                $quantity =
                    floatval($quantities[$i]);

                $unitPrice =
                    floatval($unitPrices[$i]);

                $itemTotal =
                    floatval($itemTotals[$i]);

                $categoryId =
                    intval($categoryIds[$i]);

                if ($categoryId <= 0) {
                    $categoryId = null;
                }

                $stmtItem->bind_param(
                    "iisddd",
                    $purchase_id,
                    $categoryId,
                    $itemName,
                    $quantity,
                    $unitPrice,
                    $itemTotal
                );

                $stmtItem->execute();
            }

            $stmtItem->close();
        }


        /* =========================
           COMMIT
        ========================= */

        $conn->commit();

        $message =
            "Purchase saved successfully! Purchase ID: #" .
            $purchase_id;

        $messageType = "success";


    } catch (Exception $e) {

        $conn->rollback();

        $message =
            "Error: " . $e->getMessage();

        $messageType = "error";
    }
}


/* =========================
   GET STORES
========================= */

$stores = [];

$result = $conn->query(
    "SELECT id, store_name
     FROM stores
     ORDER BY store_name ASC"
);

if ($result) {

    while ($row = $result->fetch_assoc()) {
        $stores[] = $row;
    }
}


/* =========================
   GET CATEGORIES
========================= */

$categories = [];

$result = $conn->query(
    "SELECT id, category_name
     FROM categories
     ORDER BY category_name ASC"
);

if ($result) {

    while ($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Add Purchase - Grocery Manager</title>

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
            font-size: 28px;
        }

        .page-header p {
            color: #999;
        }

        /* MESSAGE */

        .message {
            padding: 14px 18px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 600;
        }

        .message.success {
            background: #12351f;
            color: #72e59b;
            border: 1px solid #245d36;
        }

        .message.error {
            background: #3b1717;
            color: #ff8585;
            border: 1px solid #6b2929;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1.5fr 1fr;
            gap: 22px;
        }

        .form-card {
            background: #181818;
            border: 1px solid #292929;
            border-radius: 14px;
            padding: 24px;
            margin-bottom: 22px;
        }

        .form-card h3 {
            margin-top: 0;
            margin-bottom: 20px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 7px;
            color: #ccc;
            font-size: 14px;
        }

        .form-group input,
        .form-group select {

            width: 100%;
            padding: 12px 13px;

            background: #101010;
            border: 1px solid #333;

            color: white;

            border-radius: 8px;
            outline: none;

            box-sizing: border-box;
        }

        .form-group input:focus,
        .form-group select:focus {
            border-color: #d4af37;
        }

        /* UPLOAD */

        .upload-box {

            display: block;

            border: 2px dashed #444;
            border-radius: 12px;

            padding: 35px 20px;

            text-align: center;

            cursor: pointer;

            transition: 0.2s;
        }

        .upload-box:hover {
            border-color: #d4af37;
            background: #1d1d1d;
        }

        .upload-icon {
            font-size: 42px;
            margin-bottom: 10px;
        }

        .upload-box p {
            color: #aaa;
            margin: 6px 0;
        }

        .upload-box small {
            color: #666;
        }

        #billImage {
            display: none;
        }

        /* OCR BUTTON */

        #scanBillBtn {

            width: 100%;

            margin-top: 12px;

            padding: 12px 18px;

            background: #d4af37;

            color: #111;

            border: none;

            border-radius: 8px;

            cursor: pointer;

            font-weight: 700;

            font-size: 14px;

            transition: 0.2s;
        }

        #scanBillBtn:hover:not(:disabled) {
            background: #e3c04b;
        }

        #scanBillBtn:disabled {

            opacity: 0.45;

            cursor: not-allowed;
        }

        #ocrStatus {

            margin-top: 10px;

            font-size: 14px;

            min-height: 20px;

            color: #d4af37;
        }

        /* IMAGE PREVIEW */

        .image-preview {
            display: none;
            margin-top: 20px;
            text-align: center;
        }

        .image-preview img {
            max-width: 100%;
            max-height: 350px;
            border-radius: 10px;
            border: 1px solid #333;
        }

        /* ITEMS TABLE */

        .table-wrapper {
            overflow-x: auto;
        }

        .items-table {

            width: 100%;
            border-collapse: collapse;

            min-width: 750px;
        }

        .items-table th {

            color: #999;
            font-size: 13px;
            text-align: left;

            padding: 10px;

            border-bottom: 1px solid #333;
        }

        .items-table td {

            padding: 8px;

            border-bottom: 1px solid #292929;
        }

        .items-table input,
        .items-table select {

            width: 100%;

            padding: 9px;

            background: #101010;

            border: 1px solid #333;

            color: white;

            border-radius: 6px;

            box-sizing: border-box;
        }

        .remove-item {

            background: #3a1717;

            color: #ff7777;

            border: none;

            padding: 8px 10px;

            border-radius: 6px;

            cursor: pointer;
        }

        .add-item-btn {

            margin-top: 15px;

            padding: 10px 15px;

            background: transparent;

            color: #d4af37;

            border: 1px solid #d4af37;

            border-radius: 7px;

            cursor: pointer;
        }

        /* SUMMARY */

        .summary-box {

            background: #101010;

            border-radius: 10px;

            padding: 18px;

            margin-top: 15px;
        }

        .summary-line {

            display: flex;

            justify-content: space-between;

            padding: 8px 0;

            color: #aaa;
        }

        .summary-line.total {

            border-top: 1px solid #333;

            margin-top: 8px;

            padding-top: 15px;

            color: white;

            font-size: 19px;

            font-weight: bold;
        }

        .save-btn {

            width: 100%;

            padding: 14px;

            border: none;

            border-radius: 8px;

            background: #d4af37;

            color: #111;

            font-weight: bold;

            font-size: 15px;

            cursor: pointer;

            margin-top: 15px;
        }

        .save-btn:hover {
            background: #e3c04b;
        }

        @media (max-width: 900px) {

            .form-grid {
                grid-template-columns: 1fr;
            }

        }

        @media (max-width: 600px) {

            .page-content {
                padding: 18px;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

        }

    </style>

</head>


<body>

<div class="page-content">


    <div class="page-header">

        <h1>Add Purchase</h1>

        <p>
            Add a new grocery purchase and bill details.
        </p>

    </div>


    <?php if ($message !== ""): ?>

        <div class="message <?= $messageType ?>">
            <?= htmlspecialchars($message) ?>
        </div>

    <?php endif; ?>


    <form
        method="POST"
        enctype="multipart/form-data"
        id="purchaseForm"
    >

        <div class="form-grid">


            <!-- =========================
                 LEFT SIDE
            ========================== -->

            <div>


                <!-- PURCHASE INFORMATION -->

                <div class="form-card">

                    <h3>Purchase Information</h3>


                    <div class="form-row">

                        <div class="form-group">

                            <label>Store *</label>

                            <select
                                name="store_id"
                                required
                            >

                                <option value="">
                                    Select Store
                                </option>

                                <?php foreach ($stores as $store): ?>

                                    <option
                                        value="<?= $store['id'] ?>"
                                    >
                                        <?= htmlspecialchars(
                                            $store['store_name']
                                        ) ?>
                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>


                        <div class="form-group">

                            <label>Bill Number</label>

                            <input
                                type="text"
                                name="bill_number"
                                placeholder="e.g. IM-45821"
                            >

                        </div>

                    </div>


                    <div class="form-row">

                        <div class="form-group">

                            <label>Purchase Date *</label>

                            <input
                                type="date"
                                name="purchase_date"
                                value="<?= date('Y-m-d') ?>"
                                required
                            >

                        </div>


                        <div class="form-group">

                            <label>Purchase Time</label>

                            <input
                                type="time"
                                name="purchase_time"
                                value="<?= date('H:i') ?>"
                            >

                        </div>

                    </div>

                </div>


                <!-- BILL IMAGE -->

                <div class="form-card">

                    <h3>Bill / Receipt</h3>


                    <label
                        for="billImage"
                        class="upload-box"
                    >

                        <div class="upload-icon">
                            📷
                        </div>

                        <strong>
                            Upload Grocery Bill
                        </strong>

                        <p>
                            Click here to select bill image
                        </p>

                        <small>
                            JPG, JPEG, PNG or WEBP — Max 5MB
                        </small>

                    </label>


                    <input
                        type="file"
                        id="billImage"
                        name="bill_image"
                        accept="image/jpeg,image/png,image/webp"
                    >


                    <!-- OCR BUTTON -->

                    <button
                        type="button"
                        id="scanBillBtn"
                        disabled
                    >
                        🔍 Scan Bill with OCR
                    </button>


                    <div id="ocrStatus"></div>


                    <div
                        class="image-preview"
                        id="imagePreview"
                    >

                        <img
                            id="previewImg"
                            src=""
                            alt="Bill Preview"
                        >

                    </div>

                </div>


                <!-- ITEMS -->

                <div class="form-card">

                    <h3>Purchase Items</h3>


                    <div class="table-wrapper">

                        <table class="items-table">

                            <thead>

                                <tr>

                                    <th>Item Name</th>

                                    <th>Qty</th>

                                    <th>Unit Price</th>

                                    <th>Total</th>

                                    <th>Category</th>

                                    <th></th>

                                </tr>

                            </thead>


                            <tbody id="itemsBody">

                                <tr>

                                    <td>

                                        <input
                                            type="text"
                                            name="item_name[]"
                                            placeholder="Milk"
                                        >

                                    </td>


                                    <td>

                                        <input
                                            type="number"
                                            name="quantity[]"
                                            class="quantity"
                                            value="1"
                                            min="0"
                                            step="0.01"
                                        >

                                    </td>


                                    <td>

                                        <input
                                            type="number"
                                            name="unit_price[]"
                                            class="unit-price"
                                            value="0"
                                            min="0"
                                            step="0.01"
                                        >

                                    </td>


                                    <td>

                                        <input
                                            type="number"
                                            name="item_total[]"
                                            class="item-total"
                                            value="0"
                                            min="0"
                                            step="0.01"
                                        >

                                    </td>


                                    <td>

                                        <select name="category_id[]">

                                            <option value="">
                                                Select
                                            </option>

                                            <?php foreach (
                                                $categories
                                                as $category
                                            ): ?>

                                                <option
                                                    value="<?= $category['id'] ?>"
                                                >
                                                    <?= htmlspecialchars(
                                                        $category['category_name']
                                                    ) ?>
                                                </option>

                                            <?php endforeach; ?>

                                        </select>

                                    </td>


                                    <td>

                                        <button
                                            type="button"
                                            class="remove-item"
                                            onclick="removeItem(this)"
                                        >
                                            ×
                                        </button>

                                    </td>

                                </tr>

                            </tbody>

                        </table>

                    </div>


                    <button
                        type="button"
                        class="add-item-btn"
                        onclick="addItem()"
                    >
                        + Add Item
                    </button>

                </div>

            </div>


            <!-- =========================
                 RIGHT SIDE
            ========================== -->

            <div>

                <div class="form-card">

                    <h3>Bill Summary</h3>


                    <div class="form-group">

                        <label>
                            Subtotal
                        </label>

                        <input
                            type="number"
                            name="subtotal"
                            id="subtotal"
                            value="0"
                            min="0"
                            step="0.01"
                        >

                    </div>


                    <div class="form-group">

                        <label>
                            Tax
                        </label>

                        <input
                            type="number"
                            name="tax"
                            id="tax"
                            value="0"
                            min="0"
                            step="0.01"
                        >

                    </div>


                    <div class="form-group">

                        <label>
                            Discount
                        </label>

                        <input
                            type="number"
                            name="discount"
                            id="discount"
                            value="0"
                            min="0"
                            step="0.01"
                        >

                    </div>


                    <div class="summary-box">

                        <div class="summary-line">

                            <span>
                                Subtotal
                            </span>

                            <span id="summarySubtotal">
                                Rs. 0.00
                            </span>

                        </div>


                        <div class="summary-line">

                            <span>
                                Tax
                            </span>

                            <span id="summaryTax">
                                Rs. 0.00
                            </span>

                        </div>


                        <div class="summary-line">

                            <span>
                                Discount
                            </span>

                            <span id="summaryDiscount">
                                Rs. 0.00
                            </span>

                        </div>


                        <div class="summary-line total">

                            <span>
                                Total
                            </span>

                            <span id="summaryTotal">
                                Rs. 0.00
                            </span>

                        </div>

                    </div>


                    <input
                        type="hidden"
                        name="total"
                        id="total"
                        value="0"
                    >


                    <button
                        type="submit"
                        class="save-btn"
                    >
                        ✓ Save Purchase
                    </button>

                </div>

            </div>

        </div>

    </form>

</div>


<script>

/* =====================================================
   BILL IMAGE + OCR
===================================================== */

const billImageInput =
    document.getElementById("billImage");

const scanBillBtn =
    document.getElementById("scanBillBtn");

const ocrStatus =
    document.getElementById("ocrStatus");


/* =========================
   IMAGE SELECTED
========================= */

billImageInput.addEventListener(
    "change",
    function () {

        const file = this.files[0];

        if (!file) {

            scanBillBtn.disabled = true;

            ocrStatus.textContent = "";

            return;
        }


        if (file.size > 5 * 1024 * 1024) {

            alert(
                "Bill image must be less than 5 MB."
            );

            this.value = "";

            scanBillBtn.disabled = true;

            return;
        }


        scanBillBtn.disabled = false;

        ocrStatus.style.color = "#d4af37";

        ocrStatus.textContent =
            "Bill selected. Click 'Scan Bill with OCR'.";


        /* IMAGE PREVIEW */

        const reader = new FileReader();

        reader.onload = function (e) {

            document.getElementById(
                "previewImg"
            ).src = e.target.result;

            document.getElementById(
                "imagePreview"
            ).style.display = "block";

        };

        reader.readAsDataURL(file);
    }
);


/* =====================================================
   START OCR
===================================================== */

scanBillBtn.addEventListener(
    "click",
    async function () {

        if (!billImageInput.files.length) {

            alert(
                "Please select a bill image first."
            );

            return;
        }


        const file =
            billImageInput.files[0];


        if (file.size > 5 * 1024 * 1024) {

            alert(
                "Bill image must be less than 5 MB."
            );

            return;
        }


        /* BUTTON LOADING */

        scanBillBtn.disabled = true;

        scanBillBtn.textContent =
            "⏳ Scanning Bill...";


        ocrStatus.style.color =
            "#d4af37";

        ocrStatus.textContent =
            "Please wait... Tesseract is reading the bill.";


        const formData =
            new FormData();

        formData.append(
            "bill_image",
            file
        );


        try {

            const response =
                await fetch(
                    "ocr.php",
                    {
                        method: "POST",
                        body: formData
                    }
                );


            const result =
                await response.json();


            console.log(
                "OCR RESULT:",
                result
            );


            if (!result.success) {

                throw new Error(
                    result.message ||
                    "OCR failed."
                );
            }


            /* FILL FORM */

            fillOCRData(
                result.data
            );


            /* SUCCESS */

            ocrStatus.style.color =
                "#72e59b";

            ocrStatus.textContent =
                "✅ OCR complete! Please review the detected information before saving.";


            console.log(
                "RAW OCR TEXT:",
                result.raw_text
            );


        } catch (error) {

            console.error(
                "OCR ERROR:",
                error
            );


            ocrStatus.style.color =
                "#ff7777";

            ocrStatus.textContent =
                "❌ " + error.message;


            alert(
                "OCR Error:\n\n" +
                error.message
            );


        } finally {

            scanBillBtn.disabled = false;

            scanBillBtn.textContent =
                "🔍 Scan Bill with OCR";

        }

    }
);


/* =====================================================
   FILL OCR DATA
===================================================== */

function fillOCRData(data) {


    console.log(
        "Filling OCR Data:",
        data
    );


    /* =========================
       BILL NUMBER
    ========================= */

    const billNumber =
        document.querySelector(
            '[name="bill_number"]'
        );

    if (
        billNumber &&
        data.invoice_number
    ) {

        billNumber.value =
            data.invoice_number;
    }


    /* =========================
       DATE
    ========================= */

    const purchaseDate =
        document.querySelector(
            '[name="purchase_date"]'
        );

    if (
        purchaseDate &&
        data.purchase_date
    ) {

        purchaseDate.value =
            data.purchase_date;
    }


    /* =========================
       TIME
    ========================= */

    const purchaseTime =
        document.querySelector(
            '[name="purchase_time"]'
        );

    if (
        purchaseTime &&
        data.purchase_time
    ) {

        purchaseTime.value =
            data.purchase_time;
    }


    /* =========================
       DISCOUNT
    ========================= */

    const discount =
        document.querySelector(
            '[name="discount"]'
        );

    if (discount) {

        discount.value =
            parseFloat(
                data.discount || 0
            ).toFixed(2);
    }


    /* =========================
       SUBTOTAL
    ========================= */

    const subtotal =
        document.querySelector(
            '[name="subtotal"]'
        );

    if (subtotal) {

        subtotal.value =
            parseFloat(
                data.subtotal || 0
            ).toFixed(2);
    }


    /* =========================
       TAX
    ========================= */

    const tax =
        document.querySelector(
            '[name="tax"]'
        );

    if (tax) {

        tax.value =
            parseFloat(
                data.tax || 0
            ).toFixed(2);
    }


    /* =========================
       ITEMS
    ========================= */

    if (
        data.items &&
        Array.isArray(data.items) &&
        data.items.length > 0
    ) {

        fillOCRItems(
            data.items
        );
    }


    /* =========================
       UPDATE SUMMARY
    ========================= */

    updateSummary();
}


/* =====================================================
   MATCH STORE
===================================================== */

function matchStore(storeName) {

    const storeSelect =
        document.querySelector(
            '[name="store_id"]'
        );


    if (!storeSelect) {
        return;
    }


    const ocrStore =
        storeName.toLowerCase();


    for (
        const option of storeSelect.options
    ) {

        const optionText =
            option.text.toLowerCase();


        if (
            optionText.includes("mega") &&
            ocrStore.includes("mega")
        ) {

            storeSelect.value =
                option.value;

            return;
        }


        if (
            optionText.includes("bahria") &&
            ocrStore.includes("bahria")
        ) {

            storeSelect.value =
                option.value;

            return;
        }

    }
}


/* =====================================================
   FILL OCR ITEMS
===================================================== */

function fillOCRItems(items) {


    const tbody =
        document.getElementById(
            "itemsBody"
        );


    if (!tbody) {

        console.warn(
            "itemsBody not found."
        );

        return;
    }


    /* Remove existing rows */

    tbody.innerHTML = "";


    /* Add OCR rows */

    items.forEach(
        function (item) {


            addItem();


            const row =
                tbody.lastElementChild;


            if (!row) {
                return;
            }


            /* ITEM NAME */

            const itemName =
                row.querySelector(
                    '[name="item_name[]"]'
                );

            if (itemName) {

                itemName.value =
                    item.item_name || "";
            }


            /* QUANTITY */

            const quantity =
                row.querySelector(
                    '[name="quantity[]"]'
                );

            if (quantity) {

                quantity.value =
                    item.quantity || 0;
            }


            /* UNIT PRICE */

            const unitPrice =
                row.querySelector(
                    '[name="unit_price[]"]'
                );

            if (unitPrice) {

                unitPrice.value =
                    item.unit_price || 0;
            }


            /* TOTAL */

            const itemTotal =
                row.querySelector(
                    '[name="item_total[]"]'
                );

            if (itemTotal) {

                itemTotal.value =
                    item.item_total || 0;
            }

        }
    );


    /* Recalculate subtotal from OCR items */

    let subtotalValue = 0;


    const itemTotalInputs =
        document.querySelectorAll(
            ".item-total"
        );


    itemTotalInputs.forEach(
        function (input) {

            subtotalValue +=
                parseFloat(
                    input.value
                ) || 0;

        }
    );


    document.getElementById(
        "subtotal"
    ).value =
        subtotalValue.toFixed(2);


    updateSummary();
}


/* =====================================================
   ADD ITEM
===================================================== */

function addItem() {


    const tbody =
        document.getElementById(
            "itemsBody"
        );


    const row =
        document.createElement("tr");


    row.innerHTML = `

        <td>

            <input
                type="text"
                name="item_name[]"
                placeholder="Item name"
            >

        </td>


        <td>

            <input
                type="number"
                name="quantity[]"
                class="quantity"
                value="1"
                min="0"
                step="0.01"
            >

        </td>


        <td>

            <input
                type="number"
                name="unit_price[]"
                class="unit-price"
                value="0"
                min="0"
                step="0.01"
            >

        </td>


        <td>

            <input
                type="number"
                name="item_total[]"
                class="item-total"
                value="0"
                min="0"
                step="0.01"
            >

        </td>


        <td>

            <select
                name="category_id[]"
            >

                <option value="">
                    Select
                </option>

                <?php foreach (
                    $categories
                    as $category
                ): ?>

                    <option
                        value="<?= $category['id'] ?>"
                    >
                        <?= htmlspecialchars(
                            $category['category_name']
                        ) ?>
                    </option>

                <?php endforeach; ?>

            </select>

        </td>


        <td>

            <button
                type="button"
                class="remove-item"
                onclick="removeItem(this)"
            >
                ×
            </button>

        </td>

    `;


    tbody.appendChild(row);
}


/* =====================================================
   REMOVE ITEM
===================================================== */

function removeItem(button) {


    const rows =
        document.querySelectorAll(
            "#itemsBody tr"
        );


    if (rows.length <= 1) {

        alert(
            "At least one item row is required."
        );

        return;
    }


    button
        .closest("tr")
        .remove();


    calculateItemsSubtotal();
}


/* =====================================================
   ITEM TOTAL CALCULATION
===================================================== */

document.addEventListener(
    "input",
    function (e) {


        if (
            e.target.classList.contains(
                "quantity"
            ) ||
            e.target.classList.contains(
                "unit-price"
            )
        ) {


            const row =
                e.target.closest("tr");


            const quantity =
                parseFloat(
                    row.querySelector(
                        ".quantity"
                    ).value
                ) || 0;


            const unitPrice =
                parseFloat(
                    row.querySelector(
                        ".unit-price"
                    ).value
                ) || 0;


            const total =
                quantity * unitPrice;


            row.querySelector(
                ".item-total"
            ).value =
                total.toFixed(2);


            calculateItemsSubtotal();

        }

    }
);


/* =====================================================
   CALCULATE ITEMS SUBTOTAL
===================================================== */

function calculateItemsSubtotal() {


    let subtotalValue = 0;


    document
        .querySelectorAll(".item-total")
        .forEach(
            function (input) {

                subtotalValue +=
                    parseFloat(
                        input.value
                    ) || 0;

            }
        );


    document.getElementById(
        "subtotal"
    ).value =
        subtotalValue.toFixed(2);


    updateSummary();
}


/* =====================================================
   BILL SUMMARY
===================================================== */

function updateSummary() {


    const subtotal =
        parseFloat(
            document.getElementById(
                "subtotal"
            ).value
        ) || 0;


    const tax =
        parseFloat(
            document.getElementById(
                "tax"
            ).value
        ) || 0;


    const discount =
        parseFloat(
            document.getElementById(
                "discount"
            ).value
        ) || 0;


    const total =
        subtotal +
        tax -
        discount;


    document.getElementById(
        "summarySubtotal"
    ).innerText =
        "Rs. " +
        subtotal.toFixed(2);


    document.getElementById(
        "summaryTax"
    ).innerText =
        "Rs. " +
        tax.toFixed(2);


    document.getElementById(
        "summaryDiscount"
    ).innerText =
        "Rs. " +
        discount.toFixed(2);


    document.getElementById(
        "summaryTotal"
    ).innerText =
        "Rs. " +
        total.toFixed(2);


    document.getElementById(
        "total"
    ).value =
        total.toFixed(2);
}


/* =====================================================
   SUMMARY INPUT LISTENERS
===================================================== */

document
    .getElementById("subtotal")
    .addEventListener(
        "input",
        updateSummary
    );


document
    .getElementById("tax")
    .addEventListener(
        "input",
        updateSummary
    );


document
    .getElementById("discount")
    .addEventListener(
        "input",
        updateSummary
    );


/* Initial calculation */

updateSummary();

</script>


</body>

</html>