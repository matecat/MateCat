var SearchInput = require("./SearchInput").default;
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
    }


    render () {
        return <nav>
                <div className="nav-wrapper">
                    <a href="#!" className="brand-logo">Logo</a>

                    <SearchInput
                        closeSearchCallback={this.props.closeSearchCallback}
                        onChange={this.props.searchFn}/>

                    <ul className="side-nav" id="mobile-demo">
                        <li><a href="sass.html">Sass</a></li>
                        <li><a href="badges.html">Components</a></li>
                        <li><a href="collapsible.html">Javascript</a></li>
                        <li><a href="mobile.html">Mobile</a></li>
                    </ul>
                </div>
            </nav>
    }
}
export default Header ;