/**
 * Product Feed — Front-office JS
 * Social feed card interactions: like, save, add to cart, infinite scroll, sidebar
 */
document.addEventListener('DOMContentLoaded', function () {
    var feed = document.getElementById('productfeed');
    if (!feed) return;

    // Measure sticky header height and set CSS variable for sidebar positioning
    var stickyHeader = document.querySelector('.js-sticky-header, #header');
    if (stickyHeader) {
        var hh = stickyHeader.offsetHeight;
        document.documentElement.style.setProperty('--pf-header-h', hh + 'px');
    }

    var ajaxUrl = feed.dataset.ajaxUrl;
    var scrollType = feed.dataset.scrollType;
    var currentPage = parseInt(feed.dataset.currentPage, 10);
    var totalPages = parseInt(feed.dataset.totalPages, 10);
    var loading = false;

    var list = document.getElementById('productfeed-list');
    var loader = document.getElementById('productfeed-loader');
    var loadMoreBtn = document.getElementById('productfeed-loadmore');

    // ==============================
    // Time Ago — convert timestamps
    // ==============================
    function formatTimeAgo(dateStr) {
        if (!dateStr) return '';
        var d = new Date(dateStr);
        var now = new Date();
        var diff = Math.floor((now - d) / 1000);

        if (diff < 60) return 'just now';
        if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
        if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
        if (diff < 604800) return Math.floor(diff / 86400) + 'd ago';
        if (diff < 2592000) return Math.floor(diff / 604800) + 'w ago';
        return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
    }

    document.querySelectorAll('.pf-card__time[data-timestamp]').forEach(function (el) {
        el.textContent = formatTimeAgo(el.dataset.timestamp);
    });

    // ==============================
    // Like & Save (persisted via AJAX)
    // ==============================
    var isLoggedIn = feed.dataset.loggedIn === '1';
    var userLikes = (feed.dataset.likes || '').split(',').filter(Boolean);
    var userSaves = (feed.dataset.saves || '').split(',').filter(Boolean);

    function bindLikeSave() {
        document.querySelectorAll('.pf-like-btn').forEach(function (btn) {
            if (btn.dataset.bound) return;
            btn.dataset.bound = '1';

            // Set initial state for dynamically loaded cards
            if (userLikes.indexOf(btn.dataset.id) !== -1) {
                btn.classList.add('is-liked');
            }

            btn.addEventListener('click', function () {
                if (!isLoggedIn) {
                    toast('Please sign in to like products', true);
                    return;
                }
                var button = this;
                var pid = button.dataset.id;
                button.disabled = true;

                var url = ajaxUrl + (ajaxUrl.includes('?') ? '&' : '?')
                    + 'ajax=1&action=like&id_product=' + pid;

                fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.success) {
                        button.classList.toggle('is-liked', data.liked);
                        if (data.liked) {
                            if (userLikes.indexOf(pid) === -1) userLikes.push(pid);
                        } else {
                            userLikes = userLikes.filter(function (x) { return x !== pid; });
                        }
                    }
                    button.disabled = false;
                })
                .catch(function () { button.disabled = false; });
            });
        });

        document.querySelectorAll('.pf-save-btn').forEach(function (btn) {
            if (btn.dataset.bound) return;
            btn.dataset.bound = '1';

            if (userSaves.indexOf(btn.dataset.id) !== -1) {
                btn.classList.add('is-saved');
            }

            btn.addEventListener('click', function () {
                if (!isLoggedIn) {
                    toast('Please sign in to save products', true);
                    return;
                }
                var button = this;
                var pid = button.dataset.id;
                button.disabled = true;

                var url = ajaxUrl + (ajaxUrl.includes('?') ? '&' : '?')
                    + 'ajax=1&action=save&id_product=' + pid;

                fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.success) {
                        button.classList.toggle('is-saved', data.saved);
                        if (data.saved) {
                            if (userSaves.indexOf(pid) === -1) userSaves.push(pid);
                        } else {
                            userSaves = userSaves.filter(function (x) { return x !== pid; });
                        }
                    }
                    button.disabled = false;
                })
                .catch(function () { button.disabled = false; });
            });
        });
    }

    bindLikeSave();

    // ==============================
    // Infinite Scroll / Load More
    // ==============================
    if (scrollType === 'infinite' && loadMoreBtn) {
        loadMoreBtn.addEventListener('click', loadMore);

        window.addEventListener('scroll', function () {
            if (loading || currentPage >= totalPages) return;
            if (window.innerHeight + window.scrollY >= document.documentElement.scrollHeight - 400) {
                loadMore();
            }
        });
    }

    function loadMore() {
        if (loading || currentPage >= totalPages) return;
        loading = true;

        var spinner = loader.querySelector('.spinner-border');
        if (spinner) spinner.style.display = 'inline-block';
        if (loadMoreBtn) loadMoreBtn.style.display = 'none';

        var nextPage = currentPage + 1;
        var url = ajaxUrl + (ajaxUrl.includes('?') ? '&' : '?') + 'ajax=1&page=' + nextPage;

        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.products && data.products.length) {
                data.products.forEach(function (p) {
                    list.insertAdjacentHTML('beforeend', buildCard(p));
                });
                currentPage = data.current_page;
                feed.dataset.currentPage = currentPage;
                bindAddToCart();
                bindLikeSave();
            }
            if (!data.has_more) {
                if (loader) loader.style.display = 'none';
            } else {
                if (spinner) spinner.style.display = 'none';
                if (loadMoreBtn) loadMoreBtn.style.display = 'inline-block';
            }
            loading = false;
        })
        .catch(function () {
            loading = false;
            if (spinner) spinner.style.display = 'none';
            if (loadMoreBtn) loadMoreBtn.style.display = 'inline-block';
        });
    }

    function buildCard(p) {
        var stickyClass = p.is_sticky ? ' pf-card--sticky' : '';
        var stickyTag = p.is_sticky
            ? '<span class="pf-card__tag pf-card__tag--pinned">Pinned</span>' : '';

        var img = p.image_url
            ? '<img src="' + p.image_url + '" alt="' + esc(p.name) + '" loading="lazy">'
            : '<div class="pf-card__no-img"><i class="material-icons">image</i></div>';

        var catUrl = ajaxUrl + (ajaxUrl.includes('?') ? '&' : '?') + 'id_category=' + (p.id_category || '');
        var cat = p.category_name
            ? '<a href="' + catUrl + '" class="pf-card__category">' + esc(p.category_name) + '</a><span class="pf-card__dot"></span>' : '';

        var timeAgo = p.date_add ? formatTimeAgo(p.date_add) : '';

        return '<article class="pf-card' + stickyClass + '" data-id="' + p.id_product + '">'
            + '<div class="pf-card__header"><div class="pf-card__meta-left">' + cat
            + '<time class="pf-card__time">' + timeAgo + '</time></div>' + stickyTag + '</div>'
            + '<div class="pf-card__content"><h2 class="pf-card__title"><a href="' + p.url + '">' + esc(p.name) + '</a></h2>'
            + '<p class="pf-card__excerpt">' + stripHtml(p.description_short || '') + '</p></div>'
            + '<div class="pf-card__thumb"><a href="' + p.url + '" class="pf-card__thumb-link">' + img + '</a>'
            + (p.discount_percent > 0 ? '<span class="pf-card__discount-badge">-' + p.discount_percent + '%</span>' : '')
            + '<div class="pf-card__price-pill">'
            + (p.original_price ? '<span class="pf-card__price-original">' + p.original_price + '</span>' : '')
            + '<span class="pf-card__price">' + p.price + '</span>'
            + '</div></div>'
            + '<div class="pf-card__divider"></div>'
            + '<div class="pf-card__actions"><div class="pf-card__actions-left">'
            + '<button class="pf-card__action-btn pf-like-btn" data-id="' + p.id_product + '">'
            + '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>'
            + '<span>Like</span></button>'
            + '<button class="pf-card__action-btn pf-save-btn" data-id="' + p.id_product + '">'
            + '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>'
            + '<span>Save</span></button>'
            + '<button class="pf-card__action-btn productfeed-add-to-cart" data-id-product="' + p.id_product + '" data-url="' + p.add_to_cart_url + '">'
            + '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>'
            + '<span>Add to Cart</span></button></div>'
            + '<div class="pf-card__actions-right">'
            + '<a href="' + p.url + '" class="pf-card__btn pf-card__btn--outline"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg> Read More</a>'
            + '<a href="' + p.buy_now_url + '" class="pf-card__btn pf-card__btn--primary"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg> Buy Now</a>'
            + '</div></div></article>';
    }

    function esc(str) {
        var d = document.createElement('div');
        d.appendChild(document.createTextNode(str));
        return d.innerHTML;
    }

    function stripHtml(html) {
        var d = document.createElement('div');
        d.innerHTML = html;
        var text = (d.textContent || d.innerText || '').trim();
        return text.length > 200 ? esc(text.substring(0, 200) + '...') : esc(text);
    }

    // ==============================
    // Add to Cart
    // ==============================
    function bindAddToCart() {
        document.querySelectorAll('.productfeed-add-to-cart').forEach(function (btn) {
            if (btn.dataset.bound) return;
            btn.dataset.bound = '1';

            btn.addEventListener('click', function (e) {
                e.preventDefault();
                var button = this;
                button.disabled = true;
                var orig = button.innerHTML;
                button.innerHTML = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg> <span>Adding...</span>';

                var fd = new FormData();
                fd.append('ajax', '1');
                fd.append('action', 'update');
                fd.append('add', '1');
                fd.append('id_product', button.dataset.idProduct);
                fd.append('qty', '1');
                fd.append('id_product_attribute', '0');
                fd.append('id_customization', '0');
                fd.append('token', prestashop.static_token);

                fetch(prestashop.urls.pages.cart, {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: fd,
                })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.success) {
                        prestashop.emit('updateCart', {
                            reason: {
                                idProduct: button.dataset.idProduct,
                                idProductAttribute: 0,
                                linkAction: 'add-to-cart',
                            },
                            resp: data,
                        });
                        toast('Product added to cart!');
                    } else {
                        var msg = (data.errors && data.errors.length) ? data.errors.join(', ') : 'Could not add to cart';
                        toast(msg, true);
                    }
                    button.innerHTML = orig;
                    button.disabled = false;
                })
                .catch(function () {
                    toast('Error adding to cart', true);
                    button.innerHTML = orig;
                    button.disabled = false;
                });
            });
        });
    }

    function toast(msg, isError) {
        var el = document.createElement('div');
        el.className = 'productfeed-cart-msg';
        if (isError) el.style.background = '#ef4444';
        el.textContent = msg;
        document.body.appendChild(el);
        setTimeout(function () {
            el.style.opacity = '0';
            el.style.transition = 'opacity 0.3s';
            setTimeout(function () { el.remove(); }, 300);
        }, 3000);
    }

    bindAddToCart();


    // Sidebar "Show More" is now a regular link — no JS needed
});
