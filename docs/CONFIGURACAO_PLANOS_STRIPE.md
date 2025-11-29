# 💳 Configuração de Planos e Preços no Stripe

**Data:** 2025-01-22  
**Versão:** 1.0  
**Status:** Guia de Configuração

---

## 📋 Índice

1. [Visão Geral dos Planos](#visão-geral-dos-planos)
2. [Sugestões de Preços](#sugestões-de-preços)
3. [Configuração no Stripe](#configuração-no-stripe)
4. [Atualização do Código](#atualização-do-código)
5. [Testes e Validação](#testes-e-validação)
6. [Boas Práticas](#boas-práticas)

---

## 🎯 Visão Geral dos Planos

O sistema SaaS oferece **3 planos** para clínicas veterinárias:

| Plano | Profissionais | Agendamentos/Mês | Usuários | Recursos |
|-------|---------------|------------------|----------|----------|
| **Básico** | Até 3 | Até 100 | 1 | Básicos |
| **Profissional** | Até 10 | Ilimitado | Até 5 | Relatórios Avançados |
| **Premium** | Ilimitado | Ilimitado | Ilimitado | Todos os Recursos |

---

## 💰 Sugestões de Preços

### Análise de Mercado

Baseado em sistemas SaaS similares para clínicas veterinárias, seguem sugestões de preços:

### Opção 1: Preços Conservadores (Recomendado para Início)

| Plano | Mensal (BRL) | Anual (BRL) | Desconto Anual |
|-------|--------------|-------------|----------------|
| **Básico** | R$ 97,00 | R$ 970,00 | 16,7% (2 meses grátis) |
| **Profissional** | R$ 197,00 | R$ 1.970,00 | 16,7% (2 meses grátis) |
| **Premium** | R$ 397,00 | R$ 3.970,00 | 16,7% (2 meses grátis) |

**Justificativa:**
- Preços acessíveis para atrair clínicas pequenas
- Margem de desconto anual atrativa
- Escalonamento claro entre planos

### Opção 2: Preços Intermediários

| Plano | Mensal (BRL) | Anual (BRL) | Desconto Anual |
|-------|--------------|-------------|----------------|
| **Básico** | R$ 147,00 | R$ 1.470,00 | 16,7% (2 meses grátis) |
| **Profissional** | R$ 297,00 | R$ 2.970,00 | 16,7% (2 meses grátis) |
| **Premium** | R$ 597,00 | R$ 5.970,00 | 16,7% (2 meses grátis) |

**Justificativa:**
- Posicionamento médio no mercado
- Maior margem de lucro
- Ainda acessível para clínicas estabelecidas

### Opção 3: Preços Premium

| Plano | Mensal (BRL) | Anual (BRL) | Desconto Anual |
|-------|--------------|-------------|----------------|
| **Básico** | R$ 197,00 | R$ 1.970,00 | 16,7% (2 meses grátis) |
| **Profissional** | R$ 397,00 | R$ 3.970,00 | 16,7% (2 meses grátis) |
| **Premium** | R$ 797,00 | R$ 7.970,00 | 16,7% (2 meses grátis) |

**Justificativa:**
- Posicionamento premium
- Maior margem de lucro
- Foco em clínicas grandes e redes

### 💡 Recomendação

**Para início, recomendo a Opção 1 (Preços Conservadores)** porque:
- Facilita a aquisição de primeiros clientes
- Permite ajustar preços depois (com aviso prévio)
- Cria base de usuários para feedback
- Margem ainda é saudável considerando custos de infraestrutura

---

## 🔧 Configuração no Stripe

### Passo 1: Acessar o Dashboard do Stripe

1. Acesse: https://dashboard.stripe.com
2. Faça login na sua conta
3. Certifique-se de estar no **modo de produção** (ou teste, se ainda estiver validando)

### Passo 2: Criar Produtos

Para cada plano, crie um **Produto** no Stripe:

#### 2.1. Produto "Plano Básico"

1. Vá em **Produtos** → **Adicionar produto**
2. Preencha:
   - **Nome:** `Plano Básico - Clínica Veterinária`
   - **Descrição:** `Ideal para clínicas pequenas. Até 3 profissionais, 100 agendamentos/mês e 1 usuário.`
   - **Imagem:** (opcional) Logo do seu SaaS
   - **Metadata:**
     ```json
     {
       "plan_type": "basic",
       "max_professionals": "3",
       "max_appointments_per_month": "100",
       "max_users": "1",
       "features": "basic"
     }
     ```
3. Clique em **Salvar**

#### 2.2. Produto "Plano Profissional"

1. Vá em **Produtos** → **Adicionar produto**
2. Preencha:
   - **Nome:** `Plano Profissional - Clínica Veterinária`
   - **Descrição:** `Para clínicas de médio porte. Até 10 profissionais, agendamentos ilimitados e 5 usuários. Inclui relatórios avançados e histórico completo.`
   - **Imagem:** (opcional) Logo do seu SaaS
   - **Metadata:**
     ```json
     {
       "plan_type": "professional",
       "max_professionals": "10",
       "max_appointments_per_month": "unlimited",
       "max_users": "5",
       "features": "basic,advanced_reports,history"
     }
     ```
3. Clique em **Salvar**

#### 2.3. Produto "Plano Premium"

1. Vá em **Produtos** → **Adicionar produto**
2. Preencha:
   - **Nome:** `Plano Premium - Clínica Veterinária`
   - **Descrição:** `Para clínicas grandes e redes. Recursos ilimitados, todos os recursos do sistema e suporte prioritário.`
   - **Imagem:** (opcional) Logo do seu SaaS
   - **Metadata:**
     ```json
     {
       "plan_type": "premium",
       "max_professionals": "unlimited",
       "max_appointments_per_month": "unlimited",
       "max_users": "unlimited",
       "features": "all"
     }
     ```
3. Clique em **Salvar**

### Passo 3: Criar Preços (Prices)

Para cada produto, crie **2 preços**: um mensal e um anual.

#### 3.1. Preços do Plano Básico

**Preço Mensal:**
1. No produto "Plano Básico", clique em **Adicionar preço**
2. Configure:
   - **Modelo de preço:** `Padrão`
   - **Preço:** `R$ 97,00` (ou valor escolhido)
   - **Cobrança:** `Recorrente`
   - **Intervalo:** `Mensal`
   - **Apelido:** `Plano Básico - Mensal`
3. Clique em **Adicionar preço**
4. **Copie o `price_id`** (ex: `price_1ABC123...`) - você precisará dele!

**Preço Anual:**
1. No mesmo produto, clique em **Adicionar preço**
2. Configure:
   - **Modelo de preço:** `Padrão`
   - **Preço:** `R$ 970,00` (ou valor escolhido)
   - **Cobrança:** `Recorrente`
   - **Intervalo:** `Anual`
   - **Apelido:** `Plano Básico - Anual`
3. Clique em **Adicionar preço**
4. **Copie o `price_id`** do preço anual

#### 3.2. Preços do Plano Profissional

Repita o processo acima para o Plano Profissional:
- **Mensal:** R$ 197,00
- **Anual:** R$ 1.970,00

#### 3.3. Preços do Plano Premium

Repita o processo acima para o Plano Premium:
- **Mensal:** R$ 397,00
- **Anual:** R$ 3.970,00

### Passo 4: Organizar os Price IDs

Crie uma tabela com todos os `price_id` obtidos:

| Plano | Tipo | Price ID | Valor |
|-------|------|----------|-------|
| Básico | Mensal | `price_xxxxx` | R$ 97,00 |
| Básico | Anual | `price_xxxxx` | R$ 970,00 |
| Profissional | Mensal | `price_xxxxx` | R$ 197,00 |
| Profissional | Anual | `price_xxxxx` | R$ 1.970,00 |
| Premium | Mensal | `price_xxxxx` | R$ 397,00 |
| Premium | Anual | `price_xxxxx` | R$ 3.970,00 |

**⚠️ IMPORTANTE:** Guarde estes `price_id` com segurança! Você precisará deles no próximo passo.

---

## 💻 Atualização do Código

### Passo 1: Atualizar PlanLimitsService

Abra o arquivo: `App/Services/PlanLimitsService.php`

Localize o método `getPlanLimits()` (linha ~24) e atualize com os `price_id` reais do Stripe:

```php
private function getPlanLimits(string $priceId): array
{
    // Mapeamento de price_id do Stripe para limites
    // ATUALIZAR: Substituir pelos price_id reais do Stripe
    $planLimits = [
        // Plano Básico - Mensal
        'price_1ABC123BASICMONTHLY' => [
            'max_professionals' => 3,
            'max_appointments_per_month' => 100,
            'max_users' => 1,
            'features' => ['basic'],
            'plan_name' => 'Plano Básico',
            'billing_interval' => 'month'
        ],
        
        // Plano Básico - Anual
        'price_1ABC123BASICYEARLY' => [
            'max_professionals' => 3,
            'max_appointments_per_month' => 100,
            'max_users' => 1,
            'features' => ['basic'],
            'plan_name' => 'Plano Básico',
            'billing_interval' => 'year'
        ],
        
        // Plano Profissional - Mensal
        'price_1ABC123PROFMONTHLY' => [
            'max_professionals' => 10,
            'max_appointments_per_month' => null, // ilimitado
            'max_users' => 5,
            'features' => ['basic', 'advanced_reports', 'history'],
            'plan_name' => 'Plano Profissional',
            'billing_interval' => 'month'
        ],
        
        // Plano Profissional - Anual
        'price_1ABC123PROFYEARLY' => [
            'max_professionals' => 10,
            'max_appointments_per_month' => null, // ilimitado
            'max_users' => 5,
            'features' => ['basic', 'advanced_reports', 'history'],
            'plan_name' => 'Plano Profissional',
            'billing_interval' => 'year'
        ],
        
        // Plano Premium - Mensal
        'price_1ABC123PREMMONTHLY' => [
            'max_professionals' => null, // ilimitado
            'max_appointments_per_month' => null, // ilimitado
            'max_users' => null, // ilimitado
            'features' => ['all'],
            'plan_name' => 'Plano Premium',
            'billing_interval' => 'month'
        ],
        
        // Plano Premium - Anual
        'price_1ABC123PREMYEARLY' => [
            'max_professionals' => null, // ilimitado
            'max_appointments_per_month' => null, // ilimitado
            'max_users' => null, // ilimitado
            'features' => ['all'],
            'plan_name' => 'Plano Premium',
            'billing_interval' => 'year'
        ]
    ];
    
    return $planLimits[$priceId] ?? [
        'max_professionals' => null,
        'max_appointments_per_month' => null,
        'max_users' => null,
        'features' => [],
        'plan_name' => 'Plano Desconhecido',
        'billing_interval' => 'month'
    ];
}
```

**⚠️ IMPORTANTE:**
- Substitua `price_1ABC123...` pelos `price_id` reais obtidos do Stripe
- Mantenha a estrutura de arrays idêntica
- Adicione os campos `plan_name` e `billing_interval` para facilitar identificação

### Passo 2: Criar Arquivo de Configuração (Opcional mas Recomendado)

Para facilitar a manutenção, você pode criar um arquivo de configuração separado:

**Criar:** `config/plans.php`

```php
<?php

/**
 * Configuração de Planos e Preços do Stripe
 * 
 * ATUALIZAR: Substituir pelos price_id reais do Stripe
 */

return [
    'basic' => [
        'monthly' => [
            'price_id' => 'price_1ABC123BASICMONTHLY', // ATUALIZAR
            'amount' => 9700, // R$ 97,00 em centavos
            'currency' => 'brl',
            'limits' => [
                'max_professionals' => 3,
                'max_appointments_per_month' => 100,
                'max_users' => 1,
                'features' => ['basic']
            ]
        ],
        'yearly' => [
            'price_id' => 'price_1ABC123BASICYEARLY', // ATUALIZAR
            'amount' => 97000, // R$ 970,00 em centavos
            'currency' => 'brl',
            'limits' => [
                'max_professionals' => 3,
                'max_appointments_per_month' => 100,
                'max_users' => 1,
                'features' => ['basic']
            ]
        ]
    ],
    'professional' => [
        'monthly' => [
            'price_id' => 'price_1ABC123PROFMONTHLY', // ATUALIZAR
            'amount' => 19700, // R$ 197,00 em centavos
            'currency' => 'brl',
            'limits' => [
                'max_professionals' => 10,
                'max_appointments_per_month' => null,
                'max_users' => 5,
                'features' => ['basic', 'advanced_reports', 'history']
            ]
        ],
        'yearly' => [
            'price_id' => 'price_1ABC123PROFYEARLY', // ATUALIZAR
            'amount' => 197000, // R$ 1.970,00 em centavos
            'currency' => 'brl',
            'limits' => [
                'max_professionals' => 10,
                'max_appointments_per_month' => null,
                'max_users' => 5,
                'features' => ['basic', 'advanced_reports', 'history']
            ]
        ]
    ],
    'premium' => [
        'monthly' => [
            'price_id' => 'price_1ABC123PREMMONTHLY', // ATUALIZAR
            'amount' => 39700, // R$ 397,00 em centavos
            'currency' => 'brl',
            'limits' => [
                'max_professionals' => null,
                'max_appointments_per_month' => null,
                'max_users' => null,
                'features' => ['all']
            ]
        ],
        'yearly' => [
            'price_id' => 'price_1ABC123PREMYEARLY', // ATUALIZAR
            'amount' => 397000, // R$ 3.970,00 em centavos
            'currency' => 'brl',
            'limits' => [
                'max_professionals' => null,
                'max_appointments_per_month' => null,
                'max_users' => null,
                'features' => ['all']
            ]
        ]
    ]
];
```

E então atualizar o `PlanLimitsService` para usar este arquivo:

```php
private function getPlanLimits(string $priceId): array
{
    $plans = require __DIR__ . '/../../config/plans.php';
    
    // Busca o price_id em todos os planos
    foreach ($plans as $planType => $intervals) {
        foreach ($intervals as $interval => $config) {
            if ($config['price_id'] === $priceId) {
                return array_merge($config['limits'], [
                    'plan_name' => ucfirst($planType),
                    'billing_interval' => $interval,
                    'amount' => $config['amount'],
                    'currency' => $config['currency']
                ]);
            }
        }
    }
    
    // Retorna padrão se não encontrar
    return [
        'max_professionals' => null,
        'max_appointments_per_month' => null,
        'max_users' => null,
        'features' => [],
        'plan_name' => 'Plano Desconhecido',
        'billing_interval' => 'month'
    ];
}
```

---

## 🧪 Testes e Validação

### Teste 1: Verificar Mapeamento de Planos

Crie um script de teste: `tests/Manual/test_plan_limits.php`

```php
<?php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/config.php';

use App\Services\PlanLimitsService;

$service = new PlanLimitsService();

// Teste com price_id do Plano Básico Mensal
$priceId = 'price_1ABC123BASICMONTHLY'; // Substitua pelo price_id real
$limits = $service->getAllLimits(1); // Use um tenant_id de teste

echo "Limites do Plano:\n";
print_r($limits);
```

### Teste 2: Criar Assinatura de Teste

1. Use o Stripe Test Mode
2. Crie uma assinatura de teste com um dos `price_id` configurados
3. Verifique se os limites são aplicados corretamente

### Teste 3: Validar Limites nos Controllers

1. Crie um tenant de teste
2. Associe uma assinatura com o Plano Básico
3. Tente criar:
   - 4 profissionais (deve falhar - limite é 3)
   - 101 agendamentos no mês (deve falhar - limite é 100)
   - 2 usuários (deve falhar - limite é 1)

---

## ✅ Checklist de Configuração

- [ ] Criar 3 produtos no Stripe (Básico, Profissional, Premium)
- [ ] Criar 6 preços no Stripe (2 por produto: mensal e anual)
- [ ] Copiar todos os `price_id` e guardar com segurança
- [ ] Atualizar `PlanLimitsService.php` com os `price_id` reais
- [ ] (Opcional) Criar arquivo `config/plans.php`
- [ ] Testar mapeamento de planos
- [ ] Testar criação de assinatura
- [ ] Validar limites nos controllers
- [ ] Documentar `price_id` em local seguro (ex: `.env.example` ou documentação interna)

---

## 📚 Boas Práticas

### 1. Versionamento de Preços

Quando precisar alterar preços:
- **NÃO** edite o preço existente no Stripe
- **Crie um novo preço** e desative o antigo
- Atualize o código com o novo `price_id`
- Clientes existentes continuam com o preço antigo (Stripe mantém)

### 2. Ambiente de Teste vs Produção

- Use **Stripe Test Mode** para desenvolvimento
- Configure `price_id` diferentes para teste e produção
- Use variáveis de ambiente para alternar entre ambientes

### 3. Documentação

- Mantenha um arquivo com todos os `price_id` atualizados
- Documente mudanças de preços e motivos
- Mantenha histórico de alterações

### 4. Monitoramento

- Monitore conversões por plano
- Acompanhe upgrades/downgrades
- Analise churn por plano
- Use os relatórios do sistema (`/v1/reports/*`)

### 5. Comunicação com Clientes

- Avise com **30 dias de antecedência** sobre mudanças de preço
- Ofereça período de transição
- Mantenha preços antigos para clientes existentes (grandfathering)

---

## 🔗 Recursos Adicionais

- [Documentação Stripe - Produtos e Preços](https://stripe.com/docs/products-prices/overview)
- [Stripe Dashboard](https://dashboard.stripe.com)
- [API de Relatórios do Sistema](../App/Controllers/ReportController.php)
- [PlanLimitsService](../App/Services/PlanLimitsService.php)

---

## 📞 Suporte

Em caso de dúvidas sobre configuração:
1. Consulte a documentação do Stripe
2. Verifique os logs do sistema (`storage/logs/`)
3. Teste em ambiente de desenvolvimento primeiro

---

**Última atualização:** 2025-01-22  
**Versão do documento:** 1.0

