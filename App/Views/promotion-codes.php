<?php
/**
 * View de Códigos Promocionais
 */
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0"><i class="bi bi-tag"></i> Códigos Promocionais</h1>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createPromoCodeModal">
            <i class="bi bi-plus-circle"></i> Novo Código
        </button>
    </div>

    <div id="alertContainer"></div>

    <!-- Lista -->
    <div class="card">
        <div class="card-body">
            <div id="loadingPromoCodes" class="text-center py-5">
                <div class="spinner-border text-primary" role="status"></div>
            </div>
            <div id="promoCodesList" style="display: none;">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Cupom</th>
                                <th>Status</th>
                                <th>Limite de Uso</th>
                                <th>Usos</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody id="promoCodesTableBody">
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Criar Código Promocional -->
<div class="modal fade" id="createPromoCodeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Novo Código Promocional</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="createPromoCodeForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Código *</label>
                        <input type="text" class="form-control" name="code" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Cupom (ID) *</label>
                        <input type="text" class="form-control" name="coupon" placeholder="cupom_id" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Criar Código</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let coupons = [];

document.addEventListener('DOMContentLoaded', () => {
    setTimeout(() => {
        loadPromoCodes();
        loadCoupons();
    }, 100);
    
    document.getElementById('createPromoCodeForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(e.target);
        const data = {
            code: formData.get('code'),
            coupon: formData.get('coupon')
        };
        
        try {
            await apiRequest('/v1/promotion-codes', {
                method: 'POST',
                body: JSON.stringify(data)
            });
            
            showAlert('Código promocional criado com sucesso!', 'success');
            bootstrap.Modal.getInstance(document.getElementById('createPromoCodeModal')).hide();
            e.target.reset();
            loadPromoCodes();
        } catch (error) {
            showAlert(error.message, 'danger');
        }
    });
});

async function loadCoupons() {
    try {
        const response = await apiRequest('/v1/coupons');
        coupons = response.data || [];
    } catch (error) {
        console.error('Erro ao carregar cupons:', error);
    }
}

async function loadPromoCodes() {
    try {
        document.getElementById('loadingPromoCodes').style.display = 'block';
        document.getElementById('promoCodesList').style.display = 'none';
        
        const response = await apiRequest('/v1/promotion-codes');
        const promoCodes = response.data || [];
        
        renderPromoCodes(promoCodes);
    } catch (error) {
        showAlert('Erro ao carregar códigos promocionais: ' + error.message, 'danger');
    } finally {
        document.getElementById('loadingPromoCodes').style.display = 'none';
        document.getElementById('promoCodesList').style.display = 'block';
    }
}

function renderPromoCodes(promoCodes) {
    const tbody = document.getElementById('promoCodesTableBody');
    
    if (promoCodes.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">Nenhum código promocional encontrado</td></tr>';
        return;
    }
    
    tbody.innerHTML = promoCodes.map(promo => `
        <tr>
            <td><code>${promo.code}</code></td>
            <td>${promo.coupon?.id || promo.coupon || '-'}</td>
            <td><span class="badge bg-${promo.active ? 'success' : 'secondary'}">${promo.active ? 'Ativo' : 'Inativo'}</span></td>
            <td>${promo.max_redemptions || 'Ilimitado'}</td>
            <td>${promo.times_redeemed || 0}</td>
            <td>
                <button class="btn btn-sm btn-outline-primary" onclick="viewPromoCode('${promo.id}')">
                    <i class="bi bi-eye"></i>
                </button>
            </td>
        </tr>
    `).join('');
}

function viewPromoCode(id) {
    alert('Detalhes do código: ' + id);
}
</script>

