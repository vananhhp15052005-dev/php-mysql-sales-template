<?php

$pageTitle = 'Quản lý nhân viên';

require_once '/var/www/src/config/database.php';

/* ---------- Tìm kiếm ---------- */
$q = trim($_GET['q'] ?? '');

$sql = "
    SELECT
        e.EmployeeID, e.LastName, e.FirstName, e.BirthDate, e.Notes,
        (SELECT COUNT(*) FROM orders AS o WHERE o.EmployeeID = e.EmployeeID)    AS OrderCount,
        (SELECT COUNT(*) FROM purchases AS p WHERE p.EmployeeID = e.EmployeeID) AS PurchaseCount
    FROM employees AS e
    " . ($q !== '' ? "WHERE CONCAT(e.LastName, ' ', e.FirstName) LIKE ?" : '') . "
    ORDER BY e.EmployeeID
";

$stmt = $connection->prepare($sql);
if ($q !== '') {
    $like = '%' . $q . '%';
    $stmt->bind_param('s', $like);
}
$stmt->execute();
$employees = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

/* ---------- Thông báo (do create/edit/delete chuyển về) ---------- */
$flashes = [
    'created' => ['success', 'Đã thêm nhân viên mới.'],
    'updated' => ['success', 'Đã cập nhật thông tin nhân viên.'],
    'deleted' => ['success', 'Đã xóa nhân viên.'],
    'in_use'  => ['danger',  'Không thể xóa: nhân viên này đã gắn với đơn hàng hoặc phiếu nhập.'],
    'missing' => ['danger',  'Không tìm thấy nhân viên cần xóa.'],
    'error'   => ['danger',  'Có lỗi hệ thống, vui lòng thử lại.'],
];
$flash = $flashes[$_GET['msg'] ?? ''] ?? null;

require_once '/var/www/src/includes/admin/header.php';
require_once '/var/www/src/includes/admin/navbar.php';

?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h2 class="h4 mb-1">Quản lý nhân viên</h2>
        <p class="text-muted mb-0">Tổng cộng <?= count($employees) ?> nhân viên<?= $q !== '' ? ' phù hợp' : '' ?></p>
    </div>
    <a href="/admin/employees/create.php" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i>Thêm nhân viên
    </a>
</div>

<?php if ($flash): ?>
    <div class="alert alert-<?= $flash[0] ?>"><?= htmlspecialchars($flash[1]) ?></div>
<?php endif; ?>

<form method="get" class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <div class="input-group">
            <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
            <input type="text" name="q" class="form-control" placeholder="Tìm theo họ tên nhân viên..."
                   value="<?= htmlspecialchars($q) ?>">
            <button class="btn btn-dark" type="submit">Tìm</button>
            <?php if ($q !== ''): ?>
                <a href="/admin/employees/" class="btn btn-outline-secondary" title="Xóa tìm kiếm">
                    <i class="bi bi-x-lg"></i>
                </a>
            <?php endif; ?>
        </div>
    </div>
</form>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-dark">
                <tr>
                    <th>Mã</th>
                    <th>Nhân viên</th>
                    <th>Ngày sinh</th>
                    <th>Ghi chú</th>
                    <th class="text-center">Đơn bán</th>
                    <th class="text-center">Phiếu nhập</th>
                    <th style="width:150px;">Thao tác</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$employees): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">Không có nhân viên nào</td></tr>
                <?php endif; ?>

                <?php foreach ($employees as $e): ?>
                    <?php
                    $age     = $e['BirthDate'] ? (new DateTime($e['BirthDate']))->diff(new DateTime('today'))->y : null;
                    $inUse   = ((int) $e['OrderCount'] + (int) $e['PurchaseCount']) > 0;
                    $initial = mb_strtoupper(mb_substr($e['FirstName'], 0, 1));
                    ?>
                    <tr>
                        <td class="fw-semibold">NV<?= str_pad((string) $e['EmployeeID'], 3, '0', STR_PAD_LEFT) ?></td>
                        <td>
                            <span><?= htmlspecialchars($e['LastName'] . ' ' . $e['FirstName']) ?></span>
                        </td>
                        <td>
                            <?php if ($e['BirthDate']): ?>
                                <?= date('d/m/Y', strtotime($e['BirthDate'])) ?>
                                <span class="text-muted small">(<?= $age ?> tuổi)</span>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-muted">
                            <?= $e['Notes'] ? htmlspecialchars(mb_strimwidth($e['Notes'], 0, 50, '...')) : '—' ?>
                        </td>
                        <td class="text-center"><?= (int) $e['OrderCount'] ?></td>
                        <td class="text-center"><?= (int) $e['PurchaseCount'] ?></td>
                        <td>
                            <div class="d-flex gap-1">
                                <a href="/admin/employees/edit.php?id=<?= (int) $e['EmployeeID'] ?>"
                                   class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-pencil"></i> Sửa
                                </a>

                                <form method="post" action="/admin/employees/delete.php" class="d-inline"
                                      onsubmit="return confirm('Xóa nhân viên này?');">
                                    <input type="hidden" name="id" value="<?= (int) $e['EmployeeID'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger"
                                        <?= $inUse ? 'disabled title="Đã có đơn hàng / phiếu nhập, không thể xóa"' : '' ?>>
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php

require_once '/var/www/src/includes/admin/footer.php';

$connection->close();
