/**
 * Auth Guard - Proteção de Rotas
 * 
 * Este arquivo contém funções para proteger páginas que requerem autenticação
 * Use este arquivo em todas as páginas que precisam de login
 */

const API_CONFIG = window.API_CONFIG || {
    baseUrl: 'http://localhost/saas-stripe'
};

/**
 * Verifica autenticação e redireciona para login se necessário
 * Use esta função no início de páginas protegidas
 * 
 * @param {boolean} requireAuth - Se true, redireciona para login se não autenticado
 * @param {string} redirectTo - URL para redirecionar após verificação (padrão: dashboard.html)
 * @returns {Promise<boolean>} true se autenticado, false caso contrário
 */
async function requireAuth(requireAuth = true, redirectTo = 'dashboard.html') {
    // Verifica se já está logado (tem session_id)
    const sessionId = localStorage.getItem('session_id');
    
    if (!sessionId) {
        if (requireAuth) {
            // Salva a URL atual para redirecionar após login
            const currentUrl = window.location.href;
            sessionStorage.setItem('redirect_after_login', currentUrl);
            
            // Redireciona para login
            window.location.href = 'login.html';
        }
        return false;
    }

    // Verifica se a sessão ainda é válida
    try {
        const isValid = await checkSession();
        
        if (!isValid) {
            if (requireAuth) {
                // Sessão inválida, redireciona para login
                sessionStorage.setItem('redirect_after_login', window.location.href);
                window.location.href = 'login.html';
            }
            return false;
        }

        // Sessão válida
        return true;
    } catch (error) {
        console.error('Erro ao verificar autenticação:', error);
        
        if (requireAuth) {
            // Em caso de erro, redireciona para login para segurança
            window.location.href = 'login.html';
        }
        
        return false;
    }
}

/**
 * Verifica se o usuário tem uma permissão específica
 * Nota: Isso requer que o backend tenha um endpoint de permissões
 * 
 * @param {string} permission - Nome da permissão
 * @returns {Promise<boolean>} true se tiver permissão
 */
async function hasPermission(permission) {
    try {
        const user = getCurrentUser();
        if (!user) {
            return false;
        }

        // Se for admin, tem todas as permissões
        if (user.role === 'admin') {
            return true;
        }

        // Aqui você pode fazer uma requisição para verificar permissões
        // Por enquanto, retorna false se não for admin
        // TODO: Implementar verificação de permissões no backend
        
        return false;
    } catch (error) {
        console.error('Erro ao verificar permissão:', error);
        return false;
    }
}

/**
 * Verifica se o usuário tem um role específico
 * 
 * @param {string} role - Role a verificar (admin, editor, viewer)
 * @returns {boolean} true se tiver o role
 */
function hasRole(role) {
    const user = getCurrentUser();
    if (!user) {
        return false;
    }

    return user.role === role;
}

/**
 * Verifica se o usuário é admin
 * @returns {boolean} true se for admin
 */
function isAdmin() {
    return hasRole('admin');
}

/**
 * Verifica se o usuário é editor ou admin
 * @returns {boolean} true se for editor ou admin
 */
function isEditorOrAdmin() {
    const user = getCurrentUser();
    if (!user) {
        return false;
    }

    return user.role === 'admin' || user.role === 'editor';
}

/**
 * Inicializa proteção de página
 * Use esta função no início de cada página protegida
 * 
 * Exemplo de uso:
 * ```javascript
 * // No início do script da página
 * document.addEventListener('DOMContentLoaded', async () => {
 *     const isAuthenticated = await requireAuth(true);
 *     if (!isAuthenticated) {
 *         return; // Já redirecionou para login
 *     }
 *     
 *     // Carrega dados da página...
 * });
 * ```
 */
async function initAuthGuard() {
    const isAuthenticated = await requireAuth(true);
    
    if (isAuthenticated) {
        // Carrega dados do usuário para uso na página
        const user = getCurrentUser();
        const tenant = getCurrentTenant();
        
        // Disponibiliza globalmente para uso na página
        window.currentUser = user;
        window.currentTenant = tenant;
        
        return true;
    }
    
    return false;
}

// Disponibiliza funções globalmente
window.requireAuth = requireAuth;
window.hasPermission = hasPermission;
window.hasRole = hasRole;
window.isAdmin = isAdmin;
window.isEditorOrAdmin = isEditorOrAdmin;
window.initAuthGuard = initAuthGuard;

