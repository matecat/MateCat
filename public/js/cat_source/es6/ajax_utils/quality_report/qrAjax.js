import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

let QUALITY_REPORT = {
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
