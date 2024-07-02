import {createRoot} from 'react-dom/client'
import React from 'react'
import CatToolActions from './es6/actions/CatToolActions'
import CatTool from './es6/pages/CatTool'
import CommonUtils from './es6/utils/commonUtils'
import CommentsActions from './es6/actions/CommentsActions'
import SegmentActions from './es6/actions/SegmentActions'

$.extend(window.UI, {
  start: function () {
    // TODO: the following variables used to be set in UI.init() which is called
    // during rendering. Those have been moved here because of the init change
    // of SegmentFilter, see below.
    UI.firstLoad = true
    UI.body = $('body')
    CommonUtils.setBrowserHistoryBehavior()

    // page content mount point
    const targetPageContent = document.getElementsByClassName('page-content')[0]
    if (targetPageContent) {
      const mountPoint = createRoot(targetPageContent)
      mountPoint.render(<CatTool />)
    }
  },
  init: function () {
    this.initStart = new Date()
    // this.version = 'x.x.x'
    this.numContributionMatchesResults = 3
    this.numMatchesResults = 10
    this.editarea = ''
    this.byButton = false
    this.displayedMessages = []

    $('html').trigger('init')
    this.warningStopped = false
    this.unsavedSegmentsToRecover = []
    this.recoverUnsavedSegmentsTimer = false
    this.setTranslationTail = []
    this.executingSetTranslation = []

    //this.checkVersion()

    // SET EVENTS
    this.checkQueryParams()

    UI.firstLoad = false
  },
  checkQueryParams: function () {
    var action = CommonUtils.getParameterByName('action')
    var interval
    if (action) {
      switch (action) {
        case 'openComments':
          interval = setTimeout(function () {
            CommentsActions.openCommentsMenu()
          }, 500)
          CommonUtils.removeParam('action')
          break
        case 'warnings':
          interval = setTimeout(function () {
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
