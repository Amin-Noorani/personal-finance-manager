// Analytics Charts - loaded after Chart.js
document.addEventListener('DOMContentLoaded', function() {
    if (typeof Chart === 'undefined' || typeof analyticsData === 'undefined') return;

    var font = {family:"Vazir", size:12};
    var colors = ["#4f46e5","#dc2626","#059669","#d97706","#7c3aed","#ec4899","#14b8a6","#f97316","#6366f1","#84cc16"];

    function toFaNum(n) {
        var f = ["۰","۱","۲","۳","۴","۵","۶","۷","۸","۹"];
        return String(n).replace(/[0-9]/g, function(d) { return f[d]; });
    }

    // Category Donut Chart
    var catData = analyticsData.category;
    if (catData.length > 0) {
        var expData = catData.filter(function(d) { return d.expense > 0; });
        if (expData.length > 0) {
            new Chart(document.getElementById("categoryChart"), {
                type: "doughnut",
                data: {
                    labels: expData.map(function(d) { return d.name; }),
                    datasets: [{
                        data: expData.map(function(d) { return d.expense; }),
                        backgroundColor: colors.slice(0, expData.length),
                        borderWidth: 2,
                        borderColor: "#fff"
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: "60%",
                    plugins: {
                        legend: { position: "right", labels: { boxWidth: 12, padding: 10, font: font } }
                    }
                }
            });
        }
    }

    // Monthly Trend Bar Chart
    var mData = analyticsData.monthly;
    if (mData.length > 0) {
        new Chart(document.getElementById("monthlyChart"), {
            type: "bar",
            data: {
                labels: mData.map(function(d) { return d.month_fa || d.month; }),
                datasets: [
                    { label: "درآمد", data: mData.map(function(d) { return d.income; }), backgroundColor: "#059669", borderRadius: 6, barPercentage: 0.7 },
                    { label: "هزینه", data: mData.map(function(d) { return d.expense; }), backgroundColor: "#dc2626", borderRadius: 6, barPercentage: 0.7 }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { intersect: false, mode: "index" },
                scales: {
                    y: { beginAtZero: true, ticks: { callback: toFaNum, font: font }, grid: { color: "#f1f5f9" } },
                    x: { ticks: { font: font }, grid: { display: false } }
                },
                plugins: {
                    legend: { labels: { font: font, usePointStyle: true, padding: 20 } },
                    tooltip: { backgroundColor: "#1e293b", titleFont: font, bodyFont: font, padding: 12, cornerRadius: 8 }
                }
            }
        });
    }

    // Account Horizontal Bar Chart
    var aData = analyticsData.account;
    if (aData.length > 0) {
        new Chart(document.getElementById("accountChart"), {
            type: "bar",
            data: {
                labels: aData.map(function(d) { return d.name; }),
                datasets: [
                    { label: "درآمد", data: aData.map(function(d) { return d.income; }), backgroundColor: "#059669", borderRadius: 4, barPercentage: 0.6 },
                    { label: "هزینه", data: aData.map(function(d) { return d.expense; }), backgroundColor: "#dc2626", borderRadius: 4, barPercentage: 0.6 }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: "y",
                interaction: { intersect: false, mode: "index" },
                scales: {
                    x: { beginAtZero: true, ticks: { callback: toFaNum, font: font }, grid: { color: "#f1f5f9" } },
                    y: { ticks: { font: font }, grid: { display: false } }
                },
                plugins: {
                    legend: { labels: { font: font, usePointStyle: true, padding: 20 } },
                    tooltip: { backgroundColor: "#1e293b", titleFont: font, bodyFont: font, padding: 12, cornerRadius: 8 }
                }
            }
        });
    }

    // Tag Pie Chart
    var tData = analyticsData.tag;
    if (tData.length > 0) {
        var tagExp = tData.filter(function(d) { return d.expense > 0; });
        if (tagExp.length > 0) {
            new Chart(document.getElementById("tagChart"), {
                type: "pie",
                data: {
                    labels: tagExp.map(function(d) { return d.name; }),
                    datasets: [{
                        data: tagExp.map(function(d) { return d.expense; }),
                        backgroundColor: colors.slice(0, tagExp.length),
                        borderWidth: 2,
                        borderColor: "#fff"
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: "right", labels: { boxWidth: 12, padding: 10, font: font } }
                    }
                }
            });
        }
    }
});
