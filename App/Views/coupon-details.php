<?php
/**
 * View de Detalhes do Cupom
 */
?>
<div class="container-fluid py-4">
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/coupons">Cupons</a></li>
            <li class="breadcrumb-item active">Detalhes</li>
        </ol>
    </nav>

    <div id="alertContainer"></div>
    <div id="loadingCoupon" class="text-center py-5">
        <div class="spinner-border text-primary" role="status"></div>
    </div>

    <div id="couponDetails" style="display: none;">
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Informações do Cupom</h5>
                <button class="btn btn-sm btn-outline-danger" onclick="deleteCoupon()">
                    <i class="bi bi-trash"></i> Remover Cupom
                </button>
            </div>
            <div class="card-body" id="couponInfo">
            </div>
        </div>
    </div>
</div>

<script>
const urlParams = new URLSearchParams(window.location.search);
const couponId = urlParams.get('id');

if (!couponId) {
    window.location.href = '/coupons';
}

let couponData = null;

document.addEventListener('DOMContentLoaded', () => {
    setTimeout(() => {
        loadCouponDetails();
    }, 100);
});

async function loadCouponDetails() {
    try {
        const response = await apiRequest(`/v1/coupons/${couponId}`);
        couponData = response.data;
        renderCouponInfo(couponData);

        document.getElementById('loadingCoupon').style.display = 'none';
        document.getElementById('couponDetails').style.display = 'block';
    } catch (error) {
        showAlert('Erro ao carregar detalhes: ' + error.message, 'danger');
        setTimeout(() => {
            window.location.href = '/coupons';
        }, 2000);
    }
}

function renderCouponInfo(coupon) {
    const discountValue = coupon.percent_off 
        ? `${coupon.percent_off}%` 
        : formatCurrency(coupon.amount_off, coupon.currency || 'BRL');
    
    const discountType = coupon.percent_off ? 'Percentual' : 'Valor Fixo';
    
    let durationText = coupon.duration;
    if (coupon.duration === 'repeating' && coupon.duration_in_months) {
        durationText = `Repetir por ${coupon.duration_in_months} ${coupon.duration_in_months === 1 ? 'mês' : 'meses'}`;
    } else if (coupon.duration === 'once') {
        durationText = 'Uma vez';
    } else if (coupon.duration === 'forever') {
        durationText = 'Para sempre';
    }
    
    document.getElementById('couponInfo').innerHTML = `
        <div class="row">
            <div class="col-md-6">
                <p><strong>ID:</strong> <code>${coupon.id}</code></p>
                <p><strong>Nome:</strong> ${coupon.name || '-'}</p>
                <p><strong>Tipo de Desconto:</strong> <span class="badge bg-info">${discountType}</span></p>
                <p><strong>Valor do Desconto:</strong> <span class="h5 text-primary">${discountValue}</span></p>
                <p><strong>Duração:</strong> ${durationText}</p>
            </div>
            <div class="col-md-6">
                <p><strong>Status:</strong> <span class="badge bg-${coupon.valid ? 'success' : 'secondary'}">${coupon.valid ? 'Válido' : 'Inválido'}</span></p>
                <p><strong>Vezes Resgatado:</strong> ${coupon.times_redeemed || 0}</p>
                <p><strong>Máximo de Resgates:</strong> ${coupon.max_redemptions ? coupon.max_redemptions : 'Ilimitado'}</p>
                ${coupon.redeem_by ? `<p><strong>Válido até:</strong> ${formatDate(coupon.redeem_by)}</p>` : ''}
                <p><strong>Criado em:</strong> ${formatDate(coupon.created)}</p>
            </div>
        </div>
        ${coupon.metadata && Object.keys(coupon.metadata).length > 0 ? `
            <div class="row mt-3">
                <div class="col-12">
                    <h6>Metadados</h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered">
                            <thead>
                                <tr>
                                    <th>Chave</th>
                                    <th>Valor</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${Object.entries(coupon.metadata).map(([key, value]) => `
                                    <tr>
                                        <td><code>${key}</code></td>
                                        <td>${value}</td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        ` : ''}
    `;
}

async function deleteCoupon() {
    // ✅ Validação básica: cupom ID não pode estar vazio
    if (!couponId || couponId.trim() === '') {
        showAlert('ID do cupom inválido', 'danger');
        return;
    }
    
    const confirmed = await showConfirmModal(
        'Tem certeza que deseja remover este cupom? Esta ação não pode ser desfeita.',
        'Confirmar Exclusão',
        'Remover Cupom'
    );
    if (!confirmed) return;
    
    try {
        await apiRequest(`/v1/coupons/${couponId}`, { method: 'DELETE' });
        showAlert('Cupom removido com sucesso!', 'success');
        setTimeout(() => {
            window.location.href = '/coupons';
        }, 1500);
    } catch (error) {
        showAlert('Erro ao remover cupom: ' + error.message, 'danger');
    }
}
</script>

