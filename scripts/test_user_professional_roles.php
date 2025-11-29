<?php
/**
 * Script de teste para verificar relação entre users, professionals e professional_roles
 */

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../config/config.php';

Config::load();

use App\Utils\Database;

$db = Database::getInstance();

// Testa a query exata que estamos usando
$tenantId = 3; // Ajuste conforme necessário

echo "=== TESTE: Relação Users -> Professionals -> Professional Roles ===\n\n";

// 1. Verifica se há profissionais
$stmt = $db->prepare("SELECT COUNT(*) as total FROM professionals WHERE tenant_id = :tenant_id AND deleted_at IS NULL");
$stmt->execute(['tenant_id' => $tenantId]);
$profCount = $stmt->fetch(PDO::FETCH_ASSOC);
echo "1. Total de profissionais (tenant_id = {$tenantId}): {$profCount['total']}\n\n";

// 2. Lista profissionais com suas roles
$stmt = $db->prepare("
    SELECT 
        p.id as professional_id,
        p.user_id,
        p.professional_role_id,
        pr.name as professional_role_name,
        u.name as user_name,
        u.email
    FROM professionals p
    LEFT JOIN users u ON p.user_id = u.id
    LEFT JOIN professional_roles pr ON p.professional_role_id = pr.id
    WHERE p.tenant_id = :tenant_id 
    AND (p.deleted_at IS NULL OR p.deleted_at = '')
    LIMIT 10
");
$stmt->execute(['tenant_id' => $tenantId]);
$professionals = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "2. Profissionais encontrados:\n";
foreach ($professionals as $prof) {
    echo "   - Professional ID: {$prof['professional_id']}, User ID: {$prof['user_id']}, User: {$prof['user_name']} ({$prof['email']})\n";
    echo "     Professional Role ID: " . ($prof['professional_role_id'] ?? 'NULL') . "\n";
    echo "     Professional Role Name: " . ($prof['professional_role_name'] ?? 'NULL') . "\n\n";
}

// 3. Testa a query exata do modelo
echo "3. Testando query do modelo User::findByTenantWithProfessionalRole:\n";
$sql = "
    SELECT 
        u.*,
        pr.name as professional_role_name,
        pr.id as professional_role_id,
        p.id as professional_id
    FROM users u
    LEFT JOIN professionals p ON u.id = p.user_id 
        AND p.tenant_id = :tenant_id1 
        AND (p.deleted_at IS NULL OR p.deleted_at = '')
    LEFT JOIN professional_roles pr ON p.professional_role_id = pr.id 
        AND pr.tenant_id = :tenant_id2 
        AND (pr.deleted_at IS NULL OR pr.deleted_at = '')
        AND pr.is_active = 1
    WHERE u.tenant_id = :tenant_id3
    ORDER BY u.created_at DESC
    LIMIT 10
";

$stmt = $db->prepare($sql);
$stmt->execute([
    'tenant_id1' => $tenantId,
    'tenant_id2' => $tenantId,
    'tenant_id3' => $tenantId
]);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "   Total de usuários retornados: " . count($users) . "\n\n";
foreach ($users as $user) {
    echo "   - User ID: {$user['id']}, Name: {$user['name']}, Email: {$user['email']}\n";
    echo "     Professional ID: " . ($user['professional_id'] ?? 'NULL') . "\n";
    echo "     Professional Role ID: " . ($user['professional_role_id'] ?? 'NULL') . "\n";
    echo "     Professional Role Name: " . ($user['professional_role_name'] ?? 'NULL') . "\n";
    echo "     System Role: {$user['role']}\n\n";
}

// 4. Verifica roles profissionais disponíveis
$stmt = $db->prepare("SELECT id, name, is_active FROM professional_roles WHERE tenant_id = :tenant_id AND (deleted_at IS NULL OR deleted_at = '')");
$stmt->execute(['tenant_id' => $tenantId]);
$roles = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "4. Roles profissionais disponíveis no tenant:\n";
foreach ($roles as $role) {
    echo "   - ID: {$role['id']}, Name: {$role['name']}, Active: " . ($role['is_active'] ? 'Sim' : 'Não') . "\n";
}

echo "\n=== FIM DO TESTE ===\n";

