/*
 Component: Advanced Options Tab
 */

if ( true )
(function($, UI, undefined) {
    
    $.extend(UI, {
        initAdvanceOptions: function() {
            var lexiqaCheck = $('.qa-box #lexi_qa');
            var speech2textCheck = $('.s2t-box #s2t_check');
            var tagProjectionCheck = $('.tagp #tagp_check');
            
            $('.mgmt-table-options .options-box.seg_rule').hide();
            $('.mgmt-table-options .options-box.dqf_options_box').hide();

            //Check Speech to Text
            speech2textCheck.on('change', this.toggleSpeech2TextOption.bind(this));
            (Speech2Text.enabled()) ? speech2textCheck.attr('checked', true) : speech2textCheck.attr('checked', false);
            //Check Lexiqa check
            if (LXQ.checkCanActivate()) {
                (LXQ.enabled()) ? lexiqaCheck.attr('checked', true) : lexiqaCheck.attr('checked', false);
                lexiqaCheck.on('change', this.toggleLexiqaOption.bind(this));
            } else {
                $('.options-box #lexi_qa').prop( "disabled", true ).attr('checked', false);
                $('.options-box.qa-box').css({opacity: 0.6 });
            }
            //Checj Tag Projection
            if (UI.checkTpCanActivate()) {
                (UI.checkTPEnabled()) ? tagProjectionCheck.attr('checked', true) : tagProjectionCheck.attr('checked', false);
                tagProjectionCheck.on('change', this.toggleTagProjectionOption.bind(this));
            } else {
                $('.options-box #tagp_check').prop( "disabled", true ).attr('checked', false);
                $('.options-box.tagp').css({opacity: 0.6 });
            }
        },

        toggleLexiqaOption: function () {
            var selected = $('.qa-box #lexi_qa').is(':checked');
            (selected) ? LXQ.enable() : LXQ.disable();
        },

        toggleSpeech2TextOption: function () {
            var selected = $('.s2t-box #s2t_check').is(':checked');
            (selected) ? Speech2Text.enable() : Speech2Text.disable();
        },

        toggleTagProjectionOption: function () {
            var selected = $('.tagp #tagp_check').is(':checked');
            (selected) ? UI.enableTagProjectionInJob() : UI.disableTagProjectionInJob();
        }

    });
    
})(jQuery, UI ); 
