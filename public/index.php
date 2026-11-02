<?php

require_once '/var/www/src/config/session.php';
require_once '/var/www/src/config/database.php';

$pageTitle = 'Trang chủ - Hệ thống quản lý bán hàng';

// 1. Lấy danh sách 8 sản phẩm mới nhất
$latestProducts = [];
try {
    $sqlProducts = "
        SELECT 
            p.ProductID, 
            p.ProductCode, 
            p.ProductName, 
            p.Price, 
            p.StockQuantity,
            c.CategoryName,
            (
                SELECT pi.ImageFile 
                FROM product_images pi 
                WHERE pi.ProductID = p.ProductID AND pi.IsPrimary = 1 
                LIMIT 1
            ) AS ImageFile
        FROM products p
        LEFT JOIN categories c ON p.CategoryID = c.CategoryID
        WHERE p.IsActive = 1
        ORDER BY p.ProductID DESC
        LIMIT 8
    ";
    $resultProducts = $connection->query($sqlProducts);
    if ($resultProducts) {
        $latestProducts = $resultProducts->fetch_all(MYSQLI_ASSOC);
        $resultProducts->free();
    }
} catch (Throwable $e) {
    error_log("Lỗi tải sản phẩm trang chủ: " . $e->getMessage());
}

// 2. Lấy danh sách danh mục nổi bật
$categoryList = [];
try {
    $sqlCategories = "
        SELECT 
            c.CategoryID, 
            c.CategoryName,
            COUNT(p.ProductID) AS TotalProducts
        FROM categories c
        LEFT JOIN products p ON c.CategoryID = p.CategoryID AND p.IsActive = 1
        GROUP BY c.CategoryID, c.CategoryName
        ORDER BY c.CategoryName ASC
        LIMIT 6
    ";
    $resultCategories = $connection->query($sqlCategories);
    if ($resultCategories) {
        $categoryList = $resultCategories->fetch_all(MYSQLI_ASSOC);
        $resultCategories->free();
    }
} catch (Throwable $e) {
    error_log("Lỗi tải danh mục trang chủ: " . $e->getMessage());
}

