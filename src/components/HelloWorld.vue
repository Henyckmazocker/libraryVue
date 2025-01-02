<template>
  <StreamBarcodeReader @decode="onDecode" @loaded="onLoaded"></StreamBarcodeReader>
  <div class="hello">
    <h1>Scan to insert isbn</h1>
    <div class="form__group field">
      <input type="input" class="form__field" placeholder="Name" name="name" id='name' val="{{ decodedText }}" required />
      <label for="name" class="form__label">Name</label>
    </div>
    <button @click="sendMessage">Send Message</button>
  </div>
</template>

<script setup>

import { StreamBarcodeReader } from "vue-barcode-reader";
import { ref } from "vue";
import axios from 'axios';

</script>

<script>

let decodedText = ref("");

export default {
  name: 'HelloWorld',
  props: {
    msg: decodedText
  },
  methods: {
    async sendMessage() {
      try {
        const response = await axios.post('http://localhost:8888/backend/api', {
          message: decodedText
        });

        console.log(response.data);
      } catch(error) {
            if (error.response) {
              // The request was made and the server responded with a status code
              // that falls out of the range of 2xx
              console.log(error.response.data);
              console.log(error.response.status);
              console.log(error.response.headers);
            } else if (error.request) {
              // The request was made but no response was received
              // `error.request` is an instance of XMLHttpRequest in the browser and an instance of
              // http.ClientRequest in node.js
              console.log(error.request);
            } else {
              // Something happened in setting up the request that triggered an Error
              console.log('Error', error.message);
            }
        }
    }
  }
};

const onDecode = (text) => {
  decodedText = text
};

const onLoaded = () => {
   console.log(`Ready to start scanning barcodes`)
};

</script>

<!-- Add "scoped" attribute to limit CSS to this component only -->
<style scoped>
h3 {
  margin: 40px 0 0;
}
ul {
  list-style-type: none;
  padding: 0;
}
li {
  display: inline-block;
  margin: 0 10px;
}
a {
  color: #42b983;
}

.form__group {
  position: relative;
  padding: 15px 0 0;
  margin-top: 10px;
  width: 50%;
  left: 25%;

}
.form__field {
  font-family: inherit;
  width: 100%;
  border: 0;
  border-bottom: 2px solid #9b9b9b;
  outline: 0;
  font-size: 1.3rem;
  color: #fff;
  padding: 7px 0;
  background: transparent;
  transition: border-color 0.2s;
}
.form__field::placeholder {
  color: transparent;
}
.form__field:placeholder-shown ~ .form__label {
  font-size: 1.3rem;
  cursor: text;
  top: 20px;
}
.form__label {
  position: absolute;
  top: 0;
  display: block;
  transition: 0.2s;
  font-size: 1rem;
  color: #9b9b9b;
}
.form__field:focus {
  padding-bottom: 6px;
  font-weight: 700;
  border-width: 3px;
  border-image: linear-gradient(to right, #11998e, #38ef7d);
  border-image-slice: 1;
}
.form__field:focus ~ .form__label {
  position: absolute;
  top: 0;
  display: block;
  transition: 0.2s;
  font-size: 1rem;
  color: #11998e;
  font-weight: 700;
}
/* reset input */
.form__field:required, .form__field:invalid {
  box-shadow: none;
}
/* demo */
body {
  font-family: 'Poppins', sans-serif;
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
  min-height: 100vh;
  font-size: 1.5rem;
  background-color: #222;
}
</style>
