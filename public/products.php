<?php

require_once '/var/www/src/config/session.php';
require_once '/var/www/src/config/database.php';

$pageTitle = 'Danh sách sản phẩm';

// 1. Tải danh mục sản phẩm cho Form lọc
$sqlCategories = "
    SELECT CategoryID, CategoryName 
    FROM categories 
    ORDER BY CategoryName ASC
";

$categoryResult =$connection->query($sqlCategories);$categories = [];

if ($categoryResult) {
    while ($category =$categoryResult->fetch_assoc()) {
        $categories[] =$category;
    }
    $categoryResult->free();
}

// 2. Tiếp nhận và xử lý tham số tìm kiếm, phân trang
$categoryID = filter_input(INPUT_GET, 'category', FILTER_VALIDATE_INT) ?: 0;
$keyword    = trim($_GET['keyword'] ?? '');$itemsPerPage = filter_input(INPUT_GET, 'limit', FILTER_VALIDATE_INT) ?: 6; // Mặc định 6 sản phẩm/trang
if (!in_array($itemsPerPage, [6, 12, 24, 48])) {$itemsPerPage = 6;
}

$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1;
if ($page < 1) {$page = 1;
}

$searchKeyword = '\%' .$keyword . '%';

// 3. Đếm tổng số sản phẩm thỏa điều kiện lọc
$sqlCount = "
    SELECT COUNT(*) AS TotalProducts
    FROM products p
    INNER JOIN categories c ON p.CategoryID = c.CategoryID
    WHERE p.IsActive = 1
";

$paramsCount = [];$typesCount  = '';

if ($categoryID > 0) {$sqlCount .= " AND p.CategoryID = ?";
    $paramsCount[] =$categoryID;
    $typesCount   .= 'i';
}

if ($keyword !== '') {$sqlCount .= " AND (p.ProductName LIKE ? OR p.ProductCode LIKE ?)";
    $paramsCount[] =$searchKeyword;
    $paramsCount[] =$searchKeyword;
    $typesCount   .= 'ss';
}

$stmtCount = $connection->prepare($sqlCount);

if (!empty($paramsCount)) {$stmtCount->bind_param($typesCount, ...$paramsCount);
}

$stmtCount->execute();
$countResult =$stmtCount->get_result();
$countRow    =$countResult->fetch_assoc();
$totalProducts = (int) ($countRow['TotalProducts'] ?? 0);

$countResult->free();$stmtCount->close();

// Tính toán tham số phân trang
$totalPages = (int) ceil($totalProducts / $itemsPerPage);
if ($totalPages > 0 && $page >$totalPages) {
    $page =$totalPages;
}

$offset = ($page - 1) *$itemsPerPage;

// 4. Truy vấn lấy danh sách sản phẩm
$sql = "
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
    INNER JOIN categories c ON p.CategoryID = c.CategoryID
    WHERE p.IsActive = 1
";

$params = [];$types  = '';

if ($categoryID > 0) {$sql .= " AND p.CategoryID = ?";
    $params[] =$categoryID;
    $types   .= 'i';
}

if ($keyword !== '') {$sql .= " AND (p.ProductName LIKE ? OR p.ProductCode LIKE ?)";
    $params[] =$searchKeyword;
    $params[] =$searchKeyword;
    $types   .= 'ss';
}

$sql .= " ORDER BY p.ProductID DESC LIMIT ? OFFSET ?";
$params[] =$itemsPerPage;
$params[] =$offset;
$types   .= 'ii';

$stmt = $connection->prepare($sql);
$stmt->bind_param($types, ...$params);$stmt->execute();
$result =$stmt->get_result();

require_once '/var/www/src/includes/frontend/header.php';
require_once '/var/www/src/includes/frontend/navbar.php';
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<style>
    :root {
        --brand-primary: #4f46e5;
        --brand-hover: #4338ca;
        --bg-subtle: #f8fafc;
        --border-card: #e2e8f0;
    }

    .catalog-card {
        background: #ffffff;
        border: 1px solid var(--border-card);
        border-radius: 16px;
        overflow: hidden;
        transition: all 0.25s ease;
        height: 100%;
        display: flex;
        flex-direction: column;
    }

    .catalog-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 12px 24px -6px rgba(15, 23, 42, 0.08);
        border-color: #cbd5e1;
    }

    .catalog-img-wrapper {
        height: 220px;
        background: #f1f5f9;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        position: relative;
        padding: 15px;
    }

    .catalog-img-wrapper img {
        max-height: 100%;
        max-width: 100%;
        object-fit: contain;
        transition: transform 0.3s ease;
    }

    .catalog-card:hover .catalog-img-wrapper img {
        transform: scale(1.05);
    }

    .filter-panel {
        background: #ffffff;
        border: 1px solid var(--border-card);
        border-radius: 16px;
        box-shadow: 0 4px 12px rgba(15, 23, 42, 0.03);
    }
