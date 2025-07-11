<?php

namespace DAWOnline\Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;
use PDO;

abstract class TestCase extends BaseTestCase
{
    protected static $testConfig;
    protected static $testDatabase;
    
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        
        // Carregar configurações de teste
        self::$testConfig = require __DIR__ . '/config.php';
        
        // Configurar constantes para testes se não existirem
        self::defineTestConstants();
        
        // Configurar banco de dados de teste
        self::setupTestDatabase();
    }
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Limpar cache, sessões, etc. antes de cada teste
        $this->clearTestState();
    }
    
    protected function tearDown(): void
    {
        // Limpeza após cada teste
        $this->clearTestState();
        
        parent::tearDown();
    }
    
    private static function defineTestConstants(): void
    {
        $constants = [
            'JWT_SECRET' => self::$testConfig['jwt']['secret'],
            'JWT_ALGORITHM' => self::$testConfig['jwt']['algorithm'],
            'JWT_EXPIRATION' => self::$testConfig['jwt']['expiration'],
            'BCRYPT_COST' => self::$testConfig['bcrypt']['cost'],
        ];
        
        foreach ($constants as $name => $value) {
            if (!defined($name)) {
                define($name, $value);
            }
        }
    }
    
    private static function setupTestDatabase(): void
    {
        self::$testDatabase = new PDO('sqlite::memory:');
        self::$testDatabase->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Executar migrations/schema para testes
        self::createTestTables();
    }
    
    private static function createTestTables(): void
    {
        $tables = [
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
            )",
            
            "CREATE TABLE audio_regions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                track_id INTEGER NOT NULL,
                start_time REAL NOT NULL,
                duration REAL NOT NULL,
                file_path VARCHAR(255),
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (track_id) REFERENCES tracks(id) ON DELETE CASCADE
            )",
            
            "CREATE TABLE plugins (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                track_id INTEGER NOT NULL,
                name VARCHAR(100) NOT NULL,
                type VARCHAR(50) NOT NULL,
                parameters TEXT,
                position INTEGER DEFAULT 0,
                enabled BOOLEAN DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (track_id) REFERENCES tracks(id) ON DELETE CASCADE
            )",
            
            "CREATE TABLE collaboration_sessions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                project_id INTEGER NOT NULL,
                user_id INTEGER NOT NULL,
                session_token VARCHAR(255) NOT NULL,
                connected_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                last_activity DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )"
        ];
        
        foreach ($tables as $sql) {
            self::$testDatabase->exec($sql);
        }
    }
    
    private function clearTestState(): void
    {
        // Limpar tabelas do banco de dados
        if (self::$testDatabase) {
            $tables = [
                'collaboration_sessions',
                'plugins',
                'audio_regions',
                'tracks',
                'projects',
                'users'
            ];
            
            foreach ($tables as $table) {
                self::$testDatabase->exec("DELETE FROM $table");
            }
        }
        
        // Limpar arquivos temporários de teste
        $this->clearTestFiles();
    }
    
    private function clearTestFiles(): void
    {
        $uploadPath = self::$testConfig['files']['upload_path'];
        
        if (is_dir($uploadPath)) {
            $files = glob($uploadPath . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
    }
    
    /**
     * Criar usuário de teste no banco
     */
    protected function createTestUser(string $username = 'testuser', string $email = 'test@example.com', string $password = 'password123'): int
    {
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        
        $stmt = self::$testDatabase->prepare("
            INSERT INTO users (username, email, password_hash) 
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$username, $email, $hashedPassword]);
        
        return self::$testDatabase->lastInsertId();
    }
    
    /**
     * Criar projeto de teste
     */
    protected function createTestProject(int $userId, string $name = 'Test Project', string $description = 'Test description'): int
    {
        $stmt = self::$testDatabase->prepare("
            INSERT INTO projects (user_id, name, description) 
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$userId, $name, $description]);
        
        return self::$testDatabase->lastInsertId();
    }
    
    /**
     * Criar track de teste
     */
    protected function createTestTrack(int $projectId, string $name = 'Test Track', float $volume = 1.0): int
    {
        $stmt = self::$testDatabase->prepare("
            INSERT INTO tracks (project_id, name, volume) 
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$projectId, $name, $volume]);
        
        return self::$testDatabase->lastInsertId();
    }
    
    /**
     * Gerar token JWT para testes
     */
    protected function generateTestJWT(int $userId, string $username): string
    {
        $payload = [
            'iss' => 'DAW Online',
            'aud' => 'DAW Online Users',
            'iat' => time(),
            'exp' => time() + JWT_EXPIRATION,
            'user_id' => $userId,
            'username' => $username
        ];
        
        return \Firebase\JWT\JWT::encode($payload, JWT_SECRET, JWT_ALGORITHM);
    }
    
    /**
     * Simular upload de arquivo
     */
    protected function createTestAudioFile(string $filename = 'test.wav', int $size = 1024): string
    {
        $uploadPath = self::$testConfig['files']['upload_path'];
        
        if (!is_dir($uploadPath)) {
            mkdir($uploadPath, 0755, true);
        }
        
        $filePath = $uploadPath . '/' . $filename;
        
        // Criar arquivo fake com dados de áudio simulados
        $fakeAudioData = str_repeat("RIFF", $size / 4);
        file_put_contents($filePath, $fakeAudioData);
        
        return $filePath;
    }
    
    /**
     * Verificar se arquivo de áudio é válido
     */
    protected function isValidAudioFile(string $filePath): bool
    {
        if (!file_exists($filePath)) {
            return false;
        }
        
        $extension = pathinfo($filePath, PATHINFO_EXTENSION);
        return in_array(strtolower($extension), self::$testConfig['files']['allowed_extensions']);
    }
    
    /**
     * Obter configuração de teste
     */
    protected function getTestConfig(string $key = null)
    {
        if ($key === null) {
            return self::$testConfig;
        }
        
        $keys = explode('.', $key);
        $value = self::$testConfig;
        
        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return null;
            }
            $value = $value[$k];
        }
        
        return $value;
    }
    
    /**
     * Obter conexão do banco de teste
     */
    protected function getTestDatabase(): PDO
    {
        return self::$testDatabase;
    }
    
    /**
     * Assert que um array contém todas as chaves esperadas
     */
    protected function assertArrayHasKeys(array $expectedKeys, array $array, string $message = ''): void
    {
        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $array, $message ?: "Array should contain key: $key");
        }
    }
    
    /**
     * Assert que um valor está dentro de um range
     */
    protected function assertBetween($min, $max, $actual, string $message = ''): void
    {
        $this->assertGreaterThanOrEqual($min, $actual, $message);
        $this->assertLessThanOrEqual($max, $actual, $message);
    }
    
    /**
     * Assert que uma string contém todas as substrings esperadas
     */
    protected function assertStringContainsAll(array $needles, string $haystack, string $message = ''): void
    {
        foreach ($needles as $needle) {
            $this->assertStringContains($needle, $haystack, $message ?: "String should contain: $needle");
        }
    }
}
