<?php
/**
 * View de Registro de Usuários
 */
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro - Sistema SaaS</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .register-card {
            max-width: 500px;
            width: 100%;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        .password-strength {
            height: 4px;
            border-radius: 2px;
            margin-top: 5px;
            transition: all 0.3s;
        }
        .strength-weak { background: #dc3545; width: 33%; }
        .strength-medium { background: #ffc107; width: 66%; }
        .strength-strong { background: #28a745; width: 100%; }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card register-card">
                    <div class="card-body p-5">
                        <div class="text-center mb-4">
                            <i class="bi bi-person-plus fs-1 text-primary"></i>
                            <h2 class="mt-3">Criar Conta</h2>
                            <p class="text-muted">Preencha os dados para criar sua conta</p>
                        </div>

                        <div id="alertContainer"></div>

                        <!-- Seletor de tipo de registro -->
                        <div class="mb-4">
                            <label class="form-label fw-bold">Tipo de Registro *</label>
                            <div class="btn-group w-100" role="group">
                                <input type="radio" class="btn-check" name="user_type" id="owner" value="owner" checked>
                                <label class="btn btn-outline-primary" for="owner">
                                    <i class="bi bi-building"></i> Sou Dono da Clínica
                                </label>
                                
                                <input type="radio" class="btn-check" name="user_type" id="employee" value="employee">
                                <label class="btn btn-outline-primary" for="employee">
                                    <i class="bi bi-person"></i> Sou Funcionário
                                </label>
                            </div>
                        </div>

                        <form id="registerForm">
                            <div class="mb-3">
                                <label class="form-label">Nome Completo *</label>
                                <input type="text" class="form-control" name="name" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Email *</label>
                                <input type="email" class="form-control" name="email" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Senha *</label>
                                <input type="password" class="form-control" name="password" id="password" required minlength="12">
                                <div class="password-strength" id="passwordStrength"></div>
                                <small class="text-muted">Mínimo 12 caracteres, com letras maiúsculas, minúsculas, números e caracteres especiais</small>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Confirmar Senha *</label>
                                <input type="password" class="form-control" name="password_confirm" id="passwordConfirm" required>
                                <div id="passwordMatch" class="mt-1"></div>
                            </div>

                            <!-- Campos para DONO DA CLÍNICA -->
                            <div id="ownerFields">
                                <div class="mb-3">
                                    <label class="form-label">Nome da Clínica *</label>
                                    <input type="text" class="form-control" name="clinic_name" id="clinic_name" placeholder="ex: Cão que Mia">
                                    <small class="text-muted">Nome da sua clínica veterinária</small>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Slug da Clínica</label>
                                    <input type="text" class="form-control" name="clinic_slug" id="clinic_slug" pattern="[a-z0-9-]+" placeholder="ex: cao-que-mia">
                                    <small class="text-muted">Opcional: será gerado automaticamente se não informado. Use apenas letras minúsculas, números e hífens</small>
                                </div>
                            </div>

                            <!-- Campos para FUNCIONÁRIO -->
                            <div id="employeeFields" style="display: none;">
                                <div class="mb-3">
                                    <label class="form-label">Slug da Clínica *</label>
                                    <input type="text" class="form-control" name="tenant_slug" id="tenant_slug" pattern="[a-z0-9-]+" placeholder="ex: cao-que-mia">
                                    <small class="text-muted">Informe o slug da clínica onde você trabalha (ex: cao-que-mia)</small>
                                </div>
                            </div>

                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="acceptTerms" required>
                                <label class="form-check-label" for="acceptTerms">
                                    Aceito os <a href="#" target="_blank">termos e condições</a>
                                </label>
                            </div>

                            <button type="submit" class="btn btn-primary w-100 mb-3">
                                <i class="bi bi-person-plus"></i> Criar Conta
                            </button>

                            <div class="text-center">
                                <p class="mb-0">
                                    Já tem uma conta? 
                                    <a href="/login">Fazer login</a>
                                </p>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const API_URL = <?php echo json_encode($apiUrl ?? '', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

        // Validação de força de senha
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const strength = document.getElementById('passwordStrength');
            
            if (password.length === 0) {
                strength.className = 'password-strength';
                strength.style.width = '0%';
                return;
            }
            
            let strengthLevel = 0;
            if (password.length >= 6) strengthLevel++;
            if (password.length >= 8) strengthLevel++;
            if (/[A-Z]/.test(password)) strengthLevel++;
            if (/[0-9]/.test(password)) strengthLevel++;
            if (/[^A-Za-z0-9]/.test(password)) strengthLevel++;
            
            if (strengthLevel <= 2) {
                strength.className = 'password-strength strength-weak';
            } else if (strengthLevel <= 3) {
                strength.className = 'password-strength strength-medium';
            } else {
                strength.className = 'password-strength strength-strong';
            }
        });

        // Validação de correspondência de senhas
        document.getElementById('passwordConfirm').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirm = this.value;
            const matchDiv = document.getElementById('passwordMatch');
            
            if (confirm.length === 0) {
                matchDiv.innerHTML = '';
                return;
            }
            
            if (password === confirm) {
                matchDiv.innerHTML = '<small class="text-success"><i class="bi bi-check-circle"></i> Senhas coincidem</small>';
            } else {
                matchDiv.innerHTML = '<small class="text-danger"><i class="bi bi-x-circle"></i> Senhas não coincidem</small>';
            }
        });

        // Toggle entre dono e funcionário
        document.querySelectorAll('input[name="user_type"]').forEach(radio => {
            radio.addEventListener('change', function() {
                const isOwner = this.value === 'owner';
                document.getElementById('ownerFields').style.display = isOwner ? 'block' : 'none';
                document.getElementById('employeeFields').style.display = isOwner ? 'none' : 'block';
                
                // Atualiza campos obrigatórios
                document.getElementById('clinic_name').required = isOwner;
                document.getElementById('clinic_slug').required = false; // Sempre opcional para dono
                document.getElementById('tenant_slug').required = !isOwner;
                
                // Limpa campos quando troca
                if (isOwner) {
                    document.getElementById('tenant_slug').value = '';
                } else {
                    document.getElementById('clinic_name').value = '';
                    document.getElementById('clinic_slug').value = '';
                }
            });
        });

        // Validação de slug em tempo real (para ambos os campos)
        function setupSlugValidation(inputId) {
            const input = document.getElementById(inputId);
            if (input) {
                input.addEventListener('input', function() {
                    const slug = this.value.toLowerCase().trim();
                    const validSlug = slug.replace(/[^a-z0-9-]/g, '');
                    if (slug !== validSlug) {
                        this.value = validSlug;
                    }
                });
            }
        }
        
        setupSlugValidation('clinic_slug');
        setupSlugValidation('tenant_slug');

        // Geração automática de slug a partir do nome da clínica
        document.getElementById('clinic_name').addEventListener('input', function() {
            const clinicName = this.value;
            const slugInput = document.getElementById('clinic_slug');
            
            // Só gera automaticamente se o campo slug estiver vazio
            if (slugInput.value === '') {
                const autoSlug = clinicName
                    .toLowerCase()
                    .normalize('NFD')
                    .replace(/[\u0300-\u036f]/g, '') // Remove acentos
                    .replace(/[^a-z0-9\s-]/g, '') // Remove caracteres especiais
                    .replace(/\s+/g, '-') // Substitui espaços por hífens
                    .replace(/-+/g, '-') // Remove hífens duplicados
                    .replace(/^-|-$/g, ''); // Remove hífens no início/fim
                
                slugInput.value = autoSlug;
            }
        });

        // Form de registro
        document.getElementById('registerForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const formData = new FormData(e.target);
            const userType = formData.get('user_type');
            const password = formData.get('password');
            const passwordConfirm = formData.get('password_confirm');
            
            if (password !== passwordConfirm) {
                showAlert('As senhas não coincidem!', 'danger');
                return;
            }
            
            let data = {
                name: formData.get('name'),
                email: formData.get('email'),
                password: password
            };
            
            let endpoint = '';
            let validationErrors = [];
            
            if (userType === 'owner') {
                // Registro de DONO DA CLÍNICA
                const clinicName = formData.get('clinic_name')?.trim();
                const clinicSlug = formData.get('clinic_slug')?.trim().toLowerCase();
                
                if (!clinicName || clinicName.length < 3) {
                    validationErrors.push('Nome da clínica deve ter pelo menos 3 caracteres');
                }
                
                if (clinicSlug && !/^[a-z0-9-]+$/.test(clinicSlug)) {
                    validationErrors.push('Slug da clínica deve conter apenas letras minúsculas, números e hífens');
                }
                
                if (clinicSlug && clinicSlug.length < 3) {
                    validationErrors.push('Slug da clínica deve ter pelo menos 3 caracteres');
                }
                
                if (validationErrors.length > 0) {
                    showAlert(validationErrors.join('. '), 'danger');
                    return;
                }
                
                data.clinic_name = clinicName;
                if (clinicSlug) {
                    data.clinic_slug = clinicSlug;
                }
                
                endpoint = '/v1/auth/register';
            } else {
                // Registro de FUNCIONÁRIO
                const tenantSlug = formData.get('tenant_slug')?.trim().toLowerCase();
                
                if (!tenantSlug) {
                    validationErrors.push('Slug da clínica é obrigatório');
                } else if (!/^[a-z0-9-]+$/.test(tenantSlug)) {
                    validationErrors.push('Slug da clínica deve conter apenas letras minúsculas, números e hífens');
                } else if (tenantSlug.length < 3) {
                    validationErrors.push('Slug da clínica deve ter pelo menos 3 caracteres');
                }
                
                if (validationErrors.length > 0) {
                    showAlert(validationErrors.join('. '), 'danger');
                    return;
                }
                
                data.tenant_slug = tenantSlug;
                endpoint = '/v1/auth/register-employee';
            }
            
            try {
                const response = await fetch(API_URL + endpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(data)
                });
                
                const result = await response.json();
                
                if (!response.ok) {
                    // Mensagens de erro mais específicas
                    let errorMessage = result.message || result.error || 'Erro ao criar conta';
                    
                    if (response.status === 404) {
                        errorMessage = 'Clínica não encontrada. Verifique se o slug está correto.';
                    } else if (response.status === 409) {
                        if (result.code === 'SLUG_ALREADY_EXISTS') {
                            errorMessage = 'Este slug já está em uso. Escolha outro ou deixe em branco para gerar automaticamente.';
                        } else {
                            errorMessage = 'Este email já está cadastrado.';
                        }
                    } else if (result.errors) {
                        // Mostra erros de validação
                        const errorList = Object.values(result.errors).join(', ');
                        errorMessage = errorList || errorMessage;
                    }
                    
                    throw new Error(errorMessage);
                }
                
                const successMessage = userType === 'owner' 
                    ? 'Clínica e conta criadas com sucesso! Você já está logado. Redirecionando...'
                    : 'Conta criada com sucesso! Redirecionando para o login...';
                
                showAlert(successMessage, 'success');
                
                // Se for dono, já está logado (retorna session_id), então pode redirecionar para dashboard
                // Se for funcionário, precisa fazer login
                setTimeout(() => {
                    if (userType === 'owner' && result.data && result.data.session_id) {
                        // Salva session_id e redireciona para dashboard
                        localStorage.setItem('session_id', result.data.session_id);
                        window.location.href = '/dashboard';
                    } else {
                        window.location.href = '/login';
                    }
                }, 2000);
            } catch (error) {
                showAlert(error.message, 'danger');
            }
        });

        function showAlert(message, type = 'info') {
            const container = document.getElementById('alertContainer');
            const alert = document.createElement('div');
            alert.className = `alert alert-${type} alert-dismissible fade show`;
            alert.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            container.innerHTML = '';
            container.appendChild(alert);
        }
    </script>
</body>
</html>

