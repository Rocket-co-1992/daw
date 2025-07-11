/**
 * Cliente para comunicação com a API REST
 */

class APIClient {
    constructor() {
        this.baseURL = DAWConfig.API_BASE_URL;
        this.token = localStorage.getItem('daw_token');
        this.refreshToken = localStorage.getItem('daw_refresh_token');
    }
    
    // Método para fazer requisições HTTP
    async request(endpoint, options = {}) {
        const url = `${this.baseURL}/${endpoint}`;
        
        const defaultOptions = {
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            }
        };
        
        // Adicionar token de autorização se disponível
        if (this.token) {
            defaultOptions.headers['Authorization'] = `Bearer ${this.token}`;
        }
        
        const requestOptions = {
            ...defaultOptions,
            ...options,
            headers: {
                ...defaultOptions.headers,
                ...options.headers
            }
        };
        
        try {
            const response = await fetch(url, requestOptions);
            
            // Se token expirou, tentar renovar
            if (response.status === 401 && this.refreshToken) {
                const refreshed = await this.refreshAuthToken();
                if (refreshed) {
                    // Tentar novamente com novo token
                    requestOptions.headers['Authorization'] = `Bearer ${this.token}`;
                    return await fetch(url, requestOptions);
                }
            }
            
            return response;
        } catch (error) {
            console.error('Erro na requisição:', error);
            throw new Error(DAWConfig.ERROR_MESSAGES.NETWORK_ERROR);
        }
    }
    
    // Métodos HTTP convenientes
    async get(endpoint, params = {}) {
        const queryString = new URLSearchParams(params).toString();
        const url = queryString ? `${endpoint}?${queryString}` : endpoint;
        
        const response = await this.request(url, { method: 'GET' });
        return this.handleResponse(response);
    }
    
    async post(endpoint, data = {}) {
        const response = await this.request(endpoint, {
            method: 'POST',
            body: JSON.stringify(data)
        });
        return this.handleResponse(response);
    }
    
    async put(endpoint, data = {}) {
        const response = await this.request(endpoint, {
            method: 'PUT',
            body: JSON.stringify(data)
        });
        return this.handleResponse(response);
    }
    
    async delete(endpoint) {
        const response = await this.request(endpoint, { method: 'DELETE' });
        return this.handleResponse(response);
    }
    
    // Upload de arquivos
    async upload(endpoint, formData) {
        const response = await this.request(endpoint, {
            method: 'POST',
            body: formData,
            headers: {} // Remove Content-Type para FormData
        });
        return this.handleResponse(response);
    }
    
    // Processar resposta
    async handleResponse(response) {
        let data;
        
        try {
            data = await response.json();
        } catch (error) {
            data = {};
        }
        
        if (!response.ok) {
            const errorMessage = data.error || `Erro HTTP ${response.status}`;
            throw new Error(errorMessage);
        }
        
        return data;
    }
    
    // Autenticação
    async login(username, password) {
        try {
            const data = await this.post('auth.php?action=login', {
                username,
                password
            });
            
            if (data.success && data.user) {
                this.setAuthData(data.user);
                return data.user;
            }
            
            throw new Error(data.error || 'Erro no login');
        } catch (error) {
            console.error('Erro no login:', error);
            throw error;
        }
    }
    
    async register(userData) {
        try {
            const data = await this.post('auth.php?action=register', userData);
            
            if (data.success && data.user) {
                this.setAuthData(data.user);
                return data.user;
            }
            
            throw new Error(data.error || 'Erro no cadastro');
        } catch (error) {
            console.error('Erro no cadastro:', error);
            throw error;
        }
    }
    
    async getCurrentUser() {
        if (!this.token) {
            return null;
        }
        
        try {
            const data = await this.post('auth.php?action=me');
            
            if (data.success && data.user) {
                return data.user;
            }
            
            return null;
        } catch (error) {
            console.error('Erro ao obter usuário atual:', error);
            this.clearAuthData();
            return null;
        }
    }
    
    async refreshAuthToken() {
        if (!this.refreshToken) {
            return false;
        }
        
        try {
            const data = await this.post('auth.php?action=refresh');
            
            if (data.success && data.token) {
                this.token = data.token;
                localStorage.setItem('daw_token', this.token);
                return true;
            }
            
            return false;
        } catch (error) {
            console.error('Erro ao renovar token:', error);
            this.clearAuthData();
            return false;
        }
    }
    
    logout() {
        this.clearAuthData();
        window.location.reload();
    }
    
    setAuthData(user) {
        this.token = user.token;
        localStorage.setItem('daw_token', this.token);
        localStorage.setItem('daw_user', JSON.stringify(user));
        
        // Disparar evento de login
        window.dispatchEvent(new CustomEvent('userLoggedIn', {
            detail: user
        }));
    }
    
    clearAuthData() {
        this.token = null;
        this.refreshToken = null;
        localStorage.removeItem('daw_token');
        localStorage.removeItem('daw_refresh_token');
        localStorage.removeItem('daw_user');
        
        // Disparar evento de logout
        window.dispatchEvent(new CustomEvent('userLoggedOut'));
    }
    
    getStoredUser() {
        const userData = localStorage.getItem('daw_user');
        return userData ? JSON.parse(userData) : null;
    }
    
    isAuthenticated() {
        return !!this.token;
    }
    
    // Métodos específicos da DAW
    
    // Projetos
    async getProjects(limit = 20, offset = 0) {
        return await this.get('projects.php', { limit, offset });
    }
    
    async getProject(id) {
        return await this.get(`projects.php?id=${id}`);
    }
    
    async createProject(projectData) {
        return await this.post('projects.php', projectData);
    }
    
    async updateProject(id, projectData) {
        return await this.put(`projects.php?id=${id}`, projectData);
    }
    
    async deleteProject(id) {
        return await this.delete(`projects.php?id=${id}`);
    }
    
    // Faixas
    async getTracks(projectId) {
        return await this.get('tracks.php', { project_id: projectId });
    }
    
    async createTrack(trackData) {
        return await this.post('tracks.php', trackData);
    }
    
    async updateTrack(id, trackData) {
        return await this.put(`tracks.php?id=${id}`, trackData);
    }
    
    async deleteTrack(id) {
        return await this.delete(`tracks.php?id=${id}`);
    }
    
    // Regiões de áudio
    async getRegions(trackId) {
        return await this.get('regions.php', { track_id: trackId });
    }
    
    async createRegion(regionData) {
        return await this.post('regions.php', regionData);
    }
    
    async updateRegion(id, regionData) {
        return await this.put(`regions.php?id=${id}`, regionData);
    }
    
    async deleteRegion(id) {
        return await this.delete(`regions.php?id=${id}`);
    }
    
    // Plugins
    async getPlugins() {
        return await this.get('plugins.php');
    }
    
    async getPluginPresets(pluginId) {
        return await this.get(`plugins.php?id=${pluginId}&action=presets`);
    }
    
    async savePluginPreset(pluginId, presetData) {
        return await this.post(`plugins.php?id=${pluginId}&action=save_preset`, presetData);
    }
    
    // Upload de áudio
    async uploadAudio(file, projectId, trackId) {
        const formData = new FormData();
        formData.append('audio_file', file);
        formData.append('project_id', projectId);
        formData.append('track_id', trackId);
        
        return await this.upload('upload.php', formData);
    }
    
    // Configurações de áudio
    async getAudioConfig() {
        return await this.get('audio_config.php');
    }
    
    async updateAudioConfig(config) {
        return await this.post('audio_config.php', config);
    }
    
    async testAudioConfig() {
        return await this.post('audio_config.php?action=test');
    }
    
    // Colaboração
    async inviteCollaborator(projectId, username, role = 'collaborator') {
        return await this.post('collaboration.php', {
            project_id: projectId,
            username,
            role
        });
    }
    
    async getCollaborators(projectId) {
        return await this.get('collaboration.php', { project_id: projectId });
    }
    
    async updateCollaboratorRole(projectId, userId, role) {
        return await this.put('collaboration.php', {
            project_id: projectId,
            user_id: userId,
            role
        });
    }
    
    async removeCollaborator(projectId, userId) {
        return await this.delete(`collaboration.php?project_id=${projectId}&user_id=${userId}`);
    }
}

// Instância global do cliente API
window.api = new APIClient();
