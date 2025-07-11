/**
 * Configuração do frontend da DAW Online
 */

const DAWConfig = {
    // URLs da API
    API_BASE_URL: 'http://localhost/daw/backend/api',
    WEBSOCKET_URL: 'ws://localhost:8080',
    
    // Configurações de áudio
    AUDIO: {
        SAMPLE_RATE: 44100,
        BUFFER_SIZE: 512,
        BIT_DEPTH: 24,
        MAX_TRACKS: 128,
        MAX_PLUGINS_PER_TRACK: 16
    },
    
    // Configurações de timeline
    TIMELINE: {
        PIXELS_PER_SECOND: 100,
        GRID_RESOLUTION: 16, // 16th notes
        SNAP_TO_GRID: true,
        AUTO_SCROLL: true
    },
    
    // Configurações de colaboração
    COLLABORATION: {
        HEARTBEAT_INTERVAL: 5000, // ms
        SYNC_INTERVAL: 100, // ms
        MAX_LATENCY: 200 // ms
    },
    
    // Configurações de UI
    UI: {
        ANIMATION_DURATION: 200,
        DEBOUNCE_DELAY: 300,
        DOUBLE_CLICK_DELAY: 300,
        CONTEXT_MENU_DELAY: 500
    },
    
    // Formatos de áudio suportados
    SUPPORTED_FORMATS: [
        'audio/wav',
        'audio/aiff',
        'audio/mp3',
        'audio/flac',
        'audio/ogg'
    ],
    
    // Cores para faixas
    TRACK_COLORS: [
        '#e74c3c', '#3498db', '#2ecc71', '#f39c12',
        '#9b59b6', '#1abc9c', '#34495e', '#e67e22',
        '#95a5a6', '#16a085', '#27ae60', '#2980b9',
        '#8e44ad', '#2c3e50', '#f1c40f', '#d35400'
    ],
    
    // Configurações de zoom
    ZOOM: {
        MIN_LEVEL: 0.1,
        MAX_LEVEL: 10.0,
        DEFAULT_LEVEL: 1.0,
        STEP: 0.1
    },
    
    // Configurações de plugin
    PLUGIN: {
        SCAN_PATHS: [
            '/plugins/vst',
            '/plugins/vst3',
            '/plugins/au',
            '/plugins/aax'
        ],
        DEFAULT_WINDOW_SIZE: {
            width: 400,
            height: 300
        }
    },
    
    // Configurações de gravação
    RECORDING: {
        AUTO_PUNCH: false,
        COUNT_IN: 1, // bars
        METRONOME_ENABLED: false,
        CLICK_TRACK_ENABLED: false
    },
    
    // Configurações de exportação
    EXPORT: {
        DEFAULT_FORMAT: 'wav',
        DEFAULT_QUALITY: 'high',
        AVAILABLE_FORMATS: [
            { value: 'wav', label: 'WAV (PCM)', extension: '.wav' },
            { value: 'mp3', label: 'MP3', extension: '.mp3' },
            { value: 'flac', label: 'FLAC', extension: '.flac' },
            { value: 'ogg', label: 'OGG Vorbis', extension: '.ogg' }
        ]
    },
    
    // Mensagens de erro
    ERROR_MESSAGES: {
        NETWORK_ERROR: 'Erro de conexão com o servidor',
        AUTH_FAILED: 'Falha na autenticação',
        PROJECT_LOAD_FAILED: 'Erro ao carregar projeto',
        AUDIO_INIT_FAILED: 'Erro ao inicializar sistema de áudio',
        PLUGIN_LOAD_FAILED: 'Erro ao carregar plugin',
        SAVE_FAILED: 'Erro ao salvar projeto',
        EXPORT_FAILED: 'Erro ao exportar áudio'
    },
    
    // Teclas de atalho
    KEYBOARD_SHORTCUTS: {
        PLAY_PAUSE: 'Space',
        STOP: 'Escape',
        RECORD: 'R',
        SAVE: 'Ctrl+S',
        UNDO: 'Ctrl+Z',
        REDO: 'Ctrl+Y',
        CUT: 'Ctrl+X',
        COPY: 'Ctrl+C',
        PASTE: 'Ctrl+V',
        DELETE: 'Delete',
        SELECT_ALL: 'Ctrl+A',
        ZOOM_IN: 'Ctrl+=',
        ZOOM_OUT: 'Ctrl+-',
        ZOOM_FIT: 'Ctrl+0',
        NEW_TRACK: 'Ctrl+T',
        DUPLICATE_TRACK: 'Ctrl+D'
    },
    
    // Configurações de display
    DISPLAY: {
        WAVEFORM_COLOR: '#3498db',
        WAVEFORM_BACKGROUND: '#2c3e50',
        SELECTION_COLOR: 'rgba(52, 152, 219, 0.3)',
        PLAYHEAD_COLOR: '#e74c3c',
        GRID_COLOR: '#555555'
    }
};

// Função para obter configuração aninhada
DAWConfig.get = function(path, defaultValue = null) {
    const keys = path.split('.');
    let current = this;
    
    for (const key of keys) {
        if (current[key] === undefined) {
            return defaultValue;
        }
        current = current[key];
    }
    
    return current;
};

// Função para definir configuração aninhada
DAWConfig.set = function(path, value) {
    const keys = path.split('.');
    const lastKey = keys.pop();
    let current = this;
    
    for (const key of keys) {
        if (current[key] === undefined) {
            current[key] = {};
        }
        current = current[key];
    }
    
    current[lastKey] = value;
};

// Detectar ambiente
DAWConfig.ENVIRONMENT = {
    IS_DEVELOPMENT: window.location.hostname === 'localhost',
    IS_MOBILE: window.innerWidth <= 768,
    HAS_TOUCH: 'ontouchstart' in window,
    SUPPORTS_WEB_AUDIO: !!(window.AudioContext || window.webkitAudioContext),
    SUPPORTS_WEBSOCKET: !!window.WebSocket,
    SUPPORTS_WEBRTC: !!(window.RTCPeerConnection || window.webkitRTCPeerConnection)
};

// Ajustar URLs para desenvolvimento
if (DAWConfig.ENVIRONMENT.IS_DEVELOPMENT) {
    DAWConfig.API_BASE_URL = 'http://localhost:8000/backend/api';
    DAWConfig.WEBSOCKET_URL = 'ws://localhost:8080';
}

// Validações de suporte
if (!DAWConfig.ENVIRONMENT.SUPPORTS_WEB_AUDIO) {
    console.error('Web Audio API não suportada neste navegador');
}

if (!DAWConfig.ENVIRONMENT.SUPPORTS_WEBSOCKET) {
    console.error('WebSocket não suportado neste navegador');
}

// Exportar configuração
window.DAWConfig = DAWConfig;
