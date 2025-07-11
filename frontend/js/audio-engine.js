/**
 * Engine de áudio usando Web Audio API
 */

class AudioEngine {
    constructor() {
        this.audioContext = null;
        this.masterGain = null;
        this.compressor = null;
        this.analyser = null;
        this.metronome = null;
        this.isInitialized = false;
        this.isPlaying = false;
        this.isRecording = false;
        this.currentTime = 0;
        this.bpm = 120;
        this.sampleRate = 44100;
        this.bufferSize = 512;
        
        this.tracks = new Map();
        this.audioBuffers = new Map();
        this.recordingStreams = new Map();
        
        this.init();
    }
    
    async init() {
        try {
            // Inicializar contexto de áudio
            const AudioContext = window.AudioContext || window.webkitAudioContext;
            this.audioContext = new AudioContext({
                sampleRate: this.sampleRate,
                latencyHint: 'interactive'
            });
            
            // Criar cadeia de áudio master
            this.setupMasterChain();
            
            // Configurar metronome
            this.setupMetronome();
            
            this.isInitialized = true;
            console.log('Audio Engine inicializado:', {
                sampleRate: this.audioContext.sampleRate,
                state: this.audioContext.state
            });
            
        } catch (error) {
            console.error('Erro ao inicializar Audio Engine:', error);
            throw new Error(DAWConfig.ERROR_MESSAGES.AUDIO_INIT_FAILED);
        }
    }
    
    setupMasterChain() {
        // Gain master
        this.masterGain = this.audioContext.createGain();
        this.masterGain.gain.value = 0.7;
        
        // Compressor master
        this.compressor = this.audioContext.createDynamicsCompressor();
        this.compressor.threshold.value = -24;
        this.compressor.knee.value = 30;
        this.compressor.ratio.value = 12;
        this.compressor.attack.value = 0.003;
        this.compressor.release.value = 0.25;
        
        // Analyser para meters
        this.analyser = this.audioContext.createAnalyser();
        this.analyser.fftSize = 256;
        this.analyser.smoothingTimeConstant = 0.8;
        
        // Conectar cadeia
        this.masterGain.connect(this.compressor);
        this.compressor.connect(this.analyser);
        this.analyser.connect(this.audioContext.destination);
    }
    
    setupMetronome() {
        this.metronome = {
            enabled: false,
            gain: this.audioContext.createGain(),
            oscillator: null,
            lastBeat: 0
        };
        
        this.metronome.gain.gain.value = 0.1;
        this.metronome.gain.connect(this.masterGain);
    }
    
    // Controle de transporte
    async play() {
        if (!this.isInitialized) {
            await this.init();
        }
        
        // Retomar contexto se suspenso
        if (this.audioContext.state === 'suspended') {
            await this.audioContext.resume();
        }
        
        this.isPlaying = true;
        this.startTime = this.audioContext.currentTime;
        
        // Iniciar todas as faixas
        for (const track of this.tracks.values()) {
            track.play(this.currentTime);
        }
        
        // Iniciar metronome se habilitado
        if (this.metronome.enabled) {
            this.startMetronome();
        }
        
        // Sincronizar com outros usuários
        wsClient.syncTransport({
            state: 'playing',
            position: this.currentTime,
            bpm: this.bpm,
            timestamp: performance.now()
        });
        
        console.log('Reprodução iniciada');
    }
    
    stop() {
        this.isPlaying = false;
        this.isRecording = false;
        this.currentTime = 0;
        
        // Parar todas as faixas
        for (const track of this.tracks.values()) {
            track.stop();
        }
        
        // Parar metronome
        this.stopMetronome();
        
        // Sincronizar com outros usuários
        wsClient.syncTransport({
            state: 'stopped',
            position: this.currentTime,
            bpm: this.bpm,
            timestamp: performance.now()
        });
        
        console.log('Reprodução parada');
    }
    
    pause() {
        this.isPlaying = false;
        
        // Pausar todas as faixas
        for (const track of this.tracks.values()) {
            track.pause();
        }
        
        // Parar metronome
        this.stopMetronome();
        
        // Sincronizar com outros usuários
        wsClient.syncTransport({
            state: 'paused',
            position: this.currentTime,
            bpm: this.bpm,
            timestamp: performance.now()
        });
        
        console.log('Reprodução pausada');
    }
    
    async record() {
        if (!this.isInitialized) {
            await this.init();
        }
        
        try {
            // Solicitar acesso ao microfone
            const stream = await navigator.mediaDevices.getUserMedia({
                audio: {
                    sampleRate: this.sampleRate,
                    echoCancellation: false,
                    noiseSuppression: false,
                    autoGainControl: false
                }
            });
            
            this.isRecording = true;
            this.isPlaying = true;
            
            // Criar MediaStreamSource
            const source = this.audioContext.createMediaStreamSource(stream);
            
            // TODO: Implementar gravação em faixas específicas
            
            // Iniciar reprodução junto com gravação
            this.play();
            
            console.log('Gravação iniciada');
            
        } catch (error) {
            console.error('Erro ao iniciar gravação:', error);
            throw new Error('Erro ao acessar microfone');
        }
    }
    
