/*
 * Segment structure example
 * {
     "last_opened_segment":"61079",
     "sid":"60984",
     "segment":"INDIETRO",
     "segment_hash":"0a7e4ea10d93b636d9de15132300870c",
     "raw_word_count":"1.00",
     "internal_id":"P147242AB-tu19",
     "translation":"",
     "version":null,
     "original_target_provied":"0",
     "status":"NEW",
     "time_to_edit":"0",
     "xliff_ext_prec_tags":"",
     "xliff_ext_succ_tags":"",
     "warning":"0",
     "suggestion_match":"85",
     "source_chunk_lengths":[],
     "target_chunk_lengths":{
         "len":[0],
         "statuses":["DRAFT"]
     },
     "readonly":"false",
     "autopropagated_from":"0",
     "repetitions_in_chunk":"1",
     "has_reference":"false",
     "parsed_time_to_edit":["00","00","00","00"],
     "notes":null
 }
 */
import {isUndefined, uniq, each} from 'lodash'
import {EventEmitter} from 'events'
import Immutable from 'immutable'
import assign from 'object-assign'

import AppDispatcher from './AppDispatcher'
import SegmentConstants from '../constants/SegmentConstants'
import SegmentUtils from '../utils/segmentUtils'
import EditAreaConstants from '../constants/EditAreaConstants'
import DraftMatecatUtils from './../components/segments/utils/DraftMatecatUtils'
import {
  JOB_WORD_CONT_TYPE,
  REVISE_STEP_NUMBER,
  SEGMENTS_STATUS,
} from '../constants/Constants'

EventEmitter.prototype.setMaxListeners(0)

const normalizeSetUpdateGlossary = (terms) => {
  const {term} = terms

  const metadataKeys = term.metadata.keys
    ? term.metadata.keys
    : [{key: term.metadata.key, key_name: term.metadata.key_name}]

  return metadataKeys.map(({key, key_name}) => {
    const {
      keys,
      key: keyProp,
      key_name: keyNameProp,
      ...restMetadata
    } = term.metadata
    return {
      ...term,
      metadata: {
        ...restMetadata,
        key,
        key_name,
      },
    }
  })
}

