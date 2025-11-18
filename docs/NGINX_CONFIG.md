# ⚙️ Configuração Nginx

**Objetivo:** Ocultar versão/stack e adicionar headers de segurança

---

## 📋 Configuração Completa

Se você estiver usando Nginx, adicione as seguintes configurações no seu arquivo de configuração do site:

```nginx
server {
    listen 80;
    server_name seu-dominio.com;
    root /caminho/para/saas-stripe/public;
    index index.php;

    # Ocultar versão do Nginx
    server_tokens off;

    # Headers de segurança
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-Frame-Options "DENY" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    # Remove headers que expõem informações
    more_clear_headers "X-Powered-By";
    more_clear_headers "Server";

    # PHP-FPM
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
        
        # Remove X-Powered-By do PHP
        fastcgi_hide_header X-Powered-By;
    }

    # Arquivos estáticos
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|svg|woff|woff2|ttf)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }

    # Rewrite para FlightPHP
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
}
```

---

## 📦 Instalação do Módulo

**Nota:** Para remover completamente o header "Server", você pode precisar do módulo `headers-more-nginx-module`:

### Ubuntu/Debian

```bash
sudo apt-get install nginx-extras
```

### Compilar com o Módulo

Siga as instruções em: https://github.com/openresty/headers-more-nginx-module

---

## 🔒 Headers de Segurança Explicados

| Header | Descrição |
|--------|-----------|
| `X-Content-Type-Options: nosniff` | Previne MIME type sniffing |
| `X-Frame-Options: DENY` | Previne clickjacking |
| `X-XSS-Protection: 1; mode=block` | Ativa proteção XSS do navegador |
| `Referrer-Policy: strict-origin-when-cross-origin` | Controla informações de referrer |

---

## ✅ Verificação

Após aplicar a configuração, verifique os headers:

```bash
curl -I http://seu-dominio.com
```

Você não deve ver:
- `Server: nginx/1.x.x`
- `X-Powered-By: PHP/x.x.x`

---

**Última Atualização:** 2025-01-XX
