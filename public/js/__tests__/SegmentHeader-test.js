
import React from 'react';
import ReactDOM from 'react-dom';
import TestUtils from 'react-addons-test-utils';
import { mount, shallow } from 'enzyme';


import SegmentHeader from '../cat_source/es6/react/components/segments/SegmentHeader';

var segment;
var expect = require('expect.js');
var assert = chai.assert;
var sinon = require('sinon');
var component;

describe('SegmentHeader Component', () => {

    beforeEach(() => {

    });

    afterEach( () => {
        ReactDOM.unmountComponentAtNode(ReactDOM.findDOMNode(component).parentNode);
    });

    it('SegmentHeader is rendered right', () => {
        component = TestUtils.renderIntoDocument(
            <SegmentHeader sid="1" autopropagated="false"/>
        );

        var segmentHeader = ReactDOM.findDOMNode(component);
        assert.equal(segmentHeader.id, "segment-1-header");

    });

    xit('', () => {


        component = TestUtils.renderIntoDocument(
            <SegmentHeader sid="1" autopropagated="false"/>
        );


    });


});