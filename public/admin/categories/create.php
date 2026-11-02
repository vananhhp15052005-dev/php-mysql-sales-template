<?php

$pageTitle = 'Thêm danh mục';
require_once '/var/www/src/config/database.php';

/**
 * Escape dữ liệu trước khi in ra HTML.
 */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

$errors = [];

// Giá trị hiện tại của form (giữ lại khi có lỗi)
$categoryName = '';
$description  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $categoryName = trim($_POST['category_name'] ?? '');
    $description  = trim($_POST['description'] ?? '');

    // Validate
    if ($categoryName === '') {
        $errors[] = 'Tên danh mục không được để trống.';
    } elseif (mb_strlen($categoryName) > 100) {
        $errors[] = 'Tên danh mục không được vượt quá 100 ký tự.';
    }

    // Lưu vào database
    if (empty($errors)) {
        $stmt = $connection->prepare(
            'INSERT INTO categories (CategoryName, Description) VALUES (?, ?)'
        );

        if ($stmt === false) {
            $errors[] = 'Lỗi hệ thống, vui lòng thử lại sau.';
        } else {
            $stmt->bind_param('ss', $categoryName, $description);
            $success = $stmt->execute();
            $stmt->close();

            if ($success) {
                header('Location: /admin/categories/');
                exit;
            }

            $errors[] = 'Không thể thêm danh mục.';
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
                <div class="card-header bg-white py-3">
                    <h4 class="mb-0">Thêm danh mục</h4>
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
                            <button type="submit" class="btn btn-primary px-4">
                                Lưu
                            </button>
                        </div>

                    </form>

                </div>
            </div>

        </div>
    </div>
</div>

<?php require_once '/var/www/src/includes/admin/footer.php'; ?>