<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/header.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: /index.php?login=1");
    exit;
}

$userId = (int)$_SESSION['user_id'];

$userStmt = $conn->prepare("
    SELECT id, nickname, email, profile_image, created_at
    FROM users
    WHERE id = ?
");
$userStmt->bind_param("i", $userId);
$userStmt->execute();
$user = $userStmt->get_result()->fetch_assoc();

$stmt = $conn->prepare("
    SELECT
        r.*,
        w.title AS webtoon_title,
        w.id AS webtoon_id,
        COUNT(rl.id) AS like_count
    FROM reviews r
    JOIN webtoons w ON r.webtoon_id = w.id
    LEFT JOIN review_likes rl ON r.id = rl.review_id
    WHERE r.user_id = ?
    GROUP BY r.id
    ORDER BY r.created_at DESC
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$myReviews = $stmt->get_result();
$reviewCount = $myReviews->num_rows;

// 현재 요청을 처리한 서버 정보 (로드밸런서 분산 확인용)
// 새로고침할 때마다 Host/IP 가 바뀌면 트래픽이 여러 서버로 분산되고 있는 것.
$serverHostname = php_uname('n');
// 이 요청을 실제로 처리한 웹 서버(백엔드)의 사설 IP
$serverInternalIp = $_SERVER['SERVER_ADDR'] ?? gethostbyname($serverHostname);
// 로드밸런서를 통해 들어온 클라이언트 IP (X-Forwarded-For 우선)
if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    $forwarded = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
    $clientIp = trim($forwarded[0]);
} else {
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? '-';
}
?>

<section class="mypage-hero">
    <div class="mypage-avatar">
        <?php if (!empty($user['profile_image'])): ?>
            <img src="<?= h($user['profile_image']) ?>" alt="프로필 이미지">
        <?php else: ?>
            <span>N</span>
        <?php endif; ?>
    </div>
    <p class="eyebrow">MY PAGE</p>
    <h1>마이페이지</h1>
    <p class="mypage-greeting">안녕하세요, <strong><?= h($user['nickname'] ?? $_SESSION['nickname']) ?></strong>님</p>
    <div class="mypage-meta">
        <?php if (!empty($user['email'])): ?>
            <span><?= h($user['email']) ?></span>
        <?php endif; ?>
        <span>작성 리뷰 <?= (int)$reviewCount ?>개</span>
    </div>
    <div class="server-info">
        <p class="server-info__title">🔀 이 요청을 처리한 서버</p>
        <p class="server-info__line">Server: <strong><?= h($serverHostname) ?></strong></p>
        <p class="server-info__line">Internal IP: <strong><?= h($serverInternalIp) ?></strong></p>
        <p class="server-info__line">Client IP: <strong><?= h($clientIp) ?></strong></p>
        <p class="server-info__hint">새로고침 시 Host/IP 가 바뀌면 로드밸런서가 트래픽을 분산하는 중입니다.</p>
    </div>
</section>

<section class="section">
    <h2>내가 작성한 리뷰 (<?= (int)$reviewCount ?>)</h2>
    <div class="mypage-review-list">
        <?php if ($reviewCount > 0): ?>
            <?php while ($r = $myReviews->fetch_assoc()): ?>
                <div class="review-item">
                    <div class="mypage-review-head">
                        <div>
                            <a href="/detail.php?id=<?= (int)$r['webtoon_id'] ?>"><strong><?= h($r['webtoon_title']) ?></strong></a>
                            <span class="rating"><?= render_stars((float)$r['rating']) ?> <?= number_format((float)$r['rating'], 1) ?></span>
                        </div>
                        <div class="review-actions">
                            <a href="/detail.php?id=<?= (int)$r['webtoon_id'] ?>#review-form" class="review-text-button">수정</a>
                            <form action="/detail.php?id=<?= (int)$r['webtoon_id'] ?>" method="post" class="delete-review-form" data-delete-review>
                                <input type="hidden" name="action" value="delete_review">
                                <input type="hidden" name="review_id" value="<?= (int)$r['id'] ?>">
                                <input type="hidden" name="redirect" value="mypage">
                                <button type="submit" class="review-text-button is-danger">삭제</button>
                            </form>
                        </div>
                    </div>
                    <p><?= nl2br(h($r['content'])) ?></p>
                    <small><?= h($r['created_at']) ?> · <span class="heart-count"><?= heart_svg('heart-icon--filled') ?> <?= (int)$r['like_count'] ?></span></small>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p class="empty">아직 작성한 리뷰가 없습니다.</p>
        <?php endif; ?>
    </div>
</section>

<script>
document.querySelectorAll('[data-delete-review]').forEach(function (form) {
    form.addEventListener('submit', function (event) {
        if (!confirm('리뷰를 삭제할까요?')) {
            event.preventDefault();
        }
    });
});
</script>

<?php
$stmt->close();
$userStmt->close();
require_once __DIR__ . '/includes/footer.php';
?>