const SegmentStore = assign({}, EventEmitter.prototype, {
  _segments: Immutable.fromJS([]),
  _globalWarnings: {
    lexiqa: [],
    matecat: {
      ERROR: {
        Categories: [],
        total: 0,
      },
      WARNING: {
        Categories: [],
        total: 0,
      },
      INFO: {
        Categories: [],
        total: 0,
      },
    },
  },
  segmentsInBulk: [],
  _footerTabsConfig: Immutable.fromJS({}),
  searchOccurrences: [],
  searchResultsDictionary: {},
  currentInSearch: 0,
  searchParams: {},
  nextUntranslatedFromServer: null,
  consecutiveCopySourceNum: [],
  clipboardFragment: '',
  clipboardPlainText: '',
  sideOpen: false,
  isSearchingGlossaryInTarget: false,
  helpAiAssistantWords: undefined,
  _aiSuggestions: [],
  /**
   * Update all
   */
  updateAll: function (segments, where) {
    if (this._segments.size > 0 && where === 'before') {
      this._segments = this._segments.unshift(
        ...Immutable.fromJS(this.normalizeSplittedSegments(segments)),
      )
    } else if (this._segments.size > 0 && where === 'after') {
      this._segments = this._segments.push(
        ...Immutable.fromJS(this.normalizeSplittedSegments(segments)),
      )
    } else {
      this._segments = Immutable.fromJS(
        this.normalizeSplittedSegments(segments),
      )
    }

    if (this.segmentsInBulk.length > 0) {
      this.setBulkSelectionSegments(this.segmentsInBulk)
    }
  },
  removeAllSegments: function () {
    this._segments = Immutable.fromJS([])
  },
  normalizeSplittedSegments: function (segments) {
    let newSegments = []
    $.each(segments, (i, segment) => {
      let splittedSourceAr = segment.segment.split(
        UI.splittedTranslationPlaceholder,
      )
      let inSearch = false
      let currentInSearch = false
      let occurrencesInSearch = null
      //if search active
      if (this.searchOccurrences.length > 0) {
        inSearch = this.searchOccurrences.indexOf(segment.sid) > -1
        currentInSearch =
          segment.sid === this.searchOccurrences[this.currentInSearch]
        occurrencesInSearch = this.searchResultsDictionary[segment.sid]
      }
      if (splittedSourceAr.length > 1) {
        var splitGroup = []
        $.each(splittedSourceAr, (i) => {
          splitGroup.push(segment.sid + '-' + (i + 1))
        })

        $.each(splittedSourceAr, (i) => {
          let translation = segment.translation.split(
            UI.splittedTranslationPlaceholder,
          )[i]
          let status = segment.target_chunk_lengths.statuses[i]
          let segData = {
            saving: false,
            splitted: true,
            autopropagated_from: '0',
            has_reference: 'false',
            parsed_time_to_edit: ['00', '00', '00', '00'],
            readonly: 'false',
            segment: splittedSourceAr[i],
            decodedSource: DraftMatecatUtils.transformTagsToText(
              segment.segment,
            ),
            segment_hash: segment.segment_hash,
            original_sid: segment.sid,
            sid: segment.sid + '-' + (i + 1),
            split_group: splitGroup,
            split_points_source: [],
            status:
              config.word_count_type === JOB_WORD_CONT_TYPE.RAW &&
              segment.revision_number === REVISE_STEP_NUMBER.REVISE2 &&
              status.toUpperCase() === SEGMENTS_STATUS.APPROVED
                ? SEGMENTS_STATUS.APPROVED2
                : status,
            time_to_edit: '0',
            originalDecodedTranslation: translation ? translation : '',
            translation: translation ? translation : '',
            decodedTranslation:
              DraftMatecatUtils.transformTagsToText(translation),
            warning: '0',
            warnings: {},
            tagged: !this.hasSegmentTagProjectionEnabled(segment),
            unlocked: false,
            edit_area_locked: false,
            notes: segment.notes,
            modified: false,
            opened: false,
            selected: false,
            id_file: segment.id_file,
            originalSource: segment.segment,
            firstOfSplit: i === 0,
            inSearch: inSearch,
            currentInSearch: currentInSearch,
            occurrencesInSearch: occurrencesInSearch,
            searchParams: this.searchParams,
            updatedSource: splittedSourceAr[i],
            openComments: false,
            openSplit: false,
            metadata: segment.metadata,
            ...(segment.id_file_part && {id_file_part: segment.id_file_part}),
          }
          newSegments.push(segData)
          segData = null
        })
      } else {
        segment.saving = false
        segment.status =
          segment.revision_number === REVISE_STEP_NUMBER.REVISE2 &&
          segment.status.toUpperCase() === SEGMENTS_STATUS.APPROVED
            ? SEGMENTS_STATUS.APPROVED2
            : segment.status
        segment.splitted = false
        segment.original_translation = segment.translation
        segment.unlocked = SegmentUtils.isUnlockedSegment(segment)
        segment.warnings = {}
        segment.tagged = !this.hasSegmentTagProjectionEnabled(segment)
        segment.edit_area_locked = false
        segment.original_sid = segment.sid
        segment.modified = false
        segment.opened = false
        segment.selected = false
        segment.propagable = segment.repetitions_in_chunk !== '1'
        segment.inSearch = inSearch
        segment.currentInSearch = currentInSearch
        segment.occurrencesInSearch = occurrencesInSearch
        segment.searchParams = this.searchParams
        segment.originalDecodedTranslation = segment.translation
        segment.decodedTranslation = DraftMatecatUtils.transformTagsToText(
          segment.translation,
        )
        segment.decodedSource = DraftMatecatUtils.transformTagsToText(
          segment.segment,
        )
        segment.updatedSource = SegmentUtils.checkCurrentSegmentTPEnabled(
          segment,
        )
          ? DraftMatecatUtils.removeTagsFromText(segment.segment)
          : segment.segment
        segment.openComments = false
        segment.openSplit = false
        newSegments.push(segment)
      }
    })
    return newSegments
  },

  openSegment(sid) {
    var index = this.getSegmentIndex(sid)
    this.closeSegments()
    this._segments = this._segments.setIn([index, 'opened'], true)
  },
  selectNextSegment() {
    let selectedSegment = this._segments.find((segment) => {
      return segment.get('selected') === true
    })
    if (!selectedSegment) {
      selectedSegment = this.getCurrentSegment()
    } else {
      selectedSegment = selectedSegment.toJS()
    }
    let next = this.getNextSegment({current_sid: selectedSegment.sid})
    if (next) {
      var index = this.getSegmentIndex(next.sid)
      this._segments = this._segments.map((segment) =>
        segment.set('selected', false),
      )
      this._segments = this._segments.setIn([index, 'selected'], true)
      return next.sid
    }
  },
  selectPrevSegment() {
    let selectedSegment = this._segments.find((segment) => {
      return segment.get('selected') === true
    })
    if (!selectedSegment) {
      selectedSegment = this.getCurrentSegment()
    } else if (selectedSegment) {
      selectedSegment = selectedSegment.toJS()
    } else {
      return
    }
    let prev = this.getPrevSegment(selectedSegment.sid)
    if (prev) {
      var index = this.getSegmentIndex(prev.sid)
      this._segments = this._segments.map((segment) =>
        segment.set('selected', false),
      )
      this._segments = this._segments.setIn([index, 'selected'], true)
      return prev.sid
    }
  },
  getSelectedSegmentId() {
    let selectedSegment = this._segments.find((segment) => {
      return segment.get('selected') === true
    })
    if (selectedSegment) {
      return selectedSegment.get('sid')
    }
    return null
  },
  closeSegments() {
    this._segments = this._segments.map((segment) =>
      segment.set('opened', false),
    )
    this._segments = this._segments.map((segment) =>
      segment.set('selected', false),
    )
  },
  setStatus(sid, fid, status) {
    const index = this.getSegmentIndex(sid)
    status =
      config.revisionNumber === REVISE_STEP_NUMBER.REVISE2 &&
      status === SEGMENTS_STATUS.APPROVED
        ? SEGMENTS_STATUS.APPROVED2
        : status
    if (index === -1) return
    this._segments = this._segments.setIn([index, 'status'], status)
    this._segments = this._segments.setIn(
      [index, 'revision_number'],
      config.revisionNumber,
    )
  },

  setSuggestionMatch(sid, fid, perc) {
    var index = this.getSegmentIndex(sid)
    if (index === -1) return
    this._segments = this._segments.setIn(
      [index, 'suggestion_match'],
      perc.replace('%', ''),
    )
  },

  setPropagation(sid, fid, propagation, from) {
    let index = this.getSegmentIndex(sid)
    if (index === -1) return
    if (propagation) {
      this._segments = this._segments.setIn(
        [index, 'autopropagated_from'],
        from,
      )
    } else {
      this._segments = this._segments.setIn([index, 'autopropagated_from'], '0')
    }
  },
  replaceTranslation(sid, translation) {
    var index = this.getSegmentIndex(sid)
    if (index === -1) return
    this._segments = this._segments.setIn([index, 'translation'], translation)
  },
  updateOriginalTranslation(sid, translation) {
    const index = this.getSegmentIndex(sid)
    if (index === -1) return
    const newTrans = DraftMatecatUtils.transformTagsToText(translation)

    this._segments = this._segments.setIn(
      [index, 'originalDecodedTranslation'],
      translation,
    )
    this._segments = this._segments.setIn(
      [index, 'decodedTranslation'],
      newTrans,
    )
  },
  updateTranslation(
    sid,
    translation,
    decodedTranslation,
    tagMap,
    missingTagsInTarget,
    lxqDecodedTranslation,
  ) {
    var index = this.getSegmentIndex(sid)
    const segment = this._segments.get(index)
    if (!segment) return

    //Check segment is modified
    if (segment.get('originalDecodedTranslation') !== translation) {
      this._segments = this._segments.setIn([index, 'modified'], true)
    } else {
      this._segments = this._segments.setIn([index, 'modified'], false)
    }
    this._segments = this._segments.setIn([index, 'translation'], translation)
    this._segments = this._segments.setIn(
      [index, 'decodedTranslation'],
      decodedTranslation,
    )
    this._segments = this._segments.setIn([index, 'targetTagMap'], tagMap)
    this._segments = this._segments.setIn(
      [index, 'missingTagsInTarget'],
      missingTagsInTarget,
    )
    this._segments = this._segments.setIn(
      [index, 'lxqDecodedTranslation'],
      lxqDecodedTranslation,
    )
  },
  updateSource(sid, source, decodedSource, tagMap, lxqDecodedSource) {
    var index = this.getSegmentIndex(sid)
    if (index === -1) return

    this._segments = this._segments.setIn(
      [index, 'decodedSource'],
      decodedSource,
    )
    this._segments = this._segments.setIn([index, 'updatedSource'], source)
    this._segments = this._segments.setIn([index, 'sourceTagMap'], tagMap)
    this._segments = this._segments.setIn(
      [index, 'lxqDecodedSource'],
      lxqDecodedSource,
    )
  },
  modifiedTranslation(sid, modified) {
    const index = this.getSegmentIndex(sid)
    if (index === -1) return
    this._segments = this._segments.setIn([index, 'modified'], modified)
    if (!modified) {
      let segment = this._segments.get(index)
      this._segments = this._segments.setIn(
        [index, 'originalDecodedTranslation'],
        segment.get('translation'),
      )
    }
  },
  setSegmentAsTagged(sid) {
    var index = this.getSegmentIndex(sid)
    if (index === -1) return
    this._segments = this._segments.setIn([index, 'tagged'], true)
  },

  addSegmentVersions(sid, versions) {
    //If is a splitted segment the versions are added to the first of the split
    let index = this.getSegmentIndex(sid)
    if (index === -1) return
    if (
      versions.length === 1 &&
      versions[0].id === 0 &&
      versions[0].translation == ''
    ) {
      // TODO Remove this if
      this._segments = this._segments.setIn(
        [index, 'versions'],
        Immutable.fromJS([]),
      )
      return this._segments.get(index)
    }
    this._segments = this._segments.setIn(
      [index, 'versions'],
      Immutable.fromJS(versions),
    )
    return this._segments.get(index)
  },
  addSegmentPreloadedIssues(sid, issues) {
    //If is a splitted segment the versions are added to the first of the split
    let index = this.getSegmentIndex(sid)
    if (index === -1) return
    let versions = []
    versions.push({
      issues: issues,
    })
    this._segments = this._segments.setIn(
      [index, 'versions'],
      Immutable.fromJS(versions),
    )
    return this._segments.get(index)
  },
  lockUnlockEditArea(sid) {
    let index = this.getSegmentIndex(sid)
    if (index === -1) return
    let segment = this._segments.get(index)
    let lockedEditArea = segment.get('edit_area_locked')
    this._segments = this._segments.setIn(
      [index, 'edit_area_locked'],
      !lockedEditArea,
    )
  },
  setToggleBulkOption: function (sid) {
    let index = this.getSegmentIndex(sid)
    if (index === -1) return
    if (this._segments.getIn([index, 'inBulk'])) {
      let indexArray = this.segmentsInBulk.indexOf(sid)
      this.segmentsInBulk.splice(indexArray, 1)
      this._segments = this._segments.setIn([index, 'inBulk'], false)
    } else {
      this.segmentsInBulk.push(sid)
      this._segments = this._segments.setIn([index, 'inBulk'], true)
    }
  },
  removeBulkOption: function () {
    let self = this
    this._segments = self._segments.map((segment) =>
      segment.set('inBulk', false),
    )
    this.segmentsInBulk = []
  },
  setBulkSelectionInterval: function (from, to, fid) {
    let index = this.getSegmentIndex(from)
    if (
      index > -1 &&
      this._segments.get(index).get('readonly') == 'false' && //not readonly
      (this._segments.get(index).get('ice_locked') === '0' || //not ice_locked
        (this._segments.get(index).get('ice_locked') === '1' &&
          this._segments.get(index).get('unlocked'))) //unlocked
    ) {
      this._segments = this._segments.setIn([index, 'inBulk'], true)
      if (this.segmentsInBulk.indexOf(from.toString()) === -1) {
        this.segmentsInBulk.push(from.toString())
      }
    }
    if (from < to) {
      this.setBulkSelectionInterval(from + 1, to, fid)
    }
  },
  setBulkSelectionSegments: function (segmentsArray) {
    this.segmentsInBulk = segmentsArray
    this._segments = this._segments.map((segment) => {
      if (segmentsArray.indexOf(segment.get('sid')) > -1) {
        if (segment.get('ice_locked') == '1' && !segment.get('unlocked')) {
          let index = segmentsArray.indexOf(segment.get('sid'))
          this.segmentsInBulk.splice(index, 1) // if is a locked segment remove it from bulk
        } else {
          return segment.set('inBulk', true)
        }
      }
      return segment.set('inBulk', false)
    })
  },
  setMutedSegments: function (segmentsArray) {
    this._segments = this._segments.map((segment) =>
      segment.set('filtering', true),
    )
    this._segments = this._segments.map((segment) => {
      if (segmentsArray.indexOf(segment.get('sid')) === -1) {
        return segment.set('muted', true)
      }
      return segment
    })
  },
  removeAllMutedSegments: function () {
    this._segments = this._segments.map((segment) =>
      segment.set('filtering', false),
    )
    this._segments = this._segments.map((segment) =>
      segment.set('muted', false),
    )
  },
  setUnlockedSegment: function (sid, fid, unlocked) {
    let index = this.getSegmentIndex(sid)
    if (index === -1) return
    this._segments = this._segments.setIn([index, 'unlocked'], unlocked)
  },

  unlockSegments: function (segments) {
    segments.forEach((sid) => {
      let index = this.getSegmentIndex(sid)
      if (index === -1) return
      this._segments = this._segments.setIn([index, 'unlocked'], true)
    })
  },

  setConcordanceMatches: function (sid, matches) {
    const index = this.getSegmentIndex(sid)
    if (index === -1) return
    this._segments = this._segments.setIn(
      [index, 'concordance'],
      Immutable.fromJS(matches),
    )
  },
  setContributionsToCache: function (sid, contributions, errors) {
    const index = this.getSegmentIndex(sid)
    if (index === -1) return
    this._segments = this._segments.setIn(
      [index, 'contributions'],
      Immutable.fromJS({
        matches: contributions,
        errors: errors,
      }),
    )
  },
  setAlternatives: function (sid, alternatives) {
    const index = this.getSegmentIndex(sid)
    if (index === -1) return
    if (isUndefined(alternatives)) {
      this._segments = this._segments.deleteIn([index, 'alternatives'])
    } else {
      this._segments = this._segments.setIn(
        [index, 'alternatives'],
        Immutable.fromJS(alternatives),
      )
    }
  },
  deleteContribution: function (sid, matchId) {
    const index = this.getSegmentIndex(sid)
    let contributions = this._segments.get(index).get('contributions')
    const indexCont = contributions
      .get('matches')
      .findIndex((contr) => contr.get('id') === matchId)
    let matches = contributions.get('matches').splice(indexCont, 1)
    this._segments = this._segments.setIn(
      [index, 'contributions', 'matches'],
      matches,
    )
  },
  setGlossaryToCache: function (sid, terms) {
    if (!this._segments.size) return
    const adaptedTerms = terms.map((term) => ({
      ...term,
      matching_words: term.matching_words.filter((value) => value),
    }))
    const index = this.getSegmentIndex(sid)
    const segment = this._segments.get(index)
    if (!segment) return

    const pendingGlossaryUpdates = this._segments
      .get(index)
      .get('pendingGlossaryUpdates')
      ? segment.get('pendingGlossaryUpdates').toJS()
      : []

    const isGlossaryAlreadyExist = !!segment.get('glossary')
    const glossary = isGlossaryAlreadyExist
      ? segment.get('glossary').toJS()
      : []

    this._segments = this._segments.setIn(
      [index, 'glossary'],
      Immutable.fromJS(
        adaptedTerms.map((term) => ({
          ...term,
          missingTerm: glossary.find(({term_id}) => term_id === term.term_id)
            ?.missingTerm,
        })),
      ),
    )
    this.setGlossarySearchToCache(sid)

    if (pendingGlossaryUpdates)
      this.addOrUpdateGlossaryItem(sid, pendingGlossaryUpdates)

    this._segments = this._segments.deleteIn([index, 'pendingGlossaryUpdates'])
  },
  setGlossarySearchToCache: function (sid, terms) {
    const index = this.getSegmentIndex(sid)
    const segment = this._segments.get(index)
    if (!segment) return

    this._segments = this._segments.setIn(
      [index, 'glossary_search_results'],
      Immutable.fromJS(terms ? terms : segment.get('glossary')),
    )
  },
  deleteFromGlossary: function (sid, term) {
    const index = this.getSegmentIndex(sid)
    const segment = this._segments.get(index)
    if (!segment) return

    let glossary = segment.get('glossary').toJS()
    const updatedGlossary = glossary.filter(
      ({term_id}) => term.term_id !== term_id,
    )
    this._segments = this._segments.setIn(
      [index, 'glossary'],
      Immutable.fromJS(updatedGlossary),
    )
    this.setGlossarySearchToCache(sid)
  },
  addOrUpdateGlossaryItem: function (
    sid,
    terms,
    shouldCheckMissingTerms = false,
  ) {
    const addedTerms = terms.map((term) => ({
      ...term,
      matching_words: term.matching_words
        ? term.matching_words.filter((value) => value)
        : [],
    }))
    if (!this._segments.size) return
    const index = this.getSegmentIndex(sid)
    const segment = this._segments.get(index)
    if (!segment) return

    const isGlossaryAlreadyExist = !!segment.get('glossary')
    const glossary = isGlossaryAlreadyExist
      ? segment.get('glossary').toJS()
      : []

    const updatedGlossary = glossary.length
      ? glossary
          .map((term) => ({
            ...term,
            ...((shouldCheckMissingTerms || term.missingTerm === undefined) && {
              missingTerm: false,
            }),
          }))
          .map((term) => {
            const matchedTerm = addedTerms.find(
              ({term_id}) => term_id === term.term_id,
            )
            return matchedTerm ? matchedTerm : term
          })
      : addedTerms

    this._segments = this._segments.setIn(
      [index, isGlossaryAlreadyExist ? 'glossary' : 'pendingGlossaryUpdates'],
      Immutable.fromJS(updatedGlossary),
    )
    this.setGlossarySearchToCache(sid, updatedGlossary)
  },
  setCrossLanguageContributionsToCache: function (
    sid,
    fid,
    contributions,
    errors,
  ) {
    const index = this.getSegmentIndex(sid)
    if (index === -1) return
    this._segments = this._segments.setIn([index, 'cl_contributions'], {
      matches: contributions,
      errors: errors,
    })
  },
  closeSide: function () {
    this.sideOpen = false
  },
  openSide: function () {
    this.sideOpen = true
  },
  isSideOpen: function () {
    return this.sideOpen
  },
  segmentHasIssues: function (segment) {
    if (!segment) return false
    const versionWithIssues =
      segment.versions &&
      segment.versions.find((item) => item.issues && item.issues.length > 0)
    return versionWithIssues && versionWithIssues.issues.length > 0
  },
  openSegmentIssuePanel: function () {
    // const index = this.getSegmentIndex(sid);
    // if ( index === -1 ) return;
    // this._segments = this._segments.setIn([index, 'openIssues'], true);
    this._segments = this._segments.map((segment) =>
      segment.set('openIssues', true),
    )
  },
  closeSegmentIssuePanel: function () {
    this._segments = this._segments.map((segment) =>
      segment.set('openIssues', false),
    )
  },
  openSegmentComments: function (sid) {
    const index = this.getSegmentIndex(sid)
    if (index === -1) return
    this._segments = this._segments.map((segment) =>
      segment.set('openComments', false),
    )
    this._segments = this._segments.setIn([index, 'openComments'], true)
  },
  closeSegmentComments: function (sid) {
    if (sid) {
      const index = this.getSegmentIndex(sid)
      try {
        this._segments = this._segments.setIn([index, 'openComments'], false)
      } catch (e) {
        console.log('closeSegmentComments fail')
      }
    } else {
      this._segments = this._segments.map((segment) =>
        segment.set('openComments', false),
      )
    }
  },

  setConfigTabs: function (tabName, visible, open) {
    if (open) {
      this._footerTabsConfig = this._footerTabsConfig.map((tab) =>
        tab.set('open', false),
      )
    }
    this._footerTabsConfig = this._footerTabsConfig.setIn(
      [tabName, 'visible'],
      visible,
    )
    this._footerTabsConfig = this._footerTabsConfig.setIn(
      [tabName, 'open'],
      open,
    )
    this._footerTabsConfig = this._footerTabsConfig.setIn(
      [tabName, 'enabled'],
      true,
    )
  },
  setChoosenSuggestion: function (sid, sugIndex) {
    sugIndex = sugIndex ? sugIndex : undefined
    this._segments = this._segments.map((segment) =>
      segment.set('choosenSuggestionIndex', sugIndex),
    )
  },
  filterGlobalWarning: function (type, sid) {
    if (type === 'TAGS') {
      let index = this.getSegmentIndex(sid)
      if (index !== -1) {
        let segment = this._segments.get(index)
        return segment.get('tagged')
      }
    }

    return sid > -1
  },
  // Local warnings
  setSegmentWarnings(sid, warning, tagMismatch) {
    let index = this.getSegmentIndex(sid)
    if (index === -1) return
    this._segments = this._segments.setIn(
      [index, 'warnings'],
      Immutable.fromJS(warning),
    )
    this._segments = this._segments.setIn(
      [index, 'tagMismatch'],
      Immutable.fromJS(tagMismatch),
    )
  },
  setQACheck(sid, data) {
    const {
      missing_terms: missingTerms = [],
      blacklisted_terms: blacklistedTerms = [],
    } = data || {}
    const terms = missingTerms.map((term) => ({
      ...term,
      missingTerm: true,
    }))

    this.addOrUpdateGlossaryItem(sid, terms, true)

    // setup blacklisted
    const index = this.getSegmentIndex(sid)
    if (index === -1) return
    this._segments = this._segments.setIn(
      [index, 'qaBlacklistGlossary'],
      blacklistedTerms,
    )
  },
  setSegmentSaving(sid, saving) {
    const index = this.getSegmentIndex(sid)
    if (index === -1) return
    this._segments = this._segments.setIn([index, 'saving'], saving)
  },
  /**
   *
   * @param sid
   * @param matches
   * @param type 1 -> source, 2->target
   */
  addLexiqaHighlight(sid, matches, type) {
    const index = this.getSegmentIndex(sid)
    if (type === 1) {
      this._segments = this._segments.setIn(
        [index, 'lexiqa', 'source'],
        Immutable.fromJS(matches),
      )
    } else if (type === 2) {
      this._segments = this._segments.setIn(
        [index, 'lexiqa', 'target'],
        Immutable.fromJS(matches),
      )
    } else {
      this._segments = this._segments.setIn(
        [index, 'lexiqa'],
        Immutable.fromJS(matches),
      )
    }
  },
  updateGlobalWarnings: function (warnings) {
    let totalWarnings = []
    Object.keys(warnings).map((key) => {
      let totalCategoryWarnings = []
      if (key === 'total') return
      Object.keys(warnings[key].Categories).map((key2) => {
        totalCategoryWarnings.push(...warnings[key].Categories[key2])
        totalWarnings.push(...warnings[key].Categories[key2])
        warnings[key].total = totalCategoryWarnings.filter(
          (value, index, self) => {
            return self.indexOf(value) === index
          },
        ).length
        warnings[key].Categories[key2] = warnings[key].Categories[key2].filter(
          this.filterGlobalWarning.bind(this, key2),
        )
      })
    })
    this._globalWarnings.matecat = warnings
    this._globalWarnings.matecat.total = uniq(totalWarnings).length
    //lexiqa
    if (this._globalWarnings.lexiqa && this._globalWarnings.lexiqa.length > 0) {
      this._globalWarnings.matecat.INFO.Categories['lexiqa'] = uniq(
        this._globalWarnings.lexiqa,
      )
      this._globalWarnings.matecat.INFO.total =
        this._globalWarnings.matecat.INFO.Categories['lexiqa'].length
      this._globalWarnings.matecat.total = uniq([
        ...totalWarnings,
        ...this._globalWarnings.matecat.INFO.Categories['lexiqa'],
      ]).length
    }
  },
  updateLexiqaWarnings: function (warnings) {
    this._globalWarnings.lexiqa = uniq(
      warnings.filter(this.filterGlobalWarning.bind(this, 'LXQ')),
    )
    if (warnings && warnings.length > 0) {
      this._globalWarnings.matecat.INFO.Categories['lexiqa'] = warnings
      this.updateGlobalWarnings(this._globalWarnings.matecat)
    } else {
      this.removeLexiqaWarning()
    }
  },
  removeLexiqaWarning: function () {
    this._segments = this._segments.map((segment) => segment.delete('lexiqa'))
  },
  addSearchResult: function (
    occurrencesList,
    searchResultsDictionary,
    current,
    params,
  ) {
    this.searchOccurrences = occurrencesList
    this.searchResultsDictionary = searchResultsDictionary
    this.currentInSearch = current
    this.searchParams = params
    this._segments = this._segments.map((segment) => {
      segment = segment.set(
        'inSearch',
        occurrencesList.indexOf(segment.get('sid')) > -1,
      )
      segment = segment.set(
        'currentInSearch',
        segment.get('sid') == occurrencesList[current],
      )
      segment = segment.set(
        'occurrencesInSearch',
        searchResultsDictionary[segment.get('sid')],
      )
      segment = segment.set('searchParams', params)
      if (segment.get('sid') === this.searchOccurrences[current]) {
        segment = segment.set('currentInSearchIndex', current)
      }
      return segment
    })
  },
  addCurrentSearchSegment: function (current) {
    this.currentInSearch = current
    let currentSegment
    this._segments = this._segments.map((segment) => {
      segment = segment.set(
        'currentInSearch',
        segment.get('sid') == this.searchOccurrences[current],
      )
      if (segment.get('sid') == this.searchOccurrences[current]) {
        segment = segment.set('currentInSearchIndex', current)
        currentSegment = segment
      } else {
        segment = segment.set('currentInSearchIndex', false)
      }
      return segment
    })
    return currentSegment
  },
  removeSearchResults: function () {
    this._segments = this._segments.map((segment) =>
      segment.set('inSearch', null),
    )
    this._segments = this._segments.map((segment) =>
      segment.set('currentInSearch', null),
    )
    this._segments = this._segments.map((segment) =>
      segment.set('occurrencesInSearch', null),
    )
    this._segments = this._segments.map((segment) =>
      segment.set('searchParams', null),
    )
    this.searchOccurrences = []
    this.searchResultsDictionary = {}
    this.currentInSearch = 0
    this.searchParams = {}
  },
  openSegmentSplit: function (sid) {
    let index = this.getSegmentIndex(sid)
    if (index === -1) return
    this._segments = this._segments.setIn([index, 'openSplit'], true)
  },
  closeSegmentsSplit: function () {
    this._segments = this._segments.map((segment) =>
      segment.set('openSplit', false),
    )
  },
  hasSegmentTagProjectionEnabled: function (segment) {
    if (SegmentUtils.checkTPEnabled()) {
      if (
        (segment.status === 'NEW' || segment.status === 'DRAFT') &&
        DraftMatecatUtils.checkXliffTagsInText(segment.segment) &&
        !DraftMatecatUtils.checkXliffTagsInText(segment.translation)
      ) {
        return true
      }
    }
    return false
  },
  setTagProjectionStatus: function (enabled) {
    this._segments = this._segments.map((segment) =>
      segment.set('tpEnabled', enabled),
    )
    this._segments = this._segments.map((segment) =>
      segment.set(
        'tagged',
        !this.hasSegmentTagProjectionEnabled(segment.toJS()),
      ),
    )
  },
  /**
   *
   * @param current_sid
   * @param status
   * status values:
   * null|undefined|false NEXT WITHOUT CHECK STATUS
   * APPROVED
   * DRAFT
   * FIXED
   * NEW
   * REBUTTED
   * REJECTED
   * TRANSLATED
   * UNTRANSLATED | is draft or new
   * @param revisionNumber
   * @param autopropagated
   * @param alsoMutedSegment
   */
  getNextSegment({
    current_sid = null,
    status = null,
    revisionNumber = null,
    autopropagated = false,
    alsoMutedSegment = false,
  } = {}) {
    let currentSegment = this.getCurrentSegment()
    if (!current_sid && !currentSegment) return null
    current_sid = !current_sid ? currentSegment.sid : current_sid
    let result,
      currentFind = false
    this._segments.forEach((segment) => {
      if (isUndefined(result)) {
        if (currentFind || current_sid === -1) {
          if (segment.get('readonly') === 'true') {
            return false
          } else if (
            status === SEGMENTS_STATUS.UNTRANSLATED &&
            (segment.get('status').toUpperCase() === SEGMENTS_STATUS.DRAFT ||
              segment.get('status').toUpperCase() === SEGMENTS_STATUS.NEW ||
              (autopropagated &&
                segment.get('status').toUpperCase() ===
                  SEGMENTS_STATUS.TRANSLATED &&
                segment.get('autopropagated_from') != 0)) &&
            (alsoMutedSegment || (!alsoMutedSegment && !segment.get('muted')))
          ) {
            result = segment.toJS()
            return false
          } else if (status === SEGMENTS_STATUS.UNAPPROVED && revisionNumber) {
            // Second pass
            if (
              ((segment.get('status').toUpperCase() ===
                SEGMENTS_STATUS.APPROVED ||
                segment.get('status').toUpperCase() ===
                  SEGMENTS_STATUS.APPROVED2 ||
                segment.get('status').toUpperCase() ===
                  SEGMENTS_STATUS.TRANSLATED) &&
                segment.get('revision_number') === revisionNumber) ||
              (autopropagated &&
                segment.get('status').toUpperCase() ===
                  SEGMENTS_STATUS.APPROVED &&
                segment.get('autopropagated_from') != 0 &&
                segment.get('revision_number') !== revisionNumber)
            ) {
              result = segment.toJS()
              return false
            }
          } else if (
            ((status && segment.get('status').toUpperCase() === status) ||
              !status) &&
            (alsoMutedSegment || (!alsoMutedSegment && !segment.get('muted')))
          ) {
            result = segment.toJS()
            return false
          }
        }
        if (segment.get('sid') === current_sid) {
          currentFind = true
        }
      } else {
        return null
      }
    })
    return result
  },
  getNextUntranslatedSegmentId() {
    let current = this.getCurrentSegment()
    current = current || this._segments.get(0)
    if (current) {
      let next = this.getNextSegment({
        current_sid: current.sid,
        status: SEGMENTS_STATUS.UNTRANSLATED,
        autopropagated: true,
      })
      return next ? next.sid : this.nextUntranslatedFromServer
    }
    return undefined
  },
  getPrevSegment(sid, alsoMutedSegments) {
    let currentSegment = this.getCurrentSegment()
    if (!sid && !currentSegment) return null
    sid = !sid ? this.getCurrentSegment().sid : sid
    var index = this.getSegmentIndex(sid)
    let segment = index > 0 ? this._segments.get(index - 1).toJS() : null
    if (
      (segment && !alsoMutedSegments && !segment.muted) ||
      !segment ||
      (segment && alsoMutedSegments)
    ) {
      return segment
    }
    return this.getPrevSegment(segment.sid, alsoMutedSegments)
  },
  getSegmentByIdToJS(sid) {
    let segment = this._segments.find(function (seg) {
      return seg.get('sid') == sid || seg.get('original_sid') === sid
    })
    return segment ? segment.toJS() : null
  },

  segmentScrollableToCenter(sid) {
    //If a segment is in the last 5 segment loaded in the UI is scrollable
    let index = this.getSegmentIndex(sid)
    return index !== -1 && this._segments.size - 5 > index
  },

  getSegmentsSplitGroup(sid) {
    let segments = this._segments.filter(function (seg) {
      return seg.get('original_sid') == sid
    })
    return segments ? segments.toJS() : null
  },

  getAllSegments: function () {
    var result = []
    $.each(this._segments, function (key, value) {
      result = result.concat(value.toJS())
    })
    return result
  },
  getSegmentById(sid) {
    return this._segments.find(function (seg) {
      return seg.get('sid') == sid
    })
  },
  getSegmentIndex(sid) {
    const index = this._segments.findIndex(function (segment) {
      if (sid.toString().indexOf('-') === -1) {
        return parseInt(segment.get('sid')) === parseInt(sid)
      } else {
        return segment.get('sid') === sid
      }
    })
    return index
  },
  getLastSegmentId() {
    return this._segments?.last()?.get('sid')
  },
  getFirstSegmentId() {
    return this._segments?.first()?.get('sid')
  },
  getCurrentSegment: function () {
    let current = null,
      tmpCurrent = null
    tmpCurrent = this._segments.find((segment) => {
      return segment.get('opened') === true
    })
    if (tmpCurrent) {
      current = Object.assign({}, tmpCurrent.toJS())
    }
    return current
  },
  getCurrentSegmentId: function () {
    let current = this.getCurrentSegment()
    if (current) {
      return current.sid
    }
    return undefined
  },
  getSegmentsInPropagation(hash, isReview) {
    let reviewStatus = [
      'DRAFT',
      'NEW',
      'REBUTTED',
      'REJECTED',
      'TRANSLATED',
      'APPROVED',
    ]
    let translateStatus = ['DRAFT', 'NEW', 'REBUTTED', 'REJECTED', 'TRANSLATED']
    return this._segments
      .filter((segment) => {
        if (
          isReview &&
          reviewStatus.indexOf(segment.get('status').toUpperCase() > -1)
        ) {
          return segment.get('segment_hash') === hash
        } else if (!isReview && translateStatus.indexOf(segment.status)) {
          return segment.get('segment_hash') === hash
        }
        return false
      })
      .toJS()
  },
  getSegmentsInSplit(sid) {
    return this._segments
      .filter((segment) => {
        return segment.get('original_sid') === sid
      })
      .toJS()
  },
  getSegmentChoosenContribution(sid) {
    const seg = this.getSegmentById(sid)
    const currentIndex = seg.get('choosenSuggestionIndex')
    const currentMatch = seg
      .get('contributions')
      ?.get('matches')
      ?.get(currentIndex - 1)

    return currentMatch?.toJS()
  },
  getGlobalWarnings() {
    return this._globalWarnings
  },
  isSidePanelToOpen: function () {
    const commentOpen = this._segments.findIndex(
      (segment) => segment.get('openComments') === true,
    )
    const issueOpen = this._segments.findIndex(
      (segment) => segment.get('openIssues') === true,
    )
    return commentOpen !== -1 || issueOpen !== -1
  },
  copyFragmentToClipboard: function (fragment, plainText) {
    this.clipboardFragment = fragment
    this.clipboardPlainText = plainText
  },
  getFragmentFromClipboard: function () {
    const fragment = this.clipboardFragment
    const plainText = this.clipboardPlainText
    return {
      fragment,
      plainText,
    }
  },
  emitChange: function () {
    this.emit.apply(this, arguments)
  },
  getAiSuggestion: (sid) =>
    SegmentStore._aiSuggestions.find((item) => item.sid === sid),
  setAiSuggestion: ({sid, suggestion}) => {
    const MAX_ITEMS = 10

    const filteredWithoutCurrent = SegmentStore._aiSuggestions.filter(
      (item) => item.sid !== sid,
    )
    const difference = filteredWithoutCurrent.length - MAX_ITEMS
    const update =
      difference > 0
        ? filteredWithoutCurrent.slice(difference)
        : filteredWithoutCurrent
    SegmentStore._aiSuggestions = [...update, {sid, suggestion}]
  },
  setSegmentCharactersCounter: function (sid, counter) {
    const index = this.getSegmentIndex(sid)
    if (index === -1) return
    this._segments = this._segments.setIn([index, 'charactersCounter'], counter)
  },
})