    // Metronome
    startMetronome() {
        if (!this.metronome.enabled) return;
        
        const beatInterval = 60 / this.bpm; // segundos por beat
        const scheduleAhead = 0.1; // segundos
        
        const scheduler = () => {
            const currentTime = this.audioContext.currentTime;
            
            while (this.metronome.lastBeat < currentTime + scheduleAhead) {
                this.playClick(this.metronome.lastBeat);
                this.metronome.lastBeat += beatInterval;
            }
            
            if (this.isPlaying && this.metronome.enabled) {
                setTimeout(scheduler, 25); // 25ms
            }
        };
        
        this.metronome.lastBeat = this.audioContext.currentTime;
        scheduler();
    }
    
    playClick(time) {
        const oscillator = this.audioContext.createOscillator();
        const envelope = this.audioContext.createGain();
        
        oscillator.frequency.value = 800;
        envelope.gain.value = 0;
        
        oscillator.connect(envelope);
        envelope.connect(this.metronome.gain);
        
        // Envelope de ataque e release
        envelope.gain.setValueAtTime(0, time);
        envelope.gain.linearRampToValueAtTime(0.2, time + 0.01);
        envelope.gain.exponentialRampToValueAtTime(0.001, time + 0.1);
        
        oscillator.start(time);
        oscillator.stop(time + 0.1);
    }
    
    stopMetronome() {
        this.metronome.enabled = false;
    }
    
    // Controle de faixas
    createTrack(id, type = 'audio') {
        const track = new AudioTrack(id, type, this.audioContext, this.masterGain);
        this.tracks.set(id, track);
        return track;
    }
    
    removeTrack(id) {
        const track = this.tracks.get(id);
        if (track) {
            track.destroy();
            this.tracks.delete(id);
        }
    }
    
    getTrack(id) {
        return this.tracks.get(id);
    }
    
    // Carregamento de áudio
    async loadAudioFile(file) {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            
            reader.onload = async (e) => {
                try {
                    const arrayBuffer = e.target.result;
                    const audioBuffer = await this.audioContext.decodeAudioData(arrayBuffer);
                    
                    const id = 'audio_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
                    this.audioBuffers.set(id, audioBuffer);
                    
                    resolve({
                        id: id,
                        buffer: audioBuffer,
                        duration: audioBuffer.duration,
                        sampleRate: audioBuffer.sampleRate,
                        numberOfChannels: audioBuffer.numberOfChannels
                    });
                    
                } catch (error) {
                    reject(new Error('Erro ao decodificar arquivo de áudio'));
                }
            };
            
            reader.onerror = () => {
                reject(new Error('Erro ao ler arquivo'));
            };
            
            reader.readAsArrayBuffer(file);
        });
    }
    
    getAudioBuffer(id) {
        return this.audioBuffers.get(id);
    }
    
    // Controles de master
    setMasterVolume(volume) {
        if (this.masterGain) {
            // Conversão linear para logarítmica
            const gain = volume === 0 ? 0 : Math.pow(volume / 100, 2);
            this.masterGain.gain.setTargetAtTime(gain, this.audioContext.currentTime, 0.01);
        }
    }
    
    getMasterVolume() {
        return this.masterGain ? Math.sqrt(this.masterGain.gain.value) * 100 : 0;
    }
    
    // Análise de áudio
    getMasterLevels() {
        if (!this.analyser) return { left: 0, right: 0 };
        
        const bufferLength = this.analyser.frequencyBinCount;
        const dataArray = new Uint8Array(bufferLength);
        this.analyser.getByteFrequencyData(dataArray);
        
        // Calcular nível RMS
        let sum = 0;
        for (let i = 0; i < bufferLength; i++) {
            sum += dataArray[i] * dataArray[i];
        }
        
        const rms = Math.sqrt(sum / bufferLength);
        const level = (rms / 255) * 100;
        
        // Por enquanto, retornar mesmo nível para L/R
        return { left: level, right: level };
    }
    
    // Configurações
    setBPM(bpm) {
        this.bpm = Math.max(60, Math.min(200, bpm));
        
        // Sincronizar com outros usuários
        wsClient.syncTransport({
            bpm: this.bpm,
            timestamp: performance.now()
        });
    }
    
    getBPM() {
        return this.bpm;
    }
    
    setMetronomeEnabled(enabled) {
        this.metronome.enabled = enabled;
        
        if (!enabled && this.isPlaying) {
            this.stopMetronome();
        } else if (enabled && this.isPlaying) {
            this.startMetronome();
        }
    }
    
    isMetronomeEnabled() {
        return this.metronome.enabled;
    }
    
    // Informações do sistema
    getAudioInfo() {
        return {
            sampleRate: this.audioContext ? this.audioContext.sampleRate : 0,
            currentTime: this.audioContext ? this.audioContext.currentTime : 0,
            state: this.audioContext ? this.audioContext.state : 'closed',
            baseLatency: this.audioContext ? this.audioContext.baseLatency : 0,
            outputLatency: this.audioContext ? this.audioContext.outputLatency : 0
        };
    }
    
    // Cleanup
    destroy() {
        this.stop();
        
        // Parar todas as faixas
        for (const track of this.tracks.values()) {
            track.destroy();
        }
        this.tracks.clear();
        
        // Fechar contexto de áudio
        if (this.audioContext) {
            this.audioContext.close();
        }
        
        this.isInitialized = false;
        console.log('Audio Engine destruído');
    }
}

