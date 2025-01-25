import React, { useEffect, useState } from 'react';
import axios from 'axios';
import qs from 'qs';

import { Order, ProductData, AggregatedDataTimeline } from './global';
import { fetchData } from './dataFetchUtils'; // Import the utility
import { aggregateDataByMonth, AggregatedChartData } from './aggregationUtils';

import ProfitTimelineChart from './DashboardProfitTimelineChart';
import ProfitDoughnutChart from './DashboardProfitDoughnutChart';
import ProductRevenueTable from './DashboardProductTable';

axios.defaults.headers.post['Content-Type'] = 'application/x-www-form-urlencoded';
axios.defaults.transformRequest = [(data) => qs.stringify(data)];

const Dashboard: React.FC = () => {
    const apiUrl = shopManagerData.ajax_url;
    const nonce = shopManagerData.nonce;

    const [ordersData, setOrdersData] = useState<ProductData[]>([]);
    const [aggregatedChartData, setAggregatedChartData] = useState<AggregatedDataTimeline[]>([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    
    useEffect(() => {
        const fetchAndSetData = async () => {
            try {
                const resolvedData = await fetchData<ProductData[]>({
                    apiUrl: shopManagerData.ajax_url,
                    action: "shop_manager_fetch_product_data",
                    security: shopManagerData.nonce,
                });

                if (resolvedData) {
                    setOrdersData(resolvedData); // Set resolved data
                }
            } catch (error) {
                console.error("Failed to fetch orders data:", error);
            }
        };
        fetchAndSetData();
    }, []);

    const fetchOrders = async (startDate: string, endDate: string) => {
        let currentPage = 1;
        let allOrders: Order[] = [];
        let totalPages = 1;

        setLoading(true);
        setError(null);

        try {
            do {
                const response = await axios.post(apiUrl, {
                    action: 'shop_manager_fetch_orders_data',
                    security: nonce,
                    paged: currentPage,
                    start_date: startDate,
                    end_date: endDate,
                });

                if (response.data.success) {
                    const { orders, max_pages } = response.data.data;

                    allOrders = [...allOrders, ...orders];
                    totalPages = max_pages;
                    currentPage++;
                } else {
                    throw new Error('Failed to load orders.');
                }
            } while (currentPage <= totalPages);

            setAggregatedChartData(aggregateDataByMonth(allOrders));
        } catch (err: any) {
            setError(err.message || 'An error occurred.');
        } finally {
            setLoading(false);
        }
    };

    const getDefaultDates = () => {
        const today = new Date();
        const fourMonthsAgo = new Date();
        fourMonthsAgo.setMonth(today.getMonth() - 4);
        return {
            startDate: fourMonthsAgo.toISOString().split('T')[0],
            endDate: today.toISOString().split('T')[0],
        };
    };

    const { startDate: defaultStartDate, endDate: defaultEndDate } = getDefaultDates();
    const [startDate, setStartDate] = useState(defaultStartDate);
    const [endDate, setEndDate] = useState(defaultEndDate);

    useEffect(() => {
        fetchOrders(startDate, endDate);
    }, [startDate, endDate]);

    const handleFilterApply = () => {
        const startInput = (document.getElementById('start-date') as HTMLInputElement)?.value;
        const endInput = (document.getElementById('end-date') as HTMLInputElement)?.value;

        if (startInput && endInput) {
            setStartDate(startInput);
            setEndDate(endInput);
        }
    };

    if (loading) return <p>Loading...</p>;
    if (error) return <p>Error: {error}</p>;

    return (
        <div className="wrap">
            <div className="postbox">
                <div className="inside">
                    <div id="date-controls" style={{ display: 'flex', alignItems: 'center', gap: '10px' }}>
                        <label htmlFor="start-date">Start Date: </label>
                        <input type="date" id="start-date" defaultValue={startDate} />
                        <label htmlFor="end-date">End Date: </label>
                        <input type="date" id="end-date" defaultValue={endDate} />
                        <button onClick={handleFilterApply} className="button-primary">
                            Apply
                        </button>
                    </div>
                </div>
            </div>
            <div className="dashboard-grid">
                <div className="postbox chart-timeline">
                    <h2 className="hndle"><span>Monthly Profit Timeline</span></h2>
                    <div className="inside">
                        <ProfitTimelineChart aggregatedDataTimeline={aggregatedChartData} />
                    </div>
                </div>
                <div className="postbox chart-doughnut">
                    <h2 className="hndle"><span>Profit Doughnut</span></h2>
                    <div className="inside">
                        <ProfitDoughnutChart aggregatedData={aggregatedChartData} />
                    </div>
                </div>
                <div className="postbox product-table">
                    <h2 className="hndle"><span>Product Table</span></h2>
                    <div className="inside">
                        <ProductRevenueTable ordersData={ordersData} />
                    </div>
                </div>
            </div>
        </div>
    );
};

export default Dashboard;
