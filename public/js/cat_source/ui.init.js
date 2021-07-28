import CatToolActions from './es6/actions/CatToolActions'
import SearchUtils from './es6/components/header/cattol/search/searchUtils'
import CommonUtils from './es6/utils/commonUtils'
import Customizations from './es6/utils/customizations'
import SegmentUtils from './es6/utils/segmentUtils'

$.extend(window.UI, {
  render: function (options) {
    options = options || {}
    var seg = options.segmentToOpen || false
    this.segmentToScrollAtRender = seg ? seg : false

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
    this.maxMinutesBeforeRerendering = 60
    this.loadingMore = false
    this.noMoreSegmentsAfter = false
    this.noMoreSegmentsBefore = false
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
    /**
     * Global Warnings array definition.
     */
    this.globalWarnings = []

    this.readonly = this.body.hasClass('archived') ? true : false

    this.setTagLockCustomizeCookie(true)
    this.debug = false

    options.openCurrentSegmentAfter = !!(!seg && !this.firstLoad)
    if (this.segmentToScrollAtRender) {
      this.startSegmentId = this.segmentToScrollAtRender
    } else {
      var hash = CommonUtils.parsedHash.segmentId
      config.last_opened_segment = CommonUtils.getLastSegmentFromLocalStorage()
      config.last_opened_segment = config.last_opened_segment
        ? config.last_opened_segment
        : config.first_job_segment
      this.startSegmentId =
        hash && hash != '' ? hash : config.last_opened_segment
    }

    return UI.getSegments(options)
  },
  start: function () {
    // TODO: the following variables used to be set in UI.init() which is called
    // during rendering. Those have been moved here because of the init change
    // of SegmentFilter, see below.
    UI.firstLoad = true
    UI.body = $('body')
    UI.checkCrossLanguageSettings()
    // If some icon is added on the top header menu, the file name is resized
    APP.addDomObserver($('.header-menu')[0], function () {
      APP.fitText($('#pname-container'), $('#pname'), 25)
    })
    CommonUtils.setBrowserHistoryBehavior()
    $('article').each(function () {
      APP.fitText($('.filename h2', $(this)), $('.filename h2', $(this)), 30)
    })

    var initialRenderPromise = UI.render()

    initialRenderPromise.done(function () {
      if (
        SegmentFilter.enabled() &&
        SegmentFilter.getStoredState().reactState
      ) {
        SegmentFilter.openFilter()
      }
      setTimeout(function () {
        UI.checkWarnings(true)
      }, 1000)
    })

    $('html').trigger('start')

    if (LXQ.enabled()) {
      LXQ.initPopup()
    }
    CatToolActions.startNotifications()
    UI.splittedTranslationPlaceholder = '##$_SPLIT$##'
    // Temporary js for header action menu
    UI.initHeader()
    CatToolActions.renderSubHeader()
    CatToolActions.renderFooter()
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
    if (SearchUtils.searchEnabled)
      $('#filterSwitch').show(100, function () {
        APP.fitText($('.breadcrumbs'), $('#pname'), 30)
      })
    this.warningStopped = false
    this.unsavedSegmentsToRecover = []
    this.recoverUnsavedSegmentsTimer = false
    this.setTranslationTail = []
    this.executingSetTranslation = []

    if (!config.isLoggedIn) this.body.addClass('isAnonymous')

    this.checkVersion()
    this.initTM()
    this.initAdvanceOptions()

    // SET EVENTS
    this.setEvents()
    APP.checkQueryParams()

    UI.firstLoad = false
  },
  restart: function () {
    UI.unmountSegments()
    this.start()
  },

  detectStartSegment: function () {
    if (this.segmentToScrollAtRender) {
      this.startSegmentId = this.segmentToScrollAtRender
    } else {
      var hash = CommonUtils.parsedHash.segmentId
      config.last_opened_segment = CommonUtils.getLastSegmentFromLocalStorage()
      if (!config.last_opened_segment) {
        config.last_opened_segment = config.first_job_segment
      }
      this.startSegmentId =
        hash && hash != '' ? hash : config.last_opened_segment
    }
  },
})
