# Plugin Development - DAW Online

## Visão Geral

A DAW Online suporta plugins de áudio através de uma arquitetura modular que permite integração com VST, AU e AAX plugins. O sistema utiliza uma camada de abstração que converte os plugins nativos para uso na web através da Web Audio API.

## Arquitetura de Plugins

### Tipos Suportados

1. **VST 2.4**: Virtual Studio Technology (Windows/macOS/Linux)
2. **VST 3**: Versão mais recente do VST (Windows/macOS/Linux)  
3. **AU**: Audio Units (macOS apenas)
4. **AAX**: Avid Audio eXtension (Windows/macOS com Pro Tools)
5. **Web Audio**: Plugins nativos JavaScript/WebAssembly

### Camada de Abstração

```
Plugin Nativo (VST/AU/AAX)
         ↓
   Middleware ASIO
         ↓
   Bridge JavaScript
         ↓
    Web Audio API
         ↓
    Interface DAW
```

## Estrutura de um Plugin

### Manifesto do Plugin (plugin.json)

```json
{
    "id": "eq_eight",
    "nome": "EQ Eight",
    "fabricante": "DAW Online",
    "versao": "1.0.0",
    "tipo": "vst3",
    "categoria": "efeito",
    "subcategoria": "eq",
    "descricao": "Equalizador de 8 bandas profissional",
    "arquivo_principal": "eq_eight.vst3",
    "arquivo_interface": "eq_eight_ui.js",
    "suporte_tempo_real": true,
    "latencia": 0,
    "entrada_canais": 2,
    "saida_canais": 2,
    "midi_input": false,
    "midi_output": false,
    "presets": [
        {
            "nome": "Padrão",
            "arquivo": "presets/default.json"
        },
        {
            "nome": "Vocal Boost",
            "arquivo": "presets/vocal_boost.json"
        }
    ],
    "parametros": [
        {
            "id": "band1_freq",
            "nome": "Banda 1 - Frequência",
            "tipo": "float",
            "min": 20,
            "max": 20000,
            "default": 100,
            "unidade": "Hz",
            "escala": "logaritmica"
        },
        {
            "id": "band1_gain",
            "nome": "Banda 1 - Ganho",
            "tipo": "float",
            "min": -18,
            "max": 18,
            "default": 0,
            "unidade": "dB",
            "escala": "linear"
        }
    ],
    "compatibilidade": {
        "sistemas": ["windows", "macos", "linux"],
        "navegadores": ["chrome", "firefox", "safari", "edge"],
        "versao_minima": {
            "chrome": 90,
            "firefox": 88,
            "safari": 14,
            "edge": 90
        }
    }
}
```

### Interface do Plugin (JavaScript)

