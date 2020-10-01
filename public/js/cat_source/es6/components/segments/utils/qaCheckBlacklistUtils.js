import TextUtils from '../../../utils/textUtils';

const QaCheckBlacklist = {
    enabled() {
        return config.qa_check_blacklist_enabled;
    },

    markBlacklistItemsInSegment(text, matched_words) {
        if (matched_words.length) {
            var newHTML = text;

            $(matched_words).each(function (index, value) {
                value = TextUtils.escapeRegExp(value);
                var re = new RegExp('\\b(' + value + ')\\b', 'g');
                newHTML = newHTML.replace(re, '<span class="blacklistItem">$1</span>');
            });
            text = newHTML;
        }
        return text;
    },
    update(blacklist) {
        var mapped = {};

        // group by segment id
        _.each(blacklist.matches, function (item) {
            mapped[item.id_segment] ? null : (mapped[item.id_segment] = []);
            mapped[item.id_segment].push({ severity: item.severity, match: item.data.match });
        });

        _.each(Object.keys(mapped), function (item, index) {
            var matched_words = _.chain(mapped[item])
                .map(function (match) {
                    return match.match;
                })
                .uniq()
                .value();
            SegmentActions.addQaBlacklistMatches(item, matched_words);

            // var editarea = segment.el.find(  UI.targetContainerSelector() ) ;
            // updateBlacklistItemsInSegment( editarea, matched_words ) ;
        });
    },

    powerTipFn(item) {
        $(item).powerTip({
            placement: 's',
        });
        $(item).data({ powertipjq: $('<div class="blacklistTooltip">Blacklisted term</div>') });
    },
};

module.exports = QaCheckBlacklist;
