<?php
/**
 * View de Configurações da Clínica
 */
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0"><i class="bi bi-gear"></i> Configurações da Clínica</h1>
    </div>

    <div id="alertContainer"></div>
    <div id="loadingConfig" class="text-center py-5">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Carregando...</span>
        </div>
    </div>

    <div id="configDetails" style="display: none;">
        <form id="configForm" enctype="multipart/form-data">
            <!-- Informações Básicas da Clínica -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-building"></i> Informações Básicas da Clínica</h5>
                </div>
                <div class="card-body">
                    <!-- Logo da Clínica -->
                    <div class="mb-4">
                        <label class="form-label">Logo da Clínica</label>
                        <div class="d-flex align-items-center gap-3">
                            <div id="logoPreview" style="display: none;">
                                <img id="logoPreviewImg" src="" alt="Logo da Clínica" style="max-width: 200px; max-height: 200px; border: 1px solid #ddd; border-radius: 8px; padding: 10px;">
                            </div>
                            <div class="flex-grow-1">
                                <input type="file" class="form-control" id="clinicLogo" name="logo" accept="image/jpeg,image/png,image/gif,image/webp">
                                <small class="form-text text-muted">Formatos aceitos: JPG, PNG, GIF, WEBP (máx. 5MB)</small>
                                <button type="button" class="btn btn-sm btn-primary mt-2" onclick="uploadLogo()">
                                    <i class="bi bi-upload"></i> Enviar Logo
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nome da Clínica</label>
                            <input type="text" class="form-control" name="clinic_name" id="clinicName" maxlength="255">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Telefone</label>
                            <input type="text" class="form-control" name="clinic_phone" id="clinicPhone" placeholder="(00) 00000-0000" maxlength="15">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="clinic_email" id="clinicEmail" maxlength="255">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Website</label>
                            <input type="url" class="form-control" name="clinic_website" id="clinicWebsite" placeholder="https://exemplo.com.br" maxlength="255">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Endereço</label>
                        <textarea class="form-control" name="clinic_address" id="clinicAddress" rows="2"></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Cidade</label>
                            <input type="text" class="form-control" name="clinic_city" id="clinicCity" maxlength="100">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Estado</label>
                            <input type="text" class="form-control" name="clinic_state" id="clinicState" maxlength="50">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">CEP</label>
                            <input type="text" class="form-control" name="clinic_zip_code" id="clinicZipCode" placeholder="00000-000" maxlength="9">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Descrição da Clínica</label>
                        <textarea class="form-control" name="clinic_description" id="clinicDescription" rows="4" placeholder="Descreva sua clínica..."></textarea>
                    </div>
                </div>
            </div>

            <!-- Horários de Funcionamento -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Horários de Funcionamento</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php
                        $days = [
                            'monday' => 'Segunda-feira',
                            'tuesday' => 'Terça-feira',
                            'wednesday' => 'Quarta-feira',
                            'thursday' => 'Quinta-feira',
                            'friday' => 'Sexta-feira',
                            'saturday' => 'Sábado',
                            'sunday' => 'Domingo'
                        ];
                        foreach ($days as $dayKey => $dayName):
                        ?>
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><?php echo $dayName; ?></label>
                            <div class="row">
                                <div class="col-6">
                                    <input type="time" class="form-control" name="opening_time_<?php echo $dayKey; ?>" id="opening_<?php echo $dayKey; ?>">
                                </div>
                                <div class="col-6">
                                    <input type="time" class="form-control" name="closing_time_<?php echo $dayKey; ?>" id="closing_<?php echo $dayKey; ?>">
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Configurações de Agendamento -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Configurações de Agendamento</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Duração Padrão de Consulta (minutos) *</label>
                            <input type="number" class="form-control" name="default_appointment_duration" id="defaultAppointmentDuration" required min="15" max="240" step="15" value="30">
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Intervalo entre Horários (minutos) *</label>
                            <input type="number" class="form-control" name="time_slot_interval" id="timeSlotInterval" required min="5" max="60" step="5" value="15">
                            <div class="invalid-feedback"></div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="allow_online_booking" id="allowOnlineBooking" value="1">
                                <label class="form-check-label" for="allowOnlineBooking">
                                    Permitir agendamento online
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="require_confirmation" id="requireConfirmation" value="1">
                                <label class="form-check-label" for="requireConfirmation">
                                    Exigir confirmação de agendamento
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Horas Mínimas para Cancelamento</label>
                        <input type="number" class="form-control" name="cancellation_hours" id="cancellationHours" min="0" max="168" value="24">
                        <small class="form-text text-muted">Número de horas antes do agendamento que permite cancelamento</small>
                    </div>
                </div>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <span class="spinner-border spinner-border-sm d-none me-2" role="status"></span>
                    Salvar Configurações
                </button>
            </div>
        </form>
    </div>
