// var SearchInput = require("./SearchInput").default;
// var FilterProjects = require("./FilterProjects").default;

var SubHeader = require("./SubHeader").default;

class Header extends React.Component {
    constructor (props) {

        super(props);
        this.imgStyle = {
            float: 'left',
            border: '0',
            marginTop: '10px',
            background: 'url(http://matecat.dev/public/img/logo.png) 0px 2px no-repeat',
            width: '145px',
            height: '31px',
            backgroundSize: '130px 26px',
        }
    }
    componentDidMount () {
        $('.team-dropdown').dropdown();
        $('.team-filter.modal')
            .modal('setting', 'transition', 'fade')
            .modal('attach events', '.team-filter.button', 'show')
        ;
    }


    render () {
        return <section className="nav-mc-bar">
                    <nav role="navigation">
                        <div className="nav-wrapper">
                            <div className="container-fluid">
                                <div className="row">
                                        <a href="/" className="logo logo-col"/>
                                    
                                
                                    {/*<div className="col l4 offset-l4 m4 offset-m4">
                                        <SearchInput
                                            closeSearchCallback={this.props.closeSearchCallback}
                                            onChange={this.props.searchFn}/>
                                    </div>
                                    <div className="col l2 m2 s4 right right-60">
                                        <FilterProjects
                                            filterFunction={this.props.filterFunction}/>
                                    </div>*/}

                                    <div className="col m2 offset-m8">
                                        <div className="ui fluid selection dropdown team-dropdown top-5">
                                            <input type="hidden" name="gender" />
                                            <i className="dropdown icon"></i>
                                            <div className="default text">Gender</div>
                                            <div className="menu">
                                                <div className="header">Create Workspace
                                                    <a className="team-filter button show" href="#">
                                                        <i className="icon-plus3 right"></i>
                                                    </a>
                                                </div>
                                                <div className="item" data-value="male" data-text="Personal">Personal
                                                    <a className="team-filter right button show" href="#">
                                                        <i className="icon-more_vert "></i>
                                                    </a>
                                                </div>    
                                                <div className="item" data-value="male" data-text="Ebay">Ebay
                                                    <a className="team-filter button show right" href="#">
                                                        <i className="icon-more_vert"></i>
                                                    </a>
                                                </div>
                                                <div className="item" data-value="female" data-text="MSC">MSC
                                                    <a className="team-filter button show right" href="#"><i className="icon-more_vert"></i></a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                         
                                        

                                    <div className="col l2 right profile-area">
                                        <ul className="right">
                                            <li>
                                                <a href="" className="right waves-effect waves-light">
                                                    <span id="nome-cognome" className="hide-on-med-and-down">Nome Cognome </span>
                                                    <button className="btn-floating btn-flat waves-effect waves-dark z-depth-0 center hoverable">RS</button>
                                                </a>
                                            </li>
                                        </ul>
                                    </div>
                                    
                                </div>
                            </div>
                        </div>
                    </nav>


                    

                    <div className="ui modal team-filter">
                        <i className="icon-cancel"></i>
                        <div className="header">
                            Create new team
                        </div>
                        <div className="image content">
                            <div className="description">
                                <form className="ui form">
                                    <div className="field">
                                        <label>First Name</label>
                                        <input type="text" name="Project Name" placeholder="Translated Team es." />
                                    </div>
                                    <div className="field">
                                        <label>Last Name</label>
                                        <input type="email" name="email" placeholder="example@mail.com" />
                                    </div>
                                    <div className="field">
                                        <div className="ui checkbox">
                                          <input type="checkbox" tabindex="0" className="hidden" />
                                          <label>I agree to the Terms and Conditions</label>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                        <div className="actions">
                            <div className="ui black deny button">
                                Nope
                            </div>
                            <div className="ui positive right labeled icon button">
                                Yep ;)
                                <i className="checkmark icon"></i>
                            </div>
                        </div>
                    </div>






                    <SubHeader
                        filterFunction={this.props.filterFunction}
                        />
                </section>;
    }
}
export default Header ;