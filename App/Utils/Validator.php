<?php

namespace App\Utils;

/**
 * Classe para validação de inputs
 * Previne injeção de dados maliciosos e valida formatos esperados
 */
class Validator
{
    /**
     * Valida dados para criação de assinatura
     * 
     * @param array $data Dados a validar
     * @return array Array de erros (vazio se válido)
     */
    public static function validateSubscriptionCreate(array $data): array
    {
        $errors = [];
        
        // customer_id: deve ser inteiro positivo
        if (!isset($data['customer_id'])) {
            $errors['customer_id'] = 'Obrigatório';
        } elseif (!is_numeric($data['customer_id']) || (int)$data['customer_id'] <= 0) {
            $errors['customer_id'] = 'Deve ser um ID válido (número inteiro positivo)';
        } elseif ((int)$data['customer_id'] > PHP_INT_MAX) {
            $errors['customer_id'] = 'ID muito grande';
        }
        
        // price_id: deve seguir formato Stripe (price_xxxxx)
        if (!isset($data['price_id'])) {
            $errors['price_id'] = 'Obrigatório';
        } elseif (!is_string($data['price_id'])) {
            $errors['price_id'] = 'Deve ser uma string';
        } elseif (strlen($data['price_id']) < 5 || strlen($data['price_id']) > 100) {
            $errors['price_id'] = 'Deve ter entre 5 e 100 caracteres';
        } elseif (!preg_match('/^price_[a-zA-Z0-9]{24,}$/', $data['price_id'])) {
            $errors['price_id'] = 'Formato inválido. Deve seguir o padrão: price_xxxxx';
        }
        
        // trial_period_days: opcional, mas se presente deve ser 0-365
        if (isset($data['trial_period_days'])) {
            if (!is_numeric($data['trial_period_days'])) {
                $errors['trial_period_days'] = 'Deve ser um número';
            } else {
                $days = (int)$data['trial_period_days'];
                if ($days < 0 || $days > 365) {
                    $errors['trial_period_days'] = 'Deve estar entre 0 e 365 dias';
                }
            }
        }
        
        // payment_behavior: opcional, valores permitidos
        if (isset($data['payment_behavior'])) {
            $allowed = ['default_incomplete', 'error_if_incomplete', 'pending_if_incomplete', 'default'];
            if (!is_string($data['payment_behavior']) || !in_array($data['payment_behavior'], $allowed, true)) {
                $errors['payment_behavior'] = 'Valor inválido. Valores permitidos: ' . implode(', ', $allowed);
            }
        }
        
        // metadata: deve ser array associativo, máximo 50 chaves, valores máx 500 chars
        if (isset($data['metadata'])) {
            if (!is_array($data['metadata'])) {
                $errors['metadata'] = 'Deve ser um objeto';
            } else {
                if (count($data['metadata']) > 50) {
                    $errors['metadata'] = 'Máximo 50 chaves permitidas';
                } else {
                    foreach ($data['metadata'] as $key => $value) {
                        if (!is_string($key)) {
                            $errors['metadata'] = 'Chaves devem ser strings';
                            break;
                        }
                        if (strlen($key) > 40) {
                            $errors['metadata'] = "Chave '{$key}' muito longa (máximo 40 caracteres)";
                            break;
                        }
                        if (!preg_match('/^[a-zA-Z0-9_]+$/', $key)) {
                            $errors['metadata'] = "Chave '{$key}' contém caracteres inválidos (apenas letras, números e underscore)";
                            break;
                        }
                        if (!is_string($value) && !is_numeric($value) && !is_bool($value)) {
                            $errors['metadata'] = "Valor de '{$key}' deve ser string, número ou booleano";
                            break;
                        }
                        if (is_string($value) && strlen($value) > 500) {
                            $errors['metadata'] = "Valor de '{$key}' muito longo (máximo 500 caracteres)";
                            break;
                        }
                    }
                }
            }
        }
        
        return $errors;
    }
    
