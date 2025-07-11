<?php
/**
 * Servidor WebSocket para sincronização em tempo real
 */

require_once '../config/config.php';

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class DAWSyncServer implements MessageComponentInterface {
    protected $clients;
    protected $sessions;
    
    public function __construct() {
        $this->clients = new \SplObjectStorage;
        $this->sessions = [];
    }
    
    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
        
        echo "Nova conexão! ({$conn->resourceId})\n";
        
        // Enviar mensagem de boas-vindas
        $conn->send(json_encode([
            'type' => 'welcome',
            'clientId' => $conn->resourceId,
            'timestamp' => microtime(true)
        ]));
    }
    
    public function onMessage(ConnectionInterface $from, $msg) {
        try {
            $data = json_decode($msg, true);
            
            if (!$data || !isset($data['type'])) {
                $this->sendError($from, 'Formato de mensagem inválido');
                return;
            }
            
            switch ($data['type']) {
                case 'auth':
                    $this->handleAuth($from, $data);
                    break;
                    
                case 'join_session':
                    $this->handleJoinSession($from, $data);
                    break;
                    
                case 'leave_session':
                    $this->handleLeaveSession($from, $data);
                    break;
                    
                case 'sync_transport':
                    $this->handleSyncTransport($from, $data);
                    break;
                    
                case 'sync_time':
                    $this->handleSyncTime($from, $data);
                    break;
                    
                case 'track_update':
                    $this->handleTrackUpdate($from, $data);
                    break;
                    
                case 'plugin_update':
                    $this->handlePluginUpdate($from, $data);
                    break;
                    
                case 'audio_data':
                    $this->handleAudioData($from, $data);
                    break;
                    
                case 'heartbeat':
                    $this->handleHeartbeat($from, $data);
                    break;
                    
                default:
                    $this->sendError($from, 'Tipo de mensagem desconhecido: ' . $data['type']);
            }
            
        } catch (Exception $e) {
            $this->sendError($from, 'Erro ao processar mensagem: ' . $e->getMessage());
        }
    }
    
    public function onClose(ConnectionInterface $conn) {
        $this->clients->detach($conn);
        
        // Remover da sessão se estava participando
        if (isset($conn->sessionId)) {
            $this->removeFromSession($conn, $conn->sessionId);
        }
        
        echo "Conexão {$conn->resourceId} desconectada\n";
    }
    
    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "Erro: {$e->getMessage()}\n";
        $conn->close();
    }
    
    private function handleAuth($conn, $data) {
        if (!isset($data['token'])) {
            $this->sendError($conn, 'Token não fornecido');
            return;
        }
        
        $userData = \DAWOnline\AuthManager::validateJWT($data['token']);
        
        if (!$userData) {
            $this->sendError($conn, 'Token inválido');
            return;
        }
        
        $conn->userId = $userData['user_id'];
        $conn->username = $userData['username'];
        $conn->authenticated = true;
        
        $conn->send(json_encode([
            'type' => 'auth_success',
            'userId' => $conn->userId,
            'username' => $conn->username
        ]));
    }
    
    private function handleJoinSession($conn, $data) {
        if (!$this->checkAuth($conn)) return;
        
        $sessionId = $data['sessionId'] ?? null;
        $projectId = $data['projectId'] ?? null;
        
        if (!$sessionId || !$projectId) {
            $this->sendError($conn, 'sessionId e projectId são obrigatórios');
            return;
        }
        
        // Criar sessão se não existir
        if (!isset($this->sessions[$sessionId])) {
            $this->sessions[$sessionId] = [
                'id' => $sessionId,
                'projectId' => $projectId,
                'masterId' => $conn->userId,
                'participants' => [],
                'transport' => [
                    'state' => 'stopped',
                    'position' => 0,
                    'bpm' => 120,
                    'metronome' => false
                ],
                'lastUpdate' => microtime(true)
            ];
        }
        
        // Adicionar usuário à sessão
        $this->sessions[$sessionId]['participants'][$conn->resourceId] = [
            'connectionId' => $conn->resourceId,
            'userId' => $conn->userId,
            'username' => $conn->username,
            'latency' => 0,
            'lastHeartbeat' => microtime(true)
        ];
        
        $conn->sessionId = $sessionId;
        
        // Notificar outros participantes
        $this->broadcastToSession($sessionId, [
            'type' => 'user_joined',
            'user' => [
                'userId' => $conn->userId,
                'username' => $conn->username
            ],
            'participants' => array_values($this->sessions[$sessionId]['participants'])
        ], $conn->resourceId);
        
        // Enviar estado atual da sessão para o novo participante
        $conn->send(json_encode([
            'type' => 'session_joined',
            'session' => $this->sessions[$sessionId],
            'isMaster' => $this->sessions[$sessionId]['masterId'] === $conn->userId
        ]));
    }
    
    private function handleLeaveSession($conn, $data) {
        if (!$this->checkAuth($conn)) return;
        
        $sessionId = $conn->sessionId ?? null;
        
        if ($sessionId) {
            $this->removeFromSession($conn, $sessionId);
        }
    }
    
    private function handleSyncTransport($conn, $data) {
        if (!$this->checkAuth($conn)) return;
        
        $sessionId = $conn->sessionId ?? null;
        
        if (!$sessionId || !isset($this->sessions[$sessionId])) {
            $this->sendError($conn, 'Sessão não encontrada');
            return;
        }
        
        // Apenas o master pode controlar o transport
        if ($this->sessions[$sessionId]['masterId'] !== $conn->userId) {
            $this->sendError($conn, 'Apenas o master pode controlar o transport');
            return;
        }
        
        $transport = $data['transport'] ?? [];
        $this->sessions[$sessionId]['transport'] = array_merge(
            $this->sessions[$sessionId]['transport'],
            $transport
        );
        $this->sessions[$sessionId]['lastUpdate'] = microtime(true);
        
        // Broadcast para todos os participantes
        $this->broadcastToSession($sessionId, [
            'type' => 'transport_update',
            'transport' => $this->sessions[$sessionId]['transport'],
            'timestamp' => microtime(true)
        ]);
    }
    
    private function handleSyncTime($conn, $data) {
        if (!$this->checkAuth($conn)) return;
        
        $sessionId = $conn->sessionId ?? null;
        
        if (!$sessionId || !isset($this->sessions[$sessionId])) {
            return;
        }
        
        $clientTime = $data['clientTime'] ?? 0;
        $serverTime = microtime(true);
        
        $conn->send(json_encode([
            'type' => 'time_sync_response',
            'clientTime' => $clientTime,
            'serverTime' => $serverTime,
            'timestamp' => $serverTime
        ]));
    }
    
    private function handleTrackUpdate($conn, $data) {
        if (!$this->checkAuth($conn)) return;
        
        $sessionId = $conn->sessionId ?? null;
        
        if (!$sessionId) return;
        
        // Broadcast update para outros participantes
        $this->broadcastToSession($sessionId, [
            'type' => 'track_update',
            'trackData' => $data['trackData'] ?? [],
            'userId' => $conn->userId,
            'timestamp' => microtime(true)
        ], $conn->resourceId);
    }
    
    private function handlePluginUpdate($conn, $data) {
        if (!$this->checkAuth($conn)) return;
        
        $sessionId = $conn->sessionId ?? null;
        
        if (!$sessionId) return;
        
        // Broadcast update para outros participantes
        $this->broadcastToSession($sessionId, [
            'type' => 'plugin_update',
            'pluginData' => $data['pluginData'] ?? [],
            'userId' => $conn->userId,
            'timestamp' => microtime(true)
        ], $conn->resourceId);
    }
    
    private function handleAudioData($conn, $data) {
        if (!$this->checkAuth($conn)) return;
        
        $sessionId = $conn->sessionId ?? null;
        
        if (!$sessionId) return;
        
        // Broadcast dados de áudio para outros participantes
        // (implementação simplificada - em produção usaria compressão)
        $this->broadcastToSession($sessionId, [
            'type' => 'audio_data',
            'audioBuffer' => $data['audioBuffer'] ?? [],
            'trackId' => $data['trackId'] ?? null,
            'userId' => $conn->userId,
            'timestamp' => microtime(true)
        ], $conn->resourceId);
    }
    
    private function handleHeartbeat($conn, $data) {
        if (!$this->checkAuth($conn)) return;
        
        $sessionId = $conn->sessionId ?? null;
        $clientTime = $data['timestamp'] ?? microtime(true);
        $serverTime = microtime(true);
        $latency = ($serverTime - $clientTime) * 1000; // ms
        
        if ($sessionId && isset($this->sessions[$sessionId]['participants'][$conn->resourceId])) {
            $this->sessions[$sessionId]['participants'][$conn->resourceId]['latency'] = $latency;
            $this->sessions[$sessionId]['participants'][$conn->resourceId]['lastHeartbeat'] = $serverTime;
        }
        
        $conn->send(json_encode([
            'type' => 'heartbeat_response',
            'clientTime' => $clientTime,
            'serverTime' => $serverTime,
            'latency' => $latency
        ]));
    }
    
    private function checkAuth($conn) {
        if (!isset($conn->authenticated) || !$conn->authenticated) {
            $this->sendError($conn, 'Não autenticado');
            return false;
        }
        return true;
    }
    
    private function sendError($conn, $message) {
        $conn->send(json_encode([
            'type' => 'error',
            'message' => $message
        ]));
    }
    
    private function broadcastToSession($sessionId, $message, $excludeId = null) {
        if (!isset($this->sessions[$sessionId])) return;
        
        foreach ($this->sessions[$sessionId]['participants'] as $participant) {
            if ($excludeId && $participant['connectionId'] === $excludeId) {
                continue;
            }
            
            foreach ($this->clients as $client) {
                if ($client->resourceId === $participant['connectionId']) {
                    $client->send(json_encode($message));
                    break;
                }
            }
        }
    }
    
    private function removeFromSession($conn, $sessionId) {
        if (!isset($this->sessions[$sessionId])) return;
        
        unset($this->sessions[$sessionId]['participants'][$conn->resourceId]);
        
        // Se não há mais participantes, remover sessão
        if (empty($this->sessions[$sessionId]['participants'])) {
            unset($this->sessions[$sessionId]);
        } else {
            // Se o master saiu, escolher novo master
            if ($this->sessions[$sessionId]['masterId'] === $conn->userId) {
                $newMaster = reset($this->sessions[$sessionId]['participants']);
                $this->sessions[$sessionId]['masterId'] = $newMaster['userId'];
                
                $this->broadcastToSession($sessionId, [
                    'type' => 'master_changed',
                    'newMasterId' => $newMaster['userId'],
                    'newMasterUsername' => $newMaster['username']
                ]);
            }
            
            // Notificar outros participantes
            $this->broadcastToSession($sessionId, [
                'type' => 'user_left',
                'userId' => $conn->userId,
                'participants' => array_values($this->sessions[$sessionId]['participants'])
            ]);
        }
        
        unset($conn->sessionId);
    }
}

// Iniciar servidor WebSocket
$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new DAWSyncServer()
        )
    ),
    WEBSOCKET_PORT,
    WEBSOCKET_HOST
);

echo "Servidor WebSocket iniciado em " . WEBSOCKET_HOST . ":" . WEBSOCKET_PORT . "\n";
$server->run();
