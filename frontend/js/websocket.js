/**
 * Cliente WebSocket para sincronização em tempo real
 */

class WebSocketClient {
    constructor() {
        this.ws = null;
        this.connected = false;
        this.sessionId = null;
        this.isReconnecting = false;
        this.reconnectAttempts = 0;
        this.maxReconnectAttempts = 5;
        this.reconnectDelay = 1000; // ms
        this.heartbeatInterval = null;
        this.latency = 0;
        
        this.eventListeners = {
            connected: [],
            disconnected: [],
            message: [],
            error: [],
            sessionJoined: [],
            userJoined: [],
            userLeft: [],
            transportUpdate: [],
            trackUpdate: [],
            pluginUpdate: [],
            audioData: []
        };
        
        this.init();
    }
    
    init() {
        this.connect();
        this.setupEventListeners();
    }
    
    connect() {
        if (this.ws && this.ws.readyState === WebSocket.OPEN) {
            return;
        }
        
        try {
            console.log('Conectando ao WebSocket...');
            this.ws = new WebSocket(DAWConfig.WEBSOCKET_URL);
            
            this.ws.onopen = this.onOpen.bind(this);
            this.ws.onmessage = this.onMessage.bind(this);
            this.ws.onclose = this.onClose.bind(this);
            this.ws.onerror = this.onError.bind(this);
            
        } catch (error) {
            console.error('Erro ao conectar WebSocket:', error);
            this.scheduleReconnect();
        }
    }
    
    onOpen() {
        console.log('WebSocket conectado');
        this.connected = true;
        this.reconnectAttempts = 0;
        this.isReconnecting = false;
        
        // Autenticar se temos token
        if (api.isAuthenticated()) {
            this.authenticate();
        }
        
        // Iniciar heartbeat
        this.startHeartbeat();
        
        this.emit('connected');
    }
    
    onMessage(event) {
        try {
            const data = JSON.parse(event.data);
            this.handleMessage(data);
        } catch (error) {
            console.error('Erro ao processar mensagem WebSocket:', error);
        }
    }
    
    onClose(event) {
        console.log('WebSocket desconectado:', event.code, event.reason);
        this.connected = false;
        this.stopHeartbeat();
        
        this.emit('disconnected', { code: event.code, reason: event.reason });
        
        // Tentar reconectar se não foi fechamento intencional
        if (event.code !== 1000 && !this.isReconnecting) {
            this.scheduleReconnect();
        }
    }
    
    onError(error) {
        console.error('Erro no WebSocket:', error);
        this.emit('error', error);
    }
    
    handleMessage(data) {
        console.log('Mensagem recebida:', data);
        
        switch (data.type) {
            case 'welcome':
                console.log('Bem-vindo! Client ID:', data.clientId);
                break;
                
            case 'auth_success':
                console.log('Autenticação bem-sucedida');
                break;
                
            case 'session_joined':
                this.sessionId = data.session.id;
                this.emit('sessionJoined', data);
                break;
                
            case 'user_joined':
                this.emit('userJoined', data);
                break;
                
            case 'user_left':
                this.emit('userLeft', data);
                break;
                
            case 'transport_update':
                this.emit('transportUpdate', data);
                break;
                
            case 'track_update':
                this.emit('trackUpdate', data);
                break;
                
            case 'plugin_update':
                this.emit('pluginUpdate', data);
                break;
                
            case 'audio_data':
                this.emit('audioData', data);
                break;
                
            case 'time_sync_response':
                this.calculateLatency(data);
                break;
                
            case 'heartbeat_response':
                this.updateLatency(data);
                break;
                
            case 'master_changed':
                console.log('Novo master:', data.newMasterUsername);
                break;
                
            case 'error':
                console.error('Erro do servidor:', data.message);
                this.emit('error', new Error(data.message));
                break;
                
            default:
                console.log('Tipo de mensagem desconhecido:', data.type);
        }
        
        this.emit('message', data);
    }
    
    send(data) {
        if (this.connected && this.ws.readyState === WebSocket.OPEN) {
            this.ws.send(JSON.stringify(data));
            return true;
        } else {
            console.warn('WebSocket não conectado, mensagem não enviada:', data);
            return false;
        }
    }
    
    authenticate() {
        if (!api.token) {
            console.warn('Token não disponível para autenticação WebSocket');
            return;
        }
        
        this.send({
            type: 'auth',
            token: api.token
        });
    }
    
    joinSession(sessionId, projectId) {
        if (!this.connected) {
            console.warn('WebSocket não conectado');
            return false;
        }
        
        this.send({
            type: 'join_session',
            sessionId: sessionId,
            projectId: projectId
        });
        
        return true;
    }
    
    leaveSession() {
        if (!this.connected || !this.sessionId) {
            return false;
        }
        
        this.send({
            type: 'leave_session',
            sessionId: this.sessionId
        });
        
        this.sessionId = null;
        return true;
    }
    
