/**
 * Cliente API para integração com o sistema de pagamentos
 * 
 * Configure suas variáveis abaixo:
 */
const API_CONFIG = {
    baseUrl: 'https://pagamentos.seudominio.com', // Altere para sua URL
    apiKey: 'sua_api_key_aqui' // Altere para sua API Key
};

/**
 * Classe para fazer requisições à API
 */
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

        try {
            const response = await fetch(url, options);
            const result = await response.json();

            if (!response.ok) {
                const error = new Error(result.error || `HTTP ${response.status}`);
                error.status = response.status;
                error.code = result.code;
                throw error;
            }

            return result;
        } catch (error) {
            // Se for erro de rede
            if (error instanceof TypeError && error.message.includes('fetch')) {
                throw new Error('Erro de conexão. Verifique sua internet.');
            }
            throw error;
        }
    }

    /**
     * Cria um novo cliente
     */
    async createCustomer(email, name, metadata = {}) {
        return this.request('POST', '/v1/customers', {
            email,
            name,
            metadata
        });
    }

    /**
     * Obtém um cliente por ID
     */
    async getCustomer(customerId) {
        return this.request('GET', `/v1/customers/${customerId}`);
    }

    /**
     * Lista todos os preços disponíveis
     */
    async listPrices() {
        return this.request('GET', '/v1/prices');
    }

    /**
     * Cria uma sessão de checkout
     */
    async createCheckout(customerId, priceId, successUrl, cancelUrl, metadata = {}) {
        return this.request('POST', '/v1/checkout', {
            customer_id: customerId,
            price_id: priceId,
            success_url: successUrl,
            cancel_url: cancelUrl,
            metadata
        });
    }

    /**
     * Obtém informações de uma sessão de checkout
     */
    async getCheckout(checkoutId) {
        return this.request('GET', `/v1/checkout/${checkoutId}`);
    }

    /**
     * Lista todas as assinaturas
     */
    async listSubscriptions() {
        return this.request('GET', '/v1/subscriptions');
    }

    /**
     * Cria uma assinatura diretamente
     */
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

    /**
     * Obtém uma assinatura por ID
     */
    async getSubscription(subscriptionId) {
        return this.request('GET', `/v1/subscriptions/${subscriptionId}`);
    }

    /**
     * Cancela uma assinatura
     */
    async cancelSubscription(subscriptionId, immediately = false) {
        const endpoint = `/v1/subscriptions/${subscriptionId}`;
        return this.request('DELETE', immediately ? `${endpoint}?immediately=true` : endpoint);
    }

    /**
     * Reativa uma assinatura cancelada
     */
    async reactivateSubscription(subscriptionId) {
        return this.request('POST', `/v1/subscriptions/${subscriptionId}/reactivate`);
    }

    /**
     * Atualiza um cliente
     */
    async updateCustomer(customerId, data) {
        return this.request('PUT', `/v1/customers/${customerId}`, data);
    }
}

// Inicializar cliente global
const api = new PaymentsAPI(API_CONFIG.baseUrl, API_CONFIG.apiKey);

