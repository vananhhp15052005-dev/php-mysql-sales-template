<?php

$pageTitle = 'Thêm sản phẩm';
require_once '/var/www/src/config/database.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

/* =====================================================
 * CẤU HÌNH
 * ===================================================== */
const MAX_IMAGES     = 4;
const MAX_IMAGE_SIZE = 2 * 1024 * 1024;               // 2 MB
const UPLOAD_DIR     = '/var/www/html/upload/products/';

// Giới hạn độ dài (chỉnh cho khớp với cột trong database)
const MAX_CODE_LENGTH = 50;
const MAX_NAME_LENGTH = 200;
const MAX_UNIT_LENGTH = 30;

const ALLOWED_IMAGE_TYPES = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
];

/**
 * Lỗi có thông báo thân thiện, được phép hiển thị cho người dùng.
 */
final class SaveException extends RuntimeException
{
}

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

/* =====================================================
 * DỮ LIỆU CHO CÁC Ô CHỌN
 * ===================================================== */
$categories = $connection
    ->query('SELECT CategoryID, CategoryName FROM categories ORDER BY CategoryName')
    ->fetch_all(MYSQLI_ASSOC);

$suppliers = $connection
    ->query('SELECT SupplierID, SupplierName FROM suppliers ORDER BY SupplierName')
    ->fetch_all(MYSQLI_ASSOC);

$categoryIDs = array_map('intval', array_column($categories, 'CategoryID'));
$supplierIDs = array_map('intval', array_column($suppliers, 'SupplierID'));

/* =====================================================
 * GIÁ TRỊ MẶC ĐỊNH CỦA FORM
 * ===================================================== */
$errors = [];   // lỗi theo từng trường, riêng 'form' là lỗi chung
$data   = [
    'product_code'   => '',
    'product_name'   => '',
    'description'    => '',
    'unit'           => '',
    'price'          => '0',
    'stock_quantity' => '0',
    'category_id'    => '',
    'supplier_id'    => '',
    'is_active'      => true,
];

