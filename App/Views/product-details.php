<?php
/**
 * View de Detalhes do Produto
 */
?>
<div class="container-fluid py-4">
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/products">Produtos</a></li>
            <li class="breadcrumb-item active">Detalhes</li>
        </ol>
    </nav>

    <div id="alertContainer"></div>
    <div id="loadingProduct" class="text-center py-5">
        <div class="spinner-border text-primary" role="status"></div>
    </div>

    <div id="productDetails" style="display: none;">
        <!-- Modo Visualização -->
        <div id="viewMode">
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Informações do Produto</h5>
                    <button class="btn btn-sm btn-outline-primary" onclick="toggleEditMode()">
                        <i class="bi bi-pencil"></i> Editar
                    </button>
                </div>
                <div class="card-body" id="productInfo">
                </div>
            </div>
        </div>

        <!-- Modo Edição -->
        <div id="editMode" style="display: none;">
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Editar Produto</h5>
                    <button class="btn btn-sm btn-outline-secondary" onclick="toggleEditMode()">
                        <i class="bi bi-x"></i> Cancelar
                    </button>
                </div>
                <div class="card-body">
                    <form id="editProductForm" class="needs-validation" novalidate>
                        <input type="hidden" id="editProductId" name="product_id">
                        
                        <div class="mb-3">
                            <label for="editProductName" class="form-label">
                                Nome do Produto <span class="text-danger">*</span>
                            </label>
                            <input 
                                type="text" 
                                class="form-control" 
                                id="editProductName" 
                                name="name"
                                required 
                                minlength="1"
                            >
                            <div class="invalid-feedback">
                                Por favor, insira um nome para o produto.
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="editProductDescription" class="form-label">
                                Descrição <small class="text-muted">(Opcional)</small>
                            </label>
                            <textarea 
                                class="form-control" 
                                id="editProductDescription" 
                                name="description"
                                rows="3"
                            ></textarea>
                        </div>

                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input 
                                    class="form-check-input" 
                                    type="checkbox" 
                                    id="editProductActive" 
                                    name="active"
                                    checked
                                >
                                <label class="form-check-label" for="editProductActive">
                                    Produto ativo
                                </label>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="editProductImages" class="form-label">
                                Imagens (URLs, uma por linha) <small class="text-muted">(Opcional)</small>
                            </label>
                            <textarea 
                                class="form-control font-monospace" 
                                id="editProductImages" 
                                name="images"
                                rows="3"
                                placeholder="https://exemplo.com/imagem1.jpg"
                            ></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="editProductMetadata" class="form-label">
                                Metadados (JSON) <small class="text-muted">(Opcional)</small>
                            </label>
                            <textarea 
                                class="form-control font-monospace" 
                                id="editProductMetadata" 
                                name="metadata"
                                rows="4"
                            ></textarea>
                        </div>

                        <div id="editProductError" class="alert alert-danger d-none mb-3" role="alert"></div>

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

        <!-- Preços do Produto -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Preços Associados</h5>
            </div>
            <div class="card-body">
                <div id="pricesList"></div>
            </div>
        </div>
    </div>
</div>

<script>
const urlParams = new URLSearchParams(window.location.search);
const productId = urlParams.get('id');

if (!productId) {
    window.location.href = '/products';
}

// ✅ Valida formato de product_id da URL
if (typeof validateStripeId === 'function') {
    const productIdError = validateStripeId(productId, 'product_id', true);
    if (productIdError) {
        showAlert('ID de produto inválido na URL: ' + productIdError, 'danger');
        window.location.href = '/products';
        throw new Error('Invalid product_id format');
    }
} else {
    // Fallback: validação básica
    const productIdPattern = /^prod_[a-zA-Z0-9]+$/;
    if (!productIdPattern.test(productId)) {
        showAlert('Formato de Product ID inválido na URL. Use: prod_xxxxx', 'danger');
        window.location.href = '/products';
        throw new Error('Invalid product_id format');
    }
}

let productData = null;

document.addEventListener('DOMContentLoaded', () => {
    setTimeout(() => {
        loadProductDetails();
    }, 100);
});

async function loadProductDetails() {
    try {
        const [product, prices] = await Promise.all([
            apiRequest(`/v1/products/${productId}`),
            apiRequest(`/v1/prices?product=${productId}`).catch(() => ({ data: [] }))
        ]);

        productData = product.data;
        renderProductInfo(productData);
        renderPrices(prices.data || []);

        document.getElementById('loadingProduct').style.display = 'none';
        document.getElementById('productDetails').style.display = 'block';
    } catch (error) {
        showAlert('Erro ao carregar detalhes: ' + error.message, 'danger');
    }
}

