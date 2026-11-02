
<?php
$pageTitle = 'Quản lý danh mục';

require_once '/var/www/src/config/database.php';

$result = false;
$totalCategories = 0;
$errorMessage = '';

try {
    $sql = "
        SELECT CategoryID, CategoryName, Description
        FROM categories
        ORDER BY CategoryID DESC
    ";

    $result = $connection->query($sql);
    $totalCategories = $result->num_rows;
} catch (mysqli_sql_exception $e) {
    error_log('Lỗi truy vấn danh mục: ' . $e->getMessage());
    $errorMessage = 'Không thể tải danh sách danh mục. Vui lòng kiểm tra cơ sở dữ liệu.';
}

require_once '/var/www/src/includes/admin/header.php';
require_once '/var/www/src/includes/admin/navbar.php';
?>

<link rel="stylesheet"
      href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<style>
    :root {
        --primary: #635bdb;
        --primary-dark: #4f46c8;
        --primary-light: #f0efff;
        --text-main: #20243a;
        --text-muted: #7b8195;
        --border-color: #eceef5;
        --page-bg: #f7f8fc;
    }

    body {
        background: var(--page-bg);
        color: var(--text-main);
    }

    .category-page {
        max-width: 1500px;
        margin: 0 auto;
        padding: 30px 24px 45px;
    }

    /* Tiêu đề */
    .category-hero {
        position: relative;
        overflow: hidden;
        padding: 30px 32px;
        border-radius: 22px;
        color: white;
        background: linear-gradient(120deg, #5146cf, #8175ed);
        box-shadow: 0 12px 30px rgba(91, 79, 209, .18);
        margin-bottom: 25px;
    }

    .category-hero::after {
        content: "";
        position: absolute;
        width: 230px;
        height: 230px;
        border-radius: 50%;
        right: -55px;
        top: -105px;
        background: rgba(255, 255, 255, .09);
        pointer-events: none;
    }

    .hero-content {
        position: relative;
        z-index: 1;
    }

    .hero-icon {
        width: 54px;
        height: 54px;
        display: inline-flex;
        justify-content: center;
        align-items: center;
        font-size: 25px;
        border-radius: 16px;
        background: rgba(255, 255, 255, .17);
        margin-bottom: 17px;
    }

    .category-hero h2 {
        font-size: clamp(1.45rem, 3vw, 2rem);
        font-weight: 750;
        letter-spacing: -.5px;
        margin-bottom: 8px;
    }

    .category-hero p {
        margin: 0;
        color: rgba(255, 255, 255, .82);
        font-size: .95rem;
    }

    .btn-add-category {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 9px;
        padding: 12px 19px;
        border-radius: 12px;
        border: 1px solid rgba(255, 255, 255, .65);
        background: white;
        color: var(--primary-dark);
        text-decoration: none;
        font-weight: 700;
        font-size: .9rem;
        transition: .2s ease;
        white-space: nowrap;
    }

    .btn-add-category:hover {
        color: var(--primary-dark);
        background: #f1efff;
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(31, 24, 100, .18);
    }

    /* Thẻ thống kê */
    .stat-card {
        display: flex;
        align-items: center;
        gap: 17px;
        background: #fff;
        border: 1px solid var(--border-color);
        border-radius: 18px;
        padding: 22px 24px;
        margin-bottom: 25px;
        box-shadow: 0 4px 15px rgba(32, 36, 58, .025);
    }

    .stat-icon {
        width: 58px;
        height: 58px;
        flex-shrink: 0;
        display: flex;
        justify-content: center;
        align-items: center;
        border-radius: 17px;
        background: var(--primary-light);
        color: var(--primary);
        font-size: 25px;
    }

    .stat-label {
        color: var(--text-muted);
        font-size: .86rem;
        margin-bottom: 5px;
    }

    .stat-value {
        font-size: 1.8rem;
        line-height: 1.2;
        font-weight: 750;
        color: var(--text-main);
    }

    .stat-note {
        margin-left: auto;
        color: #26976b;
        background: #eaf8f1;
        padding: 7px 11px;
        border-radius: 10px;
        font-size: .78rem;
        font-weight: 600;
        white-space: nowrap;
    }

    /* Bảng danh mục */
    .category-card {
        background: #fff;
        border: 1px solid var(--border-color);
        border-radius: 20px;
        overflow: hidden;
        box-shadow: 0 5px 22px rgba(32, 36, 58, .035);
    }

    .table-heading {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 15px;
        padding: 24px 26px;
        border-bottom: 1px solid var(--border-color);
    }

    .table-heading h5 {
        font-size: 1.05rem;
        font-weight: 750;
        margin: 0 0 5px;
    }

    .table-heading p {
        color: var(--text-muted);
        font-size: .85rem;
        margin: 0;
    }

    .count-pill {
        color: var(--primary-dark);
        background: var(--primary-light);
        border-radius: 10px;
        padding: 8px 12px;
        font-size: .8rem;
        font-weight: 700;
        white-space: nowrap;
    }

    .category-table {
        margin: 0;
        border-collapse: separate;
        border-spacing: 0;
    }

    .category-table thead th {
        padding: 16px 24px;
        color: #858ba0;
        background: #fafbfe;
        border-bottom: 1px solid var(--border-color);
        font-size: .73rem;
        text-transform: uppercase;
        letter-spacing: .65px;
        font-weight: 750;
        white-space: nowrap;
    }

    .category-table tbody td {
        padding: 19px 24px;
        vertical-align: middle;
        border-bottom: 1px solid #f0f1f7;
        font-size: .9rem;
    }

    .category-table tbody tr:last-child td {
        border-bottom: 0;
    }

    .category-table tbody tr {
        transition: background .18s ease;
    }

    .category-table tbody tr:hover {
        background: #faf9ff;
    }

    .category-id {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 40px;
        height: 34px;
        border-radius: 10px;
        background: #f0efff;
        color: var(--primary-dark);
        font-weight: 750;
        font-size: .84rem;
    }

    .category-info {
        display: flex;
        align-items: center;
        gap: 13px;
        min-width: 180px;
    }

    .category-avatar {
        width: 43px;
        height: 43px;
        flex-shrink: 0;
        display: flex;
        justify-content: center;
        align-items: center;
        border-radius: 13px;
        color: var(--primary);
        background: #f0efff;
        font-size: 19px;
    }

    .category-name {
        color: #292d43;
        font-weight: 700;
        margin-bottom: 4px;
        overflow-wrap: anywhere;
    }

    .category-subtitle {
        color: #a0a5b5;
        font-size: .77rem;
    }

    .category-description {
        color: #72788c;
        max-width: 440px;
        min-width: 180px;
        line-height: 1.65;
        overflow-wrap: anywhere;
    }

    .description-empty {
        color: #a3a8b6;
        font-style: italic;
    }

    .action-group {
        display: flex;
        justify-content: flex-end;
        gap: 8px;
    }

    .btn-action {
        width: 38px;
        height: 38px;
        display: inline-flex;
        justify-content: center;
        align-items: center;
        border: 1px solid var(--border-color);
        border-radius: 11px;
        background: white;
        font-size: 16px;
        text-decoration: none;
        transition: all .18s ease;
    }

    .btn-edit {
        color: #d18b22;
    }

    .btn-edit:hover {
        color: #a96a08;
        background: #fff6e6;
        border-color: #f4d9a6;
    }

    .btn-delete {
        color: #e05265;
    }

    .btn-delete:hover {
        color: #c52f45;
        background: #fff0f2;
        border-color: #f5c7ce;
    }

    .empty-state {
        text-align: center;
        padding: 65px 20px !important;
    }

    .empty-icon {
        width: 75px;
        height: 75px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 18px;
        border-radius: 23px;
        background: #f3f2ff;
        color: #8175ed;
        font-size: 32px;
    }

    .empty-state h6 {
        font-size: 1rem;
        font-weight: 750;
        margin-bottom: 8px;
    }

    .empty-state p {
        color: var(--text-muted);
        font-size: .87rem;
        margin-bottom: 20px;
    }

    .alert-category {
        border: 0;
        border-radius: 13px;
        padding: 16px 20px;
        margin-bottom: 20px;
    }

    @media (max-width: 767px) {
        .category-page {
            padding: 20px 13px 30px;
        }

        .category-hero {
            padding: 24px 21px;
            border-radius: 18px;
        }

        .hero-layout {
            align-items: flex-start !important;
            flex-direction: column;
        }

        .btn-add-category {
            width: 100%;
        }

        .stat-card {
            padding: 18px;
            gap: 13px;
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            font-size: 21px;
        }

        .stat-value {
            font-size: 1.55rem;
        }

        .stat-note {
            display: none;
        }

        .table-heading {
            padding: 19px 17px;
        }

        .category-table thead th,
        .category-table tbody td {
            padding: 15px 16px;
        }
    }
</style>

<div class="category-page">

    <!-- Tiêu đề trang -->
    <section class="category-hero">
        <div class="hero-content">
            <div class="d-flex justify-content-between gap-4 hero-layout">
                <div>
                    <div class="hero-icon">
                        <i class="bi bi-tags-fill"></i>
                    </div>

                    <h2>Quản lý danh mục</h2>

                    <p>
                        Quản lý và tổ chức các danh mục trong hệ thống
                        một cách dễ dàng.
                    </p>
                </div>

                <div class="d-flex align-items-center">
                    <a href="/admin/categories/create.php"
                       class="btn-add-category">
                        <i class="bi bi-plus-circle-fill"></i>
                        Thêm danh mục mới
                    </a>
                </div>
            </div>
        </div>
    </section>

    <?php if ($errorMessage !== ''): ?>
        <div class="alert alert-danger alert-category" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <!-- Thống kê -->
    <section class="stat-card">
        <div class="stat-icon">
            <i class="bi bi-collection-fill"></i>
        </div>

        <div>
            <div class="stat-label">Tổng số danh mục</div>
            <div class="stat-value">
                <?= number_format($totalCategories) ?>
            </div>
        </div>

        <div class="stat-note ms-auto">
            <i class="bi bi-check-circle-fill me-1"></i>
            Dữ liệu hệ thống
        </div>
    </section>

    <!-- Bảng dữ liệu -->
    <section class="category-card">

        <div class="table-heading">
            <div>
                <h5>Danh sách danh mục</h5>
                <p>Theo dõi và quản lý thông tin danh mục</p>
            </div>

            <span class="count-pill">
                <?= number_format($totalCategories) ?> danh mục
            </span>
        </div>

        <div class="table-responsive">
            <table class="table category-table align-middle">

                <thead>
                    <tr>
                        <th style="width: 90px;">Mã</th>
                        <th style="min-width: 240px;">Tên danh mục</th>
                        <th style="min-width: 240px;">Mô tả</th>
                        <th class="text-end" style="width: 125px;">
                            Thao tác
                        </th>
                    </tr>
                </thead>

                <tbody>

                <?php if ($result && $totalCategories > 0): ?>

                    <?php while ($category = $result->fetch_assoc()): ?>

                        <?php
                        $categoryId = (int) $category['CategoryID'];
                        $categoryName = htmlspecialchars(
                            $category['CategoryName'] ?? '',
                            ENT_QUOTES,
                            'UTF-8'
                        );
                        $description = trim($category['Description'] ?? '');
                        ?>

                        <tr>
                            <td>
                                <span class="category-id">
                                    #<?= $categoryId ?>
                                </span>
                            </td>

                            <td>
                                <div class="category-info">
                                    <div class="category-avatar">
                                        <i class="bi bi-folder2-open"></i>
                                    </div>

                                    <div>
                                        <div class="category-name">
                                            <?= $categoryName ?>
                                        </div>
                                        <div class="category-subtitle">
                                            Danh mục nội dung
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <td>
                                <?php if ($description !== ''): ?>
                                    <div class="category-description">
                                        <?= htmlspecialchars(
                                            $description,
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </div>
                                <?php else: ?>
                                    <span class="description-empty">
                                        Chưa có mô tả
                                    </span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <div class="action-group">
                                    <a
                                        href="/admin/categories/edit.php?id=<?= $categoryId ?>"
                                        class="btn-action btn-edit"
                                        title="Sửa danh mục"
                                        aria-label="Sửa danh mục <?= $categoryId ?>"
                                    >
                                        <i class="bi bi-pencil-square"></i>
                                    </a>

                                    <form
                                        action="/admin/categories/delete.php"
                                        method="post"
                                        class="d-inline"
                                        onsubmit="return confirm('Bạn có chắc chắn muốn xóa danh mục này không?');"
                                    >
                                        <input
                                            type="hidden"
                                            name="id"
                                            value="<?= $categoryId ?>"
                                        >

                                        <button
                                            type="submit"
                                            class="btn-action btn-delete"
                                            title="Xóa danh mục"
                                            aria-label="Xóa danh mục <?= $categoryId ?>"
                                        >
                                            <i class="bi bi-trash3"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>

                    <?php endwhile; ?>

                <?php elseif ($errorMessage === ''): ?>

                    <tr>
                        <td colspan="4" class="empty-state">
                            <div class="empty-icon">
                                <i class="bi bi-folder2-open"></i>
                            </div>

                            <h6>Chưa có danh mục nào</h6>

                            <p>
                                Hãy tạo danh mục đầu tiên để bắt đầu
                                quản lý dữ liệu.
                            </p>

                            <a
                                href="/admin/categories/create.php"
                                class="btn btn-primary rounded-3 px-4 py-2"
                                style="background: var(--primary); border-color: var(--primary);"
                            >
                                <i class="bi bi-plus-lg me-1"></i>
                                Tạo danh mục
                            </a>
                        </td>
                    </tr>

                <?php endif; ?>

                </tbody>
            </table>
        </div>
    </section>

</div>

<?php
require_once '/var/www/src/includes/admin/footer.php';

if ($result instanceof mysqli_result) {
    $result->free();
}

$connection->close();
?>
