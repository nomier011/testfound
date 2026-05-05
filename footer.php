    <!-- Footer -->
    <footer class="site-footer">
        <div class="footer-content">
            <p>&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. All rights reserved.</p>
            <p class="footer-credit">St. Cecilia's College - Cebu, Inc.</p>
        </div>
    </footer>
    
    <style>
    .site-footer {
        text-align: center;
        padding: 20px;
        margin-top: 40px;
        background: rgba(0,0,0,0.6);
        backdrop-filter: blur(10px);
        color: rgba(255,255,255,0.7);
        font-size: 0.8rem;
    }
    .footer-credit {
        font-size: 0.7rem;
        margin-top: 5px;
        opacity: 0.6;
    }
    @media (max-width: 768px) {
        .site-footer {
            padding: 15px;
            margin-top: 20px;
        }
    }
    </style>
    
    <script>
    // Close sidebar when clicking outside on mobile
    document.addEventListener('click', function(event) {
        const sidebar = document.querySelector('.sidebar');
        const menuToggle = document.querySelector('.menu-toggle');
        
        if (window.innerWidth <= 768 && sidebar && sidebar.classList.contains('active')) {
            if (!sidebar.contains(event.target) && !menuToggle?.contains(event.target)) {
                sidebar.classList.remove('active');
            }
        }
    });
    
    // Prevent form double submission
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function() {
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            }
        });
    });
    </script>
</body>
</html>
<script>
function toggleMobileMenu() {
    const drawer  = document.getElementById('mobileDrawer');
    const overlay = document.getElementById('drawerOverlay');
    const btn     = document.getElementById('hamburgerBtn');
    if (!drawer) return;
    const open = drawer.classList.toggle('open');
    overlay.classList.toggle('open', open);
    btn && btn.classList.toggle('open', open);
}
// Close drawer on resize
window.addEventListener('resize', () => {
    if (window.innerWidth > 768) {
        const d = document.getElementById('mobileDrawer');
        const o = document.getElementById('drawerOverlay');
        const b = document.getElementById('hamburgerBtn');
        d && d.classList.remove('open');
        o && o.classList.remove('open');
        b && b.classList.remove('open');
    }
});
</script>