    /**
     * Valida dados para atualização de assinatura
     * 
     * @param array $data Dados a validar
     * @return array Array de erros (vazio se válido)
     */
    public static function validateSubscriptionUpdate(array $data): array
    {
        $errors = [];
        
        // price_id: opcional, mas se presente deve ser válido
        if (isset($data['price_id'])) {
            if (!is_string($data['price_id'])) {
                $errors['price_id'] = 'Deve ser uma string';
            } elseif (strlen($data['price_id']) < 5 || strlen($data['price_id']) > 100) {
                $errors['price_id'] = 'Deve ter entre 5 e 100 caracteres';
            } elseif (!preg_match('/^price_[a-zA-Z0-9]{24,}$/', $data['price_id'])) {
                $errors['price_id'] = 'Formato inválido. Deve seguir o padrão: price_xxxxx';
            }
        }
        
        // quantity: opcional, mas se presente deve ser inteiro positivo
        if (isset($data['quantity'])) {
            if (!is_numeric($data['quantity'])) {
                $errors['quantity'] = 'Deve ser um número';
            } else {
                $quantity = (int)$data['quantity'];
                if ($quantity < 1 || $quantity > 10000) {
                    $errors['quantity'] = 'Deve estar entre 1 e 10000';
                }
            }
        }
        
        // cancel_at_period_end: opcional, deve ser booleano
        if (isset($data['cancel_at_period_end'])) {
            if (!is_bool($data['cancel_at_period_end'])) {
                $errors['cancel_at_period_end'] = 'Deve ser true ou false';
            }
        }
        
        // proration_behavior: opcional, valores permitidos
        if (isset($data['proration_behavior'])) {
            $allowed = ['create_prorations', 'none', 'always_invoice'];
            if (!is_string($data['proration_behavior']) || !in_array($data['proration_behavior'], $allowed, true)) {
                $errors['proration_behavior'] = 'Valor inválido. Valores permitidos: ' . implode(', ', $allowed);
            }
        }
        
        // trial_end: opcional, deve ser timestamp Unix válido
        if (isset($data['trial_end'])) {
            if (!is_numeric($data['trial_end'])) {
                $errors['trial_end'] = 'Deve ser um timestamp Unix';
            } else {
                $trialEnd = (int)$data['trial_end'];
                $now = time();
                $maxFuture = $now + (365 * 24 * 60 * 60); // 1 ano no futuro
                if ($trialEnd < $now || $trialEnd > $maxFuture) {
                    $errors['trial_end'] = 'Deve ser um timestamp válido entre agora e 1 ano no futuro';
                }
            }
        }
        
        // metadata: mesma validação da criação
        if (isset($data['metadata'])) {
            $metadataErrors = self::validateMetadata($data['metadata']);
            if (!empty($metadataErrors)) {
                $errors['metadata'] = $metadataErrors;
            }
        }
        
        return $errors;
    }
    
    /**
     * Valida dados para criação de cliente
     * 
     * @param array $data Dados a validar
     * @return array Array de erros (vazio se válido)
     */
    public static function validateCustomerCreate(array $data): array
    {
        $errors = [];
        
        // email: obrigatório, deve ser email válido
        if (!isset($data['email'])) {
            $errors['email'] = 'Obrigatório';
        } elseif (!is_string($data['email'])) {
            $errors['email'] = 'Deve ser uma string';
        } elseif (strlen($data['email']) > 255) {
            $errors['email'] = 'Muito longo (máximo 255 caracteres)';
        } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Formato de email inválido';
        }
        
        // name: opcional, mas se presente deve ser válido
        if (isset($data['name'])) {
            if (!is_string($data['name'])) {
                $errors['name'] = 'Deve ser uma string';
            } elseif (strlen($data['name']) > 255) {
                $errors['name'] = 'Muito longo (máximo 255 caracteres)';
            } elseif (strlen(trim($data['name'])) === 0) {
                $errors['name'] = 'Não pode ser vazio';
            }
        }
        
        // phone: opcional, mas se presente deve ser válido
        if (isset($data['phone'])) {
            if (!is_string($data['phone'])) {
                $errors['phone'] = 'Deve ser uma string';
            } elseif (strlen($data['phone']) > 50) {
                $errors['phone'] = 'Muito longo (máximo 50 caracteres)';
            }
        }
        
