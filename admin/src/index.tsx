import React from 'react';
import { createRoot } from 'react-dom/client';
import { BrowserRouter as Router, useLocation } from 'react-router-dom';
import ProductEditor from './ProductEditor';
import Dashboard from './Dashboard';
import ProductOrderData from './ProductOrderData';
import '@assets/styles.css';

// Helper to get query parameters from the URL
const useQuery = () => {
    return new URLSearchParams(useLocation().search);
};

// Wrapper component to handle query-based routing
const QueryRouter = () => {
    const query = useQuery();
    const page = query.get('page');
    const subPage = query.get('shop-manager');

    if (page === 'shop-manager') {
        if (subPage === 'product-editor') {
            return <ProductEditor />;
        }
        if (subPage === 'product-order-data') {
            return <ProductOrderData />;
        }
        return <Dashboard />;
    }
    return <p>Page not found.</p>;
};

// Main entry point
const rootElement = document.getElementById('shop-manager-app');
console.log('Root element:', rootElement);

if (rootElement) {
    const root = createRoot(rootElement);
    root.render(
        <Router>
            <nav className="nav-tab-wrapper">
                <a
                    href="/wp-admin/admin.php?page=shop-manager"
                    className={`nav-tab ${window.location.search.includes('shop-manager=product-editor') ? '' : 'nav-tab-active'}`}
                >
                    Dashboard
                </a>
                <a
                    href="/wp-admin/admin.php?page=shop-manager&shop-manager=product-editor"
                    className={`nav-tab ${window.location.search.includes('shop-manager=product-editor') ? 'nav-tab-active' : ''}`}
                >
                    Product Editor
                </a>
                                <a
                    href="/wp-admin/admin.php?page=shop-manager&shop-manager=product-order-data"
                    className={`nav-tab ${window.location.search.includes('shop-manager=product-order-data') ? 'nav-tab-active' : ''}`}
                >
                    Product Order Data
                </a>
            </nav>
            <QueryRouter />
        </Router>
    );
}
