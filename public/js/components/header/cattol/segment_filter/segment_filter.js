import $ from 'jquery'
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
      document.addEventListener('segmentsAdded', function () {
        if (SegmentFilterUtils.filtering()) {
          SegmentFilterUtils.tryToFocusLastSegment()
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
    return SegmentFilterUtils.filteringSegments && SegmentFilterUtils.open
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
    getFilteredSegments(config.id_job, password, filter, config.revisionNumber)
      .then((data) => {
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
          open: true,
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
      .catch(() => {
        CatToolActions.setSegmentFilterError()
        CatToolActions.addNotification({
          title: 'Segments filters error',
          type: 'error',
          text: 'We got an error, please contact support',
          position: 'br',
          timer: 5000,
        })
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
    SegmentFilterUtils.setStoredState({
      open: true,
    })
    const localStorageData = SegmentFilterUtils.getStoredState()
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
    SegmentFilterUtils.setStoredState({
      open: false,
    })
    SegmentActions.removeAllMutedSegments()
    setTimeout(function () {
      SegmentActions.scrollToSegment(UI.currentSegmentId)
    }, 600)
  },
  goToNextRepetition: function (status) {
    const segment = SegmentStore.getCurrentSegment()
    const hash = segment.segment_hash
    const segmentFilterData = SegmentFilterUtils.getStoredState()
    const groupArray = segmentFilterData.serverData.grouping[hash]
    const index = groupArray ? groupArray.indexOf(UI.currentSegmentId) : -1
    let nextItem
    if (index >= 0 && index < groupArray.length - 1) {
      nextItem = groupArray[index + 1]
    } else if (groupArray) {
      nextItem = groupArray[0]
    } else {
      return
    }
    UI.changeStatus(segment, status, function () {
      SegmentActions.openSegment(nextItem)
    })
  },
  goToNextRepetitionGroup: function (status) {
    const segment = SegmentStore.getCurrentSegment()
    const hash = segment.segment_hash
    const segmentFilterData = SegmentFilterUtils.getStoredState()
    const groupsArray = Object.keys(segmentFilterData.serverData.grouping)
    const index = groupsArray.indexOf(hash)
    let nextGroupHash
    if (index >= 0 && index < groupsArray.length - 1) {
      nextGroupHash = groupsArray[index + 1]
    } else {
      nextGroupHash = groupsArray[0]
    }
    const nextItem = segmentFilterData.serverData.grouping[nextGroupHash][0]

    UI.changeStatus(segment, status, function () {
      SegmentActions.openSegment(nextItem)
    })
  },
  gotoPreviousSegment: () => {
    var list = SegmentFilterUtils.getLastFilterData()['segment_ids']
    var index = list.indexOf('' + SegmentStore.getCurrentSegmentId())
    var nextFiltered = index !== 0 ? list[index - 1] : list[list.length - 1]

    if (!nextFiltered) {
      return
    }

    SegmentActions.openSegment(nextFiltered)
  },
  gotoNextTranslatedSegment: (sid) => {
    const filteredData = SegmentFilterUtils.getLastFilterData()['segment_ids']
    if (filteredData) {
      const index = filteredData.indexOf('' + sid)
      const nextFiltered =
        index !== filteredData.length - 1
          ? filteredData[index + 1]
          : filteredData[0]
      let segment = SegmentStore.getSegmentByIdToJS(nextFiltered)
      if (segment && segment.status !== 'DRAFT' && segment.status !== 'NEW') {
        SegmentActions.openSegment(nextFiltered)
      } else if (segment) {
        SegmentFilterUtils.gotoNextTranslatedSegment(nextFiltered)
      }
    }
  },
  gotoNextSegment: (sid) => {
    var list = SegmentFilterUtils.getLastFilterData()['segment_ids']
    var index = list.indexOf('' + sid)
    var nextFiltered = index !== list.length - 1 ? list[index + 1] : list[0]
    if (!nextFiltered) {
      return
    }
    SegmentActions.openSegment(nextFiltered)
  },
}

export default SegmentFilterUtils
