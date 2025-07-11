<?php

namespace DAWOnline\Tests\Integration;

use PHPUnit\Framework\TestCase;
use DAWOnline\AuthManager;
use DAWOnline\Database;
use PDO;

class AuthIntegrationTest extends TestCase
{
    private static $pdo;
    
    public static function setUpBeforeClass(): void
    {
        // Configurar banco de dados em memória para testes
        self::$pdo = new PDO('sqlite::memory:');
        self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Criar tabela de usuários
        self::$pdo->exec("
            CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username VARCHAR(50) UNIQUE NOT NULL,
                email VARCHAR(100) UNIQUE NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // Configurar constantes se não existirem
        if (!defined('JWT_SECRET')) {
            define('JWT_SECRET', 'test_secret_integration_key');
        }
        if (!defined('JWT_ALGORITHM')) {
            define('JWT_ALGORITHM', 'HS256');
        }
        if (!defined('JWT_EXPIRATION')) {
            define('JWT_EXPIRATION', 3600);
        }
        if (!defined('BCRYPT_COST')) {
            define('BCRYPT_COST', 10);
        }
    }
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Limpar tabela de usuários antes de cada teste
        self::$pdo->exec("DELETE FROM users");
    }
    
    public function testCompleteAuthenticationFlow()
    {
        $username = 'testuser';
        $email = 'test@example.com';
        $password = 'senha123';
        
        // 1. Registrar usuário
        $hashedPassword = AuthManager::generatePasswordHash($password);
        
        $stmt = self::$pdo->prepare("
            INSERT INTO users (username, email, password_hash) 
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$username, $email, $hashedPassword]);
        $userId = self::$pdo->lastInsertId();
        
        // 2. Verificar se usuário foi criado
        $stmt = self::$pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $this->assertNotFalse($user);
        $this->assertEquals($username, $user['username']);
        $this->assertEquals($email, $user['email']);
        
        // 3. Verificar senha
        $this->assertTrue(AuthManager::verifyPassword($password, $user['password_hash']));
        $this->assertFalse(AuthManager::verifyPassword('senha_errada', $user['password_hash']));
        
        // 4. Gerar e validar JWT
        $token = AuthManager::generateJWT($userId, $username);
        $this->assertIsString($token);
        
        $decoded = AuthManager::validateJWT($token);
        $this->assertIsArray($decoded);
        $this->assertEquals($userId, $decoded['user_id']);
        $this->assertEquals($username, $decoded['username']);
        
