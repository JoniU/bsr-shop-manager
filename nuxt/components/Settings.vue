<template>
    <div class="w-full space-y-8">
        <!-- Costs Table -->
        <div>
            <h3 class="text-xl font-semibold m-2 mt-4">Monthly target</h3>
            <UInput v-model="monthlyTarget" />
        </div>
        <div>
            <h3 class="text-xl font-semibold m-2 mt-4">General fixed Costs</h3>
            <div class="overflow-x-auto">
                <table class="border-collapse w-full">
                    <thead class="bg-gray-100 dark:bg-gray-800">
                        <tr>
                            <th :class="classTh" class="text-center">Year</th>
                            <th v-for="month in months" :key="month" :class="classTh" class="text-right">
                                {{ month }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="year in years"
                            :key="year"
                            class="odd:bg-gray-50 even:bg-gray-100 dark:odd:bg-gray-800 dark:even:bg-gray-900"
                        >
                            <td :class="classTd" class="text-center">{{ year }}</td>
                            <td v-for="(value, index) in costs[year]" :key="index" :class="classTd" class="text-right">
                                <input
                                    type="number"
                                    v-model.number="costs[year][index]"
                                    class="w-full bg-transparent text-right border-none focus:outline-none"
                                />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Marketing Costs Table -->
        <div>
            <h3 class="text-xl font-semibold m-2 mt-4">Marketing Costs</h3>
            <div class="overflow-x-auto">
                <table class="table-auto border-collapse border border-gray-300 dark:border-gray-700 w-full">
                    <thead class="bg-gray-100 dark:bg-gray-800">
                        <tr>
                            <th :class="classTh" class="text-center">Year</th>
                            <th v-for="month in months" :key="month" :class="classTh" class="text-right">
                                {{ month }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="year in years"
                            :key="year"
                            class="odd:bg-gray-50 even:bg-gray-100 dark:odd:bg-gray-800 dark:even:bg-gray-900"
                        >
                            <td :class="classTd" class="text-center">{{ year }}</td>
                            <td
                                v-for="(value, index) in marketingCosts[year]"
                                :key="index"
                                :class="classTd"
                                class="text-right"
                            >
                                <input
                                    type="number"
                                    v-model.number="marketingCosts[year][index]"
                                    class="w-full bg-transparent text-right border-none focus:outline-none"
                                />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Rent Table -->
        <div>
            <h3 class="text-xl font-semibold m-2 mt-4">Rent</h3>
            <div class="overflow-x-auto">
                <table class="table-auto border-collapse border border-gray-300 dark:border-gray-700 w-full">
                    <thead class="bg-gray-100 dark:bg-gray-800">
                        <tr>
                            <th :class="classTh" class="text-left">Year</th>
                            <th v-for="month in months" :key="month" :class="classTh" class="text-right">
                                {{ month }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="year in years"
                            :key="year"
                            class="odd:bg-gray-50 even:bg-gray-100 dark:odd:bg-gray-800 dark:even:bg-gray-900"
                        >
                            <td :class="classTd" class="text-center">{{ year }}</td>
                            <td v-for="(value, index) in rent[year]" :key="index" :class="classTd" class="text-right">
                                <input
                                    type="number"
                                    v-model.number="rent[year][index]"
                                    class="w-full bg-transparent text-right border-none focus:outline-none"
                                />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="p-4">
        <UButton @click="saveSettings" color="success" variant="solid" :disabled="loading"> Save Settings </UButton>

        <p v-if="error" class="text-red-500 mt-2">{{ error }}</p>
    </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue';
import axios from 'axios';

// Generate years and months dynamically
const currentYear = new Date().getFullYear();
const years = ref<number[]>(Array.from({ length: currentYear - 2020 + 1 }, (_, i) => 2020 + i));
const months = ref<string[]>(['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']);
const classTd = 'border border-gray-300 dark:border-gray-700 px0 py-2 text-sm';
const classTh = 'border-b border-gray-300 dark:border-gray-700 px-4 py-2';

// Initialize data for costs, marketing-cost, and rent
const costs = ref<Record<number, number[]>>(generateEmptyData());
const marketingCosts = ref<Record<number, number[]>>(generateEmptyData());
const rent = ref<Record<number, number[]>>(generateEmptyData());
const monthlyTarget = ref<number>(0);

// Function to generate an empty data structure for the tables
function generateEmptyData() {
    const data: Record<number, number[]> = {};
    years.value.forEach((year) => {
        data[year] = Array(12).fill(0); // 12 months with default value of 0
    });
    return data;
}

// Function to update the value in a specific table
const updateValue = (table: Record<number, number[]>, year: number, monthIndex: number, value: number) => {
    table[year][monthIndex] = value;
};
const config = useRuntimeConfig();
const apiUrl = `${config.public.baseUrl}/wp-json/custom/v1/bsr-shop-manager-settings`;
const loading = ref(false);
const error = ref<string | null>(null);

// Fetch data from the WordPress API
async function fetchSettings() {
    loading.value = true;
    error.value = null;

    try {
        console.log(`Fetching settings from: ${apiUrl}`);
        const response = await axios.get(apiUrl);
        console.log('Settings fetched:', response.data);
        const {
            costs: savedCosts,
            marketingCosts: savedMarketingCosts,
            rent: savedRent,
            monthlyTarget: savedMonthlyTarget,
        } = response.data;

        // Update the local state with fetched data
        costs.value = savedCosts || generateEmptyData();
        marketingCosts.value = savedMarketingCosts || generateEmptyData();
        rent.value = savedRent || generateEmptyData();
        monthlyTarget.value = savedMonthlyTarget || 0;
    } catch (err) {
        error.value = 'Failed to fetch settings.';
        console.error(err);
    } finally {
        loading.value = false;
    }
}

// Save data to the WordPress API
async function saveSettings() {
    loading.value = true;
    error.value = null;

    const data = {
        costs: costs.value,
        marketingCosts: marketingCosts.value,
        rent: rent.value,
        monthlyTarget: monthlyTarget.value,
    };

    try {
        await axios.post(apiUrl, data);
    } catch (err) {
        error.value = 'Failed to save settings.';
        console.error(err);
    } finally {
        loading.value = false;
    }
}

// Initialize data on component mount
onMounted(fetchSettings);
</script>
