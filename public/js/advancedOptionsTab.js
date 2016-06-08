/*
 Component: Advanced Options Tab
 */

$.extend(UI, {
    isInCattol : false,

    initAdvanceOptions: function() {
        this.isInCattol = $('body').hasClass('cattool');
        var lexiqaCheck = $('.qa-box #lexi_qa');
        var speech2textCheck = $('.s2t-box #s2t_check');
        var tagProjectionCheck = $('.tagp #tagp_check');
        if (this.isInCattol) {
            $('.mgmt-table-options .options-box.seg_rule').hide();
            $('.mgmt-table-options .options-box.dqf_options_box').hide();

            lexiqaCheck.on('change', this.toggleLexiqaOption.bind(this));
            speech2textCheck.on('change', this.toggleSpeech2TextOption.bind(this));
            (LXQ.enabled()) ? lexiqaCheck.attr('checked', true) : lexiqaCheck.attr('checked', false);
            (Speech2Text.enabled()) ? speech2textCheck.attr('checked', true) : speech2textCheck.attr('checked', false);

            if (UI.checkTpCanActivate()) {
                (UI.checkTPEnabled()) ? tagProjectionCheck.attr('checked', true) : tagProjectionCheck.attr('checked', false);
                tagProjectionCheck.on('change', this.toggleTagProjectionOption.bind(this));
            } else {
                $('.options-box #tagp_check').prop( "disabled", true );
                $('.options-box.tagp').css({opacity: 0.6 });
            }
        }

    },

    toggleLexiqaOption: function () {
        var selected = $('.qa-box #lexi_qa').is(':checked');
        if (this.isInCattol) {
            (selected) ? LXQ.enable() : LXQ.disable();
        } else {
            // add to configuration project
        }
    },

    toggleSpeech2TextOption: function () {
        var selected = $('.s2t-box #s2t_check').is(':checked');
        if (this.isInCattol) {
            (selected) ? Speech2Text.enable() : Speech2Text.disable();
        } else {
            // add to configuration project
        }
    },

    toggleTagProjectionOption: function () {
        var selected = $('.tagp #tagp_check').is(':checked');
        if (this.isInCattol) {
            (selected) ? UI.enableTagProjectionInJob() : UI.disableTagProjectionInJob();
        } else {
            // add to configuration project
        }
    }


});