        // 5. Usar JWT para "autenticar" requisições subsequentes
        $this->assertTrue($this->authenticateWithToken($token));
    }
    
    public function testUserLoginSimulation()
    {
        // Criar usuário no banco
        $username = 'loginuser';
        $email = 'login@example.com';
        $password = 'minhasenha123';
        $hashedPassword = AuthManager::generatePasswordHash($password);
        
        $stmt = self::$pdo->prepare("
            INSERT INTO users (username, email, password_hash) 
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$username, $email, $hashedPassword]);
        $userId = self::$pdo->lastInsertId();
        
        // Simular processo de login
        $loginResult = $this->simulateLogin($username, $password);
        
        $this->assertTrue($loginResult['success']);
        $this->assertArrayHasKey('token', $loginResult);
        $this->assertArrayHasKey('user', $loginResult);
        $this->assertEquals($username, $loginResult['user']['username']);
        $this->assertEquals($email, $loginResult['user']['email']);
        
        // Verificar se o token é válido
        $decoded = AuthManager::validateJWT($loginResult['token']);
        $this->assertEquals($userId, $decoded['user_id']);
        $this->assertEquals($username, $decoded['username']);
    }
    
    public function testFailedLoginAttempts()
    {
        // Criar usuário
        $username = 'secureuser';
        $email = 'secure@example.com';
        $password = 'senhasegura123';
        $hashedPassword = AuthManager::generatePasswordHash($password);
        
        $stmt = self::$pdo->prepare("
            INSERT INTO users (username, email, password_hash) 
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$username, $email, $hashedPassword]);
        
        // Tentar login com senha incorreta
        $failedLogin = $this->simulateLogin($username, 'senha_incorreta');
        $this->assertFalse($failedLogin['success']);
        $this->assertArrayHasKey('error', $failedLogin);
        
        // Tentar login com usuário inexistente
        $failedLogin2 = $this->simulateLogin('usuario_inexistente', $password);
        $this->assertFalse($failedLogin2['success']);
        $this->assertArrayHasKey('error', $failedLogin2);
    }
    
    public function testTokenExpiration()
    {
        $username = 'expireuser';
        $userId = 1;
        
        // Criar token com expiração no passado
        $expiredPayload = [
            'iss' => 'DAW Online',
            'aud' => 'DAW Online Users',
            'iat' => time() - 7200, // 2 horas atrás
            'exp' => time() - 3600, // 1 hora atrás (expirado)
            'user_id' => $userId,
            'username' => $username
        ];
        
        $expiredToken = \Firebase\JWT\JWT::encode($expiredPayload, JWT_SECRET, JWT_ALGORITHM);
        
        // Tentar validar token expirado
        $result = AuthManager::validateJWT($expiredToken);
        $this->assertFalse($result);
        
        // Verificar que não consegue autenticar com token expirado
        $this->assertFalse($this->authenticateWithToken($expiredToken));
    }
    
    public function testMultipleUsersAuthentication()
    {
        $users = [
            ['username' => 'user1', 'email' => 'user1@test.com', 'password' => 'pass1'],
            ['username' => 'user2', 'email' => 'user2@test.com', 'password' => 'pass2'],
            ['username' => 'user3', 'email' => 'user3@test.com', 'password' => 'pass3']
        ];
        
        $tokens = [];
        
        // Criar usuários e gerar tokens
        foreach ($users as $userData) {
            $hashedPassword = AuthManager::generatePasswordHash($userData['password']);
            
            $stmt = self::$pdo->prepare("
                INSERT INTO users (username, email, password_hash) 
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$userData['username'], $userData['email'], $hashedPassword]);
            $userId = self::$pdo->lastInsertId();
            
            $token = AuthManager::generateJWT($userId, $userData['username']);
            $tokens[$userData['username']] = $token;
        }
        
        // Verificar que todos os tokens são válidos e únicos
        $this->assertCount(3, $tokens);
        $this->assertCount(3, array_unique($tokens)); // Todos diferentes
        
        foreach ($tokens as $username => $token) {
            $decoded = AuthManager::validateJWT($token);
            $this->assertEquals($username, $decoded['username']);
        }
    }
    
    public function testPasswordSecurityRequirements()
    {
        $passwords = [
            'senha123',           // válida
            'MinhaSenh@123',      // válida com caracteres especiais
            'a',                  // muito curta
            str_repeat('a', 1000) // muito longa
        ];
        
        foreach ($passwords as $password) {
            if (strlen($password) === 0) {
                $this->expectException(\ValueError::class);
                AuthManager::generatePasswordHash($password);
            } else {
                $hash = AuthManager::generatePasswordHash($password);
                $this->assertTrue(AuthManager::verifyPassword($password, $hash));
            }
        }
    }
    
    private function simulateLogin(string $username, string $password): array
    {
        // Buscar usuário no banco
        $stmt = self::$pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            return ['success' => false, 'error' => 'Usuário não encontrado'];
        }
        
        // Verificar senha
        if (!AuthManager::verifyPassword($password, $user['password_hash'])) {
            return ['success' => false, 'error' => 'Senha incorreta'];
        }
        
        // Gerar token
        $token = AuthManager::generateJWT($user['id'], $user['username']);
        
        return [
            'success' => true,
            'token' => $token,
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email']
            ]
        ];
    }
    
    private function authenticateWithToken(string $token): bool
    {
        $decoded = AuthManager::validateJWT($token);
        return $decoded !== false && isset($decoded['user_id']) && isset($decoded['username']);
    }
}
