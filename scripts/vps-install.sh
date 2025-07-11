#!/bin/bash

# DAW Online - Script de Instalação Automática para VPS Ubuntu 22.04
# Versão: 1.0
# Autor: DAW Online Team

set -e  # Parar em caso de erro

# Cores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Função para logging
log() {
    echo -e "${GREEN}[$(date +'%Y-%m-%d %H:%M:%S')] $1${NC}"
}

error() {
    echo -e "${RED}[ERROR] $1${NC}"
    exit 1
}

warning() {
    echo -e "${YELLOW}[WARNING] $1${NC}"
}

info() {
    echo -e "${BLUE}[INFO] $1${NC}"
}

# Verificar se é root
if [[ $EUID -ne 0 ]]; then
   error "Este script deve ser executado como root (use sudo)"
fi

# Banner
echo -e "${BLUE}"
cat << "EOF"
 ____    _    _       __   ___        _ _            
|  _ \  / \  | |  _ __\ \ / / |      (_) |  ___   
| | | |/ _ \ | | |___ _\ \ V /| |     | | | / _ \
| |_| / ___ \|___| _ \|  |\_| | |___  | | ||  __/
|____/_/   \_\   |_| |_|    |_____|  |_|_| \___|
                                                   
         VPS Installation Script v1.0
         Ubuntu 22.04 LTS Automated Setup
EOF
echo -e "${NC}"

# Verificar versão do Ubuntu
if ! grep -q "Ubuntu 22.04" /etc/os-release; then
    warning "Este script foi testado no Ubuntu 22.04. Continuando mesmo assim..."
    read -p "Deseja continuar? (y/N): " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        exit 1
    fi
fi

# Configurações (podem ser alteradas)
DB_NAME="daw_online"
DB_USER="daw_user"
DB_PASSWORD=$(openssl rand -base64 32)
REDIS_PASSWORD=$(openssl rand -base64 32)
JWT_SECRET=$(openssl rand -base64 64)
DOMAIN=""
EMAIL=""

# Solicitar informações do usuário
echo
read -p "Digite seu domínio (ex: meusite.com): " DOMAIN
read -p "Digite seu email para SSL (ex: admin@meusite.com): " EMAIL

if [[ -z "$DOMAIN" || -z "$EMAIL" ]]; then
    error "Domínio e email são obrigatórios!"
fi

log "Iniciando instalação do DAW Online..."
log "Domínio: $DOMAIN"
log "Email: $EMAIL"

# 1. Atualizar sistema
log "Atualizando sistema..."
export DEBIAN_FRONTEND=noninteractive
apt update && apt upgrade -y

# 2. Instalar utilitários essenciais
log "Instalando utilitários essenciais..."
apt install -y curl wget unzip software-properties-common apt-transport-https \
    ca-certificates gnupg lsb-release git htop fail2ban ufw logrotate

# 3. Configurar firewall
log "Configurando firewall..."
ufw --force enable
ufw allow 22/tcp
ufw allow 80/tcp
ufw allow 443/tcp
ufw allow 8080/tcp

# 4. Instalar PHP 8.2
log "Instalando PHP 8.2 e extensões..."
add-apt-repository ppa:ondrej/php -y
apt update

# Verificar se o repositório foi adicionado corretamente
if ! apt-cache search php8.2 | grep -q "php8.2 "; then
    error "Repositório PHP 8.2 não foi encontrado. Verifique sua conexão com a internet."
fi

# Instalar PHP 8.2 e extensões (json e openssl vêm incluídos no core)
log "Instalando pacotes PHP 8.2..."
apt install -y php8.2 php8.2-fpm php8.2-mysql php8.2-gd php8.2-curl \
    php8.2-mbstring php8.2-zip php8.2-redis php8.2-intl php8.2-xml \
    php8.2-cli php8.2-bcmath php8.2-soap php8.2-imagick php8.2-dev \
    php8.2-imap php8.2-opcache

# Verificar se PHP foi instalado corretamente
if ! command -v php >/dev/null 2>&1; then
    error "PHP não foi instalado corretamente"
fi

# Verificar versão do PHP
php_version=$(php -v | head -n1 | cut -d' ' -f2)
info "PHP instalado: versão $php_version"

# Verificar se JSON funciona (deve estar incluído por padrão)
if ! php -m | grep -q json; then
    warning "Extensão JSON não detectada (pode ser normal no PHP 8.2+)"
else
    info "Extensão JSON detectada e funcionando"
