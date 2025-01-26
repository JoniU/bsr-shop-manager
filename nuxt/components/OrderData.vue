<template>
    <div class="space-y-4 flex items-center ">
        <!-- Order ID Input and Fetch Button -->
        <div class="space-y-4 pt-8 w-full">
            <div
                class="flex flex-col m-auto sm:flex-row justify-center items-center w-full space-y-4 sm:space-y-0 sm:space-x-4 max-w-xl">
                <!-- Button -->
                <UButton label="Fetch Order Cache" @click="handleFetchOrder" color="primary" variant="subtle"
                    class="w-full sm:w-auto" />
                <!-- Input -->
                <UInput id="order-id" v-model="orderId" type="text" placeholder="Enter Order ID" label="Order ID"
                    class="w-full flex-1" />
                <UButton label="Regenerate Cache" @click="handleRegenerateFile" color="secondary" variant="outline"
                    class="w-full sm:w-auto" />
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
const loading = ref(null);
const successMessage = ref('');

// API base URL (Nuxt runtime config)
const apiUrl = `${useRuntimeConfig().public.baseUrl}/wp-json/custom/v1/order`;

async function handleFetchOrder() {
    isLoading.value = true;
    errorMessage.value = null;
    orders.value = []; // Reset orders

    try {
        let currentPage = 1;
        const perPage = 200;
        let totalPages = 1; // Will update dynamically after the first fetch

        do {
            // Fetch data for the current page
            const response = await $fetch(`${apiUrl}?page=${currentPage}&per_page=${perPage}`, {
                method: 'GET',
                credentials: 'include', // Ensures cookies are sent for CORS requests
            });

            // Append fetched orders to the existing array
            orders.value = [...orders.value, ...response.orders];

            // Update total pages from the response metadata
            totalPages = response.meta.total_pages;

            // Increment the current page
            currentPage++;
        } while (currentPage <= totalPages);

        errorMessage.value = ''; // Clear any previous errors
    } catch (error) {
        console.error('Error fetching orders:', error);
        orders.value = null; // Reset order data
        errorMessage.value = 'Failed to fetch orders. Please try again.';
    } finally {
        isLoading.value = false;
    }
}

// Function to regenerate the file using the paginated API
async function handleRegenerateFile() {
    isLoading.value = true;
    errorMessage.value = '';
    successMessage.value = '';
    let currentPage = 1;
    const perPage = 200; // Adjust as needed
    let hasMoreData = true;
    const pageLimit = 150; // Limit to prevent infinite loops

    try {
        while (hasMoreData) {
            // Determine if this is the last page
            const isLastPage = currentPage >= pageLimit;

            // Call the paginated API endpoint
            const response = await $fetch(
                `${apiUrl}-flush?page=${currentPage}&per_page=${perPage}&last=${isLastPage}`,
                {
                    method: 'GET',
                    credentials: 'include', // Ensures cookies for authentication
                }
            );

            console.log(
                `Processed page ${currentPage}, orders: ${response.orders_count}`
            );

            // Check if there are more orders to process
            if (response.orders_count < perPage || isLastPage) {
                hasMoreData = false; // Exit loop if no more data or we're on the last page
                console.log(`No more data to fetch`);
            } else {
                currentPage++; // Move to the next page
            }
        }
        successMessage.value = 'File regenerated successfully!';
    } catch (error) {
        console.error('Error regenerating file:', error);
        errorMessage.value =
            'Failed to regenerate the file. Please check the API.';
    } finally {
        isLoading.value = false;
        currentPage = 1;
    }
}

</script>

<style scoped></style>