</style>

<main class="container py-5">

    <!-- Tiêu đề trang -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 fw-bold text-dark mb-1">Cửa hàng sản phẩm</h1>
            <p class="text-muted small mb-0">Tìm kiếm và lựa chọn sản phẩm phù hợp dành cho bạn</p>
        </div>
        <span class="badge bg-light text-dark border px-3 py-2 fw-semibold">
            Tổng cộng: <strong class="text-primary"><?= $totalProducts ?></strong> sản phẩm
        </span>
    </div>

    <!-- Thanh tìm kiếm & Bộ lọc -->
    <div class="filter-panel p-4 mb-4">
        <form method="get" action="/products.php" class="row g-3 align-items-end">
            <div class="col-md-5 col-lg-4">
                <label for="keyword" class="form-label fw-semibold text-secondary small">Từ khóa tìm kiếm</label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-search"></i></span>
                    <input type="text" name="keyword" id="keyword" class="form-control border-start-0 ps-0" 
                           value="<?= htmlspecialchars($keyword) ?>" placeholder="Nhập tên hoặc mã sản phẩm...">
                </div>
            </div>

            <div class="col-md-4 col-lg-3">
                <label for="category" class="form-label fw-semibold text-secondary small">Danh mục sản phẩm</label>
                <select name="category" id="category" class="form-select">
                    <option value="0">Tất cả danh mục</option>
                    <?php foreach ($categories as$cat): ?>
                        <option value="<?= (int) $cat['CategoryID'] ?>" <?= $categoryID === (int)$cat['CategoryID'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['CategoryName']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-3 col-lg-2">
                <label for="limit" class="form-label fw-semibold text-secondary small">Hiển thị / trang</label>
                <select name="limit" id="limit" class="form-select">
                    <option value="6" <?= $itemsPerPage === 6 ? 'selected' : '' ?>>6 sản phẩm</option>
                    <option value="12" <?= $itemsPerPage === 12 ? 'selected' : '' ?>>12 sản phẩm</option>
                    <option value="24" <?= $itemsPerPage === 24 ? 'selected' : '' ?>>24 sản phẩm</option>
                </select>
            </div>

            <div class="col-md-12 col-lg-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary fw-bold flex-grow-1">
                    <i class="bi bi-funnel me-1"></i> Áp dụng
                </button>
                <a href="/products.php" class="btn btn-outline-secondary" title="Xóa bộ lọc">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
            </div>
        </form>
    </div>

    <!<?php

require_once '/var/www/src/config/session.php';
require_once '/var/www/src/config/database.php';

$sqlCategories = "
    SELECT
        CategoryID,
        CategoryName
    FROM categories
    ORDER BY CategoryName
";

$categoryResult = $connection->query($sqlCategories);

$categories = [];

while ($category = $categoryResult->fetch_assoc()) {
    $categories[] = $category;
}

$categoryResult->free();

$categoryID = isset($_GET['category'])
    ? (int) $_GET['category']
    : 0;

$keyword = isset($_GET['keyword'])
    ? trim($_GET['keyword'])
    : '';

$itemsPerPage = 2;

$page = isset($_GET['page'])
    ? (int) $_GET['page']
    : 1;

if ($page < 1) {
    $page = 1;
}

$searchKeyword = '%' . $keyword . '%';

$sqlCount = "
    SELECT
        COUNT(*) AS TotalProducts
    FROM
        products p,
        categories c
    WHERE
        p.CategoryID = c.CategoryID
        AND p.IsActive = 1
";

if ($categoryID > 0) {
    $sqlCount .= "
        AND p.CategoryID = ?
    ";
}

if ($keyword !== '') {
    $sqlCount .= "
        AND (
            p.ProductName LIKE ?
            OR p.ProductCode LIKE ?
        )
    ";
}

$stmtCount = $connection->prepare($sqlCount);


if ($categoryID > 0 && $keyword !== '') {

    $stmtCount->bind_param(
        'iss',
        $categoryID,
        $searchKeyword,
        $searchKeyword
    );

} elseif ($categoryID > 0) {

    $stmtCount->bind_param(
        'i',
        $categoryID
    );

} elseif ($keyword !== '') {

    $stmtCount->bind_param(
        'ss',
        $searchKeyword,
        $searchKeyword
    );
}

$stmtCount->execute();

$countResult = $stmtCount->get_result();
$countRow = $countResult->fetch_assoc();

$totalProducts = (int) $countRow['TotalProducts'];

$countResult->free();
$stmtCount->close();

$totalPages = (int) ceil(
    $totalProducts / $itemsPerPage
);

if ($totalPages > 0 && $page > $totalPages) {
    $page = $totalPages;
}

$offset = ($page - 1) * $itemsPerPage;

// $sql = "
//     SELECT
//         p.ProductID,
//         p.ProductCode,
//         p.ProductName,
//         p.Price,
//         c.CategoryName,

//         (
//             SELECT pi.ImageFile
//             FROM product_images pi
//             WHERE pi.ProductID = p.ProductID
//               AND pi.IsPrimary = 1
//             LIMIT 1
//         ) AS ImageFile

//     FROM
//         products p,
//         categories c

//     WHERE
//         p.CategoryID = c.CategoryID
//         AND p.IsActive = 1

//     ORDER BY
//         p.ProductID DESC
// ";

$sql = "
    SELECT
        p.ProductID,
        p.ProductCode,
        p.ProductName,
        p.Price,
        c.CategoryName,

        (
            SELECT pi.ImageFile
            FROM product_images pi
            WHERE pi.ProductID = p.ProductID
              AND pi.IsPrimary = 1
            LIMIT 1
        ) AS ImageFile

    FROM
        products p,
        categories c

    WHERE
        p.CategoryID = c.CategoryID
        AND p.IsActive = 1
";

if ($categoryID > 0) {
    $sql .= "
        AND p.CategoryID = ?
    ";
}

if ($keyword !== '') {
    $sql .= "
        AND (
            p.ProductName LIKE ?
            OR p.ProductCode LIKE ?
        )
    ";
}

$sql .= "
    ORDER BY
        p.ProductID DESC
    LIMIT ?
    OFFSET ?
";

$stmt = $connection->prepare($sql);

if ($categoryID > 0 && $keyword !== '') {

    $stmt->bind_param(
        'issii',
        $categoryID,
        $searchKeyword,
        $searchKeyword,
        $itemsPerPage,
        $offset
    );

} elseif ($categoryID > 0) {

    $stmt->bind_param(
        'iii',
        $categoryID,
        $itemsPerPage,
        $offset
    );

} elseif ($keyword !== '') {

    $stmt->bind_param(
        'ssii',
        $searchKeyword,
        $searchKeyword,
        $itemsPerPage,
        $offset
    );

} else {

    $stmt->bind_param(
        'ii',
        $itemsPerPage,
        $offset
    );
}

$stmt->execute();

$result = $stmt->get_result();

// $result = $connection->query($sql);

$pageTitle = 'Sản phẩm';

require_once '/var/www/src/includes/frontend/header.php';
require_once '/var/www/src/includes/frontend/navbar.php';
?>

<main class="container py-5">

    <div class="mb-4">

        <h1>Sản phẩm</h1>

        <p class="text-muted">
            Khám phá các sản phẩm hiện có tại cửa hàng.
        </p>

    </div>

    <form
        method="get"
        action="/products.php"
        class="row g-3 mb-4"
    >

        <div class="col-md-6 col-lg-4">

            <label
                for="keyword"
                class="form-label"
            >
                Tìm sản phẩm
            </label>

            <input
                type="text"
                name="keyword"
                id="keyword"
                class="form-control"
                value="<?= htmlspecialchars($keyword) ?>"
                placeholder="Nhập tên hoặc mã sản phẩm"
            >

        </div>

        <div class="col-md-6 col-lg-4">

            <label
                for="category"
                class="form-label"
            >
                Danh mục
            </label>

            <select
                name="category"
                id="category"
                class="form-select"
            >

                <option value="0">
                    Tất cả danh mục
                </option>

                <?php foreach ($categories as $category): ?>

                    <option
                        value="<?= (int) $category['CategoryID'] ?>"
                        <?=
                            $categoryID ===
                            (int) $category['CategoryID']
                                ? 'selected'
                                : ''
                        ?>
                    >
                        <?=
                            htmlspecialchars(
                                $category['CategoryName']
                            )
                        ?>
                    </option>

                <?php endforeach; ?>

            </select>

        </div>

        <div class="col-md-auto align-self-end">

            <button
                type="submit"
                class="btn btn-primary"
            >
                Tìm kiếm
            </button>

            <a
                href="/products.php"
                class="btn btn-outline-secondary"
            >
                Xóa bộ lọc
            </a>

        </div>

    </form>

    <p class="text-muted">
        Tìm thấy
        <strong><?= $totalProducts ?></strong>
        sản phẩm.
    </p>

    <?php if ($result->num_rows > 0): ?>

        <div class="row g-4">

            <?php while ($product = $result->fetch_assoc()): ?>

                <div class="col-md-6 col-lg-4">

                    <div class="card h-100">

                        <?php if (!empty($product['ImageFile'])): ?>

                            <div
                                class="bg-light d-flex
                                       align-items-center
                                       justify-content-center
                                       p-3"
                                style="height: 260px;"
                            >

                                <img
                                    src="/upload/products/<?=
                                        htmlspecialchars(
                                            $product['ImageFile']
                                        )
                                    ?>"
                                    alt="<?=
                                        htmlspecialchars(
                                            $product['ProductName']
                                        )
                                    ?>"
                                    style="
                                        width: 100%;
                                        height: 100%;
                                        object-fit: contain;
                                    "
                                >

                            </div>

                        <?php else: ?>

                            <div
                                class="bg-light d-flex
                                       align-items-center
                                       justify-content-center
                                       text-muted"
                                style="height: 260px;"
                            >
                                Chưa có hình ảnh
                            </div>

                        <?php endif; ?>

                        <div class="card-body d-flex flex-column">

                            <p class="text-muted small mb-1">
                                <?=
                                    htmlspecialchars(
                                        $product['CategoryName']
                                    )
                                ?>
                            </p>

                            <h5 class="card-title">
                                <?=
                                    htmlspecialchars(
                                        $product['ProductName']
                                    )
                                ?>
                            </h5>

                            <p class="text-muted small">
                                Mã sản phẩm:
                                <?=
                                    htmlspecialchars(
                                        $product['ProductCode']
                                    )
                                ?>
                            </p>

                            <p class="fw-bold fs-5 mb-3">
                                <?=
                                    number_format(
                                        (float) $product['Price'],
                                        0,
                                        ',',
                                        '.'
                                    )
                                ?> đ
                            </p>

                            <a
                                href="/product-detail.php?id=<?=
                                    (int) $product['ProductID']
                                ?>"
                                class="btn btn-outline-primary mt-auto"
                            >
                                Xem chi tiết
                            </a>

                        </div>

                    </div>

                </div>

            <?php endwhile; ?>

        </div>

    <?php else: ?>

        <div class="alert alert-info">
            Hiện chưa có sản phẩm nào để hiển thị.
        </div>

    <?php endif; ?>

</main>

<?php if ($totalPages > 1): ?>

    <nav
        class="mt-5"
        aria-label="Phân trang sản phẩm"
    >
        <ul
            class="pagination
                   justify-content-center
                   flex-wrap"
        >
        <?php
            $previousQuery = http_build_query([
                'keyword' => $keyword,
                'category' => $categoryID,
                'page' => max(1, $page - 1)
            ]);
        ?>

        <li
            class="page-item <?=
                $page <= 1
                    ? 'disabled'
                    : ''
            ?>"
        >
            <a
                class="page-link"
                href="/products.php?<?= $previousQuery ?>"
            >
                &laquo; Trước
            </a>
        </li>
        <?php for (
            $pageNumber = 1;
            $pageNumber <= $totalPages;
            $pageNumber++
        ): ?>

            <?php
            $query = http_build_query([
                'keyword' => $keyword,
                'category' => $categoryID,
                'page' => $pageNumber
            ]);
            ?>

            <li
                class="page-item <?=
                    $pageNumber === $page
                        ? 'active'
                        : ''
                ?>"
            >
                <a
                    class="page-link"
                    href="/products.php?<?= $query ?>"
                >
                    <?= $pageNumber ?>
                </a>
            </li>

        <?php endfor; ?>
        <?php
            $nextQuery = http_build_query([
                'keyword' => $keyword,
                'category' => $categoryID,
                'page' => min(
                    $totalPages,
                    $page + 1
                )
            ]);
            ?>

            <li
                class="page-item <?=
                    $page >= $totalPages
                        ? 'disabled'
                        : ''
                ?>"
            >
                <a
                    class="page-link"
                    href="/products.php?<?= $nextQuery ?>"
                >
                    Sau &raquo;
                </a>
            </li>
         </ul>

    </nav>

<?php endif; ?>
<?php

$result->free();
$stmt->close();

require_once '/var/www/src/includes/frontend/footer.php';