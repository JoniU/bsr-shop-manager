import React, { useState } from "react";
import { ProductData } from "./global";

const RawProductDataTable: React.FC<{ rawData: ProductData[] }> = ({ rawData }) => {
    const [filterSKU, setFilterSKU] = useState<string>("");

    const handleFilterChange = (event: React.ChangeEvent<HTMLInputElement>) => {
        setFilterSKU(event.target.value);
    };

    const filteredData = filterSKU
        ? rawData.filter((item) => item.sku && item.sku.includes(filterSKU))
        : rawData;

    return (
        <div className="raw-data-table">
            <div className="filter-container">
                <label htmlFor="sku-filter">Filter by SKU:</label>
                <input
                    type="text"
                    id="sku-filter"
                    value={filterSKU}
                    onChange={handleFilterChange}
                    placeholder="Enter SKU"
                />
            </div>
            {filteredData.length > 0 ? (
                <table className="widefat fixed striped raw-data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Product ID</th>
                            <th>Parent ID</th>
                            <th>SKU</th>
                            <th>Name</th>
                            <th>Quantity</th>
                            <th>Subtotal (€)</th>
                            <th>Total (€)</th>
                            <th>Subtotal (Base Currency €)</th>
                            <th>Total (Base Currency €)</th>
                            <th>Order Date</th>
                            <th>Meta Data</th>
                        </tr>
                    </thead>
                    <tbody>
                        {filteredData.map((item, index) => (
                            <tr key={index}>
                                <td>{index + 1}</td>
                                <td>{item.product_id}</td>
                                <td>{item.parent_id}</td>
                                <td>{item.sku || "N/A"}</td>
                                <td>{item.name}</td>
                                <td>{item.quantity}</td>
                                <td>{item.subtotal.toFixed(2)}</td>
                                <td>{item.total.toFixed(2)}</td>
                                <td>{item.order_date}</td>
                                <td>
                                    <pre>{JSON.stringify(item.meta, null, 2)}</pre>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            ) : (
                <div>No matching data found.</div>
            )}
        </div>
    );
};

export default RawProductDataTable;
