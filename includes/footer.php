</main> <script>
/**
 * Danan Foods Application Mobile Navigation Handler Engine
 */
function toggleMobileMenu() {
    const bodyElement = document.body;
    
    // Toggle mobile drawer visibility rule state classes
    if (bodyElement.classList.contains('mobile-menu-open')) {
        bodyElement.classList.remove('mobile-menu-open');
        bodyElement.style.overflow = ''; 
    } else {
        bodyElement.classList.add('mobile-menu-open');
        bodyElement.style.overflow = 'hidden'; // Stop background content shifting
    }
}

// Window resize listener safety gate
window.addEventListener('resize', () => {
    if (window.innerWidth > 992 && document.body.classList.contains('mobile-menu-open')) {
        document.body.classList.remove('mobile-menu-open');
        document.body.style.overflow = '';
    }
});
</script>
</body>
</html>