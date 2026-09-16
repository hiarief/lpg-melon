<script>
    var STORAGE_KEY = 'rekap_gas_desktop_mode';

    function applyDesktopMode(isDesktop) {
        if (isDesktop) {
            document.body.classList.add('desktop-mode');
        } else {
            document.body.classList.remove('desktop-mode');
        }
    }

    function toggleDesktopMode() {
        var isCurrentlyDesktop = document.body.classList.contains('desktop-mode');
        var newState = !isCurrentlyDesktop;
        try {
            localStorage.setItem(STORAGE_KEY, newState ? '1' : '0');
        } catch(e) {}
        applyDesktopMode(newState);
    }

    (function() {
        var saved;
        try { saved = localStorage.getItem(STORAGE_KEY); } catch(e) {}
        if (saved === null) {
            saved = window.innerWidth >= 1024 ? '1' : '0';
        }
        applyDesktopMode(saved === '1');
    })();
</script>
