<?php

$pageTitle = 'Thêm nhân viên';
require_once '/var/www/src/config/database.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

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

$errors = [];   // lỗi theo từng trường, riêng 'form' là lỗi chung
$data   = [
    'LastName'  => '',
    'FirstName' => '',
    'BirthDate' => '',
    'Notes'     => '',
];

/* =====================================================
 * XỬ LÝ FORM (chạy trước khi in HTML để redirect được)
 * ===================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($data as $field => $_) {
        $data[$field] = trim($_POST[$field] ?? '');
    }

    // Họ
    if ($data['LastName'] === '') {
        $errors['LastName'] = 'Vui lòng nhập họ.';
    } elseif (mb_strlen($data['LastName']) > 50) {
        $errors['LastName'] = 'Họ không được dài quá 50 ký tự.';
    }

    // Tên
    if ($data['FirstName'] === '') {
        $errors['FirstName'] = 'Vui lòng nhập tên.';
    } elseif (mb_strlen($data['FirstName']) > 50) {
        $errors['FirstName'] = 'Tên không được dài quá 50 ký tự.';
    }

    // Ngày sinh (không bắt buộc)
    if ($data['BirthDate'] !== '') {
        $date = DateTime::createFromFormat('Y-m-d', $data['BirthDate']);

        if (!$date || $date->format('Y-m-d') !== $data['BirthDate']) {
            $errors['BirthDate'] = 'Ngày sinh không hợp lệ.';
        } elseif ($date > new DateTime('today')) {
            $errors['BirthDate'] = 'Ngày sinh không được ở tương lai.';
        }
    }

    // Lưu vào database
    if (empty($errors)) {
        try {
            $birth = $data['BirthDate'] !== '' ? $data['BirthDate'] : null;
            $notes = $data['Notes'] !== '' ? $data['Notes'] : null;

            $stmt = $connection->prepare(
                'INSERT INTO employees (LastName, FirstName, BirthDate, Notes)
                 VALUES (?, ?, ?, ?)'
            );
            $stmt->bind_param(
                'ssss',
                $data['LastName'],
                $data['FirstName'],
                $birth,
                $notes
            );
            $stmt->execute();
            $stmt->close();

            header('Location: /admin/employees/?msg=created');
            exit;
        } catch (Throwable $ex) {
            error_log('Employee create failed: ' . $ex->getMessage());
            $errors['form'] = 'Có lỗi hệ thống khi lưu nhân viên. Vui lòng thử lại.';
        }
    }
}

require_once '/var/www/src/includes/admin/header.php';
require_once '/var/www/src/includes/admin/navbar.php';

?>

<div class="container mt-4 mb-5">
    <div class="row justify-content-center">
        <div class="col-xl-9">

            <form method="post" class="card border-0 shadow-sm" novalidate>

                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <div>
                        <h2 class="h4 mb-1">Thêm nhân viên</h2>
                        <p class="text-muted small mb-0">Nhập thông tin nhân viên mới</p>
                    </div>
                    <a href="/admin/employees/" class="btn btn-outline-secondary btn-sm">
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

                    <div class="row g-3">

                        <div class="col-md-4">
                            <label class="form-label" for="LastName">
                                Họ <span class="text-danger">*</span>
                            </label>
                            <input
                                type="text"
                                name="LastName"
                                id="LastName"
                                class="form-control<?= invalidClass($errors, 'LastName') ?>"
                                maxlength="50"
                                placeholder="Ví dụ: Nguyễn"
                                value="<?= e($data['LastName']) ?>"
                                required
                                autofocus
                            >
                            <?= fieldError($errors, 'LastName') ?>
                        </div>

                        <div class="col-md-5">
                            <label class="form-label" for="FirstName">
                                Tên <span class="text-danger">*</span>
                            </label>
                            <input
                                type="text"
                                name="FirstName"
                                id="FirstName"
                                class="form-control<?= invalidClass($errors, 'FirstName') ?>"
                                maxlength="50"
                                placeholder="Ví dụ: Hoàng Nam"
                                value="<?= e($data['FirstName']) ?>"
                                required
                            >
                            <?= fieldError($errors, 'FirstName') ?>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label" for="BirthDate">Ngày sinh</label>
                            <input
                                type="date"
                                name="BirthDate"
                                id="BirthDate"
                                class="form-control<?= invalidClass($errors, 'BirthDate') ?>"
                                max="<?= date('Y-m-d') ?>"
                                value="<?= e($data['BirthDate']) ?>"
                            >
                            <?= fieldError($errors, 'BirthDate') ?>
                        </div>

                        <div class="col-12">
                            <label class="form-label" for="Notes">Ghi chú / Vị trí công việc</label>
                            <textarea
                                name="Notes"
                                id="Notes"
                                rows="4"
                                class="form-control"
                                placeholder="Ví dụ: Nhân viên bán hàng"
                            ><?= e($data['Notes']) ?></textarea>
                            <div class="form-text">Không bắt buộc.</div>
                        </div>

                    </div>
                </div>

                <div class="card-footer bg-white py-3 d-flex justify-content-end gap-2">
                    <a href="/admin/employees/" class="btn btn-outline-secondary">Hủy</a>
                    <button type="submit" class="btn btn-success px-4">
                        <i class="bi bi-check-lg me-1"></i>Thêm nhân viên
                    </button>
                </div>

            </form>

        </div>
    </div>
</div>

<?php

require_once '/var/www/src/includes/admin/footer.php';

$connection->close();