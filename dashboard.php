<?php
// dashboard.php - SCADA Control Panel

require_once __DIR__ . '/auth.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SCADA Engine - Control Panel</title>
    <link rel="stylesheet" href="index.css">
    <!-- Chart.js for beautiful historical trends -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .dashboard-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px 20px;
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        /* Header Layout */
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 30px;
        }

        .header-brand {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .header-brand h1 {
            font-size: 1.5rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .user-widget {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .user-info {
            text-align: right;
            font-size: 0.875rem;
        }

        .user-info .username {
            font-weight: 600;
            color: var(--text-main);
        }

        .user-info .role {
            font-size: 0.75rem;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        /* Status & Config Grid */
        .status-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 24px;
        }

        @media (min-width: 900px) {
            .status-grid {
                grid-template-columns: 1fr 2fr;
            }
        }

        .widget-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-main);
            border-bottom: 1px solid var(--border-glass);
            padding-bottom: 12px;
            margin-bottom: 20px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        /* Info / Status Card */
        .status-card {
            padding: 24px;
        }

        .status-list {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .status-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .status-label {
            font-size: 0.875rem;
            color: var(--text-muted);
        }

        /* Config Form */
        .config-card {
            padding: 24px;
        }

        .config-form-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 16px;
        }

        @media (min-width: 600px) {
            .config-form-grid {
                grid-template-columns: repeat(3, 1fr);
            }
            .config-form-grid .full-width {
                grid-column: span 3;
            }
        }

        /* Telemetry Cards Grid */
        .telemetry-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 20px;
        }

        .telemetry-card {
            padding: 20px;
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            min-height: 140px;
        }

        .telemetry-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: var(--primary);
        }

        .tag-addr {
            font-size: 0.75rem;
            color: var(--text-muted);
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }

        .tag-val-wrapper {
            margin: 15px 0;
        }

        .tag-val {
            font-size: 2.25rem;
            font-weight: 700;
            color: var(--text-main);
        }

        .tag-ts {
            font-size: 0.7rem;
            color: var(--text-muted);
        }

        /* Chart Card */
        .chart-card {
            padding: 24px;
        }

        .chart-container {
            position: relative;
            height: 350px;
            width: 100%;
        }

        /* Flash Animation for value changes */
        @keyframes pulse-cyan {
            0% { background: rgba(0, 242, 254, 0.18); }
            100% { background: transparent; }
        }

        .flash-update {
            animation: pulse-cyan 0.6s ease-out;
        }

        /* Alert Toast */
        .toast {
            position: fixed;
            bottom: 20px;
            right: 20px;
            padding: 16px 24px;
            border-radius: var(--radius-sm);
            box-shadow: 0 10px 25px rgba(0,0,0,0.5);
            display: none;
            z-index: 1000;
            animation: slideIn 0.3s ease;
            backdrop-filter: blur(10px);
        }

        .toast-success {
            background: rgba(0, 230, 118, 0.2);
            border: 1px solid var(--success);
            color: var(--success);
        }

        .toast-error {
            background: rgba(255, 23, 68, 0.2);
            border: 1px solid var(--danger);
            color: var(--danger);
        }

        @keyframes slideIn {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
    </style>
</head>
<body>
    <!-- Toast Notifications -->
    <div id="toast-notify" class="toast"></div>

    <div class="dashboard-container">
        <!-- 1. Header Card -->
        <header class="glass-card dashboard-header fade-in">
            <div class="header-brand">
                <h1 class="digital-font">SCADA Engine v2</h1>
            </div>
            
            <div class="user-widget">
                <div class="user-info">
                    <div class="username"><?php echo htmlspecialchars($_SESSION['username']); ?></div>
                    <div class="role"><?php echo htmlspecialchars($_SESSION['role']); ?> Mode</div>
                </div>
                <a href="logout.php" class="btn-secondary" style="padding: 8px 16px; font-size: 0.85rem;">Sign Out</a>
            </div>
        </header>

        <!-- 2. Connection Settings & Status Row -->
        <div class="status-grid">
            <!-- Left Side: System Health Status -->
            <section class="glass-card status-card fade-in" style="animation-delay: 0.1s;">
                <h2 class="widget-title">System Status</h2>
                <div class="status-list">
                    <div class="status-item">
                        <span class="status-label">Collector Daemon:</span>
                        <span id="daemon-status-badge" class="status-indicator status-offline">Checking...</span>
                    </div>
                    <div class="status-item">
                        <span class="status-label">PLC Modbus TCP:</span>
                        <span id="plc-status-badge" class="status-indicator status-offline">Checking...</span>
                    </div>
                    <div class="status-item">
                        <span class="status-label">Polling Rate:</span>
                        <span class="mono-font" style="font-size: 0.9rem; font-weight: bold; color: var(--primary);">1.0 Hz</span>
                    </div>
                    <div class="status-item" style="border-top: 1px solid var(--border-glass); padding-top: 12px; margin-top: 4px;">
                        <span class="status-label">Active Target:</span>
                        <span id="active-target-display" class="mono-font" style="font-size: 0.85rem;">-</span>
                    </div>
                    <div class="status-item">
                        <span class="status-label">Register Area:</span>
                        <span id="active-register-display" class="mono-font" style="font-size: 0.85rem;">-</span>
                    </div>
                    <div class="status-item">
                        <span class="status-label">Last Polled:</span>
                        <span id="last-poll-time" class="mono-font" style="font-size: 0.85rem; color: var(--text-muted);">-</span>
                    </div>
                </div>
            </section>

            <!-- Right Side: Config Form (Conditional rendering for admin) -->
            <section class="glass-card config-card fade-in" style="animation-delay: 0.15s;">
                <h2 class="widget-title">
                    PLC Configuration
                    <span style="font-size: 0.75rem; color: var(--text-muted); text-transform: none;">
                        <?php echo isAdmin() ? '🛡️ Admin Access Granted' : '👁️ Read-Only Mode'; ?>
                    </span>
                </h2>

                <?php if (isAdmin()): ?>
                    <!-- Admin Config Input Form -->
                    <form id="config-form" class="config-form-grid">
                        <div class="form-group">
                            <label for="device_ip">DEVICE IP / HOST</label>
                            <input type="text" id="device_ip" name="device_ip" class="form-control mono-font" placeholder="127.0.0.1" required>
                        </div>
                        <div class="form-group">
                            <label for="tcp_port">TCP PORT</label>
                            <input type="number" id="tcp_port" name="tcp_port" class="form-control mono-font" placeholder="5020" min="1" max="65535" required>
                        </div>
                        <div class="form-group">
                            <label for="register_type">REGISTER TYPE (FC)</label>
                            <select id="register_type" name="register_type" class="form-control mono-font" required>
                                <option value="FC01">FC01 - Read Coils</option>
                                <option value="FC02">FC02 - Read Discrete Inputs</option>
                                <option value="FC03">FC03 - Read Holding Registers</option>
                                <option value="FC04">FC04 - Read Input Registers</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="start_address">STARTING ADDRESS</label>
                            <input type="number" id="start_address" name="start_address" class="form-control mono-font" placeholder="0" min="0" max="65535" required>
                        </div>
                        <div class="form-group">
                            <label for="register_count">REGISTER COUNT</label>
                            <input type="number" id="register_count" name="register_count" class="form-control mono-font" placeholder="10" min="1" max="125" required>
                        </div>
                        
                        <div class="form-group" style="justify-content: flex-end; margin-bottom: 0;">
                            <label style="opacity: 0;">ACTION</label>
                            <button type="submit" class="btn-primary" style="padding: 11px 20px;">
                                APPLY SETTINGS
                            </button>
                        </div>
                    </form>
                <?php else: ?>
                    <!-- Operator view (Read-only status board) -->
                    <div style="background: rgba(255,255,255,0.02); border: 1px solid var(--border-glass); border-radius: var(--radius-sm); padding: 20px; display: flex; flex-direction: column; gap: 12px; justify-content: center; height: 100%;">
                        <p style="font-size: 0.95rem; line-height: 1.5; color: var(--text-muted);">
                            You are logged in as an <strong>Operator</strong>. In accordance with system Role-Based Access Control (RBAC), operator roles are granted read-only telemetry visibility.
                        </p>
                        <p style="font-size: 0.85rem; color: var(--accent-purple); font-weight: 500;">
                            ℹ️ Contact your SCADA Administrator to modify target PLC settings, IP mappings, or polling registers.
                        </p>
                    </div>
                <?php endif; ?>
            </section>
        </div>

        <!-- 3. Telemetry Grid Section -->
        <section class="fade-in" style="animation-delay: 0.2s;">
            <h2 style="font-size: 1.1rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted); margin-bottom: 15px;">Live Registers</h2>
            <div id="telemetry-cards-container" class="telemetry-grid">
                <!-- Cards dynamically populated by JavaScript -->
                <div class="glass-card telemetry-card" style="justify-content: center; align-items: center; min-height: 150px; grid-column: 1 / -1;">
                    <p style="color: var(--text-muted);">Waiting for system data...</p>
                </div>
            </div>
        </section>

        <!-- 4. Historic Chart Section -->
        <section class="glass-card chart-card fade-in" style="animation-delay: 0.25s;">
            <h2 class="widget-title">Telemetry History (Live Trend)</h2>
            <div class="chart-container">
                <canvas id="history-chart"></canvas>
            </div>
        </section>
    </div>

    <!-- Script Logic -->
    <script>
        // Store current client configuration parameters to check if they have changed
        let activeConfig = null;
        let historyChart = null;
        let lastValuesMap = new Map(); // Keep track of tag values to trigger animations on update

        // Show toast helper
        function showToast(message, type = 'success') {
            const toast = document.getElementById('toast-notify');
            toast.textContent = message;
            toast.className = `toast toast-${type}`;
            toast.style.display = 'block';
            
            setTimeout(() => {
                toast.style.display = 'none';
            }, 4000);
        }

        // 1. Fetch live telemetry data
        async function fetchLiveTelemetry() {
            try {
                const response = await fetch('api/get_live.php');
                if (!response.ok) {
                    throw new Error(`HTTP Error: ${response.status}`);
                }
                const data = await response.json();
                
                if (data.status === 'success') {
                    updateStatusWidgets(data.daemon, data.config);
                    updateTelemetryGrid(data.telemetry, data.config);
                } else {
                    console.error("Live fetch error: ", data.message);
                }
            } catch (error) {
                console.error("Failed fetching live telemetry: ", error);
            }
        }

        // Update top status widgets
        function updateStatusWidgets(daemon, config) {
            // Update daemon status badge
            const daemonBadge = document.getElementById('daemon-status-badge');
            if (daemon.status === 'active') {
                daemonBadge.textContent = 'ACTIVE';
                daemonBadge.className = 'status-indicator status-active';
            } else {
                daemonBadge.textContent = 'OFFLINE';
                daemonBadge.className = 'status-indicator status-offline';
            }

            // Update PLC connection badge
            const plcBadge = document.getElementById('plc-status-badge');
            if (daemon.plc_connection === 'connected') {
                plcBadge.textContent = 'CONNECTED';
                plcBadge.className = 'status-indicator status-active';
            } else {
                plcBadge.textContent = 'DISCONNECTED';
                plcBadge.className = 'status-indicator status-offline';
            }

            // Update details
            document.getElementById('active-target-display').textContent = `${config.device_ip}:${config.tcp_port}`;
            document.getElementById('active-register-display').textContent = `${config.register_type} (Start: ${config.start_address}, Count: ${config.register_count})`;
            document.getElementById('last-poll-time').textContent = daemon.last_poll;

            // Fill form values on first load for admins
            const form = document.getElementById('config-form');
            if (form && activeConfig === null) {
                document.getElementById('device_ip').value = config.device_ip;
                document.getElementById('tcp_port').value = config.tcp_port;
                document.getElementById('register_type').value = config.register_type;
                document.getElementById('start_address').value = config.start_address;
                document.getElementById('register_count').value = config.register_count;
            }
            
            // Set active config locally
            activeConfig = config;
        }

        // Render & update telemetry cards
        function updateTelemetryGrid(telemetry, config) {
            const container = document.getElementById('telemetry-cards-container');
            
            // Generate full grid HTML if container is empty or structure has changed (e.g. start address or count modified)
            const expectedCount = config.register_count;
            const currentCards = container.querySelectorAll('.telemetry-card:not([style*="grid-column"])');
            
            const startAddr = config.start_address;
            const regType = config.register_type;
            
            if (currentCards.length !== expectedCount || 
                (currentCards.length > 0 && parseInt(currentCards[0].dataset.address) !== startAddr)) {
                // Clear and recreate cards
                container.innerHTML = '';
                
                telemetry.forEach(item => {
                    const card = document.createElement('div');
                    card.className = 'glass-card telemetry-card';
                    card.id = `card-${item.address}`;
                    card.dataset.address = item.address;
                    
                    // Style label based on Register type (analog telemetry vs digital state)
                    let regLabel = '';
                    if (regType === 'FC01' || regType === 'FC02') {
                        regLabel = `Coil/Bit [${item.address}]`;
                    } else {
                        regLabel = `Register [${item.address}]`;
                    }
                    
                    // Set color bars based on function code
                    let barColor = 'var(--primary)';
                    if (regType === 'FC01') barColor = '#e040fb'; // Purple for coils
                    if (regType === 'FC02') barColor = '#ffb300'; // Yellow for discrete inputs
                    if (regType === 'FC04') barColor = '#00b0ff'; // Cyan for input registers
                    card.style.borderLeft = `4px solid ${barColor}`;

                    card.innerHTML = `
                        <div class="tag-addr">${regLabel}</div>
                        <div class="tag-val-wrapper">
                            <span class="tag-val mono-font" id="val-${item.address}">-</span>
                        </div>
                        <div class="tag-ts" id="ts-${item.address}">-</div>
                    `;
                    container.appendChild(card);
                });
            }

            // Update card values
            telemetry.forEach(item => {
                const valElem = document.getElementById(`val-${item.address}`);
                const tsElem = document.getElementById(`ts-${item.address}`);
                const cardElem = document.getElementById(`card-${item.address}`);
                
                if (valElem && tsElem) {
                    let displayVal = '-';
                    
                    if (item.value !== null) {
                        if (regType === 'FC01' || regType === 'FC02') {
                            displayVal = item.value === 1 ? 'ON' : 'OFF';
                            // Apply helper color class to digital signals
                            valElem.style.color = item.value === 1 ? 'var(--success)' : 'var(--text-muted)';
                        } else {
                            // Format raw analog telemetry
                            displayVal = item.value;
                        }
                    }

                    // Check if value changed to trigger visual flash animation
                    const prevVal = lastValuesMap.get(item.address);
                    if (prevVal !== undefined && prevVal !== item.value) {
                        cardElem.classList.add('flash-update');
                        setTimeout(() => {
                            cardElem.classList.remove('flash-update');
                        }, 600);
                    }
                    
                    // Keep map updated
                    lastValuesMap.set(item.address, item.value);

                    valElem.textContent = displayVal;
                    tsElem.textContent = item.timestamp ? `Updated: ${item.timestamp.split(' ')[1]}` : item.status;
                }
            });
        }

        // 2. Fetch and render historical chart trend
        async function fetchHistoryAndRenderChart() {
            try {
                const response = await fetch('api/get_history.php');
                if (!response.ok) {
                    throw new Error(`HTTP Error: ${response.status}`);
                }
                const data = await response.json();
                
                if (data.status === 'success') {
                    updateChart(data.history);
                }
            } catch (error) {
                console.error("Failed fetching historical logs: ", error);
            }
        }

        function updateChart(history) {
            const ctx = document.getElementById('history-chart').getContext('2d');
            
            const addresses = Object.keys(history).sort((a,b) => a-b);
            if (addresses.length === 0) return;

            // Gather unique list of timestamps for x-axis labels
            let allTimes = new Set();
            addresses.forEach(addr => {
                history[addr].forEach(pt => allTimes.add(pt.time));
            });
            const labels = Array.from(allTimes).sort();

            // Colors array for lines
            const colors = [
                '#00f2fe', // Cyan
                '#9b51e0', // Purple
                '#00e676', // Green
                '#ffb300', // Yellow
                '#ff1744', // Red
                '#00b0ff', // Blue
                '#ff5722', // Orange
                '#e91e63', // Pink
                '#8bc34a', // Light Green
                '#3f51b5'  // Indigo
            ];

            // Build datasets
            const datasets = addresses.map((addr, idx) => {
                const color = colors[idx % colors.length];
                
                // Align data points with labels
                const dataPoints = labels.map(time => {
                    const found = history[addr].find(pt => pt.time === time);
                    return found ? found.value : null;
                });

                return {
                    label: `Address ${addr}`,
                    data: dataPoints,
                    borderColor: color,
                    backgroundColor: `${color}1A`, // 10% opacity
                    borderWidth: 2,
                    tension: 0.35,
                    pointRadius: 2,
                    pointHoverRadius: 4,
                    spanGaps: true
                };
            });

            if (historyChart) {
                // If chart already initialized, update datasets without recreating full canvas
                historyChart.data.labels = labels;
                historyChart.data.datasets = datasets;
                historyChart.update('none'); // silent update
            } else {
                // Create new chart instance
                historyChart = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: datasets
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                labels: { color: '#f3f4f6', font: { family: 'Inter' } }
                            },
                            tooltip: {
                                mode: 'index',
                                intersect: false
                            }
                        },
                        scales: {
                            x: {
                                grid: { color: 'rgba(255, 255, 255, 0.05)' },
                                ticks: { color: '#9ca3af', font: { family: 'Inter' } }
                            },
                            y: {
                                grid: { color: 'rgba(255, 255, 255, 0.05)' },
                                ticks: { color: '#9ca3af', font: { family: 'Inter' } }
                            }
                        }
                    }
                });
            }
        }

        // 3. Setup form submission for network settings (Admin only)
        const configForm = document.getElementById('config-form');
        if (configForm) {
            configForm.addEventListener('submit', async function(e) {
                e.preventDefault();

                const formData = {
                    device_ip: document.getElementById('device_ip').value,
                    tcp_port: parseInt(document.getElementById('tcp_port').value),
                    register_type: document.getElementById('register_type').value,
                    start_address: parseInt(document.getElementById('start_address').value),
                    register_count: parseInt(document.getElementById('register_count').value)
                };

                try {
                    const response = await fetch('api/update_network.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify(formData)
                    });

                    const result = await response.json();
                    
                    if (response.ok && result.status === 'success') {
                        showToast(result.message, 'success');
                        // Instantly trigger polling refresh to reflect changes on GUI
                        fetchLiveTelemetry();
                        fetchHistoryAndRenderChart();
                    } else {
                        showToast(result.message || 'Failed updating config.', 'error');
                    }
                } catch (error) {
                    showToast('Connection error updating network settings.', 'error');
                    console.error(error);
                }
            });
        }

        // 4. Initialization and Polling intervals
        document.addEventListener('DOMContentLoaded', () => {
            fetchLiveTelemetry();
            fetchHistoryAndRenderChart();

            // Poll live register updates every 1.0 second (matching background daemon poll rate)
            setInterval(fetchLiveTelemetry, 1000);

            // Poll chart historical trend updates every 5.0 seconds
            setInterval(fetchHistoryAndRenderChart, 5000);
        });
    </script>
</body>
</html>
