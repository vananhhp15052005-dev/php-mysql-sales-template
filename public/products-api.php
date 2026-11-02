<?php

require_once '/var/www/src/config/session.php';

$pageTitle = 'Khám phá sản phẩm qua REST API';

require_once '/var/www/src/includes/frontend/header.php';
require_once '/var/www/src/includes/frontend/navbar.php';
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<style>
    :root {
        --api-accent: #2563eb;
        --api-accent-hover: #1d4ed8;
        --bg-subtle: #f8fafc;
        --border-subtle: #e2e8f0;
    }

    .filter-card {
        background: #ffffff;
        border: 1px solid var(--border-subtle);
        border-radius: 16px;
        box-shadow: 0 4px 12px rgba(15, 23, 42, 0.03);
    }

    .product-api-card {
        background: #ffffff;
        border: 1px solid var(--border-subtle);
        border-radius: 16px;
        overflow: hidden;
        transition: all 0.25s ease;
        height: 100%;
        display: flex;
        flex-direction: column;
    }

    .product-api-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 12px 24px -6px rgba(15, 23, 42, 0.08);
        border-color: #cbd5e1;
    }

    .product-img-holder {
        height: 220px;
        background: #f1f5f9;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        position: relative;
    }

    .product-img-holder img {
        max-height: 100%;
        max-width: 100%;
        object-fit: contain;
        transition: transform 0.3s ease;
    }

    .product-api-card:hover .product-img-holder img {
        transform: scale(1.05);
    }

    /* Skeleton Loading Placeholder */
    .skeleton-box {
        background: linear-gradient(90deg, #f1f5f9 25%, #e2e8f0 50%, #f1f5f9 75%);
        background-size: 200% 100%;
        animation: loadingSkeleton 1.5s infinite;
        border-radius: 8px;
    }

    @keyframes loadingSkeleton {
        0% { background-position: 200% 0; }
        100% { background-position: -200% 0; }
    }
</style>

<main class="container py-5">

    <!-- Tiêu đề trang -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 fw-bold text-dark mb-1">Cửa hàng sản phẩm API</h1>
            <p class="text-muted small mb-0">Dữ liệu được đồng bộ trực tiếp từ hệ thống RESTful API</p>
        </div>
        <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2 rounded-pill fw-semibold">
            <i class="bi bi-cloud-check me-1"></i> REST API Active
        </span>
    </div>

    <!-- Khung bộ lọc -->
    <div class="filter-card p-4 mb-4">
        <form id="apiFilterForm" class="row g-3">
            <div class="col-md-6 col-lg-7">
                <label for="searchInput" class="form-label fw-semibold text-secondary small">Tìm kiếm từ khóa</label>
                <div class="input-group">
                    <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-search"></i></span>
                    <input type="text" id="searchInput" class="form-control border-start-0 ps-0" placeholder="Nhập tên hoặc mã sản phẩm...">
                </div>
            </div>

            <div class="col-md-4 col-lg-3">
                <label for="categorySelect" class="form-label fw-semibold text-secondary small">Danh mục sản phẩm</label>
                <select id="categorySelect" class="form-select">
                    <option value="">Tất cả danh mục</option>
                </select>
            </div>

            <div class="col-md-2 col-lg-2 d-flex align-items-end gap-2">
                <button type="submit" class="btn btn-primary w-100 fw-bold">
                    <i class="bi bi-funnel me-1"></i> Lọc
                </button>
                <button type="button" id="btnResetFilter" class="btn btn-outline-secondary" title="Xóa bộ lọc">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </button>
            </div>
        </form>
    </div>

    <!-- Thông báo lỗi -->
    <div id="apiErrorMessage" class="alert alert-danger d-none rounded-3 shadow-sm mb-4" role="alert"></div>

    <!-- Danh sách sản phẩm -->
    <div id="productContainer" class="row g-4"></div>

</main>

<script>
    const formElement = document.getElementById('apiFilterForm');
    const searchInputElement = document.getElementById('searchInput');
    const categorySelectElement = document.getElementById('categorySelect');
    const resetBtnElement = document.getElementById('btnResetFilter');
    const errorContainerElement = document.getElementById('apiErrorMessage');
    const productContainerElement = document.getElementById('productContainer');

    let debounceTimer = null;

    // Hàm mã hóa ký tự HTML chống XSS
    function sanitizeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str ?? '';
        return div.innerHTML;
    }

    // Định dạng đơn giá VND
    function formatCurrency(amount) {
        return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount);
    }

    // Hiển thị khung chờ Skeleton
    function renderSkeletons(count = 6) {
        let html = '';
        for (let i = 0; i < count; i++) {
            html += `
                <div class="col-md-6 col-lg-4">
                    <div class="product-api-card p-3">
                        <div class="skeleton-box mb-3" style="height: 200px;"></div>
                        <div class="skeleton-box mb-2" style="height: 16px; width: 40%;"></div>
                        <div class="skeleton-box mb-2" style="height: 22px; width: 80%;"></div>
                        <div class="skeleton-box mb-3" style="height: 20px; width: 50%;"></div>
                        <div class="skeleton-box mt-auto" style="height: 38px;"></div>
                    </div>
                </div>
            `;
        }
        productContainerElement.innerHTML = html;
    }

    // Tạo HTML Template Card Sản phẩm
    function buildProductCard(item) {
        const title = sanitizeHtml(item.name);
        const code = sanitizeHtml(item.code);
        const category = sanitizeHtml(item.category);
        const stock = parseInt(item.stock) || 0;

        const imageSrc = item.image 
            ? `/upload/products/${encodeURIComponent(item.image)}`
            : null;

        const imageMarkup = imageSrc
            ? `<img src="${imageSrc}" alt="${title}" loading="lazy">`
            : `<div class="text-muted text-center"><i class="bi bi-image fs-1 d-block"></i><span class="small">Chưa có ảnh</span></div>`;

        let stockBadge = stock > 0 
            ? `<span class="badge bg-success-subtle text-success border border-success-subtle">Tồn: ${stock}</span>`
            : `<span class="badge bg-danger-subtle text-danger border border-danger-subtle">Hết hàng</span>`;