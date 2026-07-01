<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/includes/naver_auth.php';

try {
    $state = naver_create_state();
    $_SESSION['naver_oauth_state'] = $state;

    header("Location: " . naver_authorization_url(naver_config(), $state));
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    echo "네이버 로그인 설정 오류: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
}
