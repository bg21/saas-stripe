<?php

/**
 * Script para configurar o front-end dentro de public/app/
 * 
 * Uso: php scripts/setup_frontend.php
 */

$rootDir = __DIR__ . '/..';
$publicDir = $rootDir . '/public';
$appDir = $publicDir . '/app';
$exemplosDir = $rootDir . '/docs/exemplos';

echo "🚀 Configurando Front-End...\n\n";

// 1. Criar pasta public/app se não existir
if (!is_dir($appDir)) {
    mkdir($appDir, 0755, true);
    echo "✅ Pasta public/app criada\n";
} else {
    echo "ℹ️  Pasta public/app já existe\n";
}

// 2. Copiar arquivos HTML
$htmlFiles = ['index.html', 'success.html', 'dashboard.html'];
foreach ($htmlFiles as $file) {
    $source = $exemplosDir . '/' . $file;
    $dest = $appDir . '/' . $file;
    
    if (file_exists($source)) {
        copy($source, $dest);
        echo "✅ Copiado: $file\n";
    } else {
        echo "⚠️  Arquivo não encontrado: $file\n";
    }
}

// 3. Copiar arquivos JavaScript
$jsFiles = ['api-client.js', 'main.js', 'success.js', 'dashboard.js'];
foreach ($jsFiles as $file) {
    $source = $exemplosDir . '/' . $file;
    $dest = $appDir . '/' . $file;
    
    if (file_exists($source)) {
        copy($source, $dest);
        echo "✅ Copiado: $file\n";
    } else {
        echo "⚠️  Arquivo não encontrado: $file\n";
    }
}

// 4. Criar arquivo .htaccess para Apache (opcional)
$htaccessFile = $appDir . '/.htaccess';
if (!file_exists($htaccessFile)) {
    $htaccessContent = <<<'HTACCESS'
# Permitir acesso a arquivos estáticos
<IfModule mod_rewrite.c>
    RewriteEngine Off
</IfModule>
HTACCESS;
    file_put_contents($htaccessFile, $htaccessContent);
    echo "✅ Arquivo .htaccess criado\n";
}

// 5. Criar arquivo README
$readmeFile = $appDir . '/README.md';
if (!file_exists($readmeFile)) {
    $readmeContent = <<<'README'
# Front-End - Sistema de Pagamentos

Este diretório contém os arquivos do front-end.

## Acesso

- Página principal: `/app/index.html`
- Página de sucesso: `/app/success.html`
- Dashboard: `/app/dashboard.html`

## Configuração

Edite `api-client.js` e configure:
- `baseUrl`: URL da API (ex: `http://localhost:8080`)
- `apiKey`: Sua API Key

## Estrutura

```
app/
├── index.html          # Página principal
├── success.html        # Página de sucesso
├── dashboard.html      # Dashboard
├── api-client.js       # Cliente da API
├── main.js            # Lógica principal
├── success.js         # Lógica da página de sucesso
└── dashboard.js       # Lógica do dashboard
```
README;
    file_put_contents($readmeFile, $readmeContent);
    echo "✅ README.md criado\n";
}

echo "\n✨ Front-end configurado com sucesso!\n\n";
echo "📝 Próximos passos:\n";
echo "1. Edite public/app/api-client.js e configure sua API Key\n";
echo "2. Acesse: http://localhost:8080/app/index.html\n";
echo "3. A API estará disponível em: http://localhost:8080/v1/customers\n\n";

