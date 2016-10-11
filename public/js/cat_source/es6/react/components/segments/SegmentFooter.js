/**
 * React Component .

 */
var React = require('react');
var SegmentConstants = require('../../constants/SegmentConstants');
var SegmentStore = require('../../stores/SegmentStore');
var SegmentTabMatches = require('./SegmentFooterTabMatches').default;
var SegmentTabConcordance = require('./SegmentFooterTabConcordance').default;
var SegmentTabGlossary = require('./SegmentFooterTabGlossary').default;
var SegmentTabConflicts = require('./SegmentFooterTabConflicts').default;
class SegmentFooter extends React.Component {

    constructor(props) {
        super(props);
        var tMLabel;
        if ( config.mt_enabled ) {
            tMLabel =  'Translation Matches';
        }
        else {
            tMLabel = 'Translation Matches' + " (No MT) ";
        }
        this.tabs = {
            matches: {
                label: tMLabel,
                code : 'tm',
                tab_class : 'matches',
                enabled: false,
                visible: false,
                open: false,
                elements: []
            },
            concordances: {
                label: 'Concordance',
                code : 'cc',
                tab_class : 'concordances',
                enabled : false,
                visible : false,
                open : false,
                elements : []
            },
            glossary: {
                label : 'Glossary',
                code : 'gl',
                tab_class : 'glossary',
                enabled : false,
                visible : false,
                open : false,
                elements : []
            },
            alternatives: {
                label : 'Translation conflicts',
                code : 'al',
                tab_class : 'alternatives',
                enabled : false,
                visible : false,
                open : false,
                elements : []
            }
        };
        this.state = {
            tabs: {},
        };
        this.registerTab = this.registerTab.bind(this);
        this.createFooter = this.createFooter.bind(this);
        this.getTabContainer = this.getTabContainer.bind(this);
        this.changeTab = this.changeTab.bind(this);
    }

    registerTab(tabName, visible, open) {
        this.tabs[tabName].visible = visible;
        // Ensure there is only one tab open.
        if (open === true) {
            for (var key in this.tabs) {
                this.tabs[key].open = false;
            }
        }
        this.tabs[tabName].open = open;
        this.tabs[tabName].enabled = true;
    }

    createFooter(sid) {
        if (this.props.sid == sid) {
            this.setState({
                tabs: this.tabs
            });
        }
    }

    getTabContainer(tab, active_class) {
        var open_class = (active_class == 'active') ? 'open' : '';
        switch(tab.code) {
            case 'tm':
                return <SegmentTabMatches
                    key={"container_" + tab.code}
                    code = {tab.code}
                    active_class = {open_class}
                    tab_class = {tab.tab_class}
                    id_segment = {this.props.sid}/>;
                break;
            case 'cc':
                return <SegmentTabConcordance
                    key={"container_" + tab.code}
                    code = {tab.code}
                    active_class = {open_class}
                    tab_class = {tab.tab_class}
                    id_segment = {this.props.sid}/>;
                break;
            case 'gl':
                return <SegmentTabGlossary
                    key={"container_" + tab.code}
                    code = {tab.code}
                    active_class = {open_class}
                    tab_class = {tab.tab_class}
                    id_segment = {this.props.sid}/>;
                break;
            case 'al':
                return <SegmentTabConflicts
                    key={"container_" + tab.code}
                    code = {tab.code}
                    active_class = {open_class}
                    tab_class = {tab.tab_class}
                    id_segment = {this.props.sid}/>;
                break;
            default:
                return ''
        }
    }

    changeTab(e) {
        e.preventDefault();

        var section = $(e.target).closest('section');
        var tab_class = $(e.target).closest('li').data('tab-class');
        var code = $(e.target).closest('li').data('code');
        var li = $(e.target).closest('li');

        $('.sub-editor', section).removeClass('open');
        $('.' + tab_class, section).addClass('open');

        $('.tab-switcher', section).removeClass('active');
        li.addClass('active');

        var item = _
            .chain(this.tabs)
            .select(function(item) { return item.code == code })
            .first()
            .value();

        if ( typeof item.on_activation  == 'function' ) {
            item.on_activation( $(this.footerRef) ) ;
        }
    }
    componentDidMount() {
        console.log("Mount SegmentFooter" + this.props.sid);
        SegmentStore.addListener(SegmentConstants.CREATE_FOOTER, this.createFooter);
        SegmentStore.addListener(SegmentConstants.REGISTER_TAB, this.registerTab);
    }

    componentWillUnmount() {
        console.log("Unmount SegmentFooter" + this.props.sid);
        SegmentStore.removeListener(SegmentConstants.CREATE_FOOTER, this.createFooter);
        SegmentStore.removeListener(SegmentConstants.REGISTER_TAB, this.registerTab);
    }

    componentWillMount() {

    }
    allowHTML(string) {
        return { __html: string };
    }

    render() {
        var labels = [];
        var containers = [];
        var self = this;
        for ( var key in this.state.tabs ) {
            var tab = this.state.tabs[key];
            if ( tab.enabled) {
                var hidden_class = tab.visible ? '' : 'hide';
                var active_class = tab.open ? 'active' : '';
                var label = <li
                    key={ tab.code }
                    className={ hidden_class + " " + active_class + " tab-switcher tab-switcher-" + tab.code }
                    id={"segment-" + this.props.sid + tab.code}
                    data-tab-class={ tab.tab_class }
                    data-code={ tab.code }
                    onClick={ self.changeTab }>
                    <a tabIndex="-1" href="#">{ tab.label }
                        <span className={"number"}/>
                    </a>
                </li>;
                labels.push(label);
                var container = self.getTabContainer(tab, active_class);
                containers.push(container);
            }
        }

        return (
            <div className="footer toggle"
                 ref={(ref) => this.footerRef = ref}>
                <ul className="submenu">
                    <li className="footerSwitcher"/>
                    {labels}
                </ul>
                {containers}
                <div className="addtmx-tr white-tx">
                    <a className="open-popup-addtm-tr">Add private resources</a>
                </div>
            </div>
        )
    }
}

export default SegmentFooter;
