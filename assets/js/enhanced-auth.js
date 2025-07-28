// Enhanced Authentication JavaScript
document.addEventListener('DOMContentLoaded', function() {
    // Initialize animations
    initializeAnimations();
    
    // Initialize form enhancements
    initializeFormEnhancements();
    
    // Initialize scroll effects
    initializeScrollEffects();
    
    // Initialize tooltips
    initializeTooltips();
});

function initializeAnimations() {
    // Animate elements on page load
    const animatedElements = document.querySelectorAll('.auth-card, .product-card, .card');
    
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('fade-in');
            }
        });
    }, {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    });
    
    animatedElements.forEach(element => {
        observer.observe(element);
    });
}

function initializeFormEnhancements() {
    // Enhanced form validation
    const forms = document.querySelectorAll('form');
    
    forms.forEach(form => {
        const inputs = form.querySelectorAll('input, textarea, select');
        
        inputs.forEach(input => {
            // Add floating label effect
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
    });
}

function validateField(field) {
    const value = field.value.trim();
    const type = field.type;
    const name = field.name;
    
    // Remove existing validation classes
    field.classList.remove('is-valid', 'is-invalid');
    
    // Email validation
    if (type === 'email' && value) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (emailRegex.test(value)) {
            field.classList.add('is-valid');
        } else {
            field.classList.add('is-invalid');
        }
    }
    
    // Phone validation
    if (name === 'phone' && value) {
        const phoneRegex = /^[\+]?[0-9\s\-\(\)]{10,}$/;
        if (phoneRegex.test(value)) {
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
    
    // Required field validation
    if (field.hasAttribute('required') && value) {
        field.classList.add('is-valid');
    } else if (field.hasAttribute('required') && !value) {
        field.classList.add('is-invalid');
    }
}

function initializeScrollEffects() {
    // Navbar scroll effect
    const navbar = document.querySelector('.navbar');
    if (navbar) {
        window.addEventListener('scroll', function() {
            if (window.scrollY > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        });
    }
    
    // Parallax effect for hero section
    const heroSection = document.querySelector('.hero-section');
    if (heroSection) {
        window.addEventListener('scroll', function() {
            const scrolled = window.pageYOffset;
            const rate = scrolled * -0.5;
            heroSection.style.transform = `translateY(${rate}px)`;
        });
    }
}

function initializeTooltips() {
    // Initialize Bootstrap tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
}

// Enhanced button loading states
function setButtonLoading(button, loading = true) {
    const btnText = button.querySelector('.btn-text');
    const btnLoader = button.querySelector('.btn-loader');
    
    if (loading) {
        button.disabled = true;
        if (btnText) btnText.style.opacity = '0';
        if (btnLoader) btnLoader.style.opacity = '1';
    } else {
        button.disabled = false;
        if (btnText) btnText.style.opacity = '1';
        if (btnLoader) btnLoader.style.opacity = '0';
    }
}

// Enhanced alert system
function showAlert(message, type = 'info', duration = 5000) {
    const alertContainer = document.getElementById('alertContainer') || createAlertContainer();
    
    const alert = document.createElement('div');
    alert.className = `alert alert-${type} alert-modern slide-in`;
    
    const icon = getAlertIcon(type);
    alert.innerHTML = `
        <i class="fas fa-${icon} me-2"></i>
        ${message}
        <button type="button" class="btn-close" onclick="dismissAlert(this)"></button>
    `;
    
    alertContainer.appendChild(alert);
    
    // Auto-dismiss
    if (duration > 0) {
        setTimeout(() => {
            dismissAlert(alert.querySelector('.btn-close'));
        }, duration);
    }
    
    return alert;
}

function getAlertIcon(type) {
    const icons = {
        'success': 'check-circle',
        'danger': 'exclamation-circle',
        'warning': 'exclamation-triangle',
        'info': 'info-circle'
    };
    return icons[type] || 'info-circle';
}

function dismissAlert(button) {
    const alert = button.closest('.alert');
    alert.classList.add('slide-out');
    setTimeout(() => {
        alert.remove();
    }, 300);
}

function createAlertContainer() {
    const container = document.createElement('div');
    container.id = 'alertContainer';
    container.style.position = 'fixed';
    container.style.top = '20px';
    container.style.right = '20px';
    container.style.zIndex = '9999';
    container.style.maxWidth = '400px';
    document.body.appendChild(container);
    return container;
}

// Enhanced form submission
function submitFormWithAnimation(form, url = null) {
    const submitBtn = form.querySelector('button[type="submit"]');
    const formData = new FormData(form);
    
    setButtonLoading(submitBtn, true);
    
    fetch(url || form.action || window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.text();
    })
    .then(text => {
        setButtonLoading(submitBtn, false);
        
        // Try to parse as JSON
        let data;
        try {
            data = JSON.parse(text);
        } catch (e) {
            // If not JSON, check for success/error in HTML
            if (text.includes('alert-success')) {
                showAlert('Operation completed successfully!', 'success');
                setTimeout(() => window.location.reload(), 1500);
            } else if (text.includes('alert-danger')) {
                showAlert('An error occurred. Please try again.', 'danger');
            }
            return;
        }
        
        if (data.success) {
            showAlert(data.message, 'success');
            if (data.redirect) {
                setTimeout(() => {
                    window.location.href = data.redirect;
                }, 1500);
            } else {
                setTimeout(() => window.location.reload(), 1500);
            }
        } else {
            showAlert(data.message || 'An error occurred', 'danger');
        }
    })
    .catch(error => {
        setButtonLoading(submitBtn, false);
        console.error('Error:', error);
        showAlert('Network error. Please check your connection and try again.', 'danger');
    });
}

// Enhanced cart functionality
function addToCartWithAnimation(productId, quantity = 1) {
    const button = event.target.closest('.add-to-cart');
    const originalText = button.innerHTML;
    
    setButtonLoading(button, true);
    
    fetch('api/cart.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'add',
            product_id: productId,
            quantity: quantity
        })
    })
    .then(response => response.json())
    .then(data => {
        setButtonLoading(button, false);
        
        if (data.success) {
            // Success animation
            button.innerHTML = '<i class="fas fa-check"></i> Added!';
            button.classList.add('btn-success');
            button.classList.remove('btn-primary');
            
            // Update cart count
            updateCartCount();
            
            // Show success message
            showAlert('Product added to cart!', 'success', 3000);
            
            // Reset button after 2 seconds
            setTimeout(() => {
                button.innerHTML = originalText;
                button.classList.remove('btn-success');
                button.classList.add('btn-primary');
            }, 2000);
        } else {
            showAlert(data.message || 'Error adding product to cart', 'danger');
        }
    })
    .catch(error => {
        setButtonLoading(button, false);
        console.error('Error:', error);
        showAlert('Error adding product to cart', 'danger');
    });
}

