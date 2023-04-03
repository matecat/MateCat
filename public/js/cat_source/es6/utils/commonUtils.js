import _ from 'lodash'
import Cookies from 'js-cookie'
import Platform from 'platform'
import OfflineUtils from './offlineUtils'
import MBC from './mbc.main'
import SegmentActions from '../actions/SegmentActions'
import SegmentStore from '../stores/SegmentStore'
import AlertModal from '../components/modals/AlertModal'

const CommonUtils = {
  millisecondsToTime(milli) {
    var seconds = Math.round((milli / 1000) % 60)
    var minutes = Math.floor((milli / (60 * 1000)) % 60)
    return [minutes, seconds]
  },
  /**
   * Returns the translation status evaluating the job stats
   */
  getTranslationStatus(stats) {
    var t = 'approved'
    var app = parseFloat(stats.APPROVED)
    var tra = parseFloat(stats.TRANSLATED)
    var dra = parseFloat(stats.DRAFT)
    var rej = parseFloat(stats.REJECTED)
    var todo = parseFloat(stats.TODO)

    // If second pass enabled
    if (config.secondRevisionsCount && stats.reviews) {
      var revWords1 = stats.reviews.find(function (value) {
        return value.revision_number === 1
      })

      var revWords2 = stats.reviews.find(function (value) {
        return value.revision_number === 2
      })

      if (revWords1 && _.round(parseFloat(revWords1.advancement_wc)) > 0) {
        app = parseFloat(revWords1.advancement_wc)
      } else if (
        revWords2 &&
        _.round(parseFloat(revWords2.advancement_wc)) > 0
      ) {
        app = parseFloat(revWords2.advancement_wc)
        t = 'approved-2ndpass'
      }
    }

    if (tra) t = 'translated'
    if (dra) t = 'draft'
    if (rej) t = 'draft'

    if (!tra && !dra && !rej && !app && todo > 0) {
      t = 'draft'
    }

    return t
  },
  levenshteinDistance(s1, s2) {
    //       discuss at: http://phpjs.org/functions/levenshtein/
    //      original by: Carlos R. L. Rodrigues (http://www.jsfromhell.com)
    //      bugfixed by: Onno Marsman
    //       revised by: Andrea Giammarchi (http://webreflection.blogspot.com)
    // reimplemented by: Brett Zamir (http://brett-zamir.me)
    // reimplemented by: Alexander M Beedie
    //        example 1: levenshtein('Kevin van Zonneveld', 'Kevin van Sommeveld');
    //        returns 1: 3

    if (s1 == s2) {
      return 0
    }

    var s1_len = s1.length
    var s2_len = s2.length
    if (s1_len === 0) {
      return s2_len
    }
    if (s2_len === 0) {
      return s1_len
    }

    // BEGIN STATIC
    var split = false
    try {
      split = !'0'[0]
    } catch (e) {
      split = true // Earlier IE may not support access by string index
    }
    // END STATIC
    if (split) {
      s1 = s1.split('')
      s2 = s2.split('')
    }

    var v0 = new Array(s1_len + 1)
    var v1 = new Array(s1_len + 1)

    var s1_idx = 0,
      s2_idx = 0,
      cost = 0
    for (s1_idx = 0; s1_idx < s1_len + 1; s1_idx++) {
      v0[s1_idx] = s1_idx
    }
    var char_s1 = '',
      char_s2 = ''
    for (s2_idx = 1; s2_idx <= s2_len; s2_idx++) {
      v1[0] = s2_idx
      char_s2 = s2[s2_idx - 1]

      for (s1_idx = 0; s1_idx < s1_len; s1_idx++) {
        char_s1 = s1[s1_idx]
        cost = char_s1 == char_s2 ? 0 : 1
        var m_min = v0[s1_idx + 1] + 1
        var b = v1[s1_idx] + 1
        var c = v0[s1_idx] + cost
        if (b < m_min) {
          m_min = b
        }
        if (c < m_min) {
          m_min = c
        }
        v1[s1_idx + 1] = m_min
      }
      var v_tmp = v0
      v0 = v1
      v1 = v_tmp
    }
    return v0[s1_len]
  },
  toTitleCase(str) {
    return str.replace(/[\wwÀ-ÿЀ-џ]\S*/g, function (txt) {
      return txt.charAt(0).toUpperCase() + txt.substr(1).toLowerCase()
    })
  },

  /**
   * A generic error message to show in modal window.
   *
   * @returns {*}
   */
  genericErrorAlertMessage() {
    ModalsActions.showModalComponent(
      AlertModal,
      {
        text:
          'There was an error while saving data to server, please try again. <br/>If the problem persists please contact <a href="mailto:' +
          config.support_mail +
          '">' +
          config.support_mail +
          '</a> reporting the web address of the current browser tab',
      },
      'Search  Alert',
    )
  },

  setBrowserHistoryBehavior() {
    let updateAppByPopState = () => {
      var segment = SegmentStore.getSegmentByIdToJS(this.parsedHash.segmentId)
      var currentSegment = SegmentStore.getCurrentSegment()
      if (segment && currentSegment?.sid === segment.sid) return
      if (segment && !segment.opened) {
        SegmentActions.openSegment(this.parsedHash.segmentId, true)
      }
    }

    window.onpopstate = () => {
      if (this.parsedHash.onlyActionRemoved(window.location.hash)) {
        return
      }

      this.parsedHash = new ParsedHash(window.location.hash)

      if (this.parsedHash.hashCleanupRequired()) {
        this.parsedHash.cleanupHash()
      }

      updateAppByPopState()
    }

    window.addEventListener('historyChangeState', () => {
      this.parsedHash = new ParsedHash(window.location.hash)
    })

    this.parsedHash = new ParsedHash(window.location.hash)
    this.parsedHash.hashCleanupRequired() && this.parsedHash.cleanupHash()
  },

  goodbye(e) {
    this.clearStorage('contribution')
    //set dont_confirm_leave to 1 when you want the user to be able to leave without confirmation
    let say_goodbye = (leave_message) => {
      if (typeof leave_message !== 'undefined') {
        if (!e) e = window.event
        //e.cancelBubble is supported by IE - this will kill the bubbling process.
        e.cancelBubble = true
        e.returnValue = leave_message
        //e.stopPropagation works in Firefox.
        if (e.stopPropagation) {
          e.stopPropagation()
          e.preventDefault()
        }
        //return works for Chrome and Safari
        return leave_message
      }
    }
    if (
      $('#downloadProject').hasClass('disabled') ||
      $('tr td a.downloading').length ||
      $('.popup-tm td.uploadfile.uploading').length
    ) {
      return say_goodbye(
        'You have a pending operation. Are you sure you want to quit?',
      )
    }

    if (OfflineUtils.offline) {
      if (UI.setTranslationTail.length) {
        return say_goodbye(
          'You are working in offline mode. If you proceed to refresh you will lose all the pending translations. ' +
            'Do you want to proceed with the refresh ?',
        )
      }
    }
  },

  getIconClass: function (ext) {
    switch (ext) {
      case 'doc':
      case 'dot':
      case 'docx':
      case 'dotx':
      case 'docm':
      case 'dotm':
      case 'odt':
      case 'sxw':
        return 'extdoc'
      case 'pot':
      case 'pps':
      case 'ppt':
      case 'potm':
      case 'potx':
      case 'ppsm':
      case 'ppsx':
      case 'pptm':
      case 'pptx':
      case 'odp':
      case 'sxi':
        return 'extppt'
      case 'htm':
      case 'html':
        return 'exthtm'
      case 'pdf':
        return 'extpdf'
      case 'xls':
      case 'xlt':
      case 'xlsm':
      case 'xlsx':
      case 'xltx':
      case 'ods':
      case 'sxc':
      case 'csv':
        return 'extxls'
      case 'txt':
        return 'exttxt'
      case 'ttx':
        return 'extttx'
      case 'itd':
        return 'extitd'
      case 'xlf':
        return 'extxlf'
      case 'mif':
        return 'extmif'
      case 'idml':
        return 'extidd'
      case 'xtg':
        return 'extqxp'
      case 'xml':
        return 'extxml'
      case 'rc':
        return 'extrcc'
      case 'resx':
        return 'extres'
      case 'sgml':
        return 'extsgl'
      case 'sgm':
        return 'extsgm'
      case 'properties':
        return 'extpro'
      default:
        return 'extxif'
    }
  },

  isLocalStorageNameSupported: function () {
    var testKey = 'test',
      storage = window.localStorage
    try {
      storage.setItem(testKey, '1')
      storage.removeItem(testKey)
      return true
    } catch (error) {
      return false
    }
  },

  /**
   * Local Storage manipulation
   */
  // localStorageCurrentSegmentId: (config) ? "currentSegmentId-"+config.id_job+config.password : null,
  localStorageArray: [],
  isSafari:
    navigator.userAgent.search('Safari') >= 0 &&
    navigator.userAgent.search('Chrome') < 0,
  isPrivateSafari: () =>
    navigator.userAgent.search('Safari') >= 0 &&
    navigator.userAgent.search('Chrome') < 0 &&
    !CommonUtils.isLocalStorageNameSupported(),
  getLastSegmentFromLocalStorage: function () {
    let localStorageCurrentSegmentId =
      'currentSegmentId-' + config.id_job + config.password
    return localStorage.getItem(localStorageCurrentSegmentId)
  },
  setLastSegmentFromLocalStorage: function (segmentId) {
    let localStorageCurrentSegmentId =
      'currentSegmentId-' + config.id_job + config.password
    try {
      localStorage.setItem(localStorageCurrentSegmentId, segmentId)
    } catch (e) {
      this.clearStorage('currentSegmentId')
      localStorage.setItem(localStorageCurrentSegmentId, segmentId)
    }
  },
  clearStorage: function (what) {
    $.each(localStorage, function (k) {
      if (k.substring(0, what.length) === what) {
        localStorage.removeItem(k)
      }
    })
  },

  addInStorage: function (key, val, operation) {
    if (this.isPrivateSafari()) {
      let item = {
        key: key,
        value: val,
      }
      this.localStorageArray.push(item)
    } else {
      try {
        localStorage.setItem(key, val)
      } catch (e) {
        CommonUtils.clearStorage(operation)
        localStorage.setItem(key, val)
      }
    }
  },
  getFromStorage: function (key) {
    if (this.isPrivateSafari()) {
      let foundVal = 0
      $.each(this.localStorageArray, function () {
        if (this.key === key) foundVal = this.value
      })
      return foundVal || false
    } else {
      return localStorage.getItem(key)
    }
  },
  removeFromStorage: function (key) {
    if (this.isPrivateSafari()) {
      let foundIndex = 0
      $.each(this.localStorageArray, function (index) {
        if (this.key == key) foundIndex = index
      })
      this.localStorageArray.splice(foundIndex, 1)
    } else {
      localStorage.removeItem(key)
    }
  },
  addInSessionStorage: function (key, val, operation) {
    if (this.isPrivateSafari()) {
      let item = {
        key: key,
        value: val,
      }
      this.localStorageArray.push(item)
    } else {
      try {
        sessionStorage.setItem(key, val)
      } catch (e) {
        CommonUtils.clearStorage(operation)
        sessionStorage.setItem(key, val)
      }
    }
  },
  getFromSessionStorage: function (key) {
    if (this.isPrivateSafari()) {
      let foundVal = 0
      $.each(this.localStorageArray, function () {
        if (this.key === key) foundVal = this.value
      })
      return foundVal || false
    } else {
      return sessionStorage.getItem(key)
    }
  },
  removeFromSessionStorage: function (key) {
    if (this.isPrivateSafari()) {
      let foundIndex = 0
      $.each(this.localStorageArray, function (index) {
        if (this.key == key) foundIndex = index
      })
      this.localStorageArray.splice(foundIndex, 1)
    } else {
      sessionStorage.removeItem(key)
    }
  },
  getLanguageNameFromLocale: function (code) {
    try {
      return config.languages_array.find((e) => e.code === code).name
    } catch (e) {
      console.error('Unknown Language', e)
      return ''
    }
  },
  addCommas: function (nStr) {
    nStr += ''
    var x = nStr.split('.')
    var x1 = x[0]
    var x2 = x.length > 1 ? '.' + x[1] : ''
    var rgx = /(\d+)(\d{3})/
    while (rgx.test(x1)) {
      x1 = x1.replace(rgx, '$1' + ',' + '$2')
    }
    return x1 + x2
  },

  getParameterByName: function (name, url) {
    if (!url) url = window.location.href
    name = name.replace(/[[\]]/g, '\\$&')
    var regex = new RegExp('[?&]' + name + '(=([^&#]*)|&|#|$)'),
      results = regex.exec(url)
    if (!results) return null
    if (!results[2]) return ''
    return decodeURIComponent(results[2].replace(/\+/g, ' '))
  },
  removeParam: function (parameter) {
    var url = document.location.href
    var urlparts = url.split('?')

    if (urlparts.length >= 2) {
      var urlBase = urlparts.shift()
      var queryString = urlparts.join('?')

      var prefix = encodeURIComponent(parameter) + '='
      var pars = queryString.split(/[&;]/g)
      for (var i = pars.length; i-- > 0; )
        if (pars[i].lastIndexOf(prefix, 0) !== -1) pars.splice(i, 1)
      url = urlBase + '?' + pars.join('&')
      window.history.pushState('', document.title, url) // added this line to push the new url directly to url bar .
    }
    return url
  },
  checkEmail: function (text) {
    var re =
      /^(([^<>()[\]\\.,;:\s@"]+(\.[^<>()[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/
    if (!re.test(text.trim())) {
      return false
    }
    return true
  },
  validateEmailList: (emails) => {
    const re =
      /^(([^<>()[\]\\.,;:\s@"]+(\.[^<>()[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/
    let result = true
    let error = null
    emails.split(',').forEach(function (email) {
      if (!re.test(email.trim())) {
        result = false
        error = email
      }
    })
    return {result, emails: error}
  },
  getUserShortName: function (user) {
    if (user && user.first_name && user.last_name) {
      return (user.first_name[0] + user.last_name[0]).toUpperCase()
    } else {
      return 'AU'
    }
  },

  getGMTDate: function (date, timeZoneFrom) {
    if (typeof date === 'string' && date.indexOf('-') > -1) {
      date = date.replace(/-/g, '/')
    }
    var timezoneToShow = Cookies.get('matecat_timezone')
    if (timezoneToShow == '') {
      timezoneToShow = -1 * (new Date().getTimezoneOffset() / 60)
    }
    var dd = new Date(date)
    timeZoneFrom = timeZoneFrom
      ? timeZoneFrom
      : -1 * (new Date().getTimezoneOffset() / 60) //TODO UTC0 ? Why the browser gmt
    dd.setMinutes(dd.getMinutes() + (timezoneToShow - timeZoneFrom) * 60)
    var timeZone = this.getGMTZoneString()
    return {
      day: $.format.date(dd, 'd'),
      month: $.format.date(dd, 'MMMM'),
      time:
        $.format.date(dd, 'hh') +
        ':' +
        $.format.date(dd, 'mm') +
        ' ' +
        $.format.date(dd, 'a'),
      time2: $.format.date(dd, 'HH') + ':' + $.format.date(dd, 'mm'),
      year: $.format.date(dd, 'yyyy'),
      gmt: timeZone,
    }
  },
  getGMTZoneString: function () {
    // var timezoneToShow = "";
    var timezoneToShow = Cookies.get('matecat_timezone')
    if (timezoneToShow == '') {
      timezoneToShow = -1 * (new Date().getTimezoneOffset() / 60)
    }
    timezoneToShow = timezoneToShow > 0 ? '+' + timezoneToShow : timezoneToShow
    return timezoneToShow % 1 === 0
      ? 'GMT ' + timezoneToShow + ':00'
      : 'GMT ' + parseInt(timezoneToShow) + ':30'
  },
  checkJobIsSplitted: function () {
    return config.job_is_splitted
  },
  //Plugins
  parseFiles: (files) => {
    return files
  },

  /**
   * Returns true if the current OS is MacOS or iOS, false otherwise
   *
   * @returns {boolean}
   */
  isMacOS: () => {
    const os = Platform.os && Platform.os.family
    return (
      os &&
      (os.indexOf('Mac') >= 0 ||
        os.indexOf('OS X') >= 0 ||
        os.indexOf('iOS') >= 0)
    )
  },
  isAllowedLinkRedirect: () => false,
  dispatchTrackingError: (message) => {
    const event = new CustomEvent('track-error', {detail: message})
    document.dispatchEvent(event)
  },
}

const ParsedHash = function (hash) {
  var split
  var actionSep = ','
  var chunkSep = '-'
  var that = this
  var _obj = {}

  var processObject = function (obj) {
    _obj = obj
  }

  var processString = function (hash) {
    if (hash.indexOf('#') == 0) hash = hash.substr(1)

    if (hash.indexOf(actionSep) != -1) {
      split = hash.split(actionSep)

      _obj.segmentId = split[0]
      _obj.action = split[1]
    } else {
      _obj.segmentId = hash
      _obj.action = null
    }

    if (_obj.segmentId.indexOf(chunkSep) != -1) {
      split = hash.split(chunkSep)

      _obj.splittedSegmentId = split[0]
      _obj.chunkId = split[1]
    }
  }

  if (typeof hash === 'string') {
    processString(hash)
  } else {
    processObject(hash)
  }

  this.segmentId = _obj.segmentId
  this.action = _obj.action
  this.splittedSegmentId = _obj.splittedSegmentId
  this.chunkId = _obj.chunkId

  this.isComment = function () {
    return _obj.action == MBC.const.commentAction
  }

  this.toString = function () {
    var hash = ''
    if (_obj.splittedSegmentId) {
      hash = _obj.splittedSegmentId + chunkSep + _obj.chunkId
    } else {
      hash = _obj.segmentId
    }
    if (_obj.action) {
      hash = hash + actionSep + _obj.action
    }
    return hash
  }

  this.onlyActionRemoved = function (hash) {
    var current = new ParsedHash(hash)
    var diff = this.toString().split(current.toString())
    return MBC.enabled() && diff[1] == actionSep + MBC.const.commentAction
  }

  this.hashCleanupRequired = function () {
    return MBC.enabled() && this.isComment()
  }

  this.cleanupHash = function () {
    notifyModules()
    window.location.hash = CommonUtils.parsedHash.segmentId
  }

  var notifyModules = function () {
    MBC.enabled() && that.isComment() && MBC.setLastCommentHash(that)
  }
}

//TODO Move this
String.prototype.splice = function (idx, rem, s) {
  return this.slice(0, idx) + s + this.slice(idx + Math.abs(rem))
}

export default CommonUtils
