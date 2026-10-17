/* AMCS realtime client (Laravel Reverb / Pusher protocol).
 * Subscribes to a private channel and signals the page whenever the live
 * stand board changes. Pages keep a polling fallback if the socket is
 * unavailable, so operational data is never stale for long.
 */
window.AMCSRealtime = (function () {
    'use strict';

    let echo = null;
    let channel = null;
    let connected = false;

    function meta(name) {
        const el = document.querySelector('meta[name="' + name + '"]');
        return el && el.content ? el.content : '';
    }

    function echoConstructor() {
        if (! window.Echo) {
            return null;
        }

        // The IIFE build exposes the module object ({ default: Echo }), while
        // module bundlers expose the constructor directly.
        return typeof window.Echo === 'function' ? window.Echo : (window.Echo.default || null);
    }

    function enabled() {
        return meta('amcs-broadcast') === '1'
            && meta('amcs-reverb-key') !== ''
            && typeof echoConstructor() === 'function';
    }

    function wsHost() {
        const configured = meta('amcs-reverb-host');

        // "localhost" only works on the machine running the browser. When the
        // page is opened through another host/IP, connect to that same host.
        if (! configured || configured === 'localhost' || configured === '127.0.0.1') {
            return window.location.hostname;
        }

        return configured;
    }

    function connect(onUpdate, onStatus) {
        if (! enabled()) {
            return false;
        }

        if (echo) {
            connected = true;
            return true;
        }

        try {
            const EchoConstructor = echoConstructor();

            echo = new EchoConstructor({
                broadcaster: 'reverb',
                key: meta('amcs-reverb-key'),
                wsHost: wsHost(),
                wsPort: parseInt(meta('amcs-reverb-port') || '8080', 10),
                wssPort: parseInt(meta('amcs-reverb-port') || '8080', 10),
                forceTLS: meta('amcs-reverb-scheme') === 'https',
                enabledTransports: ['ws', 'wss'],
                authEndpoint: meta('amcs-broadcast-auth') || '/broadcasting/auth',
                auth: {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                },
                disableStats: true,
            });
        } catch (error) {
            console.warn('[AMCS] Realtime connection could not be initialised.', error);
            echo = null;
            return false;
        }

        channel = echo.private(meta('amcs-realtime-channel') || 'amcs.stands');

        channel.listen('.stand-board.updated', function (payload) {
            if (typeof onUpdate === 'function') {
                onUpdate(payload || {});
            }
        });

        try {
            const connection = echo.connector.pusher.connection;

            const markConnected = function () {
                connected = true;
                if (typeof onStatus === 'function') onStatus(true);
            };

            const markDisconnected = function () {
                connected = false;
                if (typeof onStatus === 'function') onStatus(false);
            };

            connection.bind('connected', markConnected);
            connection.bind('disconnected', markDisconnected);
            connection.bind('unavailable', markDisconnected);
            connection.bind('failed', markDisconnected);

            if (connection.state === 'connected') {
                markConnected();
            }
        } catch (error) {
            console.warn('[AMCS] Realtime status binding failed.', error);
        }

        return true;
    }

    function disconnect() {
        if (echo) {
            try {
                echo.disconnect();
            } catch (error) {
                /* ignore */
            }
        }

        echo = null;
        channel = null;
        connected = false;
    }

    return {
        connect: connect,
        disconnect: disconnect,
        isConnected: function () { return connected; },
        isEnabled: enabled,
    };
})();
