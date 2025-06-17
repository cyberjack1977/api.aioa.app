<?php

namespace Controllers;

use PDO;
use Utils\JWT;

class TrackController
{
    private PDO $db;
    private array $jwtConfig;
    private string $secretSalt = 'track_salt';

    public function __construct(PDO $db, array $jwtConfig)
    {
        $this->db = $db;
        $this->jwtConfig = $jwtConfig;
    }

    private function auth(): ?int
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (!preg_match('/Bearer (.+)/', $header, $m)) {
            return null;
        }
        $payload = JWT::decode($m[1], $this->jwtConfig['secret']);
        return $payload['sub'] ?? null;
    }

    public function uploadInit()
    {
        $userId = $this->auth();
        if (!$userId) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            return;
        }
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || empty($data['title'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid data']);
            return;
        }
        $stmt = $this->db->prepare('INSERT INTO tracks (user_id, title, ai_model, track_hash) VALUES (?, ?, ?, "")');
        $stmt->execute([$userId, $data['title'], $data['ai_model'] ?? null]);
        $trackId = (int)$this->db->lastInsertId();
        $trackHash = sha1($trackId . $this->secretSalt);
        $this->db->prepare('UPDATE tracks SET track_hash = ? WHERE id = ?')->execute([$trackHash, $trackId]);
        $uploadToken = bin2hex(random_bytes(16));
        $this->db->prepare('INSERT INTO upload_tokens (track_id, token) VALUES (?, ?)')->execute([$trackId, $uploadToken]);
        echo json_encode([
            'track_id' => $trackId,
            'track_hash' => $trackHash,
            'upload_token' => $uploadToken,
            'upload_url' => 'https://upload.aioa.app/upload.php?token=' . $uploadToken,
        ]);
    }

    public function getTrack(int $id)
    {
        $stmt = $this->db->prepare('SELECT title, ai_model, track_hash FROM tracks WHERE id = ?');
        $stmt->execute([$id]);
        $track = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$track) {
            http_response_code(404);
            echo json_encode(['error' => 'Not found']);
            return;
        }
        $hash = $track['track_hash'];
        $urls = [
            '128' => "https://media.aioa.app/tracks/{$hash}_128.mp3",
            '320' => "https://media.aioa.app/tracks/{$hash}_320.mp3",
        ];
        echo json_encode([
            'title' => $track['title'],
            'ai_model' => $track['ai_model'],
            'stream_urls' => $urls,
        ]);
    }
}
