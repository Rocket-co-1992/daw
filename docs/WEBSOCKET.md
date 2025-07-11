# WebSocket Events - DAW Online

## Visão Geral

O servidor WebSocket da DAW Online permite comunicação em tempo real para colaboração entre múltiplos usuários. O servidor roda na porta 8080 por padrão.

**Endpoint**: `ws://localhost:8080/`

**Protocolo**: WebSocket com JSON

**Autenticação**: JWT token enviado na primeira mensagem

## Conexão e Autenticação

### Conectar e autenticar

```javascript
const ws = new WebSocket('ws://localhost:8080/');

ws.onopen = function() {
    // Autenticar com JWT token
    ws.send(JSON.stringify({
        type: 'auth',
        data: {
            token: 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...'
        }
    }));
};
```

### Resposta de autenticação

```json
{
    "type": "auth.response",
    "success": true,
    "data": {
        "user_id": 1,
        "username": "usuario",
        "session_id": "sess_abc123"
    }
}
```

## Estrutura de Mensagens

Todas as mensagens seguem o formato:

```json
{
    "type": "event.action",
    "data": {
        // dados específicos do evento
    },
    "timestamp": 1640995200,
    "session_id": "sess_abc123",
    "user_id": 1
}
```

## Eventos de Projeto e Sessão

### Entrar em um projeto

**Cliente → Servidor:**
```json
{
    "type": "project.join",
    "data": {
        "project_id": 1
    }
}
```

**Servidor → Cliente:**
```json
{
    "type": "project.joined",
    "data": {
        "project_id": 1,
        "active_users": [
            {
                "user_id": 1,
                "username": "usuario1",
                "status": "online"
            },
            {
                "user_id": 2,
                "username": "usuario2",
                "status": "recording"
            }
        ]
    }
}
```

### Sair de um projeto

**Cliente → Servidor:**
```json
{
    "type": "project.leave",
    "data": {
        "project_id": 1
    }
}
```

### Usuário entrou/saiu

**Servidor → Clientes:**
```json
{
    "type": "user.joined",
    "data": {
        "user_id": 3,
        "username": "novo_usuario",
        "project_id": 1
    }
}
```

```json
{
    "type": "user.left",
    "data": {
        "user_id": 3,
        "username": "usuario_saiu",
        "project_id": 1
    }
}
```

## Eventos de Transporte

### Controles de reprodução

**Play:**
```json
{
    "type": "transport.play",
    "data": {
        "project_id": 1,
        "position": 0,
        "timestamp": 1640995200
    }
}
```

**Pause:**
```json
{
    "type": "transport.pause",
    "data": {
        "project_id": 1,
        "position": 32.5,
        "timestamp": 1640995200
    }
}
```

**Stop:**
```json
{
    "type": "transport.stop",
    "data": {
        "project_id": 1,
        "timestamp": 1640995200
    }
}
```

**Seek (navegar para posição):**
```json
{
    "type": "transport.seek",
    "data": {
        "project_id": 1,
        "position": 64.0,
        "timestamp": 1640995200
    }
}
```

### Status de transporte

**Servidor → Clientes:**
```json
{
    "type": "transport.status",
    "data": {
        "project_id": 1,
        "playing": true,
        "position": 32.5,
        "bpm": 120,
        "timestamp": 1640995200,
        "master_user": 1
    }
}
```

## Eventos de Faixas

### Atualizar faixa

**Cliente → Servidor:**
```json
{
    "type": "track.update",
    "data": {
        "track_id": 1,
        "project_id": 1,
        "changes": {
            "volume": 80,
            "pan": -10,
            "mute": false,
            "solo": true
        }
    }
}
```

**Servidor → Clientes:**
```json
{
    "type": "track.updated",
    "data": {
        "track_id": 1,
        "project_id": 1,
        "user_id": 1,
        "changes": {
            "volume": 80,
            "pan": -10,
            "mute": false,
            "solo": true
        }
    }
}
```

### Criar faixa

