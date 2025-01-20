import React, { useState } from "react";
import { Product } from './global';

const ProductRow = ({
    index,
    product,
    onSave,
}: {
    index: number;
    product: Product;
    onSave: (id: number, updates: Product) => void;
}) => {
    const [isUnsaved, setIsUnsaved] = useState(false);
    const [localProduct, setLocalProduct] = useState<Product>({ ...product });
    const [error, setError] = useState<string | null>(null);

    const handleChange = (field: keyof Product, value: any) => {
        setLocalProduct((prev: Product) => ({ ...prev, [field]: value }));
        setIsUnsaved(true);
        setError(null); // Clear error on edit
    };

    const handleSave = async () => {
        try {
            await onSave(product.id, localProduct);
            setError(null); // Clear errors on success
        } catch (err: any) {
            setError(err.message || "An error occurred while saving.");
        } finally {
            setIsUnsaved(false);
        }
    };

    return (
        <>
            <tr className={isUnsaved ? "unsaved" : ""}>
                <td>{index}</td>
                <td>{localProduct.id}</td>
                <td>{localProduct.name}</td>
                <td>{localProduct.type}</td>
                <td className="bsr-sku">
                    <input
                        type="text"
                        value={localProduct.sku || ""}
                        className="edit-sku"
                        onChange={(e) => handleChange("sku", e.target.value)}
                    />
                </td>
                <td>{localProduct.price}</td>
                <td className="bsr-cogs">
                    <input
                        type="number"
                        value={localProduct.cogs || ""}
                        className="edit-cogs"
                        onChange={(e) => handleChange("cogs", e.target.value)}
                    />
                </td>
                <td className="bsr-packing-cost">
                    <input
                        type="number"
                        value={localProduct.packing_cost || ""}
                        className="edit-packing-cost"
                        onChange={(e) => handleChange("packing_cost", e.target.value)}
                    />
                </td>
                <td className="bsr-stock">
                    {product.manage_stock ? (
                        <input
                            type="number"
                            value={localProduct.stock || ""}
                            className="edit-stock"
                            onChange={(e) => handleChange("stock", e.target.value)}
                        />
                    ) : (
                        localProduct.stock || ""
                    )}
                </td>
                {/* New Fields */}
                <td className="bsr-work-time">
                    <input
                        type="number"
                        value={localProduct.work_time_minutes || ""}
                        className="edit-work-time"
                        onChange={(e) => handleChange("work_time_minutes", e.target.value)}
                    />
                </td>
                <td className="bsr-development-cost">
                    <input
                        type="number"
                        value={localProduct.development_cost || ""}
                        className="edit-development-cost"
                        onChange={(e) => handleChange("development_cost", e.target.value)}
                    />
                </td>
                <td className="bsr-development-months">
                    <input
                        type="number"
                        value={localProduct.development_months || ""}
                        className="edit-development-months"
                        onChange={(e) => handleChange("development_months", e.target.value)}
                    />
                </td>
                <td>
                    <button className="save-product button" onClick={handleSave} disabled={!isUnsaved}>
                        Save
                    </button>
                    <a
                        href={`${shopManagerData.edit_product_url}${localProduct.id}&action=edit`}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="open-product button"
                    >
                        Open
                    </a>
                </td>
            </tr>
            {error && (
                <tr className="error-message">
                    <td colSpan={13} style={{ color: "red" }}>
                        {error}
                    </td>
                </tr>
            )}
        </>
    );
};

export default ProductRow;
