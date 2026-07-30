<?php

namespace SoftNova\Core;

/**
 * Clase para manejar las vistas
 */

class View
{
    private string $viewPath;
    
    public function __construct()
    {
        $this->viewPath = APP_PATH . '/Views/';
    }
    
    /**
     * Renderizar una vista
     */
    public function render(string $view, array $data = []): void
    {
        $viewFile = $this->viewPath . str_replace('.', '/', $view) . '.php';
        
        if (!file_exists($viewFile)) {
            throw new \Exception("Vista no encontrada: {$view}");
        }
        
        // Cargar helpers de vistas (funciones compartidas)
        $viewHelpersFile = $this->viewPath . 'helpers.php';
        if (file_exists($viewHelpersFile)) {
            require_once $viewHelpersFile;
        }
        
        // Extraer datos a variables
        extract($data);
        
        // Hacer $viewInstance disponible en la vista
        $viewInstance = $this;
        
        // Iniciar buffer de salida
        ob_start();
        
        try {
            require $viewFile;
            $content = ob_get_clean();
            
            // Si la vista define un layout, renderizar el layout
            if (isset($layout)) {
                $layoutFile = $this->viewPath . 'layouts/' . $layout . '.php';
                if (file_exists($layoutFile)) {
                    // Hacer $content disponible para el layout (+ scripts de módulo definidos en la vista)
                    $layoutData = array_merge($data, [
                        'content' => $content,
                        'pageScripts' => $pageScripts ?? ($data['pageScripts'] ?? []),
                        'loadBarcode' => $loadBarcode ?? ($data['loadBarcode'] ?? null),
                    ]);
                    extract($layoutData);
                    ob_start();
                    require $layoutFile;
                    echo ob_get_clean();
                } else {
                    echo $content;
                }
            } else {
                echo $content;
            }
        } catch (\Exception $e) {
            ob_end_clean();
            throw $e;
        }
    }
    
    /**
     * Incluir un partial
     */
    public function partial(string $partial, array $data = []): void
    {
        // Cargar helpers si no están cargados
        if (!function_exists('route')) {
            require_once CORE_PATH . '/helpers.php';
        }
        
        $partialFile = $this->viewPath . 'partials/' . $partial . '.php';
        
        if (file_exists($partialFile)) {
            extract($data);
            // Hacer $viewInstance disponible en el partial (después de extract para evitar sobrescritura)
            $viewInstance = $this;
            require $partialFile;
        }
    }
    
    /**
     * Escapar salida HTML
     */
    public function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Generar URL de ruta
     */
    public function route(string $path): string
    {
        $baseUrl = config('app.url', 'http://localhost/SoftNova/public');
        return rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
    }
    
    /**
     * Generar URL de asset
     */
    public function asset(string $path): string
    {
        $baseUrl = config('app.url', 'http://localhost/SoftNova/public');
        return rtrim($baseUrl, '/') . '/assets/' . ltrim($path, '/');
    }
}
