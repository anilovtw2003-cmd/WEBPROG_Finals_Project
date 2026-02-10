// soil moisture monitoring system - frontend application

const CONFIG = {
    updateInterval: 3000,
    maxHistoryItems: 10,
    dataStaleThreshold: 15,
    sheetId: '1TSmGgE5V3aNaZRBO-4UQK86r3YU3315dcduGhqU5xSM',
    get googleSheetsUrl() {
        return `https://docs.google.com/spreadsheets/d/${this.sheetId}/gviz/tq?tqx=out:json`;
    }
};

let historyData = [];
let previousStatus = { soilState: null, waterState: null, pumpOn: null };
let dataLoaded = false;

// notification system
const notifications = {
    container: null,
    init() { this.container = document.getElementById('notificationContainer'); },
    show(message, type = 'info', duration = 5000) {
        const icons = { 'pump-on': '●', 'pump-off': '✓', 'water-full': '▲', 'water-good': '◆', 'water-low': '!', info: 'i' };
        const el = document.createElement('div');
        el.className = `notification ${type}`;
        el.setAttribute('role', 'status');
        el.innerHTML = `<span class="notification-icon">${icons[type] || icons.info}</span><span class="notification-message">${message}</span><button class="notification-close" aria-label="Dismiss" onclick="this.parentElement.remove()">×</button>`;
        this.container.appendChild(el);
        setTimeout(() => el.classList.add('show'), 10);
        setTimeout(() => { el.classList.remove('show'); setTimeout(() => el.remove(), 300); }, duration);
    },
    pumpOn() { this.show('Pump is ON - Watering the soil!', 'pump-on', 6000); },
    pumpOff() { this.show('Pump is OFF - Soil is hydrated!', 'pump-off', 5000); },
    waterLevel(level) {
        const m = { FULL: ['Water Tank is FULL', 'water-full'], GOOD: ['Water Tank level is GOOD', 'water-good'], LOW: ['Water Tank is LOW - Please refill!', 'water-low'] };
        if (m[level]) this.show(m[level][0], m[level][1], level === 'LOW' ? 8000 : 5000);
    }
};

// DOM references
let el = {};
function cacheElements() {
    el = {
        temp: document.getElementById('temperatureValue'),
        tempStatus: document.getElementById('temperatureStatus'),
        hum: document.getElementById('humidityValue'),
        humStatus: document.getElementById('humidityStatus'),
        moist: document.getElementById('moistureValue'),
        moistStatus: document.getElementById('moistureStatus'),
        moistFill: document.getElementById('moistureFill'),
        histBody: document.getElementById('historyTableBody'),
        lastUpdate: document.getElementById('lastUpdate'),
        connStatus: document.getElementById('connectionStatus'),
        waterState: document.getElementById('waterStateStatus'),
        tubeWater: document.getElementById('tubeWater')
    };
}

// status helpers
function getSoilStatus(state) {
    if (!state) return { text: 'Unknown', class: 'dry' };
    const s = state.toString().toUpperCase();
    if (s.includes('WET')) return { text: 'WET', class: 'wet' };
    if (s.includes('GOOD')) return { text: 'GOOD', class: 'good' };
    return { text: 'DRY', class: 'dry' };
}

function getWaterStatus(state) {
    if (!state) return { text: 'Unknown', class: 'unknown' };
    const s = state.toString().toUpperCase();
    if (s.includes('FULL')) return { text: 'FULL', class: 'full' };
    if (s.includes('GOOD')) return { text: 'GOOD', class: 'good' };
    if (s.includes('LOW')) return { text: 'LOW', class: 'low' };
    if (s.includes('ERR')) return { text: 'ERROR', class: 'error' };
    return { text: 'Unknown', class: 'unknown' };
}

function getTemperatureStatus(t) { return t < 15 ? 'Cold' : t < 25 ? 'Normal' : t < 35 ? 'Warm' : 'Hot'; }
function getHumidityStatus(h) { return h < 30 ? 'Low' : h < 60 ? 'Normal' : 'High'; }

// skeleton loading
function removeSkeletons() {
    if (dataLoaded) return;
    dataLoaded = true;
    document.querySelectorAll('.skeleton-text').forEach(e => { e.classList.remove('skeleton-text'); e.classList.add('loaded'); });
}

// connection status
function setConnectionStatus(connected, waiting = false) {
    const dot = el.connStatus.querySelector('.status-dot');
    const text = el.connStatus.querySelector('.status-text');
    dot.className = 'status-dot ' + (waiting ? 'waiting' : connected ? 'connected' : 'disconnected');
    text.textContent = waiting ? 'Waiting for sensor...' : connected ? 'Connected' : 'Disconnected';
}

