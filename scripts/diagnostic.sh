#!/bin/bash

# DAW Online - Script de Diagnóstico
# Verifica se todos os componentes estão funcionando corretamente

set -e

# Cores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# Contadores
PASSED=0
FAILED=0
WARNINGS=0

# Funções de teste
test_pass() {
    echo -e "${GREEN}✓ $1${NC}"
    ((PASSED++))
}

test_fail() {
    echo -e "${RED}✗ $1${NC}"
    ((FAILED++))
}

test_warn() {
    echo -e "${YELLOW}⚠ $1${NC}"
    ((WARNINGS++))
}

echo -e "${BLUE}"
echo "=========================================="
echo "     DAW Online - Diagnóstico do Sistema"
echo "=========================================="
echo -e "${NC}"

# 1. Verificar sistema operacional
echo -e "\n${BLUE}1. Sistema Operacional${NC}"
if grep -q "Ubuntu 22.04" /etc/os-release; then
    test_pass "Ubuntu 22.04 detectado"
else
    test_warn "Sistema operacional não é Ubuntu 22.04"
fi

# 2. Verificar serviços
echo -e "\n${BLUE}2. Serviços do Sistema${NC}"
services=("nginx" "php8.2-fpm" "mariadb" "redis-server")

for service in "${services[@]}"; do
    if systemctl is-active --quiet "$service"; then
        test_pass "$service está rodando"
    else
        test_fail "$service não está rodando"
    fi
done

# 3. Verificar portas
echo -e "\n${BLUE}3. Portas de Rede${NC}"
ports=("80:HTTP" "443:HTTPS" "8080:WebSocket" "3306:MySQL" "6379:Redis")

for port_info in "${ports[@]}"; do
    port="${port_info%:*}"
    name="${port_info#*:}"
    
    if netstat -tuln | grep -q ":$port "; then
        test_pass "Porta $port ($name) está aberta"
    else
        test_fail "Porta $port ($name) não está aberta"
    fi
done

# 4. Verificar PHP
echo -e "\n${BLUE}4. Configuração PHP${NC}"

# Versão do PHP
php_version=$(php -v | head -n1 | cut -d' ' -f2)
if [[ $php_version == 8.2* ]]; then
    test_pass "PHP versão $php_version"
else
    test_warn "PHP versão $php_version (recomendado: 8.2+)"
fi

# Extensões PHP obrigatórias
php_extensions=("mysql" "gd" "curl" "json" "mbstring" "openssl" "zip" "redis" "opcache")

for ext in "${php_extensions[@]}"; do
    if php -m | grep -q "$ext"; then
        test_pass "Extensão PHP $ext instalada"
    else
        test_fail "Extensão PHP $ext não encontrada"
    fi
done

# 5. Verificar banco de dados
echo -e "\n${BLUE}5. Banco de Dados${NC}"

if command -v mysql >/dev/null 2>&1; then
    test_pass "MySQL/MariaDB client instalado"
    
    # Verificar conexão (se credenciais estiverem disponíveis)
    if [[ -f "/var/www/daw-online/config/.env" ]]; then
        source /var/www/daw-online/config/.env 2>/dev/null || true
        if [[ -n "$DB_USERNAME" && -n "$DB_PASSWORD" ]]; then
            if mysql -u "$DB_USERNAME" -p"$DB_PASSWORD" -e "SELECT 1;" >/dev/null 2>&1; then
                test_pass "Conexão com banco de dados OK"
            else
                test_fail "Não foi possível conectar ao banco"
            fi
        fi
    fi
else
    test_fail "Cliente MySQL/MariaDB não encontrado"
fi

# 6. Verificar Redis
echo -e "\n${BLUE}6. Redis${NC}"

if command -v redis-cli >/dev/null 2>&1; then
    test_pass "Redis client instalado"
    
    if redis-cli ping 2>/dev/null | grep -q "PONG"; then
        test_pass "Redis respondendo"
    else
        test_fail "Redis não está respondendo"
    fi
else
    test_fail "Redis client não encontrado"
fi

# 7. Verificar arquivos da aplicação
echo -e "\n${BLUE}7. Arquivos da Aplicação${NC}"

app_files=(
    "/var/www/daw-online/config/.env:Arquivo de configuração"
    "/var/www/daw-online/frontend/index.html:Frontend principal"
    "/var/www/daw-online/api/index.php:API backend"
    "/var/www/daw-online/composer.json:Composer config"
)

for file_info in "${app_files[@]}"; do
    file="${file_info%:*}"
    name="${file_info#*:}"
    
    if [[ -f "$file" ]]; then
        test_pass "$name existe"
    else
        test_fail "$name não encontrado"
    fi
done

# 8. Verificar permissões
echo -e "\n${BLUE}8. Permissões de Arquivos${NC}"

directories=(
    "/var/www/daw-online/uploads"
    "/var/www/daw-online/storage"
    "/var/www/daw-online/cache"
)

for dir in "${directories[@]}"; do
    if [[ -d "$dir" ]]; then
        owner=$(stat -c '%U:%G' "$dir")
        if [[ "$owner" == "www-data:www-data" ]]; then
            test_pass "Permissões de $dir OK ($owner)"
        else
            test_warn "Permissões de $dir: $owner (esperado: www-data:www-data)"
        fi
    else
        test_fail "Diretório $dir não existe"
    fi
