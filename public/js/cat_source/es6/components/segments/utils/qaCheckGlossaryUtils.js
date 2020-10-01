import TextUtils from '../../../utils/textUtils';

const QaCheckGlossary = {
    matchRegExp: '\\b(%s)\\b',

    regExpFlags: 'g',

    enabled() {
        return config.qa_check_glossary_enabled;
    },
    update(glossary) {
        var mapped = {};

        // group by segment id
        _.each(glossary.matches, function (item) {
            mapped[item.id_segment] ? null : (mapped[item.id_segment] = []);
            mapped[item.id_segment].push(item.data);
        });
        _.forOwn(mapped, function (value, key) {
            SegmentActions.addQaCheckMatches(key, value);
        });
    },
    markGlossaryUnusedMatches(segmentSource, unusedMatches) {
        // read the segment source, find with a regexp and replace with a span

        if (_.isUndefined(unusedMatches) || unusedMatches.length === 0) {
            return;
        }
        // container.find('.inside-attribute').remove();
        // var newHTML = TextUtils.htmlEncode(container.text());
        var newHTML = segmentSource;

        //Replace ph tags
        var base64Tags = [];
        newHTML = newHTML.replace(/&lt;ph.*?equiv-text="base64:(.*?)".*?\/&gt;/gi, function (match, text) {
            base64Tags.push(match);
            return '###' + text + '###';
        });
        unusedMatches = unusedMatches.sort(function (a, b) {
            return b.raw_segment.length - a.raw_segment.length;
        });
        $.each(unusedMatches, function (index) {
            var value = this.raw_segment ? this.raw_segment : this.translation;
            value = TextUtils.escapeRegExp(value);
            value = value.replace(
                / /g,
                '(?: *</*(?:mark)*(?:span *)*(?: (data-id="(.*?)" )*class="(unusedGlossaryTerm)*(inGlossary)*")*> *)* *'
            );
            var re = new RegExp(sprintf(QaCheckGlossary.matchRegExp, value), QaCheckGlossary.regExpFlags);

            var check = re.test('<span class="unusedGlossaryTerm">$1</span>');
            if (!check) {
                newHTML = newHTML.replace(re, '<span class="unusedGlossaryTerm">$1</span>');
            } else {
                re = new RegExp(sprintf('\\s\\b(%s)\\s\\b', value), QaCheckGlossary.regExpFlags);
                newHTML = newHTML.replace(re, ' <span class="unusedGlossaryTerm">$1</span> ');
            }
        });
        newHTML = newHTML.replace(/###(.*?)###/gi, function (match, text) {
            var tag = base64Tags.shift();
            return tag;
        });

        return newHTML;
    },

    powerTipFn(item, unusedMatches) {
        var el = $(item);

        _.chain(unusedMatches)
            .filter(function findMatch(match) {
                return match.id == el.data('id');
            })
            .first()
            .value();
        el.powerTip({ placement: 's' });
        el.data({ powertipjq: $('<div class="unusedGlossaryTip" style="padding: 4px;">Unused glossary term</div>') });
    },
};

module.exports = QaCheckGlossary;
