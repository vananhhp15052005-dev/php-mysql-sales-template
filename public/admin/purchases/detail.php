<?php

$pageTitle = 'Chi tiết phiếu nhập';

require_once '/var/www/src/config/database.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

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

$id = (int) ($_GET['id'] ?? 0);
$error = '';

/* =====================================================
 * HỦY PHIẾU: trừ lại tồn kho trong 1 transaction
 * ===================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel' && $id > 0) {
    try {
        $connection->begin_transaction();

        // Chỉ hủy được phiếu đang ở trạng thái Completed (chặn bấm hủy 2 lần)
        $stmt = $connection->prepare(
            "UPDATE purchases SET Status = 'Cancelled' WHERE PurchaseID = ? AND Status = 'Completed'"
        );
        $stmt->bind_param('i', $id);
        $stmt->execute();
        if ($stmt->affected_rows === 0) {
            throw new RuntimeException('Phiếu nhập không tồn tại hoặc đã được hủy trước đó.');
        }
        $stmt->close();

        $stmt = $connection->prepare(
            "SELECT d.ProductID, d.Quantity, p.ProductName, p.StockQuantity
             FROM purchasedetail AS d
             INNER JOIN products AS p ON d.ProductID = p.ProductID
             WHERE d.PurchaseID = ?
             FOR UPDATE"
        );
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $lines = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $stmtStock = $connection->prepare(
            "UPDATE products SET StockQuantity = StockQuantity - ? WHERE ProductID = ?"
        );

        foreach ($lines as $line) {
            if ((int) $line['StockQuantity'] < (int) $line['Quantity']) {
                throw new RuntimeException(
                    'Không thể hủy: "' . $line['ProductName'] . '" chỉ còn ' . (int) $line['StockQuantity']
                    . ' trong kho, ít hơn số lượng đã nhập (' . (int) $line['Quantity']
                    . '). Có thể hàng đã được bán.'
                );
            }
            $qty = (int) $line['Quantity'];
            $pid = (int) $line['ProductID'];
            $stmtStock->bind_param('ii', $qty, $pid);
            $stmtStock->execute();
        }
        $stmtStock->close();

        $connection->commit();

        header('Location: /admin/purchases/detail.php?id=' . $id . '&cancelled=1');
        exit;
    } catch (RuntimeException $e) {
        $connection->rollback();
        $error = $e->getMessage();
    } catch (Throwable $e) {
        $connection->rollback();
        error_log('Purchase cancel failed: ' . $e->getMessage());
        $error = 'Có lỗi hệ thống khi hủy phiếu. Vui lòng thử lại.';
    }
}

$stmt = $connection->prepare("
    SELECT
        p.PurchaseID, p.PurchaseDate, p.TotalAmount, p.Status, p.Note,
        s.SupplierName, s.ContactName, s.Phone, s.Address, s.City,
        CONCAT(e.LastName, ' ', e.FirstName) AS EmployeeName
    FROM purchases AS p
    INNER JOIN suppliers AS s ON p.SupplierID = s.SupplierID
    LEFT JOIN employees AS e ON p.EmployeeID = e.EmployeeID
    WHERE p.PurchaseID = ?
");
$stmt->bind_param('i', $id);
$stmt->execute();
$purchase = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$purchase) {
    http_response_code(404);
}

$lines = [];
if ($purchase) {
    $stmt = $connection->prepare("
        SELECT d.Quantity, d.UnitCost, pr.ProductCode, pr.ProductName, pr.Unit
        FROM purchasedetail AS d
        INNER JOIN products AS pr ON d.ProductID = pr.ProductID
        WHERE d.PurchaseID = ?
        ORDER BY d.PurchaseDetailID
    ");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $lines = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$statusText = ['Completed' => 'Đã nhập kho', 'Cancelled' => 'Đã hủy'];
$badge      = ['Completed' => 'bg-success',  'Cancelled' => 'bg-secondary'];

require_once '/var/www/src/includes/admin/header.php';
require_once '/var/www/src/includes/admin/navbar.php';

?>

<?php if (!$purchase): ?>

    <div class="alert alert-warning">Không tìm thấy phiếu nhập.</div>
    <a href="/admin/purchases/" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Quay lại danh sách
    </a>

<?php else: ?>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="h4 mb-1">
                Phiếu nhập <?= purchaseCode((int) $purchase['PurchaseID']) ?>
                <span class="badge <?= $badge[$purchase['Status']] ?? 'bg-secondary' ?> ms-2 fs-6">
                    <?= htmlspecialchars($statusText[$purchase['Status']] ?? $purchase['Status']) ?>
                </span>
            </h2>
            <p class="text-muted mb-0">Ngày nhập: <?= htmlspecialchars($purchase['PurchaseDate']) ?></p>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-dark d-print-none" onclick="window.print()">
                <i class="bi bi-printer me-1"></i>In phiếu
            </button>
            <a href="/admin/purchases/" class="btn btn-outline-secondary d-print-none">
                <i class="bi bi-arrow-left me-1"></i>Quay lại
            </a>
        </div>
    </div>

    <?php if (isset($_GET['created'])): ?>
        <div class="alert alert-success">Đã tạo phiếu nhập và cộng tồn kho thành công.</div>
    <?php endif; ?>
    <?php if (isset($_GET['cancelled'])): ?>
        <div class="alert alert-info">Đã hủy phiếu nhập và trừ lại tồn kho.</div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="row g-3 mb-3">
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white fw-semibold">Nhà cung cấp</div>
                <div class="card-body">
                    <div class="fw-semibold"><?= htmlspecialchars($purchase['SupplierName']) ?></div>
                    <?php if ($purchase['ContactName']): ?>
                        <div class="text-muted small">Liên hệ: <?= htmlspecialchars($purchase['ContactName']) ?></div>
                    <?php endif; ?>
                    <?php if ($purchase['Phone']): ?>
                        <div class="text-muted small">Điện thoại: <?= htmlspecialchars($purchase['Phone']) ?></div>
                    <?php endif; ?>
                    <?php if ($purchase['Address'] || $purchase['City']): ?>
                        <div class="text-muted small">
                            Địa chỉ: <?= htmlspecialchars(trim(($purchase['Address'] ?? '') . ' ' . ($purchase['City'] ?? ''))) ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white fw-semibold">Thông tin khác</div>
                <div class="card-body">
                    <div class="small mb-1">
                        <span class="text-muted">Nhân viên lập phiếu:</span>
                        <?= htmlspecialchars($purchase['EmployeeName'] ?? '—') ?>
                    </div>
                    <div class="small">
                        <span class="text-muted">Ghi chú:</span>
                        <?= $purchase['Note'] !== null && $purchase['Note'] !== ''
                            ? nl2br(htmlspecialchars($purchase['Note']))
                            : '—' ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold">Sản phẩm đã nhập</div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Mã SP</th>
                        <th>Tên sản phẩm</th>
                        <th class="text-end">Số lượng</th>
                        <th class="text-end">Giá nhập</th>
                        <th class="text-end">Thành tiền</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lines as $i => $l): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td><?= htmlspecialchars($l['ProductCode']) ?></td>
                            <td><?= htmlspecialchars($l['ProductName']) ?></td>
                            <td class="text-end">
                                <?= number_format((int) $l['Quantity']) ?>
                                <?= htmlspecialchars($l['Unit'] ?? '') ?>
                            </td>
                            <td class="text-end"><?= money((float) $l['UnitCost']) ?></td>
                            <td class="text-end"><?= money((int) $l['Quantity'] * (float) $l['UnitCost']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="table-light">
                        <td colspan="5" class="text-end fw-semibold">Tổng cộng</td>
                        <td class="text-end fw-bold"><?= money((float) $purchase['TotalAmount']) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <?php if ($purchase['Status'] === 'Completed'): ?>
        <form method="post" class="d-print-none"
              onsubmit="return confirm('Hủy phiếu nhập này? Tồn kho sẽ bị trừ lại tương ứng.');">
            <input type="hidden" name="action" value="cancel">
            <button type="submit" class="btn btn-outline-danger">
                <i class="bi bi-x-circle me-1"></i>Hủy phiếu nhập
            </button>
        </form>
    <?php endif; ?>

<?php endif; ?>

<?php

require_once '/var/www/src/includes/admin/footer.php';

$connection->close();

