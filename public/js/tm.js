import Cookies from 'js-cookie'
import CommonUtils from './cat_source/es6/utils/commonUtils'
import SegmentActions from './cat_source/es6/actions/SegmentActions'
import ConfirmMessageModal from './cat_source/es6/components/modals/ConfirmMessageModal'
import {checkTMKey} from './cat_source/es6/api/checkTMKey'
import {createNewTmKey} from './cat_source/es6/api/createNewTmKey'
import {updateTmKey} from './cat_source/es6/api/updateTmKey'
import {deleteTmKey} from './cat_source/es6/api/deleteTmKey'
import {getInfoTmKey} from './cat_source/es6/api/getInfoTmKey'
import {shareTmKey} from './cat_source/es6/api/shareTmKey'
import {tmCreateRandUser} from './cat_source/es6/api/tmCreateRandUser'
import {updateJobKeys} from './cat_source/es6/api/updateJobKeys'
import {addMTEngine as addMTEngineApi} from './cat_source/es6/api/addMTEngine'
import {deleteMTEngine} from './cat_source/es6/api/deleteMTEngine'
import {downloadTMX as downloadTMXApi} from './cat_source/es6/api/downloadTMX'
import {loadTMX} from './cat_source/es6/api/loadTMX'
import {loadGlossaryFile} from './cat_source/es6/api/loadGlossaryFile'
import AlertModal from './cat_source/es6/components/modals/AlertModal'
import ShareTmModal from './cat_source/es6/components/modals/ShareTmModal'
import ModalsActions from './cat_source/es6/actions/ModalsActions'
import CatToolActions from './cat_source/es6/actions/CatToolActions'
import {downloadGlossary} from './cat_source/es6/api/downloadGlossary'
import TEXT_UTILS from './cat_source/es6/utils/textUtils'
;(function ($) {
  function isVisible($el) {
    var winTop = $(window).scrollTop()
    var winBottom = winTop + $(window).height()
    var elTop = $el.offset().top
    var elBottom = elTop + $el.height()
    return elBottom <= winBottom && elTop >= winTop
  }

  var checkAnalyzabilityTimer

  $.extend(UI, {
    initTM: function () {
      // script per lo slide del pannello di manage tmx
      UI.setDropDown()
      UI.initOptionsTip()
      UI.initTmxTooltips()
      //Fix: When you move to a page using the browser arrow the checkbox seems to be not checked
      $('#activetm').find('tr:not(.new) input[checked]').prop('checked', true)
      $('#inactivetm').find('tr.mine input').prop('checked', false)

      UI.checkTMKeysUpdateChecks()
      UI.checkCrossLanguageSettings()
      $('.popup-tm .x-popup, .popup-tm h1 .continue').click(function (e) {
        e.preventDefault()
        UI.saveTMdata(true)
      })

      $('.outer-tm').click(function () {
        UI.saveTMdata(true)
      })

      $('.popup-tm li.mgmt-tm').click(function (e) {
        e.preventDefault()
        $(this).addClass('active')
        $('.mgmt-mt,.mgmt-opt').removeClass('active')
        $('.mgmt-table-mt').hide()
        $('.mgmt-table-tm').show()
        $('.mgmt-table-options').hide()
      })
      $('.popup-tm .tm-mgmt').click(function (e) {
        e.preventDefault()
        $('.mgmt-mt').addClass('active')
        $('.mgmt-tm,.mgmt-opt').removeClass('active')
        $('.mgmt-table-tm,.mgmt-table-options').hide()
        $('.mgmt-table-mt').show()
      })
      $('.popup-tm .mgmt-opt').click(function (e) {
        e.preventDefault()
        $('.mgmt-opt').addClass('active')
        $('.mgmt-tm,.mgmt-mt').removeClass('active')
        $('.mgmt-table-tm,.mgmt-table-mt').hide()
        $('.mgmt-table-options').show()
      })

      $('li.mgmt-mt').click(function (e) {
        e.preventDefault()
        $(this).addClass('active')
        $('.mgmt-tm,.mgmt-opt').removeClass('active')
        $('.mgmt-table-tm').hide()
        $('.mgmt-table-mt').show()
        $('.mgmt-table-options').hide()
      })
      $('#mt_engine').change(function () {
        if ($(this).val() == 0) {
          $('table.mgmt-mt tr.activemt').removeClass('activemt')
        } else {
          var checkbox = $(
            'table.mgmt-mt tr[data-id=' + $(this).val() + '] .enable-mt input',
          )
          UI.activateMT(checkbox)
        }
      })
      $('#mt_engine_int').change(function () {
        // $('#add-mt-provider-cancel').hide();
        $('#mt-provider-details .error').empty()
        $('#add-mt-provider-confirm').addClass('disabled')
        $('.insert-tm').show()
        var provider = $(this).val()
        if (provider == 'none') {
          $('.step2 .fields').html('')
          $('.step2').hide()
          $('.step3').hide()
          $('#add-mt-provider-cancel').show()
        } else {
          $('.step2 .fields').html(
            $('#mt-provider-' + provider + '-fields').html(),
          )
          $('.step3 .text-left').html(
            $('#mt-provider-' + provider + '-msg').html(),
          )
          $('.step2').show()
          $('.step3').show()
          $('#add-mt-provider-confirm').removeClass('hide')
        }
        if (provider === 'mmt') {
          $('.mgmt-container .tooltip-preimport').data(
            'powertip',
            "<div style='line-height: 20px;font-size: 15px;text-align: left;'>" +
              'If the option is enabled, all the TMs linked to your Matecat account' +
              '<br/> will be automatically imported to your ModernMT account for adaptation purposes.' +
              '<br/>If the option is not enabled, the only TMs imported to your ModernMT account' +
              '<br/> will be those used on projects that use ModernMT as their MT engine.</div>',
          )
          $('.mgmt-container .tooltip-preimport').powerTip({
            placement: 's',
          })

          $('.mgmt-container .tooltip-context_analyzer').data(
            'powertip',
            "<div style='line-height: 20px;font-size: 15px;text-align: left;'>" +
              'If the option is enabled, ModernMT will adapt the suggestions provided for a job' +
              '<br/> using mainly the content of the TMs that you activate for that job and your corrections during translation,' +
              '<br/>but it will also scan all your other TMs for further adaptation based on the context of the document that you are translating.' +
              '<br/> If the option is not enabled, ModernMT will only adapt based on the TMs that you activate for a job and on your corrections during translation.</div>',
          )
          $('.mgmt-container .tooltip-context_analyzer').powerTip({
            placement: 's',
          })

          $('.mgmt-container .tooltip-pretranslate').data(
            'powertip',
            "<div style='line-height: 20px;font-size: 15px; text-align: left;'>" +
              'If the option is enabled, ModernMT is used during the analysis phase.' +
              '<br/> This makes downloading drafts from the translation interface quicker, ' +
              '<br/>but may lead to additional charges for plans other than the "Professional" one.' +
              '<br>If the option is not enabled, ModernMT is only used to provide adaptive ' +
              '<br/>suggestions when opening segments.</div>',
          )
          $('.mgmt-container .tooltip-pretranslate').powerTip({
            placement: 's',
          })
        }
        if (provider === 'letsmt') {
          // Tilde MT (letsmt) uses a standalone web component
          // we'll hide the button because it's easier to use the webcomponent's builtin buttons
          $('#add-mt-provider-confirm').addClass('hide')
          // when done, we'll want to simulate clicking the original button. for this it must be enabled
          $('#add-mt-provider-confirm').removeClass('disabled')
        }
      })
      $('.add-mt-engine').click(function () {
        if ($(this).hasClass('disabled')) {
          var props = {
            modalName: 'mmt-message-modal-not-logged-in',
            text: 'If you want to add an MT engine for use in your projects, please login first.',
            successText: 'Login',
            successCallback: function () {
              ModalsActions.onCloseModal()
              $('#modal').trigger('openlogin')
            },
            warningText: 'Cancel',
            warningCallback: function () {
              ModalsActions.onCloseModal()
            },
          }
          ModalsActions.showModalComponent(
            ConfirmMessageModal,
            props,
            'Add MT Engine',
          )
          return false
        }
        $(this).hide()
        UI.resetMTProviderPanel()
        $('.mgmt-table-mt tr.new').removeClass('hide').show()
        $('#add-mt-provider-cancel').show()
        $('#add-mt-provider-confirm').addClass('hide')
        $('.insert-tm').removeClass('hide')
      })

      $('#add-mt-provider-confirm').click(function (e) {
        e.preventDefault()
        if ($(this).hasClass('disabled')) return false
        var provider = $('#mt_engine_int').val()
        var providerName = $('#mt_engine_int option:selected').text()
        UI.addMTEngine(provider, providerName)
      })
      $('#add-mt-provider-cancel').click(function () {
        $('.add-mt-engine').show()
        $('.insert-tm').addClass('hide')
        $('#mt_engine_int').val('none').trigger('change')
        $('.insert-tm').addClass('hide').removeAttr('style')
        $('#add-mt-provider-cancel').show()
      })
      $('#add-mt-provider-cancel-int').click(function () {
        $('.add-mt-engine').show()
        $('.insert-tm').addClass('hide')
        $('#mt_engine_int').val('none').trigger('change')
        $('.insert-tm').addClass('hide').removeAttr('style')
        $('#add-mt-provider-cancel').show()
      })
      $('html').on('input', '#mt-provider-details input', function () {
        let num = 0
        $('#mt-provider-details input.required').each(function () {
          if ($(this).val() == '') num++
        })
        if (num) {
          $('#add-mt-provider-confirm').addClass('disabled')
        } else {
          $('#add-mt-provider-confirm').removeClass('disabled')
        }
      })

      // script per fare apparire e scomparire la riga con l'upload della tmx
      $('body')
        .on(
          'click',
          'tr a.canceladdtmx, tr a.cancelsharetmx, tr.ownergroup a.canceladdtmx, tr a.canceladdglossary, tr.ownergroup a.canceladdglossary, #inactivetm tr.new .action .addtmxfile',
          function () {
            $(this).parents('tr').find('.action a').removeClass('disabled')
            $(this)
              .parents('td.uploadfile, .share-tmx-container')
              .slideToggle(function () {
                $(this).remove()
              })
            UI.hideAllBoxOnTables()
          },
        )
        .on('mousedown', '.addtmx:not(.disabled)', function (e) {
          e.preventDefault()
          UI.addFormUpload(this, 'tmx')
        })
        .on('mousedown', '.addGlossary:not(.disabled)', function (e) {
          e.preventDefault()
          UI.addFormUpload(this, 'glossary')
        })
        .on('paste', '#shared-tm-key', function () {
          // set Timeout to get the text value after paste event, otherwise it is empty
          setTimeout(function () {
            UI.checkTMKey('change')
          }, 200)
        })
        .on('input', '#shared-tm-key', function () {
          // set Timeout to get the text value after paste event, otherwise it is empty
          $('#activetm tr.new .uploadtm').removeClass('disabled')
        })
        .on('click', '.mgmt-tm tr.new a.uploadtm:not(.disabled)', function () {
          UI.createNewTmKey()
        })
        .on('keydown', '.new #new-tm-description', function (e) {
          if (e.key === 'Enter') {
            e.preventDefault()
            UI.createNewTmKey()
          }
        })
        .on('click', 'tr .uploadfile .addtmxfile:not(.disabled)', function () {
          $(this).addClass('disabled')
          $(this).parents('.uploadfile').find('.error').text('').hide()
          UI.execAddTMOrGlossary(this, 'tmx')
        })
        .on(
          'click',
          'tr .uploadfile .addglossaryfile:not(.disabled)',
          function () {
            $(this).addClass('disabled')
            $(this).parents('.uploadfile').find('.error').text('').hide()

            UI.execAddTMOrGlossary(this, 'glossary')
          },
        )
        .on('click', '.mgmt-tm tr.mine td.description .edit-desc', function () {
          // $('.mgmt-tm .edit-desc[contenteditable=true]').blur();
          $(
            '#activetm tr.mine td.description .edit-desc:not(.current)',
          ).removeAttr('contenteditable')

          $(this).attr('contenteditable', true)
          $(this).focus()
        })
        .on(
          'blur',
          '#activetm td.description .edit-desc, #inactivetm td.description .edit-desc',
          function () {
            $(this).removeAttr('contenteditable')
            UI.saveTMdescription($(this))
          },
        )
        .on(
          'keydown',
          '.mgmt-tm td.description .edit-desc',
          'return',
          function (e) {
            if (e.which == 13) {
              e.preventDefault()
              $(this).trigger('blur')
            }
          },
        )
        .on('click', '.popup-tm h1 .btn-ok', function (e) {
          e.preventDefault()
          UI.saveTMdata(true)
        })
        .on(
          'click',
          '#activetm tr.new a.addtmxfile:not(.disabled)',
          function () {
            UI.checkTMKey('tm')
            $(this).addClass('disabled')
          },
        )
        .on('click', 'a.disabletm', function () {
          UI.disableTM(this)
        })
        .on('change', '.mgmt-table-tm tr.mine .activate input', function () {
          UI.enableTM(this)
        })
        .on('click', '.mgmt-table-mt tr .enable-mt input', function () {
          if ($(this).is(':checked')) {
            UI.activateMT(this)
          } else {
            UI.deactivateMT(this)
          }
        })
        .on(
          'click',
          '#activetm .lookup input, #activetm .update input',
          function () {
            var tr = $(this).parents('tr.mine')
            if (
              !tr.find('td.lookup input').is(':checked') &&
              !tr.find('td.update input').is(':checked')
            ) {
              UI.checkTMGrantsModifications(this)
              tr.find('.activate input').prop('checked', false)
            }
            UI.checkTMKeysUpdateChecks()
          },
        )
        .on('click', '.mgmt-table-mt tr .action .deleteMT', function () {
          UI.showMTDeletingMessage($(this))
        })
        .on('click', 'a.usetm', function () {
          UI.useTM(this)
        })
        .on('change', '#new-tm-read, #new-tm-write', function () {
          UI.checkTMgrants()
        })
        .on(
          'change',
          'tr td.uploadfile input[type="file"], tr.ownergroup td.uploadfile input[type="file"]',
          function () {
            UI.hideAllBoxOnTables()
            if (this.files && this.files[0].size > config.maxTMXFileSize) {
              const numMb = config.maxTMXFileSize / (1024 * 1024)
              ModalsActions.showModalComponent(
                AlertModal,
                {
                  text:
                    'File is too big.<br/>The maximuxm size allowed is ' +
                    numMb +
                    'MB.',
                  buttonText: 'OK',
                },
                'File too big',
              )
              return false
            }
            if ($(this).val() == '') {
              $(this)
                .parents('.uploadfile')
                .find('.addtmxfile, .addglossaryfile')
                .hide()
            } else {
              $(this)
                .parents('.uploadfile')
                .find('.addtmxfile, .addglossaryfile')
                .removeClass('disabled')
                .show()
              $(this)
                .parents('.uploadfile')
                .find('.upload-file-msg-error')
                .hide()
            }
          },
        )
        .on('keyup', '#filterInactive', function () {
          if ($(this).val() == '') {
            $('#inactivetm').removeClass('filtering')
            $('#inactivetm tbody tr.found').removeClass('found')
          } else {
            $('#inactivetm').addClass('filtering')
            UI.filterInactiveTM($('#filterInactive').val())
          }
        })
        .on('mousedown', '.mgmt-tm .downloadtmx:not(.disabled)', function (e) {
          e.preventDefault()

          UI.openExport(this, 'tmx')
        })
        .on('click', '.shareKey:not(.disabled)', function () {
          var tr = $(this).closest('tr')
          if (
            tr.hasClass('mymemory') ||
            ((tr.hasClass('ownergroup') || tr.hasClass('anonymous')) &&
              !config.isLoggedIn)
          )
            return
          UI.openShareResource($(this))
        })
        .on('mousedown', '.mgmt-tm .downloadGlossary', function () {
          if ($(this).hasClass('disabled')) return false
          UI.openExport($(this), 'glossary')
        })
        .on('mousedown', '.mgmt-tm .export-tmx .export-button', function (e) {
          e.preventDefault()
          UI.startExport(this, 'tmx')
        })
        .on(
          'mousedown',
          '.mgmt-tm .export-glossary .export-button',
          function (e) {
            e.preventDefault()
            UI.startExport(this, 'glossary')
          },
        )
        .on('keydown', '.export-tmx .email-export.mgmt-input', function (e) {
          if (e.which == 13) {
            // enter
            e.preventDefault()
            UI.startExport(this, 'tmx')
          }
          UI.hideAllBoxOnTables()
        })
        .on(
          'keydown',
          '.export-glossary .email-export.mgmt-input',
          function (e) {
            if (e.which == 13) {
              // enter
              e.preventDefault()
              UI.startExport(this, 'glossary')
            }
            UI.hideAllBoxOnTables()
          },
        )
        .on('mousedown', '.mgmt-tm .canceladd-export', function (e) {
          e.preventDefault()
          UI.closeExport($(this).closest('tr'))
          UI.hideAllBoxOnTables()
        })
        .on('mousedown', '.mgmt-tm .deleteTM:not(.disabled)', function (e) {
          e.preventDefault()
          UI.showDeleteTmMessage(this)
        })
        .on('keydown', function (e) {
          var esc = 27

          var handleEscPressed = function () {
            if ($('.popup-tm.open').length) {
              e.stopPropagation()
              UI.closeTMPanel()
              UI.clearTMPanel()
              return
            }
          }

          if (e.which == esc) handleEscPressed()
        })
        .on('click', '.share-button', function (e) {
          e.preventDefault()
          UI.clickOnShareButton($(this))
        })
        .on('keydown', '.message-share-tmx-input-email', function (e) {
          $(this).removeClass('error')
          UI.hideAllBoxOnTables()
          if (e.which == 13) {
            e.preventDefault()
            UI.clickOnShareButton($(this).parent().find('.share-button'))
          }
        })
        .on('change', '#multi-match-1, #multi-match-2', function () {
          UI.storeMultiMatchLangs()
        })
      $('.popup-tm.slide-panel').on('scroll', function () {
        if (!isVisible($('.active-tm-container thead'))) {
          $('.active-tm-container .notification-message').addClass('fixed-msg')
        } else {
          $('.active-tm-container .notification-message').removeClass(
            'fixed-msg',
          )
        }
        if (!isVisible($('.inactive-tm-container h3'))) {
          $('.inactive-tm-container .notification-message').addClass(
            'fixed-msg',
          )
        } else {
          $('.inactive-tm-container .notification-message').removeClass(
            'fixed-msg',
          )
        }
      })

      // script per filtrare il contenuto dinamicamente, da qui: http://www.datatables.net

      $(document).ready(function () {
        UI.setTMsortable()
        UI.checkCreateTmKeyFromQueryString()
        UI.checkOpenTabFromParameters()
      })

      $('.mgmt-table-tm .add-tm').click(function () {
        // $(this).hide()
        UI.openAddNewTm()
      })
      $('.mgmt-table-tm .add-shared-tm').click(function () {
        // $(this).hide()
        UI.openAddNewTmShared()
      })
      $(
        '.mgmt-tm tr.new .canceladdtmx, .mgmt-tm tr.new .canceladdglossary',
      ).click(function () {
        UI.clearTMPanel()
      })

      $('.add-gl').click(function () {
        $(this).hide()
        $('.addrow-gl').show()
      })

      $('.cancel-tm').click(function () {
        $('.mgmt-tm tr.new').hide()
        $('.add-tm').show()
      })

      $('#sign-in').click(function () {
        $('.loginpopup').show()
      })
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
    },
    enableTM: function (el) {
      UI.checkTMGrantsModifications(el)
      if (APP.isCattool) UI.saveTMdata(false)
      UI.checkTMKeysUpdateChecks()
    },
    disableAllTM: function () {
      $('#activetm tr.mine .activate input').trigger('click')
    },
    checkOpenTabFromParameters: function () {
      var keyParam = CommonUtils.getParameterByName('openTab')
      if (keyParam) {
        window.history.pushState(
          '',
          document.title,
          document.location.href.split('?')[0],
        )
        switch (keyParam) {
          case 'options':
            this.openLanguageResourcesPanel('opt')
            break
          case 'tm':
            this.openLanguageResourcesPanel('tm')
            break
        }
      }
    },
    createNewTmKey: function () {
      if ($('#shared-tm-key').is(':visible')) {
        UI.addSharedTmKey()
        return
      }
      const descKey = $('#new-tm-description').val()
      UI.saveTMkey(descKey)
    },
    addSharedTmKey: function () {
      const descKey = $('#new-tm-description').val()
      const key = $('#shared-tm-key').val()
      UI.checkTMKey('key', key).then((success) => {
        if (success) {
          createNewTmKey({
            key: key,
            description: descKey,
          })
            .then(() => {
              UI.hideAllBoxOnTables()
            })
            .catch((errors) => {
              setTimeout(function () {
                if (errors[0].code === '23000') {
                  UI.showErrorOnActiveTMTable('The key you entered is invalid.')
                } else {
                  UI.showErrorOnActiveTMTable(errors[0].message)
                }
              }, 200)
            })
        }
      })
    },
    checkCreateTmKeyFromQueryString: function () {
      var keyParam = CommonUtils.getParameterByName('private_tm_key')
      if (keyParam) {
        //Check if present and enable it
        var keyActive = UI.checkTMKeyIsActive(keyParam)
        if (keyActive) {
          return false
        }
        var keyInactive = UI.checkTMKeyIsInactive(keyParam)
        if (keyInactive) {
          UI.selectTm(keyParam)
          return false
        }
        //Create the TM Key
        var keyParams = {
          r: true,
          w: true,
          desc: 'No Description',
          TMKey: keyParam,
        }
        this.appendNewTmKeyToPanel(keyParams)
        new UI.DropDown(
          $('#activetm tr.mine').last().find('.wrapper-dropdown-5'),
        )
        UI.checkTMKeysUpdateChecks()
      }
    },
    activateInactiveKey: function (keyParam) {
      var objectsArray = $('#inactivetm tr:not(".new") .privatekey')
      var trKey = $.grep(objectsArray, function (value) {
        if ($(value).text().slice(-5) == keyParam.slice(-5)) {
          return value
        }
      })
      //Check the inputs
      var row = $(trKey).closest('tr')
      row
        .find('td.lookup input, td.activate input, td.update input')
        .attr('checked', true)
      UI.useTM(trKey)
      UI.checkTMKeysUpdateChecks()
      setTimeout(function () {
        $('#activetm').trigger('update', ['', keyParam])
      }, 500)
    },
    openLanguageResourcesPanel: function (tab, elToClick) {
      if ($('.popup-tm').hasClass('open')) {
        return false
      }
      tab = tab || 'tm'
      elToClick = elToClick || null
      $('body').addClass('side-popup')
      $('.outer-tm').show()
      $('.popup-tm').addClass('open').show().animate({right: '0px'}, 400)
      setTimeout(function () {
        $('.mgmt-panel-tm .nav-tabs .mgmt-' + tab).click()
      }, 100)
      $('.mgmt-panel-tm .nav-tabs .mgmt-' + tab).click()
      if (elToClick) $(elToClick).click()
      // $.cookie('tmpanel-open', 1, { path: '/' });
    },
    setTMsortable: function () {
      var fixHelper = function (e, ui) {
        ui.children().each(function () {
          $(this).width($(this).width())
        })
        return ui
      }

      $('#activetm tbody').sortable({
        helper: fixHelper,
        handle: '.dragrow',
        items: '.mine',
      })
    },

    checkTMKey: function (operation, keyValue) {
      //check if the key already exists, it can not be sent nor added twice
      if (keyValue === '') {
        UI.showErrorOnKeyInput()
        return Promise.resolve(false)
      }

      var keyActive = this.checkTMKeyIsActive(keyValue)
      var keyInactive = this.checkTMKeyIsInactive(keyValue)

      if (keyActive) {
        UI.showErrorOnKeyInput('The key is already present in this project.')
        return Promise.resolve(false)
      } else if (keyInactive) {
        UI.showErrorOnKeyInput(
          'The key is already assigned to one of your Inactive TMs. <a class="active-tm-key-link activate-key">Click here to activate it</a>',
        )
        setTimeout(function () {
          $('.activate-key').off('click')
          $('.activate-key').on('click', function () {
            UI.clearTMPanel()
            UI.activateInactiveKey(keyValue)
          })
        }, 500)
        return Promise.resolve(false)
      }

      const promise = checkTMKey({
        tmKey: keyValue,
      })
        .then((data) => {
          if (data.success === true) {
            UI.removeErrorOnKeyInput()
            if (operation == 'key') {
              UI.addTMKeyToList(false, keyValue)
              UI.clearTMUploadPanel()
            }
            return true
          }
        })
        .catch(() => {
          UI.showErrorOnKeyInput('The key is not valid.')
          setTimeout(function () {
            $('.active-tm-key-link').off('click')
            $('.active-tm-key-link').on('click', function () {
              UI.openAddNewTm()
              UI.removeErrorOnKeyInput()
            })
          }, 500)
          return false
        })
      return promise
    },
    checkTMKeyIsActive: function (key) {
      var keys_of_the_job = $('#activetm tbody tr:not(".new") .privatekey')
      var keyIsAlreadyPresent = false
      $(keys_of_the_job).each(function (index, value) {
        if ($(value).text().slice(-5) == key.slice(-5)) {
          keyIsAlreadyPresent = true
          return false
        }
      })
      return keyIsAlreadyPresent
    },
    checkTMKeyIsInactive: function (key) {
      var keys_of_the_job = $('#inactivetm tbody tr:not(".new") .privatekey')
      var keyIsAlreadyPresent = false
      $(keys_of_the_job).each(function (index, value) {
        if ($(value).text().slice(-5) == key.slice(-5)) {
          keyIsAlreadyPresent = true
          return false
        }
      })
      return keyIsAlreadyPresent
    },
    showErrorOnKeyInput: function (message) {
      if (message) {
        this.showErrorOnActiveTMTable(message)
      }
      $('#activetm tr.new').addClass('badkey')
      UI.checkTMAddAvailability() //some enable/disable stuffs
    },
    removeErrorOnKeyInput: function () {
      this.hideAllBoxOnTables()
      $('#activetm tr.new').removeClass('badkey')
      UI.checkTMAddAvailability()
    },
    checkTMAddAvailability: function () {
      if (
        $('#activetm tr.new').hasClass('badkey') ||
        $('#activetm tr.new').hasClass('badgrants')
      ) {
        $('#activetm tr.new .uploadtm').addClass('disabled')
      } else {
        $('#activetm tr.new .uploadtm').removeClass('disabled')
      }
    },

    checkTMgrants: function () {
      const panel = $('.mgmt-tm tr.new')
      var r = $(panel).find('.r').is(':checked') ? 1 : 0
      var w = $(panel).find('.w').is(':checked') ? 1 : 0
      if (!r && !w) {
        $('#activetm tr.new').addClass('badgrants')
        UI.showErrorOnActiveTMTable(
          'Either "Lookup" or "Update" must be checked',
        )
        UI.checkTMAddAvailability()

        return false
      } else {
        UI.hideAllBoxOnTables()
        $('#activetm tr.new').removeClass('badgrants')
        UI.checkTMAddAvailability()

        return true
      }
    },
    checkTMGrantsModifications: function (el) {
      var tr = $(el).parents('tr.mine')
      var isActive =
        $(tr).parents('table').attr('id') == 'activetm' ? true : false
      var deactivate =
        isActive &&
        !tr.find('.lookup input').is(':checked') &&
        !tr.find(' td.update input').is(':checked')

      if (!tr.find('.activate input').is(':checked') || deactivate) {
        if (isActive) {
          if (!config.isLoggedIn) {
            var data = {
              grant: $(el).parents('td').hasClass('lookup')
                ? 'lookup'
                : 'update',
              key: $(tr).find('.privatekey').text(),
            }
            ModalsActions.showModalComponent(
              ConfirmMessageModal,
              {
                text: 'If you confirm this action, your Private TM key will be lost. <br />If you want to avoid this, please, log in with your account now.',
                successText: 'Continue',
                cancelText: 'Cancel',
                successCallback: () => UI.continueTMDisable(data),
                cancelCallback: () => UI.cancelTMDisable(data),
                closeOnSuccess: true,
              },
              'Confirmation required',
            )
            return false
          }
          UI.disableTM(el)
          $('#activetm').trigger('removeTm', [$(tr).find('.privatekey').text()])
        }
      } else {
        if (!isActive) {
          UI.useTM(el)
          setTimeout(function () {
            $('#activetm').trigger('update', [
              '',
              $(tr).find('.privatekey').text(),
            ])
          }, 500)
        }
      }
    },
    cancelTMDisable: function (options) {
      $(
        '.mgmt-tm tr[data-key="' +
          options.key +
          '"] td.' +
          options.grant +
          ' input',
      ).click()
    },
    continueTMDisable: function (options) {
      const el = $(
        '.mgmt-tm tr[data-key="' +
          options.key +
          '"] td.' +
          options.grant +
          ' input',
      )
      UI.disableTM(el)
      $('#activetm').trigger('deleteTm', [options.key])
    },

    disableTM: function (el) {
      var row = $(el).closest('tr')
      if (row.find('td.uploadfile').length) {
        row.find('td.uploadfile .canceladdtmx').click()
        row.find('.addtmx').removeAttr('style')
      }
      row.detach()
      $('#inactivetm').prepend(row)

      row.css('display', 'block')

      // draw the user's attention to it
      row.fadeOut()
      row.fadeIn()

      $('.addtmxrow').hide()
    },

    useTM: function (el) {
      var row = $(el).closest('tr')
      row.detach()
      $('#activetm tr.new').before(row)
      if (!$('#inactivetm tbody tr:not(.noresults)').length)
        $('#inactivetm tr.noresults').show()
      row.addClass('mine')
      row.find('td.lookup input, td.update input').attr('checked', true)
      row.find('td.lookup input, td.update input').prop('checked', true)
      row.css('display', 'block')

      //update datatable struct
      // draw the user's attention to it
      row.fadeOut()
      row.fadeIn()

      $('.addtmxrow').hide()
    },
    execAddTMOrGlossary: function (el, type) {
      const action =
        type == 'glossary' ? '/api/v2/glossaries/import/' : '/?action=loadTMX'
      const line = $(el).parents('tr')
      line.find('.uploadfile').addClass('uploading')
      const form = line.find('.add-TM-Form')[0]
      const filesLength = $(form).find('input[type=file]').get(0).files.length
      if (filesLength > 10) {
        UI.showErrorUpload(
          $(form).parents('.uploadfile'),
          'You can only upload a maximum of 10 files',
        )
        return
      }
      var path = line.find('.uploadfile').find('input[type="file"]').val()
      var file = path.split('\\')[path.split('\\').length - 1]
      this.fileUpload(form, action, 'uploadCallback', file, type)
    },
    addTMKeyToList: function (uploading, key) {
      var descr = $('#new-tm-description').val()
      descr = descr.length ? descr : 'Private resource'
      var keyParams = {
        r: $('#new-tm-read').is(':checked'),
        w: $('#new-tm-write').is(':checked'),
        desc: descr,
        TMKey: key,
      }

      this.appendNewTmKeyToPanel(keyParams)
      new UI.DropDown($('#activetm tr.mine').last().find('.wrapper-dropdown-5'))
      if (uploading) {
        $('.mgmt-tm tr.new').addClass('hide')
      } else {
        $('.mgmt-tm tr.new .canceladdtmx').click()
      }

      UI.pulseTMadded($('#activetm tr.mine').last())

      if (APP.isCattool) UI.saveTMdata(false)
      UI.checkTMKeysUpdateChecks()
      if (config.isLoggedIn) {
        UI.checkKeyIsShared(key)
      }
      this.initTmxTooltips()
    },
    checkKeyIsShared: function (key) {
      UI.getUserSharedKey(key).then((response) => {
        var users = response.data
        if (users.length > 1) {
          $('tr.mine[data-key=' + key + '] .icon-owner')
            .removeClass('icon-lock icon-owner-private')
            .addClass('icon-users icon-owner-shared')
        }
      })
    },
    /**
     * Row structure
     * @var keyParams
     *
     * <code>
     * var keyParams = {
     *       r: 1|0,
     *       w: 1|0,
     *       desc: "string",
     *       TMKey: "string"
     *   };
     * </code>
     */
    appendNewTmKeyToPanel: function (keyParams) {
      keyParams = {
        r: typeof keyParams.r !== 'undefined' ? keyParams.r : 0,
        w: typeof keyParams.w !== 'undefined' ? keyParams.w : 0,
        desc: typeof keyParams.desc !== 'undefined' ? keyParams.desc : '',
        TMKey: typeof keyParams.TMKey !== 'undefined' ? keyParams.TMKey : '',
      }

      var newTr =
        '<tr class="mine" data-tm="1" data-glos="1" data-key="' +
        keyParams.TMKey +
        '" data-owner="' +
        config.ownerIsMe +
        '">' +
        '    <td class="dragrow"><div class="status"></div></td>' +
        '    <td class="activate"><input type="checkbox" checked="checked"/></td>' +
        '    <td class="lookup check text-center"><input type="checkbox"' +
        (keyParams.r ? ' checked="checked"' : '') +
        ' /></td>' +
        '    <td class="update check text-center"><input type="checkbox"' +
        (keyParams.w ? ' checked="checked"' : '') +
        ' /></td>' +
        '    <td class="description"><div class="edit-desc" data-descr="' +
        keyParams.desc +
        '">' +
        keyParams.desc +
        '</div></td>' +
        '    <td class="privatekey">' +
        keyParams.TMKey +
        '</td>' +
        '    <td class="owner text-center">' +
        '       <a class="icon-owner icon-lock icon-owner-private"></a>' +
        '   </td>' +
        '    <td class="action">' +
        '       <a class="btn pull-left addtmx"><span class="text">Import TMX</span></a>' +
        '          <div class="wrapper-dropdown-5 pull-left" tabindex="1">&nbsp;' +
        '              <ul class="dropdown pull-left">' +
        '                   <li><a class="addGlossary" title="Import Glossary" alt="Import Glossary"><span class="icon-upload"></span>Import Glossary</a></li>' +
        '                   <li><a class="downloadtmx" title="Export TMX" alt="Export TMX"><span class="icon-download"></span>Export TMX</a></li>' +
        '                   <li><a class="downloadGlossary" title="Export Glossary" alt="Export Glossary"><span class="icon-download"></span>Export Glossary</a></li>' +
        '                   <li><a class="shareKey" title="Share resource" alt="Share resource"><span class="icon-share"></span>Share resource</a></li>' +
        '                  <li><a class="deleteTM" title="Delete TMX" alt="Delete TMX"><span class="icon-trash-o"></span>Delete TM</a></li>' +
        '              </ul>' +
        '          </div>' +
        '</td>' +
        '</tr>'

      $('#activetm').find('tr.new').before(newTr)

      UI.setTMsortable()
      setTimeout(function () {
        $('#activetm').trigger('update', [keyParams.desc, keyParams.TMKey])
      }, 500)
    },

    pulseTMadded: function (row) {
      setTimeout(function () {
        $('#activetm tbody').animate({scrollTop: 5000}, 0)
        row.fadeOut()
        row.fadeIn()
      }, 10)
      setTimeout(function () {
        $('#activetm tbody').animate({scrollTop: 5000}, 0)
      }, 1000)
    },
    clearTMUploadPanel: function () {
      $('#shared-tm-key, #new-tm-description').val('')
      $('#new-tm-read, #new-tm-write').prop('checked', true)
    },
    clearAddTMRow: function () {
      $('#new-tm-description').val('')
      $('#activetm .fileupload').val('')
      $('.mgmt-tm tr.new').removeClass('badkey badgrants')
      $('.mgmt-tm tr.new .message').text('')
      $('.mgmt-tm tr.new .error span').text('').hide()
      $('.mgmt-tm tr.new .addtmxfile, .mgmt-tm tr.new .addglossaryfile').show()
    },
    clearTMPanel: function () {
      $('#activetm .edit-desc').removeAttr('contenteditable')
      $('#activetm td.action a').removeClass('disabled')
      $('#activetm tr.new').hide()
      $('tr.tm-key-deleting').removeClass('tm-key-deleting')
      $(
        '#activetm tr.new .addtmxfile, #activetm tr.new .addtmxfile .addglossaryfile',
      ).removeClass('disabled')
      $('.mgmt-table-tm .add-tm').show()
      UI.clearTMUploadPanel()
      UI.clearAddTMRow()
      UI.hideAllBoxOnTables()
    },

    fileUpload: function (form, action_url, div_id, tmName, type) {
      // Create the iframe...
      var ts = new Date().getTime()
      var ifId = 'upload_iframe-' + ts
      var iframe = document.createElement('iframe')
      iframe.setAttribute('id', ifId)
      iframe.setAttribute('name', 'upload_iframe')
      iframe.setAttribute('width', '0')
      iframe.setAttribute('height', '0')
      iframe.setAttribute('border', '0')
      iframe.setAttribute('style', 'width: 0; height: 0; border: none;')
      // Add to document...
      document.body.appendChild(iframe)

      window.frames['upload_iframe'].name = 'upload_iframe'
      const iframeId = document.getElementById(ifId)
      UI.UploadIframeId = iframeId

      // Add event...
      var eventHandler = function () {
        let content

        if (iframeId.detachEvent) iframeId.detachEvent('onload', eventHandler)
        else iframeId.removeEventListener('load', eventHandler, false)

        // Message from server...
        if (iframeId.contentDocument) {
          content = iframeId.contentDocument.body.innerHTML
        } else if (iframeId.contentWindow) {
          content = iframeId.contentWindow.document.body.innerHTML
        } else if (iframeId.document) {
          content = iframeId.document.body.innerHTML
        }

        document.getElementById(div_id).innerHTML = content
      }

      if (iframeId.addEventListener)
        iframeId.addEventListener('load', eventHandler, true)
      if (iframeId.attachEvent) iframeId.attachEvent('onload', eventHandler)
      var TR = $(form).parents('tr')
      var Key = TR.find('.privatekey').first().text().trim()
      var keyName = TR.find('.description').first().text().trim()

      // Set properties of form...
      form.setAttribute('target', 'upload_iframe')
      form.setAttribute('action', action_url)
      form.setAttribute('method', 'post')
      form.setAttribute('enctype', 'multipart/form-data')
      form.setAttribute('encoding', 'multipart/form-data')
      if (type === 'tmx') {
        $(form).append('<input type="hidden" name="exec" value="newTM" />')
      }
      $(form)
        .append('<input type="hidden" name="tm_key" value="' + Key + '" />')
        .append('<input type="hidden" name="name" value="' + keyName + '" />')
        .append('<input type="hidden" name="r" value="1" />')
        .append('<input type="hidden" name="w" value="1" />')
      if (APP.isCattool) {
        $(form)
          .append(
            '<input type="hidden" name="job_id" value="' +
              config.job_id +
              '" />',
          )
          .append(
            '<input type="hidden" name="job_pass" value="' +
              config.password +
              '" />',
          )
      }

      // Submit the form...
      form.submit()

      document.getElementById(div_id).innerHTML = ''
      var filePath = $(form).find('input[type="file"]').val()
      var fileName = filePath.split('\\')[filePath.split('\\').length - 1]

      var TRcaller = $(form).parents('.uploadfile')
      // TRcaller.addClass('startUploading');
      UI.showStartUpload(TRcaller)
      setTimeout(function () {
        UI.pollForUploadCallback(Key, fileName, TRcaller, type)
      }, 1000)

      return false
    },
    pollForUploadCallback: function (Key, fileName, TRcaller, type) {
      if ($('#uploadCallback').text() != '') {
        var msg = $.parseJSON($('#uploadCallback pre').text())
        if (msg.success === true) {
          setTimeout(function () {
            //delay because server can take some time to process large file
            // TRcaller.removeClass('startUploading');
            const uuid =
              type === 'glossary' && msg.data?.uuids?.length > 0
                ? msg.data.uuids[0]
                : undefined
            UI.pollForUploadProgress(Key, fileName, TRcaller, type, uuid)
          }, 2000)
        } else {
          TRcaller.find('.action a').removeClass('disabled')
          UI.showErrorUpload($(TRcaller))
          UI.UploadIframeId.remove()

          if ($(TRcaller).closest('table').attr('id') == 'inactivetm') {
            UI.showErrorOnInactiveTMTable(msg.errors[0].message)
          } else {
            UI.showErrorOnActiveTMTable(msg.errors[0].message)
          }
        }
      } else {
        setTimeout(function () {
          UI.pollForUploadCallback(Key, fileName, TRcaller, type)
        }, 1000)
      }
    },
    showErrorUpload: function ($tr, text) {
      var msg = text ? text : 'Error uploading your files. Please try again.'
      var msg2 = text ? text : 'Error uploading your files. Please try again.'
      $tr.find('.addtmxfile, .addglossaryfile, .uploadprogress').hide()
      $tr.find('.upload-file-msg-error').text(msg2).show()
      $tr.find('.canceladdglossary, .canceladdtmx').show()
      $tr.find('input[type="file"]').attr('disabled', false)
      if ($tr.closest('table').attr('id') == 'inactivetm') {
        UI.showErrorOnInactiveTMTable(msg)
      } else {
        UI.showErrorOnActiveTMTable(msg)
      }
      $tr.addClass('tm-error')
    },
    showStartUpload: function ($tr) {
      $tr
        .find(
          '.addglossaryfile, .canceladdglossary, .addtmxfile, .canceladdtmx',
        )
        .hide()
      $tr.find('input[type="file"]').attr('disabled', true)
      $tr.find('.uploadprogress').show()
    },
    showSuccessUpload: function ($tr) {
      $tr.find('.action a').removeClass('disabled')
      $tr
        .find(
          '.canceladdtmx,.addtmxfile, .addglossaryfile, .cancelladdglossary',
        )
        .hide()

      $tr.find('.progress .inner').css('width', '90%')
      setTimeout(function () {
        $tr.find('.upload-file-msg-success').show()
        $tr.find('.uploadprogress').hide()
      }, 1000)
    },
    pollForUploadProgress: function (Key, fileName, TRcaller, type, uuid) {
      const promise =
        type === 'glossary'
          ? loadGlossaryFile({id: uuid})
          : loadTMX({key: Key, name: fileName})
      promise
        .then((response) => {
          var TDcaller = TRcaller
          $(TDcaller).closest('tr').find('.action a').removeClass('disabled')
          UI.showStartUpload($(TDcaller))

          if (response.data.total == null && response.data.totals === null) {
            setTimeout(function () {
              UI.pollForUploadProgress(Key, fileName, TDcaller, type, uuid)
            }, 1000)
          } else {
            if (
              (type === 'tmx' && response.data.completed) ||
              (type === 'glossary' &&
                response.data.completed === response.data.totals)
            ) {
              var tr = $(TDcaller).parents('tr')
              UI.showSuccessUpload(tr)

              if (!tr.find('td.description .edit-desc').text()) {
                tr.find('td.description .edit-desc').text(fileName)
              }

              setTimeout(function () {
                $(TDcaller).slideToggle(function () {
                  this.remove()
                })
              }, 3000)

              UI.UploadIframeId.remove()

              return false
            }
            const done =
              type === 'tmx' ? response.data.done : response.data.completed
            const total =
              type === 'tmx' ? response.data.total : response.data.totals
            var progress = (parseInt(done) / parseInt(total)) * 100
            $(TDcaller)
              .find('.progress .inner')
              .css('width', progress + '%')
            setTimeout(function () {
              UI.pollForUploadProgress(Key, fileName, TDcaller, type, uuid)
            }, 1000)
          }
        })
        .catch(({errors}) => {
          var TDcaller = TRcaller
          UI.showErrorUpload($(TDcaller), errors[0].message)
          $(TDcaller).closest('tr').find('.action a').removeClass('disabled')
          UI.UploadIframeId.remove()
        })
    },

    allTMUploadsCompleted: function () {
      if ($('#activetm .uploadfile.uploading').length) {
        ModalsActions.showModalComponent(
          AlertModal,
          {
            text: 'There is one or more TM uploads in progress. Try again when all uploads are completed!',
          },
          'Upload in progress',
        )
        return false
      } else if ($('tr td a.downloading').length) {
        ModalsActions.showModalComponent(
          AlertModal,
          {
            text: 'There is one or more TM downloads in progress. Try again when all downloads are completed or open another browser tab.',
          },
          'Upload in progress',
        )
        return false
      } else {
        return true
      }
    },

    extractTMdataFromTable: function () {
      var categories = ['ownergroup', 'mine', 'anonymous']
      var newArray = {}
      $.each(categories, function (index, value) {
        newArray[value] = UI.extractTMDataFromRowCategory(this)
      })
      return JSON.stringify(newArray)
    },
    extractTMDataFromRowCategory: function (cat) {
      var tt = $('#activetm tbody tr.' + cat)
      var dataOb = []
      $(tt).each(function () {
        var r = $(this).find('.lookup input').is(':checked') ? 1 : 0
        var w = $(this).find('.update input').is(':checked') ? 1 : 0
        var isMymemory = $(this).hasClass('mymemory')
        if ((!r && !w) || isMymemory) {
          return true
        }
        var dd = {
          tm: $(this).attr('data-tm'),
          glos: $(this).attr('data-glos'),
          owner: $(this).attr('data-owner'),
          key: $(this).find('.privatekey').text().trim(), // remove spaces and unwanted chars from string
          name: $(this).find('.description').text().trim(),
          r: r,
          w: w,
        }
        dataOb.push(dd)
      })
      return dataOb
    },

    extractTMdataFromRow: function (tr) {
      return {
        tm_key: tr.find('.privatekey').text(),
        key: this.tm_key,
        tmx_name: tr.find('.description').text(),
        name: this.tmx_name,
        r: tr.find('.lookup input').is(':checked') ? 1 : 0,
        w: tr.find('.update input').is(':checked') ? 1 : 0,
      }
    },

    saveTMdata: function (closeAfter) {
      if (closeAfter) {
        UI.closeTMPanel()
        UI.clearTMPanel()
      }
      if (!APP.isCattool) return
      var data = this.extractTMdataFromTable()
      var getPublicMatches = $('#activetm')
        .find('tr.mymemory .lookup input')
        .is(':checked')

      updateJobKeys({
        getPublicMatches,
        dataTm: data,
      })
        .then(() => {
          UI.hideAllBoxOnTables()
          // TODO: update keys for glossary
          CatToolActions.onTMKeysChangeStatus()
        })
        .catch(() => {
          UI.showErrorOnActiveTMTable(
            'There was an error saving your data. Please retry!',
          )
        })
    },
    saveTMdescription: function (field) {
      if (!config.isLoggedIn) return
      var tr = field.parents('tr').first()
      var old_descr = tr.find('.edit-desc').data('descr')
      var new_descr = field.text()

      if (new_descr === '') {
        old_descr.length > 0
          ? (new_descr = old_descr)
          : (new_descr = 'Private resource')
        field.text(new_descr)
      }
      if (old_descr === new_descr) {
        return
      }
      updateTmKey({
        key: tr.find('.privatekey').text(),
        description: new_descr,
      })
        .then(() => {
          tr.find('.edit-desc').data('descr', new_descr)
          UI.hideAllBoxOnTables()
        })
        .catch((errors) => {
          UI.showErrorOnActiveTMTable(errors[0].message)
        })
    },
    saveTMkey: function (desc) {
      if (desc.length == 0) {
        desc = 'Private resource'
      }

      //call API
      return tmCreateRandUser().then((response) => {
        const {key} = response.data
        if (!config.isLoggedIn) {
          UI.checkTMKey('key', key)
          return
        }
        createNewTmKey({
          key: key,
          description: desc,
        })
          .then(() => {
            UI.checkTMKey('key', key)
            UI.hideAllBoxOnTables()
          })
          .catch((errors) => {
            setTimeout(function () {
              if (errors[0].code === '23000') {
                UI.showErrorOnActiveTMTable('The key you entered is invalid.')
              } else {
                UI.showErrorOnActiveTMTable(errors[0].message)
              }
            }, 200)
          })
      })
    },

    closeTMPanel: function () {
      $('.popup-tm').removeClass('open').animate({right: '-1100px'}, 400)
      $('.outer-tm').hide()
      $('body').removeClass('side-popup')
      if (!APP.isCattool && !UI.checkAnalyzability('closing tmx panel')) {
        UI.disableAnalyze()
        if (!checkAnalyzabilityTimer)
          checkAnalyzabilityTimer = window.setInterval(function () {
            if (UI.checkAnalyzability('set interval')) {
              UI.enableAnalyze()
              window.clearInterval(checkAnalyzabilityTimer)
            }
          }, 500)
      }
    },
    filterInactiveTM: function (txt) {
      const inactiveItems = Array.from(
        document.getElementById('inactivetm')?.getElementsByClassName('list')[0]
          ?.children,
      )
      inactiveItems.forEach((item) => {
        const description = item
          .getElementsByClassName('description')[0]
          .getElementsByClassName('edit-desc')[0]
          .getAttribute('data-descr')

        const regex = new RegExp(TEXT_UTILS.escapeRegExp(txt), 'gi')
        if (regex.test(description)) {
          item.classList.add('found')
        } else {
          item.classList.remove('found')
        }
      })
    },
    showMTDeletingMessage: function (button) {
      var tr = button.closest('tr')
      var id = tr.data('id')
      $('.mgmt-table-mt .tm-warning-message')
        .html(
          'Do you really want to delete this MT? ' +
            '<a class="pull-right btn-orange-small cancelDeletingMT cancel-tm-key-delete">      <span class="text"></span>   </a>' +
            '<a class="pull-right btn-confirm-small continueDeletingMT confirm-tm-key-delete">       <span class="text">Confirm</span>   </a>',
        )
        .show()
      $('.continueDeletingMT, .cancelDeletingMT').off('click')
      $('.continueDeletingMT').on('click', function (e) {
        e.preventDefault()
        UI.deleteMT(id)
        $('.mgmt-table-mt .tm-warning-message').empty().hide()
      })
      $('.cancelDeletingMT').on('click', function (e) {
        e.preventDefault()
        UI.hideAllBoxOnTables()
      })
    },
    showDeleteTmMessage: function (button) {
      $('tr.tm-key-deleting').removeClass('tm-key-deleting')
      var message =
        'Do you really want to delete this resource (<b>XXX</b>)? ' +
        '<a class="pull-right btn-orange-medium cancelDelete cancel-tm-key-delete">      <span class="text"></span>   </a>' +
        '<a class="pull-right btn-confirm-small confirmDelete confirm-tm-key-delete" style="display: inline;">       <span class="text">Confirm</span>   </a>'
      var elem = $(button).closest('table')
      var tr = $(button).closest('tr')
      tr.addClass('tm-key-deleting')
      var key = tr.find('.privatekey').text()
      message = message.replace('XXX', key)
      // message = message.replace('YYY', descr);
      if (elem.attr('id') === 'activetm') {
        UI.showWarningOnActiveTMTable(message)
      } else {
        UI.showWarningOnInactiveTMTable(message)
      }
      var removeListeners = function () {
        $('.confirm-tm-key-delete, .cancel-tm-key-delete').off('click')
      }
      setTimeout(function () {
        $('.confirm-tm-key-delete').off('click')
        $('.confirm-tm-key-delete').on('click', function (e) {
          e.preventDefault()
          UI.deleteTM(button)
          UI.hideAllBoxOnTables()
          removeListeners()
        })
        $('.cancel-tm-key-delete').off('click')
        $('.cancel-tm-key-delete').on('click', function (e) {
          e.preventDefault()
          UI.hideAllBoxOnTables()
          $('tr.tm-key-deleting').removeClass('tm-key-deleting')
          removeListeners()
        })
      }, 200)
    },
    deleteTM: function (button) {
      const tr = $(button).parents('tr').first()
      $(tr).fadeOut('normal', function () {
        $(this).remove()
      })

      deleteTmKey({
        key: tr.find('.privatekey').text(),
      })
        .then(() => {
          UI.hideAllBoxOnTables()
          setTimeout(function () {
            $('#activetm').trigger('deleteTm', [tr.find('.privatekey').text()])
          }, 500)
          // TODO: update keys for glossary
          if (APP.isCattool) CatToolActions.onTMKeysChangeStatus()
        })
        .catch(() => {
          UI.showErrorOnActiveTMTable(
            'There was an error saving your data. Please retry!',
          )
        })
    },
    deleteMT: function (id) {
      deleteMTEngine({
        id,
      })
        .then(({data}) => {
          UI.hideAllBoxOnTables()
          $('.mgmt-table-mt tr[data-id=' + data.id + ']').remove()
          $('#mt_engine option[value=' + data.id + ']').remove()
          if (!$('#mt_engine option[selected=selected]').length)
            $('#mt_engine option[value=0]').attr('selected', 'selected')
        })
        .catch(() => {
          $('.mgmt-table-mt .tm-error-message')
            .text('There was an error saving your data. Please retry!')
            .show()
        })
    },

    addMTEngine: function (provider, providerName) {
      var providerData = {}
      $('.insert-tm .provider-data .provider-field').each(function () {
        const field = $(this).find('input, select').first()
        if (field.prop('type') === 'checkbox') {
          providerData[field.attr('data-field-name')] = field.prop('checked')
        } else {
          providerData[field.attr('data-field-name')] = field.val()
        }
      })

      var name = $('#new-engine-name').val()

      const props = {
        name,
        provider,
        data: JSON.stringify(providerData),
        providerName,
      }

      addMTEngineApi({
        name: props.name,
        provider: props.provider,
        dataMt: props.data,
      })
        .then((response) => {
          if (
            response.data.config &&
            Object.keys(response.data.config).length
          ) {
            UI.renderMTConfig(provider, response.name, response.data.config)
          } else {
            UI.renderNewMT(props, response.data)
            if (!APP.isCattool) {
              UI.activateMT(
                $(
                  'table.mgmt-mt tr[data-id=' +
                    response.data.id +
                    '] .enable-mt input',
                ),
              )
              $('#mt_engine').append(
                '<option value="' +
                  response.data.id +
                  '">' +
                  response.data.name +
                  '</option>',
              )
              $('#mt_engine option:selected').removeAttr('selected')
              $('#mt_engine option[value="' + response.data.id + '"]').attr(
                'selected',
                'selected',
              )
            }
            $('#mt_engine_int').val('none').trigger('change')
            UI.decorateMMTRow && UI.decorateMMTRow()
          }
        })
        .catch((errors) => {
          if (errors[0].code !== undefined) {
            $('#mt-provider-details .mt-error-key')
              .text(errors[0].message)
              .show()
          } else {
            $('#mt-provider-details .mt-error-key')
              .text('API key not valid')
              .show()
          }
        })
    },
    renderNewMT: function (data, serverResponse) {
      var newTR =
        '<tr data-id="' +
        serverResponse.id +
        '">' +
        '    <td class="mt-provider">' +
        serverResponse.name +
        '</td>' +
        '    <td class="engine-name">' +
        data.providerName +
        '</td>' +
        '    <td class="enable-mt text-center">' +
        '        <input type="checkbox" checked />' +
        '    </td>' +
        '    <td class="action">' +
        '        <a class="deleteMT btn pull-left"><span class="text">Delete</span></a>' +
        '    </td>' +
        '</tr>'
      if (APP.isCattool) {
        $('table.mgmt-mt tbody tr:not(.activemt)').first().before(newTR)
      } else {
        $('table.mgmt-mt tbody tr.activemt')
          .removeClass('activemt')
          .find('.enable-mt input')
          .click()
        $('table.mgmt-mt.active-mt tbody').prepend(newTR)
      }
    },

    /* codice inserito da Daniele */
    pulseMTadded: function (row) {
      setTimeout(function () {
        $('.activemt').animate({scrollTop: 5000}, 0)
        row.fadeOut()
        row.fadeIn()
      }, 10)
      setTimeout(function () {
        $('.activemt').animate({scrollTop: 5000}, 0)
      }, 1000)
    },
    resetMTProviderPanel: function () {
      if ($('.insert-tm .step2').css('display') == 'block') {
        $('#add-mt-provider-cancel-int').click()
        $('.add-mt-engine').click()
      }
    },
    activateMT: function (el) {
      var tr = $(el).parents('tr')
      $(el).replaceWith('<input type="checkbox" checked class="temp" />')

      const activeMtBody = $('.mgmt-mt.active-mt tbody')
      activeMtBody.append(tr)
      // var tbody = tr.parents('tbody')
      tr.removeClass('inactivemt')
      activeMtBody.find('.activemt input[type=checkbox]').each((i, elem) => {
        UI.deactivateMT(elem)
      })
      // $(tbody).prepend(tr)
      tr.addClass('activemt').removeClass('inactivemt').removeClass('temp')
      $('#mt_engine option').removeAttr('selected')
      $('#mt_engine option[value=' + tr.attr('data-id') + ']').attr(
        'selected',
        'selected',
      )
      UI.pulseMTadded($('.activemt').last())
    },
    deactivateMT: function (el) {
      var tr = $(el).parents('tr')
      $(el).replaceWith('<input type="checkbox" />')
      tr.removeClass('activemt').addClass('inactivemt')
      $('#mt_engine option').removeAttr('selected')
      $('#mt_engine option[value=0]').attr('selected', 'selected')
      const inactiveMtBody = $('.mgmt-mt.inactive-mt tbody')
      inactiveMtBody.prepend(tr)
      UI.pulseMTadded($('.inactivemt').first())
    },
    openTMActionDropdown: function (switcher) {
      $(switcher).parents('td').find('.dropdown').toggle()
    },
    closeTMActionDropdown: function (el) {
      $(el).parents('td').find('.wrapper-dropdown-5').click()
    },

    setDropDown: function () {
      //init dropdown events on every class
      new UI.DropDown($('.wrapper-dropdown-5'))

      //set control events
      $('.action').off('mouseleave')
      $('.action').mouseleave(function () {
        $('.wrapper-dropdown-5').removeClass('activeMenu')
      })

      $(document).click(function () {
        // all dropdowns
        $('.wrapper-dropdown-5').removeClass('activeMenu')
      })
    },

    DropDown: function (el) {
      this.initEvents = function () {
        var obj = this
        var fun = function (event) {
          $(this).toggleClass('activeMenu')
          event.preventDefault()
          if ($(this).hasClass('activeMenu')) {
            event.stopPropagation()
          }
        }
        obj.dd.off('click')
        obj.dd.on('click', fun)
      }
      this.dd = el
      this.initEvents()
    },

    renderMTConfig: function (provider, newEngineName, configData) {
      if (provider == 'none') {
        $('.step2 .fields').html('')
        $('.step2').hide()
        $('.step3').hide()
        $('#add-mt-provider-cancel').show()
      } else {
        $('.step2 .fields').html(
          $('#mt-provider-' + provider + '-config-fields').html(),
        )
        $('.step3 .text-left').html(
          $('#mt-provider-' + provider + '-config-msg').html(),
        )
        $('.step2').show()
        $('.step3').show()
        $('#add-mt-provider-confirm').removeClass('hide')
      }

      $('#new-engine-name').val(newEngineName)

      // Populate the template fields with given values and store extra data within their data attributes
      var selectorBase = '.insert-tm .provider-data .provider-field'
      for (var fieldName in configData) {
        var field = $(selectorBase + " [data-field-name='" + fieldName + "']")
        var tagName = field.prop('tagName')
        if (tagName == 'INPUT') {
          var fieldContents = configData[fieldName]['value']
          field.val(fieldContents)

          var fieldData = configData[fieldName]['data']
          for (var dataKeyField in fieldData) {
            field.attr('data-' + dataKeyField, fieldData[dataKeyField])
          }
        } else if (tagName == 'SELECT') {
          for (var optionKey in configData[fieldName]) {
            var optionName = configData[fieldName][optionKey]['value']
            var option = $(
              "<option value='" + optionKey + "'>" + optionName + '</option>',
            )

            var optionData = configData[fieldName][optionKey]['data']
            for (var dataKey in optionData) {
              option.attr('data-' + dataKey, optionData[dataKey])
            }

            field.append(option)
          }
        }
      }

      // notify the template's javascript that the template has been populated
      if (typeof renderMTConfigCallback == 'function') {
        renderMTConfigCallback()
      }
    },

    openAddNewTm: function () {
      UI.removeErrorOnKeyInput()
      $('#shared-tm-key').addClass('hide')
      $('.mgmt-table-tm tr.new').removeClass('hide').show()
      $('#new-tm-description').focus()
    },
    openAddNewTmShared: function () {
      UI.removeErrorOnKeyInput()
      $('#shared-tm-key').removeClass('hide')
      $('.mgmt-table-tm tr.new').removeClass('hide').show()
      $('#new-tm-description').focus()
    },

    openExport: function (elem, type) {
      $(elem)
        .parents('.action')
        .find('a')
        .each(function () {
          $(this).addClass('disabled')
        })
      const text =
        type === 'glossary'
          ? 'We will send a link to download the exported Glossary to this email:'
          : 'We will send a link to download the exported TM to this email:'
      const className = type === 'glossary' ? 'export-glossary' : 'export-tmx'
      const exportDiv =
        '<td class="download-container ' +
        className +
        '" style="display: none">' +
        '<div class="message-export">' +
        text +
        '</div>' +
        '<div class="message-export-success"></div>' +
        '<input type="email" required class="email-export mgmt-input" value="' +
        config.userMail +
        '"/>' +
        '<span class="uploadloader"></span>' +
        '<span class="email-export-email-sent">Request submitted</span>' +
        '<a class="pull-right btn-grey canceladd-export">' +
        '<span class="text"></span>' +
        '</a>' +
        '<a class="pull-right btn-ok export-button export-tmx-button">' +
        '   <span class="text export-button-label">Confirm</span>' +
        '</a>' +
        '<span class="email-export-email-error">We got an error,</br> please contact support</span>' +
        '</td>'

      $(elem).parents('tr').append(exportDiv)
      $(elem).parents('tr').find('.download-container').slideToggle()
    },
    getUserSharedKey: function (keyValue) {
      const promise = getInfoTmKey({
        key: keyValue,
      })

      promise.then((data) => {
        if (data.success !== true) {
          UI.showErrorOnActiveTMTable(
            'Error retrieving the information, try again',
          )
        }
      })

      return promise
    },
    openShareResource: function (elem) {
      var tr = $(elem).parents('tr')
      if (tr.find('.share-tmx-container').length) {
        tr.find('.share-tmx-container').slideToggle(function () {
          $(this).remove()
        })
        tr.find('.action')
          .find('a')
          .each(function () {
            $(this).removeClass('disabled')
          })
        return
      }

      var key = tr.find('.privatekey').text()
      if (key.indexOf('*') > -1) return
      this.getUserSharedKey(key).then((response) => {
        if (response.success !== true) return

        var users = response.data
        //Remove the user from the list
        var indexOfUser = users
          .map(function (item) {
            return item.email
          })
          .indexOf(config.userMail)
        var user = users.splice(indexOfUser, 1)
        user = user[0]

        tr.find('.action')
          .find('a')
          .each(function () {
            $(this).addClass('disabled')
          })
        //Create the container
        var exportDiv = ''
        if (users.length === 0) {
          $('tr.mine[data-key=' + key + '] .icon-owner')
            .removeClass('icon-users icon-owner-shared')
            .addClass('icon-lock icon-owner-private')
          exportDiv =
            '<td class="share-tmx-container" style="display: none">' +
            '<div class="message-share-tmx">Share ownership of the resource by sharing the key. This action cannot be undone.</div>' +
            '<input class="message-share-tmx-input-email" placeholder="Enter email addresses separated by comma"/>' +
            '<a class="pull-right btn-grey cancelsharetmx"><span class="text"></span>   </a>' +
            '<div class="pull-right btn-ok share-button">Share</div>' +
            '</td>'
        } else if (users.length > 0) {
          $('tr.mine[data-key=' + key + '] .icon-owner')
            .removeClass('icon-lock icon-owner-private')
            .addClass('icon-users icon-owner-shared')
          var totalShareUsers =
            users.length === 1
              ? ''
              : 'and <span class="message-share-tmx-openemailpopup">' +
                (users.length - 1) +
                ' others</span>'
          exportDiv =
            '<td class="share-tmx-container" style="display: none">' +
            '<div class="message-share-tmx">Shared resource ' +
            'is co-owned by you, <span class="message-share-tmx-email message-share-tmx-openemailpopup">' +
            users[0].first_name +
            ' ' +
            users[0].last_name +
            '</span>  ' +
            totalShareUsers +
            '</div>' +
            '<input class="message-share-tmx-input-email" placeholder="Enter email addresses separated by comma" type="email" multiple/>' +
            '<a class="pull-right btn-orange-small cancelsharetmx"><span class="text"></span>   </a>' +
            '<div class="pull-right btn-ok share-button">Share</div>' +
            '</td>'
        } else {
          return false
        }

        tr.append(exportDiv)
        tr.find('.share-tmx-container').slideToggle()

        //If still not shared dont create the popup
        if (users.length === 0) return

        var description = tr.find('.edit-desc').data('descr')

        tr.find('.message-share-tmx-openemailpopup').on('click', function () {
          ModalsActions.showModalComponent(
            ShareTmModal,
            {
              description,
              tmKey: key,
              user,
              users,
              callback: () => UI.shareTmCallbackFromPopup(key),
            },
            'Share resource',
          )
        })
      })
    },
    startExport: function (elem, type) {
      var line = $(elem).closest('tr')
      var email = line.find('.email-export').val()
      var successText = 'You should receive the link at ' + email

      line.find('.uploadloader').show()
      line.find('.export-button, .canceladd-export').addClass('disabled')
      const tm_key = $('.privatekey', line).text().trim()
      const tm_name = $('.description', line).text().trim()
      const params = {
        key: tm_key,
        name: tm_name,
        email,
      }
      const promise =
        type === 'glossary' ? downloadGlossary(params) : downloadTMXApi(params)
      promise
        .then((response) => {
          var time = Math.round(response.data.estimatedTime / 60)
          time = time > 0 ? time : 1
          successText = successText.replace('%XX%', time)
          setTimeout(function () {
            line.find('.message-export-success').html(successText)
            line.find('.uploadloader').hide()
            line.find('.export-button, .canceladd-export, .email-export').hide()
            line.find('.message-export').hide()
            line
              .find('.message-export-success, .email-export-email-sent')
              .show()
            setTimeout(function () {
              UI.closeExport(line)
            }, 5000)
          }, 3000)
        })
        .catch(() => {
          setTimeout(function () {
            line.find('.uploadloader').hide()
            line.find('.export-button').hide()
            line.find('.action a').removeClass('disabled')
            line.find('.canceladd-export').removeClass('disabled')
            line.find('.email-export-email-error').show()
            UI.showErrorMessage(line, 'We got an error, please contact support')
            line.find('.download-container').addClass('tm-error')
          }, 2000)
        })
    },
    closeExport: function (elem) {
      $(elem)
        .find('td.download-container')
        .slideToggle(function () {
          $(this).remove()
        })
      $(elem).find('.action a').removeClass('disabled')
    },
    addFormUpload: function (elem, type) {
      var label, format
      if (type == 'tmx') {
        label =
          '<p class="pull-left">Select up to 10 TMX files to be imported</p>'
        format = '.tmx'
        if ($(elem).parents('tr').find('.uploadfile').length > 0) {
          // $(elem).parents('tr').find('.uploadfile').slideToggle();
          $(elem).closest('tr').find('.action a').addClass('disabled')
          return
        }
      } else if (type == 'glossary') {
        label =
          '<p class="pull-left">Select up to 10 glossaries in XLSX, XLS or ODS format ' +
          '   <a href="https://guides.matecat.com/how-to-add-a-glossary" target="_blank">(How-to)</a>' +
          '</p>'
        format = '.xlsx,.xls, .ods'
      }
      $(elem).closest('tr').find('.action a').addClass('disabled')
      var nr =
        '<td class="uploadfile" style="display: none">' +
        label +
        '<form class="existing add-TM-Form pull-left" action="/" method="post">' +
        '    <input type="submit" class="addtm-add-submit" style="display: none" />' +
        '    <input type="file" name="uploaded_file[]" accept="' +
        format +
        '" multiple/>' +
        '</form>' +
        '   <a class="pull-right btn-grey canceladd' +
        type +
        '">' +
        '      <span class="text"></span>' +
        '   </a>' +
        '   <a class="existingKey pull-right btn-ok add' +
        type +
        'file">' +
        '       <span class="text">Confirm</span>' +
        '   </a>' +
        '   <span class="upload-file-msg-error"></span>' +
        '   <span class="upload-file-msg-success">Import Complete</span>' +
        '  <div class="uploadprogress">' +
        '       <span class="msgText">Uploading</span>' +
        '       <span class="progress">' +
        '           <span class="inner"></span>' +
        '       </span>' +
        '  </div>' +
        '</td>'

      $(elem).parents('tr').append(nr)
      $(elem).parents('tr').find('.uploadfile').slideToggle()
    },
    showErrorOnActiveTMTable: function (message) {
      setTimeout(function () {
        $('.mgmt-container .active-tm-container .tm-error-message')
          .html(message)
          .fadeIn(100)
      })
    },
    showErrorOnInactiveTMTable: function (message) {
      setTimeout(function () {
        $('.mgmt-container .inactive-tm-container .tm-error-message')
          .html(message)
          .fadeIn(100)
      })
    },
    showWarningOnActiveTMTable: function (message) {
      setTimeout(function () {
        $('.mgmt-container .active-tm-container .tm-warning-message')
          .html(message)
          .fadeIn(100)
      })
    },
    showWarningOnInactiveTMTable: function (message) {
      setTimeout(function () {
        $('.mgmt-container .inactive-tm-container .tm-warning-message')
          .html(message)
          .fadeIn(100)
      })
    },
    showSuccessOnActiveTMTable: function (message) {
      setTimeout(function () {
        $('.mgmt-container .active-tm-container .tm-success-message')
          .html(message)
          .fadeIn(100)
      })
    },
    showSuccessOnInactiveTMTable: function (message) {
      setTimeout(function () {
        $('.mgmt-container .inactive-tm-container .tm-success-message')
          .html(message)
          .fadeIn(100)
      })
    },

    hideAllBoxOnTables: function () {
      $(
        '.mgmt-container .active-tm-container .tm-error-message, .mgmt-container .active-tm-container .tm-warning-message, .mgmt-container .active-tm-container .tm-success-message,' +
          '.mgmt-container .inactive-tm-container .tm-error-message, .mgmt-container .inactive-tm-container .tm-warning-message, .mgmt-container .inactive-tm-container .tm-success-message,' +
          '.mgmt-table-mt .tm-error-message, .mgmt-table-mt .tm-warning-message, .mgmt-table-mt .tm-success-message',
      ).fadeOut(0, function () {
        $(this).html('')
      })
      $('.tm-error').removeClass('tm-error')
    },
    showErrorMessage: function (tr, message) {
      if (tr.closest('table').attr('id') == 'inactivetm') {
        UI.showErrorOnInactiveTMTable(message)
      } else {
        UI.showErrorOnActiveTMTable(message)
      }
    },
    initOptionsTip: function () {
      var acceptedLanguagesLXQ = config.lexiqa_languages.slice()
      var lexiqaText =
        "<div class='powerTip-options-tm'><div class='powerTip-options-tm-title'>Any combination of the supported languages:</div>" +
        '<ul>'
      acceptedLanguagesLXQ.forEach(function (elem) {
        var lang = config.languages_array.find(function (e) {
          return e.code === elem
        })
        if (lang && lang.name) {
          lexiqaText =
            lexiqaText +
            "<li class='powerTip-options-tm-list'>" +
            lang.name +
            '</li>'
        }
      })
      lexiqaText = lexiqaText + '</ul></div>'

      $('.tooltip-lexiqa').data('powertip', lexiqaText)
      $('.tooltip-lexiqa').powerTip({
        placement: 's',
        mouseOnToPopup: true,
      })
    },

    initTmxTooltips: function () {
      //Description input
      if (config.isLoggedIn) {
        /*$('tr:not(.ownergroup) .edit-desc').data(
          'powertip',
          "<div style='line-height: 18px;font-size: 15px;'>Rename</div>",
        )*/
        $('.edit-desc').powerTip({
          placement: 's',
        })

        $('.icon-owner-private').data(
          'powertip',
          "<div style='line-height: 18px;font-size: 15px;'>Private resource.<br/>Share it from the dropdown menu.</div>",
        )
        $('.icon-owner-private').powerTip({
          placement: 's',
        })
      } else {
        $('.icon-owner-private').data(
          'powertip',
          "<div style='line-height: 18px;font-size: 15px;'>To retrieve resource information or share it <br/>you must be logged.<br/></div>",
        )
        $('.icon-owner-private').powerTip({
          placement: 's',
        })
      }

      $('.icon-owner-public').data(
        'powertip',
        "<div style='line-height: 20px;font-size: 15px;'>Public translation memory.</div>",
      )
      $('.icon-owner-public').powerTip({
        placement: 's',
      })

      $('.icon-owner-shared').data(
        'powertip',
        "<div style='line-height: 20px;font-size: 15px;'>Shared resource.<br/>Select Share resource from the dropdown menu to see owners.</div>",
      )
      $('.icon-owner-shared').powerTip({
        placement: 's',
      })
      var mymemoryChecks = $('#activetm tr.mymemory .update div')
      mymemoryChecks.data(
        'powertip',
        "<div style='line-height: 20px;font-size: 15px;'>Add a private resource to disable updating.</div>",
      )
      mymemoryChecks.powerTip({
        placement: 's',
      })
    },

    setLanguageTooltipLXQ: function () {
      var lxTooltip = $('.tooltip-lexiqa').data('powertip')

      $('.qa-box .onoffswitch-container').data('powertip', lxTooltip)
      $('.qa-box .onoffswitch-container').powerTip({
        placement: 's',
        mouseOnToPopup: true,
      })
    },

    removeTooltipTP: function () {
      $('.qa-box .onoffswitch-container').powerTip('destroy')
      $('.tagp .onoffswitch-container').powerTip('destroy')
    },

    removeTooltipLXQ: function () {
      $('.qa-box .onoffswitch-container').powerTip('destroy')
      $('.tagp .onoffswitch-container').powerTip('destroy')
    },

    checkTMKeysUpdateChecks: function () {
      var updateCheck = $('#activetm').find(
        'tr:not(.mymemory, .new)  .update input:checked',
      ).length
      if (updateCheck === 0) {
        $('#activetm').find('tr.mymemory .update input').prop('checked', true)
      } else {
        $('#activetm').find('tr.mymemory .update input').prop('checked', false)
      }
    },
    clickOnShareButton(button) {
      var tr = button.closest('tr')
      var key = tr.data('key')
      var msg =
        'The resource <span style="font-weight: bold">' +
        key +
        '</span> has been shared.'

      var emails = button
        .closest('.share-tmx-container')
        .find('.message-share-tmx-input-email')
        .val()
      var validateReturn = CommonUtils.validateEmailList(emails)

      if (validateReturn.result !== true) {
        var errorMsg =
          'The email <span style="font-weight: bold">' +
          validateReturn.emails +
          '</span> is not valid.'

        if (tr.closest('table').attr('id') == 'inactivetm') {
          UI.showErrorOnInactiveTMTable(errorMsg)
        } else {
          UI.showErrorOnActiveTMTable(errorMsg)
        }
        tr.find('.message-share-tmx-input-email').addClass('error')

        return
      }

      UI.shareKeyByEmail(emails, key)
        .then(() => {
          UI.hideAllBoxOnTables()
          button.closest('.share-tmx-container').find('.cancelsharetmx').click()
          if (tr.closest('table').attr('id') == 'inactivetm') {
            UI.showSuccessOnInactiveTMTable(msg)
          } else {
            UI.showSuccessOnActiveTMTable(msg)
          }
          setTimeout(function () {
            UI.hideAllBoxOnTables()
          }, 4000)
        })
        .catch((errors) => {
          var errorMsg = errors[0].message
          if (tr.closest('table').attr('id') == 'inactivetm') {
            UI.showErrorOnInactiveTMTable(errorMsg)
          } else {
            UI.showErrorOnActiveTMTable(errorMsg)
          }
          tr.find('.message-share-tmx-input-email').addClass('error')
        })
    },
    shareTmCallbackFromPopup: function (key) {
      var msg =
        'The resource <span style="font-weight: bold">' +
        key +
        '</span> has been shared.'
      UI.showSuccessOnActiveTMTable(msg)
      $('tr .action a').removeClass('disabled')
      $('.share-tmx-container').slideToggle(function () {
        $(this).remove()
      })
      UI.hideAllBoxOnTables()
    },
    /**
     * Share a key to one or more email, separated by comma
     * @param container
     */
    shareKeyByEmail: function (emails, key) {
      const promise = shareTmKey({
        key: key,
        emails: emails,
      })

      promise.catch(() => {
        UI.showErrorOnActiveTMTable(
          'There was a problem sharing the key, try again or contact the support.',
        )
      })

      return promise
    },

    storeMultiMatchLangs: function () {
      var primary = $('#multi-match-1').val()
        ? $('#multi-match-1').val()
        : undefined
      var secondary = $('#multi-match-2').val()
        ? $('#multi-match-2').val()
        : undefined
      if (primary) {
        $('#multi-match-2').removeAttr('disabled')
      } else {
        $('#multi-match-2').attr('disabled', true)
        $('#multi-match-2').val('')
        secondary = undefined
      }
      UI.crossLanguageSettings = {primary: primary, secondary: secondary}
      localStorage.setItem(
        'multiMatchLangs',
        JSON.stringify(UI.crossLanguageSettings),
      )
      if (SegmentActions.getContribution) {
        if (primary) {
          SegmentActions.modifyTabVisibility('multiMatches', true)
          $('section.loaded').removeClass('loaded')
          SegmentActions.getContribution(UI.currentSegmentId, true)
        } else {
          SegmentActions.modifyTabVisibility('multiMatches', false)
          SegmentActions.activateTab(UI.currentSegmentId, 'matches')
          SegmentActions.updateAllSegments()
        }
      }
    },

    checkCrossLanguageSettings: function () {
      var settings = localStorage.getItem('multiMatchLangs')
      if (settings) {
        var selectPrimary = $('#multi-match-1')
        var selectSecondary = $('#multi-match-2')
        settings = JSON.parse(settings)
        UI.crossLanguageSettings = settings
        selectPrimary.val(settings.primary)
        selectSecondary.val(settings.secondary)
        if (settings.primary) selectSecondary.removeAttr('disabled')
      }
    },
  })
})(jQuery)
