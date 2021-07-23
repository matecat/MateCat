'use strict'
'undefined' != typeof jQuery &&
  (function (n) {
    function e(t, e) {
      ;(this.settings = n.extend({}, i, e)),
        (this._defaults = i),
        (this._name = r),
        this.init()
    }
    var r = 'lexiqaAuthenticator',
      i = {
        licenseKey: 'aaaaaaaaa',
        projectId: 'projectid',
        partnerId: 'adsf',
        lxqServer: 'http://localhost:8181',
      },
      a = ''
    ;(n.fn[r] = function (t) {
      return this.map(function () {
        return (
          n.data(this, 'plugin_' + r) ||
            n.data(this, 'plugin_' + r, new e(this, t)),
          n.data(this, 'plugin_' + r)
        )
      })
    }),
      (n.lexiqaAuthenticator = {
        setDefaults: function (t) {
          i = n.extend({}, i, t)
        },
        init: function (t) {
          return (
            (a = location.origin.split(/\/\//g)[1]),
            (this.settings = n.extend({}, i, t)),
            (this._defaults = i),
            (this._name = r),
            this
          )
        },
        doLexiQA: function (t, e) {
          n.ajax({
            type: 'POST',
            url: this.settings.lxqServer + '/qasegment',
            data: {
              qaData: {
                sourcelanguage: t.sourcelanguage,
                targetlanguage: t.targetlanguage,
                sourcetext: t.sourcetext,
                targettext: t.targettext,
                returnUrl: location.href,
                segmentId: t.segmentId,
                baseUrl: a,
                partnerId: this.settings.partnerId,
                projectId: this.settings.projectId,
                isSegmentCompleted: t.isSegmentCompleted,
                responseMode: 'includeQAResults',
                token: p(
                  this.settings.licenseKey +
                    '|' +
                    a +
                    '|' +
                    this.settings.projectId +
                    '|' +
                    t.segmentId +
                    '|' +
                    this.settings.partnerId,
                ),
              },
            },
            success: function (t) {
              e(null, t)
            },
            error: function (t) {
              e(t, null)
            },
          })
        },
      })
    function s(t, e) {
      var n = t[0],
        r = t[1],
        i = t[2],
        a = t[3]
      ;(n = c(n, r, i, a, e[0], 7, -680876936)),
        (a = c(a, n, r, i, e[1], 12, -389564586)),
        (i = c(i, a, n, r, e[2], 17, 606105819)),
        (r = c(r, i, a, n, e[3], 22, -1044525330)),
        (n = c(n, r, i, a, e[4], 7, -176418897)),
        (a = c(a, n, r, i, e[5], 12, 1200080426)),
        (i = c(i, a, n, r, e[6], 17, -1473231341)),
        (r = c(r, i, a, n, e[7], 22, -45705983)),
        (n = c(n, r, i, a, e[8], 7, 1770035416)),
        (a = c(a, n, r, i, e[9], 12, -1958414417)),
        (i = c(i, a, n, r, e[10], 17, -42063)),
        (r = c(r, i, a, n, e[11], 22, -1990404162)),
        (n = c(n, r, i, a, e[12], 7, 1804603682)),
        (a = c(a, n, r, i, e[13], 12, -40341101)),
        (i = c(i, a, n, r, e[14], 17, -1502002290)),
        (r = c(r, i, a, n, e[15], 22, 1236535329)),
        (n = l(n, r, i, a, e[1], 5, -165796510)),
        (a = l(a, n, r, i, e[6], 9, -1069501632)),
        (i = l(i, a, n, r, e[11], 14, 643717713)),
        (r = l(r, i, a, n, e[0], 20, -373897302)),
        (n = l(n, r, i, a, e[5], 5, -701558691)),
        (a = l(a, n, r, i, e[10], 9, 38016083)),
        (i = l(i, a, n, r, e[15], 14, -660478335)),
        (r = l(r, i, a, n, e[4], 20, -405537848)),
        (n = l(n, r, i, a, e[9], 5, 568446438)),
        (a = l(a, n, r, i, e[14], 9, -1019803690)),
        (i = l(i, a, n, r, e[3], 14, -187363961)),
        (r = l(r, i, a, n, e[8], 20, 1163531501)),
        (n = l(n, r, i, a, e[13], 5, -1444681467)),
        (a = l(a, n, r, i, e[2], 9, -51403784)),
        (i = l(i, a, n, r, e[7], 14, 1735328473)),
        (r = l(r, i, a, n, e[12], 20, -1926607734)),
        (n = f(n, r, i, a, e[5], 4, -378558)),
        (a = f(a, n, r, i, e[8], 11, -2022574463)),
        (i = f(i, a, n, r, e[11], 16, 1839030562)),
        (r = f(r, i, a, n, e[14], 23, -35309556)),
        (n = f(n, r, i, a, e[1], 4, -1530992060)),
        (a = f(a, n, r, i, e[4], 11, 1272893353)),
        (i = f(i, a, n, r, e[7], 16, -155497632)),
        (r = f(r, i, a, n, e[10], 23, -1094730640)),
        (n = f(n, r, i, a, e[13], 4, 681279174)),
        (a = f(a, n, r, i, e[0], 11, -358537222)),
        (i = f(i, a, n, r, e[3], 16, -722521979)),
        (r = f(r, i, a, n, e[6], 23, 76029189)),
        (n = f(n, r, i, a, e[9], 4, -640364487)),
        (a = f(a, n, r, i, e[12], 11, -421815835)),
        (i = f(i, a, n, r, e[15], 16, 530742520)),
        (r = f(r, i, a, n, e[2], 23, -995338651)),
        (n = d(n, r, i, a, e[0], 6, -198630844)),
        (a = d(a, n, r, i, e[7], 10, 1126891415)),
        (i = d(i, a, n, r, e[14], 15, -1416354905)),
        (r = d(r, i, a, n, e[5], 21, -57434055)),
        (n = d(n, r, i, a, e[12], 6, 1700485571)),
        (a = d(a, n, r, i, e[3], 10, -1894986606)),
        (i = d(i, a, n, r, e[10], 15, -1051523)),
        (r = d(r, i, a, n, e[1], 21, -2054922799)),
        (n = d(n, r, i, a, e[8], 6, 1873313359)),
        (a = d(a, n, r, i, e[15], 10, -30611744)),
        (i = d(i, a, n, r, e[6], 15, -1560198380)),
        (r = d(r, i, a, n, e[13], 21, 1309151649)),
        (n = d(n, r, i, a, e[4], 6, -145523070)),
        (a = d(a, n, r, i, e[11], 10, -1120210379)),
        (i = d(i, a, n, r, e[2], 15, 718787259)),
        (r = d(r, i, a, n, e[9], 21, -343485551)),
        (t[0] = x(n, t[0])),
        (t[1] = x(r, t[1])),
        (t[2] = x(i, t[2])),
        (t[3] = x(a, t[3]))
    }
    function u(t, e, n, r, i, a) {
      return (e = x(x(e, t), x(r, a))), x((e << i) | (e >>> (32 - i)), n)
    }
    function o(t) {
      for (var e = '', n = 0; n < 4; n++)
        e += h[(t >> (8 * n + 4)) & 15] + h[(t >> (8 * n)) & 15]
      return e
    }
    var c = function (t, e, n, r, i, a, s) {
        return u((e & n) | (~e & r), t, e, i, a, s)
      },
      l = function (t, e, n, r, i, a, s) {
        return u((e & r) | (n & ~r), t, e, i, a, s)
      },
      f = function (t, e, n, r, i, a, s) {
        return u(e ^ n ^ r, t, e, i, a, s)
      },
      d = function (t, e, n, r, i, a, s) {
        return u(n ^ (e | ~r), t, e, i, a, s)
      },
      g = function (t) {
        var e,
          n = []
        for (e = 0; e < 64; e += 4)
          n[e >> 2] =
            t.charCodeAt(e) +
            (t.charCodeAt(e + 1) << 8) +
            (t.charCodeAt(e + 2) << 16) +
            (t.charCodeAt(e + 3) << 24)
        return n
      },
      h = '0123456789abcdef'.split(''),
      p = function (t) {
        return (function (t) {
          for (var e = 0; e < t.length; e++) t[e] = o(t[e])
          return t.join('')
        })(
          (function (t) {
            var e,
              n = t.length,
              r = [1732584193, -271733879, -1732584194, 271733878]
            for (e = 64; e <= t.length; e += 64) s(r, g(t.substring(e - 64, e)))
            t = t.substring(e - 64)
            var i = [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0]
            for (e = 0; e < t.length; e++)
              i[e >> 2] |= t.charCodeAt(e) << (e % 4 << 3)
            if (((i[e >> 2] |= 128 << (e % 4 << 3)), 55 < e))
              for (s(r, i), e = 0; e < 16; e++) i[e] = 0
            return (i[14] = 8 * n), s(r, i), r
          })(t),
        )
      },
      x = function (t, e) {
        return (t + e) & 4294967295
      }
    '5d41402abc4b2a76b9719d911017c592' != p('hello') &&
      (x = function (t, e) {
        var n = (65535 & t) + (65535 & e)
        return (((t >> 16) + (e >> 16) + (n >> 16)) << 16) | (65535 & n)
      })
  })(jQuery)
