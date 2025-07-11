# ASIO Integration - DAW Online

## Visão Geral

A integração ASIO (Audio Stream Input/Output) permite que a DAW Online utilize drivers de áudio profissionais para obter latência ultra-baixa. Esta implementação funciona como uma ponte entre os drivers ASIO nativos e a Web Audio API.

## Arquitetura

```
┌─────────────────┐    ┌──────────────────┐    ┌─────────────────┐
│   Web Browser   │    │  ASIO Middleware │    │  ASIO Driver    │
│                 │    │                  │    │                 │
│  Web Audio API  │◄──►│  Native Bridge   │◄──►│ Hardware Interface │
│                 │    │                  │    │                 │
└─────────────────┘    └──────────────────┘    └─────────────────┘
```

### Componentes Principais

1. **ASIO Middleware**: Aplicação nativa que comunica com drivers ASIO
2. **WebSocket Bridge**: Comunicação entre navegador e middleware
3. **Audio Buffer Manager**: Gerenciamento de buffers de baixa latência
4. **Device Manager**: Descoberta e configuração de dispositivos

## Configuração do Sistema

### Requisitos

**Windows:**
- Windows 10/11 (64-bit)
- Visual Studio 2019+ ou Build Tools
- ASIO SDK 2.3+
- Interface de áudio compatível com ASIO

**macOS:**
- macOS 10.15+ 
- Xcode 12+
- Core Audio framework
- Interface de áudio compatível

**Linux:**
- Ubuntu 20.04+ ou distribuição equivalente
- ALSA development libraries
- JACK Audio Connection Kit (opcional)
- Interface de áudio compatível

### Instalação de Dependências

**Windows:**
```bash
# Instalar ASIO SDK
git clone https://github.com/steinberg/asiosdk.git
cd asiosdk
mkdir build && cd build
cmake ..
make install

# Instalar Visual Studio Build Tools
winget install Microsoft.VisualStudio.2022.BuildTools
```

**macOS:**
```bash
# Instalar Xcode command line tools
xcode-select --install

# Instalar dependências via Homebrew
brew install cmake portaudio
```

**Linux:**
```bash
# Ubuntu/Debian
sudo apt update
sudo apt install build-essential cmake libasound2-dev libjack-jackd2-dev portaudio19-dev

# CentOS/RHEL
sudo dnf install gcc-c++ cmake alsa-lib-devel jack-audio-connection-kit-devel portaudio-devel
```

## Implementação do Middleware

### Estrutura do Projeto

```
middleware/
├── src/
│   ├── ASIOManager.cpp      # Gerenciador principal ASIO
│   ├── ASIOManager.h
│   ├── DeviceManager.cpp    # Descoberta de dispositivos
│   ├── DeviceManager.h
│   ├── BufferManager.cpp    # Gerenciamento de buffers
│   ├── BufferManager.h
│   ├── WebSocketServer.cpp  # Servidor WebSocket
│   ├── WebSocketServer.h
│   └── main.cpp            # Ponto de entrada
├── include/
│   └── asio/               # Headers ASIO SDK
├── lib/
│   └── asiosdk.lib         # Biblioteca ASIO
├── CMakeLists.txt
└── config.json            # Configuração padrão
```

### ASIOManager (C++)

```cpp
// ASIOManager.h
#pragma once

#include "asio.h"
#include <vector>
#include <functional>
#include <memory>

class ASIOManager {
public:
    struct AudioConfig {
        int sampleRate = 44100;
        int bufferSize = 128;
        int inputChannels = 2;
        int outputChannels = 2;
        std::string driverName;
    };
    
    struct AudioBuffer {
        float* inputBuffer;
        float* outputBuffer;
        long bufferSize;
        double sampleTime;
        long systemTime;
    };
    
    using AudioCallback = std::function<void(const AudioBuffer&)>;
    
    ASIOManager();
    ~ASIOManager();
    
    // Métodos principais
    bool initialize();
    bool start();
    bool stop();
    void shutdown();
    
    // Configuração
    bool setDriver(const std::string& driverName);
    bool setConfig(const AudioConfig& config);
    AudioConfig getConfig() const;
    
    // Dispositivos
    std::vector<std::string> getAvailableDrivers();
    std::vector<std::string> getInputChannels();
    std::vector<std::string> getOutputChannels();
    
    // Callbacks
    void setAudioCallback(AudioCallback callback);
    
    // Status
    bool isRunning() const;
    double getCurrentLatency() const;
    long getCurrentSampleRate() const;
    
private:
    // ASIO callbacks estáticos
    static void bufferSwitch(long doubleBufferIndex, ASIOBool directProcess);
    static ASIOTime* bufferSwitchTimeInfo(ASIOTime* params, long doubleBufferIndex, ASIOBool directProcess);
    static void sampleRateChanged(ASIOSampleRate sRate);
    static long asioMessage(long selector, long value, void* message, double* opt);
    
    // Implementação interna
    bool loadDriver(const std::string& driverName);
    bool setupBuffers();
    void processAudio(long bufferIndex);
    
    AudioConfig config_;
    AudioCallback audioCallback_;
    bool running_;
    bool initialized_;
    
    // Buffers ASIO
    ASIOBufferInfo* bufferInfos_;
    ASIOChannelInfo* channelInfos_;
    long inputBuffers_;
    long outputBuffers_;
    long bufferSize_;
    
    // Instância singleton para callbacks estáticos
    static ASIOManager* instance_;
};
```

