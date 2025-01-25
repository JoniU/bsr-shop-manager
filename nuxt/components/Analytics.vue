<template>
    <div v-if="isLoading" class="p-4">
        <UProgress v-model="loading" />
    </div>
    <div v-else>
        <ChartTimelineStacked :report="report" />
        <ChartTimeline :report="report" />
    </div>
</template>

<script setup>
import { ref } from "vue";

const report = ref(null);
const isLoading = ref(false);
const error = ref(null);

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
fetchReport();

</script>
