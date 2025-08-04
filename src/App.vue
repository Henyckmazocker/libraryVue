<template>
  <div id="nav">
    <div class="nav-center">
      <router-link to="/"><i class="fas fa-home"></i></router-link> | 
      <router-link to="/library"><i class="fas fa-bookmark"></i></router-link>
    </div>
    <div class="nav-right">
      <template v-if="!userPicture">
        <div id="g_id_signin"></div>
      </template>
      <template v-if="userPicture">
        <img :src="userPicture" alt="Usuario" class="user-avatar" />
      </template>
    </div>
  </div>
  <router-view/> <!-- Router will render components here -->
</template>

<script>

import { ref, onMounted } from 'vue';

export default {
  name: 'App',
  setup() {
    const userPicture = ref(null);


    onMounted(() => {
      console.log(userPicture);
      const clientId = process.env.VUE_APP_GOOGLE_CLIENT_ID;
      if (!clientId) {
        alert('No se ha definido GOOGLE_CLIENT_ID en las variables de entorno.');
        return;
      }
      if (window.google && window.google.accounts && window.google.accounts.id) {
        window.google.accounts.id.initialize({
          client_id: clientId,
          callback: handleCredentialResponse
        });
        window.google.accounts.id.renderButton(
          document.getElementById('g_id_signin'),
          { theme: 'outline', size: 'large', shape: 'circle' }
        );
      }
    });

    function handleCredentialResponse(response) {
      // Decodificar el JWT para obtener la foto de perfil
      console.log('handleCredentialResponse:', response.credential);
      const base64Url = response.credential.split('.')[1];
      const base64 = base64Url.replace(/-/g, '+').replace(/_/g, '/');
      const jsonPayload = decodeURIComponent(atob(base64).split('').map(function(c) {
        return '%' + ('00' + c.charCodeAt(0).toString(16)).slice(-2);
      }).join(''));
      const payload = JSON.parse(jsonPayload);
      userPicture.value = payload.picture;
      // Puedes guardar más datos del usuario si lo deseas
      console.log('Usuario:', payload);
    }

    return { userPicture };
  }
}
</script>

<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&display=swap');

html, body {
  margin: 0;
  padding: 0;
  height: 100%;
  /* Eliminar cualquier espacio extra arriba */
  box-sizing: border-box;
}

#app {
  font-family: 'Inter', sans-serif;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
  text-align: center;
  background-color: #1a1a1a; /* Dark background */
  color: #e0e0e0; /* Light default text color */
  min-height: 100%;
  /* display: flex; flex-direction: column; align-items: center; justify-content: center; */ /* Removed to allow router-view to control layout */
  /* padding-top: 20px;  Eliminado para evitar gap arriba */
  box-sizing: border-box;
}



#nav {
  padding: 15px 30px;
  background-color: #252525; /* Slightly different dark for nav */
  margin-bottom: 30px;
  border-radius: 0 0 15px 15px; /* Rounded bottom corners for nav bar */
  box-shadow: 0 2px 10px rgba(0,0,0,0.3);
  position: fixed; /* Make nav bar fixed at the top */
  top: 0;
  left: 0;
  right: 0;
  z-index: 1000; /* Ensure nav is above other content */
  height: 68px;
  position: relative;
}


.nav-center {
  display: flex;
  align-items: center;
  gap: 10px;
  position: absolute;
  left: 50%;
  top: 50%;
  transform: translate(-50%, -50%);
}

.nav-right {
  display: flex;
  align-items: center;
  gap: 16px;
  position: absolute;
  right: 30px;
  top: 50%;
  transform: translateY(-50%);
}


#nav .user-avatar {
  width: 38px;
  height: 38px;
  border-radius: 50%;
  object-fit: cover;
  border: 2px solid #fff;
  box-shadow: 0 1px 4px rgba(0,0,0,0.10);
}

#nav a {
  font-weight: bold;
  color: #88aaff; /* Light blue for links */
  text-decoration: none;
  margin: 0 15px;
  font-size: 1.1rem;
}

#nav a.router-link-exact-active {
  color: #42b983; /* Vue green for active link */
}

/* Adjust padding for main content area to account for fixed nav */
.hello-container { /* Asumiendo que BookSearch.vue usa esta clase, y MyLibrary.vue también */
  padding-top: 80px; /* Height of nav + some space */
}
</style>
