<?php
/**
 * Script para criar um PDF de exemplo para o exame ID 1
 * 
 * Uso: php scripts/create_example_exam_pdf.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Models\Exam;

// Conecta ao banco usando as mesmas configurações do sistema
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

$tenantId = $exam['tenant_id'];

// Cria diretório de uploads se não existir
$uploadDir = __DIR__ . '/../storage/exams/' . $tenantId . '/';
if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0755, true);
}

// Gera nome do arquivo
$filename = 'exam_1_' . time() . '_' . uniqid() . '.pdf';
$filePath = $uploadDir . $filename;

// Cria PDF simples usando HTML/CSS (será convertido para PDF se tiver wkhtmltopdf)
// Ou cria um PDF básico usando TCPDF/FPDF se disponível
// Por enquanto, vamos criar um PDF simples usando uma abordagem básica

// Se tiver TCPDF ou FPDF instalado, usa. Senão, cria um PDF básico manualmente
$pdfContent = createSimplePDF($exam);

// Salva arquivo
file_put_contents($filePath, $pdfContent);

// Atualiza banco de dados
$relativePath = 'storage/exams/' . $tenantId . '/' . $filename;
$stmt = $db->prepare("UPDATE exams SET results_file = ? WHERE id = 1");
$stmt->execute([$relativePath]);

echo "✅ PDF de exemplo criado com sucesso!\n";
echo "   Arquivo: {$filePath}\n";
echo "   Caminho no banco: {$relativePath}\n";

/**
 * Cria um PDF simples
 */
function createSimplePDF($exam): string
{
    // Cria um PDF básico usando a estrutura PDF simples
    // Este é um PDF mínimo válido
    $pdf = "%PDF-1.4\n";
    $pdf .= "1 0 obj\n";
    $pdf .= "<< /Type /Catalog /Pages 2 0 R >>\n";
    $pdf .= "endobj\n";
    
    $pdf .= "2 0 obj\n";
    $pdf .= "<< /Type /Pages /Kids [3 0 R] /Count 1 >>\n";
    $pdf .= "endobj\n";
    
    $pdf .= "3 0 obj\n";
    $pdf .= "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>\n";
    $pdf .= "endobj\n";
    
    // Conteúdo do PDF
    $content = "BT\n";
    $content .= "/F1 12 Tf\n";
    $content .= "50 750 Td\n";
    $content .= "(RESULTADO DE EXAME VETERINARIO) Tj\n";
    $content .= "0 -20 Td\n";
    $content .= "(Data: " . date('d/m/Y', strtotime($exam['exam_date'])) . ") Tj\n";
    $content .= "0 -20 Td\n";
    $content .= "(Pet ID: " . $exam['pet_id'] . ") Tj\n";
    $content .= "0 -30 Td\n";
    $content .= "(RESULTADOS) Tj\n";
    $content .= "0 -20 Td\n";
    $content .= "(" . str_replace(['(', ')'], ['\\(', '\\)'], $exam['results'] ?? 'Resultados dentro dos parâmetros normais. Nenhuma alteração significativa detectada.') . ") Tj\n";
    $content .= "0 -30 Td\n";
    $content .= "(OBSERVACOES) Tj\n";
    $content .= "0 -20 Td\n";
    $content .= "(" . str_replace(['(', ')'], ['\\(', '\\)'], $exam['notes'] ?? 'Exame realizado com sucesso. Pet apresentou bom estado geral.') . ") Tj\n";
    $content .= "ET\n";
    
    $contentLength = strlen($content);
    $pdf .= "4 0 obj\n";
    $pdf .= "<< /Length {$contentLength} >>\n";
    $pdf .= "stream\n";
    $pdf .= $content;
    $pdf .= "endstream\n";
    $pdf .= "endobj\n";
    
    // Fonte básica
    $pdf .= "5 0 obj\n";
    $pdf .= "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\n";
    $pdf .= "endobj\n";
    
    // Xref
    $xrefOffset = strlen($pdf);
    $pdf .= "xref\n";
    $pdf .= "0 6\n";
    $pdf .= "0000000000 65535 f \n";
    $pdf .= "0000000009 00000 n \n";
    $pdf .= "0000000058 00000 n \n";
    $pdf .= "0000000115 00000 n \n";
    $pdf .= sprintf("%010d 00000 n \n", $xrefOffset - 200);
    $pdf .= sprintf("%010d 00000 n \n", $xrefOffset - 100);
    
    // Trailer
    $pdf .= "trailer\n";
    $pdf .= "<< /Size 6 /Root 1 0 R >>\n";
    $pdf .= "startxref\n";
    $pdf .= $xrefOffset . "\n";
    $pdf .= "%%EOF\n";
    
    return $pdf;
}