// Enhanced cart count update
function updateCartCount() {
    fetch('api/cart.php?action=count')
        .then(response => response.json())
        .then(data => {
            const cartBadges = document.querySelectorAll('.cart-count');
            cartBadges.forEach(badge => {
                const newCount = data.count || 0;
                badge.textContent = newCount;
                
                // Animate count change
                if (newCount > 0) {
                    badge.style.transform = 'scale(1.3)';
                    setTimeout(() => {
                        badge.style.transform = 'scale(1)';
                    }, 200);
                }
            });
        })
        .catch(error => {
            console.error('Error updating cart count:', error);
        });
}

// Enhanced search functionality
function initializeSearch() {
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        let searchTimeout;
        
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            const query = this.value.trim();
            
            if (query.length >= 2) {
                searchTimeout = setTimeout(() => {
                    performSearch(query);
                }, 300);
            } else {
                clearSearchResults();
            }
        });
    }
}

function performSearch(query) {
    const searchResults = document.getElementById('searchResults');
    if (!searchResults) return;
    
    // Show loading state
    searchResults.innerHTML = '<div class="text-center p-4"><div class="spinner"></div></div>';
    
    fetch(`api/search.php?q=${encodeURIComponent(query)}`)
        .then(response => response.json())
        .then(data => {
            displaySearchResults(data.results || []);
        })
        .catch(error => {
            console.error('Search error:', error);
            searchResults.innerHTML = '<div class="text-center p-4 text-muted">Search error occurred</div>';
        });
}

function displaySearchResults(results) {
    const searchResults = document.getElementById('searchResults');
    if (!searchResults) return;
    
    if (results.length === 0) {
        searchResults.innerHTML = '<div class="text-center p-4 text-muted">No products found</div>';
        return;
    }
    
    const resultsHTML = results.map(product => `
        <div class="col-md-4 mb-4">
            <div class="product-card hover-lift">
                <img src="${product.image}" alt="${product.name}" class="product-image">
                <div class="product-info">
                    <h5 class="product-title">${product.name}</h5>
                    <p class="product-type">${product.type} - ${product.size}</p>
                    <p class="product-price">₦${parseFloat(product.price).toLocaleString()}</p>
                    <button class="btn btn-primary add-to-cart w-100" onclick="addToCartWithAnimation(${product.id})">
                        <i class="fas fa-cart-plus"></i> Add to Cart
                    </button>
                </div>
            </div>
        </div>
    `).join('');
    
    searchResults.innerHTML = resultsHTML;
    
    // Animate new results
    const newCards = searchResults.querySelectorAll('.product-card');
    newCards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        setTimeout(() => {
            card.style.transition = 'all 0.3s ease';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, index * 100);
    });
}

function clearSearchResults() {
    const searchResults = document.getElementById('searchResults');
    if (searchResults) {
        searchResults.innerHTML = '';
    }
}

// Enhanced modal functionality
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        const bsModal = new bootstrap.Modal(modal);
        bsModal.show();
        
        // Add entrance animation
        modal.addEventListener('shown.bs.modal', function() {
            modal.querySelector('.modal-content').classList.add('slide-in');
        });
    }
}

// Enhanced copy to clipboard
function copyToClipboard(text, button = null) {
    navigator.clipboard.writeText(text).then(() => {
        if (button) {
            const originalText = button.innerHTML;
            button.innerHTML = '<i class="fas fa-check"></i> Copied!';
            button.classList.add('btn-success');
            
            setTimeout(() => {
                button.innerHTML = originalText;
                button.classList.remove('btn-success');
            }, 2000);
        }
        
        showAlert('Copied to clipboard!', 'success', 2000);
    }).catch(err => {
        console.error('Failed to copy: ', err);
        showAlert('Failed to copy to clipboard', 'danger');
    });
}

// Enhanced image lazy loading
function initializeLazyLoading() {
    const images = document.querySelectorAll('img[data-src]');
    
    const imageObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const img = entry.target;
                img.src = img.dataset.src;
                img.classList.remove('lazy');
                imageObserver.unobserve(img);
            }
        });
    });
    
    images.forEach(img => imageObserver.observe(img));
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    initializeSearch();
    initializeLazyLoading();
    updateCartCount();
});

// Enhanced error handling
window.addEventListener('error', function(e) {
    console.error('JavaScript error:', e.error);
    // Don't show alerts for every JS error, but log them
});

// Enhanced performance monitoring
if ('performance' in window) {
    window.addEventListener('load', function() {
        setTimeout(() => {
            const perfData = performance.getEntriesByType('navigation')[0];
            console.log('Page load time:', perfData.loadEventEnd - perfData.loadEventStart, 'ms');
        }, 0);
    });
}