```cpp
// ASIOManager.cpp
#include "ASIOManager.h"
#include <iostream>
#include <algorithm>

ASIOManager* ASIOManager::instance_ = nullptr;

ASIOManager::ASIOManager() 
    : running_(false), initialized_(false), bufferInfos_(nullptr), 
      channelInfos_(nullptr), inputBuffers_(0), outputBuffers_(0) {
    instance_ = this;
}

ASIOManager::~ASIOManager() {
    shutdown();
    instance_ = nullptr;
}

bool ASIOManager::initialize() {
    if (initialized_) return true;
    
    // Inicializar COM (Windows)
    #ifdef _WIN32
    CoInitialize(nullptr);
    #endif
    
    initialized_ = true;
    return true;
}

bool ASIOManager::setDriver(const std::string& driverName) {
    if (running_) {
        std::cerr << "Não é possível trocar driver enquanto está rodando" << std::endl;
        return false;
    }
    
    if (!loadDriver(driverName)) {
        std::cerr << "Falha ao carregar driver: " << driverName << std::endl;
        return false;
    }
    
    config_.driverName = driverName;
    return true;
}

bool ASIOManager::loadDriver(const std::string& driverName) {
    // Implementação específica da plataforma
    #ifdef _WIN32
    return loadASIODriver(const_cast<char*>(driverName.c_str()));
    #else
    // macOS/Linux: usar Core Audio ou JACK
    return loadCoreAudioDriver(driverName);
    #endif
}

std::vector<std::string> ASIOManager::getAvailableDrivers() {
    std::vector<std::string> drivers;
    
    #ifdef _WIN32
    // Windows: enumerar drivers ASIO do registro
    char driverName[32];
    for (int i = 0; i < getNumASIODrivers(); ++i) {
        if (getASIODriverName(i, driverName, 32) == 0) {
            drivers.push_back(std::string(driverName));
        }
    }
    #elif defined(__APPLE__)
    // macOS: enumerar dispositivos Core Audio
    drivers = enumerateCoreAudioDevices();
    #else
    // Linux: enumerar dispositivos ALSA/JACK
    drivers = enumerateLinuxAudioDevices();
    #endif
    
    return drivers;
}

bool ASIOManager::setConfig(const AudioConfig& config) {
    if (running_) {
        std::cerr << "Não é possível alterar configuração enquanto está rodando" << std::endl;
        return false;
    }
    
    config_ = config;
    
    // Aplicar configurações ao driver
    if (ASIOSetSampleRate(config_.sampleRate) != ASE_OK) {
        std::cerr << "Falha ao definir sample rate: " << config_.sampleRate << std::endl;
        return false;
    }
    
    long minSize, maxSize, preferredSize, granularity;
    if (ASIOGetBufferSize(&minSize, &maxSize, &preferredSize, &granularity) != ASE_OK) {
        std::cerr << "Falha ao obter tamanhos de buffer" << std::endl;
        return false;
    }
    
    // Ajustar buffer size dentro dos limites
    config_.bufferSize = std::max(minSize, std::min(maxSize, (long)config_.bufferSize));
    
    return setupBuffers();
}

bool ASIOManager::setupBuffers() {
    // Limpar buffers existentes
    if (bufferInfos_) {
        ASIODisposeBuffers();
        delete[] bufferInfos_;
        delete[] channelInfos_;
    }
    
    // Contar canais disponíveis
    long numInputChannels, numOutputChannels;
    if (ASIOGetChannels(&numInputChannels, &numOutputChannels) != ASE_OK) {
        std::cerr << "Falha ao obter número de canais" << std::endl;
        return false;
    }
    
    inputBuffers_ = std::min((long)config_.inputChannels, numInputChannels);
    outputBuffers_ = std::min((long)config_.outputChannels, numOutputChannels);
    
    long totalBuffers = inputBuffers_ + outputBuffers_;
    bufferInfos_ = new ASIOBufferInfo[totalBuffers];
    channelInfos_ = new ASIOChannelInfo[totalBuffers];
    
    // Configurar buffers de entrada
    for (int i = 0; i < inputBuffers_; ++i) {
        bufferInfos_[i].isInput = ASIOTrue;
        bufferInfos_[i].channelNum = i;
        bufferInfos_[i].buffers[0] = nullptr;
        bufferInfos_[i].buffers[1] = nullptr;
    }
    
    // Configurar buffers de saída
    for (int i = 0; i < outputBuffers_; ++i) {
        int idx = inputBuffers_ + i;
        bufferInfos_[idx].isInput = ASIOFalse;
        bufferInfos_[idx].channelNum = i;
        bufferInfos_[idx].buffers[0] = nullptr;
        bufferInfos_[idx].buffers[1] = nullptr;
    }
    
    // Criar buffers
    if (ASIOCreateBuffers(bufferInfos_, totalBuffers, config_.bufferSize, &asioCallbacks) != ASE_OK) {
        std::cerr << "Falha ao criar buffers ASIO" << std::endl;
        return false;
    }
    
    // Obter informações dos canais
    for (int i = 0; i < totalBuffers; ++i) {
        channelInfos_[i].channel = bufferInfos_[i].channelNum;
        channelInfos_[i].isInput = bufferInfos_[i].isInput;
        if (ASIOGetChannelInfo(&channelInfos_[i]) != ASE_OK) {
            std::cerr << "Falha ao obter info do canal " << i << std::endl;
        }
    }
    
    return true;
}

bool ASIOManager::start() {
    if (running_) return true;
    
    if (!initialized_) {
        std::cerr << "ASIO não foi inicializado" << std::endl;
        return false;
    }
    
    if (ASIOStart() != ASE_OK) {
        std::cerr << "Falha ao iniciar ASIO" << std::endl;
        return false;
    }
    
    running_ = true;
    std::cout << "ASIO iniciado com sucesso" << std::endl;
    return true;
}

bool ASIOManager::stop() {
    if (!running_) return true;
    
    if (ASIOStop() != ASE_OK) {
        std::cerr << "Falha ao parar ASIO" << std::endl;
        return false;
    }
    
    running_ = false;
    std::cout << "ASIO parado" << std::endl;
    return true;
}

void ASIOManager::shutdown() {
    stop();
    
    if (bufferInfos_) {
        ASIODisposeBuffers();
        delete[] bufferInfos_;
        delete[] channelInfos_;
        bufferInfos_ = nullptr;
        channelInfos_ = nullptr;
    }
    
    ASIOExit();
    
    #ifdef _WIN32
    CoUninitialize();
    #endif
    
    initialized_ = false;
}

// Callbacks ASIO estáticos
void ASIOManager::bufferSwitch(long doubleBufferIndex, ASIOBool directProcess) {
    if (instance_) {
        instance_->processAudio(doubleBufferIndex);
    }
}

ASIOTime* ASIOManager::bufferSwitchTimeInfo(ASIOTime* params, long doubleBufferIndex, ASIOBool directProcess) {
    bufferSwitch(doubleBufferIndex, directProcess);
    return params;
}

void ASIOManager::sampleRateChanged(ASIOSampleRate sRate) {
    std::cout << "Sample rate mudou para: " << sRate << std::endl;
}

long ASIOManager::asioMessage(long selector, long value, void* message, double* opt) {
    switch (selector) {
        case kAsioSelectorSupported:
            return 1L;
        case kAsioEngineVersion:
            return 2L;
        case kAsioResetRequest:
            std::cout << "ASIO reset solicitado" << std::endl;
            return 1L;
        case kAsioBufferSizeChange:
            std::cout << "Buffer size mudou" << std::endl;
            return 1L;
        case kAsioResyncRequest:
            return 1L;
        case kAsioLatenciesChanged:
            std::cout << "Latências mudaram" << std::endl;
            return 1L;
        default:
            return 0L;
    }
}

void ASIOManager::processAudio(long bufferIndex) {
    if (!audioCallback_) return;
    
    AudioBuffer audioBuffer;
    audioBuffer.bufferSize = config_.bufferSize;
    audioBuffer.sampleTime = 0; // Implementar timestamp preciso
    audioBuffer.systemTime = 0; // Implementar timestamp do sistema
    
    // Converter buffers ASIO para formato float
    audioBuffer.inputBuffer = new float[config_.bufferSize * inputBuffers_];
    audioBuffer.outputBuffer = new float[config_.bufferSize * outputBuffers_];
    
    // Conversão de entrada (dependente do formato do canal)
    for (int ch = 0; ch < inputBuffers_; ++ch) {
        void* asioBuffer = bufferInfos_[ch].buffers[bufferIndex];
        float* floatBuffer = &audioBuffer.inputBuffer[ch * config_.bufferSize];
        
        convertASIOToFloat(asioBuffer, floatBuffer, config_.bufferSize, 
                          channelInfos_[ch].type);
    }
    
    // Chamar callback de processamento
    audioCallback_(audioBuffer);
    
    // Conversão de saída
    for (int ch = 0; ch < outputBuffers_; ++ch) {
        int idx = inputBuffers_ + ch;
        void* asioBuffer = bufferInfos_[idx].buffers[bufferIndex];
        float* floatBuffer = &audioBuffer.outputBuffer[ch * config_.bufferSize];
        
        convertFloatToASIO(floatBuffer, asioBuffer, config_.bufferSize,
                          channelInfos_[idx].type);
    }
    
    // Limpar buffers temporários
    delete[] audioBuffer.inputBuffer;
    delete[] audioBuffer.outputBuffer;
}

double ASIOManager::getCurrentLatency() const {
    long inputLatency, outputLatency;
    if (ASIOGetLatencies(&inputLatency, &outputLatency) == ASE_OK) {
        return (double)(inputLatency + outputLatency) / config_.sampleRate * 1000.0; // ms
    }
    return 0.0;
}
```

