function addRow() {
          
    var myName = document.getElementById("name");
    var key = document.getElementById("key");
    var description = document.getElementById("description");
    var table = document.getElementById("sort");
 
    var rowCount = table.rows.length;
    var row = table.insertRow(rowCount);
    row.insertCell(0).innerHTML= '<span class="index newrow text-center">*New*</span>';
    row.insertCell(1).innerHTML= myName.value;
    row.insertCell(2).innerHTML= key.value;
    row.insertCell(3).innerHTML= description.value;
    row.insertCell(4).innerHTML= 'Lang pair';
    row.insertCell(5).innerHTML= '<span class="newrow text-center"><input type="checkbox" /></span>';
    row.insertCell(6).innerHTML= '<span class="newrow text-center"><input type="checkbox" /></span>';
}
 

 
function addTable() {
      
           
    for (var i=0; i<3; i++){
       var tr = document.createElement('TR');
       tableBody.appendChild(tr);
	   
       for (var j=0; j<4; j++){
           var td = document.createElement('TD');
           td.width='75';
           td.appendChild(document.createTextNode("Cell " + i + "," + j));
           tr.appendChild(td);
       }
	   
    }
	
}