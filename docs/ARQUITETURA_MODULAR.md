# 🏗️ Arquitetura Modular para Sistema SaaS

**Data:** 2025-01-22  
**Objetivo:** Separar funcionalidades específicas (como clínica veterinária) do sistema base, permitindo reutilização em outros contextos.

---

## 📋 PROBLEMA ATUAL

Atualmente, o código da clínica veterinária está **misturado** com o sistema base:

- ✅ **Sistema Base (Core):** Pagamentos, Stripe, Tenants, Usuários, Permissões
- ⚠️ **Clínica Veterinária:** Misturada no mesmo diretório (Controllers, Models, Views)

**Problema:** Se você quiser usar o sistema base para outro SaaS (ex: e-commerce, gestão de imóveis), terá código de clínica veterinária desnecessário.

---

## 🎯 SOLUÇÃO PROPOSTA: ARQUITETURA MODULAR

### Estrutura Proposta

```
saas-stripe/
├── App/
│   ├── Core/                    # ← Sistema base (sempre carregado)
│   │   ├── Controllers/
│   │   │   ├── CustomerController.php
│   │   │   ├── SubscriptionController.php
│   │   │   ├── AuthController.php
│   │   │   └── ...
│   │   ├── Models/
│   │   │   ├── Customer.php
│   │   │   ├── Subscription.php
│   │   │   ├── Tenant.php
│   │   │   └── ...
│   │   ├── Services/
│   │   │   ├── PaymentService.php
│   │   │   ├── StripeService.php
│   │   │   └── ...
│   │   └── Views/
│   │       ├── customers.php
│   │       ├── subscriptions.php
│   │       └── ...
│   │
│   └── Modules/                  # ← Módulos opcionais
│       ├── VeterinaryClinic/    # ← Módulo de Clínica Veterinária
│       │   ├── Controllers/
│       │   │   ├── AppointmentController.php
│       │   │   ├── ProfessionalController.php
│       │   │   └── ...
│       │   ├── Models/
│       │   │   ├── Appointment.php
│       │   │   ├── Professional.php
│       │   │   └── ...
│       │   ├── Services/
│       │   │   ├── AppointmentService.php
│       │   │   └── ScheduleService.php
│       │   ├── Views/
│       │   │   ├── appointments.php
│       │   │   └── ...
│       │   ├── Routes.php       # ← Rotas do módulo
│       │   ├── Permissions.php  # ← Permissões do módulo
│       │   └── Module.php       # ← Classe de inicialização
│       │
│       └── ECommerce/           # ← Futuro módulo de E-commerce (exemplo)
│           └── ...
│
├── db/
│   └── migrations/
│       ├── core/                # ← Migrations do sistema base
│       └── modules/
│           └── veterinary_clinic/  # ← Migrations do módulo
│
└── config/
    └── modules.php              # ← Configuração de módulos ativos
```

---

## 🔧 IMPLEMENTAÇÃO

### 1. Sistema de Registro de Módulos

Criar um sistema que permite ativar/desativar módulos via configuração:

**`config/modules.php`**
```php
<?php

return [
    'enabled' => [
        'veterinary_clinic' => true,  // Ativa/desativa módulo
        // 'ecommerce' => false,
    ],
    'paths' => [
        'veterinary_clinic' => __DIR__ . '/../App/Modules/VeterinaryClinic',
    ]
];
```

### 2. Classe Base de Módulo

**`App/Modules/BaseModule.php`**
```php
<?php

namespace App\Modules;

abstract class BaseModule
{
    abstract public function getName(): string;
    abstract public function getVersion(): string;
    abstract public function registerRoutes(\flight\Engine $app): void;
    abstract public function registerPermissions(): array;
    abstract public function getMigrationsPath(): ?string;
}
```

### 3. Módulo de Clínica Veterinária

**`App/Modules/VeterinaryClinic/Module.php`**
```php
<?php

namespace App\Modules\VeterinaryClinic;

use App\Modules\BaseModule;
use flight\Engine;

class Module extends BaseModule
{
    public function getName(): string
    {
        return 'Veterinary Clinic';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function registerRoutes(Engine $app): void
    {
        // Carrega rotas do módulo
        require __DIR__ . '/Routes.php';
    }

    public function registerPermissions(): array
    {
        return require __DIR__ . '/Permissions.php';
    }

    public function getMigrationsPath(): ?string
    {
        return __DIR__ . '/../../db/migrations/modules/veterinary_clinic';
    }
}
```

### 4. Carregamento de Módulos no `index.php`

**`public/index.php` (modificado)**
```php
// ... código do sistema base ...

// Carrega módulos ativos
$moduleManager = new \App\Core\ModuleManager();
$moduleManager->loadModules($app);
```

