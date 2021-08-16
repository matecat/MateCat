import SegmentActions from './es6/actions/SegmentActions'
import SegmentFilter from './es6/components/header/cattol/segment_filter/segment_filter'
import SegmentStore from './es6/stores/SegmentStore'

if (SegmentFilter.enabled())
  (function ($, UI, SF) {
    var original_renderFiles = UI.renderFiles

    var original_gotoNextSegment = UI.gotoNextSegment
    var original_gotoPreviousSegment = UI.gotoPreviousSegment

    var original_openNextTranslated = UI.openNextTranslated

    /**
     * This function handles the movement to the next segment when filter is open. This is a natural operation
     * that is commonly used and likely to cause the number of segments loaded to be come huge.
     *
     */

    var gotoNextSegment = function () {
      var list = SegmentFilter.getLastFilterData()['segment_ids']
      var index = list.indexOf('' + UI.currentSegmentId)
      var nextFiltered = index !== list.length - 1 ? list[index + 1] : list[0]
      if (!nextFiltered) {
        return
      }
      SegmentActions.openSegment(nextFiltered)
    }

    var gotoPreviousSegment = function () {
      var list = SegmentFilter.getLastFilterData()['segment_ids']
      var index = list.indexOf('' + UI.currentSegmentId)
      var nextFiltered = index !== 0 ? list[index - 1] : list[list.length - 1]

      if (!nextFiltered) {
        return
      }

      SegmentActions.openSegment(nextFiltered)
    }

    var gotoNextTranslatedSegment = function (sid) {
      var list = SegmentFilter.getLastFilterData()['segment_ids']
      var index = list.indexOf('' + sid)
      var nextFiltered = index !== list.length - 1 ? list[index + 1] : list[0]
      let segment = SegmentStore.getSegmentByIdToJS(nextFiltered)
      if (segment && segment.status !== 'DRAFT' && segment.status !== 'NEW') {
        SegmentActions.openSegment(nextFiltered)
      } else if (segment) {
        gotoNextTranslatedSegment(nextFiltered)
      }
    }

    $.extend(UI, {
      openNextTranslated: function () {
        // this is expected behaviour in review
        // change this if we are filtering, go to the next
        // segment, assuming the sample is what we want to revise.
        if (SF.filtering()) {
          gotoNextTranslatedSegment(this.currentSegmentId)
        } else {
          original_openNextTranslated.apply(this, arguments)
        }
      },

      gotoPreviousSegment: function () {
        if (SF.filtering()) {
          gotoPreviousSegment.apply(this, arguments)
        } else {
          original_gotoPreviousSegment.apply(this, arguments)
        }
      },

      gotoNextSegment: function () {
        if (SF.filtering()) {
          gotoNextSegment.apply(this, arguments)
        } else {
          original_gotoNextSegment.apply(this, arguments)
        }
      },
      renderFiles: function () {
        original_renderFiles.apply(this, arguments)

        if (SF.filtering()) {
          // var segments = SegmentStore.getAllSegments();
          var filterArray = SF.getLastFilterData()['segment_ids']
          SegmentActions.setMutedSegments(filterArray)
          // segments.forEach(function (segment,index) {
          //     if (filterArray.indexOf(segment.sid) === -1) {
          //         SegmentActions.addClassToSegment(segment.sid, 'muted');
          //         SegmentActions.removeClassToSegment(segment.sid, 'editor opened');
          //     }
          // })
        }
      },
    })
  })(jQuery, UI, SegmentFilter)
