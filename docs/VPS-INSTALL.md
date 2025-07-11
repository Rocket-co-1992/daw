# Instalação do DAW Online em VPS Ubuntu 22.04

Este guia fornece instruções completas para instalar e configurar o DAW Online em uma VPS Ubuntu 22.04.

## 📋 Pré-requisitos

### Recursos Mínimos da VPS
- **CPU**: 2 vCPUs (recomendado: 4+ vCPUs)
- **RAM**: 4GB (recomendado: 8GB+)
- **Armazenamento**: 40GB SSD (recomendado: 100GB+)
- **Largura de Banda**: 1Gbps
- **Sistema**: Ubuntu 22.04 LTS

### Portas Necessárias
- **80**: HTTP (será redirecionado para HTTPS)
- **443**: HTTPS
- **8080**: WebSocket Server
- **22**: SSH (para administração)

## 🚀 Instalação Automática

### 1. Clone o Repositório

```bash
# Conectar à VPS via SSH
ssh root@SEU_IP_VPS

# Atualizar sistema
apt update && apt upgrade -y

# Instalar Git
apt install -y git

# Clone do projeto
cd /var/www
git clone https://github.com/seu-usuario/daw-online.git
cd daw-online
```

### 2. Execute o Script de Instalação

```bash
# Tornar o script executável
chmod +x scripts/vps-install.sh

# Executar instalação completa
./scripts/vps-install.sh
```

## 🔧 Instalação Manual (Passo a Passo)

### 1. Atualizar Sistema e Instalar Dependências Base

```bash
# Atualizar pacotes
sudo apt update && sudo apt upgrade -y

# Instalar utilitários essenciais
sudo apt install -y curl wget unzip software-properties-common apt-transport-https ca-certificates gnupg lsb-release

# Instalar Git
sudo apt install -y git
```

### 2. Instalar PHP 8.1+

```bash
# Adicionar repositório PHP
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update

# Instalar PHP e extensões necessárias
sudo apt install -y php8.2 php8.2-fpm php8.2-mysql php8.2-gd php8.2-curl \
    php8.2-mbstring php8.2-zip php8.2-redis \
    php8.2-intl php8.2-xml php8.2-cli php8.2-bcmath php8.2-soap \
    php8.2-imagick php8.2-dev php8.2-imap php8.2-opcache

# Verificar instalação
php -v
```

### 3. Instalar Composer

```bash
# Baixar e instalar Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
sudo chmod +x /usr/local/bin/composer

# Verificar instalação
composer --version
```

### 4. Instalar e Configurar MariaDB

```bash
# Instalar MariaDB
sudo apt install -y mariadb-server mariadb-client

# Configurar segurança
sudo mysql_secure_installation

# Configurar MariaDB
sudo mysql -u root -p
```

```sql
-- No prompt do MySQL:
CREATE DATABASE daw_online CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'daw_user'@'localhost' IDENTIFIED BY 'SUA_SENHA_SEGURA_AQUI';
GRANT ALL PRIVILEGES ON daw_online.* TO 'daw_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### 5. Instalar Redis

```bash
# Instalar Redis
sudo apt install -y redis-server

# Configurar Redis
sudo nano /etc/redis/redis.conf

# Alterar as seguintes linhas:
# bind 127.0.0.1
# requirepass SUA_SENHA_REDIS_AQUI

# Reiniciar Redis
sudo systemctl restart redis-server
sudo systemctl enable redis-server
```

### 6. Instalar e Configurar Nginx

```bash
# Instalar Nginx
sudo apt install -y nginx

# Criar configuração do site
sudo nano /etc/nginx/sites-available/daw-online
```

### 7. Instalar Node.js (para WebSocket Server)

```bash
# Instalar Node.js 18+
curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
sudo apt install -y nodejs

# Verificar instalação
node --version
npm --version

# Instalar PM2 para gerenciar processos Node.js
sudo npm install -g pm2
```

### 8. Configurar SSL com Let's Encrypt

```bash
# Instalar Certbot
sudo apt install -y certbot python3-certbot-nginx

# Gerar certificado SSL (substitua SEU_DOMINIO.com)
sudo certbot --nginx -d SEU_DOMINIO.com -d www.SEU_DOMINIO.com

# Verificar renovação automática
sudo systemctl status certbot.timer
```

## ⚙️ Configuração da Aplicação

### 1. Configurar Permissões

```bash
# Navegar para o diretório da aplicação
cd /var/www/daw-online

# Definir proprietário
sudo chown -R www-data:www-data /var/www/daw-online

# Definir permissões
sudo chmod -R 755 /var/www/daw-online
sudo chmod -R 775 storage/
sudo chmod -R 775 uploads/
sudo chmod -R 775 cache/
```

### 2. Configurar Environment

```bash
# Copiar arquivo de configuração
cp config/.env.example config/.env

# Editar configurações
nano config/.env
```

### 3. Instalar Dependências PHP

```bash
# Instalar dependências via Composer
composer install --no-dev --optimize-autoloader

