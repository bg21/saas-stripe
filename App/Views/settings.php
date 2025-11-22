<?php
/**
 * View de Configurações
 */
?>
<div class="container-fluid py-4">
    <h1 class="h3 mb-4"><i class="bi bi-gear"></i> Configurações</h1>

    <div id="alertContainer"></div>

    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Configurações Gerais</h5>
                </div>
                <div class="card-body">
                    <form id="settingsForm">
                        <div class="mb-3">
                            <label class="form-label">URL Base da API</label>
                            <input type="text" class="form-control" id="apiUrl" value="<?php echo htmlspecialchars($apiUrl ?? '', ENT_QUOTES); ?>">
                            <small class="text-muted">URL base para requisições à API</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Tema</label>
                            <select class="form-select" id="theme">
                                <option value="light">Claro</option>
                                <option value="dark">Escuro</option>
                                <option value="auto">Automático</option>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Salvar Configurações
                        </button>
                    </form>
                </div>
            </div>

            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="mb-0">Estatísticas do Sistema</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Versão da API:</strong> <span id="apiVersion">-</span></p>
                            <p><strong>Status:</strong> <span id="apiStatus" class="badge bg-success">Online</span></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Ambiente:</strong> <span id="apiEnvironment">-</span></p>
                            <p><strong>Última verificação:</strong> <span id="lastCheck">-</span></p>
                        </div>
                    </div>
                    <button class="btn btn-sm btn-outline-primary" onclick="checkApiStatus()">
                        <i class="bi bi-arrow-clockwise"></i> Verificar Status
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Carrega configurações salvas
    const savedApiUrl = localStorage.getItem('apiUrl');
    if (savedApiUrl) {
        document.getElementById('apiUrl').value = savedApiUrl;
    }
    
    const savedTheme = localStorage.getItem('theme') || 'light';
    document.getElementById('theme').value = savedTheme;
    
    checkApiStatus();
    
    document.getElementById('settingsForm').addEventListener('submit', (e) => {
        e.preventDefault();
        const apiUrl = document.getElementById('apiUrl').value;
        const theme = document.getElementById('theme').value;
        
        localStorage.setItem('apiUrl', apiUrl);
        localStorage.setItem('theme', theme);
        
        showAlert('Configurações salvas com sucesso!', 'success');
    });
});

async function checkApiStatus() {
    try {
        const response = await fetch(API_URL + '/');
        const data = await response.json();
        
        document.getElementById('apiVersion').textContent = data.version || '-';
        document.getElementById('apiStatus').textContent = data.status === 'ok' ? 'Online' : 'Offline';
        document.getElementById('apiStatus').className = data.status === 'ok' ? 'badge bg-success' : 'badge bg-danger';
        document.getElementById('apiEnvironment').textContent = data.environment || '-';
        document.getElementById('lastCheck').textContent = new Date().toLocaleString('pt-BR');
    } catch (error) {
        document.getElementById('apiStatus').textContent = 'Erro';
        document.getElementById('apiStatus').className = 'badge bg-danger';
    }
}
</script>

