if (!window.API) {
  window.API = {}
}

API.USER = {
  getApiKey: () => {
    return $.ajax({
      type: 'get',
      xhrFields: {withCredentials: true},
      url: APP.getRandomUrl() + 'api/app/api-key/show',
    })
  },
  createApiKey: () => {
    return $.ajax({
      type: 'post',
      xhrFields: {withCredentials: true},
      url: APP.getRandomUrl() + 'api/app/api-key/create',
    })
  },
  deleteApiKey: () => {
    return $.ajax({
      type: 'delete',
      xhrFields: {withCredentials: true},
      url: APP.getRandomUrl() + 'api/app/api-key/delete',
    })
  },
}
