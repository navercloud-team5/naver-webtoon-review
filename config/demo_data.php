<?php
// DB 연결이 안 될 때 디자인 확인용으로 쓰이는 가짜 데이터입니다.

$demoWebtoons = [
    ['id' => 1, 'title' => 'K학원 생존기', 'authors' => '양아최', 'update_days' => 'MON', 'thumbnail_url' => 'images/placeholder.svg', 'provider' => 'NAVER', 'source_url' => '#', 'avg_rating' => 4.7, 'review_count' => 2, 'like_count' => 4],
    ['id' => 2, 'title' => '1초', 'authors' => '시니, 광운', 'update_days' => 'FRI', 'thumbnail_url' => 'images/placeholder.svg', 'provider' => 'NAVER', 'source_url' => '#', 'avg_rating' => 4.5, 'review_count' => 1, 'like_count' => 2],
    ['id' => 3, 'title' => '나노마신', 'authors' => '현절무, 금강불괴', 'update_days' => 'THU', 'thumbnail_url' => 'images/placeholder.svg', 'provider' => 'NAVER', 'source_url' => '#', 'avg_rating' => 4.8, 'review_count' => 1, 'like_count' => 3],
    ['id' => 4, 'title' => '화산귀환', 'authors' => 'LICO, 비가', 'update_days' => 'WED', 'thumbnail_url' => 'images/placeholder.svg', 'provider' => 'NAVER', 'source_url' => '#', 'avg_rating' => 4.6, 'review_count' => 1, 'like_count' => 1],
];

$demoReviews = [
    ['id' => 1, 'webtoon_id' => 1, 'nickname' => '웹툰러버', 'rating' => 5, 'content' => '전개가 빠르고 시원해요.', 'created_at' => '2026-06-20 10:00:00', 'webtoon_title' => 'K학원 생존기', 'like_count' => 3, 'is_liked' => 0],
    ['id' => 2, 'webtoon_id' => 1, 'nickname' => '독자A', 'rating' => 4.5, 'content' => '그림체가 점점 좋아져요.', 'created_at' => '2026-06-21 14:30:00', 'webtoon_title' => 'K학원 생존기', 'like_count' => 1, 'is_liked' => 0],
    ['id' => 3, 'webtoon_id' => 3, 'nickname' => '목요웹툰', 'rating' => 5, 'content' => '목요일 작품 중 제일 재밌어요.', 'created_at' => '2026-06-22 23:10:00', 'webtoon_title' => '나노마신', 'like_count' => 2, 'is_liked' => 0],
];

function demoFilterWebtoons($webtoons, $q = '', $day = '', $sort = 'rating') {
    $filtered = array_filter($webtoons, function ($w) use ($q, $day) {
        if ($q !== '' && mb_stripos($w['title'], $q) === false && mb_stripos($w['authors'], $q) === false) return false;
        if ($day !== '' && $w['update_days'] !== $day) return false;
        return true;
    });

    $filtered = array_values($filtered);

    usort($filtered, function ($a, $b) use ($sort) {
        if ($sort === 'newest') return $b['id'] <=> $a['id'];
        if ($sort === 'reviews') return ($b['review_count'] ?? 0) <=> ($a['review_count'] ?? 0);
        if ($sort === 'likes') return ($b['like_count'] ?? 0) <=> ($a['like_count'] ?? 0);
        if ($sort === 'title') return strcmp($a['title'], $b['title']);
        return [($b['avg_rating'] ?? 0), ($b['review_count'] ?? 0), ($b['like_count'] ?? 0)]
            <=> [($a['avg_rating'] ?? 0), ($a['review_count'] ?? 0), ($a['like_count'] ?? 0)];
    });

    return $filtered;
}
?>