```javascript
class EQEightPlugin {
    constructor(audioContext, pluginId) {
        this.audioContext = audioContext;
        this.pluginId = pluginId;
        this.initialized = false;
        
        // Nodes Web Audio API
        this.inputNode = audioContext.createGain();
        this.outputNode = audioContext.createGain();
        this.eqBands = [];
        
        // Parâmetros
        this.parameters = new Map();
        
        this.init();
    }
    
    async init() {
        // Criar 8 bandas de EQ
        for (let i = 0; i < 8; i++) {
            const filter = this.audioContext.createBiquadFilter();
            filter.type = 'peaking';
            filter.frequency.value = this.getDefaultFrequency(i);
            filter.Q.value = 1.0;
            filter.gain.value = 0;
            
            this.eqBands.push(filter);
            
            // Conectar em série
            if (i === 0) {
                this.inputNode.connect(filter);
            } else {
                this.eqBands[i-1].connect(filter);
            }
        }
        
        // Conectar última banda à saída
        this.eqBands[7].connect(this.outputNode);
        
        // Registrar parâmetros
        this.registerParameters();
        
        this.initialized = true;
    }
    
    registerParameters() {
        for (let i = 0; i < 8; i++) {
            this.parameters.set(`band${i+1}_freq`, {
                node: this.eqBands[i].frequency,
                min: 20,
                max: 20000,
                default: this.getDefaultFrequency(i),
                scale: 'logarithmic'
            });
            
            this.parameters.set(`band${i+1}_gain`, {
                node: this.eqBands[i].gain,
                min: -18,
                max: 18,
                default: 0,
                scale: 'linear'
            });
            
            this.parameters.set(`band${i+1}_q`, {
                node: this.eqBands[i].Q,
                min: 0.1,
                max: 10,
                default: 1.0,
                scale: 'logarithmic'
            });
        }
    }
    
    getDefaultFrequency(bandIndex) {
        const frequencies = [63, 125, 250, 500, 1000, 2000, 4000, 8000];
        return frequencies[bandIndex];
    }
    
    setParameter(parameterId, value) {
        const param = this.parameters.get(parameterId);
        if (param) {
            param.node.value = value;
            
            // Notificar mudança de parâmetro
            this.onParameterChange(parameterId, value);
        }
    }
    
    getParameter(parameterId) {
        const param = this.parameters.get(parameterId);
        return param ? param.node.value : null;
    }
    
    onParameterChange(parameterId, value) {
        // Callback para UI ou automação
        if (this.onParameterChangeCallback) {
            this.onParameterChangeCallback(parameterId, value);
        }
    }
    
    loadPreset(presetData) {
        Object.entries(presetData.parameters).forEach(([key, value]) => {
            this.setParameter(key, value);
        });
    }
    
    savePreset() {
        const preset = {
            nome: 'Preset Personalizado',
            parameters: {}
        };
        
        this.parameters.forEach((param, key) => {
            preset.parameters[key] = param.node.value;
        });
        
        return preset;
    }
    
    // Métodos de conexão
    connect(destination) {
        this.outputNode.connect(destination);
    }
    
    disconnect() {
        this.outputNode.disconnect();
    }
    
    // Bypass
    set bypassed(value) {
        if (value) {
            this.inputNode.disconnect();
            this.inputNode.connect(this.outputNode);
        } else {
            this.inputNode.disconnect();
            this.inputNode.connect(this.eqBands[0]);
        }
    }
    
    // Cleanup
    destroy() {
        this.eqBands.forEach(band => band.disconnect());
        this.inputNode.disconnect();
        this.outputNode.disconnect();
    }
}
```

### Interface Gráfica (HTML/CSS)

```html
<div class="plugin-interface" data-plugin="eq_eight">
    <div class="plugin-header">
        <h3>EQ Eight</h3>
        <div class="plugin-controls">
            <button class="bypass-btn">Bypass</button>
            <select class="preset-selector">
                <option value="default">Padrão</option>
                <option value="vocal_boost">Vocal Boost</option>
            </select>
        </div>
    </div>
    
    <div class="eq-display">
        <canvas id="eq-curve" width="400" height="200"></canvas>
    </div>
    
    <div class="eq-bands">
        <!-- Banda 1 -->
        <div class="eq-band" data-band="1">
            <label>63 Hz</label>
            <div class="knob-container">
                <div class="knob" data-param="band1_gain">
                    <div class="knob-indicator"></div>
                </div>
                <span class="knob-value">0 dB</span>
            </div>
        </div>
        
        <!-- Repetir para todas as 8 bandas -->
        <!-- ... -->
    </div>
</div>
```

```css
.plugin-interface {
    background: #2a2a2a;
    border: 1px solid #444;
    border-radius: 8px;
    padding: 16px;
    color: white;
    font-family: 'Segoe UI', sans-serif;
}

.plugin-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
}

.eq-display {
    background: #1a1a1a;
    border: 1px solid #333;
    margin-bottom: 16px;
    border-radius: 4px;
}

.eq-bands {
    display: flex;
    justify-content: space-between;
    gap: 8px;
}

.eq-band {
    text-align: center;
}

.knob {
    width: 40px;
    height: 40px;
    border: 2px solid #666;
    border-radius: 50%;
    position: relative;
    cursor: pointer;
    background: linear-gradient(135deg, #333, #555);
}

.knob-indicator {
    width: 2px;
    height: 16px;
    background: #00ff00;
    position: absolute;
    top: 2px;
    left: 50%;
    transform: translateX(-50%);
    border-radius: 1px;
    transform-origin: bottom center;
}

.knob-value {
    display: block;
    margin-top: 4px;
    font-size: 10px;
    color: #999;
}
```

