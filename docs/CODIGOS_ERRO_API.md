# Códigos de Erro da API

Este documento lista todos os códigos de erro retornados pela API, suas descrições e como tratá-los.

## Estrutura de Resposta de Erro

Todas as respostas de erro seguem o formato padronizado:

```json
{
  "error": "Tipo do erro",
  "message": "Mensagem amigável para o usuário",
  "code": "CODIGO_ERRO",
  "errors": {
    "campo": "Mensagem de erro específica"
  },
  "debug": {
    "action": "nome_da_acao",
    "contexto": "informações_adicionais"
  }
}
```

**Nota:** O campo `debug` só é retornado em ambiente de desenvolvimento.

---

## Códigos HTTP

### 2xx - Sucesso

| Código | Descrição | Quando Usar |
|--------|-----------|-------------|
| 200 | OK | Requisição bem-sucedida |
| 201 | Created | Recurso criado com sucesso |
| 204 | No Content | Requisição bem-sucedida sem conteúdo |

### 4xx - Erros do Cliente

| Código | Descrição | Quando Ocorre |
|--------|-----------|-----------------|
| 400 | Bad Request | Dados inválidos na requisição |
| 401 | Unauthorized | Token de autenticação ausente ou inválido |
| 403 | Forbidden | Usuário autenticado mas sem permissão |
| 404 | Not Found | Recurso não encontrado |
| 409 | Conflict | Conflito (ex: email duplicado) |
| 422 | Unprocessable Entity | Dados válidos mas não processáveis |
| 429 | Too Many Requests | Rate limit excedido |

### 5xx - Erros do Servidor

| Código | Descrição | Quando Ocorre |
|--------|-----------|-----------------|
| 500 | Internal Server Error | Erro interno do servidor |
| 502 | Bad Gateway | Erro na comunicação com serviço externo (Stripe) |
| 503 | Service Unavailable | Serviço temporariamente indisponível |

---

## Códigos de Erro Internos

### Autenticação e Autorização

| Código | Descrição | HTTP | Solução |
|--------|-----------|------|---------|
| `UNAUTHORIZED` | Não autenticado | 401 | Fornecer token válido no header `Authorization: Bearer <token>` |
| `FORBIDDEN` | Sem permissão | 403 | Verificar permissões do usuário |
| `INVALID_TOKEN` | Token inválido | 401 | Gerar novo token via login |
| `TOKEN_EXPIRED` | Token expirado | 401 | Fazer login novamente |
| `INVALID_CREDENTIALS` | Credenciais inválidas | 401 | Verificar email/senha |

### Validação

| Código | Descrição | HTTP | Solução |
|--------|-----------|------|---------|
| `VALIDATION_ERROR` | Erro de validação | 400 | Verificar campo `errors` para detalhes |
| `MISSING_REQUIRED_FIELD` | Campo obrigatório ausente | 400 | Incluir campo obrigatório na requisição |
| `INVALID_FORMAT` | Formato inválido | 400 | Verificar formato do campo (email, data, etc.) |
| `INVALID_VALUE` | Valor inválido | 400 | Verificar valores permitidos |

### Recursos

| Código | Descrição | HTTP | Solução |
|--------|-----------|------|---------|
| `NOT_FOUND` | Recurso não encontrado | 404 | Verificar se o ID existe e pertence ao tenant |
| `ALREADY_EXISTS` | Recurso já existe | 409 | Verificar se o recurso já foi criado |
| `RELATIONSHIP_NOT_FOUND` | Relacionamento não encontrado | 400 | Verificar se IDs relacionados existem |
| `SOFT_DELETED` | Recurso foi excluído (soft delete) | 404 | Recurso foi excluído logicamente |

### Rate Limiting

| Código | Descrição | HTTP | Solução |
|--------|-----------|------|---------|
| `RATE_LIMIT_EXCEEDED` | Limite de requisições excedido | 429 | Aguardar reset da janela de tempo |
| `TOO_MANY_REQUESTS` | Muitas requisições | 429 | Reduzir frequência de requisições |

### Stripe

| Código | Descrição | HTTP | Solução |
|--------|-----------|------|---------|
| `STRIPE_ERROR` | Erro do Stripe | 502 | Verificar logs e status do Stripe |
| `STRIPE_CARD_ERROR` | Erro no cartão | 400 | Verificar dados do cartão |
| `STRIPE_PAYMENT_FAILED` | Pagamento falhou | 400 | Verificar método de pagamento |
| `STRIPE_SUBSCRIPTION_ERROR` | Erro na assinatura | 502 | Verificar configuração da assinatura |

### Negócio

| Código | Descrição | HTTP | Solução |
|--------|-----------|------|---------|
| `APPOINTMENT_CONFLICT` | Conflito de agendamento | 409 | Escolher outro horário |
| `PROFESSIONAL_UNAVAILABLE` | Profissional indisponível | 400 | Escolher outro profissional ou horário |
| `SCHEDULE_BLOCKED` | Horário bloqueado | 400 | Horário não disponível |
| `PLAN_LIMIT_EXCEEDED` | Limite do plano excedido | 403 | Atualizar plano de assinatura |
| `INVALID_STATUS_TRANSITION` | Transição de status inválida | 400 | Verificar status atual e transições permitidas |

