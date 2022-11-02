import Cookies from 'js-cookie'
import _ from 'lodash'
import {createRoot} from 'react-dom/client'
import React from 'react'

import ModalsActions from './cat_source/es6/actions/ModalsActions'
import CatToolActions from './cat_source/es6/actions/CatToolActions'
import Header from './cat_source/es6/components/header/Header'
import LanguageSelector from './cat_source/es6/components/languageSelector/LanguageSelector'
import TeamsStore from './cat_source/es6/stores/TeamsStore'
import TeamConstants from './cat_source/es6/constants/TeamConstants'
import {clearNotCompletedUploads as clearNotCompletedUploadsApi} from './cat_source/es6/api/clearNotCompletedUploads'
import {projectCreationStatus} from './cat_source/es6/api/projectCreationStatus'
import {tmCreateRandUser} from './cat_source/es6/api/tmCreateRandUser'
import {createProject} from './cat_source/es6/api/createProject'
import AlertModal from './cat_source/es6/components/modals/AlertModal'
import NotificationBox from './cat_source/es6/components/notificationsComponent/NotificationBox'
import NewProject from './cat_source/es6/pages/NewProject'

APP.openOptionsPanel = function (tab, elem) {
  var elToClick = $(elem).attr('data-el-to-click') || null
  UI.openLanguageResourcesPanel(tab, elToClick)
}

APP.createTMKey = function () {
  if (
    $('.mgmt-tm .new .privatekey .btn-ok').hasClass('disabled') ||
    APP.pendingCreateTMkey
  ) {
    return false
  }
  APP.pendingCreateTMkey = true

  //call API
  const promise = tmCreateRandUser()
  promise.then(({data}) => {
    APP.pendingCreateTMkey = false
    $('tr.template-download.fade.ready ').each(function (key, fileUploadedRow) {
      if (
        $('.mgmt-panel #activetm tbody tr.mine').length &&
        $('.mgmt-panel #activetm tbody tr.mine .update input').is(':checked')
      )
        return false
      var _fileName = $(fileUploadedRow).find('.name').text()
      if (
        _fileName.split('.').pop().toLowerCase() == 'tmx' ||
        _fileName.split('.').pop().toLowerCase() == 'g'
      ) {
        UI.appendNewTmKeyToPanel({
          r: 1,
          w: 1,
          desc: _fileName,
          TMKey: data.key,
        })
        UI.setDropDown()
        return true
      }
    })

    return false
  })
  return promise
}

/**
 * ajax call to clear the uploaded files when an user refresh the home page
 * called in main.js
 */
window.clearNotCompletedUploads = function () {
  clearNotCompletedUploadsApi()
}

APP.changeTargetLang = function (lang) {
  if (localStorage.getItem('currentTargetLang') != lang) {
    localStorage.setItem('currentTargetLang', lang)
  }
}

APP.changeSourceLang = function (lang) {
  if (localStorage.getItem('currentSourceLang') != lang) {
    localStorage.setItem('currentSourceLang', lang)
  }
}

/**
 * Disable/Enable languages for LexiQA
 *
 */
APP.checkForLexiQALangs = function () {
  var acceptedLanguages = config.lexiqa_languages.slice()
  var LXQCheck = $('.options-box.qa-box')
  var notAcceptedLanguages = []
  var targetLanguages = localStorage.getItem('currentTargetLang').split(',')
  var sourceAccepted =
    acceptedLanguages.indexOf($('#source-lang').dropdown('get value')) > -1
  var targetAccepted =
    targetLanguages.filter(function (n) {
      if (acceptedLanguages.indexOf(n) === -1) {
        var elem = $.grep(config.languages_array, function (e) {
          return e.code == n
        })
        notAcceptedLanguages.push(elem[0].name)
      }
      return acceptedLanguages.indexOf(n) != -1
    }).length > 0

  if (!sourceAccepted) {
    notAcceptedLanguages.push($('#source-lang').dropdown('get text'))
  }

  LXQCheck.find('.onoffswitch').off('click')
  $('.options-box #lexi_qa').removeAttr('disabled')
  LXQCheck.removeClass('option-unavailable')
  LXQCheck.find('.option-qa-box-languages').hide()
  UI.removeTooltipLXQ()
  //disable LexiQA
  var disableLexiQA = !(
    sourceAccepted &&
    targetAccepted &&
    config.defaults.lexiqa
  )
  if (notAcceptedLanguages.length > 0) {
    LXQCheck.find('.option-notsupported-languages')
      .html(notAcceptedLanguages.join(', '))
      .show()
    LXQCheck.find('.option-qa-box-languages').show()
  }
  if (!(sourceAccepted && targetAccepted)) {
    LXQCheck.addClass('option-unavailable')
    $('.options-box #lexi_qa').prop('disabled', disableLexiQA)
    UI.setLanguageTooltipLXQ()
  }
  $('.options-box #lexi_qa').attr('checked', !disableLexiQA)
}

