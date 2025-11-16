# Documentação Completa de Views do Front-End

Este documento explica em detalhes cada view/página necessária no front-end, o que cada uma trata, quais rotas da API utiliza, fluxos de dados, componentes necessários e interações do usuário.

**Tecnologias:** HTML5, CSS (Bootstrap 5), JavaScript puro (Vanilla JS)

**📋 Documentação Relacionada:**
- [Rotas da API](ROTAS_API.md) - Todas as rotas disponíveis
- [Formulários Bootstrap](FORMULARIOS_BOOTSTRAP.md) - Formulários detalhados com campos e exemplos

---

## 📋 Índice de Views

### Views Públicas (Sem Autenticação)
1. [Página Inicial / Landing Page](#1-página-inicial--landing-page)
2. [Seleção de Planos](#2-seleção-de-planos)
3. [Formulário de Dados do Cliente](#3-formulário-de-dados-do-cliente)
4. [Página de Checkout (Redirecionamento)](#4-página-de-checkout-redirecionamento)
5. [Página de Sucesso](#5-página-de-sucesso)
6. [Página de Cancelamento](#6-página-de-cancelamento)

### Views Autenticadas (Dashboard)
7. [Dashboard Principal](#7-dashboard-principal)
8. [Gerenciamento de Assinaturas (Listagem)](#8-gerenciamento-de-assinaturas-listagem)
9. [Detalhes da Assinatura (Visualização)](#9-detalhes-da-assinatura-visualização)
9.1. [Criar Assinatura](#91-criar-assinatura)
9.2. [Editar Assinatura](#92-editar-assinatura)
10. [Histórico de Assinaturas](#10-histórico-de-assinaturas)
11. [Gerenciamento de Clientes (Listagem)](#11-gerenciamento-de-clientes-listagem)
12. [Detalhes do Cliente (Visualização)](#12-detalhes-do-cliente-visualização)
12.1. [Criar Cliente](#121-criar-cliente)
12.2. [Editar Cliente](#122-editar-cliente)
13. [Faturas do Cliente](#13-faturas-do-cliente)
14. [Métodos de Pagamento](#14-métodos-de-pagamento)
15. [Portal de Cobrança](#15-portal-de-cobrança)
16. [Estatísticas e Relatórios](#16-estatísticas-e-relatórios)
17. [Faturas e Invoices](#17-faturas-e-invoices)
18. [Reembolsos](#18-reembolsos)
19. [Disputas e Chargebacks](#19-disputas-e-chargebacks)

### Views Administrativas
20. [Login de Usuários](#20-login-de-usuários)
21. [Gerenciamento de Usuários (Listagem)](#21-gerenciamento-de-usuários-listagem)
21.1. [Criar Usuário](#211-criar-usuário)
21.2. [Editar Usuário](#212-editar-usuário)
22. [Gerenciamento de Permissões](#22-gerenciamento-de-permissões)
23. [Gerenciamento de Produtos](#23-gerenciamento-de-produtos)
23.1. [Criar Produto](#231-criar-produto)
23.2. [Editar Produto](#232-editar-produto)
24. [Gerenciamento de Preços](#24-gerenciamento-de-preços)
24.1. [Criar Preço](#241-criar-preço)
24.2. [Editar Preço](#242-editar-preço)
25. [Gerenciamento de Cupons](#25-gerenciamento-de-cupons)
25.1. [Criar Cupom](#251-criar-cupom)
26. [Logs de Auditoria](#26-logs-de-auditoria)

---

## 1. Página Inicial / Landing Page

### 📄 Descrição
Primeira página que o usuário acessa. Apresenta o produto/serviço, seus benefícios, planos disponíveis e um call-to-action para começar.

### 🎯 Objetivo
- Apresentar o produto/serviço
- Explicar benefícios e funcionalidades
- Direcionar para seleção de planos
- Coletar leads (opcional)

### 🔌 Rotas da API Utilizadas
- **GET `/v1/prices`** - Lista preços disponíveis (para exibir planos na página)
- **GET `/v1/products`** - Lista produtos (para exibir informações dos planos)

### 📊 Dados Necessários
```javascript
{
  plans: [
    {
      id: "price_xxxxx",
      product: {
        id: "prod_xxxxx",
        name: "Plano Básico",
        description: "Descrição do plano"
      },
      unit_amount: 2999, // em centavos
      currency: "brl",
      recurring: {
        interval: "month"
      }
    }
  ]
}
```

### 🧩 Componentes Necessários (Bootstrap 5)
- **Hero Section**: Banner principal com título e CTA (usar `jumbotron` ou `container` com `text-center`)
- **Features Section**: Lista de funcionalidades/benefícios (usar `row` e `col` com cards)
- **Plans Preview**: Cards com os planos disponíveis (usar `card` do Bootstrap)
- **Testimonials**: Depoimentos (opcional, usar `card` ou `carousel`)
- **Footer**: Links e informações (usar `footer` com classes Bootstrap)

### 🔄 Fluxo de Dados
1. Página carrega
2. Faz requisição `GET /v1/prices` para obter planos
3. Faz requisição `GET /v1/products` para obter detalhes dos produtos
4. Combina dados e exibe na tela
5. Usuário clica em "Escolher Plano" → Redireciona para `/planos`

### 💡 Interações do Usuário
- Visualizar informações do produto
- Ver planos e preços
- Clicar em "Começar Agora" ou "Escolher Plano"
- Navegar para seções específicas (features, preços, etc.)

### ⚠️ Tratamento de Erros
- Se API não responder: Mostrar mensagem "Planos temporariamente indisponíveis"
- Se não houver planos: Mostrar mensagem "Nenhum plano disponível no momento"

---

## 2. Seleção de Planos

### 📄 Descrição
Página onde o usuário visualiza todos os planos disponíveis, compara features, preços e seleciona o plano desejado.

### 🎯 Objetivo
- Exibir todos os planos disponíveis
- Permitir comparação entre planos
- Coletar seleção do usuário
- Redirecionar para formulário de dados

### 🔌 Rotas da API Utilizadas
- **GET `/v1/prices`** - Lista todos os preços
- **GET `/v1/products/:id`** - Detalhes de cada produto (se necessário)

### 📊 Dados Necessários
```javascript
{
  plans: [
    {
      id: "price_xxxxx",
      product: {
        id: "prod_xxxxx",
        name: "Plano Básico",
        description: "Ideal para pequenas empresas"
      },
      unit_amount: 2999,
      currency: "brl",
      recurring: {
        interval: "month",
        interval_count: 1
      },
      metadata: {
        features: "Feature 1, Feature 2, Feature 3"
      }
    }
  ]
}
```

### 🧩 Componentes Necessários
- **Plans Grid**: Grid de cards com os planos
- **Plan Card**: Card individual com:
  - Nome do plano
  - Preço formatado (R$ 29,99/mês)
  - Lista de features
  - Botão "Escolher este plano"
  - Badge "Mais Popular" (se aplicável)
- **Comparison Table**: Tabela comparativa (opcional)
- **Filter/Sort**: Filtros por intervalo (mensal/anual) se houver

### 🔄 Fluxo de Dados
1. Página carrega
2. Faz `GET /v1/prices?active=true` para obter planos ativos
3. Para cada preço, busca produto com `GET /v1/products/:id` (se necessário)
4. Formata dados (preço em formato monetário, features, etc.)
5. Exibe planos em cards
6. Usuário seleciona um plano → Salva no estado/localStorage
7. Redireciona para `/cliente` (formulário de dados)

### 💡 Interações do Usuário
- Visualizar todos os planos
- Comparar features entre planos
- Selecionar um plano (clicar no card ou botão)
- Ver detalhes de um plano específico
- Voltar para página anterior

### ⚠️ Tratamento de Erros
- Se não houver planos: Mostrar mensagem e botão "Voltar"
- Se API falhar: Mostrar erro e opção de tentar novamente
- Loading state enquanto carrega planos

### 💾 Estado Local
```javascript
{
  selectedPlan: {
    id: "price_xxxxx",
    product: { ... },
    amount: 2999,
    currency: "brl"
  }
}
```

---

## 3. Formulário de Dados do Cliente

### 📄 Descrição
Formulário onde o usuário insere seus dados pessoais (nome, email) antes de prosseguir para o checkout. Cria o cliente no sistema.

### 🎯 Objetivo
- Coletar dados do cliente (nome, email)
- Criar registro do cliente no banco
- Validar dados antes de prosseguir
- Redirecionar para checkout

### 🔌 Rotas da API Utilizadas
- **POST `/v1/customers`** - Cria o cliente
- **GET `/v1/customers/:id`** - Verifica se cliente já existe (opcional)

### 📊 Dados Enviados
```javascript
{
  email: "cliente@exemplo.com",
  name: "Nome do Cliente",
  metadata: {
    source: "website",
    plan_selected: "price_xxxxx"
  }
}
```

### 📊 Dados Recebidos
```javascript
{
  success: true,
  data: {
    id: 1, // ID local
    stripe_customer_id: "cus_xxxxx",
    email: "cliente@exemplo.com",
    name: "Nome do Cliente",
    created_at: "2024-01-01 10:00:00"
  }
}
```

### 🧩 Componentes Necessários (Bootstrap 5)
- **Form Container**: Container do formulário (usar `container` ou `card`)
- **Input Fields** (usar `form-control`):
  - Nome (text, obrigatório, `required`, `minlength="2"`)
  - Email (email, obrigatório, `required`, `type="email"`)
- **Validation Messages**: Mensagens de erro inline (usar `invalid-feedback` do Bootstrap)
- **Submit Button**: "Continuar para Pagamento" (usar `btn btn-primary btn-lg`)
- **Back Button**: "Voltar para Planos" (usar `btn btn-outline-secondary`)
- **Loading Spinner**: Durante criação do cliente (usar `spinner-border spinner-border-sm`)

**📋 Para estrutura HTML completa e código JavaScript, consulte:** [Formulário de Dados do Cliente](FORMULARIOS_BOOTSTRAP.md#1-formulário-de-dados-do-cliente-público)

### 🔄 Fluxo de Dados
1. Página carrega com plano selecionado (do estado/localStorage)
2. Usuário preenche formulário
3. Validação client-side (email válido, campos obrigatórios)
4. Ao submeter:
   - Mostra loading
   - Faz `POST /v1/customers` com dados do formulário
   - Se sucesso: Salva `customer.id` no estado
   - Redireciona para `/checkout` ou inicia processo de checkout
5. Se erro: Mostra mensagem de erro

### 💡 Interações do Usuário
- Preencher nome e email
- Ver validação em tempo real
- Submeter formulário
- Voltar para seleção de planos
- Ver mensagens de erro/sucesso

### ⚠️ Tratamento de Erros
- **Email já existe**: "Este email já está cadastrado. Deseja continuar?"
- **Email inválido**: "Por favor, insira um email válido"
- **Campos obrigatórios**: "Por favor, preencha todos os campos"
- **Erro de API**: "Erro ao criar conta. Tente novamente."

### 💾 Estado Local
```javascript
{
  customer: {
    id: 1,
    email: "cliente@exemplo.com",
    name: "Nome do Cliente"
  },
  selectedPlan: { ... },
  formData: {
    name: "",
    email: ""
  },
  errors: {
    name: "",
    email: ""
  }
}
```

---

## 4. Página de Checkout (Redirecionamento)

### 📄 Descrição
Página intermediária que cria a sessão de checkout no Stripe e redireciona o usuário para a página de pagamento do Stripe.

### 🎯 Objetivo
- Criar sessão de checkout no Stripe
- Obter URL de checkout
- Redirecionar usuário para Stripe Checkout
- Mostrar loading durante o processo

### 🔌 Rotas da API Utilizadas
- **POST `/v1/checkout`** - Cria sessão de checkout

### 📊 Dados Enviados
```javascript
{
  customer_id: 1, // ID local do cliente
  price_id: "price_xxxxx", // ID do plano selecionado
  success_url: "https://seu-site.com/success?session_id={CHECKOUT_SESSION_ID}",
  cancel_url: "https://seu-site.com/cancel",
  metadata: {
    plan_name: "Plano Básico",
    customer_name: "Nome do Cliente"
  }
}
```

### 📊 Dados Recebidos
```javascript
{
  success: true,
  data: {
    session_id: "cs_test_xxxxx",
    url: "https://checkout.stripe.com/c/pay/cs_test_xxxxx"
  }
}
```

### 🧩 Componentes Necessários
- **Loading Spinner**: Indicador de carregamento
- **Progress Indicator**: "Redirecionando para pagamento..."
- **Error Message**: Mensagem de erro se falhar

### 🔄 Fluxo de Dados
1. Página carrega automaticamente
2. Obtém `customer_id` e `price_id` do estado/localStorage
3. Constrói URLs de sucesso e cancelamento
4. Faz `POST /v1/checkout` com dados
5. Se sucesso: Redireciona para `data.url` (Stripe Checkout)
6. Se erro: Mostra mensagem e opção de voltar

### 💡 Interações do Usuário
- Visualizar loading (automático)
- Aguardar redirecionamento
- Se erro: Clicar em "Tentar Novamente" ou "Voltar"

### ⚠️ Tratamento de Erros
- **Erro ao criar checkout**: "Erro ao iniciar pagamento. Tente novamente."
- **URL não retornada**: "Erro ao obter link de pagamento."
- **Timeout**: "Tempo de resposta excedido. Verifique sua conexão."

### ⏱️ Timeout
- Se não redirecionar em 5 segundos, mostrar erro
- Permitir retry manual

---

## 5. Página de Sucesso

### 📄 Descrição
Página exibida após o usuário completar o pagamento no Stripe. Verifica o status do pagamento e exibe confirmação.

### 🎯 Objetivo
- Confirmar pagamento bem-sucedido
- Exibir detalhes da transação
- Mostrar informações da assinatura criada
- Oferecer próximos passos

### 🔌 Rotas da API Utilizadas
- **GET `/v1/checkout/:id`** - Obtém detalhes da sessão de checkout
- **GET `/v1/subscriptions`** - Lista assinaturas (para verificar se foi criada)

### 📊 Dados Recebidos
```javascript
// Da sessão de checkout
{
  success: true,
  data: {
    id: "cs_test_xxxxx",
    payment_status: "paid",
    customer_email: "cliente@exemplo.com",
    amount_total: 2999,
    currency: "brl",
    subscription: "sub_xxxxx"
  }
}
```

### 🧩 Componentes Necessários
- **Success Icon**: Ícone de sucesso (check verde)
- **Success Message**: "Pagamento realizado com sucesso!"
- **Transaction Details**: Card com detalhes:
  - Valor pago
  - Data/hora
  - Email do cliente
  - ID da transação
- **Subscription Info**: Informações da assinatura (se criada)
- **CTA Buttons**:
  - "Acessar Dashboard"
  - "Ver Minha Assinatura"
  - "Baixar Recibo" (opcional)

### 🔄 Fluxo de Dados
1. Página carrega com `session_id` na URL (`?session_id=cs_test_xxxxx`)
2. Extrai `session_id` da query string
3. Faz `GET /v1/checkout/:id` para obter detalhes
4. Verifica `payment_status`:
   - Se `paid`: Mostra sucesso
   - Se `unpaid`: Mostra pendente
   - Se outro: Mostra erro
5. Se tiver `subscription`, faz `GET /v1/subscriptions` para obter detalhes
6. Exibe informações na tela

### 💡 Interações do Usuário
- Visualizar confirmação de pagamento
- Ver detalhes da transação
- Clicar em "Acessar Dashboard"
- Clicar em "Ver Minha Assinatura"
- Compartilhar (opcional)

### ⚠️ Tratamento de Erros
- **Session ID não encontrado**: "Sessão não encontrada"
- **Pagamento não confirmado**: "Seu pagamento está sendo processado"
- **Erro ao buscar dados**: "Erro ao verificar pagamento. Entre em contato."

### 🔄 Estados Possíveis
- **paid**: Pagamento confirmado ✅
- **unpaid**: Pagamento pendente ⏳
- **no_payment_required**: Sem pagamento necessário ℹ️

---

## 6. Página de Cancelamento

### 📄 Descrição
Página exibida quando o usuário cancela o checkout no Stripe ou fecha a página de pagamento.

### 🎯 Objetivo
- Informar que o checkout foi cancelado
- Oferecer opção de tentar novamente
- Explicar o que aconteceu

### 🔌 Rotas da API Utilizadas
- Nenhuma (página informativa)

### 🧩 Componentes Necessários
- **Cancel Icon**: Ícone indicando cancelamento
- **Message**: "Checkout cancelado"
- **Explanation**: "Você cancelou o processo de pagamento."
- **CTA Buttons**:
  - "Tentar Novamente" → Volta para seleção de planos
  - "Voltar ao Início" → Vai para landing page
  - "Falar com Suporte" (opcional)

### 🔄 Fluxo de Dados
1. Página carrega
2. Exibe mensagem de cancelamento
3. Oferece opções de ação

### 💡 Interações do Usuário
- Visualizar mensagem
- Clicar em "Tentar Novamente"
- Clicar em "Voltar ao Início"
- Entrar em contato com suporte

---

## 7. Dashboard Principal

### 📄 Descrição
Página principal do dashboard autenticado. Exibe visão geral com estatísticas, assinaturas ativas, clientes recentes e ações rápidas.

### 🎯 Objetivo
- Fornecer visão geral do negócio
- Exibir métricas importantes
- Acesso rápido a funcionalidades principais
- Mostrar status geral do sistema

### 🔌 Rotas da API Utilizadas
- **GET `/v1/stats`** - Estatísticas gerais
- **GET `/v1/subscriptions`** - Lista assinaturas (últimas 5)
- **GET `/v1/customers`** - Lista clientes (últimos 5)
- **GET `/v1/auth/me`** - Dados do usuário autenticado

### 📊 Dados Recebidos
```javascript
// Stats
{
  customers: { total: 100, active: 80 },
  subscriptions: { total: 50, active: 45 },
  revenue: { total: 10000.00, currency: "BRL" }
}

// Subscriptions (últimas)
[
  {
    id: 1,
    status: "active",
    plan_name: "Plano Básico",
    amount: 29.99,
    customer: { name: "Cliente 1", email: "..." }
  }
]

// Customers (últimos)
[
  {
    id: 1,
    name: "Cliente 1",
    email: "cliente1@exemplo.com",
    created_at: "2024-01-01"
  }
]
```

### 🧩 Componentes Necessários
- **Stats Cards**: Cards com métricas:
  - Total de Clientes
  - Clientes Ativos
  - Total de Assinaturas
  - Assinaturas Ativas
  - Receita Total
  - Receita Mensal
- **Quick Actions**: Botões de ação rápida:
  - "Criar Cliente"
  - "Criar Assinatura"
  - "Ver Relatórios"
- **Recent Subscriptions**: Lista das últimas assinaturas
- **Recent Customers**: Lista dos últimos clientes
- **Charts/Graphs**: Gráficos de receita (opcional)
- **Notifications**: Notificações importantes

### 🔄 Fluxo de Dados
1. Página carrega
2. Faz múltiplas requisições em paralelo:
   - `GET /v1/stats`
   - `GET /v1/subscriptions?limit=5`
   - `GET /v1/customers?limit=5`
   - `GET /v1/auth/me`
3. Combina dados e exibe
4. Atualiza periodicamente (opcional, a cada 30s)

### 💡 Interações do Usuário
- Visualizar estatísticas
- Clicar em cards para ver detalhes
- Navegar para outras seções
- Executar ações rápidas
- Ver notificações

### ⚠️ Tratamento de Erros
- Se uma requisição falhar: Mostrar erro específico, manter outras informações
- Loading states para cada seção
- Retry automático ou manual

### 🔄 Atualização Automática
- Opcional: Atualizar stats a cada 30-60 segundos
- Indicador visual de "última atualização"

---

## 8. Gerenciamento de Assinaturas (Listagem)

### 📄 Descrição
Página que lista todas as assinaturas do tenant em formato de tabela, permite filtrar, buscar e acessar ações (ver detalhes, criar, editar, cancelar, reativar).

### 🎯 Objetivo
- Listar todas as assinaturas em tabela
- Permitir busca e filtros
- Acesso rápido para criar nova assinatura
- Acesso rápido para ver detalhes de uma assinatura
- Acesso rápido para editar uma assinatura
- Ações rápidas: cancelar, reativar

### 🔌 Rotas da API Utilizadas
- **GET `/v1/subscriptions`** - Lista todas as assinaturas

### 📊 Dados Recebidos
```javascript
{
  success: true,
  data: [
    {
      id: 1,
      stripe_subscription_id: "sub_xxxxx",
      status: "active",
      plan_name: "Plano Básico",
      amount: 29.99,
      currency: "BRL",
      current_period_start: "2024-01-01",
      current_period_end: "2024-02-01",
      cancel_at_period_end: false,
      customer: {
        id: 1,
        name: "Cliente 1",
        email: "cliente1@exemplo.com"
      }
    }
  ],
  count: 10
}
```

### 🧩 Componentes Necessários (Bootstrap 5)
- **Search Bar**: Busca por nome do cliente, email, ID (usar `input-group` com `form-control`)
- **Filters** (usar `form-select` ou `form-check`):
  - Status (active, canceled, past_due, etc.)
  - Plano
  - Período (este mês, último mês, etc.)
- **Subscriptions Table**: Tabela com colunas (usar `table table-striped table-hover`):
  - ID/Referência
  - Cliente (nome, email)
  - Plano
  - Status (com badge colorido)
  - Valor
  - Período Atual
  - Data de Criação
  - Ações (ver, cancelar, editar)
- **Pagination**: Paginação de resultados
- **Empty State**: Mensagem quando não há assinaturas
- **Create Button**: "Criar Assinatura" (`btn btn-primary`) → Navega para `/assinaturas/criar` ou abre modal
- **Action Buttons** (usar `btn-group`):
  - "Ver" (`btn btn-sm btn-outline-primary`) → Navega para `/assinaturas/:id`
  - "Editar" (`btn btn-sm btn-outline-secondary`) → Navega para `/assinaturas/:id/editar`
  - "Cancelar" (`btn btn-sm btn-outline-danger`) → Mostra modal de confirmação
  - "Reativar" (`btn btn-sm btn-outline-success`) → Reativa (se cancelada)

### 🔄 Fluxo de Dados
1. Página carrega
2. Faz `GET /v1/subscriptions` para obter todas
3. Aplica filtros/busca (client-side ou server-side)
4. Exibe em tabela Bootstrap
5. Ao clicar em ação:
   - **Criar Assinatura**: Navega para `/assinaturas/criar` ou abre modal
   - **Ver**: Navega para `/assinaturas/:id` (página de detalhes)
   - **Editar**: Navega para `/assinaturas/:id/editar` (página de edição)
   - **Cancelar**: Mostra modal de confirmação → `DELETE /v1/subscriptions/:id` → Recarrega lista
   - **Reativar**: `POST /v1/subscriptions/:id/reactivate` → Recarrega lista

### 💡 Interações do Usuário
- Buscar assinaturas (em tempo real ou ao pressionar Enter)
- Filtrar por status/plano/período
- Ordenar colunas (clicando no header)
- Clicar em "Criar Assinatura" → Vai para página de criação
- Clicar em "Ver" → Vai para página de detalhes
- Clicar em "Editar" → Vai para página de edição
- Cancelar assinatura (com confirmação em modal)
- Reativar assinatura cancelada
- Exportar lista para CSV/Excel (opcional)

### 📋 Formulários Relacionados
- **Criar Assinatura**: Consulte [Formulário de Criar Assinatura](FORMULARIOS_BOOTSTRAP.md#5-formulário-de-criar-assinatura)

### ⚠️ Tratamento de Erros
- Erro ao carregar: "Erro ao carregar assinaturas"
- Erro ao cancelar: "Erro ao cancelar assinatura"
- Confirmação antes de cancelar: "Tem certeza que deseja cancelar?"

### 🎨 Status Badges
- **active**: Verde
- **canceled**: Cinza
- **past_due**: Amarelo
- **unpaid**: Vermelho
- **trialing**: Azul

---

## 9. Detalhes da Assinatura (Visualização)

### 📄 Descrição
Página de visualização completa de uma assinatura específica, incluindo informações do cliente, plano, histórico de pagamentos, próximas cobranças e ações disponíveis.

### 🎯 Objetivo
- Exibir todos os detalhes da assinatura (somente leitura)
- Mostrar histórico de mudanças
- Fornecer acesso para editar, cancelar, reativar
- Exibir informações de cobrança

### 🔌 Rotas da API Utilizadas
- **GET `/v1/subscriptions/:id`** - Detalhes da assinatura
- **GET `/v1/subscriptions/:id/history`** - Histórico de mudanças
- **GET `/v1/subscriptions/:id/history/stats`** - Estatísticas do histórico

### 📊 Dados Recebidos
```javascript
{
  id: 1,
  stripe_subscription_id: "sub_xxxxx",
  status: "active",
  plan_id: "price_xxxxx",
  plan_name: "Plano Básico",
  amount: 29.99,
  currency: "BRL",
  current_period_start: "2024-01-01 00:00:00",
  current_period_end: "2024-02-01 00:00:00",
  cancel_at_period_end: false,
  customer: {
    id: 1,
    name: "Cliente 1",
    email: "cliente1@exemplo.com"
  },
  metadata: {}
}
```

### 🧩 Componentes Necessários (Bootstrap 5)
- **Page Header**: 
  - Título com ID da assinatura
  - Breadcrumb (Dashboard > Assinaturas > ID)
  - Status badge (`badge bg-success`, `badge bg-danger`, etc.)
  - Botões de ação: "Editar", "Cancelar", "Reativar", "Voltar"
- **Subscription Info Card** (usar `card`):
  - Plano atual (nome e ID)
  - Valor formatado (R$ 29,99/mês)
  - Período atual (início e fim)
  - Próxima cobrança
  - Status (badge colorido)
  - Cancelar no final do período (sim/não)
- **Customer Info Card** (usar `card`):
  - Nome e email do cliente
  - Link para ver detalhes do cliente (`/clientes/:id`)
- **Billing History Card** (usar `card`):
  - Lista de faturas/invoices (tabela)
  - Próximas cobranças
- **History Timeline Card** (usar `card`):
  - Histórico de mudanças (timeline vertical)
  - Eventos importantes
- **Action Buttons**:
  - "Editar Assinatura" (`btn btn-primary`) → Navega para `/assinaturas/:id/editar`
  - "Cancelar Assinatura" (`btn btn-danger`) → Mostra modal de confirmação
  - "Reativar" (`btn btn-success`) → Reativa (se cancelada)
  - "Ver Histórico" (`btn btn-outline-info`) → Navega para `/assinaturas/:id/historico`
  - "Voltar" (`btn btn-secondary`) → Volta para lista

### 🔄 Fluxo de Dados
1. Página carrega com `subscription_id` da URL (`/assinaturas/:id`)
2. Faz múltiplas requisições em paralelo:
   - `GET /v1/subscriptions/:id` → Detalhes
   - `GET /v1/subscriptions/:id/history` → Histórico
   - `GET /v1/subscriptions/:id/history/stats` → Estatísticas do histórico
3. Exibe todas as informações em cards Bootstrap
4. Ao clicar em "Editar": Navega para `/assinaturas/:id/editar`
5. Ao clicar em "Cancelar": Mostra modal → `DELETE /v1/subscriptions/:id` → Recarrega
6. Ao clicar em "Reativar": `POST /v1/subscriptions/:id/reactivate` → Recarrega

### 💡 Interações do Usuário
- Visualizar todos os detalhes da assinatura
- Ver histórico de mudanças
- Clicar em "Editar Assinatura" → Vai para página de edição
- Cancelar assinatura (com confirmação em modal)
- Reativar assinatura cancelada
- Ver histórico completo
- Navegar para detalhes do cliente
- Voltar para lista de assinaturas

### ⚠️ Tratamento de Erros
- Assinatura não encontrada: 404
- Erro ao carregar: Mensagem de erro
- Confirmação antes de ações destrutivas

---

## 9.1. Criar Assinatura

### 📄 Descrição
Página ou modal para criar uma nova assinatura para um cliente existente.

### 🎯 Objetivo
- Selecionar cliente
- Selecionar plano/preço
- Configurar opções (trial, payment behavior)
- Criar assinatura no sistema
- Redirecionar após criação

### 🔌 Rotas da API Utilizadas
- **GET `/v1/customers`** - Lista clientes (para seleção)
- **GET `/v1/prices`** - Lista preços (para seleção)
- **POST `/v1/subscriptions`** - Cria a assinatura

### 📋 Campos do Formulário
- **Cliente** (select, obrigatório) - Seleção de cliente existente
- **Plano/Preço** (select, obrigatório) - Seleção de preço do Stripe
- **Período de Trial** (number, opcional) - Dias de trial
- **Comportamento de Pagamento** (select, opcional) - Payment behavior
- **Metadados** (JSON, opcional) - Metadados adicionais

### 🧩 Componentes Necessários (Bootstrap 5)
- **Page Header**: Título "Criar Nova Assinatura" + Breadcrumb
- **Form Container** (usar `card` com `card-body`):
  - Select de clientes (`form-select`)
  - Select de preços (`form-select`)
  - Campos opcionais (trial, payment behavior)
  - Validação Bootstrap
- **Action Buttons**:
  - "Criar Assinatura" (`btn btn-primary`) → Submete formulário
  - "Cancelar" (`btn btn-secondary`) → Volta para lista

### 🔄 Fluxo de Dados
1. Página carrega (`/assinaturas/criar`)
2. Carrega lista de clientes (`GET /v1/customers`)
3. Carrega lista de preços (`GET /v1/prices?active=true`)
4. Preenche selects
5. Usuário seleciona cliente e plano
6. Preenche opções (se necessário)
7. Ao submeter: `POST /v1/subscriptions`
8. Se sucesso: Redireciona para `/assinaturas/:id` (detalhes da assinatura criada)
9. Se erro: Mostra mensagem de erro

### 💡 Interações do Usuário
- Selecionar cliente da lista
- Selecionar plano/preço da lista
- Configurar período de trial (opcional)
- Adicionar metadados (opcional)
- Submeter formulário
- Cancelar e voltar para lista

**📋 Para estrutura HTML completa e código JavaScript, consulte:** [Formulário de Criar Assinatura](FORMULARIOS_BOOTSTRAP.md#5-formulário-de-criar-assinatura)

---

## 9.2. Editar Assinatura

### 📄 Descrição
Página para editar uma assinatura existente (alterar plano, cancelar no final do período, atualizar metadata).

### 🎯 Objetivo
- Carregar dados atuais da assinatura
- Permitir alteração de plano
- Permitir configurar cancelamento no final do período
- Atualizar metadata
- Salvar alterações

### 🔌 Rotas da API Utilizadas
- **GET `/v1/subscriptions/:id`** - Carrega dados da assinatura
- **GET `/v1/prices`** - Lista preços (para alterar plano)
- **PUT `/v1/subscriptions/:id`** - Atualiza a assinatura

### 📋 Campos do Formulário
- **Plano Atual** (text, somente leitura) - Plano atual da assinatura
- **Alterar para Plano** (select, opcional) - Novo plano (se quiser alterar)
- **Cancelar no Final do Período** (checkbox, opcional) - Marcar para cancelar ao final
- **Metadados** (JSON, opcional) - Metadados adicionais

**Nota:** Campos não editáveis (ID, Status, Cliente, Data de Criação) devem ser exibidos como somente leitura.

### 🧩 Componentes Necessários (Bootstrap 5)
- **Page Header**: 
  - Título "Editar Assinatura: [ID]" + Breadcrumb
  - Botão "Voltar"
- **Form Container** (usar `card` com `card-body`):
  - Campos somente leitura (ID, Cliente, Status, Plano Atual, Data de Criação) - usar `form-control-plaintext`
  - Select para alterar plano (`form-select`)
  - Checkbox para cancelar no final do período (`form-check form-switch`)
  - Textarea para metadados (`form-control font-monospace`)
  - Validação Bootstrap
- **Action Buttons**:
  - "Salvar Alterações" (`btn btn-primary`) → Submete formulário
  - "Cancelar" (`btn btn-secondary`) → Volta para detalhes
  - "Voltar para Detalhes" (`btn btn-outline-secondary`) → Volta para `/assinaturas/:id`

### 🔄 Fluxo de Dados
1. Página carrega com `subscription_id` da URL (`/assinaturas/:id/editar`)
2. Faz `GET /v1/subscriptions/:id` para carregar dados atuais
3. Faz `GET /v1/prices?active=true` para carregar planos disponíveis
4. Preenche formulário com dados da assinatura
5. Usuário edita campos
6. Validação client-side
7. Ao submeter: `PUT /v1/subscriptions/:id` com dados editados
8. Se sucesso: Redireciona para `/assinaturas/:id` (detalhes atualizados)
9. Se erro: Mostra mensagem de erro

### 💡 Interações do Usuário
- Visualizar dados atuais da assinatura
- Ver campos não editáveis (somente leitura)
- Selecionar novo plano (se quiser alterar)
- Marcar/desmarcar "Cancelar no final do período"
- Editar metadados (JSON)
- Salvar alterações
- Cancelar e voltar para detalhes

**📋 Para estrutura HTML completa e código JavaScript, consulte:** [Formulário de Editar Assinatura](FORMULARIOS_BOOTSTRAP.md#6-formulário-de-editar-assinatura)

---

## 10. Histórico de Assinaturas

### 📄 Descrição
Página que exibe o histórico completo de mudanças de uma assinatura, incluindo alterações de plano, status, valores e eventos importantes.

### 🎯 Objetivo
- Mostrar todas as mudanças da assinatura
- Exibir timeline de eventos
- Permitir auditoria
- Mostrar estatísticas do histórico

### 🔌 Rotas da API Utilizadas
- **GET `/v1/subscriptions/:id/history`** - Histórico completo
- **GET `/v1/subscriptions/:id/history/stats`** - Estatísticas

### 📊 Dados Recebidos
```javascript
// History
[
  {
    id: 1,
    subscription_id: 1,
    change_type: "created",
    old_data: {},
    new_data: {
      status: "active",
      plan_id: "price_xxxxx",
      amount: 29.99
    },
    changed_by: "api",
    description: "Assinatura criada",
    created_at: "2024-01-01 10:00:00"
  },
  {
    id: 2,
    change_type: "updated",
    old_data: { plan_id: "price_old" },
    new_data: { plan_id: "price_new" },
    changed_by: "user",
    description: "Plano atualizado",
    created_at: "2024-01-15 14:30:00"
  }
]

// Stats
{
  total_changes: 5,
  changes_by_type: {
    created: 1,
    updated: 3,
    canceled: 1
  },
  first_change: "2024-01-01",
  last_change: "2024-01-15"
}
```

### 🧩 Componentes Necessários
- **Timeline View**: Timeline vertical com eventos
- **History Table**: Tabela com todas as mudanças
- **Stats Cards**: Estatísticas do histórico
- **Filters**: Filtrar por tipo de mudança, data
- **Export**: Exportar histórico (opcional)

### 🔄 Fluxo de Dados
1. Página carrega com `subscription_id`
2. Faz `GET /v1/subscriptions/:id/history`
3. Faz `GET /v1/subscriptions/:id/history/stats`
4. Exibe em timeline e tabela
5. Aplica filtros (client-side)

### 💡 Interações do Usuário
- Visualizar timeline
- Filtrar por tipo de mudança
- Ver detalhes de cada mudança
- Exportar histórico
- Navegar entre mudanças

---

## 11. Gerenciamento de Clientes (Listagem)

### 📄 Descrição
Página que lista todos os clientes do tenant em formato de tabela, permite buscar, filtrar e acessar ações (ver detalhes, criar, editar).

### 🎯 Objetivo
- Listar todos os clientes em tabela
- Buscar e filtrar clientes
- Acesso rápido para criar novo cliente
- Acesso rápido para ver detalhes de um cliente
- Acesso rápido para editar um cliente

### 🔌 Rotas da API Utilizadas
- **GET `/v1/customers`** - Lista todos os clientes

### 📊 Dados Recebidos
```javascript
{
  success: true,
  data: [
    {
      id: 1,
      stripe_customer_id: "cus_xxxxx",
      email: "cliente1@exemplo.com",
      name: "Cliente 1",
      created_at: "2024-01-01 10:00:00",
      metadata: {}
    }
  ],
  count: 10
}
```

### 🧩 Componentes Necessários (Bootstrap 5)
- **Page Header**: Título "Clientes" + Botão "Criar Cliente"
- **Search Bar**: Busca por nome, email (usar `input-group` com `form-control`)
- **Filters**: Filtrar por data de criação, etc. (usar `form-select` ou `form-check`)
- **Customers Table** (usar `table table-striped table-hover`): Tabela com colunas:
  - ID
  - Nome
  - Email
  - ID Stripe (formato monospace)
  - Data de Criação
  - Ações (botões: Ver, Editar)
- **Action Buttons** (usar `btn-group`):
  - "Ver" (`btn btn-sm btn-outline-primary`) → Navega para `/clientes/:id`
  - "Editar" (`btn btn-sm btn-outline-secondary`) → Navega para `/clientes/:id/editar`
- **Create Button**: "Criar Cliente" (`btn btn-primary`) → Navega para `/clientes/criar` ou abre modal
- **Pagination**: Paginação (usar `pagination` do Bootstrap)
- **Empty State**: Mensagem quando não há clientes

### 🔄 Fluxo de Dados
1. Página carrega
2. Faz `GET /v1/customers` para obter todos os clientes
3. Exibe em tabela Bootstrap
4. Aplica busca/filtros (client-side ou server-side)
5. Ao clicar em "Criar Cliente": Navega para `/clientes/criar` ou abre modal
6. Ao clicar em "Ver": Navega para `/clientes/:id` (página de detalhes)
7. Ao clicar em "Editar": Navega para `/clientes/:id/editar` (página de edição)

### 💡 Interações do Usuário
- Buscar clientes (em tempo real ou ao pressionar Enter)
- Filtrar clientes por critérios
- Ordenar colunas (clicando no header)
- Clicar em "Criar Cliente" → Vai para página de criação
- Clicar em "Ver" → Vai para página de detalhes
- Clicar em "Editar" → Vai para página de edição
- Exportar lista para CSV/Excel (opcional)

### 📋 Formulários Relacionados
- **Criar Cliente**: Consulte [Formulário de Criar Cliente](FORMULARIOS_BOOTSTRAP.md#3-formulário-de-criar-cliente)

---

## 12. Detalhes do Cliente (Visualização)

### 📄 Descrição
Página de visualização completa de um cliente específico, incluindo informações pessoais, assinaturas ativas, histórico de faturas, métodos de pagamento e ações disponíveis.

### 🎯 Objetivo
- Exibir todos os detalhes do cliente (somente leitura)
- Mostrar assinaturas ativas do cliente
- Exibir histórico de faturas
- Listar métodos de pagamento salvos
- Fornecer acesso para editar, criar assinatura, etc.

### 🔌 Rotas da API Utilizadas
- **GET `/v1/customers/:id`** - Detalhes do cliente
- **GET `/v1/customers/:id/invoices`** - Faturas do cliente
- **GET `/v1/customers/:id/payment-methods`** - Métodos de pagamento
- **GET `/v1/subscriptions`** - Assinaturas (filtrar por customer_id no front-end)

### 📊 Dados Recebidos
```javascript
// Customer
{
  id: 1,
  stripe_customer_id: "cus_xxxxx",
  email: "cliente@exemplo.com",
  name: "Cliente 1",
  created_at: "2024-01-01",
  metadata: {}
}

// Invoices
[
  {
    id: "in_xxxxx",
    amount_paid: 2999,
    currency: "brl",
    status: "paid",
    created: 1704110400
  }
]

// Payment Methods
[
  {
    id: "pm_xxxxx",
    type: "card",
    card: {
      brand: "visa",
      last4: "4242",
      exp_month: 12,
      exp_year: 2025
    }
  }
]
```

### 🧩 Componentes Necessários (Bootstrap 5)
- **Page Header**: 
  - Título com nome do cliente
  - Breadcrumb (Dashboard > Clientes > Nome do Cliente)
  - Botões de ação: "Editar", "Voltar"
- **Customer Info Card** (usar `card`):
  - Nome completo
  - Email
  - ID Stripe (formato monospace)
  - Data de criação
  - Metadados (se houver)
- **Active Subscriptions Card** (usar `card`):
  - Lista de assinaturas ativas do cliente
  - Link para ver detalhes de cada assinatura
  - Botão "Criar Nova Assinatura"
- **Invoices List Card** (usar `card`):
  - Tabela com faturas (`table table-striped`)
  - Status de cada fatura (badges)
  - Link para ver detalhes de cada fatura
- **Payment Methods Card** (usar `card`):
  - Lista de métodos de pagamento
  - Indicador de método padrão
  - Botões: "Gerenciar Métodos de Pagamento"
- **Action Buttons**:
  - "Editar Cliente" (`btn btn-primary`) → Navega para `/clientes/:id/editar`
  - "Criar Assinatura" (`btn btn-success`) → Navega para criar assinatura
  - "Portal de Cobrança" (`btn btn-outline-primary`) → Abre portal do Stripe
  - "Voltar" (`btn btn-secondary`) → Volta para lista de clientes

### 🔄 Fluxo de Dados
1. Página carrega com `customer_id` da URL (`/clientes/:id`)
2. Faz múltiplas requisições em paralelo:
   - `GET /v1/customers/:id` → Detalhes do cliente
   - `GET /v1/customers/:id/invoices` → Faturas
   - `GET /v1/customers/:id/payment-methods` → Métodos de pagamento
   - `GET /v1/subscriptions` → Todas as assinaturas (filtrar por customer_id no front-end)
3. Exibe todas as informações em cards Bootstrap
4. Ao clicar em "Editar": Navega para `/clientes/:id/editar`
5. Ao clicar em assinatura: Navega para `/assinaturas/:id`

### 💡 Interações do Usuário
- Visualizar todos os detalhes do cliente
- Ver assinaturas ativas do cliente
- Ver histórico de faturas
- Ver métodos de pagamento salvos
- Clicar em "Editar Cliente" → Vai para página de edição
- Clicar em "Criar Assinatura" → Cria nova assinatura para este cliente
- Clicar em "Portal de Cobrança" → Abre portal do Stripe
- Navegar para detalhes de assinaturas/faturas
- Voltar para lista de clientes

---

## 12.1. Criar Cliente

### 📄 Descrição
Página ou modal para criar um novo cliente no sistema.

### 🎯 Objetivo
- Coletar dados do novo cliente
- Validar dados antes de enviar
- Criar cliente no banco de dados
- Redirecionar após criação

### 🔌 Rotas da API Utilizadas
- **POST `/v1/customers`** - Cria o cliente

### 📋 Campos do Formulário
- **Nome** (text, obrigatório, min: 2 caracteres)
- **Email** (email, obrigatório, email válido)
- **Metadados** (JSON, opcional)

### 🧩 Componentes Necessários (Bootstrap 5)
- **Page Header**: Título "Criar Novo Cliente" + Breadcrumb
- **Form Container** (usar `card` com `card-body`):
  - Formulário completo de criação
  - Validação Bootstrap
  - Mensagens de erro inline
- **Action Buttons**:
  - "Salvar" (`btn btn-primary`) → Submete formulário
  - "Cancelar" (`btn btn-secondary`) → Volta para lista

### 🔄 Fluxo de Dados
1. Página carrega (`/clientes/criar`)
2. Usuário preenche formulário
3. Validação client-side (Bootstrap validation)
4. Ao submeter: `POST /v1/customers`
5. Se sucesso: Redireciona para `/clientes/:id` (detalhes do cliente criado)
6. Se erro: Mostra mensagem de erro

### 💡 Interações do Usuário
- Preencher nome e email
- Adicionar metadados (opcional)
- Ver validação em tempo real
- Submeter formulário
- Cancelar e voltar para lista

**📋 Para estrutura HTML completa e código JavaScript, consulte:** [Formulário de Criar Cliente](FORMULARIOS_BOOTSTRAP.md#3-formulário-de-criar-cliente)

---

## 12.2. Editar Cliente

### 📄 Descrição
Página para editar dados de um cliente existente.

### 🎯 Objetivo
- Carregar dados atuais do cliente
- Permitir edição de campos editáveis
- Validar dados antes de salvar
- Atualizar cliente no banco
- Redirecionar após atualização

### 🔌 Rotas da API Utilizadas
- **GET `/v1/customers/:id`** - Carrega dados do cliente
- **PUT `/v1/customers/:id`** - Atualiza o cliente

### 📋 Campos do Formulário
- **Nome** (text, obrigatório, min: 2 caracteres)
- **Email** (email, obrigatório, email válido)
- **Metadados** (JSON, opcional)

**Nota:** Campos não editáveis (ID, Stripe Customer ID, Data de Criação) devem ser exibidos como somente leitura.

### 🧩 Componentes Necessários (Bootstrap 5)
- **Page Header**: 
  - Título "Editar Cliente: [Nome]" + Breadcrumb
  - Botão "Voltar"
- **Form Container** (usar `card` com `card-body`):
  - Campos editáveis (Nome, Email, Metadados)
  - Campos somente leitura (ID, Stripe ID, Data de Criação) - usar `form-control-plaintext`
  - Validação Bootstrap
  - Mensagens de erro inline
- **Action Buttons**:
  - "Salvar Alterações" (`btn btn-primary`) → Submete formulário
  - "Cancelar" (`btn btn-secondary`) → Volta para detalhes do cliente
  - "Voltar para Detalhes" (`btn btn-outline-secondary`) → Volta para `/clientes/:id`

### 🔄 Fluxo de Dados
1. Página carrega com `customer_id` da URL (`/clientes/:id/editar`)
2. Faz `GET /v1/customers/:id` para carregar dados atuais
3. Preenche formulário com dados do cliente
4. Usuário edita campos
5. Validação client-side
6. Ao submeter: `PUT /v1/customers/:id` com dados editados
7. Se sucesso: Redireciona para `/clientes/:id` (detalhes atualizados)
8. Se erro: Mostra mensagem de erro

### 💡 Interações do Usuário
- Visualizar dados atuais do cliente
- Editar nome e email
- Editar metadados (JSON)
- Ver validação em tempo real
- Salvar alterações
- Cancelar e voltar para detalhes
- Ver campos não editáveis (somente leitura)

**📋 Para estrutura HTML completa e código JavaScript, consulte:** [Formulário de Editar Cliente](FORMULARIOS_BOOTSTRAP.md#4-formulário-de-editar-cliente)

---

## 13. Faturas do Cliente

### 📄 Descrição
Página que lista todas as faturas de um cliente específico, com detalhes de cada fatura, status de pagamento e opção de download.

### 🎯 Objetivo
- Listar faturas do cliente
- Mostrar status de cada fatura
- Exibir detalhes (valor, data, etc.)
- Permitir download (se Stripe permitir)

### 🔌 Rotas da API Utilizadas
- **GET `/v1/customers/:id/invoices`** - Lista faturas
- **GET `/v1/invoices/:id`** - Detalhes de uma fatura

### 📊 Dados Recebidos
```javascript
[
  {
    id: "in_xxxxx",
    customer: "cus_xxxxx",
    amount_paid: 2999,
    amount_due: 0,
    currency: "brl",
    status: "paid",
    created: 1704110400,
    due_date: 1704110400,
    invoice_pdf: "https://..."
  }
]
```

### 🧩 Componentes Necessários
- **Invoices Table**: Tabela com faturas
- **Status Badges**: Status de cada fatura
- **Download Button**: Baixar PDF (se disponível)
- **Filters**: Filtrar por status, período

### 🔄 Fluxo de Dados
1. Página carrega com `customer_id`
2. Faz `GET /v1/customers/:id/invoices`
3. Exibe em tabela
4. Ao clicar em fatura: Mostra detalhes ou navega para página de detalhes

### 💡 Interações do Usuário
- Ver todas as faturas
- Filtrar faturas
- Ver detalhes de uma fatura
- Baixar PDF da fatura
- Ver status de pagamento

---

## 14. Métodos de Pagamento

### 📄 Descrição
Página que lista e gerencia métodos de pagamento de um cliente, permite adicionar, remover, definir como padrão e atualizar métodos de pagamento.

### 🎯 Objetivo
- Listar métodos de pagamento do cliente
- Adicionar novo método
- Remover método
- Definir método padrão
- Atualizar método

### 🔌 Rotas da API Utilizadas
- **GET `/v1/customers/:id/payment-methods`** - Lista métodos
- **PUT `/v1/customers/:id/payment-methods/:pm_id`** - Atualiza
- **DELETE `/v1/customers/:id/payment-methods/:pm_id`** - Remove
- **POST `/v1/customers/:id/payment-methods/:pm_id/set-default`** - Define padrão
- **POST `/v1/setup-intents`** - Cria setup intent para adicionar método

### 📊 Dados Recebidos
```javascript
[
  {
    id: "pm_xxxxx",
    type: "card",
    card: {
      brand: "visa",
      last4: "4242",
      exp_month: 12,
      exp_year: 2025
    },
    billing_details: {
      name: "Nome",
      email: "email@exemplo.com"
    }
  }
]
```

### 🧩 Componentes Necessários
- **Payment Methods List**: Lista de métodos
- **Add Method Button**: Adicionar novo método
- **Method Card**: Card para cada método com:
  - Tipo (cartão, etc.)
  - Últimos 4 dígitos
  - Data de expiração
  - Badge "Padrão" se for padrão
  - Botões: Definir padrão, Editar, Remover
- **Add Method Modal**: Modal para adicionar (integração com Stripe Elements)

### 🔄 Fluxo de Dados
1. Página carrega com `customer_id`
2. Faz `GET /v1/customers/:id/payment-methods`
3. Exibe métodos
4. Ao adicionar:
   - Cria Setup Intent (`POST /v1/setup-intents`)
   - Integra com Stripe Elements
   - Confirma (`POST /v1/setup-intents/:id/confirm`)
   - Recarrega lista
5. Ao definir padrão: `POST /v1/customers/:id/payment-methods/:pm_id/set-default`
6. Ao remover: `DELETE /v1/customers/:id/payment-methods/:pm_id` (com confirmação)

### 💡 Interações do Usuário
- Ver métodos de pagamento
- Adicionar novo método
- Definir método como padrão
- Editar método
- Remover método
- Ver detalhes do método

---

## 15. Portal de Cobrança

### 📄 Descrição
Página que cria uma sessão do Stripe Billing Portal e redireciona o cliente para gerenciar sua assinatura, métodos de pagamento e faturas diretamente no Stripe.

### 🎯 Objetivo
- Criar sessão do portal de cobrança
- Redirecionar cliente para portal do Stripe
- Permitir que cliente gerencie própria conta

### 🔌 Rotas da API Utilizadas
- **POST `/v1/billing-portal`** - Cria sessão do portal

### 📊 Dados Enviados
```javascript
{
  customer_id: 1,
  return_url: "https://seu-site.com/dashboard"
}
```

### 📊 Dados Recebidos
```javascript
{
  success: true,
  data: {
    url: "https://billing.stripe.com/session/..."
  }
}
```

### 🧩 Componentes Necessários
- **Loading State**: Enquanto cria sessão
- **Redirect Handler**: Redireciona automaticamente

### 🔄 Fluxo de Dados
1. Página carrega
2. Obtém `customer_id` (do contexto autenticado ou parâmetro)
3. Faz `POST /v1/billing-portal` com `return_url`
4. Redireciona para `data.url` (portal do Stripe)
5. Cliente gerencia no Stripe
6. Stripe redireciona de volta para `return_url`

### 💡 Interações do Usuário
- Clicar em "Gerenciar Assinatura" ou similar
- Ser redirecionado para portal do Stripe
- Gerenciar no Stripe
- Voltar para dashboard

---

## 16. Estatísticas e Relatórios

### 📄 Descrição
Página que exibe estatísticas detalhadas, gráficos, relatórios de receita, crescimento de clientes, assinaturas e outras métricas importantes.

### 🎯 Objetivo
- Exibir estatísticas gerais
- Mostrar gráficos e visualizações
- Permitir análise de dados
- Exportar relatórios

### 🔌 Rotas da API Utilizadas
- **GET `/v1/stats`** - Estatísticas gerais
- **GET `/v1/subscriptions`** - Para cálculos adicionais
- **GET `/v1/customers`** - Para cálculos adicionais
- **GET `/v1/balance-transactions`** - Transações de saldo

### 📊 Dados Recebidos
```javascript
{
  customers: {
    total: 100,
    active: 80,
    new_this_month: 10
  },
  subscriptions: {
    total: 50,
    active: 45,
    canceled: 5
  },
  revenue: {
    total: 10000.00,
    this_month: 2000.00,
    currency: "BRL"
  }
}
```

### 🧩 Componentes Necessários
- **Stats Cards**: Cards com métricas principais
- **Charts**: Gráficos de:
  - Receita ao longo do tempo
  - Novos clientes
  - Assinaturas ativas
  - Churn rate
- **Filters**: Filtrar por período (último mês, trimestre, ano)
- **Export Button**: Exportar relatório
- **Comparison**: Comparar períodos

### 🔄 Fluxo de Dados
1. Página carrega
2. Faz `GET /v1/stats`
3. Faz requisições adicionais para dados históricos (se necessário)
4. Processa dados para gráficos
5. Renderiza gráficos e estatísticas
6. Ao exportar: Gera PDF/Excel com dados

### 💡 Interações do Usuário
- Visualizar estatísticas
- Filtrar por período
- Ver gráficos detalhados
- Exportar relatórios
- Comparar períodos

---

## 17. Faturas e Invoices

### 📄 Descrição
Página que lista todas as faturas do tenant, permite buscar, filtrar, ver detalhes e gerenciar faturas.

### 🎯 Objetivo
- Listar todas as faturas
- Buscar e filtrar faturas
- Ver detalhes de cada fatura
- Gerenciar faturas

### 🔌 Rotas da API Utilizadas
- **GET `/v1/customers/:id/invoices`** - Faturas de um cliente (via lista de clientes)
- **GET `/v1/invoices/:id`** - Detalhes de uma fatura

**Nota:** A API não tem endpoint direto para listar todas as faturas. É necessário iterar pelos clientes ou usar webhooks.

### 🧩 Componentes Necessários
- **Invoices Table**: Tabela com todas as faturas
- **Search/Filter**: Buscar por cliente, status, período
- **Status Badges**: Status de cada fatura
- **Details Modal**: Detalhes da fatura

### 🔄 Fluxo de Dados
1. Página carrega
2. Obtém lista de clientes (`GET /v1/customers`)
3. Para cada cliente, obtém faturas (`GET /v1/customers/:id/invoices`)
4. Combina e exibe todas as faturas
5. Aplica filtros/busca

### 💡 Interações do Usuário
- Ver todas as faturas
- Buscar faturas
- Filtrar por status/cliente/período
- Ver detalhes de uma fatura
- Baixar PDF (se disponível)

---

## 18. Reembolsos

### 📄 Descrição
Página que permite criar reembolsos para cobranças, listar reembolsos criados e gerenciar reembolsos.

### 🎯 Objetivo
- Criar reembolsos
- Listar reembolsos
- Ver detalhes de reembolsos
- Gerenciar reembolsos

### 🔌 Rotas da API Utilizadas
- **POST `/v1/refunds`** - Cria reembolso

**Nota:** A API não tem endpoint para listar reembolsos. É necessário usar webhooks ou Stripe Dashboard.

### 📊 Dados Enviados
```javascript
{
  charge_id: "ch_xxxxx",
  amount: 2999, // opcional, se não informado reembolsa total
  reason: "requested_by_customer",
  metadata: {}
}
```

### 🧩 Componentes Necessários
- **Create Refund Form**: Formulário para criar reembolso
- **Refunds List**: Lista de reembolsos (se disponível)
- **Refund Details**: Detalhes de um reembolso

### 🔄 Fluxo de Dados
1. Página carrega
2. Usuário seleciona cobrança (charge_id)
3. Preenche formulário (valor, motivo)
4. Submete: `POST /v1/refunds`
5. Mostra confirmação

### 💡 Interações do Usuário
- Selecionar cobrança para reembolsar
- Preencher dados do reembolso
- Criar reembolso
- Ver confirmação

---

## 19. Disputas e Chargebacks

### 📄 Descrição
Página que lista disputas/chargebacks, permite visualizar detalhes, adicionar evidências e gerenciar disputas.

### 🎯 Objetivo
- Listar disputas
- Ver detalhes de disputas
- Adicionar evidências
- Gerenciar disputas

### 🔌 Rotas da API Utilizadas
- **GET `/v1/disputes`** - Lista disputas
- **GET `/v1/disputes/:id`** - Detalhes de uma disputa
- **PUT `/v1/disputes/:id`** - Atualiza disputa (adiciona evidências)

### 📊 Dados Recebidos
```javascript
[
  {
    id: "dp_xxxxx",
    amount: 2999,
    currency: "brl",
    status: "warning_needs_response",
    reason: "fraudulent",
    charge: "ch_xxxxx",
    evidence_details: {
      due_by: 1704110400
    }
  }
]
```

### 🧩 Componentes Necessários
- **Disputes Table**: Tabela com disputas
- **Status Badges**: Status de cada disputa
- **Details View**: Detalhes da disputa
- **Evidence Form**: Formulário para adicionar evidências
- **File Upload**: Upload de arquivos como evidência

### 🔄 Fluxo de Dados
1. Página carrega
2. Faz `GET /v1/disputes`
3. Exibe disputas
4. Ao clicar em disputa: `GET /v1/disputes/:id`
5. Ao adicionar evidências: `PUT /v1/disputes/:id` com evidências

### 💡 Interações do Usuário
- Ver todas as disputas
- Ver detalhes de uma disputa
- Adicionar evidências
- Upload de arquivos
- Responder disputa

---

## 20. Login de Usuários

### 📄 Descrição
Página de login para usuários administrativos do sistema (não clientes finais).

### 🎯 Objetivo
- Autenticar usuários administrativos
- Criar sessão de usuário
- Redirecionar para dashboard

### 🔌 Rotas da API Utilizadas
- **POST `/v1/auth/login`** - Faz login

### 📊 Dados Enviados
```javascript
{
  email: "usuario@exemplo.com",
  password: "senha123",
  tenant_id: 1
}
```

### 📊 Dados Recebidos
```javascript
{
  success: true,
  data: {
    token: "session_id_xxxxx",
    user: {
      id: 1,
      email: "usuario@exemplo.com",
      name: "Nome do Usuário",
      role: "admin",
      tenant_id: 1
    }
  }
}
```

### 🧩 Componentes Necessários (Bootstrap 5)
- **Login Form** (usar `card` com `card-body`):
  - Email (usar `form-control` com `type="email"`, `required`)
  - Senha (usar `form-control` com `type="password"`, `required`, `minlength="6"`)
  - Tenant ID (usar `form-control` com `type="number"`, `required`)
- **Submit Button**: "Entrar" (usar `btn btn-primary btn-lg`)
- **Error Messages**: Mensagens de erro (usar `alert alert-danger`)
- **Remember Me**: Checkbox (opcional, usar `form-check`)

**📋 Para estrutura HTML completa e código JavaScript, consulte:** [Formulário de Login](FORMULARIOS_BOOTSTRAP.md#2-formulário-de-login)

### 🔄 Fluxo de Dados
1. Usuário preenche formulário
2. Validação client-side
3. Submete: `POST /v1/auth/login`
4. Se sucesso:
   - Salva `token` (session ID) no localStorage
   - Salva dados do usuário
   - Redireciona para dashboard
5. Se erro: Mostra mensagem de erro

### 💡 Interações do Usuário
- Preencher email e senha
- Selecionar tenant (se aplicável)
- Fazer login
- Ver mensagens de erro
- Recuperar senha (se implementado)

### ⚠️ Tratamento de Erros
- Credenciais inválidas: "Email ou senha incorretos"
- Usuário inativo: "Sua conta está inativa"
- Erro de API: "Erro ao fazer login. Tente novamente."

---

## 21. Gerenciamento de Usuários (Listagem)

### 📄 Descrição
Página administrativa que lista todos os usuários do sistema em formato de tabela, permite buscar, filtrar e acessar ações (ver detalhes, criar, editar, remover, alterar roles).

### 🎯 Objetivo
- Listar todos os usuários em tabela
- Buscar e filtrar usuários
- Acesso rápido para criar novo usuário
- Acesso rápido para ver detalhes de um usuário
- Acesso rápido para editar um usuário
- Ações: remover, alterar role

### 🔌 Rotas da API Utilizadas
- **GET `/v1/users`** - Lista todos os usuários

### 📊 Dados Recebidos
```javascript
[
  {
    id: 1,
    email: "usuario@exemplo.com",
    name: "Nome do Usuário",
    role: "admin",
    status: "active",
    created_at: "2024-01-01"
  }
]
```

### 🧩 Componentes Necessários (Bootstrap 5)
- **Page Header**: Título "Usuários" + Botão "Criar Usuário"
- **Search Bar**: Busca por nome, email (usar `input-group` com `form-control`)
- **Filters**: Filtrar por role, status (usar `form-select` ou `form-check`)
- **Users Table** (usar `table table-striped table-hover`): Tabela com colunas:
  - ID
  - Nome
  - Email
  - Role (badge)
  - Status (badge)
  - Data de Criação
  - Ações (botões: Ver, Editar, Remover)
- **Action Buttons** (usar `btn-group`):
  - "Ver" (`btn btn-sm btn-outline-primary`) → Navega para `/usuarios/:id`
  - "Editar" (`btn btn-sm btn-outline-secondary`) → Navega para `/usuarios/:id/editar`
  - "Remover" (`btn btn-sm btn-outline-danger`) → Mostra modal de confirmação
- **Create Button**: "Criar Usuário" (`btn btn-primary`) → Navega para `/usuarios/criar` ou abre modal
- **Pagination**: Paginação (usar `pagination` do Bootstrap)
- **Empty State**: Mensagem quando não há usuários

### 🔄 Fluxo de Dados
1. Página carrega
2. Faz `GET /v1/users` para obter todos os usuários
3. Exibe em tabela Bootstrap
4. Aplica busca/filtros (client-side ou server-side)
5. Ao clicar em "Criar Usuário": Navega para `/usuarios/criar` ou abre modal
6. Ao clicar em "Ver": Navega para `/usuarios/:id` (página de detalhes)
7. Ao clicar em "Editar": Navega para `/usuarios/:id/editar` (página de edição)
8. Ao clicar em "Remover": Mostra modal de confirmação → `DELETE /v1/users/:id` → Recarrega

### 💡 Interações do Usuário
- Buscar usuários (em tempo real ou ao pressionar Enter)
- Filtrar por role/status
- Ordenar colunas (clicando no header)
- Clicar em "Criar Usuário" → Vai para página de criação
- Clicar em "Ver" → Vai para página de detalhes
- Clicar em "Editar" → Vai para página de edição
- Remover usuário (com confirmação em modal)
- Exportar lista (opcional)

### 📋 Formulários Relacionados
- **Criar Usuário**: Consulte [Formulário de Criar Usuário](FORMULARIOS_BOOTSTRAP.md#7-formulário-de-criar-usuário)

---

## 21.1. Criar Usuário

### 📄 Descrição
Página ou modal para criar um novo usuário administrativo no sistema.

### 🎯 Objetivo
- Coletar dados do novo usuário
- Validar dados antes de enviar
- Criar usuário no banco de dados
- Redirecionar após criação

### 🔌 Rotas da API Utilizadas
- **POST `/v1/users`** - Cria o usuário

### 📋 Campos do Formulário
- **Nome** (text, obrigatório, min: 2 caracteres)
- **Email** (email, obrigatório, email válido)
- **Senha** (password, obrigatório, min: 6 caracteres)
- **Role** (select, obrigatório) - admin, editor, viewer
- **Status** (select, opcional) - active, inactive (padrão: active)

### 🧩 Componentes Necessários (Bootstrap 5)
- **Page Header**: Título "Criar Novo Usuário" + Breadcrumb
- **Form Container** (usar `card` com `card-body`):
  - Formulário completo de criação
  - Validação Bootstrap
  - Mensagens de erro inline
- **Action Buttons**:
  - "Salvar" (`btn btn-primary`) → Submete formulário
  - "Cancelar" (`btn btn-secondary`) → Volta para lista

### 🔄 Fluxo de Dados
1. Página carrega (`/usuarios/criar`)
2. Usuário preenche formulário
3. Validação client-side (Bootstrap validation)
4. Ao submeter: `POST /v1/users`
5. Se sucesso: Redireciona para `/usuarios/:id` (detalhes do usuário criado) ou recarrega lista
6. Se erro: Mostra mensagem de erro

### 💡 Interações do Usuário
- Preencher nome, email, senha
- Selecionar role
- Selecionar status (opcional)
- Ver validação em tempo real
- Submeter formulário
- Cancelar e voltar para lista

**📋 Para estrutura HTML completa e código JavaScript, consulte:** [Formulário de Criar Usuário](FORMULARIOS_BOOTSTRAP.md#7-formulário-de-criar-usuário)

---

## 21.2. Editar Usuário

### 📄 Descrição
Página para editar dados de um usuário existente (sem alterar senha).

### 🎯 Objetivo
- Carregar dados atuais do usuário
- Permitir edição de campos editáveis
- Validar dados antes de salvar
- Atualizar usuário no banco
- Redirecionar após atualização

### 🔌 Rotas da API Utilizadas
- **GET `/v1/users/:id`** - Carrega dados do usuário
- **PUT `/v1/users/:id`** - Atualiza o usuário
- **PUT `/v1/users/:id/role`** - Atualiza role (endpoint separado)

### 📋 Campos do Formulário
- **Nome** (text, obrigatório, min: 2 caracteres)
- **Email** (email, obrigatório, email válido)
- **Status** (select, opcional) - active, inactive

**Nota:** Para alterar senha, criar endpoint separado. Para alterar role, usar botão separado que chama `PUT /v1/users/:id/role`.

### 🧩 Componentes Necessários (Bootstrap 5)
- **Page Header**: 
  - Título "Editar Usuário: [Nome]" + Breadcrumb
  - Botão "Voltar"
- **Form Container** (usar `card` com `card-body`):
  - Campos editáveis (Nome, Email, Status)
  - Campos somente leitura (ID, Role, Data de Criação) - usar `form-control-plaintext`
  - Validação Bootstrap
  - Mensagens de erro inline
- **Role Section** (separado):
  - Select de role (`form-select`)
  - Botão "Atualizar Role" (`btn btn-outline-primary`) → Chama `PUT /v1/users/:id/role`
- **Action Buttons**:
  - "Salvar Alterações" (`btn btn-primary`) → Submete formulário
  - "Cancelar" (`btn btn-secondary`) → Volta para detalhes
  - "Voltar para Detalhes" (`btn btn-outline-secondary`) → Volta para `/usuarios/:id`

### 🔄 Fluxo de Dados
1. Página carrega com `user_id` da URL (`/usuarios/:id/editar`)
2. Faz `GET /v1/users/:id` para carregar dados atuais
3. Preenche formulário com dados do usuário
4. Usuário edita campos
5. Validação client-side
6. Ao submeter: `PUT /v1/users/:id` com dados editados
7. Se sucesso: Redireciona para `/usuarios/:id` (detalhes atualizados) ou recarrega lista
8. Se erro: Mostra mensagem de erro

### 💡 Interações do Usuário
- Visualizar dados atuais do usuário
- Editar nome e email
- Alterar status
- Alterar role (via botão separado)
- Ver campos não editáveis (somente leitura)
- Salvar alterações
- Cancelar e voltar para detalhes

**📋 Para estrutura HTML completa e código JavaScript, consulte:** [Formulário de Editar Usuário](FORMULARIOS_BOOTSTRAP.md#8-formulário-de-editar-usuário)

---

## 22. Gerenciamento de Permissões

### 📄 Descrição
Página administrativa para gerenciar permissões de usuários (conceder, revogar permissões específicas).

### 🎯 Objetivo
- Listar permissões disponíveis
- Ver permissões de um usuário
- Conceder permissões
- Revogar permissões

### 🔌 Rotas da API Utilizadas
- **GET `/v1/permissions`** - Lista permissões disponíveis
- **GET `/v1/users/:id/permissions`** - Permissões de um usuário
- **POST `/v1/users/:id/permissions`** - Concede permissão
- **DELETE `/v1/users/:id/permissions/:permission`** - Revoga permissão

### 📊 Dados Recebidos
```javascript
// Available permissions
[
  "create_customers",
  "view_customers",
  "create_subscriptions",
  "view_subscriptions",
  // ...
]

// User permissions
[
  "create_customers",
  "view_customers"
]
```

### 🧩 Componentes Necessários
- **Permissions List**: Lista de permissões disponíveis
- **User Permissions**: Permissões do usuário selecionado
- **Toggle Switches**: Ativar/desativar permissões
- **User Selector**: Seletor de usuário

### 🔄 Fluxo de Dados
1. Página carrega
2. Faz `GET /v1/permissions` para obter todas
3. Seleciona usuário
4. Faz `GET /v1/users/:id/permissions`
5. Exibe permissões com toggles
6. Ao alterar: `POST` ou `DELETE` conforme necessário

### 💡 Interações do Usuário
- Selecionar usuário
- Ver permissões do usuário
- Ativar/desativar permissões
- Ver todas as permissões disponíveis

---

## 23. Gerenciamento de Produtos (Listagem)

### 📄 Descrição
Página administrativa que lista todos os produtos do Stripe em formato de tabela, permite buscar, filtrar e acessar ações (ver detalhes, criar, editar, remover).

### 🎯 Objetivo
- Listar todos os produtos do Stripe em tabela
- Buscar e filtrar produtos
- Acesso rápido para criar novo produto
- Acesso rápido para ver detalhes de um produto
- Acesso rápido para editar um produto
- Remover produtos

### 🔌 Rotas da API Utilizadas
- **GET `/v1/products`** - Lista todos os produtos do Stripe
- **GET `/v1/products/:id`** - Detalhes de um produto
- **POST `/v1/products`** - Cria produto
- **PUT `/v1/products/:id`** - Atualiza produto
- **DELETE `/v1/products/:id`** - Remove produto

**Nota:** Os produtos são armazenados no Stripe, não no banco de dados local. A listagem busca diretamente do Stripe.

### 📊 Dados Recebidos
```javascript
{
  success: true,
  data: [
    {
      id: "prod_xxxxx",
      name: "Plano Premium",
      description: "Descrição do plano",
      active: true,
      images: [],
      statement_descriptor: null,
      unit_label: null,
      created: "2024-01-01 10:00:00",
      updated: "2024-01-01 10:00:00",
      metadata: {
        tenant_id: "3"
      }
    }
  ],
  has_more: false,
  count: 5
}
```

### 🧩 Componentes Necessários (Bootstrap 5)
- **Page Header**: Título "Produtos" + Botão "Criar Produto"
- **Search Bar**: Busca por nome (usar `input-group` com `form-control`)
- **Filters**: Filtrar por status (ativo/inativo) (usar `form-select` ou `form-check`)
- **Products Table** (usar `table table-striped table-hover`): Tabela com colunas:
  - ID Stripe (formato monospace)
  - Nome
  - Descrição (truncada)
  - Status (badge: Ativo/Inativo)
  - Imagens (miniaturas, se houver)
  - Data de Criação
  - Ações (botões: Ver, Editar, Remover)
- **Action Buttons** (usar `btn-group`):
  - "Ver" (`btn btn-sm btn-outline-primary`) → Navega para `/produtos/:id`
  - "Editar" (`btn btn-sm btn-outline-secondary`) → Navega para `/produtos/:id/editar`
  - "Remover" (`btn btn-sm btn-outline-danger`) → Mostra modal de confirmação
- **Create Button**: "Criar Produto" (`btn btn-primary`) → Navega para `/produtos/criar` ou abre modal
- **Pagination**: Paginação (usar `pagination` do Bootstrap) - se `has_more: true`
- **Empty State**: Mensagem quando não há produtos
- **Loading State**: Spinner enquanto carrega do Stripe

### 🔄 Fluxo de Dados
1. Página carrega
2. Faz `GET /v1/products` para obter todos os produtos do Stripe
3. Exibe em tabela Bootstrap
4. Aplica busca/filtros (client-side ou via query params)
5. Ao clicar em "Criar Produto": Navega para `/produtos/criar` ou abre modal
6. Ao clicar em "Ver": Navega para `/produtos/:id` (página de detalhes)
7. Ao clicar em "Editar": Navega para `/produtos/:id/editar` (página de edição)
8. Ao clicar em "Remover": Mostra modal de confirmação → `DELETE /v1/products/:id` → Recarrega

### 💡 Interações do Usuário
- Buscar produtos por nome (em tempo real ou ao pressionar Enter)
- Filtrar por status (ativo/inativo)
- Ordenar colunas (clicando no header)
- Clicar em "Criar Produto" → Vai para página de criação
- Clicar em "Ver" → Vai para página de detalhes
- Clicar em "Editar" → Vai para página de edição
- Remover produto (com confirmação em modal)
- Ver paginação (se houver mais resultados)
- Exportar lista (opcional)

### ⚠️ Tratamento de Erros
- Erro ao carregar: "Erro ao carregar produtos do Stripe"
- Produto não encontrado: 404
- Erro de conexão com Stripe: Mostrar mensagem apropriada

### 📋 Formulários Relacionados
- **Criar Produto**: Consulte [Formulário de Criar Produto](FORMULARIOS_BOOTSTRAP.md#9-formulário-de-criar-produto)

---

## 24. Gerenciamento de Preços (Listagem)

### 📄 Descrição
Página administrativa que lista todos os preços do Stripe em formato de tabela, permite buscar, filtrar e acessar ações (ver detalhes, criar, editar).

### 🎯 Objetivo
- Listar todos os preços do Stripe em tabela
- Buscar e filtrar preços
- Acesso rápido para criar novo preço
- Acesso rápido para ver detalhes de um preço
- Acesso rápido para editar um preço

### 🔌 Rotas da API Utilizadas
- **GET `/v1/prices`** - Lista todos os preços do Stripe (com filtros)
- **GET `/v1/prices/:id`** - Detalhes de um preço
- **POST `/v1/prices`** - Cria preço
- **PUT `/v1/prices/:id`** - Atualiza preço (apenas metadata)

**Nota:** Os preços são armazenados no Stripe, não no banco de dados local. A listagem busca diretamente do Stripe. Preços não podem ser editados após criação (exceto metadata).

### 📊 Dados Recebidos
```javascript
{
  success: true,
  data: [
    {
      id: "price_xxxxx",
      active: true,
      currency: "BRL",
      type: "recurring",
      unit_amount: 2999,
      unit_amount_decimal: "29.99",
      formatted_amount: "29,99",
      created: "2024-01-01 10:00:00",
      metadata: {
        tenant_id: "3"
      },
      recurring: {
        interval: "month",
        interval_count: 1,
        trial_period_days: null
      },
      product: {
        id: "prod_xxxxx",
        name: "Plano Premium",
        description: "Descrição do plano"
      }
    }
  ],
  has_more: false,
  count: 10
}
```

### 🧩 Componentes Necessários (Bootstrap 5)
- **Page Header**: Título "Preços" + Botão "Criar Preço"
- **Search Bar**: Busca por nome do produto, ID do preço (usar `input-group` com `form-control`)
- **Filters** (usar `form-select` ou `form-check`):
  - Status (ativo/inativo)
  - Tipo (one_time, recurring)
  - Produto (select com produtos)
  - Moeda (brl, usd, etc.)
- **Prices Table** (usar `table table-striped table-hover`): Tabela com colunas:
  - ID Stripe (formato monospace)
  - Produto (nome)
  - Valor formatado (R$ 29,99)
  - Tipo (badge: Recorrente/Único)
  - Intervalo (se recorrente: mensal, anual, etc.)
  - Moeda
  - Status (badge: Ativo/Inativo)
  - Data de Criação
  - Ações (botões: Ver, Editar)
- **Action Buttons** (usar `btn-group`):
  - "Ver" (`btn btn-sm btn-outline-primary`) → Navega para `/precos/:id`
  - "Editar" (`btn btn-sm btn-outline-secondary`) → Navega para `/precos/:id/editar` (só metadata)
- **Create Button**: "Criar Preço" (`btn btn-primary`) → Navega para `/precos/criar` ou abre modal
- **Pagination**: Paginação (usar `pagination` do Bootstrap) - se `has_more: true`
- **Empty State**: Mensagem quando não há preços
- **Loading State**: Spinner enquanto carrega do Stripe
- **Info Alert**: Aviso de que preços não podem ser editados após criação (exceto metadata)

### 🔄 Fluxo de Dados
1. Página carrega
2. Faz `GET /v1/prices` para obter todos os preços do Stripe
3. Opcionalmente faz `GET /v1/products` para obter lista de produtos (para filtros)
4. Exibe em tabela Bootstrap
5. Aplica busca/filtros (client-side ou via query params)
6. Ao clicar em "Criar Preço": Navega para `/precos/criar` ou abre modal
7. Ao clicar em "Ver": Navega para `/precos/:id` (página de detalhes)
8. Ao clicar em "Editar": Navega para `/precos/:id/editar` (só permite editar metadata)

### 💡 Interações do Usuário
- Buscar preços por produto, ID (em tempo real ou ao pressionar Enter)
- Filtrar por status/tipo/produto/moeda
- Ordenar colunas (clicando no header)
- Clicar em "Criar Preço" → Vai para página de criação
- Clicar em "Ver" → Vai para página de detalhes
- Clicar em "Editar" → Vai para página de edição (só metadata)
- Ver paginação (se houver mais resultados)
- Exportar lista (opcional)

### ⚠️ Tratamento de Erros
- Erro ao carregar: "Erro ao carregar preços do Stripe"
- Preço não encontrado: 404
- Erro de conexão com Stripe: Mostrar mensagem apropriada

### 📋 Formulários Relacionados
- **Criar Preço**: Consulte [Formulário de Criar Preço](FORMULARIOS_BOOTSTRAP.md#11-formulário-de-criar-preço)

---

## 25. Gerenciamento de Cupons (Listagem)

### 📄 Descrição
Página administrativa que lista todos os cupons do Stripe em formato de tabela, permite buscar, filtrar e acessar ações (ver detalhes, criar, remover).

### 🎯 Objetivo
- Listar todos os cupons do Stripe em tabela
- Buscar e filtrar cupons
- Acesso rápido para criar novo cupom
- Acesso rápido para ver detalhes de um cupom
- Remover cupons

### 🔌 Rotas da API Utilizadas
- **GET `/v1/coupons`** - Lista todos os cupons do Stripe
- **GET `/v1/coupons/:id`** - Detalhes de um cupom
- **POST `/v1/coupons`** - Cria cupom
- **DELETE `/v1/coupons/:id`** - Remove cupom

**Nota:** Os cupons são armazenados no Stripe, não no banco de dados local. A listagem busca diretamente do Stripe.

### 📊 Dados Recebidos
```javascript
{
  success: true,
  data: [
    {
      id: "desconto10",
      name: "Desconto 10%",
      percent_off: 10,
      amount_off: null,
      currency: null,
      duration: "once",
      duration_in_months: null,
      max_redemptions: null,
      times_redeemed: 5,
      redeem_by: null,
      valid: true,
      created: "2024-01-01 10:00:00",
      metadata: {
        tenant_id: "3"
      }
    }
  ],
  has_more: false,
  count: 5
}
```

### 🧩 Componentes Necessários (Bootstrap 5)
- **Page Header**: Título "Cupons" + Botão "Criar Cupom"
- **Search Bar**: Busca por ID, nome do cupom (usar `input-group` com `form-control`)
- **Filters**: Filtrar por duração, válido/inválido (usar `form-select` ou `form-check`)
- **Coupons Table** (usar `table table-striped table-hover`): Tabela com colunas:
  - ID (formato monospace)
  - Nome
  - Tipo de Desconto (badge: Percentual/Valor Fixo)
  - Valor do Desconto (10% ou R$ 10,00)
  - Duração (badge: Uma vez/Repetir/Sempre)
  - Usos (times_redeemed / max_redemptions)
  - Válido (badge: Sim/Não)
  - Data de Expiração (se houver)
  - Data de Criação
  - Ações (botões: Ver, Remover)
- **Action Buttons** (usar `btn-group`):
  - "Ver" (`btn btn-sm btn-outline-primary`) → Navega para `/cupons/:id`
  - "Remover" (`btn btn-sm btn-outline-danger`) → Mostra modal de confirmação
- **Create Button**: "Criar Cupom" (`btn btn-primary`) → Navega para `/cupons/criar` ou abre modal
- **Pagination**: Paginação (usar `pagination` do Bootstrap) - se `has_more: true`
- **Empty State**: Mensagem quando não há cupons
- **Loading State**: Spinner enquanto carrega do Stripe

### 🔄 Fluxo de Dados
1. Página carrega
2. Faz `GET /v1/coupons` para obter todos os cupons do Stripe
3. Exibe em tabela Bootstrap
4. Aplica busca/filtros (client-side ou via query params)
5. Ao clicar em "Criar Cupom": Navega para `/cupons/criar` ou abre modal
6. Ao clicar em "Ver": Navega para `/cupons/:id` (página de detalhes)
7. Ao clicar em "Remover": Mostra modal de confirmação → `DELETE /v1/coupons/:id` → Recarrega

### 💡 Interações do Usuário
- Buscar cupons por ID, nome (em tempo real ou ao pressionar Enter)
- Filtrar por duração/válido
- Ordenar colunas (clicando no header)
- Clicar em "Criar Cupom" → Vai para página de criação
- Clicar em "Ver" → Vai para página de detalhes
- Remover cupom (com confirmação em modal)
- Ver paginação (se houver mais resultados)
- Exportar lista (opcional)

### ⚠️ Tratamento de Erros
- Erro ao carregar: "Erro ao carregar cupons do Stripe"
- Cupom não encontrado: 404
- Erro de conexão com Stripe: Mostrar mensagem apropriada

### 📋 Formulários Relacionados
- **Criar Cupom**: Consulte [Formulário de Criar Cupom](FORMULARIOS_BOOTSTRAP.md#13-formulário-de-criar-cupom)

---

## 26. Logs de Auditoria

### 📄 Descrição
Página administrativa para visualizar logs de auditoria do sistema (todas as ações realizadas).

### 🎯 Objetivo
- Listar logs de auditoria
- Filtrar logs
- Ver detalhes de logs
- Exportar logs

### 🔌 Rotas da API Utilizadas
- **GET `/v1/audit-logs`** - Lista logs
- **GET `/v1/audit-logs/:id`** - Detalhes de um log

### 📊 Dados Recebidos
```javascript
[
  {
    id: 1,
    user_id: 1,
    action: "create_customer",
    resource_type: "customer",
    resource_id: 1,
    ip_address: "192.168.1.1",
    user_agent: "Mozilla/5.0...",
    created_at: "2024-01-01 10:00:00"
  }
]
```

### 🧩 Componentes Necessários
- **Audit Logs Table**: Tabela com logs
- **Filters**: Filtrar por:
  - Ação
  - Usuário
  - Tipo de recurso
  - Período
- **Details Modal**: Detalhes de um log
- **Export Button**: Exportar logs

### 🔄 Fluxo de Dados
1. Página carrega
2. Faz `GET /v1/audit-logs` com filtros (query params)
3. Exibe logs
4. Ao clicar em log: `GET /v1/audit-logs/:id` para detalhes

### 💡 Interações do Usuário
- Ver logs de auditoria
- Filtrar logs
- Ver detalhes de um log
- Exportar logs
- Buscar logs

---

## 📝 Notas Finais

### Autenticação nas Views

- **Views Públicas (1-6)**: Não requerem autenticação, usam API Key do tenant
- **Views Autenticadas (7-19)**: Requerem Session ID (login de usuário)
- **Views Administrativas (20-26)**: Requerem Session ID com role `admin`

### Estados e Gerenciamento

- Use **localStorage** para salvar dados temporários (plano selecionado, customer criado, token de autenticação)
- Use **variáveis JavaScript globais** ou **objetos de estado** para dados globais (usuário autenticado, tenant)
- Implemente **loading states** em todas as requisições (usar `spinner-border` do Bootstrap)
- Implemente **error handling** consistente (usar `alert` do Bootstrap)

### Navegação

- Use **páginas HTML separadas** ou **SPA com roteamento JavaScript** (ex: Page.js, Navigo)
- Implemente **breadcrumbs** nas páginas de detalhes usando Bootstrap (`breadcrumb` component)
- Mantenha **histórico de navegação** para voltar (`history.back()` ou roteamento)

### UX/UI com Bootstrap 5

- Implemente **skeleton loaders** durante carregamento (usar `spinner-border` ou `placeholder` do Bootstrap)
- Use **Bootstrap Toasts** para feedback de ações (`toast` component)
- Implemente **confirmações** para ações destrutivas (usar Bootstrap Modals)
- Use **Bootstrap Modals** para formulários e confirmações (`modal` component)
- Implemente **validação client-side** usando Bootstrap validation (`needs-validation`, `was-validated`, `invalid-feedback`)
- Use **Bootstrap Alerts** para mensagens (`alert alert-success`, `alert alert-danger`, etc.)
- Implemente **Bootstrap Tables** para listagens (`table table-striped table-hover`)
- Use **Bootstrap Cards** para exibir informações agrupadas (`card`, `card-body`, `card-header`)
- Implemente **Bootstrap Forms** com classes apropriadas (`form-control`, `form-select`, `form-check`, etc.)
- Use **Bootstrap Badges** para status (`badge bg-success`, `badge bg-danger`, etc.)
- Implemente **Bootstrap Buttons** com estados (`btn btn-primary`, `btn btn-outline-secondary`, etc.)

### Formulários

Para detalhes completos sobre todos os formulários, campos específicos, validações e exemplos de código HTML com Bootstrap, consulte: **[Documentação de Formulários](FORMULARIOS_BOOTSTRAP.md)**

O documento de formulários inclui:
- Estrutura HTML completa com Bootstrap 5
- Todos os campos de cada formulário
- Validações HTML5 e JavaScript
- Exemplos de código JavaScript para submissão
- Tratamento de erros
- Loading states
- 20+ formulários documentados

---

**Última atualização:** Baseado nas rotas documentadas em `docs/ROTAS_API.md`

