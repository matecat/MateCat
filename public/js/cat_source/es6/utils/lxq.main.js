import {cloneDeep} from 'lodash/lang'
import {each} from 'lodash/collection'
import {merge} from 'lodash/object'
import {isUndefined} from 'lodash'

import SegmentActions from '../actions/SegmentActions'
import {toggleTagLexica} from '../api/toggleTagLexica'
import {getLexiqaWarnings as getLexiqaWarningsApi} from '../api/getLexiqaWarnings'
import {lexiqaIgnoreError} from '../api/lexiqaIgnoreError'
import SegmentStore from '../stores/SegmentStore'
import {lexiqaTooltipwarnings} from '../api/lexiqaTooltipwarnings'
import CatToolActions from '../actions/CatToolActions'
import CommonUtils from './commonUtils'

const LXQ = {
  enabled: function () {
    return !!config.lxq_enabled
  },
  enable: function () {
    if (!config.lxq_enabled) {
      config.lxq_enabled = 1
      toggleTagLexica({enabled: true}).then(() => {
        if (!LXQ.initialized) {
          LXQ.init()
        } else {
          SegmentActions.qaComponentsetLxqIssues(LXQ.lexiqaData.segments)
        }
        CatToolActions.onRender()
        SegmentActions.getSegmentsQa(SegmentStore.getCurrentSegment())
      })
    }
  },
  disable: function () {
    if (config.lxq_enabled) {
      config.lxq_enabled = 0
      toggleTagLexica({enabled: false}).then(() => {
        CatToolActions.onRender()
        SegmentActions.qaComponentsetLxqIssues([])
      })
    }
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

    var segObj = segment

    if (!segObj) return

    var sourcetext = segObj.lxqDecodedSource
    var translation = segObj.lxqDecodedTranslation

    var returnUrl = window.location.href.split('#')[0] + '#' + id_segment
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
    $.lexiqaAuthenticator.doLexiQA(
      data,
      function (err, result) {
        if (!err) {
          var noVisibleErrorsFound = false

          if (result.hasOwnProperty('qaData') && result.qaData.length > 0) {
            //highlight the segments
            // source_val = $( ".source", segment ).html();

            var highlights = {}
            var errorsMap = {
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
            var newWarnings = {}
            newWarnings[id_segment] = {}
            result.qaData.forEach(function (qadata) {
              if (
                LXQ.lexiqaData.lexiqaWarnings.hasOwnProperty(id_segment) &&
                LXQ.lexiqaData.lexiqaWarnings[id_segment].hasOwnProperty(
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
                var category = qadata.category
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
        } //there was no error
        else {
          if (callback != null) callback()
        } //error in doQA
      }, //end lexiqaAuthenticator callback
    ) //end lexiqaAuthenticator.doLexiqa
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
      if (results.errors != 0) {
        //only do something if there are errors in lexiqa server
        LXQ.lexiqaData.lexiqaWarnings = {}

        results.segments.forEach(function (element) {
          LXQ.lexiqaData.segments.push(element.segid)
          if (element.errornum === 0) {
            return
          }

          //highlight the respective segments here
          var highlights = {}
          var errorsMap = {
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
    var segments = LXQ.lexiqaData.segments.filter(function (id_segment) {
      return LXQ.lexiqaData.lexiqaWarnings.hasOwnProperty(id_segment)
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
}

LXQ.init = function () {
  LXQ.initialized = true
  var globalReceived = false
  if (config.lxq_license && $.lexiqaAuthenticator) {
    $.lexiqaAuthenticator.init({
      licenseKey: config.lxq_license,
      partnerId: config.lxq_partnerid,
      lxqServer: config.lexiqaServer,
      projectId: config.id_job + '-' + config.password,
    })
  } else {
    config.lxq_enabled = false
  }
  /*
   * Add lexiQA event handlers for warnings events
   */
  $(document).on('getWarning:local:success', function (e, data) {
    LXQ.doLexiQA(data.segment, false, function () {})
  })
  /* Invoked when page loads */
  $(document).on('getWarning:global:success', function () {
    if (globalReceived) {
      return
    }

    LXQ.getLexiqaWarnings(function () {
      globalReceived = true
    })
  })

  /* invoked when segment is completed (translated clicked)*/
  $(document).on('setTranslation:success', function (e, data) {
    LXQ.doLexiQA(data.segment, true, null)
  })

  /* invoked when more segments are loaded...*/
  $(window).on('segmentsAdded', function (e, data) {
    globalReceived = false
    console.log('[LEXIQA] got segmentsAdded ')
    each(data.resp, function (file) {
      if (
        file.segments &&
        LXQ.hasOwnProperty('lexiqaData') &&
        LXQ.lexiqaData.hasOwnProperty('lexiqaWarnings')
      ) {
        each(file.segments, function (segment) {
          if (LXQ.lexiqaData.lexiqaWarnings.hasOwnProperty(segment.sid)) {
            // console.log('in loadmore segments, segment: '+segment.sid+' already has qa info...');
            //clean up and redo powertip on any glossaries/blacklists
            LXQ.redoHighlighting(segment.sid, true)
            LXQ.redoHighlighting(segment.sid, false)
          }
        })
      }
    })
  })

  return (function ($, config, window, LXQ) {
    var partnerid = config.lxq_partnerid
    var colors = {
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
    }
    var warningMessages = {
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
    }

    var modulesNoHighlight = ['b1g', 'g1g', 'g2g', 'g3g']

    var isNumeric = function (n) {
      return !isNaN(parseFloat(n)) && isFinite(n)
    }
    var cleanRanges = function (ranges) {
      var out = []
      if ($.isPlainObject(ranges) || isNumeric(ranges[0])) {
        ranges = [ranges]
      }

      for (var i = 0, l = ranges.length; i < l; i++) {
        var range = ranges[i]

        if ($.isArray(range)) {
          out.push({
            color: color,
            start: range[0],
            end: range[1],
          })
        } else {
          if (range.ranges) {
            if ($.isPlainObject(range.ranges) || isNumeric(range.ranges[0])) {
              range.ranges = [range.ranges]
            }

            for (var j = 0, m = range.ranges.length; j < m; j++) {
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
      if (out.length == 0) {
        return null
      }
      out.sort(function (a, b) {
        if (a.end == b.end) {
          return a.start - b.start
        }
        return a.end - b.end
      })
      var textMaxHighlight = out[out.length - 1].end

      out.sort(function (a, b) {
        if (a.start == b.start) {
          return a.end - b.end
        }
        return a.start - b.start
      })
      var textMinHighlight = out[0].start
      var txt = new Array(textMaxHighlight - textMinHighlight + 1)
      for (let i = 0; i < txt.length; i++) txt[i] = []
      $.each(out, function (j, range) {
        var i
        if (range.ignore != true) {
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
      var newout = []
      var curitem = null
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
            var areErrorsSame = true
            if (curitem.errors.length == txt[i].length) {
              for (let j = 0; j < curitem.errors.length; j++) {
                if (curitem.errors[j] != txt[i][j]) {
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
    }

    var getRanges = function (results, text, isSource) {
      //var text = $(area).val();
      // var LTPLACEHOLDER = "##LESSTHAN##";
      // var GTPLACEHOLDER = "##GREATERTHAN##";
      var rangesIn = [
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

      var ranges = cleanRanges(rangesIn)
      if (ranges == null) {
        //do nothing
        return {}
      }

      ranges.newout.map(function (range) {
        if (text && range.start !== range.end) {
          if (range.start < text.length && !range.ignore) {
            var dataErrors = '',
              multiple
            //calculate the color
            if (range.errors.length === 1) {
              range.color = ranges.out[range.errors[0]].color
              range.myClass =
                ranges.out[range.errors[0]].module +
                ' ' +
                ranges.out[range.errors[0]].module[0] +
                '0' //.p0 for instance
              dataErrors = ranges.out[range.errors[0]].errorid
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
                dataErrors += ' ' + ranges.out[element].errorid
                if (modulesNoHighlight.indexOf(ranges.out[element].module) < 0)
                  multiple++
              })
              range.color = colors.multiple
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
    }

    var buildTooltipMessages = function (range, sid, isSource) {
      let classList = range.myClass.split(/\s+/)
      let messages = []
      let errorList = range.errorid.split(/\s+/)
      $.each(classList, function (j, cl) {
        var txt = getWarningForModule(cl, isSource)
        if (cl === 'g3g' && LXQ.lexiqaData.lexiqaWarnings[sid]) {
          //need to modify message with word.
          ind = Math.floor(j / 2) //we aredding the x0 classes after each class..
          var word = LXQ.lexiqaData.lexiqaWarnings[sid][errorList[ind]].msg
          txt = txt.replace('#xxx#', word)
        } else if (cl === 'o1' && LXQ.lexiqaData.lexiqaWarnings[sid]) {
          //need to modify message with word.
          ind = Math.floor(j / 2) //we aredding the x0 classes after each class..
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
    }

    // var replaceWord  = function(word, suggest,target) {
    //
    //     //retrieve suggestion code
    //     // var word = $(this).text();
    //     // $.ajax({
    //     //     url: config.lexiqaServer+'/getSuggestions',
    //     //     data: {
    //     //         word: word,
    //     //         lang: config.target_rfc
    //     //     },
    //     //     type: 'GET',
    //     //     success: function(response) {
    //     //         $.each(response,function(i,suggest) {
    //     //             //txt+='</br>'+suggest;
    //     //             var row = $('<div class="tooltip-error-container"> '+
    //     //                 '<a class="tooltip-error-category"/></div>');
    //     //             row.find('.tooltip-error-category').text(suggest);
    //     //             row.find('.tooltip-error-category').on('click', function (e) {
    //     //                 e.preventDefault();
    //     //                 LXQ.replaceWord(word, suggest,that);
    //     //             });
    //     //             $('#powerTip').append(row);
    //     //         });
    //     //     }
    //     // });
    //
    //
    // };
    var ignoreError = function (errorid) {
      var splits = errorid.split(/_/g)
      var targetSeg = splits[1]
      var inSource = splits[splits.length - 1] === 's' ? true : false
      //console.log('ignoring error with id: '+ errorid +' in segment: '+targetSeg);
      if (LXQ.lexiqaData.lexiqaWarnings[targetSeg]) {
        LXQ.lexiqaData.lexiqaWarnings[targetSeg][errorid].ignored = true
        redoHighlighting(targetSeg, inSource)
        if (getVisibleWarningsCountForSegment(targetSeg) <= 0) {
          //remove the segment from database/reduce the number count
          LXQ.lxqRemoveSegmentFromWarningList(targetSeg)
        }
        postIgnoreError(errorid)
      }
    }

    var redoHighlighting = function (segmentId, insource) {
      // var segment = UI.getSegmentById(segmentId);
      var highlights = {
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
    }

    var postIgnoreError = function (errorid) {
      lexiqaIgnoreError({errorId: errorid})
    }

    var getVisibleWarningsCountForSegment = function (segment) {
      var segId
      if (typeof segment === 'string') {
        segId = segment
      } else segId = UI.getSegmentId(segment)

      if (!LXQ.lexiqaData.lexiqaWarnings.hasOwnProperty(segId)) return 0
      var count = 0
      $.each(LXQ.lexiqaData.lexiqaWarnings[segId], function (i, element) {
        if (
          (element.ignored === undefined || !element.ignored) &&
          element.module !== 'c3'
        )
          count++
      })
      return count
    }

    var getWarningForModule = function (module, isSource) {
      if (warningMessages.hasOwnProperty(module))
        return isSource ? warningMessages[module].s : warningMessages[module].t
      else return null
    }
    var notCheckedSegments //store the unchecked segments at startup
    var doQAallSegments = function () {
      var segments = SegmentStore.getAllSegments
      var notChecked = []
      $.each(segments, function (keys, segment) {
        var segId = segment.sid
        if (LXQ.lexiqaData.segments.indexOf(segId) < 0) {
          notChecked.push(segment)
        }
      })
      notCheckedSegments = notChecked
      checkNextUncheckedSegment()
    }

    var checkNextUncheckedSegment = function () {
      if (!(notCheckedSegments.length > 0)) return
      var segment = notCheckedSegments.pop()
      if (!segment) return
      if (segment.translation) {
        // console.log('Requesting QA for: '+segment);
        LXQ.doLexiQA(segment, true, checkNextUncheckedSegment)
      } else {
        checkNextUncheckedSegment()
      }
    }

    var initPopup = function () {
      lexiqaTooltipwarnings().then((data) => {
        warningMessages = data
        modulesNoHighlight = Object.entries(data)
          .filter(([key]) => key[key.length - 1] === 'g')
          .map(([key]) => key)
      })
    }
    // Interfaces
    $.extend(LXQ, {
      getRanges: getRanges,
      colors: colors,
      buildTooltipMessages: buildTooltipMessages,
      getVisibleWarningsCountForSegment: getVisibleWarningsCountForSegment,
      ignoreError: ignoreError,
      redoHighlighting: redoHighlighting,
      doQAallSegments: doQAallSegments,
      initPopup: initPopup,
      partnerid: partnerid,
      projectid: config.id_job + '-' + config.password,
    })
  })(jQuery, config, window, LXQ)
}

document.addEventListener('DOMContentLoaded', () => {
  if (LXQ.enabled()) {
    LXQ.init()
  }
})

export default LXQ
