import ModalsActions from './cat_source/es6/actions/ModalsActions'
import LXQ from './cat_source/es6/utils/lxq.main'
import SegmentUtils from './cat_source/es6/utils/segmentUtils'
import Speech2Text from './cat_source/es6/utils/speech2text'
import AlertModal from './cat_source/es6/components/modals/AlertModal'
import SegmentActions from './cat_source/es6/actions/SegmentActions'
import CreateProjectStore from './cat_source/es6/stores/CreateProjectStore'
;(function ($, UI) {
  $.extend(UI, {
    initAdvanceOptions: function () {
      var lexiqaCheck = $('.qa-box #lexi_qa')
      var speech2textCheck = $('.s2t-box #s2t_check')
      var tagProjectionCheck = $('.tagp #tagp_check')
      var dqfCheck = $('.dqf-box #dqf_switch')
      var segmentationRule =
        config.segmentation_rule === '' ||
        config.segmentation_rule === 'standard'
          ? ''
          : config.segmentation_rule

      $('.mgmt-table-options .options-box.dqf_options_box').hide()
      $('.mgmt-table-options .options-box.seg_rule select#segm_rule')
        .val(segmentationRule)
        .attr('disabled', true)
      $('.mgmt-table-options .options-box.seg_rule').on('click', function () {
        ModalsActions.showModalComponent(
          AlertModal,
          {
            text: 'Segment rules settings can only be edited when creating the project.',
            buttonText: 'Continue',
          },
          'Option not editable',
        )
      })
      //Check Lexiqa check
      if (LXQ.checkCanActivate()) {
        LXQ.enabled()
          ? lexiqaCheck.attr('checked', true)
          : lexiqaCheck.attr('checked', false)
        lexiqaCheck.on('change', this.toggleLexiqaOption.bind(this))
      } else {
        var notAcceptedLanguages = []
        var LXQContainer = $('.options-box.qa-box')
        var acceptedLanguagesLXQ = config.lexiqa_languages.slice()
        if (acceptedLanguagesLXQ.indexOf(config.source_rfc) === -1) {
          notAcceptedLanguages.push(CreateProjectStore.getSourceLangName())
        }
        if (acceptedLanguagesLXQ.indexOf(config.target_rfc) === -1) {
          try {
            notAcceptedLanguages.push(
              config.languages_array.find(function (e) {
                return e.code === config.target_rfc
              }).name,
            )
          } catch (e) {
            console.error(
              'initAdvanceOptions: no language found',
              config.target_rfc,
            )
          }
        }
        LXQContainer.find('.option-notsupported-languages').html(
          notAcceptedLanguages.join(', '),
        )
        $('.options-box #lexi_qa').prop('disabled', true).attr('checked', false)
        LXQContainer.addClass('option-unavailable')
        UI.setLanguageTooltipLXQ()
      }
      //Check Tag Projection
      if (SegmentUtils.checkTpCanActivate()) {
        SegmentUtils.checkTPEnabled()
          ? tagProjectionCheck.attr('checked', true)
          : tagProjectionCheck.attr('checked', false)
        tagProjectionCheck.on(
          'change',
          this.toggleTagProjectionOption.bind(this),
        )
      } else {
        var tpContainer = $('.options-box.tagp')

        $('.options-box #tagp_check')
          .prop('disabled', true)
          .attr('checked', false)
        if (config.isReview) {
          tpContainer.addClass('option-unavailable-revise')
        } else {
          var sourceLang = config.languages_array.find(function (e) {
            return e.code === config.source_rfc
          })
          var targetLang = config.languages_array.find(function (e) {
            return e.code === config.target_rfc
          })
          sourceLang = sourceLang ? sourceLang.name : config.source_rfc
          targetLang = targetLang ? targetLang.name : config.target_rfc
          var label = sourceLang + ' - ' + targetLang
          tpContainer.find('.option-notsupported-languages').html(label)
        }
        tpContainer.addClass('option-unavailable')
      }
      //Check Speech To Text
      if (!('webkitSpeechRecognition' in window)) {
        var speech2textContainer = $('.s2t-box')
        speech2textCheck.prop('disabled', true).attr('checked', false)
        // speech2textContainer.css({opacity: 0.6 });
        speech2textContainer
          .find('.option-s2t-box-chrome-label')
          .css('display', 'inline')
        speech2textContainer.find('.onoffswitch').on('click', function () {
          ModalsActions.showModalComponent(
            AlertModal,
            {
              text: 'This options is only available on your browser.',
              buttonText: 'Continue',
            },
            'Option not available',
          )
        })
        speech2textCheck.addClass('option-unavailable')
      } else {
        //Check Speech to Text
        speech2textCheck.on('change', this.toggleSpeech2TextOption.bind(this))
        Speech2Text.enabled()
          ? speech2textCheck.attr('checked', true)
          : speech2textCheck.attr('checked', false)
      }

      // Check DQF
      if (UI.checkDqfCanActivate()) {
        UI.checkDqfIsActive()
          ? dqfCheck.attr('checked', true)
          : dqfCheck.attr('checked', false)
        dqfCheck.prop('disabled', true)

        $('.dqf-box .dqf-settings').on('click', function () {
          ModalsActions.openDQFModal()
        })
      }

      // Check character counter
      const charscounterCheck = document.getElementById('charscounter_check')
      charscounterCheck.checked = SegmentUtils.isCharacterCounterEnable()
      charscounterCheck.onchange = () => SegmentActions.toggleCharacterCounter()
    },

    toggleLexiqaOption: function () {
      var selected = $('.qa-box #lexi_qa').is(':checked')
      selected ? LXQ.enable() : LXQ.disable()
    },

    toggleSpeech2TextOption: function () {
      var selected = $('.s2t-box #s2t_check').is(':checked')
      selected ? Speech2Text.enable() : Speech2Text.disable()
    },

    toggleTagProjectionOption: function () {
      var selected = $('.tagp #tagp_check').is(':checked')
      selected ? UI.enableTagProjectionInJob() : UI.disableTagProjectionInJob()
    },

    checkDqfCanActivate: function () {
      return !!config.dqf_enabled
    },
    checkDqfIsActive: function () {
      return config.dqf_active_on_project
    },
    setTagProjectionChecked: (value) =>
      ($('.tagp #tagp_check')[0].checked = value),
  })
})(jQuery, UI)
