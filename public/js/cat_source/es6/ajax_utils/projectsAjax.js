import {getMatecatApiDomain} from '../utils/getMatecatApiDomain'

if (!window.API) {
  window.API = {}
}

window.API.PROJECTS = {
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
