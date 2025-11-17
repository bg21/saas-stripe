/**
 * Funções de Autenticação
 * 
 * Este arquivo contém todas as funções relacionadas à autenticação:
 * - Login
 * - Logout
 * - Verificação de sessão
 * - Gerenciamento de tokens
 */

const API_CONFIG = window.API_CONFIG || {
    baseUrl: 'http://localhost/saas-stripe'
};

/**
 * Faz login no sistema
 * @param {string} email - Email do usuário
 * @param {string} password - Senha do usuário
 * @param {number|null} tenantId - ID do tenant (opcional - será detectado automaticamente se único)
 * @returns {Promise<Object>} Resultado do login
 */
async function login(email, password, tenantId = null) {
    try {
        const body = {
            email: email,
            password: password
        };
        
        // Só adiciona tenant_id se foi fornecido
        if (tenantId !== null && tenantId > 0) {
            body.tenant_id = tenantId;
        }
        
        const response = await fetch(`${API_CONFIG.baseUrl}/v1/auth/login`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(body)
        });

        const result = await response.json();

        if (!response.ok) {
            // Se for erro de múltiplos tenants, retorna a lista
            if (response.status === 400 && result.tenants) {
                return {
                    success: false,
                    error: result.message || result.error || 'Múltiplos tenants encontrados',
                    tenants: result.tenants,
                    status: response.status
                };
            }
            
            return {
                success: false,
                error: result.message || result.error || 'Erro ao fazer login',
                status: response.status
            };
        }

        if (result.success) {
            // ✅ SEGURANÇA: Session ID agora está em cookie httpOnly (não precisa armazenar)
            // ❌ REMOVIDO: localStorage.setItem('session_id', sessionId); // Vulnerável a XSS
            
            // Apenas armazena dados do usuário e tenant (não sensíveis)
            if (result.data.user) {
                sessionStorage.setItem('user', JSON.stringify(result.data.user)); // sessionStorage (expira ao fechar aba)
            }
            if (result.data.tenant) {
                sessionStorage.setItem('tenant', JSON.stringify(result.data.tenant)); // sessionStorage
            }
            
            // ✅ Session ID é enviado automaticamente pelo navegador via cookie httpOnly

            console.log('Login realizado com sucesso', {
                user: result.data.user,
                tenant: result.data.tenant
            });

            return {
                success: true,
                data: result.data
            };
        } else {
            return {
                success: false,
                error: result.message || 'Erro ao fazer login'
            };
        }
    } catch (error) {
        console.error('Erro ao fazer login:', error);
        return {
            success: false,
            error: error.message || 'Erro ao conectar com o servidor'
        };
    }
}

/**
 * Faz logout do sistema
 * @returns {Promise<Object>} Resultado do logout
 */
async function logout() {
    try {
        // ✅ SEGURANÇA: Cookie httpOnly é enviado automaticamente pelo navegador
        // Não precisa ler session_id manualmente do localStorage

        const response = await fetch(`${API_CONFIG.baseUrl}/v1/auth/logout`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            credentials: 'include' // ✅ IMPORTANTE: Envia cookie httpOnly
        });

        // Limpa dados locais independente da resposta do servidor
        clearAuthData(); // Remove dados do sessionStorage

        const result = await response.json();

        console.log('Logout realizado');

        return {
            success: true,
            data: result
        };
    } catch (error) {
        console.error('Erro ao fazer logout:', error);
        // Mesmo com erro, limpa dados locais
        clearAuthData();
        return {
            success: true, // Considera sucesso para não bloquear o usuário
            error: error.message
        };
    }
}

/**
 * Verifica se a sessão atual é válida
 * @returns {Promise<boolean>} true se válida, false caso contrário
 */
async function checkSession() {
    try {
        // ✅ SEGURANÇA: Session ID está em cookie httpOnly
        // Cookie é enviado automaticamente pelo navegador nas requisições
        // Não precisa ler manualmente do localStorage

        const response = await fetch(`${API_CONFIG.baseUrl}/v1/auth/me`, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json'
            },
            credentials: 'include' // ✅ IMPORTANTE: Envia cookie httpOnly
        });

        if (!response.ok) {
            if (response.status === 401) {
                // Sessão inválida
                clearAuthData();
                return false;
            }
            throw new Error(`HTTP ${response.status}`);
        }

        const result = await response.json();

        if (result.success) {
            // Atualiza dados do usuário no sessionStorage
            if (result.data.user) {
                sessionStorage.setItem('user', JSON.stringify(result.data.user));
            }
            if (result.data.tenant) {
                sessionStorage.setItem('tenant', JSON.stringify(result.data.tenant));
            }
            return true;
        }

        return false;
    } catch (error) {
        console.error('Erro ao verificar sessão:', error);
        // Em caso de erro de rede, verifica se tem dados no sessionStorage
        return !!sessionStorage.getItem('user');
    }
}

