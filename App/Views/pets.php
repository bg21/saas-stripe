<?php
/**
 * View de Gerenciamento de Pets
 */
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0"><i class="bi bi-paw"></i> Pets</h1>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createPetModal">
            <i class="bi bi-plus-circle"></i> Novo Pet
        </button>
    </div>

    <div id="alertContainer"></div>

    <!-- Filtros -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Buscar</label>
                    <input type="text" class="form-control" id="searchInput" placeholder="Nome, espécie, raça...">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Espécie</label>
                    <select class="form-select" id="speciesFilter">
                        <option value="">Todas</option>
                        <option value="Cachorro">Cachorro</option>
                        <option value="Gato">Gato</option>
                        <option value="Ave">Ave</option>
                        <option value="Roedor">Roedor</option>
                        <option value="Réptil">Réptil</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Cliente</label>
                    <select class="form-select" id="clientFilter">
                        <option value="">Todos</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button class="btn btn-outline-secondary w-100" onclick="loadPets()">
                        <i class="bi bi-search"></i> Filtrar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Lista de Pets -->
    <div class="card">
        <div class="card-body">
            <div id="loadingPets" class="text-center py-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Carregando...</span>
                </div>
            </div>
            <div id="petsList" style="display: none;">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nome</th>
                                <th>Espécie</th>
                                <th>Raça</th>
                                <th>Idade</th>
                                <th>Peso</th>
                                <th>Cliente</th>
                                <th>Criado em</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody id="petsTableBody">
                        </tbody>
                    </table>
                </div>
                <div id="emptyState" class="text-center py-5" style="display: none;">
                    <i class="bi bi-paw fs-1 text-muted"></i>
                    <p class="text-muted mt-3">Nenhum pet encontrado</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Criar Pet -->
<div class="modal fade" id="createPetModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Novo Pet</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="createPetForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Cliente *</label>
                        <select class="form-select" name="client_id" id="createPetClientId" required>
                            <option value="">Selecione um cliente...</option>
                        </select>
                        <div class="invalid-feedback"></div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nome *</label>
                            <input type="text" class="form-control" name="name" required minlength="2" maxlength="255">
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Espécie *</label>
                            <select class="form-select" name="species" required>
                                <option value="">Selecione...</option>
                                <option value="Cachorro">Cachorro</option>
                                <option value="Gato">Gato</option>
                                <option value="Ave">Ave</option>
                                <option value="Roedor">Roedor</option>
                                <option value="Réptil">Réptil</option>
                                <option value="Outro">Outro</option>
                            </select>
                            <div class="invalid-feedback"></div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Raça</label>
                            <input type="text" class="form-control" name="breed" maxlength="100">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Sexo</label>
                            <select class="form-select" name="gender">
                                <option value="unknown">Desconhecido</option>
                                <option value="male">Macho</option>
                                <option value="female">Fêmea</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Data de Nascimento</label>
                            <input type="date" class="form-control" name="birth_date">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Peso (kg)</label>
                            <input type="number" class="form-control" name="weight" step="0.01" min="0">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Cor</label>
                            <input type="text" class="form-control" name="color" maxlength="50">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Microchip</label>
                            <input type="text" class="form-control" name="microchip" maxlength="50">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Observações</label>
                        <textarea class="form-control" name="notes" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Criar Pet</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let pets = [];
let clients = [];

document.addEventListener('DOMContentLoaded', () => {
    setTimeout(() => {
        loadPets();
        loadClientsForSelect();
    }, 100);
    
    // Form criar pet
    document.getElementById('createPetForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        
        if (!e.target.checkValidity()) {
            e.target.classList.add('was-validated');
            return;
        }
        
        const formData = new FormData(e.target);
        const data = Object.fromEntries(formData);
        
        // Converte client_id para inteiro
        if (data.client_id) {
            data.client_id = parseInt(data.client_id);
        }
        
        // Converte weight para número (se fornecido)
        if (data.weight) {
            data.weight = parseFloat(data.weight);
        }
        
        // Remove campos vazios
        Object.keys(data).forEach(key => {
            if (data[key] === '' || data[key] === null) {
                delete data[key];
            }
        });
        
        try {
            const response = await apiRequest('/v1/pets', {
                method: 'POST',
                body: JSON.stringify(data)
            });
            
            cache.clear('/v1/pets');
            showAlert('Pet criado com sucesso!', 'success');
            bootstrap.Modal.getInstance(document.getElementById('createPetModal')).hide();
            e.target.reset();
            e.target.classList.remove('was-validated');
            
            setTimeout(async () => {
                await loadPets(true);
            }, 100);
        } catch (error) {
            showAlert(error.message, 'danger');
        }
    });
});

