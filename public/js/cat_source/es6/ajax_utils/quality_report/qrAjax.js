import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

let QUALITY_REPORT = {
  getSegmentsFiles(filter, segmentId) {
    let data = {
      ref_segment: segmentId,
    }
    if (filter) {
      data.filter = filter
    }
    data.revision_number = config.revisionNumber
    return $.ajax({
      data: data,
      type: 'GET',
      xhrFields: {withCredentials: true},
      url:
        getMatecatApiDomain() +
        'api/app/jobs/' +
        config.id_job +
        '/' +
        config.password +
        '/quality-report/segments',
    })
  },

  getUserData() {
    return $.ajax({
      type: 'GET',
      xhrFields: {withCredentials: true},
      url: getMatecatApiDomain() + 'api/app/user',
    })
  },

  getQRinfo() {
    return $.ajax({
      type: 'GET',
      xhrFields: {withCredentials: true},
      url:
        getMatecatApiDomain() +
        'api/v3/jobs/' +
        config.id_job +
        '/' +
        config.password,
    })
  },
}

export default QUALITY_REPORT
