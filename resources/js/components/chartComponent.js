// resources/js/components/chartComponent.js

export default (initialState) => ({
    chart: null,
    data: initialState.data,
    type: initialState.type,
    chartId: initialState.chartId,
    isPortrait: window.innerWidth < window.innerHeight,
    resizeHandler: null,

    /**
     * INICIALIZACIÓN
     * Ya no recibe parámetros.
     */
    init() {
        // LA CORRECCIÓN FINAL:
        // En lugar de un parámetro, usamos la propiedad mágica `this.$el`
        // que Alpine proporciona al componente.
        if (this.$el._x_is_initialized) {
            return;
        }
        this.$el._x_is_initialized = true;

        this.resizeHandler = this.handleResize.bind(this);
        window.addEventListener('resize', this.resizeHandler);
        
        Livewire.on('update-chart-data', this.handleUpdate.bind(this));
        
        this.renderChart();
    },
    
    destroy() {
        window.removeEventListener('resize', this.resizeHandler);
        if (this.chart) {
            this.chart.destroy();
        }
        this.chart = null;
    },
    
    // ... El resto del archivo (handleResize, renderChart, etc.) no necesita cambios ...

    handleResize() {
        const currentlyIsPortrait = window.innerWidth < window.innerHeight;
        if (currentlyIsPortrait !== this.isPortrait) {
            this.isPortrait = currentlyIsPortrait;
            this.updateChart(this.data);
        }
    },

    renderChart() {
        if (this.chart) { this.chart.destroy(); }
        if (!this.$refs.chartContainer) { return; }

        this.$nextTick(() => {
            const options = this.getChartOptions();
            this.$dispatch('register-chart', { chartId: this.chartId, options: options });
            this.chart = new window.ApexCharts(this.$refs.chartContainer, options);
            this.chart.render();
        });
    },

    updateChart(newData) {
        this.data = newData;
        if (this.chart) {
            const newOptions = this.getChartOptions();
            this.$dispatch('register-chart', { chartId: this.chartId, options: newOptions });
            this.chart.updateOptions(newOptions, true);
        } else {
            this.renderChart();
        }
    },

    handleUpdate(eventDetail) {
        if (eventDetail.chartId === this.chartId) {
            this.updateChart(eventDetail.data);
        }
    },

    getChartOptions() {
        const commonOptions = {
            chart: { height: 350, fontFamily: 'inherit', animations: { enabled: true } },
            dataLabels: { enabled: false },
            legend: { position: 'top', labels: { colors: document.body.classList.contains('dark') ? '#E5E7EB' : '#374151' } },
            grid: { borderColor: document.body.classList.contains('dark') ? '#4B5563' : '#E5E7EB', strokeDashArray: 4 },
            tooltip: { theme: document.body.classList.contains('dark') ? 'dark' : 'light' },
            series: this.data.series || [],
        };

        if (this.type === 'daily') {
            const isMobile = window.innerWidth < 1024;
            const shouldBeHorizontal = isMobile && this.isPortrait;

            return {
                ...commonOptions,
                chart: { ...commonOptions.chart, type: 'bar', stacked: true, toolbar: { show: false } },
                plotOptions: { bar: { horizontal: shouldBeHorizontal, borderRadius: 4 } },
                colors: ['#34D399', '#60A5FA'],
                stroke: { width: 1, colors: ['#fff'] },
                xaxis: {
                    categories: this.data.categories || [],
                    title: { text: shouldBeHorizontal ? 'Cantidad de Operaciones' : 'Día del Mes', style: { color: document.body.classList.contains('dark') ? '#9CA3AF' : '#6B7280' } },
                    labels: {
                        style: { colors: document.body.classList.contains('dark') ? '#9CA3AF' : '#6B7280' },
                        rotate: shouldBeHorizontal ? 0 : -45,
                        rotateAlways: !shouldBeHorizontal,
                        textAnchor: shouldBeHorizontal ? 'middle' : 'end'
                    }
                },
                yaxis: {
                    title: { text: shouldBeHorizontal ? 'Día del Mes' : 'Cantidad de Operaciones' },
                    labels: {
                        style: {
                            colors: document.body.classList.contains('dark') ? '#E5E7EB' : '#374151',
                            fontSize: shouldBeHorizontal ? '8px' : '11px'
                        }
                    }
                },
                tooltip: { ...commonOptions.tooltip, y: { formatter: (val) => val + " operaciones" } }
            };
        }

        if (this.type === 'comparison') {
            return {
                ...commonOptions,
                chart: { ...commonOptions.chart, type: 'bar', toolbar: { show: false } },
                plotOptions: { bar: { horizontal: true, barHeight: '80%', borderRadius: 4, dataLabels: { position: 'top' } } },
                colors: ['#34D399', '#60A5FA'],
                dataLabels: { enabled: true, offsetX: -6, style: { fontSize: '12px', colors: ['#fff'] }, formatter: (val) => val > 0 ? val : '' },
                stroke: { show: true, width: 1, colors: ['#fff'] },
                xaxis: { categories: this.data.categories || [], labels: { style: { colors: document.body.classList.contains('dark') ? '#9CA3AF' : '#6B7280' } } },
                yaxis: { labels: { show: true, style: { colors: document.body.classList.contains('dark') ? '#E5E7EB' : '#374151' } } },
                tooltip: { ...commonOptions.tooltip, shared: true, intersect: false, y: { formatter: (val) => val + " operaciones" } }
            };
        }

        return commonOptions;
    }
});