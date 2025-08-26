// Simple sidebar test
document.addEventListener('DOMContentLoaded', function() {
    console.log('Sidebar script loaded');
    
    const menuIcon = document.querySelector('.menu-icon');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlay');
    
    console.log('Elements found:', {
        menuIcon: !!menuIcon,
        sidebar: !!sidebar,
        overlay: !!overlay
    });
    
    if (menuIcon) {
        menuIcon.addEventListener('click', function(e) {
            console.log('Menu clicked');
            if (sidebar) {
                sidebar.classList.toggle('active');
                console.log('Sidebar classes:', sidebar.className);
            }
            if (overlay) {
                overlay.classList.toggle('active');
                console.log('Overlay classes:', overlay.className);
            }
        });
    }
    
    if (overlay) {
        overlay.addEventListener('click', function() {
            console.log('Overlay clicked');
            sidebar.classList.remove('active');
            overlay.classList.remove('active');
        });
    }
});
