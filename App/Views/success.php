<?php
/**
 * View de Sucesso do Checkout
 */
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagamento Realizado - Sistema SaaS</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card text-center">
                    <div class="card-body py-5">
                        <div class="mb-4">
                            <i class="bi bi-check-circle-fill text-success" style="font-size: 4rem;"></i>
                        </div>
                        <h2 class="mb-3">Pagamento Realizado com Sucesso!</h2>
                        <p class="text-muted mb-4">Sua assinatura foi ativada com sucesso.</p>
                        
                        <div id="subscriptionDetails" class="mb-4">
                            <div class="spinner-border spinner-border-sm" role="status">
                                <span class="visually-hidden">Carregando...</span>
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <a href="/dashboard" class="btn btn-primary">
                                <i class="bi bi-speedometer2"></i> Ir para Dashboard
                            </a>
                            <a href="/" class="btn btn-outline-secondary">
                                Voltar ao Início
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const API_URL = <?php echo json_encode($apiUrl ?? '', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        const urlParams = new URLSearchParams(window.location.search);
        const sessionId = urlParams.get('session_id');
        
        if (sessionId) {
            fetch(API_URL + '/v1/checkout/' + sessionId)
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('subscriptionDetails').innerHTML = `
                            <div class="alert alert-info">
                                <strong>Sessão:</strong> ${data.data.id}<br>
                                <strong>Status:</strong> ${data.data.payment_status || 'paid'}
                            </div>
                        `;
                    }
                })
                .catch(() => {
                    document.getElementById('subscriptionDetails').innerHTML = '';
                });
        }
    </script>
</body>
</html>

