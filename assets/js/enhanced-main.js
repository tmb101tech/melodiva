// Enhanced Melodiva Skincare - Main JavaScript File

document.addEventListener("DOMContentLoaded", () => {
    // Initialize all components
    initializeCart()
    initializeForms()
    initializeAnimations()
    initializeSearch()
    initializeNavbar()
    initializeScrollEffects()
    initializeTooltips()
})

// Enhanced navbar functionality
function initializeNavbar() {
    const navbar = document.getElementById('mainNavbar');
    if (navbar) {
        window.addEventListener('scroll', function() {
            if (window.scrollY > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        });
    }
}

// Enhanced scroll effects
function initializeScrollEffects() {
    // Parallax effect for hero section
    const heroSection = document.querySelector('.hero-section');
    if (heroSection) {
        window.addEventListener('scroll', function() {
            const scrolled = window.pageYOffset;
            const rate = scrolled * -0.3;
            heroSection.style.transform = `translateY(${rate}px)`;
        });
    }
    
    // Reveal animations on scroll
    const revealElements = document.querySelectorAll('.product-card, .card, .hero-content > *');
    const revealObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('fade-in');
                revealObserver.unobserve(entry.target);
            }
        });
    }, {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    });
    
    revealElements.forEach(element => {
        revealObserver.observe(element);
    });
}

// Enhanced cart functionality
function initializeCart() {
    // Add to cart buttons with enhanced animation
    document.querySelectorAll(".add-to-cart").forEach((button) => {
        button.addEventListener("click", function (e) {
            e.preventDefault()
            const productId = this.dataset.productId || this.getAttribute('onclick')?.match(/\d+/)?.[0];
            const quantity = this.closest(".product-card")?.querySelector(".quantity-input")?.value || 1

            if (productId) {
                addToCartWithAnimation(productId, quantity, this)
            }
        })
    })

    // Enhanced quantity controls
    document.querySelectorAll(".quantity-btn").forEach((button) => {
        button.addEventListener("click", function () {
            const input = this.parentNode.querySelector(".quantity-input")
            const isIncrement = this.classList.contains("increment")
            let value = parseInt(input.value)

            if (isIncrement) {
                value++
            } else if (value > 1) {
                value--
            }

            input.value = value

            // Animate the change
            input.style.transform = 'scale(1.1)';
            setTimeout(() => {
                input.style.transform = 'scale(1)';
            }, 150);

            // Update cart if on cart page
            if (this.dataset.cartId) {
                updateCartQuantity(this.dataset.cartId, value)
            }
        })
    })

    // Remove from cart with confirmation
    document.querySelectorAll(".remove-from-cart").forEach((button) => {
        button.addEventListener("click", function (e) {
            e.preventDefault()
            const cartId = this.dataset.cartId
            
            // Enhanced confirmation dialog
            if (confirm('Are you sure you want to remove this item from your cart?')) {
                removeFromCart(cartId, this)
            }
        })
    })
}

// Enhanced add to cart function
function addToCartWithAnimation(productId, quantity = 1, button = null) {
    if (!button) {
        button = event?.target?.closest('.add-to-cart');
    }
    
    if (!button) return;
    
    const originalText = button.innerHTML;
    const btnText = button.querySelector('.btn-text');
    const btnLoader = button.querySelector('.btn-loader');
    
    // Show loading state
    button.disabled = true;
    if (btnText && btnLoader) {
        btnText.style.opacity = '0';
        btnLoader.style.opacity = '1';
    } else {
        button.innerHTML = '<div class="spinner"></div>';
    }

    fetch("api/cart.php", {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify({
            action: "add",
            product_id: productId,
            quantity: quantity,
        }),
    })
    .then((response) => response.json())
    .then((data) => {
        if (data.success) {
            // Success animation
            if (btnText && btnLoader) {
                btnLoader.style.opacity = '0';
                btnText.innerHTML = '<i class="fas fa-check me-2"></i>Added!';
                btnText.style.opacity = '1';
            } else {
                button.innerHTML = '<i class="fas fa-check"></i> Added!';
            }
            
            button.classList.remove('btn-primary');
            button.classList.add('btn-success');
            
            // Update cart count with animation
            updateCartCount();
            
            // Show floating success message
            showFloatingMessage('Product added to cart!', 'success');
            
            // Reset button after 2 seconds
            setTimeout(() => {
                button.disabled = false;
                button.classList.remove('btn-success');
                button.classList.add('btn-primary');
                
                if (btnText && btnLoader) {
                    btnText.innerHTML = originalText.includes('btn-text') ? 
                        originalText.match(/<span class="btn-text">(.*?)<\/span>/)?.[1] || 'Add to Cart' : 
                        '<i class="fas fa-cart-plus me-2"></i>Add to Cart';
                } else {
                    button.innerHTML = originalText;
                }
            }, 2000);
        } else {
            // Reset button state
            button.disabled = false;
            if (btnText && btnLoader) {
                btnLoader.style.opacity = '0';
                btnText.style.opacity = '1';
            } else {
                button.innerHTML = originalText;
            }
            
            showFloatingMessage(data.message || "Error adding product to cart", "danger");
        }
    })
    .catch((error) => {
        // Reset button state
        button.disabled = false;
        if (btnText && btnLoader) {
            btnLoader.style.opacity = '0';
            btnText.style.opacity = '1';
        } else {
            button.innerHTML = originalText;
        }
        
        showFloatingMessage("Error adding product to cart", "danger");
        console.error("Error:", error);
    });
}

