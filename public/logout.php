<?php

require_once '/var/www/src/config/session.php';

/**
 * Danh sách các khóa Session cần hủy khi đăng xuất.
 * Giữ nguyên $_SESSION['cart'] để không làm mất giỏ hàng của khách.
 */
$authKeys = [
    'customer_id',
    'customer_name',
    'customer_email'
];

foreach ($authKeys as $key) {
    if (array_key_exists($key, $_SESSION)) {
        unset($_SESSION[$key]);
    }
}

// Làm mới ID Session để ngăn ngừa tấn công Session Hijacking
if (session_status() === PHP_SESSION_ACTIVE) {
    session_regenerate_id(true);
}

// Lưu thông báo phản hồi chuyển hướng về trang chủ
$_SESSION['flash_message'] = [
    'type' => 'success',
    'text' => 'Bạn đã đăng xuất tài khoản thành công.'
];

// Chuyển hướng người dùng về trang chủ
header('Location: /index.php', true, 302);
exit;