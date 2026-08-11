define([
    'jquery'
], function ($) {
    'use strict';

    /**
     * Header search AI icon.
     * - Input has text → submit Magento mini-search (AI results page)
     * - Input empty → open AI entry landing
     *
     * Magento quickSearch disables the submit button until a 300ms-debounced
     * handler runs. Disabled buttons ignore click, so search feels broken.
     */
    return function (config, element) {
        var $root = $(element),
            $btn = $root.find('[data-role="ai-launcher"]'),
            entryUrl = config.entryUrl || '',
            searchInputSelector = config.searchInputSelector || '#search',
            formSelector = config.formSelector || '#search_mini_form',
            $form = $(formSelector),
            $input = $(searchInputSelector),
            $submit = $form.find('button.action.search[type="submit"]');

        function queryText() {
            return $.trim($input.val() || '');
        }

        function syncSearchSubmitEnabled() {
            if (!$submit.length) {
                return;
            }
            // Allow submit whenever there is text (Magento _onSubmit still blocks empty).
            $submit.prop('disabled', queryText().length === 0);
        }

        function goToAiEntry() {
            if (entryUrl) {
                window.location.href = entryUrl;
            }
        }

        function submitSearch() {
            if (!$form.length) {
                goToAiEntry();
                return;
            }
            $submit.prop('disabled', false);
            $form.trigger('submit');
        }

        if ($input.length) {
            $input.on(
                'input.klizerAiLauncher keyup.klizerAiLauncher change.klizerAiLauncher',
                syncSearchSubmitEnabled
            );
            $input.on('paste.klizerAiLauncher', function () {
                setTimeout(syncSearchSubmitEnabled, 0);
            });
            syncSearchSubmitEnabled();
        }

        // mousedown fires even when the button is still disabled; enable before click.
        $form.on('mousedown.klizerAiLauncher', 'button.action.search', function () {
            if (queryText()) {
                this.disabled = false;
            }
        });

        // Magento's debounced handler may re-disable; re-sync after it runs.
        $input.on('input.klizerAiLauncherDeferred', function () {
            setTimeout(syncSearchSubmitEnabled, 350);
        });

        $btn.on('click', function (e) {
            e.preventDefault();

            if (queryText()) {
                submitSearch();
                return;
            }

            goToAiEntry();
        });
    };
});
