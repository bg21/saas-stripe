/**
 * Exemplo Completo de Integração React
 * 
 * Este componente demonstra como integrar o sistema de pagamentos
 * em uma aplicação React.
 */

import React, { useState, useEffect } from 'react';
import { PaymentsAPI } from './frontend-integration-example';

// Configuração da API
const API_BASE_URL = process.env.REACT_APP_API_BASE_URL || 'https://pagamentos.seudominio.com';
const API_KEY = process.env.REACT_APP_API_KEY || 'sua_api_key_aqui';

// Inicializar cliente
const api = new PaymentsAPI(API_BASE_URL, API_KEY);

/**
 * Componente de Seleção de Plano
 */
function PlanSelector({ onSelectPlan }) {
    const [plans, setPlans] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    useEffect(() => {
        async function loadPlans() {
            try {
                setLoading(true);
                const result = await api.listPrices();
                setPlans(result.data || []);
            } catch (err) {
                setError(err.message);
            } finally {
                setLoading(false);
            }
        }
        loadPlans();
    }, []);

    if (loading) return <div>Carregando planos...</div>;
    if (error) return <div>Erro: {error}</div>;
    if (plans.length === 0) return <div>Nenhum plano disponível</div>;

    return (
        <div className="plans-grid">
            {plans.map(plan => (
                <div key={plan.id} className="plan-card">
                    <h3>{plan.product?.name || 'Plano'}</h3>
                    <p className="price">
                        {new Intl.NumberFormat('pt-BR', {
                            style: 'currency',
                            currency: plan.currency.toUpperCase()
                        }).format(plan.unit_amount / 100)}
                        /{plan.recurring?.interval || 'mês'}
                    </p>
                    <p>{plan.product?.description || ''}</p>
                    <button onClick={() => onSelectPlan(plan)}>
                        Selecionar Plano
                    </button>
                </div>
            ))}
        </div>
    );
}

/**
 * Componente de Formulário de Cliente
 */
function CustomerForm({ onSubmit, initialData = {} }) {
    const [email, setEmail] = useState(initialData.email || '');
    const [name, setName] = useState(initialData.name || '');
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);

    const handleSubmit = async (e) => {
        e.preventDefault();
        setLoading(true);
        setError(null);

        try {
            await onSubmit({ email, name });
        } catch (err) {
            setError(err.message);
        } finally {
            setLoading(false);
        }
    };

    return (
        <form onSubmit={handleSubmit}>
            {error && <div className="error">{error}</div>}
            <div>
                <label>Email:</label>
                <input
                    type="email"
                    value={email}
                    onChange={(e) => setEmail(e.target.value)}
                    required
                />
            </div>
            <div>
                <label>Nome:</label>
                <input
                    type="text"
                    value={name}
                    onChange={(e) => setName(e.target.value)}
                    required
                />
            </div>
            <button type="submit" disabled={loading}>
                {loading ? 'Processando...' : 'Continuar'}
            </button>
        </form>
    );
}

/**
 * Componente Principal de Checkout
 */
function CheckoutPage() {
    const [step, setStep] = useState('plans'); // 'plans', 'customer', 'processing'
    const [selectedPlan, setSelectedPlan] = useState(null);
    const [customer, setCustomer] = useState(null);
    const [error, setError] = useState(null);

    // Verificar se já existe customer salvo
    useEffect(() => {
        const savedCustomerId = localStorage.getItem('customer_id');
        if (savedCustomerId) {
            api.getCustomer(savedCustomerId)
                .then(result => {
                    setCustomer(result.data);
                })
                .catch(() => {
                    // Customer não encontrado, limpar localStorage
                    localStorage.removeItem('customer_id');
                });
        }
    }, []);

    const handleSelectPlan = (plan) => {
        setSelectedPlan(plan);
        if (customer) {
            // Se já tem customer, ir direto para checkout
            handleCheckout(customer);
        } else {
            // Se não tem customer, pedir dados
            setStep('customer');
        }
    };

    const handleCreateCustomer = async (customerData) => {
        try {
            const result = await api.createCustomer(
                customerData.email,
                customerData.name
            );
            const newCustomer = result.data;
            setCustomer(newCustomer);
            localStorage.setItem('customer_id', newCustomer.id);
            
            // Ir para checkout
            handleCheckout(newCustomer);
        } catch (err) {
            throw err;
        }
    };

    const handleCheckout = async (customerData) => {
        try {
            setStep('processing');
            setError(null);

            const result = await api.createCheckout(
                customerData.id,
                selectedPlan.id,
                `${window.location.origin}/success?session_id={CHECKOUT_SESSION_ID}`,
                `${window.location.origin}/cancel`
            );

            // Redirecionar para Stripe Checkout
            window.location.href = result.data.url;
        } catch (err) {
            setError(err.message);
            setStep('customer');
        }
    };

    return (
        <div className="checkout-page">
            <h1>Escolha seu Plano</h1>

            {error && <div className="error-message">{error}</div>}

            {step === 'plans' && (
                <PlanSelector onSelectPlan={handleSelectPlan} />
            )}

            {step === 'customer' && (
                <div>
                    <h2>Informações do Cliente</h2>
                    <CustomerForm
                        onSubmit={handleCreateCustomer}
                        initialData={customer || {}}
                    />
                </div>
            )}

            {step === 'processing' && (
                <div className="processing">
                    <p>Redirecionando para o checkout...</p>
                </div>
            )}
        </div>
    );
}

