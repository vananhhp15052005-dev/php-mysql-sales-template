<?php
$sql = "
    UPDATE products
    SET
        ProductCode = ?,
        ProductName = ?,
        Description = ?,
        Unit = ?,
        Price = ?,
        StockQuantity = ?,
        IsActive = ?,
        SupplierID = ?,
        CategoryID = ?
    WHERE ProductID = ?
";

$stmt = $conn->prepare($sql);

$stmt->bind_param(
    'ssssdiiiii',
    $productCode,
    $productName,
    $description,
    $unit,
    $price,
    $stockQuantity,
    $isActive,
    $supplierID,
    $categoryID,
    $productID
);