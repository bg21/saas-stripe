/**
 * ✅ MELHORIA: Validações Frontend que espelham as validações do backend
 * Garante consistência entre frontend e backend
 */

/**
 * Valida força da senha (espelha Validator::validatePasswordStrength)
 * @param {string} password - Senha a validar
 * @returns {string|null} Mensagem de erro ou null se válida
 */
function validatePasswordStrength(password) {
    // Tamanho mínimo: 12 caracteres
    if (password.length < 12) {
        return 'Senha deve ter no mínimo 12 caracteres';
    }
    
    // Tamanho máximo: 128 caracteres
    if (password.length > 128) {
        return 'Senha muito longa (máximo 128 caracteres)';
    }
    
    // Deve conter pelo menos uma letra maiúscula
    if (!/[A-Z]/.test(password)) {
        return 'Senha deve conter pelo menos uma letra maiúscula';
    }
    
    // Deve conter pelo menos uma letra minúscula
    if (!/[a-z]/.test(password)) {
        return 'Senha deve conter pelo menos uma letra minúscula';
    }
    
    // Deve conter pelo menos um número
    if (!/[0-9]/.test(password)) {
        return 'Senha deve conter pelo menos um número';
    }
    
    // Deve conter pelo menos um caractere especial
    if (!/[!@#$%^&*()_+\-=\[\]{};'\\:"|,.<>\/?]/.test(password)) {
        return 'Senha deve conter pelo menos um caractere especial (!@#$%^&*()_+-=[]{};\':"|,.<>/?).';
    }
    
    // Verifica senhas comuns/fracas (lista básica)
    const commonPasswords = [
        'password', 'password123', '12345678', '123456789', '1234567890',
        'qwerty', 'abc123', 'admin', 'letmein', 'welcome', 'monkey',
        '1234567', 'sunshine', 'princess', 'football', 'iloveyou'
    ];
    
    if (commonPasswords.includes(password.toLowerCase())) {
        return 'Esta senha é muito comum e não é segura. Escolha uma senha mais forte.';
    }
    
    // Verifica padrões simples (ex: aaa, 123, abc)
    if (/(.)\1{2,}/.test(password)) {
        return 'Senha não pode conter caracteres repetidos consecutivos (ex: aaa, 111)';
    }
    
    // Verifica sequências simples (ex: 123, abc)
    if (/(012|123|234|345|456|567|678|789|abc|bcd|cde|def|efg|fgh|ghi|hij|ijk|jkl|klm|lmn|mno|nop|opq|pqr|qrs|rst|stu|tuv|uvw|vwx|wxy|xyz)/i.test(password)) {
        return 'Senha não pode conter sequências simples (ex: 123, abc)';
    }
    
    return null; // Senha válida
}

/**
 * Valida email (espelha validação do backend)
 * @param {string} email - Email a validar
 * @returns {string|null} Mensagem de erro ou null se válido
 */
function validateEmail(email) {
    if (!email || email.trim() === '') {
        return 'Email é obrigatório';
    }
    
    const trimmedEmail = email.trim();
    
    // Tamanho máximo: 255 caracteres
    if (trimmedEmail.length > 255) {
        return 'Email muito longo (máximo 255 caracteres)';
    }
    
    // Validação de formato de email
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(trimmedEmail)) {
        return 'Formato de email inválido';
    }
    
    return null; // Email válido
}

/**
 * Valida nome (espelha validação do backend)
 * @param {string} name - Nome a validar
 * @param {boolean} required - Se o nome é obrigatório
 * @returns {string|null} Mensagem de erro ou null se válido
 */
function validateName(name, required = false) {
    if (required && (!name || name.trim() === '')) {
        return 'Nome é obrigatório';
    }
    
    if (name && name.trim() !== '') {
        // Tamanho máximo: 255 caracteres
        if (name.trim().length > 255) {
            return 'Nome muito longo (máximo 255 caracteres)';
        }
        
        // Tamanho mínimo: 2 caracteres
        if (name.trim().length < 2) {
            return 'Nome deve ter no mínimo 2 caracteres';
        }
    }
    
    return null; // Nome válido
}

/**
 * Valida role (espelha validação do backend)
 * @param {string} role - Role a validar
 * @returns {string|null} Mensagem de erro ou null se válido
 */
function validateRole(role) {
    const allowedRoles = ['admin', 'viewer', 'editor'];
    
    if (!role) {
        return 'Role é obrigatório';
    }
    
    if (!allowedRoles.includes(role)) {
        return `Role inválido. Valores permitidos: ${allowedRoles.join(', ')}`;
    }
    
    return null; // Role válido
}

