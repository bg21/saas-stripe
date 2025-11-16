<?php

namespace App\Controllers;

use OpenApi\Generator;
use Flight;
use Config;

/**
 * Controller para servir documentação Swagger/OpenAPI
 */
class SwaggerController
{
    /**
     * Gera e retorna a especificação OpenAPI 3.0
     * GET /api-docs
     */
    public function getSpec(): void
    {
        try {
            // Define o diretório onde estão os controllers com anotações
            $openapi = Generator::scan([
                __DIR__ . '/../Controllers',
                __DIR__ . '/../Models'
            ]);

            // Define informações da API
            $spec = $openapi->toJson();
            $specArray = json_decode($spec, true);

            // Se não houver anotações, cria especificação básica
            if (empty($specArray) || !isset($specArray['openapi'])) {
                $specArray = $this->getBasicSpec();
            } else {
                // Atualiza informações básicas se já existir
                $specArray['info'] = [
                    'title' => 'SaaS Payments API',
                    'version' => '1.0.3',
                    'description' => 'Sistema base reutilizável para gerenciar pagamentos, assinaturas e clientes via Stripe',
                    'contact' => [
                        'name' => 'API Support'
                    ]
                ];

                // Adiciona servidores
                $baseUrl = Config::get('APP_URL', 'http://localhost:8080');
                $specArray['servers'] = [
                    [
                        'url' => $baseUrl,
                        'description' => 'Servidor de desenvolvimento'
                    ]
                ];

                // Adiciona esquema de autenticação se não existir
                if (!isset($specArray['components'])) {
                    $specArray['components'] = [];
                }
                if (!isset($specArray['components']['securitySchemes'])) {
                    $specArray['components']['securitySchemes'] = [];
                }
                $specArray['components']['securitySchemes']['bearerAuth'] = [
                    'type' => 'http',
                    'scheme' => 'bearer',
                    'bearerFormat' => 'JWT/Token',
                    'description' => 'Autenticação via Bearer Token (API Key ou Session ID)'
                ];

                // Adiciona segurança global (pode ser sobrescrita por endpoint)
                if (!isset($specArray['security'])) {
                    $specArray['security'] = [
                        ['bearerAuth' => []]
                    ];
                }
            }

            Flight::json($specArray);
        } catch (\Exception $e) {
            // Em caso de erro, retorna especificação básica
            $specArray = $this->getBasicSpec();
            Flight::json($specArray);
        }
    }

    /**
     * Retorna especificação OpenAPI básica
     */
    private function getBasicSpec(): array
    {
        $baseUrl = Config::get('APP_URL', 'http://localhost:8080');
        
        return [
            'openapi' => '3.0.0',
            'info' => [
                'title' => 'SaaS Payments API',
                'version' => '1.0.3',
                'description' => 'Sistema base reutilizável para gerenciar pagamentos, assinaturas e clientes via Stripe',
                'contact' => [
                    'name' => 'API Support'
                ]
            ],
            'servers' => [
                [
                    'url' => $baseUrl,
                    'description' => 'Servidor de desenvolvimento'
                ]
            ],
            'components' => [
                'securitySchemes' => [
                    'bearerAuth' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                        'bearerFormat' => 'JWT/Token',
                        'description' => 'Autenticação via Bearer Token (API Key ou Session ID)'
                    ]
                ]
            ],
            'security' => [
                ['bearerAuth' => []]
            ],
            'paths' => [
                '/health' => [
                    'get' => [
                        'summary' => 'Health Check básico',
                        'description' => 'Retorna status básico da API',
                        'tags' => ['Health'],
                        'security' => [],
                        'responses' => [
                            '200' => [
                                'description' => 'API está funcionando',
                                'content' => [
                                    'application/json' => [
                                        'schema' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'status' => ['type' => 'string', 'example' => 'ok'],
                                                'timestamp' => ['type' => 'string', 'example' => '2025-01-16T10:00:00Z']
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ],
                '/health/detailed' => [
                    'get' => [
                        'summary' => 'Health Check detalhado',
                        'description' => 'Retorna status detalhado de todas as dependências',
                        'tags' => ['Health'],
                        'security' => [],
                        'responses' => [
                            '200' => [
                                'description' => 'Status detalhado',
                                'content' => [
                                    'application/json' => [
                                        'schema' => [
                                            'type' => 'object'
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            'tags' => [
                ['name' => 'Health', 'description' => 'Health Check'],
                ['name' => 'Clientes', 'description' => 'Gerenciamento de clientes'],
                ['name' => 'Assinaturas', 'description' => 'Gerenciamento de assinaturas'],
                ['name' => 'Checkout', 'description' => 'Sessões de checkout'],
                ['name' => 'Webhooks', 'description' => 'Webhooks do Stripe']
            ]
        ];
    }

    /**
     * Retorna a interface Swagger UI
     * GET /api-docs/ui
     */
    public function getUI(): void
    {
        $apiDocsUrl = '/api-docs';
        $html = <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SaaS Payments API - Documentação</title>
    <link rel="stylesheet" type="text/css" href="https://unpkg.com/swagger-ui-dist@5.9.0/swagger-ui.css" />
    <style>
        html {
            box-sizing: border-box;
            overflow: -moz-scrollbars-vertical;
            overflow-y: scroll;
        }
        *, *:before, *:after {
            box-sizing: inherit;
        }
        body {
            margin:0;
            background: #fafafa;
        }
    </style>
</head>
<body>
    <div id="swagger-ui"></div>
    <script src="https://unpkg.com/swagger-ui-dist@5.9.0/swagger-ui-bundle.js"></script>
    <script src="https://unpkg.com/swagger-ui-dist@5.9.0/swagger-ui-standalone-preset.js"></script>
    <script>
        window.onload = function() {
            const ui = SwaggerUIBundle({
                url: '{$apiDocsUrl}',
                dom_id: '#swagger-ui',
                deepLinking: true,
                presets: [
                    SwaggerUIBundle.presets.apis,
                    SwaggerUIStandalonePreset
                ],
                plugins: [
                    SwaggerUIBundle.plugins.DownloadUrl
                ],
                layout: "StandaloneLayout",
                validatorUrl: null,
                tryItOutEnabled: true,
                supportedSubmitMethods: ['get', 'post', 'put', 'delete', 'patch'],
                oauth2RedirectUrl: window.location.origin + '/swagger-ui/oauth2-redirect.html',
                onComplete: function() {
                    console.log("Swagger UI carregado");
                }
            });
        };
    </script>
</body>
</html>
HTML;

        header('Content-Type: text/html; charset=utf-8');
        echo $html;
        exit;
    }
}

