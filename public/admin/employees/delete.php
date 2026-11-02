<?php

/**
 * Xóa nhân viên. Chỉ nhận POST, không in HTML, xong thì chuyển về danh sách.
 */

require_once '/var/www/src/config/database.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Chặn truy cập trực tiếp bằng đường dẫn (GET)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/employees/');
    exit;
}

$id  = (int) ($_POST['id'] ?? 0);
$msg = 'error';

try {
    $stmt = $connection->prepare("DELETE FROM employees WHERE EmployeeID = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $msg = $stmt->affected_rows > 0 ? 'deleted' : 'missing';
    $stmt->close();
} catch (mysqli_sql_exception $e) {
    // 1451: bị khóa ngoại tham chiếu (đã có đơn hàng / phiếu nhập)
    if ((int) $e->getCode() === 1451) {
        $msg = 'in_use';
    } else {
        error_log('Employee delete failed: ' . $e->getMessage());
    }
}

$connection->close();

header('Location: /admin/employees/?msg=' . $msg);
exit;
