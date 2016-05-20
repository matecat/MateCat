// Used for calculations
var defaultWidth = 400;
var defaultColors = {
    success: {
        rgb: '94, 164, 0',
        hex: '#5ea400'
    },
    error: {
        rgb: '236, 61, 61',
        hex: '#ec3d3d'
    },
    warning: {
        rgb: '235, 173, 23',
        hex: '#ebad1a'
    },
    info: {
        rgb: '54, 156, 199',
        hex: '#369cc7'
    }
};

var defaultShadowOpacity = '0.9';

var STYLE = {
    Containers: {
        DefaultStyle: {
            fontFamily: 'inherit',
            position: 'fixed',
            width: defaultWidth,
            padding: '0 10px 10px 10px',
            zIndex: 9998,
            WebkitBoxSizing: 'border-box',
            MozBoxSizing: 'border-box',
            boxSizing: 'border-box',
            height: 'auto'
        },
        tl: {
            top: '60px',
            bottom: 'auto',
            left: '0px',
            right: 'auto'
        },

        tr: {
            top: '60px',
            bottom: 'auto',
            left: 'auto',
            right: '0px'
        },

        tc: {
            top: '60px',
            bottom: 'auto',
            margin: '0 auto',
            left: '50%',
            marginLeft: -(defaultWidth / 2)
        },

        bl: {
            top: 'auto',
            bottom: '30px',
            left: '0px',
            right: 'auto'
        },

        br: {
            top: 'auto',
            bottom: '30px',
            left: 'auto',
            right: '0px'
        },

        bc: {
            top: 'auto',
            bottom: '30px',
            margin: '0 auto',
            left: '50%',
            marginLeft: -(defaultWidth / 2)
        }
    },
    NotificationItem: {
        DefaultStyle: {
            position: 'relative',
            width: '100%',
            cursor: 'pointer',
            borderRadius: '2px',
            fontSize: '15px',
            margin: '10px 0 0',
            padding: '10px',
            display: 'block',
            WebkitBoxSizing: 'border-box',
            MozBoxSizing: 'border-box',
            boxSizing: 'border-box',
            opacity: 0,
            transition: '0.3s ease-in-out'
        },
        success: {
            backgroundColor: '#f0f5ea',
            color: '#4b583a',
            WebkitBoxShadow: '0 0 1px rgba(' + defaultColors.success.rgb + ',' + defaultShadowOpacity + ')',
            MozBoxShadow: '0 0 1px rgba(' + defaultColors.success.rgb + ',' + defaultShadowOpacity + ')',
            boxShadow: '0 0 1px rgba(' + defaultColors.success.rgb + ',' + defaultShadowOpacity + ')'
        },

        error: {
            backgroundColor: '#f4e9e9',
            color: '#412f2f',
            WebkitBoxShadow: '0 0 1px rgba(' + defaultColors.error.rgb + ',' + defaultShadowOpacity + ')',
            MozBoxShadow: '0 0 1px rgba(' + defaultColors.error.rgb + ',' + defaultShadowOpacity + ')',
            boxShadow: '0 0 1px rgba(' + defaultColors.error.rgb + ',' + defaultShadowOpacity + ')'
        },

        warning: {
            backgroundColor: '#f9f6f0',
            color: '#5a5343',
            WebkitBoxShadow: '0 0 1px rgba(' + defaultColors.warning.rgb + ',' + defaultShadowOpacity + ')',
            MozBoxShadow: '0 0 1px rgba(' + defaultColors.warning.rgb + ',' + defaultShadowOpacity + ')',
            boxShadow: '0 0 1px rgba(' + defaultColors.warning.rgb + ',' + defaultShadowOpacity + ')'
        },

        info: {
            backgroundColor: '#e8f0f4',
            color: '#41555d',
            WebkitBoxShadow: '0 0 1px rgba(' + defaultColors.info.rgb + ',' + defaultShadowOpacity + ')',
            MozBoxShadow: '0 0 1px rgba(' + defaultColors.info.rgb + ',' + defaultShadowOpacity + ')',
            boxShadow: '0 0 1px rgba(' + defaultColors.info.rgb + ',' + defaultShadowOpacity + ')'
        }
    },
    Title: {
        DefaultStyle: {
            fontSize: '17px',
            margin: '0 0 5px 0',
            padding: 0,
            fontWeight: 'bold'
        },

        success: {
            color: defaultColors.success.hex
        },

        error: {
            color: defaultColors.error.hex
        },

        warning: {
            color: defaultColors.warning.hex
        },

        info: {
            color: defaultColors.info.hex
        }

    },
    Dismiss: {
        DefaultStyle: {
            fontSize: '13px',
            position: 'absolute',
            top: '4px',
            right: '5px',
            lineHeight: '15px',
            backgroundColor: '#ADADB1',
            color: '#ffffff',
            borderRadius: '50%',
            width: '14px',
            height: '14px',
            fontWeight: 'bold',
            textAlign: 'center'
        }

    }
};
export default STYLE ;
