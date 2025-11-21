<?php

namespace App\Utils;

use Config;
use App\Services\Logger;

/**
 * Classe para tratamento seguro de erros
 * Previne exposição de informações sensíveis em logs e respostas
 */
class ErrorHandler
{
    /**
     * Padrões de dados sensíveis que devem ser mascarados
     */
    private const SENSITIVE_PATTERNS = [
        '/password/i',
        '/secret/i',
        '/key/i',
        '/token/i',
        '/api[_-]?key/i',
        '/authorization/i',
        '/bearer/i',
        '/stripe[_-]?secret/i',
        '/stripe[_-]?key/i',
        '/db[_-]?pass/i',
        '/database[_-]?password/i',
        '/connection[_-]?string/i',
        '/dsn/i',
        '/credit[_-]?card/i',
        '/card[_-]?number/i',
        '/cvv/i',
        '/cvc/i',
        '/ssn/i',
        '/cpf/i',
        '/cnpj/i'
    ];
    
    /**
     * Palavras-chave que indicam dados sensíveis em mensagens
     */
    private const SENSITIVE_KEYWORDS = [
        'password',
        'secret',
        'key',
        'token',
        'api_key',
        'authorization',
        'bearer',
        'stripe_secret',
        'database',
        'connection',
        'dsn',
        'credit_card',
        'card_number',
        'cvv',
        'cvc'
    ];
    
    /**
     * Sanitiza uma mensagem de erro removendo informações sensíveis
     * 
     * @param string $message Mensagem original
     * @return string Mensagem sanitizada
     */
    public static function sanitizeMessage(string $message): string
    {
        // Remove caminhos de arquivos completos (pode expor estrutura)
        $message = preg_replace('/\/[^\s]+\.php:\d+/', '[arquivo]:[linha]', $message);
        
        // Remove caminhos absolutos
        $message = preg_replace('/[A-Z]:\\\\[^\s]+/', '[caminho]', $message);
        $message = preg_replace('/\/[^\s]+/', '[caminho]', $message);
        
        // Remove possíveis tokens/keys em mensagens
        foreach (self::SENSITIVE_PATTERNS as $pattern) {
            // ✅ CORREÇÃO: Extrai o corpo do padrão (sem delimitadores e modificadores)
            // Remove delimitadores iniciais e finais com modificadores
            $patternBody = preg_replace('/^\/|\/[a-z]*$/i', '', $pattern);
            // Extrai modificadores (se houver)
            preg_match('/\/([a-z]*)$/i', $pattern, $modMatches);
            $modifiers = $modMatches[1] ?? 'i'; // Default para case-insensitive
            
            // Reconstrói o padrão completo corretamente
            $fullPattern = '/' . $patternBody . '.*?(\s|$|")/' . $modifiers;
            
            // Tenta aplicar o padrão, se falhar usa um padrão mais simples
            $result = @preg_replace($fullPattern, '[dados_sensiveis] ', $message);
            if ($result !== null && preg_last_error() === PREG_NO_ERROR) {
                $message = $result;
            } else {
                // Fallback: padrão mais simples sem lookahead
                $simplePattern = '/' . $patternBody . '/i';
                $message = preg_replace($simplePattern, '[dados_sensiveis]', $message);
            }
        }
        
        // Remove possíveis SQL queries completas (pode expor estrutura do banco)
        if (preg_match('/SQLSTATE|PDOException|SQL syntax/i', $message)) {
            $message = 'Erro de banco de dados';
        }
        
        // Remove stack traces completos
        if (strpos($message, 'Stack trace:') !== false) {
            $message = substr($message, 0, strpos($message, 'Stack trace:'));
            $message .= ' [stack trace removido]';
        }
        
        return trim($message);
    }
    
