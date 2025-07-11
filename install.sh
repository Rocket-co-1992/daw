#!/bin/bash

# DAW Online - Script de Instalação Automática
# Compatível com Ubuntu 20.04+, Debian 11+

set -e

# Cores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Funções auxiliares
log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

check_root() {
    if [[ $EUID -ne 0 ]]; then
        log_error "Este script deve ser executado como root (use sudo)"
        exit 1
    fi
}

detect_os() {
    if [[ -f /etc/debian_version ]]; then
        OS="debian"
        if [[ $(lsb_release -si 2>/dev/null) == "Ubuntu" ]]; then
            OS="ubuntu"
        fi
    elif [[ -f /etc/redhat-release ]]; then
        OS="centos"
    else
        log_error "Sistema operacional não suportado"
        exit 1
    fi
    log_info "Sistema detectado: $OS"
}

install_dependencies_debian() {
    log_info "Atualizando sistema..."
    apt update && apt upgrade -y

    log_info "Instalando repositório PHP..."
    apt install -y software-properties-common
    add-apt-repository ppa:ondrej/php -y
    apt update

    log_info "Instalando PHP e extensões..."
    apt install -y php8.1-fpm php8.1-mysql php8.1-gd php8.1-curl \
        php8.1-json php8.1-mbstring php8.1-openssl php8.1-zip \
        php8.1-redis php8.1-intl php8.1-xml php8.1-cli

    log_info "Instalando MariaDB..."
    apt install -y mariadb-server mariadb-client

    log_info "Instalando Nginx..."
    apt install -y nginx

    log_info "Instalando Redis..."
    apt install -y redis-server

    log_info "Instalando utilitários..."
    apt install -y curl wget unzip git htop

    log_success "Dependências instaladas com sucesso"
}

install_dependencies_centos() {
    log_info "Instalando EPEL e Remi..."
    dnf install -y epel-release
    dnf install -y https://rpms.remirepo.net/enterprise/remi-release-8.rpm

    log_info "Habilitando PHP 8.1..."
    dnf module reset php -y
    dnf module enable php:remi-8.1 -y

    log_info "Instalando PHP e extensões..."
    dnf install -y php php-fpm php-mysqlnd php-gd php-curl \
        php-json php-mbstring php-openssl php-zip php-redis \
        php-intl php-xml php-cli

    log_info "Instalando MariaDB..."
    dnf install -y mariadb-server mariadb

    log_info "Instalando Nginx..."
    dnf install -y nginx

    log_info "Instalando Redis..."
    dnf install -y redis

    log_info "Instalando utilitários..."
    dnf install -y curl wget unzip git htop

    log_success "Dependências instaladas com sucesso"
}

install_composer() {
    log_info "Instalando Composer..."
    
    curl -sS https://getcomposer.org/installer | php
    mv composer.phar /usr/local/bin/composer
    chmod +x /usr/local/bin/composer
    
    log_success "Composer instalado com sucesso"
}

configure_services() {
    log_info "Configurando serviços..."
    
    if [[ $OS == "ubuntu" || $OS == "debian" ]]; then
        systemctl enable nginx
        systemctl enable php8.1-fpm
        systemctl enable mariadb
        systemctl enable redis-server
        
        systemctl start nginx
        systemctl start php8.1-fpm
        systemctl start mariadb
        systemctl start redis-server
    else
        systemctl enable nginx
        systemctl enable php-fpm
        systemctl enable mariadb
        systemctl enable redis
        
        systemctl start nginx
        systemctl start php-fpm
        systemctl start mariadb
        systemctl start redis
    fi
    
    log_success "Serviços configurados e iniciados"
}

setup_database() {
    log_info "Configurando banco de dados..."
    
    # Gerar senha aleatória
    DB_PASSWORD=$(openssl rand -base64 32)
    
    # Configurar MariaDB
    mysql -e "CREATE DATABASE IF NOT EXISTS daw_online CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    mysql -e "CREATE USER IF NOT EXISTS 'daw_user'@'localhost' IDENTIFIED BY '$DB_PASSWORD';"
    mysql -e "GRANT ALL PRIVILEGES ON daw_online.* TO 'daw_user'@'localhost';"
    mysql -e "FLUSH PRIVILEGES;"
    
    # Salvar credenciais
    echo "DB_PASSWORD=$DB_PASSWORD" > /tmp/daw_credentials.txt
    chmod 600 /tmp/daw_credentials.txt
    
    log_success "Banco de dados configurado"
    log_info "Senha do banco salva em: /tmp/daw_credentials.txt"
}

