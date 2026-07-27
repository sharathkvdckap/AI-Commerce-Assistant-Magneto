define([
    'jquery',
    'mage/cookies',
    'mage/translate'
], function ($, cookies, $t) {
    'use strict';

    var PAGE_SIZE_DEFAULT = 0; // 0 = show all products (no client-side page limit)

    function escapeHtml(str) {
        return $('<div>').text(str == null ? '' : String(str)).html();
    }

    function humanizeKey(key) {
        return String(key || '')
            .replace(/_/g, ' ')
            .replace(/\b\w/g, function (c) { return c.toUpperCase(); });
    }

    function filterTags(filters) {
        var tags = [],
            skip = { query: true, latest_answer: true };

        Object.keys(filters || {}).forEach(function (key) {
            var value = filters[key];
            if (skip[key] || value == null || value === '') {
                return;
            }
            if (typeof value === 'object') {
                return;
            }
            tags.push({
                key: key,
                label: humanizeKey(key),
                value: String(value)
            });
        });

        return tags;
    }

    function goalTitle(filters, query) {
        if (filters && filters.query) {
            return String(filters.query);
        }
        return query || $t('Your shopping goal');
    }

    function formatPrice(item) {
        var price = item.price != null ? Number(item.price) : null,
            currency = item.currency || 'USD';

        if (price == null || isNaN(price)) {
            return '';
        }

        return (currency === 'USD' ? '$' : currency + ' ') + price.toFixed(2);
    }

    function productCard(item, absoluteIndex, options) {
        var opts = options || {},
            url = escapeHtml(item.productUrl || item.url || '#'),
            name = escapeHtml(item.name || ''),
            sku = escapeHtml(item.sku || ''),
            priceText = formatPrice(item),
            image = escapeHtml(item.imageUrl || item.image || ''),
            badge = '',
            category = (item.reasons && item.reasons[0])
                ? '<span class="klizer-ai-card__cat">' + escapeHtml(String(item.reasons[0]).replace(/^Magento category:\s*/i, '')) + '</span>'
                : '';

        if (opts.alt) {
            badge = '<span class="klizer-ai-card__badge klizer-ai-card__badge--alt">' + $t('Alt') + '</span>';
        } else if (absoluteIndex === 0) {
            badge = '<span class="klizer-ai-card__badge klizer-ai-card__badge--best">' + $t('Best Match') + '</span>';
        } else if (absoluteIndex === 1) {
            badge = '<span class="klizer-ai-card__badge klizer-ai-card__badge--value">' + $t('Best Value') + '</span>';
        }

        return '<li class="klizer-ai-item' + (opts.carousel ? ' klizer-ai-carousel__item' : '') + '">' +
            '<div class="klizer-ai-card" data-price="' +
            (item.price != null ? Number(item.price) : '') + '">' +
            '<div class="klizer-ai-card__media">' +
            category + badge +
            (image ? '<img src="' + image + '" alt="' + name + '" loading="lazy" />' : '') +
            '<a class="klizer-ai-card__cta" href="' + url + '">' + $t('View Details') + '</a>' +
            '</div>' +
            '<div class="klizer-ai-card__body">' +
            '<a class="klizer-ai-card__name" href="' + url + '">' + name + '</a>' +
            (sku ? '<span class="klizer-ai-card__sku">' + $t('SKU') + ': ' + sku + '</span>' : '') +
            (priceText ? '<span class="klizer-ai-card__price">' + escapeHtml(priceText) + '</span>' : '') +
            '</div></div></li>';
    }

    return function (config, element) {
        var $root = $(element),
            $status = $root.find('[data-role="status"]'),
            $goal = $root.find('[data-role="goal"]'),
            $goalTitle = $root.find('[data-role="goal-title"]'),
            $goalSummary = $root.find('[data-role="goal-summary"]'),
            $goalTags = $root.find('[data-role="goal-tags"]'),
            $clarify = $root.find('[data-role="clarify"]'),
            $clarifyMessage = $root.find('[data-role="clarify-message"]'),
            $clarifyOptions = $root.find('[data-role="clarify-options"]'),
            $clarifyInput = $root.find('[data-role="clarify-input"]'),
            $budget = $root.find('[data-role="budget"]'),
            $budgetSlider = $root.find('[data-role="budget-slider"]'),
            $budgetValue = $root.find('[data-role="budget-value"]'),
            $products = $root.find('[data-role="products"]'),
            $matchableSection = $root.find('[data-role="matchable-section"]'),
            $productsGrid = $root.find('[data-role="products-grid"]'),
            $productsCount = $root.find('[data-role="products-count"]'),
            $matchableBanner = $root.find('[data-role="matchable-banner"]'),
            $noExactBanner = $root.find('[data-role="no-exact-banner"]'),
            $alternativesSection = $root.find('[data-role="alternatives-section"]'),
            $alternativesCount = $root.find('[data-role="alternatives-count"]'),
            $alternativesTrack = $root.find('[data-role="alternatives-track"]'),
            $altViewport = $root.find('[data-role="alt-viewport"]'),
            $altPrev = $root.find('[data-role="alt-prev"]'),
            $altNext = $root.find('[data-role="alt-next"]'),
            $toolbar = $root.find('[data-role="toolbar"]'),
            $toolbarAmount = $root.find('[data-role="toolbar-amount"]'),
            $sorter = $root.find('[data-role="sorter"]'),
            $modeGrid = $root.find('[data-role="mode-grid"]'),
            $modeList = $root.find('[data-role="mode-list"]'),
            $pagination = $root.find('[data-role="pagination"]'),
            $paginationItems = $root.find('[data-role="pagination-items"]'),
            sessionId = null,
            allProducts = [],
            allAlternatives = [],
            filteredProducts = [],
            currentPage = 1,
            pageSize = (function () {
                var n = parseInt(config.pageSize, 10);
                if (!Number.isFinite(n) || n < 0) {
                    return PAGE_SIZE_DEFAULT;
                }
                return n; // 0 = show all products
            })(),
            viewMode = 'grid',
            sortBy = 'relevance',
            query = config.query || '';

        function formKey() {
            return $.mage.cookies.get('form_key');
        }

        function setStatus(message, isError) {
            $status
                .text(message || '')
                .toggleClass('is-error', !!isError)
                .prop('hidden', !message);
        }

        function renderGoal(data) {
            var filters = data.filters || {},
                tags = filterTags(filters),
                title = goalTitle(filters, query),
                summary = data.message || '';

            $goalTitle.text(title);
            if (summary && data.action === 'search_products') {
                $goalSummary.text(summary).prop('hidden', false);
            } else {
                $goalSummary.prop('hidden', true);
            }

            $goalTags.empty();
            tags.forEach(function (tag) {
                $goalTags.append(
                    $('<span class="klizer-ai-tag"></span>').text('✓ ' + tag.value)
                );
            });

            $goal.prop('hidden', false);
        }

        function renderClarify(data) {
            var question = data.question || data.message || '',
                options = data.options || [];

            $clarifyMessage.text(question);
            $clarifyOptions.empty();
            options.forEach(function (opt) {
                $clarifyOptions.append(
                    $('<button type="button" class="klizer-ai-chip"></button>')
                        .text(String(opt))
                        .attr('data-answer', String(opt))
                );
            });
            $clarifyInput.val('');
            $clarify.prop('hidden', false);
            $products.prop('hidden', true);
            $budget.prop('hidden', true);
            $toolbar.prop('hidden', true);
            $pagination.prop('hidden', true);
            $alternativesSection.prop('hidden', true);
            $noExactBanner.prop('hidden', true);
        }

        function getBudgetFiltered() {
            var max,
                maxAttr = Number($budgetSlider.attr('max')) || 0;

            if (!$budget.is(':visible') || !maxAttr) {
                return allProducts.slice();
            }

            max = Number($budgetSlider.val()) || 0;
            if (max >= maxAttr) {
                $budgetValue.text($t('All prices'));
                return allProducts.slice();
            }

            $budgetValue.text($t('Up to') + ' $' + max);
            return allProducts.filter(function (p) {
                return p.price == null || Number(p.price) <= max;
            });
        }

        function sortProducts(list) {
            var sorted = list.slice();

            if (sortBy === 'name_asc') {
                sorted.sort(function (a, b) {
                    return String(a.name || '').localeCompare(String(b.name || ''));
                });
            } else if (sortBy === 'price_asc') {
                sorted.sort(function (a, b) {
                    return (Number(a.price) || 0) - (Number(b.price) || 0);
                });
            } else if (sortBy === 'price_desc') {
                sorted.sort(function (a, b) {
                    return (Number(b.price) || 0) - (Number(a.price) || 0);
                });
            }
            // relevance = original AI order

            return sorted;
        }

        function effectivePageSize() {
            // 0 = no limit — show every product returned by the API
            if (!pageSize || pageSize <= 0) {
                return Math.max(filteredProducts.length, 1);
            }
            return pageSize;
        }

        function totalPages() {
            return Math.max(1, Math.ceil(filteredProducts.length / effectivePageSize()));
        }

        function updateToolbarAmount() {
            var total = filteredProducts.length,
                size = effectivePageSize(),
                from,
                to;

            if (!total) {
                $toolbarAmount.text($t('Items 0 of 0'));
                $productsCount.text('').prop('hidden', true);
                return;
            }

            from = ((currentPage - 1) * size) + 1;
            to = Math.min(currentPage * size, total);
            $toolbarAmount.text(
                $t('Items %1-%2 of %3').replace('%1', from).replace('%2', to).replace('%3', total)
            );
            $productsCount
                .text($t('%1 products').replace('%1', total))
                .prop('hidden', false);
        }

        function renderPagination() {
            var pages = totalPages(),
                html = '',
                i;

            if (pages <= 1) {
                $pagination.prop('hidden', true);
                $paginationItems.empty();
                return;
            }

            html += '<li class="klizer-ai-pages__item klizer-ai-pages__item--prev">' +
                '<button type="button" class="klizer-ai-pages__btn" data-page="' + (currentPage - 1) + '"' +
                (currentPage <= 1 ? ' disabled' : '') +
                ' title="' + $t('Previous') + '">' + $t('Prev') + '</button></li>';

            for (i = 1; i <= pages; i++) {
                if (i === currentPage) {
                    html += '<li class="klizer-ai-pages__item is-current"><span class="klizer-ai-pages__current">' +
                        i + '</span></li>';
                } else {
                    html += '<li class="klizer-ai-pages__item"><button type="button" class="klizer-ai-pages__btn" data-page="' +
                        i + '">' + i + '</button></li>';
                }
            }

            html += '<li class="klizer-ai-pages__item klizer-ai-pages__item--next">' +
                '<button type="button" class="klizer-ai-pages__btn" data-page="' + (currentPage + 1) + '"' +
                (currentPage >= pages ? ' disabled' : '') +
                ' title="' + $t('Next') + '">' + $t('Next') + '</button></li>';

            $paginationItems.html(html);
            $pagination.prop('hidden', false);
        }

        function setViewMode(mode) {
            viewMode = mode === 'list' ? 'list' : 'grid';
            $productsGrid
                .attr('data-view', viewMode)
                .toggleClass('products-grid grid', viewMode === 'grid')
                .toggleClass('products-list list', viewMode === 'list');
            $modeGrid.toggleClass('is-active', viewMode === 'grid');
            $modeList.toggleClass('is-active', viewMode === 'list');
        }

        function renderCurrentPage() {
            var start,
                size,
                pageItems,
                html;

            if (!filteredProducts.length) {
                $productsGrid.html('<div class="message info empty"><div>' +
                    $t('No products in this budget. Try raising the limit.') +
                    '</div></div>');
                $pagination.prop('hidden', true);
                updateToolbarAmount();
                return;
            }

            if (currentPage > totalPages()) {
                currentPage = totalPages();
            }

            size = effectivePageSize();
            start = (currentPage - 1) * size;
            pageItems = filteredProducts.slice(start, start + size);

            html = '<ol class="klizer-ai-products__list">' +
                pageItems.map(function (item, idx) {
                    // absoluteIndex preserves Best Match / Best Value on first AI hits only when still on page 1 & relevance
                    var absoluteIndex = (sortBy === 'relevance' && currentPage === 1) ? (start + idx) : -1;
                    return productCard(item, absoluteIndex);
                }).join('') +
                '</ol>';

            $productsGrid.html(html);
            updateToolbarAmount();
            renderPagination();
            setViewMode(viewMode);
        }

        function refreshProductView() {
            filteredProducts = sortProducts(getBudgetFiltered());
            currentPage = 1;
            renderCurrentPage();
        }

        function applyBudgetFilter() {
            refreshProductView();
        }

        function updateAltNav() {
            var el = $altViewport.get(0),
                max;

            if (!el) {
                return;
            }
            max = el.scrollWidth - el.clientWidth;
            $altPrev.prop('disabled', el.scrollLeft <= 4);
            $altNext.prop('disabled', el.scrollLeft >= max - 4);
        }

        function renderAlternatives(list) {
            var items = list || [];

            if (!items.length) {
                $alternativesSection.prop('hidden', true);
                $alternativesTrack.empty();
                return;
            }

            $alternativesTrack.html(
                items.map(function (item) {
                    return productCard(item, -1, { alt: true, carousel: true });
                }).join('')
            );
            $alternativesCount
                .text($t('%1 products').replace('%1', items.length))
                .prop('hidden', false);
            $alternativesSection.prop('hidden', false);
            $altViewport.scrollLeft(0);
            setTimeout(updateAltNav, 50);
        }

        function renderProducts(data) {
            allProducts = data.products || [];
            allAlternatives = data.alternatives || [];
            $clarify.prop('hidden', true);
            sortBy = 'relevance';
            $sorter.val('relevance');
            viewMode = 'grid';
            currentPage = 1;

            $products.prop('hidden', false);

            if (!allProducts.length && !allAlternatives.length) {
                $matchableSection.prop('hidden', false);
                $productsGrid.html('<div class="message info empty"><div>' +
                    $t('No matching products found. Try refining your answer.') +
                    '</div></div>');
                $budget.prop('hidden', true);
                $toolbar.prop('hidden', true);
                $pagination.prop('hidden', true);
                $productsCount.prop('hidden', true);
                $matchableBanner.prop('hidden', true);
                $noExactBanner.prop('hidden', true);
                renderAlternatives([]);
                return;
            }

            if (!allProducts.length && allAlternatives.length) {
                $matchableSection.prop('hidden', true);
                $toolbar.prop('hidden', true);
                $pagination.prop('hidden', true);
                $budget.prop('hidden', true);
                $noExactBanner.prop('hidden', false);
                $matchableBanner.prop('hidden', true);
                renderAlternatives(allAlternatives);
                return;
            }

            $matchableSection.prop('hidden', false);
            $noExactBanner.prop('hidden', true);
            $matchableBanner
                .text($t('These products best match what you asked for.'))
                .prop('hidden', false);

            var prices = allProducts
                    .map(function (p) { return Number(p.price); })
                    .filter(function (n) { return !isNaN(n) && n > 0; }),
                maxPrice = prices.length ? Math.ceil(Math.max.apply(null, prices) / 10) * 10 : 0,
                minPrice = prices.length ? Math.floor(Math.min.apply(null, prices) / 10) * 10 : 0;

            if (maxPrice > minPrice) {
                $budgetSlider.attr({ min: minPrice, max: maxPrice, step: 10 }).val(maxPrice);
                $budgetValue.text($t('All prices'));
                $budget.prop('hidden', false);
            } else {
                $budget.prop('hidden', true);
            }

            $toolbar.prop('hidden', false);
            refreshProductView();
            renderAlternatives(allAlternatives);
        }

        function handlePayload(data) {
            if (!data) {
                setStatus($t('Empty AI response.'), true);
                return;
            }

            if (data.sessionId) {
                sessionId = data.sessionId;
            }

            renderGoal(data);

            if (data.action === 'ask_question') {
                renderClarify(data);
                setStatus('', false);
                return;
            }

            if (data.action === 'search_products') {
                renderProducts(data);
                setStatus('', false);
                return;
            }

            setStatus(data.message || $t('Unexpected AI response.'), true);
        }

        function post(url, payload) {
            var body = payload || {};
            body.form_key = formKey();

            setStatus($t('Thinking…'), false);

            return $.ajax({
                url: url,
                method: 'POST',
                data: body,
                dataType: 'json',
                showLoader: true
            });
        }

        function start() {
            if (!query) {
                setStatus($t('Enter a search query to use the AI assistant.'), true);
                return;
            }

            post(config.startUrl, { query: query })
                .done(function (res) {
                    if (!res || !res.success) {
                        setStatus((res && res.message) || $t('AI start failed.'), true);
                        return;
                    }
                    handlePayload(res.data);
                })
                .fail(function (xhr) {
                    var msg = $t('AI assistant request failed.');
                    try {
                        var body = xhr.responseJSON || JSON.parse(xhr.responseText || '{}');
                        if (body && body.message) {
                            msg = body.message;
                        }
                    } catch (e) {}
                    setStatus(msg, true);
                });
        }

        function sendAnswer(answer) {
            if (!sessionId || !answer) {
                return;
            }

            post(config.messageUrl, { sessionId: sessionId, answer: answer })
                .done(function (res) {
                    if (!res || !res.success) {
                        setStatus((res && res.message) || $t('AI follow-up failed.'), true);
                        return;
                    }
                    handlePayload(res.data);
                })
                .fail(function (xhr) {
                    var msg = $t('AI assistant request failed.');
                    try {
                        var body = xhr.responseJSON || JSON.parse(xhr.responseText || '{}');
                        if (body && body.message) {
                            msg = body.message;
                        }
                    } catch (e) {}
                    setStatus(msg, true);
                });
        }

        $clarifyOptions.on('click', '.klizer-ai-chip', function () {
            sendAnswer($(this).attr('data-answer'));
        });

        $root.find('[data-role="clarify-send"]').on('click', function () {
            sendAnswer($.trim($clarifyInput.val()));
        });

        $clarifyInput.on('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                sendAnswer($.trim($clarifyInput.val()));
            }
        });

        $budgetSlider.on('input change', applyBudgetFilter);

        $sorter.on('change', function () {
            sortBy = $(this).val() || 'relevance';
            currentPage = 1;
            filteredProducts = sortProducts(getBudgetFiltered());
            renderCurrentPage();
        });

        $modeGrid.on('click', function () {
            setViewMode('grid');
        });
        $modeList.on('click', function () {
            setViewMode('list');
        });

        $paginationItems.on('click', 'button[data-page]', function () {
            var page = parseInt($(this).attr('data-page'), 10);
            if (!page || page < 1 || page > totalPages() || page === currentPage) {
                return;
            }
            currentPage = page;
            renderCurrentPage();
            $('html, body').animate({
                scrollTop: $products.offset().top - 80
            }, 200);
        });

        $altPrev.on('click', function () {
            var el = $altViewport.get(0);
            if (!el) {
                return;
            }
            el.scrollBy({ left: -el.clientWidth * 0.85, behavior: 'smooth' });
        });
        $altNext.on('click', function () {
            var el = $altViewport.get(0);
            if (!el) {
                return;
            }
            el.scrollBy({ left: el.clientWidth * 0.85, behavior: 'smooth' });
        });
        $altViewport.on('scroll', updateAltNav);
        $(window).on('resize.klizerAiAlt', updateAltNav);

        start();
    };
});
