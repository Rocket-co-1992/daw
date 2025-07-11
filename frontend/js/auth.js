/**
 * Sistema de autenticação
 */

class AuthManager {
    constructor() {
        this.currentUser = null;
        this.loginModal = null;
        this.registerModal = null;
        
        this.init();
    }
    
    init() {
        this.setupModals();
        this.setupEventListeners();
        this.checkAuthStatus();
    }
    
    setupModals() {
        this.loginModal = $('#loginModal');
        this.registerModal = $('#registerModal');
        
        // Event listeners para modals
        $('.modal-close').on('click', () => {
            this.hideAllModals();
        });
        
        $(window).on('click', (e) => {
            if ($(e.target).hasClass('modal')) {
                this.hideAllModals();
            }
        });
        
        // Alternar entre login e registro
        $('#showRegisterForm').on('click', (e) => {
            e.preventDefault();
            this.showRegisterModal();
        });
        
        $('#showLoginForm').on('click', (e) => {
            e.preventDefault();
            this.showLoginModal();
        });
        
        // Submissão de formulários
        $('#loginForm').on('submit', (e) => {
            e.preventDefault();
            this.handleLogin();
        });
        
        $('#registerForm').on('submit', (e) => {
            e.preventDefault();
            this.handleRegister();
        });
    }
    
    setupEventListeners() {
        // Eventos de autenticação
        window.addEventListener('userLoggedIn', (e) => {
            this.currentUser = e.detail;
            this.updateUI();
            this.hideAllModals();
        });
        
        window.addEventListener('userLoggedOut', () => {
            this.currentUser = null;
            this.updateUI();
            this.showLoginModal();
        });
        
        // Menu do usuário
        $('#userMenuBtn').on('click', () => {
            this.showUserMenu();
        });
    }
    
    async checkAuthStatus() {
        try {
            const user = await api.getCurrentUser();
            
            if (user) {
                this.currentUser = user;
                this.updateUI();
            } else {
                this.showLoginModal();
            }
        } catch (error) {
            console.error('Erro ao verificar status de autenticação:', error);
            this.showLoginModal();
        }
    }
    
    async handleLogin() {
        const username = $('#loginUsername').val().trim();
        const password = $('#loginPassword').val();
        
        if (!username || !password) {
            this.showError('Por favor, preencha todos os campos');
            return;
        }
        
        try {
            this.showLoading(true);
            
            const user = await api.login(username, password);
            
            // O evento userLoggedIn será disparado automaticamente
            
        } catch (error) {
            this.showError(error.message);
        } finally {
            this.showLoading(false);
        }
    }
    
    async handleRegister() {
        const username = $('#registerUsername').val().trim();
        const email = $('#registerEmail').val().trim();
        const password = $('#registerPassword').val();
        const name = $('#registerName').val().trim();
        
        if (!username || !email || !password) {
            this.showError('Por favor, preencha todos os campos obrigatórios');
            return;
        }
        
        if (password.length < 6) {
            this.showError('A senha deve ter pelo menos 6 caracteres');
            return;
        }
        
        if (!this.isValidEmail(email)) {
            this.showError('Por favor, insira um email válido');
            return;
        }
        
        try {
            this.showLoading(true);
            
            const userData = {
                username,
                email,
                password,
                nome_completo: name
            };
            
            const user = await api.register(userData);
            
            // O evento userLoggedIn será disparado automaticamente
            
        } catch (error) {
            this.showError(error.message);
        } finally {
            this.showLoading(false);
        }
    }
    
    showLoginModal() {
        this.hideAllModals();
        this.loginModal.fadeIn(200);
        $('#loginUsername').focus();
        this.clearForms();
    }
    
    showRegisterModal() {
        this.hideAllModals();
        this.registerModal.fadeIn(200);
        $('#registerUsername').focus();
        this.clearForms();
    }
    
    hideAllModals() {
        $('.modal').fadeOut(200);
        this.clearErrors();
    }
    
