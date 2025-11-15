<?php

/**
 * Script para criar um novo tenant (SaaS) no sistema de pagamentos
 * 
 * Uso:
 *   php scripts/setup_tenant.php "Nome do SaaS"
 * 
 * Ou interativo:
 *   php scripts/setup_tenant.php
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';

Config::load();

use App\Models\Tenant;

echo "╔═══════════════════════════════════════════════════════════════╗\n";
echo "║          Setup de Tenant (SaaS) no Sistema de Pagamentos     ║\n";
echo "╚═══════════════════════════════════════════════════════════════╝\n\n";

// Obtém nome do tenant
$tenantName = $argv[1] ?? null;

if (!$tenantName) {
    echo "Digite o nome do seu SaaS: ";
    $tenantName = trim(fgets(STDIN));
    
    if (empty($tenantName)) {
        echo "❌ Nome do tenant é obrigatório!\n";
        exit(1);
    }
}

// Cria tenant
$tenantModel = new Tenant();
$tenantId = $tenantModel->create($tenantName, null); // null = gera API key automaticamente

$tenant = $tenantModel->findById($tenantId);

if (!$tenant) {
    echo "❌ Erro ao criar tenant!\n";
    exit(1);
}

echo "✅ Tenant criado com sucesso!\n\n";
echo "═══════════════════════════════════════════════════════════\n";
echo "INFORMAÇÕES DO TENANT\n";
echo "═══════════════════════════════════════════════════════════\n";
echo "ID: {$tenant['id']}\n";
echo "Nome: {$tenant['name']}\n";
echo "Status: {$tenant['status']}\n";
echo "API Key: {$tenant['api_key']}\n\n";

echo "⚠️  IMPORTANTE:\n";
echo "   - GUARDE ESTA API KEY EM LOCAL SEGURO!\n";
echo "   - Ela não será exibida novamente\n";
echo "   - Use esta API Key para autenticar requisições\n";
echo "   - Configure no seu SaaS principal\n\n";

echo "📝 Próximos Passos:\n";
echo "   1. Configure esta API Key no seu SaaS\n";
echo "   2. Use a API Key no header: Authorization: Bearer {$tenant['api_key']}\n";
echo "   3. Comece a fazer requisições para a API de pagamentos\n";
echo "   4. Consulte docs/GUIA_INTEGRACAO_SAAS.md para mais detalhes\n\n";

// Salva em arquivo (opcional)
$outputFile = __DIR__ . "/../tenant_{$tenantId}_credentials.txt";
file_put_contents($outputFile, 
    "Tenant ID: {$tenant['id']}\n" .
    "Nome: {$tenant['name']}\n" .
    "API Key: {$tenant['api_key']}\n" .
    "Criado em: " . date('Y-m-d H:i:s') . "\n"
);

echo "💾 Credenciais salvas em: $outputFile\n";
echo "   (Delete este arquivo após copiar as credenciais)\n\n";

