import _ from 'lodash'

import SegmentActions from '../../../actions/SegmentActions'

const QaCheckGlossary = {
  enabled() {
    return config.qa_check_glossary_enabled
  },
  update(glossary) {
    var mapped = {}

    // group by segment id
    _.each(glossary.matches, function (item) {
      mapped[item.id_segment] ? null : (mapped[item.id_segment] = [])
      mapped[item.id_segment].push(item.data)
    })
    SegmentActions.addQaCheckMatches(mapped)
  },
}

export default QaCheckGlossary
