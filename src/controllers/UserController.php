<?php

namespace Controllers;

use PDO;
use Utils\JWT;

class UserController
{
    private PDO $db;
    private array $jwtConfig;

    public function __construct(PDO $db, array $jwtConfig)
    {
        $this->db = $db;
        $this->jwtConfig = $jwtConfig;
    }

    public function register()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || empty($data['email']) || empty($data['password'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid data']);
            return;
        }
        $stmt = $this->db->prepare('INSERT INTO users (email, password_hash, display_name) VALUES (?, ?, ?)');
        $passwordHash = password_hash($data['password'], PASSWORD_BCRYPT);
        try {
            $stmt->execute([$data['email'], $passwordHash, $data['display_name'] ?? null]);
            $id = (int)$this->db->lastInsertId();
        } catch (\PDOException $e) {
            http_response_code(400);
            echo json_encode(['error' => 'User exists']);
            return;
        }
        $token = $this->createToken($id);
        echo json_encode(['token' => $token, 'user' => ['id' => $id, 'email' => $data['email'], 'display_name' => $data['display_name'] ?? null]]);
    }

    public function login()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || empty($data['email']) || empty($data['password'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid data']);
            return;
        }
        $stmt = $this->db->prepare('SELECT id, password_hash, display_name FROM users WHERE email = ?');
        $stmt->execute([$data['email']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user || !password_verify($data['password'], $user['password_hash'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid credentials']);
            return;
        }
        $token = $this->createToken((int)$user['id']);
        echo json_encode(['token' => $token, 'user' => ['id' => (int)$user['id'], 'email' => $data['email'], 'display_name' => $user['display_name']]]);
    }

    private function createToken(int $userId): string
    {
        $payload = [
            'sub' => $userId,
            'iss' => $this->jwtConfig['issuer'],
            'aud' => $this->jwtConfig['audience'],
            'iat' => time(),
            'exp' => time() + $this->jwtConfig['expiration'],
        ];
        return JWT::encode($payload, $this->jwtConfig['secret']);
    }
}
