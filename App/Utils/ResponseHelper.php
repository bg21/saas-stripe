<?php

namespace App\Utils;

use Flight;
use Config;
use App\Services\Logger;

/**
 * Helper para padronizar respostas da API
 * Centraliza tratamento de erros e sucessos
 */
class ResponseHelper
{
    /**
     * Envia resposta de erro padronizada
     * 
     * @param int $statusCode Código HTTP (400, 401, 404, 500, etc.)
     * @param string $error Tipo do erro (ex: 'Dados inválidos', 'Não autenticado')
     * @param string $message Mensagem amigável para o usuário
     * @param string|null $errorCode Código de erro interno (opcional)
     * @param array $errors Array de erros de validação (opcional)
     * @param array $context Contexto adicional para log (opcional)
     */
    public static function sendError(
        int $statusCode,
        string $error,
        string $message,
        ?string $errorCode = null,
        array $errors = [],
        array $context = []
    ): void {
        // Log do erro (sanitizado)
        if (!empty($context)) {
            Logger::error($message, ErrorHandler::sanitizeContext($context));
        } else {
            Logger::error($message, [
                'error_type' => $error,
                'error_code' => $errorCode,
                'status_code' => $statusCode
            ]);
        }
        
        // Prepara resposta
        $response = [
            'error' => $error,
            'message' => $message
        ];
        
        // Adiciona código de erro se fornecido
        if ($errorCode !== null) {
            $response['code'] = $errorCode;
        }
        
        // Adiciona erros de validação se houver
        if (!empty($errors)) {
            $response['errors'] = $errors;
        }
        
        // Em desenvolvimento, adiciona informações extras
        if (Config::isDevelopment() && !empty($context)) {
            $response['debug'] = ErrorHandler::sanitizeContext($context);
        }
        
        // Usa Flight::halt() para garantir que o status code seja definido corretamente
        // e que a execução seja interrompida
        Flight::halt($statusCode, json_encode($response, JSON_UNESCAPED_UNICODE));
    }
    
    /**
     * Envia resposta de erro de validação (400)
     * 
     * @param string $message Mensagem amigável
     * @param array $errors Array de erros de validação
     * @param array $context Contexto adicional para log
     */
    public static function sendValidationError(
        string $message,
        array $errors = [],
        array $context = []
    ): void {
        self::sendError(400, 'Dados inválidos', $message, 'VALIDATION_ERROR', $errors, $context);
    }
    
    /**
     * Envia resposta de erro de autenticação (401)
     * 
     * @param string $message Mensagem amigável
     * @param array $context Contexto adicional para log
     */
    public static function sendUnauthorizedError(
        string $message = 'Não autenticado',
        array $context = []
    ): void {
        self::sendError(401, 'Não autenticado', $message, 'UNAUTHORIZED', [], $context);
    }
    
    /**
     * Envia resposta de erro de permissão (403)
     * 
     * @param string $message Mensagem amigável
     * @param array $context Contexto adicional para log
     */
    public static function sendForbiddenError(
        string $message = 'Acesso negado',
        array $context = []
    ): void {
        self::sendError(403, 'Acesso negado', $message, 'FORBIDDEN', [], $context);
    }
    
    /**
     * Envia resposta de erro de recurso não encontrado (404)
     * 
     * @param string $resource Nome do recurso (ex: 'Cliente', 'Assinatura')
     * @param array $context Contexto adicional para log
     */
    public static function sendNotFoundError(
        string $resource = 'Recurso',
        array $context = []
    ): void {
        self::sendError(404, 'Não encontrado', "{$resource} não encontrado", 'NOT_FOUND', [], $context);
    }
    
    /**
     * Envia resposta de erro de JSON inválido (400)
     * 
     * @param array $context Contexto adicional para log
     */
    public static function sendInvalidJsonError(array $context = []): void
    {
        $jsonError = json_last_error_msg();
        self::sendError(
            400,
            'JSON inválido',
            'O corpo da requisição contém JSON inválido',
            'INVALID_JSON',
            [],
            array_merge($context, ['json_error' => $jsonError])
        );
    }
    
    /**
     * Envia resposta de erro do Stripe
     * 
     * @param \Stripe\Exception\ApiErrorException $exception Exceção do Stripe
     * @param string $userMessage Mensagem amigável (usada como fallback se código não mapeado)
     * @param array $context Contexto adicional para log
     */
    public static function sendStripeError(
        \Stripe\Exception\ApiErrorException $exception,
        string $userMessage,
        array $context = []
    ): void {
        $result = ErrorHandler::prepareStripeErrorResponse($exception, $userMessage);
        $response = $result['response'];
        $statusCode = $result['status_code'];
        
        // Adiciona contexto ao log (com tenant_id e user_id se disponíveis)
        $logContext = array_merge(
            ErrorHandler::sanitizeContext($context),
            [
                'stripe_code' => $exception->getStripeCode(),
                'stripe_type' => $exception->getStripeType() ?? null,
                'http_status' => $exception->getHttpStatus(),
                'tenant_id' => Flight::get('tenant_id') ?? null,
                'user_id' => Flight::get('user_id') ?? null,
                'action' => $context['action'] ?? 'unknown'
            ]
        );
        
        Logger::error($userMessage, $logContext);
        
        Flight::json($response, $statusCode);
        Flight::stop();
    }
    
    /**
     * Envia resposta de erro genérico (500)
     * 
     * @param \Throwable $exception Exceção capturada
     * @param string $userMessage Mensagem amigável
     * @param string|null $errorCode Código de erro interno
     * @param array $context Contexto adicional para log
     */
    public static function sendGenericError(
        \Throwable $exception,
        string $userMessage,
        ?string $errorCode = null,
        array $context = []
    ): void {
        $response = ErrorHandler::prepareErrorResponse($exception, $userMessage, $errorCode);
        
        // Adiciona contexto ao log se fornecido
        if (!empty($context)) {
            ErrorHandler::logException($exception, $context);
        }
        
        Flight::json($response, 500);
        Flight::stop();
    }
    
    /**
     * Envia resposta de sucesso
     * 
     * @param mixed $data Dados da resposta
     * @param int $statusCode Código HTTP (200, 201, etc.)
     * @param string|null $message Mensagem opcional
     */
    public static function sendSuccess($data, int $statusCode = 200, ?string $message = null): void
    {
        $response = [
            'success' => true
        ];
        
        if ($message !== null) {
            $response['message'] = $message;
        }
        
        if ($data !== null) {
            $response['data'] = $data;
        }
        
        Flight::json($response, $statusCode);
    }
    
    /**
     * Envia resposta de sucesso com criação (201)
     * 
     * @param mixed $data Dados criados
     * @param string|null $message Mensagem opcional
     */
    public static function sendCreated($data, ?string $message = null): void
    {
        self::sendSuccess($data, 201, $message);
    }
    
    /**
     * Envia resposta de sucesso sem conteúdo (204)
     */
    public static function sendNoContent(): void
    {
        Flight::json([], 204);
    }
}