// Register callback to handle all updates
AppDispatcher.register(function (action) {
  switch (action.actionType) {
    case SegmentConstants.RENDER_SEGMENTS:
      SegmentStore.updateAll(action.segments)
      if (action.idToOpen) {
        SegmentStore.openSegment(action.idToOpen)
        SegmentStore.emitChange(SegmentConstants.OPEN_SEGMENT, action.idToOpen)
      }
      SegmentStore.emitChange(action.actionType, SegmentStore._segments)
      if (SegmentStore.searchOccurrences.length > 0) {
        // Search Active
        SegmentStore.emitChange(SegmentConstants.UPDATE_SEARCH)
      }
      break
    case SegmentConstants.SET_OPEN_SEGMENT:
      SegmentStore.openSegment(action.sid)
      SegmentStore.closeSegmentsSplit()
      SegmentStore.emitChange(
        SegmentConstants.RENDER_SEGMENTS,
        SegmentStore._segments,
      )
      break
    case SegmentConstants.OPEN_SEGMENT:
      SegmentStore.openSegment(action.sid)
      SegmentStore.emitChange(
        SegmentConstants.OPEN_SEGMENT,
        action.sid,
        action.wasOriginatedFromBrowserHistory,
      )
      // SegmentStore.emitChange(SegmentConstants.SCROLL_TO_SEGMENT, action.sid);
      break
    case SegmentConstants.SELECT_SEGMENT: {
      let idToScroll
      if (action.direction === 'next') {
        idToScroll = SegmentStore.selectNextSegment(action.sid)
      } else {
        idToScroll = SegmentStore.selectPrevSegment(action.sid)
      }
      SegmentStore.emitChange(
        SegmentConstants.RENDER_SEGMENTS,
        SegmentStore._segments,
      )
      if (idToScroll) {
        SegmentStore.emitChange(
          SegmentConstants.SCROLL_TO_SELECTED_SEGMENT,
          idToScroll,
        )
      }
      break
    }
    case SegmentConstants.CLOSE_SEGMENT:
      SegmentStore.closeSegments(action.sid)
      SegmentStore.emitChange(
        SegmentConstants.RENDER_SEGMENTS,
        SegmentStore._segments,
      )
      break
    case SegmentConstants.ADD_SEGMENTS:
      SegmentStore.updateAll(action.segments, action.where)
      if (SegmentStore._segments.size)
        SegmentStore.emitChange(SegmentConstants.FREEZING_SEGMENTS, false)
      SegmentStore.emitChange(
        SegmentConstants.RENDER_SEGMENTS,
        SegmentStore._segments,
      )
      if (SegmentStore.searchOccurrences.length > 0) {
        // Search Active
        SegmentStore.emitChange(SegmentConstants.UPDATE_SEARCH)
      }
      break
    case SegmentConstants.SCROLL_TO_SEGMENT:
      SegmentStore.emitChange(action.actionType, action.sid)
      break
    case SegmentConstants.ADD_SEGMENT_CLASS:
      SegmentStore.emitChange(action.actionType, action.id, action.newClass)
      break
    case SegmentConstants.REMOVE_SEGMENT_CLASS:
      SegmentStore.emitChange(action.actionType, action.id, action.className)
      break
    case SegmentConstants.SET_SEGMENT_STATUS:
      SegmentStore.setStatus(action.id, action.fid, action.status)
      // SegmentStore.emitChange(SegmentConstants.SET_SEGMENT_STATUS, action.id, action.status);
      SegmentStore.emitChange(
        SegmentConstants.RENDER_SEGMENTS,
        SegmentStore._segments,
      )
      break
    case SegmentConstants.UPDATE_ALL_SEGMENTS:
      SegmentStore.emitChange(SegmentConstants.UPDATE_ALL_SEGMENTS)
      break
    case SegmentConstants.SET_SEGMENT_HEADER:
      SegmentStore.setSuggestionMatch(action.id, action.fid, action.perc)
      SegmentStore.emitChange(
        SegmentConstants.SET_SEGMENT_PROPAGATION,
        action.id,
        false,
      )
      SegmentStore.emitChange(
        action.actionType,
        action.id,
        action.perc,
        action.className,
        action.createdBy,
      )
      break
    case SegmentConstants.HIDE_SEGMENT_HEADER:
      SegmentStore.emitChange(
        SegmentConstants.SET_SEGMENT_PROPAGATION,
        action.id,
        false,
      )
      SegmentStore.emitChange(action.actionType, action.id, action.fid)
      break
    case SegmentConstants.SET_SEGMENT_PROPAGATION:
      SegmentStore.setPropagation(
        action.id,
        action.fid,
        action.propagation,
        action.from,
      )
      SegmentStore.emitChange(action.actionType, action.id, action.propagation)
      break
    case SegmentConstants.SET_SEGMENT_ORIGINAL_TRANSLATION:
      SegmentStore.updateOriginalTranslation(
        action.id,
        action.originalTranslation,
      )
      break
    case SegmentConstants.REPLACE_TRANSLATION:
      SegmentStore.replaceTranslation(action.id, action.translation)
      SegmentStore.emitChange(action.actionType, action.id, action.translation)
      break
    case SegmentConstants.UPDATE_TRANSLATION:
      SegmentStore.updateTranslation(
        action.id,
        action.translation,
        action.decodedTranslation,
        action.tagMap,
        action.missingTagsInTarget,
        action.lxqDecodedTranslation,
      )
      SegmentStore.emitChange(
        SegmentConstants.RENDER_SEGMENTS,
        SegmentStore._segments,
      )
      break
    case SegmentConstants.UPDATE_SOURCE:
      SegmentStore.updateSource(
        action.id,
        action.source,
        action.decodedSource,
        action.tagMap,
        action.lxqDecodedSource,
      )
      SegmentStore.emitChange(
        SegmentConstants.RENDER_SEGMENTS,
        SegmentStore._segments,
      )
      break
    case SegmentConstants.MODIFIED_TRANSLATION:
      SegmentStore.modifiedTranslation(action.sid, action.status)
      SegmentStore.emitChange(
        SegmentConstants.RENDER_SEGMENTS,
        SegmentStore._segments,
      )
      break
    case SegmentConstants.LOCK_EDIT_AREA:
      SegmentStore.lockUnlockEditArea(action.id, action.fid)
      SegmentStore.emitChange(
        SegmentConstants.RENDER_SEGMENTS,
        SegmentStore._segments,
      )
      break
    case SegmentConstants.REGISTER_TAB:
      SegmentStore.setConfigTabs(action.tab, action.visible, action.open)
      SegmentStore.emitChange(
        action.actionType,
        action.tab,
        SegmentStore._footerTabsConfig.toJS(),
      )
      break
    case SegmentConstants.SET_DEFAULT_TAB:
      SegmentStore.setConfigTabs(action.tabName, true, true)
      SegmentStore.emitChange(
        SegmentConstants.RENDER_SEGMENTS,
        SegmentStore._segments,
        action.fid,
      )
      break
    case SegmentConstants.MODIFY_TAB_VISIBILITY:
      SegmentStore.emitChange(action.actionType, action.tabName, action.visible)
      break
    case SegmentConstants.SHOW_FOOTER_MESSAGE:
      SegmentStore.emitChange(action.actionType, action.sid, action.message)
      break
    case SegmentConstants.SET_CONTRIBUTIONS:
      SegmentStore.setContributionsToCache(
        action.sid,
        action.matches,
        action.errors,
      )
      SegmentStore.emitChange(
        SegmentConstants.RENDER_SEGMENTS,
        SegmentStore._segments,
      )
      break
    case SegmentConstants.SET_CL_CONTRIBUTIONS:
      SegmentStore.setCrossLanguageContributionsToCache(
        action.sid,
        action.fid,
        action.matches,
        action.errors,
      )
      SegmentStore.emitChange(
        SegmentConstants.RENDER_SEGMENTS,
        SegmentStore._segments,
        action.fid,
      )
      break
    case SegmentConstants.SET_ALTERNATIVES:
      SegmentStore.setAlternatives(action.sid, action.alternatives)
      SegmentStore.emitChange(
        SegmentConstants.RENDER_SEGMENTS,
        SegmentStore._segments,
        action.fid,
      )
      break
    case SegmentConstants.CHOOSE_CONTRIBUTION:
      SegmentStore.emitChange(action.actionType, action.sid, action.index)
      break
    case SegmentConstants.DELETE_CONTRIBUTION:
      SegmentStore.deleteContribution(action.sid, action.matchId)
      SegmentStore.emitChange(
        SegmentConstants.RENDER_SEGMENTS,
        SegmentStore._segments,
        action.fid,
      )
      break
    case SegmentConstants.SET_GLOSSARY_TO_CACHE:
      SegmentStore.setGlossaryToCache(action.sid, action.glossary)
      SegmentStore.emitChange(
        SegmentConstants.RENDER_SEGMENTS,
        SegmentStore._segments,
        action.fid,
      )
      SegmentStore.emitChange(action.actionType, action.sid)
      break
    case SegmentConstants.SET_GLOSSARY_TO_CACHE_BY_SEARCH:
      SegmentStore.setGlossarySearchToCache(action.sid, action.glossary)
      SegmentStore.emitChange(
        SegmentConstants.RENDER_SEGMENTS,
        SegmentStore._segments,
        action.fid,
      )
      SegmentStore.emitChange(action.actionType, action.sid)
      break
    case SegmentConstants.DELETE_FROM_GLOSSARY:
      SegmentStore.deleteFromGlossary(action.sid, action.term)
      SegmentStore.emitChange(
        SegmentConstants.RENDER_SEGMENTS,
        SegmentStore._segments,
        action.fid,
      )
      break
    case SegmentConstants.CHANGE_GLOSSARY:
      SegmentStore.addOrUpdateGlossaryItem(
        action.sid,
        normalizeSetUpdateGlossary(action.terms),
      )
      SegmentStore.emitChange(action.actionType)
      SegmentStore.emitChange(
        SegmentConstants.RENDER_SEGMENTS,
        SegmentStore._segments,
        action.fid,
      )
      break
    case SegmentConstants.ADD_GLOSSARY_ITEM:
      SegmentStore.addOrUpdateGlossaryItem(
        action.sid,
        normalizeSetUpdateGlossary(action.terms),
      )
      SegmentStore.emitChange(action.actionType)
      SegmentStore.emitChange(
        SegmentConstants.RENDER_SEGMENTS,
        SegmentStore._segments,
        action.fid,
      )
      break
    case SegmentConstants.ERROR_ADD_GLOSSARY_ITEM:
    case SegmentConstants.ERROR_DELETE_FROM_GLOSSARY:
    case SegmentConstants.ERROR_CHANGE_GLOSSARY:
      SegmentStore.emitChange(action.actionType, action.sid, action.error)
      break
    case EditAreaConstants.COPY_GLOSSARY_IN_EDIT_AREA:
      SegmentStore.emitChange(
        action.actionType,
        action.segment,
        action.glossaryTranslation,
      )
      break
    case SegmentConstants.CONCORDANCE_RESULT:
      SegmentStore.setConcordanceMatches(action.sid, action.matches)
      SegmentStore.emitChange(action.actionType, action.sid, action.matches)
      break
    case SegmentConstants.SET_SEGMENT_TAGGED:
      SegmentStore.setSegmentAsTagged(action.id, action.fid)
      SegmentStore.emitChange(
        SegmentConstants.RENDER_SEGMENTS,
        SegmentStore._segments,
        action.fid,
      )
      SegmentStore.emitChange(SegmentConstants.SET_SEGMENT_TAGGED, action.id)
      break
    case SegmentConstants.ADD_SEGMENT_VERSIONS_ISSUES: {
      let seg = SegmentStore.addSegmentVersions(action.sid, action.versions)
      if (seg) {
        SegmentStore.emitChange(action.actionType, action.sid, seg.toJS())
      }
      SegmentStore.emitChange(
        SegmentConstants.RENDER_SEGMENTS,
        SegmentStore._segments,
      )
      break
    }
    case SegmentConstants.ADD_SEGMENT_PRELOADED_ISSUES:
      each(action.versionsIssues, function (issues, segmentId) {
        SegmentStore.addSegmentPreloadedIssues(segmentId, issues)
      })
      SegmentStore.emitChange(
        SegmentConstants.RENDER_SEGMENTS,
        SegmentStore._segments,
        action.fid,
      )
      break
    case SegmentConstants.ADD_TAB_INDEX:
      SegmentStore.emitChange(
        action.actionType,
        action.sid,
        action.tab,
        action.data,
      )
      break
    case SegmentConstants.TOGGLE_SEGMENT_ON_BULK:
      SegmentStore.setToggleBulkOption(action.sid, action.fid)
      SegmentStore.emitChange(
        SegmentConstants.SET_BULK_SELECTION_SEGMENTS,
        SegmentStore.segmentsInBulk,
      )
      SegmentStore.emitChange(
        SegmentConstants.RENDER_SEGMENTS,
        SegmentStore._segments,
        action.fid,
      )
      break
    case SegmentConstants.REMOVE_SEGMENTS_ON_BULK:
      SegmentStore.removeBulkOption()
      SegmentStore.emitChange(SegmentConstants.REMOVE_SEGMENTS_ON_BULK, [])
      SegmentStore.emitChange(
        SegmentConstants.RENDER_SEGMENTS,
        SegmentStore._segments,
      )

      break
    case SegmentConstants.SET_BULK_SELECTION_INTERVAL:
      SegmentStore.setBulkSelectionInterval(action.from, action.to, action.fid)
      SegmentStore.emitChange(
        SegmentConstants.RENDER_SEGMENTS,
        SegmentStore._segments,
        action.fid,
      )
      SegmentStore.emitChange(
        SegmentConstants.SET_BULK_SELECTION_SEGMENTS,
        SegmentStore.segmentsInBulk,
      )
      break
    case SegmentConstants.SET_BULK_SELECTION_SEGMENTS:
      SegmentStore.setBulkSelectionSegments(action.segmentsArray)
      SegmentStore.emitChange(
        SegmentConstants.RENDER_SEGMENTS,
        SegmentStore._segments,
      )
      SegmentStore.emitChange(
        SegmentConstants.SET_BULK_SELECTION_SEGMENTS,
        SegmentStore.segmentsInBulk,
      )
      break
    case SegmentConstants.SET_UNLOCKED_SEGMENT:
      SegmentStore.setUnlockedSegment(action.sid, action.fid, action.unlocked)
      SegmentStore.emitChange(
        SegmentConstants.RENDER_SEGMENTS,
        SegmentStore._segments,
        action.fid,
      )
      break
    case SegmentConstants.SET_UNLOCKED_SEGMENTS:
      SegmentStore.unlockSegments(action.segments)
      SegmentStore.emitChange(
        SegmentConstants.RENDER_SEGMENTS,
        SegmentStore._segments,
      )
      break
    case SegmentConstants.SET_MUTED_SEGMENTS:
      SegmentStore.setMutedSegments(action.segmentsArray)
      SegmentStore.emitChange(
        SegmentConstants.RENDER_SEGMENTS,
        SegmentStore._segments,
      )
      break
    case SegmentConstants.REMOVE_MUTED_SEGMENTS:
      SegmentStore.removeAllMutedSegments()
      SegmentStore.emitChange(
        SegmentConstants.RENDER_SEGMENTS,
        SegmentStore._segments,
      )
      break
    case SegmentConstants.SET_SEGMENT_WARNINGS: // LOCAL
      SegmentStore.setSegmentWarnings(
        action.sid,
        action.warnings,
        action.tagMismatch,
      )
      SegmentStore.emitChange(
        SegmentConstants.RENDER_SEGMENTS,
        SegmentStore._segments,
      )
      SegmentStore.emitChange(SegmentConstants.SET_SEGMENT_WARNINGS, action.sid)
      break
    case SegmentConstants.UPDATE_GLOBAL_WARNINGS:
      SegmentStore.updateGlobalWarnings(action.warnings)
      SegmentStore.emitChange(action.actionType, SegmentStore._globalWarnings)
      break

    case SegmentConstants.QA_LEXIQA_ISSUES:
      SegmentStore.updateLexiqaWarnings(action.warnings)
      SegmentStore.emitChange(
        SegmentConstants.UPDATE_GLOBAL_WARNINGS,
        SegmentStore._globalWarnings,
      )
      SegmentStore.emitChange(
        SegmentConstants.RENDER_SEGMENTS,
        SegmentStore._segments,
      )
      break
    case SegmentConstants.OPEN_ISSUES_PANEL:
      SegmentStore.openSegmentIssuePanel(action.data.sid)
      SegmentStore.emitChange(
        SegmentConstants.RENDER_SEGMENTS,
        SegmentStore._segments,
      )
      SegmentStore.emitChange(action.actionType, action.data)
      if (SegmentStore.isSidePanelToOpen() && !SegmentStore.sideOpen) {
        SegmentStore.openSide()
        SegmentStore.emitChange(
          SegmentConstants.OPEN_SIDE,
          SegmentStore._segments,
        )
      }
      break
    case SegmentConstants.CLOSE_ISSUES_PANEL:
      SegmentStore.closeSegmentIssuePanel()
      SegmentStore.emitChange(
        SegmentConstants.RENDER_SEGMENTS,
        SegmentStore._segments,
      )
      SegmentStore.emitChange(action.actionType)
      if (!SegmentStore.isSidePanelToOpen() && SegmentStore.sideOpen) {
        SegmentStore.closeSide()
        SegmentStore.emitChange(
          SegmentConstants.CLOSE_SIDE,
          SegmentStore._segments,
        )
      }
      break
    case SegmentConstants.CLOSE_SIDE:
      SegmentStore.closeSegmentIssuePanel()
      SegmentStore.closeSegmentComments()
      SegmentStore.emitChange(
        SegmentConstants.RENDER_SEGMENTS,
        SegmentStore._segments,
      )
      SegmentStore.emitChange(action.actionType)
      if (!SegmentStore.isSidePanelToOpen() && SegmentStore.sideOpen) {
        SegmentStore.closeSide()
        SegmentStore.emitChange(
          SegmentConstants.CLOSE_SIDE,
          SegmentStore._segments,
        )
      }
      break
    case SegmentConstants.OPEN_SIDE:
      if (SegmentStore.isSidePanelToOpen() && !SegmentStore.sideOpen) {
        SegmentStore.openSide()
        SegmentStore.emitChange(
          SegmentConstants.OPEN_SIDE,
          SegmentStore._segments,
        )
      }
      break
    case SegmentConstants.OPEN_COMMENTS:
      SegmentStore.openSegmentComments(action.sid)
      SegmentStore.emitChange(
        SegmentConstants.RENDER_SEGMENTS,
        SegmentStore._segments,
      )
      if (SegmentStore.isSidePanelToOpen() && !SegmentStore.sideOpen) {
        SegmentStore.openSide()
        SegmentStore.emitChange(
          SegmentConstants.OPEN_SIDE,
          SegmentStore._segments,
        )
      }
      break
    case SegmentConstants.CLOSE_COMMENTS:
      SegmentStore.closeSegmentComments()
      if (!SegmentStore.isSidePanelToOpen() && SegmentStore.sideOpen) {
        SegmentStore.closeSide()
        SegmentStore.emitChange(
          SegmentConstants.CLOSE_SIDE,
          SegmentStore._segments,
        )
      }
      SegmentStore.emitChange(
        SegmentConstants.RENDER_SEGMENTS,
        SegmentStore._segments,
      )
      break
    case SegmentConstants.OPEN_SPLIT_SEGMENT:
      SegmentStore.openSegmentSplit(action.sid)
      SegmentStore.emitChange(
        SegmentConstants.RENDER_SEGMENTS,
        SegmentStore._segments,
      )
      break
    case SegmentConstants.CLOSE_SPLIT_SEGMENT:
      SegmentStore.closeSegmentsSplit()
      SegmentStore.emitChange(
        SegmentConstants.RENDER_SEGMENTS,
        SegmentStore._segments,
      )
      SegmentStore.emitChange(SegmentConstants.CLOSE_SPLIT_SEGMENT)
      break
    case SegmentConstants.SET_CHOOSEN_SUGGESTION:
      SegmentStore.setChoosenSuggestion(action.sid, action.index)
      break
    case SegmentConstants.SET_QA_CHECK:
      SegmentStore.setQACheck(action.sid, action.data)
      SegmentStore.emitChange(
        SegmentConstants.RENDER_SEGMENTS,
        SegmentStore._segments,
      )
      break
    case SegmentConstants.ADD_LXQ_HIGHLIGHT:
      SegmentStore.addLexiqaHighlight(action.sid, action.matches, action.type)
      SegmentStore.emitChange(
        SegmentConstants.RENDER_SEGMENTS,
        SegmentStore._segments,
      )
      break
    case SegmentConstants.ADD_SEARCH_RESULTS:
      SegmentStore.addSearchResult(
        action.occurrencesList,
        action.searchResultsDictionary,
        action.currentIndex,
        action.text,
      )
      SegmentStore.emitChange(
        SegmentConstants.RENDER_SEGMENTS,
        SegmentStore._segments,
      )
      action.occurrencesList
        .filter((v, i, a) => a.indexOf(v) === i)
        .forEach((sid) => {
          SegmentStore.emitChange(SegmentConstants.ADD_SEARCH_RESULTS, sid)
        })
      break
    case SegmentConstants.REMOVE_SEARCH_RESULTS:
      SegmentStore.removeSearchResults()
      SegmentStore.emitChange(
        SegmentConstants.RENDER_SEGMENTS,
        SegmentStore._segments,
      )
      SegmentStore.emitChange(
        SegmentConstants.REMOVE_SEARCH_RESULTS,
        SegmentStore._segments,
      )
      break
    case SegmentConstants.ADD_CURRENT_SEARCH: {
      let currentSegment = SegmentStore.addCurrentSearchSegment(
        action.currentIndex,
      )
      SegmentStore.emitChange(
        SegmentConstants.RENDER_SEGMENTS,
        SegmentStore._segments,
      )
      if (currentSegment) {
        SegmentStore.emitChange(
          SegmentConstants.FORCE_UPDATE_SEGMENT,
          currentSegment.get('sid'),
        )
        SegmentStore.emitChange(
          SegmentConstants.ADD_CURRENT_SEARCH,
          currentSegment.get('sid'),
          currentSegment.get('currentInSearchIndex'),
        )
      }
      break
    }
    case EditAreaConstants.REPLACE_SEARCH_RESULTS:
      SegmentStore.emitChange(
        EditAreaConstants.REPLACE_SEARCH_RESULTS,
        action.text,
      )
      break
    case EditAreaConstants.COPY_FRAGMENT_TO_CLIPBOARD:
      SegmentStore.copyFragmentToClipboard(action.fragment, action.plainText)
      break
    case SegmentConstants.SET_GUESS_TAGS: {
      SegmentStore.setTagProjectionStatus(action.enabled)
      SegmentStore.emitChange(
        SegmentConstants.RENDER_SEGMENTS,
        SegmentStore._segments,
      )
      const current = SegmentStore.getCurrentSegment()
      if (current)
        SegmentStore.emitChange(
          SegmentConstants.SET_SEGMENT_TAGGED,
          current.sid,
        )
      break
    }
    case EditAreaConstants.EDIT_AREA_CHANGED:
      SegmentStore.emitChange(
        EditAreaConstants.EDIT_AREA_CHANGED,
        action.sid,
        action.isTarget,
      )
      break
    case SegmentConstants.HIGHLIGHT_TAGS:
      SegmentStore.emitChange(
        SegmentConstants.HIGHLIGHT_TAGS,
        action.tagId,
        action.tagPlaceholder,
        action.entityKey,
        action.isTarget,
      )
      break
    case SegmentConstants.SET_SEGMENT_SAVING:
      SegmentStore.setSegmentSaving(action.sid, action.saving)
      SegmentStore.emitChange(
        SegmentConstants.RENDER_SEGMENTS,
        SegmentStore._segments,
      )
      break
    case SegmentConstants.CHARACTER_COUNTER:
      SegmentStore.setSegmentCharactersCounter(action.sid, action.counter)
      SegmentStore.emitChange(SegmentConstants.CHARACTER_COUNTER, {
        sid: action.sid,
        counter: action.counter,
        limit: action.limit,
      })
      break
    case SegmentConstants.GET_MORE_SEGMENTS:
      SegmentStore.emitChange(SegmentConstants.GET_MORE_SEGMENTS, action.where)
      break
    case SegmentConstants.REMOVE_ALL_SEGMENTS:
      SegmentStore.removeAllSegments()
      SegmentStore.emitChange(SegmentConstants.REMOVE_ALL_SEGMENTS)
      break
    case SegmentConstants.FREEZING_SEGMENTS:
      SegmentStore.emitChange(
        SegmentConstants.FREEZING_SEGMENTS,
        action.isFreezing,
      )
      break
    case SegmentConstants.HIGHLIGHT_GLOSSARY_TERM:
      SegmentStore.emitChange(SegmentConstants.HIGHLIGHT_GLOSSARY_TERM, {
        ...action,
      })
      break
    case SegmentConstants.HELP_AI_ASSISTANT:
      SegmentStore.helpAiAssistantWords = {...action}
      SegmentStore.emitChange(SegmentConstants.HELP_AI_ASSISTANT, {
        ...action,
      })
      break
    case SegmentConstants.AI_SUGGESTION:
      SegmentStore.setAiSuggestion({
        sid: action.sid,
        suggestion: action.suggestion,
        isCompleted: action.isCompleted,
        hasError: action.hasError,
      })
      SegmentStore.emitChange(SegmentConstants.AI_SUGGESTION, {
        ...action,
      })
      break
    case SegmentConstants.SET_IS_CURRENT_SEARCH_OCCURRENCE_TAG:
      SegmentStore.emitChange(
        SegmentConstants.SET_IS_CURRENT_SEARCH_OCCURRENCE_TAG,
        {
          ...action,
        },
      )
      break
    case SegmentConstants.OPEN_GLOSSARY_FORM_PREFILL:
      SegmentStore.emitChange(SegmentConstants.OPEN_GLOSSARY_FORM_PREFILL, {
        ...action,
      })
      break
    case SegmentConstants.FOCUS_TAGS:
      SegmentStore.emitChange(SegmentConstants.FOCUS_TAGS, {
        ...action,
      })
      break
    case SegmentConstants.REFRESH_TAG_MAP:
      SegmentStore.emitChange(SegmentConstants.REFRESH_TAG_MAP)
      SegmentStore.emitChange(
        SegmentConstants.RENDER_SEGMENTS,
        SegmentStore._segments,
      )
      break
    default:
      SegmentStore.emitChange(action.actionType, action.sid, action.data)
  }
})

export default SegmentStore
