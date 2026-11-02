<?php

require_once '/var/www/src/config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /products/');
    exit;
}

$productID = isset($_POST['id'])
    ? (int) $_POST['id']
    : 0;

if ($productID <= 0) {
    header('Location: /products/');
    exit;
}

$sql = "
    DELETE FROM products
    WHERE ProductID = ?
";

$stmt = $connection->prepare($sql);
$stmt->bind_param('i', $productID);

if ($stmt->execute()) {

    $stmt->close();
    $connection->close();

    header('Location: /products/');
    exit;
}

$stmt->close();
$connection->close();

die('Không thể xóa sản phẩm.');
