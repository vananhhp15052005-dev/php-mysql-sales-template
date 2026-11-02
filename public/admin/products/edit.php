<?php

$pageTitle = 'Sửa sản phẩm';
require_once '/var/www/src/config/database.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

/* =====================================================
 * CẤU HÌNH
 * ===================================================== */
const MAX_IMAGES     = 4;
const MAX_IMAGE_SIZE = 2 * 1024 * 1024;               // 2 MB
const UPLOAD_DIR     = '/var/www/html/upload/products/';
const IMAGE_URL      = '/upload/products/';

// Giới hạn độ dài (chỉnh cho khớp với cột trong database)
const MAX_CODE_LENGTH = 50;
const MAX_NAME_LENGTH = 200;
const MAX_UNIT_LENGTH = 30;

const ALLOWED_IMAGE_TYPES = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
];

const FLASH_MESSAGES = [
    'primary_updated' => 'Đã đặt ảnh chính.',
    'images_added'    => 'Đã thêm ảnh mới.',
    'image_deleted'   => 'Đã xóa ảnh.',
];

/**
 * Lỗi có thông báo thân thiện, được phép hiển thị cho người dùng.
 */
final class SaveException extends RuntimeException
{
}

/* =====================================================
 * HÀM HỖ TRỢ
 * ===================================================== */

/**
 * Escape dữ liệu trước khi in ra HTML.
 */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Trả về class "is-invalid" nếu trường có lỗi.
 */
function invalidClass(array $errors, string $field): string
{
    return isset($errors[$field]) ? ' is-invalid' : '';
}

/**
 * Hiển thị thông báo lỗi ngay dưới trường nhập.
 */
function fieldError(array $errors, string $field): string
{
    return isset($errors[$field])
        ? '<div class="invalid-feedback">' . e($errors[$field]) . '</div>'
        : '';
}

/**
 * Kiểm tra thông tin sản phẩm, trả về mảng lỗi theo từng trường.
 */
function validateProduct(array $data, array $categoryIDs, array $supplierIDs): array
{
    $errors = [];

    if ($data['product_code'] === '') {
        $errors['product_code'] = 'Mã sản phẩm không được để trống.';
    } elseif (mb_strlen($data['product_code']) > MAX_CODE_LENGTH) {
        $errors['product_code'] = 'Mã sản phẩm không được dài quá ' . MAX_CODE_LENGTH . ' ký tự.';
    }

    if ($data['product_name'] === '') {
        $errors['product_name'] = 'Tên sản phẩm không được để trống.';
    } elseif (mb_strlen($data['product_name']) > MAX_NAME_LENGTH) {
        $errors['product_name'] = 'Tên sản phẩm không được dài quá ' . MAX_NAME_LENGTH . ' ký tự.';
    }

    if (mb_strlen($data['unit']) > MAX_UNIT_LENGTH) {
        $errors['unit'] = 'Đơn vị tính không được dài quá ' . MAX_UNIT_LENGTH . ' ký tự.';
    }

    if (!is_numeric($data['price']) || (float) $data['price'] < 0) {
        $errors['price'] = 'Giá sản phẩm không hợp lệ.';
    }

    $stockOptions = ['options' => ['min_range' => 0]];
    if (filter_var($data['stock_quantity'], FILTER_VALIDATE_INT, $stockOptions) === false) {
        $errors['stock_quantity'] = 'Số lượng tồn kho không hợp lệ.';
    }

    if (!in_array((int) $data['category_id'], $categoryIDs, true)) {
        $errors['category_id'] = 'Vui lòng chọn danh mục.';
    }

    if (!in_array((int) $data['supplier_id'], $supplierIDs, true)) {
        $errors['supplier_id'] = 'Vui lòng chọn nhà cung cấp.';
    }

    return $errors;
}

/**
 * Kiểm tra các file ảnh upload.
 * Trả về [danh sách ảnh hợp lệ, thông báo lỗi hoặc null].
 */
