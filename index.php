<<<<<<< HEAD
<?php
// index.php - Entry point redirector

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
} else {
    header('Location: login.php');
}
exit;
=======
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Production SCADA Dashboard</title>
    <style>
        body { background: #0f172a; color: white; font-family: 'Segoe UI', sans-serif; padding: 20px; }
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 15px; }
        .card { background: #1e293b; padding: 20px; border-radius: 10px; text-align: center; border: 1px solid #334155; }
        .label { color: #94a3b8; font-size: 0.9rem; }
        .value { font-size: 32px; font-weight: bold; color: #fbbf24; margin-top: 10px; }
    </style>
</head>
<body>
    <h1>Live Production Telemetry</h1>
    <div class="grid" id="dashboard">Loading live data...</div>

    <script>
        async function refreshData() {
            const response = await fetch('api/get_live.php');
            const data = await response.json();
            const container = document.getElementById('dashboard');
            container.innerHTML = '';
            
            data.forEach(item => {
                container.innerHTML += `
                    <div class="card">
                        <div class="label">Register ${item.tag_address}</div>
                        <div class="value">${parseFloat(item.tag_value).toFixed(2)}</div>
                    </div>
                `;
            });
        }
        setInterval(refreshData, 3000);
        refreshData();
    </script>
</body>
</html>
>>>>>>> 28b37767f32c545b0fd3633c89604c5adf1e3960
