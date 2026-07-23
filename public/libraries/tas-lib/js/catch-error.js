(function (window, document) {
    const config = {
        reportUrl: `${appUrl}/api/js-error`,
        maxClicks: 10,
        enableClicks: true,
        localKey: 'catchjs_offline_errors'
    };

    let clickLog = [];

    function getPendingErrors() {
        try {
            return JSON.parse(localStorage.getItem(config.localKey) || '[]');
        } catch {
            return [];
        }
    }

    function savePendingErrors(errors) {
        localStorage.setItem(config.localKey, JSON.stringify(errors));
    }

    function trySendPendingErrors() {
        const errors = getPendingErrors();
        if (errors.length === 0) return;

        const remaining = [];

        errors.forEach(error => {
            fetch(config.reportUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(error)
            }).catch(() => {
                remaining.push(error); // gagal lagi, simpan
            });
        });

        savePendingErrors(remaining);
    }

    function sendErrorReport(data) {
        if (navigator.onLine) {
            fetch(config.reportUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            }).catch(() => {
                // gagal kirim, simpan ke localStorage
                const pending = getPendingErrors();
                pending.push(data);
                savePendingErrors(pending);
            });
        } else {
            const pending = getPendingErrors();
            pending.push(data);
            savePendingErrors(pending);
        }
    }

    function captureError(message, source, lineno, colno, error) {
        const errorData = {
            message,
            source,
            lineno,
            colno,
            stack: error && error.stack,
            name: error && error.name,
            url: location.href,
            width: window.innerWidth,
            height: window.innerHeight,
            userAgent: navigator.userAgent,
            clicks: clickLog,
            timestamp: new Date().toISOString()
        };

        sendErrorReport(errorData);
    }

    // Global error & console error
    window.onerror = captureError;

    if (console && console.error) {
        const originalConsoleError = console.error;
        console.error = function (...args) {
            console.log(args[0] instanceof Error);
            
            if (args[0] instanceof Error) {
                captureError(args[0].message, '', 0, 0, args[0]);
            }
            return originalConsoleError.apply(console, args);
        };
    }

    // Promise rejection
    window.addEventListener('unhandledrejection', function (event) {
        const error = event.reason;
        captureError(error?.message || 'Unhandled rejection', '', 0, 0, error);
    });

    // Click capture
    if (config.enableClicks) {
        document.addEventListener('click', function (e) {
            const path = [];
            let el = e.target;
            while (el && el !== document) {
                let desc = el.tagName.toLowerCase();
                if (el.id) desc += `#${el.id}`;
                if (el.className) desc += `.${el.className.toString().replace(/\s+/g, '.')}`;
                path.unshift(desc);
                el = el.parentElement;
            }

            clickLog.push({
                x: e.pageX,
                y: e.pageY,
                html: e.target.outerHTML.slice(0, 100),
                path: path.join(' > '),
                time: new Date().toISOString()
            });

            if (clickLog.length > config.maxClicks) clickLog.shift();
        });
    }

    // Saat koneksi balik online, coba kirim ulang error tertunda
    window.addEventListener('online', trySendPendingErrors);
    // Juga coba langsung saat pertama kali load
    trySendPendingErrors();

})(window, document);
