import {createRoot} from 'react-dom/client'
import React from 'react'
import CatToolActions from './es6/actions/CatToolActions'
import CatTool from './es6/pages/CatTool'
import CommonUtils from './es6/utils/commonUtils'
import Customizations from './es6/utils/customizations'

$.extend(window.UI, {
  start: function () {
    // TODO: the following variables used to be set in UI.init() which is called
    // during rendering. Those have been moved here because of the init change
    // of SegmentFilter, see below.
    UI.firstLoad = true
    UI.body = $('body')
    UI.checkCrossLanguageSettings()
    CommonUtils.setBrowserHistoryBehavior()
    $('article').each(function () {
      APP.fitText($('.filename h2', $(this)), $('.filename h2', $(this)), 30)
    })

    // page content mount point
    const targetPageContent = document.getElementsByClassName('page-content')[0]
    if (targetPageContent) {
      const mountPoint = createRoot(targetPageContent)
      mountPoint.render(<CatTool />)
    }
  },
  init: function () {
    this.isMac = navigator.platform == 'MacIntel' ? true : false
    this.shortcutLeader = this.isMac ? 'CMD' : 'CTRL'

    this.initStart = new Date()
    this.version = 'x.x.x'
    this.numContributionMatchesResults = 3
    this.numMatchesResults = 10
    this.editarea = ''
    this.byButton = false
    this.displayedMessages = []

    Customizations.loadCustomization()
    $('html').trigger('init')
    this.warningStopped = false
    this.unsavedSegmentsToRecover = []
    this.recoverUnsavedSegmentsTimer = false
    this.setTranslationTail = []
    this.executingSetTranslation = []

    this.checkVersion()
    this.initTM()
    this.initAdvanceOptions()

    // SET EVENTS
    this.setEvents()
    this.checkQueryParams()

    UI.firstLoad = false
  },
  checkQueryParams: function () {
    var action = CommonUtils.getParameterByName('action')
    var interval
    if (action) {
      switch (action) {
        case 'download':
          interval = setTimeout(function () {
            $('#downloadProject').trigger('click')
            clearInterval(interval)
          }, 300)
          CommonUtils.removeParam('action')
          break
        case 'openComments':
          if (MBC.enabled()) {
            interval = setInterval(function () {
              if ($('.mbc-history-balloon-outer')) {
                $('.mbc-history-balloon-outer').addClass('mbc-visible')
                $('#mbc-history').addClass('open')
                clearInterval(interval)
              }
            }, 500)
          }
          CommonUtils.removeParam('action')
          break
        case 'warnings':
          interval = setInterval(function () {
            if ($('#notifbox.warningbox')) {
              CatToolActions.toggleQaIssues()
              clearInterval(interval)
            }
          }, 500)
          CommonUtils.removeParam('action')
          break
      }
    }
  },
})
