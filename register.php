<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/header.php';
?>

<div class="auth-box">
    <h1>회원가입</h1>
    <p>이 서비스는 네이버 간편로그인으로 가입과 로그인을 함께 처리합니다.</p>
    <p><a href="/naver_login.php" class="signup-btn">네이버로 시작하기</a></p>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
