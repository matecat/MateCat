import {getMatecatApiDomain} from '../utils/getMatecatApiDomain'

if (!window.API) {
  window.API = {}
}

API.USER = {
  getApiKey: () => {
    return $.ajax({
      type: 'get',
      xhrFields: {withCredentials: true},
      url: getMatecatApiDomain() + 'api/app/api-key/show',
    })
  },
  createApiKey: () => {
    return $.ajax({
      type: 'post',
      xhrFields: {withCredentials: true},
      url: getMatecatApiDomain() + 'api/app/api-key/create',
    })
  },
  deleteApiKey: () => {
    return $.ajax({
      type: 'delete',
      xhrFields: {withCredentials: true},
      url: getMatecatApiDomain() + 'api/app/api-key/delete',
    })
  },
}
