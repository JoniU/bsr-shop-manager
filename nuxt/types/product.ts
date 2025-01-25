export interface Product {
    id: ProductId;
    name: string;
    sku: string;
    price: number;
    regular_price: number;
    sale_price?: number | null;
    stock_quantity: number;
    manage_stock: boolean;
    type: 'Simple' | 'Variable' | 'Woosb';
    variations?: Product[];
    parentId?: number;
    meta_data?: MetaData; // Simplified meta_data structure
    attributes?: {
        name: string;
        option: string;
    }[];
}

export interface MetaData {
    _cogs_price: number;
    _packing_cost: number;
    _work_time_minutes: number;
    _development_cost: number;
    _development_months: number;
}

export interface ProductSave {
    id: ProductId;
    name: string;
    sku: string;
    regular_price: string;
    sale_price?: string | null;
    stock_quantity: string | null;
    manage_stock: boolean;
    type: 'Simple' | 'Variable' | 'Woosb';
    parentId?: number;
    meta_data?: MetaDataSave; // Correctly matches the transformation
    attributes?: {
        name: string;
        option: string;
    }[];
}

export interface MetaDataSaveItem {
    key: string;
    value: number;
}

export type MetaDataSave = MetaDataSaveItem[];

export type ProductId = number;
