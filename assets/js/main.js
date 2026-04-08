/**
 * Script principal para funcionalidades gerais do sistema
 * Responsável por gerenciar o menu lateral, modais e outras interações comuns
 */

document.addEventListener('DOMContentLoaded', function() {
    // Inicializar tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Elementos do menu lateral
    const sidebarWrapper = document.getElementById('sidebar-wrapper');
    const menuToggle = document.getElementById('menu-toggle');
    const closeSidebar = document.getElementById('close-sidebar');
    const overlay = document.getElementById('sidebar-overlay');
    const dropdownToggles = document.querySelectorAll('.dropdown-toggle');
    const windowMatch = window.matchMedia('(min-width: 992px)');
    const body = document.body;

    // Função para alternar o menu lateral
    function toggleSidebar() {
        if (window.innerWidth < 992) {
            sidebarWrapper.classList.toggle('active');
            overlay.classList.toggle('active');
            body.style.overflow = sidebarWrapper.classList.contains('active') ? 'hidden' : '';
        }
    }

    // Fechar menu ao clicar no overlay
    function closeSidebarHandler() {
        if (window.innerWidth < 992) {
            sidebarWrapper.classList.remove('active');
            overlay.classList.remove('active');
            body.style.overflow = '';
        }
    }

    // Gerenciar o clique no botão de menu
    if (menuToggle) {
        menuToggle.addEventListener('click', function(e) {
            e.preventDefault();
            toggleSidebar();
        });
    }

    // Fechar menu ao clicar no botão de fechar
    if (closeSidebar) {
        closeSidebar.addEventListener('click', function(e) {
            e.preventDefault();
            closeSidebarHandler();
        });
    }

    // Fechar menu ao clicar no overlay
    if (overlay) {
        overlay.addEventListener('click', function() {
            closeSidebarHandler();
        });
    }

    // Fechar menu ao redimensionar para desktop
    function handleResize() {
        if (window.innerWidth >= 992) {
            sidebarWrapper.classList.remove('active');
            if (overlay) overlay.classList.remove('active');
            body.style.overflow = '';
        }
    }

    // Adicionar evento de redimensionamento
    window.addEventListener('resize', handleResize);
    
    // Inicializar o menu no carregamento
    handleResize();

    // Inicializar dropdowns
    dropdownToggles.forEach(toggle => {
        toggle.addEventListener('click', function(e) {
            const parent = this.closest('.dropdown');
            const menu = parent.querySelector('.dropdown-menu');
            
            // Fechar outros dropdowns abertos
            document.querySelectorAll('.dropdown-menu.show').forEach(openMenu => {
                if (openMenu !== menu) {
                    openMenu.classList.remove('show');
                    openMenu.previousElementSibling.setAttribute('aria-expanded', 'false');
                }
            });
            
            // Alternar o dropdown atual
            menu.classList.toggle('show');
            this.setAttribute('aria-expanded', 
                this.getAttribute('aria-expanded') === 'true' ? 'false' : 'true'
            );
            
            // Se for em dispositivo móvel, manter o menu aberto
            if (window.innerWidth < 992) {
                e.stopPropagation();
            }
        });
    });

    // Fechar dropdowns ao clicar fora
    document.addEventListener('click', function(e) {
        if (!e.target.matches('.dropdown-toggle')) {
            document.querySelectorAll('.dropdown-menu.show').forEach(menu => {
                menu.classList.remove('show');
                menu.previousElementSibling.setAttribute('aria-expanded', 'false');
            });
        }
    });

    // Adicionar classe de carregamento global
    function setLoading(show = true) {
        if (show) {
            document.body.classList.add('loading');
        } else {
            document.body.classList.remove('loading');
        }
    }

    // Inicializar tooltips dinâmicos
    function initTooltips() {
        const tooltips = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltips.map(function (tooltip) {
            return new bootstrap.Tooltip(tooltip);
        });
    }

    // Inicializar popovers
    function initPopovers() {
        const popovers = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
        popovers.map(function (popover) {
            return new bootstrap.Popover(popover);
        });
    }

    // Função para rolar suavemente para um elemento
    function scrollToElement(element, offset = 20) {
        if (element) {
            const elementPosition = element.getBoundingClientRect().top;
            const offsetPosition = elementPosition + window.pageYOffset - offset;
            
            window.scrollTo({
                top: offsetPosition,
                behavior: 'smooth'
            });
        }
    }

    // Função para copiar texto para a área de transferência
    function copyToClipboard(text, element = null) {
        navigator.clipboard.writeText(text).then(function() {
            if (element) {
                const originalText = element.innerHTML;
                element.innerHTML = '<i class="bi bi-check"></i> Copiado!';
                setTimeout(() => {
                    element.innerHTML = originalText;
                }, 2000);
            }
        }).catch(function(err) {
            console.error('Erro ao copiar texto: ', err);
        });
    }

    // Exportar funções para uso global
    window.App = {
        showNotification: function(message, type = 'success') {
            // Implementar lógica de notificação aqui
            console.log(`${type}: ${message}`);
        },
        loadContent: function(url, targetElement, callback) {
            // Implementar carregamento de conteúdo via AJAX aqui
            console.log(`Carregando conteúdo de ${url} para ${targetElement}`);
            if (callback && typeof callback === 'function') {
                callback();
            }
        },
        setLoading: setLoading,
        initTooltips: initTooltips,
        initPopovers: initPopovers,
        scrollToElement: scrollToElement,
        copyToClipboard: copyToClipboard
    };

    // Inicializar componentes
    initTooltips();
    initPopovers();

    // Adicionar classe de carregamento ao body quando a página estiver carregando
    document.body.classList.add('page-loading');
    
    // Remover classe de carregamento quando a página estiver totalmente carregada
    window.addEventListener('load', function() {
        setTimeout(function() {
            document.body.classList.remove('page-loading');
            document.body.classList.add('page-loaded');
        }, 300);
    });

    // Adicionar classe de transição suave após o carregamento
    setTimeout(function() {
        document.documentElement.classList.add('transitions-enabled');
    }, 300);

    // Inicializar DataTables com configurações padrão
    if (typeof $.fn.DataTable === 'function') {
        $('.datatable').DataTable({
            responsive: true,
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json'
            },
            dom: "<'row'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6'f>>" +
                 "<'row'<'col-sm-12'tr>>" +
                 "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
            pageLength: 10,
            lengthMenu: [[5, 10, 25, 50, -1], [5, 10, 25, 50, "Todos"]]
        });
    }

    // Adicionar classe para itens de menu ativos
    const currentPath = window.location.pathname;
    document.querySelectorAll('#sidebar-wrapper a').forEach(link => {
        const href = link.getAttribute('href');
        if (href && currentPath.includes(href) && href !== '/') {
            link.classList.add('active');
            // Expandir o submenu pai se existir
            const parentCollapse = link.closest('.collapse');
            if (parentCollapse) {
                parentCollapse.classList.add('show');
                const parentLink = document.querySelector(`[href="#${parentCollapse.id}"]`);
                if (parentLink) {
                    parentLink.classList.add('active');
                }
            }
        }
    });

    // Adicionar efeito de loading em formulários
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function() {
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processando...';
            }
        });
    });

    // Adicionar máscaras de entrada
    if (typeof $().mask === 'function') {
        $('.cpf').mask('000.000.000-00', {reverse: true});
        $('.cnpj').mask('00.000.000/0000-00', {reverse: true});
        $('.phone').mask('(00) 00000-0000');
        $('.date').mask('00/00/0000');
        $('.cep').mask('00000-000');
        $('.money').mask('000.000.000.000.000,00', {reverse: true});
    }

    // Adicionar confirmação para ações de exclusão
    document.querySelectorAll('.btn-delete').forEach(button => {
        button.addEventListener('click', function(e) {
            if (!confirm('Tem certeza que deseja excluir este item? Esta ação não poderá ser desfeita.')) {
                e.preventDefault();
            }
        });
    });
});

