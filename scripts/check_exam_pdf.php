<?php
/**
 * Script para verificar e corrigir o PDF do exame ID 1
 */

require_once __DIR__ . '/../vendor/autoload.php';

$dbHost = getenv('DB_HOST') ?: 'localhost';
$dbName = getenv('DB_NAME') ?: 'saas_payments';
$dbUser = getenv('DB_USER') ?: 'root';
$dbPass = getenv('DB_PASS') ?: '';

$db = new PDO(
    "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4",
    $dbUser,
    $dbPass,
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]
);

// Busca o exame ID 1
$stmt = $db->prepare("SELECT * FROM exams WHERE id = 1 AND deleted_at IS NULL");
$stmt->execute();
$exam = $stmt->fetch();

if (!$exam) {
    echo "❌ Exame ID 1 não encontrado.\n";
    exit(1);
}

echo "📋 Exame ID 1 encontrado:\n";
echo "   Tenant ID: {$exam['tenant_id']}\n";
echo "   Results File: " . ($exam['results_file'] ?? 'NULL') . "\n";
echo "   Notes: " . ($exam['notes'] ?? 'NULL') . "\n";
echo "   Results: " . ($exam['results'] ?? 'NULL') . "\n\n";

// Verifica se há arquivo PDF no diretório
$tenantId = $exam['tenant_id'];
$uploadDir = __DIR__ . '/../storage/exams/' . $tenantId . '/';

if (is_dir($uploadDir)) {
    $files = glob($uploadDir . 'exam_1_*.pdf');
    if (!empty($files)) {
        echo "✅ PDF encontrado no diretório:\n";
        foreach ($files as $file) {
            $relativePath = 'storage/exams/' . $tenantId . '/' . basename($file);
            echo "   Arquivo: {$file}\n";
            echo "   Caminho relativo: {$relativePath}\n";
            
            // Atualiza o banco se não estiver atualizado
            if ($exam['results_file'] !== $relativePath) {
                $updateStmt = $db->prepare("UPDATE exams SET results_file = ? WHERE id = 1");
                $updateStmt->execute([$relativePath]);
                echo "   ✅ Banco de dados atualizado!\n";
            } else {
                echo "   ✅ Banco de dados já está atualizado.\n";
            }
        }
    } else {
        echo "⚠️  Nenhum PDF encontrado no diretório {$uploadDir}\n";
        echo "   Criando novo PDF...\n";
        
        // Cria novo PDF
        require_once __DIR__ . '/create_example_exam_pdf.php';
    }
} else {
    echo "⚠️  Diretório não existe: {$uploadDir}\n";
    echo "   Criando diretório e PDF...\n";
    
    // Cria novo PDF
    require_once __DIR__ . '/create_example_exam_pdf.php';
}

// Verifica novamente
$stmt = $db->prepare("SELECT results_file FROM exams WHERE id = 1");
$stmt->execute();
$updated = $stmt->fetch();

echo "\n📊 Status final:\n";
echo "   Results File no banco: " . ($updated['results_file'] ?? 'NULL') . "\n";

if ($updated['results_file']) {
    $fullPath = __DIR__ . '/../' . $updated['results_file'];
    if (file_exists($fullPath)) {
        echo "   ✅ Arquivo existe no sistema de arquivos!\n";
        echo "   Tamanho: " . filesize($fullPath) . " bytes\n";
    } else {
        echo "   ❌ Arquivo NÃO existe no sistema de arquivos: {$fullPath}\n";
    }
}

