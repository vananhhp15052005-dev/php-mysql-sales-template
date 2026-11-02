<?php

require_once '/var/www/src/config/session.php';
require_once '/var/www/src/config/database.php';

$pageTitle = 'Đăng ký tài khoản';

// Tự động chuyển hướng về trang chủ nếu người dùng đã đăng nhập
if (isset($_SESSION['customer_id'])) {
    header('Location: /index.php');
    exit;
}

$formData = [
    'customer_name' => '',
    'phone'         => '',
    'email'         => ''
];
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Làm sạch dữ liệu đầu vào
    $formData['customer_name'] = trim($_POST['customer_name'] ?? '');
    $formData['phone']         = trim($_POST['phone'] ?? '');
    $formData['email']         = trim($_POST['email'] ?? '');
    $password                  = $_POST['password'] ?? '';
    $confirmPassword           = $_POST['confirm_password'] ?? '';

    // Ràng buộc kiểm tra tính hợp lệ của Form (Validation)
    if (empty($formData['customer_name']) || empty($formData['email']) || empty($password) || empty($confirmPassword)) {
        $errorMessage = 'Vui lòng nhập đầy đủ các trường thông tin bắt buộc.';
    } elseif (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
        $errorMessage = 'Địa chỉ Email nhập vào không hợp lệ.';
    } elseif (mb_strlen($password) < 6) {
        $errorMessage = 'Mật khẩu bảo mật phải có độ dài tối thiểu 6 ký tự.';
    } elseif ($password !== $confirmPassword) {
        $errorMessage = 'Mật khẩu xác nhận không trùng khớp với mật khẩu đã nhập.';
    } else {
        // Kiểm tra sự tồn tại của Email trong cơ sở dữ liệu
        $sqlCheck = "SELECT CustomerID FROM customers WHERE Email = ? LIMIT 1";
        $stmtCheck = $connection->prepare($sqlCheck);
        $stmtCheck->bind_param('s', $formData['email']);
        $stmtCheck->execute();
        
        $resCheck = $stmtCheck->get_result();
        $isDuplicate = (bool) $resCheck->fetch_assoc();
        
        $resCheck->free();
        $stmtCheck->close();

        if ($isDuplicate) {
            $errorMessage = 'Địa chỉ Email này đã được đăng ký tài khoản trước đó.';
        } else {
            // Mã hóa mật khẩu bảo mật chuẩn BCRYPT
            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

            $sqlInsert = "
                INSERT INTO customers (CustomerName, Phone, Email, PasswordHash)
                VALUES (?, ?, ?, ?)
            ";

            $stmtInsert = $connection->prepare($sqlInsert);
            $stmtInsert->bind_param(
                'ssss',
                $formData['customer_name'],
                $formData['phone'],
                $formData['email'],
                $hashedPassword
            );

            if ($stmtInsert->execute()) {
                $stmtInsert->close();
                header('Location: /login.php?registered=1');
                exit;
            } else {
                $errorMessage = 'Có lỗi xảy ra trong quá trình khởi tạo tài khoản. Vui lòng thử lại sau.';
                $stmtInsert->close();
            }
        }
    }
}

require_once '/var/www/src/includes/frontend/header.php';
require_once '/var/www/src/includes/frontend/navbar.php';
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<style>
    :root {
        --brand-color: #635bdb;
        --brand-hover: #4f46c8;
        --bg-page: #f8fafc;
        --card-border: #e2e8f0;
    }

    body {
        background-color: var(--bg-page);
    }

    .register-card-wrapper {
        background: #ffffff;
        border: 1px solid var(--card-border);
        border-radius: 20px;
        box-shadow: 0 10px 30px rgba(15, 23, 42, 0.04);
        padding: 40px;
    }

    .form-control:focus {
        border-color: var(--brand-color);
        box-shadow: 0 0 0 0.25rem rgba(99, 91, 219, 0.15);
    }

    .btn-submit-register {
        background: var(--brand-color);
        border: none;
        color: #ffffff;
        font-weight: 700;
        padding: 12px;
        border-radius: 12px;
        transition: all 0.2s ease;
    }

    .btn-submit-register:hover {
        background: var(--brand-hover);
        color: #ffffff;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(99, 91, 219, 0.25);
    }

    /* Thanh đo độ mạnh mật khẩu */
    .strength-meter {
        height: 4px;
        background-color: #e2e8f0;
        border-radius: 2px;
        overflow: hidden;
        transition: all 0.3s ease;
    }

    .strength-meter-bar {
        height: 100%;
        width: 0%;
        transition: width 0.3s ease, background-color 0.3s ease;
    }
