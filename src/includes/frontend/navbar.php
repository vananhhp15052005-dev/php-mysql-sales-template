<?php

$cartCount = array_sum(
    $_SESSION['cart'] ?? []
);

$isLoggedIn = isset($_SESSION['customer_id']);

$customerName =
    $_SESSION['customer_name'] ?? '';

?>

<nav class="navbar navbar-expand-lg bg-dark navbar-dark">

    <div class="container d-flex align-items-center justify-content-between">
        <a class="navbar-brand" href="/">
            Sales Management
        </a>

        <button
            class="navbar-toggler"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#frontendNavbar"
        >
            <span class="navbar-toggler-icon"></span>
        </button>

        <div
            class="collapse navbar-collapse"
            id="frontendNavbar"
        >

            <ul class="navbar-nav">

                <li class="nav-item">
                    <a class="nav-link" href="/">
                        Trang chủ
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="/products.php">
                        Sản phẩm
                    </a>
                </li>

            </ul>
            
        </div>
        <div class="d-flex gap-2">
            <a
                class="btn btn-outline-light btn-sm"
                href="/cart.php"
            >
                Giỏ hàng (<?= (int) $cartCount ?>)
            </a>

            <?php if ($isLoggedIn): ?>

                <span class="text-light">
                    <?= htmlspecialchars($customerName) ?>
                </span>

                <a
                    class="btn btn-outline-light btn-sm"
                    href="/logout.php"
                >
                    Đăng xuất
                </a>

            <?php else: ?>

                <a
                    class="btn btn-outline-light btn-sm"
                    href="/register.php"
                >
                    Đăng ký
                </a>

                <a
                    class="btn btn-outline-light btn-sm"
                    href="/login.php"
                >
                    Đăng nhập
                </a>

            <?php endif; ?>
            
            <a
                class="btn btn-outline-light btn-sm"
                href="/admin/"
            >
                Quản trị
            </a>
        </div>
    </div>

</nav>  


