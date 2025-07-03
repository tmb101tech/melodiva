// Melodiva Skincare - Main JavaScript File

document.addEventListener("DOMContentLoaded", () => {
  // Initialize all components
  initializeCart()
  initializeForms()
  initializeAnimations()
  initializeSearch()
})

// Cart functionality
function initializeCart() {
  // Add to cart buttons
  document.querySelectorAll(".add-to-cart").forEach((button) => {
    button.addEventListener("click", function (e) {
      e.preventDefault()
      const productId = this.dataset.productId
      const quantity = this.closest(".product-card").querySelector(".quantity-input")?.value || 1

      addToCart(productId, quantity)
    })
  })

  // Quantity controls
  document.querySelectorAll(".quantity-btn").forEach((button) => {
    button.addEventListener("click", function () {
      const input = this.parentNode.querySelector(".quantity-input")
      const isIncrement = this.classList.contains("increment")
      let value = Number.parseInt(input.value)

      if (isIncrement) {
        value++
      } else if (value > 1) {
        value--
      }

      input.value = value

      // Update cart if on cart page
      if (this.dataset.cartId) {
        updateCartQuantity(this.dataset.cartId, value)
      }
    })
  })

  // Remove from cart
  document.querySelectorAll(".remove-from-cart").forEach((button) => {
    button.addEventListener("click", function (e) {
      e.preventDefault()
      const cartId = this.dataset.cartId
      removeFromCart(cartId)
    })
  })
}

// Add product to cart
function addToCart(productId, quantity = 1) {
  showLoading()

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
      hideLoading()
      if (data.success) {
        showAlert("Product added to cart!", "success")
        updateCartCount()
      } else {
        showAlert(data.message || "Error adding product to cart", "danger")
      }
    })
    .catch((error) => {
      hideLoading()
      showAlert("Error adding product to cart", "danger")
      console.error("Error:", error)
    })
}

// Update cart quantity
function updateCartQuantity(cartId, quantity) {
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
      if (data.success) {
        location.reload() // Refresh to update totals
      } else {
        showAlert(data.message || "Error updating cart", "danger")
      }
    })
    .catch((error) => {
      showAlert("Error updating cart", "danger")
      console.error("Error:", error)
    })
}

// Remove from cart
function removeFromCart(cartId) {
  if (confirm("Are you sure you want to remove this item from your cart?")) {
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
          location.reload()
        } else {
          showAlert(data.message || "Error removing item", "danger")
        }
      })
      .catch((error) => {
        showAlert("Error removing item", "danger")
        console.error("Error:", error)
      })
  }
}

// Update cart count in navbar
function updateCartCount() {
  fetch("api/cart.php?action=count")
    .then((response) => response.json())
    .then((data) => {
      const cartBadge = document.querySelector(".cart-count")
      if (cartBadge) {
        cartBadge.textContent = data.count || 0
      }
    })
    .catch((error) => {
      console.error("Error updating cart count:", error)
    })
}

// Form validation and submission
function initializeForms() {
  // Login form - handled directly in login.php now

  // Registration form
  const registerForm = document.getElementById("registerForm")
  if (registerForm) {
    registerForm.addEventListener("submit", function (e) {
      e.preventDefault()
      if (validateRegistrationForm(this)) {
        submitFormToSamePage(this)
      }
    })
  }

  // Checkout form
  const checkoutForm = document.getElementById("checkoutForm")
  if (checkoutForm) {
    checkoutForm.addEventListener("submit", function (e) {
      e.preventDefault()
      processCheckout(this)
    })
  }

  // Affiliate code application
  const affiliateForm = document.getElementById("affiliateForm")
  if (affiliateForm) {
    affiliateForm.addEventListener("submit", (e) => {
      e.preventDefault()
      applyAffiliateCode()
    })
  }
}

// Validate registration form
function validateRegistrationForm(form) {
  const password = form.querySelector('[name="password"]').value
  const confirmPassword = form.querySelector('[name="confirm_password"]').value

  if (password !== confirmPassword) {
    showAlert("Passwords do not match", "danger")
    return false
  }

  if (password.length < 6) {
    showAlert("Password must be at least 6 characters long", "danger")
    return false
  }

  return true
}

