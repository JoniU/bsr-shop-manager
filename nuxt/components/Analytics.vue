<template>
    <div class="m-2 p-2 rounded-md shadow-md bg-elevated border-1 border-gray-700 dark:bg-gray-800">
        <TargetsDashboard />
    </div>
    <div v-if="isLoading" class="p-4">
        <UProgress v-model="loading" />
    </div>
    <div v-else>
        <div class="flex items-center justify-between p-4 rounded-lg shadow">
            <!-- Radio Group -->
            <div class="flex items-center gap-4">
                <URadioGroup
                    v-model="selectedGranularity"
                    orientation="horizontal"
                    :items="items"
                    class="flex items-center gap-2"
                />
            </div>
            <div>
                <USelect
                    v-model="selectedDatePreset"
                    variant="subtle"
                    :items="datePresetOptions"
                    label-prop="label"
                    :placeholder="selectedDatePreset.label"
                    @update:model-value="applyPreset"
                />
            </div>
            <!-- Date Range Picker -->
            <div>
                <div>
                    <UPopover v-model:open="open">
                        <UButton color="neutral" variant="subtle" icon="i-lucide-calendar">
                            <template v-if="dateRange.start">
                                <template v-if="dateRange.end">
                                    {{ df.format(dateRange.start.toDate(getLocalTimeZone())) }} -
                                    {{ df.format(dateRange.end.toDate(getLocalTimeZone())) }}
                                </template>

                                <template v-else>
                                    {{ df.format(dateRange.start.toDate(getLocalTimeZone())) }}
                                </template>
                            </template>
                            <template v-else> Pick a date </template>
                        </UButton>

                        <template #content>
                            <UCalendar
                                v-model="dateRange"
                                :max-value="maxCalendar"
                                :min-value="minCalendar"
                                class="p-2"
                                :number-of-months="3"
                                size="sm"
                                range
                            />
                        </template>
                    </UPopover>
                </div>
            </div>
        </div>
        <div v-if="filteredReport">
            <div class="m-2 p-2 rounded-md shadow-md bg-elevated border-1 border-gray-700 dark:bg-gray-800">
                <ChartTimelineStacked :report="filteredReport" :selected-granularity="selectedGranularity" />
            </div>
            <div class="m-2 p-2 rounded-md shadow-md bg-elevated border-1 border-gray-700 dark:bg-gray-800">
                <ChartTimeline :report="filteredReport" :selected-granularity="selectedGranularity" />
            </div>
        </div>
    </div>
</template>

<script setup>
import { useRuntimeConfig } from '#app';
import { CalendarDate, today, DateFormatter, getLocalTimeZone } from '@internationalized/date';
import dayjs from 'dayjs';
import duration from 'dayjs/plugin/duration';
import isBetween from 'dayjs/plugin/isBetween';

dayjs.extend(duration);
dayjs.extend(isBetween);

const rawReport = ref(null); // Store the fetched data
const isLoading = ref(false);
const error = ref(null);
const loading = ref(null);

const items = ref(['Day', 'Week', 'Month', 'Year']);
const selectedGranularity = ref('Month'); // Default granularity

const df = new DateFormatter('fi-FI', {
    dateStyle: 'medium',
});

// Preset definitions
const datePresetOptions = [
    { label: 'Last 7 Days', value: 'last7' },
    { label: 'Last 30 Days', value: 'last30' },
    { label: 'Last 90 Days', value: 'last90' },
    { label: 'This Week', value: 'thisWeek' },
    { label: 'This Month', value: 'thisMonth' },
    { label: 'This Year', value: 'thisYear' },
];
const selectedDatePreset = ref({ label: 'Last 30 Days', value: 'last30' }); // Default preset

// Calculate the start date (12 months ago) using @internationalized/date
const now = today('UTC'); // Current date in UTC
const twelveMonthsAgo = now.subtract({ months: 12 });
const maxCalendar = today('UTC');
const minCalendar = new CalendarDate(2014, 1, 1);

// Reactive date range object
const dateRange = ref({
    start: twelveMonthsAgo,
    end: now,
});

const open = ref(false);

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
            if (durationInDays > 500 && selectedGranularity.value === 'Day') {
                selectedGranularity.value = 'Week';
            }
        }
    },
    { deep: true }, // Watch deeply for changes in the nested dateRange object
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
        error.value = 'Failed to fetch the report.';
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

function applyPreset() {
    const now = today('UTC');
    let start;
    let end;

    switch (selectedDatePreset.value) {
        case 'last7':
            start = today('UTC').subtract({ days: 7 });
            end = today('UTC');
            selectedGranularity.value = 'Day';
            break;
        case 'last30':
            start = today('UTC').subtract({ days: 30 });
            end = today('UTC');
            selectedGranularity.value = 'Day';
            break;
        case 'last90':
            start = today('UTC').subtract({ days: 90 });
            end = today('UTC');
            selectedGranularity.value = 'Day';
            break;
        case 'thisWeek': {
            // Calculate Monday and Sunday using JavaScript Date, then convert to CalendarDate.
            const currentDate = new Date();
            let day = currentDate.getDay(); // Sunday = 0, Monday = 1, etc.
            if (day === 0) day = 7; // Treat Sunday as day 7
            const monday = new Date(currentDate);
            monday.setDate(currentDate.getDate() - day + 1);
            monday.setHours(0, 0, 0, 0);
            const sunday = new Date(monday);
            sunday.setDate(monday.getDate() + 6);
            sunday.setHours(23, 59, 59, 999);
            start = new CalendarDate(monday.getFullYear(), monday.getMonth() + 1, monday.getDate());
            end = new CalendarDate(sunday.getFullYear(), sunday.getMonth() + 1, sunday.getDate());
            selectedGranularity.value = 'Day';
            break;
        }
        case 'thisMonth': {
            // For current month, start on the 1st and end on the last day.
            start = new CalendarDate(now.year, now.month, 1);
            const lastDay = new Date(
                now.toDate(getLocalTimeZone()).getFullYear(),
                now.toDate(getLocalTimeZone()).getMonth() + 1,
                0,
            ).getDate();
            end = new CalendarDate(now.year, now.month, lastDay);
            selectedGranularity.value = 'Day';
            break;
        }
        case 'thisYear':
            start = new CalendarDate(now.year, 1, 1);
            end = new CalendarDate(now.year, 12, 31);
            selectedGranularity.value = 'Week';
            break;
        default:
            return; // Do nothing if no preset selected (Custom Range)
    }
    dateRange.value = { start: start, end: end };
}

fetchReport();
</script>
