import SegmentStore from '../../../../stores/SegmentStore'
import SegmentActions from '../../../../actions/SegmentActions'
import CatToolActions from '../../../../actions/CatToolActions'
import CommonUtils from '../../../../utils/commonUtils'
import {getFilteredSegments} from '../../../../api/getFilteredSegments'

let SegmentFilterUtils = {
  enabled: () => config.segmentFilterEnabled,

  cachedStoredState: null,

  keyForLocalStorage: () => {
    var page = config.isReview ? 'revise' : 'translate'
    return (
      'SegmentFilter-v2-' + page + '-' + config.id_job + '-' + config.password
    )
  },

  segmentIsInSample: (segmentId, listOfSegments) => {
    return listOfSegments.indexOf(segmentId) !== -1
  },

  callbackForSegmentNotInSample: (segmentId) => {
    var title = 'Segment not in sample'
    var text =
      'Sample is trying to focus on segment #' +
      segmentId +
      ', but ' +
      'segment is no longer in the sample'

    return (function () {
      CatToolActions.addNotification({
        uid: 'segment-filter',
        autoDismiss: false,
        dismissable: true,
        position: 'bl',
        text: text,
        title: title,
        type: 'warning',
        allowHtml: true,
      })
    })()
  },

  tryToFocusLastSegment: () => {
    var segment = SegmentStore.getSegmentByIdToJS(
      SegmentFilterUtils.getStoredState().lastSegmentId,
    )

    if (!(SegmentFilterUtils.getStoredState().lastSegmentId && segment)) {
      return // the stored lastSegmentId is not in the DOM, this should never happen
    }

    if (segment.opened) {
      SegmentActions.scrollToSegment(segment.original_sid)
    } else {
      SegmentActions.openSegment(segment.sid)
    }
  },

  initEvents: () => {
    if (SegmentFilterUtils.enabled()) {
      $(document).on('segmentsAdded', function () {
        if (SegmentFilterUtils.filtering()) {
          SegmentFilterUtils.tryToFocusLastSegment()
        }
      })

      $(window).on('segmentOpened', function (event, data) {
        if (SegmentFilterUtils.filtering()) {
          SegmentFilterUtils.setStoredState({lastSegmentId: data.segmentId})
        }
      })

      $(document).on('click', 'header .filter', function (e) {
        e.preventDefault()
        if (!SegmentFilterUtils.open) {
          SegmentFilterUtils.openFilter()
        } else {
          SegmentFilterUtils.closeFilter()
          SegmentFilterUtils.open = false
        }
      })
    }
  },

  open: false,
  filteringSegments: false,
  getLastFilterData: () => {
    return SegmentFilterUtils.getStoredState().serverData
  },

  /**
   * This function return true if the user is in a filtered session with zoomed segments.
   *
   * @returns {*}
   */
  filtering: function () {
    return SegmentFilterUtils.filteringSegments
  },

  /**
   * @returns {{reactState: null, serverData: null, lastSegmentId: null}}
   */
  getStoredState: function () {
    if (null != SegmentFilterUtils.cachedStoredState) {
      return SegmentFilterUtils.cachedStoredState
    }

    var data = localStorage.getItem(SegmentFilterUtils.keyForLocalStorage())

    if (data) {
      try {
        SegmentFilterUtils.cachedStoredState = JSON.parse(data)
      } catch (e) {
        SegmentFilterUtils.clearStoredData()
        console.error(e.message)
      }
    } else {
      SegmentFilterUtils.cachedStoredState = {
        reactState: null,
        serverData: null,
        lastSegmentId: null,
      }
    }

    return SegmentFilterUtils.cachedStoredState
  },

  setStoredState: function (data) {
    SegmentFilterUtils.cachedStoredState = $.extend(
      SegmentFilterUtils.getStoredState(),
      data,
    )
    localStorage.setItem(
      SegmentFilterUtils.keyForLocalStorage(),
      JSON.stringify(SegmentFilterUtils.cachedStoredState),
    )
  },

  clearStoredData: function () {
    SegmentFilterUtils.cachedStoredState = null
    return localStorage.removeItem(SegmentFilterUtils.keyForLocalStorage())
  },

  filterSubmit: function (filter, extendendLocalStorageValues) {
    if (!extendendLocalStorageValues) {
      extendendLocalStorageValues = {}
    }
    SegmentFilterUtils.filteringSegments = true
    filter.revision = config.isReview
    var password = config.isReview ? config.review_password : config.password
    getFilteredSegments(
      config.id_job,
      password,
      filter,
      filter.revision_number,
    ).then((data) => {
      CommonUtils.clearStorage('SegmentFilter')

      SegmentActions.removeAllMutedSegments()

      $(document).trigger('segment-filter:filter-data:load', {data: data})

      var reactState = Object.assign(
        {
          filteredCount: data.count,
          filtering: true,
          segmentsArray: data.segment_ids,
        },
        extendendLocalStorageValues,
      )

      SegmentFilterUtils.setStoredState({
        serverData: data,
        reactState: reactState,
      })

      CatToolActions.setSegmentFilter(data)

      SegmentActions.setMutedSegments(data['segment_ids'])

      var segmentToOpen
      var lastSegmentId = SegmentFilterUtils.getStoredState().lastSegmentId
      if (!lastSegmentId) {
        segmentToOpen = data['segment_ids'][0]
        SegmentActions.scrollToSegment(segmentToOpen)
        SegmentActions.openSegment(segmentToOpen)
      } else if (
        lastSegmentId &&
        !SegmentFilterUtils.segmentIsInSample(
          lastSegmentId,
          data['segment_ids'],
        )
      ) {
        SegmentFilterUtils.callbackForSegmentNotInSample(lastSegmentId)
      } else {
        segmentToOpen = lastSegmentId
        SegmentActions.openSegment(segmentToOpen)
        SegmentActions.scrollToSegment(segmentToOpen)
      }
    })
  },

  /**
   * This function gets called when segments are still to be rendered
   * and sometimes when the segments are rendered ( click on filter icon ).
   *
   *
   */
  openFilter: () => {
    CatToolActions.openSegmentFilter()
    SegmentFilterUtils.open = true
    var localStorageData = SegmentFilterUtils.getStoredState()
    if (localStorageData.serverData) {
      SegmentActions.setMutedSegments(
        SegmentFilterUtils.getStoredState().serverData.segment_ids,
      )
      SegmentFilterUtils.filteringSegments = true
      setTimeout(() => {
        CatToolActions.setSegmentFilter(
          localStorageData.serverData,
          localStorageData.reactState,
        )
        CatToolActions.openSegmentFilter()
        SegmentFilterUtils.tryToFocusLastSegment()
      }, 200)
    }
  },

  clearFilter: function () {
    SegmentFilterUtils.clearStoredData()
    SegmentFilterUtils.filteringSegments = false
    SegmentActions.removeAllMutedSegments()
  },

  closeFilter: function () {
    CatToolActions.closeSubHeader()
    SegmentFilterUtils.open = false
    SegmentActions.removeAllMutedSegments()
    setTimeout(function () {
      SegmentActions.scrollToSegment(UI.currentSegmentId)
    }, 600)
  },
  goToNextRepetition: function (button, status) {
    var hash = UI.currentSegment.data('hash')
    var segmentFilterData = SegmentFilterUtils.getStoredState()
    var groupArray = segmentFilterData.serverData.grouping[hash]
    var index = groupArray.indexOf(UI.currentSegmentId)
    var nextItem
    if (index >= 0 && index < groupArray.length - 1) {
      nextItem = groupArray[index + 1]
    } else {
      nextItem = groupArray[0]
    }
    UI.changeStatus(SegmentStore.getCurrentSegment(), status, function () {
      SegmentActions.openSegment(nextItem)
    })
  },
  goToNextRepetitionGroup: function (button, status) {
    var hash = UI.currentSegment.data('hash')
    var segmentFilterData = SegmentFilterUtils.getStoredState()
    var groupsArray = Object.keys(segmentFilterData.serverData.grouping)
    var index = groupsArray.indexOf(hash)
    var nextGroupHash
    if (index >= 0 && index < groupsArray.length - 1) {
      nextGroupHash = groupsArray[index + 1]
    } else {
      nextGroupHash = groupsArray[0]
    }
    var nextItem = segmentFilterData.serverData.grouping[nextGroupHash][0]

    UI.changeStatus(SegmentStore.getCurrentSegment(), status, function () {
      SegmentActions.openSegment(nextItem)
    })
  },
}

export default SegmentFilterUtils
