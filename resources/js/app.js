// Importa tu configuración de Bootstrap (Axios, Echo, etc.)
import './bootstrap';

// Importa Chart.js y expón la clase globalmente
import Chart from 'chart.js/auto';
window.Chart = Chart;

// Si en el futuro necesitas añadir más librerías o inicializaciones,
// puedes hacerlo debajo de estas líneas.

document.addEventListener('alpine:init', () => {
    Alpine.data('printModal', () => ({
        print() {
            // Encuentra el elemento que contiene todo el contenido que quieres imprimir
            const modalContent = document.querySelector('.printable-modal');

            if (modalContent) {
                // Abre una nueva ventana en blanco para la impresión
                const printWindow = window.open('', '_blank', 'height=600,width=800');

                // Construye el HTML de la nueva ventana
                printWindow.document.write('<!DOCTYPE html><html><head><title>Historial del Cliente</title>');

                // **INCLUYE AQUÍ LOS ESTILOS NECESARIOS PARA LA IMPRESIÓN**
                // Puedes copiar tus estilos existentes o simplificarlos.
                // Es crucial que estos estilos se escriban directamente, no que se referencien
                printWindow.document.write('<style>');
                printWindow.document.write(`
                    body {
                        font-family: sans-serif;
                        margin: 0;
                        padding: 10mm; /* Un padding para los bordes de la página impresa */
                        color: #333;
                    }
                    h3 {
                        font-size: 14pt;
                        margin-top: 20px;
                        margin-bottom: 10px;
                        color: #000;
                    }
                    p {
                        font-size: 9pt;
                        margin-bottom: 10px;
                    }
                    table {
                        width: 100%;
                        border-collapse: collapse;
                        margin-bottom: 20px;
                        font-size: 9pt;
                    }
                    th, td {
                        border: 1px solid #ccc;
                        padding: 6px 8px;
                        text-align: left;
                    }
                    thead {
                        background-color: #f0f0f0;
                    }
                    /* Estilos específicos para texto azul y subrayado en celdas si aplica */
                    .text-blue-600 { color: #2563eb; }
                    .hover\\:underline { text-decoration: underline; }
                `);
                printWindow.document.write('</style>');

                printWindow.document.write('</head><body>');

                // Clona el contenido del modal para evitar manipular el DOM original
                const clonedContent = modalContent.cloneNode(true);

                // Elimina los elementos "no-print" del contenido clonado antes de imprimir
                const noPrintElements = clonedContent.querySelectorAll('.no-print');
                noPrintElements.forEach(el => el.remove());

                // Añade el contenido clonado y limpiado a la nueva ventana
                printWindow.document.write(clonedContent.outerHTML);

                printWindow.document.write('</body></html>');
                printWindow.document.close(); // Cierra el documento para renderizarlo

                // Llama a la función de impresión nativa de la nueva ventana
                printWindow.print();

                // Cierra la ventana de impresión después de que el usuario interactúe (imprimir o cancelar)
                printWindow.onafterprint = function() {
                    printWindow.close();
                };

            } else {
                console.error('El elemento con la clase .printable-modal no fue encontrado para imprimir.');
            }
        }
    }));
});