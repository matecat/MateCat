import {each, forEach} from 'lodash'

import SegmentActions from './es6/actions/SegmentActions'
import CatToolActions from './es6/actions/CatToolActions'
import {getSegmentsIssues} from './es6/api/getSegmentsIssues'
import {sendSegmentVersionIssue} from './es6/api/sendSegmentVersionIssue'
import {sendSegmentVersionIssueComment} from './es6/api/sendSegmentVersionIssueComment'
import SegmentStore from './es6/stores/SegmentStore'
import {getSegmentVersionsIssues} from './es6/api/getSegmentVersionsIssues'
import CommonUtils from './es6/utils/commonUtils'
import {
  JOB_WORD_CONT_TYPE,
  REVISE_STEP_NUMBER,
  SEGMENTS_STATUS,
} from './es6/constants/Constants'

window.Review = {
  enabled: function () {
    return config.enableReview && !!config.isReview
  },
  type: config.reviewType,
}
window.ReviewExtended = {
  enabled: function () {
    return Review.enabled()
  },
  alertNotTranslatedMessage:
    'This segment is not translated yet.<br /> Only translated segments can be revised.',
  type: config.reviewType,
  issueRequiredOnSegmentChange: true,
  localStoragePanelClosed:
    'issuePanelClosed-' + config.id_job + config.password,
  number: config.revisionNumber,
  getSegmentsIssues: function () {
    getSegmentsIssues().then((data) => {
      let versionsIssues = {}
      each(data.issues, (issue) => {
        if (!versionsIssues[issue.id_segment]) {
          versionsIssues[issue.id_segment] = []
        }
        versionsIssues[issue.id_segment].push(issue)
      })
      SegmentActions.addPreloadedIssuesToSegment(versionsIssues)
    })
  },
  submitIssue: function (sid, data) {
    const promise = sendSegmentVersionIssue(sid, {
      ...data,
    })
    promise
      .then(() => {
        ReviewExtended.getSegmentVersionsIssues(sid)
        CatToolActions.reloadQualityReport()
      })
      .catch(() => {
        //todo Capture Error
        console.log('Error creating issue')
      })

    return promise
  },

  submitIssueComment: function (id_segment, id_issue, data) {
    const promise = sendSegmentVersionIssueComment(id_segment, id_issue, data)
    promise.then(() => {
      this.getSegmentVersionsIssues(id_segment)
    })
    return promise
  },
  openIssuesPanel: function (data, openSegment) {
    const segment = SegmentStore.getSegmentByIdToJS(data.sid)
    const canOpenSegment =
      segment.status !== 'NEW' && segment.status !== 'DRAFT'
    if (segment && !canOpenSegment) {
      return false
    }
    $('body').addClass('review-extended-opened')
    localStorage.setItem(ReviewExtended.localStoragePanelClosed, false)

    $('body').addClass('side-tools-opened review-side-panel-opened')
    window.dispatchEvent(new Event('resize'))
    if (data && openSegment) {
      SegmentActions.openSegment(data.sid)
      SegmentActions.scrollToSegment(data.sid)
      window.setTimeout(
        function (data) {
          SegmentActions.scrollToSegment(data.sid)
        },
        500,
        data,
      )
    }
    return true
  },
  getSegmentVersionsIssues: function (segmentId) {
    const segment = SegmentStore.getSegmentByIdToJS(segmentId)
    if (segment) {
      getSegmentVersionsIssues(segmentId)
        .then((response) => {
          SegmentActions.addTranslationIssuesToSegment(
            segmentId,
            response.versions,
          )
        })
        .catch(() => {})
    }
  },
}

if (Review.enabled()) {
  $(document).on('files:appended', function () {
    ReviewExtended.getSegmentsIssues()
  })

  $(window).on('segmentOpened', function (e, data) {
    const panelClosed =
      localStorage.getItem(ReviewExtended.localStoragePanelClosed) === 'true'
    if (config.isReview && !panelClosed) {
      setTimeout(() =>
        SegmentActions.openIssuesPanel({sid: data.segmentId}, false),
      )
    }
    ReviewExtended.getSegmentVersionsIssues(data.segmentId)
  })

  $(document).on('translation:change', function (e, data) {
    ReviewExtended.getSegmentVersionsIssues(data.sid)
    CatToolActions.reloadQualityReport()
  })

  $(document).on('header-tool:open', function (e, data) {
    if (data.name === 'search') {
      SegmentActions.closeIssuesPanel()
    }
  })
}

if (Review.enabled()) {
  $.extend(UI, {
    getSegmentRevisionIssues(segment, revisionNumber) {
      let issues = []
      if (segment.versions && segment.versions.length > 0) {
        forEach(segment.versions, (version) => {
          if (version.issues && version.issues.length > 0) {
            forEach(version.issues, (issue) => {
              if (issue.revision_number === revisionNumber) {
                issues.push(issue)
              }
            })
          }
        })
      }
      return issues
    },

    clickOnApprovedButton: function (segment, goToNextUnapproved) {
      // the event click: 'A.APPROVED' i need to specify the tag a and not only the class
      // because of the event is triggered even on download button
      var sid = segment.sid

      let issues = this.getSegmentRevisionIssues(segment, config.revisionNumber)
      /* If segment is modified and there aren't issues and is not an ICE force to add an Issue.
         If is an ICE we allow to change the translation because is not possible to add an issue
       */

      if (
        config.isReview &&
        !segment.splitted &&
        segment.modified &&
        issues.length === 0 &&
        segment.ice_locked !== '1'
      ) {
        SegmentActions.openIssuesPanel({sid: segment.sid}, true)
        setTimeout(() => SegmentActions.showIssuesMessage(segment.sid, 1))
        return
      }

      SegmentActions.removeClassToSegment(sid, 'modified')

      var afterApproveFn = function () {
        if (goToNextUnapproved) {
          if (segment.revision_number > 1) {
            UI.openNextApproved()
          } else {
            SegmentActions.gotoNextTranslatedSegment()
          }
        } else {
          SegmentActions.gotoNextSegment(sid)
        }
      }

      UI.setTimeToEdit(sid)
      const status =
        segment.revision_number === REVISE_STEP_NUMBER.REVISE1
          ? SEGMENTS_STATUS.APPROVED
          : config.word_count_type === JOB_WORD_CONT_TYPE.EQUIVALENT
          ? SEGMENTS_STATUS.APPROVED
          : SEGMENTS_STATUS.APPROVED2
      UI.changeStatus(segment, status, afterApproveFn) // this does < setTranslation

      // Lock the segment if it's approved in a second pass but was previously approved in first revision
      if (ReviewExtended.number > 1) {
        CommonUtils.removeFromStorage('unlocked-' + sid)
      }
    },
    openNextApproved: function (sid) {
      sid = sid || UI.currentSegmentId
      var nextApprovedSegment = SegmentStore.getNextSegment(
        sid,
        null,
        9,
        1,
        true,
      )
      var nextApprovedSegmentInPrevious = SegmentStore.getNextSegment(
        -1,
        null,
        9,
        1,
        true,
      )
      // find in next segments
      if (nextApprovedSegment) {
        SegmentActions.openSegment(nextApprovedSegment.sid)
      } else {
        // find in not loaded segments or go to the next approved
        SegmentActions.openSegment(
          UI.nextUntranslatedSegmentIdByServer
            ? UI.nextUntranslatedSegmentIdByServer
            : nextApprovedSegmentInPrevious.sid,
        )
      }
    },
  })
}