setup_directories() {
    log_info "Criando estrutura de diretórios..."
    
    # Diretórios principais
    mkdir -p /var/www/html/daw
    mkdir -p /var/www/html/daw/uploads
    mkdir -p /var/www/html/daw/plugins
    mkdir -p /var/log/daw
    mkdir -p /var/log/php-fpm
    mkdir -p /var/backups/daw
    
    # Permissões
    chown -R www-data:www-data /var/www/html/daw
    chmod -R 755 /var/www/html/daw
    chmod -R 775 /var/www/html/daw/uploads
    chmod -R 755 /var/www/html/daw/plugins
    
    chown -R www-data:www-data /var/log/daw
    chmod -R 755 /var/log/daw
    
    log_success "Diretórios criados e configurados"
}

setup_firewall() {
    log_info "Configurando firewall..."
    
    if command -v ufw &> /dev/null; then
        # Ubuntu/Debian - UFW
        ufw allow 'Nginx Full'
        ufw allow OpenSSH
        ufw allow 8080/tcp  # WebSocket
        ufw --force enable
        log_success "UFW configurado"
    elif command -v firewall-cmd &> /dev/null; then
        # CentOS/RHEL - firewalld
        firewall-cmd --permanent --add-service=http
        firewall-cmd --permanent --add-service=https
        firewall-cmd --permanent --add-port=8080/tcp
        firewall-cmd --reload
        log_success "Firewalld configurado"
    else
        log_warning "Nenhum firewall encontrado"
    fi
}

install_daw() {
    log_info "Instalando aplicação DAW..."
    
    # Se não estivermos já no diretório do projeto
    if [[ ! -f "composer.json" ]]; then
        log_error "composer.json não encontrado. Execute este script no diretório do projeto DAW."
        exit 1
    fi
    
    # Copiar arquivos
    cp -r * /var/www/html/daw/
    
    # Instalar dependências PHP
    cd /var/www/html/daw
    composer install --no-dev --optimize-autoloader
    
    # Configurar environment
    cp config/.env.example config/.env
    
    # Gerar chave JWT
    JWT_SECRET=$(openssl rand -base64 64)
    sed -i "s/JWT_SECRET=.*/JWT_SECRET=$JWT_SECRET/" config/.env
    
    # Configurar banco de dados no .env
    if [[ -f /tmp/daw_credentials.txt ]]; then
        DB_PASSWORD=$(grep DB_PASSWORD /tmp/daw_credentials.txt | cut -d'=' -f2)
        sed -i "s/DB_PASS=.*/DB_PASS=$DB_PASSWORD/" config/.env
    fi
    
    # Ajustar permissões
    chown -R www-data:www-data /var/www/html/daw
    chmod -R 755 /var/www/html/daw
    chmod -R 775 /var/www/html/daw/uploads
    
    log_success "Aplicação DAW instalada"
}

setup_nginx() {
    log_info "Configurando Nginx..."
    
    # Backup da configuração padrão
    if [[ -f /etc/nginx/sites-enabled/default ]]; then
        mv /etc/nginx/sites-enabled/default /etc/nginx/sites-enabled/default.backup
    fi
    
    # Copiar configuração do DAW
    cp /var/www/html/daw/config/nginx.conf /etc/nginx/sites-available/daw
    ln -sf /etc/nginx/sites-available/daw /etc/nginx/sites-enabled/
    
    # Configurar PHP-FPM
    cp /var/www/html/daw/config/php-fpm-daw.conf /etc/php/8.1/fpm/pool.d/ 2>/dev/null || \
    cp /var/www/html/daw/config/php-fpm-daw.conf /etc/php-fpm.d/ 2>/dev/null || true
    
    # Testar configuração
    nginx -t
    
    # Reiniciar serviços
    systemctl restart nginx
    systemctl restart php8.1-fpm 2>/dev/null || systemctl restart php-fpm
    
    log_success "Nginx configurado"
}

setup_websocket() {
    log_info "Configurando servidor WebSocket..."
    
    # Criar serviço systemd
    cat > /etc/systemd/system/daw-websocket.service << EOF
[Unit]
Description=DAW WebSocket Server
After=network.target

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=/var/www/html/daw/websockets
ExecStart=/usr/bin/php8.1 server.php
Restart=always
RestartSec=5
StandardOutput=journal
StandardError=journal

[Install]
WantedBy=multi-user.target
EOF

    # Habilitar e iniciar serviço
    systemctl daemon-reload
    systemctl enable daw-websocket
    systemctl start daw-websocket
    
    log_success "Servidor WebSocket configurado"
}

