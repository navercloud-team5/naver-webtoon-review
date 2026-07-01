<?php

if (session_status() === PHP_SESSION_ACTIVE) {
    return;
}

if (!$DEMO_MODE && isset($conn) && $conn instanceof mysqli) {

    class DbSessionHandler implements SessionHandlerInterface
    {
        private mysqli $conn;

        public function __construct(mysqli $conn)
        {
            $this->conn = $conn;
        }

        public function open(string $savePath, string $sessionName): bool
        {
            return true;
        }

        public function close(): bool
        {
            return true;
        }

        public function read(string $id): string
        {
            $stmt = $this->conn->prepare("SELECT data FROM sessions WHERE id = ?");
            $stmt->bind_param('s', $id);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            return $row ? $row['data'] : '';
        }

        public function write(string $id, string $data): bool
        {
            $now = time();
            $stmt = $this->conn->prepare("
                INSERT INTO sessions (id, data, last_access)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE data = VALUES(data), last_access = VALUES(last_access)
            ");
            $stmt->bind_param('ssi', $id, $data, $now);
            $ok = $stmt->execute();
            $stmt->close();

            return $ok;
        }

        public function destroy(string $id): bool
        {
            $stmt = $this->conn->prepare("DELETE FROM sessions WHERE id = ?");
            $stmt->bind_param('s', $id);
            $stmt->execute();
            $stmt->close();

            return true;
        }

        public function gc(int $maxLifetime): int|false
        {
            $threshold = time() - $maxLifetime;
            $stmt = $this->conn->prepare("DELETE FROM sessions WHERE last_access < ?");
            $stmt->bind_param('i', $threshold);
            $stmt->execute();
            $affected = $stmt->affected_rows;
            $stmt->close();

            return $affected;
        }
    }

    session_set_save_handler(new DbSessionHandler($conn), true);
}

// DEMO_MODE(DB 연결 실패)일 때는 자동으로 PHP 기본 파일 세션으로 폴백됨
session_start();
