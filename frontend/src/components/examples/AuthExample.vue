<!-- 
  Ejemplo de uso de los composables de autenticación
  Este componente muestra cómo usar los diferentes composables
-->
<template>
  <div class="auth-example">
    <h2>Ejemplo de Composables de Autenticación</h2>
    
    <!-- Estado de autenticación -->
    <div class="auth-status">
      <h3>Estado de Autenticación</h3>
      <p><strong>Autenticado:</strong> {{ isAuthenticated ? 'Sí' : 'No' }}</p>
      <p v-if="user"><strong>Usuario:</strong> {{ user.name }}</p>
      <p v-if="user"><strong>Email:</strong> {{ user.email }}</p>
      <p><strong>Cargando:</strong> {{ isLoading ? 'Sí' : 'No' }}</p>
    </div>

    <!-- Estados de Google -->
    <div class="google-status">
      <h3>Estado de Google OAuth</h3>
      <p><strong>Google SDK Cargado:</strong> {{ googleAuth.isGoogleSDKLoaded ? 'Sí' : 'No' }}</p>
      <p><strong>Google Inicializado:</strong> {{ googleAuth.isGoogleInitialized ? 'Sí' : 'No' }}</p>
      <p><strong>Google Listo:</strong> {{ googleAuth.isGoogleReady ? 'Sí' : 'No' }}</p>
    </div>

    <!-- Permisos -->
    <div class="permissions-status">
      <h3>Permisos del Usuario</h3>
      <p><strong>Rol:</strong> {{ permissions.getCurrentRole }}</p>
      <p><strong>Puede leer libros:</strong> {{ permissions.canReadBooks ? 'Sí' : 'No' }}</p>
      <p><strong>Puede escribir libros:</strong> {{ permissions.canWriteBooks ? 'Sí' : 'No' }}</p>
      <p><strong>Puede eliminar libros:</strong> {{ permissions.canDeleteBooks ? 'Sí' : 'No' }}</p>
      <p><strong>Puede importar datos:</strong> {{ permissions.canImportData ? 'Sí' : 'No' }}</p>
    </div>

    <!-- Botones de acción -->
    <div class="actions">
      <h3>Acciones</h3>
      <button @click="testApiCall" :disabled="!isAuthenticated || isLoading">
        Probar API Call
      </button>
      <button @click="handleLogout" :disabled="!isAuthenticated || isLoading">
        Cerrar Sesión
      </button>
      <button @click="clearAllErrors">
        Limpiar Errores
      </button>
    </div>

    <!-- Errores -->
    <div v-if="error || googleAuth.googleError" class="errors">
      <h3>Errores</h3>
      <p v-if="error" class="error">Auth Error: {{ error }}</p>
      <p v-if="googleAuth.googleError" class="error">Google Error: {{ googleAuth.googleError }}</p>
    </div>

    <!-- Pruebas de permisos -->
    <div class="permission-tests">
      <h3>Pruebas de Permisos</h3>
      <div class="test-item">
        <span>¿Puede crear libro?</span>
        <span>{{ canPerformAction('create_book') ? 'Sí' : 'No' }}</span>
      </div>
      <div class="test-item">
        <span>¿Puede acceder a /library?</span>
        <span>{{ permissions.canAccessRoute('/library') ? 'Sí' : 'No' }}</span>
      </div>
      <div class="test-item">
        <span>¿Puede acceder a /books?</span>
        <span>{{ permissions.canAccessRoute('/books') ? 'Sí' : 'No' }}</span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { useAuthSystem } from '@/composables';

// Usar el composable combinado que incluye todo
const {
  // Estados de autenticación
  user,
  isAuthenticated,
  isLoading,
  error,
  
  // Métodos de autenticación
  authenticatedApiCall,
  logout,
  clearError,
  canPerformAction,
  
  // Google Auth (prefijo googleAuth)
  googleAuth,
  
  // Permissions (prefijo permissions)
  permissions
} = useAuthSystem();

// Métodos del componente
const testApiCall = async () => {
  try {
    const response = await authenticatedApiCall('get_library_items');
    console.log('API Call successful:', response.data);
  } catch (err) {
    console.error('API Call failed:', err);
  }
};

const handleLogout = async () => {
  try {
    await logout();
    console.log('Logout successful');
  } catch (err) {
    console.error('Logout failed:', err);
  }
};

const clearAllErrors = () => {
  clearError();
  googleAuth.clearGoogleError();
  permissions.clearAccessDenied();
};
</script>

<style scoped lang="scss">
.auth-example {
  max-width: 800px;
  margin: 20px auto;
  padding: 20px;
  font-family: Arial, sans-serif;
}

.auth-status,
.google-status,
.permissions-status,
.actions,
.errors,
.permission-tests {
  margin-bottom: 30px;
  padding: 15px;
  border: 1px solid #ddd;
  border-radius: 8px;
  background-color: #f9f9f9;
}

.auth-status h3,
.google-status h3,
.permissions-status h3,
.actions h3,
.errors h3,
.permission-tests h3 {
  margin-top: 0;
  color: #333;
  border-bottom: 2px solid #007bff;
  padding-bottom: 5px;
}

.actions button {
  margin-right: 10px;
  margin-bottom: 10px;
  padding: 8px 16px;
  background-color: #007bff;
  color: white;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  transition: background-color 0.3s;
}

.actions button:hover:not(:disabled) {
  background-color: #0056b3;
}

.actions button:disabled {
  background-color: #6c757d;
  cursor: not-allowed;
}

.error {
  color: #dc3545;
  background-color: #f8d7da;
  border: 1px solid #f5c6cb;
  border-radius: 4px;
  padding: 8px 12px;
  margin: 5px 0;
}

.test-item {
  display: flex;
  justify-content: space-between;
  padding: 5px 0;
  border-bottom: 1px solid #eee;
}

.test-item:last-child {
  border-bottom: none;
}

p {
  margin: 8px 0;
}

strong {
  color: #495057;
}
</style>
