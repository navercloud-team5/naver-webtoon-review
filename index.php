<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/header.php';

if ($DEMO_MODE) {
    $popularArr = $demoWebtoons;
    usort($popularArr, fn($a, $b) => $b['avg_rating'] <=> $a['avg_rating']);
    $popularList = array_slice($popularArr, 0, 6);
    $recentReviewsList = array_slice(array_reverse($demoReviews), 0, 5);
} else {
    $popular = $conn->query("
        SELECT
            w.*,
            COALESCE(rs.avg_rating, 0) AS avg_rating,
            COALESCE(rs.review_count, 0) AS review_count,
            COALESCE(ls.like_count, 0) AS like_count
        FROM webtoons w
        LEFT JOIN (
            SELECT webtoon_id, AVG(rating) AS avg_rating, COUNT(*) AS review_count
            FROM reviews
            GROUP BY webtoon_id
        ) rs ON rs.webtoon_id = w.id
        LEFT JOIN (
            SELECT r.webtoon_id, COUNT(rl.id) AS like_count
            FROM reviews r
            LEFT JOIN review_likes rl ON rl.review_id = r.id
            GROUP BY r.webtoon_id
        ) ls ON ls.webtoon_id = w.id
        ORDER BY avg_rating DESC, review_count DESC, like_count DESC, w.title ASC
        LIMIT 6
    ");

    $recentReviews = $conn->query("
        SELECT r.*, w.title AS webtoon_title, u.nickname
        FROM reviews r
        JOIN webtoons w ON r.webtoon_id = w.id
        JOIN users u ON r.user_id = u.id
        ORDER BY r.created_at DESC
        LIMIT 5
    ");

    $popularList = $popular ? $popular->fetch_all(MYSQLI_ASSOC) : [];
    $recentReviewsList = $recentReviews ? $recentReviews->fetch_all(MYSQLI_ASSOC) : [];
}
?>

<?php if ($DEMO_MODE): ?>
<div class="demo-banner">DB 미연결 상태 - 디자인 확인용 더미 데이터가 표시되고 있습니다.</div>
<?php endif; ?>

<section class="hero">
    <h1>웹툰, 보기 전에 리뷰부터</h1>
    <p>네이버 웹툰 독자들의 솔직한 평점과 리뷰를 확인하세요.</p>
</section>

<section class="section">
    <h2>인기 웹툰</h2>
    <div class="card-grid">
        <?php if (count($popularList) > 0): ?>
            <?php foreach ($popularList as $w): ?>
                <a href="/detail.php?id=<?= (int)$w['id'] ?>" class="webtoon-card">
                    <div class="card-thumb">
                        <img src="<?= h($w['thumbnail_url']) ?>" alt="<?= h($w['title']) ?>" onerror="this.src='/images/placeholder.svg'">
                    </div>
                    <div class="card-body">
                        <p class="genre-badge"><?= h(day_code_to_label($w['update_days'])) ?>요일</p>
                        <h3><?= h($w['title']) ?></h3>
                        <p class="author-name"><?= h($w['authors']) ?></p>
                        <div class="card-rating">
                            <?= render_stars((float)$w['avg_rating']) ?>
                            <strong><?= number_format((float)$w['avg_rating'], 1) ?></strong>
                        </div>
                        <div class="card-meta">
                            <span>리뷰 <?= (int)($w['review_count'] ?? 0) ?></span>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="empty">등록된 웹툰이 없습니다.</p>
        <?php endif; ?>
    </div>
</section>

<section class="section">
    <h2>최신 리뷰</h2>
    <div class="recent-review-list">
        <?php if (count($recentReviewsList) > 0): ?>
            <?php foreach ($recentReviewsList as $r): ?>
                <div class="review-item">
                    <strong><?= h($r['webtoon_title']) ?></strong>
                    <span class="rating"><?= render_stars((float)$r['rating']) ?> <?= number_format((float)$r['rating'], 1) ?></span>
                    <p><?= h(mb_substr($r['content'], 0, 60)) ?>...</p>
                    <small><?= h($r['nickname']) ?> · <?= h($r['created_at']) ?></small>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="empty">아직 리뷰가 없습니다.</p>
        <?php endif; ?>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
