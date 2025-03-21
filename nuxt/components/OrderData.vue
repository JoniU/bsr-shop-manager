<template>
    <div>
        <div v-if="isLoading">Loading...</div>
        <div v-else-if="error" class="text-red-500">Error: {{ error }}</div>
        <pre v-else class="p-4 border border-gray-300 dark:border-gray-700 m-4 rounded overflow-x-auto">{{
            formattedData
        }}</pre>
    </div>
</template>

<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { useRuntimeConfig } from '#app';

// Reactive state
const data = ref(null);
const isLoading = ref(true);
const error = ref<String | null>(null);

// Get the public base URL from runtime config
const config = useRuntimeConfig();
const apiUrl = config.public.baseUrl || '';

// Function to fetch orders data from the API endpoint
async function fetchOrdersData() {
    isLoading.value = true;
    error.value = null;

    try {
        // Call the REST API endpoint: /wp-json/custom/v1/order
        data.value = await $fetch(`${apiUrl}/wp-json/custom/v1/order`, {
            method: 'GET',
            credentials: 'include', // if needed for cookies/CORS
        });
    } catch (err) {
        // Determine the error message using a type guard.
        const errorMessage = err instanceof Error ? err.message : 'Error fetching orders data';
        console.error('Error fetching orders data:', err);
        error.value = errorMessage;
    } finally {
        isLoading.value = false;
    }
}

onMounted(() => {
    fetchOrdersData();
});

// Compute a formatted version of the JSON data for display.
const formattedData = computed(() => {
    return data.value ? JSON.stringify(data.value, null, 2) : '';
});
</script>

<style scoped>
/* Simple styling for the JSON output */
pre {
    font-family: Menlo, Monaco, Consolas, 'Courier New', monospace;
    font-size: 0.9rem;
    line-height: 1.4;
}
</style>
