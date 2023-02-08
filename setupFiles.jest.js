import 'whatwg-fetch'

global.$ = require('./public/api/dist/lib/jquery-3.3.1.min.js')
global.jQuery = $
global.config = {
  id_job: 2,
}

require('./public/js/lib/semantic.min.js')
require('./public/js/lib/diff_match_patch.js')
