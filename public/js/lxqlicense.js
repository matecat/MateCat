'use strict'
'undefined' != typeof jQuery &&
  !(function (a) {
    function e(d, e) {
      ;(this.settings = a.extend({}, c, e)),
        (this._defaults = c),
        (this._name = b),
        this.init()
    }
    var b = 'lexiqaAuthenticator',
      c = {
        licenseKey: 'aaaaaaaaa',
        projectId: 'projectid',
        partnerId: 'adsf',
        lxqServer: 'http://localhost:8181',
      },
      d = ''
    ;(a.fn[b] = function (c) {
      return this.map(function () {
        return (
          a.data(this, 'plugin_' + b) ||
            a.data(this, 'plugin_' + b, new e(this, c)),
          a.data(this, 'plugin_' + b)
        )
      })
    }),
      (a.lexiqaAuthenticator = {
        setDefaults: function setDefaults(b) {
          c = a.extend({}, c, b)
        },
        init: function init(e) {
          return (
            (d = location.origin.split(/\/\//g)[1]),
            (this.settings = a.extend({}, c, e)),
            (this._defaults = c),
            (this._name = b),
            this
          )
        },
        doLexiQA: function doLexiQA(b, c) {
          a.ajax({
            type: 'POST',
            url: this.settings.lxqServer + '/qasegment',
            data: {
              qaData: {
                sourcelanguage: b.sourcelanguage,
                targetlanguage: b.targetlanguage,
                sourcetext: b.sourcetext,
                targettext: b.targettext,
                returnUrl: location.href,
                segmentId: b.segmentId,
                baseUrl: d,
                partnerId: this.settings.partnerId,
                projectId: this.settings.projectId,
                isSegmentCompleted: b.isSegmentCompleted,
                responseMode: 'includeQAResults',
                token: q(
                  this.settings.licenseKey +
                    '|' +
                    d +
                    '|' +
                    this.settings.projectId +
                    '|' +
                    b.segmentId +
                    '|' +
                    this.settings.partnerId,
                ),
              },
            },
            success: function success(a) {
              c(null, a)
            },
            error: function error(a) {
              c(a, null)
            },
          })
        },
      })
    var f = function f(a, b) {
        var c = a[0],
          d = a[1],
          e = a[2],
          f = a[3]
        ;(c = h(c, d, e, f, b[0], 7, -680876936)),
          (f = h(f, c, d, e, b[1], 12, -389564586)),
          (e = h(e, f, c, d, b[2], 17, 606105819)),
          (d = h(d, e, f, c, b[3], 22, -1044525330)),
          (c = h(c, d, e, f, b[4], 7, -176418897)),
          (f = h(f, c, d, e, b[5], 12, 1200080426)),
          (e = h(e, f, c, d, b[6], 17, -1473231341)),
          (d = h(d, e, f, c, b[7], 22, -45705983)),
          (c = h(c, d, e, f, b[8], 7, 1770035416)),
          (f = h(f, c, d, e, b[9], 12, -1958414417)),
          (e = h(e, f, c, d, b[10], 17, -42063)),
          (d = h(d, e, f, c, b[11], 22, -1990404162)),
          (c = h(c, d, e, f, b[12], 7, 1804603682)),
          (f = h(f, c, d, e, b[13], 12, -40341101)),
          (e = h(e, f, c, d, b[14], 17, -1502002290)),
          (d = h(d, e, f, c, b[15], 22, 1236535329)),
          (c = i(c, d, e, f, b[1], 5, -165796510)),
          (f = i(f, c, d, e, b[6], 9, -1069501632)),
          (e = i(e, f, c, d, b[11], 14, 643717713)),
          (d = i(d, e, f, c, b[0], 20, -373897302)),
          (c = i(c, d, e, f, b[5], 5, -701558691)),
          (f = i(f, c, d, e, b[10], 9, 38016083)),
          (e = i(e, f, c, d, b[15], 14, -660478335)),
          (d = i(d, e, f, c, b[4], 20, -405537848)),
          (c = i(c, d, e, f, b[9], 5, 568446438)),
          (f = i(f, c, d, e, b[14], 9, -1019803690)),
          (e = i(e, f, c, d, b[3], 14, -187363961)),
          (d = i(d, e, f, c, b[8], 20, 1163531501)),
          (c = i(c, d, e, f, b[13], 5, -1444681467)),
          (f = i(f, c, d, e, b[2], 9, -51403784)),
          (e = i(e, f, c, d, b[7], 14, 1735328473)),
          (d = i(d, e, f, c, b[12], 20, -1926607734)),
          (c = j(c, d, e, f, b[5], 4, -378558)),
          (f = j(f, c, d, e, b[8], 11, -2022574463)),
          (e = j(e, f, c, d, b[11], 16, 1839030562)),
          (d = j(d, e, f, c, b[14], 23, -35309556)),
          (c = j(c, d, e, f, b[1], 4, -1530992060)),
          (f = j(f, c, d, e, b[4], 11, 1272893353)),
          (e = j(e, f, c, d, b[7], 16, -155497632)),
          (d = j(d, e, f, c, b[10], 23, -1094730640)),
          (c = j(c, d, e, f, b[13], 4, 681279174)),
          (f = j(f, c, d, e, b[0], 11, -358537222)),
          (e = j(e, f, c, d, b[3], 16, -722521979)),
          (d = j(d, e, f, c, b[6], 23, 76029189)),
          (c = j(c, d, e, f, b[9], 4, -640364487)),
          (f = j(f, c, d, e, b[12], 11, -421815835)),
          (e = j(e, f, c, d, b[15], 16, 530742520)),
          (d = j(d, e, f, c, b[2], 23, -995338651)),
          (c = k(c, d, e, f, b[0], 6, -198630844)),
          (f = k(f, c, d, e, b[7], 10, 1126891415)),
          (e = k(e, f, c, d, b[14], 15, -1416354905)),
          (d = k(d, e, f, c, b[5], 21, -57434055)),
          (c = k(c, d, e, f, b[12], 6, 1700485571)),
          (f = k(f, c, d, e, b[3], 10, -1894986606)),
          (e = k(e, f, c, d, b[10], 15, -1051523)),
          (d = k(d, e, f, c, b[1], 21, -2054922799)),
          (c = k(c, d, e, f, b[8], 6, 1873313359)),
          (f = k(f, c, d, e, b[15], 10, -30611744)),
          (e = k(e, f, c, d, b[6], 15, -1560198380)),
          (d = k(d, e, f, c, b[13], 21, 1309151649)),
          (c = k(c, d, e, f, b[4], 6, -145523070)),
          (f = k(f, c, d, e, b[11], 10, -1120210379)),
          (e = k(e, f, c, d, b[2], 15, 718787259)),
          (d = k(d, e, f, c, b[9], 21, -343485551)),
          (a[0] = r(c, a[0])),
          (a[1] = r(d, a[1])),
          (a[2] = r(e, a[2])),
          (a[3] = r(f, a[3]))
      },
      g = function g(a, b, c, d, e, f) {
        return (b = r(r(b, a), r(d, f))), r((b << e) | (b >>> (32 - e)), c)
      },
      h = function h(a, b, c, d, e, f, _h) {
        return g((b & c) | (~b & d), a, b, e, f, _h)
      },
      i = function i(a, b, c, d, e, f, h) {
        return g((b & d) | (c & ~d), a, b, e, f, h)
      },
      j = function j(a, b, c, d, e, f, h) {
        return g(b ^ c ^ d, a, b, e, f, h)
      },
      k = function k(a, b, c, d, e, f, h) {
        return g(c ^ (b | ~d), a, b, e, f, h)
      },
      l = function l(a) {
        var e,
          c = a.length,
          d = [1732584193, -271733879, -1732584194, 271733878]
        for (e = 64; e <= a.length; e += 64) {
          f(d, m(a.substring(e - 64, e)))
        }
        a = a.substring(e - 64)
        var g = [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0]
        for (e = 0; e < a.length; e++) {
          g[e >> 2] |= a.charCodeAt(e) << (e % 4 << 3)
        }
        if (((g[e >> 2] |= 128 << (e % 4 << 3)), e > 55))
          for (f(d, g), e = 0; 16 > e; e++) {
            g[e] = 0
          }
        return (g[14] = 8 * c), f(d, g), d
      },
      m = function m(a) {
        var c,
          b = []
        for (c = 0; 64 > c; c += 4) {
          b[c >> 2] =
            a.charCodeAt(c) +
            (a.charCodeAt(c + 1) << 8) +
            (a.charCodeAt(c + 2) << 16) +
            (a.charCodeAt(c + 3) << 24)
        }
        return b
      },
      n = '0123456789abcdef'.split(''),
      o = function o(a) {
        for (var b = '', c = 0; 4 > c; c++) {
          b += n[(a >> (8 * c + 4)) & 15] + n[(a >> (8 * c)) & 15]
        }
        return b
      },
      p = function p(a) {
        for (var b = 0; b < a.length; b++) {
          a[b] = o(a[b])
        }
        return a.join('')
      },
      q = function q(a) {
        return p(l(a))
      },
      r = function r(a, b) {
        return (a + b) & 4294967295
      }
    '5d41402abc4b2a76b9719d911017c592' != q('hello') &&
      (r = function r(a, b) {
        var c = (65535 & a) + (65535 & b),
          d = (a >> 16) + (b >> 16) + (c >> 16)
        return (d << 16) | (65535 & c)
      })
  })(jQuery)
//# sourceMappingURL=lxqlicense.js.map
