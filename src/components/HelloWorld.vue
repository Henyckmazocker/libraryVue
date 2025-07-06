<template>
  <div class="hello-container">
    <BarcodeScanner @isbn-scanned="handleIsbnScanned" @scanner-loaded="onScannerLoaded" />
    <h1 class="title">ISBN Book Finder</h1>
    <div class="input-group">
      <input type="text" class="isbn-input" placeholder="Enter ISBN manually" v-model="decodedText" @keyup.enter="triggerFetchBookInfo" required />
      <button @click="triggerFetchBookInfo" class="search-button">Search</button>
    </div>

    <BookDisplay 
      :book="currentBook" 
      @add-book-to-library="addBookToLibrary" 
      :allowed-user-statuses="ALLOWED_USER_STATUSES" 
      v-if="currentBook.title" 
    />

    <div v-if="searchError" class="error-message">
      <p>{{ searchError }}</p>
    </div>
    <div v-if="addBookMessage" :class="['add-book-message', addBookStatus]">
      <p>{{ addBookMessage }}</p>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive } from "vue";
import axios from 'axios';
import BarcodeScanner from './BarcodeScanner.vue';
import BookDisplay from './BookDisplay.vue';

// Ideally, this should come from the backend or a shared configuration
const ALLOWED_USER_STATUSES = ['owned', 'read', 'want to buy', 'reading'];

const decodedText = ref("");
const currentBook = reactive({
  isbn: "",
  title: "",
  author: "",
  coverUrl: ""
});
const searchError = ref("");
const addBookMessage = ref("");
const addBookStatus = ref(""); // 'success' or 'error'

const clearBookDetails = () => {
  currentBook.isbn = "";
  currentBook.title = "";
  currentBook.author = "";
  currentBook.coverUrl = "";
  addBookMessage.value = "";
  addBookStatus.value = "";
};

// Handler for the event from BarcodeScanner.vue
const handleIsbnScanned = (scannedIsbn) => {
  decodedText.value = scannedIsbn;
  clearBookDetails();
  searchError.value = "";
  fetchBookInfo();
};

const onScannerLoaded = () => {
  console.log("Scanner loaded event received in parent (HelloWorld.vue).");
  // You can add any logic here if needed when the scanner is ready
};

// Renamed from onDecode, which was specific to the old structure
const triggerFetchBookInfo = () => {
  clearBookDetails();
  searchError.value = "";
  fetchBookInfo();
}

const fetchBookInfo = async () => {
  // searchError and book details are cleared by the calling functions (handleIsbnScanned or triggerFetchBookInfo)
  const isbn = decodedText.value.trim();
  currentBook.isbn = isbn;

  if (!isbn) {
    searchError.value = "Please enter or scan an ISBN.";
    return;
  }

  try {
    const apiUrl = `https://openlibrary.org/isbn/${isbn}.json`;
    const response = await axios.get(apiUrl);
    const data = response.data;

    if (!data.error) {
      const details = data;
      currentBook.title = details.title || "Title not found";
      currentBook.author = (details.authors && details.authors.length > 0) ? details.authors[0].name : "Author not found";
      currentBook.coverUrl = (details.covers && details.covers.length > 0) ? `https://covers.openlibrary.org/b/id/${details.covers[0]}-L.jpg` : "";
      
      if (currentBook.title === "Title not found" && currentBook.author === "Author not found") {
        searchError.value = "Book details not found for this ISBN.";
      }
    } else {
      searchError.value = "Book not found for this ISBN.";
    }
  } catch (error) {
    console.error("Error fetching book information:", error);
    if (error.response) {
      console.error("API Error Response:", error.response.data);
      if (error.response.status === 503) {
        searchError.value = "The book information service (OpenLibrary) is temporarily unavailable (503). Please try again later.";
      } else if (error.response.status === 404) {
        searchError.value = "Book not found for this ISBN (404 error from API).";
      } else if (error.response.status === 429) {
        searchError.value = "Too many requests to book API. Please try again later.";
      } else {
        searchError.value = `Failed to fetch book information. API returned status ${error.response.status}.`;
      }
    } else if (error.request) {
      searchError.value = "No response from book API. Check your internet connection or try again later.";
    } else {
      searchError.value = "Error setting up request to book API: " + error.message;
    }
    // Clear book details on error as well, so no stale info is shown
    currentBook.title = "";
    currentBook.author = "";
    currentBook.coverUrl = "";
  }
};

