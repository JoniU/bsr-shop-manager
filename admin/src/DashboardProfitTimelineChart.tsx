import React, { useEffect, useRef } from 'react';
import { Chart, BarElement, CategoryScale, LinearScale, Tooltip, Legend } from 'chart.js';

import { AggregatedDataTimeline } from './global';


Chart.register(BarElement, CategoryScale, LinearScale, Tooltip, Legend);

type ProfitTimelineChartProps = {
    aggregatedDataTimeline: AggregatedDataTimeline[];
};

const ProfitTimelineChart: React.FC<ProfitTimelineChartProps> = ({ aggregatedDataTimeline }) => {
    const chartRef = useRef<HTMLCanvasElement>(null);
    const chartInstanceRef = useRef<Chart | null>(null);

    useEffect(() => {
        if (chartInstanceRef.current) {
            chartInstanceRef.current.destroy();
        }

        if (chartRef.current) {
            const ctx = chartRef.current.getContext('2d');

            if (ctx) {
                const sortedData = [...aggregatedDataTimeline].sort(
                    (a, b) => new Date(a.month).getTime() - new Date(b.month).getTime()
                );

                const labels = sortedData.map((data) => data.month);
                const totalCogs = sortedData.map((data) => data.total_cogs);
                const totalPackingCost = sortedData.map((data) => data.total_packing_cost);
                const totalShipping = sortedData.map((data) => data.total_shipping);
                const discount = sortedData.map((data) => data.total_discount);
                const profit = sortedData.map((data) => data.profit);

                const chartData = {
                    labels,
                    datasets: [
                        {
                            label: 'Profit',
                            data: profit,
                            backgroundColor: 'rgba(153, 102, 255, 0.2)',
                            borderColor: 'rgba(153, 102, 255, 1)',
                            borderWidth: 1,
                            stack: 'expenses',
                        },
                        {
                            label: 'COGS',
                            data: totalCogs,
                            backgroundColor: 'rgba(255, 99, 132, 0.2)',
                            borderColor: 'rgba(255, 99, 132, 1)',
                            borderWidth: 1,
                            stack: 'expenses',
                        },
                        {
                            label: 'Packing Cost',
                            data: totalPackingCost,
                            backgroundColor: 'rgba(255, 206, 86, 0.2)',
                            borderColor: 'rgba(255, 206, 86, 1)',
                            borderWidth: 1,
                            stack: 'expenses',
                        },
                        {
                            label: 'Shipping',
                            data: totalShipping,
                            backgroundColor: 'rgba(54, 162, 235, 0.2)',
                            borderColor: 'rgba(54, 162, 235, 1)',
                            borderWidth: 1,
                            stack: 'expenses',
                        },
                        {
                            label: 'Discount',
                            data: discount,
                            backgroundColor: 'rgba(75, 192, 192, 0.2)',
                            borderColor: 'rgba(75, 192, 192, 1)',
                            borderWidth: 1,
                            stack: 'expenses',
                        },
                    ],
                };

                const chartOptions = {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'top' as const,
                        },
                    },
                    scales: {
                        x: {
                            stacked: true,
                        },
                        y: {
                            stacked: true,
                            beginAtZero: true,
                        },
                    },
                };

                chartInstanceRef.current = new Chart(ctx, {
                    type: 'bar',
                    data: chartData,
                    options: chartOptions,
                });
            }
        }
    }, [aggregatedDataTimeline]);

    return <canvas ref={chartRef} id="profitChart" />;
};

export default ProfitTimelineChart;