### WebSocket Bridge

```cpp
// WebSocketServer.h
#pragma once

#include <websocketpp/config/asio_no_tls.hpp>
#include <websocketpp/server.hpp>
#include <json/json.h>
#include <memory>
#include <thread>

class ASIOManager;

class WebSocketServer {
public:
    using Server = websocketpp::server<websocketpp::config::asio>;
    using ConnectionHdl = websocketpp::connection_hdl;
    
    WebSocketServer(std::shared_ptr<ASIOManager> asioManager, int port = 8081);
    ~WebSocketServer();
    
    bool start();
    void stop();
    
    // Enviar dados para todos os clientes conectados
    void broadcastAudioData(const float* inputData, const float* outputData, 
                           size_t frames, int channels);
    void broadcastStatus(const Json::Value& status);
    
private:
    void onMessage(ConnectionHdl hdl, Server::message_ptr msg);
    void onOpen(ConnectionHdl hdl);
    void onClose(ConnectionHdl hdl);
    
    void handleConfigMessage(const Json::Value& data);
    void handleStartMessage();
    void handleStopMessage();
    void handleDeviceListMessage();
    
    Server server_;
    std::shared_ptr<ASIOManager> asioManager_;
    std::thread serverThread_;
    std::set<ConnectionHdl, std::owner_less<ConnectionHdl>> connections_;
    int port_;
    bool running_;
};
```

