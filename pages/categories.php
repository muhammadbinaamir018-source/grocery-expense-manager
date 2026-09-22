<?php

require_once "../db.php";

/*
|--------------------------------------------------------------------------
| ICONS
|--------------------------------------------------------------------------
*/

$categoryIcons = [
    'Grocery'    => '🛒',
    'Meat'       => '🥩',
    'Vegetables' => '🥬',
    'Dairy'      => '🥛',
    'Beverages'  => '🥤',
    'Other'      => '📦'
];


/*
|--------------------------------------------------------------------------
| ADD CATEGORY
|--------------------------------------------------------------------------
*/

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';


    /*
    |--------------------------------------------------------------------------
    | ADD
    |--------------------------------------------------------------------------
    */

    if ($action === 'add') {

        $categoryName = trim($_POST['category_name'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if ($categoryName === '') {

            $message = "Category name is required.";
            $messageType = "error";

        } else {

            $check = $conn->prepare("
                SELECT id
                FROM categories
                WHERE category_name = ?
            ");

            $check->bind_param("s", $categoryName);
            $check->execute();

            $checkResult = $check->get_result();

            if ($checkResult->num_rows > 0) {

                $message = "This category already exists.";
                $messageType = "error";

            } else {

                $stmt = $conn->prepare("
                    INSERT INTO categories
                    (category_name, description)
                    VALUES (?, ?)
                ");

                $stmt->bind_param(
                    "ss",
                    $categoryName,
                    $description
                );

                if ($stmt->execute()) {

                    $message = "Category added successfully.";
                    $messageType = "success";

                } else {

                    $message = "Failed to add category.";
                    $messageType = "error";
                }

                $stmt->close();
            }

            $check->close();
        }
    }


    /*
    |--------------------------------------------------------------------------
    | EDIT
    |--------------------------------------------------------------------------
    */

    if ($action === 'edit') {

        $categoryId = (int)($_POST['category_id'] ?? 0);
        $categoryName = trim($_POST['category_name'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if ($categoryId <= 0 || $categoryName === '') {

            $message = "Invalid category information.";
            $messageType = "error";

        } else {

            $stmt = $conn->prepare("
                UPDATE categories
                SET category_name = ?,
                    description = ?
                WHERE id = ?
            ");

            $stmt->bind_param(
                "ssi",
                $categoryName,
                $description,
                $categoryId
            );

            if ($stmt->execute()) {

                $message = "Category updated successfully.";
                $messageType = "success";

            } else {

                $message = "Failed to update category.";
                $messageType = "error";
            }

            $stmt->close();
        }
    }


    /*
    |--------------------------------------------------------------------------
    | DELETE
    |--------------------------------------------------------------------------
    */

    if ($action === 'delete') {

        $categoryId = (int)($_POST['category_id'] ?? 0);

        if ($categoryId > 0) {

            /*
            | Check whether category is being used
            */

            $check = $conn->prepare("
                SELECT COUNT(*) AS item_count
                FROM purchase_items
                WHERE category_id = ?
            ");

            $check->bind_param(
                "i",
                $categoryId
            );

            $check->execute();

            $checkResult = $check->get_result();
            $usage = $checkResult->fetch_assoc();

            $check->close();


            if ((int)$usage['item_count'] > 0) {

                $message =
                    "This category is already used by purchase items, so it cannot be deleted.";

                $messageType = "error";

            } else {

                $stmt = $conn->prepare("
                    DELETE FROM categories
                    WHERE id = ?
                ");

                $stmt->bind_param(
                    "i",
                    $categoryId
                );

                if ($stmt->execute()) {

                    $message =
                        "Category deleted successfully.";

                    $messageType = "success";

                } else {

                    $message =
                        "Failed to delete category.";

                    $messageType = "error";
                }

                $stmt->close();
            }
        }
    }
}


/*
|--------------------------------------------------------------------------
| GET CATEGORIES
|--------------------------------------------------------------------------
*/

$categories = [];

$query = "
    SELECT
        c.id,
        c.category_name,
        c.description,
        c.created_at,
        COUNT(pi.id) AS item_count
    FROM categories c
    LEFT JOIN purchase_items pi
        ON c.id = pi.category_id
    GROUP BY
        c.id,
        c.category_name,
        c.description,
        c.created_at
    ORDER BY c.category_name ASC
";

$result = mysqli_query($conn, $query);

if ($result) {

    while ($row = mysqli_fetch_assoc($result)) {
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

    <title>Categories - Grocery Manager</title>

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

        .add-category {
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

        .category-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 18px;
        }

        .category-card {
            background: #181818;
            border: 1px solid #292929;
            border-radius: 14px;
            padding: 22px;
            position: relative;
        }

        .category-icon {
            font-size: 30px;
            margin-bottom: 12px;
        }

        .category-card h3 {
            margin-bottom: 5px;
        }

        .category-card p {
            color: #888;
            min-height: 20px;
        }

        .category-count {
            color: #d4af37 !important;
            font-size: 13px;
        }

        .category-actions {
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

            .category-grid {
                grid-template-columns: 1fr 1fr;
            }

        }


        @media(max-width: 500px) {

            .page-content {
                padding: 18px;
            }

            .category-grid {
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
            Categories
        </h1>

        <p>
            Manage grocery and expense categories.
        </p>

    </div>


    <!-- MESSAGE -->

    <?php if ($message !== ''): ?>

        <div class="message <?php echo $messageType; ?>">

            <?php
            echo htmlspecialchars($message);
            ?>

        </div>

    <?php endif; ?>


    <!-- ADD BUTTON -->

    <button
        type="button"
        class="add-category"
        onclick="openAddModal()"
    >
        + Add Category
    </button>


    <!-- CATEGORY GRID -->

    <div class="category-grid">


        <?php if (count($categories) > 0): ?>


            <?php foreach ($categories as $category): ?>

                <?php

                $name = $category['category_name'];

                $icon =
                    $categoryIcons[$name]
                    ?? '📦';

                ?>

                <div class="category-card">


                    <div class="category-icon">

                        <?php
                        echo $icon;
                        ?>

                    </div>


                    <h3>

                        <?php
                        echo htmlspecialchars($name);
                        ?>

                    </h3>


                    <p>

                        <?php

                        if (!empty($category['description'])) {

                            echo htmlspecialchars(
                                $category['description']
                            );

                        } else {

                            echo 'No description';

                        }

                        ?>

                    </p>


                    <p class="category-count">

                        <?php

                        echo (int)$category['item_count'];

                        ?>

                        <?php

                        echo (
                            (int)$category['item_count'] == 1
                        )
                            ? ' item purchased'
                            : ' items purchased';

                        ?>

                    </p>


                    <!-- ACTIONS -->

                    <div class="category-actions">


                        <button
                            type="button"
                            class="edit-btn"
                            onclick='openEditModal(
                                <?php echo json_encode($category); ?>
                            )'
                        >
                            Edit
                        </button>


                        <form
                            method="POST"
                            style="display:inline;"
                            onsubmit="return confirm(
                                'Are you sure you want to delete this category?'
                            );"
                        >

                            <input
                                type="hidden"
                                name="action"
                                value="delete"
                            >

                            <input
                                type="hidden"
                                name="category_id"
                                value="<?php
                                echo $category['id'];
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


            <div class="category-card">

                <h3>
                    No Categories
                </h3>

                <p>
                    Add your first category.
                </p>

            </div>


        <?php endif; ?>


    </div>

</div>


<!-- ADD MODAL -->

<div
    class="modal"
    id="addModal"
>

    <div class="modal-box">

        <h2>
            Add Category
        </h2>


        <form method="POST">

            <input
                type="hidden"
                name="action"
                value="add"
            >


            <div class="form-group">

                <label>
                    Category Name
                </label>

                <input
                    type="text"
                    name="category_name"
                    placeholder="e.g. Bakery"
                    required
                >

            </div>


            <div class="form-group">

                <label>
                    Description
                </label>

                <textarea
                    name="description"
                    placeholder="Category description..."
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
                    Save Category
                </button>

            </div>

        </form>

    </div>

</div>


<!-- EDIT MODAL -->

<div
    class="modal"
    id="editModal"
>

    <div class="modal-box">

        <h2>
            Edit Category
        </h2>


        <form method="POST">

            <input
                type="hidden"
                name="action"
                value="edit"
            >


            <input
                type="hidden"
                name="category_id"
                id="edit_category_id"
            >


            <div class="form-group">

                <label>
                    Category Name
                </label>

                <input
                    type="text"
                    name="category_name"
                    id="edit_category_name"
                    required
                >

            </div>


            <div class="form-group">

                <label>
                    Description
                </label>

                <textarea
                    name="description"
                    id="edit_description"
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
                    Update Category
                </button>

            </div>

        </form>

    </div>

</div>


<script>

function openAddModal() {

    document
        .getElementById('addModal')
        .classList
        .add('active');

}


function closeAddModal() {

    document
        .getElementById('addModal')
        .classList
        .remove('active');

}


function openEditModal(category) {

    document.getElementById(
        'edit_category_id'
    ).value = category.id;


    document.getElementById(
        'edit_category_name'
    ).value = category.category_name;


    document.getElementById(
        'edit_description'
    ).value = category.description || '';


    document
        .getElementById('editModal')
        .classList
        .add('active');

}


function closeEditModal() {

    document
        .getElementById('editModal')
        .classList
        .remove('active');

}


/*
|--------------------------------------------------------------------------
| Close modal by clicking outside
|--------------------------------------------------------------------------
*/

window.onclick = function(event) {

    const addModal =
        document.getElementById('addModal');

    const editModal =
        document.getElementById('editModal');


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