// set all displays to a blank/offline state
function setBlankState(label, isWaiting) {
    el.temp.textContent = '--';
    el.tempStatus.textContent = label;
    el.hum.textContent = '--';
    el.humStatus.textContent = label;
    el.moist.textContent = '--';
    el.moistStatus.innerHTML = `<span class="status-badge">${label}</span>`;
    el.moistFill.style.width = '0%';
    el.waterState.innerHTML = `<span class="status-badge">${isWaiting ? 'Waiting...' : label}</span>`;
    if (el.tubeWater) {
        el.tubeWater.style.height = '0%';
        if (!isWaiting) el.tubeWater.className = 'tube-water';
    }
    setConnectionStatus(false, isWaiting);
}

// UI update
function updateUI(data) {
    try {
        if (data.temperature === 0 && data.humidity === 0 && data.moisture === 0) {
            setBlankState('Waiting for data...', true);
            return;
        }
        removeSkeletons();

        el.temp.textContent = data.temperature.toFixed(1);
        el.tempStatus.textContent = getTemperatureStatus(data.temperature);
        el.hum.textContent = data.humidity.toFixed(1);
        el.humStatus.textContent = getHumidityStatus(data.humidity);
        el.moist.textContent = data.moisture.toFixed(1);

        const soil = getSoilStatus(data.soilState || data.moisture);
        el.moistStatus.innerHTML = `<span class="status-badge ${soil.class}">${soil.text}</span>`;
        el.moistFill.style.width = `${Math.min(100, Math.max(0, data.moisture))}%`;
        el.moistFill.className = `moisture-fill ${soil.class}`;
        const mc = document.querySelector('.moisture-card');
        mc.classList.remove('wet', 'good', 'dry');
        mc.classList.add(soil.class);

        const water = getWaterStatus(data.waterState);
        const wp = water.text === 'FULL' ? 100 : water.text === 'GOOD' ? 60 : water.text === 'LOW' ? 25 : 0;
        if (el.tubeWater) { el.tubeWater.style.height = `${wp}%`; el.tubeWater.className = `tube-water ${water.class}`; }
        el.waterState.innerHTML = `<span class="status-badge ${water.class}">${water.text}</span>`;

        const isPumpOn = soil.text === 'DRY';
        if (previousStatus.pumpOn !== null && previousStatus.pumpOn !== isPumpOn) {
            isPumpOn ? notifications.pumpOn() : notifications.pumpOff();
        }
        previousStatus.pumpOn = isPumpOn;
        if (previousStatus.waterState !== null && previousStatus.waterState !== water.text) notifications.waterLevel(water.text);
        previousStatus.waterState = water.text;

        el.lastUpdate.textContent = new Date().toLocaleString();
        setConnectionStatus(true);
    } catch (e) { console.error('UI Error:', e); }
}

// history
function addToHistory(data) {
    if (data.temperature === 0 && data.humidity === 0 && data.moisture === 0) return;
    const soil = getSoilStatus(data.soilState || data.moisture);
    const water = getWaterStatus(data.waterState);
    if (previousStatus.soilState === soil.text && previousStatus.waterState === water.text) return;

    historyData.unshift({
        time: new Date().toLocaleTimeString(),
        temperature: data.temperature.toFixed(1),
        humidity: data.humidity.toFixed(1),
        moisture: data.moisture.toFixed(1),
        soilStatus: soil, waterStatus: water
    });
    if (historyData.length > CONFIG.maxHistoryItems) historyData.pop();
    renderHistory();
    previousStatus.soilState = soil.text;
    previousStatus.waterState = water.text;
    saveHistoryToDatabase({ temperature: data.temperature, humidity: data.humidity, moisture: data.moisture, soilState: soil.text, waterDist: data.waterDist || 0, waterState: water.text });
}

function renderHistory() {
    if (!historyData.length) { el.histBody.innerHTML = '<tr><td colspan="6" class="no-data">No history data yet...</td></tr>'; return; }
    el.histBody.innerHTML = historyData.map(e => `<tr><td>${e.time}</td><td>${e.temperature}°C</td><td>${e.humidity}%</td><td>${e.moisture}%</td><td><span class="status-badge ${e.soilStatus.class}">${e.soilStatus.text}</span></td><td><span class="status-badge ${e.waterStatus.class}">${e.waterStatus.text}</span></td></tr>`).join('');
}

