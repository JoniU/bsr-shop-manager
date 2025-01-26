<template>
    <canvas id="turnoverStacked"></canvas>
</template>

<script setup lang="ts">
import { ref, watch } from "vue";
import { Chart } from "chart.js/auto";
import { getISOWeek } from "@/utils/dateUtils"; // Assuming the utility is moved to a separate file
import type { ReportData } from "@/types/timelineReport.ts"

const props = defineProps({
    report: {
        type: Object,
        required: true,
    },
    selectedGranularity: {
        type: String, // Expecting a string value for granularity
        required: true,
    },
});

const selectedGranularity = computed(() => props.selectedGranularity);
const report = computed(() => props.report);

const chartInstance = ref<Chart | null>(null);

// Watch for changes in granularity and update the chart
watch(
    [selectedGranularity, report],
    ([newGranularity, newReport]) => {
        renderChart(newGranularity, newReport); // Re-render chart on changes
    },
    { deep: true } // Watch deeply for nested changes in report
);

// Render the chart
function renderChart(granularity: string, newReport: ReportData) {
    const aggregatedData = aggregateData(newReport, granularity.toLowerCase());

    const datasets = [
        {
            label: "Profit",
            data: aggregatedData.map(
                (entry) =>
                    entry.total -
                    (entry.shipping +
                        entry.tax +
                        entry.shipping_tax +
                        entry.cogs_price +
                        entry.packing_cost +
                        entry.work_time_minutes +
                        entry.development_cost)
            ),
            backgroundColor: "rgba(5, 224, 114, 1)", // Green 500
            borderColor: "rgba(5, 224, 114, 0.4)", // Gray 500
            borderWidth: 3,
        },
        {
            label: "Cost of Goods",
            data: aggregatedData.map((entry) => entry.cogs_price),
            backgroundColor: "rgba(251, 44, 54, 0.4)", // Gray 500
            borderColor: "rgba(251, 44, 54, 0.8)", // Gray 500
            borderWidth: 3,

        },
        {
            label: "Work Cost",
            data: aggregatedData.map((entry) => entry.work_time_minutes),
            backgroundColor: "rgba(230, 0, 118, 0.4)", // Orange 500
            borderColor: "rgba(230, 0, 118, 1)", // Gray 500
            borderWidth: 3,
        },
        {
            label: "Packing Cost",
            data: aggregatedData.map((entry) => entry.packing_cost),
            backgroundColor: "rgba(68, 0, 183, 0.4)", // Orange 500
            borderColor: "rgba(68, 0, 183, 1)", // Gray 500
            borderWidth: 3,
        },
        {
            label: "Shipping",
            data: aggregatedData.map((entry) => entry.shipping),
            backgroundColor: "oklch(0.446 0.043 257.281)", // Blue 500
        },
        {
            label: "Shipping Tax",
            data: aggregatedData.map((entry) => entry.shipping_tax),
            backgroundColor: "oklch(0.372 0.044 257.287)", // Yellow 500
        },
        {
            label: "Tax",
            data: aggregatedData.map((entry) => entry.tax),
            backgroundColor: "oklch(0.279 0.041 260.031)", // Red 500
        },
    ];

    const ctx = document.getElementById("turnoverStacked") as HTMLCanvasElement;

    if (!ctx) {
        console.error("Failed to get canvas context.");
        return;
    }

    // Destroy the existing chart instance to avoid conflicts
    if (chartInstance.value) {
        chartInstance.value.destroy();
        chartInstance.value = null; // Explicitly set it to null
    }

    chartInstance.value = new Chart(ctx, {
        type: "bar",
        data: {
            labels: aggregatedData.map((entry) => entry.date),
            datasets,
        },
        options: {
            responsive: true,
            scales: {
                x: {
                    stacked: true,
                },
                y: {
                    stacked: true,
                    beginAtZero: true,
                },
            },
            plugins: {
                legend: {
                    labels: {
                        usePointStyle: true, // Use a point style for labels
                        pointStyle: "circle",
                    },
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

onMounted(() => renderChart(selectedGranularity.value, report.value));
</script>

<style scoped>
canvas {
    max-width: 100%;
    height: 400px;
}
</style>
