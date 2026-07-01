<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/naver_auth.php';

try {
    if ($DEMO_MODE) {
        throw new RuntimeException('DB 연결이 필요합니다. config/db.php를 확인해주세요.');
    }

    if (isset($_GET['error'])) {
        throw new RuntimeException('네이버 로그인 실패: ' . ($_GET['error_description'] ?? $_GET['error']));
    }

    $code = $_GET['code'] ?? null;
    $state = $_GET['state'] ?? null;

    if (!is_string($code) || $code === '') {
        throw new RuntimeException('네이버 callback code가 없습니다.');
    }

    if (!naver_validate_state(is_string($state) ? $state : null)) {
        throw new RuntimeException('네이버 로그인 state 검증에 실패했습니다.');
    }

    $token = naver_issue_token($code, (string)$state);
    $profile = naver_fetch_profile($token['access_token']);
    $user = naver_find_or_create_user($conn, $profile);

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['nickname'] = $user['nickname'];

    header("Location: /mypage.php");
    exit;
} catch (Throwable $e) {
    http_response_code(400);
    $message = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>네이버 로그인 오류</title>
<link rel="stylesheet" href="/css/style.css">
</head>
<body>
<main class="container">
    <div class="auth-box">
        <h1>네이버 로그인 오류</h1>
        <p class="error-msg"><?= h($message ?? '알 수 없는 오류가 발생했습니다.') ?></p>
        <p><a href="/index.php?login=1">로그인으로 돌아가기</a></p>
    </div>
</main>
</body>
</html>