// Classe para faixas de áudio individuais
class AudioTrack {
    constructor(id, type, audioContext, destination) {
        this.id = id;
        this.type = type; // 'audio' ou 'midi'
        this.audioContext = audioContext;
        this.destination = destination;
        
        this.gain = audioContext.createGain();
        this.gain.gain.value = 1.0;
        
        this.panner = audioContext.createStereoPanner();
        this.panner.pan.value = 0;
        
        this.muted = false;
        this.soloed = false;
        this.volume = 100;
        this.pan = 0;
        
        this.regions = [];
        this.plugins = [];
        
        // Conectar cadeia de áudio
        this.gain.connect(this.panner);
        this.panner.connect(destination);
    }
    
    // Controle de reprodução
    play(startTime = 0) {
        this.regions.forEach(region => {
            region.play(startTime);
        });
    }
    
    stop() {
        this.regions.forEach(region => {
            region.stop();
        });
    }
    
    pause() {
        this.regions.forEach(region => {
            region.pause();
        });
    }
    
    // Controles de volume e pan
    setVolume(volume) {
        this.volume = Math.max(0, Math.min(100, volume));
        const gain = this.volume === 0 ? 0 : Math.pow(this.volume / 100, 2);
        this.gain.gain.setTargetAtTime(gain, this.audioContext.currentTime, 0.01);
    }
    
    getVolume() {
        return this.volume;
    }
    
    setPan(pan) {
        this.pan = Math.max(-100, Math.min(100, pan));
        this.panner.pan.setTargetAtTime(this.pan / 100, this.audioContext.currentTime, 0.01);
    }
    
    getPan() {
        return this.pan;
    }
    
    setMute(muted) {
        this.muted = muted;
        this.gain.gain.setTargetAtTime(muted ? 0 : this.volume / 100, this.audioContext.currentTime, 0.01);
    }
    
    isMuted() {
        return this.muted;
    }
    
    setSolo(soloed) {
        this.soloed = soloed;
        // TODO: Implementar lógica de solo no nível do engine
    }
    
    isSoloed() {
        return this.soloed;
    }
    
    // Regiões de áudio
    addRegion(audioBuffer, startTime, duration) {
        const region = new AudioRegion(audioBuffer, startTime, duration, this.audioContext);
        region.connect(this.gain);
        this.regions.push(region);
        return region;
    }
    
    removeRegion(region) {
        const index = this.regions.indexOf(region);
        if (index > -1) {
            region.disconnect();
            this.regions.splice(index, 1);
        }
    }
    
    // Plugins
    addPlugin(plugin) {
        this.plugins.push(plugin);
        this.rebuildPluginChain();
    }
    
    removePlugin(plugin) {
        const index = this.plugins.indexOf(plugin);
        if (index > -1) {
            this.plugins.splice(index, 1);
            this.rebuildPluginChain();
        }
    }
    
    rebuildPluginChain() {
        // TODO: Implementar cadeia de plugins
        // Por enquanto, conexão direta
    }
    
    // Cleanup
    destroy() {
        this.stop();
        this.regions.forEach(region => region.disconnect());
        this.gain.disconnect();
        this.panner.disconnect();
    }
}

// Classe para regiões de áudio
class AudioRegion {
    constructor(audioBuffer, startTime, duration, audioContext) {
        this.audioBuffer = audioBuffer;
        this.startTime = startTime;
        this.duration = duration;
        this.audioContext = audioContext;
        this.source = null;
        this.isPlaying = false;
    }
    
    connect(destination) {
        this.destination = destination;
    }
    
    disconnect() {
        if (this.source) {
            this.source.disconnect();
        }
    }
    
    play(offset = 0) {
        this.stop(); // Parar qualquer reprodução anterior
        
        this.source = this.audioContext.createBufferSource();
        this.source.buffer = this.audioBuffer;
        this.source.connect(this.destination);
        
        const when = this.audioContext.currentTime;
        const offsetTime = Math.max(0, offset - this.startTime);
        
        if (offsetTime < this.duration) {
            this.source.start(when, offsetTime, this.duration - offsetTime);
            this.isPlaying = true;
            
            this.source.onended = () => {
                this.isPlaying = false;
            };
        }
    }
    
    stop() {
        if (this.source && this.isPlaying) {
            this.source.stop();
            this.source.disconnect();
            this.isPlaying = false;
        }
    }
    
    pause() {
        this.stop();
    }
}

// Instância global do engine de áudio
window.audioEngine = new AudioEngine();
