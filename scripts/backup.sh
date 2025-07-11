#!/bin/bash

# DAW Online - Backup Script
# Cria backup completo do sistema

set -e

# Configurações
BACKUP_DIR="/var/backups/daw-online"
APP_DIR="/var/www/daw-online"
DATE=$(date +%Y%m%d_%H%M%S)
RETENTION_DAYS=7

# Cores
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
NC='\033[0m'

log() {
    echo -e "${GREEN}[$(date +'%H:%M:%S')] $1${NC}"
}

info() {
    echo -e "${BLUE}[INFO] $1${NC}"
}

warn() {
    echo -e "${YELLOW}[WARN] $1${NC}"
}

# Criar diretório de backup
mkdir -p "$BACKUP_DIR"

log "Iniciando backup do DAW Online..."

# Carregar configurações do banco
if [[ -f "$APP_DIR/config/.env" ]]; then
    source "$APP_DIR/config/.env"
else
    warn "Arquivo .env não encontrado, usando valores padrão"
    DB_USERNAME="daw_user"
    DB_DATABASE="daw_online"
fi

# 1. Backup do banco de dados
log "Fazendo backup do banco de dados..."
if [[ -n "$DB_PASSWORD" ]]; then
    mysqldump -u "$DB_USERNAME" -p"$DB_PASSWORD" \
        --single-transaction \
        --routines \
        --triggers \
        --add-drop-database \
        --databases "$DB_DATABASE" > "$BACKUP_DIR/database_$DATE.sql"
    
    # Comprimir dump do banco
    gzip "$BACKUP_DIR/database_$DATE.sql"
    info "Backup do banco: database_$DATE.sql.gz"
else
    warn "Senha do banco não encontrada no .env"
fi

# 2. Backup dos uploads
log "Fazendo backup dos uploads..."
if [[ -d "$APP_DIR/uploads" ]]; then
    tar -czf "$BACKUP_DIR/uploads_$DATE.tar.gz" -C "$APP_DIR" uploads/
    info "Backup dos uploads: uploads_$DATE.tar.gz"
fi

# 3. Backup das configurações
log "Fazendo backup das configurações..."
tar -czf "$BACKUP_DIR/config_$DATE.tar.gz" \
    -C "$APP_DIR" config/ \
    -C /etc/nginx/sites-available . \
    -C /etc/php/8.2/fpm/pool.d www.conf \
    2>/dev/null || true

info "Backup das configurações: config_$DATE.tar.gz"

# 4. Backup dos logs importantes
log "Fazendo backup dos logs..."
tar -czf "$BACKUP_DIR/logs_$DATE.tar.gz" \
    --ignore-failed-read \
    "$APP_DIR/storage/logs/" \
    "/var/log/nginx/daw-*.log" \
    "/var/log/php8.2-fpm.log" \
    2>/dev/null || true

info "Backup dos logs: logs_$DATE.tar.gz"

# 5. Backup de projetos de usuários (se existir)
log "Fazendo backup dos projetos..."
if [[ -d "$APP_DIR/storage/projects" ]]; then
    tar -czf "$BACKUP_DIR/projects_$DATE.tar.gz" -C "$APP_DIR/storage" projects/
    info "Backup dos projetos: projects_$DATE.tar.gz"
fi

# 6. Criar arquivo de informações do backup
cat > "$BACKUP_DIR/backup_info_$DATE.txt" << EOF
DAW Online Backup Information
=============================
Date: $(date)
Hostname: $(hostname)
Backup Directory: $BACKUP_DIR
App Directory: $APP_DIR

System Info:
- OS: $(lsb_release -d | cut -f2)
- PHP: $(php -v | head -n1)
- Nginx: $(nginx -v 2>&1)
- MySQL: $(mysql --version)
- Node.js: $(node --version 2>/dev/null || echo "Not installed")

Files in this backup:
$(ls -lh "$BACKUP_DIR"/*_$DATE.* 2>/dev/null || echo "No files created")

Database Info:
- Database: $DB_DATABASE
- Username: $DB_USERNAME

Disk Usage:
$(df -h /var/www)

EOF

# 7. Limpar backups antigos
log "Limpando backups antigos (>$RETENTION_DAYS dias)..."
find "$BACKUP_DIR" -name "*.gz" -mtime +$RETENTION_DAYS -delete
find "$BACKUP_DIR" -name "*.txt" -mtime +$RETENTION_DAYS -delete

# 8. Calcular tamanhos
log "Calculando tamanhos dos backups..."
total_size=$(du -sh "$BACKUP_DIR" | cut -f1)
backup_count=$(ls -1 "$BACKUP_DIR"/*_$DATE.* 2>/dev/null | wc -l)

# 9. Verificar integridade dos arquivos criados
log "Verificando integridade dos backups..."
for file in "$BACKUP_DIR"/*_$DATE.*; do
    if [[ -f "$file" ]]; then
        case "$file" in
            *.gz)
                if gzip -t "$file" 2>/dev/null; then
                    info "✓ $(basename "$file") - OK"
                else
                    warn "✗ $(basename "$file") - Corrompido"
                fi
                ;;
            *.tar.gz)
                if tar -tzf "$file" >/dev/null 2>&1; then
                    info "✓ $(basename "$file") - OK"
                else
                    warn "✗ $(basename "$file") - Corrompido"
                fi
                ;;
            *)
                info "✓ $(basename "$file") - Criado"
                ;;
        esac
    fi
done

# 10. Relatório final
echo
echo -e "${GREEN}=================================="
echo "     Backup Concluído!"
echo "==================================${NC}"
echo
echo -e "${BLUE}📁 Diretório:${NC} $BACKUP_DIR"
echo -e "${BLUE}📊 Total:${NC} $total_size"
echo -e "${BLUE}📋 Arquivos:${NC} $backup_count"
echo -e "${BLUE}🕐 Data:${NC} $(date)"
echo

# 11. Opcional: Sincronizar com backup remoto
if [[ -n "$REMOTE_BACKUP_HOST" ]]; then
    log "Sincronizando com backup remoto..."
    rsync -avz --delete \
        "$BACKUP_DIR/" \
        "$REMOTE_BACKUP_HOST:$REMOTE_BACKUP_PATH/" || warn "Falha no sync remoto"
fi

# 12. Opcional: Enviar notificação
if command -v mail >/dev/null 2>&1 && [[ -n "$BACKUP_EMAIL" ]]; then
    echo "Backup do DAW Online concluído em $(date). Total: $total_size" | \
    mail -s "Backup DAW Online - $DATE" "$BACKUP_EMAIL"
fi

log "Backup concluído com sucesso!"

# Sair com código de sucesso
exit 0
