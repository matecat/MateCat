
import React from 'react';
import ReactDOM from 'react-dom';
import TestUtils from 'react-addons-test-utils';
import { mount, shallow } from 'enzyme';
import SegmentSource from '../cat_source/es6/react/components/segments/SegmentSource';
var expect = require('expect.js');
var assert = chai.assert;
var sinon = require('sinon');
var segment;
describe('SegmentSource Component', () => {
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

    it('SegmentTarget is rendered right', () => {
        var decodeTextMock = function (seg, text) {
            return text;
        };
        var fnMock = function () {
          return true;
        };
        var component = shallow(
            <SegmentSource
                segment={segment}
                decodeTextFn={decodeTextMock}
                afterRenderOrUpdate={fnMock}
                beforeRenderOrUpdate={fnMock}
            />
        );

        expect(component.find('.source')).to.have.length(1);
    });

    it('SegmentSource: replace source', () => {
        var decodeTextMock = function (seg, text) {
            return text;
        };
        var fnMock = function () {
            return true;
        };

        var component = mount(
            <SegmentSource
                segment={segment}
                decodeTextFn={decodeTextMock}
                afterRenderOrUpdate={fnMock}
                beforeRenderOrUpdate={fnMock}
            />
        );

        expect(component.find('.source')).to.have.length(1);
        expect(component.find('.source').text()).to.equal(segment.segment);

        component.setState({
            source: "New source"
        });

        expect(component.find('.source').text()).to.equal("New source");

    });
});