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
        } else {
            $priceIdErrors = self::validateStripeId($data['price_id'], 'price_id');
            if (!empty($priceIdErrors)) {
                $errors = array_merge($errors, $priceIdErrors);
            }
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
            $priceIdErrors = self::validateStripeId($data['price_id'], 'price_id');
            if (!empty($priceIdErrors)) {
                $errors = array_merge($errors, $priceIdErrors);
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
            $metadataError = self::validateMetadataInternal($data['metadata']);
            if ($metadataError !== null) {
                $errors['metadata'] = $metadataError;
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
            } elseif (!preg_match('/^[\d\s\-\+\(\)]+$/', $data['phone'])) {
                $errors['phone'] = 'Formato de telefone inválido';
            }
        }
        
        // address: opcional, mas se presente deve ser válido
        if (isset($data['address'])) {
            $addressErrors = self::validateAddress($data['address'], 'address');
            if (!empty($addressErrors)) {
                $errors = array_merge($errors, $addressErrors);
            }
        }
        
        // metadata: mesma validação
        if (isset($data['metadata'])) {
            $metadataError = self::validateMetadataInternal($data['metadata']);
            if ($metadataError !== null) {
                $errors['metadata'] = $metadataError;
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
            $metadataError = self::validateMetadataInternal($data['metadata']);
            if ($metadataError !== null) {
                $errors['metadata'] = $metadataError;
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
        
        // tenant_id OU tenant_slug: pelo menos um deve ser fornecido
        // Prioridade: tenant_slug (se fornecido, usa ele; senão, usa tenant_id)
        $hasTenantId = isset($data['tenant_id']);
        $hasTenantSlug = isset($data['tenant_slug']);
        
        if (!$hasTenantId && !$hasTenantSlug) {
            $errors['tenant_id'] = 'Obrigatório (ou forneça tenant_slug)';
            $errors['tenant_slug'] = 'Obrigatório (ou forneça tenant_id)';
        } else {
            // Valida tenant_slug se fornecido
            if ($hasTenantSlug) {
                if (!is_string($data['tenant_slug'])) {
                    $errors['tenant_slug'] = 'Deve ser uma string';
                } else {
                    $slug = trim($data['tenant_slug']);
                    if (empty($slug)) {
                        $errors['tenant_slug'] = 'Não pode estar vazio';
                    } elseif (strlen($slug) > 100) {
                        $errors['tenant_slug'] = 'Muito longo (máximo 100 caracteres)';
                    } elseif (!\App\Utils\SlugHelper::isValid($slug)) {
                        $errors['tenant_slug'] = 'Formato inválido. Use apenas letras minúsculas, números e hífens';
                    }
                }
            }
            
            // Valida tenant_id se fornecido (e slug não foi fornecido)
            if ($hasTenantId && !$hasTenantSlug) {
                if (!is_numeric($data['tenant_id'])) {
                    $errors['tenant_id'] = 'Deve ser um número';
                } else {
                    $tenantId = (int)$data['tenant_id'];
                    if ($tenantId <= 0) {
                        $errors['tenant_id'] = 'Deve ser um número positivo';
                    } elseif ($tenantId > PHP_INT_MAX) {
                        $errors['tenant_id'] = 'ID muito grande';
                    }
                }
            }
        }
        
        return $errors;
    }
    
    /**
     * Valida metadata (usado internamente)
     * ✅ CORREÇÃO: Renomeado para validateMetadataInternal para evitar conflito com validateMetadata() público
     * 
     * @param mixed $metadata Metadata a validar
     * @return string|null Mensagem de erro ou null se válido
     */
    private static function validateMetadataInternal($metadata): ?string
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
        return self::validateStripeId($priceId, 'price_id');
    }
    
    /**
     * Valida customer_id do Stripe
     * 
     * @param mixed $customerId Customer ID a validar
     * @return array Array de erros (vazio se válido)
     */
    public static function validateStripeCustomerId($customerId): array
    {
        return self::validateStripeId($customerId, 'customer_id');
    }
    
    /**
     * Valida qualquer ID do Stripe de forma genérica
     * 
     * @param mixed $value Valor a validar
     * @param string $type Tipo do ID Stripe (ex: 'price_id', 'product_id', 'subscription_id', etc.)
     * @param string|null $fieldName Nome do campo para mensagem de erro (opcional, usa $type se não fornecido)
     * @return array Array de erros (vazio se válido). Chave do array é o nome do campo.
     */
    public static function validateStripeId($value, string $type, ?string $fieldName = null): array
    {
        $errors = [];
        $fieldName = $fieldName ?? $type;
        
        // Valida se é string
        if (!is_string($value)) {
            $errors[$fieldName] = 'Deve ser uma string';
            return $errors;
        }
        
        // Valida tamanho
        if (strlen($value) < 5 || strlen($value) > 100) {
            $errors[$fieldName] = 'Deve ter entre 5 e 100 caracteres';
            return $errors;
        }
        
        // Mapeamento de tipos para padrões regex e exemplos
        $stripeIdPatterns = [
            'price_id' => ['pattern' => '/^price_[a-zA-Z0-9]{24,}$/', 'example' => 'price_xxxxx'],
            'product_id' => ['pattern' => '/^prod_[a-zA-Z0-9]{24,}$/', 'example' => 'prod_xxxxx'],
            'customer_id' => ['pattern' => '/^cus_[a-zA-Z0-9]{24,}$/', 'example' => 'cus_xxxxx'],
            'subscription_id' => ['pattern' => '/^sub_[a-zA-Z0-9]{24,}$/', 'example' => 'sub_xxxxx'],
            'payment_method_id' => ['pattern' => '/^pm_[a-zA-Z0-9]{24,}$/', 'example' => 'pm_xxxxx'],
            'payment_intent_id' => ['pattern' => '/^pi_[a-zA-Z0-9]{24,}$/', 'example' => 'pi_xxxxx'],
            'invoice_id' => ['pattern' => '/^in_[a-zA-Z0-9]{24,}$/', 'example' => 'in_xxxxx'],
            'invoice_item_id' => ['pattern' => '/^ii_[a-zA-Z0-9]{24,}$/', 'example' => 'ii_xxxxx'],
            'charge_id' => ['pattern' => '/^ch_[a-zA-Z0-9]{24,}$/', 'example' => 'ch_xxxxx'],
            'balance_transaction_id' => ['pattern' => '/^txn_[a-zA-Z0-9]{24,}$/', 'example' => 'txn_xxxxx'],
            'setup_intent_id' => ['pattern' => '/^seti_[a-zA-Z0-9]{24,}$/', 'example' => 'seti_xxxxx'],
            'checkout_session_id' => ['pattern' => '/^cs_[a-zA-Z0-9]{24,}$/', 'example' => 'cs_xxxxx'],
            'payout_id' => ['pattern' => '/^po_[a-zA-Z0-9]{24,}$/', 'example' => 'po_xxxxx'],
            'dispute_id' => ['pattern' => '/^dp_[a-zA-Z0-9]{24,}$/', 'example' => 'dp_xxxxx'],
            'tax_rate_id' => ['pattern' => '/^txr_[a-zA-Z0-9]{24,}$/', 'example' => 'txr_xxxxx'],
            'subscription_item_id' => ['pattern' => '/^si_[a-zA-Z0-9]{24,}$/', 'example' => 'si_xxxxx'],
            'promotion_code_id' => ['pattern' => '/^promo_[a-zA-Z0-9]{24,}$/', 'example' => 'promo_xxxxx'],
            'coupon_id' => ['pattern' => '/^coupon_[a-zA-Z0-9]{24,}$/', 'example' => 'coupon_xxxxx'],
        ];
        
        // Verifica se o tipo é conhecido
        if (isset($stripeIdPatterns[$type])) {
            $pattern = $stripeIdPatterns[$type]['pattern'];
            $example = $stripeIdPatterns[$type]['example'];
            
            if (!preg_match($pattern, $value)) {
                $errors[$fieldName] = "Formato inválido. Deve seguir o padrão: {$example}";
            }
        } else {
            // Tipo desconhecido - valida formato genérico Stripe (prefixo_xxxxx)
            // Permite qualquer prefixo minúsculo seguido de underscore e caracteres alfanuméricos
            if (!preg_match('/^[a-z]+_[a-zA-Z0-9]{24,}$/', $value)) {
                $errors[$fieldName] = 'Formato inválido. Deve seguir o padrão Stripe: prefixo_xxxxx (ex: price_xxxxx, prod_xxxxx)';
            }
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
     * Valida dados para registro de tenant e primeiro usuário
     * 
     * @param array $data Dados a validar
     * @return array Array de erros (vazio se válido)
     */
    public static function validateRegister(array $data): array
    {
        $errors = [];
        
        // clinic_name: obrigatório, nome da clínica
        if (!isset($data['clinic_name'])) {
            $errors['clinic_name'] = 'Obrigatório';
        } elseif (!is_string($data['clinic_name'])) {
            $errors['clinic_name'] = 'Deve ser uma string';
        } else {
            $clinicName = trim($data['clinic_name']);
            if (empty($clinicName)) {
                $errors['clinic_name'] = 'Obrigatório';
            } elseif (strlen($clinicName) > 255) {
                $errors['clinic_name'] = 'Muito longo (máximo 255 caracteres)';
            } elseif (strlen($clinicName) < 3) {
                $errors['clinic_name'] = 'Muito curto (mínimo 3 caracteres)';
            }
        }
        
        // clinic_slug: opcional, mas se fornecido deve ser válido
        if (isset($data['clinic_slug'])) {
            if (!is_string($data['clinic_slug'])) {
                $errors['clinic_slug'] = 'Deve ser uma string';
            } else {
                $slug = trim($data['clinic_slug']);
                if (!empty($slug) && !\App\Utils\SlugHelper::isValid($slug)) {
                    $errors['clinic_slug'] = 'Formato inválido. Use apenas letras minúsculas, números e hífens';
                }
            }
        }
        
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
        
        return $errors;
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
        
        // password: opcional no update (só valida se fornecido)
        if (isset($data['password'])) {
            if (!is_string($data['password'])) {
                $errors['password'] = 'Deve ser uma string';
            } else {
                $passwordError = self::validatePasswordStrength($data['password']);
                if ($passwordError) {
                    $errors['password'] = $passwordError;
                }
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
    
    /**
     * Valida dados para criação de Tax Rate
     * 
     * @param array $data Dados a validar
     * @return array Array de erros (vazio se válido)
     */
    public static function validateTaxRateCreate(array $data): array
    {
        $errors = [];
        
        // display_name: obrigatório, string, máximo 255 caracteres
        if (!isset($data['display_name'])) {
            $errors['display_name'] = 'Obrigatório';
        } elseif (!is_string($data['display_name'])) {
            $errors['display_name'] = 'Deve ser uma string';
        } elseif (strlen(trim($data['display_name'])) === 0) {
            $errors['display_name'] = 'Não pode ser vazio';
        } elseif (strlen($data['display_name']) > 255) {
            $errors['display_name'] = 'Muito longo (máximo 255 caracteres)';
        }
        
        // percentage: obrigatório, numérico, entre 0 e 100
        if (!isset($data['percentage'])) {
            $errors['percentage'] = 'Obrigatório';
        } elseif (!is_numeric($data['percentage'])) {
            $errors['percentage'] = 'Deve ser um número';
        } else {
            $percentage = (float)$data['percentage'];
            if ($percentage < 0 || $percentage > 100) {
                $errors['percentage'] = 'Deve estar entre 0 e 100';
            }
        }
        
        // inclusive: opcional, deve ser booleano
        if (isset($data['inclusive']) && !is_bool($data['inclusive'])) {
            $errors['inclusive'] = 'Deve ser true ou false';
        }
        
        // country: opcional, deve ser código ISO 3166-1 alpha-2 (2 letras)
        if (isset($data['country'])) {
            if (!is_string($data['country'])) {
                $errors['country'] = 'Deve ser uma string';
            } elseif (!preg_match('/^[A-Z]{2}$/', strtoupper($data['country']))) {
                $errors['country'] = 'Deve ser um código de país válido (ISO 3166-1 alpha-2, ex: BR, US)';
            }
        }
        
        // state: opcional, string, máximo 50 caracteres
        if (isset($data['state'])) {
            if (!is_string($data['state'])) {
                $errors['state'] = 'Deve ser uma string';
            } elseif (strlen($data['state']) > 50) {
                $errors['state'] = 'Muito longo (máximo 50 caracteres)';
            }
        }
        
        // description: opcional, string, máximo 500 caracteres
        if (isset($data['description'])) {
            if (!is_string($data['description'])) {
                $errors['description'] = 'Deve ser uma string';
            } elseif (strlen($data['description']) > 500) {
                $errors['description'] = 'Muito longo (máximo 500 caracteres)';
            }
        }
        
        // metadata: validação padrão
        if (isset($data['metadata'])) {
            $metadataErrors = self::validateMetadata($data['metadata'], 'metadata');
            if (!empty($metadataErrors)) {
                $errors = array_merge($errors, $metadataErrors);
            }
        }
        
        return $errors;
    }
    
    /**
     * Valida dados para criação de Promotion Code
     * 
     * @param array $data Dados a validar
     * @return array Array de erros (vazio se válido)
     */
    public static function validatePromotionCodeCreate(array $data): array
    {
        $errors = [];
        
        // coupon: obrigatório, deve ser coupon_id válido do Stripe
        if (!isset($data['coupon'])) {
            $errors['coupon'] = 'Obrigatório';
        } else {
            $couponErrors = self::validateStripeId($data['coupon'], 'coupon_id', 'coupon');
            if (!empty($couponErrors)) {
                $errors = array_merge($errors, $couponErrors);
            }
        }
        
        // code: opcional, mas se presente deve ser válido
        if (isset($data['code'])) {
            if (!is_string($data['code'])) {
                $errors['code'] = 'Deve ser uma string';
            } elseif (strlen(trim($data['code'])) === 0) {
                $errors['code'] = 'Não pode ser vazio';
            } elseif (strlen($data['code']) > 50) {
                $errors['code'] = 'Muito longo (máximo 50 caracteres)';
            } elseif (!preg_match('/^[A-Z0-9_-]+$/', strtoupper($data['code']))) {
                $errors['code'] = 'Deve conter apenas letras maiúsculas, números, underscore e hífen';
            }
        }
        
        // active: opcional, deve ser booleano
        if (isset($data['active']) && !is_bool($data['active'])) {
            $errors['active'] = 'Deve ser true ou false';
        }
        
        // customer: opcional, deve ser customer_id válido do Stripe
        if (isset($data['customer'])) {
            $customerErrors = self::validateStripeId($data['customer'], 'customer_id', 'customer');
            if (!empty($customerErrors)) {
                $errors = array_merge($errors, $customerErrors);
            }
        }
        
        // expires_at: opcional, deve ser timestamp Unix válido
        if (isset($data['expires_at'])) {
            if (is_string($data['expires_at'])) {
                // Tenta converter string para timestamp
                $timestamp = strtotime($data['expires_at']);
                if ($timestamp === false) {
                    $errors['expires_at'] = 'Data inválida. Use formato ISO 8601 ou timestamp Unix';
                } elseif ($timestamp < time()) {
                    $errors['expires_at'] = 'Data de expiração não pode ser no passado';
                }
            } elseif (!is_numeric($data['expires_at'])) {
                $errors['expires_at'] = 'Deve ser um timestamp Unix ou string de data';
            } else {
                $timestamp = (int)$data['expires_at'];
                if ($timestamp < time()) {
                    $errors['expires_at'] = 'Data de expiração não pode ser no passado';
                }
            }
        }
        
        // first_time_transaction: opcional, deve ser booleano
        if (isset($data['first_time_transaction']) && !is_bool($data['first_time_transaction'])) {
            $errors['first_time_transaction'] = 'Deve ser true ou false';
        }
        
        // max_redemptions: opcional, deve ser inteiro positivo
        if (isset($data['max_redemptions'])) {
            if (!is_numeric($data['max_redemptions'])) {
                $errors['max_redemptions'] = 'Deve ser um número';
            } else {
                $maxRedemptions = (int)$data['max_redemptions'];
                if ($maxRedemptions < 1 || $maxRedemptions > 1000000) {
                    $errors['max_redemptions'] = 'Deve estar entre 1 e 1000000';
                }
            }
        }
        
        // metadata: validação padrão
        if (isset($data['metadata'])) {
            $metadataErrors = self::validateMetadata($data['metadata'], 'metadata');
            if (!empty($metadataErrors)) {
                $errors = array_merge($errors, $metadataErrors);
            }
        }
        
        return $errors;
    }
    
    /**
     * Valida query params para listagem (limit, paginação, etc.)
     * 
     * @param array $queryParams Parâmetros da query
     * @param array $allowedFilters Filtros permitidos (opcional)
     * @return array ['limit' => int, 'errors' => array, 'filters' => array]
     */
    public static function validateListParams(array $queryParams, array $allowedFilters = []): array
    {
        $errors = [];
        $limit = 10; // Padrão
        $filters = [];
        
        // limit: opcional, deve ser inteiro entre 1 e 100
        if (isset($queryParams['limit'])) {
            if (!is_numeric($queryParams['limit'])) {
                $errors['limit'] = 'Deve ser um número';
            } else {
                $limit = (int)$queryParams['limit'];
                if ($limit < 1 || $limit > 100) {
                    $errors['limit'] = 'Deve estar entre 1 e 100';
                }
            }
        }
        
        // starting_after: opcional, deve ser string não vazia
        if (isset($queryParams['starting_after'])) {
            if (!is_string($queryParams['starting_after']) || empty(trim($queryParams['starting_after']))) {
                $errors['starting_after'] = 'Deve ser uma string não vazia';
            } else {
                $filters['starting_after'] = trim($queryParams['starting_after']);
            }
        }
        
        // ending_before: opcional, deve ser string não vazia
        if (isset($queryParams['ending_before'])) {
            if (!is_string($queryParams['ending_before']) || empty(trim($queryParams['ending_before']))) {
                $errors['ending_before'] = 'Deve ser uma string não vazia';
            } else {
                $filters['ending_before'] = trim($queryParams['ending_before']);
            }
        }
        
        // Valida filtros permitidos
        foreach ($allowedFilters as $filterName => $filterType) {
            if (isset($queryParams[$filterName])) {
                $value = $queryParams[$filterName];
                
                switch ($filterType) {
                    case 'stripe_id':
                        // Valida formato genérico de ID Stripe
                        if (!is_string($value) || !preg_match('/^[a-z]+_[a-zA-Z0-9]{24,}$/', $value)) {
                            $errors[$filterName] = 'Formato inválido. Deve ser um ID válido do Stripe';
                        } else {
                            $filters[$filterName] = $value;
                        }
                        break;
                        
                    case 'boolean':
                        $filters[$filterName] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                        break;
                        
                    case 'integer':
                        if (!is_numeric($value)) {
                            $errors[$filterName] = 'Deve ser um número';
                        } else {
                            $filters[$filterName] = (int)$value;
                        }
                        break;
                        
                    case 'string':
                        if (!is_string($value)) {
                            $errors[$filterName] = 'Deve ser uma string';
                        } else {
                            $filters[$filterName] = trim($value);
                        }
                        break;
                }
            }
        }
        
        return [
            'limit' => $limit,
            'errors' => $errors,
            'filters' => $filters
        ];
    }
    
    /**
     * Valida ID de dispute
     * 
     * @param mixed $disputeId Dispute ID a validar
     * @return array Array de erros (vazio se válido)
     */
    public static function validateDisputeId($disputeId): array
    {
        return self::validateStripeId($disputeId, 'dispute_id');
    }
    
    /**
     * Valida ID de charge
     * 
     * @param mixed $chargeId Charge ID a validar
     * @return array Array de erros (vazio se válido)
     */
    public static function validateChargeId($chargeId): array
    {
        return self::validateStripeId($chargeId, 'charge_id');
    }
    
    /**
     * Valida dados para criação de Payment Intent
     * 
     * @param array $data Dados a validar
     * @return array Array de erros (vazio se válido)
     */
    public static function validatePaymentIntentCreate(array $data): array
    {
        $errors = [];
        
        // amount: obrigatório, deve ser numérico e positivo
        if (!isset($data['amount'])) {
            $errors['amount'] = 'Obrigatório';
        } elseif (!is_numeric($data['amount'])) {
            $errors['amount'] = 'Deve ser um número';
        } else {
            $amount = (int)$data['amount'];
            if ($amount < 1) {
                $errors['amount'] = 'Deve ser maior que zero';
            } elseif ($amount > 99999999) { // Limite de ~R$ 999.999,99
                $errors['amount'] = 'Valor muito alto (máximo: 99999999 centavos)';
            }
        }
        
        // currency: obrigatório, deve ser código de moeda válido (3 letras)
        if (!isset($data['currency'])) {
            $errors['currency'] = 'Obrigatório';
        } elseif (!is_string($data['currency'])) {
            $errors['currency'] = 'Deve ser uma string';
        } elseif (strlen($data['currency']) !== 3) {
            $errors['currency'] = 'Deve ser um código de moeda válido (3 letras, ex: BRL, USD)';
        } elseif (!preg_match('/^[A-Z]{3}$/', strtoupper($data['currency']))) {
            $errors['currency'] = 'Formato inválido (deve conter apenas letras maiúsculas)';
        }
        
        // customer_id: opcional, mas se presente deve ser ID Stripe válido
        if (isset($data['customer_id'])) {
            $customerIdErrors = self::validateStripeId($data['customer_id'], 'customer_id');
            if (!empty($customerIdErrors)) {
                $errors = array_merge($errors, $customerIdErrors);
            }
        }
        
        // payment_method: opcional, mas se presente deve ser ID Stripe válido
        if (isset($data['payment_method'])) {
            $paymentMethodErrors = self::validateStripeId($data['payment_method'], 'payment_method_id');
            if (!empty($paymentMethodErrors)) {
                $errors = array_merge($errors, $paymentMethodErrors);
            }
        }
        
        // description: opcional, mas se presente deve ser string válida
        if (isset($data['description'])) {
            if (!is_string($data['description'])) {
                $errors['description'] = 'Deve ser uma string';
            } elseif (strlen($data['description']) > 500) {
                $errors['description'] = 'Muito longo (máximo 500 caracteres)';
            }
        }
        
        // confirm: opcional, deve ser boolean
        if (isset($data['confirm'])) {
            if (!is_bool($data['confirm'])) {
                $errors['confirm'] = 'Deve ser true ou false';
            }
        }
        
        // capture_method: opcional, valores permitidos
        if (isset($data['capture_method'])) {
            $allowedMethods = ['automatic', 'manual'];
            if (!is_string($data['capture_method']) || !in_array($data['capture_method'], $allowedMethods, true)) {
                $errors['capture_method'] = 'Deve ser "automatic" ou "manual"';
            }
        }
        
        // metadata: validação padrão
        if (isset($data['metadata'])) {
            $metadataError = self::validateMetadataInternal($data['metadata']);
            if ($metadataError !== null) {
                $errors['metadata'] = $metadataError;
            }
        }
        
        return $errors;
    }
    
    /**
     * Valida dados para criação de Checkout Session
     * 
     * @param array $data Dados a validar
     * @return array Array de erros (vazio se válido)
     */
    public static function validateCheckoutCreate(array $data): array
    {
        $errors = [];
        
        // success_url: obrigatório, deve ser URL válida
        if (!isset($data['success_url'])) {
            $errors['success_url'] = 'Obrigatório';
        } elseif (!is_string($data['success_url'])) {
            $errors['success_url'] = 'Deve ser uma string';
        } elseif (!filter_var($data['success_url'], FILTER_VALIDATE_URL)) {
            $errors['success_url'] = 'URL inválida';
        } elseif (strlen($data['success_url']) > 2048) {
            $errors['success_url'] = 'URL muito longa (máximo 2048 caracteres)';
        }
        
        // cancel_url: obrigatório, deve ser URL válida
        if (!isset($data['cancel_url'])) {
            $errors['cancel_url'] = 'Obrigatório';
        } elseif (!is_string($data['cancel_url'])) {
            $errors['cancel_url'] = 'Deve ser uma string';
        } elseif (!filter_var($data['cancel_url'], FILTER_VALIDATE_URL)) {
            $errors['cancel_url'] = 'URL inválida';
        } elseif (strlen($data['cancel_url']) > 2048) {
            $errors['cancel_url'] = 'URL muito longa (máximo 2048 caracteres)';
        }
        
        // line_items ou price_id: pelo menos um deve estar presente
        if (empty($data['line_items']) && empty($data['price_id'])) {
            $errors['line_items'] = 'Obrigatório (ou forneça price_id)';
            $errors['price_id'] = 'Obrigatório (ou forneça line_items)';
        }
        
        // line_items: se presente, deve ser array válido
        if (isset($data['line_items'])) {
            if (!is_array($data['line_items'])) {
                $errors['line_items'] = 'Deve ser um array';
            } elseif (count($data['line_items']) === 0) {
                $errors['line_items'] = 'Não pode estar vazio';
            } elseif (count($data['line_items']) > 100) {
                $errors['line_items'] = 'Máximo de 100 itens permitidos';
            } else {
                // Valida cada item
                foreach ($data['line_items'] as $index => $item) {
                    if (!is_array($item)) {
                        $errors["line_items.{$index}"] = 'Deve ser um objeto';
                        continue;
                    }
                    
                    if (empty($item['price'])) {
                        $errors["line_items.{$index}.price"] = 'Campo price é obrigatório';
                    } else {
                        $priceErrors = self::validateStripeId($item['price'], 'price_id');
                        if (!empty($priceErrors)) {
                            foreach ($priceErrors as $key => $msg) {
                                $errors["line_items.{$index}.price"] = $msg;
                            }
                        }
                    }
                    
                    if (isset($item['quantity'])) {
                        if (!is_numeric($item['quantity'])) {
                            $errors["line_items.{$index}.quantity"] = 'Deve ser um número';
                        } else {
                            $quantity = (int)$item['quantity'];
                            if ($quantity < 1 || $quantity > 10000) {
                                $errors["line_items.{$index}.quantity"] = 'Deve estar entre 1 e 10000';
                            }
                        }
                    }
                }
            }
        }
        
        // price_id: se presente, deve ser ID Stripe válido
        if (isset($data['price_id'])) {
            $priceIdErrors = self::validateStripeId($data['price_id'], 'price_id');
            if (!empty($priceIdErrors)) {
                $errors = array_merge($errors, $priceIdErrors);
            }
        }
        
        // quantity: opcional, mas se presente deve ser válido
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
        
        // customer_id: opcional, pode ser ID nosso ou Stripe
        if (isset($data['customer_id'])) {
            // Se for numérico, é ID do nosso banco (válido)
            // Se for string, deve ser ID Stripe válido
            if (is_string($data['customer_id']) && !is_numeric($data['customer_id'])) {
                $customerIdErrors = self::validateStripeId($data['customer_id'], 'customer_id');
                if (!empty($customerIdErrors)) {
                    $errors = array_merge($errors, $customerIdErrors);
                }
            } elseif (!is_numeric($data['customer_id']) && !is_string($data['customer_id'])) {
                $errors['customer_id'] = 'Deve ser um ID válido (número ou string)';
            }
        }
        
        // mode: opcional, valores permitidos
        if (isset($data['mode'])) {
            $allowedModes = ['payment', 'subscription', 'setup'];
            if (!is_string($data['mode']) || !in_array($data['mode'], $allowedModes, true)) {
                $errors['mode'] = 'Deve ser "payment", "subscription" ou "setup"';
            }
        }
        
        // payment_method_types: opcional, deve ser array
        if (isset($data['payment_method_types'])) {
            if (!is_array($data['payment_method_types'])) {
                $errors['payment_method_types'] = 'Deve ser um array';
            } elseif (count($data['payment_method_types']) > 10) {
                $errors['payment_method_types'] = 'Máximo de 10 tipos permitidos';
            } else {
                $allowedTypes = ['card', 'ideal', 'sepa_debit', 'sofort', 'bancontact', 'p24', 'giropay', 'eps', 'alipay', 'wechat_pay'];
                foreach ($data['payment_method_types'] as $index => $type) {
                    if (!is_string($type) || !in_array($type, $allowedTypes, true)) {
                        $errors["payment_method_types.{$index}"] = 'Tipo de método de pagamento inválido';
                    }
                }
            }
        }
        
        // metadata: validação padrão
        if (isset($data['metadata'])) {
            $metadataError = self::validateMetadataInternal($data['metadata']);
            if ($metadataError !== null) {
                $errors['metadata'] = $metadataError;
            }
        }
        
        return $errors;
    }
    
    /**
     * Valida estrutura de endereço
     * 
     * @param mixed $address Dados do endereço
     * @param string $fieldName Nome do campo (para mensagens de erro)
     * @return array Array de erros (vazio se válido)
     */
    public static function validateAddress($address, string $fieldName = 'address'): array
    {
        $errors = [];
        
        if (!is_array($address)) {
            return [$fieldName => 'Deve ser um objeto'];
        }
        
        // line1: opcional, mas se presente deve ser válido
        if (isset($address['line1'])) {
            if (!is_string($address['line1'])) {
                $errors["{$fieldName}.line1"] = 'Deve ser uma string';
            } elseif (strlen($address['line1']) > 200) {
                $errors["{$fieldName}.line1"] = 'Muito longo (máximo 200 caracteres)';
            }
        }
        
        // line2: opcional
        if (isset($address['line2'])) {
            if (!is_string($address['line2'])) {
                $errors["{$fieldName}.line2"] = 'Deve ser uma string';
            } elseif (strlen($address['line2']) > 200) {
                $errors["{$fieldName}.line2"] = 'Muito longo (máximo 200 caracteres)';
            }
        }
        
        // city: opcional
        if (isset($address['city'])) {
            if (!is_string($address['city'])) {
                $errors["{$fieldName}.city"] = 'Deve ser uma string';
            } elseif (strlen($address['city']) > 100) {
                $errors["{$fieldName}.city"] = 'Muito longo (máximo 100 caracteres)';
            }
        }
        
        // state: opcional
        if (isset($address['state'])) {
            if (!is_string($address['state'])) {
                $errors["{$fieldName}.state"] = 'Deve ser uma string';
            } elseif (strlen($address['state']) > 100) {
                $errors["{$fieldName}.state"] = 'Muito longo (máximo 100 caracteres)';
            }
        }
        
        // postal_code: opcional
        if (isset($address['postal_code'])) {
            if (!is_string($address['postal_code'])) {
                $errors["{$fieldName}.postal_code"] = 'Deve ser uma string';
            } elseif (strlen($address['postal_code']) > 20) {
                $errors["{$fieldName}.postal_code"] = 'Muito longo (máximo 20 caracteres)';
            }
        }
        
        // country: opcional, deve ser código ISO 3166-1 alpha-2 (2 letras)
        if (isset($address['country'])) {
            if (!is_string($address['country'])) {
                $errors["{$fieldName}.country"] = 'Deve ser uma string';
            } elseif (strlen($address['country']) !== 2) {
                $errors["{$fieldName}.country"] = 'Deve ser um código de país válido (2 letras, ex: BR, US)';
            } elseif (!preg_match('/^[A-Z]{2}$/', strtoupper($address['country']))) {
                $errors["{$fieldName}.country"] = 'Formato inválido (deve conter apenas letras maiúsculas)';
            }
        }
        
        return $errors;
    }
}