fi

# 5. Instalar Composer
log "Instalando Composer..."
curl -sS https://getcomposer.org/installer | php
mv composer.phar /usr/local/bin/composer
chmod +x /usr/local/bin/composer

# 6. Instalar MariaDB
log "Instalando MariaDB..."
apt install -y mariadb-server mariadb-client

# Configurar MariaDB de forma não-interativa
mysql -e "CREATE DATABASE IF NOT EXISTS $DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -e "CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASSWORD';"
mysql -e "GRANT ALL PRIVILEGES ON $DB_NAME.* TO '$DB_USER'@'localhost';"
mysql -e "FLUSH PRIVILEGES;"

# 7. Instalar Redis
log "Instalando Redis..."
apt install -y redis-server

# Configurar Redis
sed -i "s/# requirepass foobared/requirepass $REDIS_PASSWORD/" /etc/redis/redis.conf
systemctl restart redis-server
systemctl enable redis-server

# 8. Instalar Nginx
log "Instalando Nginx..."
apt install -y nginx

# 9. Instalar Node.js 18
log "Instalando Node.js..."
curl -fsSL https://deb.nodesource.com/setup_18.x | bash -
apt install -y nodejs
npm install -g pm2

# 10. Configurar diretório da aplicação
log "Configurando aplicação..."
APP_DIR="/var/www/daw"

if [[ ! -d "$APP_DIR" ]]; then
    error "Diretório da aplicação não encontrado em $APP_DIR"
fi

cd "$APP_DIR"

# 11. Instalar dependências PHP
log "Instalando dependências PHP..."
composer install --no-dev --optimize-autoloader

# 12. Configurar permissões
log "Configurando permissões..."
chown -R www-data:www-data "$APP_DIR"
chmod -R 755 "$APP_DIR"
chmod -R 775 "$APP_DIR/storage" 2>/dev/null || mkdir -p "$APP_DIR/storage" && chmod -R 775 "$APP_DIR/storage"
chmod -R 775 "$APP_DIR/uploads" 2>/dev/null || mkdir -p "$APP_DIR/uploads" && chmod -R 775 "$APP_DIR/uploads"
chmod -R 775 "$APP_DIR/cache" 2>/dev/null || mkdir -p "$APP_DIR/cache" && chmod -R 775 "$APP_DIR/cache"

# 13. Criar arquivo de configuração
log "Criando arquivo de configuração..."
cat > "$APP_DIR/config/.env" << EOF
# Configuração gerada automaticamente
# $(date)

APP_ENV=production
APP_DEBUG=false
APP_URL=https://$DOMAIN

# Banco de Dados
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=$DB_NAME
DB_USERNAME=$DB_USER
DB_PASSWORD=$DB_PASSWORD

# Redis
REDIS_HOST=localhost
REDIS_PORT=6379
REDIS_PASSWORD=$REDIS_PASSWORD

# JWT
JWT_SECRET=$JWT_SECRET
JWT_ALGORITHM=HS256
JWT_EXPIRATION=3600

# Uploads
UPLOAD_MAX_SIZE=100MB
UPLOAD_PATH=/var/www/daw/uploads

# WebSocket
WEBSOCKET_HOST=0.0.0.0
WEBSOCKET_PORT=8080

# SSL
SSL_CERT=/etc/letsencrypt/live/$DOMAIN/fullchain.pem
SSL_KEY=/etc/letsencrypt/live/$DOMAIN/privkey.pem

# Email (configure conforme necessário)
MAIL_HOST=localhost
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_FROM=$EMAIL
EOF

# 14. Configurar Nginx
log "Configurando Nginx..."
cat > "/etc/nginx/sites-available/$DOMAIN" << EOF
server {
    listen 80;
    server_name $DOMAIN www.$DOMAIN;
    return 301 https://\$server_name\$request_uri;
}

