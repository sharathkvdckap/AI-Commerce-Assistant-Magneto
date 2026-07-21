define([
    'jquery',
    'mage/cookies',
    'mage/translate'
], function ($, cookies, $t) {
    'use strict';

    return function (config, element) {
        var $root = $(element),
            $input = $root.find('.klizer-vs-camera__input');

        $input.on('change', function () {
            var file = this.files && this.files[0],
                formData,
                formKey,
                previewUrl;

            if (!file) {
                return;
            }

            previewUrl = URL.createObjectURL(file);
            formData = new FormData();
            formData.append('image', file);
            formData.append('matchMode', config.matchMode || 'style');
            formKey = $.mage.cookies.get('form_key');
            if (formKey) {
                formData.append('form_key', formKey);
            }

            $root.addClass('is-loading');

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
                    alert((res && res.message) || $t('Search failed'));
                    return;
                }
                try {
                    res.queryPreview = previewUrl;
                    sessionStorage.setItem('klizer_vs_last_results', JSON.stringify(res));
                } catch (e) {}
                window.location.href = config.resultsUrl;
            }).fail(function (xhr) {
                var msg = $t('Visual search request failed');
                try {
                    var body = xhr.responseJSON || JSON.parse(xhr.responseText || '{}');
                    if (body && body.message) {
                        msg = body.message;
                    }
                } catch (e) {}
                alert(msg);
                URL.revokeObjectURL(previewUrl);
            }).always(function () {
                $root.removeClass('is-loading');
                $input.val('');
            });
        });
    };
});
