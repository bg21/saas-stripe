<?php

/**
 * Script para verificar qual tenant_id o usuário logado tem
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';

Config::load();

use App\Models\User;
use App\Models\UserSession;

echo "🔍 Verificando usuários e seus tenant_ids...\n\n";

// Lista todos os usuários
$userModel = new User();
$users = $userModel->findAll([]);

echo "👤 Usuários no sistema:\n";
foreach ($users as $user) {
    echo "  - ID: {$user['id']}, Nome: {$user['name']}, Email: {$user['email']}, Tenant ID: {$user['tenant_id']}, Role: {$user['role']}\n";
}
echo "\n";

// Lista sessões ativas
$sessionModel = new UserSession();
$sessions = $sessionModel->findAll(['expires_at >=' => date('Y-m-d H:i:s')]);

echo "🔐 Sessões ativas:\n";
if (empty($sessions)) {
    echo "  Nenhuma sessão ativa encontrada.\n";
} else {
    foreach ($sessions as $session) {
        $user = $userModel->findById($session['user_id']);
        echo "  - Session ID: " . substr($session['session_id'], 0, 20) . "...\n";
        echo "    User: {$user['name']} ({$user['email']})\n";
        echo "    Tenant ID: {$session['tenant_id']}\n";
        echo "    Expira em: {$session['expires_at']}\n";
        echo "\n";
    }
}

echo "✅ Verificação concluída!\n";
echo "\n💡 Dica: Se o tenant_id do usuário logado for diferente de 3, os dados não aparecerão.\n";
echo "   Os dados do seed foram criados no tenant ID 3.\n";

