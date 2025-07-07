<template>
  <div id="nav">
    <router-link to="/">Inicio</router-link> | 
    <router-link to="/library">My Library</router-link>
  </div>
  <router-view/> <!-- Router will render components here -->
</template>

<script>
// No script changes needed here unless HelloWorld was being imported for other reasons
export default {
  name: 'App'
  // components: { HelloWorld } // HelloWorld is now rendered by the router, not directly here
}
</script>

<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&display=swap');

html, body {
  margin: 0;
  padding: 0;
  height: 100%;
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
  padding-top: 20px; /* Add some padding at the top */
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
.hello-container { /* Assuming HelloWorld.vue uses this class, and MyLibrary.vue will too */
  padding-top: 80px; /* Height of nav + some space */ 
}

/* img {
  width: 400px;
} */ /* Commenting out as the img tag is removed */
</style>
