/* ============================================================
   Jagd-Verwaltung: Gemeinsames JavaScript
   ============================================================ */

'use strict';

/* --- DataTables Standard-Initialisierung ---
   Alle Tabellen mit Klasse .dt-tabelle werden automatisch initialisiert */
$(function () {
    $('.dt-tabelle').DataTable(Object.assign({}, dtDeutsch, {
        pageLength: 25,
        responsive: true,
    }));
});

/* --- Toast-Benachrichtigung anzeigen ---
   Aufruf: jagdToast('Gespeichert!', 'success')
   Typen: success | danger | warning | info */
function jagdToast(nachricht, typ = 'success') {
    const id   = 'toast_' + Date.now();
    const html = `
        <div id="${id}" class="toast align-items-center text-bg-${typ} border-0" role="alert">
            <div class="d-flex">
                <div class="toast-body">${nachricht}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto"
                        data-bs-dismiss="toast"></button>
            </div>
        </div>`;
    document.getElementById('toastContainer').insertAdjacentHTML('beforeend', html);
    const toastEl = document.getElementById(id);
    new bootstrap.Toast(toastEl, { delay: 3500 }).show();
    toastEl.addEventListener('hidden.bs.toast', () => toastEl.remove());
}

/* --- AJAX CSRF-Helper ---
   Liest den CSRF-Token aus dem Meta-Tag (wird in header.php gesetzt) */
function getCsrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
}

/* --- AJAX POST mit CSRF ---
   Aufruf: ajaxPost('/modules/reviermanagement/api/foo.php', {id: 1}, callback) */
function ajaxPost(url, daten, callback) {
    daten.csrf_token = getCsrfToken();
    fetch(url, {
        method:  'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': getCsrfToken() },
        body:    JSON.stringify(daten),
    })
    .then(r => r.json())
    .then(callback)
    .catch(err => jagdToast('Fehler: ' + err.message, 'danger'));
}

/* --- Leaflet-Hilfsfunktion: Koordinaten-Picker ---
   Gibt ein Leaflet-Objekt zurück mit Drag-Marker für GPS-Eingabe.
   lat/lng: Startwerte (optional)
   onMove: Callback (lat, lng) wenn Marker verschoben wird */
function leafletPicker(mapId, lat, lng, onMove) {
    const startLat = lat || window.KARTE_LAT || 49.28;
    const startLng = lng || window.KARTE_LNG || 8.53;
    const zoom     = lat ? 15 : (window.KARTE_ZOOM || 14);

    const map = L.map(mapId).setView([startLat, startLng], zoom);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        maxZoom: 19,
    }).addTo(map);

    const marker = L.marker([startLat, startLng], { draggable: true }).addTo(map);

    marker.on('dragend', function () {
        const pos = marker.getLatLng();
        if (typeof onMove === 'function') onMove(pos.lat, pos.lng);
    });

    map.on('click', function (e) {
        marker.setLatLng(e.latlng);
        if (typeof onMove === 'function') onMove(e.latlng.lat, e.latlng.lng);
    });

    return { map, marker };
}

/* --- Lösch-Bestätigung für alle Buttons mit data-confirm ---
   <button data-confirm="Wirklich löschen?"> */
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-confirm]').forEach(function (el) {
        el.addEventListener('click', function (e) {
            if (!confirm(el.getAttribute('data-confirm'))) {
                e.preventDefault();
            }
        });
    });
});
