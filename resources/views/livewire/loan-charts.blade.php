<div
    wire:ignore
    x-data="{
        init() {
            // Función para cargar Chart.js y su plugin si no están ya disponibles
            const loadChartJsAndPlugin = (callback) => {
                if (window.Chart && typeof Chart === 'function' && window.ChartDataLabels) {
                    return callback();
                }

                const scriptChart = document.createElement('script');
                scriptChart.src = 'https://cdn.jsdelivr.net/npm/chart.js';
                scriptChart.onload = () => {
                    const scriptPlugin = document.createElement('script');
                    scriptPlugin.src = 'https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0/dist/chartjs-plugin-datalabels.min.js';
                    scriptPlugin.onload = () => {
                        if (window.Chart && typeof window.Chart === 'function' && window.ChartDataLabels) {
                            callback();
                        } else {
                            console.error('Chart.js o plugin datalabels no se cargaron correctamente.');
                        }
                    };
                    document.head.appendChild(scriptPlugin);
                };
                document.head.appendChild(scriptChart);
            };

            // Función CLAVE para determinar COLORES según el MODO
            const getDynamicChartColors = () => {
                const isDarkMode = document.documentElement.classList.contains('dark');
                return {
                    textColor: isDarkMode ? '#E0E0E0' : 'rgb(4, 2, 22)', // Color general del texto
                    gridColor: isDarkMode ? 'rgba(255, 255, 255, 0.15)' : 'rgb(75, 73, 73)', // Color de las líneas de la cuadrícula
                    dataLabelsPieColor: '#FFFFFF', // Color del texto de las etiquetas en el gráfico de pastel
                    tooltipBgColor: isDarkMode ? 'rgba(30, 41, 59, 0.9)' : 'rgba(255, 255, 255, 0.9)', // Fondo del tooltip
                    tooltipTextColor: isDarkMode ? '#FFFFFF' : '#000000', // Color del texto dentro del tooltip
                };
            };

            // Función para inicializar/actualizar el gráfico de pastel
            const initPie = (data) => {
                let ctx = document.getElementById('loanPieChart');
                const noData = document.getElementById('noDataMessage');

                if (!ctx || !data || !window.Chart) {
                    console.warn('Falta el contexto del canvas, datos o Chart.js para el gráfico de pastel.');
                    return;
                }

                // Destruir el gráfico existente si lo hay
                if (window.loanPieChart && typeof window.loanPieChart.destroy === 'function') {
                    window.loanPieChart.destroy();
                }

                // Eliminar y recrear el canvas para una limpieza total del DOM
                const parentDiv = ctx.parentNode;
                const newCanvas = document.createElement('canvas');
                newCanvas.id = 'loanPieChart';
                newCanvas.className = 'w-full h-full';
                parentDiv.removeChild(ctx);
                parentDiv.appendChild(newCanvas);
                ctx = newCanvas;

                Chart.defaults.maintainAspectRatio = false;

                if (data.labels.length > 0 && data.data.some(v => v > 0)) {
                    ctx.style.display = 'block';
                    noData.style.display = 'none';

                    ctx.removeAttribute('width');
                    ctx.removeAttribute('height');
                    ctx.style.width = '100%';
                    ctx.style.height = '100%';
                    ctx.style.boxSizing = 'border-box';

                    const total = data.data.reduce((acc, val) => acc + val, 0);
                    const colors = getDynamicChartColors();

                    window.loanPieChart = new Chart(ctx, {
                        type: 'pie',
                        data: {
                            labels: data.labels,
                            datasets: [{
                                data: data.data,
                                backgroundColor: data.colors,
                                hoverOffset: 4
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            aspectRatio: undefined,
                            plugins: {
                                legend: {
                                    position: 'bottom',
                                    labels: {
                                        color: colors.textColor,
                                        boxWidth: 14,
                                        padding: 10,
                                        font: {
                                            size: 12
                                        }
                                    }
                                },
                                tooltip: {
                                    // *** Aquí es donde ajustamos el fondo del tooltip ***
                                    backgroundColor: colors.tooltipBgColor,
                                    titleColor: colors.tooltipTextColor, // Título del tooltip
                                    bodyColor: colors.tooltipTextColor,  // Cuerpo del tooltip
                                    callbacks: {
                                        label: function(context) {
                                            const label = context.label || '';
                                            const value = context.parsed;
                                            const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                            return `${label}: ${value} (${percentage}%)`;
                                        }
                                    },
                                },
                                datalabels: {
                                    color: colors.dataLabelsPieColor,
                                    font: {
                                        weight: 'bold',
                                        size: 12
                                    },
                                    formatter: function(value) {
                                        if (total === 0) return '';
                                        const percentage = ((value / total) * 100).toFixed(1);
                                        return percentage + '%';
                                    }
                                }
                            }
                        },
                        plugins: [ChartDataLabels]
                    });

                    setTimeout(() => {
                        if (window.loanPieChart) {
                            window.loanPieChart.resize();
                        }
                    }, 200);
                } else {
                    ctx.style.display = 'none';
                    noData.style.display = 'block';
                }
            };

            // Función para inicializar/actualizar el gráfico de líneas
            const initLine = (data) => {
                let ctx = document.getElementById('loanLineChart');
                const noData = document.getElementById('noLineDataMessage');

                if (!ctx || !data || !window.Chart) {
                     console.warn('Falta el contexto del canvas, datos o Chart.js para el gráfico de líneas.');
                    return;
                }

                // Destruir el gráfico existente si lo hay
                if (window.loanLineChart && typeof window.loanLineChart.destroy === 'function') {
                    window.loanLineChart.destroy();
                }

                // Eliminar y recrear el canvas para una limpieza total del DOM
                const parentDiv = ctx.parentNode;
                const newCanvas = document.createElement('canvas');
                newCanvas.id = 'loanLineChart';
                newCanvas.className = 'w-full h-full';
                parentDiv.removeChild(ctx);
                parentDiv.appendChild(newCanvas);
                ctx = newCanvas;

                Chart.defaults.maintainAspectRatio = false;

                if (data.labels.length > 0 && data.data.some(v => v !== null)) {
                    ctx.style.display = 'block';
                    noData.style.display = 'none';

                    ctx.removeAttribute('width');
                    ctx.removeAttribute('height');
                    ctx.style.width = '100%';
                    ctx.style.height = '100%';
                    ctx.style.boxSizing = 'border-box';

                    const colors = getDynamicChartColors();

                    window.loanLineChart = new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: data.labels,
                            datasets: [{
                                label: 'Base en caja diaria',
                                data: data.data,
                                borderColor: '#3B82F6',
                                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                                fill: true,
                                tension: 0.3,
                                pointBackgroundColor: '#3B82F6'
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    display: true,
                                    labels: {
                                        color: colors.textColor,
                                        font: {
                                            size: 12
                                        }
                                    }
                                },
                                tooltip: {
                                    mode: 'index',
                                    intersect: false,
                                    // *** Aquí es donde ajustamos el fondo del tooltip ***
                                    backgroundColor: colors.tooltipBgColor,
                                    titleColor: colors.tooltipTextColor, // Título del tooltip
                                    bodyColor: colors.tooltipTextColor,  // Cuerpo del tooltip
                                    callbacks: {
                                        label: function(context) {
                                            let label = context.dataset.label || '';
                                            if (label) {
                                                label += ': ';
                                            }
                                            if (context.parsed.y !== null) {
                                                label += context.parsed.y.toLocaleString();
                                            }
                                            return label;
                                        }
                                    }
                                }
                            },
                            scales: {
                                x: {
                                    ticks: {
                                        color: colors.textColor
                                    },
                                    grid: {
                                        color: colors.gridColor
                                    }
                                },
                                y: {
                                    beginAtZero: true,
                                    ticks: {
                                        color: colors.textColor,
                                        callback: function(value) {
                                            return value.toLocaleString();
                                        }
                                    },
                                    grid: {
                                        color: colors.gridColor
                                    }
                                }
                            }
                        }
                    });

                    setTimeout(() => {
                        if (window.loanLineChart) {
                            window.loanLineChart.resize();
                        }
                    }, 200);
                } else {
                    ctx.style.display = 'none';
                    noData.style.display = 'block';
                }
            };

            const renderAllCharts = () => {
                initPie(@js($pieChartData));
                initLine(@js($lineChartData));
            };

            loadChartJsAndPlugin(() => {
                renderAllCharts();
            });

            const observer = new MutationObserver((mutations) => {
                mutations.forEach((mutation) => {
                    if (mutation.type === 'attributes' && mutation.attributeName === 'class') {
                        renderAllCharts();
                    }
                });
            });
            observer.observe(document.documentElement, { attributes: true });

            window.Livewire.on('chartDataUpdated', (detail) => {
                // CAMBIO AQUÍ: Acceder directamente a 'detail' si 'pieChartData' es el único parámetro nombrado,
                // o si Livewire lo envía como un objeto con esa propiedad.
                // Para ser más seguros, verificamos si detail.pieChartData existe, si no, usamos detail.
                initPie(detail.pieChartData || detail);
            });

            window.Livewire.on('lineChartDataUpdated', (detail) => {
                initLine(detail.lineChartData);
            });
        }
    }"
    x-init="init()"
    class="mx-auto mt-6 max-w-7xl px-4"
