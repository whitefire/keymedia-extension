<input type="button" class="keymedia-scale hid button"
    data-truesize='{$media.size|json}'
    {if $handler.mediaFits}
    value="{'Scale'|i18n( 'content/edit' )}"
    {else}
    disabled="disabled"
    value="{'Requires a bigger media'|i18n( 'content/edit' )}"
    {/if}
    data-versions='{$handler.toscale|json}'>
