/**
 * SIGEVEM - Map Picker JavaScript
 * Funcionalidad para selección de ubicación con Leaflet
 */

class MapPicker {
    constructor(containerId, options = {}) {
        this.container = containerId;
        this.map = null;
        this.marker = null;
        this.selectedCoords = null;
        
        // Coordenadas de Ecatepec por defecto
        this.defaultLat = options.lat || 19.6012;
        this.defaultLon = options.lon || -99.0597;
        this.zoom = options.zoom || 15;
        
        this.init();
    }
    
    init() {
        this.map = L.map(this.container).setView([this.defaultLat, this.defaultLon], this.zoom);
        
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(this.map);
        
        this.map.on('click', (e) => {
            this.setCoordinates(e.latlng.lat, e.latlng.lng);
        });
    }
    
    setCoordinates(lat, lng) {
        this.selectedCoords = { lat, lng };
        
        if (this.marker) {
            this.marker.setLatLng([lat, lng]);
        } else {
            this.marker = L.marker([lat, lng]).addTo(this.map);
        }
        
        this.map.setView([lat, lng], this.zoom);
        
        // Callback si existe
        if (typeof this.onCoordinateSelect === 'function') {
            this.onCoordinateSelect(lat, lng);
        }
    }
    
    clearCoordinates() {
        this.selectedCoords = null;
        
        if (this.marker) {
            this.map.removeLayer(this.marker);
            this.marker = null;
        }
        
        if (typeof this.onCoordinateClear === 'function') {
            this.onCoordinateClear();
        }
    }
    
    loadCoordinates(lat, lng) {
        this.setCoordinates(lat, lng);
    }
    
    onCoordinateSelect(callback) {
        this.onCoordinateSelect = callback;
    }
    
    onCoordinateClear(callback) {
        this.onCoordinateClear = callback;
    }
    
    getCoordinates() {
        return this.selectedCoords;
    }
}

// Exportar para uso global
window.MapPicker = MapPicker;