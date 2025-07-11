<?php

namespace DAWOnline\Tests\Unit;

use PHPUnit\Framework\TestCase;
use DAWOnline\ProjectManager;
use DAWOnline\Database;
use PDO;
use PDOStatement;

class ProjectManagerTest extends TestCase
{
    private $mockPdo;
    private $mockStatement;
    private $projectManager;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Criar mocks para PDO e PDOStatement
        $this->mockPdo = $this->createMock(PDO::class);
        $this->mockStatement = $this->createMock(PDOStatement::class);
        
        // Mock do Database para retornar nossa conexão mock
        $databaseMock = $this->createMock(Database::class);
        $databaseMock->method('getConnection')->willReturn($this->mockPdo);
        
        $this->projectManager = new ProjectManager();
        
        // Injetar mock do database usando reflexão
        $reflection = new \ReflectionClass($this->projectManager);
        $property = $reflection->getProperty('db');
        $property->setAccessible(true);
        $property->setValue($this->projectManager, $databaseMock);
    }
    
    public function testCreateProject()
    {
        $userId = 1;
        $projectName = 'Meu Projeto';
        $description = 'Descrição do projeto';
        
        // Configurar expectativas do mock
        $this->mockPdo->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('INSERT INTO projects'))
            ->willReturn($this->mockStatement);
            
        $this->mockStatement->expects($this->once())
            ->method('execute')
            ->with($this->arrayHasKey('name'))
            ->willReturn(true);
            
        $this->mockPdo->expects($this->once())
            ->method('lastInsertId')
            ->willReturn('123');
        
        $result = $this->projectManager->createProject($userId, $projectName, $description);
        
        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        $this->assertEquals(123, $result['project_id']);
    }
    
    public function testCreateProjectFailure()
    {
        $userId = 1;
        $projectName = 'Projeto Falhou';
        $description = 'Descrição';
        
        // Simular falha na execução
        $this->mockPdo->expects($this->once())
            ->method('prepare')
            ->willReturn($this->mockStatement);
            
        $this->mockStatement->expects($this->once())
            ->method('execute')
            ->willReturn(false);
        
        $result = $this->projectManager->createProject($userId, $projectName, $description);
        
        $this->assertIsArray($result);
        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
    }
    
    public function testGetProjectsByUser()
    {
        $userId = 1;
        $expectedProjects = [
            [
                'id' => 1,
                'name' => 'Projeto 1',
                'description' => 'Desc 1',
                'created_at' => '2024-01-01 12:00:00',
                'updated_at' => '2024-01-01 12:00:00'
            ],
            [
                'id' => 2,
                'name' => 'Projeto 2',
                'description' => 'Desc 2',
                'created_at' => '2024-01-02 12:00:00',
                'updated_at' => '2024-01-02 12:00:00'
            ]
        ];
        
        $this->mockPdo->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('SELECT * FROM projects WHERE user_id = ?'))
            ->willReturn($this->mockStatement);
            
        $this->mockStatement->expects($this->once())
            ->method('execute')
            ->with([$userId])
            ->willReturn(true);
            
        $this->mockStatement->expects($this->once())
            ->method('fetchAll')
            ->with(PDO::FETCH_ASSOC)
            ->willReturn($expectedProjects);
        
        $result = $this->projectManager->getProjectsByUser($userId);
        
        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertEquals($expectedProjects, $result);
    }
    
    public function testGetProjectById()
    {
        $projectId = 1;
        $userId = 1;
        $expectedProject = [
            'id' => 1,
            'user_id' => 1,
            'name' => 'Projeto Test',
            'description' => 'Descrição test',
            'created_at' => '2024-01-01 12:00:00',
            'updated_at' => '2024-01-01 12:00:00'
        ];
        
        $this->mockPdo->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('SELECT * FROM projects WHERE id = ? AND user_id = ?'))
            ->willReturn($this->mockStatement);
            
        $this->mockStatement->expects($this->once())
            ->method('execute')
            ->with([$projectId, $userId])
            ->willReturn(true);
            
        $this->mockStatement->expects($this->once())
            ->method('fetch')
            ->with(PDO::FETCH_ASSOC)
            ->willReturn($expectedProject);
        
        $result = $this->projectManager->getProject($projectId, $userId);
        
        $this->assertIsArray($result);
        $this->assertEquals($expectedProject, $result);
    }
    
    public function testGetProjectNotFound()
    {
        $projectId = 999;
        $userId = 1;
        
        $this->mockPdo->expects($this->once())
            ->method('prepare')
            ->willReturn($this->mockStatement);
            
        $this->mockStatement->expects($this->once())
            ->method('execute')
            ->willReturn(true);
            
        $this->mockStatement->expects($this->once())
            ->method('fetch')
            ->willReturn(false);
        
        $result = $this->projectManager->getProject($projectId, $userId);
        
        $this->assertFalse($result);
    }
    
    public function testUpdateProject()
    {
        $projectId = 1;
        $userId = 1;
        $newName = 'Projeto Atualizado';
        $newDescription = 'Nova descrição';
        
        $this->mockPdo->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('UPDATE projects SET'))
            ->willReturn($this->mockStatement);
            
        $this->mockStatement->expects($this->once())
            ->method('execute')
            ->willReturn(true);
            
        $this->mockStatement->expects($this->once())
            ->method('rowCount')
            ->willReturn(1);
        
        $result = $this->projectManager->updateProject($projectId, $userId, $newName, $newDescription);
        
        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
    }
    
    public function testUpdateProjectNotFound()
    {
        $projectId = 999;
        $userId = 1;
        $newName = 'Projeto Inexistente';
        $newDescription = 'Descrição';
        
        $this->mockPdo->expects($this->once())
            ->method('prepare')
            ->willReturn($this->mockStatement);
            
        $this->mockStatement->expects($this->once())
            ->method('execute')
            ->willReturn(true);
            
        $this->mockStatement->expects($this->once())
            ->method('rowCount')
            ->willReturn(0);
        
        $result = $this->projectManager->updateProject($projectId, $userId, $newName, $newDescription);
        
        $this->assertIsArray($result);
        $this->assertFalse($result['success']);
        $this->assertStringContains('não encontrado', $result['error']);
    }
    
    public function testDeleteProject()
    {
        $projectId = 1;
        $userId = 1;
        
        $this->mockPdo->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('DELETE FROM projects WHERE id = ? AND user_id = ?'))
            ->willReturn($this->mockStatement);
            
        $this->mockStatement->expects($this->once())
            ->method('execute')
            ->with([$projectId, $userId])
            ->willReturn(true);
            
        $this->mockStatement->expects($this->once())
            ->method('rowCount')
            ->willReturn(1);
        
        $result = $this->projectManager->deleteProject($projectId, $userId);
        
        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
    }
    
    public function testDeleteProjectNotFound()
    {
        $projectId = 999;
        $userId = 1;
        
        $this->mockPdo->expects($this->once())
            ->method('prepare')
            ->willReturn($this->mockStatement);
            
        $this->mockStatement->expects($this->once())
            ->method('execute')
            ->willReturn(true);
            
        $this->mockStatement->expects($this->once())
            ->method('rowCount')
            ->willReturn(0);
        
        $result = $this->projectManager->deleteProject($projectId, $userId);
        
        $this->assertIsArray($result);
        $this->assertFalse($result['success']);
        $this->assertStringContains('não encontrado', $result['error']);
    }
    
    public function testSaveProjectData()
    {
        $projectId = 1;
        $userId = 1;
        $projectData = [
            'tracks' => [
                ['id' => 1, 'name' => 'Track 1', 'volume' => 0.8],
                ['id' => 2, 'name' => 'Track 2', 'volume' => 0.6]
            ],
            'tempo' => 120,
            'timeSignature' => '4/4'
        ];
        
        $this->mockPdo->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('UPDATE projects SET project_data = ?, updated_at = NOW()'))
            ->willReturn($this->mockStatement);
            
        $this->mockStatement->expects($this->once())
            ->method('execute')
            ->with([json_encode($projectData), $projectId, $userId])
            ->willReturn(true);
            
        $this->mockStatement->expects($this->once())
            ->method('rowCount')
            ->willReturn(1);
        
        $result = $this->projectManager->saveProjectData($projectId, $userId, $projectData);
        
        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
    }
    
    public function testGetProjectWithInvalidUserId()
    {
        $projectId = 1;
        $userId = 999; // usuário que não tem acesso ao projeto
        
        $this->mockPdo->expects($this->once())
            ->method('prepare')
            ->willReturn($this->mockStatement);
            
        $this->mockStatement->expects($this->once())
            ->method('execute')
            ->with([$projectId, $userId])
            ->willReturn(true);
            
        $this->mockStatement->expects($this->once())
            ->method('fetch')
            ->willReturn(false);
        
        $result = $this->projectManager->getProject($projectId, $userId);
        
        $this->assertFalse($result);
    }
}
