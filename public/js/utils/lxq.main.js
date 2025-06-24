import {cloneDeep} from 'lodash/lang'
import {each} from 'lodash/collection'
import {merge} from 'lodash/object'
import {isUndefined} from 'lodash'
import $ from 'jquery'

import SegmentActions from '../actions/SegmentActions'
import {toggleTagLexica} from '../api/toggleTagLexica'
import {getLexiqaWarnings as getLexiqaWarningsApi} from '../api/getLexiqaWarnings'
import {lexiqaIgnoreError} from '../api/lexiqaIgnoreError'
import SegmentStore from '../stores/SegmentStore'
import {lexiqaTooltipwarnings} from '../api/lexiqaTooltipwarnings'
import UserStore from '../stores/UserStore'
import {getLexiqaQa} from '../api/getLexiqaQa'

const LXQ = {
  partnerid: config.lxq_partnerid,
  colors: {
    numbers: '#D08053',
    punctuation: '#3AB45F',
    spaces: '#3AB45F',
    urls: '#b8a300',
    spelling: '#b9a7d3',
    specialchardetect: '#38C0C5',
    mspolicheck: '#38C0C5',
    multiple: '#EA92B8',
    glossary: '#EA92B8',
    blacklist: '#EA92B8',
  },
  warningMessages: {
    u2: {t: 'email not found in source', s: 'email missing from target'},
    u1: {t: 'url not found in source', s: 'url missing from target'},
    n1: {t: 'index not found in source', s: 'index missing from target'},
    n2: {t: 'number not found in source', s: 'number missing from target'},
    n3: {
      t: 'phonenumber not found in source',
      s: 'phonenumber missing from target',
    },
    n4: {t: 'date not found in source', s: 'date missing from target'},
    s1: {
      t: 'placeholder not found in source',
      s: 'placeholder missing from target',
    },
    p1: {t: 'consecutive punctuation marks', s: ''},
    p2: {t: 'space before punctuation mark', s: ''},
    p2sub1: {t: 'space before punctuation mark missing', s: ''},
    p2sub2: {t: 'no space before opening parenthesis', s: ''},
    p3: {t: 'space after punctuation mark missing', s: ''},
    p3sub1: {t: 'no space after closing parenthesis', s: ''},
    p4: {
      t: 'trailing punctuation mark different from source',
      s: 'trailing punctuation mark different from target',
    },
    l2: {
      t: 'leading capitalization different from source',
      s: 'leading capitalization different from target',
    },
    p6: {t: 'should be capitalized after punctuation mark', s: ''},
    l1: {t: 'repeated word', s: ''},
    c1: {t: 'multiple spaces', s: ''},
    c2: {t: 'segment starting with space', s: ''},
    c2sub1: {t: 'space found after opening bracket/parenthesis', s: ''},
    c3sub1: {t: 'space found before closing bracket/parenthesis', s: ''},
    c3: {t: 'space found at the end of segment', s: ''},
    s2: {t: 'foreign character', s: ''},
    s3: {t: 'bracket mismatch', s: ''},
    s3sub1: {t: 'bracket not closed', s: ''},
    s3sub2: {t: 'bracket not opened', s: ''},
    s4: {
      t: 'character missing from source',
      s: 'character missing from target',
    },
    s5: {t: 'currency mismatch', s: 'currency mismatch'},
    default: {t: 'not found in source', s: 'missing from target'},
  },
  modulesNoHighlight: ['b1g', 'g1g', 'g2g', 'g3g'],
  init: () => {
    LXQ.initialized = true
    let globalReceived = false
    if (config.lxq_license) {
      LXQ.settings = {
        licenseKey: config.lxq_license,
        partnerId: config.lxq_partnerid,
        lxqServer: config.lexiqaServer,
        projectId: config.id_job + '-' + config.password,
      }
    }
    /*
     * Add lexiQA event handlers for warnings events
     */
    document.addEventListener('getWarning:local:success', (event) => {
      const data = event.detail
      LXQ.doLexiQA(data.segment, false, function () {})
    })
    /* Invoked when page loads */
    document.addEventListener('getWarning:global:success', function () {
      if (globalReceived) {
        return
      }

      LXQ.getLexiqaWarnings(function () {
        globalReceived = true
      })
    })

    /* invoked when segment is completed (translated clicked)*/
    document.addEventListener('setTranslation:success', (event) => {
      const data = event.detail
      LXQ.doLexiQA(data.segment, true, null)
    })

    /* invoked when more segments are loaded...*/
    document.addEventListener('segmentsAdded', (event) => {
      const data = event.detail
      globalReceived = false
      console.log('[LEXIQA] got segmentsAdded ')
      each(data.resp, function (file) {
        if (
          file.segments &&
          Object.hasOwn(LXQ, 'lexiqaData') &&
          Object.hasOwn(LXQ.lexiqaData, 'lexiqaWarnings')
        ) {
          each(file.segments, function (segment) {
            if (Object.hasOwn(LXQ.lexiqaData.lexiqaWarnings, segment.sid)) {
              // console.log('in loadmore segments, segment: '+segment.sid+' already has qa info...');
              //clean up and redo powertip on any glossaries/blacklists
              LXQ.redoHighlighting(segment.sid, true)
              LXQ.redoHighlighting(segment.sid, false)
            }
          })
        }
      })
    })
    lexiqaTooltipwarnings().then((data) => {
      LXQ.warningMessages = data
      LXQ.modulesNoHighlight = Object.entries(data)
        .filter(([key]) => key[key.length - 1] === 'g')
        .map(([key]) => key)
    })
  },
  enabled: function ({lexiqa} = {}) {
    return (
      LXQ.checkCanActivate() &&
      (lexiqa ? lexiqa : UserStore.getUserMetadata()?.lexiqa) === 1
    )
  },
  enable: function () {
    toggleTagLexica({enabled: true}).then(() => {
      if (!LXQ.initialized) {
        LXQ.init()
      } else {
        SegmentActions.qaComponentsetLxqIssues(LXQ.lexiqaData.segments)
      }
      SegmentActions.getSegmentsQa(SegmentStore.getCurrentSegment())
    })
  },
  disable: function () {
    toggleTagLexica({enabled: false}).then(() => {
      SegmentActions.qaComponentsetLxqIssues([])
    })
  },
  checkCanActivate: function () {
    if (isUndefined(this.canActivate)) {
      this.canActivate =
        config.lexiqa_languages.indexOf(config.source_rfc) > -1 &&
        config.lexiqa_languages.indexOf(config.target_rfc) > -1
    }
    return this.canActivate
  },
  lexiqaData: {
    lexiqaWarnings: {},
    enableHighlighting: true,
    lexiqaFetching: false,
    segments: [],
    segmentsInfo: {},
  },

  doLexiQA: function (segment, isSegmentCompleted, callback) {
    if (!LXQ.enabled()) {
      if (callback !== undefined && typeof callback === 'function') {
        callback()
      }
      return
    }
    let id_segment = segment.sid

    const segObj = segment

    if (!segObj) return

    const sourcetext = segObj.lxqDecodedSource
    const translation = segObj.lxqDecodedTranslation

    const returnUrl = window.location.href.split('#')[0] + '#' + id_segment
    const data = {
      sourcelanguage: config.source_rfc,
      targetlanguage: config.target_rfc,
      sourcetext: sourcetext,
      targettext: translation,
      returnUrl: returnUrl,
      segmentId: id_segment,
      partnerId: LXQ.partnerid,
      projectId: LXQ.projectid,
      isSegmentCompleted: isSegmentCompleted,
      responseMode: 'includeQAResults',
    }
    getLexiqaQa({params: data, settings: LXQ.settings})
      .then((result) => {
        let noVisibleErrorsFound = false

        if (Object.hasOwn(result, 'qaData') && result.qaData.length > 0) {
          //highlight the segments
          // source_val = $( ".source", segment ).html();

          const highlights = {}
          const errorsMap = {
            numbers: [],
            punctuation: [],
            spaces: [],
            urls: [],
            spelling: [],
            specialchardetect: [],
            mspolicheck: [],
            glossary: [],
            blacklist: [],
          }
          const newWarnings = {}
          newWarnings[id_segment] = {}
          result.qaData.forEach(function (qadata) {
            if (
              Object.hasOwn(LXQ.lexiqaData.lexiqaWarnings, id_segment) &&
              Object.hasOwn(
                LXQ.lexiqaData.lexiqaWarnings[id_segment],
                qadata.errorid,
              )
            ) {
              //this error is already here, update it
              //basically do thing because each error is unique....
              qadata.ignored =
                LXQ.lexiqaData.lexiqaWarnings[id_segment][
                  qadata.errorid
                ].ignored
            }
            newWarnings[id_segment][qadata.errorid] = qadata
            if (!qadata.ignored) {
              qadata.color = LXQ.colors[qadata.category]
              const category = qadata.category
              if (qadata.insource) {
                highlights.source = highlights.source
                  ? highlights.source
                  : cloneDeep(errorsMap)
                highlights.source[category].push(qadata)
              } else {
                highlights.target = highlights.target
                  ? highlights.target
                  : cloneDeep(errorsMap)
                highlights.target[category].push(qadata)
              }
            }
          })
          LXQ.lexiqaData.lexiqaWarnings[id_segment] = newWarnings[id_segment]
          //do something here -- enable qa errors
          if (LXQ.lexiqaData.segments.indexOf(id_segment) < 0) {
            LXQ.lexiqaData.segments.push(id_segment)
            LXQ.updateWarningsUI()
          }
          SegmentActions.addLexiqaHighlight(id_segment, highlights)

          if (!(LXQ.getVisibleWarningsCountForSegment(id_segment) > 0)) {
            noVisibleErrorsFound = true
          }
        } else {
          //do something else
          noVisibleErrorsFound = true

          if (callback != null) callback()
        }
        if (noVisibleErrorsFound) {
          LXQ.lxqRemoveSegmentFromWarningList(id_segment)
        }
      })
      .finally(() => {
        if (callback != null) callback()
      })
  },
  lxqRemoveSegmentFromWarningList: function (id_segment) {
    SegmentActions.addLexiqaHighlight(id_segment, {})
    LXQ.removeSegmentWarning(id_segment)
  },
  getLexiqaWarnings: function (callback) {
    if (!LXQ.enabled()) {
      if (callback) callback()
      return
    }
    //FOTD
    LXQ.lexiqaData.lexiqaFetching = true
    getLexiqaWarningsApi({partnerId: LXQ.partnerid}).then((results) => {
      if (results.errors !== 0) {
        //only do something if there are errors in lexiqa server
        LXQ.lexiqaData.lexiqaWarnings = {}

        results.segments.forEach(function (element) {
          LXQ.lexiqaData.segments.push(element.segid)
          if (element.errornum === 0) {
            return
          }

          //highlight the respective segments here
          const highlights = {}
          const errorsMap = {
            numbers: [],
            punctuation: [],
            spaces: [],
            urls: [],
            spelling: [],
            specialchardetect: [],
            mspolicheck: [],
            glossary: [],
            blacklist: [],
          }

          let seg = SegmentStore.getSegmentByIdToJS(element.segid)
          if (!seg) return

          LXQ.lexiqaData.lexiqaWarnings[element.segid] = {}
          results.results[element.segid].forEach(function (qadata) {
            LXQ.lexiqaData.lexiqaWarnings[element.segid][qadata.errorid] =
              qadata

            if (!qadata.ignored) {
              qadata.color = LXQ.colors[qadata.category]
              if (qadata.insource) {
                highlights.source = highlights.source
                  ? highlights.source
                  : cloneDeep(errorsMap)
                highlights.source[qadata.category].push(qadata)
              } else {
                highlights.target = highlights.target
                  ? highlights.target
                  : cloneDeep(errorsMap)
                highlights.target[qadata.category].push(qadata)
              }
            }
          })
          if (!LXQ.getVisibleWarningsCountForSegment(element.segid) > 0) {
            LXQ.removeSegmentWarning(element.segid)
          }
          SegmentActions.addLexiqaHighlight(element.segid, highlights)
        })

        LXQ.updateWarningsUI()
      }

      if (LXQ.enabled()) {
        LXQ.doQAallSegments()
        //LXQ.refreshElements();
      }
      LXQ.lexiqaData.lexiqaFetching = false
      if (callback) callback()
    })
  },
  updateWarningsUI: function () {
    LXQ.lexiqaData.segments.sort()
    const segments = LXQ.lexiqaData.segments.filter(function (id_segment) {
      return Object.hasOwn(LXQ.lexiqaData.lexiqaWarnings, id_segment)
    })
    SegmentActions.qaComponentsetLxqIssues(segments)
  },
  removeSegmentWarning: function (idSegment) {
    let ind = LXQ.lexiqaData.segments.indexOf(idSegment)
    if (ind >= 0) {
      LXQ.lexiqaData.segments.splice(ind, 1)
      delete LXQ.lexiqaData.lexiqaWarnings[idSegment]
      this.updateWarningsUI()
    }
  },
  getRanges: (results, text, isSource) => {
    //var text = $(area).val();
    // var LTPLACEHOLDER = "##LESSTHAN##";
    // var GTPLACEHOLDER = "##GREATERTHAN##";
    const rangesIn = [
      {
        //color: '#D08053',
        ranges: results.numbers,
      },
      {
        //color: '#3AB45F',
        ranges: results.punctuation,
      },
      {
        //color: '#3AB45F',
        ranges: results.spaces,
      },
      {
        //color: '#38C0C5',
        ranges: results.specialchardetect,
      },
      {
        //color: '#38C0C5',
        ranges: results.mspolicheck,
      },
      {
        //color: '#b8a300',
        ranges: results.urls,
      },
      {
        //color: '#563d7c',
        ranges: results.spelling,
      },
      {
        //color: '#563d7c',
        ranges: results.glossary,
      },
      {
        //color: '#563d7c',
        ranges: results.blacklist,
      },
    ]

    const ranges = LXQ.cleanRanges(rangesIn)
    if (ranges == null) {
      //do nothing
      return {}
    }

    ranges.newout.map(function (range) {
      if (text && range.start !== range.end) {
        if (range.start < text.length && !range.ignore) {
          // var dataErrors = '',
          let multiple
          //calculate the color
          if (range.errors.length === 1) {
            range.color = ranges.out[range.errors[0]].color
            range.myClass =
              ranges.out[range.errors[0]].module +
              ' ' +
              ranges.out[range.errors[0]].module[0] +
              '0' //.p0 for instance
            // dataErrors = ranges.out[range.errors[0]].errorid
          } else {
            range.myClass = ''
            multiple = 0
            range.errors.forEach(function (element) {
              range.myClass +=
                ' ' +
                ranges.out[element].module +
                ' ' +
                ranges.out[element].module[0] +
                '0'
              // dataErrors += ' ' + ranges.out[element].errorid
              if (
                LXQ.modulesNoHighlight.indexOf(ranges.out[element].module) < 0
              )
                multiple++
            })
            range.color = LXQ.colors.multiple
            if (multiple > 1) range.myClass += ' m'
            range.myClass = range.myClass.trim()
          }

          if (!LXQ.lexiqaData.enableHighlighting) {
            range.myClass += ' lxq-invisible'
          } else {
            if (isSource) range.myClass += ' tooltipas'
            else range.myClass += ' tooltipa'
          }
        }
      }
      return range
    })

    return merge(ranges.out, ranges.newout)
  },
  isNumeric: (n) => {
    return !isNaN(parseFloat(n)) && isFinite(n)
  },
  cleanRanges: (ranges) => {
    const out = []
    if ($.isPlainObject(ranges) || LXQ.isNumeric(ranges[0])) {
      ranges = [ranges]
    }

    let i = 0,
      l = ranges.length
    for (; i < l; i++) {
      let range = ranges[i]

      if ($.isArray(range)) {
        out.push({
          color: range.color,
          start: range[0],
          end: range[1],
        })
      } else {
        if (range.ranges) {
          if ($.isPlainObject(range.ranges) || LXQ.isNumeric(range.ranges[0])) {
            range.ranges = [range.ranges]
          }

          let j = 0,
            m = range.ranges.length
          for (; j < m; j++) {
            if ($.isArray(range.ranges[j])) {
              out.push({
                color: range.color,
                class: range.class,
                start: range.ranges[j][0],
                end: range.ranges[j][1],
              })
            } else {
              if (range.ranges[j].length) {
                range.ranges[j].end =
                  range.ranges[j].start + range.ranges[j].length
              }
              out.push(range.ranges[j])
            }
          }
        } else {
          if (range.length) {
            range.end = range.start + range.length
          }
          out.push(range)
        }
      }
    }
    if (out.length === 0) {
      return null
    }
    out.sort(function (a, b) {
      if (a.end === b.end) {
        return a.start - b.start
      }
      return a.end - b.end
    })
    const textMaxHighlight = out[out.length - 1].end

    out.sort(function (a, b) {
      if (a.start === b.start) {
        return a.end - b.end
      }
      return a.start - b.start
    })
    const textMinHighlight = out[0].start
    let txt = new Array(textMaxHighlight - textMinHighlight + 1)
    for (let i = 0; i < txt.length; i++) txt[i] = []
    $.each(out, function (j, range) {
      let i
      if (range.ignore !== true) {
        //do not add the ignored errors
        //if (!(!isSegmentCompleted && (range.module=== 'c1'  || range.module=== 'c3'))) {
        //if (!(!isSegmentCompleted &&  range.module=== 'c3')) {
        if (!(range.module === 'c3')) {
          //if segment is not complete  - completely ignore doublespaces, becuase
          //they seriously break formating....
          for (i = range.start; i < range.end; i++) {
            txt[i - textMinHighlight].push(j)
          }
        }
      }
    })
    const newout = []
    let curitem = null
    for (let i = 0; i < txt.length; i++) {
      if (txt[i].length > 0) {
        //more than one errors - start multiple
        if (curitem == null) {
          curitem = {}
          curitem.start = i + textMinHighlight
          curitem.errors = txt[i].slice(0)
          curitem.ignore = false
        } else {
          //check if the errors are the same or not..
          let areErrorsSame = true
          if (curitem.errors.length === txt[i].length) {
            for (let j = 0; j < curitem.errors.length; j++) {
              if (curitem.errors[j] !== txt[i][j]) {
                areErrorsSame = false
                continue
              }
            }
          } else {
            areErrorsSame = false
          }
          if (!areErrorsSame) {
            curitem.end = i + textMinHighlight
            newout.push(curitem)
            //restart it!
            curitem = {}
            curitem.start = i + textMinHighlight
            curitem.errors = txt[i].slice(0)
          }
        }
      } else {
        if (curitem != null) {
          curitem.end = i + textMinHighlight
          newout.push(curitem)
          curitem = null
        }
      }
    }
    return {out: out, newout: newout}
  },
  buildTooltipMessages: (range, sid, isSource) => {
    let classList = range.myClass.split(/\s+/)
    let messages = []
    let errorList = range.errorid.split(/\s+/)
    let word
    $.each(classList, (j, cl) => {
      let txt = LXQ.getWarningForModule(cl, isSource)
      if (cl === 'g3g' && LXQ.lexiqaData.lexiqaWarnings[sid]) {
        //need to modify message with word.
        const ind = Math.floor(j / 2) //we aredding the x0 classes after each class..
        word = LXQ.lexiqaData.lexiqaWarnings[sid][errorList[ind]].msg
        txt = txt.replace('#xxx#', word)
      } else if (cl === 'o1' && LXQ.lexiqaData.lexiqaWarnings[sid]) {
        //need to modify message with word.
        const ind = Math.floor(j / 2) //we aredding the x0 classes after each class..
        word =
          LXQ.lexiqaData.lexiqaWarnings[sid][errorList[ind]].tootipExtraText
        txt = txt.replace('XXXX', word)
      }
      if (txt !== null && LXQ.lexiqaData.lexiqaWarnings[sid]) {
        messages.push({
          msg: txt,
          error: errorList[j],
        })
      }
    })
    return messages
  },
  ignoreError: (errorid) => {
    const splits = errorid.split(/_/g)
    const targetSeg = splits[1]
    const inSource = splits[splits.length - 1] === 's'
    //console.log('ignoring error with id: '+ errorid +' in segment: '+targetSeg);
    if (LXQ.lexiqaData.lexiqaWarnings[targetSeg]) {
      LXQ.lexiqaData.lexiqaWarnings[targetSeg][errorid].ignored = true
      LXQ.redoHighlighting(targetSeg, inSource)
      if (LXQ.getVisibleWarningsCountForSegment(targetSeg) <= 0) {
        //remove the segment from database/reduce the number count
        LXQ.lxqRemoveSegmentFromWarningList(targetSeg)
      }
      LXQ.postIgnoreError(errorid)
    }
  },

  redoHighlighting: (segmentId, insource) => {
    let highlights = {
      source: {
        numbers: [],
        punctuation: [],
        spaces: [],
        urls: [],
        spelling: [],
        specialchardetect: [],
        mspolicheck: [],
        glossary: [],
        blacklist: [],
      },
      target: {
        numbers: [],
        punctuation: [],
        spaces: [],
        urls: [],
        spelling: [],
        specialchardetect: [],
        mspolicheck: [],
        glossary: [],
        blacklist: [],
      },
    }
    $.each(LXQ.lexiqaData.lexiqaWarnings[segmentId], function (key, qadata) {
      if (!qadata.ignored)
        if (qadata.insource) {
          highlights.source[qadata.category].push(qadata)
        } else {
          highlights.target[qadata.category].push(qadata)
        }
    })
    SegmentActions.addLexiqaHighlight(segmentId, highlights)
  },
  postIgnoreError: function (errorid) {
    lexiqaIgnoreError({errorId: errorid})
  },

  getVisibleWarningsCountForSegment: function (segId) {
    if (!Object.hasOwn(LXQ.lexiqaData.lexiqaWarnings, segId)) return 0
    let count = 0
    $.each(LXQ.lexiqaData.lexiqaWarnings[segId], function (i, element) {
      if (
        (element.ignored === undefined || !element.ignored) &&
        element.module !== 'c3'
      )
        count++
    })
    return count
  },

  getWarningForModule: (module, isSource) => {
    if (Object.hasOwn(LXQ.warningMessages, module))
      return isSource
        ? LXQ.warningMessages[module].s
        : LXQ.warningMessages[module].t
    else return null
  },
  notCheckedSegments: [],
  //store the unchecked segments at startup
  doQAallSegments: () => {
    const segments = SegmentStore.getAllSegments()
    let notChecked = []
    segments.forEach((segment) => {
      const segId = segment.sid
      if (LXQ.lexiqaData.segments.indexOf(segId) < 0) {
        notChecked.push(segment)
      }
    })
    LXQ.notCheckedSegments = notChecked
    LXQ.checkNextUncheckedSegment()
  },
  checkNextUncheckedSegment: () => {
    if (!(LXQ.notCheckedSegments.length > 0)) return
    const segment = LXQ.notCheckedSegments.pop()
    if (!segment) return
    if (segment.translation) {
      // console.log('Requesting QA for: '+segment);
      LXQ.doLexiQA(segment, true, LXQ.checkNextUncheckedSegment)
    } else {
      LXQ.checkNextUncheckedSegment()
    }
  },
}

export default LXQ