/**
 * Disable/Enable languages for LexiQA
 *
 */
APP.checkForTagProjectionLangs = function () {
  if ($('.options-box #tagp_check').length == 0) return

  var acceptedLanguages = config.tag_projection_languages
  var tpCheck = $('.options-box.tagp')
  var sourceLanguageCode = $('#source-lang').dropdown('get value')
  var sourceLanguageText = $('#source-lang').dropdown('get text')
  var languageCombinations = []
  var notSupportedCouples = []

  localStorage
    .getItem('currentTargetLang')
    .split(',')
    .forEach(function (value) {
      var elem = {}
      elem.targetCode = value
      elem.sourceCode = sourceLanguageCode
      elem.targetName = $(
        $('#target-lang div[data-value="' + value + '"]')[0],
      ).text()
      elem.sourceName = sourceLanguageText
      languageCombinations.push(elem)
    })
  //Intersection between the combination of choosen languages and the supported
  var arrayIntersection = languageCombinations.filter(function (n) {
    var elemST = n.sourceCode.split('-')[0] + '-' + n.targetCode.split('-')[0]
    var elemTS = n.targetCode.split('-')[0] + '-' + n.sourceCode.split('-')[0]
    if (
      typeof acceptedLanguages[elemST] == 'undefined' &&
      typeof acceptedLanguages[elemTS] == 'undefined'
    ) {
      notSupportedCouples.push(n.sourceName + ' - ' + n.targetName)
    }
    return (
      typeof acceptedLanguages[elemST] !== 'undefined' ||
      typeof acceptedLanguages[elemTS] !== 'undefined'
    )
  })

  tpCheck.removeClass('option-unavailable')
  tpCheck.find('.onoffswitch').off('click')
  tpCheck.find('.option-tagp-languages').hide()
  $('.options-box #tagp_check').removeAttr('disabled')
  var disableTP = !(
    arrayIntersection.length > 0 && config.defaults.tag_projection
  )
  if (notSupportedCouples.length > 0) {
    tpCheck
      .find('.option-notsupported-languages')
      .html(notSupportedCouples.join(', '))
      .show()
    tpCheck.find('.option-tagp-languages').show()
  }
  //disable Tag Projection
  if (arrayIntersection.length == 0) {
    tpCheck.addClass('option-unavailable')
    $('.options-box #tagp_check').prop('disabled', disableTP)
  }
  $('.options-box #tagp_check').attr('checked', !disableTP)
}

APP.getDQFParameters = function () {
  var dqf = {
    dqfEnabled: false,
  }
  if (!config.dqf_enabled) {
    return dqf
  }

  dqf.dqfEnabled = !!(
    $('#dqf_switch').prop('checked') && !$('#dqf_switch').prop('disabled')
  )

  if (dqf.dqfEnabled) {
    dqf.dqf_content_type = APP.USER.STORE.metadata.dqf_options.contentType
    dqf.dqf_industry = APP.USER.STORE.metadata.dqf_options.industry
    dqf.dqf_process = APP.USER.STORE.metadata.dqf_options.process
    dqf.dqf_quality_level = APP.USER.STORE.metadata.dqf_options.qualityLevel
  }
  return dqf
}

