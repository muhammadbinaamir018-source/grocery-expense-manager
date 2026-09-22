<?php

require_once "../db.php";

$message = "";
$messageType = "";


/*
|--------------------------------------------------------------------------
| ADD / EDIT / DELETE STORE
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? "";


    /*
    |--------------------------------------------------------------------------
    | ADD STORE
    |--------------------------------------------------------------------------
    */

    if ($action === "add") {

        $storeName = trim($_POST['store_name'] ?? "");
        $phone = trim($_POST['phone'] ?? "");
        $address = trim($_POST['address'] ?? "");
        $notes = trim($_POST['notes'] ?? "");

        if ($storeName === "") {

            $message = "Store name is required.";
            $messageType = "error";

        } else {

            $check = $conn->prepare("
                SELECT id
                FROM stores
                WHERE store_name = ?
            ");

            $check->bind_param("s", $storeName);
            $check->execute();

            $result = $check->get_result();

            if ($result->num_rows > 0) {

                $message = "This store already exists.";
                $messageType = "error";

            } else {

                $stmt = $conn->prepare("
                    INSERT INTO stores
                    (store_name, phone, address, notes)
                    VALUES (?, ?, ?, ?)
                ");

                $stmt->bind_param(
                    "ssss",
                    $storeName,
                    $phone,
                    $address,
                    $notes
                );

                if ($stmt->execute()) {

                    $message = "Store added successfully.";
                    $messageType = "success";

                } else {

                    $message = "Failed to add store.";
                    $messageType = "error";
                }

                $stmt->close();
            }

            $check->close();
        }
    }


    /*
    |--------------------------------------------------------------------------
    | EDIT STORE
    |--------------------------------------------------------------------------
    */

    if ($action === "edit") {

        $storeId = (int)($_POST['store_id'] ?? 0);

        $storeName = trim($_POST['store_name'] ?? "");
        $phone = trim($_POST['phone'] ?? "");
        $address = trim($_POST['address'] ?? "");
        $notes = trim($_POST['notes'] ?? "");


        if ($storeId <= 0 || $storeName === "") {

            $message = "Invalid store information.";
            $messageType = "error";

        } else {

            $stmt = $conn->prepare("
                UPDATE stores
                SET store_name = ?,
                    phone = ?,
                    address = ?,
                    notes = ?
                WHERE id = ?
            ");

            $stmt->bind_param(
                "ssssi",
                $storeName,
                $phone,
                $address,
                $notes,
                $storeId
            );

            if ($stmt->execute()) {

                $message = "Store updated successfully.";
                $messageType = "success";

            } else {

                $message = "Failed to update store.";
                $messageType = "error";
            }

            $stmt->close();
        }
    }


    /*
    |--------------------------------------------------------------------------
    | DELETE STORE
    |--------------------------------------------------------------------------
    */

    if ($action === "delete") {

        $storeId = (int)($_POST['store_id'] ?? 0);


        if ($storeId > 0) {

            /*
            | Check if this store has purchases
            */

            $check = $conn->prepare("
                SELECT COUNT(*) AS purchase_count
                FROM purchases
                WHERE store_id = ?
            ");

            $check->bind_param(
                "i",
                $storeId
            );

            $check->execute();

            $result = $check->get_result();

            $data = $result->fetch_assoc();

            $check->close();


            if ((int)$data['purchase_count'] > 0) {

                $message =
                    "This store already has purchases, so it cannot be deleted.";

                $messageType = "error";

            } else {

                $stmt = $conn->prepare("
                    DELETE FROM stores
                    WHERE id = ?
                ");

                $stmt->bind_param(
                    "i",
                    $storeId
                );

                if ($stmt->execute()) {

                    $message =
                        "Store deleted successfully.";

                    $messageType = "success";

                } else {

                    $message =
                        "Failed to delete store.";

                    $messageType = "error";
                }

                $stmt->close();
            }
        }
    }
}


/*
|--------------------------------------------------------------------------
| GET STORES
|--------------------------------------------------------------------------
*/

$stores = [];


$query = "
    SELECT
        s.id,
        s.store_name,
        s.phone,
        s.address,
        s.notes,
        s.created_at,

        COUNT(p.id) AS purchase_count,

        COALESCE(SUM(p.total), 0) AS total_spent

    FROM stores s

    LEFT JOIN purchases p
        ON s.id = p.store_id

    GROUP BY
        s.id,
        s.store_name,
        s.phone,
        s.address,
        s.notes,
        s.created_at

    ORDER BY s.store_name ASC
";


$result = mysqli_query($conn, $query);


if ($result) {

    while ($row = mysqli_fetch_assoc($result)) {

        $stores[] = $row;

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

    <title>Stores - Grocery Manager</title>

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
        | ADD BUTTON
        |--------------------------------------------------------------------------
        */

        .add-store {
            display: inline-block;
            margin-bottom: 20px;
            padding: 11px 16px;
            background: #d4af37;
            color: #111;
            text-decoration: none;
            border-radius: 7px;
            font-weight: bold;
            border: none;
            cursor: pointer;
        }


        /*
        |--------------------------------------------------------------------------
        | GRID
        |--------------------------------------------------------------------------
        */

        .stores-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 18px;
        }


        /*
        |--------------------------------------------------------------------------
        | CARD
        |--------------------------------------------------------------------------
        */

        .store-card {
            background: #181818;
            border: 1px solid #292929;
            border-radius: 14px;
            padding: 22px;
            position: relative;
        }


        .store-logo {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            background: #252525;

            display: flex;
            align-items: center;
            justify-content: center;

            font-size: 25px;

            margin-bottom: 15px;
        }


        .store-card h3 {
            margin-bottom: 7px;
        }


        .store-card p {
            color: #888;
            margin: 4px 0;
        }


        .store-total {
            margin-top: 15px;
            color: #d4af37;
            font-weight: bold;
            font-size: 17px;
        }


        .store-actions {
            display: flex;
            gap: 8px;
            margin-top: 18px;
        }


        .edit-btn,
        .delete-btn {

            padding: 7px 11px;

            border-radius: 6px;

            cursor: pointer;

            font-size: 13px;

        }


        .edit-btn {

            background: #242424;

            color: #ddd;

            border: 1px solid #444;

        }


        .edit-btn:hover {

            border-color: #d4af37;

            color: #d4af37;

        }


        .delete-btn {

            background: #241717;

            color: #e88;

            border: 1px solid #513333;

        }


        .delete-btn:hover {

            border-color: #e55;

        }


        /*
        |--------------------------------------------------------------------------
        | MESSAGE
        |--------------------------------------------------------------------------
        */

        .message {

            padding: 12px 15px;

            border-radius: 8px;

            margin-bottom: 20px;

        }


        .message.success {

            background: #14251a;

            border: 1px solid #285c38;

            color: #8bd49d;

        }


        .message.error {

            background: #291717;

            border: 1px solid #633333;

            color: #ed9999;

        }


        /*
        |--------------------------------------------------------------------------
        | MODAL
        |--------------------------------------------------------------------------
        */

        .modal {

            display: none;

            position: fixed;

            inset: 0;

            background: rgba(0,0,0,0.75);

            z-index: 999;

            align-items: center;

            justify-content: center;

            padding: 20px;

        }


        .modal.active {

            display: flex;

        }


        .modal-box {

            width: 100%;

            max-width: 450px;

            background: #181818;

            border: 1px solid #333;

            border-radius: 14px;

            padding: 25px;

        }


        .modal-box h2 {

            margin-top: 0;

        }


        .form-group {

            margin-bottom: 16px;

        }


        .form-group label {

            display: block;

            color: #aaa;

            font-size: 13px;

            margin-bottom: 6px;

        }


        .form-group input,
        .form-group textarea {

            width: 100%;

            box-sizing: border-box;

            padding: 11px;

            background: #101010;

            border: 1px solid #333;

            color: white;

            border-radius: 7px;

            font-family: inherit;

        }


        .form-group textarea {

            min-height: 90px;

            resize: vertical;

        }


        .modal-actions {

            display: flex;

            gap: 10px;

            justify-content: flex-end;

            margin-top: 20px;

        }


        .save-btn {

            background: #d4af37;

            color: #111;

            border: none;

            padding: 10px 18px;

            border-radius: 7px;

            cursor: pointer;

            font-weight: bold;

        }


        .cancel-btn {

            background: #242424;

            color: #ddd;

            border: 1px solid #444;

            padding: 10px 18px;

            border-radius: 7px;

            cursor: pointer;

        }


        @media(max-width: 800px) {

            .stores-grid {

                grid-template-columns: 1fr 1fr;

            }

        }


        @media(max-width: 500px) {

            .page-content {

                padding: 18px;

            }

            .stores-grid {

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
            Stores
        </h1>

        <p>
            Manage grocery stores and suppliers.
        </p>

    </div>


    <!-- MESSAGE -->

    <?php if ($message !== ""): ?>

        <div class="message <?php echo $messageType; ?>">

            <?php
            echo htmlspecialchars($message);
            ?>

        </div>

    <?php endif; ?>


    <!-- ADD BUTTON -->

    <button
        type="button"
        class="add-store"
        onclick="openAddModal()"
    >

        + Add Store

    </button>


    <!-- STORES -->

    <div class="stores-grid">


        <?php if (count($stores) > 0): ?>


            <?php foreach ($stores as $store): ?>


                <div class="store-card">


                    <div class="store-logo">

                        🛒

                    </div>


                    <h3>

                        <?php

                        echo htmlspecialchars(
                            $store['store_name']
                        );

                        ?>

                    </h3>


                    <p>
                        Grocery Supplier
                    </p>


                    <p>

                        <?php

                        echo (int)$store['purchase_count'];

                        ?>

                        <?php

                        echo (
                            (int)$store['purchase_count'] == 1
                        )
                            ? " Purchase"
                            : " Purchases";

                        ?>

                    </p>


                    <?php if (!empty($store['phone'])): ?>

                        <p>
                            📞
                            <?php
                            echo htmlspecialchars(
                                $store['phone']
                            );
                            ?>
                        </p>

                    <?php endif; ?>


                    <?php if (!empty($store['address'])): ?>

                        <p>
                            📍
                            <?php
                            echo htmlspecialchars(
                                $store['address']
                            );
                            ?>
                        </p>

                    <?php endif; ?>


                    <div class="store-total">

                        Rs.
                        <?php

                        echo number_format(
                            (float)$store['total_spent'],
                            2
                        );

                        ?>

                    </div>


                    <!-- ACTIONS -->

                    <div class="store-actions">


                        <button
                            type="button"
                            class="edit-btn"
                            onclick='openEditModal(
                                <?php echo json_encode($store); ?>
                            )'
                        >

                            Edit

                        </button>


                        <form
                            method="POST"
                            style="display:inline;"
                            onsubmit="return confirm(
                                'Are you sure you want to delete this store?'
                            );"
                        >

                            <input
                                type="hidden"
                                name="action"
                                value="delete"
                            >

                            <input
                                type="hidden"
                                name="store_id"
                                value="<?php
                                echo $store['id'];
                                ?>"
                            >


                            <button
                                type="submit"
                                class="delete-btn"
                            >

                                Delete

                            </button>

                        </form>


                    </div>


                </div>


            <?php endforeach; ?>


        <?php else: ?>


            <div class="store-card">

                <h3>
                    No Stores
                </h3>

                <p>
                    Add your first grocery store.
                </p>

            </div>


        <?php endif; ?>


    </div>


</div>


<!-- ADD STORE MODAL -->

<div
    class="modal"
    id="addModal"
>

    <div class="modal-box">


        <h2>
            Add Store
        </h2>


        <form method="POST">


            <input
                type="hidden"
                name="action"
                value="add"
            >


            <div class="form-group">

                <label>
                    Store Name
                </label>

                <input
                    type="text"
                    name="store_name"
                    placeholder="e.g. Imtiaz Super Market"
                    required
                >

            </div>


            <div class="form-group">

                <label>
                    Phone
                </label>

                <input
                    type="text"
                    name="phone"
                    placeholder="e.g. 021-111-468-429"
                >

            </div>


            <div class="form-group">

                <label>
                    Address
                </label>

                <input
                    type="text"
                    name="address"
                    placeholder="Store address"
                >

            </div>


            <div class="form-group">

                <label>
                    Notes
                </label>

                <textarea
                    name="notes"
                    placeholder="Additional notes..."
                ></textarea>

            </div>


            <div class="modal-actions">


                <button
                    type="button"
                    class="cancel-btn"
                    onclick="closeAddModal()"
                >

                    Cancel

                </button>


                <button
                    type="submit"
                    class="save-btn"
                >

                    Save Store

                </button>


            </div>


        </form>


    </div>

</div>


<!-- EDIT STORE MODAL -->

<div
    class="modal"
    id="editModal"
>

    <div class="modal-box">


        <h2>
            Edit Store
        </h2>


        <form method="POST">


            <input
                type="hidden"
                name="action"
                value="edit"
            >


            <input
                type="hidden"
                name="store_id"
                id="edit_store_id"
            >


            <div class="form-group">

                <label>
                    Store Name
                </label>

                <input
                    type="text"
                    name="store_name"
                    id="edit_store_name"
                    required
                >

            </div>


            <div class="form-group">

                <label>
                    Phone
                </label>

                <input
                    type="text"
                    name="phone"
                    id="edit_phone"
                >

            </div>


            <div class="form-group">

                <label>
                    Address
                </label>

                <input
                    type="text"
                    name="address"
                    id="edit_address"
                >

            </div>


            <div class="form-group">

                <label>
                    Notes
                </label>

                <textarea
                    name="notes"
                    id="edit_notes"
                ></textarea>

            </div>


            <div class="modal-actions">


                <button
                    type="button"
                    class="cancel-btn"
                    onclick="closeEditModal()"
                >

                    Cancel

                </button>


                <button
                    type="submit"
                    class="save-btn"
                >

                    Update Store

                </button>


            </div>


        </form>


    </div>

</div>


<script>


function openAddModal() {

    document
        .getElementById("addModal")
        .classList
        .add("active");

}


function closeAddModal() {

    document
        .getElementById("addModal")
        .classList
        .remove("active");

}


function openEditModal(store) {

    document.getElementById(
        "edit_store_id"
    ).value = store.id;


    document.getElementById(
        "edit_store_name"
    ).value = store.store_name;


    document.getElementById(
        "edit_phone"
    ).value = store.phone || "";


    document.getElementById(
        "edit_address"
    ).value = store.address || "";


    document.getElementById(
        "edit_notes"
    ).value = store.notes || "";


    document
        .getElementById("editModal")
        .classList
        .add("active");

}


function closeEditModal() {

    document
        .getElementById("editModal")
        .classList
        .remove("active");

}


/*
|--------------------------------------------------------------------------
| CLOSE MODAL WHEN CLICKING OUTSIDE
|--------------------------------------------------------------------------
*/

window.onclick = function(event) {

    const addModal =
        document.getElementById("addModal");

    const editModal =
        document.getElementById("editModal");


    if (event.target === addModal) {

        closeAddModal();

    }


    if (event.target === editModal) {

        closeEditModal();

    }

};

</script>


</body>

</html>