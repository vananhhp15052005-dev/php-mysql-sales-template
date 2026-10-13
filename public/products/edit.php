
<?php
// ========================================
// 1. Kết nối cơ sở dữ liệu
// ========================================

// Thay đường dẫn này bằng file kết nối thực tế của dự án.
require_once __DIR__ . '/../../config/database.php';

// Lấy mã sản phẩm từ URL
$productID = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$productID || $productID <= 0) {
    http_response_code(400);
    exit('Mã sản phẩm không hợp lệ.');
}

// Thư mục lưu ảnh
$uploadDir = dirname(__DIR__, 2) . '/uploads/products/';
$uploadUrl = '/uploads/products/';

$error = null;


// ========================================
// 2. Xử lý các thao tác POST
// ========================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ------------------------------------
    // 2.1. Đặt ảnh chính
    // ------------------------------------
    if (isset($_POST['set_primary_image'])) {

        $imageID = filter_var(
            $_POST['set_primary_image'],
            FILTER_VALIDATE_INT
        );

        try {
            if (!$imageID || $imageID <= 0) {
                throw new Exception('Mã hình ảnh không hợp lệ.');
            }

            $conn->begin_transaction();

            // Kiểm tra ảnh có thuộc sản phẩm không
            $sqlCheck = "
                SELECT ProductImageID
                FROM product_images
                WHERE ProductImageID = ?
                  AND ProductID = ?
            ";

            $stmtCheck = $conn->prepare($sqlCheck);
            $stmtCheck->bind_param('ii', $imageID, $productID);
            $stmtCheck->execute();

            $validImage = $stmtCheck->get_result()->num_rows === 1;
            $stmtCheck->close();

            if (!$validImage) {
                throw new Exception(
                    'Hình ảnh không thuộc sản phẩm này.'
                );
            }

            // Bỏ ảnh chính hiện tại
            $sqlReset = "
                UPDATE product_images
                SET IsPrimary = 0
                WHERE ProductID = ?
            ";

            $stmtReset = $conn->prepare($sqlReset);
            $stmtReset->bind_param('i', $productID);
            $stmtReset->execute();
            $stmtReset->close();

            // Đặt ảnh được chọn làm ảnh chính
            $sqlPrimary = "
                UPDATE product_images
                SET IsPrimary = 1
                WHERE ProductImageID = ?
                  AND ProductID = ?
            ";

            $stmtPrimary = $conn->prepare($sqlPrimary);
            $stmtPrimary->bind_param(
                'ii',
                $imageID,
                $productID
            );
            $stmtPrimary->execute();

            if ($stmtPrimary->affected_rows !== 1) {
                $stmtPrimary->close();
                throw new Exception('Không thể đặt ảnh chính.');
            }

            $stmtPrimary->close();
            $conn->commit();

            header(
                'Location: /products/edit.php?id='
                . $productID
                . '&primary_updated=1'
            );
            exit;

        } catch (Throwable $e) {
            $conn->rollback();
            $error = $e->getMessage();
        }
    }


    // ------------------------------------
    // 2.2. Cập nhật thông tin sản phẩm
    // ------------------------------------
    elseif (isset($_POST['update_product'])) {

        $productCode = trim($_POST['ProductCode'] ?? '');
        $productName = trim($_POST['ProductName'] ?? '');
        $description = trim($_POST['Description'] ?? '');
        $unit = trim($_POST['Unit'] ?? '');

        $price = filter_var(
            $_POST['Price'] ?? null,
            FILTER_VALIDATE_FLOAT
        );

        $stockQuantity = filter_var(
            $_POST['StockQuantity'] ?? null,
            FILTER_VALIDATE_INT
        );

        $isActive = filter_var(
            $_POST['IsActive'] ?? null,
            FILTER_VALIDATE_INT
        );

        $supplierID = filter_var(
            $_POST['SupplierID'] ?? null,
            FILTER_VALIDATE_INT
        );

        $categoryID = filter_var(
            $_POST['CategoryID'] ?? null,
            FILTER_VALIDATE_INT
        );

        if (
            $productCode === '' ||
            $productName === '' ||
            $price === false || $price === null || $price < 0 ||
            $stockQuantity === false ||
            $stockQuantity === null || $stockQuantity < 0 ||
            !in_array($isActive, [0, 1], true) ||
            !$supplierID || $supplierID <= 0 ||
            !$categoryID || $categoryID <= 0
        ) {
            $error = 'Vui lòng kiểm tra lại thông tin sản phẩm.';
        } else {
            $sql = "
                UPDATE products
                SET
                    ProductCode = ?,
                    ProductName = ?,
                    Description = ?,
                    Unit = ?,
                    Price = ?,
                    StockQuantity = ?,
                    IsActive = ?,
                    SupplierID = ?,
                    CategoryID = ?
                WHERE ProductID = ?
            ";

            $stmt = $conn->prepare($sql);

            $stmt->bind_param(
                'ssssdiiiii',
                $productCode,
                $productName,
                $description,
                $unit,
                $price,
                $stockQuantity,
                $isActive,
                $supplierID,
                $categoryID,
                $productID
            );

            if ($stmt->execute()) {
                $stmt->close();

                header(
                    'Location: /products/edit.php?id='
                    . $productID
                    . '&updated=1'
                );
                exit;
            }

            $error = 'Lỗi cập nhật sản phẩm: ' . $stmt->error;
            $stmt->close();
        }
    }


    // ------------------------------------
    // 2.3. Tải lên nhiều hình ảnh
    // ------------------------------------
    elseif (isset($_POST['add_images'])) {

        try {
            if (
                !isset($_FILES['product_images']) ||
                !is_array($_FILES['product_images']['name'])
            ) {
                throw new Exception('Vui lòng chọn hình ảnh.');
            }

            $files = $_FILES['product_images'];
            $validFiles = [];

            // Chỉ cho phép JPEG, PNG và WebP
            $allowedTypes = [
                'image/jpeg' => 'jpg',
                'image/png'  => 'png',
                'image/webp' => 'webp'
            ];

            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $count = count($files['name']);

            if ($count === 0 || $count > 20) {
                throw new Exception(
                    'Vui lòng chọn từ 1 đến 20 hình ảnh mỗi lần.'
                );
            }

            // Kiểm tra toàn bộ ảnh trước khi lưu
            for ($i = 0; $i < $count; $i++) {

                if ($files['error'][$i] === UPLOAD_ERR_NO_FILE) {
                    continue;
                }

                if ($files['error'][$i] !== UPLOAD_ERR_OK) {
                    throw new Exception(
                        'Có lỗi khi tải lên tệp ảnh.'
                    );
                }

                if ($files['size'][$i] > 2 * 1024 * 1024) {
                    throw new Exception(
                        'Mỗi ảnh không được vượt quá 2 MB.'
                    );
                }

                if (!is_uploaded_file($files['tmp_name'][$i])) {
                    throw new Exception('Tệp tải lên không hợp lệ.');
                }

                $mime = $finfo->file($files['tmp_name'][$i]);

                if (!isset($allowedTypes[$mime])) {
                    throw new Exception(
                        'Chỉ chấp nhận ảnh JPG, PNG hoặc WebP.'
                    );
                }

                $validFiles[] = [
                    'tmp' => $files['tmp_name'][$i],
                    'extension' => $allowedTypes[$mime],
                    'alt' => pathinfo(
                        $files['name'][$i],
                        PATHINFO_FILENAME
                    )
                ];
            }

            if (count($validFiles) === 0) {
                throw new Exception('Bạn chưa chọn ảnh hợp lệ.');
            }

            if (!is_dir($uploadDir)) {
                if (!mkdir($uploadDir, 0755, true) &&
                    !is_dir($uploadDir)) {
                    throw new Exception(
                        'Không thể tạo thư mục lưu ảnh.'
                    );
                }
            }

            if (!is_writable($uploadDir)) {
                throw new Exception(
                    'Thư mục lưu ảnh không có quyền ghi.'
                );
            }

            // Xác định thứ tự ảnh tiếp theo
            $sqlOrder = "
                SELECT COALESCE(MAX(SortOrder), 0) AS MaxOrder
                FROM product_images
                WHERE ProductID = ?
            ";

            $stmtOrder = $conn->prepare($sqlOrder);
            $stmtOrder->bind_param('i', $productID);
            $stmtOrder->execute();

            $maxOrder = (int) $stmtOrder
                ->get_result()
                ->fetch_assoc()['MaxOrder'];

            $stmtOrder->close();

            // Kiểm tra sản phẩm đã có ảnh chính chưa
            $sqlExisting = "
                SELECT COUNT(*) AS Total
                FROM product_images
                WHERE ProductID = ?
                  AND IsPrimary = 1
            ";

            $stmtExisting = $conn->prepare($sqlExisting);
            $stmtExisting->bind_param('i', $productID);
            $stmtExisting->execute();

            $hasPrimary = (int) $stmtExisting
                ->get_result()
                ->fetch_assoc()['Total'] > 0;

            $stmtExisting->close();

            $savedFiles = [];
            $conn->begin_transaction();

            try {
                foreach ($validFiles as $index => $file) {

                    // Tạo tên tệp ngẫu nhiên để tránh trùng tên
                    $filename = bin2hex(random_bytes(16))
                        . '.' . $file['extension'];

                    $destination = $uploadDir . $filename;

                    if (!move_uploaded_file(
                        $file['tmp'],
                        $destination
                    )) {
                        throw new Exception(
                            'Không thể lưu tệp hình ảnh.'
                        );
                    }

                    $savedFiles[] = $destination;

                    // Ảnh đầu tiên là ảnh chính nếu sản phẩm chưa có
                    $isPrimary = (!$hasPrimary && $index === 0)
                        ? 1 : 0;

                    $sortOrder = ++$maxOrder;

                    $altText = trim($file['alt']);
                    if ($altText === '') {
                        $altText = 'Hình ảnh sản phẩm';
                    }

                    $sqlInsert = "
                        INSERT INTO product_images
                            (ProductID, ImageFile, AltText,
                             IsPrimary, SortOrder)
                        VALUES (?, ?, ?, ?, ?)
                    ";

                    $stmtInsert = $conn->prepare($sqlInsert);
                    $stmtInsert->bind_param(
                        'issii',
                        $productID,
                        $filename,
                        $altText,
                        $isPrimary,
                        $sortOrder
                    );

                    if (!$stmtInsert->execute()) {
                        $stmtInsert->close();
                        throw new Exception(
                            'Không thể lưu thông tin hình ảnh.'
                        );
                    }

                    $stmtInsert->close();
                }

                $conn->commit();

            } catch (Throwable $e) {
                $conn->rollback();

                // Xóa các tệp đã lưu nếu giao dịch thất bại
                foreach ($savedFiles as $path) {
                    if (is_file($path)) {
                        unlink($path);
                    }
                }

                throw $e;
            }

            header(
                'Location: /products/edit.php?id='
                . $productID
                . '&images_added=1'
            );
            exit;

        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}


// ========================================
// 3. Lấy thông tin sản phẩm
// ========================================

$sqlProduct = "
    SELECT *
    FROM products
    WHERE ProductID = ?
";

$stmtProduct = $conn->prepare($sqlProduct);
$stmtProduct->bind_param('i', $productID);
$stmtProduct->execute();

$product = $stmtProduct->get_result()->fetch_assoc();
$stmtProduct->close();

if (!$product) {
    http_response_code(404);
    exit('Không tìm thấy sản phẩm.');
}


// ========================================
// 4. Lấy danh sách hình ảnh
// ========================================

$sqlImages = "
    SELECT
        ProductImageID,
        ImageFile,
        AltText,
        IsPrimary,
        SortOrder
    FROM product_images
    WHERE ProductID = ?
    ORDER BY SortOrder, ProductImageID
";

$stmtImages = $conn->prepare($sqlImages);
$stmtImages->bind_param('i', $productID);
$stmtImages->execute();

$productImages = $stmtImages->get_result();

?>

<!-- ========================================
     5. Giao diện chỉnh sửa sản phẩm
     ======================================== -->

<div class="container py-4">

    <h2 class="mb-4">Chỉnh sửa sản phẩm</h2>

    <?php if ($error !== null): ?>
        <div class="alert alert-danger">
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['updated'])): ?>
        <div class="alert alert-success">
            Cập nhật sản phẩm thành công.
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['primary_updated'])
        && $_GET['primary_updated'] === '1'): ?>
        <div class="alert alert-success">
            Đã cập nhật ảnh chính.
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['images_added'])
        && $_GET['images_added'] === '1'): ?>
        <div class="alert alert-success">
            Đã thêm hình ảnh thành công.
        </div>
    <?php endif; ?>


    <!-- Form cập nhật thông tin sản phẩm -->

    <form
        method="post"
        action="/products/edit.php?id=<?= (int) $productID ?>"
    >

        <div class="mb-3">
            <label class="form-label">Mã sản phẩm</label>
            <input
                type="text"
                name="ProductCode"
                class="form-control"
                value="<?= htmlspecialchars(
                    $product['ProductCode'] ?? '',
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
                required
            >
        </div>

        <div class="mb-3">
            <label class="form-label">Tên sản phẩm</label>
            <input
                type="text"
                name="ProductName"
                class="form-control"
                value="<?= htmlspecialchars(
                    $product['ProductName'] ?? '',
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
                required
            >
        </div>

        <div class="mb-3">
            <label class="form-label">Mô tả</label>
            <textarea
                name="Description"
                class="form-control"
            ><?= htmlspecialchars(
                $product['Description'] ?? '',
                ENT_QUOTES,
                'UTF-8'
            ) ?></textarea>
        </div>

        <div class="mb-3">
            <label class="form-label">Đơn vị</label>
            <input
                type="text"
                name="Unit"
                class="form-control"
                value="<?= htmlspecialchars(
                    $product['Unit'] ?? '',
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
            >
        </div>

        <div class="mb-3">
            <label class="form-label">Giá bán</label>
            <input
                type="number"
                name="Price"
                class="form-control"
                min="0"
                step="0.01"
                value="<?= htmlspecialchars(
                    (string) $product['Price'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
                required
            >
        </div>

        <div class="mb-3">
            <label class="form-label">Số lượng</label>
            <input
                type="number"
                name="StockQuantity"
                class="form-control"
                min="0"
                step="1"
                value="<?= (int) $product['StockQuantity'] ?>"
                required
            >
        </div>

        <div class="mb-3">
            <label class="form-label">Trạng thái</label>
            <select name="IsActive" class="form-select">
                <option
                    value="1"
                    <?= (int) $product['IsActive'] === 1
                        ? 'selected' : '' ?>
                >
                    Đang bán
                </option>
                <option
                    value="0"
                    <?= (int) $product['IsActive'] === 0
                        ? 'selected' : '' ?>
                >
                    Ngừng bán
                </option>
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label">Mã nhà cung cấp</label>
            <input
                type="number"
                name="SupplierID"
                class="form-control"
                min="1"
                value="<?= (int) $product['SupplierID'] ?>"
                required
            >
        </div>

        <div class="mb-3">
            <label class="form-label">Mã danh mục</label>
            <input
                type="number"
                name="CategoryID"
                class="form-control"
                min="1"
                value="<?= (int) $product['CategoryID'] ?>"
                required
            >
        </div>

        <button
            type="submit"
            name="update_product"
            value="1"
            class="btn btn-primary"
        >
            Lưu thay đổi
        </button>

    </form>


    <!-- ====================================
         6. Hiển thị hình ảnh hiện có
         ==================================== -->

    <hr class="my-4">

    <h4 class="mb-3">Hình ảnh sản phẩm</h4>

    <div class="row">

        <?php if ($productImages->num_rows > 0): ?>

            <?php while ($image = $productImages->fetch_assoc()): ?>

                <div class="col-md-3 mb-3">

                    <div class="card h-100">

                        <img
                            src="<?= $uploadUrl
                                . rawurlencode($image['ImageFile']) ?>"
                            class="card-img-top"
                            alt="<?= htmlspecialchars(
                                $image['AltText'] ?? '',
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                            style="height: 200px; object-fit: cover;"
                        >

                        <div class="card-body">

                            <small class="text-muted">
                                Thứ tự:
                                <?= (int) $image['SortOrder'] ?>
                            </small>

                            <?php if ((int) $image['IsPrimary'] === 1): ?>

                                <div class="mt-2">
                                    <span class="badge bg-success">
                                        Ảnh chính
                                    </span>
                                </div>

                            <?php else: ?>

                                <form
                                    method="post"
                                    action="/products/edit.php?id=<?= (int) $productID ?>"
                                    class="mt-2"
                                >
                                    <button
                                        type="submit"
                                        class="btn btn-outline-primary btn-sm"
                                        name="set_primary_image"
                                        value="<?= (int) $image['ProductImageID'] ?>"
                                    >
                                        Đặt làm ảnh chính
                                    </button>
                                </form>

                            <?php endif; ?>

                        </div>
                    </div>

                </div>

            <?php endwhile; ?>

        <?php else: ?>

            <div class="col-12">
                <div class="alert alert-info">
                    Sản phẩm chưa có hình ảnh.
                </div>
            </div>

        <?php endif; ?>

    </div>


    <!-- ====================================
         7. Form thêm nhiều hình ảnh
         ==================================== -->

    <form
        method="post"
        action="/products/edit.php?id=<?= (int) $productID ?>"
        enctype="multipart/form-data"
        class="mt-4"
    >

        <div class="mb-3">

            <label for="productImages" class="form-label">
                Thêm hình ảnh
            </label>

            <input
                type="file"
                class="form-control"
                id="productImages"
                name="product_images[]"
                accept="image/jpeg,image/png,image/webp"
                multiple
            >

            <div class="form-text">
                Chấp nhận JPG, PNG hoặc WebP.
                Mỗi ảnh tối đa 2 MB.
                Tối đa 20 ảnh mỗi lần tải lên.
            </div>

        </div>

        <button
            type="submit"
            class="btn btn-outline-success"
            name="add_images"
            value="1"
        >
            Thêm ảnh
        </button>

    </form>

</div>

<?php
$stmtImages->close();
?>
