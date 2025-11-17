<?php
/**
 * View de Produtos
 */
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0"><i class="bi bi-box"></i> Produtos</h1>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createProductModal">
            <i class="bi bi-plus-circle"></i> Novo Produto
        </button>
    </div>

    <div id="alertContainer"></div>

    <!-- Filtros -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Buscar</label>
                    <input type="text" class="form-control" id="searchInput" placeholder="Nome, descrição...">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select class="form-select" id="statusFilter">
                        <option value="">Todos</option>
                        <option value="true">Ativos</option>
                        <option value="false">Inativos</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button class="btn btn-outline-secondary w-100" onclick="loadProducts()">
                        <i class="bi bi-search"></i> Filtrar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Lista de Produtos -->
    <div class="row" id="productsGrid">
        <div class="col-12 text-center py-5" id="loadingProducts">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Carregando...</span>
            </div>
        </div>
    </div>
</div>

<!-- Modal Criar Produto -->
<div class="modal fade" id="createProductModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Novo Produto</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="createProductForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nome *</label>
                        <input type="text" class="form-control" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Descrição</label>
                        <textarea class="form-control" name="description" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="active" id="productActive" checked>
                            <label class="form-check-label" for="productActive">
                                Produto ativo
                            </label>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Imagens (URLs, uma por linha)</label>
                        <textarea class="form-control" name="images" rows="3" placeholder="https://exemplo.com/imagem1.jpg"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Criar Produto</button>
                </div>
            </form>
        </div>
    </div>
</div>


<script>
let products = [];

let currentPage = 1;
let pageSize = 20;

document.addEventListener('DOMContentLoaded', () => {
    // Carrega dados imediatamente
    loadProducts();
    
    // Debounce na busca
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('input', debounce(() => {
            currentPage = 1;
            loadProducts();
        }, 500));
    }
    
    // Form criar produto
    document.getElementById('createProductForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(e.target);
        const data = Object.fromEntries(formData);
        
        // Processa imagens
        if (data.images) {
            data.images = data.images.split('\n').filter(url => url.trim());
        }
        
        // Processa active
        data.active = document.getElementById('productActive').checked;
        
        try {
            const response = await apiRequest('/v1/products', {
                method: 'POST',
                body: JSON.stringify(data)
            });
            
            showAlert('Produto criado com sucesso!', 'success');
            bootstrap.Modal.getInstance(document.getElementById('createProductModal')).hide();
            e.target.reset();
            loadProducts();
        } catch (error) {
            showAlert(error.message, 'danger');
        }
    });
    
});

async function loadProducts() {
    try {
        document.getElementById('loadingProducts').style.display = 'block';
        
        const params = new URLSearchParams();
        params.append('page', currentPage);
        params.append('limit', pageSize);
        
        const statusFilter = document.getElementById('statusFilter')?.value;
        if (statusFilter) {
            params.append('active', statusFilter);
        }
        
        const search = document.getElementById('searchInput')?.value.trim();
        if (search) {
            params.append('search', search);
        }
        
        const url = '/v1/products?' + params.toString();
        const response = await apiRequest(url, {
            cacheTTL: 15000 // Cache de 15 segundos (produtos mudam pouco)
        });
        
        products = response.data || [];
        
        renderProducts();
    } catch (error) {
        showAlert('Erro ao carregar produtos: ' + error.message, 'danger');
    } finally {
        document.getElementById('loadingProducts').style.display = 'none';
    }
}

function renderProducts() {
    const grid = document.getElementById('productsGrid');
    
    if (products.length === 0) {
        grid.innerHTML = `
            <div class="col-12 text-center py-5">
                <i class="bi bi-box fs-1 text-muted"></i>
                <p class="text-muted mt-3">Nenhum produto encontrado</p>
            </div>
        `;
        return;
    }
    
    grid.innerHTML = products.map(product => `
        <div class="col-md-4 mb-4">
            <div class="card h-100">
                ${product.images && product.images.length > 0 ? `
                    <img src="${product.images[0]}" class="card-img-top" style="height: 200px; object-fit: cover;">
                ` : `
                    <div class="card-img-top bg-light d-flex align-items-center justify-content-center" style="height: 200px;">
                        <i class="bi bi-box fs-1 text-muted"></i>
                    </div>
                `}
                <div class="card-body">
                    <h5 class="card-title">${product.name}</h5>
                    <p class="card-text text-muted">${product.description || 'Sem descrição'}</p>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="badge bg-${product.active ? 'success' : 'secondary'}">
                            ${product.active ? 'Ativo' : 'Inativo'}
                        </span>
                        <div>
                            <a href="/product-details?id=${product.id}" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-eye"></i> Ver Detalhes
                            </a>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <small class="text-muted">ID: <code>${product.id}</code></small>
                </div>
            </div>
        </div>
    `).join('');
}


async function deleteProduct(productId) {
    if (!confirm('Tem certeza que deseja remover este produto?')) {
        return;
    }
    
    try {
        await apiRequest(`/v1/products/${productId}`, {
            method: 'DELETE'
        });
        
        showAlert('Produto removido com sucesso!', 'success');
        loadProducts();
    } catch (error) {
        showAlert(error.message, 'danger');
    }
}
</script>

