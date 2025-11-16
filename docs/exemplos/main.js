/**
 * Script principal da aplicação
 */

// Estado da aplicação
let state = {
    currentStep: 1,
    selectedPlan: null,
    customer: null,
    plans: []
};

/**
 * Obtém a URL base do site (com o caminho do diretório)
 * Funciona tanto com / quanto com /index.html
 */
function getBaseUrl() {
    const origin = window.location.origin;
    const pathname = window.location.pathname;
    
    // Remove o nome do arquivo se existir (ex: /index.html)
    const basePath = pathname.replace(/\/[^/]*$/, '') || '';
    
    return origin + basePath;
}

/**
 * Inicialização
 */
document.addEventListener('DOMContentLoaded', function() {
    loadPlans();
    setupEventListeners();
    
    // Verificar se há customer salvo
    const savedCustomerId = localStorage.getItem('customer_id');
    if (savedCustomerId) {
        loadCustomer(savedCustomerId);
    }
});

/**
 * Configura event listeners
 */
function setupEventListeners() {
    // Formulário de cliente
    const customerForm = document.getElementById('customerForm');
    if (customerForm) {
        customerForm.addEventListener('submit', handleCustomerSubmit);
    }
}

/**
 * Carrega planos disponíveis
 */
async function loadPlans() {
    const container = document.getElementById('plansContainer');
    
    try {
        showLoading(container);
        
        const result = await api.listPrices();
        state.plans = result.data || [];
        
        if (state.plans.length === 0) {
            container.innerHTML = `
                <div class="col-12">
                    <div class="alert alert-warning text-center">
                        <i class="bi bi-exclamation-triangle"></i> Nenhum plano disponível no momento.
                    </div>
                </div>
            `;
            return;
        }
        
        renderPlans(state.plans);
    } catch (error) {
        showAlert('danger', `Erro ao carregar planos: ${error.message}`);
        container.innerHTML = `
            <div class="col-12">
                <div class="alert alert-danger text-center">
                    <i class="bi bi-x-circle"></i> Erro ao carregar planos. Tente novamente mais tarde.
                </div>
            </div>
        `;
    }
}

/**
 * Renderiza os planos na tela
 */
function renderPlans(plans) {
    const container = document.getElementById('plansContainer');
    container.innerHTML = '';
    
    plans.forEach(plan => {
        const planCard = createPlanCard(plan);
        container.appendChild(planCard);
    });
}

/**
 * Cria um card de plano
 */
function createPlanCard(plan) {
    const col = document.createElement('div');
    col.className = 'col-md-4 col-sm-6 mb-4';
    
    const price = plan.unit_amount / 100;
    const currency = plan.currency.toUpperCase();
    const interval = plan.recurring?.interval || 'mês';
    const intervalText = interval === 'month' ? 'mês' : interval === 'year' ? 'ano' : interval;
    
    col.innerHTML = `
        <div class="card card-plan h-100" data-plan-id="${plan.id}">
            <div class="card-body p-4">
                <h5 class="card-title mb-3">
                    ${plan.product?.name || 'Plano Premium'}
                </h5>
                <p class="card-text text-muted mb-4">
                    ${plan.product?.description || 'Plano completo com todas as funcionalidades'}
                </p>
                <div class="mb-4">
                    <span class="plan-price">${formatCurrency(price, currency)}</span>
                    <span class="plan-interval">/${intervalText}</span>
                </div>
                <ul class="list-unstyled mb-4">
                    ${plan.product?.metadata?.features ? 
                        plan.product.metadata.features.split(',').map(f => `<li><i class="bi bi-check-circle text-success me-2"></i>${f.trim()}</li>`).join('') 
                        : '<li><i class="bi bi-check-circle text-success me-2"></i>Suporte completo</li>'
                    }
                </ul>
                <button class="btn btn-primary-custom text-white w-100" onclick="selectPlan('${plan.id}')">
                    Selecionar Plano
                </button>
            </div>
        </div>
    `;
    
    return col;
}

/**
 * Seleciona um plano
 */
function selectPlan(planId) {
    // Remover seleção anterior
    document.querySelectorAll('.card-plan').forEach(card => {
        card.classList.remove('selected');
    });
    
    // Marcar como selecionado
    const planCard = document.querySelector(`[data-plan-id="${planId}"]`);
    if (planCard) {
        planCard.classList.add('selected');
    }
    
    // Encontrar plano nos dados
    state.selectedPlan = state.plans.find(p => p.id === planId);
    
    if (!state.selectedPlan) {
        showAlert('danger', 'Plano não encontrado');
        return;
    }
    
    // Se já tem customer, ir direto para checkout
    if (state.customer) {
        proceedToCheckout();
    } else {
        // Senão, pedir dados do cliente
        goToCustomerStep();
    }
}

/**
 * Vai para o passo de dados do cliente
 */
function goToCustomerStep() {
    state.currentStep = 2;
    updateStepIndicator();
    
    document.getElementById('stepPlans').style.display = 'none';
    document.getElementById('stepCustomer').style.display = 'block';
    document.getElementById('stepProcessing').style.display = 'none';
    
    // Preencher dados se já existir customer
    if (state.customer) {
        document.getElementById('customerEmail').value = state.customer.email || '';
        document.getElementById('customerName').value = state.customer.name || '';
    }
}

/**
 * Volta para seleção de planos
 */
