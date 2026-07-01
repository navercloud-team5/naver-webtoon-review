<?php
// ===== DB 연결 설정 (예시 템플릿) =====
// 이 파일을 config/db.php 로 복사한 뒤, 아래 값을 실제 서버 정보로 채우세요.
// config/db.php 는 .gitignore 에 등록되어 있어 GitHub 에 올라가지 않습니다.

$DB_HOST = "YOUR_DB_PRIVATE_DOMAIN";   // DB 내부 도메인/IP
$DB_USER = "YOUR_DB_USER";             // DB 계정명
$DB_PASS = "YOUR_DB_PASSWORD";         // DB 비밀번호. 절대 GitHub에 올리지 말 것!
$DB_NAME = "YOUR_DB_NAME";             // DB 이름

// DEMO_MODE: DB 연결이 안 되거나(또는 PHP에 mysqli 확장이 꺼져 있으면)
// 자동으로 더미 데이터로 화면만 보여줍니다.
// (디자인/UI 테스트용. 실제 회원가입/리뷰작성 등은 DB가 있어야 동작합니다.)
$DEMO_MODE = false;
$conn = null;

if (!extension_loaded('mysqli') || !function_exists('mysqli_report')) {
    // mysqli 확장 자체가 PHP에 설치/활성화되어 있지 않은 경우
    $DEMO_MODE = true;
    require_once __DIR__ . '/demo_data.php';
} else {
    mysqli_report(MYSQLI_REPORT_OFF); // 연결 실패 시 경고창 대신 우리가 직접 처리
    $conn = @new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

    if (!$conn || $conn->connect_error) {
        $DEMO_MODE = true;
        require_once __DIR__ . '/demo_data.php';
    } else {
        $conn->set_charset("utf8mb4");
    }
}
?>
