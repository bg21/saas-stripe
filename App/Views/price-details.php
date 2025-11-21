<?php
/**
 * View de Detalhes do Preço
 */
?>
<div class="container-fluid py-4">
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/prices">Preços</a></li>
            <li class="breadcrumb-item active">Detalhes</li>
        </ol>
    </nav>

    <div id="alertContainer"></div>
    <div id="loadingPrice" class="text-center py-5">
        <div class="spinner-border text-primary" role="status"></div>
    </div>

    <div id="priceDetails" style="display: none;">
        <!-- Modo Visualização -->
        <div id="viewMode">
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Informações do Preço</h5>
                    <button class="btn btn-sm btn-outline-primary" onclick="toggleEditMode()">
                        <i class="bi bi-pencil"></i> Editar
                    </button>
                </div>
                <div class="card-body" id="priceInfo">
                </div>
            </div>
        </div>

        <!-- Modo Edição -->
        <div id="editMode" style="display: none;">
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Editar Preço</h5>
                    <button class="btn btn-sm btn-outline-secondary" onclick="toggleEditMode()">
                        <i class="bi bi-x"></i> Cancelar
                    </button>
                </div>
                <div class="card-body">
                    <form id="editPriceForm" class="needs-validation" novalidate>
                        <input type="hidden" id="editPriceId" name="price_id">
                        
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            <strong>Nota:</strong> No Stripe, preços não podem ter valor, moeda ou tipo alterados após criação. Apenas metadata, active e nickname podem ser atualizados.
                        </div>

                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input 
                                    class="form-check-input" 
                                    type="checkbox" 
                                    id="editPriceActive" 
                                    name="active"
                                >
                                <label class="form-check-label" for="editPriceActive">
                                    Preço ativo
                                </label>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="editPriceNickname" class="form-label">
                                Apelido (Nickname) <small class="text-muted">(Opcional)</small>
                            </label>
                            <input 
                                type="text" 
                                class="form-control" 
                                id="editPriceNickname" 
                                name="nickname"
                                placeholder="Ex: Plano Mensal Premium"
                            >
                        </div>

                        <div class="mb-3">
                            <label for="editPriceMetadata" class="form-label">
                                Metadados (JSON) <small class="text-muted">(Opcional)</small>
                            </label>
                            <textarea 
                                class="form-control font-monospace" 
                                id="editPriceMetadata" 
                                name="metadata"
                                rows="4"
                            ></textarea>
                        </div>

                        <div id="editPriceError" class="alert alert-danger d-none mb-3" role="alert"></div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <span class="spinner-border spinner-border-sm d-none me-2" role="status"></span>
                                Salvar Alterações
                            </button>
                            <button type="button" class="btn btn-secondary" onclick="toggleEditMode()">
                                Cancelar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const urlParams = new URLSearchParams(window.location.search);
const priceId = urlParams.get('id');

if (!priceId) {
    window.location.href = '/prices';
}

// ✅ Valida formato de price_id da URL
if (typeof validateStripeId === 'function') {
    const priceIdError = validateStripeId(priceId, 'price_id', true);
    if (priceIdError) {
        showAlert('ID de preço inválido na URL: ' + priceIdError, 'danger');
        window.location.href = '/prices';
        throw new Error('Invalid price_id format');
    }
} else {
    // Fallback: validação básica
    const priceIdPattern = /^price_[a-zA-Z0-9]+$/;
    if (!priceIdPattern.test(priceId)) {
        showAlert('Formato de Price ID inválido na URL. Use: price_xxxxx', 'danger');
        window.location.href = '/prices';
        throw new Error('Invalid price_id format');
    }
}

let priceData = null;

document.addEventListener('DOMContentLoaded', () => {
    setTimeout(() => {
        loadPriceDetails();
    }, 100);
});

async function loadPriceDetails() {
    try {
        const price = await apiRequest(`/v1/prices/${priceId}`);
        priceData = price.data;
        renderPriceInfo(priceData);

        document.getElementById('loadingPrice').style.display = 'none';
        document.getElementById('priceDetails').style.display = 'block';
    } catch (error) {
        showAlert('Erro ao carregar detalhes: ' + error.message, 'danger');
    }
}

