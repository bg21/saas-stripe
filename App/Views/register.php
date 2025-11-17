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
                                <input type="password" class="form-control" name="password" id="password" required minlength="6">
                                <div class="password-strength" id="passwordStrength"></div>
                                <small class="text-muted">Mínimo 6 caracteres</small>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Confirmar Senha *</label>
                                <input type="password" class="form-control" name="password_confirm" id="passwordConfirm" required>
                                <div id="passwordMatch" class="mt-1"></div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Tenant ID *</label>
                                <input type="number" class="form-control" name="tenant_id" required>
                                <small class="text-muted">ID do tenant ao qual você pertence</small>
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

        // Form de registro
        document.getElementById('registerForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const formData = new FormData(e.target);
            const password = formData.get('password');
            const passwordConfirm = formData.get('password_confirm');
            
            if (password !== passwordConfirm) {
                showAlert('As senhas não coincidem!', 'danger');
                return;
            }
            
            const data = {
                name: formData.get('name'),
                email: formData.get('email'),
                password: password,
                tenant_id: parseInt(formData.get('tenant_id'))
            };
            
            try {
                const response = await fetch(API_URL + '/v1/users', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(data)
                });
                
                const result = await response.json();
                
                if (!response.ok) {
                    throw new Error(result.message || result.error || 'Erro ao criar conta');
                }
                
                showAlert('Conta criada com sucesso! Redirecionando...', 'success');
                setTimeout(() => {
                    window.location.href = '/login';
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

