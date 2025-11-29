<?php
/**
 * Script para associar roles profissionais aos profissionais existentes
 */

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../config/config.php';

Config::load();

$pdo = new PDO(
    'mysql:host=' . Config::get('DB_HOST') . ';dbname=' . Config::get('DB_NAME'),
    Config::get('DB_USER'),
    Config::get('DB_PASS')
);

$tenantId = 3; // Ajuste conforme necessário

echo "=== ASSOCIANDO ROLES PROFISSIONAIS AOS PROFISSIONAIS ===\n\n";

// 1. Busca roles profissionais disponíveis
$stmt = $pdo->prepare("SELECT id, name FROM professional_roles WHERE tenant_id = :tenant_id AND is_active = 1 AND (deleted_at IS NULL OR deleted_at = '') ORDER BY sort_order");
$stmt->execute(['tenant_id' => $tenantId]);
$roles = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Roles profissionais disponíveis:\n";
foreach ($roles as $role) {
    echo "  - ID: {$role['id']}, Nome: {$role['name']}\n";
}
echo "\n";

// 2. Busca profissionais sem role
$stmt = $pdo->prepare("
    SELECT p.id, p.user_id, u.name as user_name, u.email
    FROM professionals p
    LEFT JOIN users u ON p.user_id = u.id
    WHERE p.tenant_id = :tenant_id 
    AND (p.deleted_at IS NULL OR p.deleted_at = '')
    AND (p.professional_role_id IS NULL OR p.professional_role_id = 0)
");
$stmt->execute(['tenant_id' => $tenantId]);
$professionals = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Profissionais sem role profissional:\n";
foreach ($professionals as $prof) {
    echo "  - Professional ID: {$prof['id']}, User: {$prof['user_name']} ({$prof['email']})\n";
}
echo "\n";

if (empty($professionals)) {
    echo "✅ Todos os profissionais já têm roles associadas!\n";
    exit(0);
}

// 3. Mapeamento sugerido (pode ser ajustado)
$roleMapping = [
    // Se o nome do usuário contém "Dr" ou "Dra", associa a "Veterinário"
    // Caso contrário, associa a "Atendente" por padrão
];

// 4. Associa roles aos profissionais
$veterinarioRoleId = null;
$atendenteRoleId = null;

foreach ($roles as $role) {
    if (stripos($role['name'], 'Veterin') !== false) {
        $veterinarioRoleId = $role['id'];
    }
    if (stripos($role['name'], 'Atendente') !== false) {
        $atendenteRoleId = $role['id'];
    }
}

$updated = 0;
foreach ($professionals as $prof) {
    $roleId = null;
    
    // Tenta determinar a role baseado no nome do usuário
    $userName = strtolower($prof['user_name'] ?? '');
    $userEmail = strtolower($prof['email'] ?? '');
    
    if (stripos($userName, 'dr') !== false || stripos($userName, 'dra') !== false || 
        stripos($userEmail, 'dr') !== false) {
        // É veterinário
        $roleId = $veterinarioRoleId;
    } else {
        // Por padrão, associa a Atendente
        $roleId = $atendenteRoleId;
    }
    
    if ($roleId) {
        $updateStmt = $pdo->prepare("UPDATE professionals SET professional_role_id = :role_id WHERE id = :id");
        $updateStmt->execute(['role_id' => $roleId, 'id' => $prof['id']]);
        $updated++;
        echo "✅ Professional ID {$prof['id']} ({$prof['user_name']}) -> Role ID {$roleId}\n";
    } else {
        echo "⚠️  Não foi possível determinar role para Professional ID {$prof['id']} ({$prof['user_name']})\n";
    }
}

echo "\n=== RESULTADO ===\n";
echo "Profissionais atualizados: {$updated} de " . count($professionals) . "\n\n";

// 5. Verifica resultado
$stmt = $pdo->prepare("
    SELECT 
        p.id,
        u.name as user_name,
        pr.name as role_name
    FROM professionals p
    LEFT JOIN users u ON p.user_id = u.id
    LEFT JOIN professional_roles pr ON p.professional_role_id = pr.id
    WHERE p.tenant_id = :tenant_id 
    AND (p.deleted_at IS NULL OR p.deleted_at = '')
    ORDER BY p.id
");
$stmt->execute(['tenant_id' => $tenantId]);
$result = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Profissionais após atualização:\n";
foreach ($result as $prof) {
    $roleName = $prof['role_name'] ?? 'SEM ROLE';
    echo "  - {$prof['user_name']} -> {$roleName}\n";
}

echo "\n=== FIM ===\n";

