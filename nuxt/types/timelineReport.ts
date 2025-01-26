export interface ReportData {
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
