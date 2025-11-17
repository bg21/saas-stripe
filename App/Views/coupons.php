<?php
/**
 * View de Cupons
 */
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0"><i class="bi bi-ticket-perforated"></i> Cupons</h1>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createCouponModal">
            <i class="bi bi-plus-circle"></i> Novo Cupom
        </button>
    </div>

    <div id="alertContainer"></div>

    <!-- Lista -->
    <div class="card">
        <div class="card-body">
            <div id="loadingCoupons" class="text-center py-5">
                <div class="spinner-border text-primary" role="status"></div>
            </div>
            <div id="couponsList" style="display: none;">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nome</th>
                                <th>Tipo</th>
                                <th>Valor</th>
                                <th>Duração</th>
                                <th>Status</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody id="couponsTableBody">
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Criar Cupom -->
<div class="modal fade" id="createCouponModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Novo Cupom</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="createCouponForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">ID do Cupom *</label>
                        <input type="text" class="form-control" name="id" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tipo de Desconto *</label>
                        <select class="form-select" name="discount_type" id="discountType" required>
                            <option value="percent">Percentual</option>
                            <option value="amount">Valor Fixo</option>
                        </select>
                    </div>
                    <div class="mb-3" id="percentField">
                        <label class="form-label">Percentual de Desconto *</label>
                        <input type="number" class="form-control" name="percent_off" min="1" max="100">
                    </div>
                    <div class="mb-3" id="amountField" style="display: none;">
                        <label class="form-label">Valor do Desconto (em centavos) *</label>
                        <input type="number" class="form-control" name="amount_off" min="1">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Duração *</label>
                        <select class="form-select" name="duration" required>
                            <option value="once">Uma vez</option>
                            <option value="repeating">Repetir</option>
                            <option value="forever">Para sempre</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Criar Cupom</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    setTimeout(() => {
        loadCoupons();
    }, 100);
    
    document.getElementById('discountType').addEventListener('change', (e) => {
        if (e.target.value === 'percent') {
            document.getElementById('percentField').style.display = 'block';
            document.getElementById('amountField').style.display = 'none';
        } else {
            document.getElementById('percentField').style.display = 'none';
            document.getElementById('amountField').style.display = 'block';
        }
    });
    
    document.getElementById('createCouponForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(e.target);
        const data = {
            id: formData.get('id'),
            duration: formData.get('duration')
        };
        
        if (formData.get('discount_type') === 'percent') {
            data.percent_off = parseInt(formData.get('percent_off'));
        } else {
            data.amount_off = parseInt(formData.get('amount_off'));
        }
        
        try {
            await apiRequest('/v1/coupons', {
                method: 'POST',
                body: JSON.stringify(data)
            });
            
            showAlert('Cupom criado com sucesso!', 'success');
            bootstrap.Modal.getInstance(document.getElementById('createCouponModal')).hide();
            e.target.reset();
            loadCoupons();
        } catch (error) {
            showAlert(error.message, 'danger');
        }
    });
});

async function loadCoupons() {
    try {
        document.getElementById('loadingCoupons').style.display = 'block';
        document.getElementById('couponsList').style.display = 'none';
        
        const response = await apiRequest('/v1/coupons');
        const coupons = response.data || [];
        
        renderCoupons(coupons);
    } catch (error) {
        showAlert('Erro ao carregar cupons: ' + error.message, 'danger');
    } finally {
        document.getElementById('loadingCoupons').style.display = 'none';
        document.getElementById('couponsList').style.display = 'block';
    }
}

function renderCoupons(coupons) {
    const tbody = document.getElementById('couponsTableBody');
    
    if (coupons.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">Nenhum cupom encontrado</td></tr>';
        return;
    }
    
    tbody.innerHTML = coupons.map(coupon => `
        <tr>
            <td><code>${coupon.id}</code></td>
            <td>${coupon.name || '-'}</td>
            <td><span class="badge bg-info">${coupon.percent_off ? 'Percentual' : 'Valor Fixo'}</span></td>
            <td>${coupon.percent_off ? `${coupon.percent_off}%` : formatCurrency(coupon.amount_off, coupon.currency || 'BRL')}</td>
            <td>${coupon.duration}</td>
            <td><span class="badge bg-${coupon.valid ? 'success' : 'secondary'}">${coupon.valid ? 'Válido' : 'Inválido'}</span></td>
            <td>
                <button class="btn btn-sm btn-outline-primary" onclick="viewCoupon('${coupon.id}')">
                    <i class="bi bi-eye"></i>
                </button>
                <button class="btn btn-sm btn-outline-danger" onclick="deleteCoupon('${coupon.id}')">
                    <i class="bi bi-trash"></i>
                </button>
            </td>
        </tr>
    `).join('');
}

function viewCoupon(id) {
    alert('Detalhes do cupom: ' + id);
}

async function deleteCoupon(id) {
    if (!confirm('Tem certeza que deseja remover este cupom?')) return;
    
    try {
        await apiRequest(`/v1/coupons/${id}`, { method: 'DELETE' });
        showAlert('Cupom removido com sucesso!', 'success');
        loadCoupons();
    } catch (error) {
        showAlert(error.message, 'danger');
    }
}
</script>

