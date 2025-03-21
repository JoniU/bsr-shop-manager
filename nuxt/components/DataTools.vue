<template>
    <div class="space-y-4 flex items-center">
        <!-- Order ID Input and Fetch Button -->
        <div class="space-y-4 pt-8 w-full">
            <div
                class="flex flex-col m-auto sm:flex-row justify-center items-center w-full space-y-4 sm:space-y-0 sm:space-x-4 max-w-xl pb-4"
            >
                <!-- Button -->
                <UButton
                    label="Regenerate Orders"
                    color="primary"
                    variant="outline"
                    class="w-full sm:w-auto"
                    @click="regenerateOrders"
                />
                <UButton
                    label="Regenerate Report"
                    color="secondary"
                    variant="outline"
                    class="w-full sm:w-auto"
                    @click="regenerateReport"
                />
            </div>
            <div
                class="flex flex-col m-auto sm:flex-row justify-center items-center w-full space-y-4 sm:space-y-0 sm:space-x-4 max-w-xl"
            >
                <!-- Button -->
                <UButton
                    label="Fetch Order"
                    color="primary"
                    variant="subtle"
                    class="w-full sm:w-auto"
                    @click="fetchOrder(orderId)"
                />
                <!-- Input -->
                <UInput
                    id="order-id"
                    v-model="orderId"
                    type="text"
                    placeholder="Enter Order ID"
                    label="Order ID"
                    class="w-full flex-1"
                />
            </div>

            <div v-if="isLoading" class="p-4">
                <UProgress v-model="loading" />
            </div>
            <!-- Display Order Data -->
            <div v-else-if="error" class="text-red-500">{{ error }}</div>
            <pre v-else-if="orders" class="p-4 border border-gray-300 dark:border-gray-700 m-4 rounded overflow-x-auto">
                {{ JSON.stringify(orders, null, 2) }}
            </pre>
            <!-- Display Report Data -->
            <pre v-else-if="report" class="p-4 border border-gray-300 dark:border-gray-700 m-4 rounded overflow-x-auto">
                {{ JSON.stringify(report, null, 2) }}
            </pre>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { useRuntimeConfig } from '#app';

const orderId = ref(''); // Tracks the entered order ID
const orders = ref('');
const report = ref(null);
const isLoading = ref(false);
const error = ref(null);
const successMessage = ref('');
const loading = ref(null);

const apiUrl = `${useRuntimeConfig().public.baseUrl}/wp-json/custom/v1`;

async function regenerateReport() {
    isLoading.value = true;
    error.value = null;
    try {
        const data = await $fetch(`${apiUrl}/profit-time?regenerate=true`);
        report.value = data;
    } catch (err) {
        console.error(err);
        error.value = 'Failed to regenerate the report.';
    } finally {
        isLoading.value = false;
    }
}

async function regenerateOrders() {
    isLoading.value = true;
    error.value = '';
    successMessage.value = '';
    let currentPage = 1;
    let hasMoreData = true;
    const pageLimit = 150; // Prevent infinite loops
    const batchSize = 200; // Must match the backend's batchSize

    try {
        // First, reset the backfill tracking and clear the table.
        await $fetch(`${apiUrl}/order/recalculate-orders?clear_tracking=true&clear_table=true`, {
            method: 'POST',
            credentials: 'include', // Ensures cookies for authentication
        });
        console.log('Backfill reset completed.');

        // Now, repeatedly call the GET endpoint to process batches.
        while (hasMoreData && currentPage < pageLimit) {
            // Call the GET endpoint with the current page number.
            const response = await $fetch(`${apiUrl}/recalculate-orders?page=${currentPage}`, {
                method: 'GET',
                credentials: 'include',
            });

            console.log(`Processed page ${currentPage}, orders processed: ${response.orders_count}`);

            // If fewer orders than batchSize were processed, we're done.
            if (response.orders_count < batchSize) {
                hasMoreData = false;
                console.log('No more data to fetch.');
            } else {
                currentPage++;
            }
        }
        successMessage.value = 'Database backfill completed successfully!';
    } catch (error) {
        console.error('Error updating database:', error);
        error.value = 'Failed to update the database. Please check the API.';
    } finally {
        isLoading.value = false;
        currentPage = 1;
    }
}

async function fetchOrder(orderId) {
    // Assume orderId is provided (e.g., as a parameter or reactive variable)
    isLoading.value = true;
    error.value = null;
    orders.value = []; // Reset orders

    if (!orderId) {
        error.value = 'Please enter an order ID.';
        isLoading.value = false;
        return;
    }

    try {
        // Call the endpoint for one order using the order ID.
        const response = await $fetch(`${apiUrl}/order/${orderId}`, {
            method: 'GET',
            credentials: 'include', // Ensures cookies are sent for CORS requests
        });

        // If the API returns the order object directly, store it (wrapped in an array for consistency)
        orders.value = [response];
        error.value = '';
    } catch (error) {
        console.error('Error fetching order:', error);
        orders.value = null;
        error.value = 'Failed to fetch order. Please try again.';
    } finally {
        isLoading.value = false;
    }
}
</script>
