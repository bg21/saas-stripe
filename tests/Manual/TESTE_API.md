# Como Testar a API

## 📋 Pré-requisitos

1. **Criar um tenant no banco de dados:**

```sql
-- Conecte ao MySQL e execute:
USE saas_payments;

-- Gere uma API key de 64 caracteres hexadecimais
INSERT INTO tenants (name, api_key, status) 
VALUES (
    'Tenant de Teste',
    'sua_api_key_64_caracteres_hexadecimais_aqui',
    'active'
);
```

Ou use o arquivo de seed (e depois atualize a API key):
```bash
mysql -u root -p saas_payments < seed_example.sql
```

Para obter a API key do tenant, execute:
```bash
php tests/Manual/verificar_api_key.php
```

## 🧪 Métodos de Teste

### Opção 1: Script PHP (Mais Fácil)

Execute o script de teste PHP:

```bash
php tests/Manual/test_api.php
```

Este script testa automaticamente:
- Health check
- Listar clientes
- Criar cliente
- Listar clientes novamente

### Opção 2: PowerShell (Windows)

Execute o script PowerShell:

```powershell
.\tests\Manual\test_api.ps1
```

### Opção 3: PowerShell Manual

No PowerShell, execute:

```powershell
# 1. Health Check
Invoke-RestMethod -Uri "http://localhost:8080/health" -Method Get

# 2. Listar Clientes
# Substitua pela sua API key (obtenha com: php tests/Manual/verificar_api_key.php)
$apiKey = "sua_api_key_aqui"
$headers = @{
    "Authorization" = "Bearer $apiKey"
}
Invoke-RestMethod -Uri "http://localhost:8080/v1/customers" -Method Get -Headers $headers

# 3. Criar Cliente
$headers = @{
    "Authorization" = "Bearer $apiKey"
    "Content-Type" = "application/json"
}
$body = @{
    email = "cliente@example.com"
    name = "João Silva"
} | ConvertTo-Json

Invoke-RestMethod -Uri "http://localhost:8080/v1/customers" -Method Post -Headers $headers -Body $body
```

### Opção 4: cURL (CMD/PowerShell)

Se você tem cURL instalado no Windows:

```bash
# Health Check
curl -X GET http://localhost:8080/health

# Listar Clientes
# Substitua pela sua API key
curl -X GET http://localhost:8080/v1/customers -H "Authorization: Bearer sua_api_key_aqui"

# Criar Cliente
curl -X POST http://localhost:8080/v1/customers ^
  -H "Authorization: Bearer sua_api_key_aqui" ^
  -H "Content-Type: application/json" ^
  -d "{\"email\": \"cliente@example.com\", \"name\": \"João Silva\"}"
```

### Opção 5: Postman / Insomnia

1. **Importe as seguintes requisições:**

#### Health Check
- **Method:** GET
- **URL:** `http://localhost:8080/health`
- **Headers:** Nenhum

#### Listar Clientes
- **Method:** GET
- **URL:** `http://localhost:8080/v1/customers`
- **Headers:**
  - `Authorization: Bearer sua_api_key_aqui`

#### Criar Cliente
- **Method:** POST
- **URL:** `http://localhost:8080/v1/customers`
- **Headers:**
  - `Authorization: Bearer sua_api_key_aqui`
  - `Content-Type: application/json`
- **Body (JSON):**
```json
{
  "email": "cliente@example.com",
  "name": "João Silva"
}
```

### Opção 6: Navegador (Apenas GET)

Para rotas GET, você pode usar extensões do navegador como:
- **ModHeader** (Chrome) - Para adicionar headers
- **REST Client** (VS Code)

## 🔍 Verificando Respostas

### Resposta de Sucesso (200/201):
```json
{
  "success": true,
  "data": { ... }
}
```

### Resposta de Erro (400/401/500):
```json
{
  "error": "Mensagem de erro"
}
```

## ⚠️ Problemas Comuns

### Erro 401: "Token de autenticação não fornecido"
- Verifique se o header `Authorization: Bearer <api_key>` está presente
- Confirme que a API key existe no banco de dados

### Erro 404: "Rota não encontrada"
- Verifique se o servidor está rodando em `localhost:8080`
- Confirme que a rota está correta

### Erro 500: "Erro interno do servidor"
- Verifique os logs em `app.log`
- Confirme que o banco de dados está configurado corretamente
- Verifique se o Stripe está configurado (para endpoints que usam Stripe)

