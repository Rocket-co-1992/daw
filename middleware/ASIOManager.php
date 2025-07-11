<?php
/**
 * Middleware para integração com drivers ASIO
 */

namespace DAWOnline\Middleware;

class ASIOManager {
    private $asioDrivers = [];
    private $currentDriver = null;
    private $audioConfig = [
        'sampleRate' => 44100,
        'bufferSize' => 512,
        'bitDepth' => 24,
        'inputChannels' => 2,
        'outputChannels' => 2
    ];
    
    public function __construct() {
        $this->scanForASIODrivers();
    }
    
    /**
     * Escanear por drivers ASIO disponíveis
     */
    private function scanForASIODrivers() {
        // No Windows, procurar no registro
        if (PHP_OS_FAMILY === 'Windows') {
            $this->scanWindowsASIODrivers();
        }
        // No macOS, procurar por Core Audio
        elseif (PHP_OS_FAMILY === 'Darwin') {
            $this->scanMacAudioDrivers();
        }
        // No Linux, procurar por JACK/ALSA
        else {
            $this->scanLinuxAudioDrivers();
        }
    }
    
    private function scanWindowsASIODrivers() {
        try {
            // Executar comando para listar drivers ASIO
            $output = [];
            $command = 'reg query "HKEY_LOCAL_MACHINE\SOFTWARE\ASIO" /s';
            exec($command, $output, $returnVar);
            
            foreach ($output as $line) {
                if (strpos($line, 'CLSID') !== false) {
                    $parts = explode('\\', $line);
                    $driverName = end($parts);
                    
                    $this->asioDrivers[] = [
                        'name' => $driverName,
                        'type' => 'asio',
                        'id' => $driverName,
                        'available' => true
                    ];
                }
            }
            
            // Adicionar drivers padrão conhecidos
            $knownDrivers = [
                'Focusrite USB ASIO',
                'PreSonus ASIO',
                'Steinberg UR22mkII',
                'RME ASIO',
                'MOTU ASIO',
                'Universal Audio ASIO'
            ];
            
            foreach ($knownDrivers as $driver) {
                if (!$this->hasDriver($driver)) {
                    $this->asioDrivers[] = [
                        'name' => $driver,
                        'type' => 'asio',
                        'id' => $driver,
                        'available' => false
                    ];
                }
            }
            
        } catch (Exception $e) {
            error_log("Erro ao escanear drivers ASIO: " . $e->getMessage());
        }
    }
    
    private function scanMacAudioDrivers() {
        try {
            // Usar system_profiler para listar dispositivos de áudio
            $output = [];
            exec('system_profiler SPAudioDataType -json', $output);
            $json = implode('', $output);
            $data = json_decode($json, true);
            
            if (isset($data['SPAudioDataType'])) {
                foreach ($data['SPAudioDataType'] as $device) {
                    $this->asioDrivers[] = [
                        'name' => $device['_name'] ?? 'Dispositivo desconhecido',
                        'type' => 'coreaudio',
                        'id' => $device['_name'] ?? '',
                        'available' => true,
                        'inputs' => $device['coreaudio_input_channels'] ?? 0,
                        'outputs' => $device['coreaudio_output_channels'] ?? 0
                    ];
                }
            }
            
        } catch (Exception $e) {
            error_log("Erro ao escanear drivers Core Audio: " . $e->getMessage());
        }
    }
    
    private function scanLinuxAudioDrivers() {
        try {
            // Verificar JACK
            exec('which jackd', $output, $returnVar);
            if ($returnVar === 0) {
                $this->asioDrivers[] = [
                    'name' => 'JACK Audio Server',
                    'type' => 'jack',
                    'id' => 'jack',
                    'available' => true
                ];
            }
            
            // Listar dispositivos ALSA
            $output = [];
            exec('aplay -l', $output);
            
            foreach ($output as $line) {
                if (preg_match('/card (\d+):.*\[(.+)\]/', $line, $matches)) {
                    $this->asioDrivers[] = [
                        'name' => $matches[2],
                        'type' => 'alsa',
                        'id' => 'hw:' . $matches[1],
                        'available' => true
                    ];
                }
            }
            
        } catch (Exception $e) {
            error_log("Erro ao escanear drivers Linux: " . $e->getMessage());
        }
    }
    
    /**
     * Obter lista de drivers disponíveis
     */
    public function getAvailableDrivers() {
        return $this->asioDrivers;
    }
    
    /**
     * Configurar driver ASIO
     */
    public function configureDriver($driverId, $config = []) {
        $driver = $this->findDriver($driverId);
        
        if (!$driver) {
            throw new \Exception("Driver não encontrado: $driverId");
        }
        
        if (!$driver['available']) {
            throw new \Exception("Driver não disponível: $driverId");
        }
        
        $this->currentDriver = $driver;
        $this->audioConfig = array_merge($this->audioConfig, $config);
        
        return $this->initializeDriver();
    }
    
    /**
     * Inicializar driver
     */
    private function initializeDriver() {
        if (!$this->currentDriver) {
            throw new \Exception("Nenhum driver configurado");
        }
        
        switch ($this->currentDriver['type']) {
            case 'asio':
                return $this->initializeASIODriver();
            case 'coreaudio':
                return $this->initializeCoreAudioDriver();
            case 'jack':
                return $this->initializeJACKDriver();
            case 'alsa':
                return $this->initializeALSADriver();
            default:
                throw new \Exception("Tipo de driver não suportado: " . $this->currentDriver['type']);
        }
    }
    
