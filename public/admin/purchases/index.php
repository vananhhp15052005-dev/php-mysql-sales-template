<?php

$pageTitle = 'Quản lý nhập hàng';

require_once '/var/www/src/config/database.php';

if (!function_exists('money')) {
    function money(float $n): string
    {
        return number_format($n, 0, ',', '.') . ' đ';
    }
}

function purchaseCode(int $id): string
{
    return 'PN' . str_pad((string) $id, 5, '0', STR_PAD_LEFT);
}

/* ---------- Bộ lọc ---------- */
$supplierFilter = (int) ($_GET['supplier_id'] ?? 0);
$from = $_GET['from'] ?? '';
$to   = $_GET['to'] ?? '';

$isDate = fn(string $d): bool => (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $d);

$where  = [];
$types  = '';
$params = [];

if ($supplierFilter > 0) {
    $where[]  = 'p.SupplierID = ?';
    $types   .= 'i';
    $params[] = $supplierFilter;
}
if ($isDate($from)) {
    $where[]  = 'p.PurchaseDate >= ?';
    $types   .= 's';
    $params[] = $from . ' 00:00:00';
} else {
    $from = '';
}
if ($isDate($to)) {
    $where[]  = 'p.PurchaseDate <= ?';
    $types   .= 's';
    $params[] = $to . ' 23:59:59';
} else {
    $to = '';
}

$sql = "
    SELECT
        p.PurchaseID,
        p.PurchaseDate,
        p.TotalAmount,
        p.Status,
        s.SupplierName,
        CONCAT(e.LastName, ' ', e.FirstName) AS EmployeeName,
        (SELECT COALESCE(SUM(d.Quantity), 0)
         FROM purchasedetail AS d
         WHERE d.PurchaseID = p.PurchaseID) AS TotalQty
    FROM purchases AS p
    INNER JOIN suppliers AS s ON p.SupplierID = s.SupplierID
    LEFT JOIN employees AS e ON p.EmployeeID = e.EmployeeID
    " . ($where ? 'WHERE ' . implode(' AND ', $where) : '') . "
    ORDER BY p.PurchaseID DESC
    LIMIT 200
";

$stmt = $connection->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$purchases = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$suppliers = $connection
    ->query("SELECT SupplierID, SupplierName FROM suppliers ORDER BY SupplierName")
    ->fetch_all(MYSQLI_ASSOC);

/* ---------- Tổng hợp (chỉ tính phiếu đã nhập kho) ---------- */
$sumAmount = 0.0;
$sumQty    = 0;
$countOk   = 0;
foreach ($purchases as $p) {
    if ($p['Status'] === 'Completed') {
        $sumAmount += (float) $p['TotalAmount'];
        $sumQty    += (int) $p['TotalQty'];
        $countOk++;
    }
}

$statusText = ['Completed' => 'Đã nhập kho', 'Cancelled' => 'Đã hủy'];
$badge      = ['Completed' => 'bg-success',  'Cancelled' => 'bg-secondary'];

require_once '/var/www/src/includes/admin/header.php';
require_once '/var/www/src/includes/admin/navbar.php';

?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h2 class="h4 mb-1">Quản lý nhập hàng</h2>
        <p class="text-muted mb-0">Danh sách phiếu nhập kho từ nhà cung cấp</p>
    </div>
    <a href="/admin/purchases/create.php" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i>Tạo phiếu nhập
    </a>
</div>

<!-- Tổng hợp -->
<div class="row g-3 mb-3">
    <div class="col-sm-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="text-muted small">Số phiếu đã nhập kho</div>
                <div class="fs-5 fw-bold"><?= number_format($countOk) ?></div>
            </div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="text-muted small">Tổng số lượng nhập</div>
                <div class="fs-5 fw-bold"><?= number_format($sumQty) ?></div>
            </div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="text-muted small">Tổng chi phí nhập</div>
                <div class="fs-5 fw-bold"><?= money($sumAmount) ?></div>
            </div>
        </div>
    </div>
</div>

<!-- Bộ lọc -->
<form method="get" class="card border-0 shadow-sm mb-3">
    <div class="card-body row g-2 align-items-end">
        <div class="col-md-4">
            <label class="form-label small mb-1">Nhà cung cấp</label>
            <select name="supplier_id" class="form-select">
                <option value="0">Tất cả</option>
                <?php foreach ($suppliers as $s): ?>
                    <option value="<?= (int) $s['SupplierID'] ?>"
                        <?= $supplierFilter === (int) $s['SupplierID'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($s['SupplierName']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label small mb-1">Từ ngày</label>
            <input type="date" name="from" class="form-control" value="<?= htmlspecialchars($from) ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label small mb-1">Đến ngày</label>
            <input type="date" name="to" class="form-control" value="<?= htmlspecialchars($to) ?>">
        </div>
        <div class="col-md-2 d-flex gap-2">
            <button class="btn btn-dark flex-grow-1" type="submit">Lọc</button>
            <a href="/admin/purchases/" class="btn btn-outline-secondary" title="Xóa lọc">
                <i class="bi bi-x-lg"></i>
            </a>
        </div>
    </div>
</form>

<!-- Bảng phiếu nhập -->
<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-dark">
                <tr>
                    <th>Mã phiếu</th>
                    <th>Ngày nhập</th>
                    <th>Nhà cung cấp</th>
                    <th>Nhân viên</th>
                    <th class="text-end">SL</th>
                    <th class="text-end">Tổng tiền</th>
                    <th>Trạng thái</th>
                    <th>Thao tác</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$purchases): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">Chưa có phiếu nhập nào</td></tr>
                <?php endif; ?>

                <?php foreach ($purchases as $p): ?>
                    <tr>
                        <td class="fw-semibold"><?= purchaseCode((int) $p['PurchaseID']) ?></td>
                        <td><?= htmlspecialchars($p['PurchaseDate']) ?></td>
                        <td><?= htmlspecialchars($p['SupplierName']) ?></td>
                        <td><?= htmlspecialchars($p['EmployeeName'] ?? '—') ?></td>
                        <td class="text-end"><?= number_format((int) $p['TotalQty']) ?></td>
                        <td class="text-end"><?= money((float) $p['TotalAmount']) ?></td>
                        <td>
                            <span class="badge <?= $badge[$p['Status']] ?? 'bg-secondary' ?>">
                                <?= htmlspecialchars($statusText[$p['Status']] ?? $p['Status']) ?>
                            </span>
                        </td>
                        <td>
                            <a href="/admin/purchases/detail.php?id=<?= (int) $p['PurchaseID'] ?>"
                               class="btn btn-sm btn-primary">Xem</a>
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

