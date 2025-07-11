/**
 * Aplicação principal da DAW Online
 */

class DAWApplication {
    constructor() {
        this.currentProject = null;
        this.isReady = false;
        this.transportState = 'stopped';
        this.currentTime = 0;
        this.zoom = 1.0;
        this.gridSnap = true;
        this.selectedTracks = new Set();
        this.selectedRegions = new Set();
        
        this.init();
    }
    
    async init() {
        try {
            console.log('Inicializando DAW Online...');
            
            // Aguardar autenticação
            await this.waitForAuth();
            
            // Configurar interface
            this.setupUI();
            
            // Configurar eventos
            this.setupEventListeners();
            
            // Configurar atalhos de teclado
            this.setupKeyboardShortcuts();
            
            // Configurar WebSocket
            this.setupWebSocket();
            
            // Carregar projeto padrão ou último projeto
            await this.loadInitialProject();
            
            // Ocultar tela de carregamento
            this.hideLoadingScreen();
            
            this.isReady = true;
            console.log('DAW Online pronta!');
            
        } catch (error) {
            console.error('Erro ao inicializar DAW:', error);
            this.showError('Erro ao inicializar aplicação: ' + error.message);
        }
    }
    
    async waitForAuth() {
        return new Promise((resolve) => {
            if (authManager.isAuthenticated()) {
                resolve();
            } else {
                const checkAuth = () => {
                    if (authManager.isAuthenticated()) {
                        window.removeEventListener('userLoggedIn', checkAuth);
                        resolve();
                    }
                };
                window.addEventListener('userLoggedIn', checkAuth);
            }
        });
    }
    
    setupUI() {
        // Configurar transport controls
        this.setupTransportControls();
        
        // Configurar master controls
        this.setupMasterControls();
        
        // Configurar track area
        this.setupTrackArea();
        
        // Configurar sidebar
        this.setupSidebar();
        
        // Configurar timeline
        this.setupTimeline();
        
        // Configurar drag and drop
        this.setupDragAndDrop();
    }
    
    setupTransportControls() {
        $('#playBtn').on('click', () => this.togglePlayPause());
        $('#stopBtn').on('click', () => this.stop());
        $('#recordBtn').on('click', () => this.toggleRecord());
        $('#loopBtn').on('click', () => this.toggleLoop());
        
        $('#bpmInput').on('change', (e) => {
            const bpm = parseInt(e.target.value);
            if (bpm >= 60 && bpm <= 200) {
                audioEngine.setBPM(bpm);
            }
        });
        
        $('#metronomeBtn').on('click', () => this.toggleMetronome());
    }
    
    setupMasterControls() {
        $('#masterVolume').on('input', (e) => {
            const volume = parseInt(e.target.value);
            audioEngine.setMasterVolume(volume);
            $('.volume-label').text(volume);
        });
        
        // Iniciar medidores de nível
        this.startMasterMeters();
    }
    
    setupTrackArea() {
        $('#addTrackBtn').on('click', () => this.showAddTrackDialog());
        
        // Configurar área de tracks para scroll horizontal
        $('.tracks-area').on('scroll', this.syncTimelineScroll.bind(this));
    }
    
    setupSidebar() {
        // Browser tabs
        $('.browser-tab').on('click', (e) => {
            const tab = $(e.target).data('tab');
            this.switchBrowserTab(tab);
        });
        
        // Plugin drag
        $('.plugin-list li').on('dragstart', this.handlePluginDragStart.bind(this));
    }
    
    setupTimeline() {
        const canvas = document.getElementById('timelineCanvas');
        this.timelineCanvas = canvas;
        this.timelineCtx = canvas.getContext('2d');
        
        // Configurar eventos de mouse
        $(canvas).on('click', this.handleTimelineClick.bind(this));
        $(canvas).on('mousedown', this.handleTimelineMouseDown.bind(this));
        
        // Desenhar timeline inicial
        this.drawTimeline();
        
        // Atualizar timeline periodicamente
        setInterval(() => {
            if (this.transportState === 'playing') {
                this.updateCurrentTime();
                this.drawPlayhead();
            }
        }, 50);
    }
    
