/**
 * Copyright © Klizer. All rights reserved.
 */
define([
    'jquery',
    'domReady!'
], function ($) {
    'use strict';

    var VIEW_STORAGE_KEY = 'klizer_irs_view';

    return function (config, element) {
        var $root = $(element),
            $sort = $root.find('[data-role="irs-sort"]'),
            $limit = $root.find('[data-role="irs-limit"]'),
            $products = $root.find('[data-role="irs-products"]'),
            $viewBtns = $root.find('[data-role="irs-view"] [data-view]');

        function applyView(mode) {
            var isList = mode === 'list',
                container = isList ? 'product-list' : 'product-grid';

            $products
                .toggleClass('products-list list', isList)
                .toggleClass('products-grid grid', !isList);

            $products.find('.product-item-info').attr('data-container', container);

            $viewBtns.each(function () {
                var $btn = $(this),
                    active = $btn.data('view') === mode;

                $btn.toggleClass('is-active', active).attr('aria-pressed', active ? 'true' : 'false');
            });

            try {
                window.sessionStorage.setItem(VIEW_STORAGE_KEY, mode);
            } catch (e) {
                // Ignore storage failures (private mode, etc.).
            }
        }

        function navigateOnChange() {
            var url = $(this).val();

            if (url) {
                window.location.href = url;
            }
        }

        $sort.on('change', navigateOnChange);
        $limit.on('change', navigateOnChange);

        $viewBtns.on('click', function (event) {
            event.preventDefault();
            applyView($(this).data('view'));
        });

        try {
            applyView(window.sessionStorage.getItem(VIEW_STORAGE_KEY) || 'grid');
        } catch (e) {
            applyView('grid');
        }
    };
});