## Plugin Bridge (C++/JUCE)

Para plugins VST nativos, utilizamos um bridge baseado em JUCE:

```cpp
#include <JuceHeader.h>

class PluginBridge : public juce::AudioProcessor {
public:
    PluginBridge() {
        // Inicializar comunicação WebSocket
        websocket = std::make_unique<WebSocketClient>();
    }
    
    void processBlock(juce::AudioBuffer<float>& buffer, 
                     juce::MidiBuffer& midiMessages) override {
        // Processar áudio através do plugin VST
        if (vstPlugin) {
            vstPlugin->processBlock(buffer, midiMessages);
        }
        
        // Enviar dados de áudio para o navegador via WebSocket
        sendAudioData(buffer);
    }
    
    void setParameter(int parameterIndex, float value) override {
        if (vstPlugin) {
            vstPlugin->setParameter(parameterIndex, value);
        }
        
        // Notificar mudança via WebSocket
        notifyParameterChange(parameterIndex, value);
    }
    
private:
    std::unique_ptr<juce::AudioPluginInstance> vstPlugin;
    std::unique_ptr<WebSocketClient> websocket;
    
    void sendAudioData(const juce::AudioBuffer<float>& buffer) {
        // Converter para formato WebSocket e enviar
        json audioData = {
            {"type", "audio_data"},
            {"samples", bufferToArray(buffer)},
            {"channels", buffer.getNumChannels()},
            {"length", buffer.getNumSamples()}
        };
        
        websocket->send(audioData.dump());
    }
    
    void notifyParameterChange(int index, float value) {
        json paramData = {
            {"type", "parameter_change"},
            {"index", index},
            {"value", value}
        };
        
        websocket->send(paramData.dump());
    }
};
```

## Registro e Descoberta

### Registro de Plugin

```javascript
class PluginRegistry {
    constructor() {
        this.plugins = new Map();
        this.categories = new Map();
    }
    
    async registerPlugin(pluginPath) {
        try {
            const manifest = await this.loadManifest(pluginPath);
            const pluginClass = await this.loadPluginClass(manifest);
            
            this.plugins.set(manifest.id, {
                manifest,
                class: pluginClass,
                path: pluginPath
            });
            
            this.addToCategory(manifest);
            
            console.log(`Plugin registrado: ${manifest.nome}`);
        } catch (error) {
            console.error('Erro ao registrar plugin:', error);
        }
    }
    
    async scanPlugins(pluginsPath) {
        const pluginDirs = await this.getPluginDirectories(pluginsPath);
        
        for (const dir of pluginDirs) {
            await this.registerPlugin(dir);
        }
    }
    
    createPlugin(pluginId, audioContext) {
        const plugin = this.plugins.get(pluginId);
        if (!plugin) {
            throw new Error(`Plugin não encontrado: ${pluginId}`);
        }
        
        return new plugin.class(audioContext, pluginId);
    }
    
    getPluginsByCategory(category) {
        return this.categories.get(category) || [];
    }
    
    getAllPlugins() {
        return Array.from(this.plugins.values());
    }
}
```

### Descoberta Automática

