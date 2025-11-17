<?php

namespace App\Utils;

/**
 * Helper para renderizar views
 */
class View
{
    /**
     * Renderiza uma view
     * 
     * @param string $viewName Nome da view (sem extensão .php)
     * @param array $data Dados para passar para a view
     * @param bool $useLayout Se deve usar o layout base (padrão: false)
     * @return void
     */
    public static function render(string $viewName, array $data = [], bool $useLayout = false): void
    {
        // Extrai variáveis do array $data para o escopo da view
        extract($data);
        
        if ($useLayout) {
            // Renderiza com layout
            $viewPath = __DIR__ . '/../Views/' . $viewName . '.php';
            
            if (!file_exists($viewPath)) {
                throw new \RuntimeException("View não encontrada: {$viewName}");
            }
            
            // Captura o conteúdo da view
            ob_start();
            require $viewPath;
            $content = ob_get_clean();
            
            // Renderiza o layout com o conteúdo
            $layoutPath = __DIR__ . '/../Views/layouts/base.php';
            if (!file_exists($layoutPath)) {
                throw new \RuntimeException("Layout não encontrado: base");
            }
            
            require $layoutPath;
        } else {
            // Renderiza view simples
            $viewPath = __DIR__ . '/../Views/' . $viewName . '.php';
            
            if (!file_exists($viewPath)) {
                throw new \RuntimeException("View não encontrada: {$viewName}");
            }
            
            require $viewPath;
        }
    }
}

