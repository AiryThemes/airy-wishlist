/**
 * Airy WooCommerce Wishlist - Frontend JavaScript
 *
 * @package Airy_Wishlist
 */

(function () {
	'use strict';

	// Wishlist class.
    class AiryWishlist {
        constructor() {
            this.data = window.airyWishlistData || {};
            this.init();
        }
        init() {
            this.bindEvents();
            this.updateCounter();
            this.handleVariationForms();
        }
        /**
         * Handle WooCommerce variation forms
         */
        handleVariationForms() {
            const enableWishlistButton = (form, variationId) => {
                const container = form.querySelector('.airy-add-to-wishlist');
                const button = form.querySelector('.airy-wishlist-btn');
                
                if (container && button) {
                    container.style.display = 'block';
                    button.disabled = false;
                    button.classList.remove('airy-variable-disabled');
                    
                    // Update variation ID in button.
                    button.setAttribute('data-variation-id', variationId);
                }
            };
            
            const disableWishlistButton = (form) => {
                const container = form.querySelector('.airy-add-to-wishlist');
                const button = form.querySelector('.airy-wishlist-btn');
                
                if (container && button) {
                    container.style.display = 'none';
                    button.disabled = true;
                    button.classList.add('airy-variable-disabled');
                    
                    // Reset variation ID.
                    button.setAttribute('data-variation-id', '0');
                    
                    // Reset button state.
                    button.classList.remove('added');
                    const textSpan = button.querySelector('.airy-wishlist-text');
                    if (textSpan) {
                        textSpan.textContent = 'Add to Wishlist';
                    }
                }
            };
            
            const checkVariationStatus = (form) => {
                const variationInput = form.querySelector('input[name="variation_id"]');
                
                if (variationInput && variationInput.value && parseInt(variationInput.value) > 0) {
                    enableWishlistButton(form, variationInput.value);
                } else {
                    disableWishlistButton(form);
                }
            };
            
            // Check all forms on page load.
            document.querySelectorAll('.variations_form').forEach(form => {
                checkVariationStatus(form);
                
                // Listen to WooCommerce found_variation event.
                form.addEventListener('found_variation', () => {
                    setTimeout(() => {
                        const variationInput = form.querySelector('input[name="variation_id"]');
                        if (variationInput && variationInput.value) {
                            enableWishlistButton(form, variationInput.value);
                        }
                    }, 100);
                });
                
                // Listen to reset_data event.
                form.addEventListener('reset_data', () => {
                    disableWishlistButton(form);
                });
                
                // Watch the variation_id input for changes.
                const variationInput = form.querySelector('input[name="variation_id"]');
                if (variationInput) {
                    // MutationObserver to watch for value changes.
                    const observer = new MutationObserver(() => {
                        checkVariationStatus(form);
                    });
                    
                    observer.observe(variationInput, {
                        attributes: true,
                        attributeFilter: ['value']
                    });
                    
                    // Also listen to input/change events.
                    variationInput.addEventListener('change', () => {
                        checkVariationStatus(form);
                    });
                    
                    // Periodically check (fallback).
                    setInterval(() => {
                        checkVariationStatus(form);
                    }, 500);
                }
                
                // Listen to all variation select changes.
                form.querySelectorAll('.variations select').forEach(select => {
                    select.addEventListener('change', () => {
                        setTimeout(() => checkVariationStatus(form), 200);
                    });
                });
            });
        }
        
        bindEvents() {
            // Add to wishlist.
            document.addEventListener('click', (e) => {
                const btn = e.target.closest('.airy-wishlist-btn');
                if (btn && !btn.classList.contains('added')) {
                    e.preventDefault();
                    this.addToWishlist(btn);
                }
            });
            
            // Remove from wishlist.
            document.addEventListener('click', (e) => {
                if (e.target.closest('.airy-remove-from-wishlist')) {
                    // Only prevent default if AJAX is enabled.
                    if (this.data.enableAjax === 'yes') {
                        e.preventDefault();
                        const btn = e.target.closest('.airy-remove-from-wishlist');
                        this.removeFromWishlist(btn);
                    }
                    // If AJAX disabled, let the form submit naturally (POST with nonce).
                }
            });
            
            // Add to cart from wishlist.
            document.addEventListener('click', (e) => {
                if (e.target.closest('.airy-add-to-cart-from-wishlist')) {
                    e.preventDefault();
                    const btn = e.target.closest('.airy-add-to-cart-from-wishlist');
                    this.addToCartFromWishlist(btn);
                }
            });
            
            // Add all to cart.
            document.addEventListener('click', (e) => {
                if (e.target.closest('.airy-add-all-to-cart')) {
                    e.preventDefault();
                    this.addAllToCart();
                }
            });
        }
        
        addToWishlist(btn) {
            let productId = btn.getAttribute('data-product-id');
            let variationId = btn.getAttribute('data-variation-id') || 0;
            
            // Check if this is a variable product.
            const isVariable = btn.getAttribute('data-is-variable') === 'yes';
            
            if (isVariable) {
                // Try to get selected variation from WooCommerce variation form.
                const product = btn.closest('.product') || document.querySelector('.product');
                
                if (product) {
                    const variationForm = product.querySelector('.variations_form');
                    
                    if (variationForm) {
                        const variationIdInput = variationForm.querySelector('input[name="variation_id"]');
                        if (variationIdInput && variationIdInput.value && parseInt(variationIdInput.value) > 0) {
                            variationId = variationIdInput.value;
                        } else {
                            // No variation selected - shouldn't happen but show error.
                            this.showMessage('Please select product options before adding to wishlist.', 'error');
                            return;
                        }
                    }
                }
                
                // If still no variation ID, try from button's data attribute (set when variation selected).
                if (!variationId || variationId == 0) {
                    const btnVariationId = btn.getAttribute('data-variation-id');
                    if (btnVariationId && parseInt(btnVariationId) > 0) {
                        variationId = btnVariationId;
                    } else {
                        this.showMessage('Please select product options before adding to wishlist.', 'error');
                        return;
                    }
                }
            }
            
            // If AJAX is disabled, use form submission.
            if (this.data.enableAjax !== 'yes') {
                this.addToWishlistNoAjax(productId, variationId);
                return;
            }
            
            btn.classList.add('loading');
            
            this.ajax('airy_add_to_wishlist', {
                product_id: productId,
                variation_id: variationId
            }, (response) => {
                btn.classList.remove('loading');
                
                if (response.success) {
                    btn.classList.add('added');
                    const textSpan = btn.querySelector('.airy-wishlist-text');
                    if (textSpan) {
                        textSpan.textContent = this.data.addedMessage || 'Added to Wishlist';
                    }
                    
                    // Store the variation ID that was added.
                    btn.setAttribute('data-added-variation-id', variationId);
                    
                    this.updateCounter(response.data.count);
                    this.showMessage(response.data.message, 'success');
                    
                    if (this.data.redirectAfterAdd === 'yes') {
                        window.location.href = response.data.wishlist_url;
                    }
                } else {
                    this.showMessage(response.data.message, 'error');
                }
            });
        }
        
        addToWishlistNoAjax(productId, variationId) {
            // Create a form and submit.
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = window.location.href;
            
            const productField = document.createElement('input');
            productField.type = 'hidden';
            productField.name = 'airy_add_to_wishlist';
            productField.value = productId;
            form.appendChild(productField);
            
            if (variationId > 0) {
                const variationField = document.createElement('input');
                variationField.type = 'hidden';
                variationField.name = 'airy_variation_id';
                variationField.value = variationId;
                form.appendChild(variationField);
            }
            
            // Add nonce field for security.
            if (this.data.nonce) {
                const nonceField = document.createElement('input');
                nonceField.type = 'hidden';
                nonceField.name = 'airy_wishlist_nonce';
                nonceField.value = this.data.nonce;
                form.appendChild(nonceField);
            }
            
            document.body.appendChild(form);
            form.submit();
        }
        
        removeFromWishlist(btn) {
            const productId = btn.getAttribute('data-product-id');
            const variationId = btn.getAttribute('data-variation-id') || 0;
            const row = btn.closest('.airy-wishlist-item') || btn.closest('.airy-wishlist-grid-item');
            
            btn.classList.add('loading');
            
            this.ajax('airy_remove_from_wishlist', {
                product_id: productId,
                variation_id: variationId
            }, (response) => {
                btn.classList.remove('loading');
                
                if (response.success) {
                    if (row) {
                        row.style.opacity = '0';
                        setTimeout(() => {
                            row.remove();
                            this.checkEmptyWishlist();
                        }, 300);
                    }
                    
                    this.updateCounter(response.data.count);
                    this.showMessage(response.data.message, 'success');
                    this.updateButtons(productId, variationId, false);
                } else {
                    this.showMessage(response.data.message, 'error');
                }
            });
        }
        
        addToCartFromWishlist(btn) {
            const productId = btn.getAttribute('data-product-id');
            const variationId = btn.getAttribute('data-variation-id') || 0;
            
            btn.classList.add('loading');
            btn.disabled = true;
            
            this.ajax('airy_add_to_cart_from_wishlist', {
                product_id: productId,
                variation_id: variationId,
                quantity: 1
            }, (response) => {
                btn.classList.remove('loading');
                btn.disabled = false;
                
                if (response.success) {
                    this.showMessage(response.data.message, 'success');
                    
                    // Remove from wishlist if option enabled.
                    const row = btn.closest('.airy-wishlist-item') || btn.closest('.airy-wishlist-grid-item');
                    if (row) {
                        setTimeout(() => {
                            row.style.opacity = '0';
                            setTimeout(() => {
                                row.remove();
                                this.checkEmptyWishlist();
                            }, 300);
                        }, 500);
                    }
                    
                    // Trigger WooCommerce added_to_cart event.
                    document.body.dispatchEvent(new Event('wc_fragment_refresh'));
                } else {
                    this.showMessage(response.data.message, 'error');
                }
            });
        }
        
        addAllToCart() {
            const addToCartButtons = document.querySelectorAll('.airy-add-to-cart-from-wishlist');
            
            if (addToCartButtons.length === 0) {
                return;
            }
            
            let completed = 0;
            const total = addToCartButtons.length;
            
            addToCartButtons.forEach((btn) => {
                const productId = btn.getAttribute('data-product-id');
                const variationId = btn.getAttribute('data-variation-id') || 0;
                
                this.ajax('airy_add_to_cart_from_wishlist', {
                    product_id: productId,
                    variation_id: variationId,
                    quantity: 1
                }, (response) => {
                    completed++;
                    
                    if (completed === total) {
                        this.showMessage('All products added to cart!', 'success');
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);
                    }
                });
            });
        }
        
        updateCounter(count) {
            if (typeof count === 'undefined') {
                this.ajax('airy_get_wishlist_count', {}, (response) => {
                    if (response.success) {
                        this.setCounterValue(response.data.count);
                    }
                });
            } else {
                this.setCounterValue(count);
            }
        }
        
        setCounterValue(count) {
            const counters = document.querySelectorAll('.airy-wishlist-count');
            counters.forEach((counter) => {
                counter.textContent = count;
                // Show/hide based on count.
                if (count > 0) {
                    counter.style.display = 'flex';
                } else {
                    counter.style.display = 'none';
                }
            });
        }
        
        updateButtons(productId, variationId, inWishlist) {
            const buttons = document.querySelectorAll(`.airy-wishlist-btn[data-product-id="${productId}"]`);
            
            buttons.forEach((btn) => {
                const btnVariationId = btn.getAttribute('data-variation-id') || 0;
                
                if (btnVariationId == variationId) {
                    const textSpan = btn.querySelector('.airy-wishlist-text');
                    
                    if (inWishlist) {
                        btn.classList.add('added');
                        if (textSpan) {
                            textSpan.textContent = this.data.addedMessage || 'Added to Wishlist';
                        }
                    } else {
                        btn.classList.remove('added');
                        if (textSpan) {
                            textSpan.textContent = 'Add to Wishlist';
                        }
                    }
                }
            });
        }
        
        checkEmptyWishlist() {
            const table = document.querySelector('.airy-wishlist-table tbody');
            const grid = document.querySelector('.airy-wishlist-grid');
            
            if (table && table.children.length === 0) {
                window.location.reload();
            } else if (grid && grid.children.length === 0) {
                window.location.reload();
            }
        }
        
        showMessage(message, type) {
            // Create message element.
            const messageEl = document.createElement('div');
            messageEl.className = `airy-wishlist-message airy-message-${type}`;
            messageEl.textContent = message;
            messageEl.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 15px 20px;
                background-color: ${type === 'success' ? '#27ae60' : '#e74c3c'};
                color: #ffffff;
                border-radius: 4px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                z-index: 99999;
                animation: slideInRight 0.3s ease;
            `;
            
            document.body.appendChild(messageEl);
            
            // Remove after 3 seconds.
            setTimeout(() => {
                messageEl.style.animation = 'slideOutRight 0.3s ease';
                setTimeout(() => {
                    messageEl.remove();
                }, 300);
            }, 3000);
        }
        
        ajax(action, data, callback) {
            const formData = new FormData();
            formData.append('action', action);
            formData.append('nonce', this.data.nonce);
            
            for (let key in data) {
                formData.append(key, data[key]);
            }
            
            fetch(this.data.ajaxUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(response => response.json())
            .then(callback)
            .catch(error => {
                console.error('Wishlist AJAX Error:', error);
                this.showMessage('An error occurred. Please try again.', 'error');
            });
        }
    }
    
    // Add animations.
    const style = document.createElement('style');
    style.textContent = `
        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        @keyframes slideOutRight {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(100%);
                opacity: 0;
            }
        }
    `;
    document.head.appendChild(style);
    
    // Initialize on DOM ready.
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            new AiryWishlist();
        });
    } else {
        new AiryWishlist();
    }
})();