```json
{
    "type": "track.create",
    "data": {
        "project_id": 1,
        "nome": "Nova Faixa",
        "tipo": "audio",
        "posicao": 3,
        "cor": "#2196f3"
    }
}
```

### Deletar faixa

```json
{
    "type": "track.delete",
    "data": {
        "track_id": 1,
        "project_id": 1
    }
}
```

## Eventos de Regiões

### Criar região

```json
{
    "type": "region.create",
    "data": {
        "track_id": 1,
        "project_id": 1,
        "nome": "Região 1",
        "inicio": 0,
        "fim": 16,
        "arquivo_audio": "uploads/audio_123.wav"
    }
}
```

### Mover região

```json
{
    "type": "region.move",
    "data": {
        "region_id": 1,
        "track_id": 1,
        "project_id": 1,
        "novo_inicio": 8,
        "novo_fim": 24
    }
}
```

### Redimensionar região

```json
{
    "type": "region.resize",
    "data": {
        "region_id": 1,
        "track_id": 1,
        "project_id": 1,
        "novo_inicio": 0,
        "novo_fim": 20
    }
}
```

### Deletar região

```json
{
    "type": "region.delete",
    "data": {
        "region_id": 1,
        "track_id": 1,
        "project_id": 1
    }
}
```

## Eventos de Gravação

### Iniciar gravação

```json
{
    "type": "recording.start",
    "data": {
        "track_id": 1,
        "project_id": 1,
        "inicio": 0,
        "input_device": "Interface de Áudio",
        "armed_tracks": [1, 3]
    }
}
```

### Parar gravação

```json
{
    "type": "recording.stop",
    "data": {
        "track_id": 1,
        "project_id": 1,
        "fim": 32.5,
        "arquivo_audio": "uploads/rec_456.wav"
    }
}
```

### Status de gravação

```json
{
    "type": "recording.status",
    "data": {
        "project_id": 1,
        "recording": true,
        "track_id": 1,
        "duration": 15.2,
        "level": -12.5
    }
}
```

## Eventos de Plugins

### Atualizar parâmetros

```json
{
    "type": "plugin.update",
    "data": {
        "plugin_id": 1,
        "track_id": 1,
        "project_id": 1,
        "parametros": {
            "gain": 5.0,
            "frequency": 1000,
            "q": 2.5
        }
    }
}
```

### Ativar/desativar plugin

```json
{
    "type": "plugin.toggle",
    "data": {
        "plugin_id": 1,
        "track_id": 1,
        "project_id": 1,
        "ativo": false
    }
}
```

## Eventos de Automação

### Criar ponto de automação

```json
{
    "type": "automation.point.create",
    "data": {
        "track_id": 1,
        "project_id": 1,
        "parametro": "volume",
        "tempo": 8.0,
        "valor": 75
    }
}
```

### Mover ponto de automação

```json
{
    "type": "automation.point.move",
    "data": {
        "point_id": 1,
        "track_id": 1,
        "project_id": 1,
        "novo_tempo": 10.0,
        "novo_valor": 80
    }
}
```

## Eventos de Sistema

### Heartbeat

**Cliente → Servidor (a cada 30s):**
```json
{
    "type": "ping",
    "data": {
        "timestamp": 1640995200
    }
}
```

**Servidor → Cliente:**
```json
{
    "type": "pong",
    "data": {
        "timestamp": 1640995200,
        "latency": 25
    }
}
```

### Sincronização de tempo

```json
{
    "type": "time.sync",
    "data": {
        "server_time": 1640995200,
        "project_id": 1,
        "position": 32.5,
        "bpm": 120
    }
}
```

### Erro

```json
{
    "type": "error",
    "data": {
        "code": "PERMISSION_DENIED",
        "message": "Você não tem permissão para editar este projeto",
        "details": {
            "project_id": 1,
            "required_role": "collaborator"
        }
    }
}
```

## Estados de Usuário

Os usuários podem ter os seguintes estados:

- `online`: Conectado e ativo
- `idle`: Conectado mas inativo
- `recording`: Gravando
- `playing`: Reproduzindo
- `editing`: Editando
- `offline`: Desconectado

