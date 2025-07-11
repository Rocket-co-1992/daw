<?php
/**
 * Gerenciador de projetos
 */

namespace DAWOnline;

class ProjectManager {
    
    public static function createProject($userId, $nome, $descricao = null, $bpm = 120, $compasso = '4/4', $tonalidade = 'C') {
        $db = Database::getInstance();
        
        try {
            $db->beginTransaction();
            
            $configuracao = [
                'bpm' => $bpm,
                'compasso' => $compasso,
                'tonalidade' => $tonalidade,
                'volume_master' => 100,
                'metronomo' => false,
                'click_track' => false
            ];
            
            $sql = "INSERT INTO projetos (nome, descricao, criador_id, bpm, compasso, tonalidade, configuracao_json) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([
                $nome, 
                $descricao, 
                $userId, 
                $bpm, 
                $compasso, 
                $tonalidade, 
                json_encode($configuracao)
            ]);
            
            $projectId = $db->lastInsertId();
            
            // Adicionar o criador como proprietário na tabela de colaboradores
            $collabSql = "INSERT INTO projeto_colaboradores (projeto_id, usuario_id, papel, status) 
                          VALUES (?, ?, 'proprietario', 'aceito')";
            $collabStmt = $db->prepare($collabSql);
            $collabStmt->execute([$projectId, $userId]);
            
            // Criar faixas padrão
            self::createDefaultTracks($projectId);
            
            $db->commit();
            