// Função para exibir notificações
function showNotification(message, type = 'success') {
    const notification = document.createElement('div');
    notification.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
    notification.style.top = '20px';
    notification.style.right = '20px';
    notification.style.zIndex = '9999';
    notification.style.minWidth = '300px';
    notification.role = 'alert';
    
    notification.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
    `;
    
    document.body.appendChild(notification);
    
    // Remover a notificação após 5 segundos
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => {
            notification.remove();
        }, 150);
    }, 5000);
}

// Função para carregar conteúdo via AJAX
function loadContent(url, targetElement, callback) {
    const target = document.querySelector(targetElement);
    if (!target) return;
    
    target.classList.add('loading');
    
    fetch(url)
        .then(response => response.text())
        .then(html => {
            target.innerHTML = html;
            if (typeof callback === 'function') {
                callback();
            }
        })
        .catch(error => {
            console.error('Erro ao carregar o conteúdo:', error);
            target.innerHTML = '<div class="alert alert-danger">Erro ao carregar o conteúdo. Por favor, tente novamente.</div>';
        })
        .finally(() => {
            target.classList.remove('loading');
        });
}

// Adicionar classe de carregamento global
function setLoading(show = true) {
    if (show) {
        document.body.classList.add('loading');
    } else {
        document.body.classList.remove('loading');
    }
}

// Inicializar tooltips dinâmicos
function initTooltips() {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
}

// Inicializar popovers
function initPopovers() {
    const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });
}

// Função para rolar suavemente para um elemento
function scrollToElement(element, offset = 20) {
    const elementPosition = element.getBoundingClientRect().top;
    const offsetPosition = elementPosition + window.pageYOffset - offset;

    window.scrollTo({
        top: offsetPosition,
        behavior: 'smooth'
    });
}

// Função para copiar texto para a área de transferência
function copyToClipboard(text, element = null) {
    navigator.clipboard.writeText(text).then(function() {
        if (element) {
            const originalText = element.innerHTML;
            element.innerHTML = '<i class="bi bi-check"></i> Copiado!';
            setTimeout(() => {
                element.innerHTML = originalText;
            }, 2000);
        }
        showNotification('Texto copiado para a área de transferência!', 'success');
    }).catch(function(err) {
        console.error('Erro ao copiar texto: ', err);
        showNotification('Erro ao copiar o texto.', 'danger');
    });
}

// Exportar funções para uso global
window.App = {
    showNotification,
    loadContent,
    setLoading,
    initTooltips,
    initPopovers,
    scrollToElement,
    copyToClipboard
};