function prepareImages(?array $files, int $maxNew): array
{
    if ($maxNew <= 0) {
        return [[], 'Sản phẩm đã đủ ' . MAX_IMAGES . ' ảnh, hãy xóa bớt ảnh cũ trước khi thêm.'];
    }

    if (!$files || !is_array($files['name'])) {
        return [[], 'Vui lòng chọn ít nhất một ảnh.'];
    }

    $finfo  = new finfo(FILEINFO_MIME_TYPE);
    $images = [];

    foreach ($files['name'] as $i => $_) {
        if ($files['error'][$i] === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        if ($files['error'][$i] !== UPLOAD_ERR_OK) {
            return [[], 'Có file ảnh upload không thành công.'];
        }

        if ($files['size'][$i] > MAX_IMAGE_SIZE) {
            return [[], 'Mỗi file ảnh không được vượt quá 2 MB.'];
        }

        $mimeType = $finfo->file($files['tmp_name'][$i]);

        if (!isset(ALLOWED_IMAGE_TYPES[$mimeType])) {
            return [[], 'Chỉ cho phép file JPG, PNG hoặc WebP.'];
        }

        $images[] = [
            'tmp_name'  => $files['tmp_name'][$i],
            'file_name' => 'product-' . bin2hex(random_bytes(8)) . '.' . ALLOWED_IMAGE_TYPES[$mimeType],
        ];
    }

    if (count($images) === 0) {
        return [[], 'Vui lòng chọn ít nhất một ảnh.'];
    }

    if (count($images) > $maxNew) {
        return [[], 'Chỉ có thể thêm tối đa ' . $maxNew . ' ảnh nữa (mỗi sản phẩm tối đa ' . MAX_IMAGES . ' ảnh).'];
    }

    return [$images, null];
}

/**
 * Trả về thông báo lỗi an toàn để hiển thị cho người dùng.
 * Lỗi hệ thống được ghi log, không in chi tiết ra màn hình.
 */
function failureMessage(Throwable $ex, string $logLabel): string
{
    if ($ex instanceof SaveException) {
        return $ex->getMessage();
    }

    error_log($logLabel . ': ' . $ex->getMessage());

    return 'Có lỗi hệ thống. Vui lòng thử lại.';
}

/* =====================================================
 * LẤY SẢN PHẨM HIỆN TẠI
 * ===================================================== */
$errors    = [];   // lỗi theo từng trường, riêng 'form' là lỗi chung
$productID = (int) ($_GET['id'] ?? 0);
$product   = null;

if ($productID > 0) {
    $stmt = $connection->prepare(
        'SELECT ProductCode, ProductName, Description, Unit, Price,
                StockQuantity, IsActive, SupplierID, CategoryID
         FROM products
         WHERE ProductID = ?'
    );
    $stmt->bind_param('i', $productID);
    $stmt->execute();
    $product = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

if (!$product) {
    http_response_code(404);
}

$categories = [];
$suppliers  = [];
$data       = [];

if ($product) {
    $categories = $connection
        ->query('SELECT CategoryID, CategoryName FROM categories ORDER BY CategoryName')
        ->fetch_all(MYSQLI_ASSOC);

    $suppliers = $connection
        ->query('SELECT SupplierID, SupplierName FROM suppliers ORDER BY SupplierName')
        ->fetch_all(MYSQLI_ASSOC);

    $data = [
        'product_code'   => $product['ProductCode'],
        'product_name'   => $product['ProductName'],
        'description'    => $product['Description'] ?? '',
        'unit'           => $product['Unit'] ?? '',
        'price'          => (string) $product['Price'],
        'stock_quantity' => (string) $product['StockQuantity'],
        'category_id'    => (string) $product['CategoryID'],
        'supplier_id'    => (string) $product['SupplierID'],
        'is_active'      => (int) $product['IsActive'] === 1,
    ];
}

$categoryIDs = array_map('intval', array_column($categories, 'CategoryID'));
$supplierIDs = array_map('intval', array_column($suppliers, 'SupplierID'));
$selfUrl     = '/admin/products/edit.php?id=' . $productID;

/* =====================================================
 * XỬ LÝ FORM (chạy trước khi in HTML để redirect được)
 * ===================================================== */
if ($product && $_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    /* ---------- Cập nhật thông tin sản phẩm ---------- */
    if ($action === 'update_product') {

        foreach (['product_code', 'product_name', 'description', 'unit', 'price', 'stock_quantity', 'category_id', 'supplier_id'] as $field) {
            $data[$field] = trim($_POST[$field] ?? '');
        }
        $data['is_active'] = isset($_POST['is_active']);

        $errors = validateProduct($data, $categoryIDs, $supplierIDs);

        if (empty($errors)) {
            try {
                $productCode = $data['product_code'];
                $productName = $data['product_name'];
                $description = $data['description'];
                $unit        = $data['unit'];
                $price       = (float) $data['price'];
                $stock       = (int) $data['stock_quantity'];
                $isActive    = $data['is_active'] ? 1 : 0;
                $supplierID  = (int) $data['supplier_id'];
                $categoryID  = (int) $data['category_id'];

                $stmt = $connection->prepare(
                    'UPDATE products
                     SET ProductCode = ?, ProductName = ?, Description = ?, Unit = ?,
                         Price = ?, StockQuantity = ?, IsActive = ?,
                         SupplierID = ?, CategoryID = ?
                     WHERE ProductID = ?'
                );
                $stmt->bind_param(
                    'ssssdiiiii',
                    $productCode,
                    $productName,
                    $description,
                    $unit,
                    $price,
                    $stock,
                    $isActive,
                    $supplierID,
                    $categoryID,
                    $productID
                );
                $stmt->execute();
                $stmt->close();

                header('Location: /admin/products/?msg=updated');
                exit;
            } catch (Throwable $ex) {
                if ($ex instanceof mysqli_sql_exception && $ex->getCode() === 1062) {
                    $errors['product_code'] = 'Mã sản phẩm đã tồn tại.';
                } else {
                    $errors['form'] = failureMessage($ex, 'Product update failed');
                }
            }
        }
    }

    /* ---------- Đặt ảnh chính ---------- */
    elseif ($action === 'set_primary') {

        $imageID = (int) ($_POST['image_id'] ?? 0);

        try {
            $connection->begin_transaction();

            $stmt = $connection->prepare(
                'UPDATE product_images SET IsPrimary = 0 WHERE ProductID = ?'
            );
            $stmt->bind_param('i', $productID);
            $stmt->execute();
            $stmt->close();

            $stmt = $connection->prepare(
                'UPDATE product_images
                 SET IsPrimary = 1
                 WHERE ProductImageID = ? AND ProductID = ?'
            );
            $stmt->bind_param('ii', $imageID, $productID);
            $stmt->execute();

            if ($stmt->affected_rows !== 1) {
                throw new SaveException('Không thể đặt ảnh chính.');
            }

            $stmt->close();
            $connection->commit();

            header('Location: ' . $selfUrl . '&msg=primary_updated');
            exit;
        } catch (Throwable $ex) {
            $connection->rollback();
            $errors['form'] = failureMessage($ex, 'Set primary image failed');
        }
    }

    /* ---------- Thêm ảnh ---------- */
    elseif ($action === 'add_images') {

        $stmt = $connection->prepare(
            'SELECT COUNT(*) AS ImageCount, COALESCE(MAX(SortOrder), 0) AS MaxSortOrder
             FROM product_images
             WHERE ProductID = ?'
        );
        $stmt->bind_param('i', $productID);
        $stmt->execute();
        $imageState = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $currentCount  = (int) $imageState['ImageCount'];
        $nextSortOrder = (int) $imageState['MaxSortOrder'] + 1;

        [$newImages, $imageError] = prepareImages(
            $_FILES['product_images'] ?? null,
            MAX_IMAGES - $currentCount
        );

        if ($imageError !== null) {
            $errors['product_images'] = $imageError;
        } else {
            $movedFiles = [];

            try {
                $connection->begin_transaction();

                $stmt = $connection->prepare(
                    'INSERT INTO product_images
                        (ProductID, ImageFile, AltText, IsPrimary, SortOrder)
                     VALUES (?, ?, ?, ?, ?)'
                );

                foreach ($newImages as $index => $image) {
                    $destination = UPLOAD_DIR . $image['file_name'];

                    if (!move_uploaded_file($image['tmp_name'], $destination)) {
                        throw new SaveException('Không thể lưu một trong các ảnh.');
                    }

                    $movedFiles[] = $destination;

                    // Nếu sản phẩm chưa có ảnh nào, ảnh đầu tiên là ảnh chính
                    $imageFile = $image['file_name'];
                    $isPrimary = ($currentCount === 0 && $index === 0) ? 1 : 0;
                    $sortOrder = $nextSortOrder + $index;
                    $altText   = $product['ProductName'] . ($isPrimary === 1
                        ? ' - ảnh chính'
                        : ' - ảnh ' . $sortOrder);

                    $stmt->bind_param(
                        'issii',
                        $productID,
                        $imageFile,
                        $altText,
                        $isPrimary,
                        $sortOrder
                    );
                    $stmt->execute();
                }

                $stmt->close();
                $connection->commit();

                header('Location: ' . $selfUrl . '&msg=images_added');
                exit;
            } catch (Throwable $ex) {
                $connection->rollback();

                foreach ($movedFiles as $movedFile) {
                    if (file_exists($movedFile)) {
                        unlink($movedFile);
                    }
                }

                $errors['form'] = failureMessage($ex, 'Add product images failed');
            }
        }
    }

    /* ---------- Xóa ảnh ---------- */
    elseif ($action === 'delete_image') {

        $imageID = (int) ($_POST['image_id'] ?? 0);

        try {
            $connection->begin_transaction();

            $stmt = $connection->prepare(
                'SELECT ImageFile, IsPrimary, SortOrder
                 FROM product_images
                 WHERE ProductImageID = ? AND ProductID = ?'
            );
            $stmt->bind_param('ii', $imageID, $productID);
            $stmt->execute();
            $imageToDelete = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$imageToDelete) {
                throw new SaveException('Không tìm thấy ảnh cần xóa.');
            }

            $stmt = $connection->prepare(
                'DELETE FROM product_images
                 WHERE ProductImageID = ? AND ProductID = ?'
            );
            $stmt->bind_param('ii', $imageID, $productID);
            $stmt->execute();

            if ($stmt->affected_rows !== 1) {
                throw new SaveException('Không thể xóa ảnh.');
            }

            $stmt->close();

            // Nếu xóa ảnh chính, chọn ảnh còn lại đầu tiên làm ảnh chính mới
            if ((int) $imageToDelete['IsPrimary'] === 1) {
                $stmt = $connection->prepare(
                    'UPDATE product_images
                     SET IsPrimary = 1
                     WHERE ProductImageID = (
                         SELECT ProductImageID
                         FROM (
                             SELECT ProductImageID
                             FROM product_images
                             WHERE ProductID = ?
                             ORDER BY SortOrder, ProductImageID
                             LIMIT 1
                         ) AS remaining_images
                     )'
                );
                $stmt->bind_param('i', $productID);
                $stmt->execute();
                $stmt->close();
            }

            // Đánh số lại thứ tự các ảnh phía sau
            $deletedSortOrder = (int) $imageToDelete['SortOrder'];

            $stmt = $connection->prepare(
                'UPDATE product_images
                 SET SortOrder = SortOrder - 1
                 WHERE ProductID = ? AND SortOrder > ?'
            );
            $stmt->bind_param('ii', $productID, $deletedSortOrder);
            $stmt->execute();
            $stmt->close();

            $connection->commit();

            // Xóa file sau khi database đã commit thành công
            $filePath = UPLOAD_DIR . basename($imageToDelete['ImageFile']);

            if (is_file($filePath)) {
                unlink($filePath);
            }

            header('Location: ' . $selfUrl . '&msg=image_deleted');
            exit;
        } catch (Throwable $ex) {
            $connection->rollback();
            $errors['form'] = failureMessage($ex, 'Delete product image failed');
        }
    }
}

/* =====================================================
 * DANH SÁCH ẢNH HIỆN TẠI (đọc sau khi xử lý form)
 * ===================================================== */
$productImages = [];

if ($product) {
    $stmt = $connection->prepare(
        'SELECT ProductImageID, ImageFile, AltText, IsPrimary, SortOrder
         FROM product_images
         WHERE ProductID = ?
         ORDER BY SortOrder, ProductImageID'
    );
    $stmt->bind_param('i', $productID);
    $stmt->execute();
    $productImages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$imageCount     = count($productImages);
$remainingSlots = max(0, MAX_IMAGES - $imageCount);
$flashMessage   = FLASH_MESSAGES[$_GET['msg'] ?? ''] ?? null;

require_once '/var/www/src/includes/admin/header.php';
require_once '/var/www/src/includes/admin/navbar.php';

?>

<div class="container mt-4 mb-5">
    <div class="row justify-content-center">
        <div class="col-xl-10">

            <?php if (!$product) : ?>

                <div class="card border-0 shadow-sm">
                    <div class="card-body p-4 text-center">
                        <i class="bi bi-box-seam fs-1 text-muted"></i>
                        <h2 class="h5 mt-3">Không tìm thấy sản phẩm</h2>
                        <p class="text-muted">
                            Sản phẩm này không tồn tại hoặc đã bị xóa.
                        </p>
                        <a href="/admin/products/" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left me-1"></i>Quay lại danh sách
                        </a>
                    </div>
                </div>

            <?php else : ?>

                <?php if ($flashMessage !== null && empty($errors)) : ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?= e($flashMessage) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Đóng"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($errors['form'])) : ?>
                    <div class="alert alert-danger" role="alert">
                        <?= e($errors['form']) ?>
                    </div>
                <?php elseif (!empty($errors)) : ?>
                    <div class="alert alert-danger" role="alert">
                        Vui lòng kiểm tra lại các trường được đánh dấu màu đỏ.
                    </div>
                <?php endif; ?>

                <!-- =====================================================
                     THÔNG TIN SẢN PHẨM
                     ===================================================== -->
                <form method="post" action="<?= e($selfUrl) ?>" class="card border-0 shadow-sm mb-4" novalidate>
                    <input type="hidden" name="action" value="update_product">

                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <div>
                            <h2 class="h4 mb-1">
                                Sửa sản phẩm
                                <span class="badge text-bg-secondary align-middle ms-1">
                                    #<?= (int) $productID ?>
                                </span>
                            </h2>
                            <p class="text-muted small mb-0">Cập nhật thông tin sản phẩm</p>
                        </div>
                        <a href="/admin/products/" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-arrow-left me-1"></i>Quay lại
                        </a>
                    </div>

                    <div class="card-body p-4">

                        <!-- ============ Thông tin cơ bản ============ -->
                        <h6 class="text-uppercase text-muted small fw-semibold mb-3">Thông tin cơ bản</h6>

                        <div class="row g-3">

                            <div class="col-md-4">
                                <label for="product_code" class="form-label">
                                    Mã sản phẩm <span class="text-danger">*</span>
                                </label>
                                <input
                                    type="text"
                                    class="form-control<?= invalidClass($errors, 'product_code') ?>"
                                    id="product_code"
                                    name="product_code"
                                    maxlength="<?= MAX_CODE_LENGTH ?>"
                                    value="<?= e($data['product_code']) ?>"
                                    required
                                >
                                <?= fieldError($errors, 'product_code') ?>
                            </div>

                            <div class="col-md-8">
                                <label for="product_name" class="form-label">
                                    Tên sản phẩm <span class="text-danger">*</span>
                                </label>
                                <input
                                    type="text"
                                    class="form-control<?= invalidClass($errors, 'product_name') ?>"
                                    id="product_name"
                                    name="product_name"
                                    maxlength="<?= MAX_NAME_LENGTH ?>"
                                    value="<?= e($data['product_name']) ?>"
                                    required
                                >
                                <?= fieldError($errors, 'product_name') ?>
                            </div>

                            <div class="col-12">
                                <label for="description" class="form-label">Mô tả</label>
                                <textarea
                                    class="form-control"
                                    id="description"
                                    name="description"
                                    rows="3"
                                    placeholder="Mô tả ngắn về sản phẩm (không bắt buộc)"
                                ><?= e($data['description']) ?></textarea>
                            </div>

                        </div>

                        <hr class="my-4">

                        <!-- ============ Giá & kho ============ -->
                        <h6 class="text-uppercase text-muted small fw-semibold mb-3">Giá &amp; kho</h6>

                        <div class="row g-3">

                            <div class="col-md-4">
                                <label for="unit" class="form-label">Đơn vị tính</label>
                                <input
                                    type="text"
                                    class="form-control<?= invalidClass($errors, 'unit') ?>"
                                    id="unit"
                                    name="unit"
                                    maxlength="<?= MAX_UNIT_LENGTH ?>"
                                    placeholder="Ví dụ: Cái, Hộp, Kg"
                                    value="<?= e($data['unit']) ?>"
                                >
                                <?= fieldError($errors, 'unit') ?>
                            </div>

                            <div class="col-md-4">
                                <label for="price" class="form-label">
                                    Giá <span class="text-danger">*</span>
                                </label>
                                <div class="input-group has-validation">
                                    <input
                                        type="number"
                                        class="form-control<?= invalidClass($errors, 'price') ?>"
                                        id="price"
                                        name="price"
                                        min="0"
                                        step="0.01"
                                        value="<?= e($data['price']) ?>"
                                        required
                                    >
                                    <span class="input-group-text">₫</span>
                                    <?= fieldError($errors, 'price') ?>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <label for="stock_quantity" class="form-label">
                                    Tồn kho <span class="text-danger">*</span>
                                </label>
                                <input
                                    type="number"
                                    class="form-control<?= invalidClass($errors, 'stock_quantity') ?>"
                                    id="stock_quantity"
                                    name="stock_quantity"
                                    min="0"
                                    step="1"
                                    value="<?= e($data['stock_quantity']) ?>"
                                    required
                                >
                                <?= fieldError($errors, 'stock_quantity') ?>
                            </div>

                        </div>

                        <hr class="my-4">

                        <!-- ============ Phân loại ============ -->
                        <h6 class="text-uppercase text-muted small fw-semibold mb-3">Phân loại</h6>

                        <div class="row g-3">

                            <div class="col-md-6">
                                <label for="category_id" class="form-label">
                                    Danh mục <span class="text-danger">*</span>
                                </label>
                                <select
                                    class="form-select<?= invalidClass($errors, 'category_id') ?>"
                                    id="category_id"
                                    name="category_id"
                                    required
                                >
                                    <option value="">-- Chọn danh mục --</option>
                                    <?php foreach ($categories as $category) : ?>
                                        <option
                                            value="<?= (int) $category['CategoryID'] ?>"
                                            <?= (string) $data['category_id'] === (string) $category['CategoryID'] ? 'selected' : '' ?>
                                        >
                                            <?= e($category['CategoryName']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?= fieldError($errors, 'category_id') ?>
                            </div>

                            <div class="col-md-6">
                                <label for="supplier_id" class="form-label">
                                    Nhà cung cấp <span class="text-danger">*</span>
                                </label>
                                <select
                                    class="form-select<?= invalidClass($errors, 'supplier_id') ?>"
                                    id="supplier_id"
                                    name="supplier_id"
                                    required
                                >
                                    <option value="">-- Chọn nhà cung cấp --</option>
                                    <?php foreach ($suppliers as $supplier) : ?>
                                        <option
                                            value="<?= (int) $supplier['SupplierID'] ?>"
                                            <?= (string) $data['supplier_id'] === (string) $supplier['SupplierID'] ? 'selected' : '' ?>
                                        >
                                            <?= e($supplier['SupplierName']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?= fieldError($errors, 'supplier_id') ?>
                            </div>

                        </div>

                        <hr class="my-4">

                        <div class="form-check form-switch">
                            <input
                                type="checkbox"
                                class="form-check-input"
                                role="switch"
                                id="is_active"
                                name="is_active"
                                value="1"
                                <?= $data['is_active'] ? 'checked' : '' ?>
                            >
                            <label class="form-check-label" for="is_active">
                                Đang kinh doanh
                            </label>
                        </div>

                    </div>

                    <div class="card-footer bg-white py-3 d-flex justify-content-end gap-2">
                        <a href="/admin/products/" class="btn btn-outline-secondary">Hủy</a>
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="bi bi-check-lg me-1"></i>Lưu thay đổi
                        </button>
                    </div>

                </form>

                <!-- =====================================================
                     HÌNH ẢNH SẢN PHẨM
                     ===================================================== -->
                <div class="card border-0 shadow-sm">

                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <div>
                            <h3 class="h5 mb-1">Hình ảnh sản phẩm</h3>
                            <p class="text-muted small mb-0">
                                Thao tác với ảnh sẽ tải lại trang, hãy lưu thông tin sản phẩm trước.
                            </p>
                        </div>
                        <span class="badge text-bg-light border">
                            <?= $imageCount ?>/<?= MAX_IMAGES ?> ảnh
                        </span>
                    </div>

                    <div class="card-body p-4">

                        <?php if ($imageCount === 0) : ?>
                            <div class="text-center text-muted py-4">
                                <i class="bi bi-images fs-1"></i>
                                <p class="mb-0 mt-2">Sản phẩm này chưa có ảnh nào.</p>
                            </div>
                        <?php else : ?>
                            <div class="row g-3">
                                <?php foreach ($productImages as $image) : ?>
                                    <?php $isPrimary = (int) $image['IsPrimary'] === 1; ?>

                                    <div class="col-6 col-md-4 col-lg-3">
                                        <div class="card h-100 overflow-hidden<?= $isPrimary ? ' border-primary' : '' ?>">

                                            <div class="position-relative">
                                                <img
                                                    src="<?= e(IMAGE_URL . $image['ImageFile']) ?>"
                                                    alt="<?= e($image['AltText'] ?? '') ?>"
                                                    class="w-100"
                                                    style="aspect-ratio: 1 / 1; object-fit: cover;"
                                                >
                                                <?php if ($isPrimary) : ?>
                                                    <span class="badge text-bg-primary position-absolute top-0 start-0 m-2">
                                                        Ảnh chính
                                                    </span>
                                                <?php endif; ?>
                                            </div>

                                            <div class="card-body p-2 d-flex flex-column gap-2">
                                                <small class="text-muted">
                                                    Thứ tự: <?= (int) $image['SortOrder'] ?>
                                                </small>

                                                <div class="d-flex flex-wrap gap-1 mt-auto">

                                                    <?php if (!$isPrimary) : ?>
                                                        <form method="post" action="<?= e($selfUrl) ?>">
                                                            <input type="hidden" name="action" value="set_primary">
                                                            <input type="hidden" name="image_id" value="<?= (int) $image['ProductImageID'] ?>">
                                                            <button type="submit" class="btn btn-outline-primary btn-sm">
                                                                Đặt làm ảnh chính
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>

                                                    <form
                                                        method="post"
                                                        action="<?= e($selfUrl) ?>"
                                                        onsubmit="return confirm('Bạn có chắc muốn xóa ảnh này?');"
                                                    >
                                                        <input type="hidden" name="action" value="delete_image">
                                                        <input type="hidden" name="image_id" value="<?= (int) $image['ProductImageID'] ?>">
                                                        <button type="submit" class="btn btn-outline-danger btn-sm">
                                                            <i class="bi bi-trash me-1"></i>Xóa
                                                        </button>
                                                    </form>

                                                </div>
                                            </div>

                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <hr class="my-4">

                        <!-- ============ Thêm ảnh ============ -->
                        <?php if ($remainingSlots === 0) : ?>

                            <div class="text-muted">
                                <i class="bi bi-info-circle me-1"></i>
                                Sản phẩm đã đủ <?= MAX_IMAGES ?> ảnh. Hãy xóa bớt ảnh cũ nếu muốn thêm ảnh mới.
                            </div>

                        <?php else : ?>

                            <form
                                method="post"
                                action="<?= e($selfUrl) ?>"
                                enctype="multipart/form-data"
                                novalidate
                            >
                                <input type="hidden" name="action" value="add_images">

                                <label for="productImages" class="form-label">Thêm hình ảnh</label>

                                <div class="input-group has-validation">
                                    <input
                                        type="file"
                                        class="form-control<?= invalidClass($errors, 'product_images') ?>"
                                        id="productImages"
                                        name="product_images[]"
                                        accept="image/jpeg,image/png,image/webp"
                                        multiple
                                    >
                                    <button type="submit" class="btn btn-outline-success">
                                        <i class="bi bi-upload me-1"></i>Thêm ảnh
                                    </button>
                                    <?= fieldError($errors, 'product_images') ?>
                                </div>

                                <div class="form-text">
                                    Còn có thể thêm <?= $remainingSlots ?> ảnh (JPG, PNG hoặc WebP, mỗi ảnh tối đa 2 MB).
                                </div>
                                <div id="imageWarning" class="text-danger small mt-2 d-none"></div>

                                <div id="imagePreview" class="row g-2 mt-1"></div>
                            </form>

                        <?php endif; ?>

                    </div>
                </div>

            <?php endif; ?>

        </div>
    </div>
</div>

<?php if ($product && $remainingSlots > 0) : ?>
    <script>
    // Xem trước ảnh sắp thêm và giới hạn số lượng ảnh
    (function () {
        const MAX_NEW = <?= (int) $remainingSlots ?>;
        const input   = document.getElementById('productImages');
        const preview = document.getElementById('imagePreview');
        const warning = document.getElementById('imageWarning');

        input.addEventListener('change', function () {
            preview.innerHTML = '';
            warning.classList.add('d-none');

            const files = Array.from(input.files);

            if (files.length > MAX_NEW) {
                input.value = '';
                warning.textContent = 'Chỉ có thể thêm tối đa ' + MAX_NEW + ' ảnh nữa.';
                warning.classList.remove('d-none');
                return;
            }

            files.forEach(function (file) {
                const col  = document.createElement('div');
                const wrap = document.createElement('div');
                const img  = document.createElement('img');

                col.className  = 'col-6 col-md-3';
                wrap.className = 'border rounded overflow-hidden';

                img.className = 'w-100';
                img.alt       = file.name;
                img.style.aspectRatio = '1 / 1';
                img.style.objectFit   = 'cover';
                img.src    = URL.createObjectURL(file);
                img.onload = function () { URL.revokeObjectURL(img.src); };

                wrap.appendChild(img);
                col.appendChild(wrap);
                preview.appendChild(col);
            });
        });
    })();
    </script>
<?php endif; ?>

<?php

require_once '/var/www/src/includes/admin/footer.php';

$connection->close();