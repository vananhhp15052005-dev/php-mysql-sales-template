<?php
$currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

function navClass(string $currentPath, string $prefix): string
{
    return str_starts_with($currentPath, $prefix) ? 'active' : 'text-white';
}
?>

<!-- SIDEBAR -->
<aside class="offcanvas-lg offcanvas-start bg-black text-white" tabindex="-1" id="sidebar">
    <div class="offcanvas-header border-bottom border-secondary">
        <a href="/admin" class="d-flex align-items-center gap-2 text-white fw-bold fs-5 text-decoration-none">
            <i class="bi bi-shop text-primary"></i>
            Sale Manager
        </a>
    </div>

    <div class="offcanvas-body d-flex flex-column p-3">
        <ul class="nav nav-pills flex-column gap-1 mb-auto">
            <li class="nav-item">
                <a class="nav-link <?= $currentPath === '/admin/' ? 'active' : 'text-white' ?>" href="/admin">
                    <i class="bi bi-bar-chart-fill me-2"></i>Thống kê
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= navClass($currentPath, '/admin/categories') ?>" href="/admin/categories/">
                    <i class="bi bi-tags me-2"></i>Danh mục
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= navClass($currentPath, '/admin/products') ?>" href="/admin/products/">
                    <i class="bi bi-box-seam me-2"></i>Sản phẩm
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= navClass($currentPath, '/admin/purchases') ?>" href="/admin/purchases/">
                    <i class="bi bi-box-arrow-in-down me-2"></i>Nhập hàng
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= navClass($currentPath, '/admin/shippers') ?>" href="/admin/shippers/">
                    <i class="bi bi-truck me-2"></i>Người giao hàng
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= navClass($currentPath, '/admin/orders') ?>" href="/admin/orders/">
                    <i class="bi bi-receipt me-2"></i>Đơn hàng
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= navClass($currentPath, '/admin/employees') ?>" href="/admin/employees/">
                    <i class="bi bi-person-badge me-2"></i>Nhân viên
                </a>
            </li>
        </ul>
    </div>
</aside>

<div class="flex-grow-1 d-flex flex-column min-vh-100 bg-light" style="min-width: 0;">

    <header class="navbar bg-white border-bottom px-3">
        <button class="btn btn-outline-dark d-lg-none" type="button"
                data-bs-toggle="offcanvas" data-bs-target="#sidebar">
            <i class="bi bi-list"></i>
        </button>
        <span class="navbar-text ms-auto">Xin chào, Admin</span>
    </header>

    <main class="flex-grow-1 p-4">



