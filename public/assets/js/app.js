// Software de Gestión Active - JavaScript Principal

document.addEventListener('DOMContentLoaded', function() {
    // Manejo de navegación activa
    const currentPath = window.location.pathname;
    const navLinks = document.querySelectorAll('.sidebar-nav a');
    
    navLinks.forEach(link => {
        if (link.getAttribute('href') === currentPath) {
            link.classList.add('active');
        }
    });
    
    // Confirmación para acciones destructivas
    const deleteButtons = document.querySelectorAll('.btn-danger');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            if (!confirm('¿Está seguro de realizar esta acción?')) {
                e.preventDefault();
            }
        });
    });
    
    // AJAX Loading para formularios
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            if (form.dataset.ajax === 'true') {
                e.preventDefault();
                handleAjaxSubmit(form);
                return;
            }
            
            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.classList.add('btn-loading');
                form.classList.add('form-loading');
            }
        });
    });
    
    // AJAX Loading para botones de acción
    const actionButtons = document.querySelectorAll('.btn-primary, .btn-secondary');
    actionButtons.forEach(button => {
        button.addEventListener('click', function() {
            if (!this.classList.contains('btn-danger')) {
                this.classList.add('btn-loading');
                setTimeout(() => {
                    this.classList.remove('btn-loading');
                }, 2000);
            }
        });
    });
});

// Función para mostrar overlay de carga
function showLoadingOverlay() {
    const overlay = document.createElement('div');
    overlay.className = 'loading-overlay';
    overlay.innerHTML = '<div class="loading-spinner"></div>';
    document.body.appendChild(overlay);
    return overlay;
}

// Función para ocultar overlay de carga
function hideLoadingOverlay(overlay) {
    if (overlay) {
        overlay.remove();
    }
}

// Función para enviar formularios con AJAX
function submitFormWithAjax(form, callback) {
    const formData = new FormData(form);
    const overlay = showLoadingOverlay();
    
    fetch(form.action, {
        method: form.method || 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        hideLoadingOverlay(overlay);
        if (callback) callback(data);
    })
    .catch(error => {
        hideLoadingOverlay(overlay);
        console.error('Error:', error);
    });
}

// Maneja el envio de formularios marcados con data-ajax="true"
function handleAjaxSubmit(form) {
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn ? submitBtn.textContent : '';
    const overlay = showLoadingOverlay();
    
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.classList.add('btn-loading');
    }
    
    fetch(form.action, {
        method: form.method || 'POST',
        body: new FormData(form),
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Error en la respuesta del servidor');
        }
        return response.json();
    })
    .then(data => {
        hideLoadingOverlay(overlay);
        
        if (data.success) {
            showAlert(data.message, 'success');
            if (data.redirect) {
                setTimeout(() => window.location.href = data.redirect, 600);
            } else {
                setTimeout(() => window.location.reload(), 600);
            }
        } else {
            showAlert(data.message, 'error');
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.classList.remove('btn-loading');
            }
        }
    })
    .catch(error => {
        hideLoadingOverlay(overlay);
        console.error('Error:', error);
        showAlert('Error de conexion con el servidor', 'error');
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.classList.remove('btn-loading');
        }
    });
}

// ============================================
// Búsqueda global en vivo
// ============================================
(function() {
    const searchInput = document.getElementById('globalSearchInput');
    const searchResults = document.getElementById('searchResults');
    const searchClear = document.getElementById('searchClear');
    let debounceTimer;
    
    if (!searchInput) return;
    
    searchInput.addEventListener('input', function() {
        clearTimeout(debounceTimer);
        const query = this.value.trim();
        
        // Mostrar/ocultar botón clear
        if (searchClear) {
            searchClear.style.display = query ? 'flex' : 'none';
        }
        
        if (query.length < 2) {
            searchResults.style.display = 'none';
            searchResults.innerHTML = '';
            return;
        }
        
        debounceTimer = setTimeout(() => {
            fetchSearchResults(query);
        }, 300);
    });
    
    searchInput.addEventListener('focus', function() {
        if (searchResults.children.length > 0) {
            searchResults.style.display = 'block';
        }
    });
    
    // Cerrar resultados al hacer clic fuera
    document.addEventListener('click', function(e) {
        const searchContainer = document.getElementById('globalSearch');
        if (searchContainer && !searchContainer.contains(e.target)) {
            searchResults.style.display = 'none';
        }
    });
    
    // Navegar con teclado
    searchInput.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            searchResults.style.display = 'none';
            this.blur();
        }
    });
    
    function fetchSearchResults(query) {
        const url = searchInput.dataset.searchUrl + '?q=' + encodeURIComponent(query);
        
        fetch(url, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function(response) {
            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }
            return response.json();
        })
        .then(function(data) {
            if (data.error) {
                searchResults.innerHTML = '<div class="search-result-empty">Error: ' + escapeHtml(data.error) + '</div>';
                searchResults.style.display = 'block';
                return;
            }
            
            if (!data.results || data.results.length === 0) {
                searchResults.innerHTML = '<div class="search-result-empty">Sin resultados para "' + escapeHtml(query) + '"</div>';
                searchResults.style.display = 'block';
                return;
            }
            
            let html = '';
            data.results.forEach(function(item) {
                const typeIcon = item.type === 'tenant' ? '👤' : (item.type === 'ticket' ? '💬' : '🔒');
                html += '<a href="' + item.url + '" class="search-result-item">';
                html += '<span class="search-result-icon">' + typeIcon + '</span>';
                html += '<div class="search-result-info">';
                html += '<span class="search-result-title">' + escapeHtml(item.title) + '</span>';
                html += '<span class="search-result-subtitle">' + escapeHtml(item.subtitle || '') + '</span>';
                html += '</div>';
                html += '<span class="badge ' + (item.badgeClass || 'badge-info') + '">' + escapeHtml(item.badge || item.type) + '</span>';
                html += '</a>';
            });
            
            searchResults.innerHTML = html;
            searchResults.style.display = 'block';
        })
        .catch(function(err) {
            searchResults.innerHTML = '<div class="search-result-empty">Error de conexión. Verifique la consola.</div>';
            searchResults.style.display = 'block';
        });
    }
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
})();

function clearSearch() {
    const input = document.getElementById('globalSearchInput');
    const results = document.getElementById('searchResults');
    const clearBtn = document.getElementById('searchClear');
    if (input) input.value = '';
    if (results) { results.style.display = 'none'; results.innerHTML = ''; }
    if (clearBtn) clearBtn.style.display = 'none';
    if (input) input.focus();
}

// Muestra una alerta temporal en la parte superior del contenido
function showAlert(message, type) {
    const existingAlert = document.querySelector('.ajax-alert');
    if (existingAlert) {
        existingAlert.remove();
    }
    
    const alert = document.createElement('div');
    alert.className = 'alert alert-' + type + ' ajax-alert';
    alert.textContent = message;
    
    const content = document.querySelector('.content');
    if (content) {
        content.insertBefore(alert, content.firstChild);
    } else {
        document.body.insertBefore(alert, document.body.firstChild);
    }
    
    setTimeout(() => {
        alert.remove();
    }, 5000);
}