```cpp
// WebSocketServer.cpp
#include "WebSocketServer.h"
#include "ASIOManager.h"
#include <iostream>

WebSocketServer::WebSocketServer(std::shared_ptr<ASIOManager> asioManager, int port)
    : asioManager_(asioManager), port_(port), running_(false) {
    
    // Configurar servidor WebSocket
    server_.set_access_channels(websocketpp::log::alevel::all);
    server_.clear_access_channels(websocketpp::log::alevel::frame_payload);
    server_.init_asio();
    
    // Configurar handlers
    server_.set_message_handler([this](ConnectionHdl hdl, Server::message_ptr msg) {
        onMessage(hdl, msg);
    });
    
    server_.set_open_handler([this](ConnectionHdl hdl) {
        onOpen(hdl);
    });
    
    server_.set_close_handler([this](ConnectionHdl hdl) {
        onClose(hdl);
    });
}

bool WebSocketServer::start() {
    try {
        server_.listen(port_);
        server_.start_accept();
        
        serverThread_ = std::thread([this]() {
            server_.run();
        });
        
        running_ = true;
        std::cout << "WebSocket server iniciado na porta " << port_ << std::endl;
        return true;
    } catch (const std::exception& e) {
        std::cerr << "Erro ao iniciar WebSocket server: " << e.what() << std::endl;
        return false;
    }
}

void WebSocketServer::onMessage(ConnectionHdl hdl, Server::message_ptr msg) {
    try {
        Json::Reader reader;
        Json::Value root;
        
        if (!reader.parse(msg->get_payload(), root)) {
            std::cerr << "Erro ao parsear JSON" << std::endl;
            return;
        }
        
        std::string type = root.get("type", "").asString();
        Json::Value data = root.get("data", Json::Value());
        
        if (type == "config") {
            handleConfigMessage(data);
        } else if (type == "start") {
            handleStartMessage();
        } else if (type == "stop") {
            handleStopMessage();
        } else if (type == "get_devices") {
            handleDeviceListMessage();
        }
        
    } catch (const std::exception& e) {
        std::cerr << "Erro ao processar mensagem: " << e.what() << std::endl;
    }
}

void WebSocketServer::handleConfigMessage(const Json::Value& data) {
    ASIOManager::AudioConfig config;
    config.sampleRate = data.get("sampleRate", 44100).asInt();
    config.bufferSize = data.get("bufferSize", 128).asInt();
    config.inputChannels = data.get("inputChannels", 2).asInt();
    config.outputChannels = data.get("outputChannels", 2).asInt();
    config.driverName = data.get("driverName", "").asString();
    
    if (!config.driverName.empty()) {
        asioManager_->setDriver(config.driverName);
    }
    
    bool success = asioManager_->setConfig(config);
    
    // Enviar resposta
    Json::Value response;
    response["type"] = "config_response";
    response["success"] = success;
    response["latency"] = asioManager_->getCurrentLatency();
    
    broadcastStatus(response);
}

void WebSocketServer::broadcastAudioData(const float* inputData, const float* outputData,
                                        size_t frames, int channels) {
    if (connections_.empty()) return;
    
    Json::Value message;
    message["type"] = "audio_data";
    message["frames"] = static_cast<int>(frames);
    message["channels"] = channels;
    
    // Converter dados de áudio para base64 ou array JSON (simplificado)
    Json::Value inputArray(Json::arrayValue);
    Json::Value outputArray(Json::arrayValue);
    
    for (size_t i = 0; i < frames * channels; ++i) {
        inputArray.append(inputData[i]);
        outputArray.append(outputData[i]);
    }
    
    message["input"] = inputArray;
    message["output"] = outputArray;
    
    Json::StreamWriterBuilder builder;
    std::string jsonString = Json::writeString(builder, message);
    
    for (auto& connection : connections_) {
        try {
            server_.send(connection, jsonString, websocketpp::frame::opcode::text);
        } catch (const std::exception& e) {
            std::cerr << "Erro ao enviar dados de áudio: " << e.what() << std::endl;
        }
    }
}
```