**`App/Core/ModuleManager.php`**
```php
<?php

namespace App\Core;

use flight\Engine;
use App\Modules\BaseModule;

class ModuleManager
{
    public function loadModules(Engine $app): void
    {
        $modules = require __DIR__ . '/../../config/modules.php';
        
        foreach ($modules['enabled'] as $moduleName => $enabled) {
            if (!$enabled) {
                continue;
            }
            
            $modulePath = $modules['paths'][$moduleName] ?? null;
            if (!$modulePath || !file_exists($modulePath . '/Module.php')) {
                continue;
            }
            
            require_once $modulePath . '/Module.php';
            $moduleClass = "App\\Modules\\" . $this->getModuleClassName($moduleName) . "\\Module";
            
            if (class_exists($moduleClass)) {
                $module = new $moduleClass();
                $module->registerRoutes($app);
                // Registra permissões, etc.
            }
        }
    }
    
    private function getModuleClassName(string $moduleName): string
    {
        // Converte 'veterinary_clinic' para 'VeterinaryClinic'
        return str_replace('_', '', ucwords($moduleName, '_'));
    }
}
```

---

## 📦 VANTAGENS DESTA ARQUITETURA

### ✅ Separação Clara
- Sistema base fica **100% limpo** e reutilizável
- Módulos são **opcionais** e isolados
- Fácil adicionar novos módulos

### ✅ Flexibilidade
- Ativa/desativa módulos via configuração
- Cada módulo pode ter suas próprias migrations
- Cada módulo pode ter suas próprias permissões

### ✅ Manutenibilidade
- Código organizado por funcionalidade
- Fácil identificar o que pertence a cada módulo
- Testes podem ser organizados por módulo

### ✅ Escalabilidade
- Pode criar módulos para:
  - E-commerce
  - Gestão de imóveis
  - CRM
  - etc.

---

## 🔄 MIGRAÇÃO DA ESTRUTURA ATUAL

### Passo 1: Criar estrutura de módulos
```
App/Modules/VeterinaryClinic/
├── Controllers/
├── Models/
├── Services/
├── Views/
├── Routes.php
├── Permissions.php
└── Module.php
```

### Passo 2: Mover arquivos da clínica
- Mover controllers da clínica para `App/Modules/VeterinaryClinic/Controllers/`
- Mover models da clínica para `App/Modules/VeterinaryClinic/Models/`
- Mover services da clínica para `App/Modules/VeterinaryClinic/Services/`
- Mover views da clínica para `App/Modules/VeterinaryClinic/Views/`

### Passo 3: Criar `Routes.php` do módulo
Extrair todas as rotas da clínica do `index.php` para `App/Modules/VeterinaryClinic/Routes.php`

### Passo 4: Criar `Permissions.php` do módulo
Extrair permissões da clínica para `App/Modules/VeterinaryClinic/Permissions.php`

### Passo 5: Atualizar `index.php`
- Remover código específico da clínica
- Adicionar carregamento de módulos

### Passo 6: Atualizar namespaces
- Atualizar namespaces dos arquivos movidos
- Atualizar imports nos testes

---

## 🎯 RESULTADO FINAL

### Sistema Base (Core)
- ✅ Apenas funcionalidades essenciais: Stripe, Tenants, Usuários, Permissões
- ✅ **100% reutilizável** para qualquer tipo de SaaS
- ✅ Sem dependências de módulos específicos

### Módulo de Clínica Veterinária
- ✅ Totalmente isolado
- ✅ Pode ser ativado/desativado via configuração
- ✅ Não interfere no sistema base

### Outros Módulos Futuros
- ✅ E-commerce: `App/Modules/ECommerce/`
- ✅ Gestão de Imóveis: `App/Modules/PropertyManagement/`
- ✅ CRM: `App/Modules/CRM/`
- ✅ etc.

---

## 📝 CONFIGURAÇÃO DE MÓDULOS

**`config/modules.php`**
```php
<?php

return [
    'enabled' => [
        'veterinary_clinic' => true,   // Clínica veterinária ativa
        // 'ecommerce' => false,       // E-commerce desativado
    ],
];
```

**Para usar em outro SaaS:**
```php
'enabled' => [
    'veterinary_clinic' => false,  // Desativa clínica
    // Adiciona outros módulos conforme necessário
],
```

---

## 🚀 PRÓXIMOS PASSOS

1. **Criar estrutura de módulos** (pasta `App/Modules/`)
2. **Criar `ModuleManager`** para carregar módulos
3. **Mover código da clínica** para módulo
4. **Atualizar `index.php`** para usar sistema de módulos
5. **Atualizar namespaces** e imports
6. **Testar** que tudo funciona
7. **Documentar** como criar novos módulos

---

## 💡 EXEMPLO DE USO

### Para um SaaS de E-commerce:
```php
// config/modules.php
'enabled' => [
    'veterinary_clinic' => false,  // Não precisa
    'ecommerce' => true,            // Ativa e-commerce
],
```

### Para um SaaS de Gestão de Imóveis:
```php
'enabled' => [
    'veterinary_clinic' => false,
    'property_management' => true,
],
```

### Para um SaaS que usa vários módulos:
```php
'enabled' => [
    'veterinary_clinic' => true,
    'ecommerce' => true,
    'crm' => true,
],
```

---

**Esta arquitetura permite que o sistema base seja verdadeiramente reutilizável!** 🎉

