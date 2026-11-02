
<?php

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$host = 'db';
$database = getenv('MYSQL_DATABASE');
$username = getenv('MYSQL_USER');
$password = getenv('MYSQL_PASSWORD');

try {
    $connection = new mysqli(
        $host,
        $username,
        $password,
        $database
    );

    $connection->set_charset('utf8mb4');
} catch (mysqli_sql_exception $e) {
    error_log('Lỗi kết nối MySQL: ' . $e->getMessage());
    die('Không thể kết nối cơ sở dữ liệu. Vui lòng kiểm tra cấu hình MySQL.');
}