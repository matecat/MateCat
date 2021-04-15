import AppDispatcher from '../stores/AppDispatcher'
import OutsourceConstants from '../constants/OutsourceConstants'

let OutsourceActions = {
  outsourceCloseTranslatorInfo: function () {
    AppDispatcher.dispatch({
      actionType: OutsourceConstants.CLOSE_TRANSLATOR,
    })
  },

  getOutsourceQuote: function () {
    AppDispatcher.dispatch({
      actionType: OutsourceConstants.GET_OUTSOURCE_QUOTE,
    })
  },

  sendJobToTranslator: function (email, date, timezone, job, project) {
    UI.sendJobToTranslator(email, date, timezone, job, project)
  },
}

module.exports = OutsourceActions
