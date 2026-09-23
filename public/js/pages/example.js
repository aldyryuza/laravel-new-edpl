/**
 * JS halaman contoh.
 *
 * Pola: semua logic halaman ada di file ini, bukan di Blade.
 */
'use strict';

$(function () {
    $('#example-table').DataTable({
        // ajax: { url: App.url('api/examples'), dataSrc: 'data' },
        data: [],
        columns: [
            { data: 'id', title: '#' },
            { data: 'name', title: 'Nama' },
            { data: 'status', title: 'Status' },
            { data: null, orderable: false, searchable: false, defaultContent: '' }
        ],
        language: { emptyTable: 'Belum ada data' }
    });
});
