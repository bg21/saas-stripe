<?php
/**
 * View Pública - Seleção de Planos
 */
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Planos - Sistema SaaS</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <style>
        .plan-card {
            transition: transform 0.3s, box-shadow 0.3s;
            border: 2px solid transparent;
        }
        .plan-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
            border-color: #007bff;
        }
        .plan-card.featured {
            border-color: #007bff;
            position: relative;
        }
        .plan-card.featured::before {
            content: 'Mais Popular';
            position: absolute;
            top: -10px;
            right: 20px;
            background: #007bff;
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 12px;
        }
        .step-indicator {
            display: flex;
            justify-content: center;
            margin-bottom: 30px;
        }
        .step {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 10px;
            font-weight: bold;
        }
        .step.active {
            background: #007bff;
            color: white;
        }
        .step.completed {
            background: #28a745;
            color: white;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-light bg-light">
        <div class="container">
            <a class="navbar-brand" href="/">
                <i class="bi bi-speedometer2"></i> Sistema SaaS
            </a>
            <div>
                <a href="/login" class="btn btn-outline-primary">Login</a>
            </div>
        </div>
    </nav>

    <div class="container py-5">
        <div class="text-center mb-5">
            <h1>Escolha seu Plano</h1>
            <p class="text-muted">Selecione o plano ideal para suas necessidades</p>
        </div>

        <div class="step-indicator">
            <div class="step active">1</div>
            <div class="step">2</div>
            <div class="step">3</div>
        </div>

        <div id="alertContainer"></div>

        <!-- Loading -->
        <div id="loadingPlans" class="text-center py-5">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Carregando planos...</span>
            </div>
        </div>

        <!-- Planos -->
        <div id="plansContainer" class="row g-4" style="display: none;">
        </div>

        <!-- Formulário de Cliente (aparece após seleção) -->
        <div id="customerFormContainer" class="mt-5" style="display: none;">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Informações do Cliente</h5>
                </div>
                <div class="card-body">
                    <form id="customerForm">
                        <input type="hidden" name="price_id" id="selectedPriceId">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nome *</label>
                                <input type="text" class="form-control" name="name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email *</label>
                                <input type="email" class="form-control" name="email" required>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-arrow-right"></i> Continuar para Checkout
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const API_URL = <?php echo json_encode($apiUrl ?? '', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        let products = [];
        let prices = [];

        document.addEventListener('DOMContentLoaded', () => {
            loadPlans();
            
            document.getElementById('customerForm').addEventListener('submit', async (e) => {
                e.preventDefault();
                const formData = new FormData(e.target);
                const priceId = formData.get('price_id');
                
                // ✅ Valida price_id antes de submeter
                if (typeof validateStripeId === 'function') {
                    const priceIdError = validateStripeId(priceId, 'price_id', true);
                    if (priceIdError) {
                        showAlert(priceIdError, 'danger');
                        return;
                    }
                } else {
                    // Fallback se validations.js não estiver carregado
                    const priceIdPattern = /^price_[a-zA-Z0-9]+$/;
                    if (!priceIdPattern.test(priceId)) {
                        showAlert('Formato de Price ID inválido. Use: price_xxxxx', 'danger');
                        return;
                    }
                }
                
                const customerData = {
                    name: formData.get('name'),
                    email: formData.get('email')
                };
                
                try {
                    // Cria cliente
                    const customerResponse = await fetch(API_URL + '/v1/customers', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify(customerData)
                    });
                    
                    if (!customerResponse.ok) {
                        throw new Error('Erro ao criar cliente');
                    }
                    
                    const customer = await customerResponse.json();
                    
                    // Redireciona para checkout
                    window.location.href = `/checkout?customer_id=${customer.data.id}&price_id=${priceId}`;
                } catch (error) {
                    showAlert('Erro: ' + error.message, 'danger');
                }
            });
        });

        async function loadPlans() {
            try {
                // Carrega produtos e preços (sem autenticação - endpoint público ou com API key pública)
                const [productsRes, pricesRes] = await Promise.all([
                    fetch(API_URL + '/v1/products'),
                    fetch(API_URL + '/v1/prices')
                ]);
                
                products = (await productsRes.json()).data || [];
                prices = (await pricesRes.json()).data || [];
                
                renderPlans();
            } catch (error) {
                showAlert('Erro ao carregar planos: ' + error.message, 'danger');
            } finally {
                document.getElementById('loadingPlans').style.display = 'none';
                document.getElementById('plansContainer').style.display = 'flex';
            }
        }

        function renderPlans() {
            const container = document.getElementById('plansContainer');
            
            // Agrupa preços por produto
            const plans = products.map(product => {
                const productPrices = prices.filter(p => p.product === product.id && p.active);
                return { product, prices: productPrices };
            }).filter(plan => plan.prices.length > 0);
            
            if (plans.length === 0) {
                container.innerHTML = '<div class="col-12 text-center"><p class="text-muted">Nenhum plano disponível no momento.</p></div>';
                return;
            }
            
            container.innerHTML = plans.map((plan, index) => {
                const mainPrice = plan.prices[0];
                const isFeatured = index === 1; // Segundo plano como featured
                
                return `
                    <div class="col-md-4">
                        <div class="card plan-card h-100 ${isFeatured ? 'featured' : ''}">
                            <div class="card-body text-center">
                                <h3>${plan.product.name}</h3>
                                <p class="text-muted">${plan.product.description || ''}</p>
                                <div class="my-4">
                                    <h2 class="text-primary">${formatCurrency(mainPrice.unit_amount, mainPrice.currency)}</h2>
                                    ${mainPrice.recurring ? `<small class="text-muted">/${mainPrice.recurring.interval === 'month' ? 'mês' : 'ano'}</small>` : ''}
                                </div>
                                <button class="btn btn-primary w-100" onclick="selectPlan('${mainPrice.id}')">
                                    Selecionar Plano
                                </button>
                            </div>
                        </div>
                    </div>
                `;
            }).join('');
        }

        function selectPlan(priceId) {
            document.getElementById('selectedPriceId').value = priceId;
            document.getElementById('customerFormContainer').style.display = 'block';
            document.getElementById('customerFormContainer').scrollIntoView({ behavior: 'smooth' });
            
            // Atualiza step indicator
            document.querySelectorAll('.step')[0].classList.add('completed');
            document.querySelectorAll('.step')[1].classList.add('active');
        }

        function formatCurrency(value, currency = 'BRL') {
            return new Intl.NumberFormat('pt-BR', {
                style: 'currency',
                currency: currency.toLowerCase()
            }).format(value / 100);
        }

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

