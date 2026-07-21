/**
 * Copyright © Klizer. All rights reserved.
 */
define([
    'jquery',
    'mage/cookies',
    'mage/translate',
    'domReady!'
], function ($, cookies, $t) {
    'use strict';

    return function (config, element) {
        var $root = $(element),
            $input = $root.find('.image-recognition-search__input'),
            $status = $root.find('[data-role="status"]'),
            $trigger = $root.find('.image-recognition-search__trigger'),
            uploadUrl = config.uploadUrl,
            maxFileSize = parseInt(config.maxFileSize, 10) || 0,
            allowedExtensions = (config.allowedExtensions || []).map(function (ext) {
                return String(ext).toLowerCase().replace(/^\./, '');
            }),
            messages = config.messages || {};

        function setStatus(message, isError) {
            $status
                .text(message || '')
                .toggleClass('is-error', !!isError)
                .toggleClass('is-visible', !!message);
        }

        function getExtension(fileName) {
            var parts = String(fileName || '').split('.');

            return parts.length > 1 ? parts.pop().toLowerCase() : '';
        }

        function validateFile(file) {
            if (!file) {
                return false;
            }

            var extension = getExtension(file.name);

            if (allowedExtensions.length && allowedExtensions.indexOf(extension) === -1) {
                setStatus(messages.invalidType || $t('Please upload a valid image file.'), true);
                return false;
            }

            if (maxFileSize > 0 && file.size > maxFileSize) {
                setStatus(messages.tooLarge || $t('Image is too large.'), true);
                return false;
            }

            return true;
        }

        function uploadImage(file) {
            var formData = new FormData(),
                formKey = $.mage.cookies.get('form_key');

            formData.append('image', file);

            if (formKey) {
                formData.append('form_key', formKey);
            }

            $root.addClass('is-loading');
            $trigger.attr('aria-disabled', 'true');
            setStatus(messages.uploading || $t('Analyzing image...'), false);

            $.ajax({
                url: uploadUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                showLoader: true
            }).done(function (response) {
                if (response && response.success && response.redirectUrl) {
                    if (config.searchInputSelector && response.query) {
                        $(config.searchInputSelector).val(response.query);
                    }
                    window.location.href = response.redirectUrl;
                    return;
                }

                setStatus(
                    (response && response.message) || messages.genericError || $t('Unable to process the image.'),
                    true
                );
            }).fail(function () {
                setStatus(messages.genericError || $t('Unable to process the image. Please try again.'), true);
            }).always(function () {
                $root.removeClass('is-loading');
                $trigger.removeAttr('aria-disabled');
                $input.val('');
            });
        }

        $input.on('change', function () {
            var file = this.files && this.files[0];

            setStatus('', false);

            if (!validateFile(file)) {
                $input.val('');
                return;
            }

            uploadImage(file);
        });
    };
});
