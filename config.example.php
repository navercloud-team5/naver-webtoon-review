<?php
$db_host = "DB_내부_IP_입력";
$db_port = 3306;
$db_user = "DB_계정명_입력";
$db_pass = "DB_비밀번호_입력";
$db_name = "DB_이름_입력";

$conn = mysqli_connect($db_host, $db_user, $db_pass, $db_name, $db_port);

if (!$conn) {
    die("DB 연결 실패: " . mysqli_connect_error());
}

mysqli_set_charset($conn, "utf8mb4");
?>
