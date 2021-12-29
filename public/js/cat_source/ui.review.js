import ConfirmMessageModal from './es6/components/modals/ConfirmMessageModal'
import OfflineUtils from './es6/utils/offlineUtils'
import SegmentActions from './es6/actions/SegmentActions'
import {ModalWindow} from './es6/components/modals/ModalWindow'

window.Review = {
  enabled: function () {
    return config.enableReview && !!config.isReview
  },
  type: config.reviewType,
}
$.extend(window.UI, {
  evalOpenableSegment: function (segment) {
    if (!(segment.status === 'NEW' || segment.status === 'DRAFT')) return true

    if (UI.projectStats && UI.projectStats.TRANSLATED_PERC === 0) {
      alertNoTranslatedSegments()
    } else {
      alertNotTranslatedYet(segment.sid)
    }
    return false
  },
})

window.alertNotTranslatedYet = function (sid) {
  APP.confirm({
    name: 'confirmNotYetTranslated',
    cancelTxt: 'Close',
    callback: 'openNextTranslated',
    okTxt: 'Open next translated segment',
    context: sid,
    msg: UI.alertNotTranslatedMessage,
  })
}

window.alertNoTranslatedSegments = function () {
  var props = {
    text: 'There are no translated segments to revise in this job.',
    successText: 'Ok',
    successCallback: function () {
      ModalWindow.onCloseModal()
    },
  }
  ModalWindow.showModalComponent(ConfirmMessageModal, props, 'Warning')
}

if (config.enableReview && config.isReview) {
  ;(function ($) {
    $.extend(UI, {
      alertNotTranslatedMessage:
        'This segment is not translated yet.<br /> Only translated segments can be revised.',
      /**
       * Each revision overwrite this function
       */
      clickOnApprovedButton: function (segment, goToNextNotApproved) {
        var sid = segment.sid
        SegmentActions.removeClassToSegment(sid, 'modified')
        var afterApproveFn = function () {
          if (goToNextNotApproved) {
            UI.openNextTranslated()
          } else {
            UI.gotoNextSegment(sid)
          }
        }

        UI.changeStatus(segment, 'approved', afterApproveFn) // this does < setTranslation
      },
    })
  })(jQuery)
}