# Otimizar autoloader
composer dump-autoload --optimize
```

### 4. Importar Schema do Banco

```bash
# Importar estrutura do banco
mysql -u daw_user -p daw_online < database/schema.sql

# Executar migrations se existirem
php scripts/migrate.php
```

### 5. Configurar WebSocket Server

```bash
# Instalar dependências Node.js
cd websocket/
npm install

# Configurar PM2
pm2 start ecosystem.config.js
pm2 save
pm2 startup

# Verificar status
pm2 status
```

## 🔐 Configuração de Segurança

### 1. Configurar Firewall

```bash
# Habilitar UFW
sudo ufw enable

# Permitir portas necessárias
sudo ufw allow 22/tcp    # SSH
sudo ufw allow 80/tcp    # HTTP
sudo ufw allow 443/tcp   # HTTPS
sudo ufw allow 8080/tcp  # WebSocket

# Verificar status
sudo ufw status
```

### 2. Configurar Fail2Ban

```bash
# Instalar Fail2Ban
sudo apt install -y fail2ban

# Criar configuração personalizada
sudo cp /etc/fail2ban/jail.conf /etc/fail2ban/jail.local
sudo nano /etc/fail2ban/jail.local

# Reiniciar serviço
sudo systemctl restart fail2ban
sudo systemctl enable fail2ban
```

## 📊 Monitoramento e Logs

### 1. Configurar Logs

```bash
# Configurar logrotate para logs da aplicação
sudo nano /etc/logrotate.d/daw-online

# Conteúdo do arquivo:
/var/www/daw-online/storage/logs/*.log {
    daily
    missingok
    rotate 14
    compress
    notifempty
    create 644 www-data www-data
}
```

### 2. Monitoramento de Recursos

```bash
# Instalar htop para monitoramento
sudo apt install -y htop

# Configurar monitoramento automático
crontab -e

# Adicionar linha para verificação de saúde:
*/5 * * * * /var/www/daw-online/scripts/health-check.sh
```

## 🚀 Otimização de Performance

### 1. Configurar PHP-FPM

```bash
# Editar configuração PHP-FPM
sudo nano /etc/php/8.2/fpm/pool.d/www.conf

# Otimizações recomendadas:
pm = dynamic
pm.max_children = 50
pm.start_servers = 5
pm.min_spare_servers = 5
pm.max_spare_servers = 35
pm.process_idle_timeout = 10s
pm.max_requests = 500

# Reiniciar PHP-FPM
sudo systemctl restart php8.2-fpm
```

### 2. Configurar Cache

```bash
# Configurar OPcache
sudo nano /etc/php/8.2/fpm/conf.d/10-opcache.ini

# Configurações recomendadas:
opcache.enable=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=8
opcache.max_accelerated_files=4000
opcache.revalidate_freq=2
opcache.fast_shutdown=1
```

## 🔄 Backup e Manutenção

### 1. Configurar Backup Automático

```bash
# Criar script de backup
sudo nano /var/www/daw-online/scripts/backup.sh

# Configurar cron para backup diário
sudo crontab -e

# Adicionar linha:
0 2 * * * /var/www/daw-online/scripts/backup.sh
```

### 2. Atualizações

```bash
# Script para atualizar aplicação
cd /var/www/daw-online
git pull origin main
composer install --no-dev --optimize-autoloader
php scripts/migrate.php
sudo systemctl reload php8.2-fpm
pm2 restart all
```

## 🆘 Solução de Problemas

### Verificar Status dos Serviços

```bash
# Status do Nginx
sudo systemctl status nginx

# Status do PHP-FPM
sudo systemctl status php8.2-fpm

# Status do MariaDB
sudo systemctl status mariadb

# Status do Redis
sudo systemctl status redis-server

# Status do WebSocket (PM2)
pm2 status
```

### Verificar Logs

```bash
# Logs do Nginx
sudo tail -f /var/log/nginx/error.log
sudo tail -f /var/log/nginx/access.log

# Logs do PHP
sudo tail -f /var/log/php8.2-fpm.log

# Logs da aplicação
tail -f /var/www/daw-online/storage/logs/app.log

# Logs do WebSocket
pm2 logs
```

### Testes de Funcionalidade

```bash
# Testar conexão com banco
php /var/www/daw-online/scripts/test-db.php

# Testar API
curl -X GET http://localhost/api/health

# Testar WebSocket
wscat -c ws://localhost:8080
```

## 📞 Suporte

Se encontrar problemas durante a instalação:

1. Verifique os logs de erro
2. Consulte a documentação completa em `/docs`
3. Execute os scripts de diagnóstico em `/scripts/diagnostics/`
4. Abra uma issue no GitHub com detalhes completos do erro

---

**Tempo estimado de instalação**: 30-60 minutos (dependendo da VPS)
**Dificuldade**: Intermediária
**Suporte**: Ubuntu 22.04 LTS