## Interface JavaScript

### Cliente ASIO

```javascript
class ASIOClient {
    constructor() {
        this.ws = null;
        this.connected = false;
        this.audioContext = null;
        this.inputNode = null;
        this.outputNode = null;
        this.latency = 0;
        
        this.callbacks = {
            onConnect: null,
            onDisconnect: null,
            onAudioData: null,
            onConfigUpdate: null,
            onError: null
        };
    }
    
    async connect(url = 'ws://localhost:8081') {
        try {
            this.ws = new WebSocket(url);
            
            this.ws.onopen = () => {
                this.connected = true;
                console.log('Conectado ao middleware ASIO');
                if (this.callbacks.onConnect) {
                    this.callbacks.onConnect();
                }
            };
            
            this.ws.onmessage = (event) => {
                this.handleMessage(JSON.parse(event.data));
            };
            
            this.ws.onclose = () => {
                this.connected = false;
                console.log('Desconectado do middleware ASIO');
                if (this.callbacks.onDisconnect) {
                    this.callbacks.onDisconnect();
                }
            };
            
            this.ws.onerror = (error) => {
                console.error('Erro WebSocket ASIO:', error);
                if (this.callbacks.onError) {
                    this.callbacks.onError(error);
                }
            };
            
        } catch (error) {
            console.error('Erro ao conectar ASIO:', error);
            throw error;
        }
    }
    
    disconnect() {
        if (this.ws) {
            this.ws.close();
            this.ws = null;
        }
        this.connected = false;
    }
    
    async getAvailableDevices() {
        return new Promise((resolve, reject) => {
            if (!this.connected) {
                reject(new Error('Não conectado ao middleware ASIO'));
                return;
            }
            
            const messageId = Date.now();
            
            // Listener temporário para a resposta
            const tempListener = (data) => {
                if (data.type === 'device_list_response' && data.messageId === messageId) {
                    this.ws.removeEventListener('message', tempListener);
                    resolve(data.devices);
                }
            };
            
            this.ws.addEventListener('message', tempListener);
            
            this.send({
                type: 'get_devices',
                messageId: messageId
            });
            
            // Timeout após 5 segundos
            setTimeout(() => {
                this.ws.removeEventListener('message', tempListener);
                reject(new Error('Timeout ao obter dispositivos'));
            }, 5000);
        });
    }
    
    async configureAudio(config) {
        if (!this.connected) {
            throw new Error('Não conectado ao middleware ASIO');
        }
        
        this.send({
            type: 'config',
            data: config
        });
        
        // Aguardar resposta de configuração
        return new Promise((resolve, reject) => {
            const tempListener = (data) => {
                if (data.type === 'config_response') {
                    this.callbacks.onConfigUpdate = null;
                    
                    if (data.success) {
                        this.latency = data.latency;
                        resolve({
                            success: true,
                            latency: data.latency
                        });
                    } else {
                        reject(new Error(data.error || 'Falha na configuração'));
                    }
                }
            };
            
            this.callbacks.onConfigUpdate = tempListener;
            
            setTimeout(() => {
                this.callbacks.onConfigUpdate = null;
                reject(new Error('Timeout na configuração'));
            }, 10000);
        });
    }
    
    async startAudio() {
        if (!this.connected) {
            throw new Error('Não conectado ao middleware ASIO');
        }
        
        this.send({ type: 'start' });
    }
    
    async stopAudio() {
        if (!this.connected) {
            throw new Error('Não conectado ao middleware ASIO');
        }
        
        this.send({ type: 'stop' });
    }
    
    send(data) {
        if (this.ws && this.ws.readyState === WebSocket.OPEN) {
            this.ws.send(JSON.stringify(data));
        }
    }
    
    handleMessage(data) {
        switch (data.type) {
            case 'audio_data':
                if (this.callbacks.onAudioData) {
                    this.callbacks.onAudioData(data);
                }
                break;
                
            case 'config_response':
                if (this.callbacks.onConfigUpdate) {
                    this.callbacks.onConfigUpdate(data);
                }
                break;
                
            case 'error':
                console.error('Erro ASIO:', data.message);
                if (this.callbacks.onError) {
                    this.callbacks.onError(new Error(data.message));
                }
                break;
                
            default:
                console.log('Mensagem ASIO não tratada:', data);
        }
    }
    
    // Bridge para Web Audio API
    async createWebAudioBridge() {
        if (!this.audioContext) {
            this.audioContext = new (window.AudioContext || window.webkitAudioContext)();
        }
        
        // Criar nodes de entrada e saída
        this.inputNode = this.audioContext.createGain();
        this.outputNode = this.audioContext.createGain();
        
        // Configurar processamento de áudio em tempo real
        const processor = this.audioContext.createScriptProcessor(
            512, // Buffer size
            2,   // Input channels
            2    // Output channels
        );
        
        processor.onaudioprocess = (event) => {
            const inputBuffer = event.inputBuffer;
            const outputBuffer = event.outputBuffer;
            
            // Enviar dados de entrada para o middleware ASIO
            this.sendAudioToASIO(inputBuffer);
            
            // Receber dados processados do ASIO e aplicar na saída
            this.receiveAudioFromASIO(outputBuffer);
        };
        
        // Conectar chain de áudio
        this.inputNode.connect(processor);
        processor.connect(this.outputNode);
        this.outputNode.connect(this.audioContext.destination);
        
        return {
            input: this.inputNode,
            output: this.outputNode,
            context: this.audioContext
        };
    }
    
    sendAudioToASIO(inputBuffer) {
        const inputData = [];
        
        for (let channel = 0; channel < inputBuffer.numberOfChannels; channel++) {
            const channelData = inputBuffer.getChannelData(channel);
            inputData.push(Array.from(channelData));
        }
        
        this.send({
            type: 'audio_input',
            data: {
                channels: inputBuffer.numberOfChannels,
                frames: inputBuffer.length,
                sampleRate: inputBuffer.sampleRate,
                audio: inputData
            }
        });
    }
    
    receiveAudioFromASIO(outputBuffer) {
        // Este método seria chamado quando dados de áudio chegam via WebSocket
        // Por enquanto, apenas passa o áudio através
        for (let channel = 0; channel < outputBuffer.numberOfChannels; channel++) {
            const channelData = outputBuffer.getChannelData(channel);
            // Aplicar dados recebidos do ASIO
            // channelData.set(asioOutputData[channel]);
        }
    }
    
    on(event, callback) {
        this.callbacks[event] = callback;
    }
    
    off(event) {
        this.callbacks[event] = null;
    }
}
```

