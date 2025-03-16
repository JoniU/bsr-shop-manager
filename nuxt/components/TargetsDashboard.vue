<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import dayjs from 'dayjs';
import duration from 'dayjs/plugin/duration';
import isBetween from 'dayjs/plugin/isBetween';
import axios from 'axios';

dayjs.extend(duration);
dayjs.extend(isBetween);

type ProfitDataEntry = {
    total: number;
    cogs_price: number;
    packing_cost: number;
    tax: number;
    shipping: number;
    shipping_tax: number;
};

type ProfitDataEntryWithDate = ProfitDataEntry & { date: string };

// Reactive data
const apiUrl = `${useRuntimeConfig().public.baseUrl}/wp-json/custom/v1/order-live`;
const profitData = ref<ProfitDataEntryWithDate[]>([]);
const isLoading = ref(true);
const error = ref<string | null>(null);

// Fetch the profit data from the API
async function fetchProfitData() {
    isLoading.value = true;
    error.value = null;
    try {
        const data = await $fetch<Record<string, ProfitDataEntry>>(apiUrl);

        profitData.value = Object.entries(data).map(([date, metrics]) => ({
            date,
            ...metrics,
        }));
    } catch (err) {
        console.error(err);
        error.value = 'Failed to fetch the report.';
    } finally {
        isLoading.value = false;
    }
}

const config = useRuntimeConfig();
const monthlyTarget = ref<number>(0);

// Fetch settings from the WordPress API
async function fetchSettings() {
    isLoading.value = true;
    error.value = null;
    try {
        console.log(`Fetching settings from: ${config.public.baseUrl}/wp-json/custom/v1/bsr-shop-manager-settings`);
        const response = await axios.get(`${config.public.baseUrl}/wp-json/custom/v1/bsr-shop-manager-settings`);
        console.log('Settings fetched:', response.data);
        // Destructure fetched data and update reactive state
        const { monthlyTarget: savedMonthlyTarget } = response.data;
        // Ensure monthlyTarget is a number (if fetched as a string, convert it)
        monthlyTarget.value = Number(savedMonthlyTarget) || 0;
    } catch (err) {
        error.value = 'Failed to fetch settings.';
        console.error(err);
    } finally {
        isLoading.value = false;
    }
}

// Fetch settings when component mounts
onMounted(async () => {
    await fetchSettings();
});

// Compute target values based on monthlyTarget
const targetValues = computed(() => {
    const todayDate = new Date();
    const daysInMonth = new Date(todayDate.getFullYear(), todayDate.getMonth() + 1, 0).getDate();
    return {
        today: monthlyTarget.value / daysInMonth || 0,
        thisWeek: (monthlyTarget.value / daysInMonth) * 7 || 0,
        thisMonth: monthlyTarget.value || 0,
        last7Days: (monthlyTarget.value / daysInMonth) * 7 || 0,
        last30Days: monthlyTarget.value || 0,
    };
});

// Calculate profit
const calculateProfit = (entry: ProfitDataEntryWithDate) => {
    return entry.total - entry.cogs_price - entry.packing_cost - entry.tax - entry.shipping - entry.shipping_tax;
};

// Define time ranges for targets
const targets = computed(() => [
    {
        label: 'Today',
        calculated: parseFloat(calculateRangeProfit(dayjs().startOf('day'), dayjs().endOf('day')).toFixed(2)) || 0,
        target: targetValues.value.today || 0,
    },
    {
        label: 'This Week',
        calculated: parseFloat(calculateRangeProfit(dayjs().startOf('week'), dayjs().endOf('week')).toFixed(2)) || 0,
        target: targetValues.value.thisWeek || 0,
    },
    {
        label: 'This Month',
        calculated: parseFloat(calculateRangeProfit(dayjs().startOf('month'), dayjs().endOf('month')).toFixed(2)) || 0,
        target: targetValues.value.thisMonth || 0,
    },
    {
        label: 'Last 7 Days',
        calculated: parseFloat(calculateRangeProfit(dayjs().subtract(7, 'days'), dayjs()).toFixed(2)) || 0,
        target: targetValues.value.last7Days || 0,
    },
    {
        label: 'Last 30 Days',
        calculated: parseFloat(calculateRangeProfit(dayjs().subtract(30, 'days'), dayjs()).toFixed(2)) || 0,
        target: targetValues.value.last30Days || 0,
    },
]);

// Calculate total profit for a date range
const calculateRangeProfit = (startDate: dayjs.Dayjs, endDate: dayjs.Dayjs) => {
    const filteredEntries = profitData.value.filter((entry) => {
        const entryDate = dayjs(entry.date);
        return entryDate.isBetween(startDate, endDate, 'day', '[]');
    });
    console.log(`Filtered entries for ${startDate.format()} - ${endDate.format()}:`, filteredEntries);
    return filteredEntries.reduce((sum, entry) => sum + calculateProfit(entry), 0);
};

// Fetch the data on component mount
onMounted(fetchProfitData);
</script>

<template>
    <div class="p-2">
        <h2 class="text-md mb-2">Modified Profit Targets</h2>
        <div v-if="isLoading">Loading...</div>
        <div v-else-if="error" class="text-red-500">{{ error }}</div>
        <div v-else>
            <!-- Add grid layout -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-2 md:gap-4">
                <div v-for="(target, index) in targets" :key="index" class="p-1">
                    <div v-if="target?.target" class="flex justify-between items-center mb-2">
                        <span class="text-sm font-medium">{{ target.label }}</span>
                        <span class="text-gray-600">
                            {{ target.calculated.toFixed(2) }} / {{ target.target.toFixed(2) }}
                        </span>
                    </div>
                    <div class="w-full bg-gray-200 dark:bg-gray-600 rounded-sm h-4 overflow-hidden shadow-md">
                        <div
                            :style="{ width: Math.min((target.calculated / target.target) * 100, 100) + '%' }"
                            class="bg-green-500 h-4 transition-all duration-300 shadow-md"
                        ></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Add custom styles if needed */
</style>