/**
 * Aplica validação em tempo real em um campo de formulário
 * @param {HTMLInputElement|HTMLSelectElement} field - Campo a validar
 * @param {Function} validator - Função de validação
 * @param {boolean} showFeedback - Se deve mostrar feedback visual
 */
function applyFieldValidation(field, validator, showFeedback = true) {
    field.addEventListener('blur', function() {
        const value = this.value;
        const error = validator(value);
        
        if (showFeedback) {
            if (error) {
                this.classList.add('is-invalid');
                this.classList.remove('is-valid');
                
                // Remove feedback anterior se existir
                let feedback = this.parentElement.querySelector('.invalid-feedback');
                if (!feedback) {
                    feedback = document.createElement('div');
                    feedback.className = 'invalid-feedback';
                    this.parentElement.appendChild(feedback);
                }
                feedback.textContent = error;
            } else if (value) {
                this.classList.remove('is-invalid');
                this.classList.add('is-valid');
            } else {
                this.classList.remove('is-invalid', 'is-valid');
            }
        }
    });
    
    // Valida também no input para feedback em tempo real
    if (field.type === 'password' || field.type === 'email' || field.type === 'text') {
        field.addEventListener('input', function() {
            const value = this.value;
            if (value) {
                const error = validator(value);
                if (error) {
                    this.classList.add('is-invalid');
                    this.classList.remove('is-valid');
                } else {
                    this.classList.remove('is-invalid');
                    this.classList.add('is-valid');
                }
            }
        });
    }
}

/**
 * Valida formulário completo antes de submeter
 * @param {HTMLFormElement} form - Formulário a validar
 * @param {Object} validators - Objeto com validadores para cada campo { fieldName: validatorFunction }
 * @returns {Object} { valid: boolean, errors: Object }
 */
function validateForm(form, validators) {
    const errors = {};
    let isValid = true;
    
    for (const [fieldName, validator] of Object.entries(validators)) {
        const field = form.querySelector(`[name="${fieldName}"]`);
        if (field) {
            const value = field.value;
            const error = validator(value);
            
            if (error) {
                errors[fieldName] = error;
                isValid = false;
                
                field.classList.add('is-invalid');
                field.classList.remove('is-valid');
                
                // Atualiza feedback
                let feedback = field.parentElement.querySelector('.invalid-feedback');
                if (!feedback) {
                    feedback = document.createElement('div');
                    feedback.className = 'invalid-feedback';
                    field.parentElement.appendChild(feedback);
                }
                feedback.textContent = error;
            } else {
                field.classList.remove('is-invalid');
                if (value) {
                    field.classList.add('is-valid');
                }
            }
        }
    }
    
    return { valid: isValid, errors };
}

/**
 * ✅ NOVO: Valida formatos de IDs do Stripe
 * Padrões Stripe: prefixo_ seguido de caracteres alfanuméricos
 */

// Padrões de validação para cada tipo de ID do Stripe
const STRIPE_ID_PATTERNS = {
    price_id: /^price_[a-zA-Z0-9]+$/,
    product_id: /^prod_[a-zA-Z0-9]+$/,
    customer_id: /^cus_[a-zA-Z0-9]+$/,
    subscription_id: /^sub_[a-zA-Z0-9]+$/,
    payment_method_id: /^pm_[a-zA-Z0-9]+$/,
    payment_intent_id: /^pi_[a-zA-Z0-9]+$/,
    invoice_id: /^in_[a-zA-Z0-9]+$/,
    charge_id: /^ch_[a-zA-Z0-9]+$/,
    coupon_id: /^coupon_[a-zA-Z0-9]+$/,
    promotion_code_id: /^promo_[a-zA-Z0-9]+$/,
    setup_intent_id: /^seti_[a-zA-Z0-9]+$/,
    subscription_item_id: /^si_[a-zA-Z0-9]+$/,
    tax_rate_id: /^txr_[a-zA-Z0-9]+$/,
    invoice_item_id: /^ii_[a-zA-Z0-9]+$/,
    checkout_session_id: /^cs_[a-zA-Z0-9]+$/,
    payout_id: /^po_[a-zA-Z0-9]+$/,
    dispute_id: /^dp_[a-zA-Z0-9]+$/,
    balance_transaction_id: /^txn_[a-zA-Z0-9]+$/
};

/**
 * Valida um ID do Stripe
 * @param {string} value - Valor a validar
 * @param {string} type - Tipo do ID (price_id, product_id, etc.)
 * @param {boolean} required - Se o campo é obrigatório
 * @returns {string|null} Mensagem de erro ou null se válido
 */
