<template>
    <div class="space-y-4 p-4">
        <URadioGroup orientation="horizontal" v-model="selectedGranularity" :items="items" />
        <canvas id="stackedChart"></canvas>
    </div>
</template>

<script setup lang="ts">
import { ref, watch } from "vue";
import { Chart } from "chart.js/auto";
import { getISOWeek } from "@/utils/dateUtils"; // Assuming the utility is moved to a separate file

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


const props = defineProps({
    report: {
        type: Object,
        required: true,
    },
});

const items = ref(["Day", "Week", "Month", "Year"]);
const selectedGranularity = ref("Month");

const chartInstance = ref<Chart | null>(null);

// Watch for changes in granularity and update the chart
watch(selectedGranularity, (newGranularity) => {
    renderChart(newGranularity);
});

// Render the chart
function renderChart(granularity: string) {
    const aggregatedData = aggregateData(props.report, granularity.toLowerCase());

    const datasets = [
        {
            label: "Shipping",
            data: aggregatedData.map((entry) => entry.shipping),
            backgroundColor: "#3B82F6", // Blue 500
        },
        {
            label: "Tax",
            data: aggregatedData.map((entry) => entry.tax),
            backgroundColor: "oklch(0.704 0.191 22.216)", // Red 500
        },
        {
            label: "Shipping Tax",
            data: aggregatedData.map((entry) => entry.shipping_tax),
            backgroundColor: "oklch(0.852 0.199 91.936)", // Yellow 500
        },
        {
            label: "COGS (Cost of Goods Sold)",
            data: aggregatedData.map((entry) => entry.cogs_price),
            backgroundColor: "oklch(0.707 0.165 254.624)", // Gray 500
        },
        {
            label: "Packing Cost",
            data: aggregatedData.map((entry) => entry.packing_cost),
            backgroundColor: "oklch(0.905 0.182 98.111)", // Indigo 500
        },
        {
            label: "Work Cost",
            data: aggregatedData.map((entry) => entry.work_time_minutes),
            backgroundColor: "oklch(0.681 0.162 75.834)", // Orange 500
        },
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
            backgroundColor: "oklch(0.792 0.209 151.711)", // Green 500
        },
    ];

    if (chartInstance.value) {
        chartInstance.value.destroy();
    }

    const ctx = document.getElementById("stackedChart") as HTMLCanvasElement;
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

<style scoped>
canvas {
    max-width: 100%;
    height: 400px;
}
</style>