/**
 * Componente de Página de Sucesso
 */
function SuccessPage() {
    const [checkout, setCheckout] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    useEffect(() => {
        async function verifyCheckout() {
            const urlParams = new URLSearchParams(window.location.search);
            const sessionId = urlParams.get('session_id');

            if (!sessionId) {
                setError('Session ID não encontrado na URL');
                setLoading(false);
                return;
            }

            try {
                const result = await api.getCheckout(sessionId);
                setCheckout(result.data);

                if (result.data.payment_status === 'paid') {
                    // Pagamento confirmado, redirecionar após 3 segundos
                    setTimeout(() => {
                        window.location.href = '/dashboard';
                    }, 3000);
                }
            } catch (err) {
                setError(err.message);
            } finally {
                setLoading(false);
            }
        }

        verifyCheckout();
    }, []);

    if (loading) return <div>Verificando pagamento...</div>;
    if (error) return <div>Erro: {error}</div>;

    return (
        <div className="success-page">
            {checkout?.payment_status === 'paid' ? (
                <>
                    <h1>✅ Pagamento Confirmado!</h1>
                    <p>Redirecionando para o dashboard...</p>
                </>
            ) : (
                <>
                    <h1>⏳ Aguardando Confirmação</h1>
                    <p>Seu pagamento está sendo processado.</p>
                </>
            )}
        </div>
    );
}

/**
 * Componente de Gerenciamento de Assinatura
 */
function SubscriptionManagement({ subscriptionId }) {
    const [subscription, setSubscription] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [actionLoading, setActionLoading] = useState(false);

    useEffect(() => {
        loadSubscription();
    }, [subscriptionId]);

    const loadSubscription = async () => {
        try {
            setLoading(true);
            const result = await api.getSubscription(subscriptionId);
            setSubscription(result.data);
        } catch (err) {
            setError(err.message);
        } finally {
            setLoading(false);
        }
    };

    const handleCancel = async () => {
        if (!window.confirm('Tem certeza que deseja cancelar sua assinatura?')) {
            return;
        }

        try {
            setActionLoading(true);
            await api.cancelSubscription(subscriptionId, false);
            await loadSubscription(); // Recarregar dados
            alert('Assinatura será cancelada no final do período.');
        } catch (err) {
            alert(`Erro: ${err.message}`);
        } finally {
            setActionLoading(false);
        }
    };

    const handleReactivate = async () => {
        try {
            setActionLoading(true);
            await api.reactivateSubscription(subscriptionId);
            await loadSubscription(); // Recarregar dados
            alert('Assinatura reativada com sucesso!');
        } catch (err) {
            alert(`Erro: ${err.message}`);
        } finally {
            setActionLoading(false);
        }
    };

    if (loading) return <div>Carregando assinatura...</div>;
    if (error) return <div>Erro: {error}</div>;
    if (!subscription) return <div>Assinatura não encontrada</div>;

    return (
        <div className="subscription-management">
            <h2>Minha Assinatura</h2>
            
            <div className="subscription-info">
                <p><strong>Status:</strong> {subscription.status}</p>
                <p><strong>Plano:</strong> {subscription.plan?.nickname || subscription.plan?.id}</p>
                <p><strong>Próximo pagamento:</strong> {
                    subscription.current_period_end 
                        ? new Date(subscription.current_period_end * 1000).toLocaleDateString('pt-BR')
                        : 'N/A'
                }</p>
            </div>

            <div className="subscription-actions">
                {subscription.status === 'active' && (
                    <button 
                        onClick={handleCancel}
                        disabled={actionLoading}
                        className="btn-cancel"
                    >
                        {actionLoading ? 'Processando...' : 'Cancelar Assinatura'}
                    </button>
                )}

                {subscription.status === 'canceled' && (
                    <button 
                        onClick={handleReactivate}
                        disabled={actionLoading}
                        className="btn-reactivate"
                    >
                        {actionLoading ? 'Processando...' : 'Reativar Assinatura'}
                    </button>
                )}
            </div>
        </div>
    );
}

/**
 * Hook Customizado para Pagamentos
 */
export function usePayments() {
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);

    const createCustomer = async (email, name) => {
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
    };

    const createCheckout = async (customerId, priceId, successUrl, cancelUrl) => {
        setLoading(true);
        setError(null);
        try {
            const result = await api.createCheckout(customerId, priceId, successUrl, cancelUrl);
            return result.data;
        } catch (err) {
            setError(err.message);
            throw err;
        } finally {
            setLoading(false);
        }
    };

    const listPrices = async () => {
        setLoading(true);
        setError(null);
        try {
            const result = await api.listPrices();
            return result.data;
        } catch (err) {
            setError(err.message);
            throw err;
        } finally {
            setLoading(false);
        }
    };

    return {
        loading,
        error,
        createCustomer,
        createCheckout,
        listPrices,
    };
}

// Exportar componentes
export {
    CheckoutPage,
    SuccessPage,
    SubscriptionManagement,
    PlanSelector,
    CustomerForm,
};

