import TextUtils from '../../../utils/textUtils'

const QaCheckBlacklist = {
  enabled() {
    return config.qa_check_blacklist_enabled
  },

  update(blacklist) {
    var mapped = {}

    // group by segment id
    _.each(blacklist.matches, function (item) {
      mapped[item.id_segment] ? null : (mapped[item.id_segment] = [])
      mapped[item.id_segment].push({
        severity: item.severity,
        match: item.data.match,
      })
    })

    _.each(Object.keys(mapped), function (item, index) {
      var matched_words = _.chain(mapped[item])
        .map(function (match) {
          return match.match
        })
        .uniq()
        .value()
      SegmentActions.addQaBlacklistMatches(item, matched_words)
    })
  },
}

module.exports = QaCheckBlacklist