function renderProductInfo(product) {
    document.getElementById('productInfo').innerHTML = `
        <div class="row">
            <div class="col-md-6">
                <p><strong>ID:</strong> <code>${product.id}</code></p>
                <p><strong>Nome:</strong> ${product.name}</p>
                <p><strong>Descrição:</strong> ${product.description || '-'}</p>
                <p><strong>Status:</strong> <span class="badge bg-${product.active ? 'success' : 'secondary'}">${product.active ? 'Ativo' : 'Inativo'}</span></p>
            </div>
            <div class="col-md-6">
                ${product.images && product.images.length > 0 ? `
                    <p><strong>Imagens:</strong></p>
                    <div class="d-flex gap-2 flex-wrap">
                        ${product.images.map(img => `<img src="${img}" alt="Produto" class="img-thumbnail" style="max-width: 100px; max-height: 100px;">`).join('')}
                    </div>
                ` : ''}
                <p class="mt-3"><strong>Criado em:</strong> ${formatDate(product.created)}</p>
            </div>
        </div>
    `;
}

function renderPrices(prices) {
    const container = document.getElementById('pricesList');
    if (prices.length === 0) {
        container.innerHTML = '<p class="text-muted">Nenhum preço associado a este produto</p>';
        return;
    }
    container.innerHTML = `
        <div class="table-responsive">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Valor</th>
                        <th>Moeda</th>
                        <th>Tipo</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    ${prices.map(price => `
                        <tr>
                            <td><code>${price.id}</code></td>
                            <td>${formatCurrency(price.unit_amount, price.currency)}</td>
                            <td>${price.currency.toUpperCase()}</td>
                            <td>${price.type === 'recurring' ? `${price.recurring?.interval || 'recurring'}` : 'one-time'}</td>
                            <td><span class="badge bg-${price.active ? 'success' : 'secondary'}">${price.active ? 'Ativo' : 'Inativo'}</span></td>
                            <td>
                                <a href="/price-details?id=${price.id}" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-eye"></i>
                                </a>
                            </td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
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
        if (productData) {
            loadProductForEdit();
        }
        viewMode.style.display = 'none';
        editMode.style.display = 'block';
    }
}

function loadProductForEdit() {
    if (!productData) return;
    
    document.getElementById('editProductId').value = productData.id;
    document.getElementById('editProductName').value = productData.name || '';
    document.getElementById('editProductDescription').value = productData.description || '';
    document.getElementById('editProductActive').checked = productData.active !== false;
    
    if (productData.images && productData.images.length > 0) {
        document.getElementById('editProductImages').value = productData.images.join('\n');
    } else {
        document.getElementById('editProductImages').value = '';
    }
    
    if (productData.metadata) {
        document.getElementById('editProductMetadata').value = 
            JSON.stringify(productData.metadata, null, 2);
    } else {
        document.getElementById('editProductMetadata').value = '';
    }
}

// Submissão do formulário de edição
document.getElementById('editProductForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    if (!this.checkValidity()) {
        this.classList.add('was-validated');
        return;
    }

    const productId = document.getElementById('editProductId').value;
    
    // ✅ Valida formato de product_id antes de submeter
    if (typeof validateStripeId === 'function') {
        const productIdError = validateStripeId(productId, 'product_id', true);
        if (productIdError) {
            showAlert(productIdError, 'danger');
            document.getElementById('editProductId').classList.add('is-invalid');
            return;
        }
    } else {
        // Fallback: validação básica
        const productIdPattern = /^prod_[a-zA-Z0-9]+$/;
        if (!productIdPattern.test(productId)) {
            showAlert('Formato de Product ID inválido. Use: prod_xxxxx', 'danger');
            document.getElementById('editProductId').classList.add('is-invalid');
            return;
        }
    }
    
    const formData = {
        name: document.getElementById('editProductName').value.trim(),
        description: document.getElementById('editProductDescription').value.trim(),
        active: document.getElementById('editProductActive').checked
    };

    const imagesText = document.getElementById('editProductImages').value.trim();
    if (imagesText) {
        formData.images = imagesText.split('\n').filter(url => url.trim().length > 0);
    }

    const metadataText = document.getElementById('editProductMetadata').value.trim();
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
    const errorDiv = document.getElementById('editProductError');
    
    submitBtn.disabled = true;
    spinner.classList.remove('d-none');
    errorDiv.classList.add('d-none');

    apiRequest(`/v1/products/${productId}`, {
        method: 'PUT',
        body: JSON.stringify(formData)
    })
    .then(data => {
        if (data.success) {
            showAlert('Produto atualizado com sucesso!', 'success');
            productData = data.data;
            renderProductInfo(productData);
            toggleEditMode();
        } else {
            throw new Error(data.error || 'Erro ao atualizar produto');
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

