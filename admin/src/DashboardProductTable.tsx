import React, { useState, useEffect } from "react";
import {
    ProductData,
    AggregatedData,
    ProductDataTableProps,
} from "./global";

type SortDirection = "asc" | "desc";

// Utility function to initialize data map for aggregation
const initializeDataMap = (sku: string | null) => ({
    sku,
    revenue: 0,
    quantity: 0,
    cogs: 0,
    developmentCost: 0,
    packingCost: 0,
    shippingCost: 0,
    workCost: 0,
    totalCost: 0,
    profit: 0,
    stockLeft: 0,
    developmentCostAdded: false,
});

// Function to calculate work cost and total cost
const calculateCosts = (item: ProductData) => {
    const workCost = (item.meta?._work_time_minutes || 0) * 1.2; // Use optional chaining
    const totalCost =
        (item.meta?._cogs_price || 0) * item.quantity +
        (item.meta?._packing_cost || 0) +
        (item.meta?._development_cost || 0) +
        workCost;

    return { workCost, totalCost };
};


const aggregateIndividualData = (rawData: ProductData[]) => {
    if (!Array.isArray(rawData)) {
        console.error("Expected rawData to be an array, but received:", rawData);
        return {};
    }

    const dataMap: Record<string, ReturnType<typeof initializeDataMap>> = {};

    rawData.forEach((item) => {
        // Skip bundle sub-products as they will be handled by distributeBundleData
        if (item.bundle_parent_id && parseInt(item.bundle_parent_id) !== 0) {
            return;
        }

        if (!item.meta) {
            console.warn(`Item with product_id ${item.product_id} is missing meta data.`);
            return; // Skip items without meta
        }

        const parentId = item.parent_id || item.product_id;
        const sku = item.sku;

        if (!dataMap[parentId]) {
            dataMap[parentId] = initializeDataMap(sku);
        }

        const { workCost, totalCost } = calculateCosts(item);

        dataMap[parentId].revenue += item.total;
        dataMap[parentId].quantity += item.quantity;
        dataMap[parentId].cogs += (item.meta._cogs_price || 0) * item.quantity;
        dataMap[parentId].packingCost += item.meta._packing_cost || 0;
        dataMap[parentId].workCost += workCost;
        dataMap[parentId].totalCost += totalCost;

        if (!dataMap[parentId].developmentCostAdded) {
            dataMap[parentId].developmentCost += item.meta._development_cost || 0;
            dataMap[parentId].developmentCostAdded = true;
        }

        dataMap[parentId].profit += item.total - totalCost;
        dataMap[parentId].stockLeft = item.meta.stock_left || 0;
    });

    return dataMap;
};

// Distribute bundle revenue and quantities
const distributeBundleData = (
    rawData: ProductData[],
    dataMap: Record<string, ReturnType<typeof initializeDataMap>>
) => {
    // First, gather all parent bundle IDs
    const bundleParentIds = new Set(
        rawData
            .filter((item) => item.bundle_parent_id && parseInt(item.bundle_parent_id) !== 0)
            .map((item) => parseInt(item.bundle_parent_id!))
    );

    // Filter out parent bundles from the raw data
    const filteredRawData = rawData.filter(
        (item) => !bundleParentIds.has(item.product_id)
    );

    // Reset the data map based on filtered raw data
    filteredRawData.forEach((item) => {
        const parentId = item.parent_id || item.product_id;
        const sku = item.sku;

        if (!dataMap[parentId]) {
            dataMap[parentId] = initializeDataMap(sku);
        }

        dataMap[parentId].revenue += item.total;
        dataMap[parentId].quantity += item.quantity;
        dataMap[parentId].cogs += (item.meta?._cogs_price || 0) * item.quantity;
    });
};


// Aggregate data by parent SKU
const aggregateDataByParentSKU = (rawData: ProductData[]): AggregatedData[] => {
    const dataMap = aggregateIndividualData(rawData);
    //distributeBundleData(rawData, dataMap);
    return Object.values(dataMap);
};

const ProductDataTable: React.FC<{ ordersData: ProductData[] }> = ({ ordersData }) => {
    const [aggregatedData, setAggregatedData] = useState<AggregatedData[]>([]);
    const [sortedData, setSortedData] = useState<AggregatedData[]>([]);
    const [sortField, setSortField] = useState<keyof AggregatedData>("profit");
    const [sortDirection, setSortDirection] = useState<SortDirection>("desc");

    useEffect(() => {
        const aggregated = aggregateDataByParentSKU(ordersData);
        setAggregatedData(aggregated);
        sortData(aggregated, "profit", "desc"); // Default sort
    }, [ordersData]);

    const sortData = (
        data: AggregatedData[],
        field: keyof AggregatedData,
        direction: SortDirection
    ) => {
        const sorted = [...data].sort((a, b) => {
            const aValue = Number(a[field] ?? 0);
            const bValue = Number(b[field] ?? 0);
            return direction === "asc" ? aValue - bValue : bValue - aValue;
        });
        setSortedData(sorted);
    };

    const handleSort = (field: keyof AggregatedData) => {
        const newDirection = sortField === field && sortDirection === "asc" ? "desc" : "asc";
        setSortField(field);
        setSortDirection(newDirection);
        sortData(aggregatedData, field, newDirection);
    };

    return (
        <div className="product-data-table">
            {sortedData.length > 0 ? (
                <table className="widefat fixed striped sortable-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th onClick={() => handleSort("sku")}>SKU</th>
                            <th onClick={() => handleSort("quantity")}>Quantity Sold</th>
                            <th onClick={() => handleSort("revenue")}>Revenue (€)</th>
                            <th onClick={() => handleSort("cogs")}>COGS (€)</th>
                            <th onClick={() => handleSort("developmentCost")}>Development Cost (€)</th>
                            <th onClick={() => handleSort("packingCost")}>Packing Cost (€)</th>
                            <th onClick={() => handleSort("workCost")}>Work Cost (€)</th>
                            <th onClick={() => handleSort("totalCost")}>Total Cost (€)</th>
                            <th onClick={() => handleSort("profit")}>Profit (€)</th>
                            <th onClick={() => handleSort("stockLeft")}>Stock Left</th>
                        </tr>
                    </thead>
                    <tbody>
                        {sortedData.map((product, index) => (
                            <tr key={index}>
                                <td>{index + 1}</td>
                                <td>{product.sku || "N/A"}</td>
                                <td>{product.quantity}</td>
                                <td>{product.revenue.toFixed(2)}</td>
                                <td>{product.cogs.toFixed(2)}</td>
                                <td>{product.developmentCost.toFixed(2)}</td>
                                <td>{product.packingCost.toFixed(2)}</td>
                                <td>{product.workCost.toFixed(2)}</td>
                                <td>{product.totalCost.toFixed(2)}</td>
                                <td>{product.profit.toFixed(2)}</td>
                                <td>{product.stockLeft}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            ) : (
                <div>No data available.</div>
            )}
        </div>
    );
};

export default ProductDataTable;
