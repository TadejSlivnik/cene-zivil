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
});
