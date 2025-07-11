# DAW Online - Comandos Úteis para VPS

## 🚀 Instalação Inicial

```bash
# 1. Conectar à VPS
ssh root@SEU_IP

# 2. Clonar repositório
cd /var/www
git clone https://github.com/seu-usuario/daw-online.git
cd daw-online

# 3. Executar instalação automática
chmod +x scripts/vps-install.sh
./scripts/vps-install.sh
```

## 🔧 Gerenciamento de Serviços

### Nginx
```bash
# Status
sudo systemctl status nginx

# Reiniciar
sudo systemctl restart nginx

# Recarregar configuração
sudo systemctl reload nginx

# Testar configuração
sudo nginx -t

# Logs
sudo tail -f /var/log/nginx/error.log
sudo tail -f /var/log/nginx/access.log
```

### PHP-FPM
```bash
# Status
sudo systemctl status php8.2-fpm

# Reiniciar
sudo systemctl restart php8.2-fpm

# Recarregar
sudo systemctl reload php8.2-fpm

# Logs
sudo tail -f /var/log/php8.2-fpm.log
```

### MariaDB/MySQL
```bash
# Status
sudo systemctl status mariadb

# Reiniciar
sudo systemctl restart mariadb

# Conectar
mysql -u daw_user -p

# Backup manual
mysqldump -u daw_user -p daw_online > backup.sql

# Restaurar
mysql -u daw_user -p daw_online < backup.sql
```

### Redis
```bash
# Status
sudo systemctl status redis-server

# Reiniciar
sudo systemctl restart redis-server

# Cliente
redis-cli

# Verificar conexão
redis-cli ping
```

### WebSocket (PM2)
```bash
# Status
pm2 status

# Reiniciar
pm2 restart daw-websocket

# Logs
pm2 logs daw-websocket

# Monitoramento
pm2 monit

# Salvar configuração
pm2 save
pm2 startup
```

## 🛠️ Manutenção e Atualizações

### Atualizar aplicação
```bash
cd /var/www/daw-online

# Backup antes de atualizar
./scripts/backup.sh

# Atualizar código
git pull origin main

# Atualizar dependências
composer install --no-dev --optimize-autoloader

# Executar migrations (se existirem)
php scripts/migrate.php

# Limpar caches
rm -rf cache/*
php scripts/clear-cache.php

# Reiniciar serviços
sudo systemctl reload php8.2-fpm
pm2 restart all
```

### Backup e Restore
```bash
# Backup manual
./scripts/backup.sh

# Backup agendado (já configurado)
crontab -l

# Restaurar banco de dados
gunzip backup.sql.gz
mysql -u daw_user -p daw_online < backup.sql

# Restaurar arquivos
tar -xzf uploads_backup.tar.gz -C /var/www/daw-online/
```

## 📊 Monitoramento

### Logs em tempo real
```bash
# Todos os logs principais
sudo multitail /var/log/nginx/access.log /var/log/nginx/error.log /var/log/php8.2-fpm.log

# Logs da aplicação
tail -f /var/www/daw-online/storage/logs/app.log

# WebSocket
pm2 logs --lines 50
```

### Recursos do sistema
```bash
# CPU e memória
htop

# Espaço em disco
df -h

# Processos
ps aux | grep -E "nginx|php|mysql|redis|node"

# Conexões ativas
netstat -tuln | grep -E ":80|:443|:8080|:3306|:6379"

# Load average
uptime
```

### Diagnóstico automático
```bash
# Script completo de diagnóstico
./scripts/diagnostic.sh

# Verificar apenas serviços críticos
systemctl status nginx php8.2-fpm mariadb redis-server
```

## 🔐 Segurança

### Firewall
```bash
# Status
sudo ufw status

# Permitir nova porta
sudo ufw allow 9000/tcp

# Bloquear IP
sudo ufw deny from 192.168.1.100

# Logs do firewall
sudo tail -f /var/log/ufw.log
```

### Fail2Ban
```bash
# Status
sudo fail2ban-client status

# Status de jail específico
sudo fail2ban-client status nginx-http-auth

# Desbanir IP
sudo fail2ban-client set nginx-http-auth unbanip 192.168.1.100

# Logs
sudo tail -f /var/log/fail2ban.log
```

### SSL/Certificados
```bash
# Renovar certificados
sudo certbot renew

# Verificar validade
sudo certbot certificates

# Testar renovação
sudo certbot renew --dry-run

# Logs do certbot
sudo tail -f /var/log/letsencrypt/letsencrypt.log
```

## 🚨 Solução de Problemas

### Nginx não inicia
```bash
# Verificar sintaxe
sudo nginx -t

# Verificar portas em uso
sudo netstat -tuln | grep :80
sudo netstat -tuln | grep :443

# Verificar logs
sudo tail -f /var/log/nginx/error.log
```

