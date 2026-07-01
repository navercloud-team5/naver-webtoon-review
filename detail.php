<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/header.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$currentUserId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$errorMsg = '';

if ($DEMO_MODE) {
    $webtoon = null;
    foreach ($demoWebtoons as $w) {
        if ($w['id'] === $id) { $webtoon = $w; break; }
    }
    if (!$webtoon) {
        echo "<p class='error-msg'>존재하지 않는 웹툰입니다. (데모 모드: id 1~10만 존재)</p>";
        require_once __DIR__ . '/includes/footer.php';
        exit;
    }
    $reviewsList = array_values(array_filter($demoReviews, fn($r) => $r['webtoon_id'] === $id));
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $errorMsg = "데모 모드에서는 리뷰 작성이 저장되지 않습니다. DB 연결이 필요합니다.";
    }
} else {
    $stmt = $conn->prepare("
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
        WHERE w.id = ?
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $webtoon = $stmt->get_result()->fetch_assoc();

    if (!$webtoon) {
        echo "<p class='error-msg'>존재하지 않는 웹툰입니다.</p>";
        require_once __DIR__ . '/includes/footer.php';
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';

        if ($currentUserId === 0) {
            $errorMsg = "로그인 후 이용할 수 있습니다.";
        } elseif ($action === 'add_review') {
            $ratingRaw = $_POST['rating'] ?? '';
            $rating = is_numeric($ratingRaw) ? (float)$ratingRaw : -1;
            $content = trim($_POST['content'] ?? '');
            $isHalfStep = abs(($rating * 2) - round($rating * 2)) < 0.001;

            if ($content === '' || $rating < 0 || $rating > 5 || !$isHalfStep) {
                $errorMsg = "0점부터 5점까지 0.5점 단위로 별점과 리뷰 내용을 입력해주세요.";
            } else {
                $ins = $conn->prepare("
                    INSERT INTO reviews (webtoon_id, user_id, rating, content)
                    VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                        rating = VALUES(rating),
                        content = VALUES(content),
                        updated_at = CURRENT_TIMESTAMP
                ");
                $ins->bind_param("iids", $id, $currentUserId, $rating, $content);
                $ins->execute();
                $ins->close();

                header("Location: /detail.php?id=$id");
                exit;
            }
        } elseif ($action === 'delete_review') {
            $reviewId = (int)($_POST['review_id'] ?? 0);
            $redirectTo = ($_POST['redirect'] ?? '') === 'mypage' ? '/mypage.php' : "/detail.php?id=$id";

            $del = $conn->prepare("DELETE FROM reviews WHERE id = ? AND user_id = ? AND webtoon_id = ?");
            $del->bind_param("iii", $reviewId, $currentUserId, $id);
            $del->execute();
            $del->close();

            header("Location: $redirectTo");
            exit;
        } elseif ($action === 'toggle_like') {
            $reviewId = (int)($_POST['review_id'] ?? 0);

            $check = $conn->prepare("SELECT id FROM review_likes WHERE user_id = ? AND review_id = ?");
            $check->bind_param("ii", $currentUserId, $reviewId);
            $check->execute();
            $alreadyLiked = $check->get_result()->fetch_assoc();
            $check->close();

            if ($alreadyLiked) {
                $del = $conn->prepare("DELETE FROM review_likes WHERE user_id = ? AND review_id = ?");
                $del->bind_param("ii", $currentUserId, $reviewId);
                $del->execute();
                $del->close();
            } else {
                $like = $conn->prepare("INSERT IGNORE INTO review_likes (user_id, review_id) VALUES (?, ?)");
                $like->bind_param("ii", $currentUserId, $reviewId);
                $like->execute();
                $like->close();
            }

            header("Location: /detail.php?id=$id");
            exit;
        }
    }

    $reviewStmt = $conn->prepare("
        SELECT
            r.*,
            u.nickname,
            COUNT(rl.id) AS like_count,
            MAX(CASE WHEN rl.user_id = ? THEN 1 ELSE 0 END) AS is_liked
        FROM reviews r
        JOIN users u ON r.user_id = u.id
        LEFT JOIN review_likes rl ON r.id = rl.review_id
        WHERE r.webtoon_id = ?
        GROUP BY r.id
        ORDER BY r.created_at DESC
    ");
    $reviewStmt->bind_param("ii", $currentUserId, $id);
    $reviewStmt->execute();
    $reviewsList = $reviewStmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

$reviewCountTop = count($reviewsList);
$totalLikeCount = isset($webtoon['like_count'])
    ? (int)$webtoon['like_count']
    : array_sum(array_map(fn($r) => (int)($r['like_count'] ?? 0), $reviewsList));

$avgRating = (float)$webtoon['avg_rating'];

// 감정 반응 집계: 별점을 4단계 감정으로 매핑
$reactions = [
    'love'  => ['emoji' => '😍', 'label' => '최고예요', 'count' => 0],
    'good'  => ['emoji' => '😊', 'label' => '재밌어요', 'count' => 0],
    'meh'   => ['emoji' => '🤔', 'label' => '아쉬워요', 'count' => 0],
    'bad'   => ['emoji' => '😞', 'label' => '별로예요', 'count' => 0],
];
foreach ($reviewsList as $r) {
    $rv = (float)$r['rating'];
    if ($rv >= 4.5)      $reactions['love']['count']++;
    elseif ($rv >= 3.5)  $reactions['good']['count']++;
    elseif ($rv >= 2.5)  $reactions['meh']['count']++;
    else                 $reactions['bad']['count']++;
}
$reactionTotal = array_sum(array_column($reactions, 'count'));
$myReview = null;
if ($currentUserId > 0) {
    foreach ($reviewsList as $r) {
        if ((int)($r['user_id'] ?? 0) === $currentUserId) {
            $myReview = $r;
            break;
        }
    }
}
$formRating = $myReview ? (float)$myReview['rating'] : '';
$formContent = $myReview ? (string)$myReview['content'] : '';
?>

<?php if ($DEMO_MODE): ?>
<div class="demo-banner">DB 미연결 상태 - 디자인 확인용 더미 데이터가 표시되고 있습니다. 리뷰 작성은 저장되지 않습니다.</div>
<?php endif; ?>

<div class="detail-top">
    <div class="detail-thumb-wrap">
        <img src="<?= h($webtoon['thumbnail_url']) ?>" alt="<?= h($webtoon['title']) ?>" class="detail-thumb" onerror="this.src='/images/placeholder.svg'">
    </div>

    <div class="detail-meta-group">
        <p class="genre-badge"><?= h(day_code_to_label($webtoon['update_days'])) ?>요일 연재 · <?= h($webtoon['provider'] ?? 'NAVER') ?></p>
        <h1><?= h($webtoon['title']) ?></h1>
        <p class="author">작가 <?= h($webtoon['authors']) ?></p>

        <div class="rating-summary">
            <span class="rating-summary-stars"><?= render_stars($avgRating) ?></span>
            <span class="rating-big"><?= number_format($avgRating, 1) ?></span>
            <span class="rating-sep">·</span>
            <span>리뷰 <?= (int)($webtoon['review_count'] ?? $reviewCountTop) ?></span>
            <span class="rating-sep">·</span>
            <span class="heart-count"><?= heart_svg('heart-icon--filled') ?> <?= $totalLikeCount ?></span>
        </div>

        <?php
        // 실제 source_url이 있으면 그걸로, 없거나 '#'이면 제목으로 네이버 웹툰 검색
        $naverUrl = (!empty($webtoon['source_url']) && $webtoon['source_url'] !== '#')
            ? $webtoon['source_url']
            : 'https://comic.naver.com/search?keyword=' . urlencode($webtoon['title']);
        ?>
        <a href="<?= h($naverUrl) ?>" target="_blank" rel="noopener" class="naver-link-btn">
            <span class="naver-mark">N</span> 네이버 웹툰에서 보기
        </a>
    </div>

    <div class="reaction-panel">
        <p class="reaction-title">독자 반응</p>
        <?php if ($reactionTotal > 0): ?>
            <ul class="reaction-list">
                <?php foreach ($reactions as $rc):
                    $pct = (int)round($rc['count'] / $reactionTotal * 100);
                ?>
                    <li class="reaction-item">
                        <span class="reaction-emoji"><?= $rc['emoji'] ?></span>
                        <span class="reaction-label"><?= h($rc['label']) ?></span>
                        <span class="reaction-pct"><?= $pct ?>%</span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p class="reaction-empty">아직 반응이 없어요.</p>
        <?php endif; ?>
    </div>
</div>

<section class="review-write" id="review-form">
    <?php if ($errorMsg): ?>
        <p class="error-msg"><?= h($errorMsg) ?></p>
    <?php endif; ?>

    <?php if ($currentUserId > 0 || $DEMO_MODE): ?>
        <form action="/detail.php?id=<?= $id ?>" method="post" class="review-form" data-review-form>
            <input type="hidden" name="action" value="add_review">
            <div class="star-picker" data-star-picker>
                <input type="hidden" name="rating" value="<?= h($formRating !== '' ? number_format((float)$formRating, 1, '.', '') : '') ?>" required>
                <span class="review-write-badge">감상평</span>
                <span class="star-picker-value" data-star-value>별점을 선택해주세요.</span>
                <div class="star-picker-stars" role="radiogroup" aria-label="별점 선택 (0.5점 단위)">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <button type="button" class="star-pick" data-star="<?= $i ?>" aria-label="<?= $i ?>점">
                            <span class="star-pick-half" data-value="<?= $i - 0.5 ?>"></span>
                            <span class="star-pick-full" data-value="<?= $i ?>"></span>
                        </button>
                    <?php endfor; ?>
                </div>
            </div>
            <div class="review-input-row">
                <textarea name="content" rows="1" placeholder="감상평을 작성해주세요." required><?= h($formContent) ?></textarea>
                <button type="submit"><?= $myReview ? '수정' : '등록' ?></button>
            </div>
        </form>
    <?php else: ?>
        <p><button type="button" class="inline-login-button" data-login-open>로그인</button> 후 감상평을 작성할 수 있습니다.</p>
    <?php endif; ?>
</section>

<section class="detail-reviews">
        <h2>리뷰 <span class="review-count-badge"><?= $reviewCountTop ?></span></h2>
        <div class="review-list" data-review-list>
            <?php if ($reviewCountTop > 0): ?>
                <?php foreach ($reviewsList as $r): ?>
                    <div class="review-item">
                        <div class="review-head">
                            <div>
                                <strong><?= h($r['nickname']) ?></strong>
                                <div class="review-rating">
                                    <?= render_stars((float)$r['rating']) ?>
                                    <span><?= number_format((float)$r['rating'], 1) ?></span>
                                </div>
                            </div>
                            <div class="review-actions">
                                <?php if (!$DEMO_MODE && (int)($r['user_id'] ?? 0) === $currentUserId): ?>
                                    <button
                                        type="button"
                                        class="review-text-button"
                                        data-review-edit
                                        data-rating="<?= h(number_format((float)$r['rating'], 1, '.', '')) ?>"
                                        data-content="<?= h($r['content']) ?>"
                                    >수정</button>
                                    <form action="/detail.php?id=<?= $id ?>" method="post" class="delete-review-form" data-delete-review>
                                        <input type="hidden" name="action" value="delete_review">
                                        <input type="hidden" name="review_id" value="<?= (int)$r['id'] ?>">
                                        <button type="submit" class="review-text-button is-danger">삭제</button>
                                    </form>
                                <?php endif; ?>
                                <?php if (!$DEMO_MODE && $currentUserId > 0): ?>
                                    <form action="/detail.php?id=<?= $id ?>" method="post" class="like-form">
                                        <input type="hidden" name="action" value="toggle_like">
                                        <input type="hidden" name="review_id" value="<?= (int)$r['id'] ?>">
                                        <button type="submit" class="heart-like <?= !empty($r['is_liked']) ? 'is-active' : '' ?>" aria-label="리뷰 좋아요" aria-pressed="<?= !empty($r['is_liked']) ? 'true' : 'false' ?>">
                                            <?= heart_svg() ?>
                                            <span class="heart-count"><?= (int)$r['like_count'] ?></span>
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <button type="button" class="heart-like" <?= $DEMO_MODE ? '' : 'data-login-open' ?> aria-label="좋아요">
                                        <?= heart_svg() ?>
                                        <span class="heart-count"><?= (int)($r['like_count'] ?? 0) ?></span>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                        <p><?= nl2br(h($r['content'])) ?></p>
                        <small><?= h($r['created_at']) ?></small>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="empty">아직 리뷰가 없습니다. 첫 리뷰를 남겨보세요!</p>
            <?php endif; ?>
        </div>
        <button type="button" class="review-expand-btn" data-review-expand hidden>전체 리뷰 보기</button>
</section>

<script>
(function () {
    // ===== 별점 선택 (0.5 단위) =====
    document.querySelectorAll('[data-star-picker]').forEach(function (picker) {
        var input = picker.querySelector('input[name="rating"]');
        var label = picker.querySelector('[data-star-value]');
        var stars = picker.querySelectorAll('.star-pick');

        function paint(value) {
            stars.forEach(function (star) {
                var base = parseInt(star.dataset.star, 10);
                star.classList.remove('is-full', 'is-half');
                if (value >= base) star.classList.add('is-full');
                else if (value >= base - 0.5) star.classList.add('is-half');
            });
        }

        function setValue(value) {
            input.value = value;
            label.textContent = value.toFixed(1) + '점';
            paint(value);
        }

        picker.reviewSetRating = setValue;

        stars.forEach(function (star) {
            star.addEventListener('mousemove', function (e) {
                var rect = star.getBoundingClientRect();
                var base = parseInt(star.dataset.star, 10);
                var isLeft = (e.clientX - rect.left) < rect.width / 2;
                paint(isLeft ? base - 0.5 : base);
            });
            star.addEventListener('click', function (e) {
                var rect = star.getBoundingClientRect();
                var base = parseInt(star.dataset.star, 10);
                var isLeft = (e.clientX - rect.left) < rect.width / 2;
                setValue(isLeft ? base - 0.5 : base);
            });
        });

        picker.querySelector('.star-picker-stars').addEventListener('mouseleave', function () {
            paint(input.value ? parseFloat(input.value) : 0);
        });

        if (input.value) {
            setValue(parseFloat(input.value));
        }
    });

    // ===== 내 리뷰 수정 버튼 =====
    document.querySelectorAll('[data-review-edit]').forEach(function (button) {
        button.addEventListener('click', function () {
            var form = document.querySelector('[data-review-form]');
            if (!form) return;

            var picker = form.querySelector('[data-star-picker]');
            var textarea = form.querySelector('textarea[name="content"]');
            var rating = parseFloat(button.dataset.rating || '0');

            if (picker && typeof picker.reviewSetRating === 'function') {
                picker.reviewSetRating(rating);
            }
            if (textarea) {
                textarea.value = button.dataset.content || '';
            }

            form.scrollIntoView({ behavior: 'smooth', block: 'center' });
            if (textarea) textarea.focus();
        });
    });

    // ===== 리뷰 삭제 확인 =====
    document.querySelectorAll('[data-delete-review]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!confirm('리뷰를 삭제할까요?')) {
                event.preventDefault();
            }
        });
    });

    // ===== 리뷰 전체보기 (높이 넘칠 때만 버튼 노출) =====
    var list = document.querySelector('[data-review-list]');
    var btn = document.querySelector('[data-review-expand]');
    if (list && btn) {
        if (list.scrollHeight > list.clientHeight + 4) {
            btn.hidden = false;
        }
        btn.addEventListener('click', function () {
            var expanded = list.classList.toggle('is-expanded');
            btn.textContent = expanded ? '접기' : '전체 리뷰 보기';
        });
    }
})();
</script>

<?php
if (!$DEMO_MODE) {
    $stmt->close();
    $reviewStmt->close();
}
require_once __DIR__ . '/includes/footer.php';
?>
