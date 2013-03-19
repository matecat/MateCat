function fitText(container,child,limitHeight){
    if(container.height() < (limitHeight+1)) return;
    txt = child.text();
    var name = txt;
    var ext = '';
    if(txt.split('.').length > 1) {
        var extension = txt.split('.')[txt.split('.').length-1];
        name = txt.replace('.'+extension,'');
        ext = '.' + extension;
    }
    firstHalf = name.substr(0 , Math.ceil(name.length/2));
    secondHalf = name.replace(firstHalf,'');
    child.text(firstHalf.substr(0,firstHalf.length-1)+'[...]'+secondHalf.substr(1)+ext);
    while (container.height() > limitHeight) {
        child.text(child.text().replace(/(.)\[\.\.\.\](.)/,'[...]'));
    }
}