done

# 9. Verificar SSL
echo -e "\n${BLUE}9. Certificados SSL${NC}"

if [[ -d "/etc/letsencrypt/live" ]]; then
    cert_dirs=$(find /etc/letsencrypt/live -maxdepth 1 -type d | wc -l)
    if [[ $cert_dirs -gt 1 ]]; then
        test_pass "Certificados SSL encontrados"
        
        # Verificar validade
        for cert_dir in /etc/letsencrypt/live/*/; do
            if [[ -f "$cert_dir/fullchain.pem" ]]; then
                domain=$(basename "$cert_dir")
                expiry=$(openssl x509 -in "$cert_dir/fullchain.pem" -noout -enddate | cut -d= -f2)
                test_pass "Certificado para $domain válido até: $expiry"
            fi
        done
    else
        test_warn "Nenhum certificado SSL encontrado"
    fi
else
    test_warn "Let's Encrypt não configurado"
fi

# 10. Verificar Node.js e PM2
echo -e "\n${BLUE}10. Node.js e WebSocket${NC}"

if command -v node >/dev/null 2>&1; then
    node_version=$(node --version)
    test_pass "Node.js $node_version instalado"
else
    test_fail "Node.js não encontrado"
fi

if command -v pm2 >/dev/null 2>&1; then
    test_pass "PM2 instalado"
    
    if pm2 list | grep -q "daw-websocket\|online"; then
        test_pass "WebSocket server rodando via PM2"
    else
        test_warn "WebSocket server não encontrado no PM2"
    fi
else
    test_fail "PM2 não encontrado"
fi

# 11. Verificar logs
echo -e "\n${BLUE}11. Logs do Sistema${NC}"

log_files=(
    "/var/log/nginx/access.log:Nginx access log"
    "/var/log/nginx/error.log:Nginx error log"
    "/var/log/php8.2-fpm.log:PHP-FPM log"
)

for log_info in "${log_files[@]}"; do
    log="${log_info%:*}"
    name="${log_info#*:}"
    
    if [[ -f "$log" ]]; then
        size=$(du -h "$log" | cut -f1)
        test_pass "$name ($size)"
    else
        test_warn "$name não encontrado"
    fi
done

# 12. Teste de conectividade
echo -e "\n${BLUE}12. Testes de Conectividade${NC}"

# Teste HTTP local
if curl -s -o /dev/null -w "%{http_code}" http://localhost | grep -q "200\|301\|302"; then
    test_pass "HTTP local respondendo"
else
    test_fail "HTTP local não está respondendo"
fi

# Teste HTTPS local (se SSL estiver configurado)
if curl -s -k -o /dev/null -w "%{http_code}" https://localhost 2>/dev/null | grep -q "200\|301\|302"; then
    test_pass "HTTPS local respondendo"
else
    test_warn "HTTPS local não está respondendo (normal se SSL não configurado)"
fi

# 13. Verificar recursos do sistema
echo -e "\n${BLUE}13. Recursos do Sistema${NC}"

# RAM
total_ram=$(free -h | awk '/^Mem:/ {print $2}')
used_ram=$(free -h | awk '/^Mem:/ {print $3}')
test_pass "RAM: $used_ram usado de $total_ram total"

# Espaço em disco
disk_usage=$(df -h /var/www | awk 'NR==2 {print $5}' | sed 's/%//')
if [[ $disk_usage -lt 80 ]]; then
    test_pass "Espaço em disco: ${disk_usage}% usado"
else
    test_warn "Espaço em disco: ${disk_usage}% usado (>80%)"
fi

# CPU Load
load_avg=$(uptime | awk -F'load average:' '{ print $2 }' | cut -d, -f1 | xargs)
test_pass "Load average: $load_avg"

# 14. Verificar firewall
echo -e "\n${BLUE}14. Firewall${NC}"

if command -v ufw >/dev/null 2>&1; then
    if ufw status | grep -q "Status: active"; then
        test_pass "UFW firewall ativo"
    else
        test_warn "UFW firewall inativo"
    fi
else
    test_warn "UFW não encontrado"
fi

# Resumo final
echo -e "\n${BLUE}=========================================="
echo "              RESUMO DO DIAGNÓSTICO"
echo "==========================================${NC}"

total=$((PASSED + FAILED + WARNINGS))

echo -e "${GREEN}✓ Testes passou: $PASSED${NC}"
echo -e "${RED}✗ Testes falharam: $FAILED${NC}"
echo -e "${YELLOW}⚠ Avisos: $WARNINGS${NC}"
echo -e "Total de verificações: $total"

if [[ $FAILED -eq 0 ]]; then
    echo -e "\n${GREEN}🎉 Sistema está funcionando corretamente!${NC}"
    exit_code=0
elif [[ $FAILED -le 2 ]]; then
    echo -e "\n${YELLOW}⚠ Sistema funcional com pequenos problemas${NC}"
    exit_code=1
else
    echo -e "\n${RED}❌ Sistema tem problemas significativos${NC}"
    exit_code=2
fi

echo -e "\n${BLUE}Para mais informações, consulte:${NC}"
echo "- Logs: /var/log/nginx/"
echo "- Configuração: /var/www/daw-online/config/.env"
echo "- Status dos serviços: systemctl status <service>"

exit $exit_code