```javascript
class PluginScanner {
    constructor() {
        this.scanPaths = [
            '/usr/lib/vst',
            '/usr/lib/vst3',
            '/Library/Audio/Plug-Ins/VST',
            '/Library/Audio/Plug-Ins/VST3',
            '/Library/Audio/Plug-Ins/Components',
            'C:\\Program Files\\VstPlugins',
            'C:\\Program Files\\Common Files\\VST3'
        ];
    }
    
    async scanAllPlugins() {
        const foundPlugins = [];
        
        for (const path of this.scanPaths) {
            if (await this.pathExists(path)) {
                const plugins = await this.scanPath(path);
                foundPlugins.push(...plugins);
            }
        }
        
        return foundPlugins;
    }
    
    async scanPath(basePath) {
        const plugins = [];
        const entries = await this.readDirectory(basePath);
        
        for (const entry of entries) {
            if (this.isPluginFile(entry)) {
                const metadata = await this.extractMetadata(entry);
                plugins.push(metadata);
            }
        }
        
        return plugins;
    }
    
    isPluginFile(filename) {
        const extensions = ['.vst', '.vst3', '.component', '.aax'];
        return extensions.some(ext => filename.endsWith(ext));
    }
}
```

## Automação de Parâmetros

### Sistema de Automação

```javascript
class ParameterAutomation {
    constructor(plugin, parameterId) {
        this.plugin = plugin;
        this.parameterId = parameterId;
        this.points = [];
        this.enabled = true;
    }
    
    addPoint(time, value) {
        const point = { time, value };
        
        // Inserir ordenado por tempo
        const index = this.points.findIndex(p => p.time > time);
        if (index === -1) {
            this.points.push(point);
        } else {
            this.points.splice(index, 0, point);
        }
    }
    
    removePoint(time) {
        const index = this.points.findIndex(p => p.time === time);
        if (index !== -1) {
            this.points.splice(index, 1);
        }
    }
    
    getValueAtTime(time) {
        if (this.points.length === 0) return null;
        if (this.points.length === 1) return this.points[0].value;
        
        // Encontrar pontos adjacentes
        let beforePoint = null;
        let afterPoint = null;
        
        for (let i = 0; i < this.points.length; i++) {
            if (this.points[i].time <= time) {
                beforePoint = this.points[i];
            } else {
                afterPoint = this.points[i];
                break;
            }
        }
        
        if (!beforePoint) return afterPoint.value;
        if (!afterPoint) return beforePoint.value;
        
        // Interpolação linear
        const ratio = (time - beforePoint.time) / (afterPoint.time - beforePoint.time);
        return beforePoint.value + (afterPoint.value - beforePoint.value) * ratio;
    }
    
    update(currentTime) {
        if (!this.enabled) return;
        
        const value = this.getValueAtTime(currentTime);
        if (value !== null) {
            this.plugin.setParameter(this.parameterId, value);
        }
    }
}
```

## Presets e Bancos

### Formato de Preset

```json
{
    "nome": "Vocal Boost",
    "autor": "DAW Online",
    "descricao": "Realça frequências vocais",
    "categoria": "vocal",
    "tags": ["vocal", "boost", "presença"],
    "plugin_id": "eq_eight",
    "plugin_versao": "1.0.0",
    "data_criacao": "2024-01-01T10:00:00Z",
    "parameters": {
        "band1_freq": 100,
        "band1_gain": 2,
        "band1_q": 1.0,
        "band2_freq": 200,
        "band2_gain": -1,
        "band2_q": 0.8,
        "band3_freq": 1000,
        "band3_gain": 3,
        "band3_q": 1.2,
        "band4_freq": 3000,
        "band4_gain": 4,
        "band4_q": 1.5
    }
}
```

### Gerenciador de Presets

```javascript
class PresetManager {
    constructor() {
        this.presets = new Map();
        this.banks = new Map();
    }
    
    async loadPreset(presetId) {
        const response = await fetch(`/api/presets/${presetId}`);
        return await response.json();
    }
    
    async savePreset(preset) {
        const response = await fetch('/api/presets', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(preset)
        });
        return await response.json();
    }
    
    async getPresetsByPlugin(pluginId) {
        const response = await fetch(`/api/presets?plugin=${pluginId}`);
        return await response.json();
    }
    
    async createBank(name, presetIds) {
        const bank = {
            nome: name,
            presets: presetIds,
            data_criacao: new Date().toISOString()
        };
        
        const response = await fetch('/api/preset-banks', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(bank)
        });
        
        return await response.json();
    }
}
```

