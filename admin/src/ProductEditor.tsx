import React, { useEffect, useState } from "react";
import axios from "axios";
import ProductTable from "./ProductTable";
import qs from "qs"; // Import qs for query string serialization
import { Product } from './global';


axios.defaults.headers.post["Content-Type"] = "application/x-www-form-urlencoded";
axios.defaults.transformRequest = [(data) => qs.stringify(data)];

const ProductEditor = () => {
    const [products, setProducts] = useState<Product[]>([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        fetchProducts();
    }, []);
    const fetchProducts = async () => {
        setLoading(true);
        setError(null);

        try {
            const response = await axios.post(shopManagerData.ajax_url, {
                action: "shop_manager_fetch_products",
                security: shopManagerData.nonce,
            });

            if (response.data.success && Array.isArray(response.data.data)) {
                setProducts(response.data.data);
            } else {
                throw new Error("Failed to load products.");
            }
        } catch (err: any) {
            setError(err.message || "An error occurred.");
        } finally {
            setLoading(false);
        }
    };

    const handleSaveProduct = async (productId: number, updates: Partial<Product>) => {
        try {
            const response = await axios.post(shopManagerData.ajax_url, {
                action: "shop_manager_update_product",
                security: shopManagerData.nonce,
                product_id: productId,
                ...updates,
            });

            if (!response.data.success) {
                throw new Error(response.data.data?.message || "Failed to save product.");
            }

            // Update the product locally
            setProducts((prevProducts) =>
                prevProducts.map((product) =>
                    product.id === productId ? { ...product, ...updates } : product
                )
            );
        } catch (err: any) {
            alert(err.message || "An error occurred while saving the product.");
        }
    };

    if (loading) return <p>Loading...</p>;
    if (error) return <p>Error: {error}</p>;

    return (
        <div className="wrap">
            <div id="bsr-cogs-app">
                <div className="postbox product-table full-width">
                    <div className="inside">
                        <ProductTable products={products} onSave={handleSaveProduct} />
                    </div>
                </div>
            </div>
        </div>
    );
};

export default ProductEditor;