>
    <div class="flex flex-col lg:flex-row gap-10">
        <div class="w-full lg:w-1/2">
            <h3 class="text-base sm:text-lg font-semibold text-center mb-4 text-gray-900 dark:text-gray-100">
                Estado Actual de Préstamos
            </h3>
            <div class="relative h-44 sm:h-48 md:h-52 w-full lg:w-[28rem] lg:h-[28rem] xl:w-[36rem] xl:h-[36rem] mx-auto flex justify-center items-center">
                <canvas id="loanPieChart" class="w-full h-full"></canvas>
                <p id="noDataMessage" class="text-center text-sm text-gray-500 dark:text-gray-400 mt-4 hidden">
                    No hay datos de préstamos disponibles para la gráfica.
                </p>
            </div>
        </div>

        <div class="w-full lg:w-1/2">
            <h3 class="text-base sm:text-lg font-semibold text-center mb-4 text-gray-900 dark:text-gray-100">
                Evolución en Caja(Mes Actual)
            </h3>
            <div class="relative h-52 sm:h-60 md:h-64 w-full flex justify-center items-center">
                <canvas id="loanLineChart" class="w-full h-full"></canvas>
                <p id="noLineDataMessage" class="text-center text-sm text-gray-500 dark:text-gray-400 mt-4 hidden">
                    No hay datos disponibles para mostrar la evolución diaria.
                </p>
            </div>
        </div>
    </div>
</div>