APP.getCreateProjectParams = function () {
  var dqf = APP.getDQFParameters()
  return {
    action: 'createProject',
    file_name: APP.getFilenameFromUploadedFiles(),
    project_name: $('#project-name').val(),
    source_lang: $('#source-lang').dropdown('get value'),
    target_lang: $('#target-lang').dropdown('get value'),
    job_subject: $('#project-subject').dropdown('get value'),
    disable_tms_engine: $('#disable_tms_engine').prop('checked')
      ? $('#disable_tms_engine').val()
      : false,
    mt_engine: $('.mgmt-mt .activemt').data('id'),
    private_keys_list: UI.extractTMdataFromTable(),
    lang_detect_files: UI.skipLangDetectArr,
    pretranslate_100: $('#pretranslate100').is(':checked') ? 1 : 0,
    lexiqa: !!(
      $('#lexi_qa').prop('checked') && !$('#lexi_qa').prop('disabled')
    ),
    speech2text: !!(
      $('#s2t_check').prop('checked') && !$('#s2t_check').prop('disabled')
    ),
    tag_projection: !!(
      $('#tagp_check').prop('checked') && !$('#tagp_check').prop('disabled')
    ),
    segmentation_rule: $('#segm_rule').val(),
    id_team: UI.UPLOAD_PAGE.getSelectedTeam(),
    dqf: dqf.dqfEnabled,
    dqf_content_type: dqf.dqf_content_type,
    dqf_industry: dqf.dqf_industry,
    dqf_process: dqf.dqf_process,
    dqf_quality_level: dqf.dqf_quality_level,
    get_public_matches: $('#activetm')
      .find('tr.mymemory .lookup input')
      .is(':checked'),
  }
}

APP.getFilenameFromUploadedFiles = function () {
  var files = ''
  $(
    '.upload-table tr:not(.failed) td.name, .gdrive-upload-table tr:not(.failed) td.name',
  ).each(function () {
    files += '@@SEP@@' + $(this).text()
  })
  return files.substr(7)
}

/**
 * Disable/Enable SpeechToText
 *
 */
