<?php
/**
 * Gerenciador de autenticação e JWT
 */

namespace DAWOnline;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class AuthManager {
    
    public static function generatePasswordHash($password) {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
    }
    
    public static function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
    
    public static function generateJWT($userId, $username) {
        $payload = [
            'iss' => 'DAW Online',
            'aud' => 'DAW Online Users',
            'iat' => time(),
            'exp' => time() + JWT_EXPIRATION,
            'user_id' => $userId,
            'username' => $username
        ];
        
        return JWT::encode($payload, JWT_SECRET, JWT_ALGORITHM);
    }
    
    public static function validateJWT($token) {
        try {
            $decoded = JWT::decode($token, new Key(JWT_SECRET, JWT_ALGORITHM));
            return (array) $decoded;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    public static function authenticateUser($username, $password) {
        $db = Database::getInstance();
        
        $sql = "SELECT id, username, email, password_hash, nome_completo, status 
                FROM usuarios 
                WHERE (username = ? OR email = ?) AND status = 'ativo'";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();
        
        if ($user && self::verifyPassword($password, $user['password_hash'])) {
            // Atualizar última atividade
            $updateSql = "UPDATE usuarios SET ultima_atividade = CURRENT_TIMESTAMP WHERE id = ?";
            $updateStmt = $db->prepare($updateSql);
            $updateStmt->execute([$user['id']]);
            
            return [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'nome_completo' => $user['nome_completo'],
                'token' => self::generateJWT($user['id'], $user['username'])
            ];
        }
        
        return false;
    }
    
    public static function registerUser($username, $email, $password, $nomeCompleto = null) {
        $db = Database::getInstance();
        
        // Verificar se usuário ou email já existem
        $checkSql = "SELECT COUNT(*) FROM usuarios WHERE username = ? OR email = ?";
        $checkStmt = $db->prepare($checkSql);
        $checkStmt->execute([$username, $email]);
        
        if ($checkStmt->fetchColumn() > 0) {
            throw new \Exception("Usuário ou email já existem");
        }
        
        $passwordHash = self::generatePasswordHash($password);
        
        $sql = "INSERT INTO usuarios (username, email, password_hash, nome_completo) 
                VALUES (?, ?, ?, ?)";
        
        $stmt = $db->prepare($sql);
        
        if ($stmt->execute([$username, $email, $passwordHash, $nomeCompleto])) {
            $userId = $db->lastInsertId();
            
            return [
                'id' => $userId,
                'username' => $username,
                'email' => $email,
                'nome_completo' => $nomeCompleto,
                'token' => self::generateJWT($userId, $username)
            ];
        }
        
        throw new \Exception("Erro ao criar usuário");
    }
    
    public static function getCurrentUser($token) {
        $decoded = self::validateJWT($token);
        
        if (!$decoded) {
            return false;
        }
        
        $db = Database::getInstance();
        $sql = "SELECT id, username, email, nome_completo, avatar, status 
                FROM usuarios 
                WHERE id = ? AND status = 'ativo'";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([$decoded['user_id']]);
        
        return $stmt->fetch();
    }
    
    public static function refreshToken($oldToken) {
        $decoded = self::validateJWT($oldToken);
        
        if (!$decoded) {
            return false;
        }
        
        return self::generateJWT($decoded['user_id'], $decoded['username']);
    }
}
