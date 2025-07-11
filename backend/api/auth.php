<?php
/**
 * API de autenticação
 */

require_once '../config/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

use DAWOnline\AuthManager;

function sendResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data);
    exit;
}

function sendError($message, $statusCode = 400) {
    sendResponse(['error' => $message], $statusCode);
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $_GET['action'] ?? '';
    
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'POST':
            switch ($action) {
                case 'login':
                    if (!isset($input['username']) || !isset($input['password'])) {
                        sendError('Username e password são obrigatórios');
                    }
                    
                    $result = AuthManager::authenticateUser($input['username'], $input['password']);
                    
                    if ($result) {
                        sendResponse([
                            'success' => true,
                            'message' => 'Login realizado com sucesso',
                            'user' => $result
                        ]);
                    } else {
                        sendError('Credenciais inválidas', 401);
                    }
                    break;
                    
                case 'register':
                    if (!isset($input['username']) || !isset($input['email']) || !isset($input['password'])) {
                        sendError('Username, email e password são obrigatórios');
                    }
                    
                    if (strlen($input['password']) < 6) {
                        sendError('Password deve ter pelo menos 6 caracteres');
                    }
                    
                    if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
                        sendError('Email inválido');
                    }
                    
                    $result = AuthManager::registerUser(
                        $input['username'],
                        $input['email'],
                        $input['password'],
                        $input['nome_completo'] ?? null
                    );
                    
                    sendResponse([
                        'success' => true,
                        'message' => 'Usuário criado com sucesso',
                        'user' => $result
                    ]);
                    break;
                    
                case 'refresh':
                    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
                    $token = str_replace('Bearer ', '', $authHeader);
                    
                    if (!$token) {
                        sendError('Token não fornecido', 401);
                    }
                    
                    $newToken = AuthManager::refreshToken($token);
                    
                    if ($newToken) {
                        sendResponse([
                            'success' => true,
                            'token' => $newToken
                        ]);
                    } else {
                        sendError('Token inválido', 401);
                    }
                    break;
                    
                case 'me':
                    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
                    $token = str_replace('Bearer ', '', $authHeader);
                    
                    if (!$token) {
                        sendError('Token não fornecido', 401);
                    }
                    
                    $user = AuthManager::getCurrentUser($token);
                    
                    if ($user) {
                        sendResponse([
                            'success' => true,
                            'user' => $user
                        ]);
                    } else {
                        sendError('Token inválido', 401);
                    }
                    break;
                    
                default:
                    sendError('Ação não encontrada', 404);
            }
            break;
            
        default:
            sendError('Método não permitido', 405);
    }
    
} catch (Exception $e) {
    sendError($e->getMessage(), 500);
}