        // metadata: mesma validação
        if (isset($data['metadata'])) {
            $metadataErrors = self::validateMetadata($data['metadata']);
            if (!empty($metadataErrors)) {
                $errors['metadata'] = $metadataErrors;
            }
        }
        
        return $errors;
    }
    
    /**
     * Valida dados para atualização de cliente
     * 
     * @param array $data Dados a validar
     * @return array Array de erros (vazio se válido)
     */
    public static function validateCustomerUpdate(array $data): array
    {
        $errors = [];
        
        // email: opcional na atualização, mas se presente deve ser válido
        if (isset($data['email'])) {
            if (!is_string($data['email'])) {
                $errors['email'] = 'Deve ser uma string';
            } elseif (strlen($data['email']) > 255) {
                $errors['email'] = 'Muito longo (máximo 255 caracteres)';
            } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                $errors['email'] = 'Formato de email inválido';
            }
        }
        
        // name: opcional
        if (isset($data['name'])) {
            if (!is_string($data['name'])) {
                $errors['name'] = 'Deve ser uma string';
            } elseif (strlen($data['name']) > 255) {
                $errors['name'] = 'Muito longo (máximo 255 caracteres)';
            }
        }
        
        // phone: opcional
        if (isset($data['phone'])) {
            if (!is_string($data['phone'])) {
                $errors['phone'] = 'Deve ser uma string';
            } elseif (strlen($data['phone']) > 50) {
                $errors['phone'] = 'Muito longo (máximo 50 caracteres)';
            }
        }
        
        // metadata: mesma validação
        if (isset($data['metadata'])) {
            $metadataErrors = self::validateMetadata($data['metadata']);
            if (!empty($metadataErrors)) {
                $errors['metadata'] = $metadataErrors;
            }
        }
        
        return $errors;
    }
    
    /**
     * Valida dados de login
     * 
     * @param array $data Dados a validar
     * @return array Array de erros (vazio se válido)
     */
    public static function validateLogin(array $data): array
    {
        $errors = [];
        
        // email: obrigatório, deve ser email válido
        if (!isset($data['email'])) {
            $errors['email'] = 'Obrigatório';
        } elseif (!is_string($data['email'])) {
            $errors['email'] = 'Deve ser uma string';
        } else {
            $email = trim($data['email']);
            if (empty($email)) {
                $errors['email'] = 'Obrigatório';
            } elseif (strlen($email) > 255) {
                $errors['email'] = 'Muito longo (máximo 255 caracteres)';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors['email'] = 'Formato de email inválido';
            }
        }
        
        // password: obrigatório, apenas validação básica (não força da senha)
        // A validação de força é apenas para criação/atualização de senha
        if (!isset($data['password'])) {
            $errors['password'] = 'Obrigatório';
        } elseif (!is_string($data['password'])) {
            $errors['password'] = 'Deve ser uma string';
        } else {
            $password = $data['password'];
            // Validação básica: apenas tamanho mínimo e máximo
            if (strlen($password) < 1) {
                $errors['password'] = 'Senha não pode estar vazia';
            } elseif (strlen($password) > 128) {
                $errors['password'] = 'Senha muito longa (máximo 128 caracteres)';
            }
        }
        
        // tenant_id: obrigatório, deve ser inteiro positivo
        if (!isset($data['tenant_id'])) {
            $errors['tenant_id'] = 'Obrigatório';
        } elseif (!is_numeric($data['tenant_id'])) {
            $errors['tenant_id'] = 'Deve ser um número';
        } else {
            $tenantId = (int)$data['tenant_id'];
            if ($tenantId <= 0) {
                $errors['tenant_id'] = 'Deve ser um número positivo';
            } elseif ($tenantId > PHP_INT_MAX) {
                $errors['tenant_id'] = 'ID muito grande';
            }
        }
        
        return $errors;
    }
    
    /**
     * Valida metadata (usado em múltiplos lugares)
     * 
     * @param mixed $metadata Metadata a validar
     * @return string|null Mensagem de erro ou null se válido
     */
    private static function validateMetadata($metadata): ?string
    {
        if (!is_array($metadata)) {
            return 'Deve ser um objeto';
        }
        
        if (count($metadata) > 50) {
            return 'Máximo 50 chaves permitidas';
        }
        
        foreach ($metadata as $key => $value) {
            if (!is_string($key)) {
                return 'Chaves devem ser strings';
            }
            
            if (strlen($key) > 40) {
                return "Chave '{$key}' muito longa (máximo 40 caracteres)";
            }
            
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $key)) {
                return "Chave '{$key}' contém caracteres inválidos (apenas letras, números e underscore)";
            }
            
            if (!is_string($value) && !is_numeric($value) && !is_bool($value)) {
                return "Valor de '{$key}' deve ser string, número ou booleano";
            }
            
            if (is_string($value) && strlen($value) > 500) {
                return "Valor de '{$key}' muito longo (máximo 500 caracteres)";
            }
        }
        
        return null;
    }
    
    /**
     * Valida ID numérico
     * 
     * @param mixed $id ID a validar
     * @param string $fieldName Nome do campo (para mensagem de erro)
     * @return array Array de erros (vazio se válido)
     */
    public static function validateId($id, string $fieldName = 'id'): array
    {
        $errors = [];
        
        if (!is_numeric($id)) {
            $errors[$fieldName] = 'Deve ser um número';
        } else {
            $idInt = (int)$id;
            if ($idInt <= 0) {
                $errors[$fieldName] = 'Deve ser um número positivo';
            } elseif ($idInt > PHP_INT_MAX) {
                $errors[$fieldName] = 'ID muito grande';
            }
        }
        
        return $errors;
    }
    
    /**
     * Valida parâmetros de paginação
     * 
     * @param array $queryParams Parâmetros da query
     * @return array ['page' => int, 'limit' => int, 'errors' => array]
     */
    public static function validatePagination(array $queryParams): array
    {
        $errors = [];
        $page = 1;
        $limit = 20;
        
        // page
        if (isset($queryParams['page'])) {
            if (!is_numeric($queryParams['page'])) {
                $errors['page'] = 'Deve ser um número';
            } else {
                $page = max(1, (int)$queryParams['page']);
                if ($page > 10000) {
                    $errors['page'] = 'Número de página muito grande (máximo 10000)';
                }
            }
        }
        
        // limit
        if (isset($queryParams['limit'])) {
            if (!is_numeric($queryParams['limit'])) {
                $errors['limit'] = 'Deve ser um número';
            } else {
                $limit = (int)$queryParams['limit'];
                if ($limit < 1) {
                    $errors['limit'] = 'Deve ser pelo menos 1';
                } elseif ($limit > 100) {
                    $errors['limit'] = 'Máximo 100 itens por página';
                }
            }
        }
        
        return [
            'page' => $page,
            'limit' => $limit,
            'errors' => $errors
        ];
    }
    
    /**
     * Valida price_id do Stripe
     * 
     * @param mixed $priceId Price ID a validar
     * @return array Array de erros (vazio se válido)
     */
    public static function validateStripePriceId($priceId): array
    {
        $errors = [];
        
        if (!is_string($priceId)) {
            $errors['price_id'] = 'Deve ser uma string';
        } elseif (strlen($priceId) < 5 || strlen($priceId) > 100) {
            $errors['price_id'] = 'Deve ter entre 5 e 100 caracteres';
        } elseif (!preg_match('/^price_[a-zA-Z0-9]{24,}$/', $priceId)) {
            $errors['price_id'] = 'Formato inválido. Deve seguir o padrão: price_xxxxx';
        }
        
        return $errors;
    }
    
    /**
     * Valida customer_id do Stripe
     * 
     * @param mixed $customerId Customer ID a validar
     * @return array Array de erros (vazio se válido)
     */
    public static function validateStripeCustomerId($customerId): array
    {
        $errors = [];
        
        if (!is_string($customerId)) {
            $errors['customer_id'] = 'Deve ser uma string';
        } elseif (strlen($customerId) < 5 || strlen($customerId) > 100) {
            $errors['customer_id'] = 'Deve ter entre 5 e 100 caracteres';
        } elseif (!preg_match('/^cus_[a-zA-Z0-9]{24,}$/', $customerId)) {
            $errors['customer_id'] = 'Formato inválido. Deve seguir o padrão: cus_xxxxx';
        }
        
        return $errors;
    }
    
    /**
     * Valida força da senha
     * 
     * @param string $password Senha a validar
     * @return string|null Mensagem de erro ou null se válida
     */
    public static function validatePasswordStrength(string $password): ?string
    {
        // Tamanho mínimo: 12 caracteres
        if (strlen($password) < 12) {
            return 'Senha deve ter no mínimo 12 caracteres';
        }
        
        // Tamanho máximo: 128 caracteres
        if (strlen($password) > 128) {
            return 'Senha muito longa (máximo 128 caracteres)';
        }
        
        // Deve conter pelo menos uma letra maiúscula
        if (!preg_match('/[A-Z]/', $password)) {
            return 'Senha deve conter pelo menos uma letra maiúscula';
        }
        
        // Deve conter pelo menos uma letra minúscula
        if (!preg_match('/[a-z]/', $password)) {
            return 'Senha deve conter pelo menos uma letra minúscula';
        }
        
        // Deve conter pelo menos um número
        if (!preg_match('/[0-9]/', $password)) {
            return 'Senha deve conter pelo menos um número';
        }
        
        // Deve conter pelo menos um caractere especial
        if (!preg_match('/[!@#$%^&*()_+\-=\[\]{};\'\\:"|,.<>\/?]/', $password)) {
            return 'Senha deve conter pelo menos um caractere especial (!@#$%^&*()_+-=[]{};\':"|,.<>/?).';
        }
        
        // Verifica senhas comuns/fracas (lista básica)
        $commonPasswords = [
            'password', 'password123', '12345678', '123456789', '1234567890',
            'qwerty', 'abc123', 'admin', 'letmein', 'welcome', 'monkey',
            '1234567', 'sunshine', 'princess', 'football', 'iloveyou'
        ];
        
        if (in_array(strtolower($password), $commonPasswords)) {
            return 'Esta senha é muito comum e não é segura. Escolha uma senha mais forte.';
        }
        
        // Verifica padrões simples (ex: aaa, 123, abc)
        if (preg_match('/(.)\1{2,}/', $password)) {
            return 'Senha não pode conter caracteres repetidos consecutivos (ex: aaa, 111)';
        }
        
        // Verifica sequências simples (ex: 123, abc)
        if (preg_match('/(012|123|234|345|456|567|678|789|abc|bcd|cde|def|efg|fgh|ghi|hij|ijk|jkl|klm|lmn|mno|nop|opq|pqr|qrs|rst|stu|tuv|uvw|vwx|wxy|xyz)/i', $password)) {
            return 'Senha não pode conter sequências simples (ex: 123, abc)';
        }
        
        return null; // Senha válida
    }
    
    /**
     * Valida dados para criação de usuário
     * 
     * @param array $data Dados a validar
     * @return array Array de erros (vazio se válido)
     */
    public static function validateUserCreate(array $data): array
    {
        $errors = [];
        
        // email: obrigatório, deve ser email válido
        if (!isset($data['email'])) {
            $errors['email'] = 'Obrigatório';
        } elseif (!is_string($data['email'])) {
            $errors['email'] = 'Deve ser uma string';
        } else {
            $email = trim($data['email']);
            if (empty($email)) {
                $errors['email'] = 'Obrigatório';
            } elseif (strlen($email) > 255) {
                $errors['email'] = 'Muito longo (máximo 255 caracteres)';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors['email'] = 'Formato de email inválido';
            }
        }
        
        // password: obrigatório, deve ser forte
        if (!isset($data['password'])) {
            $errors['password'] = 'Obrigatório';
        } elseif (!is_string($data['password'])) {
            $errors['password'] = 'Deve ser uma string';
        } else {
            $passwordError = self::validatePasswordStrength($data['password']);
            if ($passwordError) {
                $errors['password'] = $passwordError;
            }
        }
        
        // name: opcional
        if (isset($data['name'])) {
            if (!is_string($data['name'])) {
                $errors['name'] = 'Deve ser uma string';
            } elseif (strlen($data['name']) > 255) {
                $errors['name'] = 'Muito longo (máximo 255 caracteres)';
            }
        }
        
        // role: opcional, valores permitidos
        if (isset($data['role'])) {
            $allowedRoles = ['admin', 'viewer', 'editor'];
            if (!is_string($data['role']) || !in_array($data['role'], $allowedRoles, true)) {
                $errors['role'] = 'Valor inválido. Valores permitidos: ' . implode(', ', $allowedRoles);
            }
        }
        
        return $errors;
    }
    
    /**
     * Valida dados para atualização de senha
     * 
     * @param array $data Dados a validar
     * @return array Array de erros (vazio se válido)
     */
    public static function validatePasswordUpdate(array $data): array
    {
        $errors = [];
        
        // password: obrigatório, deve ser forte
        if (!isset($data['password'])) {
            $errors['password'] = 'Obrigatório';
        } elseif (!is_string($data['password'])) {
            $errors['password'] = 'Deve ser uma string';
        } else {
            $passwordError = self::validatePasswordStrength($data['password']);
            if ($passwordError) {
                $errors['password'] = $passwordError;
            }
        }
        
        // current_password: opcional (para verificar senha atual antes de alterar)
        if (isset($data['current_password'])) {
            if (!is_string($data['current_password'])) {
                $errors['current_password'] = 'Deve ser uma string';
            } elseif (empty($data['current_password'])) {
                $errors['current_password'] = 'Obrigatório para alterar senha';
            }
        }
        
        return $errors;
    }
    
    /**
     * Valida tamanho máximo de um array (prevenção de DoS)
     * 
     * @param mixed $array Array a validar
     * @param string $fieldName Nome do campo (para mensagem de erro)
     * @param int $maxSize Tamanho máximo permitido (padrão: 100)
     * @return array Array de erros (vazio se válido)
     */
    public static function validateArraySize($array, string $fieldName, int $maxSize = 100): array
    {
        $errors = [];
        
        if (!is_array($array)) {
            $errors[$fieldName] = 'Deve ser um array';
        } elseif (count($array) > $maxSize) {
            $errors[$fieldName] = "Máximo {$maxSize} itens permitidos (encontrados: " . count($array) . ")";
        }
        
        return $errors;
    }
    
    /**
     * Valida se JSON foi decodificado corretamente
     * 
     * @param mixed $data Dados decodificados
     * @param string $fieldName Nome do campo (para mensagem de erro)
     * @return array Array de erros (vazio se válido)
     */
    public static function validateJsonDecode($data, string $fieldName = 'json'): array
    {
        $errors = [];
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $errors[$fieldName] = 'JSON inválido: ' . json_last_error_msg();
        } elseif (!is_array($data)) {
            $errors[$fieldName] = 'Dados devem ser um objeto JSON (array associativo)';
        }
        
        return $errors;
    }
    
    /**
     * Valida tamanho máximo de metadata (prevenção de DoS)
     * 
     * @param mixed $metadata Metadata a validar
     * @param string $fieldName Nome do campo (para mensagem de erro)
     * @param int $maxKeys Número máximo de chaves (padrão: 50)
     * @return array Array de erros (vazio se válido)
     */
    public static function validateMetadata($metadata, string $fieldName = 'metadata', int $maxKeys = 50): array
    {
        $errors = [];
        
        if (!is_array($metadata)) {
            $errors[$fieldName] = 'Deve ser um objeto (array associativo)';
        } elseif (count($metadata) > $maxKeys) {
            $errors[$fieldName] = "Máximo {$maxKeys} chaves permitidas em metadata (encontradas: " . count($metadata) . ")";
        } else {
            // Valida tamanho total dos valores (prevenção de DoS)
            $totalSize = 0;
            foreach ($metadata as $key => $value) {
                if (is_string($value)) {
                    $totalSize += strlen($value);
                } elseif (is_array($value)) {
                    $totalSize += strlen(json_encode($value));
                }
            }
            
            // Limite de 10KB para metadata total
            if ($totalSize > 10240) {
                $errors[$fieldName] = "Metadata muito grande (máximo 10KB, encontrado: " . round($totalSize / 1024, 2) . "KB)";
            }
        }
        
        return $errors;
    }
}

