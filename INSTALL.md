# DAW Online - Configuração de Instalação e Deploy

## Pré-requisitos

### Sistema Operacional
- Ubuntu 20.04+ / Debian 11+ / CentOS 8+
- Windows 10+ (para desenvolvimento)
- macOS 10.15+ (para desenvolvimento)

### Software Necessário
- PHP 8.1+
- MariaDB 10.6+ ou MySQL 8.0+
- Nginx 1.18+ ou Apache 2.4+
- Composer 2.0+
- Node.js 16+ (para ferramentas de build)
- Redis 6.0+ (recomendado para sessões)

### Extensões PHP Obrigatórias
```bash
php8.1-fpm
php8.1-mysql
php8.1-gd
php8.1-curl
php8.1-json
php8.1-mbstring
php8.1-openssl
php8.1-zip
php8.1-redis
php8.1-intl
php8.1-xml
```

## Instalação Automática

### Ubuntu/Debian

```bash
#!/bin/bash

# Atualizar sistema
sudo apt update && sudo apt upgrade -y

# Instalar repositório PHP
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update

# Instalar PHP e extensões
sudo apt install -y php8.1-fpm php8.1-mysql php8.1-gd php8.1-curl \
    php8.1-json php8.1-mbstring php8.1-openssl php8.1-zip \
    php8.1-redis php8.1-intl php8.1-xml php8.1-cli

# Instalar MariaDB
sudo apt install -y mariadb-server mariadb-client

# Instalar Nginx
sudo apt install -y nginx

# Instalar Redis
sudo apt install -y redis-server

# Instalar Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Configurar MariaDB
sudo mysql_secure_installation

# Configurar firewall
sudo ufw allow 'Nginx Full'
sudo ufw allow OpenSSH
sudo ufw --force enable
```

### CentOS/RHEL

```bash
#!/bin/bash

# Instalar EPEL e Remi
sudo dnf install -y epel-release
sudo dnf install -y https://rpms.remirepo.net/enterprise/remi-release-8.rpm

# Habilitar PHP 8.1
sudo dnf module reset php
sudo dnf module enable php:remi-8.1

# Instalar PHP e extensões
sudo dnf install -y php php-fpm php-mysqlnd php-gd php-curl \
    php-json php-mbstring php-openssl php-zip php-redis \
    php-intl php-xml php-cli

# Instalar MariaDB
sudo dnf install -y mariadb-server mariadb

# Instalar Nginx
sudo dnf install -y nginx

# Instalar Redis
sudo dnf install -y redis

# Instalar Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Iniciar serviços
sudo systemctl enable --now mariadb
sudo systemctl enable --now nginx
sudo systemctl enable --now php-fpm
sudo systemctl enable --now redis

# Configurar firewall
sudo firewall-cmd --permanent --add-service=http
sudo firewall-cmd --permanent --add-service=https
sudo firewall-cmd --reload
```

## Configuração Manual

### 1. Configurar Banco de Dados

```bash
# Entrar no MySQL/MariaDB
sudo mysql -u root -p

# Criar banco e usuário
CREATE DATABASE daw_online CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'daw_user'@'localhost' IDENTIFIED BY 'senha_segura_aqui';
GRANT ALL PRIVILEGES ON daw_online.* TO 'daw_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;

# Importar schema
mysql -u daw_user -p daw_online < /path/to/daw/database/schema.sql
```

### 2. Configurar Diretórios

```bash
# Criar estrutura de diretórios
sudo mkdir -p /var/www/html/daw
sudo mkdir -p /var/www/html/daw/uploads
sudo mkdir -p /var/www/html/daw/plugins
sudo mkdir -p /var/log/daw

# Configurar permissões
sudo chown -R www-data:www-data /var/www/html/daw
sudo chmod -R 755 /var/www/html/daw
sudo chmod -R 775 /var/www/html/daw/uploads
sudo chmod -R 755 /var/www/html/daw/plugins

# Copiar arquivos do projeto
sudo cp -r /path/to/source/* /var/www/html/daw/
```

### 3. Instalar Dependências PHP

```bash
cd /var/www/html/daw
sudo composer install --no-dev --optimize-autoloader
```

### 4. Configurar Environment

```bash
# Copiar arquivo de configuração
sudo cp /var/www/html/daw/config/.env.example /var/www/html/daw/config/.env

# Editar configurações
sudo nano /var/www/html/daw/config/.env
```

### 5. Configurar Nginx

```bash
# Copiar configuração
sudo cp /var/www/html/daw/config/nginx.conf /etc/nginx/sites-available/daw
sudo ln -s /etc/nginx/sites-available/daw /etc/nginx/sites-enabled/

# Testar configuração
sudo nginx -t

# Reiniciar Nginx
sudo systemctl restart nginx
```

### 6. Configurar PHP-FPM

```bash
# Copiar configuração do pool
sudo cp /var/www/html/daw/config/php-fpm-daw.conf /etc/php/8.1/fpm/pool.d/

# Reiniciar PHP-FPM
sudo systemctl restart php8.1-fpm
```

### 7. Configurar WebSocket Server

