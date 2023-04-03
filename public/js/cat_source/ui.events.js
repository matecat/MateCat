import CatToolActions from './es6/actions/CatToolActions'
import SegmentActions from './es6/actions/SegmentActions'
import SearchUtils from './es6/components/header/cattol/search/searchUtils'
import ShortCutsModal from './es6/components/modals/ShortCutsModal'
import SegmentStore from './es6/stores/SegmentStore'
import CommonUtils from './es6/utils/commonUtils'
import Shortcuts from './es6/utils/shortcuts'
import ModalsActions from './es6/actions/ModalsActions'

$.extend(window.UI, {
  bindShortcuts: function () {
    $('body')
      .on(
        'keydown.shortcuts',
        null,
        Shortcuts.cattol.events.openShortcutsModal.keystrokes[
          Shortcuts.shortCutsKeyType
        ],
        function () {
          ModalsActions.showModalComponent(ShortCutsModal, {}, 'Shortcuts')
        },
      )
      .on(
        'keydown.shortcuts',
        null,
        Shortcuts.cattol.events.copySource.keystrokes[
          Shortcuts.shortCutsKeyType
        ],
        function (e) {
          e.preventDefault()
          SegmentActions.copySourceToTarget()
        },
      )
      .on(
        'keydown.shortcuts',
        null,
        Shortcuts.cattol.events.openSettings.keystrokes[
          Shortcuts.shortCutsKeyType
        ],
        function () {
          UI.openLanguageResourcesPanel()
        },
      )
      .on(
        'keydown.shortcuts',
        null,
        Shortcuts.cattol.events.openSearch.keystrokes[
          Shortcuts.shortCutsKeyType
        ],
        function (e) {
          if (SearchUtils.searchEnabled && $('#action-search').length)
            SearchUtils.toggleSearch(e)
        },
      )
      .on(
        'keydown.shortcuts',
        null,
        Shortcuts.cattol.events.redoInSegment.keystrokes[
          Shortcuts.shortCutsKeyType
        ],
        function (e) {
          e.preventDefault()
          // UI.redoInSegment(UI.currentSegment);
          SegmentActions.redoInSegment()
        },
      )
      .on(
        'keydown.shortcuts',
        null,
        Shortcuts.cattol.events.undoInSegment.keystrokes[
          Shortcuts.shortCutsKeyType
        ],
        function (e) {
          e.preventDefault()
          SegmentActions.undoInSegment()
        },
      )
      .on(
        'keydown.shortcuts',
        null,
        Shortcuts.cattol.events.gotoCurrent.keystrokes[
          Shortcuts.shortCutsKeyType
        ],
        function (e) {
          e.preventDefault()
          SegmentActions.scrollToCurrentSegment()
          SegmentActions.setFocusOnEditArea()
        },
      )
      .on(
        'keydown.shortcuts',
        null,
        Shortcuts.cattol.events.openPrevious.keystrokes[
          Shortcuts.shortCutsKeyType
        ],
        function (e) {
          e.preventDefault()
          e.stopPropagation()
          SegmentActions.selectPrevSegmentDebounced()
          // UI.gotoPreviousSegment();
        },
      )
      .on(
        'keydown.shortcuts',
        null,
        Shortcuts.cattol.events.openNext.keystrokes[Shortcuts.shortCutsKeyType],
        function (e) {
          e.preventDefault()
          e.stopPropagation()
          SegmentActions.selectNextSegmentDebounced()
          // UI.gotoNextSegment();
        },
      )
      //For shortcut arrows + ctrl in windows to move between segments
      .on('keyup.shortcuts', null, 'ctrl', function () {
        SegmentActions.openSelectedSegment()
      })
      .on(
        'keydown.shortcuts',
        null,
        Shortcuts.cattol.events.translate_nextUntranslated.keystrokes[
          Shortcuts.shortCutsKeyType
        ],
        function (e) {
          e.preventDefault()
          e.stopPropagation()
          var segment = SegmentStore.getCurrentSegment()
          if (!segment || UI.isReadonlySegment(segment)) {
            return
          }
          if (config.isReview) {
            if ($('.editor .next-unapproved:not(.disabled)').length > 0) {
              setTimeout(function () {
                UI.clickOnApprovedButton(segment, true)
              })
            } else {
              setTimeout(function () {
                UI.clickOnApprovedButton(segment, false)
              })
            }
          } else {
            var nextUntranslatedSegmentId =
              SegmentStore.getNextUntranslatedSegmentId()
            if (!segment.tagged) {
              setTimeout(function () {
                UI.startSegmentTagProjection(segment.sid)
              })
            } else if (
              nextUntranslatedSegmentId &&
              segment.translation.trim() !== ''
            ) {
              setTimeout(function () {
                UI.clickOnTranslatedButton(segment, true)
              })
            } else if (segment.translation.trim() !== '') {
              setTimeout(function () {
                UI.clickOnTranslatedButton(segment, false)
              })
            }
          }
        },
      )
      .on(
        'keydown.shortcuts',
        null,
        Shortcuts.cattol.events.translate.keystrokes[
          Shortcuts.shortCutsKeyType
        ],
        function () {
          UI.translateAndGoToNext()
        },
      )
      .on(
        'keydown.shortcuts',
        null,
        Shortcuts.cattol.events.openIssuesPanel.keystrokes[
          Shortcuts.shortCutsKeyType
        ],
        function (e) {
          e.preventDefault()
          var segment = SegmentStore.getCurrentSegment()
          if (segment && Review.enabled()) {
            SegmentActions.openIssuesPanel({sid: segment.sid})
            SegmentActions.scrollToSegment(segment.sid)
          }
        },
      )
      .on(
        'keydown.shortcuts',
        null,
        Shortcuts.cattol.events.copyContribution1.keystrokes[
          Shortcuts.shortCutsKeyType
        ],
        function (e) {
          e.preventDefault()
          SegmentActions.chooseContributionOnCurrentSegment(1)
        },
      )
      .on(
        'keydown.shortcuts',
        null,
        Shortcuts.cattol.events.copyContribution2.keystrokes[
          Shortcuts.shortCutsKeyType
        ],
        function (e) {
          e.preventDefault()
          SegmentActions.chooseContributionOnCurrentSegment(2)
        },
      )
      .on(
        'keydown.shortcuts',
        null,
        Shortcuts.cattol.events.copyContribution3.keystrokes[
          Shortcuts.shortCutsKeyType
        ],
        function (e) {
          e.preventDefault()
          SegmentActions.chooseContributionOnCurrentSegment(3)
        },
      )
      .on(
        'keydown.shortcuts',
        null,
        Shortcuts.cattol.events.splitSegment.keystrokes[
          Shortcuts.shortCutsKeyType
        ],
        function (e) {
          e.preventDefault()
          e.stopPropagation()
          SegmentActions.openSplitSegment(UI.currentSegmentId)
        },
      )
      .on(
        'keydown.shortcuts',
        null,
        Shortcuts.cattol.events.openComments.keystrokes[
          Shortcuts.shortCutsKeyType
        ],
        function (e) {
          e.preventDefault()
          e.stopPropagation()
          var current = SegmentStore.getCurrentSegmentId()
          if (current) SegmentActions.openSegmentComment(current)
        },
      )
      .on('keydown.shortcuts', null, 'ctrl+u', function (e) {
        // to prevent the underline shortcut
        e.preventDefault()
      })
      .on('keydown.shortcuts', null, 'ctrl+b', function (e) {
        // to prevent the underline shortcut
        e.preventDefault()
      })
  },

  setEvents: function () {
    this.bindShortcuts()

    window.onbeforeunload = function (e) {
      return CommonUtils.goodbye(e)
    }
    //Header/Footer events

    $('div.notification-box').mouseup(function () {
      return false
    })

    $('.search-icon, .search-on').click(function (e) {
      e.preventDefault()
      $('#search').toggle()
    })

    $('form#fileDownload').bind('submit', function (e) {
      e.preventDefault()
    })

    $('html')
      .on('keyup', function (e) {
        if (e.key === 'Meta' && navigator.platform === 'MacIntel') {
          SegmentActions.openSelectedSegment()
        }
      })
      .on('click', '#previewDropdown .downloadTranslation a', function (e) {
        e.preventDefault()
        UI.runDownload()
      })
      .on('click', '#action-download', function (e) {
        if (
          $(e.target).attr('id') === '#action-download' ||
          $(e.target).hasClass('dropdown-menu-overlay')
        ) {
          e.preventDefault()
          UI.runDownload()
        }
      })
      .on('click', '#previewDropdown .previewLink a', function (e) {
        e.preventDefault()
        UI.runDownload()
      })
      .on('click', '#previewDropdown a.tmx', function (e) {
        e.preventDefault()
        window.open($(this).attr('href'))
      })
      .on('click', '#downloadProject', function (e) {
        e.preventDefault()
        UI.runDownload()
      })
      .on('mousedown', '.originalDownload, .sdlxliff', function (e) {
        if (e.which == 1) {
          // left click
          e.preventDefault()
          e.stopPropagation()
          var iFrameDownload = $(document.createElement('iframe'))
            .hide()
            .prop({
              id:
                'iframeDownload_' +
                new Date().getTime() +
                '_' +
                parseInt(Math.random(0, 1) * 10000000),
              src: $(e.currentTarget).attr('data-href'),
            })
          $('body').append(iFrameDownload)

          //console.log( $( e.currentTarget ).attr( 'href' ) );
        }
      })
      .on('click', '#previewDropdown .originalsGDrive', function () {
        UI.continueDownloadWithGoogleDrive(1)
      })
      .on('click', '.alert .close', function (e) {
        e.preventDefault()
        $('.alert').remove()
      })
      .on('click', '#statistics .meter a', function (e) {
        e.preventDefault()
        if (config.isReview) {
          UI.openNextTranslated()
        } else {
          SegmentActions.gotoNextUntranslatedSegment()
        }
      })

    $('#navSwitcher').on('click', function (e) {
      e.preventDefault()
    })
    $('#jobNav .currseg').on('click', function (e) {
      e.preventDefault()
      var current = SegmentStore.getCurrentSegment()
      if (!current) {
        SegmentActions.removeAllSegments()
        CatToolActions.onRender({
          firstLoad: false,
        })
      } else {
        SegmentActions.scrollToSegment(current.original_sid)
      }
    })

    //###################################################

    $('#outer').on('keydown', function (e) {
      if (
        e.which === 27 &&
        $('.modal[data-name=confirmAutopropagation]').length
      ) {
        $('.modal[data-name=confirmAutopropagation] .btn-ok').click()
        e.preventDefault()
        e.stopPropagation()
      }
    })
  },
})
