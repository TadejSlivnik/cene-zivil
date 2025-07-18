/*
 * Welcome to your app's main JavaScript file!
 *
 * We recommend including the built version of this JavaScript file
 * (and its CSS file) in your base layout (base.html.twig).
 */

// any CSS you import will output into a single css file (app.css in this case)
import './styles/app.css';

// start the Stimulus application
// import './bootstrap';

// import 'typeface-inter';

// enable the interactive UI components from Flowbite
import 'flowbite';

import Chart from 'chart.js/auto';

// https://scanapp.org/html5-qrcode-docs/docs/intro
// To use Html5QrcodeScanner (more info below)
import { Html5QrcodeScanner } from "html5-qrcode";

// To use Html5Qrcode (more info below)
import { Html5Qrcode } from "html5-qrcode";

import Tablesort from 'tablesort';
document.addEventListener('DOMContentLoaded', () => {
    const table = document.getElementById('product-list');
    if (table) {
        // Initialize Tablesort
        const tablesort = new Tablesort(table);

        // locale sorting fix for č, š, ž
        Tablesort.extend('text', function (item) {
            return !item.includes('€') && !item.includes('%');
        }, function (a, b) {
            return a.localeCompare(b, 'sl');
        });

        // Add a custom sorter for numeric columns
        Tablesort.extend('number', (item) => {
            return item.includes('€') || item.includes('%');
        }, (a, b) => {
            // Parse the numeric values and compare them
            const numA = parseFloat(a.replace(/[^\d.-]/g, '')) || 0;
            const numB = parseFloat(b.replace(/[^\d.-]/g, '')) || 0;
            return numB - numA;
        });

        // Add event listener to update column classes
        table.addEventListener('afterSort', () => {
            const headers = table.querySelectorAll('th');
            headers.forEach((header) => {
                header.classList.remove('sorted-asc', 'sorted-desc');
            });

            // Find the currently sorted column
            const sortedHeader = table.querySelector('th.sortable.sorted');
            if (sortedHeader) {
                if (sortedHeader.classList.contains('asc')) {
                    sortedHeader.classList.add('sorted-asc');
                } else if (sortedHeader.classList.contains('desc')) {
                    sortedHeader.classList.add('sorted-desc');
                }
            }
        });
    }

    // bind the click event to the fetch buttons
    const fetchButtons = document.querySelectorAll('.fetch-button');
    fetchButtons.forEach((fetchButton) => {
        fetchButton.addEventListener('click', () => {
            const dataId = fetchButton.getAttribute('data-id');
            if (dataId) {
                fetch(`pin/${dataId}`)
                    .then((data) => {
                        const svg = fetchButton.querySelector('svg');
                        if (svg.classList.contains('fill-red-700')) {
                            svg.classList.remove('fill-red-700');
                            svg.setAttribute('stroke-width', '1');
                        } else {
                            svg.classList.add('fill-red-700');
                            svg.setAttribute('stroke-width', '0');
                        }
                    })
                    .catch((error) => {
                        console.error('Error fetching pin:', error);
                    });
            }
        });
    })

    // Store the chart instance globally
    let priceChart = null;

    document.querySelectorAll('.open-chart').forEach(button => {
        button.addEventListener('click', () => {
            const id = button.getAttribute('data-id');
            fetch(`/chart/${id}`).then(res => res.json()).then(data => {
                const ctx = document.getElementById('myChart');

                // Create chart title from product name and store
                const chartTitle = data.title + ' - ' + data.trgovina;

                // Destroy the previous chart if it exists
                if (priceChart) {
                    priceChart.destroy();
                }

                // Create a new chart
                priceChart = new Chart(ctx, {
                    type: 'line',
                    data: data,
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            title: {
                                display: true,
                                text: chartTitle,
                                font: {
                                    size: 16,
                                    weight: 'bold'
                                },
                                padding: {
                                    bottom: 20
                                }
                            },
                            legend: {
                                position: 'top',
                            },
                            tooltip: {
                                mode: 'index',
                                intersect: false,
                                callbacks: {
                                    label: function (context) {
                                        const datasetIndex = context.datasetIndex;
                                        const dataIndex = context.dataIndex;
                                        const dataset = context.chart.data.datasets[datasetIndex];

                                        // Use formatted currency values if available
                                        if (dataset.formattedData && dataset.formattedData[dataIndex]) {
                                            return dataset.label + ': ' + dataset.formattedData[dataIndex];
                                        }
                                        // Fallback to raw values
                                        return dataset.label + ': ' + context.parsed.y + ' €';
                                    }
                                }
                            }
                        },
                        hover: {
                            mode: 'nearest',
                            intersect: true
                        },
                        scales: {
                            y: {
                                beginAtZero: false
                            }
                        }
                    }
                });
            });
        });
    });

    // QR Code scanner implementation
    let html5QrScanner = null;

    function onScanSuccess(decodedText, decodedResult) {
        // handle the scanned code - set as search term and submit the form
        console.log(`Code matched = ${decodedText}`, decodedResult);
        
        // Close the QR scanner modal
        const qrScannerModal = document.getElementById('qrScannerModal');
        if (qrScannerModal && window.Flowbite) {
            window.Flowbite.Modal.getOrCreateInstance(document.getElementById('qrScannerModal')).hide();
        }
        
        // Stop the scanner
        if (html5QrScanner) {
            html5QrScanner.clear();
        }
        
        // Set the scanned text as search term and submit the form
        const searchInput = document.querySelector('input[name="q"]');
        if (searchInput) {
            searchInput.value = decodedText;
            const searchForm = document.getElementById('search-form');
            if (searchForm) {
                searchForm.submit();
            }
        }
    }

    function onScanFailure(error) {
        // handle scan failure, usually better to ignore and keep scanning.
        console.warn(`Code scan error = ${error}`);
    }
    
    // Initialize QR scanner when the modal is opened
    const qrScanButton = document.getElementById('qr-scan-button');
    if (qrScanButton) {
        qrScanButton.addEventListener('click', () => {
            // Initialize scanner after a small delay to ensure the modal is visible
            setTimeout(() => {
                if (!html5QrScanner) {
                    // Get container width for responsive QR box sizing
                    const readerElement = document.getElementById('reader');
                    const containerWidth = readerElement ? readerElement.clientWidth : 300;
                    const qrboxSize = Math.min(containerWidth - 50, 250); // Responsive QR box size
                    
                    html5QrScanner = new Html5QrcodeScanner("reader", { 
                        fps: 10, 
                        qrbox: { width: qrboxSize, height: qrboxSize },
                        rememberLastUsedCamera: true,
                        aspectRatio: 1.0
                    });
                    html5QrScanner.render(onScanSuccess, onScanFailure);
                }
            }, 300);
        });
    }
    
    // Handle scanner modal close buttons
    const qrScannerCloseBtn = document.getElementById('qrScannerCloseBtn');
    
    function stopScanner() {
        if (html5QrScanner) {
            html5QrScanner.clear();
            html5QrScanner = null;
        }
    }
    
    if (qrScannerCloseBtn) {
        qrScannerCloseBtn.addEventListener('click', stopScanner);
    }
    
    // Also handle modal hidden event to ensure scanner is stopped
    document.addEventListener('hidden.bs.modal', function (event) {
        if (event.target.id === 'qrScannerModal') {
            stopScanner();
        }
    });
});
