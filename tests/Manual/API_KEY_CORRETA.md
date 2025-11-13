# 🔑 API Key Correta para Testes

## API Key do Tenant

Use esta API key para testar a API:

```
11a24058efc4d211144d9121361c286a7acedcd67e96811cdc4ab1e0bc728086
```

## 📝 Como Usar

### PowerShell:

```powershell
$apiKey = "11a24058efc4d211144d9121361c286a7acedcd67e96811cdc4ab1e0bc728086"

# Listar clientes
$headers = @{
    "Authorization" = "Bearer $apiKey"
}
Invoke-RestMethod -Uri "http://localhost:8080/v1/customers" -Method Get -Headers $headers

# Criar cliente
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

### cURL:

```bash
# Listar clientes
curl -X GET http://localhost:8080/v1/customers \
  -H "Authorization: Bearer 11a24058efc4d211144d9121361c286a7acedcd67e96811cdc4ab1e0bc728086"

# Criar cliente
curl -X POST http://localhost:8080/v1/customers \
  -H "Authorization: Bearer 11a24058efc4d211144d9121361c286a7acedcd67e96811cdc4ab1e0bc728086" \
  -H "Content-Type: application/json" \
  -d '{"email": "cliente@example.com", "name": "João Silva"}'
```

## ⚠️ Nota

Se você precisar gerar uma nova API key, você pode:

1. Usar o modelo Tenant diretamente no código
2. Executar SQL diretamente no banco:
```sql
UPDATE tenants SET api_key = 'nova_api_key_aqui' WHERE id = 1;
```
3. Ou criar um script temporário usando o método `generateApiKey()` do modelo `Tenant`

