<?php

namespace DAWOnline\Tests\Feature;

use PHPUnit\Framework\TestCase;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use PDO;

class ApiEndpointsTest extends TestCase
{
    private $client;
    private static $pdo;
    private static $baseUrl = 'http://localhost:8000';
    private $authToken;
    private $testUserId;
    
    public static function setUpBeforeClass(): void
    {
        // Configurar banco de dados de teste
        self::$pdo = new PDO('sqlite::memory:');
        self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Criar tabelas necessárias
        self::createTestTables();
        
        // Configurar constantes se necessário
        if (!defined('JWT_SECRET')) {
            define('JWT_SECRET', 'test_secret_feature_key');
        }
    }
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Cliente HTTP para requisições
        $this->client = new Client([
            'base_uri' => self::$baseUrl,
            'timeout' => 10.0,
            'http_errors' => false // Não lançar exceções para códigos 4xx/5xx
        ]);
        
        // Limpar dados e criar usuário de teste
        $this->cleanDatabase();
        $this->createTestUser();
    }
    
    private static function createTestTables(): void
    {
        $sql = [
            "CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username VARCHAR(50) UNIQUE NOT NULL,
                email VARCHAR(100) UNIQUE NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )",
            "CREATE TABLE projects (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                name VARCHAR(100) NOT NULL,
                description TEXT,
                project_data TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )",
            "CREATE TABLE tracks (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                project_id INTEGER NOT NULL,
                name VARCHAR(100) NOT NULL,
                volume REAL DEFAULT 1.0,
                pan REAL DEFAULT 0.0,
                muted BOOLEAN DEFAULT 0,
                solo BOOLEAN DEFAULT 0,
                track_order INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
            )"
        ];
        
        foreach ($sql as $query) {
            self::$pdo->exec($query);
        }
    }
    
    private function cleanDatabase(): void
    {
        self::$pdo->exec("DELETE FROM tracks");
        self::$pdo->exec("DELETE FROM projects");
        self::$pdo->exec("DELETE FROM users");
    }
    
    private function createTestUser(): void
    {
        $username = 'apitest_user';
        $email = 'apitest@example.com';
        $password = 'testpassword123';
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        
        $stmt = self::$pdo->prepare("
            INSERT INTO users (username, email, password_hash) 
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$username, $email, $hashedPassword]);
        $this->testUserId = self::$pdo->lastInsertId();
        
        // Gerar token de autenticação
        $this->authToken = $this->generateTestToken($this->testUserId, $username);
    }
    
    private function generateTestToken(int $userId, string $username): string
    {
        $payload = [
            'iss' => 'DAW Online',
            'aud' => 'DAW Online Users',
            'iat' => time(),
            'exp' => time() + 3600,
            'user_id' => $userId,
            'username' => $username
        ];
        
        return \Firebase\JWT\JWT::encode($payload, JWT_SECRET, 'HS256');
    }
    
    public function testAuthenticationEndpoints()
    {
        // Test POST /api/auth/register
        $registerData = [
            'username' => 'newuser',
            'email' => 'newuser@test.com',
            'password' => 'newpassword123'
        ];
        
        $response = $this->client->post('/api/auth/register', [
            'json' => $registerData
        ]);
        
        $this->assertEquals(201, $response->getStatusCode());
        $responseData = json_decode($response->getBody(), true);
        $this->assertTrue($responseData['success']);
        $this->assertArrayHasKey('user_id', $responseData);
        
        // Test POST /api/auth/login
        $loginData = [
            'username' => 'newuser',
            'password' => 'newpassword123'
        ];
        
        $response = $this->client->post('/api/auth/login', [
            'json' => $loginData
        ]);
        
        $this->assertEquals(200, $response->getStatusCode());
        $responseData = json_decode($response->getBody(), true);
        $this->assertTrue($responseData['success']);
        $this->assertArrayHasKey('token', $responseData);
        $this->assertArrayHasKey('user', $responseData);
        
        // Test login com credenciais incorretas
        $response = $this->client->post('/api/auth/login', [
            'json' => ['username' => 'newuser', 'password' => 'wrongpassword']
        ]);
        
        $this->assertEquals(401, $response->getStatusCode());
        $responseData = json_decode($response->getBody(), true);
        $this->assertFalse($responseData['success']);
    }
    
    public function testProjectEndpoints()
    {
        $headers = ['Authorization' => 'Bearer ' . $this->authToken];
        
        // Test GET /api/projects (lista vazia inicialmente)
        $response = $this->client->get('/api/projects', ['headers' => $headers]);
        $this->assertEquals(200, $response->getStatusCode());
        $projects = json_decode($response->getBody(), true);
        $this->assertIsArray($projects);
        $this->assertEmpty($projects);
        
        // Test POST /api/projects (criar projeto)
        $newProject = [
            'name' => 'Projeto API Test',
            'description' => 'Projeto criado via API para teste'
        ];
        
        $response = $this->client->post('/api/projects', [
            'headers' => $headers,
            'json' => $newProject
        ]);
        
        $this->assertEquals(201, $response->getStatusCode());
        $responseData = json_decode($response->getBody(), true);
        $this->assertTrue($responseData['success']);
        $this->assertArrayHasKey('project_id', $responseData);
        $projectId = $responseData['project_id'];
        
        // Test GET /api/projects (agora deve ter 1 projeto)
        $response = $this->client->get('/api/projects', ['headers' => $headers]);
        $this->assertEquals(200, $response->getStatusCode());
        $projects = json_decode($response->getBody(), true);
        $this->assertCount(1, $projects);
        $this->assertEquals('Projeto API Test', $projects[0]['name']);
        
        // Test GET /api/projects/{id}
        $response = $this->client->get("/api/projects/$projectId", ['headers' => $headers]);
        $this->assertEquals(200, $response->getStatusCode());
        $project = json_decode($response->getBody(), true);
        $this->assertEquals($projectId, $project['id']);
        $this->assertEquals('Projeto API Test', $project['name']);
        
        // Test PUT /api/projects/{id}
        $updateData = [
            'name' => 'Projeto Atualizado',
            'description' => 'Descrição atualizada'
        ];
        
        $response = $this->client->put("/api/projects/$projectId", [
            'headers' => $headers,
            'json' => $updateData
        ]);
        
        $this->assertEquals(200, $response->getStatusCode());
        $responseData = json_decode($response->getBody(), true);
        $this->assertTrue($responseData['success']);
        
        // Verificar atualização
        $response = $this->client->get("/api/projects/$projectId", ['headers' => $headers]);
        $project = json_decode($response->getBody(), true);
        $this->assertEquals('Projeto Atualizado', $project['name']);
        
        // Test DELETE /api/projects/{id}
        $response = $this->client->delete("/api/projects/$projectId", ['headers' => $headers]);
        $this->assertEquals(200, $response->getStatusCode());
        $responseData = json_decode($response->getBody(), true);
        $this->assertTrue($responseData['success']);
        
        // Verificar que foi deletado
        $response = $this->client->get("/api/projects/$projectId", ['headers' => $headers]);
        $this->assertEquals(404, $response->getStatusCode());
    }
    
    public function testTrackEndpoints()
    {
        $headers = ['Authorization' => 'Bearer ' . $this->authToken];
        
        // Primeiro criar um projeto
        $response = $this->client->post('/api/projects', [
            'headers' => $headers,
            'json' => ['name' => 'Projeto para Tracks', 'description' => 'Teste']
        ]);
        $projectData = json_decode($response->getBody(), true);
        $projectId = $projectData['project_id'];
        
        // Test POST /api/projects/{id}/tracks
        $newTrack = [
            'name' => 'Vocal Track',
            'volume' => 0.8,
            'pan' => 0.1
        ];
        
        $response = $this->client->post("/api/projects/$projectId/tracks", [
            'headers' => $headers,
            'json' => $newTrack
        ]);
        
        $this->assertEquals(201, $response->getStatusCode());
        $responseData = json_decode($response->getBody(), true);
        $this->assertTrue($responseData['success']);
        $this->assertArrayHasKey('track_id', $responseData);
        $trackId = $responseData['track_id'];
        
        // Test GET /api/projects/{id}/tracks
        $response = $this->client->get("/api/projects/$projectId/tracks", ['headers' => $headers]);
        $this->assertEquals(200, $response->getStatusCode());
        $tracks = json_decode($response->getBody(), true);
        $this->assertCount(1, $tracks);
        $this->assertEquals('Vocal Track', $tracks[0]['name']);
        $this->assertEquals(0.8, $tracks[0]['volume']);
        
        // Test PUT /api/tracks/{id}
        $updateTrack = [
            'name' => 'Lead Vocal',
            'volume' => 0.9,
            'pan' => 0.0,
            'muted' => false,
            'solo' => true
        ];
        
        $response = $this->client->put("/api/tracks/$trackId", [
            'headers' => $headers,
            'json' => $updateTrack
        ]);
        
        $this->assertEquals(200, $response->getStatusCode());
        $responseData = json_decode($response->getBody(), true);
        $this->assertTrue($responseData['success']);
        
        // Test DELETE /api/tracks/{id}
        $response = $this->client->delete("/api/tracks/$trackId", ['headers' => $headers]);
        $this->assertEquals(200, $response->getStatusCode());
        $responseData = json_decode($response->getBody(), true);
        $this->assertTrue($responseData['success']);
        
        // Verificar que foi deletado
        $response = $this->client->get("/api/projects/$projectId/tracks", ['headers' => $headers]);
        $tracks = json_decode($response->getBody(), true);
        $this->assertEmpty($tracks);
    }
    
    public function testUnauthorizedAccess()
    {
        // Tentar acessar endpoints sem token
        $endpoints = [
            ['method' => 'GET', 'path' => '/api/projects'],
            ['method' => 'POST', 'path' => '/api/projects'],
            ['method' => 'GET', 'path' => '/api/projects/1'],
            ['method' => 'PUT', 'path' => '/api/projects/1'],
            ['method' => 'DELETE', 'path' => '/api/projects/1']
        ];
        
        foreach ($endpoints as $endpoint) {
            $response = $this->client->request($endpoint['method'], $endpoint['path']);
            $this->assertEquals(401, $response->getStatusCode(), 
                "Endpoint {$endpoint['method']} {$endpoint['path']} deveria retornar 401");
        }
        
        // Tentar com token inválido
        $invalidHeaders = ['Authorization' => 'Bearer invalid_token_here'];
        
        foreach ($endpoints as $endpoint) {
            $response = $this->client->request($endpoint['method'], $endpoint['path'], [
                'headers' => $invalidHeaders
            ]);
            $this->assertEquals(401, $response->getStatusCode(),
                "Endpoint {$endpoint['method']} {$endpoint['path']} com token inválido deveria retornar 401");
        }
    }
    
    public function testCorsHeaders()
    {
        // Test preflight request
        $response = $this->client->options('/api/projects', [
            'headers' => [
                'Origin' => 'http://localhost:3000',
                'Access-Control-Request-Method' => 'POST',
                'Access-Control-Request-Headers' => 'Content-Type, Authorization'
            ]
        ]);
        
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContains('http://localhost:3000', 
            $response->getHeaderLine('Access-Control-Allow-Origin'));
        $this->assertStringContains('POST', 
            $response->getHeaderLine('Access-Control-Allow-Methods'));
        $this->assertStringContains('Authorization', 
            $response->getHeaderLine('Access-Control-Allow-Headers'));
    }
    
    public function testErrorHandling()
    {
        $headers = ['Authorization' => 'Bearer ' . $this->authToken];
        
        // Test 404 para projeto inexistente
        $response = $this->client->get('/api/projects/99999', ['headers' => $headers]);
        $this->assertEquals(404, $response->getStatusCode());
        $responseData = json_decode($response->getBody(), true);
        $this->assertFalse($responseData['success']);
        $this->assertArrayHasKey('error', $responseData);
        
        // Test 400 para dados inválidos
        $response = $this->client->post('/api/projects', [
            'headers' => $headers,
            'json' => ['name' => ''] // nome vazio
        ]);
        $this->assertEquals(400, $response->getStatusCode());
        $responseData = json_decode($response->getBody(), true);
        $this->assertFalse($responseData['success']);
        
        // Test 405 para método não permitido
        $response = $this->client->patch('/api/projects', ['headers' => $headers]);
        $this->assertEquals(405, $response->getStatusCode());
    }
    
    public function testRateLimiting()
    {
        $headers = ['Authorization' => 'Bearer ' . $this->authToken];
        
        // Fazer muitas requisições rapidamente
        $responses = [];
        for ($i = 0; $i < 100; $i++) {
            $response = $this->client->get('/api/projects', ['headers' => $headers]);
            $responses[] = $response->getStatusCode();
            
            // Se encontrar rate limiting, parar
            if ($response->getStatusCode() === 429) {
                break;
            }
        }
        
        // Verificar se rate limiting foi aplicado (pode não ser sempre)
        if (in_array(429, $responses)) {
            $this->assertContains(429, $responses);
            
            // Verificar headers de rate limiting
            $response = $this->client->get('/api/projects', ['headers' => $headers]);
            if ($response->getStatusCode() === 429) {
                $this->assertNotEmpty($response->getHeaderLine('X-RateLimit-Limit'));
                $this->assertNotEmpty($response->getHeaderLine('X-RateLimit-Remaining'));
                $this->assertNotEmpty($response->getHeaderLine('Retry-After'));
            }
        }
        
        // Sempre deve ter pelo menos algumas requisições bem-sucedidas
        $this->assertContains(200, $responses);
    }
}