require_once '/var/www/src/includes/frontend/header.php';
require_once '/var/www/src/includes/frontend/navbar.php';
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<style>
    :root {
        --brand-indigo: #4f46e5;
        --brand-indigo-hover: #4338ca;
        --brand-indigo-light: #e0e7ff;
        --dark-heading: #0f172a;
        --bg-subtle: #f8fafc;
        --border-color: #e2e8f0;
    }

    body {
        background-color: var(--bg-subtle);
        color: #334155;
    }

    /* Hero Banner Gradient */
    .hero-banner {
        background: linear-gradient(135deg, #3730a3 0%, #4f46e5 50%, #6366f1 100%);
        border-radius: 24px;
        padding: 56px 40px;
        color: #ffffff;
        box-shadow: 0 12px 32px -8px rgba(79, 70, 229, 0.25);
        margin-bottom: 40px;
        position: relative;
        overflow: hidden;
    }

    .hero-banner::after {
        content: '';
        position: absolute;
        width: 260px;
        height: 260px;
        background: rgba(255, 255, 255, 0.08);
        border-radius: 50%;
        bottom: -80px;
        right: -40px;
        pointer-events: none;
    }

    .btn-hero-action {
        background: #ffffff;
        color: var(--brand-indigo);
        font-weight: 700;
        padding: 12px 28px;
        border-radius: 12px;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: all 0.2s ease;
    }

    .btn-hero-action:hover {
        background: #f1f5f9;
        color: var(--brand-indigo-hover);
        transform: translateY(-2px);
    }

    /* Feature Cards */
    .feature-box {
        background: #ffffff;
        border: 1px solid var(--border-color);
        border-radius: 16px;
        padding: 20px;
        display: flex;
        align-items: center;
        gap: 16px;
        transition: transform 0.2s ease;
    }

    .feature-box:hover {
        transform: translateY(-3px);
    }

    .feature-icon-circle {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        background: var(--brand-indigo-light);
        color: var(--brand-indigo);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 22px;
        flex-shrink: 0;
    }

    /* Category Cards */
    .category-chip {
        background: #ffffff;
        border: 1px solid var(--border-color);
        border-radius: 14px;
        padding: 16px;
        text-align: center;
        text-decoration: none;
        color: var(--dark-heading);
        display: block;
        transition: all 0.2s ease;
    }

    .category-chip:hover {
        border-color: var(--brand-indigo);
        color: var(--brand-indigo);
        transform: translateY(-2px);
        box-shadow: 0 8px 16px -4px rgba(15, 23, 42, 0.04);
    }

    /* Product Cards */
    .product-grid-card {
        background: #ffffff;
        border: 1px solid var(--border-color);
        border-radius: 16px;
        overflow: hidden;
        transition: all 0.25s ease;
        height: 100%;
        display: flex;
        flex-direction: column;
    }

    .product-grid-card:hover {
        border-color: #cbd5e1;
        box-shadow: 0 12px 24px -6px rgba(15, 23, 42, 0.08);
        transform: translateY(-4px);
    }

    .product-thumb-holder {
        height: 200px;
        background: #f1f5f9;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 15px;
        position: relative;
    }

    .product-thumb-holder img {
        max-height: 100%;
        max-width: 100%;
        object-fit: contain;
        transition: transform 0.3s ease;
    }

    .product-grid-card:hover .product-thumb-holder img {
        transform: scale(1.05);
    }

    .btn-view-product {
        border: 1px solid var(--border-color);
        background: #ffffff;
        color: var(--dark-heading);
        border-radius: 8px;
        padding: 8px 14px;
        font-weight: 600;
        font-size: 0.88rem;
        text-align: center;
        text-decoration: none;
        transition: all 0.2s ease;
        display: block;
    }

    .btn-view-product:hover {
        background: var(--brand-indigo);
        color: #ffffff;
        border-color: var(--brand-indigo);
    }
</style>

<main class="container py-4">

    <!-- Hero Section Banner -->
    <section class="hero-banner">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <span class="badge bg-white text-dark mb-3 px-3 py-2 rounded-pill fw-bold">
                    <i class="bi bi-stars text-warning me-1"></i> Hệ thống mua sắm trực tuyến
                </span>
                <h1 class="display-6 fw-extrabold mb-3">Hệ thống quản lý & Bán hàng hiện đại</h1>
                <p class="fs-6 opacity-90 mb-4 max-w-lg">
                    Cung cấp trải nghiệm tìm kiếm sản phẩm phong phú, đặt hàng tiện lợi và quản lý kho bãi mượt mà.
                </p>
                <div class="d-flex gap-3 flex-wrap">
                    <a href="/products.php" class="btn-hero-action">
                        Khám phá sản phẩm <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Grid -->
    <section class="row g-3 mb-5">
        <div class="col-md-4">
            <div class="feature-box">
                <div class="feature-icon-circle"><i class="bi bi-shield-check"></i></div>
                <div>
                    <h6 class="fw-bold mb-0 text-dark">Sản phẩm chính hãng</h6>
                    <small class="text-muted">Đảm bảo chất lượng & nguồn gốc</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="feature-box">
                <div class="feature-icon-circle"><i class="bi bi-truck"></i></div>
                <div>
                    <h6 class="fw-bold mb-0 text-dark">Giao hàng nhanh chóng</h6>
                    <small class="text-muted">Xử lý đơn hàng trong ngày</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="feature-box">
                <div class="feature-icon-circle"><i class="bi bi-headset"></i></div>
                <div>
                    <h6 class="fw-bold mb-0 text-dark">Hỗ trợ 24/7</h6>
                    <small class="text-muted">Tư vấn tận tình chu đáo</small>
                </div>
            </div>
        </div>
    </section>

    <!-- Danh mục nổi bật -->
    <?php if (!empty($categoryList)): ?>
        <section class="mb-5">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold text-dark mb-0">Danh mục sản phẩm</h5>
                <a href="/products.php" class="text-decoration-none fw-semibold small text-primary">Xem tất cả <i class="bi bi-chevron-right"></i></a>
            </div>
            <div class="row g-3">
                <?php foreach ($categoryList as $cat): ?>
                    <div class="col-6 col-sm-4 col-md-2">
                        <a href="/products.php?category=<?= (int)$cat['CategoryID'] ?>" class="category-chip">
                            <i class="bi bi-folder2-open fs-3 d-block mb-1 text-primary"></i>
                            <span class="fw-bold d-block text-truncate" style="font-size: 0.88rem;"><?= htmlspecialchars($cat['CategoryName']) ?></span>
                            <small class="text-muted" style="font-size: 0.75rem;"><?= (int)$cat['TotalProducts'] ?> sản phẩm</small>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <!-- Sản phẩm mới nhất -->
    <section class="mb-5">
        <div class="d-flex justify-content-between align-items-end mb-4">
            <div>
                <h4 class="fw-bold text-dark mb-1">Sản phẩm mới nhất</h4>
                <p class="text-muted small mb-0">Những mặt hàng vừa cập nhật vào hệ thống</p>
            </div>
            <a href="/products.php" class="btn btn-sm btn-outline-secondary rounded-2 fw-semibold">Tất cả sản phẩm</a>
        </div>

        <?php if (empty($latestProducts)): ?>
            <div class="p-5 bg-white border rounded-4 text-center text-muted">
                <i class="bi bi-box-seam fs-2 d-block mb-2"></i>
                Hiện chưa có sản phẩm nào được cập nhật.
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($latestProducts as $p): ?>
                    <div class="col-12 col-sm-6 col-md-4 col-lg-3">
                        <div class="product-grid-card">
                            <div class="product-thumb-holder">
                                <?php if (!empty($p['ImageFile'])): ?>
                                    <img src="/upload/products/<?= htmlspecialchars($p['ImageFile']) ?>" alt="<?= htmlspecialchars($p['ProductName']) ?>" loading="lazy">
                                <?php else: ?>
                                    <i class="bi bi-image text-muted fs-1"></i>
                                <?php endif; ?>
                            </div>

                            <div class="p-3 d-flex flex-column flex-grow-1">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="badge bg-light text-secondary border">
                                        <?= htmlspecialchars($p['CategoryName'] ?? 'Sản phẩm') ?>
                                    </span>
                                    <span class="badge bg-light text-muted border font-monospace" style="font-size: 0.7rem;">
                                        <?= htmlspecialchars($p['ProductCode']) ?>
                                    </span>
                                </div>

                                <h6 class="fw-bold text-dark mb-2 text-truncate" title="<?= htmlspecialchars($p['ProductName']) ?>">
                                    <?= htmlspecialchars($p['ProductName']) ?>
                                </h6>

                                <div class="mt-auto pt-2 border-top">
                                    <div class="fs-5 fw-bold text-primary mb-2">
                                        <?= number_format((float)$p['Price'], 0, ',', '.') ?> đ
                                    </div>
                                    <a href="/product-detail.php?id=<?= (int)$p['ProductID'] ?>" class="btn-view-product">
                                        Xem thông tin
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

</main>

<?php
require_once '/var/www/src/includes/frontend/footer.php';
?>