    setupDragAndDrop() {
        // Configurar drop zones
        $('.track-content').on('dragover', this.handleDragOver.bind(this));
        $('.track-content').on('drop', this.handleDrop.bind(this));
        
        // Configurar drag de regiões
        $(document).on('dragstart', '.audio-region', this.handleRegionDragStart.bind(this));
    }
    
    setupEventListeners() {
        // Eventos de janela
        $(window).on('resize', this.handleResize.bind(this));
        $(window).on('beforeunload', this.handleBeforeUnload.bind(this));
        
        // Eventos de menu
        $('#fileBtn').on('click', () => this.showFileMenu());
        $('#editBtn').on('click', () => this.showEditMenu());
        $('#viewBtn').on('click', () => this.showViewMenu());
        $('#toolsBtn').on('click', () => this.showToolsMenu());
        
        // Eventos de projeto
        $('#projectForm').on('submit', this.handleCreateProject.bind(this));
        
        // Eventos de colaboração
        $('#collaborateBtn').on('click', () => this.showCollaborationDialog());
    }
    
    setupKeyboardShortcuts() {
        $(document).on('keydown', (e) => {
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') {
                return; // Ignorar se estiver em campo de texto
            }
            
            const key = e.code;
            const ctrl = e.ctrlKey || e.metaKey;
            
            switch (key) {
                case 'Space':
                    e.preventDefault();
                    this.togglePlayPause();
                    break;
                    
                case 'Escape':
                    e.preventDefault();
                    this.stop();
                    break;
                    
                case 'KeyR':
                    if (!ctrl) {
                        e.preventDefault();
                        this.toggleRecord();
                    }
                    break;
                    
                case 'KeyS':
                    if (ctrl) {
                        e.preventDefault();
                        this.saveProject();
                    }
                    break;
                    
                case 'KeyZ':
                    if (ctrl && !e.shiftKey) {
                        e.preventDefault();
                        this.undo();
                    } else if (ctrl && e.shiftKey) {
                        e.preventDefault();
                        this.redo();
                    }
                    break;
                    
                case 'KeyT':
                    if (ctrl) {
                        e.preventDefault();
                        this.showAddTrackDialog();
                    }
                    break;
                    
                case 'Equal':
                    if (ctrl) {
                        e.preventDefault();
                        this.zoomIn();
                    }
                    break;
                    
                case 'Minus':
                    if (ctrl) {
                        e.preventDefault();
                        this.zoomOut();
                    }
                    break;
                    
                case 'Digit0':
                    if (ctrl) {
                        e.preventDefault();
                        this.zoomFit();
                    }
                    break;
            }
        });
    }
    
    setupWebSocket() {
        // Eventos de colaboração
        wsClient.on('sessionJoined', this.handleSessionJoined.bind(this));
        wsClient.on('userJoined', this.handleUserJoined.bind(this));
        wsClient.on('userLeft', this.handleUserLeft.bind(this));
        wsClient.on('transportUpdate', this.handleTransportUpdate.bind(this));
        wsClient.on('trackUpdate', this.handleTrackUpdate.bind(this));
        wsClient.on('pluginUpdate', this.handlePluginUpdate.bind(this));
    }
    
    // Transport controls
    async togglePlayPause() {
        if (this.transportState === 'playing') {
            this.pause();
        } else {
            await this.play();
        }
    }
    
    async play() {
        try {
            await audioEngine.play();
            this.transportState = 'playing';
            this.updateTransportUI();
        } catch (error) {
            this.showError('Erro ao iniciar reprodução: ' + error.message);
        }
    }
    
    pause() {
        audioEngine.pause();
        this.transportState = 'paused';
        this.updateTransportUI();
    }
    
    stop() {
        audioEngine.stop();
        this.transportState = 'stopped';
        this.currentTime = 0;
        this.updateTransportUI();
        this.updateTimeDisplay();
    }
    
    async toggleRecord() {
        if (this.transportState === 'recording') {
            this.stop();
        } else {
            try {
                await audioEngine.record();
                this.transportState = 'recording';
                this.updateTransportUI();
            } catch (error) {
                this.showError('Erro ao iniciar gravação: ' + error.message);
            }
        }
    }
    
    toggleLoop() {
        // TODO: Implementar loop
        $('#loopBtn').toggleClass('active');
    }
    
    toggleMetronome() {
        const enabled = !audioEngine.isMetronomeEnabled();
        audioEngine.setMetronomeEnabled(enabled);
        $('#metronomeBtn').toggleClass('active', enabled);
    }
    
    updateTransportUI() {
        // Atualizar botões
        $('#playBtn i').removeClass('fa-play fa-pause').addClass(
            this.transportState === 'playing' ? 'fa-pause' : 'fa-play'
        );
        
        $('#playBtn').toggleClass('active', this.transportState === 'playing');
        $('#recordBtn').toggleClass('active', this.transportState === 'recording');
        $('#stopBtn').removeClass('active');
    }
    
    updateCurrentTime() {
        if (this.transportState === 'playing') {
            this.currentTime += 0.05; // 50ms increment
        }
        this.updateTimeDisplay();
    }
    
    updateTimeDisplay() {
        const formatTime = (seconds) => {
            const mins = Math.floor(seconds / 60);
            const secs = Math.floor(seconds % 60);
            const centisecs = Math.floor((seconds % 1) * 100);
            return `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}:${centisecs.toString().padStart(2, '0')}`;
        };
        
        $('#currentTime').text(formatTime(this.currentTime));
    }
    
    // Master controls
    startMasterMeters() {
        setInterval(() => {
            const levels = audioEngine.getMasterLevels();
            
            $('#masterMeterL').css('--meter-level', levels.left + '%');
            $('#masterMeterR').css('--meter-level', levels.right + '%');
        }, 50);
    }
    
    // Project management
    async loadInitialProject() {
        try {
            // Tentar carregar último projeto ou criar novo
            const projects = await api.getProjects(1, 0);
            
            if (projects.projects && projects.projects.length > 0) {
                await this.loadProject(projects.projects[0].id);
            } else {
                await this.createDefaultProject();
            }
        } catch (error) {
            console.error('Erro ao carregar projeto inicial:', error);
            await this.createDefaultProject();
        }
    }
    
    async createDefaultProject() {
        const projectData = {
            nome: 'Novo Projeto',
            descricao: 'Projeto criado automaticamente',
            bpm: 120,
            tonalidade: 'C'
        };
        
        try {
            const result = await api.createProject(projectData);
            this.currentProject = result.project;
            this.setupProject();
        } catch (error) {
            console.error('Erro ao criar projeto padrão:', error);
        }
    }
    
    async loadProject(projectId) {
        try {
            const result = await api.getProject(projectId);
            this.currentProject = result.project;
            this.setupProject();
            
            // Unir sessão de colaboração
            if (wsClient.isConnected()) {
                wsClient.joinSession(`project_${projectId}`, projectId);
            }
            
        } catch (error) {
            console.error('Erro ao carregar projeto:', error);
            this.showError('Erro ao carregar projeto: ' + error.message);
        }
    }
    
    setupProject() {
        if (!this.currentProject) return;
        
        // Configurar BPM
        audioEngine.setBPM(this.currentProject.bpm);
        $('#bpmInput').val(this.currentProject.bpm);
        
        // Criar faixas
        this.createTracks();
        
        // Atualizar timeline
        this.drawTimeline();
        
        console.log('Projeto configurado:', this.currentProject.nome);
    }
    
    createTracks() {
        const container = $('#trackHeadersContainer');
        const tracksArea = $('#tracksArea');
        
        container.empty();
        tracksArea.empty();
        
        if (!this.currentProject.faixas) return;
        
        this.currentProject.faixas.forEach(trackData => {
            this.createTrackElements(trackData);
            
            // Criar track no audio engine
            const audioTrack = audioEngine.createTrack(trackData.id, trackData.tipo);
            audioTrack.setVolume(trackData.volume);
            audioTrack.setPan(trackData.pan);
            audioTrack.setMute(trackData.mute);
        });
    }
    
    createTrackElements(trackData) {
        // Header da track
        const header = $(`
            <div class="track-header" data-track-id="${trackData.id}">
                <div class="track-info">
                    <input type="text" class="track-name" value="${trackData.nome}">
                    <span class="track-type">${trackData.tipo}</span>
                </div>
                <div class="track-controls">
                    <button class="track-control-btn mute ${trackData.mute ? 'active' : ''}">M</button>
                    <button class="track-control-btn solo">S</button>
                    <button class="track-control-btn record">R</button>
                </div>
                <div class="track-volume">
                    <input type="range" class="track-volume-slider" min="0" max="100" value="${trackData.volume}">
                    <span class="track-volume-value">${trackData.volume}</span>
                </div>
            </div>
        `);
        
        // Área de conteúdo da track
        const content = $(`
            <div class="track" data-track-id="${trackData.id}">
                <div class="track-content">
                    <!-- Regiões serão adicionadas aqui -->
                </div>
            </div>
        `);
        
        // Adicionar eventos
        this.setupTrackEvents(header, content, trackData);
        
        $('#trackHeadersContainer').append(header);
        $('#tracksArea').append(content);
        
        // Criar regiões se existirem
        if (trackData.regioes) {
            trackData.regioes.forEach(regionData => {
                this.createRegionElement(content.find('.track-content'), regionData);
            });
        }
    }
    
    setupTrackEvents(header, content, trackData) {
        const trackId = trackData.id;
        
        // Controles de track
        header.find('.mute').on('click', (e) => {
            const muted = !$(e.target).hasClass('active');
            $(e.target).toggleClass('active', muted);
            audioEngine.getTrack(trackId)?.setMute(muted);
        });
        
        header.find('.solo').on('click', (e) => {
            const soloed = !$(e.target).hasClass('active');
            $(e.target).toggleClass('active', soloed);
            audioEngine.getTrack(trackId)?.setSolo(soloed);
        });
        
        header.find('.record').on('click', (e) => {
            $(e.target).toggleClass('active');
        });
        
        // Volume
        header.find('.track-volume-slider').on('input', (e) => {
            const volume = parseInt(e.target.value);
            audioEngine.getTrack(trackId)?.setVolume(volume);
            header.find('.track-volume-value').text(volume);
        });
        
        // Nome da track
        header.find('.track-name').on('change', (e) => {
            // TODO: Salvar nome da track
        });
    }
    
    createRegionElement(container, regionData) {
        const pixelsPerSecond = DAWConfig.TIMELINE.PIXELS_PER_SECOND * this.zoom;
        const left = regionData.inicio_tempo * pixelsPerSecond;
        const width = regionData.duracao * pixelsPerSecond;
        
        const region = $(`
            <div class="audio-region ${regionData.tipo}" 
                 data-region-id="${regionData.id}"
                 style="left: ${left}px; width: ${width}px;">
                <div class="region-waveform"></div>
                <div class="region-name">${regionData.nome || 'Audio'}</div>
                <div class="region-handles left"></div>
                <div class="region-handles right"></div>
            </div>
        `);
        
        container.append(region);
        
        // Adicionar eventos de interação
        this.setupRegionEvents(region, regionData);
    }
    
    setupRegionEvents(element, regionData) {
        element.on('click', () => {
            this.selectRegion(element);
        });
        
        // TODO: Implementar redimensionamento e movimentação
    }
    
    // Timeline
    drawTimeline() {
        if (!this.timelineCtx) return;
        
        const canvas = this.timelineCanvas;
        const ctx = this.timelineCtx;
        
        // Limpar canvas
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        
        // Configurações
        const pixelsPerSecond = DAWConfig.TIMELINE.PIXELS_PER_SECOND * this.zoom;
        const beatsPerSecond = audioEngine.getBPM() / 60;
        const pixelsPerBeat = pixelsPerSecond / beatsPerSecond;
        
        // Desenhar marcadores
        ctx.strokeStyle = '#555';
        ctx.fillStyle = '#ccc';
        ctx.font = '10px monospace';
        
        const visibleSeconds = canvas.width / pixelsPerSecond;
        
        for (let i = 0; i <= visibleSeconds * beatsPerSecond; i++) {
            const x = i * pixelsPerBeat;
            const isMeasure = i % 4 === 0;
            
            // Linha
            ctx.beginPath();
            ctx.moveTo(x, isMeasure ? 0 : 15);
            ctx.lineTo(x, 30);
            ctx.lineWidth = isMeasure ? 2 : 1;
            ctx.stroke();
            
            // Texto
            if (isMeasure) {
                const measure = Math.floor(i / 4) + 1;
                ctx.fillText(measure.toString(), x + 2, 12);
            }
        }
        
        this.drawPlayhead();
    }
    
    drawPlayhead() {
        if (!this.timelineCtx) return;
        
        const canvas = this.timelineCanvas;
        const ctx = this.timelineCtx;
        const pixelsPerSecond = DAWConfig.TIMELINE.PIXELS_PER_SECOND * this.zoom;
        const x = this.currentTime * pixelsPerSecond;
        
        // Redesenhar apenas a área do playhead
        const width = 4;
        ctx.clearRect(x - width/2, 0, width, canvas.height);
        
        // Redesenhar grid na área
        // TODO: Otimizar redraw
        
        // Desenhar playhead
        ctx.fillStyle = '#e74c3c';
        ctx.fillRect(x - 1, 0, 2, canvas.height);
    }
    
    handleTimelineClick(e) {
        const rect = this.timelineCanvas.getBoundingClientRect();
        const x = e.clientX - rect.left;
        const pixelsPerSecond = DAWConfig.TIMELINE.PIXELS_PER_SECOND * this.zoom;
        const time = x / pixelsPerSecond;
        
        this.currentTime = Math.max(0, time);
        this.updateTimeDisplay();
        this.drawPlayhead();
        
        // Se estiver tocando, buscar para nova posição
        if (this.transportState === 'playing') {
            // TODO: Implementar seek
        }
    }
    
    // Colaboração
    handleSessionJoined(data) {
        console.log('Sessão de colaboração iniciada:', data);
        this.updateCollaboratorCount(data.session.participants.length);
    }
    
    handleUserJoined(data) {
        console.log('Usuário entrou na sessão:', data.user.username);
        this.updateCollaboratorCount(data.participants.length);
    }
    
    handleUserLeft(data) {
        console.log('Usuário saiu da sessão:', data.userId);
        this.updateCollaboratorCount(data.participants.length);
    }
    
    handleTransportUpdate(data) {
        // Sincronizar estado de transport
        if (data.transport.state !== this.transportState) {
            this.transportState = data.transport.state;
            this.updateTransportUI();
        }
        
        if (data.transport.bpm !== audioEngine.getBPM()) {
            audioEngine.setBPM(data.transport.bpm);
            $('#bpmInput').val(data.transport.bpm);
        }
    }
    
    handleTrackUpdate(data) {
        // TODO: Atualizar tracks baseado em mudanças remotas
        console.log('Track atualizada remotamente:', data);
    }
    
    handlePluginUpdate(data) {
        // TODO: Atualizar plugins baseado em mudanças remotas
        console.log('Plugin atualizado remotamente:', data);
    }
    
    updateCollaboratorCount(count) {
        $('#collaboratorCount').text(count);
    }
    
    // Utility methods
    showError(message) {
        // TODO: Implementar sistema de notificações
        alert(message);
    }
    
    hideLoadingScreen() {
        $('#loadingScreen').fadeOut(500);
    }
    
    handleResize() {
        // TODO: Ajustar layout responsivo
    }
    
    handleBeforeUnload(e) {
        if (this.hasUnsavedChanges()) {
            e.preventDefault();
            e.returnValue = 'Você tem alterações não salvas. Deseja sair mesmo assim?';
        }
    }
    
    hasUnsavedChanges() {
        // TODO: Verificar se há alterações não salvas
        return false;
    }
}

// Inicializar aplicação quando o DOM estiver pronto
$(document).ready(() => {
    window.dawApp = new DAWApplication();
});
