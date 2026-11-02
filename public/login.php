<?php

require_once '/var/www/src/config/session.php';
require_once '/var/www/src/config/database.php';

$pageTitle = 'Đăng nhập tài khoản';

// Nếu đã đăng nhập thì tự động chuyển hướng về trang chủ
if (isset($_SESSION['customer_id'])) {
    header('Location: /');
    exit;
}

$email = '';
$errorMessage = '';
$successMessage = '';

// Hiển thị thông báo sau khi đăng ký thành công
if (isset($_GET['registered'])) {
    $successMessage = 'Tạo tài khoản thành công! Vui lòng đăng nhập để tiếp tục.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $errorMessage = 'Vui lòng điền đầy đủ Email và Mật khẩu.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errorMessage = 'Địa chỉ Email không đúng định dạng.';
    } else {
        $sql = "
            SELECT CustomerID, CustomerName, Email, PasswordHash 
            FROM customers 
            WHERE Email = ?
            LIMIT 1
        ";

        $stmt = $connection->prepare($sql);
        $stmt->bind_param('s', $email);
        $stmt->execute();
        
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        $result->free();
        $stmt->close();

        if ($user && !empty($user['PasswordHash']) && password_verify($password, $user['PasswordHash'])) {
            // Tái tạo Session ID để chống tấn công Session Fixation
            session_regenerate_id(true);

            $_SESSION['customer_id'] = (int) $user['CustomerID'];
            $_SESSION['customer_name'] = $user['CustomerName'];

            header('Location: /');
            exit;
        } else {
            $errorMessage = 'Email hoặc mật khẩu không chính xác.';
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
        --bg-color: #f7f8fc;
    }

    body {
        background-color: var(--bg-color);
    }

    .auth-card {
        background: #ffffff;
        border: 1px solid #eceef5;
        border-radius: 20px;
        box-shadow: 0 10px 30px rgba(32, 36, 58, 0.05);
        padding: 40px;
    }

    .auth-title {
        font-weight: 800;
        color: #20243a;
        letter-spacing: -0.5px;
    }

    .form-control:focus {
        border-color: var(--brand-color);
        box-shadow: 0 0 0 0.25rem rgba(99, 91, 219, 0.15);
    }

    .btn-auth {
        background: var(--brand-color);
        border: none;
        color: white;
        font-weight: 700;
        padding: 12px;
        border-radius: 12px;
        transition: all 0.2s ease;
    }

    .btn-auth:hover {
        background: var(--brand-hover);
        color: white;
        transform: translateY(-1px);
    }
</style>

<main class="container py-5">
    <div class="row justify-content-center my-4">
        <div class="col-12 col-sm-10 col-md-8 col-lg-5">
            <div class="auth-card">
                
                <!-- Tiêu đề & Lời chào -->
                <div class="text-center mb-4">
                    <div class="d-inline-flex align-items-center justify-content-center bg-light text-primary rounded-circle mb-3" style="width: 60px; height: 60px; color: var(--brand-color) !important;">
                        <i class="bi bi-person-lock fs-2"></i>
                    </div>
                    <h1 class="h4 auth-title mb-1">Đăng nhập</h1>
                    <p class="text-muted small">Nhập thông tin tài khoản của bạn để tiếp tục</p>
                </div>

                <!-- Thông báo lỗi / thành công -->
                <?php if ($successMessage !== ''): ?>
                    <div class="alert alert-success alert-dismissible fade show rounded-3 small" role="alert">
                        <i class="bi bi-check-circle-fill me-1"></i> <?= htmlspecialchars($successMessage) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if ($errorMessage !== ''): ?>
                    <div class="alert alert-danger alert-dismissible fade show rounded-3 small" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i> <?= htmlspecialchars($errorMessage) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <!-- Form Đăng nhập -->
                <form method="post" action="/login.php" novalidate>
                    <div class="mb-3">
                        <label for="email" class="form-label fw-semibold text-secondary small">Địa chỉ Email</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-envelope"></i></span>
                            <input type="email" 
                                   class="form-control border-start-0 ps-0" 
                                   id="email" 
                                   name="email" 
                                   placeholder="name@example.com"
                                   value="<?= htmlspecialchars($email) ?>" 
                                   required 
                                   autofocus>
                        </div>
                    </div>

                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label for="password" class="form-label fw-semibold text-secondary small mb-0">Mật khẩu</label>
                        </div>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-key"></i></span>
                            <input type="password" 
                                   class="form-control border-start-0 border-end-0 ps-0" 
                                   id="password" 
                                   name="password" 
                                   placeholder="••••••••"
                                   required>
                            <button class="btn btn-outline-secondary border-start-0" type="button" id="togglePassword">
                                <i class="bi bi-eye" id="toggleIcon"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-auth w-100 mb-3">
                        Đăng nhập <i class="bi bi-arrow-right ms-1"></i>
                    </button>
                </form>

                <!-- Footer Card / Chuyển hướng đăng ký -->
                <div class="text-center pt-3 border-top mt-3">
                    <p class="small text-muted mb-0">
                        Chưa có tài khoản? 
                        <a href="/register.php" class="fw-bold text-decoration-none" style="color: var(--brand-color);">Đăng ký ngay</a>
                    </p>
                </div>

            </div>
        </div>
    </div>
</main>

<script>
    // Xử lý bật/tắt hiển thị mật khẩu
    document.getElementById('togglePassword')?.addEventListener('click', function () {
        const passwordInput = document.getElementById('password');
        const toggleIcon = document.getElementById('toggleIcon');
        
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            toggleIcon.classList.replace('bi-eye', 'bi-eye-slash');
        } else {
            passwordInput.type = 'password';
            toggleIcon.classList.replace('bi-eye-slash', 'bi-eye');
        }
    });
</script>

<?php
require_once '/var/www/src/includes/frontend/footer.php';

$connection->close();
?>