    // Controle de transport
    syncTransport(transportData) {
        if (!this.sessionId) {
            return false;
        }
        
        this.send({
            type: 'sync_transport',
            transport: transportData,
            timestamp: this.getTimestamp()
        });
        
        return true;
    }
    
    // Sincronização de faixas
    syncTrackUpdate(trackData) {
        if (!this.sessionId) {
            return false;
        }
        
        this.send({
            type: 'track_update',
            trackData: trackData,
            timestamp: this.getTimestamp()
        });
        
        return true;
    }
    
    // Sincronização de plugins
    syncPluginUpdate(pluginData) {
        if (!this.sessionId) {
            return false;
        }
        
        this.send({
            type: 'plugin_update',
            pluginData: pluginData,
            timestamp: this.getTimestamp()
        });
        
        return true;
    }
    
    // Envio de dados de áudio
    sendAudioData(audioBuffer, trackId) {
        if (!this.sessionId) {
            return false;
        }
        
        // Em uma implementação real, aqui seria aplicada compressão
        this.send({
            type: 'audio_data',
            audioBuffer: audioBuffer,
            trackId: trackId,
            timestamp: this.getTimestamp()
        });
        
        return true;
    }
    
    // Sincronização de tempo
    syncTime() {
        if (!this.connected) {
            return;
        }
        
        this.send({
            type: 'sync_time',
            clientTime: this.getTimestamp()
        });
    }
    
    calculateLatency(data) {
        const now = this.getTimestamp();
        const roundTrip = now - data.clientTime;
        this.latency = roundTrip / 2;
        
        console.log(`Latência calculada: ${this.latency.toFixed(2)}ms`);
    }
    
    updateLatency(data) {
        this.latency = data.latency;
    }
    
    // Heartbeat para manter conexão viva
    startHeartbeat() {
        this.stopHeartbeat();
        
        this.heartbeatInterval = setInterval(() => {
            if (this.connected) {
                this.send({
                    type: 'heartbeat',
                    timestamp: this.getTimestamp()
                });
            }
        }, DAWConfig.COLLABORATION.HEARTBEAT_INTERVAL);
    }
    
    stopHeartbeat() {
        if (this.heartbeatInterval) {
            clearInterval(this.heartbeatInterval);
            this.heartbeatInterval = null;
        }
    }
    
    scheduleReconnect() {
        if (this.isReconnecting || this.reconnectAttempts >= this.maxReconnectAttempts) {
            return;
        }
        
        this.isReconnecting = true;
        this.reconnectAttempts++;
        
        const delay = this.reconnectDelay * Math.pow(2, this.reconnectAttempts - 1);
        
        console.log(`Tentativa de reconexão ${this.reconnectAttempts}/${this.maxReconnectAttempts} em ${delay}ms`);
        
        setTimeout(() => {
            this.connect();
        }, delay);
    }
    
    disconnect() {
        this.stopHeartbeat();
        
        if (this.ws) {
            this.ws.close(1000, 'Desconexão intencional');
        }
        
        this.connected = false;
        this.sessionId = null;
    }
    
    // Gerenciamento de eventos
    on(event, callback) {
        if (this.eventListeners[event]) {
            this.eventListeners[event].push(callback);
        }
    }
    
    off(event, callback) {
        if (this.eventListeners[event]) {
            const index = this.eventListeners[event].indexOf(callback);
            if (index > -1) {
                this.eventListeners[event].splice(index, 1);
            }
        }
    }
    
    emit(event, data = null) {
        if (this.eventListeners[event]) {
            this.eventListeners[event].forEach(callback => {
                try {
                    callback(data);
                } catch (error) {
                    console.error(`Erro no callback do evento ${event}:`, error);
                }
            });
        }
    }
    
    setupEventListeners() {
        // Eventos de autenticação
        window.addEventListener('userLoggedIn', () => {
            if (this.connected) {
                this.authenticate();
            }
        });
        
        window.addEventListener('userLoggedOut', () => {
            this.disconnect();
        });
        
        // Eventos de visibilidade da página
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                // Página ficou oculta
                this.stopHeartbeat();
            } else {
                // Página ficou visível
                if (this.connected) {
                    this.startHeartbeat();
                } else {
                    this.connect();
                }
            }
        });
        
        // Evento antes de fechar página
        window.addEventListener('beforeunload', () => {
            this.leaveSession();
            this.disconnect();
        });
    }
    
    // Utilitários
    getTimestamp() {
        return performance.now();
    }
    
    getLatency() {
        return this.latency;
    }
    
    isConnected() {
        return this.connected;
    }
    
    getSessionId() {
        return this.sessionId;
    }
    
    // Métodos para debugging
    getConnectionStats() {
        return {
            connected: this.connected,
            sessionId: this.sessionId,
            latency: this.latency,
            reconnectAttempts: this.reconnectAttempts,
            readyState: this.ws ? this.ws.readyState : null
        };
    }
}

// Instância global do cliente WebSocket
window.wsClient = new WebSocketClient();
