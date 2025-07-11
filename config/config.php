<?php
/**
 * Configuração principal da DAW Online
 */

// Configurações do banco de dados
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'daw_online');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_CHARSET', 'utf8mb4');

// Configurações de segurança
define('JWT_SECRET', getenv('JWT_SECRET') ?: 'your-super-secret-key-change-this-in-production');
define('JWT_ALGORITHM', 'HS256');
define('JWT_EXPIRATION', 86400); // 24 horas
define('BCRYPT_COST', 12);

// Configurações de upload
define('UPLOAD_MAX_SIZE', 100 * 1024 * 1024); // 100MB
define('UPLOAD_ALLOWED_TYPES', ['wav', 'aiff', 'mp3', 'flac', 'ogg']);
define('UPLOAD_PATH', __DIR__ . '/../uploads/');

// Configurações de áudio
define('DEFAULT_SAMPLE_RATE', 44100);
define('DEFAULT_BUFFER_SIZE', 512);
define('DEFAULT_BIT_DEPTH', 24);
define('MAX_TRACKS_PER_PROJECT', 128);
define('MAX_PLUGINS_PER_TRACK', 16);

// Configurações do WebSocket
define('WEBSOCKET_HOST', getenv('WEBSOCKET_HOST') ?: '0.0.0.0');
define('WEBSOCKET_PORT', getenv('WEBSOCKET_PORT') ?: 8080);

// Configurações de plugins
define('PLUGIN_PATH', __DIR__ . '/../plugins/');
define('VST_PATH', PLUGIN_PATH . 'vst/');
define('VST3_PATH', PLUGIN_PATH . 'vst3/');
define('AU_PATH', PLUGIN_PATH . 'au/');
define('AAX_PATH', PLUGIN_PATH . 'aax/');

// Configurações de sistema
define('DEBUG_MODE', getenv('DEBUG_MODE') ?: false);
define('LOG_LEVEL', getenv('LOG_LEVEL') ?: 'INFO');
define('LOG_PATH', __DIR__ . '/../logs/');

// Configurações de CORS
define('ALLOWED_ORIGINS', [
    'http://localhost:3000',
    'http://localhost:8000',
    'https://dawonline.com'
]);

// Configurações de rate limiting
define('RATE_LIMIT_REQUESTS', 100);
define('RATE_LIMIT_WINDOW', 3600); // 1 hora

// Configurações de cache
define('CACHE_TTL', 3600); // 1 hora
define('CACHE_PREFIX', 'daw_online:');

// Configurações de email (para notificações)
define('MAIL_HOST', getenv('MAIL_HOST') ?: 'localhost');
define('MAIL_PORT', getenv('MAIL_PORT') ?: 587);
define('MAIL_USERNAME', getenv('MAIL_USERNAME') ?: '');
define('MAIL_PASSWORD', getenv('MAIL_PASSWORD') ?: '');
define('MAIL_FROM', getenv('MAIL_FROM') ?: 'noreply@dawonline.com');

// Timezone
date_default_timezone_set('America/Sao_Paulo');

// Headers de segurança
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Strict-Transport-Security: max-age=31536000; includeSubDomains');

// Autoload de classes
spl_autoload_register(function ($class) {
    $prefix = 'DAWOnline\\';
    $base_dir = __DIR__ . '/../backend/src/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

// Configurações específicas por ambiente
if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Função para carregar configurações do arquivo .env
function loadEnv($file = '.env') {
    if (file_exists($file)) {
        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos($line, '=') !== false && $line[0] !== '#') {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                if (!array_key_exists($key, $_ENV)) {
                    putenv("$key=$value");
                    $_ENV[$key] = $value;
                }
            }
        }
    }
}

// Carregar variáveis de ambiente
loadEnv(__DIR__ . '/../.env');