// Enhanced cart quantity update
function updateCartQuantity(cartId, quantity) {
    showLoadingOverlay();
    
    fetch("api/cart.php", {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify({
            action: "update",
            cart_id: cartId,
            quantity: quantity,
        }),
    })
    .then((response) => response.json())
    .then((data) => {
        hideLoadingOverlay();
        
        if (data.success) {
            // Smooth page reload with fade effect
            document.body.style.opacity = '0.7';
            setTimeout(() => {
                location.reload();
            }, 300);
        } else {
            showFloatingMessage(data.message || "Error updating cart", "danger");
        }
    })
    .catch((error) => {
        hideLoadingOverlay();
        showFloatingMessage("Error updating cart", "danger");
        console.error("Error:", error);
    });
}

// Enhanced remove from cart
function removeFromCart(cartId, button = null) {
    if (button) {
        button.disabled = true;
        button.innerHTML = '<div class="spinner"></div>';
    }
    
    fetch("api/cart.php", {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify({
            action: "remove",
            cart_id: cartId,
        }),
    })
    .then((response) => response.json())
    .then((data) => {
        if (data.success) {
            // Animate item removal
            const cartItem = button?.closest('.cart-item');
            if (cartItem) {
                cartItem.style.transform = 'translateX(-100%)';
                cartItem.style.opacity = '0';
                setTimeout(() => {
                    location.reload();
                }, 300);
            } else {
                location.reload();
            }
        } else {
            if (button) {
                button.disabled = false;
                button.innerHTML = '<i class="fas fa-trash"></i> Remove';
            }
            showFloatingMessage(data.message || "Error removing item", "danger");
        }
    })
    .catch((error) => {
        if (button) {
            button.disabled = false;
            button.innerHTML = '<i class="fas fa-trash"></i> Remove';
        }
        showFloatingMessage("Error removing item", "danger");
        console.error("Error:", error);
    });
}

// Enhanced cart count update with animation
function updateCartCount() {
    fetch("api/cart.php?action=count")
        .then((response) => response.json())
        .then((data) => {
            const cartBadges = document.querySelectorAll(".cart-count");
            cartBadges.forEach(badge => {
                const currentCount = parseInt(badge.textContent) || 0;
                const newCount = data.count || 0;
                
                if (newCount !== currentCount) {
                    // Animate count change
                    badge.style.transform = 'scale(1.5)';
                    badge.style.background = '#28a745';
                    
                    setTimeout(() => {
                        badge.textContent = newCount;
                        badge.style.transform = 'scale(1)';
                        badge.style.background = '';
                    }, 200);
                }
            });
        })
        .catch((error) => {
            console.error("Error updating cart count:", error);
        });
}

// Enhanced form handling
function initializeForms() {
    const forms = document.querySelectorAll('form');
    
    forms.forEach(form => {
        // Add floating label effects
        const inputs = form.querySelectorAll('input, textarea, select');
        inputs.forEach(input => {
            // Check if input has value on load
            if (input.value) {
                input.parentElement.classList.add('focused');
            }
            
            input.addEventListener('focus', function() {
                this.parentElement.classList.add('focused');
            });
            
            input.addEventListener('blur', function() {
                if (!this.value) {
                    this.parentElement.classList.remove('focused');
                }
            });
            
            // Real-time validation
            input.addEventListener('input', function() {
                validateField(this);
            });
        });
        
        // Enhanced form submission
        form.addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn && submitBtn.classList.contains('btn-animated')) {
                const btnText = submitBtn.querySelector('.btn-text');
                const btnLoader = submitBtn.querySelector('.btn-loader');
                
                if (btnText && btnLoader) {
                    submitBtn.disabled = true;
                    btnText.style.opacity = '0';
                    btnLoader.style.opacity = '1';
                }
            }
        });
    });
}

