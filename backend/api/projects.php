<?php
/**
 * API de projetos
 */

require_once '../config/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

use DAWOnline\AuthManager;
use DAWOnline\ProjectManager;

function sendResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data);
    exit;
}

function sendError($message, $statusCode = 400) {
    sendResponse(['error' => $message], $statusCode);
}

function authenticateRequest() {
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    $token = str_replace('Bearer ', '', $authHeader);
    
    if (!$token) {
        sendError('Token não fornecido', 401);
    }
    
    $user = AuthManager::getCurrentUser($token);
    
    if (!$user) {
        sendError('Token inválido', 401);
    }
    
    return $user;
}

try {
    $user = authenticateRequest();
    $input = json_decode(file_get_contents('php://input'), true);
    $projectId = $_GET['id'] ?? null;
    
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            if ($projectId) {
                // Buscar projeto específico
                $project = ProjectManager::getProject($projectId, $user['id']);
                sendResponse([
                    'success' => true,
                    'project' => $project
                ]);
            } else {
                // Listar projetos do usuário
                $limit = intval($_GET['limit'] ?? 20);
                $offset = intval($_GET['offset'] ?? 0);
                
                $projects = ProjectManager::getUserProjects($user['id'], $limit, $offset);
                sendResponse([
                    'success' => true,
                    'projects' => $projects
                ]);
            }
            break;
            
        case 'POST':
            // Criar novo projeto
            if (!isset($input['nome'])) {
                sendError('Nome do projeto é obrigatório');
            }
            
            $project = ProjectManager::createProject(
                $user['id'],
                $input['nome'],
                $input['descricao'] ?? null,
                $input['bpm'] ?? 120,
                $input['compasso'] ?? '4/4',
                $input['tonalidade'] ?? 'C'
            );
            
            sendResponse([
                'success' => true,
                'message' => 'Projeto criado com sucesso',
                'project' => $project
            ], 201);
            break;
            
        case 'PUT':
            // Atualizar projeto
            if (!$projectId) {
                sendError('ID do projeto é obrigatório');
            }
            
            ProjectManager::updateProject($projectId, $user['id'], $input);
            
            sendResponse([
                'success' => true,
                'message' => 'Projeto atualizado com sucesso'
            ]);
            break;
            
        case 'DELETE':
            // Deletar projeto
            if (!$projectId) {
                sendError('ID do projeto é obrigatório');
            }
            
            ProjectManager::deleteProject($projectId, $user['id']);
            
            sendResponse([
                'success' => true,
                'message' => 'Projeto deletado com sucesso'
            ]);
            break;
            
        default:
            sendError('Método não permitido', 405);
    }
    
} catch (Exception $e) {
    sendError($e->getMessage(), 500);
}
