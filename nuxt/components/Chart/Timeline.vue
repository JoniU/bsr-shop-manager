<script setup lang="ts">
import { onMounted, ref, watch } from "vue";
import { Chart } from "chart.js/auto";
import { getISOWeek } from "@/utils/dateUtils";

import type { ChartType } from "chart.js/auto";

const props = defineProps({
    report: {
        type: Object as () => ReportData, // Correctly define the prop as an object
        required: true,
    },
});

// Define the expected structure of the report data
interface ReportData {
    [key: string]: {
        total: number;
        discount: number;
        shipping: number;
        tax: number;
        shipping_tax: number;
        quantity: number;
        cogs_price: number;
        packing_cost: number;
        work_time_minutes: number;
        development_cost: number;
        development_months: number;
    };
}

// Granularity options
const items = ref(["Day", "Week", "Month", "Year"]);
const selectedGranularity = ref("Month"); // Default granularity

const apiUrl = `${useRuntimeConfig().public.baseUrl}/wp-json/custom/v1/profit-time`;
const chartInstance = ref<Chart | null>(null);

// Fetch and render chart
async function renderChart(granularity: string) {
    const reportData = props.report as ReportData;

    // Aggregate data based on granularity
    const aggregatedData = aggregateData(reportData, granularity.toLowerCase());

    // Prepare the data for the chart
    const labels = aggregatedData.map((entry) => entry.date);
    const totals = aggregatedData.map((entry) => entry.total);

    // Destroy the previous chart instance if it exists
    if (chartInstance.value) {
        chartInstance.value.destroy();
    }

    // Create the chart
    const ctx = document.getElementById("orderTotalsChart") as HTMLCanvasElement;
    chartInstance.value = new Chart(ctx, {
        type: "bar" as ChartType,
        data: {
            labels,
            datasets: [
                {
                    label: "Total Sales",
                    data: totals,
                    backgroundColor: "rgba(75, 192, 192, 0.2)",
                    borderColor: "rgba(75, 192, 192, 1)",
                    borderWidth: 1,
                },
            ],
        },
        options: {
            responsive: true,
            scales: {
                x: {
                    title: {
                        display: true,
                        text: "Date",
                    },
                },
                y: {
                    title: {
                        display: true,
                        text: "Total",
                    },
                    beginAtZero: true,
                },
            },
        },
    });
}

// Aggregate data based on granularity
function aggregateData(reportData: ReportData, granularity: string) {
    const aggregatedData: { [key: string]: any } = {};

    Object.entries(reportData).forEach(([date, values]) => {
        const jsDate = new Date(date);
        let key: string = "";

        if (granularity === "day") {
            key = date;
        } else if (granularity === "week") {
            const year = jsDate.getFullYear();
            const week = getISOWeek(jsDate);
            key = `${year}-W${String(week).padStart(2, "0")}`;
        } else if (granularity === "month") {
            const year = jsDate.getFullYear();
            const month = String(jsDate.getMonth() + 1).padStart(2, "0");
            key = `${year}-${month}`;
        } else if (granularity === "year") {
            key = `${jsDate.getFullYear()}`; // Year format
        }

        if (!aggregatedData[key]) {
            aggregatedData[key] = {
                total: 0,
                discount: 0,
                shipping: 0,
                tax: 0,
                shipping_tax: 0,
                quantity: 0,
                cogs_price: 0,
                packing_cost: 0,
                work_time_minutes: 0,
                development_cost: 0,
                development_months: 0,
            };
        }

        // Aggregate all fields
        Object.keys(aggregatedData[key]).forEach((field) => {
            aggregatedData[key][field] += values[field as keyof typeof values] || 0;
        });
    });

    // Convert the aggregated data to an array and sort by date
    return Object.entries(aggregatedData)
        .map(([key, values]) => ({
            date: key,
            ...values,
        }))
        .sort((a, b) => {
            const parseDate = (date: string) => {
                if (date.includes("-W")) {
                    // Parse ISO week format (e.g., 2025-W01)
                    const [year, week] = date.split("-W").map(Number);
                    const firstDayOfYear = new Date(Date.UTC(year, 0, 1));
                    const dayOffset = (week - 1) * 7;
                    const dayOfWeek = firstDayOfYear.getUTCDay();
                    const firstWeekStart = firstDayOfYear;
                    if (dayOfWeek <= 4) {
                        firstWeekStart.setUTCDate(firstDayOfYear.getUTCDate() - dayOfWeek + 1);
                    } else {
                        firstWeekStart.setUTCDate(firstDayOfYear.getUTCDate() + (8 - dayOfWeek));
                    }
                    return new Date(firstWeekStart.getTime() + dayOffset * 86400000); // Add days to the first week
                }
                // Parse other date formats (day, month, year)
                return new Date(date);
            };

            const dateA = parseDate(a.date);
            const dateB = parseDate(b.date);

            return dateA.getTime() - dateB.getTime(); // Sort ascending
        });

}

// Watch for changes in granularity and update the chart
watch(selectedGranularity, (newGranularity) => {
    renderChart(newGranularity);
});

onMounted(() => renderChart(selectedGranularity.value));

</script>

<template>
    <div class="space-y-4 p-4">
        <!-- Radio Group for Granularity -->
        <URadioGroup orientation="horizontal" v-model="selectedGranularity" :items="items" />

        <!-- Chart Container -->
        <canvas id="orderTotalsChart"></canvas>
    </div>
</template>

<style scoped>
canvas {
    max-width: 100%;
    height: 400px;
}
</style>