## Testes de Plugin

### Teste Unitário

```javascript
describe('EQEightPlugin', () => {
    let audioContext;
    let plugin;
    
    beforeEach(() => {
        audioContext = new (window.AudioContext || window.webkitAudioContext)();
        plugin = new EQEightPlugin(audioContext, 'eq_eight');
    });
    
    afterEach(() => {
        plugin.destroy();
        audioContext.close();
    });
    
    test('deve inicializar com parâmetros padrão', () => {
        expect(plugin.getParameter('band1_freq')).toBe(63);
        expect(plugin.getParameter('band1_gain')).toBe(0);
        expect(plugin.getParameter('band1_q')).toBe(1.0);
    });
    
    test('deve atualizar parâmetros corretamente', () => {
        plugin.setParameter('band1_gain', 5);
        expect(plugin.getParameter('band1_gain')).toBe(5);
    });
    
    test('deve carregar preset', () => {
        const preset = {
            parameters: {
                'band1_gain': 3,
                'band2_gain': -2
            }
        };
        
        plugin.loadPreset(preset);
        expect(plugin.getParameter('band1_gain')).toBe(3);
        expect(plugin.getParameter('band2_gain')).toBe(-2);
    });
});
```

## Otimização de Performance

### Worker Thread para DSP

```javascript
// dsp-worker.js
class DSPWorker {
    constructor() {
        this.plugins = new Map();
    }
    
    processAudio(inputData, pluginIds) {
        const outputData = new Float32Array(inputData.length);
        outputData.set(inputData);
        
        // Processar cada plugin em série
        for (const pluginId of pluginIds) {
            const plugin = this.plugins.get(pluginId);
            if (plugin) {
                plugin.process(outputData);
            }
        }
        
        return outputData;
    }
}

// Uso no thread principal
const worker = new Worker('dsp-worker.js');

worker.postMessage({
    type: 'process',
    inputData: audioBuffer,
    pluginIds: ['eq_eight', 'compressor']
});

worker.onmessage = (event) => {
    const processedAudio = event.data;
    // Usar áudio processado
};
```

## Distribuição de Plugins

### Empacotamento

```bash
# Estrutura do pacote
plugin-package/
├── plugin.json           # Manifesto
├── plugin.js            # Código principal
├── plugin.css           # Estilos da interface
├── presets/             # Presets incluídos
│   ├── default.json
│   └── vocal_boost.json
├── assets/              # Recursos (imagens, samples)
│   ├── background.png
│   └── impulse.wav
└── docs/                # Documentação
    └── README.md

# Criar pacote
tar -czf eq_eight_v1.0.0.dawplugin plugin-package/
```

### Instalação

```javascript
class PluginInstaller {
    async installPlugin(packageFile) {
        // Extrair pacote
        const extracted = await this.extractPackage(packageFile);
        
        // Validar manifesto
        const manifest = await this.validateManifest(extracted.manifest);
        
        // Verificar dependências
        await this.checkDependencies(manifest.dependencies);
        
        // Instalar arquivos
        const installPath = `/plugins/${manifest.id}`;
        await this.copyFiles(extracted.files, installPath);
        
        // Registrar plugin
        await this.registerPlugin(installPath);
        
        console.log(`Plugin ${manifest.nome} instalado com sucesso`);
    }
    
    async uninstallPlugin(pluginId) {
        const installPath = `/plugins/${pluginId}`;
        
        // Remover arquivos
        await this.removeDirectory(installPath);
        
        // Desregistrar plugin
        await this.unregisterPlugin(pluginId);
        
        console.log(`Plugin ${pluginId} removido`);
    }
}
```

Este sistema de plugins oferece uma base sólida para expandir as capacidades da DAW Online com efeitos e instrumentos profissionais.
