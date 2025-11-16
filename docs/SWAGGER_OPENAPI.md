# 📚 Documentação Swagger/OpenAPI

## 🎯 Visão Geral

O sistema possui documentação interativa da API usando **Swagger/OpenAPI 3.0**. A documentação é gerada automaticamente a partir de anotações nos controllers.

## 🔗 Acessar Documentação

### Interface Swagger UI
```
GET /api-docs/ui
```

Acesse no navegador: `http://localhost:8080/api-docs/ui`

### Especificação OpenAPI (JSON)
```
GET /api-docs
```

Retorna a especificação OpenAPI 3.0 em formato JSON.

## 📝 Como Adicionar Anotações

### Exemplo Básico

```php
<?php

namespace App\Controllers;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: "1.0.3",
    title: "SaaS Payments API"
)]
class CustomerController
{
    #[OA\Post(
        path: "/v1/customers",
        summary: "Cria um novo cliente",
        tags: ["Clientes"],
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: "application/json",
                schema: new OA\Schema(
                    required: ["email"],
                    properties: [
                        "email" => new OA\Property(property: "email", type: "string", format: "email", example: "cliente@example.com"),
                        "name" => new OA\Property(property: "name", type: "string", example: "João Silva"),
                        "phone" => new OA\Property(property: "phone", type: "string", example: "+5511999999999")
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Cliente criado com sucesso",
                content: new OA\MediaType(
                    mediaType: "application/json",
                    schema: new OA\Schema(
                        properties: [
                            "success" => new OA\Property(property: "success", type: "boolean", example: true),
                            "data" => new OA\Property(
                                property: "data",
                                type: "object",
                                properties: [
                                    "id" => new OA\Property(property: "id", type: "integer", example: 1),
                                    "stripe_id" => new OA\Property(property: "stripe_id", type: "string", example: "cus_xxx"),
                                    "email" => new OA\Property(property: "email", type: "string", example: "cliente@example.com")
                                ]
                            )
                        ]
                    )
                )
            ),
            new OA\Response(response: 400, description: "Dados inválidos"),
            new OA\Response(response: 401, description: "Não autenticado")
        ]
    )]
    public function create(): void
    {
        // ...
    }
}
```

### Exemplo com GET

```php
#[OA\Get(
    path: "/v1/customers/{id}",
    summary: "Obtém cliente específico",
    tags: ["Clientes"],
    security: [["bearerAuth" => []]],
    parameters: [
        new OA\Parameter(
            name: "id",
            in: "path",
            required: true,
            description: "ID do cliente",
            schema: new OA\Schema(type: "integer", example: 1)
        )
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: "Cliente encontrado",
            content: new OA\MediaType(
                mediaType: "application/json",
                schema: new OA\Schema(ref: "#/components/schemas/Customer")
            )
        ),
        new OA\Response(response: 404, description: "Cliente não encontrado")
    ]
)]
public function get(string $id): void
{
    // ...
}
```

### Definir Schemas Reutilizáveis

Crie um arquivo separado para schemas:

```php
<?php

namespace App\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: "Customer",
    title: "Customer",
    description: "Modelo de Cliente",
    required: ["id", "email"],
    properties: [
        new OA\Property(property: "id", type: "integer", example: 1),
        new OA\Property(property: "stripe_id", type: "string", example: "cus_xxx"),
        new OA\Property(property: "email", type: "string", format: "email", example: "cliente@example.com"),
        new OA\Property(property: "name", type: "string", nullable: true, example: "João Silva"),
        new OA\Property(property: "created_at", type: "string", format: "date-time", example: "2025-01-16T10:00:00Z")
    ]
)]
class CustomerSchema {}
```

## 🔧 Configuração

### Variáveis de Ambiente

Adicione no `.env`:

```env
APP_URL=http://localhost:8080
```

### Gerar Especificação Estática

Para gerar um arquivo JSON estático:

```bash
vendor/bin/openapi App/Controllers -o public/openapi.json
```

## 📚 Recursos

- [Documentação Swagger PHP](https://zircote.github.io/swagger-php/)
- [OpenAPI Specification](https://swagger.io/specification/)
- [Swagger UI](https://swagger.io/tools/swagger-ui/)

## 🎨 Personalização

Para personalizar a interface Swagger UI, edite o método `getUI()` em `App/Controllers/SwaggerController.php`.

## ✅ Status

- ✅ Biblioteca instalada (`zircote/swagger-php`)
- ✅ Controller criado (`SwaggerController`)
- ✅ Rotas configuradas (`/api-docs`, `/api-docs/ui`)
- ⚠️ Anotações nos controllers (em progresso)

---

**Última Atualização:** 2025-01-16

