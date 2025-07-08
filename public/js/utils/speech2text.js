import SegmentActions from '../actions/SegmentActions'
import SegmentStore from '../stores/SegmentStore'
import CatToolActions from '../actions/CatToolActions'
import $ from 'jquery'
import UserStore from '../stores/UserStore'

const Speech2Text = {
  enabled: function ({dictation} = {}) {
    return (
      'webkitSpeechRecognition' in window &&
      (dictation === 1 || UserStore.getUserMetadata()?.dictation === 1)
    )
  },
  disable: function () {
    Speech2Text.initialized = false
    document.removeEventListener(
      'contribution:copied',
      Speech2Text.contributionCopied,
    )
  },
  enable: function () {},
  init: function () {
    Speech2Text.initialized = true
    Speech2Text.loadRecognition()
    document.addEventListener(
      'contribution:copied',
      Speech2Text.contributionCopied,
    )
  },
  contributionCopied: function (event) {
    const data = event.detail
    if (Speech2Text.microphone && Speech2Text.sid == data.segment.sid) {
      Speech2Text.finalTranscript = data.translation + ' '
    }
  },
  recognition: null,
  recognizing: false,
  microphone: null,
  finalTranscript: '',
  interimTranscript: '',
  targetElement: null,
  isStopingRecognition: false,
  isToKeepRecognizing: false,
  wereMatchesPreviouslyOpened: false,
  loadRecognition: function () {
    Speech2Text.recognition = new webkitSpeechRecognition()
    Speech2Text.recognition.continuous = true
    Speech2Text.recognition.interimResults = true
    Speech2Text.recognition.onstart = Speech2Text.onRecognitionStart
    Speech2Text.recognition.onerror = Speech2Text.onRecognitionError
    Speech2Text.recognition.onend = Speech2Text.onRecognitionEnd
    Speech2Text.recognition.onresult = Speech2Text.onRecognitionResult
    Speech2Text.recognition.lang = config.target_rfc
  },
  enableMicrophone: function (segment) {
    Speech2Text.microphone = segment.find('.micSpeech')
    if (Speech2Text.recognition) {
      const segmentObj = SegmentStore.getCurrentSegment()
      if (segmentObj) {
        Speech2Text.targetElement = segmentObj.translation
        Speech2Text.sid = segmentObj.sid

        Speech2Text.microphone.on('click', Speech2Text.clickMicrophone)

        if (Speech2Text.recognizing) {
          Speech2Text.startSpeechRecognition(Speech2Text.microphone)
        }
      }
    } else {
      Speech2Text.microphone.hide()
      const notification = {
        title: 'Dictation not supported',
        text: 'Web Speech API is not supported by this browser. Update Chrome to the latest version.',
        type: 'error',
      }
      CatToolActions.addNotification(notification)
    }
  },
  clickMicrophone: function (event) {
    var microphone = $(event.currentTarget)

    Speech2Text.isStopingRecognition = false

    if (microphone.hasClass('micSpeechActive')) {
      Speech2Text.disableContinuousRecognizing()
      Speech2Text.stopSpeechRecognition(microphone)
    } else {
      Speech2Text.startSpeechRecognition(microphone)
      Speech2Text.enableContinuousRecognizing()
    }
  },
  startSpeechRecognition: function (microphone) {
    const segment = SegmentStore.getCurrentSegment()
    if (!segment) return
    if (!microphone.hasClass('micSpeechActive')) {
      microphone.addClass('micSpeechActive')
      Speech2Text.animateSpeechActive()
    }

    if (Speech2Text.shouldEmptyTargetElement(segment)) {
      Speech2Text.finalTranscript = ''
      SegmentActions.replaceEditAreaTextContent(Speech2Text.sid, '')
    } else {
      Speech2Text.finalTranscript = segment.translation + ' '
    }

    Speech2Text.interimTranscript = ''

    if (!Speech2Text.recognizing) {
      Speech2Text.recognition.start()
      Speech2Text.showMatches()
    }
  },
  stopSpeechRecognition: function (microphone) {
    microphone.removeClass('micSpeechActive micSpeechReceiving')

    Speech2Text.recognition.stop()

    if (Speech2Text.recognizing) {
      Speech2Text.isStopingRecognition = true
    }
  },
  onRecognitionStart: function () {
    Speech2Text.recognizing = true
  },
  onRecognitionError: function (event) {
    if (event.error === 'no-speech') {
      Speech2Text.disableContinuousRecognizing()
      Speech2Text.stopSpeechRecognition(Speech2Text.microphone)
    } else {
      //TODO: Display a user-friendly error message
      console.error('Error found: ' + event.error)
    }
  },
  onRecognitionEnd: function () {
    Speech2Text.recognizing = false
    Speech2Text.isStopingRecognition = false

    if (Speech2Text.isToKeepRecognizing) {
      Speech2Text.startSpeechRecognition(Speech2Text.microphone)
    } else {
      Speech2Text.microphone.removeClass('micSpeechActive')
    }
  },
  onRecognitionResult: function (event) {
    Speech2Text.interimTranscript = ''

    for (var i = event.resultIndex; i < event.results.length; ++i) {
      if (event.results[i].isFinal) {
        Speech2Text.finalTranscript += event.results[i][0].transcript
        Speech2Text.animateSpeechActive()
      } else {
        Speech2Text.interimTranscript += event.results[i][0].transcript
        Speech2Text.animateSpeechReceiving()
      }
    }

    if (!Speech2Text.isStopingRecognition) {
      var html =
        Speech2Text.linebreak(Speech2Text.finalTranscript) +
        Speech2Text.linebreak(Speech2Text.interimTranscript)
      let sid = Speech2Text.sid
      SegmentActions.replaceEditAreaTextContent(sid, html)
      SegmentActions.modifiedTranslation(sid, true)
    }
  },
  linebreak: function (s) {
    var two_line = /\n\n/g
    var one_line = /\n/g

    return s.replace(two_line, '<p/>').replace(one_line, '<br>')
  },
  shouldEmptyTargetElement: function (segment) {
    return (
      segment &&
      !(
        (segment.autopropagated_from && segment.autopropagated_from != '0') ||
        segment.suggestion_match === '100' ||
        segment.status !== 'NEW'
      )
    )
  },
  enableContinuousRecognizing: function () {
    Speech2Text.isToKeepRecognizing = true
  },
  disableContinuousRecognizing: function () {
    Speech2Text.isToKeepRecognizing = false
  },
  showMatches: function () {
    SegmentActions.activateTab(UI.getSegmentId(UI.currentSegment, 'matches'))
  },
  animateSpeechActive: function () {
    Speech2Text.microphone.removeClass('micSpeechReceiving')
  },
  animateSpeechReceiving: function () {
    Speech2Text.microphone.addClass('micSpeechReceiving')
  },

  /**
   * This method checks if a contribution match is to be copied inside the edit area.
   * If speech is active, then only contributions with match 100% are to be copied.
   *
   * @param match
   * @returns {boolean}
   */
  isContributionToBeAllowed: function (match) {
    return !Speech2Text.recognizing || match == '100%'
  },
}

export default Speech2Text
