import ReactDOM from 'react-dom'
import React from 'react'

import {Header} from './es6/components/header/cattol/Header'

$.extend(window.UI, {
  initHeader: function () {
    ReactDOM.render(
      React.createElement(Header, {
        pid: config.id_project,
        jid: config.job_id,
        password: config.password,
        reviewPassword: config.review_password,
        pname: 'test', //TODO
        source_code: config.source_rfc,
        target_code: config.target_rfc,
        isReview: config.isReview,
        revisionNumber: config.revisionNumber,
        stats: UI.projectStats, //TODO
        user: {}, //TODO,
        userLogged: config.isLoggedIn,
        projectName: config.project_name,
        projectCompletionEnabled: config.project_completion_feature_enabled,
        secondRevisionsCount: config.secondRevisionsCount,
        overallQualityClass: config.overall_quality_class,
        qualityReportHref: config.quality_report_href,
      }),
      $('header')[0],
    )
  },
})