/* =====================================================
 * XỬ LÝ FORM (chạy trước khi in HTML để redirect được)
 * ===================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    foreach (['product_code', 'product_name', 'description', 'unit', 'price', 'stock_quantity', 'category_id', 'supplier_id'] as $field) {
        $data[$field] = trim($_POST[$field] ?? '');
    }
    $data['is_active'] = isset($_POST['is_active']);

    // Mã sản phẩm
    if ($data['product_code'] === '') {
        $errors['product_code'] = 'Mã sản phẩm không được để trống.';
    } elseif (mb_strlen($data['product_code']) > MAX_CODE_LENGTH) {
        $errors['product_code'] = 'Mã sản phẩm không được dài quá ' . MAX_CODE_LENGTH . ' ký tự.';
    }

    // Tên sản phẩm
    if ($data['product_name'] === '') {
        $errors['product_name'] = 'Tên sản phẩm không được để trống.';
    } elseif (mb_strlen($data['product_name']) > MAX_NAME_LENGTH) {
        $errors['product_name'] = 'Tên sản phẩm không được dài quá ' . MAX_NAME_LENGTH . ' ký tự.';
    }

    // Đơn vị tính
    if (mb_strlen($data['unit']) > MAX_UNIT_LENGTH) {
        $errors['unit'] = 'Đơn vị tính không được dài quá ' . MAX_UNIT_LENGTH . ' ký tự.';
    }

    // Giá
    if (!is_numeric($data['price']) || (float) $data['price'] < 0) {
        $errors['price'] = 'Giá sản phẩm không hợp lệ.';
    }

    // Tồn kho
    $stockOptions = ['options' => ['min_range' => 0]];
    if (filter_var($data['stock_quantity'], FILTER_VALIDATE_INT, $stockOptions) === false) {
        $errors['stock_quantity'] = 'Số lượng tồn kho không hợp lệ.';
    }

    // Danh mục & nhà cung cấp (phải tồn tại trong database)
    if (!in_array((int) $data['category_id'], $categoryIDs, true)) {
        $errors['category_id'] = 'Vui lòng chọn danh mục.';
    }

    if (!in_array((int) $data['supplier_id'], $supplierIDs, true)) {
        $errors['supplier_id'] = 'Vui lòng chọn nhà cung cấp.';
    }

    // Hình ảnh
    $files          = $_FILES['product_images'] ?? null;
    $preparedImages = [];

    if (
        !$files
        || !is_array($files['name'])
        || ($files['error'][0] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE
    ) {
        $errors['product_images'] = 'Vui lòng chọn ảnh sản phẩm.';
    } elseif (count($files['name']) > MAX_IMAGES) {
        $errors['product_images'] = 'Chỉ được chọn từ 1 đến ' . MAX_IMAGES . ' ảnh.';
    } else {
        $finfo = new finfo(FILEINFO_MIME_TYPE);

        foreach ($files['name'] as $i => $_) {
            if ($files['error'][$i] !== UPLOAD_ERR_OK) {
                $errors['product_images'] = 'Có file ảnh upload không thành công.';
                break;
            }

            if ($files['size'][$i] > MAX_IMAGE_SIZE) {
                $errors['product_images'] = 'Mỗi file ảnh không được vượt quá 2 MB.';
                break;
            }

            $mimeType = $finfo->file($files['tmp_name'][$i]);

            if (!isset(ALLOWED_IMAGE_TYPES[$mimeType])) {
                $errors['product_images'] = 'Chỉ cho phép file JPG, PNG hoặc WebP.';
                break;
            }

            $preparedImages[] = [
                'tmp_name'   => $files['tmp_name'][$i],
                'file_name'  => 'product-' . bin2hex(random_bytes(8)) . '.' . ALLOWED_IMAGE_TYPES[$mimeType],
                'is_primary' => $i === 0 ? 1 : 0,
                'sort_order' => $i + 1,
            ];
        }
    }

    // Lưu vào database
    if (empty($errors)) {
        $movedFiles = [];

        try {
            $connection->begin_transaction();

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
                'INSERT INTO products
                    (ProductCode, ProductName, Description, Unit, Price,
                     StockQuantity, IsActive, SupplierID, CategoryID)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->bind_param(
                'ssssdiiii',
                $productCode,
                $productName,
                $description,
                $unit,
                $price,
                $stock,
                $isActive,
                $supplierID,
                $categoryID
            );
            $stmt->execute();

            $productID = $connection->insert_id;
            $stmt->close();

            $stmtImage = $connection->prepare(
                'INSERT INTO product_images
                    (ProductID, ImageFile, AltText, IsPrimary, SortOrder)
                 VALUES (?, ?, ?, ?, ?)'
            );

            foreach ($preparedImages as $index => $image) {
                $destination = UPLOAD_DIR . $image['file_name'];

                if (!move_uploaded_file($image['tmp_name'], $destination)) {
                    throw new SaveException('Không thể lưu một trong các file ảnh.');
                }

                $movedFiles[] = $destination;

                $imageFile = $image['file_name'];
                $isPrimary = $image['is_primary'];
                $sortOrder = $image['sort_order'];
                $altText   = $productName . ($isPrimary === 1
                    ? ' - ảnh chính'
                    : ' - ảnh ' . ($index + 1));

                $stmtImage->bind_param(
                    'issii',
                    $productID,
                    $imageFile,
                    $altText,
                    $isPrimary,
                    $sortOrder
                );
                $stmtImage->execute();
            }

            $stmtImage->close();
            $connection->commit();

            header('Location: /admin/products/');
            exit;
        } catch (Throwable $ex) {
            $connection->rollback();

            // Xóa các file ảnh đã lưu nếu giao dịch thất bại
            foreach ($movedFiles as $movedFile) {
                if (file_exists($movedFile)) {
                    unlink($movedFile);
                }
            }

            if ($ex instanceof SaveException) {
                $errors['form'] = $ex->getMessage();
            } elseif ($ex instanceof mysqli_sql_exception && $ex->getCode() === 1062) {
                $errors['product_code'] = 'Mã sản phẩm đã tồn tại.';
            } else {
                error_log('Product create failed: ' . $ex->getMessage());
                $errors['form'] = 'Có lỗi hệ thống khi lưu sản phẩm. Vui lòng thử lại.';
            }
        }
    }
}

require_once '/var/www/src/includes/admin/header.php';
require_once '/var/www/src/includes/admin/navbar.php';

?>

<div class="container mt-4 mb-5">
    <div class="row justify-content-center">
        <div class="col-xl-10">

            <form method="post" enctype="multipart/form-data" class="card border-0 shadow-sm" novalidate>

                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <div>
                        <h2 class="h4 mb-1">Thêm sản phẩm</h2>
                        <p class="text-muted small mb-0">Nhập thông tin sản phẩm mới</p>
                    </div>
                    <a href="/admin/products/" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-arrow-left me-1"></i>Quay lại
                    </a>
                </div>

                <div class="card-body p-4">

                    <?php if (isset($errors['form'])) : ?>
                        <div class="alert alert-danger" role="alert">
                            <?= e($errors['form']) ?>
                        </div>
                    <?php elseif (!empty($errors)) : ?>
                        <div class="alert alert-danger" role="alert">
                            Vui lòng kiểm tra lại các trường được đánh dấu màu đỏ.
                        </div>
                    <?php endif; ?>

                    <!-- ============ Thông tin cơ bản ============ -->
                    <h6 class="text-uppercase text-muted small fw-semibold mb-3">Thông tin cơ bản</h6>

                    <div class="row g-3">

                        <div class="col-md-4">
                            <label for="productCode" class="form-label">
                                Mã sản phẩm <span class="text-danger">*</span>
                            </label>
                            <input
                                type="text"
                                class="form-control<?= invalidClass($errors, 'product_code') ?>"
                                id="productCode"
                                name="product_code"
                                maxlength="<?= MAX_CODE_LENGTH ?>"
                                placeholder="Ví dụ: SP001"
                                value="<?= e($data['product_code']) ?>"
                                required
                                autofocus
                            >
                            <?= fieldError($errors, 'product_code') ?>
                        </div>

                        <div class="col-md-8">
                            <label for="productName" class="form-label">
                                Tên sản phẩm <span class="text-danger">*</span>
                            </label>
                            <input
                                type="text"
                                class="form-control<?= invalidClass($errors, 'product_name') ?>"
                                id="productName"
                                name="product_name"
                                maxlength="<?= MAX_NAME_LENGTH ?>"
                                placeholder="Nhập tên sản phẩm"
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
                            <div class="input-group">
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
                            <label for="stockQuantity" class="form-label">
                                Tồn kho <span class="text-danger">*</span>
                            </label>
                            <input
                                type="number"
                                class="form-control<?= invalidClass($errors, 'stock_quantity') ?>"
                                id="stockQuantity"
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
                            <label for="categoryID" class="form-label">
                                Danh mục <span class="text-danger">*</span>
                            </label>
                            <select
                                class="form-select<?= invalidClass($errors, 'category_id') ?>"
                                id="categoryID"
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
                            <label for="supplierID" class="form-label">
                                Nhà cung cấp <span class="text-danger">*</span>
                            </label>
                            <select
                                class="form-select<?= invalidClass($errors, 'supplier_id') ?>"
                                id="supplierID"
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

                    <!-- ============ Hình ảnh ============ -->
                    <h6 class="text-uppercase text-muted small fw-semibold mb-3">Hình ảnh</h6>

                    <div class="mb-3">
                        <label for="productImages" class="form-label">
                            Hình ảnh sản phẩm <span class="text-danger">*</span>
                        </label>
                        <div class="input-group has-validation">
                            <input
                                type="file"
                                class="form-control<?= invalidClass($errors, 'product_images') ?>"
                                id="productImages"
                                name="product_images[]"
                                accept="image/jpeg,image/png,image/webp"
                                multiple
                                required
                            >
                            <?= fieldError($errors, 'product_images') ?>
                        </div>
                        <div class="form-text">
                            Chọn từ 1 đến <?= MAX_IMAGES ?> ảnh (JPG, PNG hoặc WebP),
                            mỗi ảnh tối đa 2 MB. Ảnh đầu tiên là ảnh chính.
                        </div>
                        <div id="imageWarning" class="text-danger small mt-2 d-none"></div>
                    </div>

                    <div id="imagePreview" class="row g-2 mb-4"></div>

                    <div class="form-check form-switch">
                        <input
                            type="checkbox"
                            class="form-check-input"
                            role="switch"
                            id="isActive"
                            name="is_active"
                            value="1"
                            <?= $data['is_active'] ? 'checked' : '' ?>
                        >
                        <label class="form-check-label" for="isActive">
                            Đang kinh doanh
                        </label>
                    </div>

                </div>

                <div class="card-footer bg-white py-3 d-flex justify-content-end gap-2">
                    <a href="/admin/products/" class="btn btn-outline-secondary">Hủy</a>
                    <button type="submit" class="btn btn-primary px-4">
                        <i class="bi bi-check-lg me-1"></i>Lưu sản phẩm
                    </button>
                </div>

            </form>

        </div>
    </div>
</div>

<script>
// Xem trước ảnh đã chọn và giới hạn số lượng ảnh
(function () {
    const MAX_IMAGES = <?= MAX_IMAGES ?>;
    const input   = document.getElementById('productImages');
    const preview = document.getElementById('imagePreview');
    const warning = document.getElementById('imageWarning');

    input.addEventListener('change', function () {
        preview.innerHTML = '';
        warning.classList.add('d-none');

        const files = Array.from(input.files);

        if (files.length > MAX_IMAGES) {
            input.value = '';
            warning.textContent = 'Chỉ được chọn tối đa ' + MAX_IMAGES + ' ảnh.';
            warning.classList.remove('d-none');
            return;
        }

        files.forEach(function (file, index) {
            const col  = document.createElement('div');
            const wrap = document.createElement('div');
            const img  = document.createElement('img');

            col.className  = 'col-6 col-md-3';
            wrap.className = 'position-relative border rounded overflow-hidden';

            img.className = 'w-100';
            img.alt       = file.name;
            img.style.aspectRatio = '1 / 1';
            img.style.objectFit   = 'cover';
            img.src    = URL.createObjectURL(file);
            img.onload = function () { URL.revokeObjectURL(img.src); };

            wrap.appendChild(img);

            if (index === 0) {
                const badge = document.createElement('span');
                badge.className   = 'badge text-bg-primary position-absolute top-0 start-0 m-2';
                badge.textContent = 'Ảnh chính';
                wrap.appendChild(badge);
            }

            col.appendChild(wrap);
            preview.appendChild(col);
        });
    });
})();
</script>

<?php

require_once '/var/www/src/includes/admin/footer.php';

$connection->close();