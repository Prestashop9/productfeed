{extends file='page.tpl'}

{block name='page_title'}
    {$feed_page_title}
{/block}

{block name='page_content'}
<div class="productfeed-layout" id="productfeed"
     data-ajax-url="{$feed_ajax_url}"
     data-scroll-type="{$scroll_type}"
     data-current-page="{$current_page}"
     data-total-pages="{$total_pages}"
>

    {* ===== LEFT COLUMN — Main Feed ===== *}
    <div class="productfeed-main">

        {* Filter bar — category or sort mode *}
        {if $active_category_id > 0}
            <div class="pf-filter-bar">
                <span class="pf-filter-bar__label">{$active_category_name|escape:'html'}</span>
                <span class="pf-filter-bar__count">{$total_products} {l s='products' d='Modules.Productfeed.Shop'}</span>
                <a href="{$feed_ajax_url}" class="pf-filter-bar__clear">
                    <i class="material-icons">close</i>
                    {l s='All Products' d='Modules.Productfeed.Shop'}
                </a>
            </div>
        {elseif $feed_sort === 'popular'}
            <div class="pf-filter-bar">
                <i class="material-icons" style="color:#1a73e8;font-size:20px">trending_up</i>
                <span class="pf-filter-bar__label">{l s='Popular Products' d='Modules.Productfeed.Shop'}</span>
                <span class="pf-filter-bar__count">{$total_products} {l s='products' d='Modules.Productfeed.Shop'}</span>
                <a href="{$feed_ajax_url}" class="pf-filter-bar__clear">
                    <i class="material-icons">close</i>
                    {l s='All Products' d='Modules.Productfeed.Shop'}
                </a>
            </div>
        {elseif $feed_sort === 'bestselling'}
            <div class="pf-filter-bar">
                <i class="material-icons" style="color:#1a73e8;font-size:20px">star</i>
                <span class="pf-filter-bar__label">{l s='Best Selling' d='Modules.Productfeed.Shop'}</span>
                <span class="pf-filter-bar__count">{$total_products} {l s='products' d='Modules.Productfeed.Shop'}</span>
                <a href="{$feed_ajax_url}" class="pf-filter-bar__clear">
                    <i class="material-icons">close</i>
                    {l s='All Products' d='Modules.Productfeed.Shop'}
                </a>
            </div>
        {/if}

        <div class="productfeed__list" id="productfeed-list">
            {foreach $feed_products as $product}
                {include file='module:productfeed/views/templates/front/_card.tpl' product=$product}
            {/foreach}
        </div>

        {if $scroll_type === 'pagination' && $total_pages > 1}
            <div class="products__pagination">
                <nav class="pagination__container">
                    <div class="pagination__number">
                        {l s='Showing %from%-%to% of %total% item(s)' d='Modules.Productfeed.Shop'
                            sprintf=['%from%' => (($current_page - 1) * $per_page) + 1,
                                     '%to%' => min($current_page * $per_page, $total_products),
                                     '%total%' => $total_products]}
                    </div>
                    <div class="pagination__nav">
                        <nav aria-label="{l s='Products pagination' d='Modules.Productfeed.Shop'}">
                            <ul class="pagination">
                                <li class="page-item">
                                    {if $current_page > 1}
                                        <a href="{$feed_ajax_url}?page={$current_page - 1}" class="page-link previous pf-pager-link" aria-label="{l s='Go to previous page' d='Modules.Productfeed.Shop'}">
                                            <i class="material-icons rtl-flip">&#xE314;</i>
                                            <span class="d-none d-xl-flex">{l s='Previous' d='Modules.Productfeed.Shop'}</span>
                                        </a>
                                    {else}
                                        <span class="page-link previous disabled">
                                            <i class="material-icons rtl-flip">&#xE314;</i>
                                            <span class="d-none d-xl-flex">{l s='Previous' d='Modules.Productfeed.Shop'}</span>
                                        </span>
                                    {/if}
                                </li>

                                {if $current_page > 4}
                                    <li class="page-item">
                                        <a href="{$feed_ajax_url}?page=1" class="page-link pf-pager-link">1</a>
                                    </li>
                                    <li class="page-item disabled"><span class="page-link">&hellip;</span></li>
                                {/if}

                                {for $p=max(1, $current_page - 3) to min($total_pages, $current_page + 3)}
                                    <li class="page-item{if $p == $current_page} active{/if}">
                                        {if $p == $current_page}
                                            <span class="page-link" aria-current="page">{$p}</span>
                                        {else}
                                            <a href="{$feed_ajax_url}?page={$p}" class="page-link pf-pager-link">{$p}</a>
                                        {/if}
                                    </li>
                                {/for}

                                {if $current_page < $total_pages - 3}
                                    <li class="page-item disabled"><span class="page-link">&hellip;</span></li>
                                    <li class="page-item">
                                        <a href="{$feed_ajax_url}?page={$total_pages}" class="page-link pf-pager-link">{$total_pages}</a>
                                    </li>
                                {/if}

                                <li class="page-item">
                                    {if $current_page < $total_pages}
                                        <a href="{$feed_ajax_url}?page={$current_page + 1}" class="page-link next pf-pager-link" aria-label="{l s='Go to next page' d='Modules.Productfeed.Shop'}">
                                            <span class="d-none d-xl-flex">{l s='Next' d='Modules.Productfeed.Shop'}</span>
                                            <i class="material-icons rtl-flip">&#xE315;</i>
                                        </a>
                                    {else}
                                        <span class="page-link next disabled">
                                            <span class="d-none d-xl-flex">{l s='Next' d='Modules.Productfeed.Shop'}</span>
                                            <i class="material-icons rtl-flip">&#xE315;</i>
                                        </span>
                                    {/if}
                                </li>
                            </ul>
                        </nav>
                    </div>
                </nav>
            </div>
        {/if}

        {if $scroll_type === 'infinite' && $has_more}
            <div class="productfeed__loader text-center py-4" id="productfeed-loader">
                <div class="spinner-border text-primary" role="status" style="display:none;">
                    <span class="sr-only">{l s='Loading...' d='Modules.Productfeed.Shop'}</span>
                </div>
                <button class="btn btn-outline-primary btn-lg" id="productfeed-loadmore">
                    {l s='Load More' d='Modules.Productfeed.Shop'}
                </button>
            </div>
        {/if}
    </div>

    {* ===== RIGHT COLUMN — Sidebar ===== *}
    <aside class="productfeed-sidebar">

        {* --- Popular Products Widget --- *}
        <div class="pf-widget" id="pf-widget-popular" data-type="popular">
            <div class="pf-widget__header">
                <h3 class="pf-widget__title">
                    <i class="material-icons">trending_up</i>
                    {l s='Popular Products' d='Modules.Productfeed.Shop'}
                </h3>
            </div>
            <div class="pf-widget__list" data-widget-list>
                {foreach $popular_products as $sp}
                    {include file='module:productfeed/views/templates/front/_sidebar_card.tpl' sp=$sp}
                {/foreach}
            </div>
            <a href="{$feed_ajax_url}?feed_sort=popular" class="pf-widget__more">
                <i class="material-icons">arrow_forward</i>
                {l s='Show More' d='Modules.Productfeed.Shop'}
            </a>
        </div>

        {* --- Best Selling Widget --- *}
        <div class="pf-widget" id="pf-widget-bestselling" data-type="bestselling">
            <div class="pf-widget__header">
                <h3 class="pf-widget__title">
                    <i class="material-icons">star</i>
                    {l s='Best Selling' d='Modules.Productfeed.Shop'}
                </h3>
            </div>
            <div class="pf-widget__list" data-widget-list>
                {foreach $bestselling_products as $sp}
                    {include file='module:productfeed/views/templates/front/_sidebar_card.tpl' sp=$sp}
                {/foreach}
            </div>
            <a href="{$feed_ajax_url}?feed_sort=bestselling" class="pf-widget__more">
                <i class="material-icons">arrow_forward</i>
                {l s='Show More' d='Modules.Productfeed.Shop'}
            </a>
        </div>

    </aside>

</div>
{/block}