    updateUI() {
        if (this.currentUser) {
            // Atualizar informações do usuário na interface
            $('#username').text(this.currentUser.username);
            
            if (this.currentUser.avatar) {
                $('#userAvatar').attr('src', this.currentUser.avatar);
            } else {
                // Avatar padrão baseado no nome
                const initial = this.currentUser.username.charAt(0).toUpperCase();
                $('#userAvatar').attr('src', 
                    `https://via.placeholder.com/32x32/3498db/fff?text=${initial}`);
            }
            
            // Mostrar interface principal
            $('.app-main').show();
            
        } else {
            // Esconder interface principal
            $('.app-main').hide();
        }
    }
    
    showUserMenu() {
        // Criar menu contextual do usuário
        const menu = $(`
            <div class="context-menu" id="userContextMenu">
                <div class="context-menu-item" data-action="profile">
                    <i class="fas fa-user"></i> Perfil
                </div>
                <div class="context-menu-item" data-action="settings">
                    <i class="fas fa-cog"></i> Configurações
                </div>
                <div class="context-menu-separator"></div>
                <div class="context-menu-item" data-action="logout">
                    <i class="fas fa-sign-out-alt"></i> Sair
                </div>
            </div>
        `);
        
        // Posicionar menu
        const button = $('#userMenuBtn');
        const offset = button.offset();
        
        menu.css({
            top: offset.top + button.outerHeight() + 5,
            right: $(window).width() - offset.left - button.outerWidth()
        });
        
        // Adicionar ao DOM
        $('body').append(menu);
        
        // Event listeners
        menu.on('click', '.context-menu-item', (e) => {
            const action = $(e.currentTarget).data('action');
            this.handleUserMenuAction(action);
            menu.remove();
        });
        
        // Fechar ao clicar fora
        $(document).one('click', () => {
            menu.remove();
        });
        
        // Prevenir fechamento imediato
        menu.on('click', (e) => {
            e.stopPropagation();
        });
    }
    
    handleUserMenuAction(action) {
        switch (action) {
            case 'profile':
                this.showProfileModal();
                break;
            case 'settings':
                this.showSettingsModal();
                break;
            case 'logout':
                this.logout();
                break;
        }
    }
    
    showProfileModal() {
        // TODO: Implementar modal de perfil
        console.log('Abrir modal de perfil');
    }
    
    showSettingsModal() {
        // TODO: Implementar modal de configurações
        console.log('Abrir modal de configurações');
    }
    
    logout() {
        if (confirm('Tem certeza que deseja sair?')) {
            api.logout();
        }
    }
    
    showError(message) {
        // Remover erros anteriores
        this.clearErrors();
        
        // Adicionar nova mensagem de erro
        const errorElement = $(`
            <div class="error-message" style="
                background: #e74c3c;
                color: white;
                padding: 10px;
                border-radius: 4px;
                margin-bottom: 15px;
                font-size: 14px;
            ">
                ${message}
            </div>
        `);
        
        $('.modal:visible .modal-body').prepend(errorElement);
        
        // Remover após 5 segundos
        setTimeout(() => {
            errorElement.fadeOut(200, () => errorElement.remove());
        }, 5000);
    }
    
    clearErrors() {
        $('.error-message').remove();
    }
    
    showLoading(show) {
        if (show) {
            $('.btn-primary').prop('disabled', true).text('Aguarde...');
        } else {
            $('.btn-primary').prop('disabled', false);
            $('#loginForm .btn-primary').text('Entrar');
            $('#registerForm .btn-primary').text('Cadastrar');
        }
    }
    
    clearForms() {
        $('#loginForm')[0].reset();
        $('#registerForm')[0].reset();
        this.clearErrors();
    }
    
    isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }
    
    getCurrentUser() {
        return this.currentUser;
    }
    
    isAuthenticated() {
        return !!this.currentUser;
    }
}

// Instância global do gerenciador de autenticação
window.authManager = new AuthManager();