// Submit form to same page (for login/register pages)
function submitFormToSamePage(form) {
  showLoading()

  const formData = new FormData(form)

  fetch(window.location.href, {
    method: "POST",
    body: formData,
  })
    .then((response) => {
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`)
      }
      return response.text()
    })
    .then((text) => {
      hideLoading()

      // Try to parse as JSON
      let data
      try {
        data = JSON.parse(text)
      } catch (e) {
        console.error("Response is not valid JSON:", text)
        showAlert("Server error: Invalid response format", "danger")
        return
      }

      if (data.success) {
        showAlert(data.message, "success")
        if (data.redirect) {
          setTimeout(() => {
            window.location.href = data.redirect
          }, 1500)
        }
      } else {
        showAlert(data.message || "An error occurred", "danger")
      }
    })
    .catch((error) => {
      hideLoading()
      console.error("Fetch error:", error)
      showAlert("Network error: Please check your connection and try again.", "danger")
    })
}

// Submit form via AJAX (for other forms)
function submitForm(form, url) {
  showLoading()

  const formData = new FormData(form)

  fetch(url, {
    method: "POST",
    body: formData,
  })
    .then((response) => {
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`)
      }
      return response.text()
    })
    .then((text) => {
      hideLoading()

      // Try to parse as JSON
      let data
      try {
        data = JSON.parse(text)
      } catch (e) {
        console.error("Response is not valid JSON:", text)
        showAlert("Server error: Invalid response format", "danger")
        return
      }

      if (data.success) {
        showAlert(data.message, "success")
        if (data.redirect) {
          setTimeout(() => {
            window.location.href = data.redirect
          }, 1500)
        }
      } else {
        showAlert(data.message || "An error occurred", "danger")
      }
    })
    .catch((error) => {
      hideLoading()
      console.error("Fetch error:", error)
      showAlert("Network error: Please check your connection and try again.", "danger")
    })
}

// Process checkout
function processCheckout(form) {
  const paymentMethod = form.querySelector('[name="payment_method"]:checked').value

  if (paymentMethod === "paystack") {
    processPaystackPayment(form)
  } else {
    processBankTransfer(form)
  }
}

// Process Paystack payment
function processPaystackPayment(form) {
  const formData = new FormData(form)

  fetch("api/checkout.php", {
    method: "POST",
    body: formData,
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        // Initialize Paystack payment
        if (typeof PaystackPop !== "undefined") {
          const handler = PaystackPop.setup({
            key: data.public_key,
            email: data.email,
            amount: data.amount * 100, // Convert to kobo
            currency: "NGN",
            ref: data.reference,
            callback: (response) => {
              // Payment successful
              verifyPayment(response.reference)
            },
            onClose: () => {
              showAlert("Payment cancelled", "warning")
            },
          })
          handler.openIframe()
        } else {
          showAlert("Paystack is not available. Please try again later.", "danger")
        }
      } else {
        showAlert(data.message, "danger")
      }
    })
    .catch((error) => {
      showAlert("Error processing payment", "danger")
      console.error("Error:", error)
    })
}

// Verify Paystack payment
function verifyPayment(reference) {
  fetch("api/verify-payment.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
    },
    body: JSON.stringify({ reference: reference }),
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        showAlert("Payment successful! Your order has been placed.", "success")
        setTimeout(() => {
          window.location.href = "my-orders.php"
        }, 2000)
      } else {
        showAlert("Payment verification failed", "danger")
      }
    })
    .catch((error) => {
      showAlert("Error verifying payment", "danger")
      console.error("Error:", error)
    })
}

// Process bank transfer
function processBankTransfer(form) {
  const formData = new FormData(form)

  fetch("api/checkout.php", {
    method: "POST",
    body: formData,
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        showAlert("Order placed successfully! Please upload your payment proof.", "success")
        setTimeout(() => {
          window.location.href = "my-orders.php"
        }, 2000)
      } else {
        showAlert(data.message, "danger")
      }
    })
    .catch((error) => {
      showAlert("Error processing order", "danger")
      console.error("Error:", error)
    })
}

// Apply affiliate code
function applyAffiliateCode() {
  const code = document.getElementById("affiliateCode").value

  if (!code) {
    showAlert("Please enter an affiliate code", "warning")
    return
  }

  fetch("api/affiliate.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
    },
    body: JSON.stringify({
      action: "apply_code",
      code: code,
    }),
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        showAlert("Affiliate code applied! 5% discount added.", "success")
        setTimeout(() => {
          location.reload()
        }, 1500)
      } else {
        showAlert(data.message, "danger")
      }
    })
    .catch((error) => {
      showAlert("Error applying affiliate code", "danger")
      console.error("Error:", error)
    })
}

