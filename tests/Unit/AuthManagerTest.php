<?php

namespace DAWOnline\Tests\Unit;

use PHPUnit\Framework\TestCase;
use DAWOnline\AuthManager;
use Exception;

class AuthManagerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Configurar constantes para teste
        if (!defined('JWT_SECRET')) {
            define('JWT_SECRET', 'test_secret_key_for_testing_only');
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
    
    public function testGeneratePasswordHash()
    {
        $password = 'senha123';
        $hash = AuthManager::generatePasswordHash($password);
        
        $this->assertIsString($hash);
        $this->assertTrue(password_verify($password, $hash));
        
        // Testar que hashes diferentes são gerados para a mesma senha
        $hash2 = AuthManager::generatePasswordHash($password);
        $this->assertNotEquals($hash, $hash2);
    }
    
    public function testVerifyPassword()
    {
        $password = 'senha123';
        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
        
        $this->assertTrue(AuthManager::verifyPassword($password, $hash));
        $this->assertFalse(AuthManager::verifyPassword('senha_errada', $hash));
        $this->assertFalse(AuthManager::verifyPassword($password, 'hash_invalido'));
    }
    
    public function testGenerateJWT()
    {
        $userId = 1;
        $username = 'testuser';
        
        $token = AuthManager::generateJWT($userId, $username);
        
        $this->assertIsString($token);
        $this->assertNotEmpty($token);
        
        // Verificar se o token tem 3 partes separadas por pontos
        $parts = explode('.', $token);
        $this->assertCount(3, $parts);
    }
    
    public function testValidateJWT()
    {
        $userId = 1;
        $username = 'testuser';
        
        // Gerar token válido
        $token = AuthManager::generateJWT($userId, $username);
        
        // Validar token
        $decoded = AuthManager::validateJWT($token);
        
        $this->assertIsArray($decoded);
        $this->assertEquals($userId, $decoded['user_id']);
        $this->assertEquals($username, $decoded['username']);
        $this->assertEquals('DAW Online', $decoded['iss']);
        $this->assertEquals('DAW Online Users', $decoded['aud']);
    }
    
    public function testValidateInvalidJWT()
    {
        // Token inválido
        $invalidToken = 'token.invalido.aqui';
        
        $result = AuthManager::validateJWT($invalidToken);
        
        $this->assertFalse($result);
    }
    
    public function testValidateExpiredJWT()
    {
        // Simular token expirado mudando temporariamente a constante
        $originalExpiration = JWT_EXPIRATION;
        
        // Criar token com expiração no passado
        $payload = [
            'iss' => 'DAW Online',
            'aud' => 'DAW Online Users',
            'iat' => time() - 7200, // 2 horas atrás
            'exp' => time() - 3600, // 1 hora atrás (expirado)
            'user_id' => 1,
            'username' => 'testuser'
        ];
        
        $expiredToken = \Firebase\JWT\JWT::encode($payload, JWT_SECRET, JWT_ALGORITHM);
        
        $result = AuthManager::validateJWT($expiredToken);
        
        $this->assertFalse($result);
    }
    
    public function testGenerateJWTWithSpecialCharacters()
    {
        $userId = 123;
        $username = 'usuário_çom_açentos_123';
        
        $token = AuthManager::generateJWT($userId, $username);
        $decoded = AuthManager::validateJWT($token);
        
        $this->assertEquals($userId, $decoded['user_id']);
        $this->assertEquals($username, $decoded['username']);
    }
    
    public function testPasswordHashWithEmptyPassword()
    {
        $this->expectException(\ValueError::class);
        AuthManager::generatePasswordHash('');
    }
    
    public function testPasswordHashWithLongPassword()
    {
        $longPassword = str_repeat('a', 1000);
        $hash = AuthManager::generatePasswordHash($longPassword);
        
        $this->assertTrue(AuthManager::verifyPassword($longPassword, $hash));
    }
    
    public function testJWTWithLargeUserId()
    {
        $userId = PHP_INT_MAX;
        $username = 'testuser';
        
        $token = AuthManager::generateJWT($userId, $username);
        $decoded = AuthManager::validateJWT($token);
        
        $this->assertEquals($userId, $decoded['user_id']);
    }
    
    public function testJWTTokenStructure()
    {
        $userId = 1;
        $username = 'testuser';
        
        $token = AuthManager::generateJWT($userId, $username);
        $parts = explode('.', $token);
        
        // Verificar header
        $header = json_decode(base64_decode(str_pad(strtr($parts[0], '-_', '+/'), strlen($parts[0]) % 4, '=', STR_PAD_RIGHT)), true);
        $this->assertEquals('JWT', $header['typ']);
        $this->assertEquals('HS256', $header['alg']);
        
        // Verificar payload
        $payload = json_decode(base64_decode(str_pad(strtr($parts[1], '-_', '+/'), strlen($parts[1]) % 4, '=', STR_PAD_RIGHT)), true);
        $this->assertEquals($userId, $payload['user_id']);
        $this->assertEquals($username, $payload['username']);
        $this->assertArrayHasKey('iat', $payload);
        $this->assertArrayHasKey('exp', $payload);
    }
}
