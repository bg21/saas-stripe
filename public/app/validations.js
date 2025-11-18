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

