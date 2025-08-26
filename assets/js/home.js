// Home page JavaScript functionality
console.log('Home.js loaded successfully'); // Initialization check

document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM Content Loaded'); // Debug log
    // Sort products functionality
    const sortSelect = document.getElementById('sort-products');
    if (sortSelect) {
        sortSelect.addEventListener('change', sortProducts);
    }

    // Sidebar functionality
    const menuIcon = document.querySelector('.menu-icon');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlay');
    
    if (menuIcon) {
        menuIcon.addEventListener('click', function(e) {
            e.preventDefault();
            toggleSidebar();
        });
    }
    
    if (overlay) {
        overlay.addEventListener('click', closeSidebar);
    }

    // Submenu toggle functionality
    const submenuToggles = document.querySelectorAll('.submenu-toggle');
    console.log('Found submenu toggles:', submenuToggles.length); // Debug log
    
    submenuToggles.forEach(toggle => {
        console.log('Adding listener to:', toggle); // Debug log
        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            const submenuId = this.getAttribute('data-submenu');
            console.log('Submenu toggle clicked:', submenuId); // Debug log
            toggleSubmenu(submenuId);
        });
    });

    // Chat functionality will be handled by chat.js

    // Profile dropdown functionality
    const profileIcon = document.querySelector('.profile-icon');
    if (profileIcon) {
        profileIcon.addEventListener('click', function(e) {
            e.stopPropagation();
            const dropdown = this.querySelector('.profile-dropdown');
            if (dropdown) {
                dropdown.classList.toggle('show');
            }
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function() {
            const dropdown = document.querySelector('.profile-dropdown');
            if (dropdown) {
                dropdown.classList.remove('show');
            }
        });
    }

    // Keyboard navigation for products
    const products = document.querySelectorAll('.product');
    products.forEach(product => {
        product.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                const link = this.querySelector('.product-img-link');
                if (link) {
                    link.click();
                }
            }
        });
    });

    // Add loading state to forms
    const addToCartForms = document.querySelectorAll('form[action="add_to_cart.php"]');
    addToCartForms.forEach(form => {
        form.addEventListener('submit', function() {
            const button = this.querySelector('.add-to-cart-btn');
            if (button) {
                button.disabled = true;
                button.innerHTML = 'Adding...';
                
                // Re-enable after 2 seconds (fallback)
                setTimeout(() => {
                    button.disabled = false;
                    button.innerHTML = 'Add to Cart';
                }, 2000);
            }
        });
    });

    // Lazy loading for images
    const imageObserver = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const img = entry.target;
                img.src = img.dataset.src;
                img.classList.remove('lazy');
                observer.unobserve(img);
            }
        });
    });

    const lazyImages = document.querySelectorAll('img[data-src]');
    lazyImages.forEach(img => imageObserver.observe(img));
});

function sortProducts() {
    const sortValue = document.getElementById('sort-products').value;
    const urlParams = new URLSearchParams(window.location.search);
    urlParams.set('sort', sortValue);
    
    // Add loading state
    const container = document.querySelector('.product-container');
    if (container) {
        container.style.opacity = '0.5';
        container.style.pointerEvents = 'none';
    }
    
    window.location.search = urlParams.toString();
}

function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlay');
    
    if (sidebar && overlay) {
        sidebar.classList.toggle('active');
        overlay.classList.toggle('active');
        
        // Update aria-expanded for accessibility
        const menuButton = document.querySelector('.menu-icon');
        const isExpanded = sidebar.classList.contains('active');
        if (menuButton) {
            menuButton.setAttribute('aria-expanded', isExpanded);
        }
        
        // Focus management
        if (isExpanded) {
            const firstLink = sidebar.querySelector('a');
            if (firstLink) {
                setTimeout(() => firstLink.focus(), 100);
            }
        }
    }
}

function closeSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlay');
    const menuButton = document.querySelector('.menu-icon');
    
    sidebar.classList.remove('active');
    overlay.classList.remove('active');
    
    if (menuButton) {
        menuButton.setAttribute('aria-expanded', 'false');
        menuButton.focus();
    }
}

function toggleSubmenu(id) {
    console.log('toggleSubmenu called with id:', id); // Debug log
    const submenu = document.getElementById(id);
    const parentLink = document.querySelector(`[data-submenu="${id}"]`);
    
    console.log('Found elements:', {submenu, parentLink}); // Debug log
    
    if (submenu) {
        submenu.classList.toggle('show');
        console.log('Submenu classes after toggle:', submenu.className); // Debug log
        
        // Update aria-expanded for accessibility
        if (parentLink) {
            const isExpanded = submenu.classList.contains('show');
            parentLink.setAttribute('aria-expanded', isExpanded);
            console.log('Updated aria-expanded to:', isExpanded); // Debug log
        }
    } else {
        console.error('Submenu not found with id:', id);
    }
}

// Search enhancements
function enhanceSearch() {
    const searchBox = document.querySelector('.search-box');
    const searchForm = document.querySelector('.search-form');
    
    if (searchBox && searchForm) {
        let searchTimeout;
        
        // Add search suggestions (basic implementation)
        searchBox.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            const query = this.value.trim();
            
            if (query.length >= 2) {
                searchTimeout = setTimeout(() => {
                    // You can implement search suggestions here
                    console.log('Search suggestions for:', query);
                }, 300);
            }
        });
        
        // Prevent empty searches
        searchForm.addEventListener('submit', function(e) {
            const query = searchBox.value.trim();
            if (!query) {
                e.preventDefault();
                searchBox.focus();
                searchBox.placeholder = 'Please enter a search term';
                setTimeout(() => {
                    searchBox.placeholder = 'Search...';
                }, 2000);
            }
        });
    }
}

// Initialize search enhancements
document.addEventListener('DOMContentLoaded', enhanceSearch);