            return self::getProject($projectId, $userId);
            
        } catch (\Exception $e) {
            $db->rollback();
            throw $e;
        }
    }
    
    private static function createDefaultTracks($projectId) {
        $db = Database::getInstance();
        
        $defaultTracks = [
            ['nome' => 'Track 1', 'tipo' => 'audio', 'posicao' => 1, 'cor' => '#e74c3c'],
            ['nome' => 'Track 2', 'tipo' => 'audio', 'posicao' => 2, 'cor' => '#3498db'],
            ['nome' => 'Track 3', 'tipo' => 'midi', 'posicao' => 3, 'cor' => '#2ecc71'],
            ['nome' => 'Track 4', 'tipo' => 'midi', 'posicao' => 4, 'cor' => '#f39c12']
        ];
        
        $sql = "INSERT INTO faixas (projeto_id, nome, tipo, posicao, cor, configuracao_json) 
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $db->prepare($sql);
        
        foreach ($defaultTracks as $track) {
            $configuracao = [
                'volume' => 100,
                'pan' => 0,
                'mute' => false,
                'solo' => false,
                'entrada' => 'Stereo In',
                'saida' => 'Master'
            ];
            
            $stmt->execute([
                $projectId,
                $track['nome'],
                $track['tipo'],
                $track['posicao'],
                $track['cor'],
                json_encode($configuracao)
            ]);
        }
    }
    
    public static function getProject($projectId, $userId) {
        $db = Database::getInstance();
        
        // Verificar se o usuário tem acesso ao projeto
        $accessSql = "SELECT pc.papel, p.*
                      FROM projetos p
                      LEFT JOIN projeto_colaboradores pc ON p.id = pc.projeto_id AND pc.usuario_id = ?
                      WHERE p.id = ? AND (p.status = 'publico' OR pc.status = 'aceito')";
        
        $accessStmt = $db->prepare($accessSql);
        $accessStmt->execute([$userId, $projectId]);
        $project = $accessStmt->fetch();
        
        if (!$project) {
            throw new \Exception("Projeto não encontrado ou acesso negado");
        }
        
        // Buscar faixas do projeto
        $tracksSql = "SELECT * FROM faixas WHERE projeto_id = ? ORDER BY posicao";
        $tracksStmt = $db->prepare($tracksSql);
        $tracksStmt->execute([$projectId]);
        $tracks = $tracksStmt->fetchAll();
        
        // Buscar regiões de cada faixa
        foreach ($tracks as &$track) {
            $regionsSql = "SELECT * FROM regioes WHERE faixa_id = ? ORDER BY inicio_tempo";
            $regionsStmt = $db->prepare($regionsSql);
            $regionsStmt->execute([$track['id']]);
            $track['regioes'] = $regionsStmt->fetchAll();
            
            // Buscar plugins da faixa
            $pluginsSql = "SELECT fp.*, p.nome as plugin_nome, p.fabricante, p.tipo 
                           FROM faixa_plugins fp 
                           JOIN plugins p ON fp.plugin_id = p.id 
                           WHERE fp.faixa_id = ? 
                           ORDER BY fp.posicao";
            $pluginsStmt = $db->prepare($pluginsSql);
            $pluginsStmt->execute([$track['id']]);
            $track['plugins'] = $pluginsStmt->fetchAll();
        }
        
        // Buscar colaboradores
        $collabSql = "SELECT pc.papel, u.username, u.nome_completo, u.avatar, u.status 
                      FROM projeto_colaboradores pc 
                      JOIN usuarios u ON pc.usuario_id = u.id 
                      WHERE pc.projeto_id = ? AND pc.status = 'aceito'";
        $collabStmt = $db->prepare($collabSql);
        $collabStmt->execute([$projectId]);
        $collaborators = $collabStmt->fetchAll();
        
        $project['faixas'] = $tracks;
        $project['colaboradores'] = $collaborators;
        $project['configuracao_json'] = json_decode($project['configuracao_json'], true);
        
        return $project;
    }
    
    public static function getUserProjects($userId, $limit = 20, $offset = 0) {
        $db = Database::getInstance();
        
        $sql = "SELECT p.*, pc.papel,
                       (SELECT COUNT(*) FROM faixas WHERE projeto_id = p.id) as total_faixas,
                       (SELECT COUNT(*) FROM projeto_colaboradores WHERE projeto_id = p.id AND status = 'aceito') as total_colaboradores
                FROM projetos p
                LEFT JOIN projeto_colaboradores pc ON p.id = pc.projeto_id AND pc.usuario_id = ?
                WHERE (p.status = 'publico' OR pc.status = 'aceito')
                ORDER BY p.data_modificacao DESC
                LIMIT ? OFFSET ?";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([$userId, $limit, $offset]);
        
        return $stmt->fetchAll();
    }
    
    public static function updateProject($projectId, $userId, $dados) {
        $db = Database::getInstance();
        
        // Verificar permissões
        if (!self::hasProjectPermission($projectId, $userId, ['proprietario', 'colaborador'])) {
            throw new \Exception("Sem permissão para editar este projeto");
        }
        
        $allowedFields = ['nome', 'descricao', 'bpm', 'compasso', 'tonalidade', 'status', 'configuracao_json'];
        $updateFields = [];
        $values = [];
        
        foreach ($dados as $field => $value) {
            if (in_array($field, $allowedFields)) {
                $updateFields[] = "$field = ?";
                $values[] = $field === 'configuracao_json' ? json_encode($value) : $value;
            }
        }
        
        if (empty($updateFields)) {
            throw new \Exception("Nenhum campo válido para atualizar");
        }
        
        $values[] = $projectId;
        
        $sql = "UPDATE projetos SET " . implode(', ', $updateFields) . ", data_modificacao = CURRENT_TIMESTAMP WHERE id = ?";
        $stmt = $db->prepare($sql);
        
        return $stmt->execute($values);
    }
    
    public static function deleteProject($projectId, $userId) {
        $db = Database::getInstance();
        
        // Apenas o proprietário pode deletar
        if (!self::hasProjectPermission($projectId, $userId, ['proprietario'])) {
            throw new \Exception("Sem permissão para deletar este projeto");
        }
        
        $sql = "DELETE FROM projetos WHERE id = ?";
        $stmt = $db->prepare($sql);
        
        return $stmt->execute([$projectId]);
    }
    
    public static function addCollaborator($projectId, $userId, $colaboradorUsername, $papel = 'colaborador') {
        $db = Database::getInstance();
        
        if (!self::hasProjectPermission($projectId, $userId, ['proprietario'])) {
            throw new \Exception("Sem permissão para adicionar colaboradores");
        }
        
        // Buscar ID do colaborador
        $userSql = "SELECT id FROM usuarios WHERE username = ? AND status = 'ativo'";
        $userStmt = $db->prepare($userSql);
        $userStmt->execute([$colaboradorUsername]);
        $collaborator = $userStmt->fetch();
        
        if (!$collaborator) {
            throw new \Exception("Usuário não encontrado");
        }
        
        $sql = "INSERT INTO projeto_colaboradores (projeto_id, usuario_id, papel, status) 
                VALUES (?, ?, ?, 'pendente')
                ON DUPLICATE KEY UPDATE papel = VALUES(papel), status = 'pendente'";
        
        $stmt = $db->prepare($sql);
        
        return $stmt->execute([$projectId, $collaborator['id'], $papel]);
    }
    
    private static function hasProjectPermission($projectId, $userId, $allowedRoles) {
        $db = Database::getInstance();
        
        $sql = "SELECT papel FROM projeto_colaboradores 
                WHERE projeto_id = ? AND usuario_id = ? AND status = 'aceito'";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([$projectId, $userId]);
        $role = $stmt->fetchColumn();
        
        return $role && in_array($role, $allowedRoles);
    }
}