function validateStripeId(value, type, required = false) {
    // Se não é obrigatório e está vazio, é válido
    if (!required && (!value || value.trim() === '')) {
        return null;
    }
    
    // Se é obrigatório e está vazio
    if (required && (!value || value.trim() === '')) {
        const fieldName = type.replace('_id', '').replace('_', ' ');
        return `${fieldName.charAt(0).toUpperCase() + fieldName.slice(1)} é obrigatório`;
    }
    
    const trimmedValue = value.trim();
    
    // Obtém o padrão para o tipo
    const pattern = STRIPE_ID_PATTERNS[type];
    if (!pattern) {
        // Se não há padrão específico, valida formato genérico Stripe (prefixo_xxxxx)
        const genericPattern = /^[a-z]+_[a-zA-Z0-9]+$/;
        if (!genericPattern.test(trimmedValue)) {
            return 'Formato inválido. Deve seguir o padrão: prefixo_xxxxx';
        }
        return null;
    }
    
    // Valida contra o padrão específico
    if (!pattern.test(trimmedValue)) {
        const examples = {
            price_id: 'price_xxxxx',
            product_id: 'prod_xxxxx',
            customer_id: 'cus_xxxxx',
            subscription_id: 'sub_xxxxx',
            payment_method_id: 'pm_xxxxx'
        };
        
        const example = examples[type] || 'prefixo_xxxxx';
        return `Formato inválido. Use: ${example}`;
    }
    
    return null; // Válido
}

/**
 * Aplica validação de formato Stripe em um campo de formulário
 * @param {HTMLInputElement|HTMLSelectElement} field - Campo a validar
 * @param {string} type - Tipo do ID Stripe (price_id, product_id, etc.)
 * @param {boolean} required - Se o campo é obrigatório
 * @param {string} errorElementId - ID do elemento para mostrar erro (opcional)
 */
function applyStripeIdValidation(field, type, required = false, errorElementId = null) {
    if (!field) return;
    
    // Valida no input (tempo real)
    field.addEventListener('input', function() {
        const value = this.value.trim();
        const error = validateStripeId(value, type, required && value !== '');
        
        if (error) {
            this.classList.add('is-invalid');
            this.classList.remove('is-valid');
            
            // Atualiza feedback
            let feedback = errorElementId 
                ? document.getElementById(errorElementId)
                : this.parentElement.querySelector('.invalid-feedback');
            
            if (!feedback && !errorElementId) {
                feedback = document.createElement('div');
                feedback.className = 'invalid-feedback';
                this.parentElement.appendChild(feedback);
            }
            
            if (feedback) {
                feedback.textContent = error;
            }
        } else if (value) {
            this.classList.remove('is-invalid');
            this.classList.add('is-valid');
            
            // Limpa feedback
            const feedback = errorElementId 
                ? document.getElementById(errorElementId)
                : this.parentElement.querySelector('.invalid-feedback');
            if (feedback) {
                feedback.textContent = '';
            }
        } else {
            this.classList.remove('is-invalid', 'is-valid');
        }
    });
    
    // Valida no blur (quando sai do campo)
    field.addEventListener('blur', function() {
        const value = this.value.trim();
        const error = validateStripeId(value, type, required);
        
        if (error) {
            this.classList.add('is-invalid');
            this.classList.remove('is-valid');
            
            let feedback = errorElementId 
                ? document.getElementById(errorElementId)
                : this.parentElement.querySelector('.invalid-feedback');
            
            if (!feedback && !errorElementId) {
                feedback = document.createElement('div');
                feedback.className = 'invalid-feedback';
                this.parentElement.appendChild(feedback);
            }
            
            if (feedback) {
                feedback.textContent = error;
            }
        } else if (value) {
            this.classList.remove('is-invalid');
            this.classList.add('is-valid');
        } else {
            this.classList.remove('is-invalid', 'is-valid');
        }
    });
}

/**
 * Valida múltiplos campos de IDs Stripe de uma vez
 * @param {Object} fields - Objeto com campos { fieldName: { element, type, required } }
 * @returns {Object} { valid: boolean, errors: Object }
 */
function validateStripeIds(fields) {
    const errors = {};
    let isValid = true;
    
    for (const [fieldName, config] of Object.entries(fields)) {
        const { element, type, required = false } = config;
        if (!element) continue;
        
        const value = element.value.trim();
        const error = validateStripeId(value, type, required);
        
        if (error) {
            errors[fieldName] = error;
            isValid = false;
            
            element.classList.add('is-invalid');
            element.classList.remove('is-valid');
            
            // Atualiza feedback
            let feedback = element.parentElement.querySelector('.invalid-feedback');
            if (!feedback) {
                feedback = document.createElement('div');
                feedback.className = 'invalid-feedback';
                element.parentElement.appendChild(feedback);
            }
            feedback.textContent = error;
        } else {
            element.classList.remove('is-invalid');
            if (value) {
                element.classList.add('is-valid');
            }
        }
    }
    
    return { valid: isValid, errors };
}

