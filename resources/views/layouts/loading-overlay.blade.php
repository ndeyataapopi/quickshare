<!-- Loading Overlay to prevent flickering -->
<div id="loading-overlay" style="position:fixed;top:0;left:0;width:100%;height:100%;background:#fff;z-index:9999;display:flex;align-items:center;justify-content:center;">
    <div class="text-center">
        <div class="spinner-border text-primary" role="status" style="width:3rem;height:3rem;">
            <span class="sr-only">Loading...</span>
        </div>
        <p class="mt-3 text-muted">Loading QuickShare...</p>
    </div>
</div>

<script>
// Hide loading overlay when page is fully loaded
window.addEventListener('load', function() {
    setTimeout(function() {
        const overlay = document.getElementById('loading-overlay');
        if (overlay) {
            overlay.style.transition = 'opacity 0.3s ease-out';
            overlay.style.opacity = '0';
            setTimeout(() => overlay.remove(), 300);
        }
    }, 100);
});

// Prevent FOUC (Flash of Unstyled Content)
document.documentElement.classList.add('loading');
</script>

<style>
/* Prevent FOUC */
html.loading body {
    visibility: hidden;
}
html.loading body.loaded {
    visibility: visible;
}

/* Smooth transitions for all interactive elements */
.btn, .card, .table, .form-control {
    transition: all 0.2s ease-in-out;
}

/* Loading states */
.loading-skeleton {
    background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
    background-size: 200% 100%;
    animation: loading 1.5s infinite;
}

@keyframes loading {
    0% { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}

/* Prevent text flickering during updates */
.text-updating {
    opacity: 0.6;
    transition: opacity 0.2s ease;
}
</style>
