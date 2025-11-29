<?php
/**
 * Teste direto da query para verificar se funciona
 */

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../config/config.php';

Config::load();

use App\Utils\Database;
use App\Models\User;

$db = Database::getInstance();
$tenantId = 3;

echo "=== TESTE DIRETO DA QUERY ===\n\n";

// Testa a query exata do modelo
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
";

try {
    echo "1. Preparando query...\n";
    $stmt = $db->prepare($sql);
    
    echo "2. Fazendo bind dos parâmetros...\n";
    $stmt->bindValue(':tenant_id1', $tenantId, \PDO::PARAM_INT);
    $stmt->bindValue(':tenant_id2', $tenantId, \PDO::PARAM_INT);
    $stmt->bindValue(':tenant_id3', $tenantId, \PDO::PARAM_INT);
    
    echo "3. Executando query...\n";
    $stmt->execute();
    
    echo "4. Buscando resultados...\n";
    $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    
    echo "✅ Query executada com sucesso!\n";
    echo "Total de usuários retornados: " . count($results) . "\n\n";
    
    foreach ($results as $user) {
        echo "   - User ID: {$user['id']}, Name: {$user['name']}, Email: {$user['email']}\n";
        echo "     Professional ID: " . ($user['professional_id'] ?? 'NULL') . "\n";
        echo "     Professional Role ID: " . ($user['professional_role_id'] ?? 'NULL') . "\n";
        echo "     Professional Role Name: " . ($user['professional_role_name'] ?? 'NULL') . "\n";
        echo "     System Role: {$user['role']}\n\n";
    }
    
} catch (\PDOException $e) {
    echo "❌ ERRO PDO:\n";
    echo "   Mensagem: " . $e->getMessage() . "\n";
    echo "   Código: " . $e->getCode() . "\n";
    echo "   SQL State: " . $e->errorInfo[0] . "\n";
    echo "   Driver Code: " . ($e->errorInfo[1] ?? 'N/A') . "\n";
    echo "   Driver Message: " . ($e->errorInfo[2] ?? 'N/A') . "\n";
    exit(1);
} catch (\Exception $e) {
    echo "❌ ERRO GERAL:\n";
    echo "   Mensagem: " . $e->getMessage() . "\n";
    echo "   Tipo: " . get_class($e) . "\n";
    exit(1);
}

// Testa usando o modelo
echo "\n=== TESTE USANDO O MODELO ===\n\n";

try {
    $userModel = new User();
    $users = $userModel->findByTenantWithProfessionalRole($tenantId);
    
    echo "✅ Modelo executado com sucesso!\n";
    echo "Total de usuários retornados: " . count($users) . "\n\n";
    
    foreach ($users as $user) {
        echo "   - User ID: {$user['id']}, Name: {$user['name']}, Email: {$user['email']}\n";
        echo "     Professional Role Name: " . ($user['professional_role_name'] ?? 'NULL') . "\n";
    }
    
} catch (\Exception $e) {
    echo "❌ ERRO NO MODELO:\n";
    echo "   Mensagem: " . $e->getMessage() . "\n";
    echo "   Tipo: " . get_class($e) . "\n";
    echo "   Trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

echo "\n=== FIM DO TESTE ===\n";

