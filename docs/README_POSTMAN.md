# Postman Collection - SaaS Payments API

Esta collection contém todas as requisições principais da API SaaS Payments para uso no Postman.

## 📥 Importar Collection

1. Abra o Postman
2. Clique em **Import**
3. Selecione o arquivo `docs/postman_collection.json`
4. A collection será importada com todas as requisições organizadas

## ⚙️ Configuração

### Variáveis de Ambiente

A collection usa as seguintes variáveis:

- `base_url`: URL base da API (padrão: `http://localhost:8080`)
- `api_key`: API Key do tenant (obtenha após criar um tenant)
- `session_id`: Session ID retornado após login (preenchido automaticamente)

### Configurar Variáveis

1. Clique na collection **SaaS Payments API**
2. Vá para a aba **Variables**
3. Configure:
   - `base_url`: URL da sua API
   - `api_key`: Sua API Key

### Obter API Key

1. Crie um tenant no sistema
2. A API Key será gerada automaticamente
3. Copie e cole na variável `api_key` da collection

## 🔐 Autenticação

A collection usa autenticação Bearer Token. Existem duas formas:

### 1. API Key (Tenant)

Configure a variável `api_key` e todas as requisições usarão automaticamente.

### 2. Session ID (Usuário)

1. Execute a requisição **Login** na pasta **Autenticação**
2. O `session_id` será salvo automaticamente na variável
3. As requisições usarão o `session_id` quando disponível

## 📁 Estrutura da Collection

### Autenticação
- Login
- Me (Informações do Usuário)
- Logout

### Clientes
- Criar Cliente
- Listar Clientes
- Obter Cliente
- Atualizar Cliente
- Listar Assinaturas do Cliente
- Listar Faturas do Cliente

### Assinaturas
- Criar Assinatura
- Listar Assinaturas
- Obter Assinatura
- Cancelar Assinatura
- Reativar Assinatura

### Agendamentos
- Criar Agendamento
- Listar Agendamentos
- Horários Disponíveis
- Confirmar Agendamento
- Completar Agendamento

### Checkout
- Criar Sessão de Checkout
- Obter Sessão de Checkout

### Health Check
- Health Check Básico
- Health Check Detalhado

## 🚀 Como Usar

### Exemplo: Criar um Cliente

1. Certifique-se de que `api_key` está configurada
2. Vá para **Clientes > Criar Cliente**
3. Clique em **Send**
4. A requisição será enviada com autenticação automática

### Exemplo: Fazer Login

1. Vá para **Autenticação > Login**
2. Edite o body com suas credenciais:
   ```json
   {
     "email": "admin@exemplo.com",
     "password": "senha123",
     "tenant_id": 1
   }
   ```
3. Clique em **Send**
4. O `session_id` será salvo automaticamente

## 📝 Notas

- Todas as requisições que requerem autenticação usam automaticamente `api_key` ou `session_id`
- Algumas requisições têm exemplos de body pré-preenchidos
- Ajuste os valores conforme necessário (IDs, emails, etc.)

## 🔗 Links Úteis

- **Documentação da API:** `/api-docs/ui`
- **Códigos de Erro:** `docs/CODIGOS_ERRO_API.md`
- **Exemplos:** `docs/EXEMPLOS_REQUISICOES_API.md`

---

**Última Atualização:** 2025-11-29

