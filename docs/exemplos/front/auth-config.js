/**
 * Configuração da API
 * 
 * IMPORTANTE: Ajuste estas configurações conforme seu ambiente
 */

const API_CONFIG = {
    // URL base da API (backend)
    baseUrl: 'http://localhost/saas-stripe', // Mude para sua URL em produção
    
    // Tenant ID padrão (pode ser alterado no formulário de login)
    tenantId: 1,
    
    // Timeout das requisições (em milissegundos)
    timeout: 30000,
    
    // Configurações de CORS (se necessário)
    credentials: 'omit', // 'omit', 'same-origin', 'include'
};

// Disponibiliza globalmente
window.API_CONFIG = API_CONFIG;

