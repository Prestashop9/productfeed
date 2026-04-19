/**
 * Product Feed — Front-office JS
 * Social feed card interactions: add to cart, infinite scroll, sidebar
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
                document.dispatchEvent(new Event('productfeed:cardsLoaded'));
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
        var badgeTag = p.badge_text
            ? '<span class="pf-card__badge">'
                + '<svg class="pf-card__badge-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
                + '<path d="M5 12h14"/><path d="M13 6l6 6-6 6"/></svg>'
                + '<span class="pf-card__badge-text">' + esc(p.badge_text) + '</span>'
              + '</span>'
            : '';
        var metaRight = (badgeTag || stickyTag)
            ? '<div class="pf-card__meta-right">' + badgeTag + stickyTag + '</div>' : '';

        var img = p.image_url
            ? '<img src="' + p.image_url + '" alt="' + esc(p.name) + '" loading="lazy">'
            : '<div class="pf-card__no-img"><i class="material-icons">image</i></div>';

        var catUrl = ajaxUrl + (ajaxUrl.includes('?') ? '&' : '?') + 'id_category=' + (p.id_category || '');
        var cat = p.category_name
            ? '<a href="' + catUrl + '" class="pf-card__category">' + esc(p.category_name) + '</a><span class="pf-card__dot"></span>' : '';

        var timeAgo = p.date_add ? formatTimeAgo(p.date_add) : '';

        return '<article class="pf-card' + stickyClass + '" data-id="' + p.id_product + '">'
            + '<div class="pf-card__header"><div class="pf-card__meta-left">' + cat
            + '<time class="pf-card__time">' + timeAgo + '</time></div>' + metaRight + '</div>'
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
            + '<span class="pf-hook-interaction" data-id-product="' + p.id_product + '" data-purchased="' + (p.is_purchased ? '1' : '0') + '"></span>'
            + (p.is_purchased
                ? ''
                : '<button class="pf-card__action-btn productfeed-add-to-cart" data-id-product="' + p.id_product + '" data-url="' + p.add_to_cart_url + '">'
                    + '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>'
                    + '<span>Add to Cart</span></button>')
            + '</div>'
            + '<div class="pf-card__actions-right">'
            + '<a href="' + p.url + '" class="pf-card__btn pf-card__btn--outline"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg> Read More</a>'
            + (p.is_purchased
                ? '<a href="' + (p.library_url || '/mylibrary') + '" class="pf-card__btn pf-card__btn--library"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg> My Library</a>'
                : '<a href="' + p.buy_now_url + '" class="pf-card__btn pf-card__btn--primary"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg> Buy Now</a>')
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
