import React, { useEffect, useRef } from "react";
import { Chart, ChartOptions } from "chart.js/auto";

interface DoughnutData {
    total_cogs: number;
    total_packing_cost: number;
    total_shipping: number;
    total_discount: number;
    profit: number;
}

interface ProfitDoughnutChartProps {
    aggregatedData: DoughnutData[];
}

const ProfitDoughnutChart: React.FC<ProfitDoughnutChartProps> = ({ aggregatedData }) => {
    const chartRef = useRef<HTMLCanvasElement | null>(null);
    const chartInstanceRef = useRef<Chart<"doughnut", number[], string> | null>(null);

    useEffect(() => {
        if (!aggregatedData.length || !chartRef.current) return;

        // Destroy previous chart instance if it exists
        if (chartInstanceRef.current) {
            chartInstanceRef.current.destroy();
        }

        const ctx = chartRef.current.getContext("2d");
        if (!ctx) return;

        // Aggregate data for doughnut chart
        const totalCogs = aggregatedData.reduce((sum, data) => sum + data.total_cogs, 0);
        const totalPackingCost = aggregatedData.reduce((sum, data) => sum + data.total_packing_cost, 0);
        const totalShipping = aggregatedData.reduce((sum, data) => sum + data.total_shipping, 0);
        const discount = aggregatedData.reduce((sum, data) => sum + data.total_discount, 0);
        const profit = aggregatedData.reduce((sum, data) => sum + data.profit, 0);

        // Chart data
        const chartData = {
            labels: ["COGS", "Packing Cost", "Shipping", "Discount", "Profit"],
            datasets: [
                {
                    data: [totalCogs, totalPackingCost, totalShipping, discount, profit],
                    backgroundColor: [
                        "rgba(255, 99, 132, 0.2)", // COGS
                        "rgba(255, 206, 86, 0.2)", // Packing Cost
                        "rgba(54, 162, 235, 0.2)", // Shipping
                        "rgba(75, 192, 192, 0.2)", // Discount
                        "rgba(153, 102, 255, 0.2)", // Profit
                    ],
                    borderColor: [
                        "rgba(255, 99, 132, 1)", // COGS
                        "rgba(255, 206, 86, 1)", // Packing Cost
                        "rgba(54, 162, 235, 1)", // Shipping
                        "rgba(75, 192, 192, 1)", // Discount
                        "rgba(153, 102, 255, 1)", // Profit
                    ],
                    borderWidth: 1,
                },
            ],
        };

        // Chart options
        const chartOptions: ChartOptions<"doughnut"> = {
            plugins: {
                legend: {
                    position: "top",
                },
            },
            responsive: true,
        };

        // Create the chart instance
        chartInstanceRef.current = new Chart(ctx, {
            type: "doughnut",
            data: chartData,
            options: chartOptions,
        });
    }, [aggregatedData]);

    return <canvas ref={chartRef} id="profitDoughnutChart" width="200" height="200"></canvas>;
};

export default ProfitDoughnutChart;