// data fetching
async function fetchSensorData() {
    try {
        const response = await fetch(CONFIG.googleSheetsUrl);
        if (!response.ok) throw new Error('Network error: ' + response.status);
        const text = await response.text();
        const match = text.match(/google\.visualization\.Query\.setResponse\(([\s\S]*?)\);/);
        if (!match?.[1]) throw new Error('Invalid response format');

        const rows = JSON.parse(match[1]).table.rows;
        if (!rows.length) { setBlankState('No data', false); el.lastUpdate.textContent = 'ESP32 Offline'; return; }

        const lastRow = rows[rows.length - 1].c;
        const tsStr = lastRow[0]?.f || lastRow[0]?.v || null;
        if (tsStr) {
            const dm = String(tsStr).match(/Date\((\d+),(\d+),(\d+),(\d+),(\d+),(\d+)\)/);
            const dt = dm ? new Date(+dm[1], +dm[2], +dm[3], +dm[4], +dm[5], +dm[6]) : new Date(tsStr);
            if (dt && !isNaN(dt.getTime())) {
                const age = (Date.now() - dt.getTime()) / 1000;
                if (age > CONFIG.dataStaleThreshold) { setBlankState('No data', false); el.lastUpdate.textContent = 'ESP32 Offline'; return; }
            }
        }

        const data = {
            timestamp: tsStr,
            temperature: parseFloat(lastRow[1]?.v) || 0,
            humidity: parseFloat(lastRow[2]?.v) || 0,
            moisture: parseFloat(lastRow[3]?.v) || 0,
            soilState: lastRow[4]?.v || null,
            waterDist: parseFloat(lastRow[5]?.v) || 0,
            waterState: lastRow[6]?.v || null
        };
        updateUI(data);
        addToHistory(data);
    } catch (e) {
        console.error('Fetch Error:', e);
        setBlankState('No data', false);
        el.lastUpdate.textContent = 'ESP32 Offline';
    }
}

// database sync
async function saveHistoryToDatabase(data) {
    try {
        const params = new URLSearchParams({ temp: data.temperature || 0, hum: data.humidity || 0, soil: data.moisture || 0, soil_state: data.soilState || 'Unknown', water_dist: data.waterDist || 0, water_state: data.waterState || 'Unknown' });
        const resp = await fetch('board.php?' + params);
        const text = await resp.text();
        if (text.trim().startsWith('<')) return;
        const result = JSON.parse(text);
        if (result.success) console.log('[DB] Saved, ID:', result.db_info?.inserted_id);
        else console.error('[DB] Failed:', result.message);
    } catch (e) { console.error('[DB] Save error:', e); }
}

// account dropdown
function toggleAccountMenu() {
    const menu = document.getElementById('accountMenu');
    const btn = document.querySelector('.account-btn');
    const open = menu.classList.toggle('show');
    btn.setAttribute('aria-expanded', open);
}

// admin tabs
function showAdminTab(tabName, evt) {
    document.querySelectorAll('.admin-tab-content').forEach(c => c.classList.remove('active'));
    document.querySelectorAll('.admin-tab').forEach(t => { t.classList.remove('active'); t.setAttribute('aria-selected', 'false'); });
    document.getElementById('admin-tab-' + tabName)?.classList.add('active');
    if (evt?.target) { evt.target.classList.add('active'); evt.target.setAttribute('aria-selected', 'true'); }
    if (tabName === 'history') refreshAdminHistory();
}

async function refreshAdminHistory() {
    const tbody = document.getElementById('adminHistoryTableBody');
    if (!tbody) return;
    try {
        const resp = await fetch('board.php?action=get_history');
        const text = await resp.text();
        if (text.trim().startsWith('<')) return;
        const result = JSON.parse(text);
        if (!result.success || !result.data) return;
        tbody.innerHTML = result.data.length === 0
            ? '<tr><td colspan="6" class="no-data">No history data available</td></tr>'
            : result.data.map(r => `<tr><td>${r.time}</td><td>${parseFloat(r.temp).toFixed(1)}</td><td>${parseFloat(r.humidity).toFixed(1)}</td><td>${parseFloat(r.moisture).toFixed(1)}</td><td><span class="status-badge ${(r.soil||'unknown').toLowerCase()}">${r.soil||'N/A'}</span></td><td><span class="status-badge ${(r.water||'unknown').toLowerCase()}">${r.water||'N/A'}</span></td></tr>`).join('');
    } catch (e) { console.error('[Admin] History error:', e); }
}

// init
function init() {
    cacheElements();
    notifications.init();
    fetchSensorData();
    setInterval(fetchSensorData, CONFIG.updateInterval);

    // auto-refresh admin history if tab is active
    setInterval(() => {
        const ht = document.getElementById('admin-tab-history');
        if (ht?.classList.contains('active')) refreshAdminHistory();
    }, 10000);

    // close dropdown on outside click
    document.addEventListener('click', (e) => {
        const dd = document.querySelector('.account-dropdown');
        if (dd && !dd.contains(e.target)) {
            document.getElementById('accountMenu').classList.remove('show');
            document.querySelector('.account-btn').setAttribute('aria-expanded', 'false');
        }
    });

    // close dropdown on Escape
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            const menu = document.getElementById('accountMenu');
            const btn = document.querySelector('.account-btn');
            if (menu?.classList.contains('show')) { menu.classList.remove('show'); btn.setAttribute('aria-expanded', 'false'); btn.focus(); }
        }
    });
}

document.addEventListener('DOMContentLoaded', init);
