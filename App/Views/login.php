<?php
/**
 * View de Login
 * 
 * @var string $apiUrl URL base da API
 */
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistema SaaS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .login-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            padding: 40px;
            max-width: 450px;
            width: 100%;
        }
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .login-header h1 {
            color: #333;
            font-weight: 600;
            margin-bottom: 10px;
        }
        .login-header p {
            color: #666;
            font-size: 14px;
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: transform 0.2s;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        .btn-login:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        .alert {
            border-radius: 10px;
            border: none;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1><i class="bi bi-shield-lock-fill text-primary"></i> Login</h1>
            <p>Entre com suas credenciais para acessar o sistema</p>
        </div>

        <div id="errorAlert" class="alert alert-danger d-none" role="alert">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <span id="errorMessage"></span>
        </div>

        <form id="loginForm" method="POST" action="#" onsubmit="return false;">
            <div class="mb-3">
                <label for="tenant_slug" class="form-label">
                    <i class="bi bi-building"></i> Slug da Clínica <span class="text-muted">(obrigatório)</span>
                </label>
                <input 
                    type="text" 
                    class="form-control" 
                    id="tenant_slug" 
                    name="tenant_slug" 
                    required 
                    pattern="[a-z0-9-]+"
                    placeholder="ex: cao-que-mia"
                    autocomplete="off"
                >
                <small class="text-muted">Informe o slug da clínica (ex: cao-que-mia)</small>
            </div>

            <div class="mb-3">
                <label for="email" class="form-label">
                    <i class="bi bi-envelope"></i> Email
                </label>
                <input 
                    type="email" 
                    class="form-control" 
                    id="email" 
                    name="email" 
                    required 
                    placeholder="seu@email.com"
                    autocomplete="email"
                >
            </div>

            <div class="mb-4">
                <label for="password" class="form-label">
                    <i class="bi bi-lock"></i> Senha
                </label>
                <input 
                    type="password" 
                    class="form-control" 
                    id="password" 
                    name="password" 
                    required 
                    placeholder="Digite sua senha"
                    autocomplete="current-password"
                >
            </div>

            <button type="submit" class="btn btn-primary btn-login w-100 mb-3" id="loginButton">
                <span class="spinner-border spinner-border-sm me-2 d-none" id="loadingSpinner"></span>
                <span id="loginButtonText">Entrar</span>
            </button>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const API_URL = <?php echo json_encode($apiUrl, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        
        // Validação de slug em tempo real
        document.getElementById('tenant_slug').addEventListener('input', function() {
            const slug = this.value.toLowerCase().trim();
            // Remove caracteres inválidos
            const validSlug = slug.replace(/[^a-z0-9-]/g, '');
            if (slug !== validSlug) {
                this.value = validSlug;
            }
        });
        
        // Previne submit padrão do formulário
        const loginForm = document.getElementById('loginForm');
        
        loginForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            e.stopPropagation();
            
            const errorAlert = document.getElementById('errorAlert');
            const errorMessage = document.getElementById('errorMessage');
            const loginButton = document.getElementById('loginButton');
            const loginButtonText = document.getElementById('loginButtonText');
            const loadingSpinner = document.getElementById('loadingSpinner');
            
            errorAlert.classList.add('d-none');
            loginButton.disabled = true;
            loginButtonText.textContent = 'Entrando...';
            loadingSpinner.classList.remove('d-none');
            
            const tenantSlug = document.getElementById('tenant_slug').value.trim().toLowerCase();
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value;
            
            // Valida formato do slug
            if (!/^[a-z0-9-]+$/.test(tenantSlug)) {
                errorMessage.textContent = 'Slug da clínica deve conter apenas letras minúsculas, números e hífens';
                errorAlert.classList.remove('d-none');
                loginButton.disabled = false;
                loginButtonText.textContent = 'Entrar';
                loadingSpinner.classList.add('d-none');
                return;
            }
            
            if (tenantSlug.length < 3) {
                errorMessage.textContent = 'Slug da clínica deve ter pelo menos 3 caracteres';
                errorAlert.classList.remove('d-none');
                loginButton.disabled = false;
                loginButtonText.textContent = 'Entrar';
                loadingSpinner.classList.add('d-none');
                return;
            }
            
            try {
                const response = await fetch(API_URL + '/v1/auth/login', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        email: email,
                        password: password,
                        tenant_slug: tenantSlug
                    })
                });
                
                const data = await response.json();
                
                if (response.ok && data.success) {
                    // Salva session_id no localStorage
                    localStorage.setItem('session_id', data.data.session_id);
                    localStorage.setItem('user', JSON.stringify(data.data.user));
                    localStorage.setItem('tenant', JSON.stringify(data.data.tenant));
                    
                    // Salva session_id em cookie também (para o servidor poder acessar)
                    // Cookie válido por 7 dias, HttpOnly será definido pelo servidor se necessário
                    const expires = new Date();
                    expires.setTime(expires.getTime() + (7 * 24 * 60 * 60 * 1000)); // 7 dias
                    document.cookie = `session_id=${data.data.session_id}; expires=${expires.toUTCString()}; path=/; SameSite=Lax`;
                    
                    // Redireciona incluindo session_id na query string como fallback
                    // Isso garante que o servidor possa acessar mesmo se o cookie não funcionar
                    // Remove o session_id da URL após o carregamento (será feito no layout base)
                    window.location.href = '/dashboard?session_id=' + encodeURIComponent(data.data.session_id);
                } else {
                    // Mensagens de erro mais específicas
                    let errorMsg = data.message || 'Erro ao fazer login';
                    
                    if (response.status === 400 || response.status === 404) {
                        if (data.errors && data.errors.tenant_slug) {
                            errorMsg = data.errors.tenant_slug;
                        } else if (data.message && data.message.includes('Clínica não encontrada')) {
                            errorMsg = 'Clínica não encontrada. Verifique se o slug está correto.';
                        }
                    } else if (response.status === 401 || response.status === 403) {
                        errorMsg = data.message || 'Email ou senha incorretos';
                    }
                    
                    errorMessage.textContent = errorMsg;
                    errorAlert.classList.remove('d-none');
                    loginButton.disabled = false;
                    loginButtonText.textContent = 'Entrar';
                    loadingSpinner.classList.add('d-none');
                }
            } catch (error) {
                console.error('Erro no login:', error);
                errorMessage.textContent = 'Erro ao conectar com o servidor';
                errorAlert.classList.remove('d-none');
                loginButton.disabled = false;
                loginButtonText.textContent = 'Entrar';
                loadingSpinner.classList.add('d-none');
            }
        });
    </script>
</body>
</html>

