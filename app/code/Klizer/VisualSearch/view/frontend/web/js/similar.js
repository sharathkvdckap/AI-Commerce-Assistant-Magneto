define([
    'jquery',
    'mage/translate'
], function ($, $t) {
    'use strict';

    function escapeHtml(str) {
        return $('<div>').text(str == null ? '' : String(str)).html();
    }

    function cardHtml(item) {
        var url = escapeHtml(item.url),
            name = escapeHtml(item.name),
            score = item.score != null
                ? '<span class="klizer-vs-scard__score">' + $t('Match') + ' ' + Math.round(item.score * 100) + '%</span>'
                : '';

        return '<a class="klizer-vs-scard" href="' + url + '" title="' + name + '">' +
            '<span class="klizer-vs-scard__img">' +
            '<img src="' + escapeHtml(item.image) + '" alt="' + name + '" loading="lazy" />' +
            '</span>' +
            '<span class="klizer-vs-scard__name">' + name + '</span>' +
            '<span class="klizer-vs-scard__price">' + escapeHtml(item.price_formatted || '') + '</span>' +
            score +
            '</a>';
    }

    function buildCarousel(items) {
        return '<div class="klizer-vs-carousel">' +
            '<button type="button" class="klizer-vs-carousel__nav klizer-vs-carousel__nav--prev" aria-label="' + $t('Previous') + '">&#8249;</button>' +
            '<div class="klizer-vs-carousel__track" data-role="track">' + items.join('') + '</div>' +
            '<button type="button" class="klizer-vs-carousel__nav klizer-vs-carousel__nav--next" aria-label="' + $t('Next') + '">&#8250;</button>' +
            '</div>';
    }

    return function (config, element) {
        var $root = $(element),
            $btn = $root.find('.klizer-vs-similar__btn'),
            $strip = $root.find('[data-role="similar-list"]'),
            $row = $root.find('[data-role="similar-row"]');

        function initCarousel() {
            var $carousel = $row.find('.klizer-vs-carousel'),
                $track = $carousel.find('[data-role="track"]'),
                $prev = $carousel.find('.klizer-vs-carousel__nav--prev'),
                $next = $carousel.find('.klizer-vs-carousel__nav--next');

            function step() {
                var $card = $track.children().first();
                if (!$card.length) {
                    return $track.width();
                }
                var cardWidth = $card.outerWidth(true),
                    perView = Math.max(1, Math.floor($track.width() / cardWidth));
                return cardWidth * perView;
            }

            function updateNav() {
                var maxScroll = $track[0].scrollWidth - $track[0].clientWidth - 1,
                    overflow = maxScroll > 0;

                $prev.toggle(overflow).prop('disabled', $track.scrollLeft() <= 0);
                $next.toggle(overflow).prop('disabled', $track.scrollLeft() >= maxScroll);
            }

            $prev.on('click', function () {
                $track.animate({ scrollLeft: $track.scrollLeft() - step() }, 250);
            });
            $next.on('click', function () {
                $track.animate({ scrollLeft: $track.scrollLeft() + step() }, 250);
            });
            $track.on('scroll', updateNav);
            $(window).on('resize.klizerVsSimilar', updateNav);

            updateNav();
        }

        $btn.on('click', function () {
            $btn.prop('disabled', true);

            $.ajax({
                url: config.url,
                method: 'GET',
                dataType: 'json',
                showLoader: true
            }).done(function (res) {
                if (!res || !res.success || !res.items || !res.items.length) {
                    $row.html('<div class="message info empty"><div>' +
                        $t('No similar products found.') + '</div></div>');
                    $strip.prop('hidden', false);
                    return;
                }

                $row.html(buildCarousel(res.items.map(cardHtml)));
                $strip.prop('hidden', false);
                initCarousel();
            }).fail(function () {
                $row.html('<div class="message info empty"><div>' +
                    $t('Unable to load similar products.') + '</div></div>');
                $strip.prop('hidden', false);
            }).always(function () {
                $btn.prop('disabled', false);
            });
        });
    };
});
