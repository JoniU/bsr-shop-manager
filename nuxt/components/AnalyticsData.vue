<template>
    <div class="space-y-4 flex items-center ">
        <!-- Order ID Input and Fetch Button -->
        <div class="space-y-4 pt-8 w-full">
            <div
                class="flex flex-col m-auto sm:flex-row justify-center items-center w-full space-y-4 sm:space-y-0 sm:space-x-4 max-w-xl">
                <!-- Button -->
                <UButton label="Fetch Report Cache" @click="fetchReport" color="primary" variant="subtle"
                    class="w-full sm:w-auto" />
                <UButton label="Regenerate Report" @click="regenerateReport" color="secondary" variant="outline"
                    class="w-full sm:w-auto" />
            </div>
            <!-- Display Order Data -->
            <div v-if="isLoading" class="p-4">
                <UProgress v-model="loading" />
            </div>
            <div v-else-if="error" class="text-red-500">{{ error }}</div>
            <pre v-else-if="report" class="p-4 border border-gray-300 dark:border-gray-700 m-4 rounded overflow-x-auto">
                {{ JSON.stringify(report, null, 2) }}
            </pre>
        </div>
    </div>
</template>


<script setup>
import { ref } from "vue";

const report = ref(null);
const isLoading = ref(false);
const error = ref(null);
const loading = ref(null);

const apiUrl = `${useRuntimeConfig().public.baseUrl}/wp-json/custom/v1/profit-time`;

async function fetchReport() {
    isLoading.value = true;
    error.value = null;
    try {
        console.log(apiUrl)
        const data = await $fetch(apiUrl);
        report.value = data;
    } catch (err) {
        console.error(err);
        error.value = "Failed to fetch the report.";
    } finally {
        isLoading.value = false;
    }
}

async function regenerateReport() {
    isLoading.value = true;
    error.value = null;
    try {
        const data = await $fetch(`${apiUrl}?regenerate=true`);
        report.value = data;
    } catch (err) {
        console.error(err);
        error.value = "Failed to regenerate the report.";
    } finally {
        isLoading.value = false;
    }
}
</script>
