<?php

$pageTitle = 'Tạo phiếu nhập hàng';

require_once '/var/www/src/config/database.php';

// Để lỗi SQL ném ra exception, cần cho transaction
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

/* ---------- Dữ liệu cho form ---------- */
$suppliers = $connection
    ->query("SELECT SupplierID, SupplierName FROM suppliers ORDER BY SupplierName")
    ->fetch_all(MYSQLI_ASSOC);

$employees = $connection
    ->query("SELECT EmployeeID, CONCAT(LastName, ' ', FirstName) AS FullName FROM employees ORDER BY FirstName")
    ->fetch_all(MYSQLI_ASSOC);

$products = $connection
    ->query("SELECT ProductID, ProductCode, ProductName, SupplierID FROM products WHERE IsActive = TRUE ORDER BY ProductName")
    ->fetch_all(MYSQLI_ASSOC);

$errors = [];
$old = [
    'supplier_id' => 0,
    'employee_id' => 0,
    'note'        => '',
    'items'       => [],
];

/* =====================================================
 * XỬ LÝ LƯU PHIẾU (phải chạy trước khi in HTML để redirect được)
 * ===================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $supplierId = (int) ($_POST['supplier_id'] ?? 0);
    $employeeId = (int) ($_POST['employee_id'] ?? 0);
    $note       = trim($_POST['note'] ?? '');

    $productIds = (array) ($_POST['product_id'] ?? []);
    $quantities = (array) ($_POST['quantity'] ?? []);
    $unitCosts  = (array) ($_POST['unit_cost'] ?? []);

    $items = [];
    foreach ($productIds as $i => $pid) {
        $pid  = (int) $pid;
        $qty  = (int) ($quantities[$i] ?? 0);
        $cost = (float) ($unitCosts[$i] ?? 0);

        if ($pid <= 0) {
            continue; // bỏ qua dòng chưa chọn sản phẩm
        }
        if ($qty <= 0) {
            $errors[] = 'Dòng ' . ($i + 1) . ': số lượng phải lớn hơn 0.';
            continue;
        }
        if ($cost < 0) {
            $errors[] = 'Dòng ' . ($i + 1) . ': giá nhập không được âm.';
            continue;
        }
        $items[] = ['pid' => $pid, 'qty' => $qty, 'cost' => $cost];
    }

    $old = [
        'supplier_id' => $supplierId,
        'employee_id' => $employeeId,
        'note'        => $note,
        'items'       => array_map(
            fn($it) => ['product_id' => $it['pid'], 'quantity' => $it['qty'], 'unit_cost' => $it['cost']],
            $items
        ),
    ];

    if ($supplierId <= 0) {
        $errors[] = 'Vui lòng chọn nhà cung cấp.';
    }
    if (!$items && !$errors) {
        $errors[] = 'Phiếu nhập cần có ít nhất một sản phẩm.';
    }

    if (!$errors) {
        try {
            $connection->begin_transaction();

            $total = 0.0;
            foreach ($items as $it) {
                $total += $it['qty'] * $it['cost'];
            }

            $noteParam = $note !== '' ? $note : null;
            $empParam  = $employeeId > 0 ? $employeeId : null;

            // 1. Tạo phiếu
            $stmt = $connection->prepare(
                "INSERT INTO purchases (TotalAmount, Note, SupplierID, EmployeeID) VALUES (?, ?, ?, ?)"
            );
            $stmt->bind_param('dsii', $total, $noteParam, $supplierId, $empParam);
            $stmt->execute();
            $purchaseId = (int) $connection->insert_id;
            $stmt->close();

            // 2. Thêm chi tiết + cộng tồn kho
            $stmtDetail = $connection->prepare(
                "INSERT INTO purchasedetail (Quantity, UnitCost, PurchaseID, ProductID) VALUES (?, ?, ?, ?)"
            );
            $stmtStock = $connection->prepare(
                "UPDATE products SET StockQuantity = StockQuantity + ? WHERE ProductID = ? AND SupplierID = ?"
            );

            foreach ($items as $it) {
                $qty  = $it['qty'];
                $cost = $it['cost'];
                $pid  = $it['pid'];

                $stmtDetail->bind_param('idii', $qty, $cost, $purchaseId, $pid);
                $stmtDetail->execute();

                $stmtStock->bind_param('iii', $qty, $pid, $supplierId);
                $stmtStock->execute();

                // Không khớp dòng nào => sản phẩm không thuộc nhà cung cấp đã chọn
                if ($stmtStock->affected_rows === 0) {
                    throw new RuntimeException(
                        'Sản phẩm #' . $pid . ' không thuộc nhà cung cấp đã chọn.'
                    );
                }
            }

            $stmtDetail->close();
            $stmtStock->close();

            $connection->commit();

            header('Location: /admin/purchases/detail.php?id=' . $purchaseId . '&created=1');
            exit;
        } catch (RuntimeException $e) {
            $connection->rollback();
            $errors[] = $e->getMessage();
        } catch (Throwable $e) {
            $connection->rollback();
            error_log('Purchase create failed: ' . $e->getMessage());
            $errors[] = 'Có lỗi hệ thống khi lưu phiếu nhập. Vui lòng thử lại.';
        }
    }
}

require_once '/var/www/src/includes/admin/header.php';
require_once '/var/www/src/includes/admin/navbar.php';

?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h2 class="h4 mb-1">Tạo phiếu nhập hàng</h2>
        <p class="text-muted mb-0">Khi lưu phiếu, tồn kho của các sản phẩm sẽ được cộng thêm tự động</p>
    </div>
    <a href="/admin/purchases/" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Quay lại
    </a>
</div>

<?php if ($errors): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($errors as $err): ?>
                <li><?= htmlspecialchars($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="post" id="purchaseForm">

    <!-- Thông tin chung -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold">Thông tin phiếu</div>
        <div class="card-body row g-3">
            <div class="col-md-5">
                <label class="form-label" for="supplier_id">Nhà cung cấp <span class="text-danger">*</span></label>
                <select name="supplier_id" id="supplier_id" class="form-select" required>
                    <option value="">-- Chọn nhà cung cấp --</option>
                    <?php foreach ($suppliers as $s): ?>
                        <option value="<?= (int) $s['SupplierID'] ?>"
                            <?= $old['supplier_id'] === (int) $s['SupplierID'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($s['SupplierName']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text">Danh sách sản phẩm bên dưới sẽ lọc theo nhà cung cấp này.</div>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="employee_id">Nhân viên lập phiếu</label>
                <select name="employee_id" id="employee_id" class="form-select">
                    <option value="0">-- Không chọn --</option>
                    <?php foreach ($employees as $e): ?>
                        <option value="<?= (int) $e['EmployeeID'] ?>"
                            <?= $old['employee_id'] === (int) $e['EmployeeID'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($e['FullName']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12">
                <label class="form-label" for="note">Ghi chú</label>
                <textarea name="note" id="note" rows="2" class="form-control"
                          placeholder="Ví dụ: Nhập lô hàng đầu tháng"><?= htmlspecialchars($old['note']) ?></textarea>
            </div>
        </div>
    </div>

    <!-- Danh sách sản phẩm nhập -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <span class="fw-semibold">Sản phẩm nhập</span>
            <button type="button" class="btn btn-sm btn-outline-primary" id="addRowBtn">
                <i class="bi bi-plus-lg me-1"></i>Thêm sản phẩm
            </button>
        </div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="min-width:280px;">Sản phẩm</th>
                        <th style="width:140px;">Số lượng</th>
                        <th style="width:180px;">Giá nhập (đ)</th>
                        <th class="text-end" style="width:170px;">Thành tiền</th>
                        <th style="width:60px;"></th>
                    </tr>
                </thead>
                <tbody id="itemRows"></tbody>
                <tfoot>
                    <tr class="table-light">
                        <td colspan="3" class="text-end fw-semibold">Tổng cộng</td>
                        <td class="text-end fw-bold fs-6" id="grandTotal">0 đ</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <div class="d-flex justify-content-end gap-2">
        <a href="/admin/purchases/" class="btn btn-outline-secondary">Hủy</a>
        <button type="submit" class="btn btn-success">
            <i class="bi bi-check-lg me-1"></i>Lưu &amp; nhập kho
        </button>
    </div>
</form>

<script>
    const products     = <?= json_encode($products, JSON_UNESCAPED_UNICODE) ?>;
    const initialItems = <?= json_encode($old['items']) ?>;

    const supplierSelect = document.getElementById('supplier_id');
    const tbody          = document.getElementById('itemRows');
    const grandTotalEl   = document.getElementById('grandTotal');

    const fmtVND = v => new Intl.NumberFormat('vi-VN').format(v) + ' đ';

    // Chống chèn HTML khi đưa tên sản phẩm vào innerHTML
    const esc = s => {
        const d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    };

    function productOptions(supplierId, selected) {
        let html = '<option value="">-- Chọn sản phẩm --</option>';
        products
            .filter(p => String(p.SupplierID) === String(supplierId))
            .forEach(p => {
                const sel = String(p.ProductID) === String(selected) ? 'selected' : '';
                html += `<option value="${p.ProductID}" ${sel}>${esc(p.ProductCode)} - ${esc(p.ProductName)}</option>`;
            });
        return html;
    }

    function recalc() {
        let total = 0;
        tbody.querySelectorAll('tr').forEach(tr => {
            const qty  = parseFloat(tr.querySelector('.qty').value) || 0;
            const cost = parseFloat(tr.querySelector('.cost').value) || 0;
            const sub  = qty * cost;
            tr.querySelector('.subtotal').textContent = fmtVND(sub);
            total += sub;
        });
        grandTotalEl.textContent = fmtVND(total);
    }

    function addRow(item = {}) {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>
                <select name="product_id[]" class="form-select product-select">
                    ${productOptions(supplierSelect.value, item.product_id)}
                </select>
            </td>
            <td><input type="number" name="quantity[]" class="form-control qty" min="1" step="1" value="${item.quantity ?? 1}"></td>
            <td><input type="number" name="unit_cost[]" class="form-control cost" min="0" step="1" value="${item.unit_cost ?? 0}"></td>
            <td class="text-end subtotal">0 đ</td>
            <td class="text-end">
                <button type="button" class="btn btn-sm btn-outline-danger remove-row" title="Xóa dòng">
                    <i class="bi bi-trash"></i>
                </button>
            </td>`;
        tbody.appendChild(tr);
        recalc();
    }

    // Tính lại khi nhập số lượng / giá
    tbody.addEventListener('input', recalc);

    // Xóa dòng (luôn giữ ít nhất 1 dòng)
    tbody.addEventListener('click', e => {
        const btn = e.target.closest('.remove-row');
        if (!btn) return;
        if (tbody.children.length > 1) {
            btn.closest('tr').remove();
        } else {
            btn.closest('tr').querySelector('.product-select').value = '';
        }
        recalc();
    });

    document.getElementById('addRowBtn').addEventListener('click', () => addRow());

    // Đổi nhà cung cấp => làm mới danh sách sản phẩm ở mọi dòng
    supplierSelect.addEventListener('change', () => {
        tbody.querySelectorAll('.product-select').forEach(sel => {
            sel.innerHTML = productOptions(supplierSelect.value, '');
        });
    });

    // Khởi tạo: dựng lại các dòng cũ khi form báo lỗi, hoặc 1 dòng trống
    if (initialItems.length) {
        initialItems.forEach(it => addRow(it));
    } else {
        addRow();
    }
</script>

<?php

require_once '/var/www/src/includes/admin/footer.php';

$connection->close();