## Resolução de Conflitos

### Conflito de edição

```json
{
    "type": "conflict.detected",
    "data": {
        "resource_type": "track",
        "resource_id": 1,
        "conflicting_users": [1, 2],
        "changes": {
            "user_1": {"volume": 80},
            "user_2": {"volume": 75}
        }
    }
}
```

### Resolução de conflito

```json
{
    "type": "conflict.resolve",
    "data": {
        "resource_type": "track",
        "resource_id": 1,
        "resolution": "latest_wins",
        "winning_user": 2,
        "final_state": {"volume": 75}
    }
}
```

## Compensação de Latência

O servidor calcula e compensa automaticamente a latência de rede:

```json
{
    "type": "latency.compensation",
    "data": {
        "user_id": 1,
        "measured_latency": 45,
        "compensation_offset": 45,
        "sync_quality": "good"
    }
}
```

## Implementação JavaScript

### Cliente básico

```javascript
class DAWWebSocket {
    constructor(url, token) {
        this.url = url;
        this.token = token;
        this.ws = null;
        this.listeners = new Map();
        this.reconnectDelay = 1000;
        this.maxReconnectDelay = 30000;
    }
    
    connect() {
        this.ws = new WebSocket(this.url);
        
        this.ws.onopen = () => {
            console.log('WebSocket conectado');
            this.authenticate();
            this.resetReconnectDelay();
        };
        
        this.ws.onmessage = (event) => {
            const message = JSON.parse(event.data);
            this.handleMessage(message);
        };
        
        this.ws.onclose = () => {
            console.log('WebSocket desconectado');
            this.scheduleReconnect();
        };
        
        this.ws.onerror = (error) => {
            console.error('Erro WebSocket:', error);
        };
    }
    
    authenticate() {
        this.send('auth', { token: this.token });
    }
    
    send(type, data) {
        if (this.ws && this.ws.readyState === WebSocket.OPEN) {
            this.ws.send(JSON.stringify({
                type,
                data,
                timestamp: Date.now()
            }));
        }
    }
    
    on(eventType, callback) {
        if (!this.listeners.has(eventType)) {
            this.listeners.set(eventType, []);
        }
        this.listeners.get(eventType).push(callback);
    }
    
    handleMessage(message) {
        const listeners = this.listeners.get(message.type);
        if (listeners) {
            listeners.forEach(callback => callback(message.data));
        }
    }
    
    scheduleReconnect() {
        setTimeout(() => {
            this.connect();
            this.reconnectDelay = Math.min(
                this.reconnectDelay * 2, 
                this.maxReconnectDelay
            );
        }, this.reconnectDelay);
    }
    
    resetReconnectDelay() {
        this.reconnectDelay = 1000;
    }
}

// Uso
const wsClient = new DAWWebSocket('ws://localhost:8080/', token);

// Escutar eventos
wsClient.on('transport.status', (data) => {
    console.log('Status do transporte:', data);
});

wsClient.on('track.updated', (data) => {
    console.log('Faixa atualizada:', data);
});

// Conectar
wsClient.connect();

// Enviar comandos
wsClient.send('transport.play', { project_id: 1, position: 0 });
wsClient.send('track.update', { 
    track_id: 1, 
    project_id: 1, 
    changes: { volume: 80 } 
});
```

## Códigos de Erro

- `AUTH_REQUIRED`: Autenticação necessária
- `INVALID_TOKEN`: Token inválido
- `PERMISSION_DENIED`: Sem permissão
- `PROJECT_NOT_FOUND`: Projeto não encontrado
- `INVALID_DATA`: Dados inválidos
- `RATE_LIMITED`: Rate limit excedido
- `SERVER_ERROR`: Erro interno do servidor

## Performance

O servidor WebSocket da DAW Online é otimizado para:

- **Baixa latência**: < 50ms para sincronização
- **Alta throughput**: 1000+ mensagens/segundo
- **Conexões simultâneas**: 100+ usuários
- **Heartbeat**: Detecção automática de desconexão
- **Reconexão automática**: Cliente reconecta automaticamente
