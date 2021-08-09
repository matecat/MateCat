import {getMatecatApiDomain} from '../utils/getMatecatApiDomain'

if (!window.API) {
  window.API = {}
}

window.API.PROJECTS = {
  changeProjectName: function (idOrg, idProject, newName) {
    var data = {
      name: newName,
    }
    return $.ajax({
      data: JSON.stringify(data),
      type: 'PUT',
      xhrFields: {withCredentials: true},
      url:
        getMatecatApiDomain() +
        'api/v2/teams/' +
        idOrg +
        '/projects/' +
        idProject,
    })
  },
  changeProjectAssignee: function (idOrg, idProject, newUserId) {
    //Pass null to unassign a Project
    var idAssignee = newUserId == '-1' ? null : newUserId
    var data = {
      id_assignee: idAssignee,
    }
    return $.ajax({
      data: JSON.stringify(data),
      type: 'put',
      xhrFields: {withCredentials: true},
      url:
        getMatecatApiDomain() +
        'api/v2/teams/' +
        idOrg +
        '/projects/' +
        idProject,
    })
  },

  changeProjectTeam: function (newTeamId, project) {
    var data = {
      id_team: newTeamId,
    }
    return $.ajax({
      data: JSON.stringify(data),
      type: 'PUT',
      xhrFields: {withCredentials: true},
      url:
        getMatecatApiDomain() +
        'api/v2/teams/' +
        project.id_team +
        '/projects/' +
        project.id,
    })
  },
  getVolumeAnalysis: function () {
    var pid = config.id_project
    var ppassword = config.password
    var data = {
      pid: pid,
      ppassword: ppassword,
    }
    return $.ajax({
      data: data,
      type: 'POST',
      xhrFields: {withCredentials: true},
      url: getMatecatApiDomain() + '?action=getVolumeAnalysis',
    })
  },
  getJobVolumeAnalysis: function () {
    var pid = config.id_project
    var jpassword = config.jpassword
    var data = {
      pid: pid,
      jpassword: jpassword,
    }
    return $.ajax({
      data: data,
      type: 'POST',
      xhrFields: {withCredentials: true},
      url: getMatecatApiDomain() + '?action=getVolumeAnalysis',
    })
  },
  getCompletionStatus: function () {
    var pid = config.id_project
    var jpassword = config.password
    var data = {
      pid: pid,
      jpassword: jpassword,
    }
    return $.ajax({
      data: data,
      type: 'GET',
      xhrFields: {withCredentials: true},
      url:
        getMatecatApiDomain() +
        'api/v2/projects/' +
        pid +
        '/' +
        jpassword +
        '/completion_status',
    })
  },

  getSecondPassReview: function (
    idProject,
    passwordProject,
    idJob,
    passwordJob,
  ) {
    var data = {
      id_job: idJob,
      password: passwordJob,
      revision_number: 2,
    }
    return $.ajax({
      data: data,
      type: 'POST',
      url:
        '/plugins/second_pass_review/project/' +
        idProject +
        '/' +
        passwordProject +
        '/reviews',
    })
  },
}