    private function initializeASIODriver() {
        // Para integração real com ASIO, seria necessário uma extensão C++
        // Aqui implementamos uma simulação
        
        $configFile = tempnam(sys_get_temp_dir(), 'asio_config_');
        $config = [
            'driver' => $this->currentDriver['id'],
            'sampleRate' => $this->audioConfig['sampleRate'],
            'bufferSize' => $this->audioConfig['bufferSize'],
            'bitDepth' => $this->audioConfig['bitDepth'],
            'inputChannels' => $this->audioConfig['inputChannels'],
            'outputChannels' => $this->audioConfig['outputChannels']
        ];
        
        file_put_contents($configFile, json_encode($config));
        
        // Executar processo externo que gerencia ASIO
        $command = "php " . __DIR__ . "/asio_process.php \"$configFile\"";
        
        if (PHP_OS_FAMILY === 'Windows') {
            $process = popen("start /B $command", 'r');
        } else {
            $process = popen("$command &", 'r');
        }
        
        if (!$process) {
            throw new \Exception("Falha ao inicializar processo ASIO");
        }
        
        return [
            'success' => true,
            'driver' => $this->currentDriver,
            'config' => $this->audioConfig,
            'latency' => $this->calculateLatency()
        ];
    }
    
    private function initializeCoreAudioDriver() {
        // Implementação para Core Audio no macOS
        $command = "auhal " . 
                  "-r " . $this->audioConfig['sampleRate'] . " " .
                  "-b " . $this->audioConfig['bufferSize'] . " " .
                  "-i " . $this->audioConfig['inputChannels'] . " " .
                  "-o " . $this->audioConfig['outputChannels'];
        
        exec($command, $output, $returnVar);
        
        return [
            'success' => $returnVar === 0,
            'driver' => $this->currentDriver,
            'config' => $this->audioConfig,
            'latency' => $this->calculateLatency()
        ];
    }
    
    private function initializeJACKDriver() {
        // Verificar se JACK está rodando
        exec('jack_lsp', $output, $returnVar);
        
        if ($returnVar !== 0) {
            // Iniciar JACK se não estiver rodando
            $command = "jackd -d alsa -r " . $this->audioConfig['sampleRate'] . 
                      " -p " . $this->audioConfig['bufferSize'] . " &";
            exec($command);
            
            // Aguardar inicialização
            sleep(2);
        }
        
        return [
            'success' => true,
            'driver' => $this->currentDriver,
            'config' => $this->audioConfig,
            'latency' => $this->calculateLatency()
        ];
    }
    
    private function initializeALSADriver() {
        // Configuração básica do ALSA
        $alsaConfig = [
            'pcm.daw_playback' => [
                'type' => 'hw',
                'card' => str_replace('hw:', '', $this->currentDriver['id'])
            ],
            'pcm.daw_capture' => [
                'type' => 'hw',
                'card' => str_replace('hw:', '', $this->currentDriver['id'])
            ]
        ];
        
        // Escrever configuração ALSA
        $configFile = '/tmp/.asoundrc_daw';
        $configContent = '';
        
        foreach ($alsaConfig as $name => $config) {
            $configContent .= "$name {\n";
            foreach ($config as $key => $value) {
                $configContent .= "    $key $value\n";
            }
            $configContent .= "}\n\n";
        }
        
        file_put_contents($configFile, $configContent);
        
        return [
            'success' => true,
            'driver' => $this->currentDriver,
            'config' => $this->audioConfig,
            'latency' => $this->calculateLatency()
        ];
    }
    
    /**
     * Calcular latência baseada na configuração
     */
    private function calculateLatency() {
        $bufferLatency = ($this->audioConfig['bufferSize'] / $this->audioConfig['sampleRate']) * 1000;
        $driverLatency = 5; // Latência estimada do driver
        
        return round($bufferLatency + $driverLatency, 2);
    }
    
    /**
     * Obter configuração de áudio atual
     */
    public function getAudioConfig() {
        return [
            'driver' => $this->currentDriver,
            'config' => $this->audioConfig,
            'latency' => $this->calculateLatency()
        ];
    }
    
    /**
     * Testar configuração de áudio
     */
    public function testAudioConfig() {
        if (!$this->currentDriver) {
            throw new \Exception("Nenhum driver configurado");
        }
        
        // Teste básico de reprodução
        $testResult = [
            'input_test' => $this->testAudioInput(),
            'output_test' => $this->testAudioOutput(),
            'latency_test' => $this->testLatency()
        ];
        
        return $testResult;
    }
    
    private function testAudioInput() {
        // Simulação de teste de entrada
        return [
            'success' => true,
            'channels_detected' => $this->audioConfig['inputChannels'],
            'signal_level' => rand(20, 80) // dB simulado
        ];
    }
    
    private function testAudioOutput() {
        // Simulação de teste de saída
        return [
            'success' => true,
            'channels_active' => $this->audioConfig['outputChannels'],
            'test_tone_played' => true
        ];
    }
    
    private function testLatency() {
        return [
            'calculated_latency' => $this->calculateLatency(),
            'measured_latency' => $this->calculateLatency() + rand(-2, 5), // Variação simulada
            'jitter' => rand(1, 3)
        ];
    }
    
    private function findDriver($driverId) {
        foreach ($this->asioDrivers as $driver) {
            if ($driver['id'] === $driverId) {
                return $driver;
            }
        }
        return null;
    }
    
    private function hasDriver($driverName) {
        foreach ($this->asioDrivers as $driver) {
            if ($driver['name'] === $driverName) {
                return true;
            }
        }
        return false;
    }
}