### PHP-FPM problemas
```bash
# Verificar configuração
php-fpm8.2 -t

# Verificar socket
ls -la /var/run/php/

# Aumentar log level temporariamente
sudo nano /etc/php/8.2/fpm/php-fpm.conf
# log_level = debug
sudo systemctl restart php8.2-fpm
```

### Banco de dados
```bash
# Verificar se está rodando
sudo systemctl status mariadb

# Verificar logs
sudo tail -f /var/log/mysql/error.log

# Reparar tabelas
mysql -u daw_user -p daw_online -e "REPAIR TABLE projects;"

# Verificar espaço
df -h /var/lib/mysql
```

### WebSocket não conecta
```bash
# Verificar se PM2 está rodando
pm2 status

# Verificar porta
netstat -tuln | grep :8080

# Testar conexão
wscat -c ws://localhost:8080

# Logs detalhados
pm2 logs daw-websocket --lines 100
```

### Espaço em disco cheio
```bash
# Verificar uso
du -sh /var/www/daw-online/*
du -sh /var/log/*
du -sh /tmp/*

# Limpar logs antigos
sudo find /var/log -name "*.log" -type f -mtime +7 -delete

# Limpar uploads antigos (cuidado!)
find /var/www/daw-online/uploads -name "*.tmp" -type f -mtime +1 -delete

# Limpar cache
rm -rf /var/www/daw-online/cache/*
```

## ⚡ Performance

### Otimizar PHP
```bash
# Verificar configuração atual
php -i | grep -E "memory_limit|max_execution_time|upload_max_filesize"

# Otimizar OPcache
sudo nano /etc/php/8.2/fpm/conf.d/99-daw-opcache.ini

# Reiniciar PHP-FPM
sudo systemctl restart php8.2-fpm
```

### Otimizar MySQL
```bash
# Verificar status
mysql -u root -p -e "SHOW GLOBAL STATUS LIKE 'Uptime%';"

# Verificar configuração
mysql -u root -p -e "SHOW VARIABLES LIKE 'innodb_buffer_pool_size';"

# Otimização básica
sudo nano /etc/mysql/mariadb.conf.d/50-server.cnf
```

### Monitorar performance
```bash
# PHP-FPM status
curl http://localhost/fpm-status

# Nginx status
curl http://localhost/nginx-status

# Redis info
redis-cli info

# Top processos por CPU/memória
ps aux --sort=-%cpu | head -10
ps aux --sort=-%mem | head -10
```

## 📈 Comandos de Deploy

### Deploy simples
```bash
cd /var/www/daw-online
git pull origin main
composer install --no-dev --optimize-autoloader
sudo systemctl reload php8.2-fpm
pm2 restart all
```

### Deploy com backup
```bash
#!/bin/bash
cd /var/www/daw-online

# Backup antes do deploy
./scripts/backup.sh

# Atualizar
git pull origin main
composer install --no-dev --optimize-autoloader

# Executar migrations
php scripts/migrate.php

# Limpar caches
rm -rf cache/*

# Reiniciar serviços
sudo systemctl reload php8.2-fpm
pm2 restart all

echo "Deploy concluído!"
```

### Rollback
```bash
# Voltar para commit anterior
git reset --hard HEAD~1

# Ou para tag específica
git checkout v1.0.0

# Restaurar banco se necessário
mysql -u daw_user -p daw_online < /var/backups/daw-online/database_YYYYMMDD.sql

# Reiniciar serviços
sudo systemctl reload php8.2-fpm
pm2 restart all
```

## 📞 Comandos de Emergência

### Parar todos os serviços
```bash
sudo systemctl stop nginx
sudo systemctl stop php8.2-fpm
sudo systemctl stop mariadb
sudo systemctl stop redis-server
pm2 stop all
```

### Iniciar todos os serviços
```bash
sudo systemctl start mariadb
sudo systemctl start redis-server
sudo systemctl start php8.2-fpm
sudo systemctl start nginx
pm2 start all
```

### Modo de manutenção
```bash
# Ativar
echo "MAINTENANCE_MODE=true" >> /var/www/daw-online/config/.env

# Desativar
sed -i 's/MAINTENANCE_MODE=true/MAINTENANCE_MODE=false/' /var/www/daw-online/config/.env
```

### Reset completo (CUIDADO!)
```bash
# Backup completo primeiro!
./scripts/backup.sh

# Reset banco
mysql -u root -p -e "DROP DATABASE daw_online; CREATE DATABASE daw_online CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u daw_user -p daw_online < database/schema.sql

# Reset uploads
rm -rf uploads/*
mkdir -p uploads/{audio,projects,temp}

# Reset cache
rm -rf cache/*
mkdir -p cache/{templates,data,sessions}

# Reiniciar tudo
sudo systemctl restart nginx php8.2-fpm mariadb redis-server
pm2 restart all
```
