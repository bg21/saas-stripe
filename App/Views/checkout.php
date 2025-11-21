<?php
/**
 * View de Checkout
 */
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - Sistema SaaS</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body text-center py-5">
                        <div class="spinner-border text-primary mb-3" role="status">
                            <span class="visually-hidden">Processando...</span>
                        </div>
                        <h4>Redirecionando para o checkout...</h4>
                        <p class="text-muted">Aguarde enquanto preparamos seu pagamento</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const API_URL = <?php echo json_encode($apiUrl ?? '', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        
        const urlParams = new URLSearchParams(window.location.search);
        const customerId = urlParams.get('customer_id');
        const priceId = urlParams.get('price_id');
        
        // ✅ Validação de parâmetros
        if (!customerId || !priceId) {
            alert('Parâmetros inválidos: customer_id e price_id são obrigatórios');
            window.location.href = '/';
            return;
        }
        
        // ✅ Valida formato de price_id (se validations.js estiver disponível)
        if (typeof validateStripeId === 'function') {
            const priceIdError = validateStripeId(priceId, 'price_id', true);
            if (priceIdError) {
                alert('Parâmetro price_id inválido: ' + priceIdError);
                window.location.href = '/';
                return;
            }
        } else {
            // Fallback: validação básica
            const priceIdPattern = /^price_[a-zA-Z0-9]+$/;
            if (!priceIdPattern.test(priceId)) {
                alert('Formato de Price ID inválido. Use: price_xxxxx');
                window.location.href = '/';
                return;
            }
        }
        
        // Cria sessão de checkout
        fetch(API_URL + '/v1/checkout', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                customer_id: parseInt(customerId),
                price_id: priceId,
                success_url: window.location.origin + '/success?session_id={CHECKOUT_SESSION_ID}',
                cancel_url: window.location.origin + '/cancel'
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success && data.data.url) {
                window.location.href = data.data.url;
            } else {
                alert('Erro ao criar checkout');
                window.location.href = '/';
            }
        })
        .catch(error => {
            console.error(error);
            alert('Erro ao processar checkout');
            window.location.href = '/';
        });
    </script>
</body>
</html>

