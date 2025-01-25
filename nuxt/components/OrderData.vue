<template>
    <div class="space-y-4 flex items-center ">
        <!-- Order ID Input and Fetch Button -->
        <div class="space-y-4 pt-8 w-full">
            <div
                class="flex flex-col m-auto sm:flex-row justify-center items-center w-full space-y-4 sm:space-y-0 sm:space-x-4 max-w-xl">
                <!-- Button -->
                <UButton label="Fetch Order" @click="handleFetchOrder" color="primary" variant="subtle"
                    class="w-full sm:w-auto" />
                <!-- Input -->
                <UInput id="order-id" v-model="orderId" type="text" placeholder="Enter Order ID" label="Order ID"
                    class="w-full flex-1" />
            </div>
            <!-- Display Order Data -->
            <div v-if="isLoading" class="p-4">
                <UProgress v-model="loading" />
            </div>
            <div v-else-if="error" class="text-red-500">{{ error }}</div>
            <pre v-else-if="orders" class="p-4 border border-gray-300 dark:border-gray-700 m-4 rounded overflow-x-auto">
                {{ JSON.stringify(orders, null, 2) }}
            </pre>
        </div>
    </div>
</template>


<script setup>
import { ref } from "vue";

const orderId = ref(''); // Tracks the entered order ID
const errorMessage = ref(''); // Tracks error messages
const error = ref(''); // Tracks error messages
const orders = ref('');
const isLoading = ref('');
const loading = ref(null)

// API base URL (Nuxt runtime config)
const apiUrl = `${useRuntimeConfig().public.baseUrl}/wp-json/custom/v1/order`;

// Function to fetch order data
async function handleFetchOrder() {
    isLoading.value = true;
    errorMessage.value = null;
    try {
        // Fetch data using Nuxt's $fetch
        const data = await $fetch(`${apiUrl}/${orderId.value}`, {
            method: 'GET',
            credentials: 'include', // Ensures cookies are sent for CORS requests
        });

        // Assign the fetched data directly
        orders.value = data; // $fetch directly returns the data, no `value` property needed
        errorMessage.value = ''; // Clear any previous errors
    } catch (error) {
        console.error('Error fetching order:', error);
        orders.value = null; // Reset order data
        errorMessage.value = 'Failed to fetch order data. Please check the order ID.';
    } finally {
        isLoading.value = false;
    }
}

</script>

<style scoped></style>
