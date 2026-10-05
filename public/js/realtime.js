/**
 * Koneksi real-time (Laravel Reverb + Echo, file statis di public/assets/vendor/libs/echo).
 *
 * Semua user login bergabung ke presence channel `online`. Daftar member dikelola
 * Reverb di memori, jadi tidak ada polling/query database untuk status online.
 *
 * Halaman yang sedang dibuka dikirim sekali sebagai parameter otorisasi saat join
 * (bukan pesan antar-browser), sehingga satu kali pindah halaman hanya memicu
 * notifikasi keluar/masuk ke tiap member: beban linear terhadap jumlah user.
 *
 *   Realtime.onMembers(list => ...)   // [{ id, nik, name, role, page, title }]
 *   Realtime.onStatus(state => ...)   // 'connecting' | 'connected' | 'unavailable' | ...
 */
window.Realtime = (function () {
    'use strict';

    const configEl = document.getElementById('realtime-config');

    if (!configEl || !window.Echo || !window.Pusher) {
        return null;
    }

    const config = JSON.parse(configEl.textContent);
    const members = new Map();
    const memberListeners = [];
    const statusListeners = [];
    let status = 'connecting';

    const echo = new window.Echo.default({
        broadcaster: 'reverb',
        key: config.key,
        wsHost: config.host,
        wsPort: config.port,
        wssPort: config.port,
        forceTLS: config.scheme === 'https',
        enabledTransports: ['ws', 'wss'],
        authEndpoint: config.authEndpoint,
        // `headers` wajib ada: Echo mengisi X-CSRF-TOKEN ke objek ini.
        auth: {
            headers: {},
            params: { page: window.location.pathname, title: document.title }
        }
    });

    const list = () => Array.from(members.values()).sort((a, b) => a.name.localeCompare(b.name));

    function emitMembers() {
        const current = list();

        document.querySelectorAll('[data-online-count]').forEach(el => {
            el.textContent = current.length;
        });

        memberListeners.forEach(listener => listener(current));
    }

    function setStatus(value) {
        status = value;
        statusListeners.forEach(listener => listener(status));
    }

    echo.join('online')
        .here(users => {
            members.clear();
            users.forEach(user => members.set(user.id, user));
            emitMembers();
        })
        .joining(user => {
            members.set(user.id, user);
            emitMembers();
        })
        .leaving(user => {
            members.delete(user.id);
            emitMembers();
        })
        .error(() => setStatus('unavailable'));

    const connection = echo.connector.pusher.connection;
    connection.bind('state_change', states => setStatus(states.current));
    setStatus(connection.state);

    return {
        echo,
        members: list,
        status: () => status,
        onMembers(listener) {
            memberListeners.push(listener);
            listener(list());
        },
        onStatus(listener) {
            statusListeners.push(listener);
            listener(status);
        }
    };
})();
