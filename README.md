# 네이버 웹툰 리뷰 사이트

PHP 8.3 + Apache + Naver Cloud DB for MySQL 기반 웹툰 리뷰 사이트입니다.

## 현재 DB 구조

서버 DB는 `schema.sql`의 구조를 사용합니다.

- `users`: 네이버 간편로그인 사용자
- `webtoons`: 네이버 웹툰 CSV 데이터
- `reviews`: 웹툰 리뷰
- `review_likes`: 리뷰 좋아요

## 서버 패키지

```bash
sudo apt update
sudo apt install -y apache2 php libapache2-mod-php php-mysql php-curl mysql-client
sudo systemctl restart apache2
```

## DB 설정

`config/db.php`에서 Cloud DB 정보를 입력합니다.

```php
$DB_HOST = "DB_PRIVATE_DOMAIN";
$DB_USER = "jjh0813";
$DB_PASS = "DB_PASSWORD";
$DB_NAME = "webtoon_db";
```

## 네이버 로그인 설정

```bash
cp config/naver.example.php config/naver.php
```

`config/naver.php`에 네이버 개발자센터 값을 입력합니다.

```php
return [
    'client_id' => 'NAVER_CLIENT_ID',
    'client_secret' => 'NAVER_CLIENT_SECRET',
    'redirect_uri' => 'http://101.79.29.119/naver_callback.php',
];
```

네이버 개발자센터 설정:

```text
서비스 환경: PC 웹
서비스 URL: http://101.79.29.119
Callback URL: http://101.79.29.119/naver_callback.php
제공 정보: 별명, 이메일, 프로필 사진
```

## 웹툰 CSV import

`naver_webtoons_weekday_export.csv`를 서버에 올린 뒤 실행합니다.

```bash
mysql --local-infile=1 -h DB_PRIVATE_DOMAIN -P 3306 -u jjh0813 -p webtoon_db
```

```sql
LOAD DATA LOCAL INFILE '/path/to/naver_webtoons_weekday_export.csv'
INTO TABLE webtoons
CHARACTER SET utf8mb4
FIELDS TERMINATED BY ','
ENCLOSED BY '"'
LINES TERMINATED BY '\r\n'
IGNORE 1 ROWS
(external_id, title, thumbnail_url, authors, update_days, provider, source_url);
```

확인:

```sql
SELECT COUNT(*) FROM webtoons;

SELECT update_days, COUNT(*)
FROM webtoons
GROUP BY update_days
ORDER BY FIELD(update_days, 'MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT', 'SUN');
```

## 테스트

```bash
php -l index.php
php -l list.php
php -l detail.php
php -l naver_login.php
php -l naver_callback.php
```

브라우저:

```text
http://101.79.29.119/index.php
http://101.79.29.119/index.php?login=1
```

## 주요 기능

- 네이버 간편로그인 모달
- 월~일 웹툰 목록
- 상단 제목/작가 검색
- 웹툰 상세
- 리뷰 작성 및 재작성 시 수정 저장
- 리뷰 좋아요/좋아요 취소
- 마이페이지에서 내가 쓴 리뷰 확인
