/**
 * Exemplo Completo de Integração Front-End
 * 
 * Este arquivo demonstra como integrar o sistema de pagamentos
 * em um front-end separado (React, Vue, Angular, Vanilla JS, etc.)
 */

// ============================================
// 1. CLIENTE API BASE
// ============================================

class PaymentsAPI {
    constructor(baseUrl, apiKey) {
        this.baseUrl = baseUrl.replace(/\/$/, '');
        this.apiKey = apiKey;
    }

    /**
     * Método genérico para fazer requisições HTTP
     */
    async request(method, endpoint, data = null) {
        const url = `${this.baseUrl}${endpoint}`;
        const options = {
            method,
            headers: {
                'Authorization': `Bearer ${this.apiKey}`,
                'Content-Type': 'application/json'
            }
        };

        if (data && ['POST', 'PUT', 'PATCH'].includes(method)) {
            options.body = JSON.stringify(data);
        }

        const response = await fetch(url, options);
        const result = await response.json();

        if (!response.ok) {
            const error = new Error(result.error || `HTTP ${response.status}`);
            error.status = response.status;
            error.code = result.code;
            throw error;
        }

        return result;
    }

    // ============================================
    // CUSTOMERS
    // ============================================

    async createCustomer(email, name, metadata = {}) {
        return this.request('POST', '/v1/customers', {
            email,
            name,
            metadata
        });
    }

    async getCustomer(customerId) {
        return this.request('GET', `/v1/customers/${customerId}`);
    }

    async listCustomers() {
        return this.request('GET', '/v1/customers');
    }

    async updateCustomer(customerId, data) {
        return this.request('PUT', `/v1/customers/${customerId}`, data);
    }

    // ============================================
    // CHECKOUT
    // ============================================

    async createCheckout(customerId, priceId, successUrl, cancelUrl, metadata = {}) {
        return this.request('POST', '/v1/checkout', {
            customer_id: customerId,
            price_id: priceId,
            success_url: successUrl,
            cancel_url: cancelUrl,
            metadata
        });
    }

    async getCheckout(checkoutId) {
        return this.request('GET', `/v1/checkout/${checkoutId}`);
    }

    // ============================================
    // SUBSCRIPTIONS
    // ============================================

    async createSubscription(customerId, priceId, trialPeriodDays = null, metadata = {}) {
        const data = {
            customer_id: customerId,
            price_id: priceId,
            metadata
        };
        if (trialPeriodDays) {
            data.trial_period_days = trialPeriodDays;
        }
        return this.request('POST', '/v1/subscriptions', data);
    }

    async listSubscriptions() {
        return this.request('GET', '/v1/subscriptions');
    }

    async getSubscription(subscriptionId) {
        return this.request('GET', `/v1/subscriptions/${subscriptionId}`);
    }

    async updateSubscription(subscriptionId, data) {
        return this.request('PUT', `/v1/subscriptions/${subscriptionId}`, data);
    }

    async cancelSubscription(subscriptionId, immediately = false) {
        const endpoint = `/v1/subscriptions/${subscriptionId}`;
        return this.request('DELETE', immediately ? `${endpoint}?immediately=true` : endpoint);
    }

    async reactivateSubscription(subscriptionId) {
        return this.request('POST', `/v1/subscriptions/${subscriptionId}/reactivate`);
    }

    // ============================================
    // PRICES
    // ============================================

    async listPrices() {
        return this.request('GET', '/v1/prices');
    }

    async getPrice(priceId) {
        return this.request('GET', `/v1/prices/${priceId}`);
    }

    // ============================================
    // AUTH (Session ID)
    // ============================================

    async login(email, password) {
        return this.request('POST', '/v1/auth/login', {
            email,
            password
        });
    }

    async logout() {
        return this.request('POST', '/v1/auth/logout');
    }

    async getMe() {
        return this.request('GET', '/v1/auth/me');
    }
}

// ============================================
// 2. EXEMPLO DE USO - FLUXO COMPLETO
// ============================================

// Inicializar cliente
const api = new PaymentsAPI(
    'https://pagamentos.seudominio.com',
    'sua_api_key_aqui'
);

// ============================================
// Exemplo 1: Criar Cliente e Checkout
// ============================================

