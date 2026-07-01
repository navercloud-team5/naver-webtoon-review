<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/header.php';

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$day = isset($_GET['day']) ? trim($_GET['day']) : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'popular';

if ($day !== '' && !is_valid_day_code($day)) {
    $day = '';
}

if ($DEMO_MODE) {
    $resultList = demoFilterWebtoons($demoWebtoons, $q, $day, $sort);
} else {
    $sql = "
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
        WHERE 1=1
    ";
    $params = [];
    $types = "";

    if ($q !== '') {
        $sql .= " AND (w.title LIKE ? OR w.authors LIKE ?)";
        $params[] = "%$q%";
        $params[] = "%$q%";
        $types .= "ss";
    }

    if ($day !== '') {
        $sql .= " AND w.update_days = ?";
        $params[] = $day;
        $types .= "s";
    }

    if ($sort === 'newest') {
        $sql .= " ORDER BY w.created_at DESC";
    } elseif ($sort === 'reviews') {
        $sql .= " ORDER BY review_count DESC, avg_rating DESC, w.title ASC";
    } elseif ($sort === 'likes') {
        $sql .= " ORDER BY like_count DESC, review_count DESC, w.title ASC";
    } elseif ($sort === 'title') {
        $sql .= " ORDER BY w.title ASC";
    } else {
        $sql .= " ORDER BY avg_rating DESC, review_count DESC, like_count DESC, w.title ASC";
    }

    $stmt = $conn->prepare($sql);
    if ($params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $resultList = $result->fetch_all(MYSQLI_ASSOC);
}
?>

<?php if ($DEMO_MODE): ?>
<div class="demo-banner">DB 미연결 상태 - 디자인 확인용 더미 데이터가 표시되고 있습니다.</div>
<?php endif; ?>

<div class="list-heading list-heading--split">
    <div>
        <p class="eyebrow">요일별 웹툰</p>
        <h1><?= $day !== '' ? h(day_code_to_label($day)) . '요일 웹툰' : '전체 웹툰' ?></h1>
        <span class="result-count"><?= count($resultList) ?>개 작품</span>
        <?php if ($q !== ''): ?>
            <p class="search-summary">"<?= h($q) ?>" 검색 결과</p>
        <?php endif; ?>
    </div>
    <div class="sort-tabs" aria-label="정렬">
        <?php
        $sorts = ['popular' => '인기순', 'reviews' => '리뷰순', 'likes' => '좋아요순', 'newest' => '업데이트순', 'title' => '제목순'];
        foreach ($sorts as $value => $label):
            $query = http_build_query(['day' => $day, 'q' => $q, 'sort' => $value]);
        ?>
            <a class="<?= $sort === $value ? 'active' : '' ?>" href="/list.php?<?= h($query) ?>"><?= h($label) ?></a>
        <?php endforeach; ?>
    </div>
</div>

<div class="card-grid">
    <?php if (count($resultList) > 0): ?>
        <?php foreach ($resultList as $w): ?>
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
        <p class="empty">검색 결과가 없습니다.</p>
    <?php endif; ?>
</div>

<?php
if (!$DEMO_MODE) { $stmt->close(); }
require_once __DIR__ . '/includes/footer.php';
?>
