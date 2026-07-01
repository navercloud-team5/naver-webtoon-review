<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/db_session.php';
require_once __DIR__ . '/includes/naver_auth.php';

try {
    $state = naver_create_state();
    $_SESSION['naver_oauth_state'] = $state; // DB 연결 실패 시(DEMO_MODE) 폴백용

    if (!$DEMO_MODE && $conn) {
        naver_store_state($conn, $state);
    }

    header("Location: " . naver_authorization_url(naver_config(), $state));
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    echo "네이버 로그인 설정 오류: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
}
