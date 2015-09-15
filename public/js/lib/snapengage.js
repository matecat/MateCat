/* 
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */


(function() {
    var se = document.createElement('script');
    se.type = 'text/javascript';
    se.async = true;
    se.src = '//commondatastorage.googleapis.com/code.snapengage.com/js/a061720b-c70b-4aa5-b24a-f77f78101cd4.js';
    var done = false;
    se.onload = se.onreadystatechange = function() {
     if (!done && (!this.readyState || this.readyState === 'loaded' || this.readyState === 'complete')) {
     done = true;
        SnapABug.showScreenshotOption(true);
        SnapABug.allowScreenshot(true);
        SnapABug.setLocale("en");
     // Place your SnapEngage JS API code below
     //SnapABug.openProactiveChat(true, true); // Example: open the proactive chat on load
     }
     };
    var s = document.getElementsByTagName('script')[0];
    s.parentNode.insertBefore(se, s);
})();