APP.checkForSpeechToText = function () {
  //disable Tag Projection
  var disableS2T = !config.defaults.speech2text
  var speech2textCheck = $('.s2t-box')
  speech2textCheck.removeClass('option-unavailable')
  speech2textCheck.find('.onoffswitch').off('click')
  if (!('webkitSpeechRecognition' in window)) {
    disableS2T = true
    $('.options-box #s2t_check').prop('disabled', disableS2T)
    speech2textCheck
      .find('.option-s2t-box-chrome-label')
      .css('display', 'inline')
    speech2textCheck
      .find('.onoffswitch')
      .off('click')
      .on('click', function () {
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
  }
  $('.options-box #s2t_check').attr('checked', !disableS2T)
}

APP.checkForDqf = function () {
  var dqfCheck = $('.dqf-box #dqf_switch')
  dqfCheck.prop('disabled', false)
  dqfCheck.prop('checked', false)
  $('.dqf-box .dqf-settings').on('click', function () {
    ModalsActions.openDQFModal()
  })

  dqfCheck.off('click').on('click', function (e) {
    if (dqfCheck.prop('checked')) {
      if (_.isUndefined(APP.USER.STORE.metadata)) {
        e.stopPropagation()
        e.preventDefault()
        $('#modal').trigger('openlogin')
        return
      } else if (
        !_.isUndefined(APP.USER.STORE.metadata) &&
        (_.isUndefined(APP.USER.STORE.metadata.dqf_username) ||
          _.isUndefined(APP.USER.STORE.metadata.dqf_options))
      ) {
        e.stopPropagation()
        e.preventDefault()
      }
      ModalsActions.openDQFModal()
    }
  })

  dqfCheck.on('dqfEnable', function () {
    dqfCheck.attr('checked', true)
    dqfCheck.prop('checked', true)
  })

  dqfCheck.on('dqfDisable', function () {
    dqfCheck.attr('checked', false)
    dqfCheck.prop('checked', false)
  })
}

UI.UPLOAD_PAGE = {}

$.extend(UI.UPLOAD_PAGE, {
  init: function () {
    /*this.checkLanguagesCookie()
    this.checkGDriveEvents()
    /!**
     * LexiQA language Enable/Disable
     *!/
    APP.checkForLexiQALangs()
    /!**
     * Guess Tags language Enable/Disable
     *!/
    APP.checkForTagProjectionLangs()
    /!**
     * SpeechToText language Enable/Disable
     *!/
    APP.checkForSpeechToText()
    APP.checkForDqf()
    this.render()
    this.addEvents()
    $('#activetm').on('update', this.checkTmKeys)
    $('#activetm').on('removeTm', this.disableTmKeysFromSelect)
    $('#activetm').on('deleteTm', this.deleteTMFromSelect)*/
  },

  initDropdowns: function () {
    var self = this
    $('#tmx-select').dropdown({
      selectOnKeydown: false,
      fullTextSearch: 'exact',
      useLabels: false,
      message: {
        count: '{count} Private TMs',
        noResults: 'No TMs found.',
      },
      onAdd: function (value) {
        self.selectTm(value)
      },
      onRemove: function (removedValue) {
        self.disableTm(removedValue)
        setTimeout(self.checkMailDropDownValueSelected, 100)
      },
    })

    $('#add-tmx-option').on('click', function () {
      UI.openLanguageResourcesPanel('tm')
    })

    $('#project-subject').dropdown({
      selectOnKeydown: false,
      fullTextSearch: 'exact',
    })

    $('#project-subject').dropdown('set selected', 'general')

    $('.tmx-select .tm-info-title .icon').popup({
      html:
        "<div style='text-align: left'>By updating MyMemory, you are contributing to making MateCat better " +
        'and helping fellow MateCat users improve their translations.</br></br>' +
        'For confidential projects, we suggest adding a private TM and selecting the Update option in the Settings panel.</div>',
      position: 'bottom center',
    })
  },

  checkGDriveEvents: function () {
    var cookie = Cookies.get('gdrive_files_to_be_listed')
    if (cookie) {
      APP.tryListGDriveFiles()
    }
  },

  selectTm: function (value) {
    var tmElem = $(
      '.mgmt-table-tm #inactivetm tr.mine[data-key=' +
        value +
        '] .activate input',
    )
    if (tmElem.length > 0) {
      $(tmElem).trigger('click')
    }
    setTimeout(function () {
      UI.UPLOAD_PAGE.setTMName()
    })
  },

  disableTm: function (value) {
    var tmElem = $(
      '.mgmt-table-tm #activetm tr.mine[data-key=' +
        value +
        '] .activate input',
    )
    if (tmElem.length > 0) {
      $(tmElem).trigger('click')
    }
    setTimeout(function () {
      UI.UPLOAD_PAGE.setTMName()
    })
  },

  checkMailDropDownValueSelected: function () {
    var values = $('#tmx-select').dropdown('get value')
    if (values.length === 0) {
      $('#tmx-select').dropdown('set text', 'MyMemory Collaborative TM')
    }
  },

  setTMName: function () {
    if (
      $('#tmx-select').dropdown('get value').indexOf(',') === -1 &&
      $('#tmx-select').dropdown('get value').length > 0
    ) {
      var html = $('#tmx-select').find('div.item.active').html()
      $('#tmx-select').dropdown('set text', html)
    }
  },

  checkTmKeys: function (event, desc, key) {
    var activeTm = $('#activetm .mine')
    if (activeTm.length === 0) {
      $('#tmx-select').dropdown('set text', 'MyMemory Collaborative TM')
      $('#tmx-select').dropdown('remove selected', key)
    } else {
      var existingKey = $('#tmx-select').find(
        'div.item[data-value=' + key + ']',
      )
      if (existingKey.length > 0) {
        if (existingKey.hasClass('active')) {
          return
        } else {
          $('#tmx-select').dropdown('set selected', key)
        }
      } else {
        var html =
          '<div class="item"  data-value="' +
          key +
          '">' +
          '<span class="item-key-name">' +
          desc +
          '</span>' +
          '<span class="item-key-id">' +
          key +
          '</span>' +
          '<i class="icon-checkmark2 icon"></i>' +
          '</div>'
        $('#tmx-select div.item').first().before(html)
        setTimeout(function () {
          $('#tmx-select').dropdown('set selected', key)
        })
      }
    }
  },

  disableTmKeysFromSelect: function (event, key) {
    var existingKey = $('#tmx-select').find('div.item[data-value=' + key + ']')
    if (existingKey.length > 0) {
      if (existingKey.hasClass('active')) {
        $('#tmx-select').dropdown('remove selected', key)
      }
    }
  },

  deleteTMFromSelect: function (event, key) {
    if ($('#tmx-select').find('div.item[data-value=' + key + ']').length > 0) {
      $('#tmx-select')
        .find('div.item[data-value=' + key + ']')
        .remove()
      if ($('#tmx-select').dropdown('get value') == key) {
        $('#tmx-select').dropdown('set text', 'MyMemory Collaborative TM')
      }
    }
  },

  getSelectedTeam: function () {
    var selectedTeamId
    if (config.isLoggedIn) {
      //selectedTeamId = $('.team-dd').val();
      selectedTeamId = $('#project-team').dropdown('get value')
    }
    return selectedTeamId
  },

  restartConversions: function () {
    if ($('.template-download').length) {
      if (UI.conversionsAreToRestart()) {
        ModalsActions.showModalComponent(
          AlertModal,
          {
            text: 'Source language changed. The files must be reimported.',
            successCallback: () => UI.confirmRestartConversions(),
          },
          'Confirmation required',
        )
      }
    } else if ($('.template-gdrive').length) {
      ModalsActions.showModalComponent(
        AlertModal,
        {
          text: 'Source language changed. The files must be reimported.',
          successCallback: () => UI.confirmGDriveRestartConversions(),
        },
        'Confirmation required',
      )
    }
  },

  addEvents: function () {
    $('.supported-file-formats').click(function (e) {
      e.preventDefault()
      $('.supported-formats').show()
    })
    $('.supported-formats .x-popup').click(function (e) {
      e.preventDefault()
      $('.supported-formats').hide()
    })
    $('.more-options-cont').on('click', function (e) {
      e.preventDefault()
      APP.openOptionsPanel('tm')
    })

    $('#target-lang').dropdown({
      selectOnKeydown: false,
      fullTextSearch: 'exact',
      onChange: function () {
        UI.UPLOAD_PAGE.targetLanguageChangedCallback()
        APP.checkForLexiQALangs()
        APP.checkForTagProjectionLangs()
      },
    })

    $('#source-lang').dropdown({
      selectOnKeydown: false,
      fullTextSearch: 'exact',
      onChange: function () {
        UI.UPLOAD_PAGE.sourceLangChangedCallback()
        APP.checkForLexiQALangs()
        APP.checkForTagProjectionLangs()
      },
    })

    $('input.uploadbtn').click(function () {
      if (!$('.uploadbtn').hasClass('disabled')) {
        if (!UI.allTMUploadsCompleted()) {
          return false
        }

        $('body').addClass('creating')

        $('.error-message').hide()
        $('.uploadbtn')
          .attr('value', 'Analyzing...')
          .attr('disabled', 'disabled')
          .addClass('disabled')

        createProject(APP.getCreateProjectParams())
          .then(({data}) => {
            APP.handleCreationStatus(data.id_project, data.password)
          })
          .catch((errors) => {
            let errorMsg
            switch (errors[0].code) {
              case -230: {
                errorMsg =
                  'Sorry, file name too long. Try shortening it and try again.'
                break
              }
              case -235: {
                errorMsg =
                  'Sorry, an error occurred while creating the project, please try again after refreshing the page.'
                break
              }
              default:
                errorMsg = errors[0].message
            }
            $('.error-message').find('p').text(errorMsg)
            $('.error-message').show()
            $('.uploadbtn').attr('value', 'Analyze')
            $('body').removeClass('creating')
          })
      }
    })

    $('.upload-table').on('click', 'a.skip_link', function () {
      var fname = decodeURIComponent($(this).attr('id').replace('skip_', ''))

      UI.skipLangDetectArr[fname] = 'skip'

      var parentTd_label = $(this).parent('.label')

      $(parentTd_label).fadeOut(200, function () {
        $(this).remove()
      })
      $(parentTd_label).parent().removeClass('error')

      //analyze button should be reactivated?
      if ($('.upload-table td.error').length == 0) {
        $('.uploadbtn').removeAttr('disabled').removeClass('disabled').focus()
      }
    })

    $('#add-multiple-lang').click(function (e) {
      e.preventDefault()

      var tlAr = $('#target-lang').dropdown('get value').split(',')
      var sourceLang = $('#source-lang').dropdown('get value')
      const mountPoint = createRoot($('#languageSelector')[0])
      mountPoint.render(
        React.createElement(LanguageSelector, {
          selectedLanguagesFromDropdown: tlAr,
          languagesList: config.languages_array,
          fromLanguage: sourceLang,
          onClose: function () {
            mountPoint.unmount()
          },
          onConfirm: function (data) {
            if (data) {
              const str = data.map((e) => e.name).join(',')
              const vals = data.map((e) => e.code).join(',')
              var direction = 'ltr' // todo: this not work. Check rtl from array
              var op =
                '<div id="extraTarget" class="item" data-selected="selected" data-direction="' +
                direction +
                '" data-value="' +
                vals +
                '">' +
                str +
                '</div>'
              $('#extraTarget').remove()
              $('#target-lang div.item').first().before(op)
              setTimeout(function () {
                $('#target-lang').dropdown('set selected', vals)
              })

              $('.translate-box.target h2 .extra').remove()
              $('.translate-box.target h2').append(
                `<span class="extra">(${vals.length} languages)</span>`,
              )
            }
            mountPoint.unmount()
          },
        }),
      )
    })

    $('#disable_tms_engine').change(function () {
      if (this.checked) {
        $("input[id^='private-tm-']").prop('disabled', true)

        // $("#create_private_tm_btn").addClass("disabled", true);
      } else {
        if (!$('#create_private_tm_btn[data-key]').length) {
          $("input[id^='private-tm-']").prop('disabled', false)
          $('#create_private_tm_btn').removeClass('disabled')
        }
      }
    })

    $('input, select').change(function () {
      $('.error-message').hide()
    })
    $('input').keyup(function () {
      $('.error-message').hide()
    })
  },
  sourceLangChangedCallback: function () {
    APP.changeSourceLang($('#source-lang').dropdown('get value'))
    if ($('.template-download').length) {
      //.template-download is present when jquery file upload is used and a file is found
      if (UI.conversionsAreToRestart()) {
        ModalsActions.showModalComponent(
          AlertModal,
          {
            text: 'Source language changed. The files must be reimported.',
            successCallback: () => UI.confirmRestartConversions(),
          },
          'Confirmation required',
        )
      }
      if (UI.checkTMXLangFailure()) {
        UI.delTMXLangFailure()
      }
    } else if ($('.template-gdrive').length) {
      ModalsActions.showModalComponent(
        AlertModal,
        {
          text: 'Source language changed. The files must be reimported.',
          successCallback: () => UI.confirmGDriveRestartConversions(),
        },
        'Confirmation required',
      )
    }
  },
  targetLanguageChangedCallback: function () {
    $('.translate-box.target h2 .extra').remove()
    if (UI.checkTMXLangFailure()) {
      UI.delTMXLangFailure()
    }
    APP.changeTargetLang($('#target-lang').dropdown('get value'))
  },
})
APP.handleCreationStatus = function (id_project, password) {
  projectCreationStatus(id_project, password)
    .then(({data, status}) => {
      if (data.status == 202 || status == 202) {
        setTimeout(APP.handleCreationStatus, 1000, id_project, password)
      } else {
        APP.postProjectCreation(data)
      }
    })
    .catch(({errors}) => {
      APP.postProjectCreation({errors})
    })
}

APP.postProjectCreation = function (d) {
  if (typeof d.lang_detect !== 'undefined') {
    UI.skipLangDetectArr = d.lang_detect
  }

  if (UI.skipLangDetectArr != null) {
    $.each(UI.skipLangDetectArr, function (file, status) {
      if (status == 'ok') UI.skipLangDetectArr[file] = 'skip'
      else UI.skipLangDetectArr[file] = 'detect'
    })
  }

  if (typeof d.errors != 'undefined' && d.errors.length) {
    $('.error-message').find('p').text('')

    $.each(d.errors, function () {
      switch (this.code) {
        //no useful memories found in TMX
        case -16:
          UI.addTMXLangFailure()
          break
        case -14:
          UI.addInlineMessage('.tmx', this.message)
          break
        //no text to translate found.
        case -1:
          var fileName = this.message
            .replace('No text to translate in the file ', '')
            .replace(/.$/g, '')

          console.log(fileName)
          UI.addInlineMessage(
            fileName,
            'Is this a scanned file or image?<br/>Try converting to DOCX using an OCR software ' +
              '(ABBYY FineReader or Nuance PDF Converter)',
          )
          break
        case -17:
          $.each(d.lang_detect, function (fileName, status) {
            if (status == 'detect') {
              UI.addInlineMessage(
                fileName,
                'Different source language. <a class="skip_link" id="skip_' +
                  fileName +
                  '">Ignore</a>',
              )
            }
          })
          break

        default:
      }

      //normal error management
      $('.error-message').find('p').text(this.message)
      $('.error-message').show()
    })

    $('.uploadbtn').attr('value', 'Analyze')
    $('body').removeClass('creating')
  } else {
    //reset the clearNotCompletedUploads event that should be called in main.js onbeforeunload
    //--> we don't want to delete the files on the upload directory
    clearNotCompletedUploads = function () {}

    if (config.analysisEnabled) {
      //this should not be.
      //A project now are never EMPTY, it is not created anymore
      if (d.status == 'EMPTY') {
        console.log('EMPTY')
        $('body').removeClass('creating')
        ModalsActions.showModalComponent(
          AlertModal,
          {
            text: 'No text to translate in the file(s).<br />Perhaps it is a scanned file or an image?',
            buttonText: 'Continue',
          },
          'No text to translate',
        )
        $('.uploadbtn')
          .attr('value', 'Analyze')
          .removeAttr('disabled')
          .removeClass('disabled')
      } else {
        location.href = d.analyze_url
      }
    } else {
      if (Object.keys(d.target_language).length > 1) {
        //if multiple language selected show a job list
        d.files = []
        d.trgLangHumanReadable = $('#target-lang')
          .dropdown('get text')
          .split(',')
        d.srcLangHumanReadable = $('#source-lang').dropdown('get text')

        $.each(d.target_language, function (idx, val) {
          d.files.push({
            href:
              config.hostpath +
              config.basepath +
              'translate/' +
              d.project_name +
              '/' +
              d.source_language.substring(0, 2) +
              '-' +
              val.substring(0, 2) +
              '/' +
              d.id_job[idx] +
              '-' +
              d.password[idx],
          })
        })

        $('.uploadbtn-box').fadeOut('slow', function () {
          $('.uploadbtn-box').replaceWith(tmpl('job-links-list', d))

          var btnContainer = $('.btncontinue')
          var btnNew = $('#add-files').clone()
          btnContainer
            .fadeOut('slow', function () {
              btnContainer.html('').addClass('newProject')
              btnNew.children('span').text('New Project')
              btnNew.children('i').remove()
              btnNew.children('input').remove()
              btnNew
                .attr({id: 'new-project'})
                .on('click', function () {
                  location.href = config.hostpath + config.basepath
                })
                .css({margin: 'auto 0'})
              btnNew.appendTo(btnContainer)
            })
            .css({height: '50px'})
            .fadeIn(1000)

          $('.translate-box input, .translate-box select').attr({
            disabled: 'disabled',
          })
          $('td.delete').empty()
          $('#info-login').fadeIn(1000)
          $('#project-' + d.id_project).fadeIn(1000)
        })
      } else {
        location.href =
          config.hostpath +
          config.basepath +
          'translate/' +
          d.project_name +
          '/' +
          d.source_language.substring(0, 2) +
          '-' +
          d.target_language[0].substring(0, 2) +
          '/' +
          d.id_job[0] +
          '-' +
          d.password[0]
      }
    }
  }
}

$(document).ready(function () {
  UI.UPLOAD_PAGE.init()
  //TODO: REMOVE
  let currentTargetLangs = localStorage.getItem('currentSourceLang')
  let currentSourceLangs = localStorage.getItem('currentTargetLang')
  if (currentSourceLangs) {
    currentSourceLangs = config.currentSourceLang
  }

  if (currentTargetLangs) {
    currentTargetLangs = config.currentTargetLang
  }
  const newProjectPage = document.getElementsByClassName('new_project__page')[0]
  const rootNewProjectPage = createRoot(newProjectPage)
  rootNewProjectPage.render(
    <NewProject
      isLoggedIn={config.isLoggedIn}
      languages={config.languages_array.map((lang) => {
        return {...lang, id: lang.code}
      })}
      sourceLanguageSelected={currentSourceLangs}
      targetLanguagesSelected={currentTargetLangs}
      subjectsArray={config.subject_array.map((item) => {
        return {...item, id: item.key, name: item.display}
      })}
    />,
  )

  const mountPoint = document.getElementsByClassName('notifications-wrapper')[0]
  const root = createRoot(mountPoint)
  root.render(<NotificationBox />)
})