## Integração com DAW

### Configuração de Áudio

```javascript
class DAWAudioConfig {
    constructor() {
        this.asioClient = new ASIOClient();
        this.currentConfig = {
            driver: null,
            sampleRate: 44100,
            bufferSize: 128,
            inputChannels: 2,
            outputChannels: 2
        };
    }
    
    async init() {
        try {
            await this.asioClient.connect();
            
            // Configurar callbacks
            this.asioClient.on('onConnect', () => {
                this.updateStatus('Conectado ao driver ASIO');
            });
            
            this.asioClient.on('onAudioData', (data) => {
                this.processAudioData(data);
            });
            
            this.asioClient.on('onError', (error) => {
                this.handleError(error);
            });
            
            return true;
        } catch (error) {
            console.error('Erro ao inicializar ASIO:', error);
            return false;
        }
    }
    
    async loadDrivers() {
        try {
            const devices = await this.asioClient.getAvailableDevices();
            return devices.map(device => ({
                id: device.id,
                name: device.name,
                manufacturer: device.manufacturer,
                inputChannels: device.inputChannels,
                outputChannels: device.outputChannels,
                supportedSampleRates: device.supportedSampleRates,
                supportedBufferSizes: device.supportedBufferSizes
            }));
        } catch (error) {
            console.error('Erro ao carregar drivers:', error);
            return [];
        }
    }
    
    async setDriver(driverId) {
        try {
            const result = await this.asioClient.configureAudio({
                ...this.currentConfig,
                driverName: driverId
            });
            
            if (result.success) {
                this.currentConfig.driver = driverId;
                this.updateLatencyDisplay(result.latency);
                return true;
            }
            
            return false;
        } catch (error) {
            console.error('Erro ao configurar driver:', error);
            return false;
        }
    }
    
    async setBufferSize(bufferSize) {
        try {
            const result = await this.asioClient.configureAudio({
                ...this.currentConfig,
                bufferSize: bufferSize
            });
            
            if (result.success) {
                this.currentConfig.bufferSize = bufferSize;
                this.updateLatencyDisplay(result.latency);
                return true;
            }
            
            return false;
        } catch (error) {
            console.error('Erro ao configurar buffer size:', error);
            return false;
        }
    }
    
    async setSampleRate(sampleRate) {
        try {
            const result = await this.asioClient.configureAudio({
                ...this.currentConfig,
                sampleRate: sampleRate
            });
            
            if (result.success) {
                this.currentConfig.sampleRate = sampleRate;
                return true;
            }
            
            return false;
        } catch (error) {
            console.error('Erro ao configurar sample rate:', error);
            return false;
        }
    }
    
    processAudioData(data) {
        // Integrar com engine de áudio da DAW
        if (window.audioEngine) {
            window.audioEngine.processASIOData(data);
        }
    }
    
    updateStatus(message) {
        const statusElement = document.getElementById('asio-status');
        if (statusElement) {
            statusElement.textContent = message;
        }
    }
    
    updateLatencyDisplay(latency) {
        const latencyElement = document.getElementById('asio-latency');
        if (latencyElement) {
            latencyElement.textContent = `${latency.toFixed(1)}ms`;
        }
    }
    
    handleError(error) {
        console.error('Erro ASIO:', error);
        this.updateStatus(`Erro: ${error.message}`);
    }
}
```

