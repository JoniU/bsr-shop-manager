<template>
    <div v-if="isLoading" class="p-4">
        <UProgress v-model="loading" />
    </div>
    <div v-else>
        <div class="flex items-center justify-between p-4 rounded-lg shadow">
            <!-- Radio Group -->
            <div class="flex items-center gap-4">
                <URadioGroup orientation="horizontal" v-model="selectedGranularity" :items="items"
                    class="flex items-center gap-2" />
            </div>

            <!-- Date Range Picker -->
            <div>
                <div>
                    <UPopover v-model:open="open">
                        <UButton color="neutral" variant="subtle" icon="i-lucide-calendar">
                            <template v-if="dateRange.start">
                                <template v-if="dateRange.end">
                                    {{ df.format(dateRange.start.toDate(getLocalTimeZone())) }} - {{
                                        df.format(dateRange.end.toDate(getLocalTimeZone())) }}
                                </template>

                                <template v-else>
                                    {{ df.format(dateRange.start.toDate(getLocalTimeZone())) }}
                                </template>
                            </template>
                            <template v-else>
                                Pick a date
                            </template>
                        </UButton>

                        <template #content>
                            <UCalendar v-model="dateRange" :max-value="maxCalendar" :min-value="minCalendar" class="p-2"
                                :number-of-months="3" size="sm" range />
                        </template>
                    </UPopover>
                </div>

            </div>
        </div>
        <div v-if="filteredReport">
            <div class="m-2 p-2 rounded-md shadow-md bg-elevated border-1 border-gray-700 dark:bg-gray-800">
                <ChartTimelineStacked :report="filteredReport" :selectedGranularity="selectedGranularity" />
            </div>
            <div class="m-2 p-2 rounded-md shadow-md bg-elevated border-1 border-gray-700 dark:bg-gray-800">
                <ChartTimeline :report="filteredReport" :selectedGranularity="selectedGranularity" />
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref } from "vue";
import { CalendarDate, today, DateFormatter, getLocalTimeZone } from '@internationalized/date'
import dayjs from 'dayjs';
import duration from 'dayjs/plugin/duration';
import isBetween from 'dayjs/plugin/isBetween';

dayjs.extend(duration);
dayjs.extend(isBetween);

const rawReport = ref(null); // Store the fetched data
const isLoading = ref(false);
const error = ref(null);
const loading = ref(null);

const items = ref(["Day", "Week", "Month", "Year"]);
const selectedGranularity = ref("Month"); // Default granularity

const df = new DateFormatter('fi-FI', {
    dateStyle: 'medium'
})

// Calculate the start date (12 months ago) using @internationalized/date
const now = today("UTC"); // Current date in UTC
const twelveMonthsAgo = now.subtract({ months: 12 });
const maxCalendar = today("UTC");
const minCalendar = new CalendarDate(2014, 1, 1)

// Reactive date range object
const dateRange = ref({
    start: twelveMonthsAgo,
    end: now
});

const open = ref(false);

// Define shortcuts
defineShortcuts({
    o: () => (open.value = !open.value),
});

watch(
    dateRange,
    () => {
        const { start, end } = dateRange.value;
        // Ensure start and end dates exist
        if (start && end) {
            open.value = false; // Close the popover

            // Calculate the difference in days using Day.js
            const durationInDays = dayjs(end).diff(dayjs(start), 'day');

            // If the range exceeds 500 days and granularity is "Day", adjust to "Week"
            if (durationInDays > 500 && selectedGranularity.value === "Day") {
                selectedGranularity.value = "Week";
            }
        }
    },
    { deep: true } // Watch deeply for changes in the nested dateRange object
);

const apiUrl = `${useRuntimeConfig().public.baseUrl}/wp-json/custom/v1/profit-time`;

// Fetch data
async function fetchReport() {
    isLoading.value = true;
    error.value = null;
    try {
        console.log(apiUrl);
        const data = await $fetch(apiUrl);

        // Store the raw data for filtering
        rawReport.value = data;
    } catch (err) {
        console.error(err);
        error.value = "Failed to fetch the report.";
    } finally {
        isLoading.value = false;
    }
}

// Computed property to filter the report based on date range
const filteredReport = computed(() => {
    if (!rawReport.value || !dateRange.value.start || !dateRange.value.end) {
        return rawReport.value || {}; // Return all data if no date range is set
    }

    const { start, end } = dateRange.value;

    // Filter the data based on the date range
    return Object.entries(rawReport.value)
        .filter(([date]) => {
            const itemDate = dayjs(date);
            return itemDate.isBetween(dayjs(start), dayjs(end), 'day', '[]');
        })
        .reduce((result, [date, values]) => {
            result[date] = values;
            return result;
        }, {});
});

fetchReport();
</script>
