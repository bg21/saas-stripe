<?php
/**
 * View de Portal de Cobrança
 */
?>
<div class="container-fluid py-4">
    <h1 class="h3 mb-4"><i class="bi bi-door-open"></i> Portal de Cobrança</h1>

    <div id="alertContainer"></div>

    <div class="card">
        <div class="card-body">
            <p class="text-muted mb-4">
                Gere um link para o portal de cobrança do Stripe, onde o cliente pode gerenciar sua assinatura, métodos de pagamento e faturas.
            </p>

            <form id="billingPortalForm">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Cliente ID *</label>
                        <input type="number" class="form-control" name="customer_id" required>
                        <small class="text-muted">ID do cliente no sistema</small>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">URL de Retorno</label>
                        <input type="url" class="form-control" name="return_url" value="<?php echo htmlspecialchars(($apiUrl ?? '') . '/dashboard', ENT_QUOTES); ?>">
                        <small class="text-muted">URL para onde o cliente será redirecionado após usar o portal</small>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-box-arrow-up-right"></i> Gerar Link do Portal
                </button>
            </form>

            <div id="portalResult" class="mt-4" style="display: none;">
                <div class="alert alert-success">
                    <h5>Link do Portal Gerado!</h5>
                    <p>Clique no botão abaixo para abrir o portal de cobrança:</p>
                    <a href="#" id="portalLink" class="btn btn-success" target="_blank">
                        <i class="bi bi-box-arrow-up-right"></i> Abrir Portal de Cobrança
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('billingPortalForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(e.target);
        const data = {
            customer_id: parseInt(formData.get('customer_id')),
            return_url: formData.get('return_url') || window.location.origin + '/dashboard'
        };
        
        try {
            const response = await apiRequest('/v1/billing-portal', {
                method: 'POST',
                body: JSON.stringify(data)
            });
            
            if (response.success && response.data.url) {
                document.getElementById('portalLink').href = response.data.url;
                document.getElementById('portalResult').style.display = 'block';
                showAlert('Link do portal gerado com sucesso!', 'success');
            }
        } catch (error) {
            showAlert(error.message, 'danger');
        }
    });
});
</script>