function renderPriceInfo(price) {
    const interval = price.recurring ? `${price.recurring.interval}${price.recurring.interval_count > 1 ? ` (a cada ${price.recurring.interval_count})` : ''}` : 'one-time';
    
    document.getElementById('priceInfo').innerHTML = `
        <div class="row">
            <div class="col-md-6">
                <p><strong>ID:</strong> <code>${price.id}</code></p>
                <p><strong>Produto:</strong> <a href="/product-details?id=${price.product}">${price.product}</a></p>
                <p><strong>Valor:</strong> ${formatCurrency(price.unit_amount, price.currency)}</p>
                <p><strong>Moeda:</strong> ${price.currency.toUpperCase()}</p>
            </div>
            <div class="col-md-6">
                <p><strong>Tipo:</strong> ${price.type === 'recurring' ? 'Recorrente' : 'Pagamento Único'}</p>
                <p><strong>Intervalo:</strong> ${interval}</p>
                <p><strong>Status:</strong> <span class="badge bg-${price.active ? 'success' : 'secondary'}">${price.active ? 'Ativo' : 'Inativo'}</span></p>
                ${price.nickname ? `<p><strong>Apelido:</strong> ${price.nickname}</p>` : ''}
                <p><strong>Criado em:</strong> ${formatDate(price.created)}</p>
            </div>
        </div>
    `;
}

function toggleEditMode() {
    const viewMode = document.getElementById('viewMode');
    const editMode = document.getElementById('editMode');
    
    if (viewMode.style.display === 'none') {
        viewMode.style.display = 'block';
        editMode.style.display = 'none';
    } else {
        if (priceData) {
            loadPriceForEdit();
        }
        viewMode.style.display = 'none';
        editMode.style.display = 'block';
    }
}

function loadPriceForEdit() {
    if (!priceData) return;
    
    document.getElementById('editPriceId').value = priceData.id;
    document.getElementById('editPriceActive').checked = priceData.active !== false;
    document.getElementById('editPriceNickname').value = priceData.nickname || '';
    
    if (priceData.metadata) {
        document.getElementById('editPriceMetadata').value = 
            JSON.stringify(priceData.metadata, null, 2);
    } else {
        document.getElementById('editPriceMetadata').value = '';
    }
}

// Submissão do formulário de edição
document.getElementById('editPriceForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const priceId = document.getElementById('editPriceId').value;
    
    // ✅ Valida formato de price_id antes de submeter
    if (typeof validateStripeId === 'function') {
        const priceIdError = validateStripeId(priceId, 'price_id', true);
        if (priceIdError) {
            showAlert(priceIdError, 'danger');
            document.getElementById('editPriceId').classList.add('is-invalid');
            return;
        }
    } else {
        // Fallback: validação básica
        const priceIdPattern = /^price_[a-zA-Z0-9]+$/;
        if (!priceIdPattern.test(priceId)) {
            showAlert('Formato de Price ID inválido. Use: price_xxxxx', 'danger');
            document.getElementById('editPriceId').classList.add('is-invalid');
            return;
        }
    }
    
    const formData = {
        active: document.getElementById('editPriceActive').checked
    };

    const nickname = document.getElementById('editPriceNickname').value.trim();
    if (nickname) {
        formData.nickname = nickname;
    }

    const metadataText = document.getElementById('editPriceMetadata').value.trim();
    if (metadataText) {
        try {
            formData.metadata = JSON.parse(metadataText);
        } catch (error) {
            showAlert('Erro: Metadados devem estar em formato JSON válido', 'danger');
            return;
        }
    }

    const submitBtn = this.querySelector('button[type="submit"]');
    const spinner = submitBtn.querySelector('.spinner-border');
    const errorDiv = document.getElementById('editPriceError');
    
    submitBtn.disabled = true;
    spinner.classList.remove('d-none');
    errorDiv.classList.add('d-none');

    apiRequest(`/v1/prices/${priceId}`, {
        method: 'PUT',
        body: JSON.stringify(formData)
    })
    .then(data => {
        if (data.success) {
            showAlert('Preço atualizado com sucesso!', 'success');
            priceData = data.data;
            renderPriceInfo(priceData);
            toggleEditMode();
        } else {
            throw new Error(data.error || 'Erro ao atualizar preço');
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        errorDiv.textContent = error.message;
        errorDiv.classList.remove('d-none');
    })
    .finally(() => {
        submitBtn.disabled = false;
        spinner.classList.add('d-none');
    });
});
</script>

