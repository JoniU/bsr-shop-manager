import { Order } from './global';

export interface AggregatedChartData {
    month: string;
    total_revenue: number;
    total_cogs: number;
    total_packing_cost: number;
    total_shipping: number;
    total_shipping_tax: number;
    total_discount: number;
    profit: number;
}

export const aggregateDataByMonth = (orders: Order[]): AggregatedChartData[] => {
    const aggregated = orders.reduce((acc, order) => {
        const month = order.date.slice(0, 7); // YYYY-MM format
        if (!acc[month]) {
            acc[month] = {
                month,
                total_revenue: 0,
                total_cogs: 0,
                total_packing_cost: 0,
                total_shipping: 0,
                total_shipping_tax: 0,
                total_discount: 0,
                profit: 0,
            };
        }
        acc[month].total_revenue += order.revenue;
        acc[month].total_cogs += order.cogs;
        acc[month].total_packing_cost += order.packing_cost;
        acc[month].total_shipping += order.shipping;
        acc[month].total_shipping_tax += order.shipping_tax;
        acc[month].total_discount += order.discount;
        acc[month].profit =
            acc[month].total_revenue -
            acc[month].total_cogs -
            acc[month].total_packing_cost -
            acc[month].total_shipping -
            acc[month].total_shipping_tax;

        return acc;
    }, {} as { [key: string]: AggregatedChartData });

    return Object.values(aggregated);
};
