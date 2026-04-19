<article class="pf-card{if $product.is_sticky} pf-card--sticky{/if}" data-id="{$product.id_product}">

    {* ── Header row ── *}
    <div class="pf-card__header">
        <div class="pf-card__meta-left">
            {if $show_category && $product.category_name}
                <a href="{$feed_ajax_url}?id_category={$product.id_category}" class="pf-card__category">{$product.category_name|escape:'html'}</a>
                <span class="pf-card__dot"></span>
            {/if}
            {if $show_date}
                <time class="pf-card__time" datetime="{$product.date_add|date_format:'%Y-%m-%d'}"
                      data-timestamp="{$product.date_add|escape:'html'}"></time>
            {/if}
        </div>
        {if $product.is_sticky}
            <span class="pf-card__tag pf-card__tag--pinned">{l s='Pinned' d='Modules.Productfeed.Shop'}</span>
        {/if}
    </div>

    {* ── Title & description ── *}
    <div class="pf-card__content">
        <h2 class="pf-card__title">
            <a href="{$product.url}">{$product.name|escape:'html'}</a>
        </h2>
        <p class="pf-card__excerpt">{$product.description_short|strip_tags|trim|truncate:200:'...'}</p>
    </div>

    {* ── Thumbnail with price pill + discount badge ── *}
    <div class="pf-card__thumb">
        <a href="{$product.url}" class="pf-card__thumb-link">
            {if $product.image_url}
                <img src="{$product.image_url}" alt="{$product.name|escape:'html'}" loading="lazy">
            {else}
                <div class="pf-card__no-img">
                    <i class="material-icons">image</i>
                </div>
            {/if}
        </a>
        {if $product.badge_text}
            <span class="pf-card__badge">{$product.badge_text|escape:'html'}</span>
        {/if}
        {if $show_price && $product.discount_percent > 0}
            <span class="pf-card__discount-badge">-{$product.discount_percent}%</span>
        {/if}
        {if $show_price}
            <div class="pf-card__price-pill">
                {if $product.original_price}
                    <span class="pf-card__price-original">{$product.original_price}</span>
                {/if}
                <span class="pf-card__price">{$product.price}</span>
            </div>
        {/if}
    </div>

    <div class="pf-card__divider"></div>

    {* ── Action bar ── *}
    <div class="pf-card__actions">
        <div class="pf-card__actions-left">
            {hook h='displayProductFeedCardActions' id_product=$product.id_product}
            <button class="pf-card__action-btn productfeed-add-to-cart" data-id-product="{$product.id_product}" data-url="{$product.add_to_cart_url}" aria-label="{l s='Add to Cart' d='Modules.Productfeed.Shop'}">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                <span>{l s='Add to Cart' d='Modules.Productfeed.Shop'}</span>
            </button>
        </div>
        <div class="pf-card__actions-right">
            <a href="{$product.url}" class="pf-card__btn pf-card__btn--outline">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                {l s='Read More' d='Modules.Productfeed.Shop'}
            </a>
            <a href="{$product.buy_now_url}" class="pf-card__btn pf-card__btn--primary">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                {l s='Buy Now' d='Modules.Productfeed.Shop'}
            </a>
        </div>
    </div>
</article>
