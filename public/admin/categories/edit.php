<?php

$pageTitle = 'Sửa danh mục';
require_once '/var/www/src/config/database.php';

/**
 * Escape dữ liệu trước khi in ra HTML.
 */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

$errors = [];

// Kiểm tra mã danh mục
$categoryID = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($categoryID <= 0) {
    http_response_code(400);
    die('Mã danh mục không hợp lệ.');
}

// Đọc dữ liệu hiện tại của danh mục
$stmt = $connection->prepare(
    'SELECT CategoryID, CategoryName, Description
     FROM categories
     WHERE CategoryID = ?'
);
$stmt->bind_param('i', $categoryID);
$stmt->execute();
$category = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$category) {
    http_response_code(404);
    die('Không tìm thấy danh mục.');
}

// Giá trị hiển thị trong form (mặc định lấy từ database)
$categoryName = $category['CategoryName'];
$description  = $category['Description'] ?? '';

// Xử lý khi người dùng gửi form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $categoryName = trim($_POST['category_name'] ?? '');
    $description  = trim($_POST['description'] ?? '');

    // Validate
    if ($categoryName === '') {
        $errors[] = 'Tên danh mục không được để trống.';
    } elseif (mb_strlen($categoryName) > 100) {
        $errors[] = 'Tên danh mục không được vượt quá 100 ký tự.';
    }

    // Cập nhật database
    if (empty($errors)) {
        $stmt = $connection->prepare(
            'UPDATE categories
             SET CategoryName = ?, Description = ?
             WHERE CategoryID = ?'
        );

        if ($stmt === false) {
            $errors[] = 'Lỗi hệ thống, vui lòng thử lại sau.';
        } else {
            $stmt->bind_param('ssi', $categoryName, $description, $categoryID);
            $success = $stmt->execute();
            $stmt->close();

            if ($success) {
                header('Location: /admin/categories/');
                exit;
            }

            $errors[] = 'Không thể cập nhật danh mục.';
        }
    }
}

require_once '/var/www/src/includes/admin/header.php';
require_once '/var/www/src/includes/admin/navbar.php';

?>

<div class="container mt-4 mb-5">
    <div class="row justify-content-center">
        <div class="col-lg-8 col-xl-6">

            <div class="card shadow-sm">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">Sửa danh mục</h4>
                    <span class="badge text-bg-secondary">
                        #<?= (int) $category['CategoryID'] ?>
                    </span>
                </div>

                <div class="card-body p-4">

                    <?php if (!empty($errors)) : ?>
                        <div class="alert alert-danger" role="alert">
                            <?php foreach ($errors as $message) : ?>
                                <div><?= e($message) ?></div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <form method="post" novalidate>

                        <div class="mb-3">
                            <label for="categoryID" class="form-label">Mã danh mục</label>
                            <input
                                type="text"
                                class="form-control"
                                id="categoryID"
                                value="<?= (int) $category['CategoryID'] ?>"
                                disabled
                            >
                        </div>

                        <div class="mb-3">
                            <label for="categoryName" class="form-label">
                                Tên danh mục <span class="text-danger">*</span>
                            </label>
                            <input
                                type="text"
                                class="form-control"
                                id="categoryName"
                                name="category_name"
                                maxlength="100"
                                placeholder="Nhập tên danh mục"
                                value="<?= e($categoryName) ?>"
                                required
                                autofocus
                            >
                        </div>

                        <div class="mb-4">
                            <label for="description" class="form-label">Mô tả</label>
                            <textarea
                                class="form-control"
                                id="description"
                                name="description"
                                rows="4"
                                placeholder="Mô tả ngắn về danh mục (không bắt buộc)"
                            ><?= e($description) ?></textarea>
                        </div>

                        <div class="d-flex justify-content-end gap-2">
                            <a href="/admin/categories/" class="btn btn-outline-secondary">
                                Hủy
                            </a>
                            <button type="submit" class="btn btn-warning px-4">
                                Cập nhật
                            </button>
                        </div>

                    </form>

                </div>
            </div>

        </div>
    </div>
</div>

<?php

require_once '/var/www/src/includes/admin/footer.php';

$connection->close();