function validateField(field) {
    const value = field.value.trim();
    const type = field.type;
    
    // Remove existing validation classes
    field.classList.remove('is-valid', 'is-invalid');
    
    if (!value && field.hasAttribute('required')) {
        field.classList.add('is-invalid');
        return false;
    }
    
    // Email validation
    if (type === 'email' && value) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (emailRegex.test(value)) {
            field.classList.add('is-valid');
        } else {
            field.classList.add('is-invalid');
        }
    }
    
    // Password validation
    if (type === 'password' && value) {
        if (value.length >= 6) {
            field.classList.add('is-valid');
        } else {
            field.classList.add('is-invalid');
        }
    }
    
    return true;
}

// Enhanced animations
function initializeAnimations() {
    // Intersection Observer for scroll animations
    const observerOptions = {
        threshold: 0.1,
        rootMargin: "0px 0px -50px 0px",
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                entry.target.classList.add("fade-in");
                
                // Stagger animation for multiple elements
                const siblings = Array.from(entry.target.parentElement.children);
                const index = siblings.indexOf(entry.target);
                entry.target.style.animationDelay = `${index * 0.1}s`;
            }
        });
    }, observerOptions);

    // Observe all animatable elements
    document.querySelectorAll(".product-card, .card, .stats-card").forEach((element) => {
        observer.observe(element);
    });
}

// Enhanced search functionality
function initializeSearch() {
    const searchInput = document.getElementById("searchInput");
    if (searchInput) {
        let searchTimeout;
        
        searchInput.addEventListener("input", function() {
            clearTimeout(searchTimeout);
            const query = this.value.trim();
            
            if (query.length >= 2) {
                // Show loading indicator
                this.classList.add('loading');
                
                searchTimeout = setTimeout(() => {
                    performSearch(query);
                }, 300);
            } else {
                this.classList.remove('loading');
                clearSearchResults();
            }
        });
    }
}

function performSearch(query) {
    const searchInput = document.getElementById("searchInput");
    const searchResults = document.getElementById("searchResults");
    
    if (!searchResults) return;
    
    fetch(`api/search.php?q=${encodeURIComponent(query)}`)
        .then((response) => response.json())
        .then((data) => {
            searchInput.classList.remove('loading');
            displaySearchResults(data.results || []);
        })
        .catch((error) => {
            searchInput.classList.remove('loading');
            console.error("Search error:", error);
            showFloatingMessage("Search error occurred", "danger");
        });
}

function displaySearchResults(results) {
    const resultsContainer = document.getElementById("searchResults");
    if (!resultsContainer) return;

    if (results.length === 0) {
        resultsContainer.innerHTML = `
            <div class="col-12">
                <div class="text-center py-5">
                    <i class="fas fa-search fa-3x text-muted mb-3"></i>
                    <h4 class="text-muted">No products found</h4>
                    <p class="text-muted">Try adjusting your search terms</p>
                </div>
            </div>
        `;
        return;
    }

    const resultsHTML = results.map((product, index) => `
        <div class="col-md-4 mb-4" style="animation-delay: ${index * 0.1}s">
            <div class="product-card hover-lift fade-in">
                <img src="${product.image}" alt="${product.name}" class="product-image">
                <div class="product-info">
                    <h5 class="product-title">${product.name}</h5>
                    <p class="product-type">${product.type} - ${product.size}</p>
                    <p class="product-price">${formatPrice(product.price)}</p>
                    <button class="btn btn-primary btn-animated add-to-cart w-100" onclick="addToCartWithAnimation(${product.id})">
                        <span class="btn-text">
                            <i class="fas fa-cart-plus me-2"></i>Add to Cart
                        </span>
                        <div class="btn-loader">
                            <div class="spinner"></div>
                        </div>
                    </button>
                </div>
            </div>
        </div>
    `).join('');

    resultsContainer.innerHTML = resultsHTML;
}

function clearSearchResults() {
    const resultsContainer = document.getElementById("searchResults");
    if (resultsContainer) {
        resultsContainer.innerHTML = '';
    }
}

// Enhanced tooltips
function initializeTooltips() {
    // Initialize Bootstrap tooltips with custom options
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl, {
            animation: true,
            delay: { show: 500, hide: 100 }
        });
    });
}

