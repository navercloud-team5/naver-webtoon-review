<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/helpers.php';
$isLoggedIn = isset($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>웹툰 리뷰</title>
<link rel="stylesheet" href="/css/style.css">
</head>
<body>
<header class="site-header">
    <div class="header-top">
        <a href="/index.php" class="logo">
            <img src="/images/logo.png" alt="웹툰리뷰" class="logo-img">
        </a>
        <div class="header-right">
            <form action="/list.php" method="get" class="header-search">
                <input type="text" name="q" placeholder="웹툰 제목/작가 검색">
                <button type="submit" aria-label="검색">검색</button>
            </form>
            <?php if ($isLoggedIn): ?>
                <a href="/mypage.php">마이페이지</a>
                <a href="/logout.php">로그아웃</a>
                <span class="welcome"><?= h($_SESSION['nickname']) ?>님</span>
            <?php else: ?>
                <button type="button" class="header-login-button" data-login-open>로그인</button>
            <?php endif; ?>
        </div>
    </div>
    <nav class="day-nav">
        <?php
        $days = ['전체' => '', '월' => 'MON', '화' => 'TUE', '수' => 'WED', '목' => 'THU', '금' => 'FRI', '토' => 'SAT', '일' => 'SUN'];
        $currentDay = isset($_GET['day']) ? $_GET['day'] : '';
        foreach ($days as $label => $val):
            $active = ($currentDay === $val) ? 'active' : '';
        ?>
            <a href="/list.php?day=<?= urlencode($val) ?>" class="day-tab <?= $active ?>"><?= h($label) ?></a>
        <?php endforeach; ?>
    </nav>
</header>
<?php if (!$isLoggedIn): ?>
<div class="login-modal" id="loginModal" aria-hidden="true">
    <div class="login-modal__backdrop" data-login-close></div>
    <section class="login-modal__panel" role="dialog" aria-modal="true" aria-labelledby="loginModalTitle">
        <button type="button" class="login-modal__close" data-login-close aria-label="닫기">&times;</button>
        <h2 id="loginModalTitle">로그인하고 리뷰를 남겨보세요.</h2>
        <a href="/naver_login.php" class="naver-login-button">
            <img src="/images/naver_login.png" alt="네이버로 로그인">
        </a>
        <p class="login-modal__hint">로그인하면 리뷰 작성과 좋아요를 사용할 수 있습니다.</p>
    </section>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('loginModal');
    if (!modal) return;

    const openModal = function () {
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('modal-open');
    };

    const closeModal = function () {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('modal-open');
    };

    document.querySelectorAll('[data-login-open]').forEach(function (button) {
        button.addEventListener('click', openModal);
    });

    document.querySelectorAll('[data-login-close]').forEach(function (button) {
        button.addEventListener('click', closeModal);
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') closeModal();
    });

    if (new URLSearchParams(window.location.search).get('login') === '1') {
        openModal();
    }
});
</script>
<?php endif; ?>
<main class="container">
