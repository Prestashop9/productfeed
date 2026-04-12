<a href="{$sp.url}" class="pf-sidebar-card">
    <div class="pf-sidebar-card__img">
        {if $sp.image_url}
            <img src="{$sp.image_url}" alt="{$sp.name|escape:'html'}" loading="lazy">
        {else}
            <div class="pf-sidebar-card__no-img">
                <i class="material-icons">image</i>
            </div>
        {/if}
    </div>
    <div class="pf-sidebar-card__info">
        <span class="pf-sidebar-card__name">{$sp.name|escape:'html'}</span>
        {if $sp.category_name}
            <span class="pf-sidebar-card__cat">{$sp.category_name|escape:'html'}</span>
        {/if}
        <span class="pf-sidebar-card__price">{$sp.price}</span>
    </div>
</a>
