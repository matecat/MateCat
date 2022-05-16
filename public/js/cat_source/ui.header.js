import _ from 'lodash'
import ReactDOM from 'react-dom'
import React from 'react'

import {getJobFileInfo} from './es6/api/getJobFileInfo'
import CatToolActions from './es6/actions/CatToolActions'
import CommonUtils from './es6/utils/commonUtils'
import ShortCutsModal from './es6/components/modals/ShortCutsModal'
import SearchUtils from './es6/components/header/cattol/search/searchUtils'
import Shortcuts from './es6/utils/shortcuts'
import SegmentActions from './es6/actions/SegmentActions'
import SegmentStore from './es6/stores/SegmentStore'
import SegmentFilter from './es6/components/header/cattol/segment_filter/segment_filter'
import {logoutUser} from './es6/api/logoutUser'
import {reloadQualityReport} from './es6/api/reloadQualityReport'
import {ModalWindow} from './es6/components/modals/ModalWindow'
import {Header} from './es6/components/header/cattol/Header'

$.extend(window.UI, {
  initHeader: function () {
    ReactDOM.render(
      React.createElement(Header, {
        pid: config.id_project,
        jid: config.job_id,
        password: config.password,
        pname: 'test', //TODO
        source_code: config.source_rfc,
        target_code: config.target_rfc,
        isReview: config.isReview,
        revisionNumber: config.revisionNumber,
        stats: UI.projectStats, //TODO
        user: {}, //TODO,
        projectName: config.project_name,
        projectCompletionEnabled: config.project_completion_feature_enabled,
        secondRevisionsCount: config.secondRevisionsCount,
        overallQualityClass: config.overall_quality_class,
        qualityReportHref: config.quality_report_href,
      }),
      $('header')[0],
    )
    // if (SearchUtils.searchEnabled)
    //   $('#action-search').show(100, function () {
    //     APP.fitText($('#pname-container'), $('#pname'), 25)
    //   })
    //
    // if ($('#action-three-dots').length) {
    //   $('#action-three-dots').dropdown()
    // }
    // if ($('#user-menu-dropdown').length) {
    //   $('#user-menu-dropdown').dropdown()
    // }
    //
    // if (config.isLoggedIn) {
    //   setTimeout(function () {
    //     CatToolActions.showHeaderTooltip()
    //   }, 3000)
    // }
    //this.renderQualityReportButton()
    //this.createJobMenu()
  },
  logoutAction: function () {
    logoutUser().then(() => {
      if ($('body').hasClass('manage')) {
        location.href = config.hostpath + config.basepath
      } else {
        window.location.reload()
      }
    })
  },

  // createJobMenu: function () {
  //   getJobMetadata(config.id_job, config.password).then(function (jobMetadata) {
  //     var fileInstructions = response.files.find(
  //       (file) =>
  //         file.metadata &&
  //         file.metadata.instructions &&
  //         file.metadata.instructions !== '',
  //     )
  //     var projectInfo =
  //       jobMetadata.project && jobMetadata.project.project_info
  //         ? jobMetadata.project.project_info
  //         : undefined
  //     if (fileInstructions || projectInfo) {
  //       ReactDOM.render(
  //         React.createElement(JobMetadata, {
  //           files: response.files,
  //           projectInfo: projectInfo,
  //         }),
  //         document.getElementById('files-instructions'),
  //       )
  //     }
  //   })
  // },
  renderQualityReportButton: function () {
    // CatToolActions.renderQualityReportButton()
    if (config.isReview) {
      UI.reloadQualityReport()
    }
  },
  reloadQualityReport: function () {
    reloadQualityReport().then((data) => {
      CatToolActions.updateQualityReport(data['quality-report'])
    })
  },
  // renderAndScrollToSegment: function (sid) {
  //   var segment = SegmentStore.getSegmentByIdToJS(sid)
  //   if (segment) {
  //     SegmentActions.openSegment(sid)
  //   } else {
  //     UI.unmountSegments()
  //     this.render({
  //       caller: 'link2file',
  //       segmentToOpen: sid,
  //       scrollToFile: true,
  //     })
  //   }
  // },
})

var initEvents = function () {
  $('#action-search').bind('click', function (e) {
    SearchUtils.toggleSearch(e)
  })
  $('#action-settings').bind('click', function (e) {
    e.preventDefault()
    UI.openOptionsPanel()
  })
  $('.user-menu-container').on('click', '#logout-item', function (e) {
    e.preventDefault()
    UI.logoutAction()
  })
  $('.user-menu-container').on('click', '#manage-item', function (e) {
    e.preventDefault()
    document.location.href = '/manage'
  })
  $('#profile-item').on('click', function (e) {
    e.preventDefault()
    e.stopPropagation()
    $('#modal').trigger('openpreferences')
    return false
  })

  $('#action-three-dots .shortcuts').on('click', function (e) {
    e.preventDefault()
    e.stopPropagation()
    ModalWindow.showModalComponent(ShortCutsModal, {}, 'Shortcuts')
    return false
  })

  $('.action-menu').on('click', '#action-filter', function (e) {
    e.preventDefault()
    if (!SegmentFilter.open) {
      SegmentFilter.openFilter()
    } else {
      SegmentFilter.closeFilter()
      SegmentFilter.open = false
    }
  })
}