async function exemploCriarClienteECheckout() {
    try {
        // 1. Criar cliente
        console.log('Criando cliente...');
        const customerResult = await api.createCustomer(
            'cliente@example.com',
            'João Silva'
        );
        const customer = customerResult.data;
        console.log('Cliente criado:', customer);

        // 2. Listar preços disponíveis
        console.log('Listando preços...');
        const pricesResult = await api.listPrices();
        const prices = pricesResult.data;
        console.log('Preços disponíveis:', prices);

        // 3. Selecionar um preço (exemplo: primeiro preço)
        const selectedPrice = prices[0];
        console.log('Preço selecionado:', selectedPrice);

        // 4. Criar checkout
        console.log('Criando checkout...');
        const checkoutResult = await api.createCheckout(
            customer.id,
            selectedPrice.id,
            `${window.location.origin}/success?session_id={CHECKOUT_SESSION_ID}`,
            `${window.location.origin}/cancel`
        );
        const checkout = checkoutResult.data;
        console.log('Checkout criado:', checkout);

        // 5. Redirecionar para Stripe Checkout
        window.location.href = checkout.url;
    } catch (error) {
        console.error('Erro:', error);
        alert(`Erro: ${error.message}`);
    }
}

// ============================================
// Exemplo 2: Criar Assinatura Diretamente
// ============================================

async function exemploCriarAssinatura() {
    try {
        // 1. Obter ou criar cliente
        let customer;
        const customerId = localStorage.getItem('customer_id');
        
        if (customerId) {
            customer = (await api.getCustomer(customerId)).data;
        } else {
            const result = await api.createCustomer(
                'cliente@example.com',
                'João Silva'
            );
            customer = result.data;
            localStorage.setItem('customer_id', customer.id);
        }

        // 2. Criar assinatura com trial de 14 dias
        console.log('Criando assinatura...');
        const subscriptionResult = await api.createSubscription(
            customer.id,
            'price_1234567890', // ID do preço no Stripe
            14, // Trial de 14 dias
            {
                user_id: '123',
                plan_name: 'Premium'
            }
        );
        const subscription = subscriptionResult.data;
        console.log('Assinatura criada:', subscription);

        // 3. Verificar status
        if (subscription.status === 'active') {
            console.log('Assinatura ativa!');
        } else if (subscription.status === 'trialing') {
            console.log('Período de trial iniciado!');
        }
    } catch (error) {
        console.error('Erro:', error);
        alert(`Erro: ${error.message}`);
    }
}

// ============================================
// Exemplo 3: Gerenciar Assinatura
// ============================================

async function exemploGerenciarAssinatura(subscriptionId) {
    try {
        // 1. Obter assinatura
        const subscriptionResult = await api.getSubscription(subscriptionId);
        const subscription = subscriptionResult.data;
        console.log('Assinatura:', subscription);

        // 2. Cancelar assinatura (no final do período)
        if (subscription.status === 'active') {
            console.log('Cancelando assinatura...');
            await api.cancelSubscription(subscriptionId, false);
            console.log('Assinatura será cancelada no final do período.');
        }

        // 3. Reativar assinatura cancelada
        if (subscription.status === 'canceled') {
            console.log('Reativando assinatura...');
            await api.reactivateSubscription(subscriptionId);
            console.log('Assinatura reativada!');
        }
    } catch (error) {
        console.error('Erro:', error);
        alert(`Erro: ${error.message}`);
    }
}

// ============================================
// Exemplo 4: Página de Sucesso (Callback)
// ============================================

async function exemploPaginaSucesso() {
    // Obter session_id da URL
    const urlParams = new URLSearchParams(window.location.search);
    const sessionId = urlParams.get('session_id');

    if (!sessionId) {
        console.error('Session ID não encontrado na URL');
        return;
    }

    try {
        // Verificar status do checkout
        console.log('Verificando checkout...');
        const checkoutResult = await api.getCheckout(sessionId);
        const checkout = checkoutResult.data;
        console.log('Checkout:', checkout);

        if (checkout.payment_status === 'paid') {
            console.log('Pagamento confirmado!');
            // Redirecionar para dashboard ou página de boas-vindas
            window.location.href = '/dashboard';
        } else {
            console.log('Pagamento ainda não foi confirmado.');
            alert('Aguardando confirmação do pagamento...');
        }
    } catch (error) {
        console.error('Erro:', error);
        alert(`Erro ao verificar pagamento: ${error.message}`);
    }
}

