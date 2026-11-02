<?php

$pageTitle = 'Quản lý danh mục';

require_once '/var/www/src/config/database.php';

$sql = "
    SELECT
        ShipperID,
        ShipperName,
        Phone
    FROM shippers
    ORDER BY ShipperID
";

$result = $connection->query($sql);

require_once '/var/www/src/includes/admin/header.php';
require_once '/var/www/src/includes/admin/navbar.php'; 
?>

<div class="container">

    <div class="d-flex justify-content-between align-items-center mb-3">

        <h2>Quản lý shipper</h2>

        <a href="http://localhost:8080/admin/shippers/create.php" class="btn btn-primary">
            Thêm shipper
        </a>

    </div>

    <div class="table-responsive">

        <table class="table table-bordered table-striped">

            <thead class="table-dark">
                <tr>
                    <th>ID</th>
                    <th>Tên shipper</th>
                    <th>Số điện thoại</th>
                    <th>Thao tác</th>
                </tr>
            </thead>

            <tbody>

            <?php while ($shipper = $result->fetch_assoc()): ?>

                <tr>

                    <td>
                        <?= $shipper['ShipperID'] ?>
                    </td>

                    <td>
                        <?= htmlspecialchars($shipper['ShipperName']) ?>
                    </td>

                    <td>
                        <?= htmlspecialchars($shipper['Phone'] ?? '') ?>
                    </td>

                    <td>

                        <a href="/admin/shippers/edit.php?id=<?= $shipper['ShipperID'] ?>" class="btn btn-sm btn-warning">
                            Sửa
                        </a>

                        <form
                            action="/admin/shippers/delete.php"
                            method="post"
                            class="d-inline"
                            onsubmit="return confirm('Bạn có chắc muốn xóa shipper này?');"
                        >
                            <input
                                type="hidden"
                                name="id"
                                value="<?= $shipper['ShipperID'] ?>"
                            >

                            <button
                                type="submit"
                                class="btn btn-sm btn-danger"
                            >
                                Xóa
                            </button>
                        </form>

                    </td>

                </tr>

            <?php endwhile; ?>

            </tbody>

        </table>

    </div>

</div>

<?php

require_once '/var/www/src/includes/admin/footer.php';

$connection->close();


