<?php

$pageTitle = 'Báo cáo thống kê';

require_once '/var/www/src/config/database.php';

/* =====================================================
 * HÀM HỖ TRỢ
 * ===================================================== */
function fetchAll(mysqli $conn, string $sql): array
{
    $result = $conn->query($sql);
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function fetchValue(mysqli $conn, string $sql, $default = 0)
{
    $result = $conn->query($sql);
    $row = $result ? $result->fetch_row() : null;
    return $row[0] ?? $default;
}

function money(float $n): string
{
    return number_format($n, 0, ',', '.') . ' đ';
}

/* =====================================================
 * CẤU HÌNH TRẠNG THÁI ĐƠN HÀNG
 * ===================================================== */
$statusText = [
    'Pending'   => 'Chờ xác nhận',
    'Confirmed' => 'Đã xác nhận',
    'Shipping'  => 'Đang giao',
    'Completed' => 'Hoàn thành',
    'Cancelled' => 'Đã hủy',
];

$badgeClasses = [
    'Pending'   => 'bg-warning text-dark',
    'Confirmed' => 'bg-primary',
    'Shipping'  => 'bg-info text-dark',
    'Completed' => 'bg-success',
    'Cancelled' => 'bg-secondary',
];

// Màu biểu đồ khớp với màu badge
$statusColors = [
    'Pending'   => '#ffc107',
    'Confirmed' => '#0d6efd',
    'Shipping'  => '#0dcaf0',
    'Completed' => '#198754',
    'Cancelled' => '#6c757d',
];

/* =====================================================
 * 1. SỐ LIỆU TỔNG QUAN
 * (Doanh thu chỉ tính đơn 'Completed' ở mọi thống kê)
 * ===================================================== */
$totalRevenue   = (float) fetchValue($connection, "SELECT COALESCE(SUM(TotalAmount), 0) FROM orders WHERE Status = 'Completed'");
$totalOrders    = (int)   fetchValue($connection, "SELECT COUNT(*) FROM orders");
$pendingOrders  = (int)   fetchValue($connection, "SELECT COUNT(*) FROM orders WHERE Status = 'Pending'");
$totalCustomers = (int)   fetchValue($connection, "SELECT COUNT(*) FROM customers");
$totalProducts  = (int)   fetchValue($connection, "SELECT COUNT(*) FROM products WHERE IsActive = TRUE");

/* =====================================================
 * 2. DOANH THU 6 THÁNG GẦN NHẤT
 * ===================================================== */
$rows = fetchAll($connection, "
    SELECT DATE_FORMAT(OrderDate, '%Y-%m') AS ym, SUM(TotalAmount) AS revenue
    FROM orders
    WHERE Status = 'Completed'
      AND OrderDate >= DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 5 MONTH)
    GROUP BY ym
");
$revenueMap = array_column($rows, 'revenue', 'ym');

$monthLabels  = [];
$monthRevenue = [];
for ($i = 5; $i >= 0; $i--) {
    $ym = date('Y-m', strtotime("first day of -$i month"));
    $monthLabels[]  = date('m/Y', strtotime($ym . '-01'));
    $monthRevenue[] = (float) ($revenueMap[$ym] ?? 0); // tháng không có đơn = 0
}

/* =====================================================
 * 3. ĐƠN HÀNG THEO TRẠNG THÁI
 * ===================================================== */
$statusRows   = fetchAll($connection, "SELECT Status, COUNT(*) AS total FROM orders GROUP BY Status");
$statusLabels = [];
$statusData   = [];
$statusBg     = [];
foreach ($statusRows as $r) {
    $statusLabels[] = $statusText[$r['Status']] ?? $r['Status'];
    $statusData[]   = (int) $r['total'];
    $statusBg[]     = $statusColors[$r['Status']] ?? '#adb5bd';
}

/* =====================================================
 * 4. TOP 5 SẢN PHẨM BÁN CHẠY
 * ===================================================== */
$topProducts = fetchAll($connection, "
    SELECT p.ProductName, SUM(od.Quantity) AS sold
    FROM orderdetail AS od
    INNER JOIN products AS p ON od.ProductID = p.ProductID
    INNER JOIN orders   AS o ON od.OrderID = o.OrderID
    WHERE o.Status = 'Completed'
    GROUP BY p.ProductID, p.ProductName
    ORDER BY sold DESC
    LIMIT 5
");
$topLabels = array_column($topProducts, 'ProductName');
$topData   = array_map('intval', array_column($topProducts, 'sold'));

/* =====================================================
 * 5. DOANH THU THEO DANH MỤC
 * ===================================================== */
$categoryRows = fetchAll($connection, "
    SELECT c.CategoryName, SUM(od.Quantity * od.UnitPrice) AS revenue
    FROM orderdetail AS od
    INNER JOIN products   AS p ON od.ProductID = p.ProductID
    INNER JOIN categories AS c ON p.CategoryID = c.CategoryID
    INNER JOIN orders     AS o ON od.OrderID = o.OrderID
    WHERE o.Status = 'Completed'
    GROUP BY c.CategoryID, c.CategoryName
    ORDER BY revenue DESC
");
$categoryLabels = array_column($categoryRows, 'CategoryName');
$categoryData   = array_map('floatval', array_column($categoryRows, 'revenue'));

/* =====================================================
 * 6. 5 ĐƠN HÀNG MỚI NHẤT
 * ===================================================== */
$recentOrders = fetchAll($connection, "
    SELECT o.OrderID, o.OrderDate, o.TotalAmount, o.Status, c.CustomerName
    FROM orders AS o
    INNER JOIN customers AS c ON o.CustomerID = c.CustomerID
    ORDER BY o.OrderID DESC
    LIMIT 5
");

/* =====================================================
 * 7. SẢN PHẨM SẮP HẾT HÀNG (tồn kho <= 10)
 * ===================================================== */
$lowStock = fetchAll($connection, "
    SELECT ProductCode, ProductName, StockQuantity
    FROM products
    WHERE IsActive = TRUE AND StockQuantity <= 10
    ORDER BY StockQuantity ASC
    LIMIT 5
");


require_once '/var/www/src/includes/admin/header.php';
require_once '/var/www/src/includes/admin/navbar.php';

?>

<!-- ===== TIÊU ĐỀ TRANG ===== -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="h4 mb-1">Báo cáo thống kê</h2>
        <p class="text-muted mb-0">Tổng quan hoạt động cửa hàng thiết bị công nghệ</p>
    </div>
    <span class="text-muted small">
        <i class="bi bi-calendar3 me-1"></i><?= date('d/m/Y') ?>
    </span>
</div>

<!-- ===== THẺ TỔNG QUAN ===== -->
<div class="row g-3 mb-4">
    <?php
    $cards = [
        ['Doanh thu (đã hoàn thành)', money($totalRevenue),               'bi-cash-coin', 'success'],
        ['Tổng đơn hàng',             number_format($totalOrders),        'bi-receipt',   'primary'],
        ['Đơn chờ xác nhận',          number_format($pendingOrders),      'bi-hourglass-split', 'warning'],
        ['Khách hàng',                number_format($totalCustomers),     'bi-people',    'info'],
        ['Sản phẩm đang bán',         number_format($totalProducts),      'bi-laptop',    'danger'],
    ];
    foreach ($cards as [$label, $value, $icon, $color]):
    ?>
        <div class="col-12 col-sm-6 col-xl">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded-3 bg-<?= $color ?> bg-opacity-10 text-<?= $color ?> d-flex align-items-center justify-content-center flex-shrink-0"
                         style="width:56px;height:56px;font-size:1.6rem;">
                        <i class="bi <?= $icon ?>"></i>
                    </div>
                    <div>
                        <div class="text-muted small"><?= $label ?></div>
                        <div class="fs-5 fw-bold"><?= $value ?></div>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- ===== BIỂU ĐỒ HÀNG 1 ===== -->
<div class="row g-3 mb-3">
    <div class="col-lg-8">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white fw-semibold">Doanh thu 6 tháng gần nhất</div>
            <div class="card-body">
                <div style="height:300px;"><canvas id="revenueChart"></canvas></div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white fw-semibold">Đơn hàng theo trạng thái</div>
            <div class="card-body">
                <?php if ($statusData): ?>
                    <div style="height:300px;"><canvas id="statusChart"></canvas></div>
                <?php else: ?>
                    <p class="text-muted text-center py-5 mb-0">Chưa có dữ liệu</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ===== BIỂU ĐỒ HÀNG 2 ===== -->
<div class="row g-3 mb-4">
    <div class="col-lg-6">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white fw-semibold">Top 5 sản phẩm bán chạy</div>
            <div class="card-body">
                <?php if ($topData): ?>
                    <div style="height:280px;"><canvas id="topProductChart"></canvas></div>
                <?php else: ?>
                    <p class="text-muted text-center py-5 mb-0">Chưa có dữ liệu</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white fw-semibold">Doanh thu theo danh mục</div>
            <div class="card-body">
                <?php if ($categoryData): ?>
                    <div style="height:280px;"><canvas id="categoryChart"></canvas></div>
                <?php else: ?>
                    <p class="text-muted text-center py-5 mb-0">Chưa có dữ liệu</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ===== BẢNG HÀNG 3 ===== -->
<div class="row g-3">

    <!-- Đơn hàng mới nhất -->
    <div class="col-xl-7">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-semibold">Đơn hàng mới nhất</span>
                <a href="/admin/orders/" class="btn btn-sm btn-outline-primary">Xem tất cả</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Mã đơn</th>
                            <th>Ngày đặt</th>
                            <th>Khách hàng</th>
                            <th class="text-end">Tổng tiền</th>
                            <th>Trạng thái</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$recentOrders): ?>
                            <tr><td colspan="5" class="text-center text-muted py-4">Chưa có đơn hàng nào</td></tr>
                        <?php endif; ?>
                        <?php foreach ($recentOrders as $o): ?>
                            <tr>
                                <td>#<?= (int) $o['OrderID'] ?></td>
                                <td><?= htmlspecialchars($o['OrderDate']) ?></td>
                                <td><?= htmlspecialchars($o['CustomerName']) ?></td>
                                <td class="text-end"><?= money((float) $o['TotalAmount']) ?></td>
                                <td>
                                    <span class="badge <?= $badgeClasses[$o['Status']] ?? 'bg-secondary' ?>">
                                        <?= htmlspecialchars($statusText[$o['Status']] ?? $o['Status']) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Sản phẩm sắp hết hàng -->
    <div class="col-xl-5">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-semibold">
                    <i class="bi bi-exclamation-triangle text-warning me-1"></i>Sản phẩm sắp hết hàng
                </span>
                <a href="/admin/products/" class="btn btn-sm btn-outline-primary">Quản lý</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Mã SP</th>
                            <th>Tên sản phẩm</th>
                            <th class="text-end">Tồn kho</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$lowStock): ?>
                            <tr><td colspan="3" class="text-center text-muted py-4">Tất cả sản phẩm đều còn đủ hàng</td></tr>
                        <?php endif; ?>
                        <?php foreach ($lowStock as $p): ?>
                            <tr>
                                <td><?= htmlspecialchars($p['ProductCode']) ?></td>
                                <td><?= htmlspecialchars($p['ProductName']) ?></td>
                                <td class="text-end">
                                    <span class="badge <?= (int) $p['StockQuantity'] === 0 ? 'bg-danger' : 'bg-warning text-dark' ?>">
                                        <?= (int) $p['StockQuantity'] ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<!-- ===== CHART.JS ===== -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
    const palette = ['#0d6efd', '#198754', '#ffc107', '#dc3545', '#6f42c1', '#0dcaf0', '#fd7e14'];
    const fmtNum  = v => new Intl.NumberFormat('vi-VN').format(v);
    const fmtVND  = v => fmtNum(v) + ' đ';

    // 1. Doanh thu theo tháng (đường)
    new Chart(document.getElementById('revenueChart'), {
        type: 'line',
        data: {
            labels: <?= json_encode($monthLabels, JSON_UNESCAPED_UNICODE) ?>,
            datasets: [{
                label: 'Doanh thu',
                data: <?= json_encode($monthRevenue) ?>,
                borderColor: '#0d6efd',
                backgroundColor: 'rgba(13,110,253,.12)',
                fill: true,
                tension: .35,
                pointRadius: 4
            }]
        },
        options: {
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: { callbacks: { label: ctx => fmtVND(ctx.parsed.y) } }
            },
            scales: { y: { beginAtZero: true, ticks: { callback: v => fmtNum(v) } } }
        }
    });

    <?php if ($statusData): ?>
    // 2. Trạng thái đơn hàng (doughnut)
    new Chart(document.getElementById('statusChart'), {
        type: 'doughnut',
        data: {
            labels: <?= json_encode($statusLabels, JSON_UNESCAPED_UNICODE) ?>,
            datasets: [{
                data: <?= json_encode($statusData) ?>,
                backgroundColor: <?= json_encode($statusBg) ?>
            }]
        },
        options: { maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
    });
    <?php endif; ?>

    <?php if ($topData): ?>
    // 3. Top sản phẩm (cột ngang)
    new Chart(document.getElementById('topProductChart'), {
        type: 'bar',
        data: {
            labels: <?= json_encode($topLabels, JSON_UNESCAPED_UNICODE) ?>,
            datasets: [{
                label: 'Đã bán',
                data: <?= json_encode($topData) ?>,
                backgroundColor: palette
            }]
        },
        options: {
            indexAxis: 'y',
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { x: { beginAtZero: true, ticks: { precision: 0 } } }
        }
    });
    <?php endif; ?>

    <?php if ($categoryData): ?>
    // 4. Doanh thu theo danh mục (pie)
    new Chart(document.getElementById('categoryChart'), {
        type: 'pie',
        data: {
            labels: <?= json_encode($categoryLabels, JSON_UNESCAPED_UNICODE) ?>,
            datasets: [{
                data: <?= json_encode($categoryData) ?>,
                backgroundColor: palette
            }]
        },
        options: {
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'right' },
                tooltip: { callbacks: { label: ctx => ctx.label + ': ' + fmtVND(ctx.parsed) } }
            }
        }
    });
    <?php endif; ?>
</script>

<?php

require_once '/var/www/src/includes/admin/footer.php';

$connection->close();