```bash
# Criar serviço systemd
sudo tee /etc/systemd/system/daw-websocket.service << EOF
[Unit]
Description=DAW WebSocket Server
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/html/daw/websockets
ExecStart=/usr/bin/php8.1 server.php
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
EOF

# Habilitar e iniciar serviço
sudo systemctl daemon-reload
sudo systemctl enable daw-websocket
sudo systemctl start daw-websocket
```

## SSL/TLS (Produção)

### Usando Let's Encrypt

```bash
# Instalar Certbot
sudo apt install -y certbot python3-certbot-nginx

# Obter certificado
sudo certbot --nginx -d dawonline.yourdomain.com

# Auto-renovação
sudo crontab -e
# Adicionar: 0 12 * * * /usr/bin/certbot renew --quiet
```

### Certificado Self-Signed (Desenvolvimento)

```bash
# Criar certificado
sudo openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
    -keyout /etc/ssl/private/dawonline.key \
    -out /etc/ssl/certs/dawonline.crt

# Atualizar configuração Nginx para usar HTTPS
```

## Monitoramento e Logs

### Configurar Logs

```bash
# Criar diretórios de log
sudo mkdir -p /var/log/daw
sudo mkdir -p /var/log/php-fpm

# Configurar logrotate
sudo tee /etc/logrotate.d/daw << EOF
/var/log/daw/*.log {
    daily
    missingok
    rotate 52
    compress
    delaycompress
    notifempty
    create 644 www-data www-data
}
EOF
```

### Monitoramento do Sistema

```bash
# Instalar htop
sudo apt install -y htop

# Monitor de processos
htop

# Monitor de logs em tempo real
sudo tail -f /var/log/nginx/daw_access.log
sudo tail -f /var/log/php-fpm/daw-error.log
sudo journalctl -f -u daw-websocket
```

## Performance e Otimização

### Configurar OPcache

```bash
# Editar php.ini
sudo nano /etc/php/8.1/fpm/php.ini

# Configurações recomendadas:
opcache.enable=1
opcache.memory_consumption=128
opcache.interned_strings_buffer=8
opcache.max_accelerated_files=4000
opcache.revalidate_freq=60
opcache.fast_shutdown=1
```

### Configurar Redis para Sessões

```bash
# Editar configuração do pool PHP
sudo nano /etc/php/8.1/fpm/pool.d/daw.conf

# Adicionar:
php_value[session.save_handler] = redis
php_value[session.save_path] = "tcp://127.0.0.1:6379"
```

## Backup e Recuperação

### Script de Backup

```bash
#!/bin/bash
# /usr/local/bin/daw-backup.sh

BACKUP_DIR="/var/backups/daw"
DATE=$(date +%Y%m%d_%H%M%S)

# Criar diretório de backup
mkdir -p $BACKUP_DIR

# Backup do banco de dados
mysqldump -u daw_user -p daw_online > $BACKUP_DIR/database_$DATE.sql

# Backup dos uploads
tar -czf $BACKUP_DIR/uploads_$DATE.tar.gz -C /var/www/html/daw uploads/

# Backup das configurações
tar -czf $BACKUP_DIR/config_$DATE.tar.gz -C /var/www/html/daw config/

# Limpar backups antigos (manter 30 dias)
find $BACKUP_DIR -name "*.sql" -mtime +30 -delete
find $BACKUP_DIR -name "*.tar.gz" -mtime +30 -delete
```

### Agendamento do Backup

```bash
# Configurar cron
sudo crontab -e

# Backup diário às 2:00 AM
0 2 * * * /usr/local/bin/daw-backup.sh
```

## Solução de Problemas

### Problemas Comuns

1. **WebSocket não conecta**
   ```bash
   # Verificar se o serviço está rodando
   sudo systemctl status daw-websocket
   
   # Verificar logs
   sudo journalctl -u daw-websocket -f
   ```

2. **Upload de arquivos falha**
   ```bash
   # Verificar permissões
   ls -la /var/www/html/daw/uploads/
   
   # Verificar configuração PHP
   php -i | grep upload
   ```

3. **Erro de conexão com banco**
   ```bash
   # Testar conexão
   mysql -u daw_user -p daw_online
   
   # Verificar logs do MariaDB
   sudo tail -f /var/log/mysql/error.log
   ```

### Comandos de Diagnóstico

```bash
# Status dos serviços
sudo systemctl status nginx php8.1-fpm mariadb redis daw-websocket

# Verificar portas
sudo netstat -tlnp | grep -E ':80|:443|:3306|:6379|:8080'

# Verificar configuração PHP
php -m | grep -E 'mysql|gd|curl|redis'

# Verificar logs de erro
sudo tail -f /var/log/nginx/error.log
sudo tail -f /var/log/php-fpm/daw-error.log
```

## Atualizações

### Atualizar Sistema

```bash
# Backup antes da atualização
/usr/local/bin/daw-backup.sh

# Atualizar dependências
cd /var/www/html/daw
sudo composer update

# Executar migrações (se houver)
# php migrate.php

# Reiniciar serviços
sudo systemctl restart php8.1-fpm nginx daw-websocket
```

---

**Nota**: Este guia assume conhecimento básico de administração de sistemas Linux. Para ambientes de produção, considere configurações adicionais de segurança, monitoramento e alta disponibilidade.
