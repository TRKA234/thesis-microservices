<?php

namespace App\Middleware;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class AuthMiddleware
{
    public function handle(): ?object
    {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? '';

        if (empty($authHeader)) {
            $this->unauthorized('Authorization header required');
        }

        $parts = explode(' ', $authHeader);
        if (count($parts) !== 2 || $parts[0] !== 'Bearer') {
            $this->unauthorized('Invalid authorization format');
        }

        $token = $parts[1];

        try {
            $decoded = JWT::decode($token, new Key($_ENV['JWT_SECRET'], 'HS256'));

            // Store user info in global variable for controllers
            $GLOBALS['auth_user'] = [
                'user_id' => $decoded->user_id,
                'identity_number' => $decoded->identity_number,
                'role' => $decoded->role
            ];

            return $decoded;
        } catch (\Exception $e) {
            $this->unauthorized('Invalid or expired token');
        }

        return null;
    }

    private function unauthorized(string $message): void
    {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => $message
        ]);
        exit;
    }
}