const addBookToLibrary = async (bookDetailsWithStatuses) => {
  const { book, statuses } = bookDetailsWithStatuses;

  if (!book.title || book.title === "Title not found") {
    addBookMessage.value = "Cannot add book: valid details not found.";
    addBookStatus.value = "error";
    return;
  }
  if (!statuses || statuses.length === 0) {
    addBookMessage.value = "Cannot add book: at least one user status must be selected.";
    addBookStatus.value = "error";
    return;
  }

  try {
    const backendApiUrl = process.env.VUE_APP_API_URL || '/backend/api.php';
    console.log("Attempting to POST to backend at:", backendApiUrl);
    const response = await axios.post(backendApiUrl, {
      action: 'add_book',
      book: { 
        ...book,
        userStatuses: statuses
      }
    });
    if (response.data && response.data.status === 'success') {
      addBookMessage.value = response.data.message || "Book added successfully!";
      addBookStatus.value = "success";
    } else {
      addBookMessage.value = response.data.message || "Failed to add book. Unknown error.";
      addBookStatus.value = "error";
    }
  } catch (error) {
    console.error("Error adding book to library:", error);
    addBookMessage.value = "Error connecting to backend to add book.";
    addBookStatus.value = "error";
    if (error.response) console.error("Backend Error Response:", error.response.data);
  }
};

</script>

<style scoped>
/* Styles for elements directly within HelloWorld.vue */
.hello-container {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 30px;
  width: 100%;
  max-width: 500px;
  margin: auto;
}

.title {
  font-size: 2.5rem;
  font-weight: 700;
  color: #e0e0e0;
  margin-bottom: 40px;
}

.input-group {
  display: flex;
  width: 100%;
  margin-bottom: 30px;
}

.isbn-input {
  flex-grow: 1;
  padding: 15px 20px;
  font-size: 1rem;
  color: #e0e0e0;
  background-color: #2c2c2c;
  border: 1px solid #444;
  border-radius: 30px 0 0 30px;
  outline: none;
  transition: border-color 0.3s ease, box-shadow 0.3s ease;
}

.isbn-input::placeholder {
  color: #888;
}

.isbn-input:focus {
  border-color: #007bff;
  box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.25);
}

.search-button {
  padding: 15px 30px;
  font-size: 1rem;
  font-weight: 500;
  color: #ffffff;
  background-color: #007bff;
  border: 1px solid #007bff;
  border-radius: 0 30px 30px 0;
  cursor: pointer;
  outline: none;
  transition: background-color 0.3s ease, border-color 0.3s ease;
}

.search-button:hover {
  background-color: #0056b3;
  border-color: #0056b3;
}

.error-message {
  margin-top: 20px;
  color: #ff4d4f;
  font-size: 0.9rem;
  background-color: rgba(255, 77, 79, 0.1);
  padding: 10px 15px;
  border-radius: 15px;
  width: 100%;
}

.add-book-message {
  margin-top: 15px;
  padding: 10px 15px;
  border-radius: 15px;
  width: 100%;
  font-size: 0.9rem;
}

.add-book-message.success {
  background-color: rgba(40, 167, 69, 0.15);
  color: #28a745;
}

.add-book-message.error {
  background-color: rgba(255, 77, 79, 0.1);
  color: #ff4d4f;
}

/* Removed styles that were moved to BookDisplay.vue and BarcodeScanner.vue */
/* e.g., .barcode-reader-small, .result-container, .book-details, etc. */

</style>
