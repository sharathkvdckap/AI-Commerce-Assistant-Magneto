define([
    'jquery',
    'mage/cookies',
    'mage/translate'
], function ($, cookies, $t) {
    'use strict';

    var LOW_MSG = $t('Try another photo or use text search');

    function isLowConfidence(meta) {
        return !!(meta && (meta.overallConfidence === 'low' || meta.suggestedAction));
    }

    function escapeHtml(str) {
        return $('<div>').text(str == null ? '' : String(str)).html();
    }

    function cardHtml(item) {
        var url = escapeHtml(item.url),
            name = escapeHtml(item.name),
            score = item.score != null
                ? '<div class="klizer-vs-score">' + $t('Match') + ' ' + Math.round(item.score * 100) + '%</div>'
                : '';

        return '<li class="item product product-item">' +
            '<div class="product-item-info">' +
            '<a href="' + url + '" class="product photo product-item-photo" tabindex="-1">' +
            '<span class="product-image-container">' +
            '<span class="product-image-wrapper">' +
            '<img class="product-image-photo" src="' + escapeHtml(item.image) + '" alt="' + name + '" loading="lazy" />' +
            '</span></span></a>' +
            '<div class="product details product-item-details">' +
            '<strong class="product name product-item-name">' +
            '<a class="product-item-link" href="' + url + '">' + name + '</a>' +
            '</strong>' +
            '<div class="price-box price-final_price">' +
            '<span class="price-container price-final_price">' +
            '<span class="price">' + escapeHtml(item.price_formatted || '') + '</span>' +
            '</span></div>' +
            score +
            '</div></div></li>';
    }

    function renderGrid($container, items) {
        $container.html('<ol class="products list items product-items">' + items.join('') + '</ol>');
    }

    function uniqueValues(items, key) {
        var map = {};
        items.forEach(function (item) {
            if (key === 'categories') {
                (item.categories || []).forEach(function (c) {
                    if (c) {
                        map[c] = true;
                    }
                });
            } else if (item[key]) {
                map[item[key]] = true;
            }
        });
        return Object.keys(map).sort();
    }

    function fillSelect($select, values) {
        var current = $select.val();
        $select.find('option:not(:first)').remove();
        values.forEach(function (v) {
            $select.append($('<option></option>').val(v).text(v));
        });
        if (current && values.indexOf(current) !== -1) {
            $select.val(current);
        }
    }

    function inPriceRange(price, range) {
        if (!range) {
            return true;
        }
        var parts = String(range).split('-'),
            min = parts[0] === '' ? null : parseFloat(parts[0]),
            max = parts[1] === '' || parts[1] == null ? null : parseFloat(parts[1]);

        if (min != null && !isNaN(min) && price < min) {
            return false;
        }
        if (max != null && !isNaN(max) && price > max) {
            return false;
        }
        return true;
    }

    /**
     * Draw cropped region of an image to a Blob via canvas.
     */
    function cropToBlob(imgEl, box, previewEl, callback) {
        var previewRect = previewEl.getBoundingClientRect(),
            naturalW = imgEl.naturalWidth,
            naturalH = imgEl.naturalHeight,
            displayW = imgEl.clientWidth,
            displayH = imgEl.clientHeight,
            offsetX = (previewRect.width - displayW) / 2,
            offsetY = (previewRect.height - displayH) / 2,
            scaleX = naturalW / displayW,
            scaleY = naturalH / displayH,
            sx = Math.max(0, (box.left - offsetX) * scaleX),
            sy = Math.max(0, (box.top - offsetY) * scaleY),
            sw = Math.min(naturalW - sx, box.width * scaleX),
            sh = Math.min(naturalH - sy, box.height * scaleY),
            canvas = document.createElement('canvas'),
            ctx = canvas.getContext('2d');

        if (sw < 8 || sh < 8) {
            callback(null);
            return;
        }

        canvas.width = Math.round(sw);
        canvas.height = Math.round(sh);
        ctx.drawImage(imgEl, sx, sy, sw, sh, 0, 0, canvas.width, canvas.height);
        canvas.toBlob(function (blob) {
            callback(blob);
        }, 'image/jpeg', 0.92);
    }

    return function (config, element) {
        var $root = $(element),
            $stage = $root.find('[data-role="stage"]'),
            $dropzone = $root.find('[data-role="dropzone"]'),
            $file = $root.find('.klizer-vs-file'),
            $crop = $root.find('[data-role="crop"]'),
            $cropPreview = $root.find('[data-role="crop-preview"]'),
            $cropImg = $root.find('[data-role="crop-img"]'),
            $cropBox = $root.find('[data-role="crop-box"]'),
            $resultsPanel = $root.find('[data-role="results-panel"]'),
            $results = $root.find('[data-role="results"]'),
            $status = $root.find('[data-role="status"]'),
            $lowconf = $root.find('[data-role="lowconf"]'),
            $queryThumb = $root.find('[data-role="query-thumb"]'),
            $close = $root.find('.klizer-vs-close'),
            allItems = [],
            queryPreviewUrl = '',
            pendingFile = null,
            lastSearch = null,
            dragState = null;

        function selectedMode() {
            var fromResults = $root.find('.klizer-vs-modes--results .klizer-vs-chip.is-on').data('mode'),
                fromEntry = $root.find('input[name="matchMode"]:checked').val();

            return fromResults || fromEntry || config.matchMode || 'style';
        }

        function syncModeChips(mode) {
            $root.find('input[name="matchMode"]').each(function () {
                var on = this.value === mode;
                $(this).prop('checked', on);
                $(this).closest('.klizer-vs-chip').toggleClass('is-on', on);
            });
            $root.find('.klizer-vs-modes--results .klizer-vs-chip').each(function () {
                $(this).toggleClass('is-on', $(this).data('mode') === mode);
            });
        }

        function showStatus(msg, isError) {
            $status.text(msg || '').prop('hidden', !msg).toggleClass('is-error', !!isError);
        }

        function applyFilters() {
            var category = $root.find('[data-filter-select="category"]').val(),
                brand = $root.find('[data-filter-select="brand"]').val(),
                size = $root.find('[data-filter-select="size"]').val(),
                price = $root.find('[data-filter-select="price"]').val(),
                filtered = allItems.filter(function (item) {
                    if (category && (item.categories || []).indexOf(category) === -1) {
                        return false;
                    }
                    if (brand && item.brand !== brand) {
                        return false;
                    }
                    if (size && String(item.size) !== String(size)) {
                        return false;
                    }
                    if (!inPriceRange(Number(item.price) || 0, price)) {
                        return false;
                    }
                    return true;
                });

            if (!filtered.length) {
                $results.html('<div class="message info empty"><div>' +
                    $t('We can\'t find products matching the selection.') + '</div></div>');
                return;
            }
            renderGrid($results, filtered.map(cardHtml));
        }

        function populateFilterOptions(items) {
            fillSelect($root.find('[data-filter-select="category"]'), uniqueValues(items, 'categories'));
            fillSelect($root.find('[data-filter-select="brand"]'), uniqueValues(items, 'brand'));
            fillSelect($root.find('[data-filter-select="size"]'), uniqueValues(items, 'size'));
        }

        function showResults(res, previewUrl) {
            allItems = res.items || [];
            queryPreviewUrl = previewUrl || queryPreviewUrl;
            if (queryPreviewUrl) {
                $queryThumb.attr('src', queryPreviewUrl);
            }

            $stage.prop('hidden', true);
            $crop.prop('hidden', true);
            $resultsPanel.prop('hidden', false);
            $close.prop('hidden', false);

            if (isLowConfidence(res.meta)) {
                $lowconf.prop('hidden', false);
            } else {
                $lowconf.prop('hidden', true);
            }

            populateFilterOptions(allItems);
            applyFilters();
            showStatus('', false);
        }

        function resetToEntry() {
            allItems = [];
            pendingFile = null;
            if (queryPreviewUrl) {
                URL.revokeObjectURL(queryPreviewUrl);
                queryPreviewUrl = '';
            }
            $resultsPanel.prop('hidden', true);
            $crop.prop('hidden', true);
            $stage.prop('hidden', false);
            $close.prop('hidden', true);
            $lowconf.prop('hidden', true);
            $file.val('');
            showStatus('', false);
        }

        function initCropBox() {
            var pw = $cropPreview.width(),
                ph = $cropPreview.height(),
                w = Math.round(pw * 0.55),
                h = Math.round(ph * 0.5),
                left = Math.round((pw - w) / 2),
                top = Math.round((ph - h) / 2);

            $cropBox.css({ left: left, top: top, width: w, height: h });
        }

        function openCrop(file) {
            pendingFile = file;
            if (queryPreviewUrl) {
                URL.revokeObjectURL(queryPreviewUrl);
            }
            queryPreviewUrl = URL.createObjectURL(file);
            $cropImg.one('load', function () {
                initCropBox();
            }).attr('src', queryPreviewUrl);

            $stage.prop('hidden', true);
            $resultsPanel.prop('hidden', true);
            $crop.prop('hidden', false);
            $close.prop('hidden', false);
        }

        function uploadBlob(blob, filename, previewUrl) {
            var formData = new FormData(),
                formKey = $.mage.cookies.get('form_key'),
                preview = previewUrl || queryPreviewUrl;

            // Remember the exact subject searched (full file or cropped blob) so
            // re-searching on a match-mode change reuses the same image + preview.
            lastSearch = { blob: blob, filename: filename || 'search.jpg', preview: preview };

            formData.append('image', blob, filename || 'search.jpg');
            formData.append('matchMode', selectedMode());
            if (formKey) {
                formData.append('form_key', formKey);
            }

            showStatus($t('Searching…'), false);
            $resultsPanel.prop('hidden', false);
            $crop.prop('hidden', true);
            $stage.prop('hidden', true);

            $.ajax({
                url: config.uploadUrl,
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                showLoader: true
            }).done(function (res) {
                if (!res || !res.success) {
                    showStatus((res && res.message) || $t('Search failed'), true);
                    return;
                }
                showResults(res, preview);
            }).fail(function (xhr) {
                var msg = $t('Visual search request failed');
                try {
                    var body = xhr.responseJSON || JSON.parse(xhr.responseText || '{}');
                    if (body && body.message) {
                        msg = body.message;
                    }
                } catch (e) {}
                showStatus(msg, true);
            });
        }

        // Entry interactions
        $dropzone.on('click', function (e) {
            // Ignore clicks bubbling from the file input, upload button, or match-mode chips
            // (otherwise triggering the input click re-fires this handler → infinite recursion).
            if ($(e.target).closest('.klizer-vs-file, .klizer-vs-hero__upload, .klizer-vs-modes').length) {
                return;
            }
            $file.trigger('click');
        });
        $file.on('click', function (e) {
            e.stopPropagation();
        });
        $root.find('.klizer-vs-hero__upload').on('click', function (e) {
            e.stopPropagation();
            $file.trigger('click');
        });
        $file.on('change', function () {
            if (this.files && this.files[0]) {
                openCrop(this.files[0]);
            }
        });
        $dropzone.on('dragover', function (e) {
            e.preventDefault();
            $dropzone.addClass('is-dragover');
        }).on('dragleave drop', function (e) {
            e.preventDefault();
            $dropzone.removeClass('is-dragover');
            if (e.type === 'drop' && e.originalEvent.dataTransfer.files[0]) {
                openCrop(e.originalEvent.dataTransfer.files[0]);
            }
        });

        // Mode chips (entry radios)
        $root.on('change', 'input[name="matchMode"]', function () {
            syncModeChips(this.value);
        });

        // Mode chips (results) → re-search the same subject (full or cropped)
        $root.on('click', '.klizer-vs-modes--results .klizer-vs-chip', function () {
            var mode = $(this).data('mode');
            syncModeChips(mode);
            if (lastSearch && lastSearch.blob) {
                uploadBlob(lastSearch.blob, lastSearch.filename, lastSearch.preview);
            } else if (pendingFile) {
                uploadBlob(pendingFile, pendingFile.name || 'search.jpg', queryPreviewUrl);
            }
        });

        // Filters
        $root.on('change', '[data-filter-select]', applyFilters);
        $root.on('click', '[data-filter="instock"]', function () {
            // Server already returns in-stock only; keep chip as visual affirmation.
            $(this).addClass('is-on').attr('aria-pressed', 'true');
        });

        // Crop actions
        $root.find('[data-role="search-full"]').on('click', function () {
            if (pendingFile) {
                uploadBlob(pendingFile, pendingFile.name || 'search.jpg', queryPreviewUrl);
            }
        });
        $root.find('[data-role="search-crop"]').on('click', function () {
            if (!$cropImg[0] || !pendingFile) {
                return;
            }
            var box = {
                left: parseFloat($cropBox.css('left')) || 0,
                top: parseFloat($cropBox.css('top')) || 0,
                width: $cropBox.outerWidth(),
                height: $cropBox.outerHeight()
            };
            cropToBlob($cropImg[0], box, $cropPreview[0], function (blob) {
                if (!blob) {
                    uploadBlob(pendingFile, pendingFile.name || 'search.jpg', queryPreviewUrl);
                    return;
                }
                // Show the cropped region (not the full photo) as the query thumbnail.
                uploadBlob(blob, 'crop.jpg', URL.createObjectURL(blob));
            });
        });

        // Crop drag (move + resize BR/TL)
        $cropBox.on('mousedown touchstart', function (e) {
            var isHandle = $(e.target).data('handle'),
                ev = e.originalEvent.touches ? e.originalEvent.touches[0] : e,
                offset = $cropBox.position();

            e.preventDefault();
            dragState = {
                handle: isHandle || 'move',
                startX: ev.clientX,
                startY: ev.clientY,
                left: offset.left,
                top: offset.top,
                width: $cropBox.outerWidth(),
                height: $cropBox.outerHeight()
            };
        });
        $(document).on('mousemove.klizervs touchmove.klizervs', function (e) {
            if (!dragState) {
                return;
            }
            var ev = e.originalEvent.touches ? e.originalEvent.touches[0] : e,
                dx = ev.clientX - dragState.startX,
                dy = ev.clientY - dragState.startY,
                pw = $cropPreview.width(),
                ph = $cropPreview.height(),
                left = dragState.left,
                top = dragState.top,
                width = dragState.width,
                height = dragState.height;

            if (dragState.handle === 'move') {
                left = Math.max(0, Math.min(pw - width, dragState.left + dx));
                top = Math.max(0, Math.min(ph - height, dragState.top + dy));
            } else if (dragState.handle === 'br') {
                width = Math.max(40, Math.min(pw - left, dragState.width + dx));
                height = Math.max(40, Math.min(ph - top, dragState.height + dy));
            } else if (dragState.handle === 'tl') {
                left = Math.max(0, dragState.left + dx);
                top = Math.max(0, dragState.top + dy);
                width = Math.max(40, dragState.width - dx);
                height = Math.max(40, dragState.height - dy);
                if (left + width > pw) {
                    width = pw - left;
                }
                if (top + height > ph) {
                    height = ph - top;
                }
            }
            $cropBox.css({ left: left, top: top, width: width, height: height });
        }).on('mouseup.klizervs touchend.klizervs', function () {
            dragState = null;
        });

        $close.on('click', resetToEntry);
        $root.find('[data-role="try-again"]').on('click', resetToEntry);

        // Restore header-camera session results
        try {
            var cached = sessionStorage.getItem('klizer_vs_last_results');
            if (cached) {
                var parsed = JSON.parse(cached);
                if (parsed && parsed.items) {
                    if (parsed.queryPreview) {
                        queryPreviewUrl = parsed.queryPreview;
                    }
                    showResults(parsed, queryPreviewUrl);
                }
                sessionStorage.removeItem('klizer_vs_last_results');
            }
        } catch (e) {}
    };
});
