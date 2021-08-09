import {getMatecatApiDomain} from '../utils/getMatecatApiDomain'

if (!window.API) {
  window.API = {}
}

API.OUTSOURCE = {
  fetchChangeRates() {
    return $.ajax({
      type: 'POST',
      xhrFields: {withCredentials: true},
      url: getMatecatApiDomain() + '?action=fetchChangeRates',
    })
  },

  getCountTranslators(source, target) {
    var data = {
      action: 'getCountTranslators',
      source: source,
      target: target,
    }
    return $.ajax({
      data: data,
      type: 'POST',
      url: 'https://www.translated.net/en/entrypoint.php',
    })
  },
}
