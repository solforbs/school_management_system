@props([
    'type' => 'area', // area, line, bar
    'series' => [],
    'labels' => [],
    'height' => 300,
    'colors' => ['#4f46e5', '#10b981', '#0ea5e9', '#f59e0b', '#f43f5e'],
    'currencyPrefix' => '',
])

@php
    $id = 'chart-' . uniqid();
    // Default to the primary accent color if not enough colors are provided
    $chartColors = json_encode(array_pad($colors, count($series), '#4f46e5'));
    $prefixJs = json_encode($currencyPrefix);
@endphp

<div id="{{ $id }}" style="height: {{ $height }}px; width: 100%;" wire:ignore x-data x-init="
    if (typeof ApexCharts !== 'undefined') {
        window['initChart_{{ md5($id) }}']();
    } else {
        let checkInterval = setInterval(() => {
            if (typeof ApexCharts !== 'undefined') {
                window['initChart_{{ md5($id) }}']();
                clearInterval(checkInterval);
            }
        }, 50);
    }
"></div>

@push('scripts')
    {{-- Ensure ApexCharts is loaded via CDN --}}
    @once
        <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    @endonce
    <script>
        window['initChart_{{ md5($id) }}'] = function() {
            if(document.querySelector('#{{ $id }}').innerHTML !== '') return; // Already initialized

            const prefix = {!! $prefixJs !!};

            var options = {
                series: @json($series),
                chart: {
                    type: '{{ $type }}',
                    height: {{ $height }},
                    toolbar: { show: false },
                    zoom: { enabled: false },
                    fontFamily: 'inherit',
                    animations: {
                        enabled: true,
                        easing: 'easeinout',
                        speed: 800,
                    }
                },
                colors: {!! $chartColors !!},
                dataLabels: { enabled: false },
                stroke: {
                    curve: 'smooth',
                    width: 2.5
                },
                fill: {
                    type: '{{ $type === "area" ? "gradient" : "solid" }}',
                    gradient: {
                        shadeIntensity: 1,
                        opacityFrom: 0.35,
                        opacityTo: 0.05,
                        stops: [0, 90, 100]
                    }
                },
                xaxis: {
                    categories: @json($labels),
                    axisBorder: { show: false },
                    axisTicks: { show: false },
                    labels: {
                        style: {
                            colors: '#a1a1aa',
                            fontSize: '11px',
                            fontWeight: 600,
                        }
                    }
                },
                yaxis: {
                    labels: {
                        style: {
                            colors: '#a1a1aa',
                            fontSize: '11px',
                            fontWeight: 500,
                        },
                        formatter: function (value) {
                            if (!value && value !== 0) return '0';
                            if (prefix) {
                                if (Math.abs(value) >= 1000000) {
                                    return prefix + (value / 1000000).toFixed(1) + 'M';
                                } else if (Math.abs(value) >= 1000) {
                                    return prefix + (value / 1000).toFixed(0) + 'K';
                                }
                                return prefix + value.toLocaleString();
                            }
                            return value.toLocaleString();
                        }
                    }
                },
                grid: {
                    borderColor: '#f4f4f5',
                    strokeDashArray: 4,
                    yaxis: { lines: { show: true } },
                    xaxis: { lines: { show: false } },
                    padding: { top: 5, right: 10, bottom: 0, left: 10 }
                },
                legend: {
                    show: true,
                    position: 'top',
                    horizontalAlign: 'right',
                    fontSize: '12px',
                    fontWeight: 600,
                    markers: {
                        radius: 12
                    }
                },
                tooltip: {
                    theme: document.documentElement.classList.contains('dark') ? 'dark' : 'light',
                    y: {
                        formatter: function (val) {
                            if (prefix) {
                                return prefix + Number(val).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                            }
                            return Number(val).toLocaleString();
                        }
                    }
                }
            };

            var chart = new ApexCharts(document.querySelector("#{{ $id }}"), options);
            chart.render();
        }
    </script>
@endpush