server {
    listen 443 ssl http2;
    server_name $DOMAIN www.$DOMAIN;
    root /var/www/daw/frontend;
    index index.html index.php;

    # SSL Configuration (será configurado pelo Certbot)
    ssl_certificate /etc/letsencrypt/live/$DOMAIN/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/$DOMAIN/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-RSA-AES128-GCM-SHA256:ECDHE-RSA-AES256-GCM-SHA384;
    ssl_prefer_server_ciphers off;

    # Security headers
    add_header X-Frame-Options DENY;
    add_header X-Content-Type-Options nosniff;
    add_header X-XSS-Protection "1; mode=block";
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains";

    # API routes
    location /api/ {
        try_files \$uri \$uri/ /api/index.php?\$query_string;
    }

    # PHP processing
    location ~ \.php\$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        include fastcgi_params;
        
        # Security
        fastcgi_param PHP_VALUE "upload_max_filesize=100M \n post_max_size=100M";
        fastcgi_read_timeout 300;
    }

    # WebSocket proxy
    location /ws {
        proxy_pass http://localhost:8080;
        proxy_http_version 1.1;
        proxy_set_header Upgrade \$http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
    }

    # Static files
    location ~* \.(css|js|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)\$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }

    # Audio files
    location ~* \.(wav|mp3|flac|aiff|m4a)\$ {
        expires 30d;
        add_header Cache-Control "public";
        add_header Access-Control-Allow-Origin "*";
    }

    # Deny access to sensitive files
    location ~ /\. {
        deny all;
    }
    
    location ~ /(config|scripts|tests|vendor)/ {
        deny all;
    }
}
EOF

# Ativar site
ln -sf "/etc/nginx/sites-available/$DOMAIN" "/etc/nginx/sites-enabled/"
rm -f /etc/nginx/sites-enabled/default

# Testar configuração
nginx -t || error "Configuração do Nginx inválida"

# 15. Configurar PHP-FPM
log "Otimizando PHP-FPM..."
cat >> "/etc/php/8.2/fpm/pool.d/www.conf" << EOF

; Otimizações para DAW Online
pm = dynamic
pm.max_children = 50
pm.start_servers = 5
pm.min_spare_servers = 5
pm.max_spare_servers = 35
pm.process_idle_timeout = 10s
pm.max_requests = 500
EOF

# Configurar OPcache
cat > "/etc/php/8.2/fpm/conf.d/99-daw-opcache.ini" << EOF
opcache.enable=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=8
opcache.max_accelerated_files=4000
opcache.revalidate_freq=2
opcache.fast_shutdown=1
opcache.enable_cli=1
EOF

# 16. Importar schema do banco
log "Importando schema do banco de dados..."
if [[ -f "$APP_DIR/database/schema.sql" ]]; then
    mysql -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" < "$APP_DIR/database/schema.sql"
else
    warning "Schema do banco não encontrado. Execute manualmente após a instalação."
fi

# 17. Configurar WebSocket Server
log "Configurando WebSocket Server..."
cd "$APP_DIR/websocket" 2>/dev/null || mkdir -p "$APP_DIR/websocket"

if [[ -f "$APP_DIR/websocket/package.json" ]]; then
    cd "$APP_DIR/websocket"
    npm install
    
    # Configurar PM2
    pm2 start ecosystem.config.js 2>/dev/null || pm2 start server.js --name "daw-websocket"
    pm2 save
    pm2 startup
fi

# 18. Instalar SSL com Let's Encrypt
log "Configurando SSL com Let's Encrypt..."
apt install -y certbot python3-certbot-nginx

# Gerar certificado
certbot --nginx --non-interactive --agree-tos --email "$EMAIL" -d "$DOMAIN" -d "www.$DOMAIN"

# 19. Reiniciar serviços
log "Reiniciando serviços..."
systemctl restart php8.2-fpm
systemctl restart nginx
systemctl restart redis-server
systemctl enable php8.2-fpm
systemctl enable nginx
systemctl enable mariadb

# 20. Configurar fail2ban
log "Configurando Fail2Ban..."
cat > "/etc/fail2ban/jail.local" << EOF
[DEFAULT]
bantime = 3600
findtime = 600
maxretry = 5

[nginx-http-auth]
enabled = true

[nginx-limit-req]
enabled = true

[sshd]
enabled = true
port = ssh
logpath = %(sshd_log)s
backend = %(sshd_backend)s
EOF

systemctl restart fail2ban
systemctl enable fail2ban

# 21. Criar scripts de manutenção
log "Criando scripts de manutenção..."
mkdir -p "$APP_DIR/scripts"

# Script de backup
cat > "$APP_DIR/scripts/backup.sh" << EOF
#!/bin/bash
BACKUP_DIR="/var/backups/daw"
DATE=\$(date +%Y%m%d_%H%M%S)

mkdir -p "\$BACKUP_DIR"

# Backup do banco
mysqldump -u $DB_USER -p$DB_PASSWORD $DB_NAME > "\$BACKUP_DIR/db_\$DATE.sql"