</div>

<script>
let configData = null;

document.addEventListener('DOMContentLoaded', () => {
    setTimeout(() => {
        loadConfig();
    }, 100);
    
    // Máscaras para telefone e CEP
    const phoneInput = document.getElementById('clinicPhone');
    if (phoneInput) {
        phoneInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length <= 10) {
                value = value.replace(/(\d{2})(\d{4})(\d{4})/, '($1) $2-$3');
            } else {
                value = value.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3');
            }
            e.target.value = value;
        });
    }
    
    const zipCodeInput = document.getElementById('clinicZipCode');
    if (zipCodeInput) {
        zipCodeInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            value = value.replace(/(\d{5})(\d{3})/, '$1-$2');
            e.target.value = value;
        });
    }
    
    document.getElementById('configForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        
        if (!e.target.checkValidity()) {
            e.target.classList.add('was-validated');
            return;
        }
        
        const formData = new FormData(e.target);
        const data = {};
        
        // Processa todos os campos (exceto logo que é enviado separadamente)
        for (let [key, value] of formData.entries()) {
            if (key === 'logo') {
                continue; // Logo é enviado separadamente
            }
            
            // Campos de horário
            if (key.startsWith('opening_time_') || key.startsWith('closing_time_')) {
                data[key] = value || null;
            }
            // Campos booleanos
            else if (key === 'allow_online_booking' || key === 'require_confirmation') {
                data[key] = value === '1' ? 1 : 0;
            }
            // Campos numéricos
            else if (key === 'default_appointment_duration' || key === 'time_slot_interval' || key === 'cancellation_hours') {
                data[key] = value ? parseInt(value) : null;
            }
            // Campos de texto (inclui informações básicas da clínica)
            else {
                // Inclui todos os campos, mesmo vazios (será convertido para null no backend se necessário)
                data[key] = value || null;
            }
        }
        
        // Debug: mostra dados que serão enviados (apenas em desenvolvimento)
        if (typeof console !== 'undefined' && console.log) {
            console.log('📤 Dados a serem enviados:', data);
            console.log('📤 Total de campos:', Object.keys(data).length);
        }
        
        const submitBtn = e.target.querySelector('button[type="submit"]');
        const spinner = submitBtn.querySelector('.spinner-border');
        
        submitBtn.disabled = true;
        spinner.classList.remove('d-none');
        
        try {
            const response = await apiRequest('/v1/clinic/configuration', {
                method: 'PUT',
                body: JSON.stringify(data)
            });
            
            if (response.success) {
                showAlert('Configurações salvas com sucesso!', 'success');
                configData = response.data;
                
                // Debug: mostra resposta
                if (typeof console !== 'undefined' && console.log) {
                    console.log('✅ Resposta recebida:', response.data);
                }
                
                // Recarrega para mostrar dados atualizados
                loadConfig();
            } else {
                showAlert('Erro ao salvar configurações', 'danger');
            }
        } catch (error) {
            console.error('❌ Erro ao salvar:', error);
            showAlert('Erro ao salvar: ' + error.message, 'danger');
        } finally {
            submitBtn.disabled = false;
            spinner.classList.add('d-none');
        }
    });
});