async function loadPets(skipCache = false) {
    try {
        document.getElementById('loadingPets').style.display = 'block';
        document.getElementById('petsList').style.display = 'none';
        
        if (skipCache) {
            cache.clear('/v1/pets');
        }
        
        const response = await apiRequest('/v1/pets', {
            skipCache: skipCache
        });
        
        // A API retorna { data: { pets: [...], pagination: {...} } }
        pets = Array.isArray(response.data?.pets) ? response.data.pets : 
               Array.isArray(response.data) ? response.data : [];
        
        // Aplicar filtros
        applyFilters();
        
        renderPets();
    } catch (error) {
        console.error('Erro ao carregar pets:', error);
        showAlert('Erro ao carregar pets: ' + error.message, 'danger');
    } finally {
        document.getElementById('loadingPets').style.display = 'none';
        document.getElementById('petsList').style.display = 'block';
    }
}

function applyFilters() {
    const search = document.getElementById('searchInput')?.value.toLowerCase() || '';
    const speciesFilter = document.getElementById('speciesFilter')?.value || '';
    const clientFilter = document.getElementById('clientFilter')?.value || '';
    
    pets = pets.filter(pet => {
        const matchSearch = !search || 
            (pet.name?.toLowerCase().includes(search)) ||
            (pet.species?.toLowerCase().includes(search)) ||
            (pet.breed?.toLowerCase().includes(search));
        
        const matchSpecies = !speciesFilter || pet.species === speciesFilter;
        const matchClient = !clientFilter || pet.client_id == clientFilter;
        
        return matchSearch && matchSpecies && matchClient;
    });
}

function renderPets() {
    const tbody = document.getElementById('petsTableBody');
    const emptyState = document.getElementById('emptyState');
    
    if (pets.length === 0) {
        tbody.innerHTML = '';
        emptyState.style.display = 'block';
        return;
    }
    
    emptyState.style.display = 'none';
    
    tbody.innerHTML = pets.map(pet => {
        const age = pet.birth_date ? calculateAge(pet.birth_date) : '-';
        return `
            <tr>
                <td>${pet.id}</td>
                <td>${pet.name || '-'}</td>
                <td>${pet.species || '-'}</td>
                <td>${pet.breed || '-'}</td>
                <td>${age}</td>
                <td>${pet.weight ? pet.weight + ' kg' : '-'}</td>
                <td>
                    <a href="/clinic-client-details?id=${pet.client_id}" class="btn btn-sm btn-outline-info">
                        Ver Cliente
                    </a>
                </td>
                <td>${formatDate(pet.created_at)}</td>
                <td>
                    <div class="btn-group" role="group">
                        <a href="/pet-details?id=${pet.id}" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-eye"></i> Ver
                        </a>
                        <button class="btn btn-sm btn-outline-danger" onclick="deletePet(${pet.id})" title="Excluir pet">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    }).join('');
}

function calculateAge(birthDate) {
    const birth = new Date(birthDate);
    const today = new Date();
    let age = today.getFullYear() - birth.getFullYear();
    const monthDiff = today.getMonth() - birth.getMonth();
    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birth.getDate())) {
        age--;
    }
    return age + ' anos';
}

async function loadClientsForSelect() {
    try {
        const response = await apiRequest('/v1/clients');
        clients = Array.isArray(response.data) ? response.data : [];
        
        const createSelect = document.getElementById('createPetClientId');
        const filterSelect = document.getElementById('clientFilter');
        
        [createSelect, filterSelect].forEach(select => {
            if (select) {
                clients.forEach(client => {
                    const option = document.createElement('option');
                    option.value = client.id;
                    option.textContent = `${client.name || 'Cliente #' + client.id} (${client.email || client.phone || ''})`;
                    select.appendChild(option);
                });
            }
        });
    } catch (error) {
        console.error('Erro ao carregar clientes:', error);
    }
}

async function deletePet(petId) {
    const confirmed = await showConfirmModal(
        'Tem certeza que deseja remover este pet? Esta ação não pode ser desfeita.',
        'Confirmar Exclusão',
        'Remover Pet'
    );
    if (!confirmed) return;
    
    try {
        await apiRequest(`/v1/pets/${petId}`, {
            method: 'DELETE'
        });
        
        cache.clear('/v1/pets');
        showAlert('Pet removido com sucesso!', 'success');
        
        setTimeout(async () => {
            await loadPets(true);
        }, 100);
    } catch (error) {
        showAlert(error.message, 'danger');
    }
}
</script>