# Backup dos arquivos
tar -czf "\$BACKUP_DIR/files_\$DATE.tar.gz" -C /var/www daw/uploads daw/config/.env

# Manter apenas os últimos 7 backups
find "\$BACKUP_DIR" -name "*.sql" -mtime +7 -delete
find "\$BACKUP_DIR" -name "*.tar.gz" -mtime +7 -delete

echo "Backup concluído: \$DATE"
EOF

chmod +x "$APP_DIR/scripts/backup.sh"

# Script de health check
cat > "$APP_DIR/scripts/health-check.sh" << EOF
#!/bin/bash
# Verificar serviços essenciais
services=("nginx" "php8.2-fpm" "mariadb" "redis-server")

for service in "\${services[@]}"; do
    if ! systemctl is-active --quiet "\$service"; then
        echo "ERRO: \$service não está rodando"
        systemctl restart "\$service"
    fi
done

# Verificar WebSocket
if ! pm2 describe daw-websocket > /dev/null 2>&1; then
    echo "ERRO: WebSocket server não está rodando"
    cd /var/www/daw/websocket
    pm2 restart daw-websocket
fi
EOF

chmod +x "$APP_DIR/scripts/health-check.sh"

# 22. Configurar cron jobs
log "Configurando tarefas agendadas..."
(crontab -l 2>/dev/null; echo "0 2 * * * $APP_DIR/scripts/backup.sh") | crontab -
(crontab -l 2>/dev/null; echo "*/5 * * * * $APP_DIR/scripts/health-check.sh") | crontab -
(crontab -l 2>/dev/null; echo "0 3 * * 0 certbot renew --quiet") | crontab -

# 23. Teste final
log "Executando testes finais..."
sleep 5

# Testar Nginx
if ! systemctl is-active --quiet nginx; then
    error "Nginx não está rodando"
fi

# Testar PHP-FPM
if ! systemctl is-active --quiet php8.2-fpm; then
    error "PHP-FPM não está rodando"
fi

# Testar conexão com banco
if ! mysql -u "$DB_USER" -p"$DB_PASSWORD" -e "USE $DB_NAME;" 2>/dev/null; then
    error "Não foi possível conectar ao banco de dados"
fi

# 24. Salvar credenciais
log "Salvando credenciais..."
cat > "$APP_DIR/CREDENTIALS.txt" << EOF
=================================
   DAW Online - Credenciais
=================================

BANCO DE DADOS:
- Host: localhost
- Database: $DB_NAME
- Username: $DB_USER
- Password: $DB_PASSWORD

REDIS:
- Host: localhost
- Port: 6379
- Password: $REDIS_PASSWORD

JWT SECRET: $JWT_SECRET

ARQUIVOS DE CONFIGURAÇÃO:
- App Config: $APP_DIR/config/.env
- Nginx Config: /etc/nginx/sites-available/$DOMAIN
- PHP Config: /etc/php/8.2/fpm/pool.d/www.conf

LOGS:
- Nginx: /var/log/nginx/
- PHP: /var/log/php8.2-fpm.log
- App: $APP_DIR/storage/logs/

COMANDOS ÚTEIS:
- Restart WebSocket: pm2 restart daw-websocket
- Backup: $APP_DIR/scripts/backup.sh
- Health Check: $APP_DIR/scripts/health-check.sh

GERADO EM: $(date)
=================================
EOF

chmod 600 "$APP_DIR/CREDENTIALS.txt"

# Finalização
echo
echo -e "${GREEN}=================================="
echo -e "  🎉 INSTALAÇÃO CONCLUÍDA! 🎉"
echo -e "==================================${NC}"
echo
echo -e "${BLUE}🌐 Site:${NC} https://$DOMAIN"
echo -e "${BLUE}🔐 Credenciais:${NC} $APP_DIR/CREDENTIALS.txt"
echo -e "${BLUE}📋 Logs:${NC} /var/log/nginx/"
echo -e "${BLUE}⚙️  Configuração:${NC} $APP_DIR/config/.env"
echo
echo -e "${YELLOW}📝 PRÓXIMOS PASSOS:${NC}"
echo "1. Configure DNS para apontar para este servidor"
echo "2. Teste o acesso: https://$DOMAIN"
echo "3. Configure backups externos se necessário"
echo "4. Customize as configurações em config/.env"
echo
echo -e "${GREEN}✅ Todos os serviços estão rodando!${NC}"

log "Instalação do DAW Online concluída com sucesso!"
