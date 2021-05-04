window.UI = null

window.UI = {
  init: function () {
    this.pollingTime = 1000
    this.segmentsThreshold = 50000
    this.reloadAnalysis = this.reloadAnalysis.bind(this)
    UI.render()
  },
  render: function () {
    var self = this
    var headerMountPoint = $('header')[0]
    ReactDOM.render(
      React.createElement(Header, {
        loggedUser: config.isLoggedIn,
        showSubHeader: false,
        showModals: false,
        changeTeam: false,
        user: APP.USER.STORE,
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

    API.TEAM.getAllTeams().done(function (data) {
      self.teams = data.teams
      TeamsActions.renderTeams(self.teams)
      self.selectedTeam = APP.getLastTeamSelected(self.teams)
      TeamsActions.selectTeam(self.selectedTeam)
    })

    this.getProjectVolumeAnalysisData()
  },
  reloadAnalysis: function () {
    window.location.reload()
    // UI.getProjectVolumeAnalysisData()
  },
  getProjectVolumeAnalysisData: function () {
    var self = this
    if (config.jobAnalysis) {
      API.PROJECTS.getJobVolumeAnalysis().done(function (response) {
        self.parseVolumeAnalysisData(response)
        API.PROJECTS.getProject(config.id_project).done(function (response) {
          UI.currentOutsourceProject = response.project
          self.renderAnalysisPage()
        })
        self.pollData(response)
      })
    } else {
      API.PROJECTS.getVolumeAnalysis().done(function (response) {
        self.parseVolumeAnalysisData(response)
        API.PROJECTS.getProject(config.id_project).done(function (response) {
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
        API.PROJECTS.getVolumeAnalysis().done(function (response) {
          UI.volumeAnalysis = response.data
          AnalyzeActions.updateVolumeAnalysis(UI.volumeAnalysis)
          if (
            response.data.summary.STATUS === 'DONE' ||
            response.data.summary.STATUS === 'NOT_TO_ANALYZE'
          ) {
            API.PROJECTS.getProject(config.id_project).done(function (
              response,
            ) {
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
