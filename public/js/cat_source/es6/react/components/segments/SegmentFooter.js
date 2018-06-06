/**
 * React Component .

 */
let React = require('react');
let SegmentConstants = require('../../constants/SegmentConstants');
let SegmentStore = require('../../stores/SegmentStore');
// let SegmentTabMatches = require('./SegmentFooterTabMatches').default;
let SegmentTabConcordance = require('./SegmentFooterTabConcordance').default;
let SegmentTabGlossary = require('./SegmentFooterTabGlossary').default;
let SegmentTabConflicts = require('./SegmentFooterTabConflicts').default;
let SegmentTabMessages = require('./SegmentFooterTabMessages').default;
let SegmentTabRevise = require('./SegmentFooterTabRevise').default;
class SegmentFooter extends React.Component {

    constructor(props) {
        super(props);
        let tMLabel;
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
                label: 'TM Search',
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
            },
            messages: {
                label : 'Messages',
                code : 'notes',
                tab_class : 'segment-notes',
                enabled : !!(this.props.segment.notes && this.props.segment.notes.length > 0),
                visible : !!(this.props.segment.notes && this.props.segment.notes.length > 0),
                open : !!(this.props.segment.notes && this.props.segment.notes.length > 0),
                elements : []
            },
            review : {
                label : 'Revise',
                code : 'review',
                tab_class : 'review',
                enabled : false,
                visible : false,
                open : false,
                elements : []
            }
        };

        this.state = {
            tabs: {}
        };
        this.registerTab = this.registerTab.bind(this);
        this.createFooter = this.createFooter.bind(this);
        this.getTabContainer = this.getTabContainer.bind(this);
        this.changeTab = this.changeTab.bind(this);
        this.openTab = this.openTab.bind(this);
        this.addTabIndex = this.addTabIndex.bind(this);
    }

    registerTab(tabName, visible, open) {
        this.tabs[tabName].visible = visible;
        // Ensure there is only one tab open.
        if (open === true) {
            for (let key in this.tabs) {
                this.tabs[key].open = false;
            }
        }
        this.tabs[tabName].open = this.state.hideMatches ? false : open;
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
        let open_class = (active_class == 'active') ? 'open' : '';
        switch(tab.code) {
            case 'tm':
                return <SegmentTabMatches
                    key={"container_" + tab.code}
                    code = {tab.code}
                    active_class = {open_class}
                    tab_class = {tab.tab_class}
                    id_segment = {this.props.sid}
                    segment = {this.props.segment}
                />;
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
            case 'notes':
                return <SegmentTabMessages
                    key={"container_" + tab.code}
                    code = {tab.code}
                    active_class = {open_class}
                    tab_class = {tab.tab_class}
                    id_segment = {this.props.sid}
                    notes={this.props.segment.notes}/>;
                break;
            case 'review':
                return <SegmentTabRevise
                    key={"container_" + tab.code}
                    code = {tab.code}
                    active_class = {open_class}
                    tab_class = {tab.tab_class}
                    id_segment = {this.props.sid}
                    translation={this.props.segment.translation}
                    segment={this.props.segment}
                    decodeTextFn={this.props.decodeTextFn}/>;
                break;
            default:
                return ''
        }
    }
    closeAllTabs() {
        let tabs = jQuery.extend(true, {}, this.state.tabs);
        for ( let item in tabs ) {
            tabs[item].open = false
        }
        this.setState({
            tabs: tabs
        });
    }

    openTab(sid, tabCode) {
        // Todo: refactoring, no jquery
        if (this.props.sid === sid ) {
            this.changeTab(tabCode, true);
        }
    }

    setHideMatchesCookie(hideMatches) {
        let cookieName = (config.isReview)? 'hideMatchesReview' : 'hideMatches';
        Cookies.set(cookieName + '-' + config.id_job, hideMatches, { expires: 30 });
    }

    getHideMatchesCookie() {
        let cookieName = (config.isReview)? 'hideMatchesReview' : 'hideMatches';
        if( !_.isUndefined(Cookies.get(cookieName + '-' + config.id_job))) {
            if (Cookies.get(cookieName + '-' + config.id_job) == "true") {
                return true;
            } else {
                return false;
            }
        }
        return true;
    }

    changeTab(tabName, forceOpen) {
        if (event) {
            event.preventDefault();
        }
        forceOpen = forceOpen ? forceOpen : false;
        let tabs = jQuery.extend(true, {}, this.state.tabs);
        let tab = jQuery.extend(true, {}, tabs[tabName]);
        //Close all tabs
        for ( let item in tabs ) {
            tabs[item].open = false
        }
        let hideMatches = this.getHideMatchesCookie();
        if (tab.open && !forceOpen && !hideMatches) {
            tab.open = false;
            this.setHideMatchesCookie(true);
        } else {
            tab.open = true;
            tab.visible = true;
            this.setHideMatchesCookie(false);
        }
        tabs[tabName] = tab;

        this.setState({
            tabs: tabs
        });
    }
    componentDidMount() {
        SegmentStore.addListener(SegmentConstants.CREATE_FOOTER, this.createFooter);
        SegmentStore.addListener(SegmentConstants.REGISTER_TAB, this.registerTab);
        SegmentStore.addListener(SegmentConstants.OPEN_TAB, this.openTab);
        SegmentStore.addListener(SegmentConstants.ADD_TAB_INDEX, this.addTabIndex);
        SegmentStore.addListener(SegmentConstants.CLOSE_TABS, this.closeAllTabs);
    }

    componentWillUnmount() {
        SegmentStore.removeListener(SegmentConstants.CREATE_FOOTER, this.createFooter);
        SegmentStore.removeListener(SegmentConstants.REGISTER_TAB, this.registerTab);
        SegmentStore.removeListener(SegmentConstants.OPEN_TAB, this.openTab);
        SegmentStore.removeListener(SegmentConstants.ADD_TAB_INDEX, this.addTabIndex);
        SegmentStore.removeListener(SegmentConstants.CLOSE_TABS, this.closeAllTabs);
    }

    componentWillMount() {

    }

    allowHTML(string) {
        return { __html: string };
    }

    addTabIndex(sid, tab, index) {
        if (this.props.sid == sid) {
            let tabs = $.extend({}, {}, this.state.tabs);
            if (tabs[tab]) {
                tabs[tab].index = index;
                this.setState({
                    tabs: tabs
                })
            }
        }
    }

    render() {
        let labels = [];
        let containers = [];
        let self = this;
        let hideMatches = this.getHideMatchesCookie();
        for ( let key in this.state.tabs ) {
            let tab = this.state.tabs[key];
            if ( tab.enabled) {
                let hidden_class = (tab.visible) ? '' : 'hide';
                let active_class = (tab.open && !hideMatches) ? 'active' : '';
                let label = <li
                    key={ tab.code }
                    ref={(elem)=> this[tab.code] = elem}
                    className={ hidden_class + " " + active_class + " tab-switcher tab-switcher-" + tab.code }
                    id={"segment-" + this.props.sid + tab.code}
                    data-tab-class={ tab.tab_class }
                    data-code={ tab.code }
                    onClick={ self.changeTab.bind(this, key, false) }>
                    <a tabIndex="-1" >{ tab.label }
                        <span className="number">{(tab.index) ? ' (' + tab.index + ')' : ''}</span>
                    </a>
                </li>;
                labels.push(label);
                let container = self.getTabContainer(tab, active_class);
                containers.push(container);
            }
        }

        return (
            <div className="footer toggle"
                 ref={(ref) => this.footerRef = ref}>
                <ul className="submenu">
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
