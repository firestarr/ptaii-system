// src/main.js
import { createApp } from "vue";
import App from "./App.vue";
import router from "./router";
import axios from "axios";
import "./assets/css/main.css";
// Import global components
import SearchFilter from "@/components/common/SearchFilter.vue";
import Pagination from "@/components/common/Pagination.vue";
import ConfirmationModal from "@/components/common/ConfirmationModal.vue";
import DataTable from "@/components/common/DataTable.vue";


// Configure axios
axios.defaults.baseURL =
    process.env.VUE_APP_API_URL || "http://127.0.0.1:8020/api";

// Add token to requests if it exists
axios.interceptors.request.use((config) => {
    const token = localStorage.getItem("token");
    if (token) {
        config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
});

// Handle unauthorized responses
axios.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error.response && error.response.status === 401) {
            // Clear token and redirect to login
            localStorage.removeItem("token");
            localStorage.removeItem("user");
            router.push("/login");
        }
        return Promise.reject(error);
    }
);

// Definisikan feature flag terlebih dahulu
window.__VUE_PROD_HYDRATION_MISMATCH_DETAILS__ = false;
// Create and mount the Vue application
const app = createApp(App);
app.use(router);
app.mount("#app");
// Register global components
app.component("SearchFilter", SearchFilter);
app.component("PaginationComponent", Pagination);
app.component("ConfirmationModal", ConfirmationModal);
app.component("DataTable", DataTable);
