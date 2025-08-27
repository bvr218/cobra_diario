// resources/js/components/fullscreenChartManager.js

import ApexCharts from 'apexcharts';

function fullscreenChartManager() {
    return {
        isModalOpen: false,
        modalChart: null,
        chartConfigurations: {},

        registerChart(detail) {
            this.chartConfigurations[detail.chartId] = detail.options;
        },

        openModal(chartId) {
            if (!this.chartConfigurations[chartId]) {
                console.error('Configuración no encontrada para el gráfico:', chartId);
                return;
            }

            this.isModalOpen = true;

            this.$nextTick(() => {
                // Hacemos una copia profunda de la configuración para no alterar la original.
                let modalOptions = JSON.parse(JSON.stringify(this.chartConfigurations[chartId]));

                // Ajustes básicos para que el gráfico ocupe todo el modal.
                modalOptions.chart.height = '100%';
                modalOptions.chart.width = '100%';

                // --- INICIO DE LA LÓGICA CORREGIDA Y CENTRALIZADA ---
                // Si el gráfico es el de actividad diaria, FORZAMOS la configuración
                // a la vista de pantalla completa deseada (barras verticales),
                // sin importar cómo estuviera el gráfico original.
                if (chartId === 'dailyActivity') {

                    // 1. Forzar barras verticales (orientación landscape)
                    if (modalOptions.plotOptions && modalOptions.plotOptions.bar) {
                        modalOptions.plotOptions.bar.horizontal = false;
                    }

                    // 2. Forzar los títulos de los ejes para que coincidan con las barras verticales.
                    //    En lugar de intercambiar, los definimos explícitamente. Esto evita errores.
                    if (modalOptions.xaxis && modalOptions.xaxis.title) {
                        modalOptions.xaxis.title.text = 'Día del Mes';
                    }
                    if (modalOptions.yaxis && modalOptions.yaxis.title) {
                        modalOptions.yaxis.title.text = 'Cantidad de Operaciones';
                    }

                    // 3. Forzar el estilo de las etiquetas para la vista de barras verticales.
                    //    Esto es clave para la legibilidad en pantalla completa.
                    if (modalOptions.xaxis && modalOptions.xaxis.labels) {
                        if (!modalOptions.xaxis.labels.style) modalOptions.xaxis.labels.style = {};
                        modalOptions.xaxis.labels.rotate = -45; // Rotar los días del mes
                        modalOptions.xaxis.labels.style.fontSize = '12px';
                        modalOptions.xaxis.labels.style.textAnchor = 'end';
                    }
                    if (modalOptions.yaxis && modalOptions.yaxis.labels && modalOptions.yaxis.labels.style) {
                         modalOptions.yaxis.labels.style.fontSize = '12px'; // Tamaño de letra para los números
                    }
                }
                // --- FIN DE LA LÓGICA CORREGIDA ---


                // Destruimos cualquier gráfico modal anterior para evitar fugas de memoria.
                if (this.modalChart) {
                    this.modalChart.destroy();
                }

                // Creamos la nueva instancia del gráfico en el modal con las opciones ya corregidas.
                this.modalChart = new ApexCharts(this.$refs.modalChartContainer, modalOptions);
                this.modalChart.render();
            });
        },

        closeModal() {
            this.isModalOpen = false;
            if (this.modalChart) {
                this.modalChart.destroy();
                this.modalChart = null;
            }
        }
    };
}

export default fullscreenChartManager;