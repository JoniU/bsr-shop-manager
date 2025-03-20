// profit-dashboard.js

// Helper functions for date manipulation
function startOfDay(date) {
    const d = new Date(date);
    d.setHours(0, 0, 0, 0);
    return d;
}

function endOfDay(date) {
    const d = new Date(date);
    d.setHours(23, 59, 59, 999);
    return d;
}

function startOfMonth(date) {
    return new Date(date.getFullYear(), date.getMonth(), 1, 0, 0, 0, 0);
}

function endOfMonth(date) {
    return new Date(date.getFullYear(), date.getMonth() + 1, 0, 23, 59, 59, 999);
}

function startOfIsoWeek(date) {
    // JavaScript getDay() returns 0 for Sunday. We'll treat Monday as the first day.
    const d = new Date(date);
    const day = d.getDay() === 0 ? 7 : d.getDay();
    d.setDate(d.getDate() - day + 1);
    d.setHours(0, 0, 0, 0);
    return d;
}

function endOfIsoWeek(date) {
    const start = startOfIsoWeek(date);
    const d = new Date(start);
    d.setDate(d.getDate() + 6);
    d.setHours(23, 59, 59, 999);
    return d;
}

function subtractDays(date, days) {
    const d = new Date(date);
    d.setDate(d.getDate() - days);
    return d;
}

// Function to check if a date is between two dates (inclusive)
function isBetween(date, start, end) {
    return date >= start && date <= end;
}

document.addEventListener('DOMContentLoaded', function () {
    const container = document.getElementById('profit-dashboard-block');
    if (!container) {
        console.error('Container element not found.');
        return;
    }

    // Define API endpoints.
    const baseUrl = window.wpApiSettings?.baseUrl || '';
    const settingsUrl = `${baseUrl}/wp-json/custom/v1/bsr-shop-manager-settings`;
    const profitDataUrl = `${baseUrl}/wp-json/custom/v1/order-live`;

    // Utility function to calculate profit for an entry.
    function calculateProfit(entry) {
        console.log(entry);
        return entry.total - entry.cogs_price - entry.packing_cost - entry.tax - entry.shipping - entry.shipping_tax;
    }

    // Function to calculate profit over a range.
    function calculateRangeProfit(profitData, startDate, endDate) {
        const filteredEntries = profitData.filter((entry) => {
            const entryDate = new Date(entry.date);
            return isBetween(entryDate, startDate, endDate);
        });
        return filteredEntries.reduce((sum, entry) => sum + calculateProfit(entry), 0);
    }

    // Date helper functions.
    function startOfDay(date) {
        const d = new Date(date);
        d.setHours(0, 0, 0, 0);
        return d;
    }

    function endOfDay(date) {
        const d = new Date(date);
        d.setHours(23, 59, 59, 999);
        return d;
    }

    function startOfMonth(date) {
        return new Date(date.getFullYear(), date.getMonth(), 1, 0, 0, 0, 0);
    }

    function endOfMonth(date) {
        return new Date(date.getFullYear(), date.getMonth() + 1, 0, 23, 59, 59, 999);
    }

    function startOfIsoWeek(date) {
        const d = new Date(date);
        const day = d.getDay() === 0 ? 7 : d.getDay();
        d.setDate(d.getDate() - day + 1);
        d.setHours(0, 0, 0, 0);
        return d;
    }

    function endOfIsoWeek(date) {
        const start = startOfIsoWeek(date);
        const d = new Date(start);
        d.setDate(d.getDate() + 6);
        d.setHours(23, 59, 59, 999);
        return d;
    }

    function subtractDays(date, days) {
        const d = new Date(date);
        d.setDate(d.getDate() - days);
        return d;
    }

    function isBetween(date, start, end) {
        return date >= start && date <= end;
    }

    // Fetch settings and profit data concurrently.
    Promise.all([fetch(settingsUrl).then((res) => res.json()), fetch(profitDataUrl).then((res) => res.json())])
        .then(([settingsData, profitDataRaw]) => {
            // Process settings.
            const monthlyTarget = Number(settingsData.monthlyTarget) || 0;
            const todayDate = new Date();
            const daysInMonth = new Date(todayDate.getFullYear(), todayDate.getMonth() + 1, 0).getDate();
            const targetValues = {
                today: monthlyTarget / daysInMonth || 0,
                thisWeek: (monthlyTarget / daysInMonth) * 7 || 0,
                thisMonth: monthlyTarget || 0,
                last7Days: (monthlyTarget / daysInMonth) * 7 || 0,
                last30Days: monthlyTarget || 0,
            };

            // Process profit data.
            const profitData = Object.entries(profitDataRaw).map(([date, metrics]) => ({
                date,
                ...metrics,
            }));

            // Build targets array.
            const targets = [
                {
                    label: 'Today',
                    calculated:
                        parseFloat(
                            calculateRangeProfit(profitData, startOfDay(new Date()), endOfDay(new Date())).toFixed(2),
                        ) || 0,
                    target: targetValues.today || 0,
                },
                {
                    label: 'This Week',
                    calculated:
                        parseFloat(
                            calculateRangeProfit(
                                profitData,
                                startOfIsoWeek(new Date()),
                                endOfIsoWeek(new Date()),
                            ).toFixed(2),
                        ) || 0,
                    target: targetValues.thisWeek || 0,
                },
                {
                    label: 'This Month',
                    calculated:
                        parseFloat(
                            calculateRangeProfit(profitData, startOfMonth(new Date()), endOfMonth(new Date())).toFixed(
                                2,
                            ),
                        ) || 0,
                    target: targetValues.thisMonth || 0,
                },
                {
                    label: 'Last 7 Days',
                    calculated:
                        parseFloat(
                            calculateRangeProfit(profitData, subtractDays(new Date(), 7), new Date()).toFixed(2),
                        ) || 0,
                    target: targetValues.last7Days || 0,
                },
                {
                    label: 'Last 30 Days',
                    calculated:
                        parseFloat(
                            calculateRangeProfit(profitData, subtractDays(new Date(), 30), new Date()).toFixed(2),
                        ) || 0,
                    target: targetValues.last30Days || 0,
                },
            ];

            // Render the dashboard.
            let html = '';
            html += '<div class="dashboard-grid">';
            targets.forEach((target) => {
                if (!isNaN(target.target) && target.target > 0) {
                    const percentage = Math.min((target.calculated / target.target) * 100, 100);
                    html += `
                        <div class="profit-target-card">
                          <div class="profit-target-header">
                              <span class="profit-target-label">${target.label}</span>
                              <span class="profit-target-numbers">${target.calculated.toFixed(2)} / ${target.target.toFixed(2)}€</span>
                          </div>
                          <div class="profit-progress">
                              <div class="profit-progress-text">${percentage.toFixed(0)}%</div>
                              <div class="profit-progress-bar" style="width: ${percentage}%"></div>
                          </div>
                        </div>
                    `;
                }
            });
            html += '</div>';
            container.innerHTML = html;
        })
        .catch((err) => {
            console.error(err);
            container.innerHTML = `<div class="text-red-500">Failed to load data.</div>`;
        });
});
