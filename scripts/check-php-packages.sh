#!/bin/bash

# Script para verificar disponibilidade dos pacotes PHP no Ubuntu 22.04
# Execute antes da instalação para verificar se todos os pacotes existem

echo "Verificando disponibilidade dos pacotes PHP 8.2 no Ubuntu 22.04..."

# Adicionar repositório PHP se não existir
if ! grep -q "ondrej/php" /etc/apt/sources.list.d/ondrej-ubuntu-php-*.list 2>/dev/null; then
    echo "Adicionando repositório PHP..."
    add-apt-repository ppa:ondrej/php -y
    apt update
fi

# Lista de pacotes PHP para verificar
php_packages=(
    "php8.2"
    "php8.2-fpm"
    "php8.2-mysql"
    "php8.2-gd"
    "php8.2-curl"
    "php8.2-mbstring"
    "php8.2-zip"
    "php8.2-redis"
    "php8.2-intl"
    "php8.2-xml"
    "php8.2-cli"
    "php8.2-bcmath"
    "php8.2-soap"
    "php8.2-imagick"
    "php8.2-dev"
    "php8.2-imap"
    "php8.2-opcache"
)

echo -e "\nVerificando pacotes:"
echo "===================="

available_packages=()
missing_packages=()

for package in "${php_packages[@]}"; do
    if apt-cache show "$package" >/dev/null 2>&1; then
        echo "✓ $package - Disponível"
        available_packages+=("$package")
    else
        echo "✗ $package - NÃO ENCONTRADO"
        missing_packages+=("$package")
    fi
done

echo -e "\nResumo:"
echo "======="
echo "Pacotes disponíveis: ${#available_packages[@]}"
echo "Pacotes não encontrados: ${#missing_packages[@]}"

if [ ${#missing_packages[@]} -eq 0 ]; then
    echo -e "\n🎉 Todos os pacotes estão disponíveis para instalação!"
else
    echo -e "\n⚠️  Pacotes não encontrados:"
    for pkg in "${missing_packages[@]}"; do
        echo "   - $pkg"
    done
    
    echo -e "\nSugestões:"
    echo "- Verifique se o repositório ondrej/php foi adicionado corretamente"
    echo "- Execute: apt update"
    echo "- php8.2-json não é necessário (incluído no core do PHP 8.2)"
    echo "- php8.2-openssl não é necessário (incluído no core do PHP 8.2)"
fi

echo -e "\nVerificando extensões que vêm por padrão no PHP 8.2:"
echo "=================================================="
echo "✓ json - Incluída no core do PHP 8.2"
echo "✓ openssl - Incluída no core do PHP 8.2"
echo "✓ hash - Incluída no core do PHP 8.2"
echo "✓ filter - Incluída no core do PHP 8.2"

echo -e "\nComando de instalação recomendado:"
echo "================================="
echo "apt install -y \\"
for package in "${available_packages[@]}"; do
    echo "    $package \\"
done | sed '$ s/ \\$//'

echo -e "\nPara testar se JSON funciona após instalação:"
echo "============================================="
echo "php -m | grep json"
echo "php -r \"echo json_encode(['test' => 'ok']);\""
