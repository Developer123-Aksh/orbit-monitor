<?php
require_once 'auth.php'; // Ensures RBAC
// DATABASE CONNECTION
$pdo = new PDO("mysql:host=localhost;dbname=scada_live_db", "root", "");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>ONGC SCADA Dashboard</title>
    <style>
        /* ADD YOUR PROFESSIONAL CSS HERE */
        body { background-color: #06070a; color: white; font-family: 'Inter', sans-serif; }
        .grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; padding: 20px; }
        .card { background: rgba(15, 17, 24, 0.85); border: 1px solid #334155; padding: 20px; border-radius: 8px; text-align: center; }
        .value { font-size: 32px; font-weight: 700; color: #f2a900; }
    </style>
</head>
<body>
    <div class="grid" id="registerGrid"></div>

    <script>
        async function fetchRegisters() {
            try {
                // This calls your API endpoint
                const response = await fetch('api/get_live.php');
                const data = await response.json();
                const container = document.getElementById('registerGrid');
                container.innerHTML = '';
                
                data.forEach(item => {
                    container.innerHTML += `
                        <div class="card">
                            <div>Register ${item.tag_address}</div>
                            <div class="value">${parseFloat(item.tag_value).toFixed(1)}</div>
                        </div>
                    `;
                });
            } catch(e) { console.error("API Error", e); }
        }
        setInterval(fetchRegisters, 3000);
        fetchRegisters();
    </script>
</body>
</html>