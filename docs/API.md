# API Documentation - DAW Online

## Visão Geral

A API da DAW Online é uma API RESTful que permite gerenciar projetos, faixas, colaboração e autenticação. Todas as rotas que requerem autenticação usam JWT Bearer tokens.

**Base URL**: `/api/`

**Formato de resposta**: JSON

**Autenticação**: Bearer Token (JWT)

## Autenticação

### Login
```http
POST /api/auth.php?action=login
```

**Body:**
```json
{
  "username": "usuario@email.com",
  "password": "senha123"
}
```

**Resposta de sucesso:**
```json
{
  "success": true,
  "message": "Login realizado com sucesso",
  "user": {
    "id": 1,
    "username": "usuario",
    "email": "usuario@email.com",
    "nome_completo": "Nome Completo",
    "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..."
  }
}
```

### Registro
```http
POST /api/auth.php?action=register
```

**Body:**
```json
{
  "username": "novousuario",
  "email": "novo@email.com",
  "password": "senha123",
  "nome_completo": "Nome Completo"
}
```

### Verificar usuário atual
```http
POST /api/auth.php?action=me
```

**Headers:**
```
Authorization: Bearer {token}
```

### Renovar token
```http
POST /api/auth.php?action=refresh
```

**Headers:**
```
Authorization: Bearer {refresh_token}
```

## Projetos

### Listar projetos do usuário
```http
GET /api/projects.php
```

**Query Parameters:**
- `limit` (opcional): Número máximo de projetos (padrão: 20)
- `offset` (opcional): Offset para paginação (padrão: 0)

**Headers:**
```
Authorization: Bearer {token}
```

**Resposta:**
```json
{
  "success": true,
  "projects": [
    {
      "id": 1,
      "nome": "Minha Música",
      "descricao": "Descrição do projeto",
      "bpm": 120,
      "compasso": "4/4",
      "tonalidade": "C",
      "status": "ativo",
      "data_criacao": "2024-01-01 10:00:00",
      "data_modificacao": "2024-01-01 15:30:00",
      "total_faixas": 8,
      "total_colaboradores": 2
    }
  ]
}
```

### Obter projeto específico
```http
GET /api/projects.php?id={project_id}
```

**Resposta:**
```json
{
  "success": true,
  "project": {
    "id": 1,
    "nome": "Minha Música",
    "descricao": "Descrição do projeto",
    "bpm": 120,
    "compasso": "4/4",
    "tonalidade": "C",
    "status": "ativo",
    "configuracao_json": {
      "volume_master": 100,
      "metronomo": false,
      "click_track": false
    },
    "faixas": [
      {
        "id": 1,
        "nome": "Vocal Principal",
        "tipo": "audio",
        "posicao": 1,
        "cor": "#ff5722",
        "ativa": true,
        "configuracao_json": {
          "volume": 100,
          "pan": 0,
          "mute": false,
          "solo": false
        },
        "regioes": [
          {
            "id": 1,
            "nome": "Verso 1",
            "inicio": 0,
            "fim": 32,
            "arquivo_audio": "uploads/audio_123.wav"
          }
        ],
        "plugins": [
          {
            "id": 1,
            "nome": "EQ Eight",
            "posicao": 1,
            "ativo": true,
            "parametros": {}
          }
        ]
      }
    ],
    "colaboradores": [
      {
        "papel": "colaborador",
        "username": "outro_usuario",
        "nome_completo": "Outro Usuário",
        "status": "online"
      }
    ]
  }
}
```

### Criar novo projeto
```http
POST /api/projects.php
```

**Body:**
```json
{
  "nome": "Novo Projeto",
  "descricao": "Descrição opcional",
  "bpm": 120,
  "compasso": "4/4",
  "tonalidade": "C"
}
```

### Atualizar projeto
```http
PUT /api/projects.php?id={project_id}
```

**Body:**
```json
{
  "nome": "Nome Atualizado",
  "bpm": 140,
  "status": "ativo"
}
```

### Deletar projeto
```http
DELETE /api/projects.php?id={project_id}
```

## Faixas

### Listar faixas de um projeto
```http
GET /api/tracks.php?project_id={project_id}
```

### Criar nova faixa
```http
POST /api/tracks.php
```

**Body:**
```json
{
  "projeto_id": 1,
  "nome": "Nova Faixa",
  "tipo": "audio",
  "posicao": 1,
  "cor": "#2196f3"
}
```

### Atualizar faixa
```http
PUT /api/tracks.php?id={track_id}
```

**Body:**
```json
{
  "nome": "Nome Atualizado",
  "cor": "#ff9800",
  "configuracao_json": {
    "volume": 80,
    "pan": -10,
    "mute": false,
    "solo": true
  }
}
```

### Deletar faixa
```http
DELETE /api/tracks.php?id={track_id}
```

## Regiões de Áudio

### Listar regiões de uma faixa
```http
GET /api/regions.php?track_id={track_id}
```

### Criar nova região
```http
POST /api/regions.php
```

**Body:**
```json
{
  "faixa_id": 1,
  "nome": "Região 1",
  "inicio": 0,
  "fim": 16,
  "arquivo_audio": "uploads/audio_456.wav"
}
```

### Atualizar região
```http
PUT /api/regions.php?id={region_id}
```

### Deletar região
```http
DELETE /api/regions.php?id={region_id}
```

## Upload de Áudio

