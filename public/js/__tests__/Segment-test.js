
import React from 'react';
import ReactDOM from 'react-dom';
import TestUtils from 'react-addons-test-utils';
import { mount, shallow } from 'enzyme';


import Segment from '../cat_source/es6/react/components/segments/Segment';
import SegmentHeader from '../cat_source/es6/react/components/segments/SegmentHeader';
import SegmentBody from '../cat_source/es6/react/components/segments/SegmentBody';
import SegmentFooter from '../cat_source/es6/react/components/segments/SegmentFooter';


var expect = require('expect.js');
var assert = chai.assert;
var sinon = require('sinon');

var segment;
describe('Segment Component', () => {

    beforeEach(() => {

        segment = {
            "last_opened_segment":"61079",
            "sid":"60984",
            "segment":"INDIETRO",
            "segment_hash":"0a7e4ea10d93b636d9de15132300870c",
            "raw_word_count":"1.00",
            "internal_id":"P147242AB-tu19",
            "translation":"",
            "version":null,
            "original_target_provied":"0",
            "status":"NEW",
            "time_to_edit":"0",
            "xliff_ext_prec_tags":"",
            "xliff_ext_succ_tags":"",
            "warning":"0",
            "suggestion_match":"85",
            "source_chunk_lengths":[],
            "target_chunk_lengths":{
                "len":[0],
                "statuses":["DRAFT"]
            },
            "readonly":"false",
            "autopropagated_from":"0",
            "repetitions_in_chunk":"1",
            "has_reference":"false",
            "parsed_time_to_edit":["00","00","00","00"],
            "notes":null
        }
    });

    it('Segment is rendered right', () => {
        var component = shallow(
            <Segment
                key={segment.sid}
                segment={segment}
                timeToEdit="0"
                fid="1"
                isReviewImproved={false}
                enableTagProjection={false}
            />
        );

        expect(component.find('section')).to.have.length(1);
        expect(component.find('section').prop('id')).to.equal("segment-" + segment.sid);
        expect(component.find('section').prop('className')).to.not.contain("readonly");
        expect(component.find('section').prop('className')).to.contain("status-new");
        expect(component.find('section').prop('className')).to.not.contain("splitStart");
        expect(component.find('section').prop('className')).to.not.contain("splitEnd");
        expect(component.find('section').prop('className')).to.not.contain("splitInner");
        expect(component.find('section').prop('className')).to.not.contain("enableTP");
        expect(component.find('section').prop('className')).to.not.contain("reviewImproved");

        expect(component.find('.txt').text()).to.equal(segment.sid);
        expect(component.find('.actions .split')).to.have.length(1);

        expect(component.find(SegmentHeader)).to.have.length(1);
        expect(component.find(SegmentHeader).prop('sid')).to.equal(segment.sid);
        expect(component.find(SegmentHeader).prop('autopropagated')).to.equal(false);

        expect(component.find(SegmentBody)).to.have.length(1);
        expect(component.find(SegmentBody).prop('segment')).to.equal(segment);
        expect(component.find(SegmentBody).prop('isReviewImproved')).to.equal(false);

        expect(component.find(SegmentFooter)).to.have.length(1);
        expect(component.find(SegmentFooter).prop('sid')).to.equal(segment.sid);
    });
});