// Initialize animations
function initializeAnimations() {
  // Intersection Observer for scroll animations
  const observerOptions = {
    threshold: 0.1,
    rootMargin: "0px 0px -50px 0px",
  }

  const observer = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      if (entry.isIntersecting) {
        entry.target.classList.add("fade-in")
      }
    })
  }, observerOptions)

  // Observe all product cards
  document.querySelectorAll(".product-card").forEach((card) => {
    observer.observe(card)
  })
}

// Initialize search functionality
function initializeSearch() {
  const searchInput = document.getElementById("searchInput")
  if (searchInput) {
    searchInput.addEventListener("input", debounce(performSearch, 300))
  }
}

// Perform search
function performSearch() {
  const query = document.getElementById("searchInput").value

  if (query.length < 2) {
    return
  }

  fetch(`api/search.php?q=${encodeURIComponent(query)}`)
    .then((response) => response.json())
    .then((data) => {
      displaySearchResults(data.results)
    })
    .catch((error) => {
      console.error("Search error:", error)
    })
}

// Display search results
function displaySearchResults(results) {
  const resultsContainer = document.getElementById("searchResults")
  if (!resultsContainer) return

  resultsContainer.innerHTML = ""

  if (results.length === 0) {
    resultsContainer.innerHTML = '<p class="text-center">No products found.</p>'
    return
  }

  results.forEach((product) => {
    const productCard = createProductCard(product)
    resultsContainer.appendChild(productCard)
  })
}

// Create product card element
function createProductCard(product) {
  const card = document.createElement("div")
  card.className = "col-md-4 mb-4"
  card.innerHTML = `
        <div class="product-card">
            <img src="uploads/${product.image}" alt="${product.name}" class="product-image">
            <div class="product-info">
                <h5 class="product-title">${product.name}</h5>
                <p class="product-type">${product.type} - ${product.size}</p>
                <p class="product-price">${formatPrice(product.price)}</p>
                <button class="btn btn-primary add-to-cart" data-product-id="${product.id}">
                    <i class="fas fa-cart-plus"></i> Add to Cart
                </button>
            </div>
        </div>
    `
  return card
}

// Utility functions
function showAlert(message, type = "info") {
  const alertContainer = document.getElementById("alertContainer") || createAlertContainer()

  const alert = document.createElement("div")
  alert.className = `alert alert-${type} alert-dismissible fade show`
  alert.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `

  alertContainer.appendChild(alert)

  // Auto-dismiss after 5 seconds
  setTimeout(() => {
    alert.remove()
  }, 5000)
}

function createAlertContainer() {
  const container = document.createElement("div")
  container.id = "alertContainer"
  container.style.position = "fixed"
  container.style.top = "20px"
  container.style.right = "20px"
  container.style.zIndex = "9999"
  container.style.maxWidth = "400px"
  document.body.appendChild(container)
  return container
}

function showLoading() {
  const loading = document.getElementById("loadingSpinner") || createLoadingSpinner()
  loading.style.display = "block"
}

function hideLoading() {
  const loading = document.getElementById("loadingSpinner")
  if (loading) {
    loading.style.display = "none"
  }
}

function createLoadingSpinner() {
  const spinner = document.createElement("div")
  spinner.id = "loadingSpinner"
  spinner.innerHTML = '<div class="spinner"></div>'
  spinner.style.position = "fixed"
  spinner.style.top = "50%"
  spinner.style.left = "50%"
  spinner.style.transform = "translate(-50%, -50%)"
  spinner.style.zIndex = "9999"
  spinner.style.display = "none"
  document.body.appendChild(spinner)
  return spinner
}

function formatPrice(price) {
  return (
    "₦" +
    Number.parseFloat(price).toLocaleString("en-NG", {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    })
  )
}

function debounce(func, wait) {
  let timeout
  return function executedFunction(...args) {
    const later = () => {
      clearTimeout(timeout)
      func(...args)
    }
    clearTimeout(timeout)
    timeout = setTimeout(later, wait)
  }
}

// Initialize cart count on page load
updateCartCount()