// Enhanced floating messages
function showFloatingMessage(message, type = "info", duration = 4000) {
    const messageContainer = getOrCreateMessageContainer();
    
    const messageElement = document.createElement('div');
    messageElement.className = `floating-message floating-message-${type}`;
    
    const icon = getMessageIcon(type);
    messageElement.innerHTML = `
        <i class="fas fa-${icon} me-2"></i>
        <span>${message}</span>
        <button class="btn-close" onclick="this.parentElement.remove()"></button>
    `;
    
    messageContainer.appendChild(messageElement);
    
    // Animate in
    setTimeout(() => {
        messageElement.classList.add('show');
    }, 10);
    
    // Auto-remove
    if (duration > 0) {
        setTimeout(() => {
            removeFloatingMessage(messageElement);
        }, duration);
    }
}

function getMessageIcon(type) {
    const icons = {
        'success': 'check-circle',
        'danger': 'exclamation-circle',
        'warning': 'exclamation-triangle',
        'info': 'info-circle'
    };
    return icons[type] || 'info-circle';
}

function removeFloatingMessage(element) {
    element.classList.add('hide');
    setTimeout(() => {
        element.remove();
    }, 300);
}

function getOrCreateMessageContainer() {
    let container = document.getElementById('floatingMessages');
    if (!container) {
        container = document.createElement('div');
        container.id = 'floatingMessages';
        container.className = 'floating-messages-container';
        document.body.appendChild(container);
    }
    return container;
}

// Enhanced loading overlay
function showLoadingOverlay() {
    let overlay = document.getElementById('loadingOverlay');
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.id = 'loadingOverlay';
        overlay.className = 'loading-overlay';
        overlay.innerHTML = `
            <div class="loading-content">
                <div class="spinner-large"></div>
                <p>Loading...</p>
            </div>
        `;
        document.body.appendChild(overlay);
    }
    overlay.classList.add('show');
}

function hideLoadingOverlay() {
    const overlay = document.getElementById('loadingOverlay');
    if (overlay) {
        overlay.classList.remove('show');
    }
}

// Utility functions
function formatPrice(price) {
    return "₦" + parseFloat(price).toLocaleString("en-NG", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Enhanced error handling
window.addEventListener('error', function(e) {
    console.error('JavaScript error:', e.error);
    // Only show user-friendly errors for critical issues
    if (e.error.message.includes('fetch')) {
        showFloatingMessage('Connection error. Please check your internet connection.', 'danger');
    }
});

// Enhanced performance monitoring
if ('performance' in window) {
    window.addEventListener('load', function() {
        setTimeout(() => {
            const perfData = performance.getEntriesByType('navigation')[0];
            const loadTime = perfData.loadEventEnd - perfData.loadEventStart;
            
            if (loadTime > 3000) {
                console.warn('Slow page load detected:', loadTime, 'ms');
            }
        }, 0);
    });
}

// Initialize cart count on page load
updateCartCount();

// Add CSS for floating messages and loading overlay
const additionalStyles = `
<style>
.floating-messages-container {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 9999;
    max-width: 400px;
}

.floating-message {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(20px);
    border-radius: 12px;
    padding: 1rem 1.5rem;
    margin-bottom: 0.5rem;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
    border-left: 4px solid;
    display: flex;
    align-items: center;
    transform: translateX(100%);
    opacity: 0;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.floating-message.show {
    transform: translateX(0);
    opacity: 1;
}

.floating-message.hide {
    transform: translateX(100%);
    opacity: 0;
}

.floating-message-success {
    border-left-color: #28a745;
    color: #155724;
}

.floating-message-danger {
    border-left-color: #dc3545;
    color: #721c24;
}

.floating-message-warning {
    border-left-color: #ffc107;
    color: #856404;
}

.floating-message-info {
    border-left-color: #17a2b8;
    color: #0c5460;
}

.floating-message .btn-close {
    background: none;
    border: none;
    font-size: 1.2rem;
    opacity: 0.5;
    transition: opacity 0.2s;
    margin-left: auto;
    padding: 0;
    width: 20px;
    height: 20px;
}

.floating-message .btn-close:hover {
    opacity: 1;
}

.loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(5px);
    z-index: 9998;
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
}

.loading-overlay.show {
    opacity: 1;
    visibility: visible;
}

.loading-content {
    text-align: center;
    color: white;
}

.spinner-large {
    width: 50px;
    height: 50px;
    border: 4px solid rgba(255, 255, 255, 0.3);
    border-top: 4px solid white;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin: 0 auto 1rem;
}

.form-control.loading {
    background-image: linear-gradient(90deg, transparent, rgba(46, 125, 50, 0.1), transparent);
    background-size: 200% 100%;
    animation: shimmer 1.5s infinite;
}

@keyframes shimmer {
    0% { background-position: -200% 0; }
    100% { background-position: 200% 0; }
}
</style>
`;

document.head.insertAdjacentHTML('beforeend', additionalStyles);