<?php
/**
 * View de Detalhes do Cliente da Clínica
 */
?>
<div class="container-fluid py-4">
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/clinic-clients">Clientes</a></li>
            <li class="breadcrumb-item active">Detalhes</li>
        </ol>
    </nav>

    <div id="alertContainer"></div>
    <div id="loadingClient" class="text-center py-5">
        <div class="spinner-border text-primary" role="status"></div>
    </div>

    <div id="clientDetails" style="display: none;">
        <!-- Modo Visualização -->
        <div id="viewMode">
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Informações do Cliente</h5>
                    <button class="btn btn-sm btn-outline-primary" onclick="toggleEditMode()">
                        <i class="bi bi-pencil"></i> Editar
                    </button>
                </div>
                <div class="card-body" id="clientInfo">
                </div>
            </div>
        </div>

        <!-- Modo Edição -->
        <div id="editMode" style="display: none;">
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Editar Cliente</h5>
                    <button class="btn btn-sm btn-outline-secondary" onclick="toggleEditMode()">
                        <i class="bi bi-x"></i> Cancelar
                    </button>
                </div>
                <div class="card-body">
                    <form id="editClientForm" class="needs-validation" novalidate>
                        <input type="hidden" id="editClientId">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="editClientName" class="form-label">Nome *</label>
                                <input type="text" class="form-control" id="editClientName" name="name" required minlength="2" maxlength="255">
                                <div class="invalid-feedback"></div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="editClientEmail" class="form-label">Email</label>
                                <input type="email" class="form-control" id="editClientEmail" name="email" maxlength="255">
                                <div class="invalid-feedback"></div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="editClientPhone" class="form-label">Telefone *</label>
                                <input type="text" class="form-control" id="editClientPhone" name="phone" required>
                                <div class="invalid-feedback"></div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="editClientPhoneAlt" class="form-label">Telefone Alternativo</label>
                                <input type="text" class="form-control" id="editClientPhoneAlt" name="phone_alt">
                                <div class="invalid-feedback"></div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="editClientAddress" class="form-label">Endereço</label>
                            <textarea class="form-control" id="editClientAddress" name="address" rows="2"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="editClientCity" class="form-label">Cidade</label>
                                <input type="text" class="form-control" id="editClientCity" name="city" maxlength="100">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="editClientState" class="form-label">Estado</label>
                                <input type="text" class="form-control" id="editClientState" name="state" maxlength="2">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="editClientPostalCode" class="form-label">CEP</label>
                                <input type="text" class="form-control" id="editClientPostalCode" name="postal_code" maxlength="10">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="editClientNotes" class="form-label">Observações</label>
                            <textarea class="form-control" id="editClientNotes" name="notes" rows="3"></textarea>
                        </div>

                        <div id="editClientError" class="alert alert-danger d-none mb-3" role="alert"></div>

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

        <!-- Pets do Cliente -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Pets</h5>
                <button class="btn btn-sm btn-primary" onclick="showCreatePetModal()">
                    <i class="bi bi-plus-circle"></i> Novo Pet
                </button>
            </div>
            <div class="card-body">
                <div id="petsList">
                    <p class="text-muted">Carregando pets...</p>
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
                <input type="hidden" name="client_id" id="createPetClientId">
                <div class="modal-body">
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
const urlParams = new URLSearchParams(window.location.search);
const clientId = urlParams.get('id');

if (!clientId) {
    window.location.href = '/clinic-clients';
}

let clientData = null;
let pets = [];

document.addEventListener('DOMContentLoaded', () => {
    setTimeout(() => {
        loadClientDetails();
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
            cache.clear(`/v1/clients/${clientId}/pets`);
            showAlert('Pet criado com sucesso!', 'success');
            bootstrap.Modal.getInstance(document.getElementById('createPetModal')).hide();
            e.target.reset();
            e.target.classList.remove('was-validated');
            
            setTimeout(async () => {
                await loadClientDetails();
            }, 100);
        } catch (error) {
            showAlert(error.message, 'danger');
        }
    });
});

async function loadClientDetails() {
    try {
        const [client, clientPets] = await Promise.all([
            apiRequest(`/v1/clients/${clientId}`),
            apiRequest(`/v1/clients/${clientId}/pets`).catch(() => ({ data: [] }))
        ]);

        clientData = client.data;
        pets = Array.isArray(clientPets.data) ? clientPets.data : [];
        
        renderClientInfo(clientData);
        renderPets();

        document.getElementById('loadingClient').style.display = 'none';
        document.getElementById('clientDetails').style.display = 'block';
    } catch (error) {
        showAlert('Erro ao carregar detalhes: ' + error.message, 'danger');
    }
}