### Interface de Configuração

```html
<div class="asio-config-panel">
    <h3>Configuração ASIO</h3>
    
    <div class="config-section">
        <label for="asio-driver">Driver de Áudio:</label>
        <select id="asio-driver">
            <option value="">Selecione um driver...</option>
        </select>
        <button id="refresh-drivers">Atualizar</button>
    </div>
    
    <div class="config-section">
        <label for="sample-rate">Sample Rate:</label>
        <select id="sample-rate">
            <option value="44100">44.1 kHz</option>
            <option value="48000">48 kHz</option>
            <option value="88200">88.2 kHz</option>
            <option value="96000">96 kHz</option>
        </select>
    </div>
    
    <div class="config-section">
        <label for="buffer-size">Buffer Size:</label>
        <select id="buffer-size">
            <option value="64">64 samples</option>
            <option value="128">128 samples</option>
            <option value="256">256 samples</option>
            <option value="512">512 samples</option>
        </select>
    </div>
    
    <div class="status-section">
        <div class="status-item">
            <label>Status:</label>
            <span id="asio-status">Desconectado</span>
        </div>
        <div class="status-item">
            <label>Latência:</label>
            <span id="asio-latency">--</span>
        </div>
    </div>
    
    <div class="control-section">
        <button id="test-audio">Testar Áudio</button>
        <button id="control-panel">Painel de Controle</button>
    </div>
</div>
```

