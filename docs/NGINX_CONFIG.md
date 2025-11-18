# Configuração Nginx para Ocultar Versão/Stack

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

**Nota:** Para remover completamente o header "Server", você pode precisar do módulo `headers-more-nginx-module`:

```bash
# Ubuntu/Debian
sudo apt-get install nginx-extras

# Ou compilar com o módulo
# https://github.com/openresty/headers-more-nginx-module
```

