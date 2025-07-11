# Troubleshooting - Problemas Comuns na Instalação

## ❌ Erro: php8.2-json não tem candidato para instalação

### **Problema**
```bash
E: Package 'php8.2-json' has no installation candidate
```

### **Causa**
A partir do PHP 8.0+, a extensão JSON foi incluída no core do PHP e não precisa mais ser instalada separadamente.

### **Solução**
✅ **Remover `php8.2-json` da lista de instalação** (já corrigido no script)

### **Verificação**
```bash
# Verificar se JSON funciona
php -m | grep json
php -r "echo json_encode(['test' => 'working']);"
```

---

## ❌ Erro: php8.2-openssl não tem candidato para instalação

### **Problema**
```bash
E: Package 'php8.2-openssl' has no installation candidate
```

### **Causa**
Similar ao JSON, OpenSSL também está incluído no core do PHP 8.2+.

### **Solução**
✅ **Remover `php8.2-openssl` da lista** (já corrigido no script)

### **Verificação**
```bash
# Verificar se OpenSSL funciona
php -m | grep openssl
php -r "echo openssl_get_version();"
```

---

## ❌ Erro: Repositório PHP não encontrado

### **Problema**
```bash
E: Unable to locate package php8.2
```

### **Causa**
O repositório ondrej/php não foi adicionado ou não foi atualizado.

### **Solução**
```bash
# Adicionar repositório PHP
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update

# Verificar se foi adicionado
apt-cache search php8.2 | head -5
```

---

## ❌ Erro: Falha ao adicionar repositório

### **Problema**
```bash
Cannot add PPA: 'ppa:ondrej/php'
```

### **Causa**
Problemas de conectividade ou software-properties-common não instalado.

### **Solução**
```bash
# Instalar dependências
sudo apt update
sudo apt install -y software-properties-common

# Adicionar repositório manualmente
curl -fsSL https://packages.sury.org/php/apt.gpg | sudo gpg --dearmor -o /usr/share/keyrings/php-archive-keyring.gpg
echo "deb [signed-by=/usr/share/keyrings/php-archive-keyring.gpg] https://packages.sury.org/php/ $(lsb_release -sc) main" | sudo tee /etc/apt/sources.list.d/php.list
sudo apt update
```

---

## ✅ Lista Correta de Pacotes PHP 8.2 para Ubuntu 22.04

### **Pacotes Principais**
```bash
php8.2                 # PHP core
php8.2-fpm            # FastCGI Process Manager
php8.2-cli            # Command Line Interface
```

### **Extensões de Banco de Dados**
```bash
php8.2-mysql          # MySQL/MariaDB
php8.2-redis          # Redis
```

### **Extensões de Processamento**
```bash
php8.2-gd             # Processamento de imagens
php8.2-imagick        # ImageMagick
php8.2-bcmath         # Matemática de precisão
php8.2-soap           # SOAP protocol
php8.2-intl           # Internacionalização
```

### **Extensões de Rede e Segurança**
```bash
php8.2-curl           # Cliente HTTP/HTTPS
php8.2-zip            # Arquivos ZIP
php8.2-mbstring       # Strings multibyte
php8.2-xml            # Processamento XML
```

### **Extensões de Performance**
```bash
php8.2-opcache        # Cache de bytecode
```

### **Extensões de Desenvolvimento**
```bash
php8.2-dev            # Ferramentas de desenvolvimento
php8.2-imap           # IMAP/POP3
```

### **❌ Pacotes que NÃO existem (incluídos no core)**
```bash
php8.2-json           # ❌ Incluído no PHP 8.2 core
php8.2-openssl        # ❌ Incluído no PHP 8.2 core
php8.2-hash           # ❌ Incluído no PHP 8.2 core
php8.2-filter         # ❌ Incluído no PHP 8.2 core
```

---

## 🔧 Script de Verificação

Use o script fornecido para verificar quais pacotes estão disponíveis:

```bash
# Executar verificação
./scripts/check-php-packages.sh
```

---

## 🚀 Comando Final de Instalação

### **Ubuntu 22.04 + PHP 8.2**
```bash
# Adicionar repositório
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update

# Instalar PHP 8.2 completo
sudo apt install -y \
    php8.2 \
    php8.2-fpm \
    php8.2-mysql \
    php8.2-gd \
    php8.2-curl \
    php8.2-mbstring \
    php8.2-zip \
    php8.2-redis \
    php8.2-intl \
    php8.2-xml \
    php8.2-cli \
    php8.2-bcmath \
    php8.2-soap \
    php8.2-imagick \
    php8.2-dev \
    php8.2-imap \
    php8.2-opcache
```

### **Verificação Pós-Instalação**
```bash
# Verificar versão
php -v

# Verificar extensões carregadas
php -m

# Verificar JSON (deve funcionar automaticamente)
php -r "echo json_encode(['status' => 'working']);"

# Verificar OpenSSL (deve funcionar automaticamente)  
php -r "echo 'OpenSSL: ' . OPENSSL_VERSION_TEXT;"
```

---

## 🐛 Debug de Problemas

### **Logs para Verificar**
```bash
# Logs do APT
sudo tail -f /var/log/apt/term.log

# Verificar se pacote existe
apt-cache show php8.2-PACOTE

# Listar todos os pacotes PHP disponíveis
apt-cache search php8.2 | sort
```

### **Limpeza em Caso de Problemas**
```bash
# Limpar cache do APT
sudo apt clean
sudo apt autoclean

# Reconfigurar repositórios
sudo apt update --fix-missing

# Forçar reinstalação
sudo apt install --reinstall php8.2
```
