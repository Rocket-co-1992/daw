-- Script de criação do banco de dados DAW Online
-- MariaDB/MySQL

CREATE DATABASE IF NOT EXISTS daw_online CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE daw_online;

-- Tabela de usuários
CREATE TABLE usuarios (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    nome_completo VARCHAR(100),
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ultima_atividade TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    status ENUM('ativo', 'inativo', 'suspenso') DEFAULT 'ativo',
    avatar VARCHAR(255),
    INDEX idx_username (username),
    INDEX idx_email (email)
);

-- Tabela de projetos
CREATE TABLE projetos (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nome VARCHAR(100) NOT NULL,
    descricao TEXT,
    criador_id INT NOT NULL,
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_modificacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    bpm INT DEFAULT 120,
    compasso VARCHAR(10) DEFAULT '4/4',
    tonalidade VARCHAR(10) DEFAULT 'C',
    status ENUM('privado', 'colaborativo', 'publico') DEFAULT 'privado',
    configuracao_json JSON,
    FOREIGN KEY (criador_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_criador (criador_id),
    INDEX idx_status (status)
);

-- Tabela de colaboradores do projeto
CREATE TABLE projeto_colaboradores (
    id INT PRIMARY KEY AUTO_INCREMENT,
    projeto_id INT NOT NULL,
    usuario_id INT NOT NULL,
    papel ENUM('proprietario', 'colaborador', 'visualizador') DEFAULT 'colaborador',
    data_convite TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_aceite TIMESTAMP NULL,
    status ENUM('pendente', 'aceito', 'rejeitado') DEFAULT 'pendente',
    FOREIGN KEY (projeto_id) REFERENCES projetos(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    UNIQUE KEY unique_projeto_usuario (projeto_id, usuario_id)
);

-- Tabela de faixas (tracks)
CREATE TABLE faixas (
    id INT PRIMARY KEY AUTO_INCREMENT,
    projeto_id INT NOT NULL,
    nome VARCHAR(100) NOT NULL,
    tipo ENUM('audio', 'midi', 'instrumento', 'aux') DEFAULT 'audio',
    posicao INT DEFAULT 0,
    volume DECIMAL(5,2) DEFAULT 100.00,
    pan DECIMAL(5,2) DEFAULT 0.00,
    mute BOOLEAN DEFAULT FALSE,
    solo BOOLEAN DEFAULT FALSE,
    cor VARCHAR(7) DEFAULT '#3498db',
    configuracao_json JSON,
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (projeto_id) REFERENCES projetos(id) ON DELETE CASCADE,
    INDEX idx_projeto (projeto_id),
    INDEX idx_posicao (posicao)
);

-- Tabela de regiões de áudio/MIDI
CREATE TABLE regioes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    faixa_id INT NOT NULL,
    nome VARCHAR(100),
    tipo ENUM('audio', 'midi') NOT NULL,
    inicio_tempo DECIMAL(10,3) NOT NULL,
    duracao DECIMAL(10,3) NOT NULL,
    arquivo_audio VARCHAR(255),
    dados_midi JSON,
    loop_inicio DECIMAL(10,3),
    loop_fim DECIMAL(10,3),
    fadein DECIMAL(5,3) DEFAULT 0.000,
    fadeout DECIMAL(5,3) DEFAULT 0.000,
    gain DECIMAL(5,2) DEFAULT 0.00,
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (faixa_id) REFERENCES faixas(id) ON DELETE CASCADE,
    INDEX idx_faixa (faixa_id),
    INDEX idx_tempo (inicio_tempo)
);

-- Tabela de plugins
CREATE TABLE plugins (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nome VARCHAR(100) NOT NULL,
    fabricante VARCHAR(100),
    versao VARCHAR(20),
    tipo ENUM('vst', 'vst3', 'au', 'aax', 'ladspa') NOT NULL,
    categoria ENUM('efeito', 'instrumento', 'utilidade') DEFAULT 'efeito',
    caminho_arquivo VARCHAR(500) NOT NULL,
    parametros_padrao JSON,
    data_instalacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('ativo', 'inativo') DEFAULT 'ativo',
    INDEX idx_tipo (tipo),
    INDEX idx_categoria (categoria)
);

-- Tabela de instâncias de plugins em faixas
CREATE TABLE faixa_plugins (
    id INT PRIMARY KEY AUTO_INCREMENT,
    faixa_id INT NOT NULL,
    plugin_id INT NOT NULL,
    posicao INT DEFAULT 0,
    ativo BOOLEAN DEFAULT TRUE,
    preset_nome VARCHAR(100),
    parametros_json JSON,
    data_adicao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (faixa_id) REFERENCES faixas(id) ON DELETE CASCADE,
    FOREIGN KEY (plugin_id) REFERENCES plugins(id) ON DELETE CASCADE,
    INDEX idx_faixa_plugin (faixa_id, posicao)
);

-- Tabela de configurações de áudio
CREATE TABLE configuracoes_audio (
    id INT PRIMARY KEY AUTO_INCREMENT,
    usuario_id INT NOT NULL,
    projeto_id INT,
    driver_tipo ENUM('asio', 'directsound', 'wasapi', 'coreaudio', 'alsa', 'jack') DEFAULT 'asio',
    dispositivo_entrada VARCHAR(200),
    dispositivo_saida VARCHAR(200),
    sample_rate INT DEFAULT 44100,
    buffer_size INT DEFAULT 512,
    bit_depth INT DEFAULT 24,
    configuracao_json JSON,
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_modificacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (projeto_id) REFERENCES projetos(id) ON DELETE SET NULL,
    INDEX idx_usuario (usuario_id)
);

-- Tabela de sessões de sincronização
CREATE TABLE sessoes_sincronizacao (
    id INT PRIMARY KEY AUTO_INCREMENT,
    projeto_id INT NOT NULL,
    session_id VARCHAR(100) UNIQUE NOT NULL,
    master_user_id INT NOT NULL,
    tempo_atual DECIMAL(10,3) DEFAULT 0.000,
    bpm_atual INT DEFAULT 120,
    estado ENUM('parado', 'reproduzindo', 'gravando', 'pausado') DEFAULT 'parado',
    metronomo_ativo BOOLEAN DEFAULT FALSE,
    click_track_ativo BOOLEAN DEFAULT FALSE,
    data_inicio TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_ultima_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (projeto_id) REFERENCES projetos(id) ON DELETE CASCADE,
    FOREIGN KEY (master_user_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_projeto (projeto_id),
    INDEX idx_session (session_id)
);

-- Tabela de participantes da sessão
CREATE TABLE sessao_participantes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    sessao_id INT NOT NULL,
    usuario_id INT NOT NULL,
    conectado BOOLEAN DEFAULT TRUE,
    latencia_ms INT DEFAULT 0,
    data_conexao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_ultima_atividade TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (sessao_id) REFERENCES sessoes_sincronizacao(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    UNIQUE KEY unique_sessao_usuario (sessao_id, usuario_id)
);

-- Tabela de automação
CREATE TABLE automacao (
    id INT PRIMARY KEY AUTO_INCREMENT,
    faixa_id INT,
    plugin_instancia_id INT,
    parametro_nome VARCHAR(100) NOT NULL,
    tipo_target ENUM('faixa', 'plugin') NOT NULL,
    pontos_automacao JSON NOT NULL,
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (faixa_id) REFERENCES faixas(id) ON DELETE CASCADE,
    FOREIGN KEY (plugin_instancia_id) REFERENCES faixa_plugins(id) ON DELETE CASCADE,
    INDEX idx_faixa (faixa_id),
    INDEX idx_plugin (plugin_instancia_id)
);

-- Tabela de templates de projeto
CREATE TABLE templates_projeto (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nome VARCHAR(100) NOT NULL,
    descricao TEXT,
    categoria VARCHAR(50),
    configuracao_json JSON NOT NULL,
    criador_id INT,
    publico BOOLEAN DEFAULT FALSE,
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (criador_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_categoria (categoria),
    INDEX idx_publico (publico)
);

-- Tabela de logs de atividade
CREATE TABLE logs_atividade (
    id INT PRIMARY KEY AUTO_INCREMENT,
    usuario_id INT,
    projeto_id INT,
    acao VARCHAR(100) NOT NULL,
    detalhes JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    FOREIGN KEY (projeto_id) REFERENCES projetos(id) ON DELETE SET NULL,
    INDEX idx_usuario_timestamp (usuario_id, timestamp),
    INDEX idx_projeto_timestamp (projeto_id, timestamp)
);

-- Inserir dados de exemplo
INSERT INTO usuarios (username, email, password_hash, nome_completo) VALUES
('admin', 'admin@dawonline.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrador'),
('demo_user', 'demo@dawonline.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Usuário Demo');

INSERT INTO plugins (nome, fabricante, versao, tipo, categoria, caminho_arquivo, parametros_padrao) VALUES
('EQ Eight', 'DAW Online', '1.0', 'vst3', 'efeito', '/plugins/eq_eight.vst3', '{"bands": 8, "gain": 0, "frequency": 440}'),
('Compressor', 'DAW Online', '1.0', 'vst3', 'efeito', '/plugins/compressor.vst3', '{"ratio": 4, "threshold": -20, "attack": 10, "release": 100}'),
('Reverb Hall', 'DAW Online', '1.0', 'vst3', 'efeito', '/plugins/reverb_hall.vst3', '{"room_size": 0.5, "damping": 0.3, "wet": 0.2}');