</style>

<main class="container py-5">
    <div class="row justify-content-center my-3">
        <div class="col-12 col-sm-10 col-md-8 col-lg-6">
            <div class="register-card-wrapper">
                
                <!-- Header Tiêu đề -->
                <div class="text-center mb-4">
                    <div class="d-inline-flex align-items-center justify-content-center bg-light text-primary rounded-circle mb-3" style="width: 64px; height: 64px; color: var(--brand-color) !important;">
                        <i class="bi bi-person-plus fs-2"></i>
                    </div>
                    <h1 class="h4 fw-extrabold text-dark mb-1">Tạo tài khoản mới</h1>
                    <p class="text-muted small">Đăng ký để nhận ưu đãi và trải nghiệm mua sắm nhanh chóng</p>
                </div>

                <!-- Cảnh báo thông báo lỗi -->
                <?php if ($errorMessage !== ''): ?>
                    <div class="alert alert-danger alert-dismissible fade show rounded-3 small mb-4" role="alert">
                        <i class="bi bi-exclamation-circle-fill me-1"></i> <?= htmlspecialchars($errorMessage) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <!-- Form Đăng ký -->
                <form method="post" action="/register.php" id="registerForm" novalidate>
                    
                    <!-- Họ và tên -->
                    <div class="mb-3">
                        <label for="customer_name" class="form-label fw-semibold text-secondary small">Họ và tên <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-person"></i></span>
                            <input type="text" 
                                   class="form-control border-start-0 ps-0" 
                                   id="customer_name" 
                                   name="customer_name" 
                                   placeholder="Nguyễn Văn A" 
                                   value="<?= htmlspecialchars($formData['customer_name']) ?>" 
                                   required>
                        </div>
                    </div>

                    <!-- Số điện thoại -->
                    <div class="mb-3">
                        <label for="phone" class="form-label fw-semibold text-secondary small">Số điện thoại</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-telephone"></i></span>
                            <input type="tel" 
                                   class="form-control border-start-0 ps-0" 
                                   id="phone" 
                                   name="phone" 
                                   placeholder="0912345678" 
                                   value="<?= htmlspecialchars($formData['phone']) ?>">
                        </div>
                    </div>

                    <!-- Email -->
                    <div class="mb-3">
                        <label for="email" class="form-label fw-semibold text-secondary small">Địa chỉ Email <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-envelope"></i></span>
                            <input type="email" 
                                   class="form-control border-start-0 ps-0" 
                                   id="email" 
                                   name="email" 
                                   placeholder="name@example.com" 
                                   value="<?= htmlspecialchars($formData['email']) ?>" 
                                   required>
                        </div>
                    </div>

                    <!-- Mật khẩu & Xác nhận Mật khẩu -->
                    <div class="row g-3 mb-2">
                        <div class="col-md-6">
                            <label for="password" class="form-label fw-semibold text-secondary small">Mật khẩu <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-key"></i></span>
                                <input type="password" 
                                       class="form-control border-start-0 border-end-0 ps-0" 
                                       id="password" 
                                       name="password" 
                                       placeholder="Tối thiểu 6 ký tự" 
                                       required>
                                <button class="btn btn-outline-secondary border-start-0" type="button" id="togglePassword">
                                    <i class="bi bi-eye" id="toggleIconPass"></i>
                                </button>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label for="confirm_password" class="form-label fw-semibold text-secondary small">Xác nhận mật khẩu <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-shield-lock"></i></span>
                                <input type="password" 
                                       class="form-control border-start-0 border-end-0 ps-0" 
                                       id="confirm_password" 
                                       name="confirm_password" 
                                       placeholder="Nhập lại mật khẩu" 
                                       required>
                                <button class="btn btn-outline-secondary border-start-0" type="button" id="toggleConfirmPassword">
                                    <i class="bi bi-eye" id="toggleIconConfirm"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Thanh hiển thị độ mạnh mật khẩu -->
                    <div class="mb-4">
                        <div class="strength-meter mb-1">
                            <div class="strength-meter-bar" id="strengthBar"></div>
                        </div>
                        <small class="text-muted" style="font-size: 0.75rem;" id="strengthText">Độ mạnh mật khẩu: Chưa nhập</small>
                    </div>

                    <!-- Nút Đăng ký -->
                    <button type="submit" class="btn btn-submit-register w-100 mb-3">
                        <i class="bi bi-person-check me-1"></i> HOÀN TẤT ĐĂNG KÝ
                    </button>
                </form>

                <!-- Footer chuyển đổi Đăng nhập -->
                <div class="text-center pt-3 border-top mt-3">
                    <p class="small text-muted mb-0">
                        Đã có tài khoản? 
                        <a href="/login.php" class="fw-bold text-decoration-none" style="color: var(--brand-color);">Đăng nhập ngay</a>
                    </p>
                </div>

            </div>
        </div>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // 1. Chức năng Ẩn/Hiện Mật khẩu
    function setupPasswordToggle(inputId, toggleBtnId, iconId) {
        const input = document.getElementById(inputId);
        const toggleBtn = document.getElementById(toggleBtnId);
        const icon = document.getElementById(iconId);

        if (input && toggleBtn && icon) {
            toggleBtn.addEventListener('click', function () {
                if (input.type === 'password') {
                    input.type = 'text';
                    icon.classList.replace('bi-eye', 'bi-eye-slash');
                } else {
                    input.type = 'password';
                    icon.classList.replace('bi-eye-slash', 'bi-eye');
                }
            });
        }
    }

    setupPasswordToggle('password', 'togglePassword', 'toggleIconPass');
    setupPasswordToggle('confirm_password', 'toggleConfirmPassword', 'toggleIconConfirm');

    // 2. Chức năng Đánh giá độ mạnh của Mật khẩu
    const passwordInput = document.getElementById('password');
    const strengthBar = document.getElementById('strengthBar');
    const strengthText = document.getElementById('strengthText');

    if (passwordInput && strengthBar && strengthText) {
        passwordInput.addEventListener('input', function () {
            const val = passwordInput.value;
            let score = 0;

            if (val.length >= 6) score++;
            if (val.length >= 10) score++;
            if (/[A-Z]/.test(val)) score++;
            if (/[0-9]/.test(val)) score++;
            if (/[^A-Za-z0-9]/.test(val)) score++;

            if (val.length === 0) {
                strengthBar.style.width = '0%';
                strengthText.textContent = 'Độ mạnh mật khẩu: Chưa nhập';
                strengthText.className = 'text-muted';
            } else if (score <= 2) {
                strengthBar.style.width = '33%';
                strengthBar.style.backgroundColor = '#ef4444';
                strengthText.textContent = 'Độ mạnh: Yếu';
                strengthText.className = 'text-danger';
            } else if (score <= 4) {
                strengthBar.style.width = '66%';
                strengthBar.style.backgroundColor = '#f59e0b';
                strengthText.textContent = 'Độ mạnh: Trung bình';
                strengthText.className = 'text-warning';
            } else {
                strengthBar.style.width = '100%';
                strengthBar.style.backgroundColor = '#10b981';
                strengthText.textContent = 'Độ mạnh: Rất mạnh';
                strengthText.className = 'text-success';
            }
        });
    }
});
</script>

<?php
require_once '/var/www/src/includes/frontend/footer.php';

$connection->close();
?>