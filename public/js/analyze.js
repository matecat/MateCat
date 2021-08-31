import ReactDOM from 'react-dom'
import React from 'react'

import TeamsActions from './cat_source/es6/actions/TeamsActions'
import TeamsStore from './cat_source/es6/stores/TeamsStore'
import Header from './cat_source/es6/components/header/Header'
import AnalyzeMain from './cat_source/es6/components/analyze/AnalyzeMain'
import AnalyzeActions from './cat_source/es6/actions/AnalyzeActions'
import {getProject} from './cat_source/es6/api/getProject'
import {getVolumeAnalysis} from './cat_source/es6/api/getVolumeAnalysis'
import {getJobVolumeAnalysis} from './cat_source/es6/api/getJobVolumeAnalysis'

window.UI = null

window.UI = {
  init: function () {
    this.pollingTime = 1000
    this.segmentsThreshold = 50000
    this.reloadAnalysis = this.reloadAnalysis.bind(this)
    UI.render()
  },
  render: function () {
    var headerMountPoint = $('header')[0]
    ReactDOM.render(
      React.createElement(Header, {
        loggedUser: config.isLoggedIn,
        showSubHeader: false,
        showModals: false,
        changeTeam: false,
        user: TeamsStore.getUser(),
      }),
      headerMountPoint,
    )

    var analyzeMountPoint = $('#analyze-container')[0]
    ReactDOM.render(
      React.createElement(AnalyzeMain, {
        jobsInfo: config.jobs,
      }),
      analyzeMountPoint,
    )

    this.getProjectVolumeAnalysisData()
  },
  reloadAnalysis: function () {
    window.location.reload()
    // UI.getProjectVolumeAnalysisData()
  },
  getProjectVolumeAnalysisData: function () {
    var self = this
    if (config.jobAnalysis) {
      getJobVolumeAnalysis().then((response) => {
        self.parseVolumeAnalysisData(response)
        getProject(config.id_project).then((response) => {
          UI.currentOutsourceProject = response.project
          self.renderAnalysisPage()
        })
        self.pollData(response)
      })
    } else {
      getVolumeAnalysis().then((response) => {
        self.parseVolumeAnalysisData(response)
        getProject(config.id_project).then((response) => {
          UI.currentOutsourceProject = response.project
          self.renderAnalysisPage()
        })
        self.pollData(response)
      })
    }
  },
  renderAnalysisPage: function () {
    AnalyzeActions.renderAnalysis(UI.volumeAnalysis, UI.currentOutsourceProject)
  },
  pollData: function (response) {
    if (
      response.data.summary.STATUS !== 'DONE' &&
      response.data.summary.STATUS !== 'NOT_TO_ANALYZE'
    ) {
      if (response.data.summary.TOTAL_SEGMENTS > UI.segmentsThreshold) {
        UI.pollingTime = parseInt(response.data.summary.TOTAL_SEGMENTS / 20)
      }

      setTimeout(function () {
        getVolumeAnalysis().then((response) => {
          UI.volumeAnalysis = response.data
          AnalyzeActions.updateVolumeAnalysis(UI.volumeAnalysis)
          if (
            response.data.summary.STATUS === 'DONE' ||
            response.data.summary.STATUS === 'NOT_TO_ANALYZE'
          ) {
            getProject(config.id_project).then((response) => {
              if (response.project) {
                UI.currentOutsourceProject = response.project
                AnalyzeActions.updateProject(UI.currentOutsourceProject)
              }
            })
          } else {
            UI.pollData(response)
          }
        })
      }, UI.pollingTime)
    }
  },
  parseVolumeAnalysisData: function (volumeAnalysisData) {
    UI.volumeAnalysis = volumeAnalysisData.data
  },
  downloadAnalysisReport: function () {
    var pid = config.id_project
    var ppassword = config.password

    var form =
      '			<form id="downloadAnalysisReportForm" action="/" method="post">' +
      '				<input type=hidden name="action" value="downloadAnalysisReport">' +
      '				<input type=hidden name="id_project" value="' +
      pid +
      '">' +
      '				<input type=hidden name="password" value="' +
      ppassword +
      '">' +
      '				<input type=hidden name="download_type" value="XTRF">' +
      '			</form>'
    $('body').append(form)
    $('#downloadAnalysisReportForm').submit()
  },
}

$(document).ready(function () {
  UI.init()
})
