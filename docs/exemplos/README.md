# 📦 Exemplos de Integração Front-End

Este diretório contém exemplos completos de integração usando **HTML, CSS (Bootstrap) e JavaScript puro**.

## 📁 Arquivos

- **`index.html`** - Página principal de seleção de planos
- **`success.html`** - Página de confirmação de pagamento
- **`dashboard.html`** - Dashboard para gerenciar assinaturas
- **`api-client.js`** - Cliente JavaScript para comunicação com a API
- **`main.js`** - Lógica principal da aplicação
- **`success.js`** - Lógica da página de sucesso
- **`dashboard.js`** - Lógica do dashboard

## 🚀 Como Usar

### 1. Configurar API

Edite o arquivo `api-client.js` e configure suas credenciais:

```javascript
const API_CONFIG = {
    baseUrl: 'https://pagamentos.seudominio.com', // Sua URL da API
    apiKey: 'sua_api_key_aqui' // Sua API Key
};
```

### 2. Servir os Arquivos

Você pode servir os arquivos de várias formas:

#### Opção 1: Servidor Local (PHP)

```bash
php -S localhost:8000
```

Acesse: `http://localhost:8000/index.html`

#### Opção 2: Servidor Python

```bash
python -m http.server 8000
```

Acesse: `http://localhost:8000/index.html`

#### Opção 3: Servidor Node.js (http-server)

```bash
npx http-server -p 8000
```

Acesse: `http://localhost:8000/index.html`

### 3. Testar

1. Abra `index.html` no navegador
2. Selecione um plano
3. Preencha os dados do cliente
4. Será redirecionado para o Stripe Checkout
5. Após o pagamento, será redirecionado para `success.html`

## 🎨 Funcionalidades

### Página Principal (`index.html`)

- ✅ Listagem de planos disponíveis
- ✅ Seleção de plano
- ✅ Formulário de dados do cliente
- ✅ Indicador de progresso (steps)
- ✅ Tratamento de erros
- ✅ Loading states
- ✅ Design responsivo com Bootstrap

### Página de Sucesso (`success.html`)

- ✅ Verificação automática do status do pagamento
- ✅ Exibição de detalhes da transação
- ✅ Estados: Loading, Sucesso, Erro, Pendente
- ✅ Links para dashboard e voltar

### Dashboard (`dashboard.html`)

- ✅ Listagem de todas as assinaturas
- ✅ Visualização de status (Ativa, Cancelada, Em Teste, etc.)
- ✅ Cancelamento de assinatura (com confirmação)
- ✅ Reativação de assinatura cancelada
- ✅ Informações detalhadas (valor, período, trial, etc.)
- ✅ Design responsivo e moderno

## 🔧 Personalização

### Alterar Cores

Edite as variáveis CSS no `<style>` do `index.html`:

```css
:root {
    --primary-color: #6366f1; /* Sua cor primária */
    --success-color: #10b981; /* Cor de sucesso */
    --danger-color: #ef4444;  /* Cor de erro */
}
```

### Adicionar Mais Campos no Formulário

Edite o formulário em `index.html` e atualize `handleCustomerSubmit()` em `main.js`.

### Customizar Mensagens

Todas as mensagens estão em português. Você pode alterá-las diretamente nos arquivos HTML/JS.

## 📱 Responsividade

O exemplo usa Bootstrap 5 e é totalmente responsivo:
- ✅ Mobile-first
- ✅ Tablets
- ✅ Desktop

## 🔒 Segurança

⚠️ **IMPORTANTE**: Este exemplo usa a API Key diretamente no JavaScript. Para produção:

1. **Use um backend proxy** (recomendado)
2. **Ou use variáveis de ambiente** (não commitadas no Git)
3. **Configure CORS** no backend para seus domínios específicos

## 🐛 Troubleshooting

### Erro de CORS

Se você receber erros de CORS, verifique:

1. Se a URL da API está correta
2. Se o backend está configurado para aceitar requisições do seu domínio
3. Se está usando HTTPS em produção

### Erro 401 (Não Autenticado)

Verifique se a API Key está correta em `api-client.js`.

### Planos Não Carregam

1. Verifique se há planos criados no Stripe
2. Verifique se a API Key tem permissão para listar preços
3. Abra o console do navegador para ver erros detalhados

## 📚 Próximos Passos

1. **Criar página de dashboard** para gerenciar assinaturas
2. **Adicionar página de cancelamento** de assinatura
3. **Implementar autenticação de usuários** (Session ID)
4. **Adicionar mais validações** no formulário
5. **Implementar cache** de planos e customer

## 💡 Exemplos de Uso

### Criar Customer Programaticamente

```javascript
const customer = await api.createCustomer('email@example.com', 'Nome');
console.log('Customer criado:', customer);
```

### Criar Checkout

```javascript
const checkout = await api.createCheckout(
    customerId,
    priceId,
    'https://meu-site.com/success',
    'https://meu-site.com/cancel'
);
window.location.href = checkout.data.url;
```

### Verificar Status do Checkout

```javascript
const checkout = await api.getCheckout(sessionId);
if (checkout.data.payment_status === 'paid') {
    console.log('Pagamento confirmado!');
}
```

## 📞 Suporte

Para mais informações, consulte:
- [Guia Completo de Integração Front-End](../INTEGRACAO_FRONTEND.md)
- [Documentação da API](../../README.md)
- [Swagger UI](https://pagamentos.seudominio.com/api-docs/ui)

