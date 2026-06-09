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
            this.i18n = this.data.i18n || {};
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
                        textSpan.textContent = this.i18n.addText || 'Add to Wishlist';
                    }
                }
            };

            // Sync the button's "added" state for the currently selected variation.
            const syncAddedState = (form, productId, variationId) => {
                const button = form.querySelector('.airy-wishlist-btn');
                if (!button) {
                    return;
                }
                this.ajax('airy_check_in_wishlist', {
                    product_id: productId,
                    variation_id: variationId
                }, (response) => {
                    if (response && response.success) {
                        this.setButtonAdded(button, response.data.in_wishlist);
                    }
                });
            };

            const checkVariationStatus = (form) => {
                const variationInput = form.querySelector('input[name="variation_id"]');

                if (variationInput && variationInput.value && parseInt(variationInput.value) > 0) {
                    enableWishlistButton(form, variationInput.value);
                    const button = form.querySelector('.airy-wishlist-btn');
                    if (button) {
                        syncAddedState(form, button.getAttribute('data-product-id'), variationInput.value);
                    }
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
            // Add to wishlist (and toggle-off when already added, if enabled).
            document.addEventListener('click', (e) => {
                const btn = e.target.closest('.airy-wishlist-btn');
                if (!btn) {
                    return;
                }

                if (btn.classList.contains('added')) {
                    // Already in the wishlist: click again to remove (toggle).
                    if (this.data.buttonToggle === 'yes' && this.data.enableAjax === 'yes') {
                        e.preventDefault();
                        this.removeViaButton(btn);
                    }
                    return;
                }

                e.preventDefault();
                if (this.data.multipleEnabled === 'yes' && this.data.enableAjax === 'yes') {
                    this.showWishlistChooser(btn);
                } else {
                    this.addToWishlist(btn);
                }
            });

            // Multiple wishlists: management controls on the wishlist page.
            document.addEventListener('click', (e) => {
                if (e.target.closest('.airy-wishlist-new-btn')) {
                    e.preventDefault();
                    this.createWishlistPrompt();
                } else if (e.target.closest('.airy-wishlist-rename-btn')) {
                    e.preventDefault();
                    this.renameWishlistPrompt(e.target.closest('.airy-wishlist-rename-btn'));
                } else if (e.target.closest('.airy-wishlist-delete-btn')) {
                    e.preventDefault();
                    this.deleteWishlistPrompt(e.target.closest('.airy-wishlist-delete-btn'));
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

            // Copy shareable wishlist link.
            document.addEventListener('click', (e) => {
                const copyBtn = e.target.closest('.airy-copy-share-link');
                if (copyBtn) {
                    e.preventDefault();
                    this.copyShareLink(copyBtn);
                }
            });

            // Toggle stock/price notifications for a wishlist.
            document.addEventListener('change', (e) => {
                const toggle = e.target.closest('.airy-notify-toggle');
                if (toggle) {
                    this.toggleNotifications(toggle);
                    return;
                }

                const moveSelect = e.target.closest('.airy-move-select');
                if (moveSelect && moveSelect.value) {
                    this.moveItem(moveSelect);
                }
            });
        }

        moveItem(select) {
            const productId = select.getAttribute('data-product-id');
            const variationId = select.getAttribute('data-variation-id') || 0;
            const fromId = select.getAttribute('data-from-id');
            const toId = select.value;
            const row = select.closest('.airy-wishlist-item') || select.closest('.airy-wishlist-grid-item');

            select.disabled = true;

            this.ajax('airy_move_item', {
                product_id: productId,
                variation_id: variationId,
                from_id: fromId,
                to_id: toId
            }, (response) => {
                if (response && response.success) {
                    this.showMessage(response.data.message, 'success');
                    this.updateCounter(response.data.count);
                    if (row) {
                        row.style.opacity = '0';
                        setTimeout(() => {
                            row.remove();
                            this.checkEmptyWishlist();
                        }, 300);
                    }
                } else {
                    select.disabled = false;
                    select.value = '';
                    this.showMessage((response && response.data && response.data.message) || this.i18n.genericError, 'error');
                }
            });
        }

        toggleNotifications(toggle) {
            const wishlistId = toggle.getAttribute('data-wishlist-id');
            const enabled = toggle.checked ? '1' : '0';

            this.ajax('airy_toggle_notifications', {
                wishlist_id: wishlistId,
                enabled: enabled
            }, (response) => {
                if (response && response.success) {
                    this.showMessage(response.data.message, 'success');
                } else {
                    // Revert the checkbox on failure.
                    toggle.checked = !toggle.checked;
                    this.showMessage((response && response.data && response.data.message) || this.i18n.genericError, 'error');
                }
            });
        }

        copyShareLink(btn) {
            const wrapper = btn.closest('.airy-wishlist-share-link');
            const input = wrapper ? wrapper.querySelector('.airy-wishlist-share-url') : null;
            if (!input) {
                return;
            }

            const url = input.value;
            const done = () => {
                const original = btn.textContent;
                btn.textContent = btn.getAttribute('data-copied-text') || 'Copied!';
                setTimeout(() => {
                    btn.textContent = original;
                }, 2000);
            };

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(url).then(done).catch(() => {
                    input.select();
                    document.execCommand('copy');
                    done();
                });
            } else {
                input.select();
                document.execCommand('copy');
                done();
            }
        }
        
        /**
         * Resolve the variation ID for a button, or null if a required
         * variation has not been selected (shows an error in that case).
         */
        resolveVariationId(btn) {
            let variationId = btn.getAttribute('data-variation-id') || 0;
            const isVariable = btn.getAttribute('data-is-variable') === 'yes';

            if (!isVariable) {
                return variationId;
            }

            const product = btn.closest('.product') || document.querySelector('.product');
            if (product) {
                const variationForm = product.querySelector('.variations_form');
                if (variationForm) {
                    const variationIdInput = variationForm.querySelector('input[name="variation_id"]');
                    if (variationIdInput && variationIdInput.value && parseInt(variationIdInput.value) > 0) {
                        variationId = variationIdInput.value;
                    } else {
                        this.showMessage(this.i18n.selectOptions || 'Please select product options before adding to wishlist.', 'error');
                        return null;
                    }
                }
            }

            if (!variationId || variationId == 0) {
                const btnVariationId = btn.getAttribute('data-variation-id');
                if (btnVariationId && parseInt(btnVariationId) > 0) {
                    variationId = btnVariationId;
                } else {
                    this.showMessage(this.i18n.selectOptions || 'Please select product options before adding to wishlist.', 'error');
                    return null;
                }
            }

            return variationId;
        }

        /**
         * Remove a product from the wishlist via the add/remove toggle button,
         * reverting it to the "Add to Wishlist" state.
         */
        removeViaButton(btn) {
            const productId = btn.getAttribute('data-product-id');
            const variationId = btn.getAttribute('data-variation-id') || 0;
            // Remove from the list it was added to this session, else the default list.
            const wishlistId = btn.getAttribute('data-added-wishlist-id') || 0;

            btn.classList.add('loading');

            this.ajax('airy_remove_from_wishlist', {
                product_id: productId,
                variation_id: variationId,
                wishlist_id: wishlistId
            }, (response) => {
                btn.classList.remove('loading');

                if (response && response.success) {
                    this.setButtonAdded(btn, false);
                    btn.removeAttribute('data-added-wishlist-id');
                    btn.removeAttribute('data-added-variation-id');
                    this.updateCounter(response.data.count);
                    this.showMessage(response.data.message, 'success');
                } else {
                    this.showMessage((response && response.data && response.data.message) || this.i18n.genericError, 'error');
                }
            });
        }

        addToWishlist(btn, targetWishlistId) {
            const productId = btn.getAttribute('data-product-id');
            const variationId = this.resolveVariationId(btn);

            if (variationId === null) {
                return;
            }

            // If AJAX is disabled, use form submission.
            if (this.data.enableAjax !== 'yes') {
                this.addToWishlistNoAjax(productId, variationId);
                return;
            }

            btn.classList.add('loading');

            const payload = {
                product_id: productId,
                variation_id: variationId
            };
            if (targetWishlistId) {
                payload.wishlist_id = targetWishlistId;
            }

            this.ajax('airy_add_to_wishlist', payload, (response) => {
                btn.classList.remove('loading');
                
                if (response.success) {
                    btn.classList.add('added');
                    const textSpan = btn.querySelector('.airy-wishlist-text');
                    if (textSpan) {
                        textSpan.textContent = this.i18n.addedText || 'Added to Wishlist';
                    }

                    // Store the variation ID and the list it was added to (for toggle-off).
                    btn.setAttribute('data-added-variation-id', variationId);
                    btn.setAttribute('data-added-wishlist-id', targetWishlistId || 0);
                    
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

        /**
         * Show a small popover under the button to choose which list to add to.
         */
        showWishlistChooser(btn) {
            // Validate variation selection up front so we don't open the chooser and then fail.
            if (this.resolveVariationId(btn) === null) {
                return;
            }

            this.closeWishlistChooser();

            const panel = document.createElement('div');
            panel.className = 'airy-wishlist-chooser';
            panel.innerHTML = '<div class="airy-wishlist-chooser-title">' + (this.i18n.chooseList || 'Add to which list?') + '</div><div class="airy-wishlist-chooser-lists">…</div>';

            document.body.appendChild(panel);
            this.positionChooser(panel, btn);
            this._activeChooser = panel;

            // Close on outside click / escape.
            this._chooserOutside = (ev) => {
                if (!panel.contains(ev.target) && ev.target !== btn && !btn.contains(ev.target)) {
                    this.closeWishlistChooser();
                }
            };
            this._chooserEsc = (ev) => {
                if (ev.key === 'Escape') {
                    this.closeWishlistChooser();
                }
            };
            setTimeout(() => {
                document.addEventListener('click', this._chooserOutside);
                document.addEventListener('keydown', this._chooserEsc);
            }, 0);

            // Load the lists.
            this.ajax('airy_get_wishlists', {}, (response) => {
                if (!this._activeChooser) {
                    return;
                }
                const listsWrap = panel.querySelector('.airy-wishlist-chooser-lists');
                listsWrap.innerHTML = '';

                if (response && response.success && response.data.wishlists.length) {
                    response.data.wishlists.forEach((list) => {
                        const item = document.createElement('button');
                        item.type = 'button';
                        item.className = 'airy-wishlist-chooser-item';
                        item.textContent = list.name + ' (' + list.count + ')';
                        item.addEventListener('click', () => {
                            this.closeWishlistChooser();
                            this.addToWishlist(btn, list.id);
                        });
                        listsWrap.appendChild(item);
                    });
                }

                // "Create new list" row.
                const createRow = document.createElement('div');
                createRow.className = 'airy-wishlist-chooser-create';
                const input = document.createElement('input');
                input.type = 'text';
                input.placeholder = this.i18n.newListName || 'New list name';
                const createBtn = document.createElement('button');
                createBtn.type = 'button';
                createBtn.className = 'airy-wishlist-chooser-create-btn';
                createBtn.textContent = this.i18n.create || 'Create';
                createBtn.addEventListener('click', () => {
                    const name = input.value.trim();
                    if (!name) {
                        input.focus();
                        return;
                    }
                    this.ajax('airy_create_wishlist', { name: name }, (res) => {
                        if (res && res.success) {
                            this.closeWishlistChooser();
                            this.addToWishlist(btn, res.data.id);
                        } else {
                            this.showMessage((res && res.data && res.data.message) || this.i18n.genericError, 'error');
                        }
                    });
                });
                input.addEventListener('keydown', (ev) => {
                    if (ev.key === 'Enter') {
                        ev.preventDefault();
                        createBtn.click();
                    }
                });
                createRow.appendChild(input);
                createRow.appendChild(createBtn);
                listsWrap.appendChild(createRow);
            });
        }

        positionChooser(panel, btn) {
            const rect = btn.getBoundingClientRect();
            panel.style.position = 'absolute';
            panel.style.top = (window.scrollY + rect.bottom + 6) + 'px';
            panel.style.left = (window.scrollX + rect.left) + 'px';
            panel.style.zIndex = '99998';
        }

        closeWishlistChooser() {
            if (this._activeChooser) {
                this._activeChooser.remove();
                this._activeChooser = null;
            }
            if (this._chooserOutside) {
                document.removeEventListener('click', this._chooserOutside);
                this._chooserOutside = null;
            }
            if (this._chooserEsc) {
                document.removeEventListener('keydown', this._chooserEsc);
                this._chooserEsc = null;
            }
        }

        createWishlistPrompt() {
            const name = window.prompt(this.i18n.newListPrompt || 'Name your new list:');
            if (name === null) {
                return;
            }
            const trimmed = name.trim();
            if (!trimmed) {
                return;
            }
            this.ajax('airy_create_wishlist', { name: trimmed }, (res) => {
                if (res && res.success) {
                    window.location.href = this.buildListUrl(res.data.id);
                } else {
                    this.showMessage((res && res.data && res.data.message) || this.i18n.genericError, 'error');
                }
            });
        }

        renameWishlistPrompt(btn) {
            const wishlistId = btn.getAttribute('data-wishlist-id');
            const current = btn.getAttribute('data-current-name') || '';
            const name = window.prompt(this.i18n.renamePrompt || 'Enter a new name for this list:', current);
            if (name === null) {
                return;
            }
            const trimmed = name.trim();
            if (!trimmed) {
                return;
            }
            this.ajax('airy_rename_wishlist', { wishlist_id: wishlistId, name: trimmed }, (res) => {
                if (res && res.success) {
                    window.location.reload();
                } else {
                    this.showMessage((res && res.data && res.data.message) || this.i18n.genericError, 'error');
                }
            });
        }

        deleteWishlistPrompt(btn) {
            const wishlistId = btn.getAttribute('data-wishlist-id');
            if (!window.confirm(this.i18n.deleteConfirm || 'Delete this list and all its items?')) {
                return;
            }
            this.ajax('airy_delete_wishlist', { wishlist_id: wishlistId }, (res) => {
                if (res && res.success) {
                    window.location.href = this.buildListUrl(0);
                } else {
                    this.showMessage((res && res.data && res.data.message) || this.i18n.genericError, 'error');
                }
            });
        }

        /**
         * Build a wishlist page URL for a given list (0 = default view).
         */
        buildListUrl(listId) {
            const base = this.data.wishlistUrl || window.location.href.split('?')[0];
            if (!listId) {
                return base;
            }
            const sep = base.indexOf('?') === -1 ? '?' : '&';
            return base + sep + 'airy_list=' + encodeURIComponent(listId);
        }

        removeFromWishlist(btn) {
            const productId = btn.getAttribute('data-product-id');
            const variationId = btn.getAttribute('data-variation-id') || 0;
            const wishlistId = btn.getAttribute('data-wishlist-id') || 0;
            const row = btn.closest('.airy-wishlist-item') || btn.closest('.airy-wishlist-grid-item');

            btn.classList.add('loading');

            this.ajax('airy_remove_from_wishlist', {
                product_id: productId,
                variation_id: variationId,
                wishlist_id: wishlistId
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
            const wishlistId = btn.getAttribute('data-wishlist-id') || 0;

            btn.classList.add('loading');
            btn.disabled = true;

            this.ajax('airy_add_to_cart_from_wishlist', {
                product_id: productId,
                variation_id: variationId,
                wishlist_id: wishlistId,
                quantity: 1
            }, (response) => {
                btn.classList.remove('loading');
                btn.disabled = false;
                
                if (response.success) {
                    this.showMessage(response.data.message, 'success');

                    // Only hide the row if the server actually removed it from the
                    // wishlist (the "remove after add to cart" option is enabled).
                    if (response.data.removed) {
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
                        if (typeof response.data.count !== 'undefined') {
                            this.updateCounter(response.data.count);
                        }
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
            let succeeded = 0;
            const total = addToCartButtons.length;

            addToCartButtons.forEach((btn) => {
                const productId = btn.getAttribute('data-product-id');
                const variationId = btn.getAttribute('data-variation-id') || 0;
                const wishlistId = btn.getAttribute('data-wishlist-id') || 0;

                this.ajax('airy_add_to_cart_from_wishlist', {
                    product_id: productId,
                    variation_id: variationId,
                    wishlist_id: wishlistId,
                    quantity: 1
                }, (response) => {
                    completed++;
                    if (response && response.success) {
                        succeeded++;
                    }

                    if (completed === total) {
                        if (succeeded === total) {
                            this.showMessage(this.i18n.allAddedToCart || 'All products added to cart!', 'success');
                        } else if (succeeded > 0) {
                            this.showMessage(this.i18n.someFailed || 'Some products could not be added to cart.', 'error');
                        } else {
                            this.showMessage(this.i18n.genericError || 'An error occurred. Please try again.', 'error');
                        }

                        if (succeeded > 0) {
                            setTimeout(() => {
                                window.location.reload();
                            }, 1000);
                        }
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
        
        setButtonAdded(btn, inWishlist) {
            const textSpan = btn.querySelector('.airy-wishlist-text');

            if (inWishlist) {
                btn.classList.add('added');
                if (textSpan) {
                    textSpan.textContent = this.i18n.addedText || 'Added to Wishlist';
                }
            } else {
                btn.classList.remove('added');
                if (textSpan) {
                    textSpan.textContent = this.i18n.addText || 'Add to Wishlist';
                }
            }
        }

        updateButtons(productId, variationId, inWishlist) {
            const buttons = document.querySelectorAll(`.airy-wishlist-btn[data-product-id="${productId}"]`);

            buttons.forEach((btn) => {
                const btnVariationId = btn.getAttribute('data-variation-id') || 0;

                if (btnVariationId == variationId) {
                    this.setButtonAdded(btn, inWishlist);
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
            .catch(() => {
                this.showMessage(this.i18n.genericError || 'An error occurred. Please try again.', 'error');
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