<?php

$pageTitle = 'Sửa thông tin shipper';

require_once '/var/www/src/config/database.php';

$error = '';

$shipperID = isset($_GET['id'])
    ? (int) $_GET['id']
    : 0;

if ($shipperID <= 0) {
    die('Mã shipper không hợp lệ.');
}

/*
 * Đọc dữ liệu hiện tại của danh mục
 */
$sql = "
    SELECT
        ShipperID,
        ShipperName,
        Phone
    FROM shippers
    WHERE ShipperID = ?
";

$stmt = $connection->prepare($sql);
$stmt->bind_param('i', $shipperID);
$stmt->execute();

$result = $stmt->get_result();
$shipper = $result->fetch_assoc();

$stmt->close();

if (!$shipper) {
    die('Không tìm thấy thông tin shipper.');
}

/*
 * Xử lý khi người dùng gửi form
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $shipperName = trim($_POST['shipper_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    if ($shipperName === '') {
        $error = 'Tên shipper không được để trống.';
    } else {

        $sql = "
            UPDATE shippers
            SET
                ShipperName = ?,
                Phone = ?
            WHERE ShipperID = ?
        ";

        $stmt = $connection->prepare($sql);

        $stmt->bind_param(
            'ssi',
            $shipperName,
            $phone,
            $shipperID
        );

        if ($stmt->execute()) {
            header('Location: /admin/shippers/');
            exit;
        } else {
            $error = 'Không thể cập nhật shipper.';
        }

        $stmt->close();
    }
}

require_once '/var/www/src/includes/admin/header.php';
require_once '/var/www/src/includes/admin/navbar.php';

?>

<div class="container mt-4">

    <h2 class="mb-4">Sửa shipper</h2>

    <?php if ($error !== ''): ?>

        <div class="alert alert-danger">
            <?= htmlspecialchars($error) ?>
        </div>

    <?php endif; ?>

    <form method="post">

        <div class="mb-3">

            <label class="form-label">
                Mã shipper
            </label>

            <input
                type="text"
                class="form-control"
                value="<?= $shipper['ShipperID'] ?>"
                disabled
            >

        </div>

        <div class="mb-3">

            <label for="shipperName" class="form-label">
                Tên shipper
            </label>

            <input
                type="text"
                class="form-control"
                id="shipperName"
                name="shipper_name"
                value="<?= htmlspecialchars(
                    $_POST['shipper_name']
                    ?? $shipper['ShipperName']
                ) ?>"
                required
            >

        </div>

        <div class="mb-3">

            <label for="phone" class="form-label">
                Số điện thoại
            </label>

            <input
                class="form-control"
                id="phone"
                name="phone"
                value="<?= htmlspecialchars($_POST['phone'] ?? $shipper['Phone']) ?>"
                required
            />
        </div>

        <button type="submit" class="btn btn-warning">
            Cập nhật
        </button>

        <a href="/admin/shippers/" class="btn btn-secondary">
            Hủy
        </a>

    </form>

</div>

<?php

require_once '/var/www/src/includes/admin/footer.php';

$connection->close();