## Build e Deploy

### CMakeLists.txt

```cmake
cmake_minimum_required(VERSION 3.16)
project(DAWASIOMiddleware)

set(CMAKE_CXX_STANDARD 17)
set(CMAKE_CXX_STANDARD_REQUIRED ON)

# Encontrar dependências
find_package(Threads REQUIRED)

# Configurações específicas da plataforma
if(WIN32)
    # Windows - ASIO SDK
    set(ASIO_SDK_PATH "${CMAKE_SOURCE_DIR}/third_party/asiosdk")
    include_directories("${ASIO_SDK_PATH}/common")
    include_directories("${ASIO_SDK_PATH}/host")
    include_directories("${ASIO_SDK_PATH}/host/pc")
    
    set(ASIO_SOURCES
        "${ASIO_SDK_PATH}/common/asio.cpp"
        "${ASIO_SDK_PATH}/host/asiodrivers.cpp"
        "${ASIO_SDK_PATH}/host/pc/asiolist.cpp"
    )
    
    set(PLATFORM_LIBS ole32 oleaut32)
    
elseif(APPLE)
    # macOS - Core Audio
    find_library(CORE_AUDIO CoreAudio)
    find_library(AUDIO_UNIT AudioUnit)
    find_library(AUDIO_TOOLBOX AudioToolbox)
    
    set(PLATFORM_LIBS ${CORE_AUDIO} ${AUDIO_UNIT} ${AUDIO_TOOLBOX})
    
else()
    # Linux - ALSA/JACK
    find_package(PkgConfig REQUIRED)
    pkg_check_modules(ALSA REQUIRED alsa)
    pkg_check_modules(JACK jack)
    
    set(PLATFORM_LIBS ${ALSA_LIBRARIES})
    if(JACK_FOUND)
        list(APPEND PLATFORM_LIBS ${JACK_LIBRARIES})
    endif()
    
endif()

# WebSocket++
find_path(WEBSOCKETPP_INCLUDE_DIR websocketpp/config/asio_no_tls.hpp)
include_directories(${WEBSOCKETPP_INCLUDE_DIR})

# JSON
find_package(PkgConfig REQUIRED)
pkg_check_modules(JSONCPP REQUIRED jsoncpp)

# Fontes principais
set(SOURCES
    src/main.cpp
    src/ASIOManager.cpp
    src/DeviceManager.cpp
    src/BufferManager.cpp
    src/WebSocketServer.cpp
    ${ASIO_SOURCES}
)

# Executável
add_executable(${PROJECT_NAME} ${SOURCES})

# Bibliotecas
target_link_libraries(${PROJECT_NAME} 
    ${PLATFORM_LIBS}
    ${JSONCPP_LIBRARIES}
    Threads::Threads
)

# Flags de compilação
target_compile_definitions(${PROJECT_NAME} PRIVATE
    $<$<PLATFORM_ID:Windows>:WIN32_LEAN_AND_MEAN>
    $<$<PLATFORM_ID:Windows>:UNICODE>
    $<$<PLATFORM_ID:Windows>:_UNICODE>
)

# Instalação
install(TARGETS ${PROJECT_NAME} DESTINATION bin)
install(FILES config.json DESTINATION etc/daw)
```

### Script de Build

```bash
#!/bin/bash
# build.sh

set -e

# Configuração
BUILD_TYPE=${1:-Release}
BUILD_DIR="build"

echo "Building DAW ASIO Middleware..."
echo "Build type: $BUILD_TYPE"

# Criar diretório de build
mkdir -p $BUILD_DIR
cd $BUILD_DIR

# Configurar CMake
cmake .. -DCMAKE_BUILD_TYPE=$BUILD_TYPE

# Compilar
make -j$(nproc)

echo "Build completed successfully!"
echo "Executable: $BUILD_DIR/DAWASIOMiddleware"
```

A integração ASIO permite que a DAW Online atinja latências profissionais de menos de 10ms, essencial para gravação e monitoramento em tempo real.