### Upload de arquivo de áudio
```http
POST /api/upload.php
```

**Body (multipart/form-data):**
- `audio_file`: Arquivo de áudio (WAV, MP3, FLAC, OGG)
- `project_id`: ID do projeto
- `track_id` (opcional): ID da faixa

**Resposta:**
```json
{
  "success": true,
  "message": "Arquivo enviado com sucesso",
  "file": {
    "filename": "audio_789.wav",
    "path": "uploads/audio_789.wav",
    "size": 1024000,
    "duration": 32.5,
    "sample_rate": 44100,
    "channels": 2
  }
}
```

## Plugins

### Listar plugins disponíveis
```http
GET /api/plugins.php
```

### Obter presets de um plugin
```http
GET /api/plugins.php?id={plugin_id}&action=presets
```

### Salvar preset
```http
POST /api/plugins.php?id={plugin_id}&action=save_preset
```

**Body:**
```json
{
  "nome": "Meu Preset",
  "parametros": {
    "gain": 5,
    "frequency": 1000,
    "q": 2.5
  }
}
```

## Colaboração

### Convidar colaborador
```http
POST /api/collaboration.php
```

**Body:**
```json
{
  "project_id": 1,
  "username": "novo_colaborador",
  "role": "collaborator"
}
```

### Listar colaboradores
```http
GET /api/collaboration.php?project_id={project_id}
```

### Atualizar papel do colaborador
```http
PUT /api/collaboration.php
```

**Body:**
```json
{
  "project_id": 1,
  "user_id": 2,
  "role": "admin"
}
```

### Remover colaborador
```http
DELETE /api/collaboration.php?project_id={project_id}&user_id={user_id}
```

## Configurações de Áudio

### Obter configurações
```http
GET /api/audio_config.php
```

### Atualizar configurações
```http
POST /api/audio_config.php
```

**Body:**
```json
{
  "driver_asio": "ASIO4ALL v2",
  "sample_rate": 44100,
  "buffer_size": 128,
  "input_device": "Interface de Áudio",
  "output_device": "Interface de Áudio"
}
```

### Testar configuração
```http
POST /api/audio_config.php?action=test
```

## Códigos de Status HTTP

- `200` - OK
- `201` - Created (recurso criado)
- `400` - Bad Request (dados inválidos)
- `401` - Unauthorized (não autenticado)
- `403` - Forbidden (sem permissão)
- `404` - Not Found (recurso não encontrado)
- `405` - Method Not Allowed (método HTTP não permitido)
- `409` - Conflict (conflito de dados)
- `422` - Unprocessable Entity (dados válidos mas não processáveis)
- `500` - Internal Server Error (erro interno)

## Estrutura de Erros

```json
{
  "error": "Mensagem de erro descritiva",
  "code": "ERROR_CODE",
  "details": {
    "field": "Campo com erro",
    "value": "Valor inválido"
  }
}
```

## Rate Limiting

A API implementa rate limiting para prevenir abuso:

- **Global**: 60 requisições por minuto
- **API**: 30 requisições por minuto
- **Autenticação**: 5 tentativas por minuto

Quando o limite é atingido, a API retorna status `429 Too Many Requests`.

## Headers de Rate Limiting

```
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 45
X-RateLimit-Reset: 1640995200
```

## Paginação

Endpoints que retornam listas suportam paginação:

**Query Parameters:**
- `limit`: Número de itens por página (máximo: 100)
- `offset`: Offset para paginação

**Headers de resposta:**
```
X-Total-Count: 150
X-Page-Count: 8
X-Current-Page: 2
```

## WebSocket Events

Para recursos em tempo real, conecte-se ao WebSocket em `ws://localhost:8080/`

### Eventos enviados pelo cliente

- `transport.play`: Iniciar reprodução
- `transport.pause`: Pausar reprodução
- `transport.stop`: Parar reprodução
- `transport.seek`: Navegar para posição
- `track.update`: Atualizar faixa
- `region.update`: Atualizar região

### Eventos recebidos do servidor

- `transport.status`: Status do transporte
- `track.updated`: Faixa atualizada
- `region.updated`: Região atualizada
- `user.joined`: Usuário entrou na sessão
- `user.left`: Usuário saiu da sessão

## Exemplos de Uso

### JavaScript/jQuery

```javascript
// Configurar cliente API
const api = new APIClient();

// Login
const user = await api.login('usuario@email.com', 'senha123');

// Criar projeto
const project = await api.createProject({
  nome: 'Minha Música',
  bpm: 120
});

// Criar faixa
const track = await api.createTrack({
  projeto_id: project.id,
  nome: 'Vocal',
  tipo: 'audio'
});

// Upload de áudio
const file = document.getElementById('audioFile').files[0];
const uploadResult = await api.uploadAudio(file, project.id, track.id);
```

### cURL

```bash
# Login
curl -X POST http://localhost/api/auth.php?action=login \
  -H "Content-Type: application/json" \
  -d '{"username":"usuario@email.com","password":"senha123"}'

# Criar projeto
curl -X POST http://localhost/api/projects.php \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer {token}" \
  -d '{"nome":"Novo Projeto","bpm":120}'

# Upload de áudio
curl -X POST http://localhost/api/upload.php \
  -H "Authorization: Bearer {token}" \
  -F "audio_file=@musica.wav" \
  -F "project_id=1"
```
