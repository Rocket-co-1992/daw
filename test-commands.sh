# Executar todos os testes
composer test

# Executar apenas testes unitários
composer test:unit

# Executar apenas testes de integração
composer test:integration

# Executar apenas testes de funcionalidade
composer test:feature

# Executar testes com relatório de cobertura
composer test:coverage

# Executar testes específicos
./vendor/bin/phpunit tests/Unit/AuthManagerTest.php
./vendor/bin/phpunit tests/Integration/ProjectIntegrationTest.php
./vendor/bin/phpunit tests/Feature/ApiEndpointsTest.php

# Executar teste específico (método)
./vendor/bin/phpunit --filter testCompleteAuthenticationFlow tests/Integration/AuthIntegrationTest.php

# Executar testes com output verboso
./vendor/bin/phpunit --verbose

# Executar testes em paralelo (se configurado)
./vendor/bin/phpunit --parallel