async function loadConfig() {
    try {
        document.getElementById('loadingConfig').style.display = 'block';
        document.getElementById('configDetails').style.display = 'none';
        
        const response = await apiRequest('/v1/clinic/configuration');
        configData = response.data;
        
        if (configData) {
            // Preenche informações básicas
            document.getElementById('clinicName').value = configData.clinic_name || '';
            document.getElementById('clinicPhone').value = configData.clinic_phone || '';
            document.getElementById('clinicEmail').value = configData.clinic_email || '';
            document.getElementById('clinicWebsite').value = configData.clinic_website || '';
            document.getElementById('clinicAddress').value = configData.clinic_address || '';
            document.getElementById('clinicCity').value = configData.clinic_city || '';
            document.getElementById('clinicState').value = configData.clinic_state || '';
            document.getElementById('clinicZipCode').value = configData.clinic_zip_code || '';
            document.getElementById('clinicDescription').value = configData.clinic_description || '';
            
            // Preenche logo se existir
            if (configData.clinic_logo) {
                const logoPreview = document.getElementById('logoPreview');
                const logoPreviewImg = document.getElementById('logoPreviewImg');
                logoPreviewImg.src = '/' + configData.clinic_logo;
                logoPreview.style.display = 'block';
            }
            
            // Preenche configurações de agendamento
            document.getElementById('defaultAppointmentDuration').value = configData.default_appointment_duration || 30;
            document.getElementById('timeSlotInterval').value = configData.time_slot_interval || 15;
            document.getElementById('cancellationHours').value = configData.cancellation_hours || 24;
            document.getElementById('allowOnlineBooking').checked = configData.allow_online_booking == 1;
            document.getElementById('requireConfirmation').checked = configData.require_confirmation == 1;
            
            // Preenche horários
            const days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
            days.forEach(day => {
                const openingField = document.getElementById(`opening_${day}`);
                const closingField = document.getElementById(`closing_${day}`);
                if (openingField && configData[`opening_time_${day}`]) {
                    openingField.value = configData[`opening_time_${day}`].substring(0, 5);
                }
                if (closingField && configData[`closing_time_${day}`]) {
                    closingField.value = configData[`closing_time_${day}`].substring(0, 5);
                }
            });
        }
        
        document.getElementById('loadingConfig').style.display = 'none';
        document.getElementById('configDetails').style.display = 'block';
    } catch (error) {
        console.error('Erro ao carregar configurações:', error);
        showAlert('Erro ao carregar configurações: ' + error.message, 'danger');
    }
}

async function uploadLogo() {
    const fileInput = document.getElementById('clinicLogo');
    const file = fileInput.files[0];
    
    if (!file) {
        showAlert('Por favor, selecione um arquivo de imagem', 'warning');
        return;
    }
    
    // Valida tamanho (5MB)
    if (file.size > 5 * 1024 * 1024) {
        showAlert('Arquivo muito grande. Máximo permitido: 5MB', 'danger');
        return;
    }
    
    // Valida tipo
    const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!allowedTypes.includes(file.type)) {
        showAlert('Tipo de arquivo inválido. Use JPG, PNG, GIF ou WEBP', 'danger');
        return;
    }
    
    const formData = new FormData();
    formData.append('logo', file);
    
    try {
        const response = await fetch(`${API_URL}/v1/clinic/logo`, {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${SESSION_ID}`
            },
            body: formData
        });
        
        const data = await response.json();
        
        if (response.ok && data.success) {
            showAlert('Logo enviado com sucesso!', 'success');
            
            // Atualiza preview
            const logoPreview = document.getElementById('logoPreview');
            const logoPreviewImg = document.getElementById('logoPreviewImg');
            logoPreviewImg.src = data.data.logo_url;
            logoPreview.style.display = 'block';
            
            // Limpa input
            fileInput.value = '';
            
            // Recarrega configurações
            loadConfig();
        } else {
            showAlert(data.message || 'Erro ao enviar logo', 'danger');
        }
    } catch (error) {
        console.error('Erro ao enviar logo:', error);
        showAlert('Erro ao enviar logo: ' + error.message, 'danger');
    }
}
</script>