### Sistema

| Código | Descrição | HTTP | Solução |
|--------|-----------|------|---------|
| `INTERNAL_ERROR` | Erro interno | 500 | Contatar suporte |
| `DATABASE_ERROR` | Erro no banco de dados | 500 | Contatar suporte |
| `CACHE_ERROR` | Erro no cache | 500 | Contatar suporte |
| `EMAIL_ERROR` | Erro ao enviar email | 500 | Verificar configuração de email |

---

## Exemplos de Respostas

### Erro de Validação (400)

```json
{
  "error": "Dados inválidos",
  "message": "Por favor, verifique os dados informados",
  "code": "VALIDATION_ERROR",
  "errors": {
    "email": "Email é obrigatório",
    "name": "Nome deve ter no mínimo 3 caracteres"
  },
  "debug": {
    "action": "create_customer",
    "tenant_id": 1
  }
}
```

### Erro de Autenticação (401)

```json
{
  "error": "Não autenticado",
  "message": "Token de autenticação ausente ou inválido",
  "code": "UNAUTHORIZED",
  "debug": {
    "action": "get_customer"
  }
}
```

### Erro de Permissão (403)

```json
{
  "error": "Sem permissão",
  "message": "Você não tem permissão para realizar esta ação",
  "code": "FORBIDDEN",
  "debug": {
    "action": "delete_user",
    "required_permission": "delete_users",
    "user_id": 1
  }
}
```

### Recurso Não Encontrado (404)

```json
{
  "error": "Recurso não encontrado",
  "message": "Cliente não encontrado",
  "code": "NOT_FOUND",
  "debug": {
    "action": "get_customer",
    "customer_id": 999,
    "tenant_id": 1
  }
}
```

### Rate Limit (429)

```json
{
  "error": "Limite excedido",
  "message": "Muitas requisições. Tente novamente em alguns segundos",
  "code": "RATE_LIMIT_EXCEEDED",
  "debug": {
    "action": "create_appointment",
    "limit": 20,
    "window": 60,
    "reset_at": 1703847600
  }
}
```

### Erro do Stripe (502)

```json
{
  "error": "Erro do Stripe",
  "message": "Erro ao processar pagamento",
  "code": "STRIPE_ERROR",
  "debug": {
    "action": "create_payment_intent",
    "stripe_error": "card_declined",
    "stripe_message": "Your card was declined."
  }
}
```

---

## Tratamento de Erros no Cliente

### JavaScript (Fetch API)

```javascript
async function criarCliente(dados) {
  try {
    const response = await fetch('/v1/customers', {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json'
      },
      body: JSON.stringify(dados)
    });
    
    const result = await response.json();
    
    if (!response.ok) {
      // Trata erros específicos
      switch (result.code) {
        case 'VALIDATION_ERROR':
          console.error('Erros de validação:', result.errors);
          // Mostrar erros no formulário
          break;
        case 'UNAUTHORIZED':
          // Redirecionar para login
          window.location.href = '/login';
          break;
        case 'RATE_LIMIT_EXCEEDED':
          // Aguardar e tentar novamente
          setTimeout(() => criarCliente(dados), 5000);
          break;
        default:
          console.error('Erro:', result.message);
      }
      throw new Error(result.message);
    }
    
    return result.data;
  } catch (error) {
    console.error('Erro ao criar cliente:', error);
    throw error;
  }
}
```

### PHP (cURL)

```php
function criarCliente($dados, $token) {
    $ch = curl_init('http://api.exemplo.com/v1/customers');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS => json_encode($dados)
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $result = json_decode($response, true);
    
    if ($httpCode >= 400) {
        // Trata erros
        switch ($result['code'] ?? '') {
            case 'VALIDATION_ERROR':
                foreach ($result['errors'] ?? [] as $campo => $erro) {
                    echo "Erro em {$campo}: {$erro}\n";
                }
                break;
            case 'UNAUTHORIZED':
                echo "Token inválido. Faça login novamente.\n";
                break;
            default:
                echo "Erro: {$result['message']}\n";
        }
        return null;
    }
    
    return $result['data'] ?? null;
}
```

---

## Boas Práticas

1. **Sempre verifique o código HTTP** antes de processar a resposta
2. **Use o campo `code`** para tratamento específico de erros
3. **Exiba mensagens amigáveis** usando o campo `message`
4. **Trate erros de validação** mostrando os campos específicos em `errors`
5. **Em desenvolvimento**, use o campo `debug` para diagnóstico
6. **Implemente retry** para erros 429 (Rate Limit) e 503 (Service Unavailable)
7. **Logue erros** para análise posterior

---

**Última Atualização:** 2025-11-29

