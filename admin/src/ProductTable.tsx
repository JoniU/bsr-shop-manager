import React from "react";
import ProductRow from "./ProductRow";

import { 
    Product, 
} from './global';

const ProductTable = ({
    products,
    onSave,
}: {
    products: Product[];
    onSave: (id: number, updates: Product) => void;
}) => {
    return (
        <div className="table-container">
            <table className="bsr-cogs-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Product Type</th>
                        <th>SKU</th>
                        <th>Price (VAT 0%)</th>
                        <th>COGS (VAT 0%)</th>
                        <th>Packing Cost (VAT 0%)</th>
                        <th>In Stock</th>
                        <th>Work Time (Minutes)</th>
                        <th>Development Cost (€)</th>
                        <th>Development Months</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    {products.map((product, index) => (
                        <ProductRow
                            key={product.id}
                            index={index + 1}
                            product={product}
                            onSave={onSave} // Ensure the passed function matches the signature
                        />
                    ))}
                </tbody>
            </table>
        </div>
    );
};

export default ProductTable;
