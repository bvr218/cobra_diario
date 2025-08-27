import './bootstrap';

import ApexCharts from 'apexcharts';
window.ApexCharts = ApexCharts;

// Importa el componente para CADA gráfico individual
import chartComponent from './components/chartComponent';

// --- INICIO DE LA MODIFICACIÓN ---
// 1. Importa el NUEVO gestor del modal de pantalla completa
import fullscreenChartManager from './components/fullscreenChartManager';
// --- FIN DE LA MODIFICACIÓN ---

document.addEventListener('alpine:init', () => {
    // Registra el componente para los gráficos individuales (esto ya lo tenías)
    Alpine.data('chartComponent', chartComponent);

    // --- INICIO DE LA MODIFICACIÓN ---
    // 2. Registra la función del gestor para que esté disponible en tu HTML.
    //    Usamos `Alpine.data` para mantenerlo consistente con el resto de tu código.
    //    Ahora, en tu HTML, puedes usar `x-data="fullscreenChartManager"`.
    //    Nota: He ajustado el HTML en mi respuesta anterior para que use `fullscreenChartManager()`
    //    por lo que es mejor registrarlo en el objeto window para que funcione como una función.
    //    Ambos métodos funcionan, pero este se alinea con el HTML ya proporcionado.
    
    // Hacemos la función globalmente accesible para que `x-data="fullscreenChartManager()"` funcione.
    window.fullscreenChartManager = fullscreenChartManager;
    // --- FIN DE LA MODIFICACIÓN ---
});