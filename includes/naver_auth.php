<?php

const NAVER_AUTHORIZE_URL = 'https://nid.naver.com/oauth2.0/authorize';
const NAVER_TOKEN_URL = 'https://nid.naver.com/oauth2.0/token';
const NAVER_PROFILE_URL = 'https://openapi.naver.com/v1/nid/me';

function naver_config() {
    $path = __DIR__ . '/../config/naver.php';

    if (!is_file($path)) {
        throw new RuntimeException('config/naver.php 파일이 없습니다. config/naver.example.php를 복사해서 값을 채워주세요.');
    }

    return require $path;
}

function naver_create_state() {
    return bin2hex(random_bytes(16));
}

function naver_authorization_url($config, $state) {
    $query = http_build_query([
        'response_type' => 'code',
        'client_id' => $config['client_id'],
        'redirect_uri' => $config['redirect_uri'],
        'state' => $state,
    ], '', '&', PHP_QUERY_RFC3986);

    return NAVER_AUTHORIZE_URL . '?' . $query;
}

function naver_validate_state($receivedState) {
    $savedState = $_SESSION['naver_oauth_state'] ?? null;
    unset($_SESSION['naver_oauth_state']);

    return is_string($savedState)
        && is_string($receivedState)
        && hash_equals($savedState, $receivedState);
}

function naver_issue_token($code, $state) {
    $config = naver_config();
    $query = http_build_query([
        'grant_type' => 'authorization_code',
        'client_id' => $config['client_id'],
        'client_secret' => $config['client_secret'],
        'code' => $code,
        'state' => $state,
    ], '', '&', PHP_QUERY_RFC3986);

    $response = naver_request_json(NAVER_TOKEN_URL . '?' . $query);

    if (!empty($response['error'])) {
        throw new RuntimeException('네이버 토큰 발급 실패: ' . ($response['error_description'] ?? $response['error']));
    }

    if (empty($response['access_token'])) {
        throw new RuntimeException('네이버 토큰 응답에 access_token이 없습니다.');
    }

    return $response;
}

function naver_fetch_profile($accessToken) {
    $response = naver_request_json(NAVER_PROFILE_URL, [
        'Authorization: Bearer ' . $accessToken,
    ]);

    if (($response['resultcode'] ?? null) !== '00') {
        throw new RuntimeException('네이버 프로필 조회 실패: ' . ($response['message'] ?? 'unknown error'));
    }

    if (empty($response['response']['id'])) {
        throw new RuntimeException('네이버 프로필 응답에 사용자 id가 없습니다.');
    }

    return $response['response'];
}

function naver_find_or_create_user($conn, $profile) {
    $provider = 'naver';
    $providerId = (string)$profile['id'];
    $nickname = trim((string)($profile['nickname'] ?? ''));
    $email = $profile['email'] ?? null;
    $profileImage = $profile['profile_image'] ?? null;

    if ($nickname === '') {
        $nickname = '네이버사용자';
    }

    $stmt = $conn->prepare("
        INSERT INTO users (login_provider, provider_id, nickname, email, profile_image)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            nickname = VALUES(nickname),
            email = VALUES(email),
            profile_image = VALUES(profile_image),
            updated_at = CURRENT_TIMESTAMP
    ");
    $stmt->bind_param("sssss", $provider, $providerId, $nickname, $email, $profileImage);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("
        SELECT id, login_provider, provider_id, nickname, email, profile_image
        FROM users
        WHERE login_provider = ? AND provider_id = ?
    ");
    $stmt->bind_param("ss", $provider, $providerId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user) {
        throw new RuntimeException('사용자 저장 후 조회에 실패했습니다.');
    }

    return $user;
}

function naver_request_json($url, $headers = []) {
    if (!function_exists('curl_init')) {
        throw new RuntimeException('PHP cURL 확장이 필요합니다. sudo apt install -y php-curl 후 Apache를 재시작하세요.');
    }

    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => array_merge(['Accept: application/json'], $headers),
    ]);

    $body = curl_exec($curl);
    $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $error = curl_error($curl);
    curl_close($curl);

    if ($body === false || $error !== '') {
        throw new RuntimeException('네이버 API 요청 실패: ' . $error);
    }

    $data = json_decode($body, true);

    if (!is_array($data)) {
        throw new RuntimeException('네이버 API JSON 파싱 실패. HTTP status: ' . $status);
    }

    if ($status < 200 || $status >= 300) {
        throw new RuntimeException('네이버 API 응답 오류. HTTP status: ' . $status);
    }

    return $data;
}
