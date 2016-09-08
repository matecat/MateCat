/*
 Component: Advanced Options Tab
 */

if ( true )
(function($, UI, undefined) {
    function showModalNotSupportedLanguages(notAcceptedLanguages, acceptedLanguages) {
        APP.alert({
            title: 'Option not available',
            okTxt: 'Continue',
            msg: "Not available for " + notAcceptedLanguages.join(", ") +
            ".</br> Only available for " + acceptedLanguages.join(", ") +"."
        });
    }

    var findInLanguagesArray = function(lang_rfc) {
        return  _.find(config.languages_array, function (e) {
            return e.code === lang_rfc ;
        });
    }

    function createSupportedLanguagesArrays(acceptedLanguages) {
        var notAcceptedLanguages = [];
        var notAcceptedLanguagesCodes = [], acceptedLanguagesCodes = [];
        var foundLang ;

        if (acceptedLanguages.indexOf(config.source_rfc) === -1 ) {
            foundLang = findInLanguagesArray( config.source_rfc );
            if ( !foundLang ) {
                notAcceptedLanguages.push( config.source_rfc );
                notAcceptedLanguagesCodes.push( config.source_rfc );
            } else {
                notAcceptedLanguagesCodes.push(foundLang.code.split("-")[0].toUpperCase());
                notAcceptedLanguages.push(foundLang.name);
            }
        }

        if (acceptedLanguages.indexOf(config.target_rfc) === -1) {
            foundLang = findInLanguagesArray( config.target_rfc );
            if ( !foundLang ) {
                notAcceptedLanguages.push( config.target_rfc );
                notAcceptedLanguagesCodes.push( config.target_rfc );
            } else {
                notAcceptedLanguages.push(foundLang.name);
                notAcceptedLanguagesCodes.push( foundLang.code.split("-")[0].toUpperCase() );
            }
        }

        acceptedLanguages.forEach(function (value, index, array) {
            var result = _.find(config.languages_array, function (e) {
                return e.code === value;
            });
            array[index] = result.name;

            if (acceptedLanguagesCodes.indexOf(result.code.split("-")[0].toUpperCase()) === -1) {
                acceptedLanguagesCodes.push(result.code.split("-")[0].toUpperCase() );
            }
        });

        return {
            accepted: acceptedLanguages,
            acceptedCodes: acceptedLanguagesCodes,
            notAccepted: notAcceptedLanguages,
            notAcceptedCodes: notAcceptedLanguagesCodes
        }
    }
    $.extend(UI, {
        
        initAdvanceOptions: function() {
            var lexiqaCheck = $('.qa-box #lexi_qa');
            var speech2textCheck = $('.s2t-box #s2t_check');
            var tagProjectionCheck = $('.tagp #tagp_check');



            $('.mgmt-table-options .options-box.dqf_options_box').hide();
            $('.mgmt-table-options .options-box.seg_rule select#segm_rule').val(config.segmentation_rule).attr("disabled", true);
            $('.mgmt-table-options .options-box.seg_rule').on("click", function () {
                APP.alert({
                    title: 'Option not editable',
                    okTxt: 'Continue',
                    msg: "Segment rules settings can only be edited <br/> when creating the project.    "
                });
            });
            //Check Lexiqa check
            if (LXQ.checkCanActivate()) {
                (LXQ.enabled()) ? lexiqaCheck.attr('checked', true) : lexiqaCheck.attr('checked', false);
                lexiqaCheck.on('change', this.toggleLexiqaOption.bind(this));
            } else {

                var LXQContainer = $('.options-box.qa-box');
                $('.options-box #lexi_qa').prop( "disabled", true ).attr('checked', false);

                var acceptedLanguagesLXQ = config.lexiqa_languages.slice();
                var languagesArraysLXQ = createSupportedLanguagesArrays(acceptedLanguagesLXQ);
                LXQContainer.find('.option-supported-languages').html(languagesArraysLXQ.acceptedCodes.join(', '));
                LXQContainer.find('.option-notsupported-languages').html(languagesArraysLXQ.notAcceptedCodes.join(', '));
                LXQContainer.find('.onoffswitch').off('click').on('click', function () {
                    showModalNotSupportedLanguages(languagesArraysLXQ.notAccepted, languagesArraysLXQ.accepted);
                });

                LXQContainer.addClass('option-unavailable');
            }
            //Check Tag Projection
            if (UI.checkTpCanActivate()) {
                (UI.checkTPEnabled()) ? tagProjectionCheck.attr('checked', true) : tagProjectionCheck.attr('checked', false);
                tagProjectionCheck.on('change', this.toggleTagProjectionOption.bind(this));
            } else {
                var tpContainer= $('.options-box.tagp');
                var sourceCode = config.source_rfc.split('-')[0];
                var targetCode = config.target_rfc.split('-')[0];
                $('.options-box #tagp_check').prop( "disabled", true ).attr('checked', false);

                var acceptedLanguagesTP = config.tag_projection_languages.slice();

                var labelArraySupportedLanguages = [];
                acceptedLanguagesTP.forEach(function (value) {
                    labelArraySupportedLanguages.push(value.replace('-', '<>').toUpperCase())
                });
                var notSupportedCouples = [];
                var languageCombinations = [sourceCode+'-'+targetCode, targetCode+'-'+sourceCode];
                languageCombinations.forEach(function(n) {
                    if (acceptedLanguagesTP.indexOf(n) === -1 && n.indexOf(sourceCode + '-') > -1) {
                        notSupportedCouples.push(n.replace('-', '<>').toUpperCase());
                    }
                });
                tpContainer.find('.option-supported-languages').html(labelArraySupportedLanguages.join(', '));
                tpContainer.find('.option-notsupported-languages').html(notSupportedCouples.join(', '));
                tpContainer.find('.onoffswitch').off('click').on('click', function () {
                    showModalNotSupportedLanguages(notSupportedCouples, labelArraySupportedLanguages);
                });
                tpContainer.addClass('option-unavailable');

            }
            //Check Speech To Text
            if (!('webkitSpeechRecognition' in window)) {
                var speech2textContainer = $('.s2t-box');
                speech2textCheck.prop( "disabled", true ).attr('checked', false);
                // speech2textContainer.css({opacity: 0.6 });
                speech2textContainer.find('.option-s2t-box-chrome-label').css('display', 'inline');
                speech2textContainer.find('.onoffswitch').on('click', function () {
                    APP.alert({
                        title: 'Option not available',
                        okTxt: 'Continue',
                        msg: "This options is only available on Chrome browser."
                    });
                });
                speech2textCheck.addClass('option-unavailable');
            } else {
                //Check Speech to Text
                speech2textCheck.on('change', this.toggleSpeech2TextOption.bind(this));
                (Speech2Text.enabled()) ? speech2textCheck.attr('checked', true) : speech2textCheck.attr('checked', false);
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