/**
 * Obtém dados do usuário atual
 * @returns {Object|null} Dados do usuário ou null se não estiver logado
 */
function getCurrentUser() {
    const userStr = sessionStorage.getItem('user');
    if (userStr) {
        try {
            return JSON.parse(userStr);
        } catch (e) {
            console.error('Erro ao parsear dados do usuário:', e);
            return null;
        }
    }
    return null;
}

/**
 * Obtém dados do tenant atual
 * @returns {Object|null} Dados do tenant ou null se não estiver logado
 */
function getCurrentTenant() {
    const tenantStr = sessionStorage.getItem('tenant');
    if (tenantStr) {
        try {
            return JSON.parse(tenantStr);
        } catch (e) {
            console.error('Erro ao parsear dados do tenant:', e);
            return null;
        }
    }
    return null;
}

/**
 * Obtém o session ID atual
 * @returns {string|null} Session ID ou null se não estiver logado
 */
function getSessionId() {
    // ✅ SEGURANÇA: Cookie httpOnly não é acessível via JavaScript
    // Session ID é enviado automaticamente pelo navegador nas requisições
    // Não é necessário ler manualmente
    // Para autenticação, use credentials: 'include' nas requisições fetch
    return null; // Cookie httpOnly não pode ser lido via JavaScript (proteção contra XSS)
}

/**
 * Verifica se o usuário está logado
 * @returns {boolean} true se estiver logado
 */
function isLoggedIn() {
    // ✅ SEGURANÇA: Cookie httpOnly não é acessível via JavaScript
    // Retorna true se tem dados de usuário no sessionStorage
    // Para verificação real, use checkSession() que faz requisição ao backend
    return !!sessionStorage.getItem('user');
}

/**
 * Limpa todos os dados de autenticação
 */
function clearAuthData() {
    // ✅ SEGURANÇA: Remove dados do sessionStorage
    sessionStorage.removeItem('user');
    sessionStorage.removeItem('tenant');
    // ✅ Cookie httpOnly é removido pelo backend no logout
}

/**
 * Requisição autenticada com tratamento automático de erros
 * @param {string} endpoint - Endpoint da API
 * @param {Object} options - Opções do fetch
 * @returns {Promise<Response>} Resposta da requisição
 */
async function authenticatedFetch(endpoint, options = {}) {
    // ✅ SEGURANÇA: Cookie httpOnly é enviado automaticamente pelo navegador
    // Não precisa ler session_id manualmente
    
    // Verifica se está logado (tem dados no sessionStorage)
    if (!sessionStorage.getItem('user')) {
        throw new Error('Não autenticado. Faça login primeiro.');
    }

    const headers = {
        'Content-Type': 'application/json',
        // ❌ REMOVIDO: 'Authorization': `Bearer ${sessionId}` // Cookie httpOnly é enviado automaticamente
        ...options.headers
    };

    const response = await fetch(`${API_CONFIG.baseUrl}${endpoint}`, {
        ...options,
        headers,
        credentials: 'include' // ✅ IMPORTANTE: Envia cookie httpOnly automaticamente
    });

    // Se não autorizado, redireciona para login
    if (response.status === 401) {
        clearAuthData();
        window.location.href = 'login.html';
        throw new Error('Sessão expirada. Redirecionando para login...');
    }

    return response;
}

/**
 * Faz requisição JSON autenticada
 * @param {string} endpoint - Endpoint da API
 * @param {Object} options - Opções do fetch
 * @returns {Promise<Object>} Resultado da requisição
 */
async function apiRequest(endpoint, options = {}) {
    try {
        const response = await authenticatedFetch(endpoint, options);
        const result = await response.json();
        
        if (!response.ok) {
            throw new Error(result.message || result.error || `HTTP ${response.status}`);
        }
        
        return result;
    } catch (error) {
        console.error(`Erro na requisição ${endpoint}:`, error);
        throw error;
    }
}

// Disponibiliza funções globalmente
window.login = login;
window.logout = logout;
window.checkSession = checkSession;
window.getCurrentUser = getCurrentUser;
window.getCurrentTenant = getCurrentTenant;
window.getSessionId = getSessionId;
window.isLoggedIn = isLoggedIn;
window.clearAuthData = clearAuthData;
window.authenticatedFetch = authenticatedFetch;
window.apiRequest = apiRequest;

