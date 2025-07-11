<?php

namespace DAWOnline\Tests\Feature;

use PHPUnit\Framework\TestCase;
use Ratchet\Client\WebSocket;
use Ratchet\Client\Connector;
use React\EventLoop\Loop;
use React\Socket\Connector as SocketConnector;
use React\Promise\Promise;

class WebSocketFunctionalityTest extends TestCase
{
    private $loop;
    private $connector;
    private $wsUrl = 'ws://localhost:8080';
    private $authToken;
    private $testUserId = 1;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->loop = Loop::get();
        $this->connector = new Connector($this->loop);
        
        // Gerar token de teste
        $this->authToken = $this->generateTestToken($this->testUserId, 'wstest_user');
    }
    
    private function generateTestToken(int $userId, string $username): string
    {
        if (!defined('JWT_SECRET')) {
            define('JWT_SECRET', 'test_secret_websocket_key');
        }
        
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
    
    public function testWebSocketConnection()
    {
        $connected = false;
        $authenticationSuccess = false;
        
        $promise = $this->connector($this->wsUrl)
            ->then(function (WebSocket $conn) use (&$connected, &$authenticationSuccess) {
                $connected = true;
                
                // Enviar autenticação
                $authMessage = json_encode([
                    'type' => 'auth',
                    'token' => $this->authToken
                ]);
                
                $conn->send($authMessage);
                
                // Aguardar resposta de autenticação
                $conn->on('message', function ($msg) use (&$authenticationSuccess, $conn) {
                    $data = json_decode($msg->getPayload(), true);
                    
                    if ($data['type'] === 'auth_response' && $data['success'] === true) {
                        $authenticationSuccess = true;
                        $conn->close();
                    }
                });
                
                // Timeout de 3 segundos
                $this->loop->addTimer(3, function () use ($conn) {
                    $conn->close();
                });
                
            }, function (\Exception $e) {
                $this->fail('Falha na conexão WebSocket: ' . $e->getMessage());
            });
        
        // Executar loop por tempo limitado
        $this->loop->addTimer(5, function () {
            $this->loop->stop();
        });
        
        $this->loop->run();
        
        $this->assertTrue($connected, 'WebSocket deveria conseguir conectar');
        $this->assertTrue($authenticationSuccess, 'Autenticação WebSocket deveria ser bem-sucedida');
    }
    
    public function testTransportControls()
    {
        $messagesReceived = [];
        $expectedEvents = ['play', 'pause', 'stop', 'seek'];
        
        $this->connector($this->wsUrl)
            ->then(function (WebSocket $conn) use (&$messagesReceived, $expectedEvents) {
                
                // Autenticar primeiro
                $conn->send(json_encode([
                    'type' => 'auth',
                    'token' => $this->authToken
                ]));
                
                $conn->on('message', function ($msg) use (&$messagesReceived, $conn, $expectedEvents) {
                    $data = json_decode($msg->getPayload(), true);
                    
                    if ($data['type'] === 'auth_response' && $data['success'] === true) {
                        // Entrar em sala de projeto
                        $conn->send(json_encode([
                            'type' => 'join_project',
                            'project_id' => 1
                        ]));
                        
                        // Enviar comandos de transport
                        foreach ($expectedEvents as $event) {
                            $conn->send(json_encode([
                                'type' => 'transport_control',
                                'action' => $event,
                                'project_id' => 1,
                                'position' => $event === 'seek' ? 30.5 : null
                            ]));
                        }
                        
                    } elseif ($data['type'] === 'transport_update') {
                        $messagesReceived[] = $data;
                        
                        // Fechar quando receber todos os eventos esperados
                        if (count($messagesReceived) >= count($expectedEvents)) {
                            $conn->close();
                        }
                    }
                });
                
                $this->loop->addTimer(5, function () use ($conn) {
                    $conn->close();
                });
                
            });
        
        $this->loop->addTimer(7, function () {
            $this->loop->stop();
        });
        
        $this->loop->run();
        
        $this->assertCount(count($expectedEvents), $messagesReceived);
        
        foreach ($expectedEvents as $index => $expectedAction) {
            $this->assertEquals('transport_update', $messagesReceived[$index]['type']);
            $this->assertEquals($expectedAction, $messagesReceived[$index]['action']);
            
            if ($expectedAction === 'seek') {
                $this->assertEquals(30.5, $messagesReceived[$index]['position']);
            }
        }
    }
    
    public function testRealTimeCollaboration()
    {
        $user1Messages = [];
        $user2Messages = [];
        $collaborationEvents = [];
        
        // Simular dois usuários conectando simultaneamente
        $user1Token = $this->generateTestToken(1, 'user1');
        $user2Token = $this->generateTestToken(2, 'user2');
        
        // Conexão do usuário 1
        $user1Promise = $this->connector($this->wsUrl)
            ->then(function (WebSocket $conn) use ($user1Token, &$user1Messages, &$collaborationEvents) {
                
                $conn->send(json_encode([
                    'type' => 'auth',
                    'token' => $user1Token
                ]));
                
                $conn->on('message', function ($msg) use (&$user1Messages, &$collaborationEvents, $conn) {
                    $data = json_decode($msg->getPayload(), true);
                    $user1Messages[] = $data;
                    
                    if ($data['type'] === 'auth_response' && $data['success'] === true) {
                        // Entrar no projeto
                        $conn->send(json_encode([
                            'type' => 'join_project',
                            'project_id' => 1
                        ]));
                        
                        // Fazer uma mudança na track
                        $conn->send(json_encode([
                            'type' => 'track_update',
                            'project_id' => 1,
                            'track_id' => 1,
                            'property' => 'volume',
                            'value' => 0.8
                        ]));
                        
                    } elseif ($data['type'] === 'collaboration_event') {
                        $collaborationEvents[] = $data;
                    }
                });
                
                return $conn;
            });
        
        // Conexão do usuário 2 (após delay)
        $this->loop->addTimer(1, function () use ($user2Token, &$user2Messages, &$collaborationEvents) {
            $this->connector($this->wsUrl)
                ->then(function (WebSocket $conn) use ($user2Token, &$user2Messages, &$collaborationEvents) {
                    
                    $conn->send(json_encode([
                        'type' => 'auth',
                        'token' => $user2Token
                    ]));
                    
                    $conn->on('message', function ($msg) use (&$user2Messages, &$collaborationEvents, $conn) {
                        $data = json_decode($msg->getPayload(), true);
                        $user2Messages[] = $data;
                        
                        if ($data['type'] === 'auth_response' && $data['success'] === true) {
                            // Entrar no mesmo projeto
                            $conn->send(json_encode([
                                'type' => 'join_project',
                                'project_id' => 1
                            ]));
                            
                        } elseif ($data['type'] === 'track_changed') {
                            // User2 deve receber mudanças do User1
                            $collaborationEvents[] = $data;
                            $conn->close();
                        }
                    });
                    
                    return $conn;
                });
        });
        
        // Timeout geral
        $this->loop->addTimer(8, function () {
            $this->loop->stop();
        });
        
        $this->loop->run();
        
        // Verificar que ambos usuários se conectaram
        $this->assertNotEmpty($user1Messages);
        $this->assertNotEmpty($user2Messages);
        
        // Verificar eventos de colaboração
        $this->assertNotEmpty($collaborationEvents);
        
        // User2 deve receber atualização da track do User1
        $trackUpdateReceived = false;
        foreach ($collaborationEvents as $event) {
            if ($event['type'] === 'track_changed' && 
                $event['track_id'] == 1 && 
                $event['property'] === 'volume' && 
                $event['value'] == 0.8) {
                $trackUpdateReceived = true;
                break;
            }
        }
        
        $this->assertTrue($trackUpdateReceived, 'User2 deveria receber atualização da track do User1');
    }
    
    public function testRecordingSession()
    {
        $recordingEvents = [];
        $recordingStarted = false;
        $recordingStopped = false;
        
        $this->connector($this->wsUrl)
            ->then(function (WebSocket $conn) use (&$recordingEvents, &$recordingStarted, &$recordingStopped) {
                
                $conn->send(json_encode([
                    'type' => 'auth',
                    'token' => $this->authToken
                ]));
                
                $conn->on('message', function ($msg) use (&$recordingEvents, &$recordingStarted, &$recordingStopped, $conn) {
                    $data = json_decode($msg->getPayload(), true);
                    
                    if ($data['type'] === 'auth_response' && $data['success'] === true) {
                        // Entrar no projeto
                        $conn->send(json_encode([
                            'type' => 'join_project',
                            'project_id' => 1
                        ]));
                        
                        // Iniciar gravação
                        $conn->send(json_encode([
                            'type' => 'recording_control',
                            'action' => 'start',
                            'project_id' => 1,
                            'track_id' => 1
                        ]));
                        
                    } elseif ($data['type'] === 'recording_started') {
                        $recordingStarted = true;
                        $recordingEvents[] = $data;
                        
                        // Simular dados de áudio
                        $conn->send(json_encode([
                            'type' => 'audio_data',
                            'project_id' => 1,
                            'track_id' => 1,
                            'timestamp' => microtime(true),
                            'data' => base64_encode('fake_audio_data_chunk')
                        ]));
                        
                        // Parar gravação após um momento
                        $this->loop->addTimer(2, function () use ($conn) {
                            $conn->send(json_encode([
                                'type' => 'recording_control',
                                'action' => 'stop',
                                'project_id' => 1,
                                'track_id' => 1
                            ]));
                        });
                        
                    } elseif ($data['type'] === 'recording_stopped') {
                        $recordingStopped = true;
                        $recordingEvents[] = $data;
                        $conn->close();
                        
                    } elseif ($data['type'] === 'audio_processed') {
                        $recordingEvents[] = $data;
                    }
                });
                
                $this->loop->addTimer(6, function () use ($conn) {
                    $conn->close();
                });
                
            });
        
        $this->loop->addTimer(8, function () {
            $this->loop->stop();
        });
        
        $this->loop->run();
        
        $this->assertTrue($recordingStarted, 'Gravação deveria ter iniciado');
        $this->assertTrue($recordingStopped, 'Gravação deveria ter parado');
        $this->assertNotEmpty($recordingEvents);
        
        // Verificar sequência de eventos
        $eventTypes = array_column($recordingEvents, 'type');
        $this->assertContains('recording_started', $eventTypes);
        $this->assertContains('recording_stopped', $eventTypes);
    }
    
    public function testConnectionLimits()
    {
        $connections = [];
        $maxConnections = 5;
        $connectionCount = 0;
        $rejectedConnections = 0;
        
        // Tentar criar múltiplas conexões
        for ($i = 0; $i < $maxConnections + 2; $i++) {
            $this->connector($this->wsUrl)
                ->then(function (WebSocket $conn) use (&$connections, &$connectionCount) {
                    $connections[] = $conn;
                    $connectionCount++;
                    
                    $conn->send(json_encode([
                        'type' => 'auth',
                        'token' => $this->authToken
                    ]));
                    
                }, function (\Exception $e) use (&$rejectedConnections) {
                    $rejectedConnections++;
                });
        }
        
        // Aguardar todas as tentativas
        $this->loop->addTimer(3, function () use (&$connections) {
            // Fechar todas as conexões
            foreach ($connections as $conn) {
                $conn->close();
            }
            $this->loop->stop();
        });
        
        $this->loop->run();
        
        // Verificar limites
        $this->assertLessThanOrEqual($maxConnections, $connectionCount);
        
        if ($connectionCount >= $maxConnections) {
            $this->assertGreaterThan(0, $rejectedConnections, 
                'Deve rejeitar conexões além do limite');
        }
    }
    
    public function testInvalidAuthentication()
    {
        $authenticationFailed = false;
        $connectionClosed = false;
        
        $this->connector($this->wsUrl)
            ->then(function (WebSocket $conn) use (&$authenticationFailed, &$connectionClosed) {
                
                // Enviar token inválido
                $conn->send(json_encode([
                    'type' => 'auth',
                    'token' => 'invalid_token_here'
                ]));
                
                $conn->on('message', function ($msg) use (&$authenticationFailed, $conn) {
                    $data = json_decode($msg->getPayload(), true);
                    
                    if ($data['type'] === 'auth_response' && $data['success'] === false) {
                        $authenticationFailed = true;
                    }
                });
                
                $conn->on('close', function () use (&$connectionClosed) {
                    $connectionClosed = true;
                });
                
                $this->loop->addTimer(3, function () use ($conn) {
                    $conn->close();
                });
                
            });
        
        $this->loop->addTimer(5, function () {
            $this->loop->stop();
        });
        
        $this->loop->run();
        
        $this->assertTrue($authenticationFailed, 
            'Autenticação com token inválido deveria falhar');
    }
}