    /**
     * Sanitiza contexto de log removendo dados sensíveis
     * 
     * @param array $context Contexto original
     * @return array Contexto sanitizado
     */
    public static function sanitizeContext(array $context): array
    {
        $sanitized = [];
        
        foreach ($context as $key => $value) {
            $keyLower = strtolower($key);
            
            // Verifica se a chave indica dados sensíveis
            $isSensitive = false;
            foreach (self::SENSITIVE_KEYWORDS as $keyword) {
                if (strpos($keyLower, $keyword) !== false) {
                    $isSensitive = true;
                    break;
                }
            }
            
            if ($isSensitive) {
                // Mascara valores sensíveis
                if (is_string($value)) {
                    $sanitized[$key] = self::maskSensitiveValue($value);
                } elseif (is_array($value)) {
                    $sanitized[$key] = self::sanitizeContext($value);
                } else {
                    $sanitized[$key] = '[valor_sensivel]';
                }
            } elseif (is_array($value)) {
                // Recursivamente sanitiza arrays
                $sanitized[$key] = self::sanitizeContext($value);
            } elseif (is_string($value) && strlen($value) > 500) {
                // Trunca strings muito longas (podem conter dados sensíveis)
                $sanitized[$key] = substr($value, 0, 100) . '...[truncado]';
            } else {
                $sanitized[$key] = $value;
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Mascara um valor sensível
     * 
     * @param string $value Valor original
     * @return string Valor mascarado
     */
    private static function maskSensitiveValue(string $value): string
    {
        if (strlen($value) <= 8) {
            return '***';
        }
        
        // Mostra apenas primeiros 4 e últimos 4 caracteres
        $start = substr($value, 0, 4);
        $end = substr($value, -4);
        return $start . '***' . $end;
    }
    
    /**
     * Prepara resposta de erro segura para o cliente
     * 
     * @param \Throwable $exception Exceção capturada
     * @param string $userMessage Mensagem amigável para o usuário
     * @param string|null $errorCode Código de erro (opcional)
     * @return array Resposta formatada
     */
    public static function prepareErrorResponse(\Throwable $exception, string $userMessage, ?string $errorCode = null): array
    {
        // Log completo no servidor (sanitizado)
        self::logException($exception);
        
        // Resposta para o cliente
        $response = [
            'error' => 'Erro ao processar requisição',
            'message' => $userMessage,
            'code' => $errorCode ?? 'INTERNAL_ERROR'
        ];
        
        // Em desenvolvimento, adiciona mais detalhes (mas sanitizados)
        if (Config::isDevelopment()) {
            $response['debug'] = [
                'type' => get_class($exception),
                'message' => self::sanitizeMessage($exception->getMessage()),
                'file' => basename($exception->getFile()),
                'line' => $exception->getLine()
            ];
            
            // Adiciona código de erro específico do Stripe se disponível
            if (method_exists($exception, 'getStripeCode')) {
                $response['debug']['stripe_code'] = $exception->getStripeCode();
            }
        }
        
        return $response;
    }
    
    /**
     * Loga exceção de forma segura
     * 
     * @param \Throwable $exception Exceção a ser logada
     * @param array $additionalContext Contexto adicional
     */
    public static function logException(\Throwable $exception, array $additionalContext = []): void
    {
        $context = [
            'exception_type' => get_class($exception),
            'message' => self::sanitizeMessage($exception->getMessage()),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'code' => $exception->getCode()
        ];
        
        // Adiciona trace apenas em desenvolvimento
        if (Config::isDevelopment()) {
            $context['trace'] = self::sanitizeTrace($exception->getTrace());
        }
        
        // Adiciona contexto adicional (sanitizado)
        if (!empty($additionalContext)) {
            $context = array_merge($context, self::sanitizeContext($additionalContext));
        }
        
        // Adiciona informações específicas do Stripe se disponível
        if (method_exists($exception, 'getStripeCode')) {
            $context['stripe_code'] = $exception->getStripeCode();
            // ✅ CORREÇÃO: Verifica se método existe antes de chamar
            if (method_exists($exception, 'getStripeType')) {
                $context['stripe_type'] = $exception->getStripeType();
            }
        }
        
        Logger::error("Exceção capturada: " . get_class($exception), $context);
    }
    
    /**
     * Sanitiza stack trace removendo informações sensíveis
     * 
     * @param array $trace Stack trace original
     * @return array Stack trace sanitizado
     */
    private static function sanitizeTrace(array $trace): array
    {
        $sanitized = [];
        
        foreach ($trace as $index => $frame) {
            $sanitized[$index] = [
                'file' => isset($frame['file']) ? basename($frame['file']) : null,
                'line' => $frame['line'] ?? null,
                'function' => $frame['function'] ?? null,
                'class' => $frame['class'] ?? null
            ];
            
            // Remove argumentos (podem conter dados sensíveis)
            // Mantém apenas tipos de argumentos
            if (isset($frame['args']) && is_array($frame['args'])) {
                $sanitized[$index]['args_types'] = array_map(function($arg) {
                    return gettype($arg);
                }, $frame['args']);
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Mapeia códigos de erro do Stripe para mensagens amigáveis
     * 
     * @param string|null $stripeCode Código de erro do Stripe
     * @return array ['message' => string, 'user_friendly' => bool]
     */
    private static function mapStripeErrorCode(?string $stripeCode): array
    {
        if ($stripeCode === null) {
            return ['message' => 'Erro na integração com Stripe', 'user_friendly' => false];
        }
        
        $errorMap = [
            // Erros de autenticação
            'api_key_expired' => ['message' => 'Chave da API expirada. Entre em contato com o suporte.', 'user_friendly' => true],
            'authentication_required' => ['message' => 'Autenticação necessária para esta operação.', 'user_friendly' => true],
            
            // Erros de cartão
            'card_declined' => ['message' => 'Cartão recusado. Verifique os dados ou use outro cartão.', 'user_friendly' => true],
            'insufficient_funds' => ['message' => 'Fundos insuficientes no cartão.', 'user_friendly' => true],
            'expired_card' => ['message' => 'Cartão expirado. Use outro cartão.', 'user_friendly' => true],
            'incorrect_cvc' => ['message' => 'Código de segurança (CVC) incorreto.', 'user_friendly' => true],
            'incorrect_number' => ['message' => 'Número do cartão incorreto.', 'user_friendly' => true],
            'invalid_cvc' => ['message' => 'Código de segurança (CVC) inválido.', 'user_friendly' => true],
            'invalid_expiry_month' => ['message' => 'Mês de expiração inválido.', 'user_friendly' => true],
            'invalid_expiry_year' => ['message' => 'Ano de expiração inválido.', 'user_friendly' => true],
            'invalid_number' => ['message' => 'Número do cartão inválido.', 'user_friendly' => true],
            'processing_error' => ['message' => 'Erro ao processar o pagamento. Tente novamente.', 'user_friendly' => true],
            
            // Erros de rate limit
            'rate_limit' => ['message' => 'Muitas requisições. Aguarde um momento e tente novamente.', 'user_friendly' => true],
            
            // Erros de recursos
            'resource_missing' => ['message' => 'Recurso não encontrado.', 'user_friendly' => true],
            'resource_already_exists' => ['message' => 'Este recurso já existe.', 'user_friendly' => true],
            
            // Erros de parâmetros
            'parameter_invalid_empty' => ['message' => 'Parâmetro obrigatório não fornecido.', 'user_friendly' => true],
            'parameter_invalid_integer' => ['message' => 'Parâmetro deve ser um número inteiro.', 'user_friendly' => true],
            'parameter_invalid_string_blank' => ['message' => 'Parâmetro não pode estar vazio.', 'user_friendly' => true],
            'parameter_missing' => ['message' => 'Parâmetro obrigatório ausente.', 'user_friendly' => true],
            
            // Erros de assinatura
            'subscription_canceled' => ['message' => 'Assinatura foi cancelada.', 'user_friendly' => true],
            'subscription_past_due' => ['message' => 'Assinatura está em atraso. Atualize o método de pagamento.', 'user_friendly' => true],
            'subscription_unpaid' => ['message' => 'Assinatura não paga. Atualize o método de pagamento.', 'user_friendly' => true],
            
            // Erros de pagamento
            'payment_intent_payment_attempt_failed' => ['message' => 'Tentativa de pagamento falhou. Tente novamente.', 'user_friendly' => true],
            'payment_method_unactivated' => ['message' => 'Método de pagamento não está ativo.', 'user_friendly' => true],
            
            // Erros de checkout
            'checkout_session_expired' => ['message' => 'Sessão de checkout expirada. Crie uma nova sessão.', 'user_friendly' => true],
        ];
        
        if (isset($errorMap[$stripeCode])) {
            return $errorMap[$stripeCode];
        }
        
        // Para códigos não mapeados, retorna mensagem genérica
        return ['message' => 'Erro na integração com Stripe', 'user_friendly' => false];
    }
    
    /**
     * Prepara resposta de erro para exceções do Stripe
     * 
     * @param \Stripe\Exception\ApiErrorException $exception Exceção do Stripe
     * @param string $userMessage Mensagem amigável (usada como fallback)
     * @return array ['response' => array, 'status_code' => int] Resposta formatada e código HTTP
     */
    public static function prepareStripeErrorResponse(\Stripe\Exception\ApiErrorException $exception, string $userMessage): array
    {
        self::logException($exception);
        
        $stripeCode = $exception->getStripeCode();
        $httpStatus = $exception->getHttpStatus();
        
        // Mapeia código de erro para mensagem amigável
        $mappedError = self::mapStripeErrorCode($stripeCode);
        
        // Se o mapeamento retornou mensagem amigável, usa ela; senão usa a mensagem fornecida
        $finalMessage = $mappedError['user_friendly'] ? $mappedError['message'] : $userMessage;
        
        // Determina código HTTP apropriado
        $statusCode = 400; // Default
        if ($httpStatus >= 400 && $httpStatus < 600) {
            $statusCode = $httpStatus;
        } elseif (in_array($stripeCode, ['api_key_expired', 'authentication_required'], true)) {
            $statusCode = 401;
        } elseif ($stripeCode === 'resource_missing') {
            $statusCode = 404;
        } elseif ($stripeCode === 'rate_limit') {
            $statusCode = 429;
        }
        
        $response = [
            'error' => 'Erro na integração com Stripe',
            'message' => $finalMessage,
            'code' => 'STRIPE_ERROR',
            'stripe_code' => $stripeCode
        ];
        
        if (Config::isDevelopment()) {
            $response['debug'] = [
                'stripe_code' => $stripeCode,
                'stripe_type' => method_exists($exception, 'getStripeType') ? $exception->getStripeType() : null,
                'http_status' => $httpStatus,
                'raw_message' => self::sanitizeMessage($exception->getMessage()),
                'mapped_message' => $mappedError['message'],
                'user_friendly' => $mappedError['user_friendly']
            ];
        }
        
        return ['response' => $response, 'status_code' => $statusCode];
    }
    
    /**
     * Prepara resposta de erro genérica
     * 
     * @param string $userMessage Mensagem amigável
     * @param string|null $errorCode Código de erro
     * @param array $context Contexto adicional para log
     * @return array Resposta formatada
     */
    public static function prepareGenericErrorResponse(string $userMessage, ?string $errorCode = null, array $context = []): array
    {
        if (!empty($context)) {
            Logger::error($userMessage, self::sanitizeContext($context));
        }
        
        return [
            'error' => 'Erro ao processar requisição',
            'message' => $userMessage,
            'code' => $errorCode ?? 'GENERIC_ERROR'
        ];
    }
}