setup_logrotate() {
    log_info "Configurando rotação de logs..."
    
    cat > /etc/logrotate.d/daw << EOF
/var/log/daw/*.log {
    daily
    missingok
    rotate 52
    compress
    delaycompress
    notifempty
    create 644 www-data www-data
}

/var/log/php-fpm/daw*.log {
    daily
    missingok
    rotate 30
    compress
    delaycompress
    notifempty
    create 644 www-data www-data
    postrotate
        systemctl reload php8.1-fpm 2>/dev/null || systemctl reload php-fpm || true
    endscript
}
EOF
    
    log_success "Logrotate configurado"
}

import_database() {
    log_info "Importando schema do banco de dados..."
    
    if [[ -f /var/www/html/daw/database/schema.sql ]]; then
        mysql daw_online < /var/www/html/daw/database/schema.sql
        log_success "Schema importado com sucesso"
    else
        log_warning "Schema não encontrado em /var/www/html/daw/database/schema.sql"
    fi
}

create_admin_user() {
    log_info "Deseja criar um usuário administrador? (y/N)"
    read -r CREATE_ADMIN
    
    if [[ $CREATE_ADMIN =~ ^[Yy]$ ]]; then
        echo -n "Nome do usuário: "
        read -r ADMIN_NAME
        echo -n "Email: "
        read -r ADMIN_EMAIL
        echo -n "Senha: "
        read -rs ADMIN_PASSWORD
        echo
        
        # Hash da senha
        ADMIN_PASSWORD_HASH=$(php -r "echo password_hash('$ADMIN_PASSWORD', PASSWORD_DEFAULT);")
        
        # Inserir no banco
        mysql daw_online -e "INSERT INTO usuarios (nome, email, senha, tipo, ativo) VALUES ('$ADMIN_NAME', '$ADMIN_EMAIL', '$ADMIN_PASSWORD_HASH', 'admin', 1);"
        
        log_success "Usuário administrador criado"
    fi
}

show_final_info() {
    log_success "=== INSTALAÇÃO CONCLUÍDA ==="
    echo
    log_info "URLs de Acesso:"
    echo "  - Aplicação: http://localhost/"
    echo "  - WebSocket: ws://localhost:8080/"
    echo
    log_info "Credenciais do Banco:"
    echo "  - Usuário: daw_user"
    echo "  - Banco: daw_online"
    if [[ -f /tmp/daw_credentials.txt ]]; then
        echo "  - Senha: $(grep DB_PASSWORD /tmp/daw_credentials.txt | cut -d'=' -f2)"
    fi
    echo
    log_info "Arquivos de Configuração:"
    echo "  - Nginx: /etc/nginx/sites-available/daw"
    echo "  - PHP-FPM: /etc/php/8.1/fpm/pool.d/php-fpm-daw.conf"
    echo "  - Environment: /var/www/html/daw/config/.env"
    echo
    log_info "Logs:"
    echo "  - Nginx: /var/log/nginx/"
    echo "  - PHP-FPM: /var/log/php-fpm/"
    echo "  - DAW: /var/log/daw/"
    echo "  - WebSocket: journalctl -u daw-websocket"
    echo
    log_info "Comandos Úteis:"
    echo "  - Status: systemctl status nginx php8.1-fpm mariadb redis daw-websocket"
    echo "  - Logs: tail -f /var/log/nginx/daw_error.log"
    echo "  - Reiniciar: systemctl restart daw-websocket"
    echo
    log_warning "Lembre-se de:"
    echo "  1. Configurar SSL/TLS em produção"
    echo "  2. Ajustar configurações em /var/www/html/daw/config/.env"
    echo "  3. Fazer backup das credenciais em /tmp/daw_credentials.txt"
    echo "  4. Configurar backup automático"
    echo
    log_success "DAW Online está pronto para uso!"
}

# Script principal
main() {
    log_info "=== DAW ONLINE - INSTALAÇÃO AUTOMÁTICA ==="
    echo
    
    check_root
    detect_os
    
    # Instalação de dependências
    if [[ $OS == "ubuntu" || $OS == "debian" ]]; then
        install_dependencies_debian
    else
        install_dependencies_centos
    fi
    
    install_composer
    configure_services
    setup_database
    setup_directories
    install_daw
    import_database
    setup_nginx
    setup_websocket
    setup_logrotate
    setup_firewall
    create_admin_user
    
    show_final_info
}

# Executar instalação
main "$@"
