<?php

namespace DAWOnline\Tests\Integration;

use PHPUnit\Framework\TestCase;
use DAWOnline\ProjectManager;
use DAWOnline\Database;
use PDO;

class ProjectIntegrationTest extends TestCase
{
    private static $pdo;
    private $projectManager;
    
    public static function setUpBeforeClass(): void
    {
        // Configurar banco de dados em memória para testes
        self::$pdo = new PDO('sqlite::memory:');
        self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Criar tabelas
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
        
        self::$pdo->exec("
            CREATE TABLE projects (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                name VARCHAR(100) NOT NULL,
                description TEXT,
                project_data TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )
        ");
        
        self::$pdo->exec("
            CREATE TABLE tracks (
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
            )
        ");
        
        self::$pdo->exec("
            CREATE TABLE audio_regions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                track_id INTEGER NOT NULL,
                start_time REAL NOT NULL,
                duration REAL NOT NULL,
                file_path VARCHAR(255),
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (track_id) REFERENCES tracks(id) ON DELETE CASCADE
            )
        ");
    }
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Limpar todas as tabelas
        self::$pdo->exec("DELETE FROM audio_regions");
        self::$pdo->exec("DELETE FROM tracks");
        self::$pdo->exec("DELETE FROM projects");
        self::$pdo->exec("DELETE FROM users");
        
        // Criar instância do ProjectManager com conexão real
        $this->projectManager = new ProjectManager();
        
        // Usar reflexão para injetar a conexão de teste
        $reflection = new \ReflectionClass($this->projectManager);
        $property = $reflection->getProperty('db');
        $property->setAccessible(true);
        
        $mockDatabase = $this->createMock(Database::class);
        $mockDatabase->method('getConnection')->willReturn(self::$pdo);
        $property->setValue($this->projectManager, $mockDatabase);
    }
    
    public function testCompleteProjectWorkflow()
    {
        // 1. Criar usuário
        $userId = $this->createTestUser('testuser', 'test@example.com');
        
        // 2. Criar projeto
        $projectData = $this->projectManager->createProject(
            $userId, 
            'Meu Primeiro Projeto', 
            'Projeto de teste para workflow completo'
        );
        
        $this->assertTrue($projectData['success']);
        $this->assertIsNumeric($projectData['project_id']);
        $projectId = $projectData['project_id'];
        
        // 3. Verificar se projeto foi criado no banco
        $stmt = self::$pdo->prepare("SELECT * FROM projects WHERE id = ?");
        $stmt->execute([$projectId]);
        $project = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $this->assertNotFalse($project);
        $this->assertEquals('Meu Primeiro Projeto', $project['name']);
        $this->assertEquals($userId, $project['user_id']);
        
        // 4. Buscar projeto pelo usuário
        $userProjects = $this->projectManager->getProjectsByUser($userId);
        $this->assertCount(1, $userProjects);
        $this->assertEquals($projectId, $userProjects[0]['id']);
        
        // 5. Atualizar projeto
        $updateResult = $this->projectManager->updateProject(
            $projectId, 
            $userId, 
            'Projeto Atualizado', 
            'Nova descrição'
        );
        
        $this->assertTrue($updateResult['success']);
        
        // 6. Verificar atualização
        $updatedProject = $this->projectManager->getProject($projectId, $userId);
        $this->assertEquals('Projeto Atualizado', $updatedProject['name']);
        $this->assertEquals('Nova descrição', $updatedProject['description']);
        
        // 7. Salvar dados do projeto
        $projectDataToSave = [
            'tracks' => [
                ['id' => 1, 'name' => 'Vocal', 'volume' => 0.8],
                ['id' => 2, 'name' => 'Guitar', 'volume' => 0.6]
            ],
            'tempo' => 120,
            'timeSignature' => '4/4'
        ];
        
        $saveResult = $this->projectManager->saveProjectData($projectId, $userId, $projectDataToSave);
        $this->assertTrue($saveResult['success']);
        
        // 8. Verificar dados salvos
        $projectWithData = $this->projectManager->getProject($projectId, $userId);
        $savedData = json_decode($projectWithData['project_data'], true);
        $this->assertEquals(120, $savedData['tempo']);
        $this->assertCount(2, $savedData['tracks']);
        
        // 9. Deletar projeto
        $deleteResult = $this->projectManager->deleteProject($projectId, $userId);
        $this->assertTrue($deleteResult['success']);
        
        // 10. Verificar que projeto foi deletado
        $deletedProject = $this->projectManager->getProject($projectId, $userId);
        $this->assertFalse($deletedProject);
    }
    
    public function testMultipleUsersProjectIsolation()
    {
        // Criar dois usuários
        $user1Id = $this->createTestUser('user1', 'user1@test.com');
        $user2Id = $this->createTestUser('user2', 'user2@test.com');
        
        // Cada usuário cria projetos
        $project1 = $this->projectManager->createProject($user1Id, 'Projeto User 1A', 'Descrição 1A');
        $project2 = $this->projectManager->createProject($user1Id, 'Projeto User 1B', 'Descrição 1B');
        $project3 = $this->projectManager->createProject($user2Id, 'Projeto User 2A', 'Descrição 2A');
        
        $this->assertTrue($project1['success']);
        $this->assertTrue($project2['success']);
        $this->assertTrue($project3['success']);
        
        // Verificar isolamento - cada usuário só vê seus projetos
        $user1Projects = $this->projectManager->getProjectsByUser($user1Id);
        $user2Projects = $this->projectManager->getProjectsByUser($user2Id);
        
        $this->assertCount(2, $user1Projects);
        $this->assertCount(1, $user2Projects);
        
        // User1 não deve conseguir acessar projeto do User2
        $user1AccessToUser2Project = $this->projectManager->getProject(
            $project3['project_id'], 
            $user1Id
        );
        $this->assertFalse($user1AccessToUser2Project);
        
        // User2 não deve conseguir atualizar projeto do User1
        $unauthorizedUpdate = $this->projectManager->updateProject(
            $project1['project_id'], 
            $user2Id, 
            'Tentativa de Hack', 
            'Não deveria funcionar'
        );
        $this->assertFalse($unauthorizedUpdate['success']);
        
        // User2 não deve conseguir deletar projeto do User1
        $unauthorizedDelete = $this->projectManager->deleteProject(
            $project1['project_id'], 
            $user2Id
        );
        $this->assertFalse($unauthorizedDelete['success']);
    }
    
    public function testProjectWithComplexData()
    {
        $userId = $this->createTestUser('complexuser', 'complex@test.com');
        
        // Criar projeto
        $project = $this->projectManager->createProject(
            $userId, 
            'Projeto Complexo', 
            'Projeto com dados complexos'
        );
        $projectId = $project['project_id'];
        
        // Dados complexos do projeto
        $complexData = [
            'version' => '1.0',
            'tempo' => 128,
            'timeSignature' => '7/8',
            'sampleRate' => 48000,
            'bufferSize' => 512,
            'tracks' => [
                [
                    'id' => 1,
                    'name' => 'Drums',
                    'type' => 'audio',
                    'volume' => 0.85,
                    'pan' => 0.0,
                    'muted' => false,
                    'solo' => false,
                    'effects' => [
                        ['type' => 'eq', 'settings' => ['low' => 0, 'mid' => 2, 'high' => -1]],
                        ['type' => 'compressor', 'settings' => ['ratio' => 4, 'threshold' => -20]]
                    ],
                    'regions' => [
                        ['start' => 0.0, 'duration' => 32.5, 'file' => 'drums.wav'],
                        ['start' => 64.0, 'duration' => 16.25, 'file' => 'fill.wav']
                    ]
                ],
                [
                    'id' => 2,
                    'name' => 'Bass',
                    'type' => 'midi',
                    'volume' => 0.7,
                    'pan' => -0.2,
                    'muted' => false,
                    'solo' => true,
                    'instrument' => [
                        'type' => 'synth',
                        'preset' => 'bass_001',
                        'parameters' => [
                            'cutoff' => 800,
                            'resonance' => 0.3,
                            'attack' => 0.01,
                            'decay' => 0.5,
                            'sustain' => 0.7,
                            'release' => 0.8
                        ]
                    ],
                    'regions' => [
                        [
                            'start' => 0.0, 
                            'duration' => 64.0, 
                            'notes' => [
                                ['time' => 0.0, 'note' => 36, 'velocity' => 127, 'duration' => 0.5],
                                ['time' => 1.0, 'note' => 38, 'velocity' => 100, 'duration' => 0.5],
                                ['time' => 2.0, 'note' => 36, 'velocity' => 110, 'duration' => 1.0]
                            ]
                        ]
                    ]
                ]
            ],
            'masterEffects' => [
                ['type' => 'limiter', 'settings' => ['threshold' => -1, 'release' => 0.05]],
                ['type' => 'reverb', 'settings' => ['room' => 0.3, 'damping' => 0.5, 'wet' => 0.2]]
            ],
            'markers' => [
                ['time' => 0.0, 'label' => 'Intro'],
                ['time' => 16.0, 'label' => 'Verse 1'],
                ['time' => 32.0, 'label' => 'Chorus'],
                ['time' => 48.0, 'label' => 'Verse 2']
            ]
        ];
        
        // Salvar dados complexos
        $saveResult = $this->projectManager->saveProjectData($projectId, $userId, $complexData);
        $this->assertTrue($saveResult['success']);
        
        // Recuperar e verificar dados
        $savedProject = $this->projectManager->getProject($projectId, $userId);
        $retrievedData = json_decode($savedProject['project_data'], true);
        
        $this->assertEquals($complexData['version'], $retrievedData['version']);
        $this->assertEquals($complexData['tempo'], $retrievedData['tempo']);
        $this->assertEquals($complexData['timeSignature'], $retrievedData['timeSignature']);
        $this->assertCount(2, $retrievedData['tracks']);
        $this->assertCount(2, $retrievedData['masterEffects']);
        $this->assertCount(4, $retrievedData['markers']);
        
        // Verificar estrutura de track complexa
        $drumsTrack = $retrievedData['tracks'][0];
        $this->assertEquals('Drums', $drumsTrack['name']);
        $this->assertCount(2, $drumsTrack['effects']);
        $this->assertCount(2, $drumsTrack['regions']);
        
        $bassTrack = $retrievedData['tracks'][1];
        $this->assertEquals('Bass', $bassTrack['name']);
        $this->assertTrue($bassTrack['solo']);
        $this->assertArrayHasKey('instrument', $bassTrack);
        $this->assertCount(3, $bassTrack['regions'][0]['notes']);
    }
    
    public function testProjectDataIntegrity()
    {
        $userId = $this->createTestUser('integrityuser', 'integrity@test.com');
        
        // Criar projeto
        $project = $this->projectManager->createProject($userId, 'Projeto Integridade', 'Teste de integridade');
        $projectId = $project['project_id'];
        
        // Dados originais
        $originalData = ['tempo' => 120, 'tracks' => [['name' => 'Track 1']]];
        $this->projectManager->saveProjectData($projectId, $userId, $originalData);
        
        // Múltiplas atualizações
        for ($i = 1; $i <= 5; $i++) {
            $data = [
                'tempo' => 120 + ($i * 10),
                'iteration' => $i,
                'tracks' => array_fill(0, $i, ['name' => "Track $i"])
            ];
            
            $result = $this->projectManager->saveProjectData($projectId, $userId, $data);
            $this->assertTrue($result['success']);
            
            // Verificar imediatamente
            $saved = $this->projectManager->getProject($projectId, $userId);
            $savedData = json_decode($saved['project_data'], true);
            $this->assertEquals($data['tempo'], $savedData['tempo']);
            $this->assertEquals($i, $savedData['iteration']);
            $this->assertCount($i, $savedData['tracks']);
        }
        
        // Verificar estado final
        $finalProject = $this->projectManager->getProject($projectId, $userId);
        $finalData = json_decode($finalProject['project_data'], true);
        $this->assertEquals(170, $finalData['tempo']); // 120 + (5 * 10)
        $this->assertEquals(5, $finalData['iteration']);
        $this->assertCount(5, $finalData['tracks']);
    }
    
    private function createTestUser(string $username, string $email): int
    {
        $stmt = self::$pdo->prepare("
            INSERT INTO users (username, email, password_hash) 
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$username, $email, password_hash('password123', PASSWORD_BCRYPT)]);
        
        return self::$pdo->lastInsertId();
    }
}