// ============================================
// Exemplo 5: Autenticação de Usuário (Session ID)
// ============================================

async function exemploLoginUsuario() {
    try {
        // 1. Login
        console.log('Fazendo login...');
        const loginResult = await api.login(
            'usuario@example.com',
            'senha123'
        );
        const sessionId = loginResult.data.session_id;
        console.log('Login realizado! Session ID:', sessionId);

        // 2. Guardar session ID
        localStorage.setItem('session_id', sessionId);

        // 3. Atualizar API para usar Session ID
        api.apiKey = sessionId;

        // 4. Verificar informações do usuário
        const meResult = await api.getMe();
        const user = meResult.data;
        console.log('Usuário autenticado:', user);

        // 5. Agora pode fazer requisições autenticadas como usuário
        // (com permissões específicas do usuário)
    } catch (error) {
        console.error('Erro no login:', error);
        alert(`Erro: ${error.message}`);
    }
}

async function exemploLogoutUsuario() {
    try {
        await api.logout();
        localStorage.removeItem('session_id');
        console.log('Logout realizado!');
    } catch (error) {
        console.error('Erro no logout:', error);
    }
}

// ============================================
// Exemplo 6: Tratamento de Erros Avançado
// ============================================

async function exemploComTratamentoDeErros() {
    try {
        const customer = await api.createCustomer('email@example.com', 'Nome');
        return customer;
    } catch (error) {
        // Tratamento específico por tipo de erro
        switch (error.status) {
            case 401:
                console.error('Não autenticado. Verifique sua API key.');
                // Redirecionar para login ou mostrar erro
                break;
            case 403:
                console.error('Sem permissão para esta ação.');
                break;
            case 404:
                console.error('Recurso não encontrado.');
                break;
            case 429:
                console.error('Muitas requisições. Aguarde um momento.');
                // Implementar retry com backoff
                break;
            case 500:
                console.error('Erro interno do servidor.');
                break;
            default:
                console.error('Erro desconhecido:', error.message);
        }
        throw error; // Re-lança para tratamento superior
    }
}

// ============================================
// Exemplo 7: React Hook
// ============================================

// usePayments.js (para React)
/*
import { useState, useCallback } from 'react';

export function usePayments() {
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);
    const api = new PaymentsAPI(
        process.env.REACT_APP_API_BASE_URL,
        process.env.REACT_APP_API_KEY
    );

    const createCustomer = useCallback(async (email, name) => {
        setLoading(true);
        setError(null);
        try {
            const result = await api.createCustomer(email, name);
            return result.data;
        } catch (err) {
            setError(err.message);
            throw err;
        } finally {
            setLoading(false);
        }
    }, []);

    const createCheckout = useCallback(async (customerId, priceId) => {
        setLoading(true);
        setError(null);
        try {
            const result = await api.createCheckout(
                customerId,
                priceId,
                `${window.location.origin}/success`,
                `${window.location.origin}/cancel`
            );
            window.location.href = result.data.url;
        } catch (err) {
            setError(err.message);
            throw err;
        } finally {
            setLoading(false);
        }
    }, []);

    return {
        loading,
        error,
        createCustomer,
        createCheckout,
    };
}
*/

// ============================================
// Exemplo 8: Vue Composable
// ============================================

// composables/usePayments.js (para Vue 3)
/*
import { ref } from 'vue';

export function usePayments() {
    const loading = ref(false);
    const error = ref(null);
    const api = new PaymentsAPI(
        import.meta.env.VITE_API_BASE_URL,
        import.meta.env.VITE_API_KEY
    );

    const createCustomer = async (email, name) => {
        loading.value = true;
        error.value = null;
        try {
            const result = await api.createCustomer(email, name);
            return result.data;
        } catch (err) {
            error.value = err.message;
            throw err;
        } finally {
            loading.value = false;
        }
    };

    return {
        loading,
        error,
        createCustomer,
    };
}
*/

// ============================================
// EXPORTAR PARA USO
// ============================================

// Se estiver usando módulos ES6
// export { PaymentsAPI };

// Se estiver usando CommonJS
// module.exports = { PaymentsAPI };

// Se estiver usando no browser diretamente
// window.PaymentsAPI = PaymentsAPI;