function renderClientInfo(client) {
    document.getElementById('clientInfo').innerHTML = `
        <div class="row">
            <div class="col-md-6">
                <p><strong>ID:</strong> ${client.id}</p>
                <p><strong>Nome:</strong> ${client.name || '-'}</p>
                <p><strong>Email:</strong> ${client.email || '-'}</p>
                <p><strong>Telefone:</strong> ${client.phone || '-'}</p>
                <p><strong>Telefone Alternativo:</strong> ${client.phone_alt || '-'}</p>
            </div>
            <div class="col-md-6">
                <p><strong>Endereço:</strong> ${client.address || '-'}</p>
                <p><strong>Cidade:</strong> ${client.city || '-'}</p>
                <p><strong>Estado:</strong> ${client.state || '-'}</p>
                <p><strong>CEP:</strong> ${client.postal_code || '-'}</p>
                <p><strong>Criado em:</strong> ${formatDate(client.created_at)}</p>
            </div>
        </div>
        ${client.notes ? `<div class="mt-3"><strong>Observações:</strong><p class="mt-2">${client.notes}</p></div>` : ''}
    `;
}

function renderPets() {
    const container = document.getElementById('petsList');
    
    if (pets.length === 0) {
        container.innerHTML = '<p class="text-muted">Nenhum pet cadastrado</p>';
        return;
    }
    
    container.innerHTML = `
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Espécie</th>
                        <th>Raça</th>
                        <th>Idade</th>
                        <th>Peso</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    ${pets.map(pet => {
                        const age = pet.birth_date ? calculateAge(pet.birth_date) : '-';
                        return `
                            <tr>
                                <td>${pet.name}</td>
                                <td>${pet.species || '-'}</td>
                                <td>${pet.breed || '-'}</td>
                                <td>${age}</td>
                                <td>${pet.weight ? pet.weight + ' kg' : '-'}</td>
                                <td>
                                    <a href="/pet-details?id=${pet.id}" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-eye"></i> Ver
                                    </a>
                                </td>
                            </tr>
                        `;
                    }).join('')}
                </tbody>
            </table>
        </div>
    `;
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

function showCreatePetModal() {
    document.getElementById('createPetClientId').value = clientId;
    const modal = new bootstrap.Modal(document.getElementById('createPetModal'));
    modal.show();
}

function toggleEditMode() {
    const viewMode = document.getElementById('viewMode');
    const editMode = document.getElementById('editMode');
    
    if (viewMode.style.display === 'none') {
        viewMode.style.display = 'block';
        editMode.style.display = 'none';
    } else {
        if (clientData) {
            loadClientForEdit();
        }
        viewMode.style.display = 'none';
        editMode.style.display = 'block';
    }
}

function loadClientForEdit() {
    if (!clientData) return;
    
    document.getElementById('editClientId').value = clientData.id;
    document.getElementById('editClientName').value = clientData.name || '';
    document.getElementById('editClientEmail').value = clientData.email || '';
    document.getElementById('editClientPhone').value = clientData.phone || '';
    document.getElementById('editClientPhoneAlt').value = clientData.phone_alt || '';
    document.getElementById('editClientAddress').value = clientData.address || '';
    document.getElementById('editClientCity').value = clientData.city || '';
    document.getElementById('editClientState').value = clientData.state || '';
    document.getElementById('editClientPostalCode').value = clientData.postal_code || '';
    document.getElementById('editClientNotes').value = clientData.notes || '';
}

document.getElementById('editClientForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    if (!this.checkValidity()) {
        this.classList.add('was-validated');
        return;
    }

    const formData = {
        name: document.getElementById('editClientName').value.trim(),
        email: document.getElementById('editClientEmail').value.trim() || null,
        phone: document.getElementById('editClientPhone').value.trim(),
        phone_alt: document.getElementById('editClientPhoneAlt').value.trim() || null,
        address: document.getElementById('editClientAddress').value.trim() || null,
        city: document.getElementById('editClientCity').value.trim() || null,
        state: document.getElementById('editClientState').value.trim() || null,
        postal_code: document.getElementById('editClientPostalCode').value.trim() || null,
        notes: document.getElementById('editClientNotes').value.trim() || null
    };

    const submitBtn = this.querySelector('button[type="submit"]');
    const spinner = submitBtn.querySelector('.spinner-border');
    const errorDiv = document.getElementById('editClientError');
    
    submitBtn.disabled = true;
    spinner.classList.remove('d-none');
    errorDiv.classList.add('d-none');

    apiRequest(`/v1/clients/${clientId}`, {
        method: 'PUT',
        body: JSON.stringify(formData)
    })
    .then(data => {
        if (data.success) {
            showAlert('Cliente atualizado com sucesso!', 'success');
            clientData = data.data;
            renderClientInfo(clientData);
            toggleEditMode();
            cache.clear(`/v1/clients/${clientId}`);
        } else {
            throw new Error(data.error || 'Erro ao atualizar cliente');
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