function goBackToPlans() {
    state.currentStep = 1;
    updateStepIndicator();
    
    document.getElementById('stepPlans').style.display = 'block';
    document.getElementById('stepCustomer').style.display = 'none';
    document.getElementById('stepProcessing').style.display = 'none';
}

/**
 * Trata o submit do formulário de cliente
 */
async function handleCustomerSubmit(e) {
    e.preventDefault();
    
    const email = document.getElementById('customerEmail').value.trim();
    const name = document.getElementById('customerName').value.trim();
    
    if (!email || !name) {
        showAlert('warning', 'Por favor, preencha todos os campos');
        return;
    }
    
    const submitBtn = document.getElementById('btnSubmitCustomer');
    const spinner = submitBtn.querySelector('.loading-spinner');
    
    try {
        submitBtn.disabled = true;
        spinner.classList.add('active');
        
        // Criar ou atualizar customer
        let customer;
        if (state.customer) {
            // Atualizar customer existente
            const result = await api.updateCustomer(state.customer.id, { email, name });
            customer = result.data;
        } else {
            // Criar novo customer
            const result = await api.createCustomer(email, name);
            customer = result.data;
            localStorage.setItem('customer_id', customer.id);
        }
        
        state.customer = customer;
        
        // Prosseguir para checkout
        proceedToCheckout();
    } catch (error) {
        showAlert('danger', `Erro ao processar: ${error.message}`);
    } finally {
        submitBtn.disabled = false;
        spinner.classList.remove('active');
    }
}

/**
 * Prossegue para o checkout
 */
async function proceedToCheckout() {
    if (!state.selectedPlan || !state.customer) {
        showAlert('danger', 'Dados incompletos. Por favor, tente novamente.');
        return;
    }
    
    state.currentStep = 3;
    updateStepIndicator();
    
    document.getElementById('stepPlans').style.display = 'none';
    document.getElementById('stepCustomer').style.display = 'none';
    document.getElementById('stepProcessing').style.display = 'block';
    
    try {
        // Criar sessão de checkout
        const baseUrl = getBaseUrl();
        const successUrl = `${baseUrl}/success.html?session_id={CHECKOUT_SESSION_ID}`;
        const cancelUrl = `${baseUrl}/index.html`;
        
        console.log('URLs do checkout:', {
            currentUrl: window.location.href,
            origin: window.location.origin,
            pathname: window.location.pathname,
            baseUrl,
            successUrl,
            cancelUrl
        });
        
        console.log('Criando checkout:', {
            customerId: state.customer.id,
            priceId: state.selectedPlan.id,
            successUrl,
            cancelUrl
        });
        
        const result = await api.createCheckout(
            state.customer.id,
            state.selectedPlan.id,
            successUrl,
            cancelUrl,
            {
                plan_name: state.selectedPlan.product?.name || 'Plano',
                customer_name: state.customer.name
            }
        );
        
        console.log('Resposta do checkout:', result);
        
        // Redirecionar para Stripe Checkout
        if (result.data && result.data.url) {
            window.location.href = result.data.url;
        } else {
            console.error('Resposta inválida:', result);
            throw new Error('URL de checkout não retornada. Resposta: ' + JSON.stringify(result));
        }
    } catch (error) {
        console.error('Erro completo:', error);
        const errorMessage = error.message || 'Erro desconhecido ao criar checkout';
        showAlert('danger', `Erro ao criar checkout: ${errorMessage}`);
        goBackToPlans();
    }
}

/**
 * Carrega customer salvo
 */
async function loadCustomer(customerId) {
    try {
        const result = await api.getCustomer(customerId);
        state.customer = result.data;
    } catch (error) {
        // Customer não encontrado, limpar localStorage
        localStorage.removeItem('customer_id');
        state.customer = null;
    }
}

/**
 * Atualiza o indicador de passos
 */
function updateStepIndicator() {
    for (let i = 1; i <= 3; i++) {
        const step = document.getElementById(`step${i}`);
        step.classList.remove('active', 'completed');
        
        if (i < state.currentStep) {
            step.classList.add('completed');
        } else if (i === state.currentStep) {
            step.classList.add('active');
        }
    }
}

/**
 * Mostra um alerta
 */
function showAlert(type, message) {
    const container = document.getElementById('alertContainer');
    const alertId = 'alert-' + Date.now();
    
    const alertHTML = `
        <div class="alert alert-${type} alert-dismissible fade show alert-custom" role="alert" id="${alertId}">
            <i class="bi bi-${type === 'danger' ? 'x-circle' : type === 'warning' ? 'exclamation-triangle' : 'info-circle'} me-2"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    
    container.innerHTML = alertHTML;
    
    // Auto-remover após 5 segundos
    setTimeout(() => {
        const alert = document.getElementById(alertId);
        if (alert) {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }
    }, 5000);
}

/**
 * Mostra loading
 */
function showLoading(container) {
    container.innerHTML = `
        <div class="col-12 text-center">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Carregando...</span>
            </div>
        </div>
    `;
}

/**
 * Formata moeda
 */
function formatCurrency(amount, currency) {
    const currencyMap = {
        'BRL': 'pt-BR',
        'USD': 'en-US',
        'EUR': 'de-DE'
    };
    
    const locale = currencyMap[currency] || 'pt-BR';
    const currencyCode = currency === 'BRL' ? 'BRL' : currency;
    
    return new Intl.NumberFormat(locale, {
        style: 'currency',
        currency: currencyCode
    }).format(amount);
}

