import {createRoot} from 'react-dom/client'
import React from 'react'
import CatToolActions from './es6/actions/CatToolActions'
import CatTool from './es6/pages/CatTool'
import CommonUtils from './es6/utils/commonUtils'
import Customizations from './es6/utils/customizations'
import LXQ from './es6/utils/lxq.main'
import SegmentUtils from './es6/utils/segmentUtils'

$.extend(window.UI, {
  render: function (options) {
    options = options || {}
    var seg = options.segmentToOpen || false

    this.isSafari =
      navigator.userAgent.search('Safari') >= 0 &&
      navigator.userAgent.search('Chrome') < 0
    this.isChrome = typeof window.chrome != 'undefined'
    this.isFirefox = typeof navigator.mozApps != 'undefined'

    this.isMac = navigator.platform === 'MacIntel'
    this.body = $('body')
    // this.firstLoad = (options.firstLoad || false);
    this.initSegNum = 100 // number of segments initially loaded
    this.moreSegNum = 25
    this.numOpenedSegments = 0
    this.nextUntranslatedSegmentIdByServer = null
    this.checkUpdatesEvery = 180000
    this.tagModesEnabled =
      typeof options.tagModesEnabled != 'undefined'
        ? options.tagModesEnabled
        : true
    if (this.tagModesEnabled && !SegmentUtils.checkTPEnabled()) {
      UI.body.addClass('tagModes')
    } else {
      UI.body.removeClass('tagModes')
    }

    /**
     * Global Translation mismatches array definition.
     */
    this.translationMismatches = []

    this.readonly = this.body.hasClass('archived') ? true : false

    this.setTagLockCustomizeCookie(true)
    this.debug = false

    options.openCurrentSegmentAfter = !!(!seg && !this.firstLoad)
    if (options.segmentToOpen) {
      this.startSegmentId = options.segmentToOpen
    } else {
      var hash = CommonUtils.parsedHash.segmentId
      config.last_opened_segment = CommonUtils.getLastSegmentFromLocalStorage()
      config.last_opened_segment = config.last_opened_segment
        ? config.last_opened_segment
        : config.first_job_segment
      this.startSegmentId =
        hash && hash != '' ? hash : config.last_opened_segment
    }
    CatToolActions.onRender({
      ...options,
      ...(this.startSegmentId && {startSegmentId: this.startSegmentId}),
      where: 'center',
    })

    // return UI.getSegments(options)
  },
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
    const CallbackAfterRender = () => {
      React.useEffect(() => {
        onPageMounted()
      }, [])
      return React.createElement(CatTool)
    }
    const mountPoint = createRoot($('.page-content')[0])
    mountPoint.render(<CallbackAfterRender />)

    const onPageMounted = () => {
      UI.render()
      $('html').trigger('start')

      if (LXQ.enabled()) {
        LXQ.initPopup()
      }
      CatToolActions.startNotifications()
      UI.splittedTranslationPlaceholder = '##$_SPLIT$##'
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
