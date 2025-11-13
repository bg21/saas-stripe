# Script PowerShell para testar a API
# Execute: .\test_api.ps1

$baseUrl = "http://localhost:8080"
$apiKey = "test_api_key_1234567890123456789012345678901234567890123456789012345678901234"

Write-Host "=== Testando API de Pagamentos SaaS ===" -ForegroundColor Green
Write-Host ""

# Teste 1: Health Check
Write-Host "1. Testando Health Check..." -ForegroundColor Yellow
try {
    $response = Invoke-RestMethod -Uri "$baseUrl/health" -Method Get
    Write-Host "Status: OK" -ForegroundColor Green
    Write-Host "Resposta: $($response | ConvertTo-Json)" -ForegroundColor Cyan
} catch {
    Write-Host "Erro: $_" -ForegroundColor Red
}
Write-Host ""

# Teste 2: Listar Clientes
Write-Host "2. Testando Listar Clientes..." -ForegroundColor Yellow
try {
    $headers = @{
        "Authorization" = "Bearer $apiKey"
    }
    $response = Invoke-RestMethod -Uri "$baseUrl/v1/customers" -Method Get -Headers $headers
    Write-Host "Status: OK" -ForegroundColor Green
    Write-Host "Resposta: $($response | ConvertTo-Json -Depth 5)" -ForegroundColor Cyan
} catch {
    Write-Host "Erro: $($_.Exception.Message)" -ForegroundColor Red
    if ($_.Exception.Response) {
        $reader = New-Object System.IO.StreamReader($_.Exception.Response.GetResponseStream())
        $responseBody = $reader.ReadToEnd()
        Write-Host "Detalhes: $responseBody" -ForegroundColor Red
    }
}
Write-Host ""

# Teste 3: Criar Cliente
Write-Host "3. Testando Criar Cliente..." -ForegroundColor Yellow
try {
    $headers = @{
        "Authorization" = "Bearer $apiKey"
        "Content-Type" = "application/json"
    }
    $body = @{
        email = "cliente@example.com"
        name = "João Silva"
    } | ConvertTo-Json
    
    $response = Invoke-RestMethod -Uri "$baseUrl/v1/customers" -Method Post -Headers $headers -Body $body
    Write-Host "Status: OK" -ForegroundColor Green
    Write-Host "Resposta: $($response | ConvertTo-Json -Depth 5)" -ForegroundColor Cyan
} catch {
    Write-Host "Erro: $($_.Exception.Message)" -ForegroundColor Red
    if ($_.Exception.Response) {
        $reader = New-Object System.IO.StreamReader($_.Exception.Response.GetResponseStream())
        $responseBody = $reader.ReadToEnd()
        Write-Host "Detalhes: $responseBody" -ForegroundColor Red
    }
}
Write-Host ""

# Teste 4: Listar Clientes novamente
Write-Host "4. Listando Clientes novamente..." -ForegroundColor Yellow
try {
    $headers = @{
        "Authorization" = "Bearer $apiKey"
    }
    $response = Invoke-RestMethod -Uri "$baseUrl/v1/customers" -Method Get -Headers $headers
    Write-Host "Status: OK" -ForegroundColor Green
    Write-Host "Resposta: $($response | ConvertTo-Json -Depth 5)" -ForegroundColor Cyan
} catch {
    Write-Host "Erro: $($_.Exception.Message)" -ForegroundColor Red
}
Write-Host ""

Write-Host "=== Testes concluídos ===" -ForegroundColor Green

